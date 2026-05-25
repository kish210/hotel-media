<?php
define("ROOT", "/var/www/html");
require ROOT . "/vendor/autoload.php";
$env = parse_ini_file(ROOT . "/.env");
App\Core\Database::init(["host"=>($env["DB_HOST"]??"mysql"),"dbname"=>($env["DB_NAME"]??"signage_cms"),"username"=>($env["DB_USER"]??"signage_user"),"password"=>($env["DB_PASS"]??"StrongPassword123!")]);
App\Modules\Core\ModuleRegistry::ensureTable();
App\Modules\Core\ModuleRegistry::boot(1);
$all = App\Modules\Core\ModuleRegistry::all();
echo "Total modules: " . count($all) . PHP_EOL;
foreach($all as $id => $m) {
    echo ($m->isInstalled() ? "[ACTIVE] " : "[off]    ") . $id . " - " . $m->name() . PHP_EOL;
}
echo "Active: " . implode(", ", App\Modules\Core\ModuleRegistry::activeIds()) . PHP_EOL;
