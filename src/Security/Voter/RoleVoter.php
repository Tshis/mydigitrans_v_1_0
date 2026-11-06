<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Role;
use App\Entity\User;
use App\Enum\Scope;
use App\Security\PermissionResolver;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class RoleVoter extends Voter
{
    public const VIEW = 'ROLE_VIEW';
    public const EDIT = 'ROLE_EDIT';

    public function __construct(private readonly PermissionResolver $permissionResolver) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true) && $subject instanceof Role;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        /** @var Role $role */
        $role = $subject;

        $userScopeStr = PermissionResolver::scopeOf($user)->value;
        $roleScopeStr = $role->getScope() instanceof Scope ? $role->getScope()->value : (string) $role->getScope();

        // 1. Périmètre Plateforme (SuperAdmin / Admin Système)
        if (Scope::Platform->value === $userScopeStr || $user->getUserType() === 'platform') {
            return Scope::Platform->value === $roleScopeStr;
        }

        // 2. Périmètre Agence (Gérants)
        if (Scope::Agency->value === $userScopeStr || $user->getUserType() === 'agency') {
            if (Scope::Agency->value !== $roleScopeStr) {
                return false;
            }

            // 🛑 RESTRICTION MAXIMALE : Seul l'Admin racine de l'agence (sans compte parent) peut créer/modifier des fonctions
            if ($attribute === self::EDIT) {
                return $user->getParent() === null;
            }

            // La consultation simple (VIEW) reste autorisée pour les autres agents de la succursale
            return true;
        }

        return false;
    }
}
