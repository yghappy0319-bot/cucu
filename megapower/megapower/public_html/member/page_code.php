<?
$recordsPerPage = ($records ? $records:9);
if (isset($_GET['page'])) {
    $currentPage = $_GET['page'];
} else {
    $currentPage = 1;
}

$countSql = "select count(*) as totalRecords from {$table} where game = 'mm' ";
$countResult = db_select($countSql);
$totalRecords = $countResult['totalRecords'];

$totalPages = ceil($totalRecords / $recordsPerPage);
$start = ($currentPage - 1) * $recordsPerPage;
?>
