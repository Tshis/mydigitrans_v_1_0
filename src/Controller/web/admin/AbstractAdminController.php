<?php

namespace App\Controller\web\admin;

use App\Entity\User;
use App\Enum\Scope;
use App\Security\PermissionResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Base des écrans d'administration communs aux agences et à la plateforme : une seule logique,
 * deux portées. Chaque contrôleur concret fixe sa portée et déclare ses routes.
 */
abstract class AbstractAdminController extends AbstractController
{
    abstract protected function scope(): Scope;

    /** Préfixe des noms de routes : admin_agency ou admin_platform. */
    protected function routePrefix(): string
    {
        return Scope::Platform === $this->scope() ? 'admin_platform' : 'admin_agency';
    }

    /**
     * Utilisateur connecté, obligatoirement de la portée de l'écran (et rattaché à une agence pour les agences).
     */
    protected function actor(): User
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $actor = $this->getUser();

        if (!$actor instanceof User
            || PermissionResolver::scopeOf($actor) !== $this->scope()
            || (Scope::Agency === $this->scope() && null === $actor->getAgency())) {
            throw $this->createAccessDeniedException('Cet espace n\'est pas accessible avec ce compte.');
        }

        return $actor;
    }
}
