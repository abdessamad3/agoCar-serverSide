<?php

namespace App\EventSubscriber;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class JwtActivitySubscriber implements EventSubscriberInterface
{
    private const INACTIVITY_LIMIT = 86400;  // 24 hours in seconds
    private const UPDATE_THROTTLE   = 300;   // only write to DB every 5 minutes

    public function __construct(
        private TokenStorageInterface  $tokenStorage,
        private EntityManagerInterface $em,
    ) {}

    public static function getSubscribedEvents(): array
    {
        // Priority -10 so security firewall runs first (user is loaded from JWT)
        return [KernelEvents::REQUEST => ['onRequest', -10]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof Utilisateur) {
            return;
        }

        $now          = new \DateTimeImmutable();
        $lastActivity = $user->getLastActivityAt();

        // Reject if idle for more than 24h
        if ($lastActivity !== null && ($now->getTimestamp() - $lastActivity->getTimestamp()) > self::INACTIVITY_LIMIT) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Session expirée par inactivité. Veuillez vous reconnecter.', 'code' => 'SESSION_EXPIRED'],
                401
            ));
            return;
        }

        // Throttle DB writes: only update if lastActivityAt is null or older than 5 minutes
        if ($lastActivity === null || ($now->getTimestamp() - $lastActivity->getTimestamp()) > self::UPDATE_THROTTLE) {
            $user->setLastActivityAt($now);
            $this->em->flush();
        }
    }
}
