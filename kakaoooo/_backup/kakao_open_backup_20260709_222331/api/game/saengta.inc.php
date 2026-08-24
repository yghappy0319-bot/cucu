<?php
/**
 * .생타 — 당일 생타 순위 / 개인 조회 (info1·info3 공통)
 * 생타 = tb_msg 로우 수 + 사진 메시지당 +2 (tasu!=0). 버프타 = SUM(tasu).
 */

if (!function_exists('생타_순위문구')) {
  /**
   * @param string|null $대상닉 null이면 전체 순위, 있으면 해당 닉 1명
   */
  function 생타_순위문구($날짜, $대상닉 = null) {
    $날짜 = preg_replace('/[^0-9\-]/', '', (string)$날짜);
    if (strlen($날짜) !== 10) {
      $날짜 = date('Y-m-d');
    }
    $where닉 = '';
    if ($대상닉 !== null && trim((string)$대상닉) !== '') {
      $where닉 = " AND nickname = '" . addslashes(trim((string)$대상닉)) . "'";
    }
    $sql = "
      SELECT
        nickname AS 닉네임,
        " . 생타_SQL_select_expr('msg') . " AS 생타,
        COALESCE(SUM(tasu), 0) AS 버프타
      FROM tb_msg
      WHERE tasu != 0
        AND regdate >= '{$날짜}'
        AND regdate < '{$날짜}' + INTERVAL 1 DAY
        AND nickname NOT IN ('오픈', '')
        {$where닉}
      GROUP BY nickname
      ORDER BY 생타 DESC, 버프타 DESC, nickname ASC
    ";
    $rows = [];
    $rs = db_query($sql);
    if ($rs) {
      while ($row = db_fetch($rs)) {
        if (!empty($row['닉네임'])) {
          $rows[] = $row;
        }
      }
    }
    if ($대상닉 !== null && trim((string)$대상닉) !== '') {
      $닉 = trim((string)$대상닉);
      if (empty($rows)) {
        return "✅ {$닉} · {$날짜}\n\n생타 0 / 버프타 0";
      }
      $r = $rows[0];
      return "✅ {$닉} · {$날짜}\n\n생타 " . (int)$r['생타'] . " / 버프타 " . (int)$r['버프타'];
    }
    $msg = "✅ 생타 순위 · {$날짜}\n(생타=로우+사진+2 / 버프타)\n\n";
    if (empty($rows)) {
      return $msg . "집계할 기록이 없어요.";
    }
    $합생타 = 0;
    foreach ($rows as $i => $r) {
      $합생타 += (int)$r['생타'];
      $msg .= ($i + 1) . "등 {$r['닉네임']} " . (int)$r['생타'] . " / " . (int)$r['버프타'] . "타\n";
    }
    $평균 = count($rows) > 0 ? round($합생타 / count($rows), 1) : 0;
    $msg .= "\n참여 " . count($rows) . "명 · 평균 생타 {$평균}";
    return $msg;
  }
}

if (!function_exists('생타_명령_처리')) {
  /** .생타 일치 시 응답 후 exit. 아니면 무시. */
  function 생타_명령_처리($status, $두자리닉넴 = '', $관리자 = []) {
    if (strpos((string)$status, '.생타') === false) {
      return;
    }
    관리자전용_확인($두자리닉넴, $관리자);
    $날짜 = date('Y-m-d');
    $대상닉 = null;
    $생타_trim = trim((string)$status);
    if (preg_match('/^\.생타\s+(\d{4}-\d{2}-\d{2})\s*$/u', $생타_trim, $생타m)) {
      $날짜 = $생타m[1];
    } elseif (preg_match('/^\.생타\s+([가-힣A-Za-z]+)\s*$/u', $생타_trim, $생타m)) {
      $대상닉 = $생타m[1];
    } elseif ($생타_trim !== '.생타') {
      echo 전송("❌ .생타 · .생타 닉네임 · .생타 YYYY-MM-DD");
      exit;
    }
    echo 전송(생타_순위문구($날짜, $대상닉));
    exit;
  }
}
