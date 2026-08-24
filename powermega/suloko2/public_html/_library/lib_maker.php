<?
include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

//	테이블명 설정


$tablez[0]		=	"MY_BALL";


//$tablez[1]		=	"";

//	기본키 설정
$priz[0]		=	"BALL_NO";


//$priz[1]		=	"no";


for ($r = 0; $r < count($tablez); $r++){
	$table_name		=	$tablez[$r];
	$primary		=	$priz[$r];
	$list			=	null;
	$insert_1		=	"";
	$insert_2		=	"";
	$update_1		=	"";


	if($primary == "") $primary		=	"no";

	$list		=	$db->get_list("SHOW COLUMNS FROM `$table_name`");

	$z		=	count($list['Field']);


	for($i = 0; $i < $z; $i++){

		$insert_1	.=	"\r\n										".$list['Field'][$i].",";
		$insert_2	.=	"\r\n										'`.&_L['".$list['Field'][$i]."'].`',";

		if ($list['Field'][$i] == $primary ) continue;
		if ($list['Field'][$i] == "write_time" ) continue;
		if ($list['Field'][$i] == "reg_date" ) continue;
		if ($list['Field'][$i] == "REG_DATE" ) continue;
		if ($list['Field'][$i] == "regDate" ) continue;
		if ($list['Field'][$i] == "hit" ) continue;
		$length		=	strlen($list['Field'][$i]) / 4;
		$tab		=	"";
		for ($t = $length; $t < 4; $t++){
			$tab	.=	"	";
		}
		//	update 할때 file1~5는 제외함
		if ($list['Field'][$i] != "file1" && $list['Field'][$i] != "file2" && $list['Field'][$i] != "file3" && $list['Field'][$i] != "file4" && $list['Field'][$i] != "file5"){
			$update_1	.=	"\r\n										".$list['Field'][$i].$tab."=	'`.&_L['".$list['Field'][$i]."'].`',";
		}
	}

	$insert_2		=	str_replace('`', '"', $insert_2);
	$insert_2		=	str_replace('&', '$', $insert_2);

	$update_1		=	str_replace('`', '"', $update_1);
	$update_1		=	str_replace('&', '$', $update_1);

	$insert_1		=	substr($insert_1, 0, strlen($insert_1)-1);
	$insert_2		=	substr($insert_2, 0, strlen($insert_2)-1);

	$update_1		=	substr($update_1, 0, strlen($update_1)-1);


	//! 로 시작하는 스트링 replace
	$write_time_str		=	'$_L['."'write_time']";
	$reg_date_str		=	"'".'".$_L['."'reg_date'].".'"'."'";
	$reg2_date_str		=	"'".'".$_L['."'REG_DATE'].".'"'."'";
	$reg3_date_str		=	"'".'".$_L['."'regDate'].".'"'."'";
	$hit_str			=	"'".'".$_L['."'hit'].".'"'."'";

	$data		=	file_load("./skin_sample.php");

	$data		=	str_replace("!table_name", $table_name, $data);
	$data		=	str_replace("!insert_1", $insert_1, $data);
	$data		=	str_replace("!insert_2", $insert_2, $data);
	$data		=	str_replace("!update_1", $update_1, $data);
	$data		=	str_replace("!primary", $primary, $data);
	$data		=	str_replace($write_time_str, "time()", $data);
	$data		=	str_replace($reg_date_str, "NOW()", $data);
	$data		=	str_replace($reg2_date_str, "NOW()", $data);
	$data		=	str_replace($reg3_date_str, "NOW()", $data);
	$data		=	str_replace($hit_str, "0", $data);

	$file_name	=	"function_".$table_name.".php";
//echo "<pre>";
//echo  $data;
//echo "</pre>";
	file_write("./auto/".$file_name, $data);
	chmod("./auto/".$file_name, 0777);


}

?>