<?php

namespace App\Controller\Api;

use App\Entity\PaiementDepense;
use App\Enum\PaymentTypeEnum;
use App\Enum\PaymentMethodEnum;
use App\Repository\DepenseRepository;
use App\Repository\PaiementDepenseRepository;
use App\Service\ActivityLogService;
use App\Service\DepensePaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/paiement-depense', name: 'app_api_paiement_depense_')]
class PaiementDepenseController extends AbstractController
{
    public function __construct(
        private DepensePaymentService     $paymentService,
        private PaiementDepenseRepository $paieRepo,
        private ActivityLogService        $activityLog,
        private SluggerInterface          $slugger,
    ) {}

    private function serialize(PaiementDepense $p): array
    {
        return [
            'id'               => $p->getId(),
            'montant'          => (float) $p->getMontant(),
            'datePaiement'     => $p->getDatePaiement()?->format('Y-m-d'),
            'note'             => $p->getNote(),
            'paymentType'      => $p->getPaymentType()?->value,
            'paymentMethod'    => $p->getPaymentMethod()?->value,
            'paymentReference' => $p->getPaymentReference(),
            'filePath'         => $p->getFilePath(),
            'depenseId'        => $p->getDepense()?->getId(),
            'creePar'          => $p->getCreePar()?->getEmail(),
            'creeAu'           => $p->getCreeAu()?->format('Y-m-d H:i:s'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $depenseId = (int) $request->query->get('depenseId', 0);
        if (!$depenseId) {
            return $this->json(['error' => 'depenseId is required'], 400);
        }
        $items = $this->paieRepo->findByDepense($depenseId);
        return $this->json(array_map(fn($p) => $this->serialize($p), $items));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, DepenseRepository $depenseRepo): JsonResponse
    {
        $depenseId = (int) $request->request->get('depenseId', 0);
        $depense   = $depenseRepo->find($depenseId);

        if (!$depense) {
            return $this->json(['error' => 'Dépense introuvable'], 404);
        }

        $montant     = (float) $request->request->get('montant', 0);
        $total       = (float) ($depense->getMontant() ?? 0);
        $alreadyPaid = (float) ($depense->getMontantPaye() ?? 0);
        $reste       = $total - $alreadyPaid;

        if ($montant <= 0) {
            return $this->json(['error' => 'Le montant doit être supérieur à 0'], 400);
        }
        if ($montant > $reste + 0.001) {
            return $this->json([
                'error' => sprintf('Le montant (%.2f) dépasse le restant dû (%.2f)', $montant, $reste)
            ], 422);
        }

        $datePaiementRaw = $request->request->get('datePaiement');
        $datePaiement = !empty($datePaiementRaw)
            ? new \DateTimeImmutable($datePaiementRaw)
            : new \DateTimeImmutable();

        $dateFacture = $depense->getDateFacture();
        if ($dateFacture && $datePaiement < $dateFacture) {
            return $this->json([
                'error' => 'La date de paiement ne peut pas être antérieure à la date de facture (' . $dateFacture->format('d/m/Y') . ')'
            ], 422);
        }

        $paiement = new PaiementDepense();
        $paiement->setDepense($depense);
        $paiement->setMontant((string) $montant);
        $paiement->setDatePaiement($datePaiement);
        $paiement->setNote($request->request->get('note'));
        $paiement->setPaymentType(PaymentTypeEnum::INSTALLMENT);

        $paymentMethodRaw = $request->request->get('paymentMethod');
        if ($paymentMethodRaw) {
            try { $paiement->setPaymentMethod(PaymentMethodEnum::from($paymentMethodRaw)); } catch (\ValueError $e) {}
        }

        $receiptFile = $request->files->get('receipt');
        if ($receiptFile) {
            $mime = $receiptFile->getMimeType();
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
            if (!in_array($mime, $allowedMimes, true)) {
                return $this->json(['error' => 'Type de fichier non autorisé (PDF, JPEG, PNG, WEBP uniquement)'], 422);
            }
            if ($receiptFile->getSize() > 10 * 1024 * 1024) {
                return $this->json(['error' => 'Le fichier ne doit pas dépasser 10 Mo'], 422);
            }
            $ext      = $receiptFile->guessExtension() ?? 'bin';
            $safeName = $this->slugger->slug(pathinfo($receiptFile->getClientOriginalName(), PATHINFO_FILENAME));
            $fileName = $safeName . '-' . uniqid() . '.' . $ext;
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/payment-receipts';
            try {
                $receiptFile->move($uploadDir, $fileName);
                $paiement->setFilePath('/uploads/payment-receipts/' . $fileName);
            } catch (FileException $e) {
                return $this->json(['error' => 'Erreur lors de l\'enregistrement du fichier'], 500);
            }
        }

        $paiement->setPaymentReference($this->paymentService->generateReference($em));
        $paiement->setCreeAu(new \DateTimeImmutable());
        $paiement->setCreePar($this->getUser());

        $em->persist($paiement);
        $em->flush();

        $this->paymentService->recalculate($depenseId, $em);

        $this->activityLog->logCreate('PaiementDepense', $paiement->getId(), [
            'depenseId'    => $depenseId,
            'montant'      => $paiement->getMontant(),
            'datePaiement' => $paiement->getDatePaiement()?->format('Y-m-d'),
            'paymentType'  => $paiement->getPaymentType()?->value,
            'note'         => $paiement->getNote(),
        ], $depense->getBureau());

        return $this->json(['message' => 'Paiement ajouté', 'id' => $paiement->getId()], 201);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(PaiementDepense $paiement, EntityManagerInterface $em): JsonResponse
    {
        $depenseId = $paiement->getDepense()?->getId();
        $bureau    = $paiement->getDepense()?->getBureau();
        $snap      = [
            'depenseId'    => $depenseId,
            'montant'      => $paiement->getMontant(),
            'datePaiement' => $paiement->getDatePaiement()?->format('Y-m-d'),
            'paymentType'  => $paiement->getPaymentType()?->value,
        ];
        $paiementId = $paiement->getId();

        $paiement->setDeletedAt(new \DateTimeImmutable());
        $paiement->setSupprimePar($this->getUser());
        $em->flush();

        if ($depenseId) {
            $this->paymentService->recalculate($depenseId, $em);
        }

        $this->activityLog->logDelete('PaiementDepense', $paiementId, $snap, $bureau);

        return $this->json(['message' => 'Paiement supprimé'], 200);
    }
}
