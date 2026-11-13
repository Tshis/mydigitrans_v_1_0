<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\admin\agency\BusDocumentData;
use App\Entity\Agency;
use App\Entity\Bus;
use App\Entity\BusDocument;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service de gestion du portefeuille administratif et légal des bus d'une agence.
 */
final class BusDocumentService
{
    private string $uploadDirectory;

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ) {
        $this->uploadDirectory = $projectDir . '/var/uploads/bus_documents';
    }

    /**
     * Hydrate un DTO BusDocumentData à partir d'un document existant (édition ou renouvellement).
     */
    public function createDtoFromDocument(BusDocument $doc, bool $isRenewal = false): BusDocumentData
    {
        $dto = new BusDocumentData();
        $dto->busCode         = $doc->getBus()?->getCode();
        $dto->type            = $doc->getType();
        $dto->referenceNumber = $doc->getReferenceNumber();

        if ($isRenewal) {
            $dto->issuedAt  = new \DateTimeImmutable('today');
            $dto->expiredAt = null;
        } else {
            $dto->issuedAt  = $doc->getIssuedAt();
            $dto->expiredAt = $doc->getExpiredAt();
        }

        return $dto;
    }

    /**
     * Liste les documents de l'agence avec filtres et statistiques en temps réel.
     *
     * @param array<string, mixed> $filters
     * @return array{
     *     documents: list<array<string, mixed>>,
     *     stats: array{valid: int, expiringSoon: int, expired: int},
     *     typesList: list<string>
     * }
     */
    public function listAgencyDocuments(Agency $agency, array $filters = []): array
    {
        $today = new \DateTimeImmutable('today');
        $warningThreshold = $today->modify('+30 days');

        /** @var list<BusDocument> $allDocs */
        $allDocs = $this->em->createQueryBuilder()
            ->select('d', 'b')
            ->from(BusDocument::class, 'd')
            ->join('d.bus', 'b')
            ->andWhere('b.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('d.expiredAt', 'ASC')
            ->getQuery()
            ->getResult();

        $stats = [
            'valid'        => 0,
            'expiringSoon' => 0,
            'expired'      => 0,
        ];

        $typesSet = [];
        foreach ($allDocs as $doc) {
            if (null !== $doc->getType() && '' !== $doc->getType()) {
                $typesSet[$doc->getType()] = true;
            }

            $expiredAt = $doc->getExpiredAt();
            if (null === $expiredAt || $expiredAt < $today) {
                ++$stats['expired'];
            } elseif ($expiredAt <= $warningThreshold) {
                ++$stats['expiringSoon'];
                ++$stats['valid'];
            } else {
                ++$stats['valid'];
            }
        }

        $typeFilter   = trim((string) ($filters['type'] ?? ''));
        $statusFilter = trim((string) ($filters['status'] ?? ''));

        $documents = [];
        foreach ($allDocs as $doc) {
            $expiredAt = $doc->getExpiredAt();
            $isExpired = null === $expiredAt || $expiredAt < $today;
            $status = $isExpired ? 'expired' : 'valid';

            if ('' !== $typeFilter && mb_strtolower((string) $doc->getType()) !== mb_strtolower($typeFilter)) {
                continue;
            }

            if ('' !== $statusFilter && $status !== $statusFilter) {
                continue;
            }

            $bus = $doc->getBus();
            $documents[] = [
                'code'            => (string) $doc->getCode(),
                'type'            => (string) $doc->getType(),
                'referenceNumber' => $doc->getReferenceNumber(),
                'issuedAt'        => $doc->getIssuedAt(),
                'expiredAt'       => $expiredAt,
                'status'          => $status,
                'url'             => $doc->getUrl(),
                'busCode'         => (string) $bus?->getCode(),
                'busPlate'        => (string) $bus?->getPlateNumber(),
                'busLabel'        => sprintf('%s %s (%s)', $bus?->getBrand(), $bus?->getModel(), $bus?->getPlateNumber()),
            ];
        }

        $typesList = array_keys($typesSet);
        sort($typesList);

        return [
            'documents' => $documents,
            'stats'     => $stats,
            'typesList' => $typesList,
        ];
    }

    /**
     * Retourne la liste des bus de l'agence pour alimenter les sélecteurs.
     *
     * @return list<Bus>
     */
    public function agencyBuses(Agency $agency): array
    {
        return $this->em->getRepository(Bus::class)->findBy(
            ['agency' => $agency],
            ['plateNumber' => 'ASC']
        );
    }

    public function findAgencyBus(Agency $agency, string $busCode): ?Bus
    {
        return $this->em->getRepository(Bus::class)->findOneBy([
            'agency' => $agency,
            'code'   => $busCode,
        ]);
    }

    public function findAgencyDocument(Agency $agency, string $docCode): ?BusDocument
    {
        return $this->em->createQueryBuilder()
            ->select('d', 'b')
            ->from(BusDocument::class, 'd')
            ->join('d.bus', 'b')
            ->andWhere('b.agency = :agency')
            ->andWhere('d.code = :code')
            ->setParameter('agency', $agency)
            ->setParameter('code', $docCode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Enregistre un nouveau document administratif à partir d'un DTO validé.
     *
     * @throws \DomainException
     */
    public function createDocument(User $actor, BusDocumentData $data): BusDocument
    {
        $agency = $actor->getAgency() ?? throw new \LogicException('Agence introuvable.');

        $bus = $this->findAgencyBus($agency, (string) $data->busCode);
        if (!$bus instanceof Bus) {
            throw new \DomainException('Le véhicule sélectionné est introuvable dans votre agence.');
        }

        $reference = trim((string) $data->referenceNumber);

        $doc = new BusDocument();
        $doc->setCode($this->generateUniqueCode())
            ->setBus($bus)
            ->setType(trim((string) $data->type))
            ->setReferenceNumber('' !== $reference ? $reference : null)
            ->setIssuedAt($data->issuedAt)
            ->setExpiredAt($data->expiredAt)
            ->setCreatedBy($actor)
            ->setUpdatedBy($actor);

        if ($data->documentFile instanceof UploadedFile) {
            $doc->setUrl($this->storeUploadedFile($data->documentFile));
        }

        $this->em->persist($doc);
        $this->em->flush();

        return $doc;
    }

    /**
     * Met à jour ou renouvelle un document existant à partir d'un DTO validé.
     *
     * @throws \DomainException
     */
    public function updateDocument(BusDocument $doc, User $actor, BusDocumentData $data, bool $isRenewal = false): void
    {
        if ($isRenewal && $data->expiredAt <= new \DateTimeImmutable('today')) {
            throw new \DomainException('Pour un renouvellement, la nouvelle date d\'expiration doit être postérieure à aujourd\'hui.');
        }

        if (!$isRenewal) {
            $doc->setType(trim((string) $data->type));
        }

        $reference = trim((string) $data->referenceNumber);

        $doc->setReferenceNumber('' !== $reference ? $reference : null)
            ->setIssuedAt($data->issuedAt)
            ->setExpiredAt($data->expiredAt)
            ->setUpdatedBy($actor)
            ->setUpdatedAt(new \DateTimeImmutable());

        if ($data->documentFile instanceof UploadedFile) {
            $oldUrl = $doc->getUrl();
            $doc->setUrl($this->storeUploadedFile($data->documentFile));
            $this->deleteStoredFile($oldUrl);
        }

        $this->em->flush();
    }

    /**
     * Retourne le chemin absolu du fichier stocké sur le disque.
     *
     * @throws \DomainException
     */
    public function resolveAbsoluteFilePath(BusDocument $doc): string
    {
        $relativeName = $doc->getUrl();
        if (null === $relativeName || '' === $relativeName) {
            throw new \DomainException('Aucun scan numérique n\'est attaché à ce document.');
        }

        $safeFilename = basename($relativeName);
        $fullPath = $this->uploadDirectory . '/' . $safeFilename;

        if (!is_file($fullPath)) {
            throw new \DomainException('Le fichier physique est introuvable sur le serveur.');
        }

        return $fullPath;
    }

    /**
     * @throws \DomainException
     */
    private function storeUploadedFile(UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new \DomainException('Le téléversement du fichier a échoué.');
        }

        if (!is_dir($this->uploadDirectory) && !mkdir($this->uploadDirectory, 0775, true) && !is_dir($this->uploadDirectory)) {
            throw new \RuntimeException('Impossible de créer le répertoire de stockage des documents.');
        }

        $extension = $file->guessExtension() ?? 'bin';
        $filename = sprintf('doc_%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(6)), $extension);

        try {
            $file->move($this->uploadDirectory, $filename);
        } catch (FileException) {
            throw new \DomainException('Erreur lors de l\'enregistrement du fichier sur le serveur.');
        }

        return $filename;
    }

    private function deleteStoredFile(?string $relativeName): void
    {
        if (null === $relativeName || '' === $relativeName) {
            return;
        }
        $fullPath = $this->uploadDirectory . '/' . basename($relativeName);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
    private function generateUniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';
        $repo = $this->em->getRepository(BusDocument::class);
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $code = 'DOC-';
            for ($i = 0; $i < 6; ++$i) {
                $code .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
            }
            if (null === $repo->findOneBy(['code' => $code])) {
                return $code;
            }
        }
        throw new \RuntimeException('Impossible de générer un code document unique.');
    }
}
