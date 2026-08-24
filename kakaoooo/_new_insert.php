<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

$높번 = db_select("select max(num) as nmax from tb_member");
$다음번호 = $높번['nmax'] + 1;

$couple_val = isset($couple) ? (int)$couple : 0;
$색확정 = ($couple_val === 2) ? 0 : 1;

if (is_file($_SERVER['DOCUMENT_ROOT']."/api/function.php")) {
  include_once $_SERVER['DOCUMENT_ROOT']."/api/function.php";
}
if (function_exists('색확정_컬럼보장')) {
  색확정_컬럼보장();
}

$sqls1 = "insert into tb_member set
status = 0,
code = '0',
couple = {$couple_val},
`색확정` = {$색확정},
num = '{$다음번호}',
gender = '{$gender}',
name = '{$name}',
content = '{$content}',
getto = 300,
max_getto = 300,
regdate = now() ";
$result = db_query($sqls1);
echo $result;


if($content){
    db_query("insert into tb_member_profile set name = '{$name}', content = '{$content}', regdate = now() ");
}
