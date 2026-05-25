@echo off
echo [SignageCMS] Fixing uploads serving - v3
echo.

echo [1/4] Copying Router.php (multi-segment path)...
docker cp app\Core\Router.php signage_php:/var/www/html/app/Core/Router.php

echo [2/4] Copying routes/web.php (upload route)...
docker cp routes\web.php signage_php:/var/www/html/routes/web.php

echo [3/4] Copying nginx.conf...
docker cp docker\nginx\nginx.conf signage_nginx:/etc/nginx/conf.d/default.conf

echo [4/4] Reloading nginx + fixing permissions...
docker exec signage_php sh -c "mkdir -p /var/www/html/public/uploads/media/1 /var/www/html/public/uploads/thumbnails/1 && chmod -R 777 /var/www/html/public/uploads"
docker exec signage_nginx nginx -t && docker exec signage_nginx nginx -s reload

echo.
echo [OK] Done! Upload a file and test.
pause
