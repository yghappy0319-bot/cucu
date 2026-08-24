<?php
/**
 * 브라우저/봇 테스트용 닉 디버그
 * 예) /api/nick_debug.php?nick=진우&msg=.닉디버그
 */
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
echo nick_디버그_리포트(false);
