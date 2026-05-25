@echo off
echo [SignageCMS] Fixing uploads - applying to Docker...
echo.

echo [1/4] Copying serve-upload.php to PHP container...
docker cp public\serve-upload.php signage_php:/var/www/html/public/serve-upload.php

echo [2/4] Copying nginx config...
docker cp docker\nginx\nginx.conf signage_nginx:/etc/nginx/conf.d/default.conf

echo [3/4] Creating upload folders with permissions...
docker exec signage_php sh -c "mkdir -p /var/www/html/public/uploads/media/1 && mkdir -p /var/www/html/public/uploads/thumbnails/1 && chmod -R 777 /var/www/html/public/uploads"

echo [4/4] Reloading nginx...
docker exec signage_nginx nginx -t && docker exec signage_nginx nginx -s reload

echo.
echo [OK] Done! Now test:
echo   http://localhost/admin/media
echo.
echo [Test upload file directly]:
echo   http://localhost/uploads/media/1/test.jpg
pause
