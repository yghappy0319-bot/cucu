<?php

include("/home/sms/public_html/lib/function.php"); //db
include("/home/sms/public_html/lib/sms_function.php");
include("/home/sms/public_html/lib/luckybank_function.php");
function POSTDATE($data, $url){
  // cURL 초기화
  $ch = curl_init();
  // cURL 옵션 설정
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, true); // POST 요청으로 설정
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // 전송할 데이터 설정
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 응답 결과를 문자열로 반환

  // 데이터 전송 및 응답 받기
  $response = curl_exec($ch);
  // cURL 에러 체크
  if (curl_errno($ch)) {
      $error = curl_error($ch);
      // 에러 처리
      echo 'cURL Error: ' . $error;
  } else {
      // 응답 결과 출력
      echo 'Response: ' . $response;
  }
  echo $url."<br />";
  // cURL 세션 닫기
  curl_close($ch);
}

function removeAfterParenthesisIfInMiddle($input) {
    $pos = strpos($input, '(');

    if ($pos !== false && $pos > 0 && $pos < strlen($input) - 1) {
        return trim(substr($input, 0, $pos));
    }

    return $input;
}

$tel = $_POST['tel'] ?? $_GET['tel'] ?? '';
$msg = $_POST['msg'] ?? $_GET['msg'] ?? '';

// 국민은행
if($msg){
  $sql = "insert into bankdata set tel = '{$tel}', sms = '{$msg}', status = 0, page = 'bankdata2', regdate = now() ";
  db_query($sql);
  $idx = mysqli_insert_id($conn);
  echo "insert status : ".$idx."<br />";
  $원본메세지 = $msg;
  $발송완료 = false;

  //여기서부터는 퍼팩트패널 미 사용자,, 즉 웹사이트로 바로 POST전송
  $sql3 = "select a.idx, a.member_no, a.site, a.bank, a.acount, a.api_url, a.telegram, b.mb_id, b.mb_name, b.mb_10
          from site a left join member b
          on a.member_no = b.mb_no
          where (a.website = 1 || a.telegram = 1) order by mb_10 desc";

  $site_result = lb_db_query($sql3); //웹사이트의 경우만
  while($site = lb_db_fetch($site_result)){

    $문자에맞는계좌확인 = 포함여부($원본메세지, $site['acount']);

    if($문자에맞는계좌확인){ //문자 내용에 마스킹된 계좌 확인
      $dateToCheck = $site['mb_10']; //서비스일
      $가입자명 = $site['mb_name'];

      if (서비스일체크($dateToCheck)) {
        echo $가입자명."__서비스기간종료__<br />";
      } else {

        $파싱메세지 = $원본메세지;
        $입금자명 = '';
        $입금액 = 0;
        $잔액 = 0;
        $거래구분 = 'deposit';

        if($site['bank']=="하나은행"){
          $하나결과 = 하나은행20241113($파싱메세지);
          $입금자명 = removeAfterParenthesisIfInMiddle($하나결과['name']);
          $입금액 = 콤마제거($하나결과['amount']);

        }

        if($site['bank']=="국민은행"){
          $_kb계좌번호_추출후문자열 = 국민_계좌번호_제거후_문자내용($파싱메세지, $site['acount']);
          $_kb_입금상태제거 = 국민_입금자명_옆_입금상태제거($_kb계좌번호_추출후문자열);
          $kb금액잔액 = 국민_입금출금_금액잔액추출($_kb_입금상태제거);
          $거래구분 = $kb금액잔액['type'];
          $입금액 = $kb금액잔액['amount'];
          $잔액 = $kb금액잔액['balance'];

          $_특수문자기준으로분할 = explode(":", $_kb_입금상태제거);
          $거래키워드 = ($거래구분 === 'withdrawal') ? '출금' : '입금';
          $키워드뒤문자열 = isset($_특수문자기준으로분할[1]) ? $_특수문자기준으로분할[1] : $_kb_입금상태제거;
          $_입금단어앞입금자명추출 = explode($거래키워드, $키워드뒤문자열);

          $입금자명 = 문자열중간에소괄호체크(국민_입금자명만추출($_입금단어앞입금자명추출[0]));
        }

        if($site['bank']=="수협"){
          $수협 = 수협_계좌번호기준으로분리($파싱메세지, $site['acount']);
          $수협결과 = 수협_문자열_금액_입금자추출1(신한_콤마전부제거($수협));

          $입금자명 = 수협_입금자명_옆_입금상태제거($수협결과['name']);
          $입금액 = $수협결과['amount'];
        }

        if($site['bank']=="농협은행"){
          $입금액 = 농협_입금_원사이금액($파싱메세지);
          $extractedText = 농협_입금자명추출($파싱메세지, $site['acount']);
          if ($extractedText !== null) {
              $입금자명 = 소괄호한개이후문자제거($extractedText,"(");
          }
        }

        if($site['bank']=="기업은행"){
          $result = 기업은행_잔액부분_추출($파싱메세지);
          $파싱메세지 = $result['rest'];
          $입금자명준비 = 기업은행_입금_이후문자열($파싱메세지); // 입금문자에서..  (금액)정한나173***24801011기업 이부분만 추출함
          // 원 이 두번들어갈시 처리가 안되서 [10,000원] 부분만 추출
          $입금금액부분추출 = 기업은행_입금금액부분_추출($입금자명준비);
          $입금자명 = 기업은행_입금자명_추출($입금금액부분추출, $site['acount']);
          $입금액 = 기업은행_문자_입금금액(콤마제거($파싱메세지));
          $잔액 = 콤마제거(rtrim($result['balance'], "원"));
        }

        if($site['bank']=="우체국"){
          $parts = explode($site['acount'], $파싱메세지);
          // '입금 ' 제거
          $after_deposit = str_replace("입금 ", "", $parts[1]);
          // '원' 기준으로 분리
          $parts = explode("원", $after_deposit, 2);

          $amount = $parts[0];
          $sender = $parts[1];

          $우체국결과 = array('name' => $sender, 'amount' => $amount);

          $입금자명 = $우체국결과['name'];
          $입금액 = $우체국결과['amount'];
          $잔액 = 0;
        }else if($site['bank']=="신협중앙회"){
          // '입금' 단어 기준으로 분리: [Web발신]신협137*****3010 02/05 16:33 | 3,000원 윤현화 잔액9,560원
          $parts = explode("입금", $파싱메세지);
          $after_deposit = trim($parts[1]); // "3,000원 윤현화 잔액9,560원"
          // '원' 기준으로 분리 (첫 번째 금액만)
          $parts = explode("원", $after_deposit, 2);
          $입금액 = trim($parts[0]); // "3,000"
          $rest = trim($parts[1]);   // "윤현화 잔액9,560원"
          // 입금자명과 잔액 추출 (잔액9,560원 형식)
          if (preg_match('/^(.*?)\s*잔액(.+?)원/u', $rest, $m)) {
            $입금자명 = trim($m[1]);
            $잔액 = 콤마제거(trim($m[2]));
          } else {
            $입금자명 = $rest;
            $잔액 = 0;
          }
        }else if($site['bank']=="새마을금고"){
          $새마을 = 새마을금고_입금자명금액추출($파싱메세지);
          $입금자명 = $새마을['name'] !== null ? $새마을['name'] : '';
          $입금액 = $새마을['amount'] !== null ? $새마을['amount'] : 0;          
        }else if($site['bank']=="케이뱅크"){
          // 예시: [Web발신][케이뱅크]장영*(0276)입금 100원잔액 ****원장영관
          $입금자명 = '';
          $입금액 = 0;
          $잔액 = 0;
          if (preg_match('/입금\s*([\d,]+)\s*원\s*잔액\s*([\*\d,]+)\s*원\s*(.+)$/u', $파싱메세지, $m)) {
            $입금액 = 콤마제거(trim($m[1]));
            $잔액원본 = trim($m[2]);
            $잔액 = (strpos($잔액원본, '*') !== false) ? 0 : 콤마제거($잔액원본);
            $입금자명 = trim($m[3]);
          }
        }

        echo $입금자명."_".$입금액."_".$잔액."<br />";

        // 모든 공백 제거 (스페이스, 탭, 줄바꿈, 전각 공백 포함)
        $_입금자명 = preg_replace('/\s|　+/u', '', $입금자명);

        $data = array(
          'name' => $_입금자명,
          'amount' => 콤마제거($입금액),
          'msg' => $원본메세지,
          'balance' => $잔액,
          'type' => $거래구분,
        );
        if($site['api_url']){
          if($site['telegram'] == 1){
            echo "send telegram :: ".$site['api_url']."<br />";
            텔레그램_입금알림전송($site['api_url'], $data);
          }else{
            echo "send :: ".$site['api_url']."<br />";
            POSTDATE($data, $site['api_url']);  //POST로 전송
          }
          site_bank_data_저장($idx, $site['member_no'], $site['site'], $site['acount'], $_입금자명, 콤마제거($입금액), $원본메세지);
          $발송완료 = true;
          echo "site_bank_data log saved ({$site['site']})<br />";
        }
      }
    }

  }

  if ($발송완료) {
    db_query("update bankdata set 가공 = 1 where id = {$idx} ");
  }
}
