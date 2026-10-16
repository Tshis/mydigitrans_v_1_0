<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Agency;
use App\Entity\User;
use App\Enum\UserType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Re-hash automatiquement le mot de passe quand l'algorithme évolue.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findOneByCode(string $code): ?User
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findOneByReferralToken(string $referralToken): ?User
    {
        return $this->findOneBy(['referralToken' => $referralToken]);
    }

    /**
     * @return User[]
     */
    public function findByAgency(Agency $agency, ?UserType $type = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('u.lastname', 'ASC')
            ->addOrderBy('u.firstname', 'ASC');

        if (null !== $type) {
            $qb->andWhere('u.userType = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Utilisateurs directement parrainés par $parent (niveau 1).
     *
     * @return User[]
     */
    public function findReferredBy(User $parent): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.parent = :parent')
            ->setParameter('parent', $parent)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
