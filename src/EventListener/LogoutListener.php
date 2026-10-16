<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class)]
class LogoutListener
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();

        if (!$user instanceof User) {
            return;
        }

        $user->setIsOnline(false);
        $this->em->flush();
    }
}
