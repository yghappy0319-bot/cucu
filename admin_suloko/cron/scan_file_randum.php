<?php
include "/home/lotto/public_html/admin/lib/function.php";

//         AND IMG_PATH != ''

$sql = "        SELECT ORDERS_NO, GUBUN, IMG_PATH, PRINT, PLAYDATE, DATE, REG_DATE
        FROM ORDERS
        WHERE (IMG_YN = '' OR IMG_YN IS NULL)

        AND REG_DATE > '2025-08-20 09:00:01' AND REG_DATE <= '2025-08-21 00:00:00'
    ";

$result = db_query($sql);

$a = 1;
foreach($result as $data){
    $datas = date("YmdHis", strtotime($data['REG_DATE']));

    $filename = $datas.$a."_a.jpg";

    $sqls = "update ORDERS SET IMG_PATH = '{$filename}' where ORDERS_NO = {$data['ORDERS_NO']}  ";
    db_query($sqls);

    $a++;
}