<?php

require_once __DIR__ . '/../../lib/_function.php';

$ad = login_admin();
if (!$ad) {
    header('Location: /admin/login.php');
    exit;
}
