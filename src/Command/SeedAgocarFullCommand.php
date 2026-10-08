<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-agocar-full', description: 'Seed all AGOCAR test data')]
class SeedAgocarFullCommand extends Command
{
    public function __construct(private Connection $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('AGOCAR Full Seed');

        $now      = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $today    = (new \DateTimeImmutable())->format('Y-m-d');
        $dateExp  = date('Y-m-d', strtotime('+8 months'));
        $dateExp2 = date('Y-m-d', strtotime('+2 months'));
        $d30ago   = date('Y-m-d H:i:s', strtotime('-30 days'));
        $d25ago   = date('Y-m-d H:i:s', strtotime('-25 days'));
        $d5ago    = date('Y-m-d H:i:s', strtotime('-5 days'));
        $d3ago    = date('Y-m-d H:i:s', strtotime('-3 days'));
        $d2future = date('Y-m-d H:i:s', strtotime('+2 days'));
        $d7future = date('Y-m-d H:i:s', strtotime('+7 days'));
        $d14fut   = date('Y-m-d H:i:s', strtotime('+14 days'));
        $d1month  = date('Y-m-d', strtotime('+1 month'));
        $d2month  = date('Y-m-d', strtotime('+2 months'));
        $d36month = date('Y-m-d', strtotime('+36 months'));
        $d4years  = date('Y-m-d', strtotime('+48 months'));

        $this->db->executeStatement("SET FOREIGN_KEY_CHECKS=0");

        // ── TRUNCATE ALL SEED TABLES ──────────────────────────────────────────
        foreach ([
            'vehicle_credit_document','vehicle_credit_payment','vehicle_credit_installment',
            'vehicle_credit_reminder','vehicle_credit','achat_installment','achat_voiture',
            'vente','credit','email_log','activity_log','notification','infraction',
            'vehicle_return_inspection','vehicle_delivery','paiement','historique_paiement',
            'contrat','contract_extension','reservation','reservation_accessoire',
            'accessoire','damage','suivi_technique','reparation','vidange','vignette','adblue',
            'assurance','depense','client','voiture_image','voiture','fournisseur',
            'financial_institution','conditions_contrat','parametres','company',
        ] as $t) {
            $this->db->executeStatement("TRUNCATE TABLE `$t`");
        }

        // ── COMPANY ──────────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO company (nom, cree_au) VALUES ('AGOCAR Maroc','$now')");
        $companyId = (int)$this->db->lastInsertId();
        $io->writeln("company=$companyId");

        // ── PARAMETRES ───────────────────────────────────────────────────────
        $this->db->executeStatement(
            "INSERT INTO parametres (company_name, city, currency, language) VALUES ('AGOCAR','Fes','MAD','fr')"
        );

        // ── CONDITIONS CONTRAT ────────────────────────────────────────────────
        $fr = $this->db->quote('Le locataire est responsable de tout dommage causé au véhicule pendant la durée de la location.');
        $ar = $this->db->quote('المستأجر مسؤول عن أي ضرر يلحق بالمركبة خلال فترة الإيجار.');
        $this->db->executeStatement("INSERT INTO conditions_contrat (texte_francais, texte_arabe) VALUES ($fr,$ar)");

        // ── FOURNISSEURS ──────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO fournisseur (raison_sociale, nom, prenom, telephone, email, adresse, cree_au) VALUES
            ('AutoPièces Fes','Hassan','Alami','+212661000001','autopièces@gmail.com','Fes, Maroc','$now'),
            ('Total Energie Fes','Karim','Tazi','+212661000002','total@fes.ma','Route Sefrou, Fes','$now')");
        $io->writeln("fournisseurs=1,2");

        // ── FINANCIAL INSTITUTION ─────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO financial_institution (name, type, contact_person, phone, email, status, created_at) VALUES
            ('CIH Bank','banque','Ahmed Benali','+212522000001','cih@cih.ma','active','$now')");
        $fiId = (int)$this->db->lastInsertId();
        $io->writeln("fi=$fiId");

        // ── ACCESSOIRES ───────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO accessoire (nom, prix, description, cree_au) VALUES
            ('GPS',50.00,'Navigateur GPS Garmin','$now'),
            ('Siège bébé',30.00,'Siège homologué 0-18kg','$now'),
            ('WiFi portable',40.00,'Routeur 4G illimité','$now')");
        $io->writeln("accessoires=1,2,3");

        // ── VOITURES (5) ─────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO voiture
            (marque, modele, immatriculation, annee, couleur, kilometrage_actuel, type_carburant,
             prix_jour, voiture_status, reservation_status, bureau_id, cree_au) VALUES
            ('Dacia','Sandero','12345-A-1',2022,'Blanc',45000,'essence',350.00,'disponible','confirmed',1,'$now'),
            ('Renault','Clio','23456-B-1',2021,'Rouge',62000,'essence',320.00,'louee','en_cours',1,'$now'),
            ('Peugeot','208','34567-C-1',2020,'Gris',81000,'diesel',300.00,'maintenance','confirmed',1,'$now'),
            ('Hyundai','i10','45678-D-1',2023,'Bleu',18000,'essence',280.00,'disponible','confirmed',1,'$now'),
            ('Toyota','Yaris','56789-E-1',2019,'Noir',110000,'diesel',260.00,'vendu','confirmed',1,'$now')");
        $io->writeln("voitures=1,2,3,4,5");

        // ── DEPENSES ASSURANCE (liées à assurance) ────────────────────────────
        $this->db->executeStatement("INSERT INTO depense (voiture_id,bureau_id,date_debut,date_fin,type_depense,montant,statut,cree_au)
            VALUES (1,1,'$today','$dateExp','assurance',3600.00,'payee','$now')");
        $depA1 = (int)$this->db->lastInsertId();

        $this->db->executeStatement("INSERT INTO depense (voiture_id,bureau_id,date_debut,date_fin,type_depense,montant,statut,cree_au)
            VALUES (2,1,'$today','$dateExp2','assurance',3200.00,'payee','$now')");
        $depA2 = (int)$this->db->lastInsertId();

        $this->db->executeStatement("INSERT INTO depense (voiture_id,bureau_id,date_debut,date_fin,type_depense,montant,statut,cree_au)
            VALUES (3,1,'$today','$dateExp','assurance',2800.00,'payee','$now')");
        $depA3 = (int)$this->db->lastInsertId();

        $this->db->executeStatement("INSERT INTO depense (voiture_id,bureau_id,date_debut,date_fin,type_depense,montant,statut,cree_au)
            VALUES (4,1,'$today','$dateExp','assurance',4100.00,'payee','$now')");
        $depA4 = (int)$this->db->lastInsertId();

        // ── ASSURANCES ────────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO assurance (depense_id, compagnie, type_assurance, numero_contrat, cree_au) VALUES
            ($depA1,'Wafa Assurance','tous risques','WA-2024-001','$now'),
            ($depA2,'Atlanta Assurance','tous risques','AT-2024-002','$now'),
            ($depA3,'AXA Maroc','tiers','AX-2024-003','$now'),
            ($depA4,'Wafa Assurance','tous risques','WA-2024-004','$now')");

        // ── DEPENSES VIDANGE ──────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO depense (voiture_id,bureau_id,date_debut,type_depense,montant,statut,cree_au)
            VALUES (1,1,'$today','vidange',280.00,'payee','$now')");
        $depVid1 = (int)$this->db->lastInsertId();

        $this->db->executeStatement("INSERT INTO depense (voiture_id,bureau_id,date_debut,type_depense,montant,statut,cree_au)
            VALUES (2,1,'$today','vidange',280.00,'payee','$now')");
        $depVid2 = (int)$this->db->lastInsertId();

        // ── VIDANGES ──────────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO vidange (depense_id, kilometrage_suivant, filtre_air, filtre_huile, filtre_carburant, cree_au) VALUES
            ($depVid1,50000,1,1,0,'$now'),
            ($depVid2,67000,0,1,0,'$now')");

        // ── DEPENSES VIGNETTE ─────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO depense (voiture_id,bureau_id,date_debut,date_fin,type_depense,montant,statut,cree_au)
            VALUES (1,1,'$today','$dateExp','vignette',400.00,'payee','$now')");
        $depVig1 = (int)$this->db->lastInsertId();

        $this->db->executeStatement("INSERT INTO depense (voiture_id,bureau_id,date_debut,date_fin,type_depense,montant,statut,cree_au)
            VALUES (2,1,'$today','$dateExp2','vignette',350.00,'payee','$now')");
        $depVig2 = (int)$this->db->lastInsertId();

        $this->db->executeStatement("INSERT INTO depense (voiture_id,bureau_id,date_debut,date_fin,type_depense,montant,statut,cree_au)
            VALUES (3,1,'$today','$dateExp','vignette',380.00,'payee','$now')");
        $depVig3 = (int)$this->db->lastInsertId();

        $this->db->executeStatement("INSERT INTO depense (voiture_id,bureau_id,date_debut,date_fin,type_depense,montant,statut,cree_au)
            VALUES (4,1,'$today','$dateExp','vignette',320.00,'payee','$now')");
        $depVig4 = (int)$this->db->lastInsertId();

        // ── VIGNETTES ─────────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO vignette (depense_id, annee, cree_au) VALUES
            ($depVig1,2024,'$now'),
            ($depVig2,2024,'$now'),
            ($depVig3,2024,'$now'),
            ($depVig4,2024,'$now')");

        // ── DEPENSES REPARATION ───────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO depense (voiture_id,bureau_id,date_debut,type_depense,montant,statut,cree_au)
            VALUES (3,1,'$today','reparation',650.00,'payee','$now')");
        $depRep1 = (int)$this->db->lastInsertId();

        $this->db->executeStatement("INSERT INTO depense (voiture_id,bureau_id,date_debut,type_depense,montant,statut,cree_au)
            VALUES (2,1,'$today','reparation',320.00,'payee','$now')");
        $depRep2 = (int)$this->db->lastInsertId();

        // ── REPARATIONS ───────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO reparation (depense_id, description_technique, cree_au) VALUES
            ($depRep1,'Remplacement plaquettes de frein','$now'),
            ($depRep2,'Vidange + filtre huile','$now')");

        // ── DEPENSE ADBLUE ────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO depense (voiture_id,bureau_id,date_debut,type_depense,montant,statut,cree_au)
            VALUES (1,1,'$today','adblue',105.00,'payee','$now')");
        $depAdblue = (int)$this->db->lastInsertId();

        // ── ADBLUE ────────────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO adblue (depense_id, quantite_litre, cree_au) VALUES ($depAdblue,10.5,'$now')");

        // ── DEPENSES GÉNÉRALES ────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO depense (voiture_id,bureau_id,date_debut,type_depense,description,montant,statut,cree_au) VALUES
            (1,1,'$today','carburant','Plein véhicule V1',350.00,'payee','$now'),
            (3,1,'$today','entretien','Révision complète V3',1200.00,'payee','$now'),
            (NULL,1,'$today','loyer','Loyer bureau Fes - Août 2024',8000.00,'payee','$now'),
            (NULL,1,'$today','salaire','Salaire agent Fes - Août 2024',4500.00,'payee','$now'),
            (NULL,1,'$today','publicite','Campagne Google Ads',1500.00,'payee','$now'),
            (NULL,1,'$today','divers','Fournitures bureau',320.00,'payee','$now')");

        // ── SUIVI TECHNIQUE ───────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO suivi_technique (voiture_id, cree_au) VALUES (1,'$now')");

        // ── DAMAGE ────────────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO damage (voiture_id, zone, description, severity, cree_au) VALUES
            (2,'portiere_avant_gauche','Rayure portière avant gauche','scratch','$now'),
            (3,'pare_chocs_arriere','Bosse pare-chocs arrière','dent','$now')");

        // ── CLIENTS (3) ───────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO client
            (nom, prenom, email, telephone, cin, permis_conduite, nationalite, date_naissance, adresse_maroc, cree_au) VALUES
            ('Benali','Rachid','rachid.benali@gmail.com','+212661111001','AB123456','P-112233','marocaine','1985-03-15','Fes, Maroc','$now'),
            ('Smith','John','john.smith@email.com','+33612345678',NULL,'B-9988776','française','1990-07-22','','$now'),
            ('Alami','Fatima','fatima.alami@gmail.com','+212661111003','CD789012','P-334455','marocaine','1992-11-08','Meknès, Maroc','$now')");
        $io->writeln("clients=1,2,3");

        // ── RESERVATIONS (4) ─────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO reservation
            (voiture_id, client_id, date_debut, date_fin, reservation_status, total, montant_paye, bureau_id, cree_au) VALUES
            (1,1,'$d30ago','$d25ago','terminee',2500.00,2500.00,1,'$now'),
            (2,2,'$d5ago','$d2future','en_cours',3200.00,1600.00,1,'$now'),
            (4,3,'$d7future','$d14fut','confirmed',1800.00,0.00,1,'$now'),
            (3,1,'$d25ago','$d5ago','annulee',0.00,0.00,1,'$now')");
        $io->writeln("reservations=1,2,3,4");

        // ── CONTRATS (2) ──────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO contrat (reservation_id, numero, has_caution, caution_montant, signed_at, cree_au) VALUES
            (1,'AGO-2024-000001',1,3000.00,'$d30ago','$now'),
            (2,'AGO-2024-000002',1,4000.00,'$d5ago','$now')");
        $io->writeln("contrats=1,2");

        // ── PAIEMENTS ─────────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO paiement (reservation_id, montant, date_paiement, mode_paiement, statut, cree_au) VALUES
            (1,2500.00,'$d30ago','especes','payee','$now'),
            (2,1600.00,'$d5ago','carte','payee','$now')");

        // ── HISTORIQUE PAIEMENT ───────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO historique_paiement (reservation_id, montant, date_paiement, mode_paiement, cree_au) VALUES
            (1,2500.00,'$d30ago','especes','$now'),
            (2,1600.00,'$d5ago','carte','$now')");

        // ── VEHICLE RETURN INSPECTION ─────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO vehicle_return_inspection (reservation_id, `condition`, inspected_at) VALUES
            (1,'clean','$d25ago')");

        // ── ACHAT VOITURE ─────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO achat_voiture
            (voiture_id, fournisseur_id, prix_achat, date_achat, type_financement, statut, cree_au) VALUES
            (4,1,85000.00,'$today','credit','actif','$now')");
        $achatId = (int)$this->db->lastInsertId();
        $io->writeln("achat=$achatId");

        // ── ACHAT INSTALLMENTS ────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO achat_installment
            (achat_voiture_id, installment_number, due_date, amount, amount_paid, status, cree_au) VALUES
            ($achatId,1,'$d1month',3200.00,0.00,'pending','$now'),
            ($achatId,2,'$d2month',3200.00,0.00,'pending','$now')");

        // ── VENTE ─────────────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO vente (voiture_id, prix_vente, date_vente, acheteur, bureau_id, cree_au) VALUES
            (5,65000.00,'$today','Mehdi Fassi',1,'$now')");

        // ── CREDIT ────────────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO credit
            (voiture_id, montant_total, mensualite, date_debut, date_fin, duree_mois, statut, cree_au) VALUES
            (3,120000.00,4500.00,'$today','$d36month',36,'en_cours','$now')");
        $creditId = (int)$this->db->lastInsertId();
        $io->writeln("credit=$creditId");

        // ── VEHICLE CREDIT ────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO vehicle_credit
            (voiture_id, financial_institution_id, vehicle_price, down_payment, financed_amount,
             interest_rate, duration_months, monthly_installment, total_cost,
             start_date, end_date, remaining_balance, status, created_at) VALUES
            (4,$fiId,85000.00,15000.00,70000.00,4.9,48,1640.00,78720.00,'$today','$d4years',68360.00,'active','$now')");
        $vcId = (int)$this->db->lastInsertId();
        $io->writeln("vehicle_credit=$vcId");

        // ── VEHICLE CREDIT PAYMENT ────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO vehicle_credit_payment
            (vehicle_credit_id, payment_type, payment_method, amount, payment_date, created_at) VALUES
            ($vcId,'installment','virement',1640.00,'$today','$now')");

        // ── VEHICLE CREDIT DOCUMENT ───────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO vehicle_credit_document
            (vehicle_credit_id, document_type, file_path, file_name, uploaded_at) VALUES
            ($vcId,'contrat','/uploads/credit/contrat-i10.pdf','contrat-i10.pdf','$now')");

        // ── INFRACTIONS ───────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO infraction (reservation_id, numero_infraction, type, date_saisie, prix, statut, cree_au) VALUES
            (1,1001,'excès de vitesse','$d25ago',700.00,'impayee','$now'),
            (2,1002,'stationnement interdit','$d3ago',200.00,'payee','$now')");

        // ── NOTIFICATIONS ─────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO notification (user_id, title, message, type, source_type, source_id, priority, created_at) VALUES
            (2,'Assurance V2 expire bientôt','Assurance du véhicule 23456-B-1 expire dans 60 jours','warning','voiture',2,'medium','$now'),
            (2,'Nouvelle réservation','Réservation #3 confirmée pour Fatima Alami','info','reservation',3,'low','$now'),
            (2,'Paiement reçu','Paiement de 1600 MAD reçu pour réservation #2','success','reservation',2,'low','$now')");

        // ── ACTIVITY LOG ──────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO activity_log (user_id, bureau_id, entity_type, entity_id, action, created_at) VALUES
            (2,1,'reservation',1,'CREATE','$now'),
            (2,1,'contrat',1,'CREATE','$now'),
            (2,1,'reservation',2,'UPDATE','$now'),
            (2,1,'voiture',1,'CREATE','$now'),
            (2,1,'client',1,'CREATE','$now'),
            (2,1,'reservation',4,'CANCEL','$now')");

        // ── EMAIL LOG ─────────────────────────────────────────────────────────
        $this->db->executeStatement("INSERT INTO email_log
            (recipient_email, subject, total_alerts, compliance_count, oil_count, credit_count, status, triggered_by, sent_at) VALUES
            ('rachid.benali@gmail.com','Confirmation réservation AGO-2024-000001',1,0,0,0,'sent','admin','$now'),
            ('john.smith@email.com','Bienvenue chez AGOCAR',1,0,0,0,'sent','admin','$now')");

        $this->db->executeStatement("SET FOREIGN_KEY_CHECKS=1");

        $io->success('Seed complet. voitures=1-5, clients=1-3, reservations=1-4, contrats=1-2');
        return Command::SUCCESS;
    }
}
