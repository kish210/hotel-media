Write-Host "[SignageCMS] Applying media upload patch..." -ForegroundColor Cyan

docker cp app\Models\Media.php signage_php:/var/www/html/app/Models/Media.php
docker exec signage_php mkdir -p /var/www/html/public/uploads/media/1
docker exec signage_php mkdir -p /var/www/html/public/uploads/thumbnails/1
docker exec signage_php sh -c "chmod -R 755 /var/www/html/public/uploads/"

Write-Host "[OK] Done!" -ForegroundColor Green
Write-Host "Refresh: http://localhost/admin/media" -ForegroundColor Yellow
