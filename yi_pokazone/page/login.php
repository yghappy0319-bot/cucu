<?php
/**
 * 로그인은 루트 /login.php 만 사용합니다.
 */
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';
header('Location: /login.php' . $qs, true, 301);
exit;
