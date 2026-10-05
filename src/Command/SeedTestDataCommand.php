<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Depense;
use App\Entity\Reservation;
use App\Entity\Voiture;
use App\Enum\StatusEnum;
use App\Repository\BureauRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-test-data',
    description: 'Seed 50 vehicles, 100 clients, 300 reservations, 500 expenses for load testing',
)]
class SeedTestDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private BureauRepository       $bureauRepo,
        private UtilisateurRepository  $userRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Required in prod to confirm intent');
        $this->addOption('batch', null, InputOption::VALUE_OPTIONAL, 'Flush every N records', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($_ENV['APP_ENV'] === 'prod' && !$input->getOption('force')) {
            $io->error('Running in prod requires --force flag. Aborting.');
            return Command::FAILURE;
        }

        $bureaux = $this->bureauRepo->findAll();
        if (empty($bureaux)) {
            $io->error('No bureaux found. Create at least one bureau first.');
            return Command::FAILURE;
        }

        $admin = $this->userRepo->findOneBy([]);
        if (!$admin) {
            $io->error('No utilisateur found. Create the admin account first.');
            return Command::FAILURE;
        }

        $batch = max(1, (int) $input->getOption('batch'));
        $now   = new \DateTimeImmutable();

        // ── 1. VEHICLES (50) ────────────────────────────────────────────────
        $io->section('Creating 50 vehicles…');
        $cars = [];

        $specs = [
            ['Dacia',     'Logan',        'essence',  'manuelle',  'blanc',  5, 4, 80,  '250', 2019],
            ['Dacia',     'Sandero',      'essence',  'manuelle',  'rouge',  5, 4, 90,  '280', 2020],
            ['Dacia',     'Duster',       'diesel',   'manuelle',  'gris',   5, 4, 110, '350', 2021],
            ['Renault',   'Clio',         'essence',  'manuelle',  'blanc',  5, 4, 90,  '300', 2020],
            ['Renault',   'Symbol',       'essence',  'manuelle',  'noir',   5, 4, 75,  '260', 2018],
            ['Renault',   'Kangoo',       'diesel',   'manuelle',  'blanc',  5, 4, 95,  '320', 2019],
            ['Hyundai',   'i10',          'essence',  'manuelle',  'bleu',   5, 4, 67,  '220', 2022],
            ['Hyundai',   'i20',          'essence',  'manuelle',  'gris',   5, 4, 84,  '270', 2021],
            ['Kia',       'Picanto',      'essence',  'manuelle',  'blanc',  5, 4, 69,  '230', 2022],
            ['Kia',       'Rio',          'essence',  'manuelle',  'rouge',  5, 4, 84,  '280', 2020],
            ['Volkswagen','Polo',         'essence',  'automatique','noir',  5, 4, 95,  '350', 2021],
            ['Volkswagen','Golf',         'essence',  'automatique','blanc', 5, 4, 110, '420', 2020],
            ['Volkswagen','Tiguan',       'diesel',   'automatique','gris',  5, 4, 150, '550', 2022],
            ['Seat',      'Ibiza',        'essence',  'manuelle',  'blanc',  5, 4, 95,  '320', 2021],
            ['Seat',      'Leon',         'essence',  'automatique','noir',  5, 4, 110, '400', 2020],
            ['Peugeot',   '208',          'essence',  'manuelle',  'rouge',  5, 4, 100, '340', 2022],
            ['Peugeot',   '301',          'diesel',   'manuelle',  'blanc',  5, 4, 92,  '290', 2019],
            ['Citroen',   'C3',           'essence',  'manuelle',  'bleu',   5, 4, 83,  '280', 2020],
            ['Toyota',    'Yaris',        'hybride',  'automatique','blanc', 5, 4, 92,  '380', 2023],
            ['Toyota',    'Corolla',      'hybride',  'automatique','gris',  5, 4, 122, '450', 2022],
            ['Suzuki',    'Swift',        'essence',  'manuelle',  'rouge',  5, 4, 83,  '270', 2021],
            ['Suzuki',    'Vitara',       'diesel',   'automatique','blanc', 5, 4, 120, '480', 2022],
            ['Ford',      'Fiesta',       'essence',  'manuelle',  'noir',   5, 4, 85,  '310', 2019],
            ['Ford',      'Focus',        'essence',  'automatique','blanc', 5, 4, 125, '420', 2020],
            ['Skoda',     'Fabia',        'essence',  'manuelle',  'gris',   5, 4, 95,  '320', 2021],
            ['Opel',      'Corsa',        'essence',  'manuelle',  'bleu',   5, 4, 75,  '260', 2020],
            ['Nissan',    'Micra',        'essence',  'manuelle',  'blanc',  5, 4, 71,  '240', 2019],
            ['Nissan',    'Qashqai',      'diesel',   'automatique','gris',  5, 4, 150, '520', 2022],
            ['Honda',     'Jazz',         'hybride',  'automatique','blanc', 5, 4, 109, '400', 2021],
            ['Mercedes',  'A180',         'essence',  'automatique','noir',  5, 4, 136, '600', 2021],
            ['BMW',       '118i',         'essence',  'automatique','blanc', 5, 4, 136, '650', 2021],
            ['Audi',      'A3',           'essence',  'automatique','gris',  5, 4, 150, '700', 2022],
            ['Dacia',     'Logan MCV',    'diesel',   'manuelle',  'blanc',  5, 5, 90,  '300', 2019],
            ['Dacia',     'Dokker',       'diesel',   'manuelle',  'blanc',  5, 4, 90,  '310', 2020],
            ['Renault',   'Megane',       'diesel',   'manuelle',  'noir',   5, 4, 110, '380', 2020],
            ['Renault',   'Captur',       'essence',  'automatique','rouge', 5, 4, 130, '480', 2022],
            ['Hyundai',   'Tucson',       'diesel',   'automatique','gris',  5, 4, 185, '600', 2022],
            ['Kia',       'Sportage',     'diesel',   'automatique','blanc', 5, 4, 185, '600', 2021],
            ['Toyota',    'RAV4',         'hybride',  'automatique','gris',  5, 4, 222, '750', 2023],
            ['Ford',      'Kuga',         'diesel',   'automatique','noir',  5, 4, 150, '550', 2021],
            ['Volkswagen','T-Roc',        'essence',  'automatique','blanc', 5, 4, 150, '580', 2022],
            ['Seat',      'Ateca',        'diesel',   'automatique','gris',  5, 4, 150, '560', 2021],
            ['Peugeot',   '3008',         'diesel',   'automatique','blanc', 5, 4, 130, '520', 2021],
            ['Citroen',   'C5 Aircross',  'diesel',   'automatique','noir',  5, 4, 130, '500', 2022],
            ['Skoda',     'Octavia',      'essence',  'automatique','blanc', 5, 4, 150, '480', 2021],
            ['Opel',      'Astra',        'diesel',   'manuelle',  'gris',   5, 4, 110, '360', 2020],
            ['Mercedes',  'CLA 200',      'essence',  'automatique','noir',  5, 4, 163, '750', 2022],
            ['BMW',       '320i',         'essence',  'automatique','blanc', 5, 4, 184, '800', 2022],
            ['Audi',      'Q3',           'diesel',   'automatique','gris',  5, 4, 150, '720', 2023],
            ['Dacia',     'Spring',       'electrique','automatique','blanc',5, 4, 45,  '280', 2023],
        ];

        $immatPrefixes = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B'];
        foreach ($specs as $i => [$marque, $modele, $carb, $trans, $couleur, $places, $portes, $cv, $prix, $annee]) {
            $car = new Voiture();
            $car->setMarque($marque);
            $car->setModele($modele);
            $car->setTypeCarburant($carb);
            $car->setTransmission($trans);
            $car->setCouleur($couleur);
            $car->setPlaces($places);
            $car->setPortes($portes);
            $car->setPuissanceCv($cv);
            $car->setPrixJour($prix);
            $car->setAnnee($annee);
            $car->setKilometrageActuel(rand(5000, 85000));
            $car->setImmatriculation(sprintf('%d-%s-%04d', rand(1, 9), $immatPrefixes[array_rand($immatPrefixes)], ($i + 1) * 37 + rand(0, 99)));
            $car->setVoitureStatus('disponible');
            $car->setBureau($bureaux[array_rand($bureaux)]);
            $car->setCreeAu(new \DateTimeImmutable());
            $car->setCreePar($admin);
            $this->em->persist($car);
            $cars[] = $car;

            if (($i + 1) % $batch === 0) { $this->em->flush(); }
        }
        $this->em->flush();
        $io->success('50 vehicles created.');

        // ── 2. CLIENTS (100) ────────────────────────────────────────────────
        $io->section('Creating 100 clients…');
        $clients = [];

        $firstNames = ['Mohamed','Ahmed','Youssef','Hassan','Karim','Omar','Amine','Rachid','Khalid','Mehdi',
                       'Fatima','Khadija','Aicha','Zineb','Samira','Nadia','Laila','Houda','Imane','Souad',
                       'Ali','Hamza','Tarik','Nabil','Samir','Abderrahim','Abdelaziz','Hicham','Ayoub','Soufiane',
                       'Meryem','Salma','Hajar','Rim','Sara','Amira','Loubna','Hafsa','Widad','Rajae'];
        $lastNames  = ['Alaoui','Benali','Chraibi','Doukkali','El Fassi','Fassi','Gharbi','Hakimi','Idrissi',
                       'Jamai','Kettani','Lahmidi','Moussaoui','Naji','Ouazzani','Qacemi','Rahmouni','Skalli',
                       'Tazi','Uribi','Vaillant','Wahidi','Xenakis','Yaacoubi','Zaidi','Benbrahim','Chaoui',
                       'Drissi','El Haddad','Filali'];

        for ($i = 0; $i < 100; $i++) {
            $fn = $firstNames[array_rand($firstNames)];
            $ln = $lastNames[array_rand($lastNames)];

            $client = new Client();
            $client->setNom("$fn $ln");
            $client->setCin(sprintf('%s%06d', ['A','B','C','D','G','H','J','K','L','M','N','Q','S','T','U','V','W','X','Y','Z'][array_rand(['A','B','C','D','G','H','J','K','L','M','N','Q','S','T','U','V','W','X','Y','Z'])], rand(100000, 999999)));
            $client->setTelephone(rand(600000000, 699999999));
            $client->setNationalite('Marocaine');
            $client->setPermisConduite(sprintf('M%07d', rand(1000000, 9999999)));
            $client->setCreeAu(new \DateTimeImmutable(sprintf('-%d days', rand(0, 730))));
            $client->setCreePar($admin);
            $this->em->persist($client);
            $clients[] = $client;

            if (($i + 1) % $batch === 0) { $this->em->flush(); }
        }
        $this->em->flush();
        $io->success('100 clients created.');

        // ── 3. RESERVATIONS (300) ───────────────────────────────────────────
        $io->section('Creating 300 reservations…');
        $statuses   = ['confirmed', 'completed', 'cancelled', 'confirmed', 'completed', 'completed'];

        for ($i = 0; $i < 300; $i++) {
            $car    = $cars[array_rand($cars)];
            $client = $clients[array_rand($clients)];
            $days   = rand(1, 14);
            $start  = new \DateTimeImmutable(sprintf('-%d days', rand(0, 365)));
            $end    = $start->modify("+$days days");
            $status = $statuses[array_rand($statuses)];
            $prix   = (float) ($car->getPrixJour() ?? 300);
            $total  = round($prix * $days, 2);

            $res = new Reservation();
            $res->setClient($client);
            $res->setVoiture($car);
            $res->setDateDebut($start);
            $res->setDateFin($end);
            $res->setTotal((string) $total);
            $res->setReservationStatus($status);
            $res->setMontantPaye($status === 'completed' ? (string) $total : (string) round($total * rand(0, 100) / 100, 2));
            $res->setCreeAu(new \DateTimeImmutable());
            $this->em->persist($res);

            if (($i + 1) % $batch === 0) { $this->em->flush(); }
        }
        $this->em->flush();
        $io->success('300 reservations created.');

        // ── 4. EXPENSES (500) ───────────────────────────────────────────────
        $io->section('Creating 500 expenses…');
        $expTypes = [
            ['carburant',   50,  300],
            ['entretien',   200, 1500],
            ['reparation',  300, 4000],
            ['lavage',      30,  100],
            ['parking',     20,  200],
            ['peage',       10,  80],
            ['pneumatique', 200, 1200],
            ['divers',      50,  500],
        ];

        for ($i = 0; $i < 500; $i++) {
            [$type, $min, $max] = $expTypes[array_rand($expTypes)];
            $montant = rand($min * 10, $max * 10) / 10;
            $car     = $cars[array_rand($cars)];
            $date    = new \DateTimeImmutable(sprintf('-%d days', rand(0, 365)));
            $paid    = rand(0, 1) === 1;

            $dep = new Depense();
            $dep->setVoiture($car);
            $dep->setBureau($car->getBureau());
            $dep->setTypeDepense($type);
            $dep->setMontant((string) $montant);
            $dep->setMontantPaye($paid ? (string) $montant : '0');
            $dep->setStatut($paid ? StatusEnum::PAYEE : StatusEnum::IMPAYE);
            $dep->setDate($date);
            $dep->setCreeAu(new \DateTimeImmutable());
            $dep->setCreePar($admin);
            $this->em->persist($dep);

            if (($i + 1) % $batch === 0) { $this->em->flush(); }
        }
        $this->em->flush();
        $io->success('500 expenses created.');

        $io->success('Seed complete: 50 vehicles, 100 clients, 300 reservations, 500 expenses.');
        return Command::SUCCESS;
    }
}
