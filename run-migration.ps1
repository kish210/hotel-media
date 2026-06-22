# SignageCMS Module Migration
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host " SignageCMS - Module Migration" -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""

$sql = Get-Content "database\migrations\003_module_tables_only.sql" -Raw -Encoding UTF8

Write-Host "در حال اجرای migration..." -ForegroundColor Yellow

$sql | docker exec -i signage_mysql mysql -u signage_user -pStrongPassword123! signage_cms

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "[OK] Migration با موفقیت اجرا شد!" -ForegroundColor Green
    Write-Host ""
    Write-Host "جداول ایجادشده:" -ForegroundColor White
    Write-Host "  - fids_flights, fids_airlines" -ForegroundColor Gray
    Write-Host "  - hotel_events, hotel_amenities, hotel_info" -ForegroundColor Gray
    Write-Host "  - hotel_room_service, hotel_attractions" -ForegroundColor Gray
    Write-Host "  - corp_kpi, corp_news, corp_departments" -ForegroundColor Gray
    Write-Host "  - retail_products, retail_queue" -ForegroundColor Gray
    Write-Host "  - transport_schedules" -ForegroundColor Gray
    Write-Host ""
    Write-Host "الان http://localhost رو refresh کن" -ForegroundColor Cyan
} else {
    Write-Host "[ERROR] خطا در migration" -ForegroundColor Red
}
