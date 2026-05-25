@echo off
echo [SignageCMS] Applying media upload patch...
docker cp app\Models\Media.php signage_php:/var/www/html/app/Models/Media.php
docker exec signage_php mkdir -p /var/www/html/public/uploads/media/1
docker exec signage_php mkdir -p /var/www/html/public/uploads/thumbnails/1
docker exec signage_php chmod -R 755 /var/www/html/public/uploads/
echo [OK] Done! Refresh http://localhost/admin/media
pause
