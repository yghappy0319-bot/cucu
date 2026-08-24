<?
include_once('../common.php');
include 'biz_common.php';

// $id = 5754;
// $sidx = 8;

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

$발행자종목 = $사이트['biz_etc1'];
$발행자업태 = $사이트['biz_etc2'];

$공급자_담당자명 = $사이트['biz_manager_name'];
$공급자_담당자이메일 = $사이트['biz_manager_email'];
$공급자_담당자연락처 = $사이트['biz_manager_phone'];


    // 팝빌회원 사업자번호, '-' 제외 10자리
    //$testCorpNum = '3246600353';
    $testCorpNum = $팝빌회원사업자번호;

    // 팝빌회원 아이디
    //$testUserID = 'lucky_snseyes';
    $testUserID = $팝빌회원아이디;

    $오늘 = date("Ymd");
    $mgtKey = date("ymd")."_".랜덤문자열(17);
    // 세금계산서 문서번호
    // - 최대 24자리 숫자, 영문, '-', '_' 조합으로 사업자별로 중복되지 않도록 구성
    $invoicerMgtKey = $mgtKey;

    // 지연발행 강제여부
    $forceIssue = true;

    // 즉시 발행 메모
    $memo = '즉시 발행 메모';

    // 안내메일 제목, 미기재시 기본제목으로 전송
    $emailSubject = '';

    // 거래명세서 동시작성 여부
    $writeSpecification = false;

    // 거래명세서 동시작성시 명세서 문서번호
    // - 최대 24자리 숫자, 영문, '-', '_' 조합으로 사업자별로 중복되지 않도록 구성
    $dealInvoiceMgtKey = '';



    /************************************************************
     *                        세금계산서 정보
     ************************************************************/

    // 세금계산서 객체 생성
    $Taxinvoice = new Taxinvoice();

    // [필수] 작성일자, 형식(yyyyMMdd) 예)20150101

    $Taxinvoice->writeDate = $오늘;

    // [필수] 발행형태, '정발행', '역발행', '위수탁' 중 기재
    $Taxinvoice->issueType = '정발행';

    // [필수] 과금방향,
    // - '정과금'(공급자 과금), '역과금'(공급받는자 과금) 중 기재, 역과금은 역발행시에만 가능.
    $Taxinvoice->chargeDirection = '정과금';

    // [필수] '영수', '청구', '없음' 중 기재
    $Taxinvoice->purposeType = '영수';

    // [필수] 과세형태, '과세', '영세', '면세' 중 기재
    $Taxinvoice->taxType = '과세';


    /************************************************************
     *                         공급자 정보
     ************************************************************/

    // [필수] 공급자 사업자번호
    $Taxinvoice->invoicerCorpNum = $testCorpNum;

    // 공급자 종사업장 식별번호, 4자리 숫자 문자열
    $Taxinvoice->invoicerTaxRegID = '';

    // [필수] 공급자 상호
    $Taxinvoice->invoicerCorpName = $발행자상호;

    // [필수] 공급자 문서번호, 최대 24자리 숫자, 영문, '-', '_' 조합으로 사업자별로 중복되지 않도록 구성
    $Taxinvoice->invoicerMgtKey = $invoicerMgtKey;

    // [필수] 공급자 대표자성명
    $Taxinvoice->invoicerCEOName = $발행자대표;

    // 공급자 주소
    $Taxinvoice->invoicerAddr = $발행자주소;

    // 공급자 종목
    $Taxinvoice->invoicerBizClass = $발행자종목;

    // 공급자 업태
    $Taxinvoice->invoicerBizType = $발행자업태;

    // 공급자 담당자 성명
    $Taxinvoice->invoicerContactName = $공급자_담당자명;

    // 공급자 담당자 메일주소
    $Taxinvoice->invoicerEmail = $공급자_담당자이메일;

    // 공급자 담당자 연락처
    $Taxinvoice->invoicerTEL = $공급자_담당자연락처;

    // 공급자 휴대폰 번호
    $Taxinvoice->invoicerHP = $발행자전화번호;

    // 발행시 알림문자 전송여부 (정발행에서만 사용가능)
    // - 공급받는자 주)담당자 휴대폰번호(invoiceeHP1)로 전송
    // - 전송시 포인트가 차감되며 전송실패하는 경우 포인트 환불처리
    $Taxinvoice->invoicerSMSSendYN = false;

    /************************************************************
     *                      공급받는자 정보
     ************************************************************/
$공급받는자 = db_select("select * from bank_request where id = {$id} ");

$공급받는자_사업자번호 = $공급받는자['biz_number'];

$공급받는자_상호 = $공급받는자['biz_name1'];
$공급받는자_대표자명 = $공급받는자['biz_name2'];
$공급받는자_주소 = $공급받는자['biz_address'];
$공급받는자_업태 = $공급받는자['biz_etc3'];
$공급받는자_종목 = $공급받는자['biz_etc4'];

$공급받는자_담당자연락처 = $공급받는자['biz_etc2'];

$공급받는자_담당자이메일 = $공급받는자['biz_email'];
$공급받는자_담당자휴대폰 = $공급받는자['biz_etc2'];

$총결제금액 = $공급받는자['amount']; // 55만원
// 부가세율
$부가세율 = 0.1; // 10%
// 부가세 계산
$부가세 = $총결제금액 / (1 + $부가세율) * $부가세율;
// 공급가액 계산
$공급가액 = $총결제금액 - $부가세;


    // [필수] 공급받는자 구분, '사업자', '개인', '외국인' 중 기재
    $Taxinvoice->invoiceeType = '사업자';

    // [필수] 공급받는자 사업자번호
    $Taxinvoice->invoiceeCorpNum = $공급받는자_사업자번호;

    // 공급받는자 종사업장 식별번호, 4자리 숫자 문자열
    $Taxinvoice->invoiceeTaxRegID = '';

    // [필수] 공급자 상호
    $Taxinvoice->invoiceeCorpName = $공급받는자_상호;

    // [역발행시 필수] 공급받는자 문서번호, 최대 24자리 숫자, 영문, '-', '_' 조합으로 사업자별로 중복되지 않도록 구성
    $Taxinvoice->invoiceeMgtKey = '';

    // [필수] 공급받는자 대표자성명
    $Taxinvoice->invoiceeCEOName = $공급받는자_대표자명;

    // 공급받는자 주소
    $Taxinvoice->invoiceeAddr = $공급받는자_주소;

    // 공급받는자 업태
    $Taxinvoice->invoiceeBizType = $공급받는자_업태;

    // 공급받는자 종목
    $Taxinvoice->invoiceeBizClass = $공급받는자_종목;

    // 공급받는자 담당자 성명
    $Taxinvoice->invoiceeContactName1 = $공급받는자_대표자명;

    // 공급받는자 담당자 메일주소
    // 팝빌 개발환경에서 테스트하는 경우에도 안내 메일이 전송되므로,
    // 실제 거래처의 메일주소가 기재되지 않도록 주의
    $Taxinvoice->invoiceeEmail1 = $공급받는자_담당자이메일;

    // 공급받는자 담당자 연락처
    $Taxinvoice->invoiceeTEL1 = $공급받는자_담당자연락처;

    // 공급받는자 담당자 휴대폰 번호
    $Taxinvoice->invoiceeHP1 = $공급받는자_담당자연락처;


    /************************************************************
     *                       세금계산서 기재정보
     ************************************************************/

    // [필수] 공급가액 합계
    $Taxinvoice->supplyCostTotal = $공급가액;

    // [필수] 세액 합계
    $Taxinvoice->taxTotal = $부가세;

    // [필수] 합계금액, (공급가액 합계 + 세액 합계)
    $Taxinvoice->totalAmount = $총결제금액;

    // 기재상 '일련번호'항목
    $Taxinvoice->serialNum = '123';

    // 기재상 '현금'항목
    $Taxinvoice->cash = '';

    // 기재상 '수표'항목
    $Taxinvoice->chkBill = '';
    // 기재상 '어음'항목
    $Taxinvoice->note = '';

    // 기재상 '외상'항목
    $Taxinvoice->credit = '';

    // 기재상 '비고' 항목
    // $Taxinvoice->remark1 = '비고1';
    // $Taxinvoice->remark2 = '비고2';
    // $Taxinvoice->remark3 = '비고3';

    // 기재상 '권' 항목, 최대값 32767
    // 미기재시 $Taxinvoice->kwon = null;
    $Taxinvoice->kwon = 1;

    // 기재상 '호' 항목, 최대값 32767
    // 미기재시 $Taxinvoice->ho = null;
    $Taxinvoice->ho = 1;

    // 사업자등록증 이미지파일 첨부여부
    $Taxinvoice->businessLicenseYN = false;

    // 통장사본 이미지파일 첨부여부
    $Taxinvoice->bankBookYN = false;



    /************************************************************
     *                     수정 세금계산서 기재정보
     * - 수정세금계산서 관련 정보는 연동매뉴얼 또는 개발가이드 링크 참조
     * - [참고] 수정세금계산서 작성방법 안내 - http://blog.linkhubcorp.com/650
     ************************************************************/

    // [수정세금계산서 작성시 필수] 수정사유코드, 수정사유에 따라 1~6중 선택기재
    //$Taxinvoice->modifyCode = '';

    // [수정세금계산서 작성시 필수] 수정사유코드, 수정사유에 따라 1~6 중 선택기재.
    //$Taxinvoice->orgNTSConfirmNum = '';


    /************************************************************
     *                       상세항목(품목) 정보
     ************************************************************/

    $Taxinvoice->detailList = array();

    $Taxinvoice->detailList[] = new TaxinvoiceDetail();
    $Taxinvoice->detailList[0]->serialNum = 1;  // [상세항목 배열이 있는 경우 필수] 일련번호 1~99까지 순차기재,
    $Taxinvoice->detailList[0]->purchaseDT = date("Ymd");  // 거래일자
    $Taxinvoice->detailList[0]->itemName = '상품구매';	// 품명
    $Taxinvoice->detailList[0]->spec = '';  // 규격
    $Taxinvoice->detailList[0]->qty = ''; // 수량
    $Taxinvoice->detailList[0]->unitCost = '';  // 단가
    $Taxinvoice->detailList[0]->supplyCost = $공급가액;	// 공급가액
    $Taxinvoice->detailList[0]->tax = $부가세;	// 세액
    $Taxinvoice->detailList[0]->remark = '';  // 비고


    try {
        $result = $TaxinvoiceService->RegistIssue($testCorpNum, $Taxinvoice, $testUserID,
            $writeSpecification, $forceIssue, $memo, $emailSubject, $dealInvoiceMgtKey);
        $code = $result->code;
        $message = $result->message;
        $ntsConfirmNum = $result->ntsConfirmNum;
    }
    catch(PopbillException $pe) {
        $code = $pe->getCode();
        $message = $pe->getMessage();
    }

    if($message!="발행 완료"){
      die($message);
    }else{
      echo $code;
      //tax_status = 1, 전자세금계산서
      db_query("update bank_request set approval_number = '{$ntsConfirmNum}', tax_status = 2 where id = {$id} ");
    }

?>
