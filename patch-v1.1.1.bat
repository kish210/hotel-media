@echo off
echo Applying patch v1.1.1...
docker cp app\Core\Database.php  signage_php:/var/www/html/app/Core/Database.php
docker cp app\Core\Response.php  signage_php:/var/www/html/app/Core/Response.php
echo [OK] Patch applied!
echo Refresh http://localhost to verify
pause
