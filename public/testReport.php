<?php
// TEMPORARY TEST FILE — delete after testing
$candidates = [
    '/usr/local/php8.4/bin/php',
    '/usr/local/php8.2/bin/php',
    '/usr/local/php8.1/bin/php',
    '/usr/bin/php8.4',
    '/usr/bin/php8.2',
    '/usr/bin/php',
];

$phpBin = null;
foreach ($candidates as $c) {
    if (file_exists($c)) { $phpBin = $c; break; }
}

if (!$phpBin) {
    echo 'Could not find PHP CLI binary. Tried: ' . implode(', ', $candidates);
    exit;
}

$cmd    = $phpBin . ' /home/agorenn/www/api/bin/console app:send-daily-report --force --triggered-by=manual 2>&1';
$output = [];
$code   = 0;

echo '<pre>PHP CLI: ' . htmlspecialchars($phpBin) . "\n\n";
exec($cmd, $output, $code);
echo 'Exit code: ' . $code . "\n\n" . htmlspecialchars(implode("\n", $output)) . '</pre>';
