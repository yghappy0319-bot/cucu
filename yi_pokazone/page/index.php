<?php
/**
 * 메인은 루트 /index.php 만 사용합니다.
 */
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';
header('Location: /index.php' . $qs, true, 301);
exit;
