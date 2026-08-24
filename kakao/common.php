<?
include_once $_SERVER['DOCUMENT_ROOT']."/lib/function.php";

define('STRING_ENCRYPT_FUNCTION', 'create_hash');
define('MYSQL_PASSWORD_LENGTH', 41);         // mysql password length 41, old_password 의 경우에는 16


$카카오링크 = "https://open.kakao.com/o/soV2H3gf";
$텔레그램링크 = "https://t.me/yghappy";

회원_휴대폰인증컬럼확보();
