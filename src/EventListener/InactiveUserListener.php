<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Déconnecte immédiatement un utilisateur dont le compte a été désactivé
 * pendant que sa session était ouverte.
 *
 * Priorité 7 : s'exécute juste après le firewall de sécurité (priorité 8),
 * quand l'utilisateur a déjà été rechargé depuis la base.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
class InactiveUserListener
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Pas de cookie de session : personne n'est connecté, on évite de démarrer l'authentification.
        if (!$request->hasPreviousSession()) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof User || $user->isActive()) {
            return;
        }

        $user->setIsOnline(false);
        $this->em->flush();

        $this->tokenStorage->setToken(null);

        $session = $request->getSession();
        $session->invalidate();

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', 'Votre compte a été désactivé. Contactez votre administrateur.');
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('security_login_index')));
    }
}
