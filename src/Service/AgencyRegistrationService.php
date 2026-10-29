<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\public\agency\AgencyRegistrationData;
use App\Dto\public\agency\AgencyRegistrationResult;
use App\Entity\Agency;
use App\Entity\Branch;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRole;
use App\Enum\BranchStatus;
use App\Enum\Scope;
use App\Enum\UserType;
use App\Exception\AgencyRegistrationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Création d'une agence : la compagnie, son siège (succursale principale) et son responsable général,
 * dans une seule transaction.
 *
 * L'agence est créée ACTIVE (isActive sert ensuite à la suspendre, par exemple pour impayé) et NON VÉRIFIÉE :
 * isVerified est un indicateur interne, mis à true par la plateforme après contrôle des documents (RCCM, NIF).
 *
 * Droits du responsable : son rôle est une ligne UserRole (portée agence). Ses permissions viennent des lignes
 * RolePermission de ce rôle : les lignes sans agence sont les permissions par défaut, valables pour toutes les agences.
 * Elles doivent donc exister (seed) pour que le responsable puisse agir : cette inscription ne les crée pas.
 */
final class AgencyRegistrationService
{
    /**
     * Le MCD n'a pas de champ « siège » sur Branch : la succursale principale se reconnaît à ce code.
     */
    public const HEADQUARTERS_CODE = 'SIEGE';

    /** Rôle (portée « agence ») donné au responsable général : à créer dans la table role, avec ses permissions par défaut. */
    public const OWNER_ROLE_CODE = 'ROLE_AGENCY_ADMIN';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PartnerRegistrationService $partners,
    ) {}

    /**
     * @throws AgencyRegistrationException
     */
    public function register(AgencyRegistrationData $data, \DateTimeImmutable $now): AgencyRegistrationResult
    {
        return $this->em->wrapInTransaction(function () use ($data, $now): AgencyRegistrationResult {
            $agencyEmail = mb_strtolower(trim((string) $data->agencyEmail));
            $ownerEmail = mb_strtolower(trim((string) $data->ownerEmail));
            $registrationNumber = $this->nullIfBlank($data->registrationNumber);
            $taxNumber = $this->nullIfBlank($data->taxNumber);
            $city = trim((string) $data->mainCity);
            $country = trim((string) $data->country);

            // Toutes les erreurs sont rassemblées pour être montrées d'un coup, chacune sous son champ.
            $errors = [];

            $sponsor = null;
            if (null !== $this->nullIfBlank($data->sponsorToken)) {
                $sponsor = $this->partners->findSponsor((string) $data->sponsorToken);
                if (null === $sponsor) {
                    $errors['sponsorToken'] = 'Ce code de parrainage est invalide.';
                }
            }

            if ($this->exists(Agency::class, 'email', $agencyEmail)) {
                $errors['agencyEmail'] = 'Une agence utilise déjà cette adresse email.';
            }
            if (null !== $registrationNumber && $this->exists(Agency::class, 'registrationNumber', $registrationNumber)) {
                $errors['registrationNumber'] = 'Ce numéro RCCM est déjà enregistré.';
            }
            if (null !== $taxNumber && $this->exists(Agency::class, 'taxNumber', $taxNumber)) {
                $errors['taxNumber'] = 'Ce numéro NIF est déjà enregistré.';
            }
            if ($this->exists(User::class, 'email', $ownerEmail)) {
                $errors['ownerEmail'] = 'Cette adresse email est déjà utilisée par un compte.';
            }

            $role = $this->em->getRepository(Role::class)->findOneBy(['code' => self::OWNER_ROLE_CODE]);
            // Le rôle doit exister, être actif et être un rôle d'agence (jamais un rôle de la plateforme).
            if (!$role instanceof Role || !$role->isActive() || Scope::Agency !== $role->getScope()) {
                $errors[AgencyRegistrationException::GLOBAL] = 'La création d\'agences est momentanément indisponible. Merci de réessayer plus tard.';
            }

            if ([] !== $errors) {
                throw new AgencyRegistrationException($errors);
            }

            // 1. La compagnie
            $agency = (new Agency())
                ->setName(trim((string) $data->agencyName))
                ->setRegistrationNumber($registrationNumber)
                ->setTaxNumber($taxNumber)
                ->setPhone($this->normalizePhone((string) $data->agencyPhone))
                ->setEmail($agencyEmail)
                ->setAddress(trim((string) $data->address))
                ->setCountry($country)
                ->setCity($city)
                ->setSlug($this->uniqueSlug((string) $data->agencyName))
                ->setCode($this->uniqueAgencyCode($city))
                ->setIsActive(true)
                ->setIsVerified(false)
                ->setParent($sponsor);
            $this->em->persist($agency);

            // 2. Le siège : succursale principale, créée automatiquement avec l'agence
            $headquarters = (new Branch())
                ->setName('Siège - ' . $city)
                ->setAgency($agency)
                ->setCity($city)
                ->setCountry($country)
                ->setStatus(BranchStatus::Opened)
                ->setCode(self::HEADQUARTERS_CODE)
                ->setSlug($this->slug($city, 'siege'));

            $this->em->persist($headquarters);

            // 3. Le responsable général, rattaché au siège
            $owner = (new User())
                ->setFirstname(trim((string) $data->ownerFirstname))
                ->setLastname(trim((string) $data->ownerLastname))
                ->setPhone($this->normalizePhone((string) $data->ownerPhone))
                ->setEmail($ownerEmail)
                ->setUserType(UserType::Agency)
                ->setAgency($agency)
                ->setBranch($headquarters)
                ->setCode($this->uniqueUserCode())
                ->setIsActive(true)
                ->setIsLegalAccepted(true)
                ->setLegalAcceptedAt($now);
            $owner->setPassword($this->passwordHasher->hashPassword($owner, (string) $data->password));
            $this->em->persist($owner);

            // 4. Son rôle d'administrateur de l'agence (auto-attribution à l'inscription)
            $this->em->persist((new UserRole())->setUser($owner)->setRole($role)->setAgency($agency)->setAssignedBy($owner));

            return new AgencyRegistrationResult($agency, $headquarters, $owner);
        });
    }

    /**
     * @param class-string $class
     */
    private function exists(string $class, string $field, string $value): bool
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from($class, 'e')
            ->andWhere(sprintf('LOWER(e.%s) = :value', $field))
            ->setParameter('value', mb_strtolower($value))
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        return '' === $value ? null : $value;
    }

    private function normalizePhone(string $phone): string
    {
        return substr((string) preg_replace('/[^\d+]/', '', $phone), 0, 30);
    }

    private function slug(string $text, string $fallback): string
    {
        $slug = (new AsciiSlugger())->slug($text)->lower()->toString();

        return '' === $slug ? $fallback : substr($slug, 0, 150);
    }

    /**
     * Slug unique de l'agence : nom-de-la-compagnie, puis -2, -3... si déjà pris.
     */
    private function uniqueSlug(string $name): string
    {
        $base = $this->slug($name, 'agence');
        $repository = $this->em->getRepository(Agency::class);
        $slug = $base;

        for ($i = 2; null !== $repository->findOneBy(['slug' => $slug]); ++$i) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    /**
     * Code d'agence : AG- + 3 lettres de la ville + 3 chiffres (par exemple AG-KIN-482), 4 chiffres si nécessaire.
     */
    private function uniqueAgencyCode(string $city): string
    {
        $ascii = (new AsciiSlugger())->slug($city, '')->toString();
        $letters = str_pad(strtoupper(substr((string) preg_replace('/[^A-Za-z]/', '', $ascii), 0, 3)) ?: 'AGC', 3, 'X');
        $repository = $this->em->getRepository(Agency::class);

        foreach ([3 => 40, 4 => 40] as $digits => $attempts) {
            for ($i = 0; $i < $attempts; ++$i) {
                $code = sprintf('AG-%s-%d', $letters, random_int(10 ** ($digits - 1), 10 ** $digits - 1));

                if (null === $repository->findOneBy(['code' => $code])) {
                    return $code;
                }
            }
        }

        throw new AgencyRegistrationException([AgencyRegistrationException::GLOBAL => 'Impossible de générer le code de l\'agence, merci de réessayer.']);
    }

    private function uniqueUserCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';
        $repository = $this->em->getRepository(User::class);

        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $code = 'USR-';
            for ($i = 0; $i < 8; ++$i) {
                $code .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
            }

            if (null === $repository->findOneBy(['code' => $code])) {
                return $code;
            }
        }

        throw new AgencyRegistrationException([AgencyRegistrationException::GLOBAL => 'Impossible de créer le compte pour le moment, merci de réessayer.']);
    }
}
