<?php
include_once('../common.php');
include_once '../lib/pass.lib.php';

header('Content-Type: text/html; charset=UTF-8');

global $conn;

if (!isset($_SESSION['midx']) || (int) $_SESSION['midx'] <= 0) {
    die(결과(0, '로그인이 필요합니다.'));
}

$login_member = 로그인정보($_SESSION['midx']);
if (!$login_member || !isset($login_member['mb_no'])) {
    die(결과(0, '로그인이 필요합니다.'));
}

$login_mb_no = (int) $login_member['mb_no'];
$req_mb_no = isset($mb_no) ? (int) $mb_no : 0;

// 본인 계정만 수정 가능 (IDOR 차단)
if ($req_mb_no <= 0 || $req_mb_no !== $login_mb_no) {
    die(결과(0, '권한이 없습니다.'));
}

$mb_no = $login_mb_no;
$mb_id = isset($login_member['mb_id']) ? (string) $login_member['mb_id'] : '';
$mb_hp = isset($mb_hp) ? trim((string) $mb_hp) : '';
$password = isset($password) ? (string) $password : '';
$repassword = isset($repassword) ? (string) $repassword : '';
$mb_name = isset($mb_name) ? trim((string) $mb_name) : '';
$mb_email = isset($mb_email) ? trim((string) $mb_email) : '';
$mb_start_page = isset($mb_start_page) ? trim((string) $mb_start_page) : '';
$auth_status = isset($auth_status) ? (int) $auth_status : 0;

if ($mb_hp === '') {
    die(결과(0, '휴대폰 번호는 필수 입니다.'));
}

if ($password !== '' || $repassword !== '') {
    if ($password !== $repassword) {
        die(결과(0, '변경할 비밀번호가 같지 않습니다.'));
    }
    if (strlen($password) < 6) {
        die(결과(0, '비밀번호는 6자리 이상 입력해주세요.'));
    }
}

$mb_hp_esc = mysqli_real_escape_string($conn, $mb_hp);
$hpchk = db_select("select count(*) as cnt from member where mb_hp = '{$mb_hp_esc}' and mb_no != {$mb_no} ");
if ($hpchk && (int) $hpchk['cnt'] > 0) {
    die(결과(0, '이미 등록된 번호 입니다.'));
}

$mb_id_esc = mysqli_real_escape_string($conn, $mb_id);
$mb_start_page_esc = mysqli_real_escape_string($conn, $mb_start_page);

$sql = "update member set ";
$sql .= "mb_id = '{$mb_id_esc}' ";
if ($password !== '') {
    $new_pass = get_encrypt_string($password);
    $new_pass_esc = mysqli_real_escape_string($conn, $new_pass);
    $sql .= ",mb_password = '{$new_pass_esc}' ";
}
if ($mb_name !== '') {
    $mb_name_esc = mysqli_real_escape_string($conn, $mb_name);
    $sql .= ",mb_name = '{$mb_name_esc}' ";
}
if ($mb_email !== '') {
    $mb_email_esc = mysqli_real_escape_string($conn, $mb_email);
    $sql .= ",mb_email = '{$mb_email_esc}' ";
}
$sql .= ",mb_start_page = '{$mb_start_page_esc}' ";
if ($auth_status === 2 && $mb_hp !== '') {
    $sql .= ",mb_hp = '{$mb_hp_esc}' ";
    $sql .= ',mb_hp_cert = 1 ';
}
$sql .= "where mb_no = {$mb_no} limit 1 ";

$result = db_query($sql);
if (!$result) {
    die(결과(0, '정보변경 실패! 관리자에게 문의해주세요.'));
}

// 관리자만 사업자 정보(site_config) 저장 가능
if ($login_member['mb_id'] === 'admin') {
    $biz_no = mysqli_real_escape_string($conn, trim((string) (isset($biz_no) ? $biz_no : '')));
    $company_name = mysqli_real_escape_string($conn, trim((string) (isset($company_name) ? $company_name : '')));
    $ceo_name = mysqli_real_escape_string($conn, trim((string) (isset($ceo_name) ? $ceo_name : '')));
    $biz_type = mysqli_real_escape_string($conn, trim((string) (isset($biz_type) ? $biz_type : '')));
    $biz_item = mysqli_real_escape_string($conn, trim((string) (isset($biz_item) ? $biz_item : '')));
    $biz_zip = mysqli_real_escape_string($conn, trim((string) (isset($biz_zip) ? $biz_zip : '')));
    $biz_addr1 = mysqli_real_escape_string($conn, trim((string) (isset($biz_addr1) ? $biz_addr1 : '')));
    $biz_addr2 = mysqli_real_escape_string($conn, trim((string) (isset($biz_addr2) ? $biz_addr2 : '')));
    $tel = mysqli_real_escape_string($conn, trim((string) (isset($tel) ? $tel : '')));
    $email = mysqli_real_escape_string($conn, trim((string) (isset($email) ? $email : '')));
    $updated_by = (int) $login_member['mb_no'];

    $create_sql = "
    CREATE TABLE IF NOT EXISTS `site_config` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `config_key` VARCHAR(50) NOT NULL DEFAULT 'default',
      `biz_no` VARCHAR(20) NOT NULL DEFAULT '',
      `company_name` VARCHAR(100) NOT NULL DEFAULT '',
      `ceo_name` VARCHAR(50) NOT NULL DEFAULT '',
      `biz_type` VARCHAR(100) NOT NULL DEFAULT '',
      `biz_item` VARCHAR(100) NOT NULL DEFAULT '',
      `biz_zip` VARCHAR(10) NOT NULL DEFAULT '',
      `biz_addr1` VARCHAR(255) NOT NULL DEFAULT '',
      `biz_addr2` VARCHAR(255) NOT NULL DEFAULT '',
      `tel` VARCHAR(20) NOT NULL DEFAULT '',
      `email` VARCHAR(100) NOT NULL DEFAULT '',
      `updated_by` INT UNSIGNED NOT NULL DEFAULT 0,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_site_config_key` (`config_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ";
    db_query($create_sql);

    $config_sql = "INSERT INTO site_config
                  (config_key, biz_no, company_name, ceo_name, biz_type, biz_item, biz_zip, biz_addr1, biz_addr2, tel, email, updated_by)
                 VALUES
                  ('default', '{$biz_no}', '{$company_name}', '{$ceo_name}', '{$biz_type}', '{$biz_item}', '{$biz_zip}', '{$biz_addr1}', '{$biz_addr2}', '{$tel}', '{$email}', {$updated_by})
                 ON DUPLICATE KEY UPDATE
                  biz_no = VALUES(biz_no),
                  company_name = VALUES(company_name),
                  ceo_name = VALUES(ceo_name),
                  biz_type = VALUES(biz_type),
                  biz_item = VALUES(biz_item),
                  biz_zip = VALUES(biz_zip),
                  biz_addr1 = VALUES(biz_addr1),
                  biz_addr2 = VALUES(biz_addr2),
                  tel = VALUES(tel),
                  email = VALUES(email),
                  updated_by = VALUES(updated_by)";
    db_query($config_sql);
}

die(결과(1, '정보변경 완료'));
