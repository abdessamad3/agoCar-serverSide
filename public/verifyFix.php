<?php
if (!isset($_GET['key']) || $_GET['key'] !== 'agocar-fix-2026') {
    http_response_code(403); die('Forbidden');
}

$base = dirname(__DIR__) . '/src/Controller/Api/';
$files = [
    'VehicleDeliveryController.php',
    'VehicleReturnInspectionController.php',
];

echo '<pre>';
foreach ($files as $f) {
    $path    = $base . $f;
    $content = file_get_contents($path);
    // Old code had: $em->flush(); immediately after the foreach remove block and before the foreach damages block
    $hasOldFlush = (bool) preg_match('/remove\(\$existing\);\s*\}\s*\$em->flush\(\);\s*\n\s*foreach \(\$data\[/', $content);
    echo $f . ': ' . ($hasOldFlush ? '❌ OLD (mid-flush still present)' : '✅ NEW (fixed)') . "\n";
}
echo '</pre>';
