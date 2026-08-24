<?php
require_once __DIR__ . '/lib/_function.php';

pz_member_session_destroy();

header('Location: /');
exit;
