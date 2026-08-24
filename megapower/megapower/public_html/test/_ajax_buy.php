<?php include_once "../lib/function.php";
include_once "../_chk.php";


function getWeightedRandomResult(array $probabilities) {
    $validProbabilities = array_filter($probabilities, function ($p) {
        return $p > 0;
    });

    if (empty($validProbabilities)) {
        return 2; // 유효한 확률이 없으면 null 반환 또는 다른 처리
    }

    $rand = mt_rand(1, array_sum($validProbabilities));
    $cumulativeProbability = 0;

    foreach ($validProbabilities as $value => $probability) {
        $cumulativeProbability += $probability;
        if ($rand <= $cumulativeProbability) {
            return $value;
        }
    }

    // 이 부분은 원래 확률 합이 100이 아닌 경우를 대비한 안전 장치
    return array_key_last($validProbabilities);
}

function getValidRandomLeverage(array $probabilities) {
    while (true) {
        $result = getWeightedRandomResult($probabilities);
        if ($result !== null && $probabilities[$result] > 0) {
            return $result;
        }
        // $probabilities 배열의 모든 값이 0 이하인 경우 무한 루프를 방지하기 위한 안전 장치
        if (count(array_filter($probabilities, function ($p) { return $p > 0; })) === 0) {
            return 2; // 또는 다른 기본값이나 오류 처리
        }
    }
}

function getOrderNumber($filePath, $maxOrder) {
    // 파일에 저장된 주문 번호를 읽기
    if (file_exists($filePath)) {
        $orderNumber = (int)file_get_contents($filePath);
    } else {
        // 파일이 없으면 첫 번째 주문 번호를 1로 설정
        $orderNumber = 1;
    }

    // 주문 번호가 $maxOrder 이하일 경우
    if ($orderNumber <= $maxOrder) {
        // 주문 번호 증가
        file_put_contents($filePath, $orderNumber + 1); // 파일에 새로운 주문 번호 저장
        return $orderNumber;  // 현재 주문 번호 반환
    } else {
        // 주문 번호가 $maxOrder를 초과하면 1부터 다시 시작
        file_put_contents($filePath, 1); // 주문 번호 1로 초기화
        return 1;  // 초기화된 주문 번호 반환
    }
}
$midx = $_SESSION['midx'];

$총구매가 = $order_game_cnt * $_amount;
if ($sell_lucky_point > 0 || $sell_prize_money > 0) {

    // 포인트 + 당첨금 둘 다 적용
    $최종가 = $총구매가 - ($sell_lucky_point + $sell_prize_money);

    // 최종가가 원래 금액과 동일하면 → 사용이 적용 안 된 상황
    if ($총구매가 == $최종가) {
        db_query("insert into cron_log set status = '{$midx}_구매불가', regdate = now() ");
        exit;
    }
}



if(!$midx){
  die("로그인 후 이용해주세요.");
}

if($member['point'] < $total_amount){
  die("구매 캐시가 부족합니다.\n잔여 캐시는 ".number_format($member['point'])."원 입니다.\n캐시를 충전해주세요.");
}
// sell_prize_money 당첨금
// sell_lucky_point 럭키포인트
if($sell_lucky_point > 0 || $sell_prize_money > 0){
  if(!$member['lucky_point']){
    die("포인트가 없습니다.");
  }
}

if($sell_lucky_point > 0){
  if($member['lucky_point'] < $sell_lucky_point){
    die("보유 포인트가 부족합니다.");
  }
}

if($sell_prize_money > 0){
  if($member['prize_money'] < $sell_prize_money){
    die("보유 당첨금이 부족합니다.");
  }
}

$스캔본_코드 = $config['드로우코드']; // 특정 시간이 지난 경우, 드로우코드 값을 증가시킴

if($coupon_idx>0){
  $쿠폰 = db_select("select * from lr_coupon_log where idx = {$coupon_idx} ");

  $endDate = strtotime($쿠폰['usedate1']);
  $currentDate = time();

  if ($currentDate > $endDate) { //쿠폰유효기간 체크
    db_query("update lr_coupon_log set status = 1 where midx = {$member['idx']} and idx = {$coupon_idx} ");//유효기간지남
    die($쿠폰['name']."은 사용할 수 없는 쿠폰 입니다.\n(사유: 사용가능기간 만료)");
  }
}


// $lottoColumns = array("lotto1", "lotto2", "lotto3", "lotto4", "lotto5", "lotto6", "lotto7", "lotto8", "lotto9", "lotto10" );
$lottoColumns = array("lotto1", "lotto2", "lotto3", "lotto4", "lotto5");
$_lotto = count($lotto);

$선택값 = array();
$aa = 1;

for ($a = 0; $a < $_lotto; $a++) {
    // 값이 비어 있지 않은 경우에만 처리
    if (!empty($lotto[$a])) {
        $선택값[] = $lotto[$a]; // 인덱스 순서와 상관없이 값을 추가
        $aa++;
    }
}

$newLottoArray = array();
foreach ($선택값 as $key => $value) {
  // 문자열을 콤마 기준으로 분할하여 배열로 만듭니다.
  $numbers = explode(",", $value);

  // 배열에서 마지막 값을 추출합니다.
  $lastNumber = array_pop($numbers);

  // 배열을 오름차순으로 정렬합니다.
  sort($numbers);

  // 정렬된 배열에 마지막 값을 다시 추가합니다.
  $numbers[] = $lastNumber;

  // 정렬된 배열을 다시 문자열로 조합합니다.
  $newString = implode(",", $numbers);

  // 새로운 배열에 저장
  $newLottoArray[$key] = $newString;
}

$_newLotto = count($newLottoArray);
$fullLoops = floor($_newLotto / count($lottoColumns)); // $_lotto를 5로 나눈 몫 (모든 루프가 5개 값으로 채워질 때까지)
$partialLoop = $_newLotto % count($lottoColumns); // 나머지 루프 (부분 루프로 채울 값 개수)

$_주문번호 = array();
$구매일자 = date();

$오늘 = date("YmdHis");
for ($i = 0; $i < $fullLoops + ($partialLoop > 0 ? 1 : 0); $i++) {

  // 함수 호출 예시
  $bgNumber = getOrderNumber('_bg_number.txt', 56);
  $vrNumber = getOrderNumber('_barcode_number.txt', 16);

    // /echo "Loop " . ($i + 1) . "\n";
    $sql = "insert into lr_orders_test set ";
    if($coupon_idx){
      $sql.= "coupon = {$coupon_idx}, ";
    }
    $sql.= "print_drow_code = {$스캔본_코드}, ";
    $sql.= "barcode = {$vrNumber},";
    $sql.= "bgimg = {$bgNumber},";
    $sql.= "midx = {$member['idx']}, ";
    $sql.= "gubun = '{$order_game_name}', ";
    $sql.= "round = {$order_round}, ";
    $loopSize = ($i < $fullLoops) ? count($lottoColumns) : $partialLoop;
    $ltcont = 0;
    for ($j = 0; $j < $loopSize; $j++) {
        $valueIndex = $i * count($lottoColumns) + $j;
        $value = $newLottoArray[$valueIndex];
        if ($value) {
            $ltcont++;
        }
      $sql.= $lottoColumns[$j]." = '{$value}', ";
      if($order_game_name=="mm"){ //메가밀리언만
        $config2 = 사이트설정();
        $probabilities = [
            2 => $config2['레버리지2'],
            3 => $config2['레버리지3'],
            4 => $config2['레버리지4'],
            5 => $config2['레버리지5'],
            10 => $config2['레버리지10'],
        ];


        $randomResult = getValidRandomLeverage($probabilities);
        //echo $config['레버리지'.$randomResult]."\n";
        if($config2['레버리지2'] < 0){
            db_query("update lr_config set 레버리지2 = 0");
        }
        if($config2['레버리지3'] < 0){
            db_query("update lr_config set 레버리지3 = 0");
        }
        if($config2['레버리지4'] < 0){
            db_query("update lr_config set 레버리지4 = 0");
        }
        if($config2['레버리지5'] < 0){
            db_query("update lr_config set 레버리지5 = 0");
        }
        if($config2['레버리지10'] < 0){
            db_query("update lr_config set 레버리지10 = 0");
        }

        $sql.= "leverage".$ltcont." = {$randomResult}, ";
        // $레버리지 = db_select('select 레버리지2, 레버리지3, 레버리지4, 레버리지5, 레버리지10 from lr_config ');
        $csql = "update lr_config set 레버리지{$randomResult} = 레버리지{$randomResult} - 1";
        //echo $csql."\n";
        db_query($csql);

        $config3 = 사이트설정();
        if($config3['레버리지2'] <= 0 and $config3['레버리지3'] <= 0 and $config3['레버리지4'] <= 0 and
            $config3['레버리지5'] <= 0 and $config3['레버리지10'] <= 0){

            $config_sql = "update lr_config set ";
            $config_sql.= "레버리지2 = {$config3['set레버리지2']}, ";
            $config_sql.= "레버리지3 = {$config3['set레버리지3']}, ";
            $config_sql.= "레버리지4 = {$config3['set레버리지4']}, ";
            $config_sql.= "레버리지5 = {$config3['set레버리지5']}, ";
            $config_sql.= "레버리지10 = {$config3['set레버리지10']} ";
            db_query($config_sql);
        }
      }
    }
    //echo "Values in this loop: " . $ltcont . "\n";
    $sql.= "drawing_day = '{$order_drawing_day}', ";
    $sql.= "buy_cnt = {$ltcont}, ";

    $스캔높이 = 게임수별_프린트_높이($order_game_name, $ltcont);

    $sql.= "print_height = {$스캔높이},";
    if($event_order){
      $sql.= "event = '{$event_order}',";
    }
    $추첨일 = date("Ymd", strtotime($order_drawing_day));
    $업로드파일명 = $order_game_name.$추첨일.랜덤문자숫자(5).".jpg";
    $sql.= "scan_path = '{$order_drawing_day}', ";
    $sql.= "scan_img = '{$업로드파일명}', ";

    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $browser = getBrowser($userAgent);
    $os = getOS($userAgent);
    $osbrowser = $os." ".$browser;
    $device = PC모바일체크();
    $sql.= "device = '{$device}', ";
    $sql.= "osbrowser = '{$osbrowser}', ";
    $sql.= "regdate = now() ";
    if($ltcont>0){
      $result = db_query($sql);
      $idx = mysqli_insert_id($conn);
      array_push($_주문번호, $idx);
    }
    //echo $sql."\n";
    $randomNumber = rand(31, 37);
    $product = db_select("select * from lr_product where idx = {$randomNumber} ");
    $productsql = "insert into lr_product_orders SET ";
    $productsql.= "product_idx = {$product['idx']}, ";
    $productsql.= "midx = {$member['idx']}, ";
    $productsql.= "view_status = 0, ";
    $productsql.= "status = 0, ";
    $productsql.= "quantity = 1, ";
    $productsql.= "product_name = '{$product['product_name']}', ";
    $productsql.= "order_price = {$product['product_price']},";
    $productsql.= "order_delivery = 3000, ";
    $productsql.= "order_name = '{$member['name']}', ";
    $productsql.= "order_phone = '{$member['phone']}', ";
    $productsql.= "order_memo = '게임주문', ";
    $productsql.= "regdate = now() ";
    //echo $productsql;
    db_query($productsql);
}

db_query("update lr_member set last_buy_date = now() where idx = {$midx}  "); //마지막 구매일

if($coupon_idx>0){ //쿠폰을 사용한경우 사용완료처리
  $쿠폰data = db_select("select * from lr_coupon_log where idx = {$coupon_idx} ");
  db_query("update lr_coupon_log set status = 1 where midx = {$member['idx']} and idx = {$coupon_idx} ");

  $sql1 = "insert into lr_coupon_log_list set ";
  $sql1.= "status = 1,";
  $sql1.= "gubun = '{$쿠폰data['gubun']}', ";
  $sql1.= "cidx = {$쿠폰data['idx']},";
  $sql1.= "midx = {$member['idx']},";
  $sql1.= "name = '{$쿠폰data['name']}',";
  $sql1.= "discount = {$쿠폰data['discount']},";
  $sql1.= "usedate1 = '{$쿠폰data['usedate1']}',";
  $sql1.= "regdate = now() ";
  db_query($sql1);

  //쿠폰을 사용했음에도 할인가가 맞지않는경우?
  if($쿠폰data['gubun']=="할인쿠폰"){
    $original_price = $org_total_amount;
    $discount_percent = $쿠폰data['discount'];

    $discounted_price = $original_price * (1 - $discount_percent / 100);
    if($discounted_price != $total_amount){
      $total_amount = $discounted_price;
      //쿠폰을 사용한 할인가가 적용되도록 재수정
    }
  }
}


if($sell_lucky_point > 0){
 $포인트_사용 = $sell_lucky_point;
 $sell_포인트 = ", lucky_point = {$포인트_사용} ";
 $포인트컬럼명 = "lucky_point";
 $_포인트한글명 = $_웹사이트명."포인트";
}
if($sell_prize_money > 0){
 $포인트_사용 = $sell_prize_money;
 $sell_포인트 = ", prize_money = {$포인트_사용} ";
 $포인트컬럼명 = "prize_money";
 $_포인트한글명 = "당첨금";
}
// 총 구매금액에 대한 게임당 단가
// $단가 = $total_amount / $order_game_cnt;
// for($o=0;$o<$주문카운트;$o++){
//   $update = "update lr_orders_test set unit_price = {$단가}, total_amount = {$최종주문값} {$sell_포인트} where idx = {$_주문번호[$o]} ";
//   db_query($update);
// }

if($포인트_사용>0){
  $sql = "update lr_member set {$포인트컬럼명} = {$포인트컬럼명} - {$포인트_사용} where idx = {$member['idx']} ";
  db_query($sql);
  포인트로그($member['idx'],'차감', $_포인트한글명,$포인트_사용, '티켓 구매'); //로그기록
}


if($result){
  첫구매체크($member['idx']);
  $po = db_query("update lr_member set point = point - {$total_amount}, accumulate = accumulate + {$total_amount} where idx = {$member['idx']} ");

  if($total_amount>0){ //차감할 포인트가 잇는경우에 - 캐시 차감 내역
    // ($order_game_name=="mm" ? "메가밀리언":"파워볼").
    $sql = "insert into lr_deposit_log set ";
    $sql.= "cash_config_idx = 0, ";
    $sql.= "status = 6, ";
    $sql.= "deposit_type = 'cash', ";
    $sql.= "midx = {$member['idx']}, ";
    $sql.= "cash = {$total_amount}, ";
    $sql.= "point = {$total_amount}, ";
    $sql.= "name = '{$member['id']}', ";
    $sql.= "deposit_name = '{$member['id']}', ";
    $sql.= "deposit_date = now(), ";
    $sql.= "memo = '{$idx}|티켓구매', ";
    $sql.= "regdate = now() ";
    db_query($sql);

  }



  echo $po;
}else{
  die("구매 실패! 관리자에게 문의해주세요.");
}
