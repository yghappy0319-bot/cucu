<?php
$db_admin_host = "localhost";
$db_admin_user = "pocketzone";
/** 비밀번호에 # $ 등이 있으면 반드시 작은따옴표 문자열 사용 (큰따옴표는 $ 이후 해석 오류 가능) */
$db_admin_pass = 'pocketzone12#$';
$db_admin_database = "poka";

/**
 * DB 접속 실패 시 원인 출력 (이전 서버 점검용). 확인 후 반드시 false 로 두세요.
 * true 일 때: mysqli 오류 메시지가 화면에 노출될 수 있습니다.
 */
$db_connection_debug = false;

/**
 * 사이트가 웹 루트가 아닌 하위 경로에 있을 때만 설정합니다.
 * 예: https://example.com/kakao/trade/trade.php 처럼 URL 이 하위 폴더에서 시작하면 '/kakao' (앞뒤 슬래시 없음)
 * 루트에 설치한 경우 빈 문자열로 둡니다.
 */
$site_path_prefix = '';

/**
 * 헤더·푸터 「박스 시세」 메뉴 노출
 * false — 메뉴 숨김 (페이지 URL 직접 접속은 가능)
 */
$pz_nav_show_card_price = true;

/**
 * 헤더·푸터 「전체 시세」 메뉴 노출
 * false — 메뉴 숨김 (페이지 URL 직접 접속은 가능)
 */
$pz_nav_show_market_price = true;

/**
 * 스마트택배 API — tracking.sweettracker.co.kr 에서 발급
 * 비우면 기본 택배사 목록만 사용하고, 배송조회 API는 비활성화됩니다.
 */
$sweettracker_api_key = 'OmwctD9TxXIe6B54Sj6Jhw';

/** 배송조회 템플릿 (1:Cyan 2:Pink 3:Gray 4:Tropical 5:Sky) — 새창 조회용 */
$sweettracker_template_id = 5;

/**
 * 사업자·법적 고지 (이용약관, 개인정보처리방침, 푸터)
 */
$pz_legal = [
    'company_name'         => '럭키루트',
    'ceo_name'             => '장영관',
    'biz_no'               => '649-11-02551',
    'address'              => '서울특별시 중랑구 용마산로129나길 101, A동 9층 3호',
    /** 통신판매업(중개) 신고번호 — 신고 완료 후 실제 번호로 교체 */
    'mail_order_report_no' => '준비중',
    'privacy_email'        => 'privacy@pokazone.com',
    'contact_email'        => 'support@pokazone.com',
    /** 고객센터 전화번호 — 있으면 입력 */
    'contact_phone'        => '',
    'business_hours'       => '평일 10:00 ~ 18:00',
    'terms_effective'      => '2026-06-29',
    'privacy_effective'    => '2026-06-29',
];

/** AWS S3 (거래게시판 첨부 등) — config/aws_s3.php */
if (is_file(__DIR__ . '/aws_s3.php')) {
    require_once __DIR__ . '/aws_s3.php';
}
