<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\UserType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service permettant de récupérer l'acteur système virtuel 
 * pour remplir les champs de traçabilité (createdBy, assignedBy, etc.).
 */
final class SystemActor
{
    private const SYSTEM_EMAIL = 'system@mydigitrans.com';
    private ?User $systemUser = null;

    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    /**
     * Récupère ou crée l'entité User représentant le système.
     */
    public function get(): User
    {
        // Évite de refaire la requête SQL si on l'a déjà récupéré durant la même exécution
        if ($this->systemUser !== null) {
            return $this->systemUser;
        }

        $repo = $this->em->getRepository(User::class);
        $user = $repo->findOneBy(['email' => self::SYSTEM_EMAIL]);

        if ($user instanceof User) {
            $this->systemUser = $user;
            return $this->systemUser;
        }

        // Si le compte robot système n'existe pas encore en base, on le génère
        $user = (new User())
            ->setFirstname('Système')
            ->setLastname('MyDigitrans')
            ->setEmail(self::SYSTEM_EMAIL)
            ->setPhone('+243000000000')
            ->setUserType(UserType::Platform) // Il appartient à la plateforme
            ->setCode('USR-SYSTEM-ROBOT')
            ->setIsActive(true)
            ->setIsLegalAccepted(true)
            ->setLegalAcceptedAt(new \DateTimeImmutable())
            ->setPassword('SYSTEM_ROBOT_ACCOUNT_DISABLED_NO_LOGIN'); // Ce compte ne peut pas se connecter

        $this->em->persist($user);
        $this->em->flush();

        $this->systemUser = $user;

        return $this->systemUser;
    }
}
