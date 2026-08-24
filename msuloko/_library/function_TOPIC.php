<?php
  function F_TOPIC_LIST($_L) {
    global $db;

    $add_query = "";
    $_L = F_add_slashes($_L);

    if ($_L['add_query']) {
      $add_query .= stripslashes($_L['add_query']);
    }

    if ($add_query) {
      $querylen = strlen($add_query);
      $where_query = " WHERE ".substr($add_query, 4, $querylen-4);
    }

    $query = "
      SELECT
        T.IDX AS TOPIC_IDX,
        T.NAME AS TOPIC_NAME,
        T.TITLE AS TOPIC_TITLE,
        T.BASIC AS IS_BASIC,
        M.USER_ID AS USER_ID,
        M.STATUS AS STATUS
      FROM
        TOPIC AS T
          LEFT JOIN
        (
          SELECT
            IDX, USER_ID, TOPIC_IDX, STATUS
          FROM
            MEMBER_TOPIC
          WHERE USER_ID = '{$_L['USER_ID']}'
        ) AS M ON M.TOPIC_IDX = T.IDX
      $where_query
      GROUP BY T.IDX
      ORDER BY T.SORT ASC
    ";
    $info = $db->get_list($query);
    return $info;
  };
?>
