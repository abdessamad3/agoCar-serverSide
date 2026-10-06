<?php

namespace App\Controller\Api;

use App\Entity\AchatInstallment;
use App\Repository\AchatInstallmentRepository;
use App\Repository\AchatVoitureRepository;
use App\Trait\BureauAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/achat-installment', name: 'app_api_achat_installment_')]
class AchatInstallmentController extends AbstractController
{
    use BureauAwareTrait;

    /** Bureau-locked staff/managers may only touch installments belonging to their own
     *  bureau. True admins (getEffectiveBureauId() === null) are unrestricted. */
    private function assertBureauAccess(AchatInstallment $installment): void
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId === null) return;

        if ($installment->getAchatVoiture()?->getVoiture()?->getBureau()?->getId() !== $bureauId) {
            throw $this->createNotFoundException('Échéance introuvable');
        }
    }

    private function serialize(AchatInstallment $i): array
    {
        $achat  = $i->getAchatVoiture();
        $voit   = $achat->getVoiture();
        $fournisseur = $achat->getFournisseur();
        return [
            'id'                => $i->getId(),
            'achatId'           => $achat->getId(),
            'num'               => $i->getInstallmentNumber(),
            'dueDate'           => $i->getDueDate()->format('Y-m-d'),
            'amount'            => (float) $i->getAmount(),
            'amountPaid'        => (float) $i->getAmountPaid(),
            'balance'           => (float) $i->getAmount() - (float) $i->getAmountPaid(),
            'status'            => $i->getStatus(),
            'paidDate'          => $i->getPaidAt()?->format('Y-m-d'),
            'notes'             => $i->getNotes(),
            'invoiceName'       => $i->getInvoiceName(),
            'voitureName'       => trim(($voit?->getMarque() ?? '') . ' ' . ($voit?->getModele() ?? '')),
            'immatriculation'   => $voit?->getImmatriculation(),
            'fournisseurName'   => $fournisseur?->getNom(),
            'creeAu'            => $i->getCreeAu()->format('Y-m-d H:i:s'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        AchatInstallmentRepository $repo,
        AchatVoitureRepository $achatRepo,
        EntityManagerInterface $em,
        Request $request
    ): JsonResponse {
        $bureauId = $this->getEffectiveBureauId();
        $achatId  = $request->query->get('achatId');

        if ($achatId) {
            $achat = $achatRepo->find((int) $achatId);
            if (!$achat) return $this->json(['error' => 'Achat introuvable.'], 404);
            // Auto-generate schedule if not yet created
            $repo->generateForAchat($achat, $em);
            $em->flush();
            $items = $repo->findByAchat($achat);
        } else {
            // Load all achats for this bureau, generate schedules, return all installments
            $qb = $em->createQueryBuilder()
                ->select('a')
                ->from(\App\Entity\AchatVoiture::class, 'a')
                ->join('a.voiture', 'v')
                ->where('a.deletedAt IS NULL');
            if ($bureauId) {
                $qb->andWhere('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
            }
            $achats = $qb->getQuery()->getResult();
            foreach ($achats as $achat) {
                $repo->generateForAchat($achat, $em);
            }
            $em->flush();

            // Now fetch all installments for those achats
            $qb2 = $repo->createQueryBuilder('i')
                ->join('i.achatVoiture', 'a')
                ->join('a.voiture', 'v')
                ->where('a.deletedAt IS NULL');
            if ($bureauId) {
                $qb2->andWhere('v.bureau = :bureauId')->setParameter('bureauId', $bureauId);
            }
            $items = $qb2->orderBy('i.dueDate', 'ASC')->getQuery()->getResult();
        }

        return $this->json(array_map(fn($i) => $this->serialize($i), $items));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(AchatInstallment $installment): JsonResponse
    {
        $this->assertBureauAccess($installment);
        return $this->json($this->serialize($installment));
    }

    #[Route('/{id}/pay', name: 'pay', methods: ['POST'])]
    public function pay(
        AchatInstallment $installment,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->assertBureauAccess($installment);
        $data = json_decode($request->getContent(), true);

        $paidAmount = (float) ($data['amountPaid'] ?? $installment->getAmount());
        $installment->setAmountPaid((string) $paidAmount);

        $total = (float) $installment->getAmount();
        if ($paidAmount >= $total) {
            $installment->setStatus('paid');
            $installment->setPaidAt(new \DateTimeImmutable($data['paidDate'] ?? 'now'));
        } elseif ($paidAmount > 0) {
            $installment->setStatus('partial');
        }

        if (isset($data['notes'])) $installment->setNotes($data['notes']);
        if (isset($data['invoiceName'])) $installment->setInvoiceName($data['invoiceName']);
        $installment->setEditAu(new \DateTimeImmutable());

        $em->flush();
        return $this->json(['message' => 'Paiement enregistré.']);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(AchatInstallment $installment, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($installment);
        $data = json_decode($request->getContent(), true);

        if (isset($data['status']))      $installment->setStatus($data['status']);
        if (isset($data['amountPaid']))  $installment->setAmountPaid((string) $data['amountPaid']);
        if (isset($data['paidDate']))    $installment->setPaidAt(!empty($data['paidDate']) ? new \DateTimeImmutable($data['paidDate']) : null);
        if (array_key_exists('notes', $data)) $installment->setNotes($data['notes'] ?: null);
        if (array_key_exists('invoiceName', $data)) $installment->setInvoiceName($data['invoiceName'] ?: null);
        $installment->setEditAu(new \DateTimeImmutable());

        $em->flush();
        return $this->json(['message' => 'Mise à jour effectuée.']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(AchatInstallment $installment, EntityManagerInterface $em): JsonResponse
    {
        $this->assertBureauAccess($installment);
        $em->remove($installment);
        $em->flush();
        return $this->json(['message' => 'Échéance supprimée.']);
    }
}
