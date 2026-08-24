<?php
require_once __DIR__ . '/../lib/_function.php';

pz_admin_session_destroy();

header('Location: /admin/login.php');
exit;
