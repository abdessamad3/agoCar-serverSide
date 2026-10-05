<?php

namespace App\Controller\Api;

use App\Entity\ErrorLog;
use App\Repository\ErrorLogRepository;
use App\Service\ErrorLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/error-log', name: 'app_api_error_log_')]
class ErrorLogController extends AbstractController
{
    public function __construct(
        private ErrorLogRepository $repo,
        private ErrorLogService    $errorLog,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(Request $request): JsonResponse
    {
        $source   = $request->query->get('source')   ?: null;
        $dateFrom = $request->query->get('dateFrom') ?: null;
        $dateTo   = $request->query->get('dateTo')   ?: null;
        $page     = max(1, (int) $request->query->get('page', 1));
        $limit    = min(200, max(1, (int) $request->query->get('limit', 20)));

        $logs  = $this->repo->findFiltered($source, $dateFrom, $dateTo, $page, $limit);
        $total = $this->repo->countFiltered($source, $dateFrom, $dateTo);

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

    #[Route('/latest', name: 'latest', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function latest(): JsonResponse
    {
        $log = $this->repo->findLatest();
        return $this->json(['data' => $log ? $this->serialize($log) : null]);
    }

    /**
     * Reports an uncaught frontend error (see the Angular GlobalErrorHandler).
     * Deliberately PUBLIC — a crash can happen before the user is logged in,
     * and this must still be recorded. Payload is strictly bounded and typed;
     * nothing here is ever reflected back or executed, only stored as text.
     */
    #[Route('/client', name: 'client_report', methods: ['POST'])]
    public function reportClientError(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $message = trim((string) ($data['message'] ?? ''));
        if ($message === '') {
            return $this->json(['error' => 'message is required'], 422);
        }
        if (mb_strlen($message) > 2000) {
            $message = mb_substr($message, 0, 2000);
        }

        $stack = isset($data['stack']) ? (string) $data['stack'] : null;
        $url   = isset($data['url'])   ? (string) $data['url']   : null;

        $this->errorLog->recordClientError($message, $stack, $url, $request);

        // Always 204 -- a reporting endpoint should never itself surface an
        // error to a client that's already in a broken state.
        return $this->json(null, 204);
    }

    private function serialize(ErrorLog $log): array
    {
        $user = $log->getUser();
        return [
            'id'             => $log->getId(),
            'source'         => $log->getSource(),
            'exceptionClass' => $log->getExceptionClass(),
            'message'        => $log->getMessage(),
            'file'           => $log->getFile(),
            'line'           => $log->getLine(),
            'trace'          => $log->getTrace(),
            'requestUrl'     => $log->getRequestUrl(),
            'requestMethod'  => $log->getRequestMethod(),
            'createdAt'      => $log->getCreatedAt()->format('Y-m-d H:i:s'),
            'user'           => $user ? [
                'id'    => $user->getId(),
                'nom'   => $user->getNom() . ' ' . $user->getPrenom(),
                'email' => $user->getEmail(),
            ] : null,
            'ipAddress'      => $log->getIpAddress(),
        ];
    }
}
