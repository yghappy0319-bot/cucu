<?php

$allow_ip = array('','');
$client_ip = getRealClientIp();


if (!in_array($client_ip, $allow_ip)) {
  echo "deny ip";
  syslog(LOG_DEBUG,"[APACHE CCU] Not allow ip");
  exit;
}

$target = $_GET['target'];
$ccu = (is_numeric($_GET['ccu']) ? $_GET['ccu'] : 0);

$ccufile = fopen("apache_ccu_{$target}.txt", "w") or die("Unable to open file!");
fwrite($ccufile, $ccu);
fclose($ccufile);



# client ip address
function getRealClientIp() {
  $ipaddress = '';
  if (getenv('HTTP_CLIENT_IP')) {
      $ipaddress = getenv('HTTP_CLIENT_IP');
  } else if(getenv('HTTP_X_FORWARDED_FOR')) {
      $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
  } else if(getenv('HTTP_X_FORWARDED')) {
      $ipaddress = getenv('HTTP_X_FORWARDED');
  } else if(getenv('HTTP_FORWARDED_FOR')) {
      $ipaddress = getenv('HTTP_FORWARDED_FOR');
  } else if(getenv('HTTP_FORWARDED')) {
      $ipaddress = getenv('HTTP_FORWARDED');
  } else if(getenv('REMOTE_ADDR')) {
      $ipaddress = getenv('REMOTE_ADDR');
  } else {
      $ipaddress = 'UNKNOWN';
  }
  if (strpos($ipaddress, " ") !== false) $ipaddress = explode(" ", $ipaddress)[0]; // 공백을 포한할 경우 첫번째 인자만 사용 (프록시 사용시)
  return str_replace(",", "", substr($ipaddress,0,15));
}
?>
