<?php

function pay_log_popbill_bootstrap_cashbill()
{
    static $booted = false;
    if ($booted) {
        return;
    }
    $popbill_dir = dirname(__DIR__) . '/Popbill/';
    require_once $popbill_dir . 'PopbillCashbill.php';

    $LinkID = 'DUDRHKS0319';
    $SecretKey = 'lHCim6QWMiwnoCNZqLY736RWtbO+yffso1uLs2UV1nI=';
    if (!defined('LINKHUB_COMM_MODE')) {
        define('LINKHUB_COMM_MODE', 'CURL');
    }

    global $CashbillService;
    $CashbillService = new CashbillService($LinkID, $SecretKey);
    $CashbillService->IsTest(false);
    $CashbillService->IPRestrictOnOff(true);
    $CashbillService->UseStaticIP(false);
    $CashbillService->UseLocalTimeYN(true);
    $booted = true;
}

function pay_log_popbill_bootstrap_taxinvoice()
{
    static $booted = false;
    if ($booted) {
        return;
    }
    $popbill_dir = dirname(__DIR__) . '/Popbill/';
    require_once $popbill_dir . 'PopbillTaxinvoice.php';

    $LinkID = 'DUDRHKS0319';
    $SecretKey = 'lHCim6QWMiwnoCNZqLY736RWtbO+yffso1uLs2UV1nI=';
    if (!defined('LINKHUB_COMM_MODE')) {
        define('LINKHUB_COMM_MODE', 'CURL');
    }

    global $TaxinvoiceService;
    $TaxinvoiceService = new TaxinvoiceService($LinkID, $SecretKey);
    $TaxinvoiceService->IsTest(false);
    $TaxinvoiceService->IPRestrictOnOff(true);
    $TaxinvoiceService->UseStaticIP(false);
    $TaxinvoiceService->UseLocalTimeYN(true);
    $booted = true;
}

function pay_log_popbill_user_id()
{
    return 'DUDRHKS0319';
}

function pay_log_popbill_corp_num($biz_no)
{
    return preg_replace('/[^0-9]/', '', (string) $biz_no);
}

function pay_log_popbill_biz_address($config)
{
    $parts = array();
    if (!empty($config['biz_zip'])) {
        $parts[] = trim((string) $config['biz_zip']);
    }
    if (!empty($config['biz_addr1'])) {
        $parts[] = trim((string) $config['biz_addr1']);
    }
    if (!empty($config['biz_addr2'])) {
        $parts[] = trim((string) $config['biz_addr2']);
    }
    return trim(implode(' ', $parts));
}

function pay_log_site_config_issuer()
{
    $config = db_select("select * from site_config where config_key = 'default' limit 1 ");
    if (!$config || empty($config['biz_no']) || empty($config['company_name']) || empty($config['ceo_name'])) {
        throw new Exception('관리자 사업자 정보가 등록되어 있지 않습니다. 마이페이지에서 사업자 정보를 등록해 주세요.');
    }
    return $config;
}

function pay_log_vat_amounts($total_amount)
{
    $total = (int) round((float) $total_amount);
    if ($total <= 0) {
        throw new Exception('발행 금액이 올바르지 않습니다.');
    }
    $tax_rate = 0.1;
    $tax = (int) round($total / (1 + $tax_rate) * $tax_rate);
    $supply = $total - $tax;
    return array(
        'total' => $total,
        'supply' => $supply,
        'tax' => $tax,
    );
}

function pay_log_cash_trade_usage($identity_num)
{
    $digits = preg_replace('/[^0-9]/', '', (string) $identity_num);
    if (strlen($digits) === 10) {
        return '지출증빙용';
    }
    return '소득공제용';
}

function pay_log_issue_cash_receipt($config, $pay_log)
{
    pay_log_popbill_bootstrap_cashbill();

    global $CashbillService;

    $corp_num = pay_log_popbill_corp_num($config['biz_no']);
    $user_id = pay_log_popbill_user_id();
    $amounts = pay_log_vat_amounts($pay_log['amt']);

    $identity_num = trim((string) $pay_log['cr_phone']);
    if ($identity_num === '') {
        throw new Exception('현금영수증 식별번호가 없습니다.');
    }

    $member = db_select('select mb_name, mb_hp from member where mb_no = ' . (int) $pay_log['midx'] . ' limit 1 ');
    $customer_name = !empty($pay_log['deposit_name']) ? $pay_log['deposit_name'] : $pay_log['userid'];
    if (!empty($member['mb_name'])) {
        $customer_name = $member['mb_name'];
    }

    $issuer_addr = pay_log_popbill_biz_address($config);
    $mgt_key = date('ymd') . '_' . 랜덤문자열(17);

    $Cashbill = new Cashbill();
    $Cashbill->mgtKey = $mgt_key;
    $Cashbill->orgConfirmNum = '';
    $Cashbill->orgTradeDate = '';
    $Cashbill->tradeType = '승인거래';
    $Cashbill->tradeUsage = pay_log_cash_trade_usage($identity_num);
    $Cashbill->tradeOpt = '일반';
    $Cashbill->taxationType = '과세';
    $Cashbill->totalAmount = (string) $amounts['total'];
    $Cashbill->supplyCost = (string) $amounts['supply'];
    $Cashbill->tax = (string) $amounts['tax'];
    $Cashbill->serviceFee = '0';
    $Cashbill->franchiseCorpNum = $corp_num;
    $Cashbill->franchiseTaxRegID = '';
    $Cashbill->franchiseCorpName = $config['company_name'];
    $Cashbill->franchiseCEOName = $config['ceo_name'];
    $Cashbill->franchiseAddr = $issuer_addr;
    $Cashbill->franchiseTEL = isset($config['tel']) ? $config['tel'] : '';
    $Cashbill->identityNum = $identity_num;
    $Cashbill->customerName = $customer_name;
    $Cashbill->itemName = $pay_log['goodsname'];
    $Cashbill->orderNumber = 'paylog_' . (int) $pay_log['idx'];
    $Cashbill->email = isset($config['email']) ? $config['email'] : '';
    $Cashbill->hp = !empty($member['mb_hp']) ? $member['mb_hp'] : $identity_num;
    $Cashbill->smssendYN = false;

    try {
        $result = $CashbillService->RegistIssue($corp_num, $Cashbill, '럭키뱅크 결제 현금영수증', $user_id, '');
        $message = isset($result->message) ? $result->message : '';
        $confirm_num = isset($result->confirmNum) ? $result->confirmNum : '';
    } catch (PopbillException $pe) {
        throw new Exception($pe->getMessage());
    }

    if ($message !== '발행 완료') {
        throw new Exception($message !== '' ? $message : '현금영수증 발행에 실패했습니다.');
    }

    return array(
        'confirm_num' => $confirm_num,
        'mgt_key' => $mgt_key,
    );
}

function pay_log_issue_tax_invoice($config, $pay_log)
{
    pay_log_popbill_bootstrap_taxinvoice();

    global $TaxinvoiceService;

    $corp_num = pay_log_popbill_corp_num($config['biz_no']);
    $user_id = pay_log_popbill_user_id();
    $amounts = pay_log_vat_amounts($pay_log['amt']);

    $buyer_bizno = pay_log_popbill_corp_num($pay_log['cr_bizno']);
    if ($buyer_bizno === '') {
        throw new Exception('세금계산서 사업자번호가 없습니다.');
    }
    if (trim((string) $pay_log['cr_company']) === '' || trim((string) $pay_log['cr_ceo']) === '') {
        throw new Exception('세금계산서 공급받는자 정보가 없습니다.');
    }
    if (trim((string) $pay_log['cr_email']) === '') {
        throw new Exception('세금계산서 이메일이 없습니다.');
    }

    $issuer_addr = pay_log_popbill_biz_address($config);
    $mgt_key = date('ymd') . '_' . 랜덤문자열(17);
    $contact = trim((string) $pay_log['cr_contact']);
    if ($contact === '') {
        $contact = trim((string) $pay_log['cr_phone']);
    }

    $Taxinvoice = new Taxinvoice();
    $Taxinvoice->writeDate = date('Ymd');
    $Taxinvoice->issueType = '정발행';
    $Taxinvoice->chargeDirection = '정과금';
    $Taxinvoice->purposeType = '영수';
    $Taxinvoice->taxType = '과세';

    $Taxinvoice->invoicerCorpNum = $corp_num;
    $Taxinvoice->invoicerTaxRegID = '';
    $Taxinvoice->invoicerCorpName = $config['company_name'];
    $Taxinvoice->invoicerMgtKey = $mgt_key;
    $Taxinvoice->invoicerCEOName = $config['ceo_name'];
    $Taxinvoice->invoicerAddr = $issuer_addr;
    $Taxinvoice->invoicerBizClass = isset($config['biz_item']) ? $config['biz_item'] : '';
    $Taxinvoice->invoicerBizType = isset($config['biz_type']) ? $config['biz_type'] : '';
    $Taxinvoice->invoicerContactName = $config['ceo_name'];
    $Taxinvoice->invoicerEmail = isset($config['email']) ? $config['email'] : '';
    $Taxinvoice->invoicerTEL = isset($config['tel']) ? $config['tel'] : '';
    $Taxinvoice->invoicerHP = isset($config['tel']) ? $config['tel'] : '';
    $Taxinvoice->invoicerSMSSendYN = false;

    $Taxinvoice->invoiceeType = '사업자';
    $Taxinvoice->invoiceeCorpNum = $buyer_bizno;
    $Taxinvoice->invoiceeTaxRegID = '';
    $Taxinvoice->invoiceeCorpName = $pay_log['cr_company'];
    $Taxinvoice->invoiceeMgtKey = '';
    $Taxinvoice->invoiceeCEOName = $pay_log['cr_ceo'];
    $Taxinvoice->invoiceeAddr = '';
    $Taxinvoice->invoiceeBizType = '';
    $Taxinvoice->invoiceeBizClass = '';
    $Taxinvoice->invoiceeContactName1 = $pay_log['cr_ceo'];
    $Taxinvoice->invoiceeEmail1 = $pay_log['cr_email'];
    $Taxinvoice->invoiceeTEL1 = $contact;
    $Taxinvoice->invoiceeHP1 = $contact;

    $Taxinvoice->supplyCostTotal = (string) $amounts['supply'];
    $Taxinvoice->taxTotal = (string) $amounts['tax'];
    $Taxinvoice->totalAmount = (string) $amounts['total'];
    $Taxinvoice->serialNum = (string) (int) $pay_log['idx'];
    $Taxinvoice->cash = '';
    $Taxinvoice->chkBill = '';
    $Taxinvoice->note = '';
    $Taxinvoice->credit = '';
    $Taxinvoice->kwon = 1;
    $Taxinvoice->ho = 1;
    $Taxinvoice->businessLicenseYN = false;
    $Taxinvoice->bankBookYN = false;

    $Taxinvoice->detailList = array();
    $Taxinvoice->detailList[] = new TaxinvoiceDetail();
    $Taxinvoice->detailList[0]->serialNum = 1;
    $Taxinvoice->detailList[0]->purchaseDT = date('Ymd');
    $Taxinvoice->detailList[0]->itemName = $pay_log['goodsname'];
    $Taxinvoice->detailList[0]->spec = '';
    $Taxinvoice->detailList[0]->qty = '';
    $Taxinvoice->detailList[0]->unitCost = '';
    $Taxinvoice->detailList[0]->supplyCost = (string) $amounts['supply'];
    $Taxinvoice->detailList[0]->tax = (string) $amounts['tax'];
    $Taxinvoice->detailList[0]->remark = '';

    try {
        $result = $TaxinvoiceService->RegistIssue(
            $corp_num,
            $Taxinvoice,
            $user_id,
            false,
            true,
            '럭키뱅크 결제 세금계산서',
            '',
            ''
        );
        $message = isset($result->message) ? $result->message : '';
        $confirm_num = isset($result->ntsConfirmNum) ? $result->ntsConfirmNum : '';
    } catch (PopbillException $pe) {
        throw new Exception($pe->getMessage());
    }

    if ($message !== '발행 완료') {
        throw new Exception($message !== '' ? $message : '세금계산서 발행에 실패했습니다.');
    }

    return array(
        'confirm_num' => $confirm_num,
        'mgt_key' => $mgt_key,
    );
}

function pay_log_issue_receipt($pay_log)
{
    $config = pay_log_site_config_issuer();
    $receipt_type = (int) $pay_log['receipt_type'];

    if ($receipt_type === 1) {
        return pay_log_issue_cash_receipt($config, $pay_log);
    }
    if ($receipt_type === 2) {
        return pay_log_issue_tax_invoice($config, $pay_log);
    }

    throw new Exception('지원하지 않는 영수증 타입입니다.');
}
