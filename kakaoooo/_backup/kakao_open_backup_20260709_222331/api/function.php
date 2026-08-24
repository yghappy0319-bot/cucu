<?php
function getTwoCharNick($nick) {
    // 0단계: 공백 및 URL 디코딩
    $nick = urldecode(trim($nick));

    // 1단계: 성별표시 제거 — 앞(여 채채) / 뒤(채채 여) 모두
    if (preg_match('/^(남|여)\s+(.+)$/u', $nick, $match)) {
        $nick = $match[2];
    } elseif (preg_match('/^(.+?)\s+(남|여)\s*$/u', $nick, $match)) {
        $nick = $match[1];
    }

    // 2단계: 이모티콘 및 특수문자 제거
    $nick = preg_replace('/[\p{So}\p{Cn}\p{Cs}]+/u', '', $nick);

    // 3단계: 공백 정리
    $nick = trim($nick);

    if ($nick === '') {
        return '';
    }

    // 4단계: 한글 2음절 후보 수집 (다오❤채채 → [다오, 채채], 앞글자만 쓰지 않음)
    $candidates = [];
    $parts = preg_split('/[^가-힣]+/u', $nick, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($parts as $part) {
        $len = mb_strlen($part, 'UTF-8');
        if ($len === 2 && preg_match('/^[가-힣]{2}$/u', $part)) {
            $candidates[] = $part;
            continue;
        }
        if ($len >= 4 && $len % 2 === 0) {
            for ($i = 0; $i < $len; $i += 2) {
                $chunk = mb_substr($part, $i, 2, 'UTF-8');
                if (preg_match('/^[가-힣]{2}$/u', $chunk)) {
                    $candidates[] = $chunk;
                }
            }
        }
    }
    $seen = [];
    $ordered = [];
    foreach ($candidates as $c) {
        if (empty($seen[$c])) {
            $seen[$c] = true;
            $ordered[] = $c;
        }
    }
    $candidates = $ordered;

    if (!empty($candidates)) {
        $registered = [];
        foreach ($candidates as $c) {
            $esc = addslashes($c);
            $row = @db_select("SELECT name FROM tb_member WHERE name = '{$esc}' AND status = 0 LIMIT 1");
            if (empty($row['name'])) {
                $row = @db_select("SELECT name FROM tb_member WHERE TRIM(name) = '{$esc}' AND status = 0 LIMIT 1");
            }
            if (!empty($row['name'])) {
                $registered[] = $c;
            }
        }
        if (count($registered) === 1) {
            return $registered[0];
        }
        if (count($registered) > 1) {
            return $registered[count($registered) - 1];
        }
        return $candidates[count($candidates) - 1];
    }

    // 5단계: 후보 없을 때 — 기존과 동일하게 첫 한글 2글자
    if (preg_match('/[가-힣]{2}/u', $nick, $m)) {
        return $m[0];
    }

    return mb_substr($nick, 0, 2, 'UTF-8');
}

/** 봇/API 원본 닉 (nick·nickname·GET/POST/REQUEST/JSON) */
function nick_요청_JSON() {
  static $json = null;
  static $checked = false;
  if ($checked) {
    return $json;
  }
  $checked = true;
  $raw = @file_get_contents('php://input');
  if (!is_string($raw) || trim($raw) === '') {
    $json = null;
    return $json;
  }
  $decoded = json_decode($raw, true);
  $json = is_array($decoded) ? $decoded : null;
  return $json;
}

function nick_요청값($keys) {
  $keys = (array)$keys;
  foreach ($keys as $key) {
    foreach ([$_POST, $_GET, $_REQUEST] as $src) {
      if (!isset($src[$key]) || is_array($src[$key])) {
        continue;
      }
      $val = trim((string)$src[$key]);
      if ($val !== '') {
        return [$key, $val, 'REQUEST'];
      }
    }
  }
  $json = nick_요청_JSON();
  if (is_array($json)) {
    foreach ($keys as $key) {
      if (!isset($json[$key]) || is_array($json[$key])) {
        continue;
      }
      $val = trim((string)$json[$key]);
      if ($val !== '') {
        return [$key, $val, 'JSON'];
      }
    }
  }
  foreach ($keys as $key) {
    if (isset($GLOBALS[$key]) && !is_array($GLOBALS[$key])) {
      $val = trim((string)$GLOBALS[$key]);
      if ($val !== '') {
        return [$key, $val, 'GLOBAL'];
      }
    }
  }
  return ['', '', ''];
}

function nick_파라미터($fallback = '') {
  list(, $val,) = nick_요청값(['nick', 'nickname', 'name', 'user', 'sender', '닉', '닉네임', 'roomNick', 'profile', 'writer']);
  if ($val !== '') {
    return $val;
  }
  return trim((string)$fallback);
}

function msg_파라미터($fallback = '') {
  list(, $val,) = nick_요청값(['msg', 'message', 'text', 'content', 'body', 'chat', '말', '메시지']);
  if ($val !== '') {
    return $val;
  }
  return trim((string)$fallback);
}

/** 채팅 명령어 정규화 — BOM·제로폭 공백 제거, 전각 마침표 → ASCII */
if (!function_exists('status_정규화')) {
  function status_정규화($status): string {
    $t = trim((string)$status);
    $t = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{FEFF}\p{Cf}]+/u', '', $t);
    $t = preg_replace('/^．/u', '.', $t);
    return $t;
  }
}

/** 표시 닉에서 두글자 닉 후보 수집 (진우 / 여름 남 / 나나 여 등) */
function nick_두글자_후보들($nick) {
  $raw = urldecode(trim((string)$nick));
  $raw = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{FEFF}\p{Cf}]+/u', ' ', $raw);
  if (preg_match('/^(남|여)\s+(.+)$/u', $raw, $m)) {
    $raw = trim($m[2]);
  } elseif (preg_match('/^(.+?)\s+(남|여)\s*$/u', $raw, $m)) {
    $raw = trim($m[1]);
  }
  $clean = preg_replace('/[\p{Extended_Pictographic}\p{So}\p{Sk}\p{Sm}\p{Cn}\p{Cs}\p{Cf}]+/u', '', $raw);
  $clean = trim($clean);

  $시도 = [];
  $primary = getTwoCharNick($raw);
  if ($primary !== '') {
    $시도[] = $primary;
  }

  $hangul = preg_replace('/[^가-힣]/u', '', $clean);
  if (mb_strlen($hangul, 'UTF-8') === 2) {
    $시도[] = $hangul;
  }
  foreach (preg_split('/[^가-힣]+/u', $clean, -1, PREG_SPLIT_NO_EMPTY) as $part) {
    if (mb_strlen($part, 'UTF-8') === 2) {
      $시도[] = $part;
    }
  }
  if (preg_match('/[가-힣]{2}/u', $clean, $m)) {
    $시도[] = $m[0];
  }

  $seen = [];
  $ordered = [];
  foreach ($시도 as $c) {
    if ($c === '' || isset($seen[$c])) {
      continue;
    }
    $seen[$c] = true;
    $ordered[] = $c;
  }
  return $ordered;
}

/** tb_member name 으로 회원 조회 */
function 회원정보_조회($두자리닉넴) {
  if (function_exists('db_ensure_connection')) {
    db_ensure_connection();
  }
  $닉 = trim((string)$두자리닉넴);
  if ($닉 === '') {
    return [];
  }
  $esc = addslashes($닉);
  $queries = [
    "SELECT * FROM tb_member WHERE name = '{$esc}' AND status = 0 LIMIT 1",
    "SELECT * FROM tb_member WHERE name = '{$esc}' LIMIT 1",
    "SELECT * FROM tb_member WHERE TRIM(name) = '{$esc}' AND status = 0 LIMIT 1",
    "SELECT * FROM tb_member WHERE TRIM(name) = '{$esc}' LIMIT 1",
  ];
  foreach ($queries as $sql) {
    $row = db_select($sql);
    if (is_array($row) && trim((string)($row['name'] ?? '')) !== '') {
      return $row;
    }
  }
  return [];
}

/** 가방 표시용 — DB 마이그레이션 없이 로또 티켓 잔량만 조회 */
if (!function_exists('로또티켓_잔량_조회_가방')) {
  function 로또티켓_잔량_조회_가방(int $idx, array $회원정보 = []): int {
    if ($idx <= 0) {
      return 0;
    }
    if (array_key_exists('lotto_ticket', $회원정보)) {
      return max(0, (int)$회원정보['lotto_ticket']);
    }
    $row = @db_select("SELECT lotto_ticket FROM tb_member WHERE idx = {$idx} LIMIT 1");
    return max(0, (int)($row['lotto_ticket'] ?? 0));
  }
}

/** 카카오톡 등에서 첫 줄 아래 여백용 (가방·로또 구매 완료 등) */
if (!function_exists('채팅_첫줄_뒤_공백')) {
  function 채팅_첫줄_뒤_공백(): string {
    static $pad = null;
    if ($pad === null) {
      $pad = "\n" . str_repeat(' ', 800);
    }
    return $pad;
  }
}

/** `.가방` / `.가방 닉` — 처리 시 exit, 미해당 시 false */
if (!function_exists('가방_명령_처리')) {
  function 가방_명령_처리(string $status, string $호칭, array $정보, string $두자리닉넴 = ''): bool {
    $status = trim($status);
    if ($status !== '.가방' && strpos($status, '.가방') !== 0) {
      return false;
    }

    $조회닉 = trim(mb_substr($status, 3));
    if ($조회닉 === '') {
      $조회회원 = $정보;
      $조회회원_idx = (int)($정보['idx'] ?? 0);
      $조회회원_name = trim((string)($정보['name'] ?? $두자리닉넴));
      if ($호칭 === '' && function_exists('계급')) {
        $계급 = 계급($조회회원['point'] ?? 0);
        $호칭 = !empty($조회회원['title']) ? (string)$조회회원['title'] : (string)($계급['name'] ?? '');
      }
      $msg = "🎒{$호칭} {$조회회원_name} 보유 아이템" . 채팅_첫줄_뒤_공백();
    } else {
      $조회회원 = 회원정보_조회($조회닉);
      if (empty($조회회원['idx'])) {
        echo 전송("❌ '{$조회닉}' 닉네임을 찾을 수 없어요.");
        exit;
      }
      $조회회원_idx = (int)$조회회원['idx'];
      $조회회원_name = trim((string)$조회회원['name']);
      $msg = "🎒{$조회회원_name} 보유 아이템" . 채팅_첫줄_뒤_공백();
    }

    if ($조회회원_idx <= 0) {
      echo 전송("❌ 회원 정보를 찾을 수 없어요.");
      exit;
    }

    $조회회원_name_esc = addslashes($조회회원_name);
    $result = db_query("
      SELECT itemname, COUNT(*) AS cnt
      FROM tb_member_item
      WHERE nick = '{$조회회원_name_esc}' AND midx = {$조회회원_idx} AND status = 0
      GROUP BY itemname
      ORDER BY itemname
    ");
    if ($result && mysqli_num_rows($result) > 0) {
      while ($row = db_fetch($result)) {
        $msg .= "• {$row['itemname']} x{$row['cnt']}\n";
      }
    } else {
      $msg .= "(보유한 아이템 없음)\n";
    }

    $수호횟수 = (int)($조회회원['enhance_suho'] ?? 0);
    $은총횟수 = (int)($조회회원['은총개수'] ?? 0);
    $티켓 = 로또티켓_잔량_조회_가방($조회회원_idx, $조회회원);

    $msg .= "• 강화 수호 x{$수호횟수}\n";
    $msg .= "• 은총 x{$은총횟수}\n";
    $msg .= "• 로또 티켓 x{$티켓}";

    echo 전송($msg);
    exit;
  }
}

/** 후보 닉 목록으로 tb_member 일괄 조회 — [두글자닉, 정보] */
function 회원정보_후보_일괄조회(array $시도) {
  $시도 = array_values(array_unique(array_filter(array_map('trim', $시도))));
  if (empty($시도)) {
    return ['', []];
  }
  if (count($시도) === 1) {
    $정보 = 회원정보_조회($시도[0]);
    return $정보 ? [$시도[0], $정보] : ['', []];
  }

  $in = [];
  foreach ($시도 as $c) {
    $in[] = "'" . addslashes($c) . "'";
  }
  $inList = implode(',', $in);
  $fieldOrder = implode(',', $in);

  foreach ([
    "SELECT * FROM tb_member WHERE name IN ({$inList}) AND status = 0 ORDER BY FIELD(name, {$fieldOrder}) LIMIT 1",
    "SELECT * FROM tb_member WHERE name IN ({$inList}) ORDER BY FIELD(name, {$fieldOrder}) LIMIT 1",
    "SELECT * FROM tb_member WHERE TRIM(name) IN ({$inList}) AND status = 0 ORDER BY FIELD(TRIM(name), {$fieldOrder}) LIMIT 1",
    "SELECT * FROM tb_member WHERE TRIM(name) IN ({$inList}) ORDER BY FIELD(TRIM(name), {$fieldOrder}) LIMIT 1",
  ] as $sql) {
    $row = db_select($sql);
    if (is_array($row) && trim((string)($row['name'] ?? '')) !== '') {
      return [trim((string)$row['name']), $row];
    }
  }

  foreach ($시도 as $c) {
    $정보 = 회원정보_조회($c);
    if ($정보) {
      return [$c, $정보];
    }
  }
  return ['', []];
}

/** 표시 닉에서 등록된 두글자닉·회원정보 찾기 — [두글자닉, 정보] */
function 회원정보_닉해석($nick, $두자리닉넴 = '') {
  $nick = nick_파라미터($nick);
  $두자리닉넴 = trim((string)$두자리닉넴);

  $시도 = nick_두글자_후보들($nick);
  if ($두자리닉넴 !== '' && !in_array($두자리닉넴, $시도, true)) {
    array_unshift($시도, $두자리닉넴);
  }
  if (empty($시도) && $두자리닉넴 !== '') {
    $시도[] = $두자리닉넴;
  }

  list($foundNick, $정보) = 회원정보_후보_일괄조회($시도);
  if ($정보) {
    return [$foundNick, $정보];
  }

  $fallback = $두자리닉넴 !== '' ? $두자리닉넴 : ($시도[0] ?? getTwoCharNick($nick));
  return [$fallback, []];
}

/** 닉·회원정보 한 번에 갱신 (info/_mutual/config 공통) */
function 회원정보_동기화($nick = '', $두자리닉넴 = '') {
  $nick = nick_파라미터($nick);
  if ($두자리닉넴 === '' && $nick !== '') {
    $두자리닉넴 = getTwoCharNick($nick);
  }
  return 회원정보_닉해석($nick, $두자리닉넴);
}

/** 닉 인식·DB 매칭 디버그 리포트 */
function nick_디버그_리포트($short = false) {
  global $conn;

  $nick = nick_파라미터();
  $msg = msg_파라미터();
  $두자리 = getTwoCharNick($nick);
  $후보 = nick_두글자_후보들($nick);
  list($syncNick, $syncInfo) = 회원정보_동기화($nick, $두자리);

  $lines = [];
  $lines[] = '🔍 닉 디버그' . ($short ? ' (요약)' : '');

  list($nickKey, $nickVal, $nickFrom) = nick_요청값(['nick', 'nickname', 'name', 'user', 'sender', '닉', '닉네임', 'roomNick', 'profile', 'writer']);
  list($msgKey, $msgVal, $msgFrom) = nick_요청값(['msg', 'message', 'text', 'content', 'body', 'chat', '말', '메시지']);

  $lines[] = '수신 nick: ' . ($nickVal !== '' ? "[{$nickFrom}/{$nickKey}] {$nickVal}" : '(없음)');
  $lines[] = '수신 msg: ' . ($msgVal !== '' ? "[{$msgFrom}/{$msgKey}] {$msgVal}" : '(없음)');

  if (!$short) {
    $reqKeys = array_keys($_REQUEST);
    $lines[] = 'REQUEST 키: ' . (empty($reqKeys) ? '(없음)' : implode(', ', $reqKeys));
    $getKeys = array_keys($_GET);
    $postKeys = array_keys($_POST);
    if (!empty($getKeys)) {
      $lines[] = 'GET 키: ' . implode(', ', $getKeys);
    }
    if (!empty($postKeys)) {
      $lines[] = 'POST 키: ' . implode(', ', $postKeys);
    }
    $json = nick_요청_JSON();
    if (is_array($json)) {
      $lines[] = 'JSON 키: ' . implode(', ', array_keys($json));
    }
  }

  $lines[] = 'nick_파라미터: ' . ($nick === '' ? '(비어있음)' : $nick);
  $lines[] = 'getTwoCharNick: ' . ($두자리 === '' ? '(비어있음)' : $두자리);
  $lines[] = '후보닉: ' . (empty($후보) ? '(없음)' : implode(', ', $후보));

  foreach ($후보 as $c) {
    $row = 회원정보_조회($c);
    if ($row) {
      $lines[] = "DB[{$c}] ✅ name={$row['name']} status=" . ($row['status'] ?? '?') . ' newpoint=' . ($row['newpoint'] ?? 0);
    } else {
      $lines[] = "DB[{$c}] ❌ 없음";
    }
  }

  if ($syncInfo) {
    $lines[] = '동기화: ✅ ' . $syncNick . ' (newpoint ' . ($syncInfo['newpoint'] ?? 0) . ')';
  } else {
    $lines[] = '동기화: ❌ 미매칭 (추정닉: ' . ($syncNick !== '' ? $syncNick : '(없음)') . ')';
  }

  if (function_exists('db_ensure_connection')) {
    db_ensure_connection();
  }
  global $conn, $db_admin_database;
  if (isset($conn) && $conn instanceof mysqli && @mysqli_ping($conn)) {
    $dbLabel = $db_admin_database ?? '';
    if ($dbLabel === '') {
      $rs = mysqli_query($conn, 'SELECT DATABASE() AS db');
      $dbRow = $rs ? db_fetch($rs) : null;
      $dbLabel = $dbRow['db'] ?? '?';
    }
    $lines[] = 'DB연결: OK (DB: ' . $dbLabel . ')';
  } else {
    $err = function_exists('db_connection_error') ? db_connection_error() : 'unknown';
    $lines[] = 'DB연결: FAIL — ' . $err;
  }

  if (!$short) {
    $cnt = db_select('SELECT COUNT(*) AS c FROM tb_member WHERE status = 0');
    $lines[] = '등록회원수(status=0): ' . (int)($cnt['c'] ?? 0);
    if ($nick !== '') {
      $esc = addslashes($nick);
      $like = db_select("SELECT name, status FROM tb_member WHERE name LIKE '%{$esc}%' LIMIT 3");
      if (is_array($like) && !empty($like['name'])) {
        $lines[] = "유사검색(name LIKE %{$nick}%): {$like['name']} (status {$like['status']})";
      }
    }
  }

  $report = implode("\n", $lines);
  nick_디버그_로그($report);
  return $report;
}

function nick_디버그_로그($text) {
  $dir = __DIR__ . '/log';
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  @file_put_contents($dir . '/nick_debug.log', '[' . date('Y-m-d H:i:s') . "]\n" . $text . "\n---\n", FILE_APPEND | LOCK_EX);
}

/**
 * 생타 집계 SQL 식 (SELECT·GROUP BY 용).
 * tb_msg 로우 수 + msg가 '사진을 보냈습니다.' 인 로우마다 +2.
 */
if (!function_exists('생타_SQL_select_expr')) {
  function 생타_SQL_select_expr($msg_col = 'msg') {
    $m = preg_replace('/[^a-zA-Z0-9_.]/', '', (string)$msg_col);
    if ($m === '') {
      $m = 'msg';
    }
    return "COUNT(*) + COALESCE(SUM(CASE WHEN {$m} = '사진을 보냈습니다.' THEN 2 ELSE 0 END), 0)";
  }
}

/**
 * .등록 진행 문자열에서 참가자 닉(tb_member.name 기준 두 글자) 목록.
 * 예: 다오❤️산지 → [다오, 산지] / 영수민희 → [영수, 민희] / 콩이🤭다오 → [콩이, 다오]
 * (이모지·기호가 \p{L}에 걸리는 환경에서도 한글 2음절 스캔이 안전)
 */
if (!function_exists('진행문자열_닉목록')) {
  function 진행문자열_닉목록($진행) {
    $진행 = trim((string)$진행);
    if ($진행 === '') {
      return [];
    }
    $uniq = [];
    if (@preg_match_all('/[가-힣]{2}/u', $진행, $m) && !empty($m[0])) {
      foreach ($m[0] as $chunk) {
        $nn = getTwoCharNick($chunk);
        if ($nn !== '' && empty($uniq[$nn])) {
          $uniq[$nn] = true;
        }
      }
    }
    if (!empty($uniq)) {
      return array_keys($uniq);
    }
    $tok = preg_split('/[^\p{L}\p{N}]+/u', $진행, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($tok as $t) {
      $nn = getTwoCharNick(trim($t));
      if ($nn !== '' && empty($uniq[$nn])) {
        $uniq[$nn] = true;
      }
    }
    return array_keys($uniq);
  }
}

require_once __DIR__ . '/game/odd_even_ranking.php';

/** .공커등록 시 tb_couple.sort — 활성(status=0) 중 MAX(sort)+1 */
function 공커등록_다음sort() {
  $row = db_select("SELECT COALESCE(MAX(sort), 0) AS maxSort FROM tb_couple WHERE status = 0");
  return (int)($row['maxSort'] ?? 0) + 1;
}

/** .공커 입력 시 status=0 커플을 sdate ASC 순으로 sort 1부터 재배치. 배치된 최대 sort 반환 */
function 공커_sort_sdate재배치($where = '') {
  $result = db_query("SELECT idx FROM tb_couple WHERE status = 0 {$where} ORDER BY sdate ASC, idx ASC");
  $sort = 1;
  while ($row = db_fetch($result)) {
    $idx = (int)($row['idx'] ?? 0);
    if ($idx > 0) {
      db_query("UPDATE tb_couple SET sort = {$sort} WHERE idx = {$idx}");
      $sort++;
    }
  }
  return $sort - 1;
}

/** .공커 적립금 기준 amount 갱신 — sort 1(가장 오래됨)=기준, sort마다 +100만 */
function 공커_amount_갱신($공커적립, $where = '') {
  $공커적립 = (int)$공커적립;
  if ($공커적립 <= 0) {
    return;
  }
  $result = db_query("SELECT idx, sort FROM tb_couple WHERE status = 0 {$where} ORDER BY sort ASC, idx ASC");
  while ($row = db_fetch($result)) {
    $idx = (int)($row['idx'] ?? 0);
    $sort = (int)($row['sort'] ?? 0);
    if ($idx <= 0 || $sort <= 0) {
      continue;
    }
    $amt = 냥_앞3자리_뒤0($공커적립 + ($sort - 1) * 100000000);
    db_query("UPDATE tb_couple SET amount = {$amt} WHERE idx = {$idx}");
  }
}

/** 공커 couple 문자열 → 참가자 닉 (영수🖤하니 등) */
if (!function_exists('공커문자열_닉목록')) {
  function 공커문자열_닉목록($couple) {
    $couple = trim((string)$couple);
    if ($couple === '') {
      return [];
    }
    $uniq = [];
    $parts = preg_split('/\p{Extended_Pictographic}+/u', $couple, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($parts as $part) {
      $part = trim($part);
      if ($part === '') {
        continue;
      }
      $nn = function_exists('getTwoCharNick') ? getTwoCharNick($part) : $part;
      if ($nn !== '' && empty($uniq[$nn])) {
        $uniq[$nn] = true;
      }
    }
    if (!empty($uniq)) {
      return array_keys($uniq);
    }
    return 진행문자열_닉목록($couple);
  }
}

/** .공커등록 시 커플 구성원이 참가 중인 tb_progress 일방 삭제 */
if (!function_exists('공커등록_일방정리')) {
  function 공커등록_일방정리($couple) {
    $닉목록 = 공커문자열_닉목록($couple);
    if ($닉목록 === []) {
      return 0;
    }
    $삭제수 = 0;
    $rs = db_query("SELECT idx, nick FROM tb_progress WHERE status = '일방'");
    if (!$rs) {
      return 0;
    }
    while ($row = db_fetch($rs)) {
      $참가자 = 진행문자열_닉목록($row['nick'] ?? '');
      $겹침 = false;
      foreach ($닉목록 as $nn) {
        if (in_array($nn, $참가자, true)) {
          $겹침 = true;
          break;
        }
      }
      if (!$겹침) {
        continue;
      }
      foreach ($참가자 as $nn) {
        $nn_esc = addslashes($nn);
        db_query("UPDATE tb_member SET oneroom = 0 WHERE name = '{$nn_esc}'");
      }
      db_query("DELETE FROM tb_progress WHERE idx = " . (int)$row['idx']);
      $삭제수++;
    }
    return $삭제수;
  }
}

/** 공커 시작일(sdate) 기준 일수 — .공커 표시와 동일 (시작일 포함) */
if (!function_exists('공커_일수')) {
  function 공커_일수($sdate) {
    $sdate = trim((string)$sdate);
    if ($sdate === '') {
      return 0;
    }
    try {
      $days = abs((int)(new DateTime())->diff(new DateTime($sdate))->format('%r%a'));
    } catch (Exception $e) {
      return 0;
    }
    return $days + 1;
  }
}

/** 공커 1일당 연금 단가(냥) */
if (!function_exists('공커연금_단가')) {
  function 공커연금_단가() {
    return 500;
  }
}

if (!function_exists('공커연금_컬럼_보장')) {
  function 공커연금_컬럼_보장() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $rs = @db_query("SHOW COLUMNS FROM tb_member LIKE 'gongkeo_pension'");
    $row = $rs ? db_fetch($rs) : null;
    if (empty($row['Field'])) {
      @db_query("ALTER TABLE tb_member ADD COLUMN `gongkeo_pension` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '공커연금 누적(수령 대기)'");
    }
  }
}

if (!function_exists('공커연금_커플컬럼_보장')) {
  function 공커연금_커플컬럼_보장() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $rs = @db_query("SHOW COLUMNS FROM tb_couple LIKE 'pension_last_accrual'");
    $row = $rs ? db_fetch($rs) : null;
    if (empty($row['Field'])) {
      @db_query("ALTER TABLE tb_couple ADD COLUMN `pension_last_accrual` date DEFAULT NULL COMMENT '공커연금 마지막 누적일'");
    }
  }
}

/** 활성 공커(tb_couple status=0) 중 닉이 참가자인 행 */
if (!function_exists('공커_활성_조회')) {
  function 공커_활성_조회($닉) {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return null;
    }
    $닉_esc = addslashes($닉);
    $rs = db_query("SELECT * FROM tb_couple WHERE status = 0 AND couple LIKE '%{$닉_esc}%' ORDER BY sdate DESC, idx DESC");
    if (!$rs) {
      return null;
    }
    while ($row = db_fetch($rs)) {
      $참가자 = 공커문자열_닉목록($row['couple'] ?? '');
      if (in_array($닉, $참가자, true)) {
        return $row;
      }
    }
    return null;
  }
}

/**
 * 활성 공커 참가자에게 일당 공커연금 누적 (cron·수령 직전)
 */
if (!function_exists('공커연금_누적갱신')) {
  function 공커연금_누적갱신() {
    공커연금_컬럼_보장();
    공커연금_커플컬럼_보장();

    $today = date('Y-m-d');
    $단가 = 공커연금_단가();
    $rs = db_query("SELECT idx, couple, sdate, pension_last_accrual FROM tb_couple WHERE status = 0");
    if (!$rs) {
      return 0;
    }

    $처리커플 = 0;
    while ($row = db_fetch($rs)) {
      $last = trim((string)($row['pension_last_accrual'] ?? ''));
      if ($last === '') {
        $sdate = substr((string)($row['sdate'] ?? ''), 0, 10);
        if ($sdate === '') {
          continue;
        }
        $last = date('Y-m-d', strtotime($sdate . ' -1 day'));
      }
      if ($last >= $today) {
        continue;
      }

      $경과일 = (int)floor((strtotime($today) - strtotime($last)) / 86400);
      if ($경과일 <= 0) {
        continue;
      }

      $추가 = $경과일 * $단가;
      foreach (공커문자열_닉목록($row['couple'] ?? '') as $nn) {
        $nn_esc = addslashes($nn);
        db_query("UPDATE tb_member SET gongkeo_pension = IFNULL(gongkeo_pension, 0) + {$추가} WHERE name = '{$nn_esc}' LIMIT 1");
      }

      $idx = (int)($row['idx'] ?? 0);
      if ($idx > 0) {
        db_query("UPDATE tb_couple SET pension_last_accrual = '{$today}' WHERE idx = {$idx}");
        $처리커플++;
      }
    }
    return $처리커플;
  }
}

/**
 * .공커연금 — 본방 공커 참가자 개인 수령
 */
if (!function_exists('공커연금_명령_처리')) {
  function 공커연금_명령_처리($status, $두자리닉넴) {
    if (trim((string)$status) !== '.공커연금') {
      return;
    }

    if ($두자리닉넴 === '') {
      echo 전송('❌ 닉네임을 확인해주세요.');
      exit;
    }

    $커플 = 공커_활성_조회($두자리닉넴);
    if (empty($커플['idx'])) {
      echo 전송('❌ 공커 중이 아니에요.\n공커연금은 공커 참여 중에만 수령할 수 있어요.');
      exit;
    }

    공커연금_누적갱신();

    $닉_esc = addslashes($두자리닉넴);
    $회원 = db_select("SELECT IFNULL(gongkeo_pension, 0) AS gongkeo_pension FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $금액 = (int)($회원['gongkeo_pension'] ?? 0);
    $일수 = 공커_일수($커플['sdate'] ?? '');
    $커플표시 = trim((string)($커플['couple'] ?? ''));
    $단가 = 공커연금_단가();

    if ($금액 <= 0) {
      echo 전송("💰 공커연금\n\n{$두자리닉넴} 님\n{$커플표시} ({$일수}일차)\n\n수령할 공커연금이 없어요.\n(1일당 " . number_format($단가) . "냥 누적)");
      exit;
    }

    db_query("UPDATE tb_member SET newpoint = IFNULL(newpoint, 0) + {$금액}, gongkeo_pension = 0 WHERE name = '{$닉_esc}' LIMIT 1");
    if (function_exists('지급로그')) {
      지급로그('공커연금', $두자리닉넴, $두자리닉넴, 0, $금액);
    }

    echo 전송("💰 공커연금 수령!\n\n{$두자리닉넴} 님\n{$커플표시} ({$일수}일차)\n\n+" . number_format($금액) . "냥 본방냥 지급");
    exit;
  }
}

if (!function_exists('공커연금_커플_표시문구')) {
  /** .공커 목록용 — 커플 참가자별 수령 대기 공커연금 */
  function 공커연금_커플_표시문구($couple) {
    공커연금_컬럼_보장();
    $닉목록 = 공커문자열_닉목록($couple);
    if ($닉목록 === []) {
      return '공커연금 0냥';
    }
    $parts = [];
    foreach ($닉목록 as $nn) {
      $nn_esc = addslashes($nn);
      $row = db_select("SELECT IFNULL(gongkeo_pension, 0) AS amt FROM tb_member WHERE name = '{$nn_esc}' LIMIT 1");
      $parts[] = $nn . ' ' . number_format((int)($row['amt'] ?? 0));
    }
    return '공커연금 ' . implode(' / ', $parts) . '냥';
  }
}

if (!function_exists('공커대실권_표시')) {
  /** .공커 목록 — tb_couple.amount (공커대실권 가격) */
  function 공커대실권_표시($amount) {
    if (function_exists('냥축약표시')) {
      return 냥축약표시($amount);
    }
    return number_format((int)$amount) . '냥';
  }
}

/**
 * 지목/강일 등 진행 등록 시 tb_item.buy 에 현재 sell 값을 누적 (sell 컬럼은 변경 없음)
 */
function 아이템_buy에_sell_누적($itemSname) {
  $esc = addslashes(trim((string)$itemSname));
  if ($esc === '' || (function_exists('아이템_percent_시세여부_sname') && 아이템_percent_시세여부_sname($esc))) {
    return;
  }
  db_query("UPDATE tb_item SET buy = IFNULL(buy, 0) + IFNULL(sell, 0) WHERE sname = '{$esc}'");
}

/**
 * .지목 명령 — (보내는닉) (대상1) (대상2) 또는 (대상1) (대상2)만(입력자=보내는닉)
 * 예) .지목 소이 하윤 돌풍
 * 처리 시 exit.
 */
function 지목_명령_처리($status, $두자리닉넴) {
  if (!preg_match('/^\.지목(?:[\s\p{Zs}]+)(.+)$/u', trim($status), $지목m)) {
    return;
  }

  $지목토큰 = preg_split('/[\s\p{Zs}]+/u', trim($지목m[1]), -1, PREG_SPLIT_NO_EMPTY);
  if (count($지목토큰) === 3) {
    $지목소유자 = 지목_명령_토큰닉($지목토큰[0]);
    $피지목자1 = 지목_명령_토큰닉($지목토큰[1]);
    $피지목자2 = 지목_명령_토큰닉($지목토큰[2]);
  } elseif (count($지목토큰) === 2) {
    $지목소유자 = $두자리닉넴;
    $피지목자1 = 지목_명령_토큰닉($지목토큰[0]);
    $피지목자2 = 지목_명령_토큰닉($지목토큰[1]);
  } else {
    echo 전송("❌ 사용법: .지목 (지목보내는닉) (대상1) (대상2)\n예) .지목 소이 하윤 돌풍");
    exit;
  }

  if ($지목소유자 === '' || $피지목자1 === '' || $피지목자2 === '') {
    echo 전송("❌ 닉네임을 확인해주세요.\n예) .지목 소이 하윤 돌풍");
    exit;
  }

  $강일제한 = 지목_강일중_제한_검사([$피지목자1, $피지목자2]);
  if ($강일제한 !== null) {
    echo 전송($강일제한);
    exit;
  }

  $지목소유자_esc = addslashes($지목소유자);
  $피지목자1_esc = addslashes($피지목자1);
  $피지목자2_esc = addslashes($피지목자2);

  $like1 = $피지목자1_esc . '%' . $피지목자2_esc;
  $like2 = $피지목자2_esc . '%' . $피지목자1_esc;
  $기존목록 = db_query("SELECT idx FROM tb_progress WHERE status = '지목' AND (nick LIKE '%{$like1}%' OR nick LIKE '%{$like2}%')");
  $삭제했음 = false;
  while ($기존 = db_fetch($기존목록)) {
    db_query("DELETE FROM tb_progress WHERE idx = {$기존['idx']}");
    $삭제했음 = true;
  }
  if ($삭제했음) {
    echo 전송("기존 지목 삭제완료.");
    exit;
  }

  $이모티콘랜덤 = array('🍭', '🪄', '🎈', '🚀');
  $이모티 = $이모티콘랜덤[array_rand($이모티콘랜덤)];
  $진행 = $피지목자1 . $이모티 . ' ' . $피지목자2;
  $진행_esc = addslashes($진행);
  $끝나는날 = date('Y-m-d H:i', strtotime('+6 hours'));

  $아이템 = 아이템보유유무($지목소유자, '지목');
  if (empty($아이템['idx'])) {
    echo 전송("❌ {$지목소유자} 님은 지목 아이템이 없어요.");
    exit;
  }
  db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx = {$아이템['idx']}");
  아이템사용_시세하락('지목', 1);

  $result = db_query("INSERT INTO tb_progress SET status = '지목', nick = '{$진행_esc}', enddate = '{$끝나는날}', regdate = NOW()");
  if ($result) {
    미션완료_기록_if_new($지목소유자, '일방', '지목');
    foreach (진행문자열_닉목록($진행) as $nn) {
      미션완료_기록_if_new($nn, '일방', '지목');
    }
    $log_msg = addslashes("{$지목소유자} 지목아이템 사용! {$진행} ({$끝나는날} 까지) 지목 시작! 큐!!!");
    $caster_esc = addslashes($지목소유자);
    db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$log_msg}', leverage = 0, item = '{$caster_esc}', regdate = NOW()");

    echo 전송("지목\n{$지목소유자} → {$진행} 등록완료!\n{$끝나는날} 까지");
    exit;
  }
  echo 전송('❌ 지목 등록 처리 중 오류가 났어요.');
  exit;
}

/** 강일 취소/만료 후 재강일 제한 — tb_gangil_tile (edate = 해제 시각) */
if (!function_exists('강일취소_타일_등록')) {
  function 강일취소_타일_등록(array $닉목록, $시간 = 12) {
    $시간 = max(1, (int)$시간);
    $edate = date('Y-m-d H:i:s', strtotime("+{$시간} hours"));
    foreach ($닉목록 as $이름) {
      $이름 = trim((string)$이름);
      if ($이름 === '') {
        continue;
      }
      $text_esc = addslashes($이름);
      db_query("INSERT INTO tb_gangil_tile SET text = '{$text_esc}', edate = '{$edate}', regdate = NOW()");
    }
  }
}

if (!function_exists('강일취소_타일_잠금조회')) {
  /**
   * @return array|null ['text'=>닉, 'edate'=>datetime]
   */
  function 강일취소_타일_잠금조회($닉) {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return null;
    }
    $닉_esc = addslashes($닉);
    $now = date('Y-m-d H:i:s');
    $row = db_select("
      SELECT text, edate
      FROM tb_gangil_tile
      WHERE text = '{$닉_esc}'
        AND edate > '{$now}'
      ORDER BY edate DESC
      LIMIT 1
    ");
    return (is_array($row) && !empty($row['edate'])) ? $row : null;
  }
}

if (!function_exists('강일취소_남은시간_문구')) {
  function 강일취소_남은시간_문구($edate) {
    $ts = strtotime((string)$edate);
    if ($ts === false) {
      return '';
    }
    $sec = max(0, $ts - time());
    $h = (int)floor($sec / 3600);
    $m = (int)floor(($sec % 3600) / 60);
    if ($h > 0) {
      return "약 {$h}시간 {$m}분";
    }
    if ($m > 0) {
      return "약 {$m}분";
    }
    return '1분 미만';
  }
}

if (!function_exists('강일_취소제한_검사')) {
  /**
   * @param string[] $닉목록 강일 당사자(보통 2명)
   * @return string|null 차단 시 안내 문구
   */
  function 강일_취소제한_검사(array $닉목록) {
    $lines = [];
    $seen = [];
    foreach ($닉목록 as $nn) {
      $nn = trim((string)$nn);
      if ($nn === '' || isset($seen[$nn])) {
        continue;
      }
      $seen[$nn] = true;
      $잠금 = 강일취소_타일_잠금조회($nn);
      if ($잠금 === null) {
        continue;
      }
      $남은 = 강일취소_남은시간_문구($잠금['edate']);
      $해제 = date('m-d H:i', strtotime($잠금['edate']));
      $lines[] = "• [ {$nn} ] 강일 취소 후 12시간 제한\n  남은 {$남은} (해제 {$해제})";
    }
    if ($lines === []) {
      return null;
    }
    return "❌ 강일 진행 불가 (취소 후 12시간 대기)\n\n" . implode("\n", $lines);
  }
}

if (!function_exists('지목강일_기록_텍스트')) {
  function 지목강일_기록_텍스트($닉) {
    return '지목강일 ' . trim((string)$닉);
  }
}

if (!function_exists('지목강일_오늘보냄_여부')) {
  function 지목강일_오늘보냄_여부($닉) {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return false;
    }
    $text_esc = addslashes(지목강일_기록_텍스트($닉));
    $오늘 = date('Y-m-d');
    $row = db_select("SELECT idx FROM tb_gangil_tile WHERE text = '{$text_esc}' AND DATE(regdate) = '{$오늘}' LIMIT 1");
    return !empty($row['idx']);
  }
}

if (!function_exists('지목강일_일일제한_검사')) {
  /**
   * @return string|null 차단 시 안내 문구
   */
  function 지목강일_일일제한_검사($닉) {
    if (!지목강일_오늘보냄_여부($닉)) {
      return null;
    }
    return "❌ [ {$닉} ] 오늘 이미 `.지목강일`을 보냈어요.\n하루 1회만 가능해요. (`.기록` 확인)";
  }
}

if (!function_exists('양도_오늘완료수')) {
  function 양도_오늘완료수($닉) {
    if (function_exists('yangdo_오늘완료수')) {
      return yangdo_오늘완료수((string)$닉);
    }
    $닉_esc = addslashes(trim((string)$닉));
    if ($닉_esc === '') {
      return 0;
    }
    $row = db_select("
      SELECT COUNT(*) AS cnt
      FROM tb_point_log
      WHERE status = '양도'
        AND nick = '{$닉_esc}'
        AND regdate >= CURDATE()
        AND regdate < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    ");
    return (int)($row['cnt'] ?? 0);
  }
}

if (!function_exists('양도_수수료')) {
  /** 보낸 금액의 1% (반올림) */
  function 양도_수수료(int $양도금액): int {
    if ($양도금액 < 1) {
      return 0;
    }
    return (int)round($양도금액 * 0.01);
  }
}

if (!function_exists('양도_수령액')) {
  /** 상대가 실제 받는 금액 = 보낸 금액 − 1% 수수료 */
  function 양도_수령액(int $보내는양): int {
    return max(0, $보내는양 - 양도_수수료($보내는양));
  }
}

if (!function_exists('양도_지호_enddate_ts')) {
  /** tb_item_use 지호 enddate → unix timestamp (없으면 null) */
  function 양도_지호_enddate_ts($닉): ?int {
    $닉_esc = addslashes(trim((string)$닉));
    if ($닉_esc === '') {
      return null;
    }
    $row = db_select("SELECT enddate FROM tb_item_use WHERE nickname = '{$닉_esc}' AND item = '지호' AND enddate > NOW() ORDER BY enddate DESC LIMIT 1");
    if (empty($row['enddate'])) {
      return null;
    }
    $ts = strtotime((string)$row['enddate']);
    return ($ts === false) ? null : $ts;
  }
}

if (!function_exists('양도_지호적용중')) {
  function 양도_지호적용중($닉): bool {
    $ts = 양도_지호_enddate_ts($닉);
    return $ts !== null && $ts > time();
  }
}

if (!function_exists('양도_지호_남은시간_초')) {
  function 양도_지호_남은시간_초($닉): int {
    $ts = 양도_지호_enddate_ts($닉);
    if ($ts === null || $ts <= time()) {
      return 0;
    }
    return $ts - time();
  }
}

if (!function_exists('양도_지호_남은시간_시간')) {
  /** 지호 enddate 기준 남은 시간(내림, 1시간 단위 추가 양도 가능 횟수) */
  function 양도_지호_남은시간_시간($닉): int {
    return (int)floor(양도_지호_남은시간_초($닉) / 3600);
  }
}

if (!function_exists('양도_지호추가양도_해당')) {
  /** 오늘 2회째 이후 양도 — 실행 직전·로그 전 (지호 1시간 차감 대상) */
  function 양도_지호추가양도_해당($닉): bool {
    return 양도_오늘완료수($닉) >= 1;
  }
}

if (!function_exists('양도_지호_소비')) {
  /** 추가 양도 시 지호 종료시각 1시간 차감. 남은 시간 없으면 행 삭제. */
  function 양도_지호_소비($닉): string {
    $닉_esc = addslashes(trim((string)$닉));
    if ($닉_esc === '') {
      return '';
    }
    $row = db_select("SELECT idx, enddate FROM tb_item_use WHERE nickname = '{$닉_esc}' AND item = '지호' AND enddate > NOW() ORDER BY enddate DESC LIMIT 1");
    if (empty($row['idx'])) {
      return '';
    }

    $종료_ts = strtotime((string)$row['enddate']);
    if ($종료_ts === false || $종료_ts <= time()) {
      db_query("DELETE FROM tb_item_use WHERE idx = " . (int)$row['idx']);
      return '(지호 1시간 차감 · 버프 종료)';
    }

    $새종료_ts = $종료_ts - 3600;
    if ($새종료_ts <= time()) {
      db_query("DELETE FROM tb_item_use WHERE idx = " . (int)$row['idx']);
      return '(지호 1시간 차감 · 버프 종료)';
    }

    $새종료 = date('Y-m-d H:i', $새종료_ts);
    $새종료_esc = addslashes($새종료);
    db_query("UPDATE tb_item_use SET enddate = '{$새종료_esc}' WHERE idx = " . (int)$row['idx']);
    return '(지호 1시간 차감 · ' . date('m-d H:i', $새종료_ts) . ' 까지)';
  }
}

if (!function_exists('양도_일일제한_검사')) {
  /**
   * 본방냥·게임냥 합산 — 하루 1회 무료 + 지호 1시간당 추가 1회
   * @return string|null 차단 시 안내 문구
   */
  function 양도_일일제한_검사($닉) {
    $완료수 = 양도_오늘완료수($닉);
    if ($완료수 < 1) {
      return null;
    }

    $남은시간 = 양도_지호_남은시간_시간($닉);
    if ($남은시간 >= 1 && 양도_지호적용중($닉)) {
      return null;
    }

    return "❌ [ {$닉} ] 오늘 `.양도` {$완료수}회 사용했어요.\n본방·게임방 합산 하루 1회 무료 — 추가 양도는 지호 1시간 이상 필요 (현재 남은 지호: {$남은시간}시간)";
  }
}

if (!function_exists('지목강일_기록_등록')) {
  function 지목강일_기록_등록($닉) {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return;
    }
    $text_esc = addslashes(지목강일_기록_텍스트($닉));
    $edate = date('Y-m-d 23:59:59');
    db_query("INSERT INTO tb_gangil_tile SET text = '{$text_esc}', edate = '{$edate}', regdate = NOW()");
  }
}

if (!function_exists('강일_진행중_건수')) {
  function 강일_진행중_건수() {
    $기준 = date('Y-m-d H:i:s');
    $row = db_select("SELECT COUNT(*) AS cnt FROM tb_progress WHERE status = '강일' AND enddate > '{$기준}'");
    return (int)($row['cnt'] ?? 0);
  }
}

if (!function_exists('강일_진행중_제한_검사')) {
  /**
   * @return string|null 차단 시 안내 문구
   */
  function 강일_진행중_제한_검사($최대건수 = 5) {
    $건수 = 강일_진행중_건수();
    if ($건수 >= (int)$최대건수) {
      return "❌ 진행 중인 강일이 {$최대건수}건이에요.\n`.강일` · `.지목강일` 등록은 잠시 후에 해주세요.";
    }
    return null;
  }
}

/**
 * .강일 / .지목강일 명령
 * - .강일 (대상): 입력자 + 대상 강일, 강일 1개 사용
 * - .지목강일 (대상1) (대상2): 지정 2명 강일, 강일 10개 사용
 * tb_progress status=강일, 12시간. 처리 시 exit.
 */
function 강일_명령_처리($status, $두자리닉넴) {
  $정리상태 = trim((string)$status);
  $명령유형 = '';
  $강일소유자 = $두자리닉넴;
  $대상1 = '';
  $대상2 = '';
  $필요개수 = 0;

  if (preg_match('/^\.강일(?:[\s\p{Zs}]+)(.+)$/u', $정리상태, $강일m)) {
    $강일토큰 = preg_split('/[\s\p{Zs}]+/u', trim($강일m[1]), -1, PREG_SPLIT_NO_EMPTY);
    if (count($강일토큰) !== 1) {
      echo 전송("❌ 사용법: .강일 (대상닉)\n예) .강일 진우");
      exit;
    }
    $명령유형 = '.강일';
    $대상1 = $두자리닉넴;
    $대상2 = 지목_명령_토큰닉($강일토큰[0]);
    $필요개수 = 1;
  } elseif (preg_match('/^\.지목강일(?:[\s\p{Zs}]+)(.+)$/u', $정리상태, $지목강일m)) {
    $강일토큰 = preg_split('/[\s\p{Zs}]+/u', trim($지목강일m[1]), -1, PREG_SPLIT_NO_EMPTY);
    if (count($강일토큰) !== 2) {
      echo 전송("❌ 사용법: .지목강일 (대상1) (대상2)\n예) .지목강일 진우 우지");
      exit;
    }
    $명령유형 = '.지목강일';
    $대상1 = 지목_명령_토큰닉($강일토큰[0]);
    $대상2 = 지목_명령_토큰닉($강일토큰[1]);
    $필요개수 = 10;
  } else {
    return;
  }

  if ($강일소유자 === '' || $대상1 === '' || $대상2 === '') {
    echo 전송("❌ 닉네임을 확인해주세요.");
    exit;
  }
  if ($대상1 === $대상2) {
    echo 전송("❌ 강일 대상 2명이 서로 달라야 해요.");
    exit;
  }

  if ($명령유형 === '.지목강일') {
    $지목강일제한 = 지목강일_일일제한_검사($강일소유자);
    if ($지목강일제한 !== null) {
      echo 전송($지목강일제한);
      exit;
    }
  }

  $제한문구 = 강일_취소제한_검사([$대상1, $대상2]);
  if ($제한문구 !== null) {
    echo 전송($제한문구);
    exit;
  }

  $대상1_esc = addslashes($대상1);
  $대상2_esc = addslashes($대상2);
  $like1 = $대상1_esc . '%' . $대상2_esc;
  $like2 = $대상2_esc . '%' . $대상1_esc;
  $기존목록 = db_query("SELECT idx, nick, regdate, enddate FROM tb_progress WHERE status = '강일' AND (nick LIKE '%{$like1}%' OR nick LIKE '%{$like2}%')");
  $삭제했음 = false;
  while ($기존 = db_fetch($기존목록)) {
    강일기록_등록($기존, 'replace');
    db_query("DELETE FROM tb_progress WHERE idx = {$기존['idx']}");
    $삭제했음 = true;
  }
  if ($삭제했음) {
    echo 전송("기존 강일 삭제완료.");
    exit;
  }

  $진행제한 = 강일_진행중_제한_검사(5);
  if ($진행제한 !== null) {
    echo 전송($진행제한);
    exit;
  }

  $강일소유자_esc = addslashes($강일소유자);
  $보유행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE nick = '{$강일소유자_esc}' AND itemname = '강일' AND status = 0");
  $보유개수 = (int)($보유행['cnt'] ?? 0);
  if ($보유개수 < $필요개수) {
    echo 전송("❌ {$강일소유자} 님은 강일 아이템이 부족해요. ({$보유개수}/{$필요개수})");
    exit;
  }
  db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE nick = '{$강일소유자_esc}' AND itemname = '강일' AND status = 0 ORDER BY idx ASC LIMIT {$필요개수}");
  아이템사용_시세하락('강일', $필요개수);

  $이모티콘랜덤 = array('❤️', '💕', '🤝', '✨');
  $이모티 = $이모티콘랜덤[array_rand($이모티콘랜덤)];
  $진행 = $대상1 . $이모티 . ' ' . $대상2;
  $진행_esc = addslashes($진행);
  $끝나는날 = date('Y-m-d H:i', strtotime('+12 hours'));

  $result = db_query("INSERT INTO tb_progress SET status = '강일', nick = '{$진행_esc}', enddate = '{$끝나는날}', regdate = NOW()");
  if ($result) {
    $강일기록됨 = false;
    foreach (진행문자열_닉목록($진행) as $nn) {
      미션완료_기록_if_new($nn, '일방', '강일');
      $강일기록됨 = true;
    }
    if (!$강일기록됨) {
      미션완료_기록_if_new($강일소유자, '일방', '강일');
    }
    $log_msg = addslashes("{$강일소유자} {$명령유형} 강일아이템 {$필요개수}개 사용! {$진행} ({$끝나는날} 까지) 강제일방 시작!");
    db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$log_msg}', leverage = 0, item = '{$강일소유자_esc}', regdate = NOW()");

    if ($명령유형 === '.지목강일') {
      지목강일_기록_등록($강일소유자);
    }

    echo 전송("강일\n{$강일소유자} → {$진행} 등록완료!\n{$끝나는날} 까지");
    exit;
  }
  echo 전송('❌ 강일 등록 처리 중 오류가 났어요.');
  exit;
}

/** tb_member.gangil_times 컬럼 (강일 만료 완료 누적) */
if (!function_exists('강일횟수_컬럼_보장')) {
  function 강일횟수_컬럼_보장() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $rs = @db_query("SHOW COLUMNS FROM tb_member LIKE 'gangil_times'");
    $row = $rs ? db_fetch($rs) : null;
    if (empty($row['Field'])) {
      @db_query("ALTER TABLE tb_member ADD COLUMN `gangil_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '강일 만료 완료 누적 횟수'");
    }
  }
}

/** tb_progress.prolongation 컬럼 (강일 연장 1회 여부) */
if (!function_exists('강일연장_컬럼_보장')) {
  function 강일연장_컬럼_보장() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $rs = @db_query("SHOW COLUMNS FROM tb_progress LIKE 'prolongation'");
    $row = $rs ? db_fetch($rs) : null;
    if (empty($row['Field'])) {
      @db_query("ALTER TABLE tb_progress ADD COLUMN `prolongation` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '강일 연장 여부(1=연장완료)'");
    }
  }
}

/** tb_gangil_log — 강일 삭제·만료 시 참가자 이력 */
if (!function_exists('강일기록_테이블_보장')) {
  function 강일기록_테이블_보장() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    @db_query("CREATE TABLE IF NOT EXISTS `tb_gangil_log` (
      `idx` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `nick1` varchar(30) NOT NULL DEFAULT '',
      `nick2` varchar(30) NOT NULL DEFAULT '',
      `nick_display` varchar(100) DEFAULT NULL,
      `progress_idx` int(11) unsigned DEFAULT NULL,
      `started_at` datetime DEFAULT NULL,
      `ended_at` datetime DEFAULT NULL,
      `closed_at` datetime NOT NULL,
      `close_type` varchar(20) NOT NULL DEFAULT 'expire',
      `regdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`idx`),
      KEY `idx_gangil_log_nick1` (`nick1`),
      KEY `idx_gangil_log_nick2` (`nick2`),
      KEY `idx_gangil_log_closed` (`closed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
  }
}

/**
 * 강일 삭제·만료 시 tb_gangil_log 등록
 *
 * @param array $progress행 idx, nick, regdate, enddate
 * @param string $close_type expire|cancel|replace|terminate
 */
if (!function_exists('강일기록_등록')) {
  function 강일기록_등록(array $progress행, $close_type = 'expire', $closed_at = null) {
    강일기록_테이블_보장();

    $nick_display = trim((string)($progress행['nick'] ?? ''));
    if ($nick_display === '') {
      return false;
    }

    $참가자 = 진행문자열_닉목록($nick_display);
    $nick1 = (string)($참가자[0] ?? '');
    $nick2 = (string)($참가자[1] ?? '');
    if ($nick1 === '' && $nick2 === '') {
      return false;
    }

    $close_type = in_array($close_type, ['expire', 'cancel', 'replace', 'terminate'], true) ? $close_type : 'expire';
    $closed_at = trim((string)($closed_at ?? date('Y-m-d H:i:s')));
    $progress_idx = (int)($progress행['idx'] ?? 0);
    $started_at = trim((string)($progress행['regdate'] ?? ''));
    $ended_at = trim((string)($progress행['enddate'] ?? ''));

    $nick1_esc = addslashes($nick1);
    $nick2_esc = addslashes($nick2);
    $nick_display_esc = addslashes($nick_display);
    $close_type_esc = addslashes($close_type);
    $closed_at_esc = addslashes($closed_at);
    $started_sql = $started_at !== '' ? "'" . addslashes($started_at) . "'" : 'NULL';
    $ended_sql = $ended_at !== '' ? "'" . addslashes($ended_at) . "'" : 'NULL';
    $progress_sql = $progress_idx > 0 ? (string)$progress_idx : 'NULL';

    return (bool)@db_query("INSERT INTO tb_gangil_log SET
      nick1 = '{$nick1_esc}',
      nick2 = '{$nick2_esc}',
      nick_display = '{$nick_display_esc}',
      progress_idx = {$progress_sql},
      started_at = {$started_sql},
      ended_at = {$ended_sql},
      closed_at = '{$closed_at_esc}',
      close_type = '{$close_type_esc}',
      regdate = NOW()");
  }
}

/**
 * 만료 예정 강일(tb_progress) 참가자에게 gangil_times +1
 * nick 예: 유하✨ 디보 → 유하, 디보 각 1회
 * 참가자 전원 tb_gangil_tile 등록 — 기본 12시간, prolongation=1 이면 24시간
 *
 * @return int 누적 처리한 회원 수(참가자 기준)
 */
if (!function_exists('강일만료_횟수_누적')) {
  function 강일만료_횟수_누적($기준시각) {
    강일횟수_컬럼_보장();
    강일연장_컬럼_보장();
    $기준_esc = addslashes(trim((string)$기준시각));
    if ($기준_esc === '') {
      return 0;
    }
    $rs = @db_query("SELECT idx, nick, regdate, enddate, IFNULL(prolongation, 0) AS prolongation FROM tb_progress WHERE status = '강일' AND enddate <= '{$기준_esc}'");
    if (!$rs) {
      return 0;
    }
    $누적 = 0;
    while ($row = db_fetch($rs)) {
      강일기록_등록($row, 'expire', $기준_esc);
      $이름들 = 강일_진행_참가자목록($row['nick'] ?? '');
      $타일시간 = ((int)($row['prolongation'] ?? 0) >= 1) ? 24 : 12;
      강일취소_타일_등록($이름들, $타일시간);
      foreach ($이름들 as $nn) {
        $nn_esc = addslashes($nn);
        db_query("UPDATE tb_member SET gangil_times = IFNULL(gangil_times, 0) + 1 WHERE name = '{$nn_esc}' LIMIT 1");
        $누적++;
      }
    }
    return $누적;
  }
}

/** tb_member.jimok_times 컬럼 (지목 만료 완료 누적) */
if (!function_exists('지목횟수_컬럼_보장')) {
  function 지목횟수_컬럼_보장() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $rs = @db_query("SHOW COLUMNS FROM tb_member LIKE 'jimok_times'");
    $row = $rs ? db_fetch($rs) : null;
    if (empty($row['Field'])) {
      @db_query("ALTER TABLE tb_member ADD COLUMN `jimok_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '지목 만료 완료 누적 횟수'");
    }
  }
}

/**
 * 만료 예정 지목(tb_progress) 참가자에게 jimok_times +1
 *
 * @return int 누적 처리한 회원 수(참가자 기준)
 */
if (!function_exists('지목만료_횟수_누적')) {
  function 지목만료_횟수_누적($기준시각) {
    지목횟수_컬럼_보장();
    $기준_esc = addslashes(trim((string)$기준시각));
    if ($기준_esc === '') {
      return 0;
    }
    $rs = @db_query("SELECT nick FROM tb_progress WHERE status = '지목' AND enddate <= '{$기준_esc}'");
    if (!$rs) {
      return 0;
    }
    $누적 = 0;
    while ($row = db_fetch($rs)) {
      foreach (진행문자열_닉목록($row['nick'] ?? '') as $nn) {
        $nn_esc = addslashes($nn);
        db_query("UPDATE tb_member SET jimok_times = IFNULL(jimok_times, 0) + 1 WHERE name = '{$nn_esc}' LIMIT 1");
        $누적++;
      }
    }
    return $누적;
  }
}

/** 강일 1회당 연금 단가(냥) */
if (!function_exists('강일연금_단가')) {
  function 강일연금_단가() {
    return 1000;
  }
}

/**
 * .강일연금 — 강일 1회 이상 완료한 전원 연금 목록 (본방)
 */
if (!function_exists('강일연금_명령_처리')) {
  function 강일연금_명령_처리($status, $두자리닉넴) {
    if (trim((string)$status) !== '.강일연금') {
      return;
    }

    강일횟수_컬럼_보장();

    $단가 = 강일연금_단가();
    $rs = db_query("
      SELECT name, IFNULL(gangil_times, 0) AS gangil_times
      FROM tb_member
      WHERE status = 0 AND IFNULL(gangil_times, 0) >= 1
      ORDER BY gangil_times DESC, name ASC
    ");

    if (!$rs || mysqli_num_rows($rs) === 0) {
      echo 전송("💰 강일연금\n\n강일 1회 이상 완료한 친구가 없어요.\n(1회당 " . number_format($단가) . "냥)");
      exit;
    }

    $msg = "💰 강일연금 (1회당 " . number_format($단가) . "냥)\n\n";
    $순위 = 1;
    while ($row = db_fetch($rs)) {
      $횟수 = (int)($row['gangil_times'] ?? 0);
      $연금 = $횟수 * $단가;
      $msg .= "{$순위}. {$row['name']} {$횟수}회 · " . number_format($연금) . "냥\n";
      $순위++;
    }

    echo 전송($msg);
    exit;
  }
}

/** 지목 1회당 연금 단가(냥) */
if (!function_exists('지목연금_단가')) {
  function 지목연금_단가() {
    return 500;
  }
}

/**
 * .지목연금 — 지목 1회 이상 완료한 전원 연금 목록 (본방)
 */
if (!function_exists('지목연금_명령_처리')) {
  function 지목연금_명령_처리($status, $두자리닉넴) {
    if (trim((string)$status) !== '.지목연금') {
      return;
    }

    지목횟수_컬럼_보장();

    $단가 = 지목연금_단가();
    $rs = db_query("
      SELECT name, IFNULL(jimok_times, 0) AS jimok_times
      FROM tb_member
      WHERE status = 0 AND IFNULL(jimok_times, 0) >= 1
      ORDER BY jimok_times DESC, name ASC
    ");

    if (!$rs || mysqli_num_rows($rs) === 0) {
      echo 전송("💰 지목연금\n\n지목 1회 이상 완료한 친구가 없어요.\n(1회당 " . number_format($단가) . "냥)");
      exit;
    }

    $msg = "💰 지목연금 (1회당 " . number_format($단가) . "냥)\n\n";
    $순위 = 1;
    while ($row = db_fetch($rs)) {
      $횟수 = (int)($row['jimok_times'] ?? 0);
      $연금 = $횟수 * $단가;
      $msg .= "{$순위}. {$row['name']} {$횟수}회 · " . number_format($연금) . "냥\n";
      $순위++;
    }

    echo 전송($msg);
    exit;
  }
}

/** 본방 채팅 알림 큐 (info1.php status=0 폴링) */
if (!function_exists('본방알림_등록')) {
  function 본방알림_등록($msg, $item = 'system') {
    $msg = trim((string)$msg);
    if ($msg === '') {
      return false;
    }
    $msg_esc = addslashes($msg);
    $item_esc = addslashes((string)$item);
    return (bool)@db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$msg_esc}', leverage = 0, item = '{$item_esc}', regdate = NOW()");
  }
}

/** 동일 채팅 webhook이 동시에 여러 번 들어올 때 1건만 처리 (GET_LOCK) */
if (!function_exists('api_동시요청_락_시작')) {
  function api_동시요청_락_시작($nick, $msg) {
    global $conn;
    if (!($conn instanceof mysqli)) {
      return true;
    }
    $key = substr(md5(trim((string)$nick) . "\0" . trim((string)$msg)), 0, 32);
    $lock_esc = addslashes('chat_' . $key);
    $row = db_select("SELECT GET_LOCK('{$lock_esc}', 0) AS ok");
    if (empty($row['ok'])) {
      return false;
    }
    $GLOBALS['_api_동시요청_락'] = $lock_esc;
    return true;
  }
}

if (!function_exists('api_동시요청_락_해제')) {
  function api_동시요청_락_해제() {
    $lock_esc = $GLOBALS['_api_동시요청_락'] ?? '';
    if ($lock_esc === '') {
      return;
    }
    db_query("SELECT RELEASE_LOCK('{$lock_esc}')");
    unset($GLOBALS['_api_동시요청_락']);
  }
}

/** tb_lotto_info 큐에서 1건을 원자적으로 꺼냄 (동시 요청 중복 전송 방지) */
if (!function_exists('본방알림_큐_꺼내기')) {
  function 본방알림_큐_꺼내기() {
    global $conn;
    if (!($conn instanceof mysqli)) {
      return null;
    }
    if (!@mysqli_begin_transaction($conn)) {
      return null;
    }
    $row = db_select("SELECT idx, msg FROM tb_lotto_info WHERE status = 0 ORDER BY regdate ASC, idx ASC LIMIT 1 FOR UPDATE");
    if (empty($row['idx'])) {
      @mysqli_rollback($conn);
      return null;
    }
    $idx = (int)$row['idx'];
    db_query("UPDATE tb_lotto_info SET status = 1 WHERE idx = {$idx} AND status = 0 LIMIT 1");
    if (mysqli_affected_rows($conn) < 1) {
      @mysqli_rollback($conn);
      return null;
    }
    @mysqli_commit($conn);
    $msg = trim((string)($row['msg'] ?? ''));
    return $msg !== '' ? $msg : null;
  }
}

if (!function_exists('본방알림_큐_응답_시도')) {
  function 본방알림_큐_응답_시도() {
    $msg = 본방알림_큐_꺼내기();
    if ($msg === null) {
      return false;
    }
    echo 전송($msg);
    exit;
  }
}

/** 일방 연금 합계 (강일횟수×단가 + 지목횟수×단가) */
if (!function_exists('일방연금_계산')) {
  function 일방연금_계산($gangil_times, $jimok_times) {
    return (int)$gangil_times * 강일연금_단가() + (int)$jimok_times * 지목연금_단가();
  }
}

if (!function_exists('일방등록_연금_축하문구')) {
  function 일방등록_연금_축하문구($진행, array $지급내역) {
    $msg = "\n\n🎉 일대일방(일방) 성사! {$진행}";
    if ($지급내역 === []) {
      $msg .= "\n\n수령할 연금이 없어요.";
      return $msg;
    }
    $msg .= "\n\n💰 연금 지급";
    foreach ($지급내역 as $row) {
      $msg .= "\n· {$row['name']} " . number_format((int)$row['total']);
      $상세 = [];
      if ((int)($row['gangil_times'] ?? 0) > 0) {
        $상세[] = '강일 ' . (int)$row['gangil_times'] . '회';
      }
      if ((int)($row['jimok_times'] ?? 0) > 0) {
        $상세[] = '지목 ' . (int)$row['jimok_times'] . '회';
      }
      if ($상세 !== []) {
        $msg .= ' (' . implode(' · ', $상세) . ')';
      }
    }
    return $msg;
  }
}

/**
 * .등록 일방 성사 시 참가자 연금 지급 + 본방 알림
 *
 * @return string 등록 응답에 붙일 축하/연금 문구
 */
if (!function_exists('일방등록_연금_지급및알림')) {
  function 일방등록_연금_지급및알림($진행, $등록자닉 = '') {
    강일횟수_컬럼_보장();
    지목횟수_컬럼_보장();

    $진행 = trim((string)$진행);
    $등록자닉 = trim((string)$등록자닉);
    $닉목록 = 진행문자열_닉목록($진행);
    $지급내역 = [];
    $처리닉 = [];

    foreach ($닉목록 as $nn) {
      if ($nn === '' || isset($처리닉[$nn])) {
        continue;
      }
      $처리닉[$nn] = true;

      $nn_esc = addslashes($nn);
      $회원 = db_select("SELECT idx, IFNULL(gangil_times, 0) AS gangil_times, IFNULL(jimok_times, 0) AS jimok_times FROM tb_member WHERE name = '{$nn_esc}' LIMIT 1");
      if (empty($회원['idx'])) {
        continue;
      }

      $강일횟수 = (int)($회원['gangil_times'] ?? 0);
      $지목횟수 = (int)($회원['jimok_times'] ?? 0);
      $강일냥 = $강일횟수 * 강일연금_단가();
      $지목냥 = $지목횟수 * 지목연금_단가();
      $합계 = $강일냥 + $지목냥;
      if ($합계 <= 0) {
        continue;
      }

      db_query("UPDATE tb_member SET newpoint = IFNULL(newpoint, 0) + {$합계}, gangil_times = 0, jimok_times = 0 WHERE name = '{$nn_esc}' LIMIT 1");
      if (function_exists('지급로그')) {
        $로그주체 = $등록자닉 !== '' ? $등록자닉 : $nn;
        지급로그('일방연금', $로그주체, $nn, 0, $합계);
      }

      $지급내역[] = [
        'name' => $nn,
        'gangil_times' => $강일횟수,
        'jimok_times' => $지목횟수,
        'gangil_pay' => $강일냥,
        'jimok_pay' => $지목냥,
        'total' => $합계,
      ];
    }

    $축하문구 = 일방등록_연금_축하문구($진행, $지급내역);
    $알림item = '일방_' . preg_replace('/[^\p{L}\p{N}]+/u', '_', $진행);
    본방알림_등록(ltrim($축하문구), $알림item);

    return $축하문구;
  }
}

/**
 * 진행 중 강일(tb_progress) — 닉이 참가자인 1건 조회
 */
if (!function_exists('강일_진행중_닉조회')) {
  function 강일_진행중_닉조회($닉) {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return null;
    }
    강일연장_컬럼_보장();
    $닉_esc = addslashes($닉);
    $rs = db_query("SELECT idx, nick, regdate, enddate, IFNULL(prolongation, 0) AS prolongation FROM tb_progress WHERE status = '강일' AND nick LIKE '%{$닉_esc}%' ORDER BY regdate DESC");
    if (!$rs) {
      return null;
    }
    while ($row = db_fetch($rs)) {
      $참가자 = 진행문자열_닉목록($row['nick'] ?? '');
      if (in_array($닉, $참가자, true)) {
        return $row;
      }
    }
    return null;
  }
}

/** .지목 대상이 강일 진행 중이면 차단 */
if (!function_exists('지목_강일중_제한_검사')) {
  /**
   * @return string|null 차단 시 안내 문구
   */
  function 지목_강일중_제한_검사(array $닉목록) {
    $기준 = date('Y-m-d H:i:s');
    $강일중 = [];
    foreach ($닉목록 as $닉) {
      $닉 = trim((string)$닉);
      if ($닉 === '') {
        continue;
      }
      $row = 강일_진행중_닉조회($닉);
      if ($row === null) {
        continue;
      }
      $enddate = trim((string)($row['enddate'] ?? ''));
      if ($enddate !== '' && $enddate <= $기준) {
        continue;
      }
      $강일중[] = $닉;
    }
    if ($강일중 === []) {
      return null;
    }
    $목록 = implode(', ', array_map(function ($n) {
      return "[ {$n} ]";
    }, $강일중));
    return "❌ {$목록} 강일 진행 중이라 지목할 수 없어요.";
  }
}

if (!function_exists('강일_진행_참가자목록')) {
  function 강일_진행_참가자목록($진행) {
    $진행 = trim((string)$진행);
    $이름들 = 진행문자열_닉목록($진행);
    if ($이름들 !== []) {
      return $이름들;
    }
    $텍스트_이모티제거 = preg_replace('/\s*[^\p{L}\p{N}\s]\s*/u', ',', $진행);
    $텍스트_이모티제거 = preg_replace('/,+/', ',', $텍스트_이모티제거);
    return array_values(array_filter(array_map('trim', explode(',', trim($텍스트_이모티제거, ', ')))));
  }
}

if (!function_exists('강일_진행_취소실행')) {
  function 강일_진행_취소실행(array $대상, $접두 = '강일취소', $완료접미 = '취소완료!') {
    if (empty($대상['idx'])) {
      return false;
    }
    $진행 = trim((string)($대상['nick'] ?? ''));
    $이름들 = 강일_진행_참가자목록($진행);
    $타일시간 = ((int)($대상['prolongation'] ?? 0) >= 1) ? 24 : 12;
    강일취소_타일_등록($이름들, $타일시간);

    $idx = (int)$대상['idx'];
    강일기록_등록($대상, 'cancel');
    db_query("DELETE FROM tb_progress WHERE idx = {$idx}");
    echo 전송("{$접두}\n{$진행} {$완료접미}");
    exit;
  }
}

if (!function_exists('강일_진행_종료실행')) {
  /**
   * .강일종료 — .강일취소와 동일하게 참가자 전원 .기록(tb_gangil_tile) 등록, 강일연금 없음
   */
  function 강일_진행_종료실행(array $대상, $접두 = '강일종료', $완료접미 = '종료완료!') {
    if (empty($대상['idx'])) {
      return false;
    }
    $진행 = trim((string)($대상['nick'] ?? ''));
    $이름들 = 강일_진행_참가자목록($진행);
    강일취소_타일_등록($이름들);

    $idx = (int)$대상['idx'];
    강일기록_등록($대상, 'terminate');
    db_query("DELETE FROM tb_progress WHERE idx = {$idx}");
    echo 전송("{$접두}\n{$진행} {$완료접미}");
    exit;
  }
}

/**
 * .강일취소 — 진행 중 강일 당사자가 취소 시 tb_gangil_tile 등록 후 tb_progress 삭제
 */
function 강일취소_명령_처리($status, $두자리닉넴) {
  if (trim((string)$status) !== '.강일취소') {
    return;
  }

  if ($두자리닉넴 === '') {
    echo 전송('❌ 닉네임을 확인해주세요.');
    exit;
  }

  $대상 = 강일_진행중_닉조회($두자리닉넴);
  if ($대상 === null) {
    echo 전송('❌ 진행 중인 강일이 없어요.');
    exit;
  }

  강일_진행_취소실행($대상, '강일취소', '취소완료!');
}

/**
 * .강일종료 (당사자닉) — 해당 닉이 참가 중인 진행 강일 종료
 */
function 강일종료_명령_처리($status, $두자리닉넴) {
  if (!preg_match('/^\.강일종료(?:[\s\p{Zs}]+)(.+)$/u', trim((string)$status), $m)) {
    return;
  }

  $대상닉 = 지목_명령_토큰닉(trim($m[1]));
  if ($대상닉 === '') {
    echo 전송("❌ 사용법: .강일종료 (당사자닉)\n예) .강일종료 유하");
    exit;
  }

  $대상 = 강일_진행중_닉조회($대상닉);
  if ($대상 === null) {
    echo 전송("❌ [ {$대상닉} ] 진행 중인 강일이 없어요.");
    exit;
  }

  강일_진행_종료실행($대상, '강일종료', '종료완료!');
}

/**
 * .강일연장 (당사자닉) — 입력자 강일 1개 차감 + 당사자닉 강일 +12시간
 * .강일연장 (아이템닉) (당사자닉) — 아이템닉 강일 1개 차감 + 당사자닉 강일 +12시간 (관리방)
 * 동일 강일(tb_progress)은 prolongation=1 로 1회만 연장 가능
 */
function 강일연장_명령_처리($status, $두자리닉넴) {
  $정리상태 = trim((string)$status);
  if (!preg_match('/^\.강일연장(?:[\s\p{Zs}]+)(.+)$/u', $정리상태, $m)) {
    return;
  }
  강일연장_컬럼_보장();
  $토큰 = preg_split('/[\s\p{Zs}]+/u', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY);
  $토큰수 = count($토큰);
  if ($토큰수 !== 1 && $토큰수 !== 2) {
    echo 전송("❌ 사용법: .강일연장 (당사자닉)\n또는 .강일연장 (아이템닉) (당사자닉)\n예) .강일연장 진우\n예) .강일연장 대성 진우");
    exit;
  }
  if ($두자리닉넴 === '') {
    echo 전송('❌ 닉네임을 확인해주세요.');
    exit;
  }

  if ($토큰수 === 1) {
    $아이템닉 = $두자리닉넴;
    $당사자닉 = 지목_명령_토큰닉($토큰[0]);
  } else {
    $아이템닉 = 지목_명령_토큰닉($토큰[0]);
    $당사자닉 = 지목_명령_토큰닉($토큰[1]);
  }
  if ($아이템닉 === '' || $당사자닉 === '') {
    echo 전송('❌ 닉네임을 확인해주세요.');
    exit;
  }

  $아이템닉_esc = addslashes($아이템닉);
  $보유행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE nick = '{$아이템닉_esc}' AND itemname = '강일' AND status = 0");
  $보유개수 = (int)($보유행['cnt'] ?? 0);
  if ($보유개수 < 1) {
    echo 전송("❌ {$아이템닉} 님은 강일 아이템이 부족해요. ({$보유개수}/1)");
    exit;
  }

  $당사자닉_esc = addslashes($당사자닉);
  $rs = db_query("SELECT idx, nick, enddate, IFNULL(prolongation, 0) AS prolongation FROM tb_progress WHERE status = '강일' AND nick LIKE '%{$당사자닉_esc}%' ORDER BY regdate DESC");
  $대상 = null;
  if ($rs) {
    while ($row = db_fetch($rs)) {
      $참가자 = 진행문자열_닉목록($row['nick'] ?? '');
      if (in_array($당사자닉, $참가자, true)) {
        $대상 = $row;
        break;
      }
    }
  }

  if (empty($대상['idx'])) {
    echo 전송("❌ [ {$당사자닉} ] 진행 중인 강일이 없어요.");
    exit;
  }

  if ((int)($대상['prolongation'] ?? 0) >= 1) {
    echo 전송("❌ [ {$당사자닉} ] 강일연장은 1회만 가능해요.");
    exit;
  }

  db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE nick = '{$아이템닉_esc}' AND itemname = '강일' AND status = 0 ORDER BY idx ASC LIMIT 1");
  아이템사용_시세하락('강일', 1);

  $끝나는날 = date('Y-m-d H:i', strtotime($대상['enddate'] . ' +12 hours'));
  $idx = (int)$대상['idx'];
  db_query("UPDATE tb_progress SET enddate = '{$끝나는날}', prolongation = 1 WHERE idx = {$idx}");

  $진행 = trim((string)($대상['nick'] ?? ''));
  echo 전송("강일\n{$진행} 12시간 연장!\n{$끝나는날} 까지\n({$아이템닉} 강일 1개 사용)");
  exit;
}

/** 상황실 봇(info3.php) 요청 여부 */
function 상황실봇_요청여부(): bool {
  $script = basename($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? '');
  return $script === 'info3.php';
}

/** 일방·공커 자숙 수호: 받는 사람 기준 오늘 이미 적용했는지 */
function 수호_일방공커_오늘적용됨($받을닉_esc) {
  $자숙수호이력 = db_select("
    SELECT COUNT(*) AS cnt FROM tb_lotto_info
    WHERE msg LIKE '%수호 아이템 사용 → {$받을닉_esc}%'
      AND (msg LIKE '%일방 자숙%' OR msg LIKE '%공커 자숙%')
      AND DATE(regdate) = CURDATE()
  ");
  return (int)($자숙수호이력['cnt'] ?? 0) >= 1;
}

/** 수호 적용 가능한 자숙/제한 1건 (가장 최근 우선, 일방·공커는 하루 1회 제외 가능) */
function 수호_적용대상_조회($받을닉_esc, $일방공커스킵) {
  $rs = db_query("
    SELECT idx, status, enddate, suho
    FROM tb_self
    WHERE nick = '{$받을닉_esc}'
      AND enddate > NOW()
      AND status IN ('일방', '공커', '보룸제한', '채팅제한', '지또제한', '게임제한')
    ORDER BY regdate DESC, idx DESC
  ");
  while ($row = db_fetch($rs)) {
    $상태 = (string)($row['status'] ?? '');
    if ($일방공커스킵 && ($상태 === '일방' || $상태 === '공커')) {
      continue;
    }
    return $row;
  }
  return null;
}

/** tb_self 1건 단축 적용. [단축문구, 만료여부] 또는 null */
function 수호_자숙제한_1건단축(array $대상) {
  $상태 = (string)($대상['status'] ?? '');
  $단축문구 = '';
  if ($상태 === '일방' || $상태 === '공커') {
    $새끝 = date('Y-m-d H:i:s', strtotime($대상['enddate'] . ' -1 day'));
    $단축문구 = ($상태 === '일방') ? '일방 자숙 1일 단축' : '공커 자숙 1일 단축';
  } elseif ($상태 === '보룸제한') {
    $새끝 = date('Y-m-d H:i:s', strtotime($대상['enddate'] . ' -3 hours'));
    $단축문구 = '보이스룸 이용제한 3시간 단축';
  } elseif ($상태 === '채팅제한') {
    $새끝 = date('Y-m-d H:i:s', strtotime($대상['enddate'] . ' -3 hours'));
    $단축문구 = '채팅금지(이모티콘 포함) 3시간 단축';
  } elseif ($상태 === '지또제한' || $상태 === '게임제한') {
    $새끝 = date('Y-m-d H:i:s', strtotime($대상['enddate'] . ' -3 hours'));
    $단축문구 = '게임 이용제한 3시간 단축';
  } else {
    return null;
  }

  $수호횟수 = (int)($대상['suho'] ?? 0) + 1;
  $idx = (int)$대상['idx'];
  $만료됨 = (strtotime($새끝) <= time());

  if ($만료됨) {
    db_query("DELETE FROM tb_self WHERE idx = {$idx}");
  } else {
    db_query("UPDATE tb_self SET suho = {$수호횟수}, enddate = '{$새끝}' WHERE idx = {$idx}");
  }

  return [$단축문구, $만료됨, $새끝];
}

/**
 * .수호 (받을닉) [개수] — 상황실 봇 전용. 수호 N개면 N회 단축(가장 최근 항목부터).
 */
function 수호_상황실_명령_처리($status, $두자리닉넴) {
  if (!preg_match('/^\.수호(?:\s|$)/u', trim((string)$status))) {
    return;
  }
  if (!상황실봇_요청여부()) {
    return;
  }

  if ($두자리닉넴 === '') {
    echo 전송('❌ 닉네임을 확인해주세요.');
    exit;
  }

  if (!preg_match('/^\.수호\s+(\S+)(?:\s+(\d+))?\s*$/u', trim((string)$status), $수호m)) {
    echo 전송("❌ 사용법: .수호 (받을닉) [개수]\n예) .수호 우지\n예) .수호 우지 5");
    exit;
  }

  $받을닉 = 지목_명령_토큰닉($수호m[1]);
  if ($받을닉 === '') {
    echo 전송('❌ 받을 닉네임을 확인해주세요.');
    exit;
  }

  $요청개수 = isset($수호m[2]) ? (int)$수호m[2] : 1;
  if ($요청개수 < 1) {
    $요청개수 = 1;
  }

  $사용자 = $두자리닉넴;
  $사용자_esc = addslashes($사용자);
  $받을닉_esc = addslashes($받을닉);

  $받는친구 = db_select("SELECT idx FROM tb_member WHERE name = '{$받을닉_esc}' LIMIT 1");
  if (empty($받는친구['idx'])) {
    echo 전송("[ {$받을닉} ] 친구는 없음");
    exit;
  }

  $보유행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE nick = '{$사용자_esc}' AND itemname = '수호' AND status = 0");
  $보유개수 = (int)($보유행['cnt'] ?? 0);
  if ($보유개수 < 1) {
    echo 전송("{$사용자} 수호 없음!");
    exit;
  }
  if ($보유개수 < $요청개수) {
    echo 전송("{$사용자} 수호 부족! (보유 {$보유개수}개 · 요청 {$요청개수}개)");
    exit;
  }

  $아이템rs = db_query("
    SELECT idx FROM tb_member_item
    WHERE nick = '{$사용자_esc}' AND itemname = '수호' AND status = 0
    ORDER BY idx ASC
    LIMIT {$요청개수}
  ");
  $아이템idx목록 = [];
  while ($아이템행 = db_fetch($아이템rs)) {
    $아이템idx목록[] = (int)$아이템행['idx'];
  }
  if (count($아이템idx목록) < $요청개수) {
    echo 전송("{$사용자} 수호 부족!");
    exit;
  }

  $일방공커스킵 = 수호_일방공커_오늘적용됨($받을닉_esc);
  $첫대상 = 수호_적용대상_조회($받을닉_esc, $일방공커스킵);
  if (empty($첫대상['idx'])) {
    $있는대상 = 수호_적용대상_조회($받을닉_esc, false);
    if (empty($있는대상['idx'])) {
      echo 전송("❌ {$받을닉}님에게 적용할 자숙/제한이 없어요.\n(가장 최근 등록·아직 남은 항목만 수호 가능)");
      exit;
    }
    $첫상태 = (string)($있는대상['status'] ?? '');
    if (($첫상태 === '일방' || $첫상태 === '공커') && $일방공커스킵) {
      echo 전송("❌ {$받을닉}님은 오늘 이미 일방/공커 자숙에 수호를 받았어요!\n일방·공커 자숙은 하루 1회만 적용됩니다. (자정 이후 재사용 가능)");
      exit;
    }
    echo 전송('❌ 수호를 적용할 수 없어요.');
    exit;
  }

  $이번명령_일방공커적용 = false;
  $적용결과 = [];
  $효과적용 = 0;

  // 요청 개수만큼 항상 수호 소모 (제한이 먼저 끝나도 남은 개수는 그대로 차감)
  for ($i = 0; $i < $요청개수; $i++) {
    $일방공커스킵 = $일방공커스킵 || $이번명령_일방공커적용;
    $템idx = $아이템idx목록[$i];
    db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx = {$템idx}");

    $대상 = 수호_적용대상_조회($받을닉_esc, $일방공커스킵);
    if (empty($대상['idx'])) {
      continue;
    }

    $상태 = (string)($대상['status'] ?? '');
    $단축결과 = 수호_자숙제한_1건단축($대상);
    if ($단축결과 === null) {
      continue;
    }

    [$단축문구, $만료됨, $새끝] = $단축결과;

    $수호기록 = addslashes("{$사용자} 수호 아이템 사용 → {$받을닉} {$단축문구}");
    db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$수호기록}', leverage = 0, item = '{$사용자_esc}', regdate = NOW()");

    if ($상태 === '일방' || $상태 === '공커') {
      $이번명령_일방공커적용 = true;
      $일방공커스킵 = true;
    }

    if ($만료됨) {
      $적용결과[] = "✅ {$단축문구} → 해제 완료!";
    } else {
      $적용결과[] = "✅ {$단축문구} (해제예정 " . date('m-d H:i', strtotime($새끝)) . ')';
    }
    $효과적용++;
  }

  if (function_exists('아이템사용_시세하락')) {
    아이템사용_시세하락('수호', 1);
  }

  $남은목록 = db_query("
    SELECT status, enddate
    FROM tb_self
    WHERE nick = '{$받을닉_esc}'
      AND enddate > NOW()
      AND status IN ('일방', '공커', '보룸제한', '채팅제한', '지또제한', '게임제한')
    ORDER BY regdate DESC, idx DESC
  ");
  $남은문구 = '';
  while ($남은 = db_fetch($남은목록)) {
    $표시상태 = in_array($남은['status'], ['지또제한', '게임제한'], true) ? '게임제한' : $남은['status'];
    $남은문구 .= "\n· [{$표시상태}] 해제예정 " . date('m-d H:i', strtotime($남은['enddate']));
  }

  $소모만 = $요청개수 - $효과적용;
  $개수표시 = ($요청개수 > 1) ? " (수호 {$요청개수}개 사용)" : '';
  $msg = "👼 {$사용자} → {$받을닉}{$개수표시}";
  if (!empty($적용결과)) {
    $msg .= "\n\n" . implode("\n", $적용결과);
  }
  if ($소모만 > 0) {
    $msg .= "\n\n⚠️ 제한·자숙이 모두 해제된 뒤 수호 {$소모만}개가 추가로 소모됐어요.";
  }
  if ($남은문구 !== '') {
    $msg .= "\n\n남은 제한/자숙{$남은문구}\n(다음 수호는 가장 최근 항목부터 적용)";
  }
  echo 전송($msg);
  exit;
}

function 지목_명령_토큰닉($raw) {
  $raw = trim((string)$raw);
  if ($raw === '') {
    return '';
  }
  $two = getTwoCharNick($raw);
  return $two !== '' ? $two : $raw;
}

/**
 * 활성 게임제한(tb_self) 조회. legacy 지또제한 포함.
 * @return array{enddate:string}|null
 */
function 게임제한_활성조회($nick) {
  $nick_esc = addslashes(trim((string)$nick));
  if ($nick_esc === '') {
    return null;
  }
  $row = db_select("
    SELECT enddate FROM tb_self
    WHERE nick = '{$nick_esc}'
      AND status IN ('게임제한', '지또제한')
      AND enddate > NOW()
    ORDER BY enddate DESC
    LIMIT 1
  ");
  return !empty($row['enddate']) ? $row : null;
}

/** @return string|null 차단 시 안내 문구 */
function 게임제한_차단문구($nick) {
  $row = 게임제한_활성조회($nick);
  if (!$row) {
    return null;
  }
  $until = date('m-d H:i', strtotime($row['enddate']));
  return "❌ 게임제한 중이에요! (해제예정 {$until})\n맞다이·홀짝·무기시전·랜박·야바위 이용 불가";
}

/** tb_self에 nick이 있고 enddate가 남아 있으면 자숙 중 */
function 자숙_활성여부($닉) {
  $닉_esc = addslashes(trim((string)$닉));
  if ($닉_esc === '') {
    return false;
  }
  $row = db_select("SELECT idx FROM tb_self WHERE nick = '{$닉_esc}' AND enddate > NOW() LIMIT 1");
  return is_array($row) && !empty($row['idx']);
}

/**
 * 자숙 중 로또 등: enddate +5시간, 보유 게임냥(point) 10% 차감, 기록.
 *
 * @return array{applied:bool, notice:string, deduct:int, new_end:?string}
 */
function 자숙_게임위반_적용($닉, $게임라벨, $단위 = '냥', $로그상태 = '') {
  $닉_esc = addslashes(trim((string)$닉));
  $게임라벨 = trim((string)$게임라벨);
  if ($닉_esc === '' || $게임라벨 === '' || !자숙_활성여부($닉)) {
    return ['applied' => false, 'notice' => '', 'deduct' => 0, 'new_end' => null];
  }
  if ($로그상태 === '') {
    $로그상태 = '자숙위반-' . $게임라벨;
  }

  $회원 = db_select("SELECT point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
  $보유 = (int)($회원['point'] ?? 0);
  $차감 = (int)floor(max(0, $보유) * 10 / 100);

  db_query("UPDATE tb_self SET enddate = DATE_ADD(enddate, INTERVAL 5 HOUR) WHERE nick = '{$닉_esc}' AND enddate > NOW()");

  if ($차감 > 0) {
    db_query("UPDATE tb_member SET point = point - {$차감} WHERE name = '{$닉_esc}' LIMIT 1");
    지급로그($로그상태, $닉, '', 0, $차감);
  }

  $새끝행 = db_select("
    SELECT enddate FROM tb_self
    WHERE nick = '{$닉_esc}' AND enddate > NOW()
    ORDER BY enddate DESC
    LIMIT 1
  ");
  $새끝 = !empty($새끝행['enddate']) ? date('m-d H:i', strtotime($새끝행['enddate'])) : '';

  $기록 = "⚠️ {$닉} 자숙 중 {$게임라벨}\n자숙 +5시간";
  if ($차감 > 0) {
    $차감표 = function_exists('냥축약표시') ? 냥축약표시($차감, $단위) : number_format($차감) . $단위;
    $기록 .= ' · 게임냥 -' . $차감표;
  }
  if ($새끝 !== '') {
    $기록 .= "\n해제예정: {$새끝}";
  }
  $기록_esc = addslashes($기록);
  db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$기록_esc}', leverage = 0, item = '{$닉_esc}', regdate = NOW()");

  $notice = "⚠️ 자숙 중 {$게임라벨} 적발!\n자숙 +5시간";
  if ($차감 > 0) {
    $notice .= ' · 게임냥 -' . (function_exists('냥축약표시') ? 냥축약표시($차감, $단위) : number_format($차감) . $단위);
  }
  if ($새끝 !== '') {
    $notice .= " (해제예정 {$새끝})";
  }
  $notice .= "\n\n";

  return ['applied' => true, 'notice' => $notice, 'deduct' => $차감, 'new_end' => $새끝 !== '' ? $새끝 : null];
}

/** @return array{applied:bool, notice:string, deduct:int, deduct_from:string, new_end:?string} */
function 자숙_홀짝위반_적용($닉, $단위 = '냥') {
  return 자숙_시전보호위반_적용($닉, '홀짝', $단위, '자숙위반-홀짝');
}

/** @return array{applied:bool, notice:string, deduct:int, deduct_from:string, new_end:?string} */
function 자숙_강화위반_적용($닉, $단위 = '냥') {
  return 자숙_시전보호위반_적용($닉, '강화', $단위, '자숙위반-강화');
}

/** @return array{applied:bool, notice:string, deduct:int, deduct_from:string, new_end:?string} */
function 자숙_로또위반_적용($닉, $단위 = '냥') {
  return 자숙_게임위반_적용($닉, '로또', $단위, '자숙위반-로또');
}

/** @return array{applied:bool, notice:string, deduct:int, deduct_from:string, new_end:?string} */
function 자숙_야바위위반_적용($닉, $단위 = '냥') {
  return 자숙_시전보호위반_적용($닉, '야바위', $단위, '자숙위반-야바위');
}

/** @return array{applied:bool, notice:string, deduct:int, deduct_from:string, new_end:?string} */
function 자숙_맞다이위반_적용($닉, $단위 = '냥') {
  return 자숙_시전보호위반_적용($닉, '맞다이', $단위, '자숙위반-맞다이');
}

/**
 * 자숙 중 시전/보호: enddate +5시간, 1% 차감 (게임냥 우선, 없으면 보유냥 newpoint).
 *
 * @return array{applied:bool, notice:string, deduct:int, deduct_from:string, new_end:?string}
 */
function 자숙_시전보호위반_적용($닉, $라벨, $단위 = '냥', $로그상태 = '') {
  $닉_esc = addslashes(trim((string)$닉));
  $라벨 = trim((string)$라벨);
  if ($닉_esc === '' || $라벨 === '' || !자숙_활성여부($닉)) {
    return ['applied' => false, 'notice' => '', 'deduct' => 0, 'deduct_from' => '', 'new_end' => null];
  }
  if ($로그상태 === '') {
    $로그상태 = '자숙위반-' . $라벨;
  }

  $회원 = db_select("SELECT point, newpoint FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
  $게임냥 = (int)($회원['point'] ?? 0);
  $보유냥 = max(0, (int)floor((float)($회원['newpoint'] ?? 0)));

  $차감 = 0;
  $차감출처 = '';
  if ($게임냥 > 0) {
    $차감 = (int)floor($게임냥 * 1 / 100);
    $차감출처 = 'point';
    if ($차감 > 0) {
      db_query("UPDATE tb_member SET point = point - {$차감} WHERE name = '{$닉_esc}' LIMIT 1");
    }
  } elseif ($보유냥 > 0) {
    $차감 = (int)floor($보유냥 * 1 / 100);
    $차감출처 = 'newpoint';
    if ($차감 > 0) {
      db_query("UPDATE tb_member SET newpoint = newpoint - {$차감} WHERE name = '{$닉_esc}' LIMIT 1");
      $로그상태 .= '(보유냥)';
    }
  }

  db_query("UPDATE tb_self SET enddate = DATE_ADD(enddate, INTERVAL 5 HOUR) WHERE nick = '{$닉_esc}' AND enddate > NOW()");

  if ($차감 > 0) {
    지급로그($로그상태, $닉, '', 0, $차감);
  }

  $새끝행 = db_select("
    SELECT enddate FROM tb_self
    WHERE nick = '{$닉_esc}' AND enddate > NOW()
    ORDER BY enddate DESC
    LIMIT 1
  ");
  $새끝 = !empty($새끝행['enddate']) ? date('m-d H:i', strtotime($새끝행['enddate'])) : '';

  $차감라벨 = ($차감출처 === 'newpoint') ? '보유냥' : '게임냥';
  $기록 = "⚠️ {$닉} 자숙 중 {$라벨}\n자숙 +5시간";
  if ($차감 > 0) {
    $차감표 = function_exists('냥축약표시') ? 냥축약표시($차감, $단위) : number_format($차감) . $단위;
    $기록 .= " · {$차감라벨} -" . $차감표;
  }
  if ($새끝 !== '') {
    $기록 .= "\n해제예정: {$새끝}";
  }
  $기록_esc = addslashes($기록);
  db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$기록_esc}', leverage = 0, item = '{$닉_esc}', regdate = NOW()");

  $notice = "⚠️ 자숙 중 {$라벨} 적발!\n자숙 +5시간";
  if ($차감 > 0) {
    $notice .= " · {$차감라벨} -" . (function_exists('냥축약표시') ? 냥축약표시($차감, $단위) : number_format($차감) . $단위);
  }
  if ($새끝 !== '') {
    $notice .= " (해제예정 {$새끝})";
  }
  $notice .= "\n\n";

  return [
    'applied' => true,
    'notice' => $notice,
    'deduct' => $차감,
    'deduct_from' => $차감출처,
    'new_end' => $새끝 !== '' ? $새끝 : null,
  ];
}

/** 연속 시전 등 누적 패널티 요약 문구 */
function 자숙_시전보호_패널티_요약문구(array $누적, $라벨, $단위 = '냥') {
  $횟수 = (int)($누적['횟수'] ?? 0);
  if ($횟수 <= 0) {
    return '';
  }
  $연장시간 = $횟수 * 5;
  $msg = "⚠️ 자숙 중 {$라벨} 적발 ×{$횟수}\n자숙 +{$연장시간}시간";
  $차감문구 = [];
  $게임차감 = (int)($누적['게임냥'] ?? 0);
  $보유차감 = (int)($누적['보유냥'] ?? 0);
  if ($게임차감 > 0) {
    $차감문구[] = '게임냥 -' . (function_exists('냥축약표시') ? 냥축약표시($게임차감, $단위) : number_format($게임차감) . $단위);
  }
  if ($보유차감 > 0) {
    $차감문구[] = '보유냥 -' . (function_exists('냥축약표시') ? 냥축약표시($보유차감, $단위) : number_format($보유차감) . $단위);
  }
  if ($차감문구) {
    $msg .= ' · ' . implode(' · ', $차감문구);
  }
  $마지막끝 = trim((string)($누적['마지막끝'] ?? ''));
  if ($마지막끝 !== '') {
    $msg .= " (해제예정 {$마지막끝})";
  }
  return $msg . "\n\n";
}

/** @param array{횟수:int,게임냥:int,보유냥:int,마지막끝:string} $누적 */
function 자숙_시전보호_패널티_누적(array &$누적, $닉, $라벨, $단위 = '냥') {
  $결과 = 자숙_시전보호위반_적용($닉, $라벨, $단위);
  if (empty($결과['applied'])) {
    return;
  }
  $누적['횟수'] = (int)($누적['횟수'] ?? 0) + 1;
  if (($결과['deduct_from'] ?? '') === 'newpoint') {
    $누적['보유냥'] = (int)($누적['보유냥'] ?? 0) + (int)$결과['deduct'];
  } else {
    $누적['게임냥'] = (int)($누적['게임냥'] ?? 0) + (int)$결과['deduct'];
  }
  if (!empty($결과['new_end'])) {
    $누적['마지막끝'] = (string)$결과['new_end'];
  }
}

/** 차단 시 전송 후 exit */
function 게임제한_차단($nick) {
  $msg = 게임제한_차단문구($nick);
  if ($msg !== null) {
    echo 전송($msg);
    exit;
  }
}

/**
 * 우리방 회원 2글자 닉 검증 (공백·여분 글자 불가, tb_member 존재 필수)
 * @return array{ok:bool, nick?:string, msg?:string}
 */
function 우리방_회원닉_검증($raw, $라벨 = '닉네임') {
  $raw = trim((string)$raw);
  if ($raw === '') {
    return ['ok' => false, 'msg' => "❌ {$라벨}을 입력해주세요."];
  }
  if (preg_match('/\s/u', $raw)) {
    return ['ok' => false, 'msg' => "❌ {$라벨}은 한 개만 입력해주세요.\n예) .게임제한 소이"];
  }
  if (!preg_match('/^[가-힣]{2}$/u', $raw)) {
    return ['ok' => false, 'msg' => "❌ 우리방 2글자 닉네임만 가능해요.\n(입력: {$raw})"];
  }
  $esc = addslashes($raw);
  $row = db_select("SELECT idx FROM tb_member WHERE name = '{$esc}' LIMIT 1");
  if (empty($row['idx'])) {
    return ['ok' => false, 'msg' => "❌ [ {$raw} ] 회원을 찾을 수 없어요."];
  }
  return ['ok' => true, 'nick' => $raw];
}

/**
 * .채팅제한 / .익명채팅제한 등 — 상황실(info3) 전용.
 * 익명 명령은 본방(tb_lotto_info) 알림을 item=익명 으로 등록.
 */
function 제한_상황실_명령_처리($status, $두자리닉넴) {
  if (!preg_match('/^\.(?:익명)?(채팅|보룸|게임)제한\s+(\S+)\s*$/u', trim((string)$status), $match)) {
    return;
  }
  if (!function_exists('상황실봇_요청여부') || !상황실봇_요청여부()) {
    return;
  }

  $익명 = (strpos(trim((string)$status), '.익명') === 0);
  $유형 = $match[1];
  $닉검증 = 우리방_회원닉_검증($match[2], '제한받을 닉네임');
  if (!$닉검증['ok']) {
    echo 전송($닉검증['msg']);
    exit;
  }

  $진행 = $닉검증['nick'];
  $상태맵 = ['채팅' => '채팅제한', '보룸' => '보룸제한', '게임' => '게임제한'];
  $표시맵 = ['채팅' => '채팅제한', '보룸' => '보룸제한', '게임' => '게임제한'];
  $db상태 = $상태맵[$유형];
  $표시명 = $표시맵[$유형];
  $진행_esc = addslashes($진행);
  $사용자_esc = addslashes($두자리닉넴);

  $제한아이템 = db_select("SELECT idx FROM tb_member_item WHERE nick = '{$사용자_esc}' AND itemname = '제한' AND status = 0 LIMIT 1");
  if (empty($제한아이템['idx'])) {
    echo 전송('제한 아이템이 없습니다.');
    exit;
  }

  $data = db_select("SELECT * FROM tb_self WHERE status = '{$db상태}' AND nick = '{$진행_esc}' ");
  if (!empty($data['idx'])) {
    db_query("UPDATE tb_self SET enddate = DATE_ADD(enddate, INTERVAL 3 HOUR) WHERE idx = {$data['idx']}");
    $새끝 = db_select("SELECT enddate FROM tb_self WHERE idx = {$data['idx']}");
    $새끝날 = !empty($새끝['enddate']) ? date('Y-m-d H:i', strtotime($새끝['enddate'])) : '';
    db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx = {$제한아이템['idx']}");
    if (function_exists('아이템사용_시세하락')) {
      아이템사용_시세하락('제한', 1);
    }
    $응답 = "{$표시명} {$진행} 3시간 연장!\n해제예정: {$새끝날}";
    if ($익명 && function_exists('본방알림_등록')) {
      본방알림_등록($응답, '익명');
    }
    echo 전송($응답);
    exit;
  }

  $끝나는날 = date('Y-m-d H:i:s', strtotime('+3 hours'));
  db_query("INSERT INTO tb_self SET status = '{$db상태}', nick = '{$진행_esc}', enddate = '{$끝나는날}', regdate = NOW()");
  db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx = {$제한아이템['idx']}");
  if (function_exists('아이템사용_시세하락')) {
    아이템사용_시세하락('제한', 1);
  }
  $끝표시 = date('Y-m-d H:i', strtotime($끝나는날));
  $응답 = "{$표시명}\n{$진행} 3시간 {$표시명} 등록완료!\n{$끝표시} 까지";
  if ($익명 && function_exists('본방알림_등록')) {
    본방알림_등록($응답, '익명');
  }
  echo 전송($응답);
  exit;
}

/** .제한 / .익명제한 사용법 안내 (상황실) */
function 제한_상황실_안내_처리($status) {
  $trim = trim((string)$status);

  if ($trim === '.익명제한' || (strpos($trim, '.익명제한') === 0 && !preg_match('/^\.익명(?:채팅|보룸|게임)제한\s+\S+\s*$/u', $trim))) {
    echo 전송("익명 제한 아이템 사용법\n\n.익명채팅제한 닉네임\n.익명보룸제한 닉네임\n.익명게임제한 닉네임\n\n※ 본방 공지는 익명으로 전송됩니다.\n※ 닉네임은 우리방 2글자 닉 1개만 (예: .익명게임제한 소이)");
    exit;
  }

  if (strpos($trim, '.제한') !== false && strpos($trim, '.익명') !== 0) {
    echo 전송("제한 아이템 사용법\n\n.채팅제한 닉네임\n.보룸제한 닉네임\n.게임제한 닉네임\n\n※ 닉네임은 우리방 2글자 닉 1개만 (예: .게임제한 소이)\n\n익명(본방) 사용: .익명제한");
    exit;
  }
}

function 전송($msg){
  $data = array("data" => $msg);
  header('Content-Type: application/json; charset=utf-8');
  $flags = JSON_UNESCAPED_UNICODE;
  if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
  }
  $json = json_encode($data, $flags);
  if ($json === false) {
    $json = json_encode(array('data' => '(응답 인코딩 오류)'), JSON_UNESCAPED_UNICODE);
  }
  return $json;
}

/** 채팅 .시전/.보호 또는 웹 강화 UI 시전 공용 응답 */
function 시전_채팅응답($msg) {
  if (!empty($GLOBALS['ENCHANT_WEB_CAST'])) {
    throw new Exception('ENCHANT_WEB_CAST:' . (string)$msg);
  }
  echo 전송($msg);
  exit;
}

/** 십진수 앞 3자리만 유지, 나머지 자리는 0. 예) 2471153 → 2470000 */
function 냥_앞3자리_뒤0($n) {
  $n = (int)$n;
  if ($n <= 0) {
    return 0;
  }
  if ($n < 1000) {
    return $n;
  }
  $s = (string)$n;
  $len = strlen($s);
  return (int)(substr($s, 0, 3) . str_repeat('0', $len - 3));
}

/** 정수 금액에서 앞 2자리(십진)만 남기고 나머지 0. 예) 12345 → 12000 */
function 냥_앞두자리_뒤0($금액) {
  $금액 = (int)$금액;
  if ($금액 < 1) {
    return 1;
  }
  $자릿수 = strlen((string)$금액);
  if ($자릿수 > 2) {
    $배수 = (int)pow(10, $자릿수 - 2);
    $금액 = (int)(floor($금액 / $배수) * $배수);
  }
  return max(1, $금액);
}

/** 큰 냥 금액 → 부호 없는 정수 문자열 (PHP int/float 한계 회피) */
if (!function_exists('냥_정수문자열')) {
  function 냥_정수문자열($v): string {
    if (is_int($v)) {
      return $v < 0 ? (string)(-$v) : (string)$v;
    }
    if (is_float($v)) {
      if (!is_finite($v) || $v == 0.0) {
        return '0';
      }
      $neg = $v < 0;
      $v = abs($v);
      // float는 ~15자리만 정확 — 과학적 표기 문자열을 직접 파싱
      $s = strtoupper(trim(sprintf('%.16E', $v)));
      if (!preg_match('/^([0-9]+)(?:\.([0-9]+))?E([+-]?[0-9]+)$/', $s, $m)) {
        return '0';
      }
      $intPart = $m[1];
      $frac = $m[2] ?? '';
      $exp = (int)$m[3];
      $digits = $intPart . $frac;
      $expAdjust = $exp - strlen($frac);
      if ($expAdjust >= 0) {
        $digits .= str_repeat('0', $expAdjust);
      } else {
        $cut = strlen($digits) + $expAdjust;
        $digits = $cut > 0 ? substr($digits, 0, $cut) : '0';
      }
      $digits = ltrim($digits, '0') ?: '0';
      return $digits;
    }
    $s = trim((string)$v);
    if ($s === '' || $s === '-' || $s === '+') {
      return '0';
    }
    // 과학적 표기 문자열 (mysqli/JSON 등) — float 재변환 금지
    if (preg_match('/^([+-])?(\d+)(?:\.(\d+))?[eE]([+-]?\d+)$/', $s, $m)) {
      $digits = $m[2] . ($m[3] ?? '');
      $exp = (int)$m[4] - strlen($m[3] ?? '');
      if ($exp >= 0) {
        $digits .= str_repeat('0', $exp);
      } else {
        $cut = strlen($digits) + $exp;
        $digits = $cut > 0 ? substr($digits, 0, $cut) : '0';
      }
      return ltrim($digits, '0') ?: '0';
    }
    if (isset($s[0]) && ($s[0] === '-' || $s[0] === '+')) {
      $s = substr($s, 1);
    }
    if (strpos($s, '.') !== false) {
      $s = explode('.', $s, 2)[0];
    }
    $s = preg_replace('/[^\d]/', '', $s);
    return ltrim((string)$s, '0') ?: '0';
  }
}

/** config 시세 스냅샷 컬럼 (보상·아이템·스왑·모금 등 경제 시세 기준) */
if (!function_exists('시세기준_컬럼_보장')) {
  function 시세기준_컬럼_보장() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $cols = [
      '시세기준_본방냥' => "ADD COLUMN `시세기준_본방냥` DECIMAL(20,4) NOT NULL DEFAULT 0 COMMENT '시세 산정용 본방냥 총량 스냅샷'",
      '시세기준_게임냥' => "ADD COLUMN `시세기준_게임냥` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '시세 산정용 게임냥 총량 스냅샷'",
      '시세기준_갱신시각' => "ADD COLUMN `시세기준_갱신시각` DATETIME NULL DEFAULT NULL COMMENT '시세 스냅샷 마지막 갱신 시각'",
    ];
    foreach ($cols as $name => $ddl) {
      $col = db_select("SHOW COLUMNS FROM config LIKE '{$name}'");
      if (empty($col['Field'])) {
        @db_query("ALTER TABLE config {$ddl}");
      }
    }
    // BIGINT/좁은 DECIMAL → DECIMAL(40,0): PHP_INT_MAX·BIGINT(~922경) 초과 총량 보존
    $ptCol = db_select("SHOW COLUMNS FROM config LIKE '시세기준_게임냥'");
    $ptType = strtolower((string)($ptCol['Type'] ?? ''));
    $needWiden = false;
    if ($ptType !== '') {
      if (strpos($ptType, 'decimal') === false) {
        $needWiden = true;
      } elseif (preg_match('/decimal\((\d+)/', $ptType, $m) && (int)$m[1] < 40) {
        $needWiden = true;
      }
    }
    if ($needWiden) {
      @db_query("ALTER TABLE config MODIFY COLUMN `시세기준_게임냥` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '시세 산정용 게임냥 총량 스냅샷'");
    }
  }
}

/** status=0 회원 실시간 newpoint·point 합계 */
if (!function_exists('시세기준_실시간합계')) {
  function 시세기준_실시간합계(): array {
    // point가 BIGINT여도 행 단위 DECIMAL 캐스팅 후 SUM → 922경 초과 합계 보존
    $row = db_select("
      SELECT
        COALESCE(SUM(newpoint), 0) AS total_np,
        CAST(COALESCE(SUM(CAST(point AS DECIMAL(40,0))), 0) AS CHAR) AS total_pt
      FROM tb_member
      WHERE status = 0
    ");
    return [
      '본방냥' => (float)($row['total_np'] ?? 0),
      '게임냥' => 냥_정수문자열($row['total_pt'] ?? 0),
    ];
  }
}

/** config 시세 스냅샷 갱신 — 크론·최초·1시간 경과 시 */
if (!function_exists('시세기준_스냅샷_갱신')) {
  function 시세기준_스냅샷_갱신(bool $강제 = false): array {
    시세기준_컬럼_보장();
    $live = 시세기준_실시간합계();
    $np = (float)$live['본방냥'];
    $pt = 냥_정수문자열($live['게임냥'] ?? 0);
    $now = date('Y-m-d H:i:s');
    $np_sql = number_format($np, 4, '.', '');
    $pt_sql = $pt;
    db_query("
      UPDATE config
      SET `시세기준_본방냥` = {$np_sql},
          `시세기준_게임냥` = {$pt_sql},
          `시세기준_갱신시각` = '{$now}'
      LIMIT 1
    ");
    if (function_exists('시세기준_스냅샷_캐시_초기화')) {
      시세기준_스냅샷_캐시_초기화();
    }
    return [
      'ok' => true,
      '본방냥' => $np,
      '게임냥' => $pt,
      '갱신시각' => $now,
      '강제' => $강제,
    ];
  }
}

if (!function_exists('시세기준_스냅샷_캐시_초기화')) {
  function 시세기준_스냅샷_캐시_초기화() {
    global $시세기준_스냅샷_캐시;
    $시세기준_스냅샷_캐시 = null;
  }
}

/** @return array{본방냥: float, 게임냥: string, 갱신시각: string} */
if (!function_exists('시세기준_스냅샷_로드')) {
  function 시세기준_스냅샷_로드(): array {
    global $시세기준_스냅샷_캐시;
    if (is_array($시세기준_스냅샷_캐시)) {
      return $시세기준_스냅샷_캐시;
    }
    시세기준_컬럼_보장();
    $row = db_select("SELECT `시세기준_본방냥`, CAST(`시세기준_게임냥` AS CHAR) AS `시세기준_게임냥`, `시세기준_갱신시각` FROM config LIMIT 1");
    $np = (float)($row['시세기준_본방냥'] ?? 0);
    $pt = 냥_정수문자열($row['시세기준_게임냥'] ?? 0);
    $at = trim((string)($row['시세기준_갱신시각'] ?? ''));

    $needsRefresh = ($np <= 0 && $pt === '0') || ($at === '');
    // 예전 BIGINT/int 상한(922경3372조)에 고정된 스냅샷이면 즉시 재집계
    if (!$needsRefresh && $pt === (string)PHP_INT_MAX) {
      $needsRefresh = true;
    }
    if (!$needsRefresh && $at !== '') {
      $ts = strtotime($at);
      if ($ts !== false && (time() - $ts) >= 3600) {
        $needsRefresh = true;
      }
    }
    if ($needsRefresh) {
      $refreshed = 시세기준_스냅샷_갱신(true);
      $시세기준_스냅샷_캐시 = [
        '본방냥' => (float)$refreshed['본방냥'],
        '게임냥' => 냥_정수문자열($refreshed['게임냥'] ?? 0),
        '갱신시각' => (string)$refreshed['갱신시각'],
      ];
      return $시세기준_스냅샷_캐시;
    }

    $시세기준_스냅샷_캐시 = [
      '본방냥' => $np,
      '게임냥' => $pt,
      '갱신시각' => $at,
    ];
    return $시세기준_스냅샷_캐시;
  }
}

/** 경제 시세 산정용 본방냥 총량 (스냅샷) */
if (!function_exists('시세기준_본방냥')) {
  function 시세기준_본방냥(): float {
    $snap = 시세기준_스냅샷_로드();
    return (float)$snap['본방냥'];
  }
}

/** 경제 시세 산정용 게임냥 총량 문자열 (스냅샷, PHP_INT_MAX 초과 가능) */
if (!function_exists('시세기준_게임냥_문자열')) {
  function 시세기준_게임냥_문자열(): string {
    $snap = 시세기준_스냅샷_로드();
    return 냥_정수문자열($snap['게임냥'] ?? 0);
  }
}

/**
 * 경제 시세 산정용 게임냥 총량 (스냅샷)
 * PHP_INT_MAX 초과 시에도 잘리지 않도록 가능하면 숫자 문자열을 그대로 반환.
 * @return int|string
 */
if (!function_exists('시세기준_게임냥')) {
  function 시세기준_게임냥() {
    $s = 시세기준_게임냥_문자열();
    if (function_exists('bccomp')) {
      if (bccomp($s, (string)PHP_INT_MAX, 0) <= 0) {
        return (int)$s;
      }
      return $s;
    }
    if (strlen($s) < strlen((string)PHP_INT_MAX)
      || (strlen($s) === strlen((string)PHP_INT_MAX) && $s <= (string)PHP_INT_MAX)) {
      return (int)$s;
    }
    return $s;
  }
}

if (!function_exists('시세기준_갱신시각_표시')) {
  function 시세기준_갱신시각_표시(): string {
    $snap = 시세기준_스냅샷_로드();
    $at = trim((string)($snap['갱신시각'] ?? ''));
    if ($at === '') {
      return '미갱신';
    }
    $ts = strtotime($at);
    if ($ts === false) {
      return $at;
    }
    return date('m-d H:i', $ts) . ' 갱신';
  }
}

if (!function_exists('전체냥기준금액')) {
  // 예: 전체냥기준금액(0.01) => 시세 기준 게임냥 총량의 0.01%
  // 예: 전체냥기준금액(0.01, true) => 위 금액에 냥_앞두자리_뒤0 적용
  // PHP_INT_MAX 초과 시 정수 문자열 반환
  function 전체냥기준금액($percent, $앞두자리만유지 = false) {
    $전체보유냥 = function_exists('시세기준_게임냥_문자열')
      ? 시세기준_게임냥_문자열()
      : 냥_정수문자열(시세기준_게임냥());
    $비율 = ((float)$percent) / 100;
    if (function_exists('bcmul') && function_exists('bcadd') && function_exists('bccomp')) {
      $ratioStr = sprintf('%.12F', $비율);
      $raw = bcmul($전체보유냥, $ratioStr, 12);
      $금액 = bcadd($raw, '0', 0);
      if (bccomp($raw, $금액, 12) > 0) {
        $금액 = bcadd($금액, '1', 0);
      }
      if (bccomp($금액, '1', 0) < 0) {
        $금액 = '1';
      }
      if ($앞두자리만유지 && function_exists('냥_앞두자리_뒤0')
        && bccomp($금액, (string)PHP_INT_MAX, 0) <= 0) {
        return 냥_앞두자리_뒤0((int)$금액);
      }
      if (bccomp($금액, (string)PHP_INT_MAX, 0) <= 0) {
        return (int)$금액;
      }
      return $금액;
    }
    $금액 = (int)ceil((float)$전체보유냥 * $비율);
    $금액 = max(1, $금액);
    if ($앞두자리만유지) {
      $금액 = 냥_앞두자리_뒤0($금액);
    }
    return $금액;
  }
}

/** 모금 12초당 필요 냥 (전체 보유 ÷ 10만, config.php 초단위변환과 동일) */
function 모금나눗값구하기(): int
{
  global $전체포인트;
  $총냥 = (int)($전체포인트['total_point'] ?? 0);
  return 모금나눗값_총냥기준($총냥);
}

/** 총 보유 냥으로 모금 12초당 단가 계산 */
function 모금나눗값_총냥기준(int $총냥): int
{
  return max(1, intdiv(max(0, $총냥), 100000));
}

/** tb_self.mogum_unit — 자숙(일방·공커) 입장 시점 모금 단가 */
if (!function_exists('tb_self_모금단가_컬럼_보장')) {
  function tb_self_모금단가_컬럼_보장() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $rs = @db_query("SHOW COLUMNS FROM tb_self LIKE 'mogum_unit'");
    $row = $rs ? db_fetch($rs) : null;
    if (empty($row['Field'])) {
      @db_query("ALTER TABLE tb_self ADD COLUMN `mogum_unit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '자숙 입장 시 모금 12초당 단가(전체냥/10만)'");
    }
  }
}

/** mogum_unit 컬럼 존재 여부 (ALTER 실패 시 SQL 오류 방지) */
if (!function_exists('tb_self_mogum_unit_존재')) {
  function tb_self_mogum_unit_존재(): bool {
    static $cache = null;
    if ($cache !== null) {
      return $cache;
    }
    tb_self_모금단가_컬럼_보장();
    $rs = @db_query("SHOW COLUMNS FROM tb_self LIKE 'mogum_unit'");
    $row = $rs ? db_fetch($rs) : null;
    $cache = !empty($row['Field']);
    return $cache;
  }
}

/**
 * 진행 중 일방·공커 중 mogum_unit=0 → 현재 모금 단가로 1회 고정
 * (과거 입장 시점 단가는 DB에 없어 복구 불가 — 지금 시점 단가로 동결)
 */
if (!function_exists('tb_self_모금단가_미설정_보정')) {
  function tb_self_모금단가_미설정_보정() {
    tb_self_모금단가_컬럼_보장();
    $unit = (int)모금나눗값구하기();
    if ($unit <= 0) {
      return;
    }
    @db_query("
      UPDATE tb_self
      SET mogum_unit = {$unit}
      WHERE status IN ('일방', '공커')
        AND enddate > NOW()
        AND IFNULL(mogum_unit, 0) = 0
    ");
  }
}

/** 일방·공커 tb_self INSERT 시 mogum_unit 절 */
if (!function_exists('tb_self_일방공커_모금단가_sql')) {
  function tb_self_일방공커_모금단가_sql($status): string {
    $status = trim((string)$status);
    if ($status !== '일방' && $status !== '공커') {
      return '';
    }
    tb_self_모금단가_컬럼_보장();
    $unit = (int)모금나눗값구하기();
    return ", mogum_unit = {$unit}";
  }
}

/** .모금 적용 단가 — 대상 닉 지정 시 해당 입장 단가, 전체면 활성 일방·공커 평균 */
if (!function_exists('모금_적용단가')) {
  function 모금_적용단가(?string $targetNick = null): int {
    tb_self_모금단가_컬럼_보장();
    tb_self_모금단가_미설정_보정();

    if ($targetNick !== null && trim($targetNick) !== '') {
      $nick_esc = addslashes(trim($targetNick));
      $row = db_select("
        SELECT mogum_unit
        FROM tb_self
        WHERE nick = '{$nick_esc}'
          AND status IN ('일방', '공커')
          AND enddate > NOW()
        ORDER BY idx DESC
        LIMIT 1
      ");
      $unit = (int)($row['mogum_unit'] ?? 0);
      if ($unit > 0) {
        return $unit;
      }
    }

    $rs = @db_query("
      SELECT mogum_unit
      FROM tb_self
      WHERE status IN ('일방', '공커')
        AND enddate > NOW()
        AND IFNULL(mogum_unit, 0) > 0
    ");
    $units = [];
    if ($rs) {
      while ($r = db_fetch($rs)) {
        $units[] = (int)$r['mogum_unit'];
      }
    }
    if (count($units) === 1) {
      return $units[0];
    }
    if (count($units) > 1) {
      return max(1, (int)round(array_sum($units) / count($units)));
    }

    return 모금나눗값구하기();
  }
}

/** .모금 금액 → 자숙 단축 초 (입장 시점 mogum_unit 기준) */
if (!function_exists('모금_금액to초')) {
  function 모금_금액to초(int $money, ?string $targetNick = null): int {
    if ($money <= 0) {
      return 0;
    }
    $단가 = 모금_적용단가($targetNick);
    return intdiv($money, $단가) * 12;
  }
}

/**
 * .모금 닉 지정 시 — 해당 자숙인 mogum_unit(12초당) 미만이면 거부
 * @return array{ok:bool, msg?:string, 단가?:int}
 */
if (!function_exists('모금_대상단가_검증')) {
  function 모금_대상단가_검증(int $금액, ?string $targetNick): array {
    $targetNick = trim((string)$targetNick);
    if ($targetNick === '') {
      return ['ok' => true];
    }
    tb_self_모금단가_컬럼_보장();
    tb_self_모금단가_미설정_보정();

    $단가 = (int)모금_적용단가($targetNick);
    if ($단가 <= 0) {
      return ['ok' => true];
    }
    if ($금액 < $단가) {
      $단가표 = function_exists('냥축약표시') ? 냥축약표시($단가) : number_format($단가) . "냥";
      return [
        'ok' => false,
        '단가' => $단가,
        'msg' => "❌ {$targetNick} 모금은 12초당 {$단가표} 이상이어야 해요.\n(입장 시점 단가 미만은 적용되지 않아요)",
      ];
    }
    $seconds = intdiv($금액, $단가) * 12;
    if ($seconds < 1) {
      $단가표 = function_exists('냥축약표시') ? 냥축약표시($단가) : number_format($단가) . "냥";
      return [
        'ok' => false,
        '단가' => $단가,
        'msg' => "❌ {$targetNick} 모금은 최소 {$단가표} (12초 단축) 이상이어야 해요.",
      ];
    }
    return ['ok' => true, '단가' => $단가];
  }
}

/** 인당 자숙 종료에 필요한 냥 (남은 초 ÷ 12초 단위 × 모금단가) */
function 자숙인당종료필요냥(int $remainSec, ?int $모금단가 = null, bool $저장단가만 = false): float
{
  if ($remainSec <= 0) {
    return 0.0;
  }
  $units = (int)ceil($remainSec / 12);
  if ($모금단가 !== null && $모금단가 > 0) {
    return (float)$units * (float)$모금단가;
  }
  if ($저장단가만) {
    return 0.0;
  }
  return (float)$units * (float)모금나눗값구하기();
}

/** 일방·공커 전원 — 인당 비용 합산 (입장 시점 단가 반영) */
function 자숙전체종료필요냥합계(array $entries, bool $저장단가만 = false): float
{
  $합계 = 0.0;
  foreach ($entries as $entry) {
    if (is_array($entry)) {
      $sec = (int)($entry['remain_seconds'] ?? 0);
      $unit = isset($entry['mogum_unit']) ? (int)$entry['mogum_unit'] : 0;
      $합계 += 자숙인당종료필요냥($sec, $unit > 0 ? $unit : null, $저장단가만);
    } else {
      $합계 += 자숙인당종료필요냥((int)$entry, null, $저장단가만);
    }
  }
  return $합계;
}

/** @deprecated 자숙인당종료필요냥 사용 */
function 자숙전체종료필요냥(int $maxRemainSec): float
{
  return 자숙인당종료필요냥($maxRemainSec);
}

/** .자숙 목록용 — 최소 모금·전체 종료 예상 냥 안내 (DB mogum_unit + enddate 기준만) */
function 자숙모금종료안내문(array $entries, int $자숙인원 = 0): string
{
  try {
    tb_self_모금단가_컬럼_보장();
    // 표시용: DB 저장 단가만 사용 (.모금 시 보정은 모금_적용단가 쪽에서 처리)

    $normalized = [];
    foreach ($entries as $entry) {
      if (is_array($entry)) {
        $sec = (int)($entry['remain_seconds'] ?? 0);
        if ($sec <= 0) {
          continue;
        }
        $normalized[] = [
          'nick' => trim((string)($entry['nick'] ?? '')),
          'remain_seconds' => $sec,
          'mogum_unit' => isset($entry['mogum_unit']) ? (int)$entry['mogum_unit'] : 0,
        ];
      } else {
        $sec = (int)$entry;
        if ($sec > 0) {
          $normalized[] = ['nick' => '', 'remain_seconds' => $sec, 'mogum_unit' => 0];
        }
      }
    }

    if (empty($normalized)) {
      return "전체 자숙: 모금 단축 대상 없음\n";
    }

    $최소 = 전체냥기준금액(0.01, true);
    $필요 = 자숙전체종료필요냥합계($normalized, true);
    $인원 = $자숙인원 > 0 ? $자숙인원 : count($normalized);
    $fmt = function_exists('냥축약표시')
      ? function ($금액) { return 냥축약표시($금액); }
      : function ($금액) { return number_format((float)$금액) . '냥'; };

    $lines = "최소 모금: " . $fmt($최소) . "\n";
    $lines .= "전체 종료 예상: " . $fmt($필요) . " ({$인원}명 인당 합산, 입장 단가×남은시간)\n";

    return $lines;
  } catch (Throwable $e) {
    return "전체 종료 예상: (계산 불가 — mogum_unit 값 확인)\n";
  }
}

/** 초 → "N분" / "N시간 N분" 표시 */
if (!function_exists('자숙_초_표시')) {
  function 자숙_초_표시(int $seconds): string {
    if ($seconds < 60) {
      return "{$seconds}초";
    }
    if ($seconds < 3600) {
      $minutes = (int)floor($seconds / 60);
      return ($minutes < 1 ? 1 : $minutes) . '분';
    }
    $hours = (int)floor($seconds / 3600);
    $remain = $seconds % 3600;
    $minutes = (int)floor($remain / 60);
    return $minutes > 0 ? "{$hours}시간 {$minutes}분" : "{$hours}시간";
  }
}

/** .자숙 목록 조회 명령 여부 (.자숙공지·.자숙추가 제외, . 자숙·.자숙(일방/…) 포함) */
if (!function_exists('자숙_명령_여부')) {
  function 자숙_명령_여부($status): bool {
    $t = function_exists('status_정규화') ? status_정규화($status) : trim((string)$status);
    if ($t === '') {
      return false;
    }
    if (preg_match('/^\.[\s\p{Zs}]*자숙(?:공지|추가|종료)/u', $t)) {
      return false;
    }
    return (bool)preg_match('/^\.[\s\p{Zs}]*자숙(?!공지|추가)/u', $t);
  }
}

/** .자숙 — 목록 응답 후 exit, 미해당 시 그대로 복귀 */
if (!function_exists('자숙_명령_처리')) {
  function 자숙_명령_처리($status): void {
    if (!자숙_명령_여부($status)) {
      return;
    }
    try {
      if (!function_exists('자숙_목록_문구')) {
        echo 전송('❌ 자숙 목록을 불러올 수 없어요. function.php 배포를 확인해주세요.');
        exit;
      }
      echo 전송(자숙_목록_문구());
    } catch (Throwable $e) {
      echo 전송('❌ 자숙 조회 중 오류가 발생했어요. 잠시 후 다시 시도해주세요.');
    }
    exit;
  }
}

/** .자숙종료 — 진행 중 자숙·모금 기록 일괄 삭제 */
if (!function_exists('자숙종료_명령_여부')) {
  function 자숙종료_명령_여부($status): bool {
    $t = function_exists('status_정규화') ? status_정규화($status) : trim((string)$status);
    return (bool)preg_match('/^\.[\s\p{Zs}]*자숙종료\s*$/u', $t);
  }
}

if (!function_exists('자숙종료_명령_처리')) {
  function 자숙종료_명령_처리($status, $두자리닉넴 = '', $nick = '', $관리자 = []): void {
    if (!자숙종료_명령_여부($status)) {
      return;
    }
    if (function_exists('관리자_명령_차단')) {
      관리자_명령_차단($두자리닉넴, $nick);
    }

    $자숙전체 = db_select("SELECT COUNT(*) AS c FROM tb_self");
    $자숙활성 = db_select("SELECT COUNT(*) AS c FROM tb_self WHERE enddate > NOW()");
    $모금전체 = db_select("SELECT COUNT(*) AS c FROM tb_loan");

    $자숙삭제수 = (int)($자숙전체['c'] ?? 0);
    $자숙활성수 = (int)($자숙활성['c'] ?? 0);
    $모금삭제수 = (int)($모금전체['c'] ?? 0);

    db_query('DELETE FROM tb_self');
    db_query('DELETE FROM tb_loan');

    $msg = "✅ 자숙 종료 완료\n\n";
    $msg .= "· tb_self {$자숙삭제수}건 삭제 (진행중 {$자숙활성수}명)\n";
    $msg .= "· tb_loan {$모금삭제수}건 삭제 (모금 기록 초기화)";

    echo 전송($msg);
    exit;
  }
}

/** .자숙 목록 — 수호(👼) 이모티콘 (과도한 str_repeat 방지) */
if (!function_exists('자숙_수호_표시')) {
  function 자숙_수호_표시($suho): string {
    $n = max(0, min(30, (int)$suho));
    return $n > 0 ? str_repeat('👼', $n) : '';
  }
}

/** .자숙 목록 본문 (본방·상황실 공통) */
if (!function_exists('자숙_목록_문구')) {
  function 자숙_목록_문구(): string {
    if (!isset($GLOBALS['전체포인트']) || !isset($GLOBALS['단위'])) {
      @include_once __DIR__ . '/config.php';
    }
    tb_self_모금단가_컬럼_보장();

    $msg = "✅ 자숙 - 일방/공커/제한\n\n";
    $카운트 = 0;

    $result0 = db_query("SELECT * FROM tb_self WHERE status = '생자' ORDER BY enddate ASC");
    if ($result0 && mysqli_num_rows($result0) > 0) {
      $msg .= "생자숙\n";
      while ($row = db_fetch($result0)) {
        $msg .= '🏝️' . $row['nick'] . ' ' . date('m/d H:i', strtotime($row['enddate'])) . "\n";
        $카운트++;
      }
      $msg .= "\n";
    }

    $result1 = db_query("SELECT * FROM tb_self WHERE status = '일방' ORDER BY enddate ASC");
    $일방개수 = $result1 ? mysqli_num_rows($result1) : 0;
    if ($일방개수 > 0) {
      $msg .= "일방 자숙\n";
      while ($row = db_fetch($result1)) {
        $수호이모티콘 = function_exists('자숙_수호_표시')
          ? 자숙_수호_표시($row['suho'] ?? 0)
          : str_repeat('👼', max(0, min(30, (int)($row['suho'] ?? 0))));
        $msg .= '🐙' . $row['nick'] . ' ' . date('m/d H:i', strtotime($row['enddate'])) . $수호이모티콘 . "\n";
        $카운트++;
      }
      $msg .= "\n";
    }

    $result2 = db_query("SELECT * FROM tb_self WHERE status = '공커' ORDER BY regdate ASC");
    $공커개수 = $result2 ? mysqli_num_rows($result2) : 0;
    if ($공커개수 > 0) {
      $msg .= "공커 자숙\n";
      while ($row = db_fetch($result2)) {
        $수호이모티콘 = function_exists('자숙_수호_표시')
          ? 자숙_수호_표시($row['suho'] ?? 0)
          : str_repeat('👼', max(0, min(30, (int)($row['suho'] ?? 0))));
        $msg .= $row['nick'] . ' ' . date('m/d H:i', strtotime($row['enddate'])) . $수호이모티콘 . "\n";
        $카운트++;
      }
      $msg .= "\n";
    }

    $result3 = db_query("SELECT * FROM tb_self WHERE status IN ('보룸제한','채팅제한','지또제한','게임제한') ORDER BY regdate ASC");
    if ($result3 && mysqli_num_rows($result3) > 0) {
      $msg .= "제한\n";
      while ($row = db_fetch($result3)) {
        $수호이모티콘 = function_exists('자숙_수호_표시')
          ? 자숙_수호_표시($row['suho'] ?? 0)
          : str_repeat('👼', max(0, min(30, (int)($row['suho'] ?? 0))));
        $제한종류 = str_replace('제한', '', (string)($row['status'] ?? ''));
        $msg .= '[' . $제한종류 . '] ' . $row['nick'] . ' ' . date('d일 H:i', strtotime($row['enddate'])) . $수호이모티콘 . "\n";
        $카운트++;
      }
      $msg .= "\n";
    }

    if (!$카운트) {
      $msg .= "다음은 너야!";
    }

    if ($일방개수 > 0 || $공커개수 > 0) {
      $now = date('Y-m-d H:i:s');
      if (function_exists('tb_self_mogum_unit_존재') && tb_self_mogum_unit_존재()) {
        $result = @db_query("
          SELECT
            nick,
            TIMESTAMPDIFF(SECOND, '{$now}', enddate) AS remain_seconds,
            IFNULL(mogum_unit, 0) AS mogum_unit
          FROM tb_self
          WHERE status IN ('공커', '일방')
            AND enddate > NOW()
        ");
      } else {
        $result = @db_query("
          SELECT
            nick,
            TIMESTAMPDIFF(SECOND, '{$now}', enddate) AS remain_seconds,
            0 AS mogum_unit
          FROM tb_self
          WHERE status IN ('공커', '일방')
            AND enddate > NOW()
        ");
      }

      $total_remain = 0;
      $remain_list = [];
      if ($result) {
        while ($row = db_fetch($result)) {
          $remain = (int)($row['remain_seconds'] ?? 0);
          if ($remain > 0) {
            $total_remain += $remain;
            $remain_list[] = [
              'nick' => (string)($row['nick'] ?? ''),
              'remain_seconds' => $remain,
              'mogum_unit' => (int)($row['mogum_unit'] ?? 0),
            ];
          }
        }
      }

      if ($total_remain <= 0) {
        $total_text = '모두 만료됨';
      } else {
        $total_hours = (int)floor($total_remain / 3600);
        $total_minutes = (int)floor(($total_remain % 3600) / 60);
        $total_text = "{$total_hours}시간 {$total_minutes}분";
      }

      $msg .= "남은 시간: {$total_text}\n";
      $msg .= 자숙모금종료안내문($remain_list, count($remain_list));

      $총타임 = db_select('SELECT SUM(times) AS times FROM tb_loan');
      $msg .= '모금 차감: ' . 자숙_초_표시((int)($총타임['times'] ?? 0)) . "\n";

      $loan_rs = db_query('SELECT * FROM tb_loan ORDER BY regdate DESC');
      $총모금액 = 0.0;
      if ($loan_rs) {
        while ($row = db_fetch($loan_rs)) {
          $times = 자숙_초_표시((int)($row['times'] ?? 0));
          $금액표 = function_exists('냥축약표시')
            ? 냥축약표시((float)($row['point'] ?? 0))
            : number_format((float)($row['point'] ?? 0)) . "냥";
          $msg .= "\n-{$times} {$row['nick']} {$금액표}";
          $총모금액 += (float)($row['point'] ?? 0);
        }
      }
      $msg .= "\n\n총 모금액 : " . (function_exists('냥축약표시') ? 냥축약표시($총모금액) : number_format($총모금액) . '냥');
    }

    return trim($msg);
  }
}

function 특정시간대여부() {
    date_default_timezone_set('Asia/Seoul');
    $hour = date('G'); // 현재 시각 (0~23)

    // ✅ 직접 시간대 지정 (시작 => 끝)
    $timeRanges = [
        [0, 2],    // 🕛 00:00 ~ 01:00
        [7, 8],   // 🕖 07:00 ~ 08:00
        [12, 13], // 🕛 12:00 ~ 13:00
        [19, 20] // 🕗 19:00 ~ 20:00

    ];

    // 현재 시각이 지정된 시간대에 포함되는지 확인
    foreach ($timeRanges as $range) {
        list($start, $end) = $range;
        if ($hour >= $start && $hour < $end) {
            return [
                'status' => true,
                'message' => "✅ 지금은 지정된 시간대입니다! ({$start}:00 ~ {$end}:00)"
            ];
        }
    }

    // 다음 시간대 계산
    $next = null;
    foreach ($timeRanges as $range) {
        if ($hour < $range[0]) {
            $next = $range;
            break;
        }
    }
    if ($next === null) $next = $timeRanges[0]; // 하루 넘어가는 경우

    // 남은 시간 계산
    $remaining = ($next[0] > $hour) ? $next[0] - $hour : (24 - $hour + $next[0]);

    // 보기 좋은 표시용 텍스트
    $표시 = "🕖 오전 00시~00:30\n🕛 오전 08시~08:30\n🕛 오후 16시~16:30";

    return [
        'status' => false,
        'message' => "❌ 이용불가 ❌\n\n📅 가능 시간대:\n{$표시}\n\n⏳ 다음은 {$next[0]}:00 ~ {$next[1]}:00 (약 {$remaining}시간 후)"
    ];
}

function 포인트지급($상태, $닉네임, $계급보상="", $point=1, $auto=0){



  if($상태){
    $오늘 = date("Y-m-d");
    $sql = "select count(*) as cnt from tb_point_log where (status = '{$상태}') and nick = '{$닉네임}' and date_format(regdate,'%Y-%m-%d') = '{$오늘}' ";
    $타수쳌 = db_select($sql);
    if($타수쳌['cnt']==0){

      $sql1 = "insert into tb_point_log set status = '".$상태."', nick = '{$닉네임}', point = {$point}, bonus = '{$계급보상}', auto = '{$auto}', regdate = now() ";
      $result = db_query($sql1);
      db_query("update tb_member set point = point + {$point} where name = '{$닉네임}' ");
    }
  }else{
    $result = 0;
  }

  return $result;
}


function 지급로그_int_안전($v): int {
  $digits = preg_replace('/[^\d]/', '', (string)$v);
  if ($digits === '') {
    return 0;
  }
  $max = '2147483647';
  if (function_exists('bccomp')) {
    if (bccomp($digits, $max) > 0) {
      return 0;
    }
    return (int)$digits;
  }
  if ((float)$digits > 2147483647) {
    return 0;
  }
  return (int)$digits;
}

function 지급로그($상태, $두자리닉넴, $받는이, $수수료, $지급냥){
  global $conn;
  $닉_esc = addslashes((string)$두자리닉넴);
  $받는_esc = addslashes((string)$받는이);
  $상태_esc = addslashes((string)$상태);
  $my = db_select("select point from tb_member where name = '{$닉_esc}' ");
  $mypoint = 지급로그_int_안전($my['point'] ?? 0);
  $수수료_int = 지급로그_int_안전($수수료);
  $지급냥_int = 지급로그_int_안전($지급냥);
  $sql = "insert into tb_point_log set ";
  $sql.= "status = '{$상태_esc}', ";
  $sql.= "nick = '{$닉_esc}', ";
  $sql.= "receiver = '{$받는_esc}', ";
  $sql.= "tax = {$수수료_int}, ";
  $sql.= "point = {$지급냥_int}, ";
  $sql.= "mypoint = {$mypoint}, ";
  $sql.= "regdate = now() ";
  $result = db_query($sql);

  return $result;
}

function 계급($point) {
    // name: 이모지 + 동물·해산물 명칭(한글 2글자), rank/add/sell 구간은 기존과 동일
    if ($point < 1000000) return ['rank'=>5, 'name'=>'🌾토끼', 'add'=>0, 'sell'=>50];
    elseif ($point < 2000000) return ['rank'=>12, 'name'=>'🏪참새', 'add'=>1, 'sell'=>50];
    elseif ($point < 5000000) return ['rank'=>25, 'name'=>'🔨다람', 'add'=>1, 'sell'=>50];
    elseif ($point < 8000000) return ['rank'=>50, 'name'=>'📜개구', 'add'=>1, 'sell'=>50];
    elseif ($point < 10000000) return ['rank'=>75, 'name'=>'🎓고슴', 'add'=>1, 'sell'=>50];
    elseif ($point < 15000000) return ['rank'=>100, 'name'=>'📖여우', 'add'=>1, 'sell'=>50];
    elseif ($point < 20000000) return ['rank'=>125, 'name'=>'✍️사슴', 'add'=>2, 'sell'=>50];
    elseif ($point < 40000000) return ['rank'=>162, 'name'=>'📝수달', 'add'=>2, 'sell'=>50];
    elseif ($point < 60000000) return ['rank'=>200, 'name'=>'🪶담비', 'add'=>2, 'sell'=>50];
    elseif ($point < 80000000) return ['rank'=>225, 'name'=>'📋박쥐', 'add'=>2, 'sell'=>50];
    elseif ($point < 100000000) return ['rank'=>275, 'name'=>'⚖️표범', 'add'=>2, 'sell'=>150];
    elseif ($point < 200000000) return ['rank'=>325, 'name'=>'🏘️사자', 'add'=>3, 'sell'=>150];
    elseif ($point < 300000000) return ['rank'=>375, 'name'=>'📜호랑', 'add'=>3, 'sell'=>150];
    elseif ($point < 400000000) return ['rank'=>425, 'name'=>'🎖️불곰', 'add'=>3, 'sell'=>150];
    elseif ($point < 500000000) return ['rank'=>475, 'name'=>'📯판다', 'add'=>3, 'sell'=>150];
    elseif ($point < 600000000) return ['rank'=>525, 'name'=>'🏛️기린', 'add'=>3, 'sell'=>300];
    elseif ($point < 700000000) return ['rank'=>600, 'name'=>'🏯코끼', 'add'=>4, 'sell'=>300];
    elseif ($point < 800000000) return ['rank'=>675, 'name'=>'👔하마', 'add'=>4, 'sell'=>300];
    elseif ($point < 900000000) return ['rank'=>750, 'name'=>'🔱코뿔', 'add'=>4, 'sell'=>300];
    elseif ($point < 1000000000) return ['rank'=>850, 'name'=>'🦁악어', 'add'=>4, 'sell'=>300];
    elseif ($point < 3000000000) return ['rank'=>950, 'name'=>'🌟고래', 'add'=>4, 'sell'=>450];
    elseif ($point < 5000000000) return ['rank'=>1050, 'name'=>'⚔️상어', 'add'=>5, 'sell'=>450];
    elseif ($point < 10000000000) return ['rank'=>1175, 'name'=>'🎋돌고', 'add'=>5, 'sell'=>450];
    elseif ($point < 30000000000) return ['rank'=>1300, 'name'=>'🔯펭귄', 'add'=>5, 'sell'=>450];
    elseif ($point < 50000000000) return ['rank'=>1450, 'name'=>'👸문어', 'add'=>5, 'sell'=>450];
    elseif ($point < 90000000000) return ['rank'=>1625, 'name'=>'🐉오징', 'add'=>5, 'sell'=>500];
    elseif ($point < 200000000000) return ['rank'=>1825, 'name'=>'👑낙지', 'add'=>6, 'sell'=>500];
    elseif ($point < 300000000000) return ['rank'=>2050, 'name'=>'🌟참치', 'add'=>6, 'sell'=>500];
    elseif ($point < 500000000000) return ['rank'=>2300, 'name'=>'🔱전복', 'add'=>6, 'sell'=>500];
    else return ['rank'=>2500, 'name'=>'👑해삼', 'add'=>8, 'sell'=>1000];
}


function 아이템보유유무($기존닉, $아이템명){
  $sql = "select * from tb_member_item where nick = '{$기존닉}' and itemname = '{$아이템명}' and status = 0 ";
  $아이템 = db_select($sql);
  return $아이템;
}

/**
 * .프변 [N] — 프로필변경 1개당 3일. N개 일괄 사용.
 */
function 프변_명령_처리($두자리닉넴, $개수 = 1) {
  $개수 = max(1, (int)$개수);
  $닉_esc = addslashes(trim((string)$두자리닉넴));
  if ($닉_esc === '') {
    echo 전송('❌ 닉네임을 확인해주세요.');
    exit;
  }

  $보유행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE nick = '{$닉_esc}' AND itemname = '프변' AND status = 0");
  $보유개수 = (int)($보유행['cnt'] ?? 0);
  if ($보유개수 < $개수) {
    echo 전송("❌ 프변 아이템 부족! (요청 {$개수}개 · 보유 {$보유개수}개)");
    exit;
  }

  $아이템rs = db_query("SELECT idx FROM tb_member_item WHERE nick = '{$닉_esc}' AND itemname = '프변' AND status = 0 ORDER BY idx ASC LIMIT {$개수}");
  $idx목록 = [];
  while ($row = db_fetch($아이템rs)) {
    $idx목록[] = (int)$row['idx'];
  }
  if (count($idx목록) < $개수) {
    echo 전송('❌ 프변 아이템 부족!');
    exit;
  }

  $연장일 = $개수 * 3;
  $사용중여부 = db_select("SELECT idx, enddate FROM tb_item_use WHERE nickname = '{$닉_esc}' AND item = '프로필변경' LIMIT 1");
  $연장중 = !empty($사용중여부['idx']);

  if ($연장중) {
    $유효시간 = date('Y-m-d H:i:s', strtotime($사용중여부['enddate'] . " +{$연장일} days"));
    db_query("UPDATE tb_item_use SET enddate = '{$유효시간}' WHERE idx = " . (int)$사용중여부['idx']);
  } else {
    $유효시간 = date('Y-m-d H:i:s', strtotime("+{$연장일} days"));
    db_query("INSERT INTO tb_item_use SET nickname = '{$닉_esc}', item = '프로필변경', enddate = '{$유효시간}', regdate = NOW()");
  }

  $인절 = implode(',', $idx목록);
  db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx IN ({$인절})");
  if (function_exists('아이템사용_시세하락')) {
    아이템사용_시세하락('프변', $개수);
  }

  $만료표시 = date('m-d H:i', strtotime($유효시간));
  if ($연장중) {
    if ($개수 > 1) {
      return "✅ 프로필변경 {$연장일}일 연장! (프변 {$개수}개)\n만료: {$만료표시}";
    }
    return "✅ 프로필변경 3일 연장!\n만료: {$만료표시}";
  }
  if ($개수 > 1) {
    return "✅ 프로필변경 적용! (프변 {$개수}개 · {$연장일}일)\n만료: {$만료표시}";
  }
  return "✅ 프로필변경 적용! (3일)\n만료: {$만료표시}";
}

function 버프_관리자_여부(string $두자리닉넴, array $관리자 = []): bool {
  return $두자리닉넴 !== '' && in_array($두자리닉넴, $관리자, true);
}

function 버프_관리자_목록_문구(string $마법라벨 = '마법(타수3배)'): string {
  $sql = "SELECT * FROM tb_item_use WHERE item = '프로필변경' ORDER BY enddate ASC";
  $result = db_query($sql);
  $itemctn = mysqli_num_rows($result);

  $html = "✅ 프로필변경                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";

  if ($itemctn > 0) {
    while ($row = db_fetch($result)) {
      $now = new DateTime();
      $end = new DateTime($row['enddate']);
      $diffHours = ($end->getTimestamp() - $now->getTimestamp()) / 3600;
      $imminent = ($diffHours <= 10 && $diffHours > 0) ? "⚠️" : "";
      $html .= $imminent . $row['nickname'] . " " . date('m월 d일 H시', strtotime($row['enddate'])) . "<br>";
    }
  }

  $sql = "SELECT * FROM tb_item_use WHERE item IN ('마법') ORDER BY enddate ASC";
  $result3 = db_query($sql);
  $itemctn3 = mysqli_num_rows($result3);

  if ($itemctn3 > 0) {
    $html .= "\n✅ {$마법라벨}\n";
    while ($row3 = db_fetch($result3)) {
      $now1 = new DateTime();
      $end1 = new DateTime($row3['enddate']);
      $diffHours1 = ($end1->getTimestamp() - $now1->getTimestamp()) / 3600;
      $imminent1 = ($diffHours1 <= 1 && $diffHours1 > 0) ? "⚠️" : "";
      $enddate = date("d일 H:i", strtotime($row3['enddate']));
      $html .= $imminent1 . " " . $row3['nickname'] . $enddate . "<br>";
    }
  }

  $sql = "SELECT * FROM tb_item_use WHERE item IN ('지호') ORDER BY enddate ASC";
  $result1 = db_query($sql);
  $itemctn1 = mysqli_num_rows($result1);

  if ($itemctn1 > 0) {
    $html .= "\n✅ 지호(타수2배 · 추가양도=남은시간)\n";
    while ($row1 = db_fetch($result1)) {
      $now1 = new DateTime();
      $end1 = new DateTime($row1['enddate']);
      $diffHours1 = ($end1->getTimestamp() - $now1->getTimestamp()) / 3600;
      if ($diffHours1 <= 0) {
        continue;
      }
      $imminent1 = ($diffHours1 <= 1) ? "⚠️" : "";
      $enddate = date("d일 H:i", strtotime($row1['enddate']));
      $html .= $imminent1 . " " . $row1['nickname'] . $enddate . "<br>";
    }
  }

  return $html;
}

function 버프_개인_항목_상태(string $닉, string $item, callable $만료표시): string {
  $닉_esc = addslashes($닉);
  $item_esc = addslashes($item);
  $row = db_select("SELECT enddate FROM tb_item_use WHERE nickname = '{$닉_esc}' AND item = '{$item_esc}' AND enddate > NOW() ORDER BY enddate DESC LIMIT 1");
  if (empty($row['enddate'])) {
    return '미적용';
  }
  return '적용 중 · ' . $만료표시($row['enddate']) . ' 까지';
}

function 버프_개인_목록_문구(string $닉): string {
  $lines = [
    '✅ 내 버프',
    '',
    '프로필변경: ' . 버프_개인_항목_상태($닉, '프로필변경', function ($end) {
      return date('m월 d일 H시', strtotime($end));
    }),
    '지호: ' . 버프_개인_항목_상태($닉, '지호', function ($end) {
      return date('d일 H:i', strtotime($end));
    }),
    '마법: ' . 버프_개인_항목_상태($닉, '마법', function ($end) {
      return date('d일 H:i', strtotime($end));
    }),
  ];
  return implode("\n", $lines);
}

function 버프_명령_응답(string $두자리닉넴, array $관리자 = [], array $opts = []): string {
  $마법라벨 = $opts['마법라벨'] ?? '마법(타수3배)';
  if (버프_관리자_여부($두자리닉넴, $관리자)) {
    return 버프_관리자_목록_문구($마법라벨);
  }
  if ($두자리닉넴 === '') {
    return '❌ 닉네임을 확인할 수 없어요.';
  }
  return 버프_개인_목록_문구($두자리닉넴);
}


function 앞에서3자리반올림($num, $자리) {
    if ($num < 100) return $num; // 3자리 미만은 그대로

    $len = strlen((string)$num);     // 전체 자릿수
    $pow = $len - $자리;                 // 버릴 자릿수

    return round($num / pow(10, $pow)) * pow(10, $pow);
}

function 나의입방일($입방일){
  $joined = new DateTime($입방일);  // 가입일
  $now = new DateTime();                         // 현재

  $diff = $now->getTimestamp() - $joined->getTimestamp();

  $days = floor($diff / 86400);           // 하루 = 86400초
  return $days;
}

function 콤마삽입($금액){
  if (function_exists('냥_정수문자열')) {
    $digits = 냥_정수문자열($금액);
    return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $digits);
  }
  return number_format((float)$금액);
}

/**
 * 냥 금액 문자열 → 정수 (숫자만 / 만·억·조·경·천·백 단위)
 * 예) 5000000, 5000만, 100억, 5조, 2경, 5천조, 1조5000억, 1조1천1백, 5조냥
 * PHP_INT_MAX 초과·연산 오버플로 시 0 (파싱 실패)
 * @return int 파싱 실패 시 0
 */
function 냥_금액_파싱($text) {
  $text = trim((string)$text);
  if ($text === '') {
    return 0;
  }
  $text = str_replace([',', ' ', '냥', '원'], '', $text);
  if ($text === '') {
    return 0;
  }
  $intMaxStr = (string)PHP_INT_MAX;
  if (preg_match('/^\d+$/', $text)) {
    if (strlen($text) > strlen($intMaxStr)
      || (strlen($text) === strlen($intMaxStr) && $text > $intMaxStr)) {
      return 0;
    }
    return (int)$text;
  }
  static $units = null;
  if ($units === null) {
    $units = [
      '천조' => '1000000000000000',
      '경' => '10000000000000000',
      '조' => '1000000000000',
      '천억' => '100000000000',
      '백억' => '10000000000',
      '십억' => '1000000000',
      '억' => '100000000',
      '천만' => '10000000',
      '백만' => '1000000',
      '만' => '10000',
      '천' => '100000000000',
      '백' => '10000000000',
    ];
  }
  $unitKeys = array_keys($units);
  usort($unitKeys, function ($a, $b) {
    return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
  });
  $unitPattern = implode('|', array_map(function ($u) {
    return preg_quote($u, '/');
  }, $unitKeys));
  if (!preg_match_all('/(\d+)(' . $unitPattern . ')?/u', $text, $matches, PREG_SET_ORDER)) {
    return 0;
  }
  $consumed = '';
  $useBc = function_exists('bcadd') && function_exists('bccomp') && function_exists('bcmul');
  $total = $useBc ? '0' : 0;
  foreach ($matches as $m) {
    $consumed .= $m[0];
    $n = (int)$m[1];
    $unit = $m[2] ?? '';
    if ($unit !== '' && isset($units[$unit])) {
      if ($useBc) {
        $part = bcmul((string)$n, $units[$unit], 0);
        $total = bcadd($total, $part, 0);
      } else {
        $unitInt = (int)$units[$unit];
        if ($n > 0 && $unitInt > intdiv(PHP_INT_MAX, $n)) {
          return 0;
        }
        $total += $n * $unitInt;
      }
    } else {
      if ($useBc) {
        $total = bcadd($total, (string)$n, 0);
      } else {
        if ($total > PHP_INT_MAX - $n) {
          return 0;
        }
        $total += $n;
      }
    }
  }
  if ($consumed !== $text) {
    return 0;
  }
  if ($useBc) {
    if (bccomp($total, '0', 0) <= 0 || bccomp($total, $intMaxStr, 0) > 0) {
      return 0;
    }
    return (int)$total;
  }
  if ($total <= 0 || $total > PHP_INT_MAX) {
    return 0;
  }
  return (int)$total;
}

/** 큰 냥 금액 비교 — a < b 이면 true (bcmath 우선) */
if (!function_exists('냥_정수_미만')) {
  function 냥_정수_미만($a, $b): bool {
    $sa = preg_replace('/[^\d-]/', '', (string)$a);
    $sb = preg_replace('/[^\d-]/', '', (string)$b);
    if ($sa === '' || $sb === '') {
      return (float)$a < (float)$b;
    }
    if (function_exists('bccomp')) {
      return bccomp($sa, $sb, 0) < 0;
    }
    return (float)$sa < (float)$sb;
  }
}

/**
 * .모금 금액·보유냥 검증
 * @return array{ok:bool, 금액?:int, msg?:string}
 */
if (!function_exists('모금_입력검증')) {
  function 모금_입력검증(string $금액텍스트, $보유냥, int $최소모금): array {
    $금액 = function_exists('냥_금액_파싱') ? (int)냥_금액_파싱($금액텍스트) : (int)$금액텍스트;
    if ($금액 <= 0) {
      return [
        'ok' => false,
        'msg' => "❌ 금액 형식을 확인해주세요.\n예) .모금 1조 / .모금 2천2백억 / .모금 진우 3천억\n(처리 가능 최대 약 922경)",
      ];
    }
    if ($금액 < $최소모금) {
      $최소표시 = function_exists('냥축약표시') ? 냥축약표시($최소모금) : number_format($최소모금) . '냥';
      return ['ok' => false, 'msg' => "모금은 최소 {$최소표시} 이상 가능!"];
    }
    if (냥_정수_미만($보유냥, $금액)) {
      $보유표시 = function_exists('냥축약표시') ? 냥축약표시($보유냥) : number_format((float)$보유냥) . '냥';
      $요청표시 = function_exists('냥축약표시') ? 냥축약표시($금액) : number_format($금액) . '냥';
      return ['ok' => false, 'msg' => "냥 부족! 보유 {$보유표시} · 요청 {$요청표시}"];
    }
    return ['ok' => true, '금액' => $금액];
  }
}

/**
 * 냥 금액 문자열 → 정수 (앞 -/+ 부호 + 만·억·조·경·천·백 축약)
 * 예) 1조, 1경, 1조5천, -1조, +100억
 * @return int 파싱 실패 시 0
 */
function 냥_금액_파싱_부호포함($text) {
  $text = trim((string)$text);
  if ($text === '') {
    return 0;
  }
  $음수 = false;
  if ($text[0] === '-') {
    $음수 = true;
    $text = ltrim(substr($text, 1));
  } elseif ($text[0] === '+') {
    $text = ltrim(substr($text, 1));
  }
  if ($text === '') {
    return 0;
  }
  $금액 = 냥_금액_파싱($text);
  if ($금액 <= 0) {
    return 0;
  }
  return $음수 ? -$금액 : $금액;
}

/** `.선물`로 양도 가능한 아이템명 — 2글자 상점템 + 일방신청권·일방연장권 */
if (!function_exists('선물_가능_아이템명')) {
  function 선물_가능_아이템명(string $itemName): bool {
    $itemName = trim($itemName);
    if ($itemName === '') {
      return false;
    }
    static $장문허용 = ['일방신청권', '일방연장권'];
    if (in_array($itemName, $장문허용, true)) {
      return true;
    }
    return mb_strlen($itemName, 'UTF-8') === 2;
  }
}

/**
 * .모금 명령 파싱 — 닉(선택) + 금액(숫자·만·억·조·천·백)
 * @return array{nick: ?string, amount_text: string}
 */
function 모금_명령_파싱($status) {
  $trim = trim((string)$status);
  if (preg_match('/^\.모금\s+(\S+)\s+(\S+)\s*$/u', $trim, $m)) {
    $nick = function_exists('getTwoCharNick') ? getTwoCharNick($m[1]) : '';
    if ($nick === '') {
      $nick = trim($m[1]);
    }
    return ['nick' => $nick, 'amount_text' => trim($m[2])];
  }
  if (preg_match('/^\.모금\s+(\S+)\s*$/u', $trim, $m)) {
    return ['nick' => null, 'amount_text' => trim($m[1])];
  }
  return ['nick' => null, 'amount_text' => ''];
}

/** config 야바위 역대 최고점수 캐시 → 표시용 배열 */
if (!function_exists('야바위_최고점수_캐시_읽기')) {
  function 야바위_최고점수_캐시_읽기($설정 = null) {
    if ($설정 === null) {
      $설정 = db_select('SELECT 야바위최고점수, 야바위최고점수닉, 야바위최고점수상태, 야바위최고점수일시 FROM config LIMIT 1');
    }
    return [
      '점수' => (int)($설정['야바위최고점수'] ?? 0),
      '닉' => trim((string)($설정['야바위최고점수닉'] ?? '')),
      '상태' => trim((string)($설정['야바위최고점수상태'] ?? '')),
      '일시' => trim((string)($설정['야바위최고점수일시'] ?? '')),
    ];
  }
}

/** 야바위 결과 메시지용 역대 최고점수 블록 */
if (!function_exists('야바위_최고점수_표시문구')) {
  function 야바위_최고점수_표시문구(array $캐시) {
    $점수 = (int)($캐시['점수'] ?? 0);
    if ($점수 <= 0) {
      return '';
    }
    $상태 = trim((string)($캐시['상태'] ?? ''));
    if ($상태 === '') {
      $상태 = "주사위{$점수}점";
    }
    $닉 = trim((string)($캐시['닉'] ?? ''));
    $일시 = trim((string)($캐시['일시'] ?? ''));
    $날자 = ($일시 !== '' && $일시 !== '0000-00-00 00:00:00')
      ? date('y-m-d', strtotime($일시))
      : '';
    $msg = "\n\n-현재 최고점수자";
    $msg .= "\n{$상태}";
    if ($닉 !== '') {
      $msg .= "\n{$닉}" . ($날자 !== '' ? " {$날자}" : '');
    }
    return $msg;
  }
}

/** 이번 게임이 역대 최고면 캐시 저장 (기존 이하이면 무시) */
if (!function_exists('야바위_최고점수_캐시_갱신')) {
  function 야바위_최고점수_캐시_갱신($점수, $닉, $상태) {
    $점수 = (int)$점수;
    if ($점수 <= 0 || trim((string)$닉) === '') {
      return false;
    }
    $현재 = 야바위_최고점수_캐시_읽기();
    if ($점수 <= (int)($현재['점수'] ?? 0)) {
      return false;
    }
    $닉_esc = addslashes(trim((string)$닉));
    $상태 = trim((string)$상태);
    if ($상태 === '') {
      $상태 = "주사위{$점수}점";
    }
    $상태_esc = addslashes($상태);
    db_query("
      UPDATE config SET
        야바위최고점수 = {$점수},
        야바위최고점수닉 = '{$닉_esc}',
        야바위최고점수상태 = '{$상태_esc}',
        야바위최고점수일시 = NOW()
    ");
    return true;
  }
}

/**
 * 캐시가 비어 있을 때 tb_point_log에서 1회만 역대 최고 시드 (백그라운드용)
 */
if (!function_exists('야바위_최고점수_캐시_없으면_시드')) {
  function 야바위_최고점수_캐시_없으면_시드() {
    $현재 = 야바위_최고점수_캐시_읽기();
    if ((int)($현재['점수'] ?? 0) > 0) {
      return;
    }
    $역대 = db_select("
      SELECT status, nick, regdate,
        CAST(REGEXP_SUBSTR(status, '[0-9]+(?=점)') AS UNSIGNED) AS dice_score
      FROM tb_point_log
      WHERE (status LIKE '주사위%점%' OR status LIKE '주사위%우승%')
        AND REGEXP_SUBSTR(status, '[0-9]+(?=점)') IS NOT NULL
        AND CAST(REGEXP_SUBSTR(status, '[0-9]+(?=점)') AS UNSIGNED) = (
          SELECT MAX(CAST(REGEXP_SUBSTR(status, '[0-9]+(?=점)') AS UNSIGNED))
          FROM tb_point_log
          WHERE (status LIKE '주사위%점%' OR status LIKE '주사위%우승%')
            AND REGEXP_SUBSTR(status, '[0-9]+(?=점)') IS NOT NULL
        )
      ORDER BY regdate DESC
      LIMIT 1
    ");
    if (!$역대 || (int)($역대['dice_score'] ?? 0) <= 0) {
      return;
    }
    $닉_esc = addslashes(trim((string)($역대['nick'] ?? '')));
    $상태_esc = addslashes(trim((string)($역대['status'] ?? '')));
    $점수 = (int)$역대['dice_score'];
    $일시 = trim((string)($역대['regdate'] ?? ''));
    $일시_sql = ($일시 !== '' && $일시 !== '0000-00-00 00:00:00')
      ? "'" . addslashes($일시) . "'"
      : 'NOW()';
    db_query("
      UPDATE config SET
        야바위최고점수 = {$점수},
        야바위최고점수닉 = '{$닉_esc}',
        야바위최고점수상태 = '{$상태_esc}',
        야바위최고점수일시 = {$일시_sql}
      WHERE 야바위최고점수 = 0 OR 야바위최고점수 IS NULL
    ");
  }
}

/** tb_member.newpoint 표시 (소수점 제외) */
function newpoint표시($금액) {
  $v = (int)floor((float)$금액);
  return number_format($v, 0, '.', ',');
}

/** status=0 회원 newpoint 합계 — 시세 스냅샷 기준 */
function 전체보유newpoint합계() {
  return 시세기준_본방냥();
}

/** 전체 newpoint 비율 계산 (소수점 버림) */
function newpoint비율계산($비율) {
  return (int)floor(전체보유newpoint합계() * $비율);
}

/** newpoint 중 양도 가능 정수 냥 (소수점 제외) */
function newpoint양도가능($금액) {
  return (int)floor((float)$금액);
}

/** 주급 지급 실행 (.보조금지급 등에서 사용) — ['ok'=>bool, 'msg'=>string] 반환 */
function 주급지급_실행() {
  $설정 = db_select("SELECT 주급지급날짜 FROM config LIMIT 1");
  $다음주급일 = isset($설정['주급지급날짜']) ? trim($설정['주급지급날짜']) : null;

  $오늘 = date("Y-m-d");
  $지급가능 = false;
  if ($다음주급일 === null || $다음주급일 === '') {
    $지급가능 = true;
  } else {
    $지급가능 = (strtotime($오늘) >= strtotime($다음주급일));
  }

  if (!$지급가능) {
    $남은일 = $다음주급일 ? max(0, (strtotime($다음주급일) - strtotime($오늘)) / 86400) : 0;
    $전체보유 = (float)전체보유newpoint합계();
    $예상주급 = (int)floor($전체보유 * 0.005);
    $예상주급문구 = "현재 전체 보유 냥 합계 기준 주급(0.5%): " . newpoint표시($예상주급) . "냥";
    return [
      'ok' => false,
      'msg' => "❌ 아직 주급일이 아닙니다.\n다음 주급일: {$다음주급일} (" . (int)$남은일 . "일 남음)\n{$예상주급문구}",
    ];
  }

  $다음날짜 = date("Y-m-d", strtotime("+7 days"));
  db_query("UPDATE config SET 주급지급날짜 = '{$다음날짜}'");
  $전체보유 = (float)전체보유newpoint합계();
  $주급금액 = (int)floor($전체보유 * 0.005);
  if ($주급금액 <= 0) {
    return [
      'ok' => false,
      'msg' => "❌ 전체 보유 냥이 부족하여 주급(0.5%)을 계산할 수 없습니다.\n다음 주급일: {$다음날짜}",
    ];
  }

  $admin_result = db_query("SELECT name FROM tb_member WHERE admin = 1");
  $지급인원 = 0;
  $총지급 = 0.0;
  while ($row = db_fetch($admin_result)) {
    $name_esc = addslashes(trim($row['name'] ?? ''));
    if ($name_esc === '') continue;
    db_query("UPDATE tb_member SET newpoint = newpoint + {$주급금액} WHERE name = '{$name_esc}'");
    $지급인원++;
    $총지급 += $주급금액;
  }
  return [
    'ok' => true,
    'msg' => "✅ 주급 지급완료\n전체 보유 냥 총합의 0.5%를 관리자 1인당 주급으로 지급했습니다.\n다음 주급일: {$다음날짜}\n관리자 {$지급인원}명 × " . newpoint표시($주급금액) . "냥 = 총 " . newpoint표시($총지급) . "냥 지급",
  ];
}

/** 보급 지급 실행 (.보조금지급 등에서 사용) — ['ok'=>bool, 'msg'=>string] 반환 */
function 보급지급_실행() {
  $설정 = db_select("SELECT 보급지급날짜 FROM config LIMIT 1");
  $다음보급일 = isset($설정['보급지급날짜']) ? trim($설정['보급지급날짜']) : null;
  $오늘 = date("Y-m-d");
  $지급가능 = false;
  if ($다음보급일 === null || $다음보급일 === '') {
    $지급가능 = true;
  } else {
    $지급가능 = (strtotime($오늘) >= strtotime($다음보급일));
  }
  if (!$지급가능) {
    $남은일 = $다음보급일 ? max(0, (strtotime($다음보급일) - strtotime($오늘)) / 86400) : 0;
    $전체보유 = (float)전체보유newpoint합계();
    $예상보급1인 = (int)floor($전체보유 * 0.005);
    $예상문구 = "현재 전체 보유 냥 합계 기준 보급(0.5%): 1인당 " . newpoint표시($예상보급1인) . "냥";
    return [
      'ok' => false,
      'msg' => "❌ 아직 보급일이 아닙니다.\n다음 보급일: {$다음보급일} (" . (int)$남은일 . "일 남음)\n{$예상문구}",
    ];
  }

  $전체보유 = (float)전체보유newpoint합계();
  $보급1인금액 = (int)floor($전체보유 * 0.005);
  if ($보급1인금액 <= 0) {
    $다음날짜 = date("Y-m-d", strtotime("+7 days"));
    db_query("UPDATE config SET 보급지급날짜 = '{$다음날짜}'");
    return [
      'ok' => false,
      'msg' => "❌ 전체 보유 냥이 부족하여 보급(0.5%)을 계산할 수 없습니다.\n다음 보급일: {$다음날짜}",
    ];
  }

  $멤버목록 = db_query("
    SELECT idx, name
    FROM tb_member
    WHERE status = 0
      AND NOT (couple = 2 AND TIMESTAMPDIFF(HOUR, regdate, NOW()) < 24)
    ORDER BY name
  ");
  $지급인원 = 0;
  while ($멤버 = db_fetch($멤버목록)) {
    $지급인원++;
  }
  if ($지급인원 <= 0) {
    $다음날짜 = date("Y-m-d", strtotime("+7 days"));
    db_query("UPDATE config SET 보급지급날짜 = '{$다음날짜}'");
    return [
      'ok' => false,
      'msg' => "❌ 보급 대상 회원이 없습니다.\n다음 보급일: {$다음날짜}",
    ];
  }

  $멤버목록 = db_query("
    SELECT idx, name
    FROM tb_member
    WHERE status = 0
      AND NOT (couple = 2 AND TIMESTAMPDIFF(HOUR, regdate, NOW()) < 24)
    ORDER BY name
  ");
  $총지급 = 0.0;
  while ($멤버 = db_fetch($멤버목록)) {
    $닉_esc = addslashes(trim($멤버['name'] ?? ''));
    if ($닉_esc === '') continue;
    db_query("UPDATE tb_member SET newpoint = newpoint + {$보급1인금액} WHERE name = '{$닉_esc}'");
    $총지급 += $보급1인금액;
  }

  $다음날짜 = date("Y-m-d", strtotime("+7 days"));
  db_query("UPDATE config SET 보급지급날짜 = '{$다음날짜}'");

  return [
    'ok' => true,
    'msg' => "✅ 보급 지급완료\n전체 tb_member 보유 냥 총합의 0.5%를 회원 1인당 그대로 지급했습니다.(퇴근,신입 제외)\n다음 보급일: {$다음날짜}\n대상 {$지급인원}명 × " . newpoint표시($보급1인금액) . "냥 = 총 " . newpoint표시($총지급) . "냥 지급",
  ];
}

/** 천억냥 = 현금 1,000원 기준 환전 시뮬 (원화 숫자) */
function 환전현금원($냥) {
  $냥 = max(0, (float)$냥);
  return $냥 * 1000 / 100000000000;
}

/** 환전 시뮬 원화 표시 문자열 */
function 환전현금표시($냥) {
  $원 = 환전현금원($냥);
  if ($원 <= 0) {
    return '0원';
  }
  if ($원 >= 1) {
    return number_format(floor($원)) . '원';
  }
  return rtrim(rtrim(number_format($원, 2), '0'), '.') . '원';
}

/** 냥 금액을 짧게 표시: 10만 이상은 만 단위(10만냥, 33만냥, 100만냥, 1234만냥) */
function 냥_짧게($금액) {
  $금액 = (int)$금액;
  if ($금액 >= 100000) {
    return floor($금액 / 10000) . '만냥';
  }
  return number_format($금액) . "냥";
}

/**
 * 냥 경·조·억 분해 (억 미만 절사) — bcmath 우선, 대수 정밀도 한계 회피
 * @return array{0:int|string,1:int|string,2:int|string} [경, 조, 억] — 922경 초과 시 문자열
 */
if (!function_exists('냥_경조억_파트')) {
  function 냥_경조억_파트($금액) {
    $경단위 = '10000000000000000';
    $조단위 = '1000000000000';
    $억단위 = '100000000';

    $digits = function_exists('냥_정수문자열')
      ? 냥_정수문자열($금액)
      : (ltrim(preg_replace('/[^\d]/', '', (string)$금액), '0') ?: '0');

    $toPart = function ($s) {
      if ($s === '0' || $s === '') {
        return 0;
      }
      if (function_exists('bccomp') && bccomp($s, (string)PHP_INT_MAX, 0) > 0) {
        return $s;
      }
      if (strlen($s) > strlen((string)PHP_INT_MAX)
        || (strlen($s) === strlen((string)PHP_INT_MAX) && $s > (string)PHP_INT_MAX)) {
        return $s;
      }
      return (int)$s;
    };

    if (function_exists('bccomp')) {
      if (bccomp($digits, $억단위) < 0) {
        return [0, 0, 0];
      }
      $경수 = $toPart(bcdiv($digits, $경단위, 0));
      $rest = bcmod($digits, $경단위);
      $조수 = $toPart(bcdiv($rest, $조단위, 0));
      $rest = bcmod($rest, $조단위);
      $억수 = $toPart(bcdiv($rest, $억단위, 0));
      return [$경수, $조수, $억수];
    }

    // bcmath 없을 때: 문자열 나눗셈 근사 (float 금지 — 1000경+ 깨짐)
    $len = strlen($digits);
    $경수 = 0;
    $조수 = 0;
    $억수 = 0;
    if ($len > 16) {
      $경수 = (int)substr($digits, 0, $len - 16);
      $digits = ltrim(substr($digits, $len - 16), '0') ?: '0';
      $len = strlen($digits);
    } elseif ($len === 16) {
      // 1경 단위 경계
    }
    if (strlen($digits) > 12 || (strlen($digits) === 12 && $digits >= '1000000000000')) {
      $조수 = (int)substr($digits, 0, max(0, strlen($digits) - 12));
      $digits = ltrim(substr($digits, max(0, strlen($digits) - 12)), '0') ?: '0';
    }
    if (strlen($digits) >= 8) {
      $억수 = (int)substr($digits, 0, max(0, strlen($digits) - 8));
    }
    return [$경수, $조수, $억수];
  }
}

/** 큰 정수 콤마 표시 (int/string 공통) */
if (!function_exists('냥_숫자콤마')) {
  function 냥_숫자콤마($n): string {
    $d = function_exists('냥_정수문자열') ? 냥_정수문자열($n) : preg_replace('/[^\d]/', '', (string)$n);
    $d = ltrim((string)$d, '0') ?: '0';
    return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $d);
  }
}

/**
 * 경·조·억 3단 축약 — 1경 이상: 경+조(억 절사), 1조 이상: 조만, 1조 미만: 억만
 * 예) 10,785조 → 1경 785조 / 785조 → 785조 / 50억 → 50억
 */
if (!function_exists('냥_경조_축약표시')) {
  function 냥_경조_축약표시($금액, $단위 = '') {
    $경단위 = '10000000000000000';
    $digits = function_exists('냥_정수문자열')
      ? 냥_정수문자열($금액)
      : (ltrim(preg_replace('/[^\d]/', '', (string)$금액), '0') ?: '0');
    $접미 = ($단위 !== '') ? (string)$단위 : '';

    $경이상 = function_exists('bccomp')
      ? bccomp($digits, $경단위) >= 0
      : (strlen($digits) > 16 || (strlen($digits) === 16 && $digits >= $경단위));

    if ($경이상) {
      [$경수, $조수] = 냥_경조억_파트($digits);
      $parts = [];
      if ((function_exists('냥_정수문자열') ? 냥_정수문자열($경수) : (string)$경수) !== '0') {
        $parts[] = 냥_숫자콤마($경수) . '경';
      }
      if ((function_exists('냥_정수문자열') ? 냥_정수문자열($조수) : (string)$조수) !== '0') {
        $parts[] = 냥_숫자콤마($조수) . '조';
      }
      return ($parts ? implode(' ', $parts) : '0') . $접미;
    }

    return 냥_조억_축약표시($digits, $단위);
  }
}

/**
 * 조·억 2단 축약 — 1조 이상: 조만(조 미만 절사), 1조 미만: 억만(억 미만 절사)
 * 예) 74,124,508,384,996,656 → 74,124조 / 999,999,999,999 → 9,999억
 */
if (!function_exists('냥_조억_축약표시')) {
  function 냥_조억_축약표시($금액, $단위 = '') {
    $조단위 = '1000000000000';
    $억단위 = '100000000';
    $digits = function_exists('냥_정수문자열')
      ? 냥_정수문자열($금액)
      : (ltrim(preg_replace('/[^\d]/', '', (string)$금액), '0') ?: '0');
    $접미 = ($단위 !== '') ? (string)$단위 : '';

    if (function_exists('bccomp')) {
      if (bccomp($digits, $조단위) >= 0) {
        $조수 = bcdiv($digits, $조단위, 0);
        return 냥_숫자콤마($조수) . '조' . $접미;
      }
      if (bccomp($digits, $억단위) >= 0) {
        $억수 = bcdiv($digits, $억단위, 0);
        return 냥_숫자콤마($억수) . '억' . $접미;
      }
      return '0' . $접미;
    }

    if (strlen($digits) > 12 || (strlen($digits) === 12 && $digits >= $조단위)) {
      $조수 = substr($digits, 0, strlen($digits) - 12);
      return 냥_숫자콤마($조수) . '조' . $접미;
    }
    if (strlen($digits) >= 8) {
      $억수 = substr($digits, 0, strlen($digits) - 8) ?: '0';
      if ($억수 !== '0') {
        return 냥_숫자콤마($억수) . '억' . $접미;
      }
    }
    return '0' . $접미;
  }
}

/**
 * 냥 경·조·억 축약 문구
 * @param string $구분자 파트 사이 구분 (기본 붙여쓰기, ' ' 등)
 */
if (!function_exists('냥_경조억_축약문구')) {
  function 냥_경조억_축약문구($금액, $단위 = '냥', $구분자 = '') {
    [$경수, $조수, $억수] = 냥_경조억_파트($금액);
    $경s = 냥_정수문자열($경수);
    $조s = 냥_정수문자열($조수);
    $억s = 냥_정수문자열($억수);
    if ($경s === '0' && $조s === '0' && $억s === '0') {
      return '0' . $단위;
    }
    $parts = [];
    if ($경s !== '0') {
      $parts[] = ($구분자 === '' ? 냥_숫자콤마($경s) : $경s) . '경';
    }
    if ($조s !== '0') {
      $parts[] = ($구분자 === '' ? 냥_숫자콤마($조s) : $조s) . '조';
    }
    if ($억s !== '0') {
      $parts[] = ($구분자 === '' ? 냥_숫자콤마($억s) : $억s) . '억';
    }
    return implode($구분자, $parts) . $단위;
  }
}

/**
 * 구매가 축약 표시 — 경·조·억만 표시, 억 미만 자릿수는 0 처리
 * 예) 123,456,789 → 1억 / 99,999,999 → 0냥 / 1,234,567,890,123 → 1조2,345억
 *     20,301,778,600,000,000 → 2경301조7,786억냥
 */
if (!function_exists('구매가_축약표시')) {
  function 구매가_축약표시($금액, $단위 = '냥') {
    return 냥_경조억_축약문구($금액, $단위, '');
  }
}

/** 랭킹용 게임냥 표시 — 1조↑ 경+조(냥 생략), 1조↓ 억+단위, 1억↓ 전체숫자+단위 */
if (!function_exists('랭킹_게임냥표시')) {
  function 랭킹_게임냥표시($금액, $단위 = '냥') {
    $조단위 = '1000000000000';
    $억단위 = '100000000';
    $digits = function_exists('냥_정수문자열')
      ? 냥_정수문자열($금액)
      : (ltrim(preg_replace('/[^\d]/', '', (string)$금액), '0') ?: '0');
    $접미 = (string)$단위;

    if (function_exists('bccomp')) {
      if (bccomp($digits, $조단위) >= 0) {
        [$경수, $조수] = 냥_경조억_파트($digits);
        $parts = [];
        $경s = 냥_정수문자열($경수);
        $조s = 냥_정수문자열($조수);
        if ($경s !== '0') {
          $parts[] = 냥_숫자콤마($경s) . '경';
        }
        if ($조s !== '0') {
          $parts[] = 냥_숫자콤마($조s) . '조';
        }
        return $parts ? implode('', $parts) : '0';
      }
      if (bccomp($digits, $억단위) >= 0) {
        return 냥_숫자콤마(bcdiv($digits, $억단위, 0)) . '억' . $접미;
      }
      return 냥_숫자콤마($digits) . $접미;
    }

    if (strlen($digits) > 12 || (strlen($digits) === 12 && $digits >= $조단위)) {
      [$경수, $조수] = 냥_경조억_파트($digits);
      $parts = [];
      $경s = 냥_정수문자열($경수);
      $조s = 냥_정수문자열($조수);
      if ($경s !== '0') {
        $parts[] = 냥_숫자콤마($경s) . '경';
      }
      if ($조s !== '0') {
        $parts[] = 냥_숫자콤마($조s) . '조';
      }
      return $parts ? implode('', $parts) : '0';
    }
    if (strlen($digits) >= 8) {
      $억수 = substr($digits, 0, strlen($digits) - 8) ?: '0';
      if ($억수 !== '0') {
        return 냥_숫자콤마($억수) . '억' . $접미;
      }
    }
    return 냥_숫자콤마($digits) . $접미;
  }
}

/** 모금·자숙 등 게임냥 축약 표시 (예: 1조, 2,200억냥) */
if (!function_exists('냥축약표시')) {
  function 냥축약표시($금액, $단위접미 = null) {
    global $단위;
    $u = ($단위접미 !== null && $단위접미 !== '') ? $단위접미 : (isset($단위) ? (string)$단위 : '냥');
    if (function_exists('랭킹_게임냥표시')) {
      return 랭킹_게임냥표시($금액, $u);
    }
    if (function_exists('구매가_축약표시')) {
      return 구매가_축약표시($금액, $u);
    }
    return number_format((int)$금액) . $u;
  }
}

/** 야바위 금액 표시 (조·억 축약) */
if (!function_exists('레벨_타수기준_구간표')) {
  /** @return array<int, array{to:int, per:int}> */
  function 레벨_타수기준_구간표(): array {
    return [
      ['to' => 10, 'per' => 150],
      ['to' => 30, 'per' => 300],
      ['to' => 100, 'per' => 500],
      ['to' => 150, 'per' => 1000],
      ['to' => 200, 'per' => 2000],
      ['to' => 300, 'per' => 3000],
    ];
  }
}

if (!function_exists('레벨_타수기준_레벨계산')) {
  /** 누적 타수 → 레벨 (.레벨업 안내 구간, 최대 300) */
  function 레벨_타수기준_레벨계산(int $total_tasu): int {
    $remaining = max(0, $total_tasu);
    $level = 0;
    $from = 0;
    foreach (레벨_타수기준_구간표() as $band) {
      $span = (int)$band['to'] - $from;
      $per = (int)$band['per'];
      if ($span <= 0 || $per <= 0) {
        continue;
      }
      $need = $span * $per;
      if ($remaining >= $need) {
        $level = (int)$band['to'];
        $remaining -= $need;
        $from = (int)$band['to'];
        continue;
      }
      $level = $from + (int)floor($remaining / $per);
      return min(300, max(0, $level));
    }
    return 300;
  }
}

if (!function_exists('레벨업_본방냥_지급합계')) {
  /** 레벨 상승 시 지급 본방냥 — 도달한 레벨 숫자만큼 (Lv1→1냥, Lv2→2냥 …) */
  function 레벨업_본방냥_지급합계(int $이전레벨, int $새레벨): int {
    if ($새레벨 <= $이전레벨) {
      return 0;
    }
    $from = $이전레벨 + 1;
    $to = $새레벨;
    $n = $to - $from + 1;
    return (int)(($n * ($from + $to)) / 2);
  }
}

if (!function_exists('레벨업_오른레벨_문구')) {
  /** 오른 레벨만 나열 — 예) Lv 15 / Lv 14 · Lv 15 · Lv 16 */
  function 레벨업_오른레벨_문구(int $이전레벨, int $새레벨): string {
    if ($새레벨 <= $이전레벨) {
      return '';
    }
    $parts = [];
    for ($lv = $이전레벨 + 1; $lv <= $새레벨; $lv++) {
      $parts[] = "Lv {$lv}";
    }
    return implode(' · ', $parts);
  }
}

if (!function_exists('레벨업_보호_구간표')) {
  /** @return array<int, array{to:int, per:int}> */
  function 레벨업_보호_구간표(): array {
    return [
      ['to' => 10, 'per' => 10],
      ['to' => 30, 'per' => 20],
      ['to' => 100, 'per' => 30],
      ['to' => 150, 'per' => 40],
      ['to' => 200, 'per' => 50],
      ['to' => 300, 'per' => 60],
    ];
  }
}

if (!function_exists('레벨업_보호_레벨당')) {
  function 레벨업_보호_레벨당(int $level): int {
    if ($level <= 0) {
      return 0;
    }
    foreach (레벨업_보호_구간표() as $band) {
      if ($level <= (int)$band['to']) {
        return max(0, (int)$band['per']);
      }
    }
    return 60;
  }
}

if (!function_exists('레벨업_보호_지급합계')) {
  function 레벨업_보호_지급합계(int $이전레벨, int $새레벨): int {
    if ($새레벨 <= $이전레벨) {
      return 0;
    }
    $total = 0;
    for ($lv = $이전레벨 + 1; $lv <= $새레벨; $lv++) {
      $total += 레벨업_보호_레벨당($lv);
    }
    return $total;
  }
}

if (!function_exists('레벨업_보호_안내문구')) {
  function 레벨업_보호_안내문구(): string {
    return "🛡️ 보호 (레벨업 시 지급)\n"
      . "Lv 1 ~ 10   : 10\n"
      . "Lv 11 ~ 30  : 20\n"
      . "Lv 31 ~ 100 : 30\n"
      . "Lv 101 ~ 150: 40\n"
      . "Lv 151 ~ 200: 50\n"
      . "Lv 201 ~ 300: 60";
  }
}

if (!function_exists('레벨업_알림_문구')) {
  /** 예) 📈 소이 레벨업! Lv 15 +15💰 +50🎟️ +20🛡️ */
  function 레벨업_알림_문구(string $name, int $이전레벨, int $새레벨, int $지급냥, int $티켓지급 = 0, int $보호지급 = 0): string {
    $레벨 = 레벨업_오른레벨_문구($이전레벨, $새레벨);
    $멘트 = "📈 {$name} 레벨업! {$레벨}";
    if ($지급냥 > 0) {
      $멘트 .= " +{$지급냥}💰";
    }
    if ($티켓지급 > 0) {
      $멘트 .= " +{$티켓지급}🎟️";
    }
    if ($보호지급 > 0) {
      $멘트 .= " +{$보호지급}🛡️";
    }
    return $멘트;
  }
}

if (!function_exists('레벨_자동갱신_크론')) {
  /** tb_member.tasu(누적 버프 타수) 기준 level 동기화 + 레벨업 시 본방냥·로또티켓 지급 */
  function 레벨_자동갱신_크론(): void {
    if (file_exists(__DIR__ . '/game/lotto_ticket.inc.php')) {
      require_once __DIR__ . '/game/lotto_ticket.inc.php';
      로또티켓_컬럼_확보();
    }

    $rs = @db_query("SELECT idx, name, level, tasu, lotto_ticket_level FROM tb_member WHERE status = 0");
    if (!$rs) {
      $rs = @db_query("SELECT idx, name, level, tasu FROM tb_member WHERE status = 0");
    }
    if (!$rs) {
      return;
    }
    while ($row = db_fetch($rs)) {
      $idx = (int)($row['idx'] ?? 0);
      $name = trim((string)($row['name'] ?? ''));
      if ($idx <= 0 || $name === '') {
        continue;
      }
      $total_tasu = (int)($row['tasu'] ?? 0);
      $new_level = 레벨_타수기준_레벨계산($total_tasu);
      $current_level = (int)($row['level'] ?? 0);
      $paid_ticket_level = (int)($row['lotto_ticket_level'] ?? 0);

      $name_esc = addslashes($name);
      $티켓지급 = 0;
      if (function_exists('로또티켓_레벨구간_지급')) {
        $티켓기준레벨 = max($new_level, $current_level);
        if ($티켓기준레벨 > $paid_ticket_level) {
          $티켓지급 = 로또티켓_레벨구간_지급($idx, $paid_ticket_level, $티켓기준레벨);
        }
      }

      if ($new_level === $current_level) {
        if ($티켓지급 > 0) {
          $멘트 = "🎟️ {$name} 로또 티켓 +{$티켓지급}장 (Lv{$티켓기준레벨}까지 반영)";
          $멘트_esc = addslashes($멘트);
          db_query("
            INSERT INTO tb_lotto_info
            SET status = 0, msg = '{$멘트_esc}', leverage = 0, item = '{$name_esc}', regdate = NOW()
          ");
        }
        continue;
      }

      if ($new_level > $current_level) {
        $지급냥 = 레벨업_본방냥_지급합계($current_level, $new_level);
        $지급보호 = 레벨업_보호_지급합계($current_level, $new_level);
        $sets = ["level = {$new_level}"];
        if ($지급냥 > 0) {
          $sets[] = "newpoint = newpoint + {$지급냥}";
        }
        if ($지급보호 > 0) {
          $sets[] = "protect = IFNULL(protect, 0) + {$지급보호}";
        }
        db_query("UPDATE tb_member SET " . implode(', ', $sets) . " WHERE idx = {$idx} LIMIT 1");

        if ($지급냥 > 0 || $티켓지급 > 0 || $지급보호 > 0) {
          $멘트 = 레벨업_알림_문구($name, $current_level, $new_level, $지급냥, $티켓지급, $지급보호);
          $멘트_esc = addslashes($멘트);
          db_query("
            INSERT INTO tb_lotto_info
            SET status = 0, msg = '{$멘트_esc}', leverage = 0, item = '{$name_esc}', regdate = NOW()
          ");
        }
      } else {
        db_query("UPDATE tb_member SET level = {$new_level} WHERE idx = {$idx} LIMIT 1");
      }
    }
  }
}

if (!function_exists('야바위_금액표시')) {
  function 야바위_금액표시($금액, $단위 = '냥') {
    return 랭킹_게임냥표시($금액, $단위);
  }
}

/**
 * 무기 강화 시도 1회 이력 저장 (tb_enhance_log)
 *
 * @param array{
 *   nick: string,
 *   channel?: 'chat'|'web',
 *   item?: string,
 *   style?: string,
 *   enhance_before: int,
 *   enhance_after: int,
 *   result: 'success'|'fail_protect'|'fail_break',
 *   cost?: int,
 *   dice?: int,
 *   dice_num?: int,
 *   dice_den?: int,
 *   rate?: string,
 *   eunchong?: bool,
 *   challenge_mode?: bool,
 *   challenge_target?: string,
 *   challenge_steal?: bool|null,
 *   suho_used?: bool,
 *   suho_left?: int|null,
 * } $opts
 */
if (!function_exists('강화_이력_기록')) {
  function 강화_이력_기록(array $opts) {
    $nick = trim((string)($opts['nick'] ?? ''));
    $result = (string)($opts['result'] ?? '');
    if ($nick === '' || !in_array($result, ['success', 'fail_protect', 'fail_break'], true)) {
      return false;
    }

    $nick_esc = addslashes($nick);
    $channel = (($opts['channel'] ?? 'chat') === 'web') ? 'web' : 'chat';
    $item = addslashes(trim((string)($opts['item'] ?? '')));
    $style = addslashes(trim((string)($opts['style'] ?? '')));
    $enhance_before = max(0, min(255, (int)($opts['enhance_before'] ?? 0)));
    $enhance_after = max(0, min(255, (int)($opts['enhance_after'] ?? 0)));
    $cost = max(0, (int)($opts['cost'] ?? 0));
    $dice_roll = max(0, (int)($opts['dice'] ?? 0));
    $dice_num = max(0, (int)($opts['dice_num'] ?? 0));
    $dice_den = max(0, (int)($opts['dice_den'] ?? 0));
    $rate_pct = addslashes(trim((string)($opts['rate'] ?? '')));
    $eunchong = !empty($opts['eunchong']) ? 1 : 0;
    $challenge_mode = !empty($opts['challenge_mode']) ? 1 : 0;
    $challenge_target = trim((string)($opts['challenge_target'] ?? ''));
    $challenge_target_sql = $challenge_target !== '' ? "'" . addslashes($challenge_target) . "'" : 'NULL';
    $challenge_steal_sql = 'NULL';
    if (array_key_exists('challenge_steal', $opts) && $opts['challenge_steal'] !== null) {
      $challenge_steal_sql = !empty($opts['challenge_steal']) ? '1' : '0';
    }
    $suho_used = !empty($opts['suho_used']) ? 1 : 0;
    $suho_left_sql = 'NULL';
    if (array_key_exists('suho_left', $opts) && $opts['suho_left'] !== null) {
      $suho_left_sql = max(0, (int)$opts['suho_left']);
    }

    return (bool)db_query("
      INSERT INTO tb_enhance_log
      SET nick = '{$nick_esc}',
          channel = '{$channel}',
          item = '{$item}',
          style = '{$style}',
          enhance_before = {$enhance_before},
          enhance_after = {$enhance_after},
          result = '{$result}',
          cost = {$cost},
          dice_roll = {$dice_roll},
          dice_num = {$dice_num},
          dice_den = {$dice_den},
          rate_pct = '{$rate_pct}',
          eunchong = {$eunchong},
          challenge_mode = {$challenge_mode},
          challenge_target = {$challenge_target_sql},
          challenge_steal = {$challenge_steal_sql},
          suho_used = {$suho_used},
          suho_left = {$suho_left_sql},
          regdate = NOW()
    ");
  }
}

/**
 * @return array<int, array<string, mixed>>
 */
if (!function_exists('강화_이력_조회')) {
  function 강화_이력_조회($nick, $limit = 10) {
    $nick_esc = addslashes(trim((string)$nick));
    if ($nick_esc === '') {
      return [];
    }
    $limit = max(1, min(50, (int)$limit));
    $rows = [];
    $rs = db_query("
      SELECT idx, nick, channel, item, style, enhance_before, enhance_after, result,
             cost, dice_roll, dice_num, dice_den, rate_pct, eunchong, challenge_mode,
             challenge_target, challenge_steal, suho_used, suho_left, regdate
      FROM tb_enhance_log
      WHERE nick = '{$nick_esc}'
      ORDER BY idx DESC
      LIMIT {$limit}
    ");
    while ($rs && $row = db_fetch($rs)) {
      $rows[] = $row;
    }
    return $rows;
  }
}

/** 채팅 `.강화 이력 [N]` 출력용 */
if (!function_exists('강화_이력_목록_문구')) {
  function 강화_이력_목록_문구($nick, $limit = 10) {
    global $단위;
    $단위표 = (isset($단위) && (string)$단위 !== '') ? (string)$단위 : '냥';
    $limit = max(1, min(30, (int)$limit));
    $목록 = 강화_이력_조회($nick, $limit);
    if (empty($목록)) {
      return "📜 {$nick} 강화 이력\n기록이 없습니다.";
    }

    $결과라벨 = [
      'success'      => '⚔️ 성공',
      'fail_protect' => '👼 실패·수호',
      'fail_break'   => '💥 실패·파손',
    ];
    $채널라벨 = ['chat' => '채팅', 'web' => '웹'];

    $msg = "📜 {$nick} 강화 이력 (최근 {$limit}건)\n";
    foreach ($목록 as $i => $row) {
      $결과 = $결과라벨[$row['result'] ?? ''] ?? (string)($row['result'] ?? '');
      $무기 = trim((string)($row['item'] ?? ''));
      $스타일 = trim((string)($row['style'] ?? ''));
      $전 = (int)($row['enhance_before'] ?? 0);
      $후 = (int)($row['enhance_after'] ?? 0);
      $주사위 = (int)($row['dice_roll'] ?? 0);
      $비용 = (int)($row['cost'] ?? 0);
      $시각 = !empty($row['regdate']) ? date('m-d H:i', strtotime($row['regdate'])) : '';
      $경로 = $채널라벨[$row['channel'] ?? 'chat'] ?? '';

      $무기표시 = $무기 !== '' ? $무기 : '(무기)';
      if ($스타일 !== '') {
        $무기표시 = $스타일 . ' ' . $무기표시;
      }
      if ($row['result'] === 'success') {
        $변화 = "+{$전} → +{$후}";
      } elseif ($row['result'] === 'fail_protect') {
        $변화 = "+{$전} 유지";
      } else {
        $변화 = "+{$전} 파손";
      }

      $부가 = '';
      if (!empty($row['challenge_mode'])) {
        $타겟 = trim((string)($row['challenge_target'] ?? ''));
        $부가 .= $타겟 !== '' ? " · +20도전[{$타겟}]" : ' · +20도전';
        if ($row['result'] === 'success' && $row['challenge_steal'] !== null) {
          $부가 .= !empty($row['challenge_steal']) ? '·탈취' : '·역풍';
        }
      }
      if (!empty($row['eunchong'])) {
        $부가 .= ' · 은총';
      }
      if (!empty($row['suho_used'])) {
        $남은 = isset($row['suho_left']) ? (int)$row['suho_left'] : null;
        $부가 .= $남은 !== null ? " · 수호남음{$남은}" : ' · 수호소모';
      }

      $msg .= "\n" . ($i + 1) . ") [{$시각}] {$결과} {$무기표시} {$변화}\n";
      $msg .= "   주사위 {$주사위} · " . number_format($비용) . "{$단위표} · {$경로}{$부가}";
    }
    return $msg;
  }
}

/** `.무기복구` 강화단계별 전체 게임냥 대비 배율 (× 합계 = 복구비) */
if (!function_exists('무기복구_비율표')) {
  function 무기복구_비율표() {
    return [
      10 => 0.001,
      11 => 0.002,
      12 => 0.003,
      13 => 0.004,
      14 => 0.005,
      15 => 0.006,
      16 => 0.01,
      17 => 0.02,
      18 => 0.03,
      19 => 0.05,
    ];
  }
}

if (!function_exists('무기복구_전체게임냥합계')) {
  function 무기복구_전체게임냥합계() {
    return function_exists('시세기준_게임냥_문자열')
      ? 시세기준_게임냥_문자열()
      : 냥_정수문자열(시세기준_게임냥());
  }
}

/** @return float|null 강화단계별 배율 (없으면 null) */
if (!function_exists('무기복구_비율배수')) {
  function 무기복구_비율배수($강화) {
    $표 = 무기복구_비율표();
    $강화 = (int)$강화;
    return array_key_exists($강화, $표) ? (float)$표[$강화] : null;
  }
}

/** 전체 게임냥 합계 × 단계별 배율 */
if (!function_exists('무기복구_비용')) {
  function 무기복구_비용($강화) {
    $배율 = 무기복구_비율배수($강화);
    if ($배율 === null) {
      return null;
    }
    $전체 = 냥_정수문자열(무기복구_전체게임냥합계());
    if (function_exists('bcmul') && function_exists('bcadd') && function_exists('bccomp')) {
      $raw = bcmul($전체, sprintf('%.12F', $배율), 12);
      $금액 = bcadd($raw, '0', 0);
      if (bccomp($raw, $금액, 12) > 0) {
        $금액 = bcadd($금액, '1', 0);
      }
      if (bccomp($금액, '1', 0) < 0) {
        $금액 = '1';
      }
      if (bccomp($금액, (string)PHP_INT_MAX, 0) <= 0) {
        return max(1, (int)$금액);
      }
      return $금액;
    }
    return max(1, (int)ceil((float)$전체 * $배율));
  }
}

/** 미복구 최근 파손 1건 */
if (!function_exists('무기복구_대상조회')) {
  function 무기복구_대상조회($nick) {
    $nick_esc = addslashes(trim((string)$nick));
    if ($nick_esc === '') {
      return null;
    }
    $row = db_select("
      SELECT idx, nick, item, style, enhance_before, regdate
      FROM tb_enhance_log
      WHERE nick = '{$nick_esc}'
        AND result = 'fail_break'
        AND IFNULL(restored, 0) = 0
      ORDER BY idx DESC
      LIMIT 1
    ");
    return !empty($row['idx']) ? $row : null;
  }
}

if (!function_exists('무기복구_안내문구')) {
  function 무기복구_안내문구($nick) {
    global $단위;
    $단위표 = (isset($단위) && (string)$단위 !== '') ? (string)$단위 : '냥';
    $냥표시 = function_exists('구매가_축약표시')
      ? function ($금액) use ($단위표) { return 구매가_축약표시($금액, $단위표); }
      : function ($금액) use ($단위표) { return number_format((int)$금액) . $단위표; };
    $전체 = 무기복구_전체게임냥합계();
    $표 = 무기복구_비율표();

    $msg = "🔧 무기복구 안내\n\n";
    $msg .= "명령어: `.무기복구` (최근 파손 무기 1건 복구)\n";
    $msg .= "조건: 현재 무기 없음 · +10~+19강 파손 이력 · 미복구 건\n";
    $msg .= "비용: 전체 게임냥 합계 × 강화단계별 비율 (게임냥 차감)\n\n";
    $msg .= "현재 전체 게임냥: " . $냥표시($전체) . "\n\n";
    $msg .= "【강화별 비율 · 현재 기준 복구비】\n";
    foreach ($표 as $강화 => $배율) {
      $비용 = max(1, (int)ceil($전체 * $배율));
      $퍼센트표시 = rtrim(rtrim(number_format($배율 * 100, 4, '.', ''), '0'), '.') . '%';
      $msg .= "· +{$강화}: {$퍼센트표시} → " . $냥표시($비용) . "\n";
    }

    $대상 = 무기복구_대상조회($nick);
    $msg .= "\n【내 복구 가능 여부】\n";
    if (empty($대상['idx'])) {
      $msg .= "복구 가능한 파손 이력이 없어요.";
      return $msg;
    }
    $무기 = trim((string)($대상['item'] ?? ''));
    $스타일 = trim((string)($대상['style'] ?? ''));
    $강화 = (int)($대상['enhance_before'] ?? 0);
    $비용 = 무기복구_비용($강화);
    $시각 = !empty($대상['regdate']) ? date('m-d H:i', strtotime($대상['regdate'])) : '';
    $무기표시 = $스타일 !== '' ? "{$스타일} {$무기}" : $무기;
    if ($비용 === null) {
      $msg .= "최근 파손: {$무기표시} +{$강화} ({$시각})\n❌ +{$강화}강은 복구 대상이 아니에요. (+10~+19)";
    } else {
      $msg .= "최근 파손: {$무기표시} +{$강화} ({$시각})\n";
      $msg .= "복구비: " . $냥표시($비용) . "\n";
      $msg .= "`.무기복구` 로 복구할 수 있어요.";
    }
    return $msg;
  }
}

/**
 * @return array{ok:bool, msg:string}
 */
if (!function_exists('무기복구_실행')) {
  function 무기복구_실행($nick, $단위 = '냥') {
    $닉 = trim((string)$nick);
    if ($닉 === '') {
      return ['ok' => false, 'msg' => '❌ 닉네임을 확인해주세요.'];
    }
    $닉_esc = addslashes($닉);

    $회원 = db_select("SELECT idx, point, item, enhance FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    if (empty($회원['idx'])) {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.'];
    }
    if (trim((string)($회원['item'] ?? '')) !== '') {
      return ['ok' => false, 'msg' => '❌ 이미 무기를 보유 중이에요. 무기가 없을 때만 복구할 수 있어요.'];
    }

    $대상 = 무기복구_대상조회($닉);
    if (empty($대상['idx'])) {
      return ['ok' => false, 'msg' => "❌ 복구할 파손 이력이 없어요.\n`.무기복구 안내` 로 비용표를 확인할 수 있어요."];
    }

    $무기 = trim((string)($대상['item'] ?? ''));
    $스타일 = trim((string)($대상['style'] ?? ''));
    $강화 = (int)($대상['enhance_before'] ?? 0);
    $로그idx = (int)$대상['idx'];

    if ($무기 === '') {
      return ['ok' => false, 'msg' => '❌ 복구 대상 무기 정보가 없어요.'];
    }
    $비용 = 무기복구_비용($강화);
    if ($비용 === null) {
      return ['ok' => false, 'msg' => "❌ +{$강화}강 무기는 복구할 수 없어요. (+10~+19강 파손만 가능)"];
    }

    $보유냥 = (int)($회원['point'] ?? 0);
    if ($보유냥 < $비용) {
      return [
        'ok'  => false,
        'msg' => "❌ 복구비가 부족해요.\n필요: " . number_format($비용) . "{$단위} (보유: " . number_format($보유냥) . "{$단위})",
      ];
    }

    $무기_esc = addslashes($무기);
    $스타일_esc = addslashes($스타일);
    $rs = db_query("
      UPDATE tb_member
      SET point = point - {$비용},
          `item` = '{$무기_esc}',
          `enhance` = {$강화},
          `style` = '{$스타일_esc}',
          강화성공시간 = NOW()
      WHERE name = '{$닉_esc}'
        AND (TRIM(COALESCE(`item`, '')) = '')
      LIMIT 1
    ");
    if (!$rs) {
      return ['ok' => false, 'msg' => '❌ 무기 복구 처리에 실패했어요.'];
    }

    if ($강화 >= 10 && function_exists('무기_최대내구도')) {
      $내구도 = (int)무기_최대내구도($무기, $강화);
      if ($내구도 > 0) {
        db_query("UPDATE tb_member SET durability = {$내구도} WHERE name = '{$닉_esc}' LIMIT 1");
      }
    }

    db_query("UPDATE tb_enhance_log SET restored = 1, restored_at = NOW() WHERE idx = {$로그idx} AND IFNULL(restored, 0) = 0 LIMIT 1");

    if (function_exists('지급로그')) {
      지급로그("무기복구+{$강화}", $닉, '', $비용, 0);
    }

    $무기표시 = $스타일 !== '' ? "{$스타일} {$무기}" : $무기;
    $파손시각 = !empty($대상['regdate']) ? date('m-d H:i', strtotime($대상['regdate'])) : '';
    $배율 = 무기복구_비율배수($강화);
    $퍼센트표시 = $배율 !== null
      ? rtrim(rtrim(number_format($배율 * 100, 4, '.', ''), '0'), '.') . '%'
      : '';

    $msg = "✅ 무기 복구 완료!\n";
    $msg .= "{$무기표시} +{$강화} 복원 (파손 {$파손시각})\n";
    $msg .= "복구비: " . number_format($비용) . "{$단위}";
    if ($퍼센트표시 !== '') {
      $msg .= " (전체 게임냥 × {$퍼센트표시})";
    }
    return ['ok' => true, 'msg' => $msg];
  }
}

/** `.수리` 내구도 1당 비율 — +20: 전체 게임냥 0.000005%, 그 외: 0.000001% */
if (!function_exists('수리_비율퍼센트')) {
  function 수리_비율퍼센트($강화단계 = 0): float {
    return ((int)$강화단계 >= 20) ? 0.000005 : 0.000001;
  }
}

/** `.수리` 내구도 1당 냥 비용 (전체 게임냥 × 수리_비율퍼센트) */
if (!function_exists('수리_회당비용')) {
  function 수리_회당비용($강화단계 = 0): int {
    return (int)전체냥기준금액(수리_비율퍼센트($강화단계));
  }
}

/** 수리 등 — 경·조·억만 표시 (억 미만 절사, 단위 사이 공백) */
if (!function_exists('게임냥_경조억표시')) {
  function 게임냥_경조억표시($금액, $단위 = '냥') {
    return 냥_경조억_축약문구($금액, $단위, ' ');
  }
}

if (!function_exists('수리_냥문구')) {
  function 수리_냥문구($금액, $단위 = '냥') {
    if (function_exists('게임냥_경조억표시')) {
      return 게임냥_경조억표시($금액, $단위);
    }
    if (function_exists('구매가_축약표시')) {
      return 구매가_축약표시($금액, $단위);
    }
    return number_format((int)$금액) . $단위;
  }
}

/** `.강화 수호` 1회당 냥 비용 (게임냥 고정) */
if (!function_exists('강화수호_회당비용')) {
  function 강화수호_회당비용(): int {
    return 100000000;
  }
}

/**
 * `.강화 수호 [N]` — 수호 아이템 우선 사용, 부족분만 냥(1회 1억) 차감
 * @param int|null $냥회당증가 null이면 냥 1~3 랜덤, 숫자면 냥 구매 시 회당 고정 (+1). 수호 아이템 전환은 항상 1~3
 * @return array{ok:bool,msg:string,enhance_suho?:int,point?:int,차감?:int,아이템사용?:int,냥사용회수?:int}
 */
if (!function_exists('강화수호_냥적용')) {
  function 강화수호_냥적용(string $닉, int $수량, $단위 = '냥', $냥회당증가 = null): array {
    $닉 = trim($닉);
    $수량 = max(1, (int)$수량);
    if ($닉 === '') {
      return ['ok' => false, 'msg' => '❌ 닉네임을 확인해주세요.'];
    }

    $닉_esc = addslashes($닉);
    $회원 = db_select("SELECT idx, point, enhance_suho FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    if (empty($회원['idx'])) {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.'];
    }

    $보유아이템행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE nick = '{$닉_esc}' AND itemname = '수호' AND status = 0");
    $보유아이템 = (int)($보유아이템행['cnt'] ?? 0);
    $아이템사용 = min($수량, $보유아이템);
    $냥사용회수 = $수량 - $아이템사용;
    $회당비용 = 강화수호_회당비용();
    $총차감 = $회당비용 * $냥사용회수;
    $보유냥 = (int)($회원['point'] ?? 0);

    if ($냥사용회수 > 0 && $보유냥 < $총차감) {
      $부족문구 = "❌ {$닉} {$단위} 부족";
      if ($아이템사용 > 0) {
        $부족문구 .= " (수호 아이템 {$아이템사용}개 사용 후 냥 {$냥사용회수}회 추가 필요)";
      }
      $부족문구 .= "\n필요: " . 구매가_축약표시($총차감, $단위)
        . " / 보유: " . 구매가_축약표시($보유냥, $단위);
      return ['ok' => false, 'msg' => $부족문구];
    }

    $현재수호 = (int)($회원['enhance_suho'] ?? 0);
    $총증가수치 = 0;
    $회차결과 = [];
    $실제아이템사용 = 0;
    $아이템증가값 = function () {
      return rand(1, 3);
    };
    $냥증가값 = function () use ($냥회당증가) {
      if ($냥회당증가 !== null) {
        return max(1, (int)$냥회당증가);
      }
      return rand(1, 3);
    };

    for ($i = 1; $i <= $아이템사용; $i++) {
      $수호한개 = db_select("SELECT idx FROM tb_member_item WHERE nick = '{$닉_esc}' AND itemname = '수호' AND status = 0 ORDER BY idx ASC LIMIT 1");
      if (empty($수호한개['idx'])) {
        break;
      }
      $증가 = $아이템증가값();
      $총증가수치 += $증가;
      $회차결과[] = "{$i}회차:+{$증가}(수호아이템)";
      db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx = {$수호한개['idx']}");
      $실제아이템사용++;
    }

    if ($실제아이템사용 > 0 && function_exists('아이템사용_시세하락')) {
      아이템사용_시세하락('수호', $실제아이템사용);
    }

    $냥회차시작 = $실제아이템사용 + 1;
    $실제냥사용회수 = 0;
    for ($i = $냥회차시작; $i <= $수량; $i++) {
      $증가 = $냥증가값();
      $총증가수치 += $증가;
      $회차결과[] = "{$i}회차:+{$증가}(냥)";
      $실제냥사용회수++;
    }
    $총차감 = $회당비용 * $실제냥사용회수;

    if ($총증가수치 <= 0) {
      return ['ok' => false, 'msg' => "❌ {$닉} 강화 수호를 처리할 수 없어요."];
    }

    $새수호 = $현재수호 + $총증가수치;
    $idx = (int)$회원['idx'];
    if ($총차감 > 0) {
      db_query("UPDATE tb_member SET point = point - {$총차감}, enhance_suho = {$새수호} WHERE idx = {$idx}");
    } else {
      db_query("UPDATE tb_member SET enhance_suho = {$새수호} WHERE name = '{$닉_esc}'");
    }

    $상세 = implode(', ', $회차결과);
    $사용문구 = '';
    if ($실제아이템사용 > 0 && $실제냥사용회수 > 0) {
      $사용문구 = "수호 아이템 {$실제아이템사용}개 + " . 구매가_축약표시($총차감, $단위) . " 차감";
    } elseif ($실제아이템사용 > 0) {
      $사용문구 = "수호 아이템 {$실제아이템사용}개 사용";
    } else {
      $사용문구 = 구매가_축약표시($총차감, $단위) . " 차감";
    }

    return [
      'ok' => true,
      'msg' => "👼 {$닉} 강화 수호 {$수량}회 적용! ({$사용문구})\n"
        . "적용값: {$상세}\n총 +{$총증가수치} 적용\n무기 파손 방지 {$새수호}회 누적",
      'enhance_suho' => $새수호,
      'point' => $보유냥 - $총차감,
      '차감' => $총차감,
      '아이템사용' => $실제아이템사용,
      '냥사용회수' => $실제냥사용회수,
    ];
  }
}

/** 보유 수호 아이템(미사용) 개수 */
if (!function_exists('강화수호_아이템개수')) {
  function 강화수호_아이템개수(string $닉): int {
    $닉_esc = addslashes(trim($닉));
    if ($닉_esc === '') {
      return 0;
    }
    $r = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE nick = '{$닉_esc}' AND itemname = '수호' AND status = 0");
    return (int)($r['cnt'] ?? 0);
  }
}

/**
 * 강화 실패 시 파손 방지 — enhance_suho 우선, 없으면 수호 아이템 1개 소모
 * @return array{enhance_suho:int,suho_item_used:bool,suho_item_left:int,msg_suffix:string}|null
 */
if (!function_exists('강화실패_수호방지_적용')) {
  function 강화실패_수호방지_적용(string $닉): ?array {
    $닉_esc = addslashes(trim($닉));
    if ($닉_esc === '') {
      return null;
    }

    $회원 = db_select("SELECT idx, enhance_suho FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($회원['idx'])) {
      return null;
    }

    $수호보유 = (int)($회원['enhance_suho'] ?? 0);
    if ($수호보유 >= 1) {
      db_query("UPDATE tb_member SET enhance_suho = GREATEST(IFNULL(enhance_suho, 0) - 1, 0) WHERE name = '{$닉_esc}'");
      $남은 = $수호보유 - 1;
      return [
        'enhance_suho' => $남은,
        'suho_item_used' => false,
        'suho_item_left' => 강화수호_아이템개수($닉),
        'msg_suffix' => $남은 > 0 ? " (수호 {$남은}회 남음)" : '',
      ];
    }

    $수호한개 = db_select("
      SELECT idx FROM tb_member_item
      WHERE nick = '{$닉_esc}' AND itemname = '수호' AND status = 0
      ORDER BY idx ASC
      LIMIT 1
    ");
    if (empty($수호한개['idx'])) {
      return null;
    }

    db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx = " . (int)$수호한개['idx']);
    if (function_exists('아이템사용_시세하락')) {
      아이템사용_시세하락('수호', 1);
    }
    $남은아이템 = 강화수호_아이템개수($닉);

    return [
      'enhance_suho' => $수호보유,
      'suho_item_used' => true,
      'suho_item_left' => $남은아이템,
      'msg_suffix' => " (수호 아이템 1개 소모" . ($남은아이템 > 0 ? " · {$남은아이템}개 남음" : '') . ")",
    ];
  }
}

/**
 * 500타 당일 계급보상(보유 1%) 지급 여부 — tb_point_log 전용 status + 당일 공지.
 */
if (!function_exists('계급보상500_오늘지급됨')) {
  function 계급보상500_오늘지급됨($nick, $date = null) {
    $nick = trim((string)$nick);
    if ($nick === '') {
      return true;
    }
    if ($date === null || $date === '') {
      $date = date('Y-m-d');
    }
    $n = addslashes($nick);
    $d = addslashes($date);
    $log = @db_select("
      SELECT idx FROM tb_point_log
      WHERE nick = '{$n}' AND DATE(regdate) = '{$d}'
        AND status = '500타계급보상'
      LIMIT 1
    ");
    if (!empty($log['idx'])) {
      return true;
    }
    $공지 = @db_select("
      SELECT idx FROM tb_lotto_info
      WHERE item = '{$n}'
        AND DATE(regdate) = '{$d}'
        AND msg LIKE '%500타 달성%'
        AND msg LIKE '%계급보상%'
      LIMIT 1
    ");
    return !empty($공지['idx']);
  }
}

/**
 * 500타 계급보상 지급·로그·공지 (당일 1회, 금액 0이어도 로그 남겨 중복 방지).
 *
 * @return array{paid:bool, amount:int, rank_name:string}
 */
if (!function_exists('계급보상500_지급')) {
  function 계급보상500_지급($nick, $비율 = 0.01, $date = null) {
    $nick = trim((string)$nick);
    if ($nick === '' || 계급보상500_오늘지급됨($nick, $date)) {
      return ['paid' => false, 'amount' => 0, 'rank_name' => ''];
    }
    $닉_esc = addslashes($nick);
    $lock_key = '계급500_' . $닉_esc;
    $lock_row = @db_select("SELECT GET_LOCK('{$lock_key}', 3) AS got");
    if ((int)($lock_row['got'] ?? 0) !== 1) {
      return ['paid' => false, 'amount' => 0, 'rank_name' => ''];
    }
    if (계급보상500_오늘지급됨($nick, $date)) {
      @db_query("SELECT RELEASE_LOCK('{$lock_key}')");
      return ['paid' => false, 'amount' => 0, 'rank_name' => ''];
    }
    $정보 = db_select("SELECT point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($정보)) {
      @db_query("SELECT RELEASE_LOCK('{$lock_key}')");
      return ['paid' => false, 'amount' => 0, 'rank_name' => ''];
    }
    $curPoint = (int)($정보['point'] ?? 0);
    $계급 = 계급($curPoint);
    $출석지급 = (int)floor($curPoint * (float)$비율);
    $계급명 = (string)($계급['name'] ?? '');

    지급로그('500타계급보상', $nick, $nick, 0, $출석지급);
    if ($출석지급 > 0) {
      db_query("UPDATE tb_member SET point = point + {$출석지급} WHERE name = '{$닉_esc}' LIMIT 1");
    }

    $멘트500 = "{$nick} 500타 달성🎉\n계급보상 " . number_format($출석지급) . "냥";
    $멘트500_esc = addslashes($멘트500);
    db_query("
      INSERT INTO tb_lotto_info
      SET status = 1, msg = '{$멘트500_esc}', leverage = 0, item = '{$닉_esc}', regdate = NOW()
    ");
    미션완료_기록_if_new($nick, '타수', '500타');

    @db_query("SELECT RELEASE_LOCK('{$lock_key}')");
    return ['paid' => true, 'amount' => $출석지급, 'rank_name' => $계급명];
  }
}

/** tb_mission: 동일 (nick, types, status) 없을 때만 1행 추가
 *  - 타수 미션: 매일 초기화 → 오늘자(regdate = 오늘) 기록이 없을 때만 INSERT
 *  - 일방 미션: 누적 → 과거 포함 기록이 전혀 없을 때만 INSERT
 *  - $daily 를 명시하면 이 기본 동작을 덮어씀 (true=오늘자만 체크, false=전체 체크)
 */
if (!function_exists('미션완료_기록_if_new')) {
  function 미션완료_기록_if_new($nick, $types, $status, $daily = null) {
    $nick = trim((string)$nick);
    if ($nick === '') {
      return;
    }
    $n = addslashes($nick);
    $t_raw = trim((string)$types);
    $s_raw = trim((string)$status);
    $t = addslashes($t_raw);
    $s = addslashes($s_raw);
    if ($t === '' || $s === '') {
      return;
    }
    $오늘 = date('Y-m-d');

    // $daily 미지정 시: 타수 = 일일 초기화, 그 외(일방 등) = 누적
    if ($daily === null) {
      $daily = ($t_raw === '타수');
    }

    if ($daily) {
      $있음 = @db_select("SELECT idx FROM tb_mission WHERE nick = '{$n}' AND types = '{$t}' AND status = '{$s}' AND regdate = '{$오늘}' LIMIT 1");
    } else {
      $있음 = @db_select("SELECT idx FROM tb_mission WHERE nick = '{$n}' AND types = '{$t}' AND status = '{$s}' LIMIT 1");
    }
    if (!empty($있음['idx'])) {
      return;
    }
    @db_query("INSERT INTO tb_mission (nick, types, status, chk, regdate) VALUES ('{$n}', '{$t}', '{$s}', '0', '{$오늘}')");
  }
}

/** 꼬맨틀 일일 참여 한도 */
if (!function_exists('꼬맨_일일한도')) {
  function 꼬맨_일일한도() {
    return 5;
  }
}

if (!function_exists('꼬맨_오늘완료수')) {
  function 꼬맨_오늘완료수() {
    $오늘 = date('Y-m-d');
    $row = db_select("SELECT COUNT(*) AS cnt FROM tb_kkomaen WHERE play_date = '{$오늘}'");
    return (int)($row['cnt'] ?? 0);
  }
}

if (!function_exists('꼬맨_오늘잔여')) {
  function 꼬맨_오늘잔여() {
    return max(0, 꼬맨_일일한도() - 꼬맨_오늘완료수());
  }
}

if (!function_exists('꼬맨_오늘참여여부')) {
  function 꼬맨_오늘참여여부($nick) {
    $nick = addslashes(trim((string)$nick));
    if ($nick === '') {
      return false;
    }
    $오늘 = date('Y-m-d');
    $row = db_select("SELECT idx FROM tb_kkomaen WHERE nick = '{$nick}' AND play_date = '{$오늘}' LIMIT 1");
    return !empty($row['idx']);
  }
}

if (!function_exists('꼬맨_완료_기록')) {
  function 꼬맨_완료_기록($nick, $reward = 1000) {
    $nick = addslashes(trim((string)$nick));
    if ($nick === '') {
      return false;
    }
    $오늘 = date('Y-m-d');
    $reward = (int)$reward;
    $rs = @db_query("INSERT INTO tb_kkomaen (nick, reward, play_date, regdate) VALUES ('{$nick}', {$reward}, '{$오늘}', NOW())");
    return $rs !== false;
  }
}

if (!function_exists('꼬맨_완료_취소')) {
  function 꼬맨_완료_취소($nick, $play_date = null) {
    $nick = addslashes(trim((string)$nick));
    if ($nick === '') {
      return false;
    }
    $d = $play_date !== null && $play_date !== '' ? addslashes($play_date) : date('Y-m-d');
    return @db_query("DELETE FROM tb_kkomaen WHERE nick = '{$nick}' AND play_date = '{$d}' LIMIT 1") !== false;
  }
}

/** nick 기준 완료 미션 types·status 집합 (키 = types + "\x1e" + status)
 *  $date 를 넘기면 해당 날짜(Y-m-d)의 기록만 필터링. null 이면 전체 기록.
 */
if (!function_exists('미션_완료_맵')) {
  function 미션_완료_맵($nick, $date = null) {
    $map = array();
    $nick = trim((string)$nick);
    if ($nick === '') {
      return $map;
    }
    $n = addslashes($nick);
    $sql = "SELECT types, status FROM tb_mission WHERE nick = '{$n}'";
    if ($date !== null && $date !== '') {
      $d = addslashes($date);
      $sql .= " AND regdate = '{$d}'";
    }
    $rs = @db_query($sql);
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $map[(string)($row['types'] ?? '') . "\x1e" . (string)($row['status'] ?? '')] = true;
      }
    }
    return $map;
  }
}

if (!function_exists('미션_완료됨')) {
  function 미션_완료됨($map, $types, $status) {
    return !empty($map[(string)$types . "\x1e" . (string)$status]);
  }
}

/** 일방신청권 보유 — status 0(미사용)·1(구매 즉시활성) 모두 인정 */
if (!function_exists('일방신청권_보유여부')) {
  function 일방신청권_보유여부(int $midx): bool {
    if ($midx <= 0) {
      return false;
    }
    $row = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$midx} AND itemname = '일방신청권' AND status IN (0, 1) LIMIT 1");
    return (int)($row['cnt'] ?? 0) > 0;
  }
}

/**
 * 일방신청권 지급 시 조건·날짜 별도 기록 (tb_ilbang_ticket_log)
 * @param string $nick 받은 사람
 * @param string $reasonCode 생타5일연속|구매|선물|관리자생성
 * @param array $opts from_nick, reason_text, midx, item_idx, qty
 */
if (!function_exists('일방신청권_지급기록')) {
  function 일방신청권_지급기록($nick, $reasonCode, array $opts = []) {
    $nick = trim((string)$nick);
    $reasonCode = trim((string)$reasonCode);
    if ($nick === '' || $reasonCode === '') {
      return false;
    }
    $qty = max(1, (int)($opts['qty'] ?? 1));
    $midx = (int)($opts['midx'] ?? 0);
    if ($midx <= 0) {
      $esc = addslashes($nick);
      $mem = @db_select("SELECT idx FROM tb_member WHERE name = '{$esc}' LIMIT 1");
      $midx = (int)($mem['idx'] ?? 0);
    }
    $from = trim((string)($opts['from_nick'] ?? ''));
    $reasonText = trim((string)($opts['reason_text'] ?? ''));
    if ($reasonText === '') {
      switch ($reasonCode) {
        case '생타5일연속':
          $reasonText = '생타 400타 × 5일 연속 달성';
          break;
        case '구매':
          $reasonText = '상점 구매';
          break;
        case '선물':
          $reasonText = ($from !== '') ? "선물 수령 (보낸이: {$from})" : '선물 수령';
          break;
        case '관리자생성':
          $reasonText = ($from !== '') ? "관리자 생성 ({$from})" : '관리자 생성';
          break;
        default:
          $reasonText = $reasonCode;
          break;
      }
    }
    $itemIdx = isset($opts['item_idx']) ? (int)$opts['item_idx'] : 0;
    $nick_esc = addslashes($nick);
    $code_esc = addslashes($reasonCode);
    $text_esc = addslashes($reasonText);
    $from_sql = ($from !== '') ? "'" . addslashes($from) . "'" : 'NULL';
    $item_sql = ($itemIdx > 0) ? (string)$itemIdx : 'NULL';
    return (bool)@db_query("
      INSERT INTO tb_ilbang_ticket_log
      SET midx = {$midx},
          nick = '{$nick_esc}',
          reason_code = '{$code_esc}',
          reason_text = '{$text_esc}',
          from_nick = {$from_sql},
          item_idx = {$item_sql},
          qty = {$qty},
          regdate = NOW()
    ");
  }
}

/**
 * 5일 400타(생타) 미션 완료 여부 — tb_mission 또는 일방신청권 보유(양도·구매·5일달성)
 * 권 보유 시 tb_mission(일방/400타) 기록도 남김
 */
if (!function_exists('일방400타_미션_완료됨')) {
  function 일방400타_미션_완료됨(string $nick, ?int $midx = null): bool {
    $nick = trim($nick);
    if ($nick === '') {
      return false;
    }
    $map = 미션_완료_맵($nick);
    if (
      미션_완료됨($map, '일방', '400타')
      || 미션_완료됨($map, '일방', '500타')
      || 미션_완료됨($map, '일방', '300타')
      || 미션_완료됨($map, '일방', '200타')
    ) {
      return true;
    }
    if ($midx === null || $midx <= 0) {
      $esc = addslashes($nick);
      $mem = db_select("SELECT idx FROM tb_member WHERE name = '{$esc}' LIMIT 1");
      $midx = (int)($mem['idx'] ?? 0);
    }
    if (!일방신청권_보유여부($midx)) {
      return false;
    }
    if (function_exists('미션완료_기록_if_new')) {
      미션완료_기록_if_new($nick, '일방', '400타');
    }
    return true;
  }
}

/** info3 `.일방신청` — 일방 필수 미션 전체 완료 여부 */
if (!function_exists('일방필수미션_점검')) {
  /**
   * @return array{ok:bool,missing:list<array{label:string}>,completed:list<array{label:string}>}
   */
  function 일방필수미션_점검(string $nick, ?int $midx = null): array {
    $nick = trim($nick);
    $missing = [];
    $completed = [];

    if ($nick === '') {
      return ['ok' => false, 'missing' => [['label' => '회원 정보 없음']], 'completed' => []];
    }

    $미션맵 = 미션_완료_맵($nick);
    $chk = function (string $types, string $status, string $label) use ($미션맵, &$missing, &$completed): void {
      if (미션_완료됨($미션맵, $types, $status)) {
        $completed[] = ['label' => $label];
      } else {
        $missing[] = ['label' => $label];
      }
    };

    $chk('일방', '얼공', '얼공');
    $chk('일방', '야바위', '야바위게임 1회 참여');
    $chk('일방', '무기구매', '무기구매 1회');
    $chk('일방', '지목', '지목 아이템 1회 사용');

    $강제일방완료 = 미션_완료됨($미션맵, '일방', '강일')
      || 미션_완료됨($미션맵, '일방', '강제일방');
    if ($강제일방완료) {
      $completed[] = ['label' => '강제일방 아이템 1회 사용'];
    } else {
      $missing[] = ['label' => '강제일방 아이템 1회 사용'];
    }

    if ($midx === null || $midx <= 0) {
      $esc = addslashes($nick);
      $mem = db_select("SELECT idx FROM tb_member WHERE name = '{$esc}' LIMIT 1");
      $midx = (int)($mem['idx'] ?? 0);
    }
    if (function_exists('일방400타_미션_완료됨') && 일방400타_미션_완료됨($nick, $midx)) {
      $completed[] = ['label' => '5일간 400타(생타)'];
    } else {
      $missing[] = ['label' => '5일간 400타(생타)'];
    }

    return [
      'ok' => count($missing) === 0,
      'missing' => $missing,
      'completed' => $completed,
    ];
  }
}

/** tb_member.`본인인증` = 0 이면 첫 일방 본인인증 대기 */
if (!function_exists('일방본인인증_대기중')) {
  function 일방본인인증_대기중(string $nick, ?array $member = null): bool {
    if ($member !== null && array_key_exists('본인인증', $member)) {
      return (int)($member['본인인증'] ?? 0) === 0;
    }
    $nick = trim($nick);
    if ($nick === '') {
      return true;
    }
    $esc = addslashes($nick);
    $row = db_select("SELECT IFNULL(`본인인증`, 0) AS bonin FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    if (empty($row)) {
      return true;
    }
    return (int)($row['bonin'] ?? 0) === 0;
  }
}

/** 타이틀 문자열(tb_title_bonus_track) 기준 일차: 전일 해당 타이틀로 보상이 있었으면 +1. 보유자 교체 시에도 유지. */
if (!function_exists('타이틀보상_타이틀별_일차')) {
  function 타이틀보상_타이틀별_일차($title_key, $어제날짜) {
    $t = trim((string)$title_key);
    if ($t === '') {
      return 1;
    }
    $esc = addslashes($t);
    $row = @db_select("SELECT streak, last_bonus_date FROM tb_title_bonus_track WHERE title_key = '{$esc}' LIMIT 1");
    $last = isset($row['last_bonus_date']) ? trim((string)$row['last_bonus_date']) : '';
    $prev = (int)($row['streak'] ?? 0);
    $y = trim((string)$어제날짜);
    if ($last !== '' && $y !== '' && $last === $y) {
      return max(1, $prev + 1);
    }
    return 1;
  }
}

/** 타이틀보상 지급 후 해당 타이틀 행 갱신 */
if (!function_exists('타이틀보상_타이틀별_저장')) {
  function 타이틀보상_타이틀별_저장($title_key, $일차, $오늘날짜) {
    $t = trim((string)$title_key);
    if ($t === '') {
      return;
    }
    $esc = addslashes($t);
    $d = addslashes(trim((string)$오늘날짜));
    $n = (int)$일차;
    @db_query("INSERT INTO tb_title_bonus_track (title_key, streak, last_bonus_date) VALUES ('{$esc}', {$n}, '{$d}')
      ON DUPLICATE KEY UPDATE streak = {$n}, last_bonus_date = '{$d}'");
  }
}

/** 회원 퇴사·삭제 시 채굴·광물 데이터 전부 제거 */
if (!function_exists('회원_채굴_전체삭제')) {
  function 회원_채굴_전체삭제($nick) {
    require_once __DIR__ . '/game/mining_storage.inc.php';
    if (!function_exists('mining_member_purge')) {
      return ['ok' => false, 'mining_deleted' => 0, 'ore_find_deleted' => 0, 'ore_log_deleted' => 0];
    }
    return mining_member_purge($nick);
  }
}

/** 채굴에 무기 장착 중이면 .시전 · .보호 차단 */
if (!function_exists('채굴_무기장착_차단문구')) {
  function 채굴_무기장착_차단문구($닉) {
    if (!function_exists('mining_weapon_equipped')) {
      require_once __DIR__ . '/game/mining_weapon.inc.php';
    }
    if (!mining_weapon_equipped($닉)) {
      return '';
    }
    return "❌ 채굴에 무기를 장착 중이에요.\n채굴 화면에서 해제 후 .시전 · .보호를 사용할 수 있어요.";
  }
}

/** 단소·활·마법 일일 한도 외 시전 상한 (지정 시전 포함, extra_uses 기준). 20강+=제한없음 */
if (!function_exists('무기_한도외_일일상한')) {
  function 무기_한도외_일일상한($강화단계) {
    $강화단계 = (int)$강화단계;
    if ($강화단계 < 1) {
      return 0;
    }
    if ($강화단계 >= 20) {
      return null;
    }
    // 1~14: 강화=횟수, 15~19: 별도 상한
    $maxMap = array(
      1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9,
      10 => 10, 11 => 11, 12 => 12, 13 => 13, 14 => 14,
      15 => 100, 16 => 200, 17 => 300, 18 => 500, 19 => 700,
    );
    return (int)($maxMap[$강화단계] ?? 0);
  }
}

/** 가입 3일 미만 신입 — 단소·활·마법 공격(시전) 대상에서 제외 */
if (!function_exists('신입_공격면역_일수')) {
  function 신입_공격면역_일수() {
    return 3;
  }
}

/** 입장(regdate) 후 퇴근 가능까지 필요한 시간(시간) */
if (!function_exists('퇴근_대기시간_시간')) {
  function 퇴근_대기시간_시간() {
    return 24;
  }
}

/** 입장 후 24시간 미만이면 true (퇴근 불가) */
if (!function_exists('퇴근_24시간_미충족')) {
  function 퇴근_24시간_미충족($regdate) {
    $regdate = trim((string)$regdate);
    if ($regdate === '') {
      return true;
    }
    $가입시각 = strtotime($regdate);
    if ($가입시각 === false) {
      return true;
    }
    return $가입시각 > strtotime('-' . 퇴근_대기시간_시간() . ' hours');
  }
}

/** 퇴근 불가 시 안내 문구 */
if (!function_exists('퇴근_24시간_안내')) {
  function 퇴근_24시간_안내($regdate) {
    $regdate = trim((string)$regdate);
    $가능시각 = '';
    if ($regdate !== '') {
      $가입시각 = strtotime($regdate);
      if ($가입시각 !== false) {
        $가능시각 = date('m/d H:i', $가입시각 + 퇴근_대기시간_시간() * 3600);
      }
    }
    $msg = '❌ 입장 후 ' . 퇴근_대기시간_시간() . '시간이 지나야 퇴근할 수 있어요.';
    if ($가능시각 !== '') {
      $msg .= "\n({$가능시각} 부터 가능)";
    }
    return $msg;
  }
}

if (!function_exists('신입_공격면역')) {
  function 신입_공격면역($regdate) {
    $regdate = trim((string)$regdate);
    if ($regdate === '') {
      return false;
    }
    $가입시각 = strtotime($regdate);
    if ($가입시각 === false) {
      return false;
    }
    return $가입시각 > strtotime('-' . 신입_공격면역_일수() . ' days');
  }
}

if (!function_exists('무기_한도외_상태')) {
  function 무기_한도외_상태($강화단계, $오늘과다횟수) {
    $강화단계 = (int)$강화단계;
    $오늘과다횟수 = (int)$오늘과다횟수;
    if ($강화단계 < 1) {
      return array('allow_pay' => false, 'exhausted' => false, 'max' => 0, 'remain' => 0, 'unlimited' => false);
    }
    if ($강화단계 >= 20) {
      return array('allow_pay' => true, 'exhausted' => false, 'max' => null, 'remain' => null, 'unlimited' => true);
    }
    $max = 무기_한도외_일일상한($강화단계);
    if ($max <= 0) {
      return array('allow_pay' => false, 'exhausted' => false, 'max' => 0, 'remain' => 0, 'unlimited' => false);
    }
    $remain = max(0, $max - $오늘과다횟수);
    return array(
      'allow_pay' => ($오늘과다횟수 < $max),
      'exhausted' => ($오늘과다횟수 >= $max),
      'max' => $max,
      'remain' => $remain,
      'unlimited' => false,
    );
  }
}

if (!function_exists('무기_한도외_잔여문구')) {
  function 무기_한도외_잔여문구($한도외상태) {
    if (!empty($한도외상태['unlimited'])) {
      return '';
    }
    if ((int)($한도외상태['max'] ?? 0) <= 0) {
      return '';
    }
    return ' (오늘 한도 외 '.(int)$한도외상태['remain'].'/'.$한도외상태['max'].'회)';
  }
}

/** 단소·활·마법·보호 한도 외 시전 기준 단가 (전체 게임냥의 0.00000001%) */
if (!function_exists('무기_한도외_기준단가')) {
  function 무기_한도외_기준단가() {
    return (int)전체냥기준금액(0.00000001);
  }
}

/** 단소·활·마법·보호 한도 외 시전 1회 비용 (등차: 1회 1×, 2회 2×, 3회 3×… 기준단가) */
if (!function_exists('무기_한도외_시전비용')) {
  function 무기_한도외_시전비용($오늘과다횟수) {
    return 무기_한도외_기준단가() * ((int)$오늘과다횟수 + 1);
  }
}

/** 한도 외 연속 N회 총 비용 (오늘과다횟수=차감 전 extra_uses) */
if (!function_exists('무기_한도외_시전총비용')) {
  function 무기_한도외_시전총비용($오늘과다횟수, $횟수) {
    $오늘과다횟수 = (int)$오늘과다횟수;
    $횟수 = (int)$횟수;
    if ($횟수 <= 0) {
      return 0;
    }
    $기준단가 = 무기_한도외_기준단가();
    $첫회비용 = $기준단가 * ($오늘과다횟수 + 1);
    $공차 = $기준단가;
    return (int)floor($횟수 * (2 * $첫회비용 + ($횟수 - 1) * $공차) / 2);
  }
}

/** 관리자 전용 명령 — 비관리자는 무응답 exit */
if (!function_exists('관리자전용_확인')) {
  function 관리자전용_확인($두자리닉넴, $관리자) {
    if (!in_array((string)$두자리닉넴, (array)$관리자, true)) {
      exit;
    }
  }
}

/** 단소·활·마법 한도 외 연속 시전 1회 명령 상한 (15~17=5, 18~19=10, 20+=15) */
if (!function_exists('무기_한도외_배치상한')) {
  function 무기_한도외_배치상한($강화단계) {
    $강화단계 = (int)$강화단계;
    if ($강화단계 < 15) {
      return null;
    }
    if ($강화단계 <= 17) {
      return 5;
    }
    if ($강화단계 <= 19) {
      return 10;
    }
    return 15;
  }
}

/** 단소·활·마법 연속 시전 시 한도 외 포함 횟수가 배치 상한을 넘는지 검증 */
if (!function_exists('무기_시전_한도외배치_검증')) {
  function 무기_시전_한도외배치_검증($내무기, $강화단계, $시전횟수, $시전자닉, $쿨타임시간 = 3) {
    $배치상한 = 무기_한도외_배치상한($강화단계);
    if ($배치상한 === null || (int)$시전횟수 <= 1) {
      return array('ok' => true);
    }
    $무기_trim = trim((string)$내무기);
    $한도외무기 = ($무기_trim === '🪈단소' || $무기_trim === '🏹활' || $무기_trim === '🏹 활'
      || $무기_trim === '🪄마법' || $무기_trim === '🪄 마법');
    if (!$한도외무기) {
      return array('ok' => true);
    }
    $구간한도 = 무기_시전한도_3시간($내무기, (int)$강화단계);
    if ($구간한도 <= 0) {
      return array('ok' => true);
    }
    $esc = addslashes(trim((string)$시전자닉));
    $행 = db_select("SELECT magic_used, magic_window FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    $사용 = 0;
    $구간시작 = is_array($행) ? ($행['magic_window'] ?? null) : null;
    if (is_array($행)) {
      $사용 = (int)($행['magic_used'] ?? 0);
    }
    $지금 = time();
    $쿨 = max(1, (int)$쿨타임시간);
    if ($구간시작 === null || $구간시작 === '' || $지금 >= strtotime($구간시작) + ($쿨 * 3600)) {
      $사용 = 0;
    }
    if ($사용 > $구간한도) {
      $사용 = $구간한도;
    }
    $남은무료 = max(0, $구간한도 - $사용);
    $필요한도외 = max(0, (int)$시전횟수 - $남은무료);
    if ($필요한도외 <= 0) {
      return array('ok' => true);
    }
    if ($필요한도외 > $배치상한) {
      $무기표시맵 = array(
        '🪈단소' => '단소',
        '🏹활' => '활',
        '🏹 활' => '활',
        '🪄마법' => '마법',
        '🪄 마법' => '마법',
      );
      $무기표시 = $무기표시맵[$무기_trim] ?? '무기';
      return array(
        'ok' => false,
        'msg' => "❌ +{$강화단계} {$무기표시} 한도 외 연속 시전은 1회에 최대 {$배치상한}회까지 가능해요.\n".
                 "({$배치상한}회씩 나눠서 시전해주세요)\n".
                 "예) .시전 {$배치상한} / .시전 닉네임 {$배치상한}",
      );
    }
    return array('ok' => true);
  }
}

/** 한도 외 냥 차감 직후 안내 (사용횟수/상한). 보호·단소·활·마법 공통 */
if (!function_exists('무기_한도외_사용표시문구')) {
  function 무기_한도외_사용표시문구($강화단계, $오늘사용횟수) {
    $오늘사용횟수 = (int)$오늘사용횟수;
    $상태 = 무기_한도외_상태($강화단계, $오늘사용횟수);
    if (!empty($상태['unlimited'])) {
      return ' · 오늘 한도 외 '.$오늘사용횟수.'회';
    }
    if ((int)($상태['max'] ?? 0) > 0) {
      return ' · 오늘 한도 외 '.$오늘사용횟수.'/'.$상태['max'].'회';
    }
    return '';
  }
}

/** .출석포기 — attendance=2, 기본냥(newpoint) 1%·게임냥(point) 1% 차감 (타수 변경 없음) ($사유는 DB 미저장·응답 표시용) */
if (!function_exists('출석포기_적용')) {
  function 출석포기_적용($닉, $사유 = '') {
    $닉_esc = addslashes(trim((string)$닉));
    if ($닉_esc === '') {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.'];
    }
    $기존 = db_select("SELECT attendance, newpoint, point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($기존)) {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.'];
    }
    if ((int)($기존['attendance'] ?? 0) === 2) {
      return [
        'ok' => true,
        'msg' => "✅ 이미 출석포기 상태예요.\n100타 달성 시 출석·누적·전체출석냥 정상 적용됩니다.",
      ];
    }

    $기본냥 = max(0, (float)($기존['newpoint'] ?? 0));
    $게임냥 = max(0, (int)($기존['point'] ?? 0));
    $기본차감 = (int)floor($기본냥 * 0.01);
    $게임차감 = $게임냥 > 0 ? (int)floor($게임냥 * 0.01) : 0;

    $set = ['attendance = 2'];
    if ($기본차감 > 0) {
      $set[] = "newpoint = newpoint - {$기본차감}";
    }
    if ($게임차감 > 0) {
      $set[] = "point = point - {$게임차감}";
    }
    db_query("UPDATE tb_member SET " . implode(', ', $set) . " WHERE name = '{$닉_esc}'");

    $차감문구 = '';
    if ($기본차감 > 0) {
      $차감문구 .= "\n기본냥 -" . newpoint표시($기본차감) . "냥 (보유 1%)"
        . "\n" . newpoint표시($기본냥) . "냥 → " . newpoint표시($기본냥 - $기본차감) . "냥";
    }
    if ($게임차감 > 0) {
      지급로그('출석포기|게임냥', $닉, $닉, 0, $게임차감);
      $차감문구 .= "\n게임냥 -" . number_format($게임차감) . "냥 (보유 1%)"
        . "\n" . number_format($게임냥) . "냥 → " . number_format($게임냥 - $게임차감) . "냥";
    }
    if ($차감문구 === '') {
      $차감문구 = "\n(차감할 냥 없음 — 기본·게임냥 0%)";
    }

    $사유표시 = trim((string)$사유) !== '' ? "\n📝 사유: " . trim((string)$사유) : '';
    return [
      'ok' => true,
      'msg' => "✅ 출석포기 처리 완료{$사유표시}{$차감문구}\n100타 달성 시 출석·누적·전체출석냥 정상 적용됩니다.",
    ];
  }
}

/** .강제출석 — 관리자용: 대상 닉 출석 기록 (냥 미지급, 연속출석 0 초기화) */
if (!function_exists('강제출석_적용')) {
  function 강제출석_적용($대상닉) {
    $대상닉 = trim((string)$대상닉);
    if ($대상닉 === '') {
      return ['ok' => false, 'msg' => "❌ 사용법: .강제출석 [닉네임] (예: .강제출석 민희)"];
    }
    $대상_esc = addslashes($대상닉);
    $대상_정보 = db_select("SELECT * FROM tb_member WHERE name = '{$대상_esc}' AND status = 0 LIMIT 1");
    if (empty($대상_정보) || !isset($대상_정보['idx'])) {
      return ['ok' => false, 'msg' => "❌ 회원을 찾을 수 없어요. (닉네임: {$대상닉})"];
    }
    $오늘 = date('Y-m-d');
    $기출석 = db_select("SELECT COUNT(*) AS cnt FROM tb_attendance WHERE regdate = '{$오늘}' AND nickname = '{$대상_esc}'");
    if ((int)($기출석['cnt'] ?? 0) > 0) {
      return ['ok' => true, 'msg' => "✅ {$대상닉}님은 이미 오늘 출석 완료했어요!"];
    }
    $대상_계급 = 계급($대상_정보['point']);
    db_query("INSERT INTO tb_attendance SET regdate = '{$오늘}', nickname = '{$대상_esc}'");
    db_query("UPDATE tb_member SET streak = 0, last_att_date = '{$오늘}', attendance = 1 WHERE name = '{$대상_esc}'");
    return [
      'ok' => true,
      'msg' => "👑 강제출석 처리 완료!\n{$대상_계급['name']} {$대상닉} 출석 처리됨 🎉\n(냥 지급 없음, 연속출석 0으로 초기화)",
    ];
  }
}

/** 고정 시세 변동 제외 아이템 */
if (!function_exists('아이템_동적시세_제외')) {
  function 아이템_동적시세_제외($아이템sname) {
    $제외 = ['공커대실권', '일방신청권', '일방연장권'];
    return in_array(trim((string)$아이템sname), $제외, true);
  }
}

/** 정상 회원 전체 게임냥(point) 합계 */
if (!function_exists('아이템_총게임냥')) {
  /** @return int|string PHP_INT_MAX 초과 시 문자열 */
  function 아이템_총게임냥() {
    $s = function_exists('시세기준_게임냥_문자열')
      ? 시세기준_게임냥_문자열()
      : 냥_정수문자열(시세기준_게임냥());
    if (function_exists('bccomp') && bccomp($s, (string)PHP_INT_MAX, 0) > 0) {
      return $s;
    }
    return max(0, (int)$s);
  }
}

/** 구매가 억 단위 절사 — 1억 미만 0, 억 아래 자릿수 버림 (표시·실제 차감 공통) */
if (!function_exists('아이템_구매단가_억절사')) {
  /** @param int|string $금액 */
  function 아이템_구매단가_억절사($금액) {
    $s = 냥_정수문자열($금액);
    if (function_exists('bccomp') && function_exists('bcdiv') && function_exists('bcmul')) {
      if (bccomp($s, '100000000', 0) < 0) {
        return 0;
      }
      $억수 = bcdiv($s, '100000000', 0);
      $결과 = bcmul($억수, '100000000', 0);
      if (bccomp($결과, (string)PHP_INT_MAX, 0) <= 0) {
        return (int)$결과;
      }
      return $결과;
    }
    $n = (int)$s;
    if ($n < 100000000) {
      return 0;
    }
    return intdiv($n, 100000000) * 100000000;
  }
}

/**
 * tb_item.percent — 전체 총 게임냥 100% 중 해당 아이템 비율
 * 예) 총게임냥 100, percent 50 → 구매가 50 (1억 미만이면 0)
 * 계산 후 억 단위 절사 — 48조4,370억26백… → 실제 48조4,370억 차감
 * percent 미설정(0 이하)이면 buy 컬럼 fallback
 */
if (!function_exists('아이템_구매단가_계산')) {
  /** @param int|string|null $총게임냥 */
  function 아이템_구매단가_계산(array $itemRow, $총게임냥 = null) {
    $percent = (float)($itemRow['percent'] ?? 0);
    if ($percent > 0) {
      if ($총게임냥 === null) {
        $총게임냥 = 아이템_총게임냥();
      }
      $총 = 냥_정수문자열($총게임냥);
      if (function_exists('bcmul') && function_exists('bcdiv')) {
        $원가 = bcdiv(bcmul($총, sprintf('%.12F', $percent), 12), '100', 0);
      } else {
        $원가 = (string)(int)round((float)$총 * $percent / 100);
      }
      return 아이템_구매단가_억절사($원가);
    }
    return max(0, (int)($itemRow['buy'] ?? 0));
  }
}

if (!function_exists('아이템_percent_시세여부')) {
  function 아이템_percent_시세여부(array $itemRow): bool {
    return (float)($itemRow['percent'] ?? 0) > 0;
  }
}

if (!function_exists('아이템_percent_시세여부_sname')) {
  function 아이템_percent_시세여부_sname(string $sname): bool {
    $sname_esc = addslashes(trim($sname));
    if ($sname_esc === '') {
      return false;
    }
    $row = db_select("SELECT percent FROM tb_item WHERE sname = '{$sname_esc}' LIMIT 1");
    return (float)($row['percent'] ?? 0) > 0;
  }
}

if (!function_exists('아이템_시세_조회_by_sname')) {
  function 아이템_시세_조회_by_sname(string $sname): int {
    $sname_esc = addslashes(trim($sname));
    if ($sname_esc === '') {
      return 0;
    }
    $row = db_select("SELECT buy, percent FROM tb_item WHERE sname = '{$sname_esc}' AND buystatus = 0 LIMIT 1");
    if (empty($row)) {
      return 0;
    }
    return 아이템_구매단가_계산($row);
  }
}

if (!function_exists('아이템_구매시세_단가')) {
  function 아이템_구매시세_단가(string $itemName, array $itemRow): int {
    if ($itemName === '일방신청권') {
      return (int)전체냥기준금액(0.01, true);
    }
    if ($itemName === '일방연장권') {
      return (int)냥_앞두자리_뒤0(전체냥기준금액(0.01, true) * 7);
    }
    return 아이템_구매단가_계산($itemRow);
  }
}

/** `.구매` 시세 목록 — 구매가 높은 순 */
if (!function_exists('아이템_구매시세_목록_문구')) {
  function 아이템_구매시세_목록_문구($단위 = '냥'): string {
    $시세_rs = db_query("SELECT sname, buy, percent FROM tb_item WHERE buystatus = 0");
    $시세_목록 = [];
    while ($시세_item = db_fetch($시세_rs)) {
      $itemName = trim((string)($시세_item['sname'] ?? ''));
      if ($itemName === '' || $itemName === '공커대실권') {
        continue;
      }
      $시세_목록[] = [
        'name' => $itemName,
        'buy' => 아이템_구매시세_단가($itemName, $시세_item),
      ];
    }
    usort($시세_목록, static function ($a, $b) {
      return ($b['buy'] <=> $a['buy']);
    });

    $시세_msg = "🛒 구매 가능 아이템\n\n";
    foreach ($시세_목록 as $시세_item) {
      $시세_msg .= $시세_item['name'] . ' : ' . 구매가_축약표시($시세_item['buy'], $단위) . "<br>";
    }
    $시세_msg .= "\n예) .구매 강일 / .구매 강일 5";
    return $시세_msg;
  }
}

/** .구매 진입 시 사용 완료(status=1) 보유 아이템 행 삭제 */
if (!function_exists('구매_사용완료아이템_삭제')) {
  function 구매_사용완료아이템_삭제() {
    db_query('DELETE FROM tb_member_item WHERE status = 1');
  }
}

/**
 * 채팅 `.구매` 와 동일한 상점 구매 (웹·API 공용)
 * @return array{ok:bool,msg:string,구매단가?:int,총구매액?:int,구매수량?:int,소량추가금?:int,point?:int}
 */
if (!function_exists('아이템_상점구매_실행')) {
  function 아이템_상점구매_실행($두자리닉넴, array $정보, $아이템명, $구매수량) {
    global $단위;

    $아이템명 = trim((string)$아이템명);
    $구매수량 = max(1, (int)$구매수량);
    if ($아이템명 === '') {
      return ['ok' => false, 'msg' => '❌ 아이템명을 확인해주세요.'];
    }

    if (function_exists('구매_사용완료아이템_삭제')) {
      구매_사용완료아이템_삭제();
    }

    $구매닉 = trim((string)($정보['name'] ?? $두자리닉넴));
    if ($구매닉 === '') {
      $구매닉 = trim((string)$두자리닉넴);
    }
    $닉_esc = addslashes($구매닉);
    $아이템_esc = addslashes($아이템명);
    $구매아이템행 = db_select("
      SELECT idx, sname, buy, percent, buystatus
      FROM tb_item
      WHERE sname = '{$아이템_esc}' AND buystatus = 0
      LIMIT 1
    ");
    if (empty($구매아이템행['idx'])) {
      return ['ok' => false, 'msg' => '❌ 구매할 수 없거나 없는 아이템이에요.'];
    }

    $커플 = null;
    if ($아이템명 === '공커대실권') {
      $커플 = db_select("SELECT * FROM tb_couple WHERE couple LIKE '%{$닉_esc}%'");
      if (empty($커플['idx'])) {
        return ['ok' => false, 'msg' => '❌ 정보가 없어 공커대실권을 구매할 수 없습니다.'];
      }
      $커플_amount = (int)($커플['amount'] ?? 0);
      if ($커플_amount < 1) {
        return ['ok' => false, 'msg' => '❌ 가격이 설정되지 않아 공커대실권을 구매할 수 없습니다.'];
      }
    }

    if ($아이템명 === '공커대실권') {
      $구매단가 = (int)(ceil($커플_amount / 1000) * 1000);
      $총구매액 = $구매단가 * $구매수량;
    } elseif ($아이템명 === '일방신청권') {
      $구매단가 = 전체냥기준금액(0.01, true);
      $총구매액 = $구매단가 * $구매수량;
    } elseif ($아이템명 === '일방연장권') {
      $구매단가 = 냥_앞두자리_뒤0(전체냥기준금액(0.01, true) * 7);
      $총구매액 = $구매단가 * $구매수량;
    } else {
      $구매단가 = 아이템_구매단가_계산($구매아이템행);
      if ($구매단가 <= 0 && 아이템_percent_시세여부($구매아이템행)) {
        return ['ok' => false, 'msg' => "❌ 현재 총 게임냥 기준 시세가 1억 미만이라 {$아이템명}을(를) 구매할 수 없어요."];
      }
      $총구매액 = $구매단가 * $구매수량;
    }

    $소량추가금 = 0;
    if ($구매수량 < 10) {
      $소량추가금 = (int)ceil($총구매액 * 0.01);
      $총구매액 += $소량추가금;
    }

    $내idx = (int)($정보['idx'] ?? 0);
    if ($내idx <= 0) {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.'];
    }

    $보유행 = db_select("SELECT point FROM tb_member WHERE idx = {$내idx} LIMIT 1");
    $보유냥 = (int)($보유행['point'] ?? 0);
    if ($보유냥 < $총구매액) {
      if ($아이템명 === '공커대실권') {
        $단가표시 = (int)(ceil($커플_amount / 1000) * 1000);
      } elseif ($아이템명 === '일방신청권') {
        $단가표시 = 전체냥기준금액(0.01, true);
      } elseif ($아이템명 === '일방연장권') {
        $단가표시 = 냥_앞두자리_뒤0(전체냥기준금액(0.01, true) * 7);
      } else {
        $단가표시 = 아이템_구매단가_계산($구매아이템행);
      }
      return [
        'ok' => false,
        'msg' => "‼️보유 {$단위} 부족!\n{$아이템명} : " . 구매가_축약표시($총구매액, $단위)
          . " (단가 " . 구매가_축약표시($단가표시, $단위) . " × {$구매수량})\n현재 보유 : " . 구매가_축약표시($보유냥, $단위),
      ];
    }

    if ($아이템명 === '지호') {
      $지호보유한도 = 100000;
      $지호보유행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$내idx} AND itemname = '지호' AND status = 0");
      $지호보유개수 = (int)($지호보유행['cnt'] ?? 0);
      if ($지호보유개수 + $구매수량 > $지호보유한도) {
        $더구매가능 = max(0, $지호보유한도 - $지호보유개수);
        return [
          'ok' => false,
          'msg' => "❌ 지호 아이템은 최대 " . number_format($지호보유한도) . "개까지만 보유할 수 있어요.\n"
            . "현재 보유: " . number_format($지호보유개수) . "개 · 이번 구매 {$구매수량}개는 불가 (추가로 최대 " . number_format($더구매가능) . "개까지 구매 가능)",
        ];
      }
    }

    $itemStatus = 0;
    if ($아이템명 === '공커대실권') {
      if (!empty($커플['idx']) && !empty($커플['edate'])) {
        $연장기간 = 7 * $구매수량;
        $칠일연장 = date('Y-m-d', strtotime($커플['edate'] . " +{$연장기간} days"));
        db_query("UPDATE tb_couple SET edate = '{$칠일연장}' WHERE idx = {$커플['idx']}");
      }
      $itemStatus = 1;
    } elseif ($아이템명 === '일방신청권') {
      $itemStatus = 1;
    } elseif ($아이템명 === '일방연장권') {
      $itemStatus = 1;
      $progress = db_select("SELECT * FROM tb_progress WHERE nick LIKE '%{$닉_esc}%'");
      if (!empty($progress['idx'])) {
        $progress['enddate'] = date('Y-m-d', strtotime($progress['enddate'] . ' +7 days'));
        $닉등록 = $progress['nick'] . '1️⃣';
        db_query("UPDATE tb_progress SET enddate = '{$progress['enddate']}', nick = '{$닉등록}' WHERE idx = {$progress['idx']}");
      }
    }

    $지급sname_esc = addslashes(trim((string)$구매아이템행['sname']));
    for ($gi = 0; $gi < $구매수량; $gi++) {
      db_query("
        INSERT INTO tb_member_item
        SET midx = {$내idx}, nick = '{$닉_esc}', status = {$itemStatus}, itemname = '{$지급sname_esc}', regdate = NOW()
      ");
    }
    if ($아이템명 === '일방신청권') {
      if (function_exists('일방신청권_지급기록')) {
        일방신청권_지급기록($구매닉, '구매', [
          'midx' => $내idx,
          'reason_text' => '상점 구매',
          'qty' => $구매수량,
        ]);
      }
      if (function_exists('미션완료_기록_if_new')) {
        미션완료_기록_if_new($구매닉, '일방', '400타');
      }
    }
    db_query("UPDATE tb_member SET point = point - {$총구매액} WHERE idx = {$내idx}");
    if (function_exists('아이템구매_시세상승')) {
      아이템구매_시세상승($아이템명, $구매단가, $구매수량);
    }

    $잔여행 = db_select("SELECT point FROM tb_member WHERE idx = {$내idx} LIMIT 1");
    $수량표시 = ($구매수량 > 1) ? " {$구매수량}개" : '';
    $추가금문구 = ($소량추가금 > 0) ? "\n(10개 미만 1% 추가금: " . 구매가_축약표시($소량추가금, $단위) . ")" : '';

    return [
      'ok' => true,
      'msg' => "►[{$구매닉}] {$아이템명}{$수량표시} 구매 " . 구매가_축약표시($총구매액, $단위) . $추가금문구,
      '구매단가' => $구매단가,
      '총구매액' => $총구매액,
      '구매수량' => $구매수량,
      '소량추가금' => $소량추가금,
      'point' => (int)($잔여행['point'] ?? 0),
    ];
  }
}

/** 지갑·웹 상점 — 구매 가능 목록 */
if (!function_exists('아이템_상점구매_목록')) {
  function 아이템_상점구매_목록(): array {
    global $단위;
    $목록 = [];
    $rs = db_query("SELECT sname, buy, percent FROM tb_item WHERE buystatus = 0");
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $name = trim((string)($row['sname'] ?? ''));
        if ($name === '' || $name === '공커대실권') {
          continue;
        }
        $price = 아이템_구매시세_단가($name, $row);
        $목록[] = [
          'name' => $name,
          'price' => $price,
          'price_fmt' => function_exists('구매가_축약표시') ? 구매가_축약표시($price, $단위) : number_format($price),
          'buyable' => !($price <= 0 && 아이템_percent_시세여부($row)),
        ];
      }
    }
    usort($목록, static function ($a, $b) {
      return ($b['price'] <=> $a['price']);
    });
    return $목록;
  }
}

/** 보유 아이템 (status=0) 집계 + 1개당 판매 실수령가 */
if (!function_exists('아이템_보유목록_판매표시')) {
  function 아이템_보유목록_판매표시(array $정보): array {
    $midx = (int)($정보['idx'] ?? 0);
    $목록 = 아이템_보유목록_집계($midx);
    if (!$목록 || !function_exists('아이템_상점판매_견적')) {
      return $목록;
    }
    $result = [];
    foreach ($목록 as $it) {
      $row = $it;
      $quote = 아이템_상점판매_견적($정보, $it['name'], 1);
      if (!empty($quote['ok'])) {
        $row['sell_price'] = (int)($quote['실수령'] ?? 0);
        $row['sell_price_fmt'] = (string)($quote['실수령_fmt'] ?? '');
        $cnt = (int)($it['count'] ?? 1);
        if ($cnt > 1) {
          $all = 아이템_상점판매_견적($정보, $it['name'], $cnt);
          if (!empty($all['ok'])) {
            $row['sell_all_fmt'] = (string)($all['실수령_fmt'] ?? '');
          }
        }
      }
      $result[] = $row;
    }
    return $result;
  }
}

/** 보유 아이템 (status=0) 집계 */
if (!function_exists('아이템_보유목록_집계')) {
  function 아이템_보유목록_집계(int $midx): array {
    if ($midx < 1) {
      return [];
    }
    $rs = db_query("
      SELECT itemname, COUNT(*) AS cnt
      FROM tb_member_item
      WHERE midx = {$midx} AND status = 0
      GROUP BY itemname
      ORDER BY itemname ASC
    ");
    $목록 = [];
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $name = trim((string)($row['itemname'] ?? ''));
        if ($name === '') {
          continue;
        }
        $목록[] = [
          'name' => $name,
          'count' => (int)($row['cnt'] ?? 0),
        ];
      }
    }
    return $목록;
  }
}

/** 채팅 `.판매` 와 동일한 판매가·수수료 견적 */
if (!function_exists('아이템_상점판매_견적')) {
  function 아이템_상점판매_견적(array $정보, string $아이템명, int $수량 = 1): array {
    global $일반판매수수료율, $전체포인트;

    $아이템명 = trim($아이템명);
    $수량 = max(1, (int)$수량);
    $midx = (int)($정보['idx'] ?? 0);
    if ($midx < 1 || $아이템명 === '') {
      return ['ok' => false, 'msg' => '❌ 상품 정보를 확인할 수 없어요.'];
    }

    $보유개수 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$midx} AND itemname = '" . addslashes($아이템명) . "' AND status = 0");
    $보유 = (int)($보유개수['cnt'] ?? 0);
    if ($보유 < $수량) {
      return ['ok' => false, 'msg' => "{$아이템명} 보유수량 {$보유}개"];
    }

    $item = db_select("SELECT * FROM tb_item WHERE sname = '" . addslashes($아이템명) . "' LIMIT 1");
    if (empty($item['idx'])) {
      return ['ok' => false, 'msg' => "❌ [ {$아이템명} ] 존재하지 않는 아이템이에요."];
    }

    $판매기준가 = db_select("
      SELECT COALESCE(SUM(t.sell), 0) AS total_sell
      FROM (
        SELECT i.sell
        FROM tb_member_item AS mi
        JOIN tb_item AS i ON mi.itemname = i.sname
        WHERE mi.midx = {$midx}
          AND mi.itemname = '" . addslashes($아이템명) . "'
          AND mi.status = 0
        LIMIT {$수량}
      ) AS t
    ");
    $basePrice = (int)($판매기준가['total_sell'] ?? 0);

    $userNyung = (int)($정보['point'] ?? 0);
    $personalMultiplier = function_exists('가격계산') ? 가격계산($userNyung) : 1.0;
    $totalNyung = (float)($전체포인트['total_point'] ?? 0);
    $baseMarketNyung = 10000000;
    $marketMultiplier = log10(($totalNyung / $baseMarketNyung) + 1) * 0.1 + 1;
    $marketMultiplier = max(0.9, min($marketMultiplier, 1.2));

    if (!empty($item['status'])) {
      $finalPrice = $basePrice;
    } else {
      $finalPrice = round($basePrice * $marketMultiplier * (1 + ($personalMultiplier - 1) * 0.5));
      $minPrice = $basePrice * 0.8;
      $maxPrice = $basePrice * 1.5;
      $finalPrice = max($minPrice, min($finalPrice, $maxPrice));
    }

    $판매금액 = (int)(ceil($finalPrice / 100) * 100);

    $닉 = trim((string)($정보['name'] ?? ''));
    $지호사용 = db_select("SELECT idx FROM tb_item_use WHERE nickname = '" . addslashes($닉) . "' AND item = '지호' LIMIT 1");
    $판매수수료율 = !empty($지호사용['idx']) ? 15 : (int)$일반판매수수료율;

    $수수료 = (int)round($판매금액 * ($판매수수료율 / 100));
    $수수료뺀금액 = $판매금액 - $수수료;
    $지급컬럼 = 아이템판매_지급컬럼($item);

    return [
      'ok' => true,
      '아이템명' => $아이템명,
      '수량' => $수량,
      '판매금액' => $판매금액,
      '수수료' => $수수료,
      '수수료율' => $판매수수료율,
      '실수령' => $수수료뺀금액,
      '지급컬럼' => $지급컬럼,
      '실수령_fmt' => 아이템판매_금액표시($수수료뺀금액, $지급컬럼),
      '수수료_fmt' => 아이템판매_금액표시($수수료, $지급컬럼),
    ];
  }
}

/** 채팅 `.판매` 와 동일한 아이템 판매 */
if (!function_exists('아이템_상점판매_실행')) {
  function 아이템_상점판매_실행($두자리닉넴, array $정보, string $아이템명, int $수량 = 1): array {
    global $단위;

    $견적 = 아이템_상점판매_견적($정보, $아이템명, $수량);
    if (empty($견적['ok'])) {
      return ['ok' => false, 'msg' => $견적['msg'] ?? '❌ 판매할 수 없어요.'];
    }

    $midx = (int)($정보['idx'] ?? 0);
    $아이템명 = $견적['아이템명'];
    $수량 = (int)$견적['수량'];
    $수수료 = (int)$견적['수수료'];
    $수수료뺀금액 = (int)$견적['실수령'];
    $지급컬럼 = $견적['지급컬럼'];
    $닉 = trim((string)($정보['name'] ?? $두자리닉넴));

    $rs = db_query("
      SELECT mi.idx
      FROM tb_member_item AS mi
      JOIN tb_item AS i ON mi.itemname = i.sname
      WHERE mi.midx = {$midx}
        AND mi.itemname = '" . addslashes($아이템명) . "'
        AND mi.status = 0
      ORDER BY mi.idx ASC
      LIMIT {$수량}
    ");
    $판매할idx = [];
    if ($rs) {
      while ($템 = db_fetch($rs)) {
        $판매할idx[] = (int)$템['idx'];
      }
    }
    if (count($판매할idx) !== $수량) {
      return ['ok' => false, 'msg' => '❌ 판매 처리 중 오류 (보유 수량 불일치). 다시 시도해주세요.'];
    }

    $idx목록 = implode(',', $판매할idx);
    db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx IN ({$idx목록})");

    $닉_esc = addslashes($닉);
    db_query("UPDATE tb_member SET {$지급컬럼} = {$지급컬럼} + {$수수료뺀금액} WHERE name = '{$닉_esc}' LIMIT 1");
    db_query("UPDATE config SET tax = tax + {$수수료}");
    지급로그('판매', $두자리닉넴, $아이템명, $수수료, $수수료뺀금액);

    $item = db_select("SELECT buy FROM tb_item WHERE sname = '" . addslashes($아이템명) . "' LIMIT 1");
    if (function_exists('아이템판매_시세하락')) {
      아이템판매_시세하락($아이템명, (int)($item['buy'] ?? 0), $수량);
    }

    $보유 = db_select("SELECT point, newpoint FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $수량표시 = ($수량 > 1) ? " {$수량}개" : '';

    return [
      'ok' => true,
      'msg' => "►[{$닉}] {$아이템명}{$수량표시} 판매 " . 아이템판매_금액표시($수수료뺀금액, $지급컬럼) . "{$단위} (수수료 " . 아이템판매_금액표시($수수료, $지급컬럼) . "{$단위})",
      '실수령' => $수수료뺀금액,
      '수수료' => $수수료,
      '지급컬럼' => $지급컬럼,
      'point' => (int)($보유['point'] ?? 0),
      'newpoint' => (float)($보유['newpoint'] ?? 0),
    ];
  }
}

/** `.판매` 시세표에 포함할 고정가 아이템 (tb_item.status != 0) */
if (!function_exists('아이템_판매시세_고정포함목록')) {
  function 아이템_판매시세_고정포함목록(): array {
    return ['1주년기념주화'];
  }
}

/** `.판매` 시세표 조회용 WHERE (동적시세 + 고정포함) */
if (!function_exists('아이템_판매시세_조회조건')) {
  function 아이템_판매시세_조회조건(): string {
    $고정목록 = 아이템_판매시세_고정포함목록();
    $조건 = 'status = 0';
    if (!empty($고정목록)) {
      $in = implode(',', array_map(function ($s) {
        return "'" . addslashes($s) . "'";
      }, $고정목록));
      $조건 .= " OR sname IN ({$in})";
    }
    return $조건;
  }
}

/** 판매 시세표 (채팅 `.판매` 목록과 동일 계산) */
if (!function_exists('아이템_상점판매_시세목록')) {
  function 아이템_상점판매_시세목록(array $정보): array {
    global $일반판매수수료율, $전체포인트;

    $userNyung = (int)($정보['point'] ?? 0);
    $personalMultiplier = function_exists('가격계산') ? 가격계산($userNyung) : 1.0;
    $totalNyung = (float)($전체포인트['total_point'] ?? 0);
    $baseMarketNyung = 10000000;
    $marketMultiplier = log10(($totalNyung / $baseMarketNyung) + 1) * 0.1 + 1;
    $marketMultiplier = max(0.9, min($marketMultiplier, 1.2));

    $목록 = [];
    $조회조건 = 아이템_판매시세_조회조건();
    $rs = db_query("SELECT * FROM tb_item WHERE {$조회조건} ORDER BY sell DESC");
    if ($rs) {
      while ($item = db_fetch($rs)) {
        $itemName = trim((string)($item['sname'] ?? ''));
        if ($itemName === '') {
          continue;
        }
        $basePrice = (int)($item['sell'] ?? 0);
        if (!empty($item['status'])) {
          $finalPrice = $basePrice;
        } else {
          $finalPrice = round($basePrice * $marketMultiplier * (1 + ($personalMultiplier - 1) * 0.5));
          $minPrice = $basePrice * 0.8;
          $maxPrice = $basePrice * 1.5;
          $finalPrice = max($minPrice, min($finalPrice, $maxPrice));
        }
        $finalPrice = (int)(ceil($finalPrice / 100) * 100);
        $지급컬럼 = 아이템판매_지급컬럼($item);
        $목록[] = [
          'name' => $itemName,
          'price' => $finalPrice,
          'price_fmt' => 아이템판매_금액표시($finalPrice, $지급컬럼),
          'unit' => ($지급컬럼 === 'newpoint') ? '본방냥' : '냥',
        ];
      }
    }
    return [
      'items' => $목록,
      'fee_rate' => (int)$일반판매수수료율,
      'jiho_fee_rate' => 15,
    ];
  }
}

/** .구매 시 tb_item.buy 누적 — percent 시세 아이템은 총게임냥 비율로 자동 변동 */
if (!function_exists('아이템구매_시세상승')) {
  function 아이템구매_시세상승($아이템sname, $단가, $수량) {
    $아이템sname = trim((string)$아이템sname);
    if ($아이템sname === '' || 아이템_동적시세_제외($아이템sname) || 아이템_percent_시세여부_sname($아이템sname)) {
      return 0;
    }
    $수량 = max(1, (int)$수량);
    $고정누적맵 = [
      '제한' => 50000,
      '수호' => 50000,
      '강일' => 50000,
      '지목' => 50000,
      '닉변' => 30000,
      '프변' => 30000,
      '색변' => 30000,
      '선물' => 10000,
    ];
    if (isset($고정누적맵[$아이템sname])) {
      // 해당 아이템은 수량과 무관하게 "한 번 구매"당 고정 누적
      $상승 = (int)$고정누적맵[$아이템sname];
    } else {
      $상승 = $수량 * 10000;
    }
    $sname_esc = addslashes($아이템sname);
    db_query("UPDATE tb_item SET buy = buy + {$상승} WHERE sname = '{$sname_esc}' LIMIT 1");
    return $상승;
  }
}

/** 관리자 전용 명령 — 비관리자면 차단 메시지 후 exit */
if (!function_exists('관리자_명령_차단')) {
  function 관리자_명령_차단($두자리닉넴, $nick = '') {
    global $관리자;
    $허용 = in_array($두자리닉넴, (array)$관리자, true)
      || in_array(getTwoCharNick($nick), (array)$관리자, true);
    if ($허용) {
      return true;
    }
    $표시닉 = trim((string)$두자리닉넴);
    echo 전송($표시닉 !== '' ? "{$표시닉} 블랙리스트 등록완료!" : '❌ 관리자만 사용할 수 있는 명령어입니다.');
    exit;
  }
}

/** .생성 — 회원 또는 전체(status=0)에게 tb_member_item 지급 */
if (!function_exists('아이템_생성_지급')) {
  function 아이템_생성_지급($닉네임, $템명, $개수) {
    $개수 = (int)$개수;
    $템명 = trim((string)$템명);
    if ($개수 < 1 || $템명 === '') {
      return ['ok' => false, 'msg' => '❌ 템명·개수를 확인해주세요.'];
    }
    $템_esc = addslashes($템명);
    $닉_trim = trim((string)$닉네임);

    $관리자닉 = '';
    if (isset($GLOBALS['두자리닉넴'])) {
      $관리자닉 = trim((string)$GLOBALS['두자리닉넴']);
    }

    if ($닉_trim === '전체') {
      $rs = db_query("SELECT idx, name FROM tb_member WHERE status = 0");
      $인원 = 0;
      $총생성 = 0;
      while ($rs && $row = db_fetch($rs)) {
        $midx = (int)($row['idx'] ?? 0);
        $name = trim((string)($row['name'] ?? ''));
        if ($midx <= 0 || $name === '') {
          continue;
        }
        $name_esc = addslashes($name);
        $인원++;
        for ($i = 0; $i < $개수; $i++) {
          db_query("
            INSERT INTO tb_member_item
            SET midx = {$midx},
                nick = '{$name_esc}',
                status = 0,
                itemname = '{$템_esc}',
                usedate = '0000-00-00 00:00:00',
                regdate = NOW()
          ");
          $총생성++;
        }
        if ($템명 === '일방신청권' && function_exists('일방신청권_지급기록')) {
          일방신청권_지급기록($name, '관리자생성', [
            'midx' => $midx,
            'from_nick' => $관리자닉,
            'reason_text' => ($관리자닉 !== '') ? "관리자 생성 ({$관리자닉})" : '관리자 생성',
            'qty' => $개수,
          ]);
        }
      }
      if ($인원 < 1) {
        return ['ok' => false, 'msg' => '❌ 지급 대상 회원이 없습니다.'];
      }
      return [
        'ok'  => true,
        'msg' => "📣 전체 인원 {$인원}명에게 {$템명} {$개수}개씩 생성 완료! (총 {$총생성}개)",
      ];
    }

    $닉_esc = addslashes($닉_trim);
    $받는친구 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($받는친구['idx'])) {
      return ['ok' => false, 'msg' => '존재하지 않는 사용자 입니다.'];
    }
    $midx = (int)$받는친구['idx'];
    $nick_esc = addslashes($받는친구['name']);
    for ($i = 0; $i < $개수; $i++) {
      db_query("
        INSERT INTO tb_member_item
        SET midx = {$midx},
            nick = '{$nick_esc}',
            status = 0,
            itemname = '{$템_esc}',
            usedate = '0000-00-00 00:00:00',
            regdate = NOW()
      ");
    }
    if ($템명 === '일방신청권' && function_exists('일방신청권_지급기록')) {
      일방신청권_지급기록($받는친구['name'], '관리자생성', [
        'midx' => $midx,
        'from_nick' => $관리자닉,
        'reason_text' => ($관리자닉 !== '') ? "관리자 생성 ({$관리자닉})" : '관리자 생성',
        'qty' => $개수,
      ]);
    }
    return ['ok' => true, 'msg' => "{$템명} {$개수}개 생성완료"];
  }
}

/** tb_item.type 이 newpoint 이면 newpoint 지급, 그 외 point */
if (!function_exists('아이템판매_지급컬럼')) {
  function 아이템판매_지급컬럼(array $item) {
    return (strtolower(trim((string)($item['type'] ?? ''))) === 'newpoint') ? 'newpoint' : 'point';
  }
}

if (!function_exists('아이템판매_금액표시')) {
  function 아이템판매_금액표시($금액, $지급컬럼) {
    if ($지급컬럼 === 'newpoint') {
      return newpoint표시($금액);
    }
    return number_format((int)$금액);
  }
}

/** .판매 시 아이템별 고정 차감(1회), 그 외는 개수×5천원 차감 */
if (!function_exists('아이템판매_시세하락')) {
  function 아이템판매_시세하락($아이템sname, $구매단가, $수량) {
    $아이템sname = trim((string)$아이템sname);
    if ($아이템sname === '' || 아이템_동적시세_제외($아이템sname) || 아이템_percent_시세여부_sname($아이템sname)) {
      return 0;
    }
    $수량 = max(1, (int)$수량);
    $차감단가맵 = [
      '제한' => 25000,
      '수호' => 25000,
      '강일' => 25000,
      '지목' => 25000,
      '닉변' => 15000,
      '프변' => 15000,
      '색변' => 15000,
      '선물' => 5000,
    ];
    if (isset($차감단가맵[$아이템sname])) {
      // 지정 아이템은 판매 수량과 무관하게 1회 고정 차감
      $차감 = (int)$차감단가맵[$아이템sname];
    } else {
      $차감 = $수량 * 5000;
    }
    $sname_esc = addslashes($아이템sname);
    db_query("UPDATE tb_item SET buy = GREATEST(0, buy - {$차감}) WHERE sname = '{$sname_esc}' LIMIT 1");
    return $차감;
  }
}

/** 아이템 사용 시 아이템별 고정 차감(1회), 그 외는 개수×5천원 차감 (.판매는 아이템판매_시세하락 사용) */
if (!function_exists('아이템사용_시세하락')) {
  function 아이템사용_시세하락($아이템sname, $수량) {
    $아이템sname = trim((string)$아이템sname);
    if ($아이템sname === '' || 아이템_동적시세_제외($아이템sname) || 아이템_percent_시세여부_sname($아이템sname)) {
      return 0;
    }
    $수량 = max(1, (int)$수량);
    $차감단가맵 = [
      '제한' => 25000,
      '수호' => 25000,
      '강일' => 25000,
      '지목' => 25000,
      '닉변' => 15000,
      '프변' => 15000,
      '색변' => 15000,
      '선물' => 5000,
    ];
    if (isset($차감단가맵[$아이템sname])) {
      // 지정 아이템은 사용 수량과 무관하게 1회 고정 차감
      $차감 = (int)$차감단가맵[$아이템sname];
    } else {
      $차감 = $수량 * 5000;
    }
    $sname_esc = addslashes($아이템sname);
    db_query("UPDATE tb_item SET buy = GREATEST(0, buy - {$차감}) WHERE sname = '{$sname_esc}' LIMIT 1");
    return $차감;
  }
}

/** tb_member.code — 강화·홀짝 웹 접속용 6자리 코드 */
if (!function_exists('회원_접속코드_유효')) {
  function 회원_접속코드_유효($code): bool {
    $code = trim((string)$code);
    return $code !== '' && $code !== '0';
  }
}

if (!function_exists('회원_6자리코드_생성')) {
  function 회원_6자리코드_생성(): string {
    $문자숫자 = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    for ($try = 0; $try < 100; $try++) {
      $코드 = '';
      for ($i = 0; $i < 6; $i++) {
        $코드 .= $문자숫자[random_int(0, strlen($문자숫자) - 1)];
      }
      $esc = addslashes($코드);
      $dup = db_select("SELECT idx FROM tb_member WHERE code = '{$esc}' LIMIT 1");
      if (empty($dup['idx'])) {
        return $코드;
      }
    }
    return substr(bin2hex(random_bytes(4)), 0, 6);
  }
}

/** 진행중 투표 1건 조회 */
if (!function_exists('투표_테이블_있음')) {
  function 투표_테이블_있음(): bool {
    static $cached = null;
    if ($cached !== null) {
      return $cached;
    }
    $row = db_select("SHOW TABLES LIKE 'tb_vote'");
    $cached = !empty($row);
    return $cached;
  }
}

if (!function_exists('투표_진행중')) {
  function 투표_진행중(): ?array {
    if (!투표_테이블_있음()) {
      return null;
    }
    $row = db_select('SELECT * FROM tb_vote WHERE status = 1 ORDER BY idx DESC LIMIT 1');
    return !empty($row['idx']) ? $row : null;
  }
}

if (!function_exists('투표_닉목록')) {
  function 투표_닉목록(int $vote_idx, int $choice): string {
    $vote_idx = (int)$vote_idx;
    $choice = (int)$choice;
    $rs = db_query("SELECT nickname FROM tb_vote_ballot WHERE vote_idx = {$vote_idx} AND choice = {$choice} ORDER BY idx ASC");
    $nicks = [];
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $n = trim((string)($row['nickname'] ?? ''));
        if ($n !== '') {
          $nicks[] = $n;
        }
      }
    }
    return $nicks ? implode(', ', $nicks) : '(없음)';
  }
}

if (!function_exists('투표_집계')) {
  function 투표_집계(int $vote_idx): array {
    $vote_idx = (int)$vote_idx;
    $찬성 = db_select("SELECT COUNT(*) AS cnt FROM tb_vote_ballot WHERE vote_idx = {$vote_idx} AND choice = 1");
    $반대 = db_select("SELECT COUNT(*) AS cnt FROM tb_vote_ballot WHERE vote_idx = {$vote_idx} AND choice = 0");
    return [
      '찬성' => (int)($찬성['cnt'] ?? 0),
      '반대' => (int)($반대['cnt'] ?? 0),
    ];
  }
}

if (!function_exists('투표_현황_문구')) {
  function 투표_현황_문구(?array $vote = null, bool $안내문 = true): string {
    if (!투표_테이블_있음()) {
      return "❌ 투표 DB가 아직 없어요.\napi/schema/tb_vote.sql 을 실행해 주세요.";
    }
    $vote = $vote ?? 투표_진행중();
    if (empty($vote['idx'])) {
      return "❌ 진행중인 투표가 없어요.\n새 투표: .투표 (제목)";
    }
    $idx = (int)$vote['idx'];
    $title = trim((string)($vote['title'] ?? ''));
    $집계 = 투표_집계($idx);
    $msg = "📊 투표 진행중: {$title}\n\n";
    $msg .= "찬성 {$집계['찬성']}명 · " . 투표_닉목록($idx, 1) . "\n";
    $msg .= "반대 {$집계['반대']}명 · " . 투표_닉목록($idx, 0);
    if ($안내문) {
      $msg .= "\n\n찬성: .찬성 / 반대: .반대";
    }
    return $msg;
  }
}

if (!function_exists('투표_시작')) {
  function 투표_시작(string $제목, string $개설자): array {
    if (!투표_테이블_있음()) {
      return ['ok' => false, 'msg' => "❌ 투표 DB가 아직 없어요.\napi/schema/tb_vote.sql 을 실행해 주세요."];
    }
    $제목 = trim($제목);
    if ($제목 === '') {
      return ['ok' => false, 'msg' => "❌ 사용법: .투표 (제목)\n예) .투표 방이름변경"];
    }
    if (mb_strlen($제목, 'UTF-8') > 200) {
      return ['ok' => false, 'msg' => '❌ 투표 제목은 200자 이하로 입력해주세요.'];
    }
    if (투표_진행중()) {
      return ['ok' => false, 'msg' => "❌ 이미 진행중인 투표가 있어요.\n.투표 로 현황 확인 · .투표종료 후 새 투표 시작"];
    }
    $제목_esc = addslashes($제목);
    $개설자_esc = addslashes(trim($개설자));
    db_query("INSERT INTO tb_vote SET title = '{$제목_esc}', status = 1, created_by = '{$개설자_esc}', started_at = NOW()");
    $msg = "{$제목}을 찬성하시는분은 .찬성\n반대하시는분은 .반대 를 입력해주세요.";
    return ['ok' => true, 'msg' => $msg];
  }
}

if (!function_exists('투표_표_기록')) {
  function 투표_표_기록(string $닉, int $choice): array {
    $닉 = trim($닉);
    if ($닉 === '') {
      return ['ok' => false, 'msg' => '❌ 회원 닉네임을 확인할 수 없어요.'];
    }
    $vote = 투표_진행중();
    if (!$vote) {
      return ['ok' => false, 'msg' => "❌ 진행중인 투표가 없어요."];
    }
    $vote_idx = (int)$vote['idx'];
    $choice = $choice ? 1 : 0;
    $닉_esc = addslashes($닉);
    $기존 = db_select("SELECT idx, choice FROM tb_vote_ballot WHERE vote_idx = {$vote_idx} AND nickname = '{$닉_esc}' LIMIT 1");
    $표시 = $choice ? '찬성' : '반대';
    if (!empty($기존['idx'])) {
      if ((int)$기존['choice'] === $choice) {
        $집계 = 투표_집계($vote_idx);
        return [
          'ok' => true,
          'msg' => "✅ 이미 {$표시} 투표하셨어요.\n\n" . 투표_현황_문구($vote, false),
        ];
      }
      db_query("UPDATE tb_vote_ballot SET choice = {$choice}, updated_at = NOW() WHERE idx = " . (int)$기존['idx']);
      $이전 = ((int)$기존['choice'] === 1) ? '찬성' : '반대';
      $집계 = 투표_집계($vote_idx);
      return [
        'ok' => true,
        'msg' => "✅ {$이전} → {$표시} 로 변경했어요.\n\n" . 투표_현황_문구($vote, false),
      ];
    }
    db_query("INSERT INTO tb_vote_ballot SET vote_idx = {$vote_idx}, nickname = '{$닉_esc}', choice = {$choice}, regdate = NOW()");
    return [
      'ok' => true,
      'msg' => "✅ {$표시} 투표 완료!\n\n" . 투표_현황_문구($vote, false),
    ];
  }
}

if (!function_exists('투표_종료')) {
  function 투표_종료(string $종료자): array {
    $vote = 투표_진행중();
    if (!$vote) {
      return ['ok' => false, 'msg' => '❌ 진행중인 투표가 없어요.'];
    }
    $idx = (int)$vote['idx'];
    $title = trim((string)($vote['title'] ?? ''));
    $집계 = 투표_집계($idx);
    $종료자_esc = addslashes(trim($종료자));
    db_query("UPDATE tb_vote SET status = 0, ended_at = NOW(), ended_by = '{$종료자_esc}' WHERE idx = {$idx} LIMIT 1");
    $msg = "✅ [{$title}] 투표 종료!\n\n";
    $msg .= "찬성 {$집계['찬성']}명 · " . 투표_닉목록($idx, 1) . "\n";
    $msg .= "반대 {$집계['반대']}명 · " . 투표_닉목록($idx, 0);
    $msg .= "\n\n새 투표: .투표 (제목)";
    return ['ok' => true, 'msg' => $msg];
  }
}

if (!function_exists('회원_접속코드_발급')) {
  /** 유효 code 있으면 반환, 없으면 6자리 생성 후 저장 */
  function 회원_접속코드_발급(string $name): string {
    $name_esc = addslashes(trim($name));
    if ($name_esc === '') {
      return '';
    }
    $멤버 = db_select("SELECT code FROM tb_member WHERE name = '{$name_esc}' LIMIT 1");
    $기존 = trim((string)($멤버['code'] ?? ''));
    if (회원_접속코드_유효($기존)) {
      return $기존;
    }
    $코드 = 회원_6자리코드_생성();
    $코드_esc = addslashes($코드);
    db_query("UPDATE tb_member SET code = '{$코드_esc}' WHERE name = '{$name_esc}' LIMIT 1");
    return $코드;
  }
}
