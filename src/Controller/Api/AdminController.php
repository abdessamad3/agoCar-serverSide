<?php

namespace App\Controller\Api;

use App\Repository\CreditRepository;
use App\Repository\VoitureRepository;
use App\Service\ComplianceService;
use App\Service\OilChangeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin', name: 'app_api_admin_')]
#[IsGranted('ROLE_USER')]
class AdminController extends AbstractController
{
    public function __construct(
        private VoitureRepository $voitureRepo,
        private CreditRepository  $creditRepo,
        private ComplianceService $complianceService,
        private OilChangeService  $oilChangeService,
        private MailerInterface   $mailer,
        private string            $senderEmail,
        private string            $recipientEmail,
    ) {}

    #[Route('/test-email', name: 'test_email', methods: ['POST'])]
    public function testEmail(): JsonResponse
    {
        $compliance = $this->collectComplianceAlerts();
        $oil        = $this->collectOilAlerts();
        $credits    = $this->collectCreditAlerts();
        $total      = count($compliance) + count($oil) + count($credits);

        $html = $this->buildHtml($compliance, $oil, $credits, $total);

        $email = (new Email())
            ->from($this->senderEmail)
            ->to($this->recipientEmail)
            ->subject(sprintf('AGOCAR Test Report — %d alert(s) — %s', $total, date('d/m/Y H:i')))
            ->html($html);

        $this->mailer->send($email);

        return $this->json([
            'ok'        => true,
            'recipient' => $this->recipientEmail,
            'alerts'    => $total,
        ]);
    }

    // ── Data collectors ───────────────────────────────────────────────────────

    private function collectComplianceAlerts(): array
    {
        $alerts = [];
        foreach ($this->voitureRepo->findActive() as $voiture) {
            $label  = $voiture->getMarque() . ' ' . $voiture->getModele()
                    . ($voiture->getImmatriculation() ? ' (' . $voiture->getImmatriculation() . ')' : '');
            $status = $this->complianceService->getComplianceStatus($voiture);

            foreach (['assurance' => 'Assurance', 'vignette' => 'Vignette', 'visite' => 'Visite technique'] as $key => $docLabel) {
                $info = $status[$key];
                if (in_array($info['status'], [ComplianceService::WARNING, ComplianceService::CRITICAL, ComplianceService::EXPIRED], true)) {
                    $alerts[] = ['car' => $label, 'doc' => $docLabel, 'status' => $info['status'], 'days' => $info['daysRemaining'], 'expires' => $info['expiresAt']];
                }
            }
        }
        return $alerts;
    }

    private function collectOilAlerts(): array
    {
        $alerts = [];
        foreach ($this->voitureRepo->findActive() as $voiture) {
            $oil = $this->oilChangeService->getOilStatus($voiture);
            if (in_array($oil['status'], [OilChangeService::DUE_SOON, OilChangeService::OVERDUE], true)) {
                $alerts[] = [
                    'car'       => $voiture->getMarque() . ' ' . $voiture->getModele()
                                 . ($voiture->getImmatriculation() ? ' (' . $voiture->getImmatriculation() . ')' : ''),
                    'status'    => $oil['status'],
                    'remaining' => $oil['remainingKm'],
                    'nextAt'    => $oil['nextOilChangeKm'],
                ];
            }
        }
        return $alerts;
    }

    private function collectCreditAlerts(): array
    {
        $alerts = [];
        foreach ($this->creditRepo->findActive() as $credit) {
            $today         = new \DateTimeImmutable('today');
            $paymentsCount = $credit->getPaiements()->count();
            $dureeMois     = $credit->getDureeMois() ?? 0;
            if ($paymentsCount >= $dureeMois) continue;

            $nextDue   = $credit->getDateDebut()->modify("+{$paymentsCount} months");
            $diff      = (int) $today->diff($nextDue)->days;
            $remaining = $nextDue >= $today ? $diff : -$diff;
            if ($remaining > 7) continue;

            $voiture = $credit->getVoiture();
            $alerts[] = [
                'car'     => $voiture ? $voiture->getMarque() . ' ' . $voiture->getModele() : 'Credit #' . $credit->getId(),
                'status'  => $remaining <= 0 ? 'OVERDUE' : 'DUE_SOON',
                'amount'  => number_format((float) $credit->getMensualite(), 2),
                'dueDate' => $nextDue->format('d/m/Y'),
            ];
        }
        return $alerts;
    }

    // ── HTML builder (same as command) ────────────────────────────────────────

    private function buildHtml(array $compliance, array $oil, array $credits, int $total): string
    {
        $date = date('d/m/Y H:i');

        $html = <<<HTML
        <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
        <style>
          body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px;color:#333}
          .wrapper{max-width:640px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
          .header{background:#1a365d;color:#fff;padding:24px 28px}
          .header h1{margin:0;font-size:22px}
          .header p{margin:4px 0 0;font-size:13px;opacity:.85}
          .test-banner{background:#d69e2e;color:#fff;padding:8px 28px;font-size:12px;font-weight:600}
          .summary-bar{background:#2b6cb0;color:#fff;padding:10px 28px;font-size:13px}
          .content{padding:24px 28px}
          .section-title{font-size:15px;font-weight:bold;margin:20px 0 10px;padding-bottom:6px;border-bottom:2px solid #e2e8f0;color:#2d3748}
          .section-title:first-child{margin-top:0}
          table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:4px}
          th{background:#edf2f7;color:#4a5568;text-align:left;padding:8px 10px;font-weight:600}
          td{padding:8px 10px;border-bottom:1px solid #f0f0f0;vertical-align:top}
          tr:last-child td{border-bottom:none}
          .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700}
          .badge-expired{background:#fff5f5;color:#c53030;border:1px solid #fc8181}
          .badge-critical{background:#fffaf0;color:#c05621;border:1px solid #f6ad55}
          .badge-warning{background:#fffff0;color:#744210;border:1px solid #f6e05e}
          .badge-overdue{background:#fff5f5;color:#c53030;border:1px solid #fc8181}
          .badge-duesoon{background:#fffaf0;color:#c05621;border:1px solid #f6ad55}
          .no-issues{color:#718096;font-size:13px;font-style:italic;padding:6px 0}
          .footer{background:#f7fafc;padding:16px 28px;font-size:11px;color:#a0aec0;text-align:center;border-top:1px solid #e2e8f0}
        </style></head><body>
        <div class="wrapper">
          <div class="header">
            <h1>🚗 AGOCAR — Daily Alert Report</h1>
            <p>Test email sent on $date</p>
          </div>
          <div class="test-banner">⚠️ This is a TEST email — sent manually from the settings panel.</div>
          <div class="summary-bar">
            Total alerts: <strong>$total</strong>
            &nbsp;·&nbsp; Compliance: <strong>{$this->c($compliance)}</strong>
            &nbsp;·&nbsp; Oil changes: <strong>{$this->c($oil)}</strong>
            &nbsp;·&nbsp; Credit installments: <strong>{$this->c($credits)}</strong>
          </div>
          <div class="content">
        HTML;

        $html .= '<div class="section-title">🛡️ Document Compliance</div>';
        if ($compliance) {
            $html .= '<table><tr><th>Vehicle</th><th>Document</th><th>Status</th><th>Expires / Days</th></tr>';
            foreach ($compliance as $a) {
                $badge = match($a['status']) {
                    ComplianceService::EXPIRED  => '<span class="badge badge-expired">EXPIRED</span>',
                    ComplianceService::CRITICAL => '<span class="badge badge-critical">CRITICAL</span>',
                    default                     => '<span class="badge badge-warning">WARNING</span>',
                };
                $days = $a['days'] !== null ? htmlspecialchars((string)$a['days']) . 'd' : '—';
                $html .= "<tr><td>{$a['car']}</td><td>{$a['doc']}</td><td>$badge</td><td>{$a['expires']} ($days)</td></tr>";
            }
            $html .= '</table>';
        } else {
            $html .= '<p class="no-issues">✅ No compliance issues.</p>';
        }

        $html .= '<div class="section-title">🛢️ Oil Changes</div>';
        if ($oil) {
            $html .= '<table><tr><th>Vehicle</th><th>Status</th><th>Remaining km</th><th>Next service at</th></tr>';
            foreach ($oil as $a) {
                $badge = $a['status'] === OilChangeService::OVERDUE
                    ? '<span class="badge badge-overdue">OVERDUE</span>'
                    : '<span class="badge badge-duesoon">DUE SOON</span>';
                $html .= "<tr><td>{$a['car']}</td><td>$badge</td><td>{$a['remaining']} km</td><td>{$a['nextAt']} km</td></tr>";
            }
            $html .= '</table>';
        } else {
            $html .= '<p class="no-issues">✅ No oil change alerts.</p>';
        }

        $html .= '<div class="section-title">💳 Credit Installments</div>';
        if ($credits) {
            $html .= '<table><tr><th>Vehicle / Credit</th><th>Status</th><th>Due date</th><th>Amount</th></tr>';
            foreach ($credits as $a) {
                $badge = $a['status'] === 'OVERDUE'
                    ? '<span class="badge badge-overdue">OVERDUE</span>'
                    : '<span class="badge badge-duesoon">DUE SOON</span>';
                $html .= "<tr><td>{$a['car']}</td><td>$badge</td><td>{$a['dueDate']}</td><td>{$a['amount']} MAD</td></tr>";
            }
            $html .= '</table>';
        } else {
            $html .= '<p class="no-issues">✅ No credit installment alerts.</p>';
        }

        $html .= '</div><div class="footer">AGOCAR automated alert system — test email.</div></div></body></html>';

        return $html;
    }

    private function c(array $arr): int { return count($arr); }
}
