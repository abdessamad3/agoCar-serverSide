<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notification', name: 'app_api_notification_')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    public function __construct(
        private NotificationRepository $repo,
        private EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user  = $this->getUser();
        $limit = min(200, max(10, (int) $request->query->get('limit', 100)));
        $tab   = $request->query->get('tab', 'all');
        $items = $this->repo->findByUserAndTab($user, $tab, $limit);

        return $this->json(array_map(fn($n) => [
            'id'         => $n->getId(),
            'title'      => $n->getTitle(),
            'message'    => $n->getMessage(),
            'type'       => $n->getType(),
            'priority'   => $n->getPriority(),
            'deepLink'   => $n->getDeepLink(),
            'sourceType' => $n->getSourceType(),
            'sourceId'   => $n->getSourceId(),
            'readAt'     => $n->getReadAt()?->format(\DateTimeInterface::ATOM),
            'createdAt'  => $n->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'isRead'     => $n->isRead(),
        ], $items));
    }

    #[Route('/unread-count', name: 'unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        return $this->json(['count' => $this->repo->countUnread($this->getUser())]);
    }

    #[Route('/{id}/read', name: 'mark_read', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function markRead(int $id): JsonResponse
    {
        $notif = $this->repo->find($id);
        if (!$notif || $notif->getUser()?->getId() !== $this->getUser()?->getId()) {
            return $this->json(['error' => 'Not found'], 404);
        }
        if (!$notif->isRead()) {
            $notif->setReadAt(new \DateTimeImmutable());
            $this->em->flush();
        }
        return $this->json(['ok' => true]);
    }

    #[Route('/read-all', name: 'mark_all_read', methods: ['PUT'])]
    public function markAllRead(): JsonResponse
    {
        $marked = $this->repo->markAllReadForUser($this->getUser(), new \DateTimeImmutable());
        return $this->json(['marked' => $marked]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $notif = $this->repo->find($id);
        if (!$notif || $notif->getUser()?->getId() !== $this->getUser()?->getId()) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $this->em->remove($notif);
        $this->em->flush();
        return $this->json(['message' => 'Notification supprimée']);
    }

    /**
     * Server-Sent Events stream — emits the unread count once and closes immediately.
     * The frontend now uses 30 s HTTP polling (/unread-count) instead of SSE to avoid
     * the EventSource reconnect loop. This endpoint is kept for future use with a
     * persistent PHP worker (e.g. nginx + PHP-FPM with async workers).
     */
    #[Route('/stream', name: 'stream', methods: ['GET'])]
    public function sseStream(): StreamedResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $repo = $this->repo;

        $response = new StreamedResponse(function () use ($user, $repo) {
            echo "retry: 30000\n\n";
            echo 'data: ' . json_encode(['count' => $repo->countUnread($user)]) . "\n\n";
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-store');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Connection', 'close');

        return $response;
    }
}
