<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\public\business\PartnerRegistrationData;
use App\Entity\User;
use App\Enum\UserType;
use App\Exception\PartnerRegistrationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Inscription publique d'un partenaire : crée l'utilisateur (type « partner ») et le rattache à son parrain
 * (User::parent, utilisé pour les commissions par niveau).
 *
 * Conformément au MCD, l'accès à l'espace partenaire dépend de User::userType, pas d'un rôle :
 * Role et UserRole restent réservés aux rôles de la plateforme et des agences.
 */
final class PartnerRegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ReferralTokenGenerator $tokens,
    ) {}

    /**
     * Parrain valide : partenaire actif qui possède ce jeton (casse ignorée).
     */
    public function findSponsor(string $token): ?User
    {
        $token = mb_strtolower(trim($token));

        if ('' === $token) {
            return null;
        }

        $sponsor = $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->andWhere('LOWER(u.referralToken) = :token')
            ->andWhere('u.userType = :type')
            ->andWhere('u.isActive = :active')
            ->setParameter('token', $token)
            ->setParameter('type', UserType::Partner->value)
            ->setParameter('active', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $sponsor instanceof User ? $sponsor : null;
    }

    /**
     * @throws PartnerRegistrationException
     */
    public function register(PartnerRegistrationData $data, \DateTimeImmutable $now): User
    {
        return $this->em->wrapInTransaction(function () use ($data, $now): User {
            $email = mb_strtolower(trim((string) $data->email));

            $sponsor = null;
            if (null !== $data->sponsorToken && '' !== trim($data->sponsorToken)) {
                $sponsor = $this->findSponsor($data->sponsorToken);

                if (null === $sponsor) {
                    throw new PartnerRegistrationException('Ce code de parrainage est invalide.', 'sponsorToken');
                }
            }

            if ($this->emailExists($email)) {
                throw new PartnerRegistrationException('Cette adresse email est déjà utilisée.', 'email');
            }

            $user = (new User())
                ->setFirstname(trim((string) $data->firstname))
                ->setLastname(trim((string) $data->lastname))
                ->setPhone(substr((string) preg_replace('/[^\d+]/', '', (string) $data->phone), 0, 30))
                ->setEmail($email)
                ->setUserType(UserType::Partner)
                ->setParent($sponsor)
                ->setReferralToken($this->tokens->generate((string) $data->firstname))
                ->setCode($this->uniqueUserCode())
                ->setIsActive(true)
                ->setIsLegalAccepted(true)
                ->setLegalAcceptedAt($now);
            $user->setPassword($this->passwordHasher->hashPassword($user, (string) $data->password));
            $this->em->persist($user);

            return $user;
        });
    }

    private function emailExists(string $email): bool
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->andWhere('LOWER(u.email) = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getSingleScalarResult() > 0;
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

        throw new PartnerRegistrationException('Impossible de créer le compte pour le moment, merci de réessayer.');
    }
}
