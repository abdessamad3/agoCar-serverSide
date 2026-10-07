<?php

namespace App\Controller\Api;

use App\Entity\BookingRequest;
use App\Entity\Client;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Entity\Voiture;
use App\Service\ActivityLogService;
use App\Service\NotificationService;
use App\Service\ReservationPricingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Public, unauthenticated entry point for booking requests submitted from the
 * customer-facing website. This intentionally does NOT create a real Reservation —
 * a Reservation requires a Client with verified ID/license documents that a public
 * visitor can't be expected to submit online. Staff convert a BookingRequest into a
 * real Client + Reservation from the admin app after contacting the customer.
 */
#[Route('/api/site/booking-requests', name: 'app_api_public_booking_')]
class PublicBookingController extends AbstractController
{
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ActivityLogService $activityLog,
        MailerInterface $mailer,
        NotificationService $notifService,
        ReservationPricingService $pricing,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = [];
        foreach (['carId', 'dateDebut', 'dateFin', 'fullName', 'phone', 'email'] as $field) {
            if (empty($data[$field])) {
                $errors[$field] = 'Ce champ est requis.';
            }
        }
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalide.';
        }
        if (!empty($errors)) {
            return $this->json(['error' => 'VALIDATION_ERROR', 'fields' => $errors], 422);
        }

        try {
            $dateDebut = new \DateTimeImmutable($data['dateDebut']);
            $dateFin   = new \DateTimeImmutable($data['dateFin']);
        } catch (\Exception) {
            return $this->json(['error' => 'VALIDATION_ERROR', 'fields' => ['dateDebut' => 'Date invalide.']], 422);
        }
        if ($dateFin <= $dateDebut) {
            return $this->json(['error' => 'VALIDATION_ERROR', 'fields' => ['dateFin' => 'La date de fin doit suivre la date de début.']], 422);
        }
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Africa/Casablanca'));
        if ($dateDebut < $today) {
            return $this->json(['error' => 'VALIDATION_ERROR', 'fields' => ['dateDebut' => 'La date de début ne peut pas être dans le passé.']], 422);
        }

        $voiture = $em->getRepository(Voiture::class)->find((int) $data['carId']);
        if (!$voiture || $voiture->getDeletedAt() !== null) {
            return $this->json(['error' => 'CAR_NOT_AVAILABLE', 'message' => 'Ce véhicule n\'est plus disponible.'], 404);
        }

        // Check for date overlap with active (non-cancelled, non-terminated) reservations
        $overlap = $em->createQuery(
            'SELECT COUNT(r.id) FROM App\Entity\Reservation r
             WHERE r.voiture = :voiture
               AND r.reservationStatus NOT IN (:closed)
               AND r.dateDebut < :fin
               AND r.dateFin > :debut'
        )
            ->setParameter('voiture', $voiture)
            ->setParameter('closed', ['terminee', 'termine_avant_terme', 'annulee'])
            ->setParameter('debut', $dateDebut)
            ->setParameter('fin', $dateFin)
            ->getSingleScalarResult();

        if ($overlap > 0) {
            return $this->json(['error' => 'CAR_NOT_AVAILABLE', 'message' => 'Ce véhicule n\'est pas disponible sur cette période.'], 409);
        }

        $booking = new BookingRequest();
        $booking->setVoiture($voiture);
        $booking->setDateDebut($dateDebut);
        $booking->setDateFin($dateFin);
        $booking->setFullName(trim($data['fullName']));
        $booking->setPhone(trim($data['phone']));
        $booking->setEmail(trim($data['email']));
        $booking->setLieuLivraison(!empty($data['lieuLivraison']) ? trim($data['lieuLivraison']) : null);
        $booking->setMessage(!empty($data['message']) ? trim($data['message']) : null);
        $booking->setCreeAu(new \DateTimeImmutable());

        // ── Client ────────────────────────────────────────────────────────────
        $nameParts = explode(' ', trim($booking->getFullName()), 2);
        $prenom    = count($nameParts) > 1 ? $nameParts[0] : null;
        $nom       = count($nameParts) > 1 ? $nameParts[1] : $nameParts[0];

        $client = new Client();
        $client->setNom($nom);
        $client->setPrenom($prenom);
        $client->setEmail($booking->getEmail());
        $client->setTelephone($booking->getPhone());
        $client->setAdresseMaroc('');
        $client->setBureau($voiture->getBureau());
        $client->setCreeAu(new \DateTimeImmutable());

        // ── Reservation ───────────────────────────────────────────────────────
        // Same authoritative computation as the admin app -- so a public booking placed
        // during a seasonal rate period prices consistently with what staff will see.
        $computed = $pricing->computeTotal($voiture, $dateDebut, $dateFin, [], null);

        $reservation = new Reservation();
        $reservation->setClient($client);
        $reservation->setVoiture($voiture);
        $reservation->setDateDebut($dateDebut);
        $reservation->setDateFin($dateFin);
        $reservation->setTotal($computed['total']);
        $reservation->setPrixParJour($computed['prixParJour']);
        $reservation->setLieuLivraison($booking->getLieuLivraison());
        $reservation->setReservationStatus('pending');
        $reservation->setBureau($voiture->getBureau());
        $reservation->setMontantPaye('0.00');
        $reservation->setCreeAu(new \DateTimeImmutable());

        $em->persist($booking);
        $em->persist($client);
        $em->persist($reservation);
        $em->flush();

        $activityLog->logCreate('BookingRequest', $booking->getId(), [
            'voitureId' => $voiture->getId(),
            'dateDebut' => $dateDebut->format('Y-m-d'),
            'dateFin'   => $dateFin->format('Y-m-d'),
            'fullName'  => $booking->getFullName(),
            'phone'     => $booking->getPhone(),
            'email'     => $booking->getEmail(),
        ], $voiture->getBureau());

        // ── Send notification email to bureau staff ────────────────────────
        try {
            $recipients = $this->collectRecipients($em, $voiture);
            if (!empty($recipients)) {
                $nights = (int) $dateDebut->setTime(0, 0, 0)->diff($dateFin->setTime(0, 0, 0))->days;
                // Reuse the already-computed total (reflects seasonal rate rules) instead
                // of recomputing from the vehicle's raw base rate -- this is exactly the
                // "two places disagree" bug the whole pricing feature exists to prevent,
                // and it was live: this email used to show the unadjusted price while the
                // saved reservation had the correct, adjusted one.
                $total = (float) $computed['total'];
                $ref   = '#' . str_pad((string) $booking->getId(), 6, '0', STR_PAD_LEFT);

                $html  = $this->buildEmailHtml($booking, $voiture, $nights, $total, $ref, $reservation->getId(), (float) $computed['prixParJour']);
                $email = (new Email())
                    ->from($_ENV['MAILER_SENDER'] ?? 'noreply@agocar.ma')
                    ->subject("🚗 Nouvelle réservation {$ref} — {$voiture->getMarque()} {$voiture->getModele()}")
                    ->html($html);

                foreach ($recipients as $to) {
                    $email->addTo($to);
                }

                $mailer->send($email);
            }
        } catch (\Throwable) {
            // Email failure never blocks the booking response
        }

        // ── Send in-app notifications to bureau staff ─────────────────────────
        try {
            $bureauUsers = $this->collectBureauUsers($em, $voiture);
            if (!empty($bureauUsers)) {
                $ref      = '#' . str_pad((string) $booking->getId(), 6, '0', STR_PAD_LEFT);
                $nights   = (int) $dateDebut->setTime(0, 0, 0)->diff($dateFin->setTime(0, 0, 0))->days;
                $car      = trim(($voiture->getMarque() ?? '') . ' ' . ($voiture->getModele() ?? '') . ($voiture->getAnnee() ? ' ' . $voiture->getAnnee() : ''));
                $totalAmt = (float) $computed['total'];

                $notifTitle = "Nouvelle réservation {$ref}";
                $notifMsg   = "{$car} — {$dateDebut->format('d/m/Y')} → {$dateFin->format('d/m/Y')} ({$nights} j)";
                if ($totalAmt !== null) {
                    $notifMsg .= ' · ' . number_format($totalAmt, 0, ',', ' ') . ' MAD';
                }

                foreach ($bureauUsers as $bureauUser) {
                    $notifService->create(
                        $bureauUser,
                        NotificationService::TYPE_RESERVATION_CREATED,
                        'reservation',
                        $reservation->getId(),
                        $notifTitle,
                        $notifMsg,
                        NotificationService::PRIORITY_HIGH,
                        '/location/' . $reservation->getId(),
                    );
                }
            }
        } catch (\Throwable) {
            // Notification failure never blocks the booking response
        }

        return $this->json([
            'id'      => $booking->getId(),
            'message' => 'Votre demande a bien été reçue. Notre équipe vous contactera sous peu pour confirmer.',
        ], 201);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return Utilisateur[] deduplicated active users for the voiture's bureau */
    private function collectBureauUsers(EntityManagerInterface $em, Voiture $voiture): array
    {
        $bureau = $voiture->getBureau();
        $seen   = [];
        $users  = [];

        $addUser = function (Utilisateur $u) use (&$seen, &$users): void {
            if (!isset($seen[$u->getId()])) {
                $seen[$u->getId()] = true;
                $users[]           = $u;
            }
        };

        if ($bureau?->getManager()?->isActif()) {
            $addUser($bureau->getManager());
        }

        if ($bureau) {
            /** @var Utilisateur[] $staff */
            $staff = $em->getRepository(Utilisateur::class)->findBy(['bureau' => $bureau, 'actif' => true]);
            foreach ($staff as $u) {
                $addUser($u);
            }
        }

        return $users;
    }

    /** @return string[] deduplicated email addresses for the voiture's bureau */
    private function collectRecipients(EntityManagerInterface $em, Voiture $voiture): array
    {
        $bureau = $voiture->getBureau();
        $seen   = [];

        // Bureau manager
        if ($bureau?->getManager()?->getEmail() && $bureau->getManager()->isActif()) {
            $seen[$bureau->getManager()->getEmail()] = true;
        }

        // All active staff attached to this bureau
        if ($bureau) {
            /** @var Utilisateur[] $staff */
            $staff = $em->getRepository(Utilisateur::class)->findBy(['bureau' => $bureau, 'actif' => true]);
            foreach ($staff as $u) {
                if ($u->getEmail()) {
                    $seen[$u->getEmail()] = true;
                }
            }
        }

        return array_keys($seen);
    }

    private function buildEmailHtml(
        BookingRequest $b,
        Voiture $v,
        int $nights,
        ?float $total,
        string $ref,
        int $reservationId,
        ?float $effectiveDailyRate = null,
    ): string {
        $fmt = fn(?string $val): string => $val ?? '—';

        $car      = $fmt($v->getMarque()) . ' ' . $fmt($v->getModele()) . ($v->getAnnee() ? ' ' . $v->getAnnee() : '');
        $depart   = $b->getDateDebut()->format('d/m/Y');
        $retour   = $b->getDateFin()->format('d/m/Y');
        $nightStr = $nights . ' jour' . ($nights > 1 ? 's' : '');
        // Effective rate (reflects any active seasonal rule), not the vehicle's raw base
        // rate -- same reasoning as $total above.
        $rateForDisplay = $effectiveDailyRate ?? ($v->getPrixJour() !== null ? (float) $v->getPrixJour() : null);
        $prixJ    = $rateForDisplay !== null ? number_format($rateForDisplay, 0, ',', ' ') . ' MAD/j' : '—';
        $totalStr = $total !== null ? number_format($total, 0, ',', ' ') . ' MAD' : '—';
        $livraison = $b->getLieuLivraison() ? htmlspecialchars($b->getLieuLivraison()) : '—';
        $message   = $b->getMessage() ? nl2br(htmlspecialchars($b->getMessage())) : '<em style="color:#94a3b8">Aucun message</em>';
        $bureau    = $v->getBureau()?->getNom() ?? '—';

        $row = fn(string $label, string $value): string =>
            "<tr>
              <td style=\"padding:10px 16px;border-bottom:1px solid #f1f5f9;font-size:13px;color:#64748b;width:42%;white-space:nowrap\">{$label}</td>
              <td style=\"padding:10px 16px;border-bottom:1px solid #f1f5f9;font-size:13px;color:#1e293b;font-weight:600\">{$value}</td>
            </tr>";

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:system-ui,-apple-system,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:32px 16px">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%">

        <!-- Header -->
        <tr>
          <td style="background:#0f172a;border-radius:16px 16px 0 0;padding:28px 32px">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td>
                  <span style="font-size:22px;font-weight:800;color:#ffffff;letter-spacing:-0.5px">
                    AGO<span style="color:#fbbf24">CAR</span>
                  </span>
                  <p style="margin:4px 0 0;font-size:12px;color:#94a3b8;text-transform:uppercase;letter-spacing:2px">
                    Nouvelle demande de réservation
                  </p>
                </td>
                <td align="right">
                  <span style="display:inline-block;background:#fbbf24;color:#0f172a;font-size:20px;font-weight:800;
                               padding:8px 18px;border-radius:10px;letter-spacing:1px;font-family:monospace">
                    {$ref}
                  </span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="background:#ffffff;padding:0">

            <!-- Section: Véhicule & dates -->
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">
              <tr>
                <td colspan="2" style="padding:20px 32px 8px;font-size:11px;font-weight:700;color:#f59e0b;
                                       text-transform:uppercase;letter-spacing:2px;border-bottom:2px solid #fef3c7">
                  🚗 Véhicule &amp; Période
                </td>
              </tr>
              {$row('Véhicule', $car)}
              {$row('Bureau', $bureau)}
              {$row('Date de départ', $depart)}
              {$row('Date de retour', $retour)}
              {$row('Durée', $nightStr)}
              {$row('Prix / jour', $prixJ)}
              {$row('Estimation totale', "<span style=\"color:#f59e0b;font-size:16px\">{$totalStr}</span>")}
              {$row('Lieu de livraison', $livraison)}
            </table>

            <!-- Section: Client -->
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">
              <tr>
                <td colspan="2" style="padding:20px 32px 8px;font-size:11px;font-weight:700;color:#3b82f6;
                                       text-transform:uppercase;letter-spacing:2px;border-bottom:2px solid #dbeafe">
                  👤 Informations Client
                </td>
              </tr>
              {$row('Nom complet', htmlspecialchars($b->getFullName()))}
              {$row('Téléphone', htmlspecialchars($b->getPhone()))}
              {$row('Email', htmlspecialchars($b->getEmail()))}
            </table>

            <!-- Message -->
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">
              <tr>
                <td style="padding:20px 32px 8px;font-size:11px;font-weight:700;color:#8b5cf6;
                           text-transform:uppercase;letter-spacing:2px;border-bottom:2px solid #ede9fe">
                  💬 Message du client
                </td>
              </tr>
              <tr>
                <td style="padding:14px 32px 20px;font-size:13px;color:#475569;line-height:1.6">
                  {$message}
                </td>
              </tr>
            </table>

          </td>
        </tr>

        <!-- CTA -->
        <tr>
          <td style="background:#fafafa;padding:24px 32px;border-top:1px solid #f1f5f9">
            <p style="margin:0 0 12px;font-size:13px;color:#64748b;text-align:center">
              Connectez-vous à l'application pour traiter cette demande
            </p>
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td align="center">
                  <a href="https://agorent.com/admin/location/{$reservationId}"
                     style="display:inline-block;background:#fbbf24;color:#0f172a;font-size:14px;font-weight:700;
                            padding:12px 32px;border-radius:10px;text-decoration:none">
                    Ouvrir l'application →
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#0f172a;border-radius:0 0 16px 16px;padding:16px 32px;text-align:center">
            <p style="margin:0;font-size:11px;color:#475569">
              © {$b->getCreeAu()->format('Y')} AGOCAR · Cet email est généré automatiquement
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}
