<?php

namespace App\Controller\Api;

use App\Entity\VehicleCreditReminder;
use App\Trait\BureauAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/vehicle-credit-reminder', name: 'app_api_vc_reminder_')]
class VehicleCreditReminderController extends AbstractController
{
    use BureauAwareTrait;

    /** Bureau-locked staff/managers may only touch reminders belonging to their own
     *  bureau. True admins (getEffectiveBureauId() === null) are unrestricted. */
    private function assertBureauAccess(VehicleCreditReminder $reminder): void
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId === null) return;

        if ($reminder->getVehicleCredit()?->getVoiture()?->getBureau()?->getId() !== $bureauId) {
            throw $this->createNotFoundException('Rappel introuvable');
        }
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $creditId = $request->query->get('vehicleCreditId');
        $status   = $request->query->get('status');

        $qb = $em->createQueryBuilder()->select('r')->from(VehicleCreditReminder::class, 'r')
            ->orderBy('r.reminderDate', 'ASC');

        if ($creditId) {
            $qb->andWhere('r.vehicleCredit = :cid')->setParameter('cid', (int) $creditId);
        }
        if ($status) {
            $qb->andWhere('r.status = :status')->setParameter('status', $status);
        }

        $items = $qb->getQuery()->getResult();
        return $this->json(array_map(fn($r) => [
            'id'              => $r->getId(),
            'vehicleCreditId' => $r->getVehicleCredit()?->getId(),
            'installmentId'   => $r->getInstallment()?->getId(),
            'installmentNumber'=> $r->getInstallment()?->getInstallmentNumber(),
            'reminderDate'    => $r->getReminderDate()?->format('Y-m-d'),
            'reminderType'    => $r->getReminderType(),
            'status'          => $r->getStatus(),
            'sentAt'          => $r->getSentAt()?->format('Y-m-d H:i'),
            'createdAt'       => $r->getCreatedAt()?->format('Y-m-d'),
        ], $items));
    }

    #[Route('/due-today', name: 'due_today', methods: ['GET'])]
    public function dueToday(EntityManagerInterface $em): JsonResponse
    {
        $today = new \DateTimeImmutable('today');
        $items = $em->createQueryBuilder()
            ->select('r')
            ->from(VehicleCreditReminder::class, 'r')
            ->where('r.reminderDate <= :today')
            ->andWhere('r.status = :status')
            ->setParameter('today', $today)
            ->setParameter('status', 'pending')
            ->getQuery()->getResult();

        return $this->json(array_map(fn($r) => [
            'id'           => $r->getId(),
            'reminderDate' => $r->getReminderDate()?->format('Y-m-d'),
            'reminderType' => $r->getReminderType(),
            'creditId'     => $r->getVehicleCredit()?->getId(),
            'installmentId'=> $r->getInstallment()?->getId(),
        ], $items));
    }

    #[Route('/{id}/mark-sent', name: 'mark_sent', methods: ['PUT'])]
    public function markSent(VehicleCreditReminder $reminder, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($reminder);
        $reminder->setStatus('sent');
        $reminder->setSentAt(new \DateTimeImmutable());
        $em->flush();
        return $this->json(['message' => 'Rappel marqué comme envoyé']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(VehicleCreditReminder $reminder, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($reminder);
        $em->remove($reminder);
        $em->flush();
        return $this->json(['message' => 'Rappel supprimé']);
    }
}
