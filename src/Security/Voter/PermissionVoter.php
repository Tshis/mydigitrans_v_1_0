<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Security\PermissionResolver;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PermissionVoter extends Voter
{
    public function __construct(
        private readonly PermissionResolver $permissionResolver
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Intercepte les actions pointées du catalogue d'accès
        return 1 === \preg_match('/^[a-z_]+\.(view|write|refund|print|confirm|activate)$/', $attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        // 🟢 LE FIX : On passe l'utilisateur au résolveur pour forcer le calcul de ses rôles en base de données
        return $this->permissionResolver->has($user, $attribute);
    }
}
