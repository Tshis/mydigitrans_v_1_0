<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Dto\admin\AgentData;
use App\Entity\User;
use App\Enum\UserType;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Voter professionnel régissant la création, édition et lecture des agents.
 */
final class UserVoter extends Voter
{
    public const VIEW = 'user.view';
    public const WRITE = 'user.write';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::WRITE], true)
            && ($subject === null || $subject instanceof User || $subject instanceof AgentData);
    }

    /**
     * @param string $attribute
     * @param mixed $subject
     * @param TokenInterface $token
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /**
         *@var User 
         */
        $currentUser = $token->getUser();



        if (!$currentUser instanceof User || !$currentUser->isActive()) {
            return false;
        }

        if ($currentUser->getUserType() === UserType::Platform) {
            return $this->canManagePlatform($attribute, $subject, $currentUser);
        }

        if ($currentUser->getUserType() === UserType::Agency) {
            return $this->canManageAgency($attribute, $subject, $currentUser);
        }

        return false;
    }


    /**
     * Autorisation pour la gestion de la plateforme
     */
    private function canManagePlatform(string $attribute, mixed $subject, User $currentUser): bool
    {

        // Seul un Super Admin de la plateforme gère la portée plateforme
        if (!\in_array('ROLE_SUPER_ADMIN', $currentUser->getRoles(), true)) {
            return false;
        }

        if ($attribute === self::WRITE && $subject instanceof User) {
            // Règle d'or : Interdiction de modifier sa propre fonction ou de se bloquer soi-même
            if ($subject->getId() === $currentUser->getId()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Autorisation pour la gestion de l'agence
     */
    private function canManageAgency(string $attribute, mixed $subject, User $currentUser): bool
    {
        // Vérification préliminaire des rôles hiérarchiques de l'agence
        $hasAdminRights = (\in_array('ROLE_AGENCY_ADMIN', $currentUser->getRoles(), true) || \in_array('ROLE_GENERAL_MANAGER', $currentUser->getRoles(), true));
        $isManager = \in_array('ROLE_MANAGER', $currentUser->getRoles(), true);

        if (!$hasAdminRights && !$isManager) {
            return false;
        }

        // Cas d'une création (Le sujet est le DTO AgentData)
        if ($subject instanceof AgentData) {
            if ($attribute !== self::WRITE) {
                return false;
            }

            // Un ROLE_MANAGER ne gère QUE sa propre succursale (branchCode obligatoire)
            if ($isManager) {
                if ($subject->branchCode !== $currentUser->getBranch()->getCode()) {
                    return false;
                }
                // Un manager ne peut pas créer un administrateur (Pas d'élévation de privilèges)
                if (\in_array($subject->roleCode, ['ROLE_AGENCY_ADMIN', 'ROLE_GENERAL_MANAGER'], true)) {
                    return false;
                }
            }
            return true;
        }

        // Cas de lecture ou modification d'une entité User existante
        if ($subject instanceof User) {
            // L'agent ciblé doit appartenir à la même agence
            if ($subject->getAgency() !== $currentUser->getAgency()) {
                return false;
            }

            // Protection : Auto-modification interdite via cet écran
            if ($subject->getId() === $currentUser->getId() && $attribute === self::WRITE) {
                return false;
            }

            // Règle du MCD : Restriction stricte au niveau de la succursale (branch)
            if ($isManager && $subject->getBranch() !== $currentUser->getBranch()) {
                return false;
            }

            if ($attribute === self::WRITE) {
                // Un manager ne peut pas modifier un compte Admin ou DG
                $targetRoles = $subject->getRoles();
                if ($isManager && (\in_array('ROLE_AGENCY_ADMIN', $targetRoles, true) || \in_array('ROLE_GENERAL_MANAGER', $targetRoles, true))) {
                    return false;
                }
            }
        }

        return true;
    }
}
