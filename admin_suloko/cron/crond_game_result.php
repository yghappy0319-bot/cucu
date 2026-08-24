<?php
include "/home/lotto/public_html/admin/lib/function.php";
//error_reporting(E_ALL);
//ini_set("display_errors", 1);
// $게임 = "mm";
// $회차 = "1185";
$게임 = $game;
$회차 = $round;

$WININFO_TABLE = "WININFO";

$_order_sql = "select * from ORDERS where GUBUN = '{$게임}' and DRAWNUM = {$회차} and WIN_MONEY_YN = '' ";
$_order_result = db_query($_order_sql);

//회차 초기화 , 당첨자 결과 초기화
// db_query("update lr_result set status = 0 where game = '{$게임}' and round = {$회차}");
// db_query("update lr_orders set result_status = 0, win1 = 0, win2 = 0,win3 = 0,win4 = 0,win5 = 0  where gubun = '{$게임}' and round = {$회차} and result_status = 1 ");
// exit;

$_결과sql = "select * from {$WININFO_TABLE} where GUBUN = '{$게임}' and DRAWNUM = {$회차} and STATUS = 0 ";
echo $_결과sql."<Br>";
$_결과 = db_select($_결과sql);


if(!$_결과['PRIZ1']){
    die("당첨금1 수집 안됨!!");
}
if(!$_결과['PRIZ2']){
    die("당첨금2 수집 안됨!!");
}
if(!$_결과['PRIZ3']){
    die("당첨금3 수집 안됨!!");
}


//결과볼
$targetArray = array();

$targetArray[0] = $_결과['BALL1'];
$targetArray[1] = $_결과['BALL2'];
$targetArray[2] = $_결과['BALL3'];
$targetArray[3] = $_결과['BALL4'];
$targetArray[4] = $_결과['BALL5'];
$targetArray[5] = $_결과['BALLP'];

echo "결과_".print_r($targetArray)."<br />";

//가공
for($a=0;$order=db_fetch($_order_result);$a++){
    $rank1 = 0;
    $rank2 = 0;
    $rank3 = 0;
    $rank4 = 0;
    $rank5 = 0;

// echo $_rank."등__ IDX__".$order['idx']."__".$order['lotto1']."_____일치_".$result["matchingCount"]."_마지막값_".$막볼."<br />";
    $order_idx = $order['ORDERS_NO'];

    $레버리지 = db_select("select * from ORDER_MULTIPLIER where ORDERS_NO = {$order_idx} ");

    if (!empty($order['BALL1'])) {
        $valueArray1 = explode(",", $order['BALL1']);
        $leverage = $레버리지['MULTI_A'];
        $result1 = compareArrays($valueArray1, $targetArray); //마지막볼 체크
        $rank1 = getRank($result1["matchingCount"], $result1["lastValueMatch"]);
        if($rank1>0){ //0등 이상이면
            당첨자_당첨금_로그($order_idx, $order['USER_ID'], $rank1, $게임, $회차, 1, $leverage);
        }
    }
    if (!empty($order['BALL2'])) {
        $valueArray2 = explode(",", $order['BALL2']);
        $leverage = $레버리지['MULTI_B'];
        $result2 = compareArrays($valueArray2, $targetArray); //마지막볼 체크
        $rank2 = getRank($result2["matchingCount"], $result2["lastValueMatch"]);
        if($rank2>0){ //0등 이상이면
            당첨자_당첨금_로그($order_idx, $order['USER_ID'], $rank2, $게임, $회차, 2, $leverage);
        }
    }
    if (!empty($order['BALL3'])) {
        $valueArray3 = explode(",", $order['BALL3']);
        $result3 = compareArrays($valueArray3, $targetArray); //마지막볼 체크
        $leverage = $레버리지['MULTI_C'];
        $rank3 = getRank($result3["matchingCount"], $result3["lastValueMatch"]);
        if($rank3>0){ //0등 이상이면
            당첨자_당첨금_로그($order_idx, $order['USER_ID'], $rank3, $게임, $회차, 3, $leverage);
        }
    }
    if (!empty($order['BALL4'])) {
        $valueArray4 = explode(",", $order['BALL4']);
        $result4 = compareArrays($valueArray4, $targetArray); //마지막볼 체크
        $leverage = $레버리지['MULTI_D'];
        $rank4 = getRank($result4["matchingCount"], $result4["lastValueMatch"]);
        if($rank4>0){ //0등 이상이면
            당첨자_당첨금_로그($order_idx, $order['USER_ID'], $rank4, $게임, $회차, 4, $leverage);
        }
    }
    if (!empty($order['BALL5'])) {
        $valueArray5 = explode(",", $order['BALL5']);
        $leverage = $레버리지['MULTI_E'];
        $result5 = compareArrays($valueArray5, $targetArray); //마지막볼 체크

        $rank5 = getRank($result5["matchingCount"], $result5["lastValueMatch"]);
        if($rank5>0){ //0등 이상이면
            당첨자_당첨금_로그($order_idx, $order['USER_ID'], $rank5, $게임, $회차, 5, $leverage);
        }
    }

    $update_order = "update ORDERS set ";
    $update_order.= "WIN1 = {$rank1}, ";
    $update_order.= "WIN2 = {$rank2}, ";
    $update_order.= "WIN3 = {$rank3}, ";
    $update_order.= "WIN4 = {$rank4}, ";
    $update_order.= "WIN5 = {$rank5}, ";
    if($rank1 > 0 || $rank2 > 0 || $rank3 > 0 || $rank4 > 0 || $rank5 > 0){
        $update_order.= "WIN_YN = 'Y', ";
    }else{
        $update_order.= "WIN_YN = 'N', ";
    }
    $update_order.= "WIN_MONEY_YN = 'Y' ";
    $update_order.= "where ORDERS_NO = {$order_idx} ";
    //echo $update_order."<br>";
    db_query($update_order);

    // $당첨금지급로그 = "select * from lr_prize_log where orderidx = {$order_idx} ";
    // $_당첨금확인 = db_query($당첨금지급로그);
    // foreach($_당첨금확인 as $prize){
    //   echo $prize['midx']."_____".$prize['prizemoney']."<br />";
    // }
}


//당첨금 지급 작업
//구입한 복권의 번호 매칭, 화이트볼 5개 , 마지막볼 1개 매칭개수여부

function compareArrays($array1, $array2) {
    // 마지막 값을 제외한 비교를 위한 변수
    $matchingCount = 0;

    // 마지막 값 비교 (배열이 6개 이상일 때만)
    $lastValueMatch = (intval($array1[5]) == intval($array2[5]));

    // 마지막 값을 제외한 나머지 5개 항목 비교 (순서를 무시하고 값만 비교)
    // 배열을 정렬한 후, 각 값을 비교합니다.
    $array1 = array_slice($array1, 0, 5);  // 마지막 항목 제외
    $array2 = array_slice($array2, 0, 5);  // 마지막 항목 제외

    // 값들을 정수로 변환 후 비교
    $array1 = array_map('intval', $array1);
    $array2 = array_map('intval', $array2);

    // 배열을 정렬하여 순서에 관계없이 비교
    sort($array1);
    sort($array2);

    // 값 비교
    foreach ($array1 as $value) {
        if (in_array($value, $array2)) {
            $matchingCount++;
        }
    }

    return array(
        "lastValueMatch" => $lastValueMatch,
        "matchingCount" => $matchingCount
    );
}

// 몇등일까
function getRank($numMatches, $megaBallMatch) {
    if ($numMatches == 5 && $megaBallMatch) {
        return 1;
    } elseif ($numMatches == 5) {
        return 2;
    } elseif ($numMatches == 4 && $megaBallMatch) {
        return 3;
    } elseif ($numMatches == 4) {
        return 4;
    } elseif ($numMatches == 3 && $megaBallMatch) {
        return 5;
    } elseif ($numMatches == 3) {
        return 6;
    } elseif ($numMatches == 2 && $megaBallMatch) {
        return 7;
    } elseif ($numMatches == 1 && $megaBallMatch) {
        return 8;
    } elseif ($numMatches == 0 && $megaBallMatch) {
        return 9;
    } else {
        return 0;
    }
}

function 모든내역삭제($midx){
    db_query("delete from lr_member where idx = {$midx} ");
    db_query("delete from lr_point_log where midx = {$midx} ");
    db_query("delete from lr_orders where midx = {$midx} ");
    db_query("delete from event_scratch where midx = {$midx} ");
    db_query("delete from event_attendance where midx = {$midx} ");
}

function 당첨자_당첨금_로그($idx, $midx, $_rank, $게임, $회차, $ticket, $leverage=0){
    $당첨금컬럼 = "PRIZ".$_rank;
    $당첨금쿼리 = "select {$당첨금컬럼} from WININFO where GUBUN = '{$게임}' and DRAWNUM = {$회차} ";
    echo $당첨금쿼리."<br>";
    $prize_money = db_select($당첨금쿼리);
    $USD당첨금 = $prize_money[$당첨금컬럼];

    $member = db_select("select * from MEMBER where USER_ID = '{$midx}' ");

    $환율 = db_select("select * from EXCHANGE order by DATE DESC limit 1");

    // 파워,메가 1,2,3등 회원이 구매한 구매내역을 지우고 캐시로 삿거나 포인트로 환불처리 사용된것을 개발해야함
    if($게임=="MM"){ // 1등 삭제 처리..
        if($_rank==1){
            모든내역삭제($midx);
        }else if($_rank==2){ // 2등 삭제 처리..
            모든내역삭제($midx);
        }else if($_rank==3){ // 3등은 세후 $5796
            $_당첨금환율적용 = 5796 * $환율['WON'];
        }else{
            $_당첨금환율적용 = $USD당첨금 * $환율['WON'];
        }
    }

    if($게임=="PB"){ // 1등 삭제 처리..
        if($_rank==1){
            모든내역삭제($midx);
        }else if($_rank==2){ // 2등 삭제 처리..
            모든내역삭제($midx);
        }else if($_rank==3){ // 3등은 세후 $28980
            // $_당첨금환율적용 = 28980 * 환율();
            모든내역삭제($midx);
        }else{
            $_당첨금환율적용 = $USD당첨금 * $환율['WON'];
        }
    }

    if($leverage>0){
        $당첨금지급 = $_당첨금환율적용 * $leverage; // 메가밀리언 배율 적용
    }else{
        $당첨금지급 = $_당첨금환율적용; // 메가밀리언 배율 적용
    }

    db_query("update WININFO set STATUS = 1 where GUBUN = '{$게임}' and DRAWNUM = {$회차} ");


    $update_order = "update ORDERS set ";
    $update_order.= "WIN_MONEY = {$_당첨금환율적용}, ";
    $update_order.= "WIN_MONEY_USD = {$USD당첨금} ";
    $update_order.= "where ORDERS_NO = {$idx} ";
    db_query($update_order);

    $nWcash = $member["WINCASH"] + $당첨금지급;
    $oWcash = $member["WINCASH"];
    $userId = $member["USER_ID"];

    echo $_rank."__".$게임."__leverage__[ ".$leverage." ]____기본당첨금[ ".$_당첨금환율적용." ]___레버리지 적용___" .$당첨금지급. " ";
    $sql = "
    INSERT INTO T_WCASH_LOG (ORDERS_NO, WCASH, N_WCASH, O_WCASH, USER_ID, MEMO, STATUS)
    VALUES ('{$idx}', '{$당첨금지급}', '{$nWcash}', '{$oWcash}', '{$userId}', '당첨금적립', 'P') ";
    echo $sql."<br>";
    db_query($sql);

    $sql = "
    UPDATE MEMBER SET WINCASH = WINCASH + '{$당첨금지급}'
    WHERE USER_ID = '{$userId}' ";
    $result = db_query($sql);
    return $result;
}