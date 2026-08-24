<?php
include_once "../lib/function.php";
include_once "../lib/config.php";
include_once "../_chk.php";

$midx = $_SESSION['midx'];
if(!$midx){
  die("로그인 후 이용해주세요.");
}

// 캐시 충전 일시 중단: 입금 계좌 문자 미발송
exit;

if($포인트충전문자발송){
  if($cash_tax){
    $_cash_tax = "(VAT 포함)";
  }else{
    $_cash_tax = "";
  }
    $_신청금액 = number_format($amount);
    $내용 = "(안내)\n";
    $내용.= $config['사이트명1']." 무통장 입금(계좌이체)\n신청이 정상 접수 되었습니다.\n";
    $내용.= "■ 입금명 : ".$deposit_name."\n";
    $내용.= "■ 입금액 : ".$_신청금액."원{$_cash_tax}\n";
    $내용.= "■ 입금 유효기간 : 최대 [24시간] 입금 확인이 안될 경우 캐시 충전이 취소 처리 됩니다.\n";
    $내용.= "[[입금 정보]]";
    $내용.= "\n";
    $내용.= "■ 입금 계좌번호 : {$config['계좌번호']}\n";
    $내용.= "■ 입금은행 : {$config['은행명']}\n";
    $내용.= "■ 예금주 : {$config['입금자명']}\n";
    $내용.= "위에 신청주신 정보와 일치하게 입금될 시 즉시 캐시충전이 완료되며, 입금이[24시간]지연되어 자동취소 될 경우 다시 캐시충전 신청을 해주신 후에 입금해주셔야 합니다.\n";
    $내용.= $config['사이트명1']." 충전 바로가기\n";
    $내용.= "https://megapower.world/buy/point.html";

    if($알리고문자발송){
      //$result = SMS_SEND($member['phone'], $제목="무통장입금안내",$내용);
      //var_dump($result);
      //로그_문자발송($member['idx'], 1, $deposit_idx, 0, $member['phone'], $내용, $아이피, '입금신청완료');
      echo $result->result_code;

    }else{
      $result = 자동문자(0, $member['phone'],'입금계좌안내', '무통장입금안내',$내용);
      echo $result;
    }

}
