<?php
// Test: آیا تب‌ها کار میکنند؟
$tabsWorking = false;
$show = file_get_contents(__DIR__ . '/../resources/views/screens/show.php');
$tabsWorking = strpos($show, "id=\"stab-info\"") !== false && strpos($show, "showTab") !== false;

echo '<pre>';
echo "show.php size: " . strlen($show) . " chars\n";
echo "Has stab-info: " . (strpos($show, "stab-info") !== false ? "YES ✅" : "NO ❌") . "\n";
echo "Has showTab: " . (strpos($show, "function showTab") !== false ? "YES ✅" : "NO ❌") . "\n";
echo "Has display:block: " . (strpos($show, "display:block") !== false ? "YES ✅" : "NO ❌") . "\n";
echo "Has display:none sec-activation: " . (strpos($show, "sec-activation\" style=\"display:none") !== false ? "YES ✅" : "NO ❌") . "\n";

// index.php
$idx = file_get_contents(__DIR__ . '/../resources/views/screens/index.php');
echo "\nindex.php size: " . strlen($idx) . " chars\n";
echo "Has showGroupModal inline: " . (strpos($idx, "m.style.display = 'flex'") !== false ? "YES ✅" : "NO ❌") . "\n";
echo "Modal display:none: " . (strpos($idx, "style=\"display:none;\"") !== false ? "YES ✅" : "NO ❌") . "\n";

echo '</pre>';
echo '<a href="/admin/screens">→ برو به صفحات</a>';
