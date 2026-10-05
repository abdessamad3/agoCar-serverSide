<?php
if (!isset($_GET['key']) || $_GET['key'] !== 'agocar-fix-2026') {
    http_response_code(403);
    die('Forbidden');
}

$files = [
    dirname(__DIR__) . '/src/Controller/Api/VehicleDeliveryController.php',
    dirname(__DIR__) . '/src/Controller/Api/VehicleReturnInspectionController.php',
];

echo '<pre>';

if (!function_exists('opcache_invalidate')) {
    echo "OPcache not available — nothing to do.\n";
} else {
    foreach ($files as $f) {
        $result = opcache_invalidate($f, true);
        echo ($result ? '✓' : '✗') . ' ' . basename($f) . "\n";
    }
    opcache_reset();
    echo "OPcache reset done.\n";
}

echo '</pre>';
