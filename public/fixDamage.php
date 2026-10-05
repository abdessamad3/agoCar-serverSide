<?php
// SECURITY: Delete this file immediately after running!
if (!isset($_GET['key']) || $_GET['key'] !== 'agocar-fix-2026') {
    http_response_code(403);
    die('Forbidden');
}

define('PROJECT_DIR', dirname(__DIR__));
require PROJECT_DIR . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(PROJECT_DIR . '/.env');

$kernel = new App\Kernel('prod', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();

$conn = $em->getConnection();

echo '<pre>';

// 1. Show current state
$maxId   = $conn->fetchOne('SELECT COALESCE(MAX(id), 0) FROM damage');
$count0  = $conn->fetchOne('SELECT COUNT(*) FROM damage WHERE id = 0');
$autoInc = $conn->fetchOne("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'damage'");

echo "Max id in damage table : $maxId\n";
echo "Rows with id=0         : $count0\n";
echo "AUTO_INCREMENT value   : $autoInc\n\n";

// 2. Delete any row with id=0
if ($count0 > 0) {
    $conn->executeStatement('DELETE FROM damage WHERE id = 0');
    echo "Deleted $count0 row(s) with id=0.\n";
}

// 3. Fix AUTO_INCREMENT if it is 0 or 1 (which would make next insert get id=0 or collide)
if ((int) $autoInc <= 1 || (int) $autoInc <= (int) $maxId) {
    $next = (int) $maxId + 1;
    $conn->executeStatement("ALTER TABLE damage AUTO_INCREMENT = $next");
    echo "Fixed AUTO_INCREMENT to $next.\n";
} else {
    echo "AUTO_INCREMENT looks fine.\n";
}

echo "\nDone.</pre>";
