<?
/**
 * 마켓 겜냥 환산 진단
 * 브라우저: /shop/_debug_price.php?face=30000
 */
define('SHOP_SKIP_MAINTENANCE_GATE', true);
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
include_once __DIR__ . '/_shop.php';

header('Content-Type: text/plain; charset=utf-8');

$face = isset($_GET['face']) ? (int)$_GET['face'] : 30000;

echo "=== ENV ===\n";
echo "bcmath=" . (extension_loaded('bcmath') ? 'yes' : 'NO') . "\n";
echo "bcmul=" . (function_exists('bcmul') ? 'yes' : 'NO') . "\n";
echo "냥_정수문자열=" . (function_exists('냥_정수문자열') ? 'yes' : 'NO') . "\n";
echo "냥_비율내림=" . (function_exists('냥_비율내림') ? 'yes' : 'NO') . "\n";

echo "\n=== RAW SQL (마이너스 제외) ===\n";
$raw = @db_select("
  SELECT
    COALESCE(SUM(newpoint), 0) AS total_np,
    CONCAT('N', CAST(COALESCE(SUM(GREATEST(CAST(IFNULL(point, 0) AS DECIMAL(40,0)), 0)), 0) AS CHAR)) AS total_pt,
    CONCAT('N', CAST(COALESCE(SUM(GREATEST(point, 0)), 0) AS CHAR)) AS total_pt2
  FROM tb_member WHERE status = 0
");
echo "total_np=" . var_export($raw['total_np'] ?? null, true) . "\n";
echo "total_pt=" . var_export($raw['total_pt'] ?? null, true) . "\n";
echo "total_pt2=" . var_export($raw['total_pt2'] ?? null, true) . "\n";
echo "total_pt_type=" . gettype($raw['total_pt'] ?? null) . "\n";

echo "\n=== RAW SQL (마이너스 포함·구버전) ===\n";
$rawOld = @db_select("
  SELECT CONCAT('N', CAST(COALESCE(SUM(CAST(IFNULL(point, 0) AS DECIMAL(40,0))), 0) AS CHAR)) AS total_pt
  FROM tb_member WHERE status = 0
");
echo "total_pt_with_neg=" . var_export($rawOld['total_pt'] ?? null, true) . "\n";

echo "\n=== shop_실시간_총량조회 ===\n";
$총량 = shop_실시간_총량조회();
echo "본방냥=" . var_export($총량['본방냥'] ?? null, true) . "\n";
echo "게임냥=" . var_export($총량['게임냥'] ?? null, true) . "\n";
echo "게임냥_len=" . strlen((string)($총량['게임냥'] ?? '')) . "\n";

echo "\n=== SNAPSHOT ===\n";
if (function_exists('시세기준_게임냥_문자열')) {
    echo "시세기준_게임냥=" . 시세기준_게임냥_문자열() . "\n";
}
if (function_exists('시세기준_본방냥')) {
    echo "시세기준_본방냥=" . 시세기준_본방냥() . "\n";
}

echo "\n=== CALC face={$face} ===\n";
$만원당25 = shop_만원당_냥_요율(2.5);
$만원당5 = shop_만원당_냥_요율(5.0);
$환산25 = shop_원화_냥환산_요율($face, 2.5);
$환산5 = shop_원화_냥환산_요율($face, 5.0);
echo "만원당_2.5%=" . $만원당25 . "\n";
echo "만원당_5%=" . $만원당5 . "\n";
echo "환산_2.5%=" . $환산25 . "\n";
echo "환산_5%=" . $환산5 . "\n";
echo "표시_2.5%=" . shop_냥_표시($환산25) . "\n";
echo "표시_5%=" . shop_냥_표시($환산5) . "\n";

echo "\n=== MEMBER point column ===\n";
$col = @db_select("SHOW COLUMNS FROM tb_member LIKE 'point'");
echo "type=" . ($col['Type'] ?? '?') . "\n";
