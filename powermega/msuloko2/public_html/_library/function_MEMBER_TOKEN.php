<?php
  function F_MEMBER_TOKEN($_L) {
    global $db;

    $_L = F_add_slashes($_L);

    if ($_L['mode'] == "insert") {
      $query = "
        INSERT INTO MEMBER_TOKEN(
          USER_ID,
          TOKEN,
          DEVICE
        ) VALUES (
          '{$_L['USER_ID']}',
          '{$_L['TOKEN']}',
          '{$_L['DEVICE']}'
        )
      ";
    };

    if ($_L['mode'] == "update") {
      $query = "UPDATE MEMBER_TOKEN SET STATUS = '{$_L['STATUS']}' WHERE IDX = '{$_L['TOKEN_IDX']}'";
    }

    if ($_L['mode'] == "delete") {
      $query = "DELETE FROM MEMBER_TOKEN WHERE TOPIC_IDX = '{$_L['TOPIC_IDX']}' AND TOKEN = '{$_L['TOKEN']}'";
    };

    $db->query($query);
  }
?>
