@echo off
echo ====================================
echo  SignageCMS - Module Migration
echo ====================================
echo.
echo در حال اجرای migration جداول ماژول‌ها...
echo.

docker exec -i signage_mysql mysql -u signage_user -pStrongPassword123! signage_cms < database\migrations\003_module_tables_only.sql

if %errorlevel% == 0 (
    echo.
    echo [OK] Migration با موفقیت اجرا شد!
    echo.
    echo جداول ایجادشده:
    echo   - fids_flights, fids_airlines
    echo   - hotel_events, hotel_amenities, hotel_info
    echo   - hotel_room_service, hotel_attractions
    echo   - corp_kpi, corp_news, corp_departments
    echo   - retail_products, retail_queue
    echo   - transport_schedules
) else (
    echo.
    echo [ERROR] خطا در اجرای migration
    echo دستور دستی:
    echo docker exec -i signage_mysql mysql -u signage_user -pStrongPassword123! signage_cms ^< database\migrations\003_module_tables_only.sql
)
echo.
pause
