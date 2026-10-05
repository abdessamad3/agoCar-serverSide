<?php
if (($_GET['key'] ?? '') !== 'agocar-debug-2026') { die('forbidden'); }

define('PROJECT_DIR', dirname(__DIR__));
require PROJECT_DIR . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(PROJECT_DIR . '/.env');

$kernel = new App\Kernel('prod', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();

header('Content-Type: text/plain');

$voitures = $em->getRepository(App\Entity\Voiture::class)->findAll();

echo "Total voiture rows: " . count($voitures) . "\n\n";
foreach ($voitures as $v) {
    echo sprintf(
        "id=%d  %s %s  |  status=%s  |  deletedAt=%s\n",
        $v->getId(),
        $v->getMarque(),
        $v->getModele(),
        $v->getVoitureStatus() ?? '(null)',
        $v->getDeletedAt() ? $v->getDeletedAt()->format('Y-m-d') : 'NULL (not deleted)'
    );
}
