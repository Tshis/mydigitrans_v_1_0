<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Dto\admin\AgentData;
use App\Entity\User;
use App\Enum\UserType;
use App\Security\AccessCatalog;
use App\Security\PermissionResolver;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class UserManagementVoter extends Voter
{
    public function __construct(
        private readonly PermissionResolver $permissionResolver
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        // 🟢 UNIVERSEL : Intercepte toutes les chaînes pointées (ex: trip.view, cash_register.view)
        return \str_contains($attribute, '.view') || \str_contains($attribute, '.write');
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
        if (!\in_array(AccessCatalog::SUPER_ADMIN, $actor->getRoles(), true)) {
            return false;
        }
        if (($attribute === 'user.write' || \str_contains($attribute, '.write')) && $subject instanceof User && $subject->getId() === $actor->getId()) {
            return false;
        }
        return true;
    }

    private function canManageAgency(string $attribute, mixed $subject, User $actor): bool
    {
        $isManager = \in_array('ROLE_MANAGER', $actor->getRoles(), true);

        if ($subject instanceof AgentData) {
            if (!\str_contains($attribute, '.write')) {
                return false;
            }
            if ($isManager) {
                if ($subject->branchCode !== $actor->getBranch()?->getCode()) {
                    return false;
                }
                if (\in_array($subject->roleCode, [AccessCatalog::AGENCY_ADMIN, AccessCatalog::GENERAL_MANAGER], true)) {
                    return false;
                }
            }
            return true;
        }

        if ($subject instanceof User) {
            if ($subject->getAgency()?->getId() !== $actor->getAgency()?->getId()) {
                return false;
            }
            if (\str_contains($attribute, '.write') && $subject->getId() === $actor->getId()) {
                return false;
            }
            if ($isManager && $subject->getBranch()?->getId() !== $actor->getBranch()?->getId()) {
                return false;
            }
            if (\str_contains($attribute, '.write') && $isManager) {
                $targetRoles = $subject->getRoles();
                if (\in_array(AccessCatalog::AGENCY_ADMIN, $targetRoles, true) || \in_array(AccessCatalog::GENERAL_MANAGER, $targetRoles, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
