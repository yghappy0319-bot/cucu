<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";

$sqls1 = "insert into tb_member1 set
status = 0,
code = '0',
couple = 0,
num = '{$number}',
gender = '{$gender}',
name = '{$name}',
content = '{$content}',
getto = 300,
max_getto = 300,
regdate = now() ";
$result = db_query($sqls1);
echo $result;