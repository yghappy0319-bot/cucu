<?
include_once('../common.php');
include 'common.php';

// echo $id;
// echo $sidx;
// $id = 5754;
// $sidx = 7;

$뱅크정보 = db_select("select * from bank_request where id = {$id} ");

if($뱅크정보['tax_status']==1){
  die('이미 처리된 건 입니다.');
}

$사이트 = db_select("select * from site where idx = {$sidx}");
if($사이트['biz_status'] != 2){
  die("사업자 정보가 등록되어있지 않습니다. 사업자정보를 수정해주세요.");
}

$팝빌회원사업자번호 = $사이트['biz_number'];
$팝빌회원아이디 = $사이트['popbill_id'];

$발행자상호 = $사이트['biz_company'];
$발행자대표 = $사이트['biz_ceoname'];
$발행자주소 = $사이트['biz_address'];
$발행자전화번호 = $사이트['phone'];
$발행담당자이메일 = $사이트['biz_manager_email'];


$총결제금액 = $뱅크정보['amount']; // 55만원
// 부가세율
$부가세율 = 0.1; // 10%
// 부가세 계산
$부가세 = $총결제금액 / (1 + $부가세율) * $부가세율;
// 공급가액 계산
$공급가액 = $총결제금액 - $부가세;

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

    $Cashbill->tradeUsage = $types;

    // [필수] 거래유형, (일반, 도서공연, 대중교통) 중 기재
    $Cashbill->tradeOpt = '일반';

    // [필수] 과세형태, (과세, 비과세) 중 기재
    $Cashbill->taxationType = '과세';

    // [필수] 거래금액, ','콤마 불가 숫자만 가능
    $Cashbill->totalAmount = $뱅크정보['amount'];

    // [필수] 공급가액, ','콤마 불가 숫자만 가능
    $Cashbill->supplyCost = $공급가액;

    // [필수] 부가세, ','콤마 불가 숫자만 가능
    $Cashbill->tax = $부가세;

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
    $Cashbill->identityNum = $뱅크정보['tax1_phone'];

    // 구매자명
    $Cashbill->customerName = $뱅크정보['name'];

    // 주문상품명
    $Cashbill->itemName = $발행자상호.'_포인트충전_'.$뱅크정보['amount'];

    // 주문주문번호
    $Cashbill->orderNumber = date("Ymdhi")."-".$뱅크정보['id'];

    // 구매자 이메일
    // 팝빌 개발환경에서 테스트하는 경우에도 안내 메일이 전송되므로,
    // 실제 거래처의 메일주소가 기재되지 않도록 주의
    $Cashbill->email = $발행담당자이메일;

    // 구매자 휴대폰
    $Cashbill->hp = $뱅크정보['phone'];

    // 발행시 알림문자 전송여부
    $Cashbill->smssendYN = false;

    try {
        $result = $CashbillService->RegistIssue($testCorpNum, $Cashbill, $memo, $testUserID, $emailSubject);
        $code = $result->code;
        $message = $result->message;
    }
    catch(PopbillException $pe) {
        $code = $pe->getCode();
        $message = $pe->getMessage();
    }

    if($message!="발행 완료"){
      die($message);
    }else{
      echo $code;
      //tax_status = 1, 현금영수증
      db_query("update bank_request set approval_number = '{$ntsConfirmNum}', tax_status = 1 where id = {$id} ");
    }

?>
