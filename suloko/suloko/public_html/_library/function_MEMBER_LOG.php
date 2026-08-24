<?php
  // 정보 처리
  function F_MEMBER_LOG($_L) {
    global $db;

    $_L = F_add_slashes($_L);

    if ($_L['mode'] == 'insert') {
      $query = "
        INSERT INTO MEMBER_LOG(
          LOG_NO,
          USER_ID,
          HP,
          NAME,
          BIRTH,
          COUNTRY,
          GENDER,
          EMAIL,
          LEVEL,
          PATNER_NO,
          TOKEN,
          IS_MARKETING,
          IN_TYPE,
          INIP,
          REG_DATE,
          PARTNER_ID
        ) VALUES (
          '{$_L['LOG_NO']}',
          '{$_L['USER_ID']}',
          '{$_L['HP']}',
          '{$_L['NAME']}',
          '{$_L['BIRTH']}',
          '{$_L['COUNTRY']}',
          '{$_L['GENDER']}',
          '{$_L['EMAIL']}',
          '{$_L['LEVEL']}',
          '{$_L['PATNER_NO']}',
          '{$_L['TOKEN']}',
          '{$_L['IS_MARKETING']}',
          '{$_L['IN_TYPE']}',
          '{$_L['INIP']}',
          NOW(),
          '{$_L['PARTNER_ID']}'
        )
      ";
    }
    $db->query($query);
  }
?>
