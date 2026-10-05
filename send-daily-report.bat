@echo off
cd /d "d:\all my work\AYOUB\ayoubA.G.O\projet\fullstack\backend\autoloc"
"C:\xampp\php\php.exe" -d memory_limit=256M bin/console app:send-daily-report --triggered-by=scheduler >> "d:\all my work\AYOUB\ayoubA.G.O\projet\fullstack\backend\autoloc\var\log\daily-report.log" 2>&1
