<?
include $_SERVER['DOCUMENT_ROOT']."/admin/lib/function.php";
include $_SERVER['DOCUMENT_ROOT']."/admin/_chk.php";

// CSV 파일 다운로드를 위한 헤더 설정
header( "Content-type: application/vnd.ms-excel; charset=ks_c_5601-1987" );
header( "Content-Disposition: attachment; filename= data_".date("Ymd").".xls" );
header( "Content-Description: PHP4 Generated Data" );

// if($searchs=="cashy"){
//   $_search = " and (select count(*) as cnt from lr_deposit_log where midx = a.idx and status = 2 ) > 0 ";
// }else if($searchs=="cashn"){
//   $_search = " and (select count(*) as cnt from lr_deposit_log where midx = a.idx and status = 2 ) = 0 ";
// }

if($searchs=="cashy"){
  $_search = " AND (bank_log.bank_cnt > 0 OR card_log.card_cnt > 0) ";
}else if($searchs=="cashn"){
  $_search = " AND (bank_log.bank_cnt is null) ";
}


if ($search) {
    $_search = " and (name like '%{$search}%' or phone like '%{$search}%' or id like '%{$search}%') ";
}

// $countSql = "select count(*) as totalRecords from lr_member a where 1=1 {$_search} ";
// include_once "../pager_result.php";

if ($searchs == "desc_latest") {
    $최신로그인 = "last_login_date desc ";
} else if ($searchs == "asc_latest") {
    $최신로그인 = "last_login_date asc ";
} else if ($searchs == "max_point") {
    $최신로그인 = "point desc ";
} else if ($searchs == "min_point") {
    $최신로그인 = "point asc ";
} else if ($searchs == "asc_regdate") {
    $최신로그인 = "regdate asc ";

} else {
    $최신로그인 = "regdate desc ";
}

$보상캐시차감일 = "2026-06-14";
$sql = "


SELECT
  a.*,
  IFNULL(bank_log.bank_cnt, 0) AS bank_cnt,
  IFNULL(card_log.card_cnt, 0) AS card_cnt,
  GREATEST(IFNULL(bank_log.bank_cnt, 0) + IFNULL(card_log.card_cnt, 0) - IFNULL(reward_adj.adj, 0), 0) AS total_cnt
FROM lr_member a
LEFT JOIN (
  SELECT midx, COUNT(*) AS bank_cnt
  FROM lr_deposit_log
  WHERE status = 2
  GROUP BY midx
) AS bank_log ON bank_log.midx = a.idx
LEFT JOIN (
  SELECT midx, COUNT(*) AS card_cnt
  FROM lr_card_log
  WHERE pg_status1 = '정상'
  GROUP BY midx
) AS card_log ON card_log.midx = a.idx
LEFT JOIN (
  SELECT midx, 1 AS adj
  FROM lr_deposit_log
  WHERE deposit_type = 'reward'
    AND status = 2
    AND regdate >= '{$보상캐시차감일} 00:00:00'
    AND regdate < '2026-06-15 00:00:00'
  GROUP BY midx
) AS reward_adj ON reward_adj.midx = a.idx
WHERE a.cut_off = 0 and a.send_sms = 0 AND DATE(a.last_login_date) != CURDATE() AND (bank_log.bank_cnt > 0 OR card_log.card_cnt > 0)

ORDER BY a.{$최신로그인}
";

//$sql = "select * from lr_member a where 1 {$_search} order by a.{$최신로그인} ";
//echo $sql;

$_data = db_query($sql);

?>
<table border="1" cellspacing="0" cellpadding="5" style="border-collapse: collapse;">
    <thead>
    <tr >
        <th >아이디</th>
        <th >이름</th>
        <th >연락처</th>
        <th >캐시</th>
        <th >당첨금</th>
        <th >포인트</th>
        <th >디바이스</th>
        <th >유입</th>
        <th >누적캐시</th>
        <th >충전횟수</th>
        <th >최근접속</th>
        <th >가입일</th>
    </tr>
    </thead>
    <tbody>
    <? foreach ($_data as $member) { ?>
        <tr >
            <td class="text-center"><?= $member['id'] ?></td>
            <td class="text-center"><?= $member['name'] ?></td>
            <td class="text-center"><?= $member['phone'] ?></td>
            <td class="text-center"><?= $member['cash'] ?></td>
            <td class="text-center"><?= $member['prize_money'] ?></td>
            <td class="text-center"><?= $member['point'] ?></td>
            <td class="text-center"><?= $member['access'] ?></td>
            <td class="text-center"><?= $member['ads'] ?></td>
            <td class="text-center"><?= $member['access'] ?></td>
            <td class="text-center"><?= $member['total_cnt'] ?></td>
            <td class="text-center"><?= $member['last_login_date'] ?></td>
            <td class="text-center"><?= $member['regdate'] ?></td>
        </tr>
    <? } ?>
    </tbody>
</table>
