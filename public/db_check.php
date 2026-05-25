<?php
try {
    $pdo = new PDO("mysql:host=mysql;dbname=signage_cms;charset=utf8mb4",
        "signage_user", "StrongPassword123!", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]);

    // بررسی ستون‌های screens
    $cols = $pdo->query("DESCRIBE screens")->fetchAll(PDO::FETCH_COLUMN);
    echo "=== ستون‌های جدول screens ===\n";
    foreach ($cols as $c) echo "  - $c\n";

    // بررسی screen_groups
    echo "\n=== جدول screen_groups ===\n";
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM screen_groups")->fetchColumn();
        echo "  وجود دارد — $count رکورد\n";
    } catch(Exception $e) {
        echo "  ❌ وجود ندارد: " . $e->getMessage() . "\n";
    }

    // بررسی iptv_channels
    echo "\n=== جدول iptv_channels ===\n";
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM iptv_channels")->fetchColumn();
        echo "  وجود دارد — $count رکورد\n";
    } catch(Exception $e) {
        echo "  ❌ وجود ندارد\n";
    }

    // بررسی apk_versions
    echo "\n=== جدول apk_versions ===\n";
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM apk_versions")->fetchColumn();
        echo "  وجود دارد — $count رکورد\n";
    } catch(Exception $e) {
        echo "  ❌ وجود ندارد\n";
    }

} catch(Exception $e) {
    echo "❌ خطای اتصال: " . $e->getMessage() . "\n";
}
