<?php

namespace App\Controller\Api;

use App\Entity\EmailLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/email-log', name: 'app_api_email_log_')]
class EmailLogController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(EntityManagerInterface $em, Request $request): JsonResponse
    {
        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 50)));

        $qb = $em->createQueryBuilder()
            ->select('e')
            ->from(EmailLog::class, 'e')
            ->orderBy('e.sentAt', 'DESC');

        $status = $request->query->get('status');
        if ($status) {
            $qb->andWhere('e.status = :status')->setParameter('status', $status);
        }

        $dateFrom = $request->query->get('dateFrom');
        if ($dateFrom) {
            $qb->andWhere('e.sentAt >= :dateFrom')->setParameter('dateFrom', new \DateTimeImmutable($dateFrom));
        }

        $dateTo = $request->query->get('dateTo');
        if ($dateTo) {
            $qb->andWhere('e.sentAt <= :dateTo')->setParameter('dateTo', new \DateTimeImmutable($dateTo . ' 23:59:59'));
        }

        $total = (clone $qb)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'data' => array_map(fn(EmailLog $e) => [
                'id'              => $e->getId(),
                'sentAt'          => $e->getSentAt()->format('Y-m-d H:i:s'),
                'recipientEmail'  => $e->getRecipientEmail(),
                'subject'         => $e->getSubject(),
                'totalAlerts'     => $e->getTotalAlerts(),
                'complianceCount' => $e->getComplianceCount(),
                'oilCount'        => $e->getOilCount(),
                'creditCount'     => $e->getCreditCount(),
                'status'          => $e->getStatus(),
                'errorMessage'    => $e->getErrorMessage(),
                'triggeredBy'     => $e->getTriggeredBy(),
            ], $items),
            'meta' => [
                'total' => (int) $total,
                'page'  => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }
}
