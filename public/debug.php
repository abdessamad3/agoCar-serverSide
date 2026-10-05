<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "step1: PHP OK<br>";

define('PROJECT_DIR', dirname(__DIR__));

require PROJECT_DIR . '/vendor/autoload.php';
echo "step2: autoload OK<br>";

try {
    (new Symfony\Component\Dotenv\Dotenv())->bootEnv(PROJECT_DIR . '/.env');
    echo "step3: env OK - APP_ENV=" . ($_ENV['APP_ENV'] ?? 'n/a') . "<br>";
} catch (\Throwable $e) {
    die('<b>ENV ERROR:</b> ' . $e->getMessage());
}

try {
    $kernel = new App\Kernel('prod', false);
    echo "step4: kernel created OK<br>";
    $kernel->boot();
    echo "step5: kernel booted OK<br>";
} catch (\Throwable $e) {
    die('<b>KERNEL ERROR:</b> ' . $e->getMessage() . '<br>' . nl2br($e->getTraceAsString()));
}
