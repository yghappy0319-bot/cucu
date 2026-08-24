<?php
/**
 * 스캔이미지를 pdf 문서로 출력(저장)하는 파일
 */
header("Cache-Control: no-cache");
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

$width = 195;

$sql = "select IMG_PATH, IMG_DATE from ORDERS where IMG_YN = 'Y' and SIGN_YN != 'C' AND IMG_DATE = '2024-04-24'";
$info = $db->get_list($sql);

for ($i = 0 ; $i < count($info['IMG_PATH']) ; $i++) {
  $table = "";
  $ticketImg     = $info['IMG_PATH'][$i];
  $ticketImgDate = $info['IMG_DATE'][$i];

  if (substr(pathinfo($ticketImg, PATHINFO_FILENAME),-1,1) == "a") {
    $ticketBackImg = (substr(pathinfo($ticketImg, PATHINFO_FILENAME),0,-1)) . "b.jpg";
  } else {
    $ticketBackImg = (string)((int)pathinfo($ticketImg, PATHINFO_FILENAME) + 1) . ".jpg";
  }

  $ticketDir  = date("ymd", strtotime($ticketImgDate));
  $ticketUrl  = $s3_ticket_url . $ticketDir . "/". $ticketImg;
  $ticketBackUrl = $s3_ticket_url . $ticketDir . "/back/" . $ticketBackImg;

  if ($i % 6 == 0) {
    $table .= "<table>";
    $table .= "<tr height='120px'></tr>";
  }

  if ($i % 2 == 0) $table .= "<tr>";
  $table .= "<td><img src='{$ticketUrl}' style='width:{$width}'></td>";
  $table .= "<td><img src='{$ticketBackUrl}' style='width:{$width}'></td>";
  if ($i % 2 == 1) $table .= "</tr>";

  if ($i % 6 == 5) {
    $table .= "</table>";
    if ($i != (count($info['IMG_PATH']) - 1)) $table .= "<div style='page-break-before:always;height:1px;'></div>";
  }
  echo $table;
}
