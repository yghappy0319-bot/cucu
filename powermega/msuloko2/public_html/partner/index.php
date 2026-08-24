<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
?>
<!DOCTYPE html>
<html lang="ko">
<head>

<?
#Define ################
// 처리 후 이동할 페이지
$URL = "/";

$Referral_code = $_GET['Idx'];
$Visit_Date = date("Y-m-d");
$Visit_Time = date("H");

// 세션 초기화
session_unregister("Referral");

// 코드 정상 확인
if (!is_numeric($Referral_code)) {
	header("location:".$URL);
	exit;
}

// 정상 partner idx 확인
$cnt = $db->get_data("SELECT COUNT(IDX) AS CNT FROM REFERRAL_PARTNER WHERE IS_USE='Y' AND Idx=".$Referral_code);

// partner idx 세션 등록
if ($cnt['CNT'] > 0) {
	$Is_Referral = true;
	$Referral["Code"] = $Referral_code;

	// session 등록
	$_SESSION["Referral"] = $Referral["Code"];
}

// 접속 정보 확인 및 저장
if ($Is_Referral) {
	$WhereIs = " WHERE REFERRAL_CODE=".$Referral_code;
	$WhereIs .= " AND VISIT_DATE = '".$Visit_Date."'";
	$WhereIs .= " AND VISIT_TIME = ".$Visit_Time;

	$SQL = "SELECT COUNT(IDX) AS CNT FROM REFERRAL_VISIT ";
	$SQL .= $WhereIs;
	$cnt = $db->get_data($SQL);

	// 접속정보 업데이트 또는 추가
	if ($cnt['CNT'] > 0 ) {
		$SQL = " UPDATE REFERRAL_VISIT SET VISIT_NUM = VISIT_NUM + 1";
		$SQL .= $WhereIs;
	}
	else{
		$SQL = " INSERT INTO REFERRAL_VISIT (REFERRAL_CODE , VISIT_DATE , VISIT_TIME , VISIT_NUM) VALUES (".$Referral_code." , '".$Visit_Date."' , ".$Visit_Time." , 1) ";
	}
	$db->query($SQL);

}

// 페이지 이동
header("location:".$URL);

?>
