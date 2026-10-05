<?php

namespace App\Controller\Api;

use App\Repository\ActivityLogRepository;
use App\Service\ActivityLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/activity-log', name: 'app_api_activity_log_')]
#[IsGranted('ROLE_USER')]
class ActivityLogController extends AbstractController
{
    public function __construct(
        private ActivityLogRepository $repo,
        private ActivityLogService    $logSvc,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $entityType = $request->query->get('entityType') ?: null;
        $action     = $request->query->get('action')     ?: null;
        $userId     = $request->query->get('userId')     ? (int) $request->query->get('userId')   : null;
        $bureauId   = $request->query->get('bureauId')   ? (int) $request->query->get('bureauId') : null;
        $dateFrom   = $request->query->get('dateFrom')   ?: null;
        $dateTo     = $request->query->get('dateTo')     ?: null;
        $page       = max(1, (int) $request->query->get('page', 1));
        $limit      = min(200, max(1, (int) $request->query->get('limit', 20)));

        $logs  = $this->repo->findFiltered($entityType, $action, $userId, $bureauId, $dateFrom, $dateTo, $page, $limit);
        $total = $this->repo->countFiltered($entityType, $action, $userId, $bureauId, $dateFrom, $dateTo);

        return $this->json([
            'data' => array_map([$this, 'serialize'], $logs),
            'meta' => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'totalPages'  => max(1, (int) ceil($total / $limit)),
                'hasNextPage' => $page < max(1, (int) ceil($total / $limit)),
                'hasPrevPage' => $page > 1,
            ],
        ]);
    }

    #[Route('/view', name: 'log_view', methods: ['POST'])]
    public function logView(Request $request): JsonResponse
    {
        $data       = json_decode($request->getContent(), true) ?? [];
        $entityType = $data['entityType'] ?? null;
        $entityId   = isset($data['entityId']) ? (int) $data['entityId'] : null;

        if (!$entityType || !$entityId) {
            return $this->json(['error' => 'entityType and entityId required'], 400);
        }

        $this->logSvc->logView($entityType, $entityId);

        return $this->json(['ok' => true], 201);
    }

    /** History of one specific record — used by the entity detail tabs. */
    #[Route('/entity/{type}/{id}', name: 'entity_history', methods: ['GET'])]
    public function entityHistory(string $type, int $id, Request $request): JsonResponse
    {
        $limit = min(200, max(10, (int) $request->query->get('limit', 100)));
        $logs  = $this->repo->findByEntity($type, $id, $limit);

        return $this->json(array_map([$this, 'serialize'], $logs));
    }

    private function serialize(\App\Entity\ActivityLog $log): array
    {
        $user = $log->getUser();
        return [
            'id'         => $log->getId(),
            'entityType' => $log->getEntityType(),
            'entityId'   => $log->getEntityId(),
            'action'     => $log->getAction(),
            'oldData'    => $log->getOldData(),
            'newData'    => $log->getNewData(),
            'createdAt'  => $log->getCreatedAt()->format('Y-m-d H:i:s'),
            'user'       => $user ? [
                'id'     => $user->getId(),
                'nom'    => $user->getNom() . ' ' . $user->getPrenom(),
                'email'  => $user->getEmail(),
            ] : null,
            'bureau'     => $log->getBureau()?->getNom(),
            'ipAddress'  => $log->getIpAddress(),
        ];
    }
}
