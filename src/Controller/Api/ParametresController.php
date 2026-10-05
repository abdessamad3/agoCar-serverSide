<?php

namespace App\Controller\Api;

use App\Entity\Bureau;
use App\Entity\Parametres;
use App\Repository\BureauRepository;
use App\Repository\ParametresRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/parametres', name: 'app_api_parametres_')]
class ParametresController extends AbstractController
{
    private function getOrCreate(
        Bureau $bureau,
        ParametresRepository $repo,
        EntityManagerInterface $em
    ): Parametres {
        $p = $repo->findByBureau($bureau);
        if (!$p) {
            $p = new Parametres();
            $p->setBureau($bureau);
            $em->persist($p);
            $em->flush();
        }
        return $p;
    }

    #[Route('/{bureauId}', name: 'get', methods: ['GET'], requirements: ['bureauId' => '\d+'])]
    public function get(
        int $bureauId,
        BureauRepository $bureauRepo,
        ParametresRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $bureau = $bureauRepo->find($bureauId);
        if (!$bureau) {
            return $this->json(['error' => 'Bureau not found'], 404);
        }
        $p = $this->getOrCreate($bureau, $repo, $em);
        return $this->json($p->toArray());
    }

    #[Route('/{bureauId}', name: 'update', methods: ['PUT'], requirements: ['bureauId' => '\d+'])]
    public function update(
        int $bureauId,
        Request $request,
        BureauRepository $bureauRepo,
        ParametresRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $bureau = $bureauRepo->find($bureauId);
        if (!$bureau) {
            return $this->json(['error' => 'Bureau not found'], 404);
        }

        $p    = $this->getOrCreate($bureau, $repo, $em);
        $data = json_decode($request->getContent(), true) ?? [];

        // ── Localization ──────────────────────────────────────────────────────
        if (array_key_exists('currency', $data))         $p->setCurrency($data['currency']);
        if (array_key_exists('language', $data))         $p->setLanguage($data['language']);
        if (array_key_exists('dateFormat', $data))       $p->setDateFormat($data['dateFormat']);
        if (array_key_exists('timezone', $data))         $p->setTimezone($data['timezone']);
        if (array_key_exists('distanceUnit', $data))     $p->setDistanceUnit($data['distanceUnit']);
        if (array_key_exists('vatRate', $data))          $p->setVatRate((float) $data['vatRate']);
        if (array_key_exists('vatLabel', $data))         $p->setVatLabel($data['vatLabel']);
        if (array_key_exists('showVatBreakdown', $data)) $p->setShowVatBreakdown(filter_var($data['showVatBreakdown'], FILTER_VALIDATE_BOOLEAN));

        // ── Rental Rules ──────────────────────────────────────────────────────
        if (array_key_exists('minDuration', $data))           $p->setMinDuration((int) $data['minDuration']);
        if (array_key_exists('maxDuration', $data))           $p->setMaxDuration((int) $data['maxDuration']);
        if (array_key_exists('advanceBookingLimit', $data))   $p->setAdvanceBookingLimit((int) $data['advanceBookingLimit']);
        if (array_key_exists('defaultPickupTime', $data))     $p->setDefaultPickupTime($data['defaultPickupTime']);
        if (array_key_exists('defaultReturnTime', $data))     $p->setDefaultReturnTime($data['defaultReturnTime']);
        if (array_key_exists('gracePeriod', $data))           $p->setGracePeriod((float) $data['gracePeriod']);
        if (array_key_exists('defaultDeposit', $data))        $p->setDefaultDeposit((float) $data['defaultDeposit']);
        if (array_key_exists('lateReturnFee', $data))         $p->setLateReturnFee((float) $data['lateReturnFee']);
        if (array_key_exists('requireDeposit', $data))        $p->setRequireDeposit(filter_var($data['requireDeposit'], FILTER_VALIDATE_BOOLEAN));
        if (array_key_exists('allowPartialPayments', $data))  $p->setAllowPartialPayments(filter_var($data['allowPartialPayments'], FILTER_VALIDATE_BOOLEAN));
        if (array_key_exists('autoGenerateContract', $data))  $p->setAutoGenerateContract(filter_var($data['autoGenerateContract'], FILTER_VALIDATE_BOOLEAN));
        if (array_key_exists('autoGenerateInvoice', $data))   $p->setAutoGenerateInvoice(filter_var($data['autoGenerateInvoice'], FILTER_VALIDATE_BOOLEAN));
        if (array_key_exists('requireSignature', $data))      $p->setRequireSignature(filter_var($data['requireSignature'], FILTER_VALIDATE_BOOLEAN));
        if (array_key_exists('contractFooterNote', $data))    $p->setContractFooterNote($data['contractFooterNote']);

        // ── Debt Rules ────────────────────────────────────────────────────────
        if (array_key_exists('debtBlockThreshold', $data)) $p->setDebtBlockThreshold((float) $data['debtBlockThreshold']);
        if (array_key_exists('debtBlockEnabled', $data))   $p->setDebtBlockEnabled(filter_var($data['debtBlockEnabled'], FILTER_VALIDATE_BOOLEAN));

        // ── Notifications ─────────────────────────────────────────────────────
        if (array_key_exists('notifNewReservation', $data))    $p->setNotifNewReservation(filter_var($data['notifNewReservation'], FILTER_VALIDATE_BOOLEAN));
        if (array_key_exists('notifContractActivated', $data)) $p->setNotifContractActivated(filter_var($data['notifContractActivated'], FILTER_VALIDATE_BOOLEAN));
        if (array_key_exists('notifReturnOverdue', $data))     $p->setNotifReturnOverdue(filter_var($data['notifReturnOverdue'], FILTER_VALIDATE_BOOLEAN));
        if (array_key_exists('notifPaymentReceived', $data))   $p->setNotifPaymentReceived(filter_var($data['notifPaymentReceived'], FILTER_VALIDATE_BOOLEAN));
        if (array_key_exists('notifMaintenanceDue', $data))    $p->setNotifMaintenanceDue(filter_var($data['notifMaintenanceDue'], FILTER_VALIDATE_BOOLEAN));
        if (array_key_exists('notifInsuranceExpiry', $data))   $p->setNotifInsuranceExpiry(filter_var($data['notifInsuranceExpiry'], FILTER_VALIDATE_BOOLEAN));
        if (array_key_exists('notifInspectionDue', $data))     $p->setNotifInspectionDue(filter_var($data['notifInspectionDue'], FILTER_VALIDATE_BOOLEAN));
        if (array_key_exists('adminAlertEmail', $data))        $p->setAdminAlertEmail($data['adminAlertEmail']);
        if (array_key_exists('operationsEmail', $data))        $p->setOperationsEmail($data['operationsEmail']);
        if (array_key_exists('financeAlertEmail', $data))      $p->setFinanceAlertEmail($data['financeAlertEmail']);

        $em->flush();

        $result = $p->toArray();
        $result['bureauNom'] = $bureau->getNom();
        return $this->json($result);
    }
}
