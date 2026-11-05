<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Dto\admin\AgentData;
use App\Entity\User;
use App\Enum\UserType;
use App\Security\AccessCatalog;
use App\Security\PermissionResolver;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Voter régissant la création, l'édition, la lecture et l'activation des agents sur MyDigitrans.
 */
final class UserManagementVoter extends Voter
{
    public const VIEW = 'user.view';
    public const WRITE = 'user.write';
    public const ACTIVATE = 'user.activate';

    public function __construct(
        private readonly PermissionResolver $permissionResolver
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::WRITE, self::ACTIVATE], true)
            && ($subject === null || $subject instanceof User || $subject instanceof AgentData);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $actor = $token->getUser();
        if (!$actor instanceof User || !$actor->isActive()) {
            return false;
        }

        // 1. Validation de la permission brute dans la base de données via le Résolveur
        if (!$this->permissionResolver->has($actor, $attribute)) {
            return false;
        }

        // 2. Traitement des règles contextuelles métier par type de compte
        return match ($actor->getUserType()) {
            UserType::Platform => $this->canManagePlatform($attribute, $subject, $actor),
            UserType::Agency => $this->canManageAgency($attribute, $subject, $actor),
            default => false,
        };
    }

    private function canManagePlatform(string $attribute, mixed $subject, User $actor): bool
    {
        // Seul le Super Administrateur interagit avec les comptes de la plateforme
        if (!\in_array(AccessCatalog::SUPER_ADMIN, $actor->getRoles(), true)) {
            return false;
        }

        // Règle d'or : Interdiction de modifier son propre profil ou de s'auto-bloquer sur cet écran
        if ($attribute === self::WRITE && $subject instanceof User && $subject->getId() === $actor->getId()) {
            return false;
        }

        return true;
    }

    private function canManageAgency(string $attribute, mixed $subject, User $actor): bool
    {
        $isManager = \in_array('ROLE_MANAGER', $actor->getRoles(), true);

        // Cas A : Phase de création (Sujet de type DTO AgentData)
        if ($subject instanceof AgentData) {
            if ($attribute !== self::WRITE) {
                return false;
            }

            if ($isManager) {
                // Un Manager de succursale ne peut assigner un nouvel agent qu'à sa propre succursale
                if ($subject->branchCode !== $actor->getBranch()?->getCode()) {
                    return false;
                }
                // Protégé : Un manager ne peut pas créer un Admin ou DG (Pas d'élévation de privilèges)
                if (\in_array($subject->roleCode, [AccessCatalog::AGENCY_ADMIN, AccessCatalog::GENERAL_MANAGER], true)) {
                    return false;
                }
            }
            return true;
        }

        // Cas B : Consultation ou modification d'une entité User existante
        if ($subject instanceof User) {
            // Étanchéité Multi-tenant : Interdiction stricte de toucher à une autre agence
            if ($subject->getAgency()?->getId() !== $actor->getAgency()?->getId()) {
                return false;
            }

            // Sécurité contre l'auto-modification destructive via l'écran collaborateur
            if ($attribute === self::WRITE && $subject->getId() === $actor->getId()) {
                return false;
            }

            // Cloisonnement du MCD : Un manager est restreint aux agents de sa succursale physique
            if ($isManager && $subject->getBranch()?->getId() !== $actor->getBranch()?->getId()) {
                return false;
            }

            // Protection hiérarchique contre l'édition de profils supérieurs
            if ($attribute === self::WRITE && $isManager) {
                $targetRoles = $subject->getRoles();
                if (\in_array(AccessCatalog::AGENCY_ADMIN, $targetRoles, true) || \in_array(AccessCatalog::GENERAL_MANAGER, $targetRoles, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
