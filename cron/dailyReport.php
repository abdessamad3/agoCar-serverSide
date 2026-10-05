<?php
$phpBin = '/usr/local/php8.4/bin/php';
$cmd    = $phpBin . ' /home/agorenn/www/api/bin/console app:send-daily-report --triggered-by=scheduler 2>&1';
$output = [];
$code   = 0;
exec($cmd, $output, $code);
