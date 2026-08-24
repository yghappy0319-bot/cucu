<?php
include_once "/home/lotto/public_html/_common/config.php";
include_once "/home/lotto/public_html/Popbill/common.php";

//$사업자정보 = db_select("select * from receipt_info where code = '{$code}' ");
//if(!$사업자정보['idx']){
//  echo json_encode([
//      'status' => 'error',
//      'message' => '사업자정보가 등록되어있지 않습니다.'
//  ]);
//  exit;
//}
//
//$sql = "select count(*) as cnt from receipt_log where code = '{$code}' and od_id = {$od_id} ";
//$_cnt = db_select($sql);
//if($_cnt['cnt']>0){
//  echo json_encode([
//      'status' => 'error',
//      'message' => '이미 신청한 정보 입니다.'
//  ]);
//  exit;
//}

$RECEIPT_INFO = $db->get_data("SELECT * FROM CASH_RECEIPT WHERE CASH_NO='".$no."'");

$info = $db->get_data("SELECT * FROM CASH WHERE CASH_NO = {$RECEIPT_INFO['CASH_NO']} ");

$회원 = $db->get_data("select * from MEMBER where USER_ID = '{$info['USER_ID']}' ");

$팝빌회원사업자번호 = "3028132445";
$팝빌회원아이디 = "slk8787";

$발행자상호 = "주식회사 수로코";
$발행자대표 = "오호동";
$발행자주소 = '서울특별시 구로구 경인로20가길 5, 9층 901-497호(오류동, 화인프라자)';
$발행자전화번호 = "1660-1018";
$발행담당자이메일 = "support@superlottokorea.co.kr";

$amount = $info['CASH'];
$거래구분 = 1;

$구매자이메일 = $회원['EMAIL'];
$구매자연락처 = $회원['HP'];

$총결제금액 = $amount; // 포함된 금액?



// 부가세율
$percent = 10;
$부가세별도 = $총결제금액 * ($percent / 100);

$total_cath = $총결제금액 + $부가세별도;

$공급가액 = $총결제금액;

    // 팝빌 회원 사업자번호, '-' 제외 10자리
    $testCorpNum = $팝빌회원사업자번호;
    // 팝빌회원 아이디
    $testUserID = $팝빌회원아이디;

    // 문서번호, 사업자별로 중복없이 1~24자리 영문, 숫자, '-', '_' 조합으로 구성
    $mgtKey = date("ymd")."_".랜덤문자열(17);


    // 메모
    $memo = '현금영수증 즉시 발행';

    // 발행안내메일 제목
    // 미기재시 기본양식으로 전송
    $emailSubject = '';

    // 현금영수증 객체 생성
    $Cashbill = new Cashbill();

    // [필수] 현금영수증 문서번호,
    $Cashbill->mgtKey = $mgtKey;

    // [취소 현금영수증 발행시 필수] 당초 승인 현금영수증 국세청승인번호
    // 국세청승인번호는 GetInfo API의 ConfirmNum 항목으로 확인할 수 있습니다.
    $Cashbill->orgConfirmNum = '';

    // [취소 현금영수증 발행시 필수] 당초 승인 현금영수증 거래일자
    // 현금영수증 거래일자는 GetInfo API의 TradeDate 항목으로 확인할 수 있습니다.
    $Cashbill->orgTradeDate = '';

    // [필수] 문서형태, (승인거래, 취소거래) 중 기재
    $Cashbill->tradeType = '승인거래';

    // [필수] 거래구분, (소득공제용, 지출증빙용) 중 기재
    if($거래구분 == 1){
      $_거래구분 = "소득공제용";
    }else{
      $_거래구분 = "지출증빙용";
    }

    $Cashbill->tradeUsage = $_거래구분;

    // [필수] 거래유형, (일반, 도서공연, 대중교통) 중 기재
    $Cashbill->tradeOpt = '일반';

    // [필수] 과세형태, (과세, 비과세) 중 기재
    $Cashbill->taxationType = '과세';

    // [필수] 거래금액, ','콤마 불가 숫자만 가능
    $Cashbill->totalAmount = $total_cath;

    // [필수] 공급가액, ','콤마 불가 숫자만 가능
    $Cashbill->supplyCost = $공급가액;

    // [필수] 부가세, ','콤마 불가 숫자만 가능
    $Cashbill->tax = $부가세별도;

    // [필수] 봉사료, ','콤마 불가 숫자만 가능
    $Cashbill->serviceFee = '0';

    // [필수] 가맹점 사업자번호
    $Cashbill->franchiseCorpNum = $testCorpNum;

    // 가맹점 종사업장 식별번호
    $Cashbill->franchiseTaxRegID = '';

    // 가맹점 상호
    $Cashbill->franchiseCorpName = $발행자상호;

    // 가맹점 대표자 성명
    $Cashbill->franchiseCEOName = $발행자대표;

    // 가맹점 주소
    $Cashbill->franchiseAddr = $발행자주소;

    // 가맹점 전화번호
    $Cashbill->franchiseTEL = $발행자전화번호;

    // [필수] 식별번호, 거래구분에 따라 작성
    // 소득공제용 - 주민등록/휴대폰/카드번호 기재가능
    // 지출증빙용 - 사업자번호/주민등록/휴대폰/카드번호 기재가능
    $Cashbill->identityNum = $회원['HP'];

    // 구매자명
    $Cashbill->customerName = $회원['NAME'];

    // 주문상품명
    $Cashbill->itemName = "포인트구매".$amount;

    // 주문주문번호
    $Cashbill->orderNumber = date("Ymdhi")."-".랜덤문자열(17);

    // 구매자 이메일
    // 팝빌 개발환경에서 테스트하는 경우에도 안내 메일이 전송되므로,
    // 실제 거래처의 메일주소가 기재되지 않도록 주의
    $Cashbill->email = $구매자이메일 ? $구매자이메일:$발행담당자이메일;


    // 구매자 휴대폰
    $Cashbill->hp = $구매자연락처;

    // 발행시 알림문자 전송여부
    $Cashbill->smssendYN = false;

    try {
        $result = $CashbillService->RegistIssue($testCorpNum, $Cashbill, $memo, $testUserID, $emailSubject);
        $code = $result->code;
        $message = $result->message;
        $confirmNum = $result->confirmNum;

        if($confirmNum){
            $query = "UPDATE CASH_RECEIPT SET STATUS = 'Y' WHERE CASH_NO = '{$no}'";
            $db->query($query);
        }

    }
    catch(PopbillException $pe) {
        $code = $pe->getCode();
        $message = $pe->getMessage();
    }

    // 성공 응답
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Receipt processed successfully.',
        'data' => [
            'status' => $message,
            'confirmNum' => $confirmNum
        ]
    ]);
?>
