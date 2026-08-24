<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

if (!empty($_GET['cp_id'])) {
  exec_partner($_GET['cp_id'], 'PC');
}

$target = "https://".$_SERVER['HTTP_HOST']."/index.html";

if (!headers_sent()) {
  header("Location: ".$target, true, 302);
  exit;
}

echo "<script>location.replace('".$target."');</script>";
echo '<meta http-equiv="refresh" content="0;url='.$target.'">';
exit;
