<?php
include_once "/home/kakao/public_html/lib/_function.php";
include_once "/home/kakao/public_html/api/function.php";

$현재시간 = date("Y-m-d H:i:s");
//echo $현재시간;
db_query("DELETE FROM tb_item_use WHERE enddate <= '{$현재시간}' ");
db_query("DELETE FROM tb_work WHERE regdate <= '{$현재시간}' ");
강일만료_횟수_누적($현재시간);
지목만료_횟수_누적($현재시간);
공커연금_누적갱신();
db_query("DELETE FROM tb_progress WHERE enddate <= '{$현재시간}' ");
db_query("DELETE FROM tb_self WHERE enddate <= '{$현재시간}' ");
db_query("DELETE FROM tb_gangil_tile WHERE edate <= '{$현재시간}' ");
db_query("DELETE FROM tb_kong_room WHERE edate <= '{$현재시간}' ");

db_query("insert into cron_log set content = 'auto_tasu_chk', regdate = NOW()");

// 누적 타수(tasu) 기준 레벨 자동 갱신 — .레벨업 안내 구간 (150/300/500…)
if (function_exists('레벨_자동갱신_크론')) {
  레벨_자동갱신_크론();
}