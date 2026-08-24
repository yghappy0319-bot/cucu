<?php
// 현금영수증 신청
function F_CASH_RECEIPT($_L) {
  global $db;

  $add_query = "";

  $_L = F_add_slashes($_L);

  if ($_L["mode"] == "insert") {
    $query = "
      INSERT INTO CASH_RECEIPT(
        CASH_NO,
        USER_ID
      ) VALUES (
        '".$_L['CASH_NO']."',
        '".$_L['USER_ID']."'
      )
    ";
  }

  $db->query($query);
}
?>
