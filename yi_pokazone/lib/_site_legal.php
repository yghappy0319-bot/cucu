<?php

/**
 * 이용약관·개인정보처리방침·푸터 등에 쓰는 사업자·법적 고지 정보
 *
 * @return array{
 *   company_name: string,
 *   ceo_name: string,
 *   biz_no: string,
 *   address: string,
 *   mail_order_report_no: string,
 *   privacy_email: string,
 *   contact_email: string,
 *   contact_phone: string,
 *   business_hours: string,
 *   terms_effective: string,
 *   privacy_effective: string
 * }
 */
function site_legal_config(): array
{
    global $pz_legal;

    $defaults = [
        'company_name'         => '럭키루트',
        'ceo_name'             => '장영관',
        'biz_no'               => '649-11-02551',
        'address'              => '',
        'mail_order_report_no' => '',
        'privacy_email'        => 'privacy@pokazone.com',
        'contact_email'        => 'support@pokazone.com',
        'contact_phone'        => '',
        'business_hours'       => '평일 10:00 ~ 18:00',
        'terms_effective'      => '2026-06-29',
        'privacy_effective'    => '2026-06-29',
    ];

    if (!is_array($pz_legal ?? null)) {
        return $defaults;
    }

    return array_merge($defaults, $pz_legal);
}

function site_legal_company_name(): string
{
    return (string) (site_legal_config()['company_name'] ?? '럭키루트');
}

function site_legal_ceo_name(): string
{
    return (string) (site_legal_config()['ceo_name'] ?? '장영관');
}

function site_legal_biz_no(): string
{
    return (string) (site_legal_config()['biz_no'] ?? '');
}

function site_legal_address(): string
{
    return trim((string) (site_legal_config()['address'] ?? ''));
}

function site_legal_mail_order_report_no(): string
{
    return trim((string) (site_legal_config()['mail_order_report_no'] ?? ''));
}

function site_legal_privacy_email(): string
{
    return trim((string) (site_legal_config()['privacy_email'] ?? ''));
}

function site_legal_contact_email(): string
{
    return trim((string) (site_legal_config()['contact_email'] ?? ''));
}

function site_legal_contact_phone(): string
{
    return trim((string) (site_legal_config()['contact_phone'] ?? ''));
}

function site_legal_business_hours(): string
{
    return trim((string) (site_legal_config()['business_hours'] ?? ''));
}

function site_legal_effective_label(string $type = 'terms'): string
{
    $cfg  = site_legal_config();
    $raw  = $type === 'privacy'
        ? (string) ($cfg['privacy_effective'] ?? '2026-06-29')
        : (string) ($cfg['terms_effective'] ?? '2026-06-29');
    $ts   = strtotime($raw);

    return $ts !== false ? date('Y년 n월 j일', $ts) : $raw;
}

/**
 * @return list<string>
 */
function site_legal_business_lines(): array
{
    $lines   = [];
    $lines[] = '상호: ' . site_legal_company_name()
        . ' | 대표: ' . site_legal_ceo_name()
        . ' | 사업자등록번호: ' . site_legal_biz_no();

    $address = site_legal_address();
    if ($address !== '') {
        $lines[] = '주소: ' . $address;
    }

    $mail_order = site_legal_mail_order_report_no();
    $lines[] = '통신판매업 신고번호: ' . ($mail_order !== '' ? $mail_order : '준비중');

    $privacy_email = site_legal_privacy_email();
    if ($privacy_email !== '') {
        $lines[] = '개인정보보호책임자: ' . site_legal_ceo_name() . ' | 이메일: ' . $privacy_email;
    }

    return $lines;
}

/**
 * @return list<array{label: string, value: string, href?: string}>
 */
function site_legal_business_rows(): array
{
    $brand = function_exists('site_setting_get') ? site_setting_get('site_name', 'Pokazone') : 'Pokazone';
    $rows  = [
        ['label' => '상호', 'value' => site_legal_company_name()],
        ['label' => '대표자', 'value' => site_legal_ceo_name()],
        ['label' => '사업자등록번호', 'value' => site_legal_biz_no()],
        ['label' => '서비스명', 'value' => $brand],
    ];

    $address = site_legal_address();
    if ($address !== '') {
        $rows[] = ['label' => '사업장 주소', 'value' => $address];
    }

    $mail_order = site_legal_mail_order_report_no();
    $rows[] = [
        'label' => '통신판매업 신고번호',
        'value' => $mail_order !== '' ? $mail_order : '준비중',
    ];

    $privacy_email = site_legal_privacy_email();
    if ($privacy_email !== '') {
        $rows[] = [
            'label' => '개인정보 문의',
            'value' => $privacy_email,
            'href'  => 'mailto:' . $privacy_email,
        ];
    }

    $contact_email = site_legal_contact_email();
    if ($contact_email !== '') {
        $rows[] = [
            'label' => '고객센터 이메일',
            'value' => $contact_email,
            'href'  => 'mailto:' . $contact_email,
        ];
    }

    $phone = site_legal_contact_phone();
    if ($phone !== '') {
        $rows[] = ['label' => '고객센터 전화', 'value' => $phone];
    }

    $hours = site_legal_business_hours();
    if ($hours !== '') {
        $rows[] = ['label' => '운영 시간', 'value' => $hours];
    }

    return $rows;
}

function site_legal_business_line(): string
{
    $lines = site_legal_business_lines();

    return $lines[0] ?? '';
}
