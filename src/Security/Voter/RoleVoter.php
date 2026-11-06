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

        // 🟢 ACCÈS MAÎTRE AUTOMATIQUE : Si l'utilisateur est de portée Plateforme (SuperAdmin / Admin Système)
        if (Scope::Platform->value === $userScopeStr || $user->getUserType() === 'platform') {
            return Scope::Platform->value === $roleScopeStr;
        }

        // 2. Un gérant d'Agence gère uniquement la portée Agence
        if (Scope::Agency->value === $userScopeStr || $user->getUserType() === 'agency') {
            if (Scope::Agency->value !== $roleScopeStr) {
                return false;
            }

            // Si c'est l'administrateur principal de l'agence, on évite le blocage
            if ($attribute === self::EDIT) {
                return $this->permissionResolver->has($user, 'MANAGE_AGENCY_ROLES') || $user->getParent() === null;
            }

            return true;
        }

        return false;
    }
}
