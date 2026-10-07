<?php

namespace App\Tests\Api;

use App\Entity\Bureau;
use App\Entity\Client;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Entity\Voiture;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Shared fixture helpers for the bureau-scoping integration tests. Every test
 * runs against the dedicated local test database (never dev, never prod) and
 * each test method's changes are left in place within that run -- tables are
 * truncated in setUp() so tests stay independent of each other and of
 * whatever was run before.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected EntityManagerInterface $em;
    protected KernelBrowser $httpClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();

        // Keep tests independent: wipe every table any fixture or controller action under
        // test can write to. Missing one here is a real trap -- TRUNCATE resets
        // auto_increment, so a later test's freshly-created row can reuse an id that an
        // untruncated table still has an orphaned row for (e.g. a Contrat left over from a
        // previous test's reservation #1 silently attaching itself to the next test's own
        // reservation #1), producing a failure that looks unrelated to what that test does.
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'reservation', 'voiture', 'client', 'utilisateur', 'bureau', 'company',
            'contrat', 'vehicle_delivery', 'vehicle_return_inspection', 'damage', 'paiement',
        ] as $table) {
            $conn->executeStatement("TRUNCATE TABLE `$table`");
        }
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function makeBureau(string $nom): Bureau
    {
        $bureau = new Bureau();
        $bureau->setNom($nom);
        $bureau->setStatut('actif');
        $bureau->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($bureau);
        $this->em->flush();

        return $bureau;
    }

    protected function makeUser(string $email, array $roles, ?Bureau $bureau): Utilisateur
    {
        $user = new Utilisateur();
        $user->setEmail($email);
        $user->setNom('Test');
        $user->setPrenom('User');
        $user->setRoles($roles);
        $user->setActif(true);
        $user->setBureau($bureau);
        $user->setCreeAu(new \DateTimeImmutable());

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'test-password'));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function makeVoiture(Bureau $bureau, string $immatriculation): Voiture
    {
        $voiture = new Voiture();
        $voiture->setMarque('Dacia');
        $voiture->setModele('Sandero');
        $voiture->setImmatriculation($immatriculation);
        $voiture->setBureau($bureau);
        $voiture->setAnnee(2024);
        // Non-zero: FleetLifecycleManager::VehicleSnapshot::isSetupIncomplete()
        // treats kilometrageActuel == 0 as "setup gate not cleared" (a real
        // operational vehicle always has a real odometer reading), which would
        // otherwise force a resync (TemporalSyncTriggered etc.) to resolve to
        // SETUP -- not a valid transition from the 'disponible' status below.
        $voiture->setKilometrageActuel(15000);
        $voiture->setTypeCarburant('essence');
        $voiture->setClimatisation(true);
        $voiture->setPrixJour('250.00');
        $voiture->setVoitureStatus('disponible');
        $voiture->setReservationStatus('disponible');
        $voiture->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($voiture);
        $this->em->flush();

        return $voiture;
    }

    protected function makeClientEntity(string $nom): Client
    {
        $client = new Client();
        $client->setNom($nom);
        $client->setTelephone('0600000000');
        $client->setBureau(null);
        $client->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($client);
        $this->em->flush();

        return $client;
    }

    protected function makeReservation(Voiture $voiture, Client $client, string $status = 'confirmed'): Reservation
    {
        $reservation = new Reservation();
        $reservation->setVoiture($voiture);
        $reservation->setClient($client);
        $reservation->setDateDebut(new \DateTimeImmutable('today'));
        $reservation->setDateFin(new \DateTimeImmutable('+2 days'));
        $reservation->setTotal('500.00');
        // Set before the first persist, same as any other initial field --
        // ReservationStatusWriteGuard only fires on updates to an already-
        // persisted row, so this does not need to go through
        // ReservationLifecycleManager::transition().
        $reservation->setReservationStatus($status);
        $reservation->setCreeAu(new \DateTimeImmutable());
        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
    }

    protected function assertForbiddenOrNotFound(Response $response, string $message = ''): void
    {
        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_NOT_FOUND, Response::HTTP_FORBIDDEN],
            $message ?: 'Expected cross-bureau access to be blocked (404/403), got ' . $response->getStatusCode() . "\n" . $response->getContent()
        );
    }
}
