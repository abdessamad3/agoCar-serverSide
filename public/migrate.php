<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// SECURITY: Delete this file immediately after running migrations!
if (!isset($_GET['key']) || $_GET['key'] !== 'agocar-migrate-2026') {
    http_response_code(403);
    die('Forbidden');
}

define('PROJECT_DIR', dirname(__DIR__));

require PROJECT_DIR . '/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->load(PROJECT_DIR . '/.env.local');

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] ?? 'prod';
$_SERVER['APP_DEBUG'] = '0';

use App\Kernel;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Bundle\FrameworkBundle\Console\Application;

try {
    $kernel = new Kernel('prod', false);
    $application = new Application($kernel);
    $application->setAutoExit(false);

    $input  = new ArrayInput(['command' => 'doctrine:migrations:migrate', '--no-interaction' => true]);
    $output = new BufferedOutput();

    $code = $application->run($input, $output);
    echo '<pre style="background:#1a1a1a;color:#0f0;padding:1rem;font-size:13px;">';
    echo htmlspecialchars($output->fetch());
    echo "\n✅ Done. Return code: $code";
    echo '</pre>';
} catch (\Throwable $e) {
    echo '<pre style="background:#1a1a1a;color:#f44;padding:1rem;">';
    echo '❌ Error: ' . htmlspecialchars($e->getMessage());
    echo '</pre>';
}
