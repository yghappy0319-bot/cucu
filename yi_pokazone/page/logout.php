<?php
/**
 * 로그아웃은 루트 /logout.php 만 사용합니다.
 */
header('Location: /logout.php', true, 301);
exit;
