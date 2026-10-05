<?php
$phpBin = '/usr/local/php8.4/bin/php';
$cmd = $phpBin . ' /home/agorenn/www/api/bin/console cache:clear --env=prod 2>&1';
exec($cmd, $output, $code);
echo '<pre>Exit code: ' . $code . "\n\n" . htmlspecialchars(implode("\n", $output)) . '</pre>';
