<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\CashSession;
use App\Entity\User;
use App\Enum\CashSessionStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Empêche un caissier/guichetier de se déconnecter tant que sa session de caisse
 * (CashSession) est au statut Opened et n'a pas été clôturée.
 */
#[AsEventListener(event: LogoutEvent::class, dispatcher: 'security.event_dispatcher.main', priority: 500)]
final class PreventLogoutWithOpenCashSessionListener
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public function __invoke(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $tokenUser = $token?->getUser();
        if (!$tokenUser instanceof User || null === $tokenUser->getId()) {
            return;
        }

        $user = $this->em->getRepository(User::class)->find($tokenUser->getId());
        if (!$user instanceof User) {
            return;
        }

        $openSession = $this->em->getRepository(CashSession::class)->findOneBy([
            'openedBy' => $user,
            'status'   => CashSessionStatus::Opened,
        ]);

        if (!$openSession instanceof CashSession) {
            return;
        }

        // Restaure immédiatement le token vidé par Symfony avant LogoutEvent
        $this->tokenStorage->setToken($token);
        $event->stopPropagation();

        $session = $event->getRequest()->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(
                'error',
                sprintf(
                    'Déconnexion impossible : vous devez d\'abord clôturer votre session de caisse sur le guichet [%s].',
                    $openSession->getCashRegister()?->getName() ?? 'Caisse'
                )
            );
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('admin_agency_cashier_session_closing')
        ));
    }
}
