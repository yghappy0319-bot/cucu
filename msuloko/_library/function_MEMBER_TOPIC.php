<?php
  function F_MEMBER_TOPIC($_L) {
    global $db;

    $_L = F_add_slashes($_L);

    if ($_L['mode'] == "insert") {
      $query = "
        INSERT INTO MEMBER_TOPIC(
          USER_ID,
          TOPIC_IDX
        ) VALUES (
          '{$_L['USER_ID']}',
          '{$_L['TOPIC_IDX']}'
        )
      ";
    }

    if ($_L['mode'] == "update") {
      $query = "
        UPDATE MEMBER_TOPIC
        SET STATUS = '{$_L['STATUS']}'
        WHERE USER_ID = '{$_L['USER_ID']}' AND TOPIC_IDX = '{$_L['TOPIC_IDX']}'
      ";
    };

    $db->query($query);
  }
?>
