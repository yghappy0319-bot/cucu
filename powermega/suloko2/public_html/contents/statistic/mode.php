<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/session_start.php"; // session start
  require_once $_SERVER["DOCUMENT_ROOT"]."/_common/config.php";

  $VAL = $_POST;
  $RES = array("error" => true, "msg" => "");

  if ($VAL['mode'] == "getPrequency") {
    $gubun = $VAL['gubun'];
    $sdate = $VAL['sdate'];
    $edate = $VAL['edate'];

    $add_query = "";
    if ($VAL['sdate'] != null) {
      $add_query = " AND PLAYDATE BETWEEN '{$sdate}' AND LAST_DAY('{$edate}')";
    }

    $query = "
      SELECT
        ball AS number,
        COUNT(*) AS count,
        (SELECT COUNT(*) FROM WINNING_NUMBER_STATISTIC WHERE GUBUN='{$gubun}'{$add_query}) AS rowcount,
        (SELECT MAXD FROM LOTTOCODE WHERE GUBUN='{$gubun}') AS max
      FROM (
        SELECT ball1 AS ball FROM WINNING_NUMBER_STATISTIC WHERE GUBUN='{$gubun}'{$add_query}
          UNION ALL
        SELECT ball2 FROM WINNING_NUMBER_STATISTIC WHERE GUBUN='{$gubun}'{$add_query}
          UNION ALL
        SELECT ball3 FROM WINNING_NUMBER_STATISTIC WHERE GUBUN='{$gubun}'{$add_query}
          UNION ALL
        SELECT ball4 FROM WINNING_NUMBER_STATISTIC WHERE GUBUN='{$gubun}'{$add_query}
          UNION ALL
        SELECT ball5 FROM WINNING_NUMBER_STATISTIC WHERE GUBUN='{$gubun}'{$add_query}
      ) AS balls
      GROUP BY ball
      ORDER BY ball
    ";
    $winfo = $db->get_list($query);

    $query = "
      SELECT
        ballp AS number,
        COUNT(*) AS count,
        (SELECT COUNT(*) FROM WINNING_NUMBER_STATISTIC WHERE GUBUN='{$gubun}'{$add_query}) AS rowcount,
        (SELECT MAXP FROM LOTTOCODE WHERE GUBUN='{$gubun}') AS max
      FROM
        WINNING_NUMBER_STATISTIC
      WHERE
        GUBUN='{$gubun}' {$add_query}
      GROUP BY ballp
      ORDER BY ballp
    ";
    $pinfo = $db->get_list($query);

    $RES['error'] = false;
    $RES['winfo'] = $winfo;
    $RES['pinfo'] = $pinfo;
    echo json_encode($RES);
    exit;
  } else if ($VAL['mode'] == "getLastDrawn") {
    $gubun = $VAL['gubun'];
    $sdate = $VAL['sdate'];
    $edate = $VAL['edate'];

    $add_query = "";
    if ($VAL['sdate'] != null) {
      $add_query = " AND PLAYDATE BETWEEN '{$sdate}' AND LAST_DAY('{$edate}')";
    }

    $query = "
      SELECT
        balls.number AS number,
        MAX(balls.max_playdate) AS recentdate,
        DATEDIFF(CURRENT_DATE(), MAX(balls.max_playdate)) AS daysago,
        ( SELECT COUNT(*)
          FROM WINNING_NUMBER_STATISTIC AS ws
          WHERE ws.GUBUN='{$gubun}'{$add_query} AND ws.PLAYDATE > MAX(balls.max_playdate)
        ) AS timesince,
        (SELECT MAXD FROM LOTTOCODE WHERE GUBUN='{$gubun}') AS max
      FROM (
        SELECT ball1 AS number, MAX(PLAYDATE) AS max_playdate
        FROM WINNING_NUMBER_STATISTIC
        WHERE GUBUN='{$gubun}'{$add_query}
        GROUP BY ball1
          UNION ALL
        SELECT ball2 AS number, MAX(PLAYDATE) AS max_playdate
        FROM WINNING_NUMBER_STATISTIC
        WHERE GUBUN='{$gubun}'{$add_query}
        GROUP BY ball2
          UNION ALL
        SELECT ball3 AS number, MAX(PLAYDATE) AS max_playdate
        FROM WINNING_NUMBER_STATISTIC
        WHERE GUBUN='{$gubun}'{$add_query}
        GROUP BY ball3
          UNION ALL
        SELECT ball4 AS number, MAX(PLAYDATE) AS max_playdate
        FROM WINNING_NUMBER_STATISTIC
        WHERE GUBUN='{$gubun}'{$add_query}
        GROUP BY ball4
          UNION ALL
        SELECT ball5 AS number, MAX(PLAYDATE) AS max_playdate
        FROM WINNING_NUMBER_STATISTIC
        WHERE GUBUN='{$gubun}'{$add_query}
        GROUP BY ball5
      ) AS balls
      GROUP BY balls.number
      ORDER BY balls.number
    ";
    $winfo = $db->get_list($query);

    $query = "
      SELECT
        ballp AS number,
        MAX(PLAYDATE) AS recentdate,
        DATEDIFF(CURRENT_DATE(), MAX(PLAYDATE)) AS daysago,
        ( SELECT COUNT(*)
          FROM WINNING_NUMBER_STATISTIC
          WHERE GUBUN='{$gubun}'{$add_query} AND PLAYDATE > MAX(ws.PLAYDATE)
        ) AS timesince,
        (SELECT MAXP FROM LOTTOCODE WHERE GUBUN='{$gubun}') AS max
      FROM WINNING_NUMBER_STATISTIC ws
      WHERE GUBUN='{$gubun}'{$add_query}
      GROUP BY ballp
      ORDER BY ballp
    ";
    $pinfo = $db->get_list($query);

    $RES['error'] = false;
    $RES['winfo'] = $winfo;
    $RES['pinfo'] = $pinfo;
    echo json_encode($RES);
    exit;
  }
?>
