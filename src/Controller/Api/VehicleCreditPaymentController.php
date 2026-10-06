<?php

namespace App\Controller\Api;

use App\Entity\Notification;
use App\Entity\VehicleCreditPayment;
use App\Entity\PaymentAttachment;
use App\Repository\UtilisateurRepository;
use App\Repository\VehicleCreditRepository;
use App\Repository\VehicleCreditInstallmentRepository;
use App\Trait\BureauAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/vehicle-credit-payment', name: 'app_api_vc_payment_')]
class VehicleCreditPaymentController extends AbstractController
{
    use BureauAwareTrait;

    /** Bureau-locked staff/managers may only touch credit payments belonging to
     *  their own bureau. True admins (getEffectiveBureauId() === null) are unrestricted. */
    private function assertBureauAccess(VehicleCreditPayment $payment): void
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId === null) return;

        $voitureBureauId = $payment->getVehicleCredit()?->getVoiture()?->getBureau()?->getId();
        if ($voitureBureauId !== $bureauId) {
            throw $this->createNotFoundException('Paiement introuvable');
        }
    }

    private function serialize(VehicleCreditPayment $p): array
    {
        return [
            'id'              => $p->getId(),
            'vehicleCreditId' => $p->getVehicleCredit()?->getId(),
            'installmentId'   => $p->getInstallment()?->getId(),
            'installmentNumber'=> $p->getInstallment()?->getInstallmentNumber(),
            'paymentType'     => $p->getPaymentType(),
            'paymentMethod'   => $p->getPaymentMethod(),
            'amount'          => $p->getAmount(),
            'paymentDate'     => $p->getPaymentDate()?->format('Y-m-d'),
            'referenceNumber' => $p->getReferenceNumber(),
            'notes'           => $p->getNotes(),
            'createdAt'       => $p->getCreatedAt()?->format('Y-m-d'),
            'attachments'     => $p->getAttachments()->map(fn($a) => [
                'id'         => $a->getId(),
                'fileName'   => $a->getFileName(),
                'filePath'   => $a->getFilePath(),
                'fileType'   => $a->getFileType(),
                'uploadedAt' => $a->getUploadedAt()?->format('Y-m-d'),
            ])->toArray(),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $creditId = $request->query->get('vehicleCreditId');
        $qb = $em->createQueryBuilder()
            ->select('p')
            ->from(VehicleCreditPayment::class, 'p')
            ->orderBy('p.paymentDate', 'DESC');

        if ($creditId) {
            $qb->where('p.vehicleCredit = :cid')->setParameter('cid', (int) $creditId);
        }

        $items = $qb->getQuery()->getResult();
        return $this->json(array_map(fn($p) => $this->serialize($p), $items));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(VehicleCreditPayment $payment): JsonResponse
    {
        $this->assertBureauAccess($payment);
        return $this->json($this->serialize($payment));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        VehicleCreditRepository $creditRepo,
        VehicleCreditInstallmentRepository $installmentRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $credit = $creditRepo->find($data['vehicleCreditId'] ?? 0);
        if (!$credit) return $this->json(['error' => 'Contrat introuvable'], 404);

        if (!in_array($credit->getStatus(), ['active', 'pending_approval'])) {
            return $this->json(['error' => 'Le contrat n\'est pas actif'], 400);
        }

        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) return $this->json(['error' => 'Montant invalide'], 400);

        $payment = new VehicleCreditPayment();
        $payment->setVehicleCredit($credit);
        $payment->setPaymentType($data['paymentType'] ?? 'scheduled');
        $payment->setPaymentMethod($data['paymentMethod'] ?? 'bank_transfer');
        $payment->setAmount((string) $amount);
        $payment->setPaymentDate(new \DateTimeImmutable($data['paymentDate'] ?? 'today'));
        $payment->setReferenceNumber($data['referenceNumber'] ?? null);
        $payment->setNotes($data['notes'] ?? null);

        // Link to a specific installment if provided
        $installment = null;
        if (!empty($data['installmentId'])) {
            $installment = $installmentRepo->find($data['installmentId']);
            if ($installment && $installment->getVehicleCredit()->getId() === $credit->getId()) {
                $payment->setInstallment($installment);
            }
        }

        // If no installment specified, auto-assign to earliest unpaid
        if (!$installment && $payment->getPaymentType() === 'scheduled') {
            $installment = $this->findNextUnpaidInstallment($credit);
            if ($installment) $payment->setInstallment($installment);
        }

        $em->persist($payment);

        // Update installment balance
        if ($installment) {
            $this->applyPaymentToInstallment($installment, $amount);
        }

        // Subtract payment from remaining balance
        $newBalance = max(0.0, (float) $credit->getRemainingBalance() - $amount);
        $credit->setRemainingBalance((string) round($newBalance, 2));
        $credit->setUpdatedAt(new \DateTimeImmutable());

        // Auto-close if fully paid
        if ((float) $credit->getRemainingBalance() <= 0) {
            $credit->setStatus('completed');
            $credit->setUpdatedAt(new \DateTimeImmutable());
        }

        $em->flush();

        return $this->json(['message' => 'Paiement enregistré', 'id' => $payment->getId()], 201);
    }

    #[Route('/{id}/attachment', name: 'upload_attachment', methods: ['POST'])]
    public function uploadAttachment(VehicleCreditPayment $payment, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        $this->assertBureauAccess($payment);

        $file = $request->files->get('file');
        if (!$file) return $this->json(['error' => 'Aucun fichier fourni.'], 400);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        if (!in_array($file->getMimeType(), $allowedMimes, true)) {
            return $this->json(['error' => 'Type de fichier non autorisé. Utilisez JPG, PNG, GIF, WEBP ou PDF.'], 400);
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->json(['error' => 'Fichier trop volumineux (maximum 10 Mo).'], 400);
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/credit-payments/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        $ext      = $file->guessExtension() ?? 'bin';
        $fileName = uniqid('pay_') . '.' . $ext;
        $file->move($uploadDir, $fileName);

        $attachment = new PaymentAttachment();
        $attachment->setPayment($payment);
        $attachment->setFileName($file->getClientOriginalName());
        $attachment->setFilePath('/uploads/credit-payments/' . $fileName);
        $attachment->setFileType($ext);

        $em->persist($attachment);
        $em->flush();

        return $this->json([
            'id'       => $attachment->getId(),
            'fileName' => $attachment->getFileName(),
            'filePath' => $attachment->getFilePath(),
        ], 201);
    }

    #[Route('/attachment/{id}', name: 'delete_attachment', methods: ['DELETE'])]
    public function deleteAttachment(PaymentAttachment $attachment, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        if ($attachment->getPayment()) $this->assertBureauAccess($attachment->getPayment());

        $diskPath = $this->getParameter('kernel.project_dir') . '/public' . $attachment->getFilePath();
        if (file_exists($diskPath)) {
            unlink($diskPath);
        }

        $em->remove($attachment);
        $em->flush();

        return $this->json(['message' => 'Pièce jointe supprimée.']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(VehicleCreditPayment $payment, EntityManagerInterface $em, UtilisateurRepository $userRepo): JsonResponse
    {
        $this->assertBureauAccess($payment);
        $credit      = $payment->getVehicleCredit();
        $installment = $payment->getInstallment();
        $amount      = (float) $payment->getAmount();

        // Reverse installment payment
        if ($installment) {
            $newPaid      = max(0, (float) $installment->getAmountPaid() - $amount);
            $newRemaining = (float) $installment->getAmountDue() - $newPaid;
            $installment->setAmountPaid((string) $newPaid);
            $installment->setRemainingAmount((string) $newRemaining);
            if ($newPaid <= 0) {
                $installment->setStatus('pending');
                $installment->setPaidAt(null);
            } elseif ($newRemaining > 0) {
                $installment->setStatus('partial');
            }
        }

        $em->remove($payment);

        if ($credit) {
            // Add payment amount back to remaining balance
            $newBalance = (float) $credit->getRemainingBalance() + $amount;
            $credit->setRemainingBalance((string) round($newBalance, 2));
            $credit->setUpdatedAt(new \DateTimeImmutable());
            if ($credit->getStatus() === 'completed' && (float) $credit->getRemainingBalance() > 0) {
                $credit->setStatus('active');
                $credit->setUpdatedAt(new \DateTimeImmutable());
            }
        }

        // Create notification if the now-pending installment is due within 5 days
        if ($installment && $installment->getDueDate()) {
            $today    = new \DateTimeImmutable('today');
            $due      = \DateTimeImmutable::createFromInterface($installment->getDueDate());
            $daysLeft = (int) $today->diff($due)->format('%r%a');

            if ($daysLeft <= 5) {
                $voiture = $credit?->getVoiture();
                $bureau  = $voiture?->getBureau();
                $carName = $voiture ? trim(($voiture->getMarque() ?? '') . ' ' . ($voiture->getModele() ?? '')) : 'Véhicule';
                $title   = $daysLeft <= 0
                    ? "Échéance crédit aujourd'hui — {$carName}"
                    : "Crédit {$carName} — {$daysLeft}j avant échéance";
                $message = sprintf(
                    'Mensualité n°%d de %.2f MAD due le %s.',
                    $installment->getInstallmentNumber(),
                    (float) $installment->getAmountDue(),
                    $due->format('d/m/Y')
                );

                // Collect bureau-scoped users only
                $seen      = [];
                $recipients = [];
                $addUser = function ($user) use (&$seen, &$recipients): void {
                    if ($user && !isset($seen[$user->getId()])) {
                        $seen[$user->getId()] = true;
                        $recipients[] = $user;
                    }
                };
                if ($bureau?->getManager()?->isActif()) $addUser($bureau->getManager());
                if ($bureau) {
                    foreach ($userRepo->findBy(['bureau' => $bureau, 'actif' => true]) as $u) {
                        $addUser($u);
                    }
                }

                foreach ($recipients as $user) {
                    $existing = $em->createQueryBuilder()
                        ->select('COUNT(n.id)')
                        ->from(Notification::class, 'n')
                        ->where('n.user = :u')
                        ->andWhere('n.sourceType = :st')
                        ->andWhere('n.sourceId = :sid')
                        ->setParameter('u', $user)
                        ->setParameter('st', 'vehicle_credit_installment')
                        ->setParameter('sid', $installment->getId())
                        ->getQuery()->getSingleScalarResult();

                    if ((int) $existing > 0) continue;

                    $notif = new Notification();
                    $notif->setUser($user);
                    $notif->setTitle($title);
                    $notif->setMessage($message);
                    $notif->setType('credit_payment');
                    $notif->setSourceType('vehicle_credit_installment');
                    $notif->setSourceId($installment->getId());
                    $notif->setPriority($daysLeft <= 1 ? 'HIGH' : 'MEDIUM');
                    $notif->setDeepLink($voiture ? '/voiture/' . $voiture->getId() . '?tab=credit' : null);
                    $em->persist($notif);
                }
            }
        }

        $em->flush();

        return $this->json(['message' => 'Paiement supprimé']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findNextUnpaidInstallment(\App\Entity\VehicleCredit $credit): ?\App\Entity\VehicleCreditInstallment
    {
        foreach ($credit->getInstallments() as $inst) {
            if (!in_array($inst->getStatus(), ['paid'])) {
                return $inst;
            }
        }
        return null;
    }

    private function applyPaymentToInstallment(\App\Entity\VehicleCreditInstallment $inst, float $amount): void
    {
        $alreadyPaid  = (float) $inst->getAmountPaid();
        $due          = (float) $inst->getAmountDue();
        $newPaid      = min($due, $alreadyPaid + $amount);
        $newRemaining = max(0, $due - $newPaid);

        $inst->setAmountPaid((string) round($newPaid, 2));
        $inst->setRemainingAmount((string) round($newRemaining, 2));

        if ($newRemaining <= 0) {
            $inst->setStatus('paid');
            $inst->setPaidAt(new \DateTimeImmutable('today'));
        } elseif ($newPaid > 0) {
            $inst->setStatus('partial');
        }
    }

}
