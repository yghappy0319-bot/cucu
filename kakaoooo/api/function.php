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

/** 채팅 명령어 정규화 — BOM·제로폭 제거, 유니코드 공백→스페이스, 전각 마침표/% → ASCII */
if (!function_exists('status_정규화')) {
  function status_정규화($status): string {
    $t = trim((string)$status);
    // 포맷·제로폭은 삭제 (글자 사이에 끼면 명령 매칭이 깨짐)
    $t = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\p{Cf}]+/u', '', $t);
    // NBSP·전각공백 등은 일반 스페이스로 (삭제하면 `.탕감 미미 100%` → `.탕감미미100%` 가 됨)
    $t = preg_replace('/[\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]+/u', ' ', $t);
    $t = preg_replace('/^．/u', '.', $t);
    $t = str_replace('％', '%', $t);
    $t = preg_replace('/[ \t]+/u', ' ', $t);
    return trim($t);
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

/** 가방 표시용 — tb_member_item_bag.로또티켓 */
if (!function_exists('로또티켓_잔량_조회_가방')) {
  function 로또티켓_잔량_조회_가방(int $idx, array $회원정보 = []): int {
    if ($idx <= 0) {
      return 0;
    }
    if (!function_exists('로또티켓_조회')) {
      $ticketInc = __DIR__ . '/game/lotto_ticket.inc.php';
      if (is_file($ticketInc)) {
        require_once $ticketInc;
      }
    }
    if (function_exists('로또티켓_조회')) {
      return 로또티켓_조회($idx);
    }
    // fallback
    if (!function_exists('item_bag_qty') && is_file(__DIR__ . '/item_bag.inc.php')) {
      require_once __DIR__ . '/item_bag.inc.php';
    }
    if (function_exists('item_bag_qty')) {
      if (function_exists('item_bag_ensure_column')) {
        item_bag_ensure_column('로또티켓');
      }
      return max(0, (int)item_bag_qty($idx, '로또티켓'));
    }
    return max(0, (int)($회원정보['lotto_ticket'] ?? 0));
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

/** `.가방` / `.가방 닉` — 처리 시 exit, 미해당 시 false · 타인 조회 시 총 본방냥 0.001% → 주인 (관리방 info3 무료) */
if (!function_exists('가방_명령_처리')) {
  function 가방_명령_처리(string $status, string $호칭, array $정보, string $두자리닉넴 = ''): bool {
    $status = trim($status);
    if ($status !== '.가방' && strpos($status, '.가방') !== 0) {
      return false;
    }

    // 관리방(연구실 info3): 타인 가방 열람비 없음
    $관리방무료 = function_exists('연구실봇_요청여부') && 연구실봇_요청여부();

    // 타인 가방 열람비 = 총 본방냥 × 0.001% (최소 1) · 본방냥 이체
    $본방총합 = 0.0;
    if (function_exists('시세기준_본방냥')) {
      $본방총합 = max(0.0, (float)시세기준_본방냥());
    } elseif (function_exists('db_select')) {
      $합행 = @db_select("SELECT COALESCE(SUM(IFNULL(newpoint, 0)), 0) AS t FROM tb_member WHERE status = 0");
      $본방총합 = max(0.0, (float)($합행['t'] ?? 0));
    }
    $열람비 = (int)ceil($본방총합 * 0.00001); // 0.001%
    if ($열람비 < 1) {
      $열람비 = 1;
    }
    $열람비_표시 = function_exists('newpoint표시')
      ? newpoint표시($열람비)
      : (function_exists('냥_숫자콤마') ? 냥_숫자콤마($열람비) : number_format($열람비));

    $조회자_name = trim((string)($정보['name'] ?? $두자리닉넴));
    $조회자_idx = (int)($정보['idx'] ?? 0);

    $조회닉 = trim(mb_substr($status, 3));
    $타인조회 = false;
    $열람이체문구 = '';
    if ($조회닉 === '') {
      $조회회원 = $정보;
      $조회회원_idx = $조회자_idx;
      $조회회원_name = $조회자_name;
      if ($호칭 === '' && function_exists('계급')) {
        $계급 = 계급($조회회원['point'] ?? 0);
        $호칭 = !empty($조회회원['title']) ? (string)$조회회원['title'] : (string)($계급['name'] ?? '');
      }
      $msg = "🎒{$호칭} {$조회회원_name} 보유 아이템" . 채팅_첫줄_뒤_공백();
    } else {
      if (function_exists('getTwoCharNick')) {
        $parsed = getTwoCharNick($조회닉);
        if ($parsed !== '') {
          $조회닉 = $parsed;
        }
      }
      $조회회원 = 회원정보_조회($조회닉);
      if (empty($조회회원['idx'])) {
        echo 전송("❌ '{$조회닉}' 닉네임을 찾을 수 없어요.");
        exit;
      }
      $조회회원_idx = (int)$조회회원['idx'];
      $조회회원_name = trim((string)$조회회원['name']);
      // 본인 닉으로 조회한 경우는 무료
      $타인조회 = ($조회자_name !== '' && $조회회원_name !== '' && $조회자_name !== $조회회원_name);
      $msg = "🎒{$조회회원_name} 보유 아이템" . 채팅_첫줄_뒤_공백();
    }

    if ($조회회원_idx <= 0) {
      echo 전송("❌ 회원 정보를 찾을 수 없어요.");
      exit;
    }

    if ($타인조회 && !$관리방무료) {
      if ($조회자_idx <= 0 || $조회자_name === '') {
        echo 전송("❌ 회원 정보를 확인할 수 없어요.");
        exit;
      }
      $조회자_esc = addslashes($조회자_name);
      $주인_esc = addslashes($조회회원_name);
      global $conn;
      $차감 = @db_query("
        UPDATE tb_member
        SET newpoint = IFNULL(newpoint, 0) - {$열람비}
        WHERE name = '{$조회자_esc}' AND status = 0 AND IFNULL(newpoint, 0) >= {$열람비}
        LIMIT 1
      ");
      $차감됨 = $차감 && ($conn instanceof mysqli) && ((int)mysqli_affected_rows($conn) > 0);
      if (!$차감됨) {
        $보유행 = db_select("SELECT IFNULL(newpoint, 0) AS newpoint FROM tb_member WHERE name = '{$조회자_esc}' LIMIT 1");
        $보유 = (float)($보유행['newpoint'] ?? 0);
        $보유표시 = function_exists('newpoint표시') ? newpoint표시($보유) : number_format($보유);
        echo 전송("❌ 타인 가방 열람에는 본방 {$열람비_표시}냥이 필요해요.\n(총 본방냥 0.001% · 보유: {$보유표시}냥)");
        exit;
      }
      db_query("
        UPDATE tb_member
        SET newpoint = IFNULL(newpoint, 0) + {$열람비}
        WHERE name = '{$주인_esc}' AND status = 0
        LIMIT 1
      ");
      if (function_exists('지급로그')) {
        지급로그('가방열람', $조회자_name, $조회회원_name, 0, $열람비);
      }
      $열람이체문구 = "\n💸 본방 {$열람비_표시}냥 → {$조회회원_name}";
    } elseif ($타인조회 && $관리방무료) {
      $열람이체문구 = "\n✅ 관리방 열람 · 냥 차감 없음";
    }

    $가방목록 = function_exists('item_bag_list') ? item_bag_list($조회회원_idx) : [];
    // 아래 전용 라인(강화 수호·은총·로또 티켓)과 겹치는 가방 행은 제외
    $가방전용스킵 = [
      '강화수호' => true,
      '강화 수호' => true,
      '은총' => true,
      '은총조각' => true,
      '로또티켓' => true,
      '로또 티켓' => true,
    ];
    $일반행 = 0;
    if ($가방목록) {
      foreach ($가방목록 as $row) {
        $이름 = trim((string)($row['name'] ?? ''));
        $표시 = trim((string)($row['label'] ?? ''));
        if ($표시 === '') {
          $표시 = function_exists('아이템_가방_표시명') ? 아이템_가방_표시명($이름) : $이름;
        }
        $정규 = preg_replace('/\s+/u', '', $이름);
        if ($이름 !== '' && (isset($가방전용스킵[$이름]) || isset($가방전용스킵[$정규]))) {
          continue;
        }
        $msg .= "• {$표시} x{$row['count']}\n";
        $일반행++;
      }
    }
    if ($일반행 < 1 && empty($가방목록)) {
      $msg .= "(보유한 아이템 없음)\n";
    }

    $수호횟수 = function_exists('bag_강화수호_수량')
      ? bag_강화수호_수량((string)($조회회원['name'] ?? ''))
      : (int)($조회회원['enhance_suho'] ?? 0);
    $은총횟수 = function_exists('bag_은총_수량')
      ? bag_은총_수량((string)($조회회원['name'] ?? ''))
      : (int)($조회회원['은총개수'] ?? 0);
    $은총조각수 = 0;
    $조각닉 = (string)($조회회원['name'] ?? $조회회원_name);
    if ($조각닉 !== '') {
      $orePath = __DIR__ . '/game/mining_ore.inc.php';
      if (!function_exists('mining_ore_shard_count') && is_file($orePath)) {
        require_once $orePath;
      }
      if (function_exists('mining_ore_shard_count')) {
        $은총조각수 = (int)mining_ore_shard_count($조각닉);
      } elseif (function_exists('boss_raid_은총조각_보유')) {
        $은총조각수 = (int)boss_raid_은총조각_보유($조각닉);
      }
    }
    $티켓 = 로또티켓_잔량_조회_가방($조회회원_idx, $조회회원);
    $보호수치 = (int)($조회회원['protect'] ?? 0);
    if ($보호수치 <= 0 && !empty($조회회원_name)) {
      $보호행 = @db_select("SELECT IFNULL(protect, 0) AS protect FROM tb_member WHERE name = '" . addslashes($조회회원_name) . "' LIMIT 1");
      $보호수치 = (int)($보호행['protect'] ?? 0);
    }

    $msg .= "• 보호 x{$보호수치}\n";
    $msg .= "• 강화 수호 x{$수호횟수}\n";
    $msg .= "• 은총 x{$은총횟수}\n";
    $msg .= "• 은총조각 x{$은총조각수}\n";
    $msg .= "• 로또 티켓 x{$티켓}";
    if ($열람이체문구 !== '') {
      $msg .= $열람이체문구;
    }

    echo 전송($msg);
    exit;
  }
}

/**
 * 관리자 `.은총` 지급/회수
 * - `.은총 전체 [개수]`
 * - `.은총 닉 [개수]` (음수=회수)
 * - `.은총 닉` → 1개 지급
 * bare `.은총` 은 처리하지 않음(강화용 사용과 구분)
 * @return bool 처리했으면 true (이미 전송·exit 호출됨) / 해당없으면 false
 */
if (!function_exists('관리자_은총지급_명령처리')) {
  function 관리자_은총지급_명령처리(string $status, string $요청자닉): bool {
    $입력 = trim($status);
    if ($입력 === '' || strpos($입력, '.은총') !== 0) {
      return false;
    }
    // bare `.은총` / `.은총사용` 등은 스킵 (인자 있는 지급만)
    if (!preg_match('/^\.은총\s+\S+/u', $입력)) {
      return false;
    }

    global $관리자;
    if (!in_array($요청자닉, (array)$관리자, true)) {
      echo 전송("❌ 관리자만 사용 가능한 명령어입니다.");
      exit;
    }

    $대상닉 = $요청자닉;
    $지급개수 = 1;

    if (!function_exists('bag_은총_가산') && is_file(__DIR__ . '/item_bag_enhance.inc.php')) {
      require_once __DIR__ . '/item_bag_enhance.inc.php';
    }

    // 전각 숫자 → 반각 (카톡/모바일 입력 대비)
    $전각숫자정규화 = static function (string $s): string {
      $map = [
        '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
        '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
        '－' => '-', '﹣' => '-',
      ];
      return strtr($s, $map);
    };

    if (preg_match('/^\.은총\s+전체(?:\s+(\d+))?\s*$/u', $전각숫자정규화($입력), $전체m)) {
      if (isset($전체m[1]) && $전체m[1] !== '') {
        $지급개수 = (int)$전체m[1];
      }
      if ($지급개수 < 1) {
        $지급개수 = 1;
      }
      if (function_exists('bag_은총_가산')) {
        $result = db_query("SELECT name FROM tb_member WHERE status = 0");
        $인원 = 0;
        while ($row = db_fetch($result)) {
          $n = trim((string)($row['name'] ?? ''));
          if ($n === '') {
            continue;
          }
          bag_은총_가산($n, $지급개수);
          $인원++;
        }
      } else {
        $rs = db_query("UPDATE tb_member SET 은총개수 = IFNULL(은총개수, 0) + {$지급개수} WHERE status = 0");
        if (!$rs) {
          echo 전송("❌ 전체 은총 지급에 실패했습니다.");
          exit;
        }
        $인원행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member WHERE status = 0");
        $인원 = (int)($인원행['cnt'] ?? 0);
      }
      echo 전송("✨ 전체 인원 {$인원}명에게 은총 {$지급개수}개씩 지급 완료!");
      exit;
    }

    $입력정규 = $전각숫자정규화($입력);
    // `.은총 닉 11` / `.은총 닉11` / `.은총 닉 -3`
    if (preg_match('/^\.은총\s+(\S+)\s+(-?\d+)\s*$/u', $입력정규, $m)) {
      $대상닉 = trim($m[1]);
      $지급개수 = (int)$m[2];
    } elseif (preg_match('/^\.은총\s+([가-힣A-Za-z]+)(-?\d+)\s*$/u', $입력정규, $m)) {
      $대상닉 = trim($m[1]);
      $지급개수 = (int)$m[2];
    } elseif (preg_match('/^\.은총\s+(\S+)/u', $입력정규, $m)) {
      $대상닉 = trim($m[1]);
      $지급개수 = 1;
    }

    if (function_exists('getTwoCharNick')) {
      $parsed = getTwoCharNick($대상닉);
      if ($parsed !== '') {
        $대상닉 = $parsed;
      }
    }

    $대상닉_esc = addslashes($대상닉);
    $대상정보 = db_select("SELECT idx, 은총개수, IFNULL(status, 0) AS status FROM tb_member WHERE name = '{$대상닉_esc}' LIMIT 1");
    if (empty($대상정보['idx'])) {
      $대상정보 = db_select("SELECT idx, 은총개수, IFNULL(status, 0) AS status FROM tb_member WHERE TRIM(name) = '{$대상닉_esc}' LIMIT 1");
    }
    if (empty($대상정보['idx'])) {
      echo 전송("❌ {$대상닉} 닉네임을 찾을 수 없습니다.\n예) .은총 희수 11");
      exit;
    }
    if ((int)($대상정보['status'] ?? 0) === 1) {
      echo 전송("❌ {$대상닉} 님은 탈퇴/정지 상태라 은총을 지급할 수 없어요.");
      exit;
    }

    $현재보유 = function_exists('bag_은총_수량')
      ? bag_은총_수량($대상닉)
      : (int)($대상정보['은총개수'] ?? 0);

    if ($지급개수 < 0) {
      $회수개수 = abs($지급개수);
      if ($회수개수 < 1) {
        echo 전송("❌ 회수 개수는 1 이상이어야 합니다.");
        exit;
      }
      $실제회수 = min($회수개수, $현재보유);
      if (function_exists('bag_은총_차감')) {
        if ($실제회수 > 0) {
          $rs = bag_은총_차감($대상닉, $실제회수);
          if (empty($rs['ok'])) {
            $사유 = trim((string)($rs['msg'] ?? ''));
            echo 전송("❌ {$대상닉} 은총 회수에 실패했습니다." . ($사유 !== '' ? "\n{$사유}" : ''));
            exit;
          }
        }
      } else {
        $rs = db_query("UPDATE tb_member SET 은총개수 = GREATEST(IFNULL(은총개수, 0) - {$회수개수}, 0) WHERE name = '{$대상닉_esc}' LIMIT 1");
        if (!$rs) {
          echo 전송("❌ {$대상닉} 은총 회수에 실패했습니다.");
          exit;
        }
      }
      $남은보유 = max(0, $현재보유 - $회수개수);
      echo 전송("✨ {$대상닉} 은총 {$실제회수}개 회수 완료! (현재 보유 {$남은보유}개)");
      exit;
    }

    if ($지급개수 < 1) {
      $지급개수 = 1;
    }
    if (function_exists('bag_은총_가산')) {
      $rs = bag_은총_가산($대상닉, $지급개수);
      if (empty($rs['ok'])) {
        $사유 = trim((string)($rs['msg'] ?? ''));
        echo 전송("❌ {$대상닉} 은총 지급에 실패했습니다." . ($사유 !== '' ? "\n{$사유}" : ''));
        exit;
      }
    } else {
      $rs = db_query("UPDATE tb_member SET 은총개수 = IFNULL(은총개수, 0) + {$지급개수} WHERE name = '{$대상닉_esc}'");
      if (!$rs) {
        echo 전송("❌ {$대상닉} 은총 지급에 실패했습니다.");
        exit;
      }
    }
    $현재보유 = $현재보유 + $지급개수;
    echo 전송("✨ {$대상닉} 은총 {$지급개수}개 지급 완료! (현재 보유 {$현재보유}개)");
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
 * 카카오 「사진을 보냈습니다」 시스템 메시지 (마침표·앞뒤 공백 포함)
 */
if (!function_exists('생타_사진메시지_SQL')) {
  function 생타_사진메시지_SQL($msg_col = 'msg'): string {
    $m = preg_replace('/[^a-zA-Z0-9_.]/', '', (string)$msg_col);
    if ($m === '') {
      $m = 'msg';
    }
    return "TRIM(IFNULL({$m}, '')) LIKE '%사진을 보냈습니다%'";
  }
}

/**
 * 생타 집계 SQL 식 (SELECT·GROUP BY 용).
 * 일반 메시지 1 · 빈 메시지·사진 시스템메시지는 0
 */
if (!function_exists('생타_SQL_select_expr')) {
  function 생타_SQL_select_expr($msg_col = 'msg', $regdate_col = 'regdate') {
    $m = preg_replace('/[^a-zA-Z0-9_.]/', '', (string)$msg_col);
    if ($m === '') {
      $m = 'msg';
    }
    unset($regdate_col);
    $사진 = function_exists('생타_사진메시지_SQL')
      ? 생타_사진메시지_SQL($m)
      : "TRIM(IFNULL({$m}, '')) LIKE '%사진을 보냈습니다%'";
    return "COALESCE(SUM(CASE"
      . " WHEN TRIM(IFNULL({$m}, '')) = '' THEN 0"
      . " WHEN {$사진} THEN 0"
      . " ELSE 1"
      . " END), 0)";
  }
}

/**
 * 버프타 집계 SQL 식 — SUM(tasu) 에서 사진 시스템메시지 제외
 */
if (!function_exists('버프타_SQL_select_expr')) {
  function 버프타_SQL_select_expr($msg_col = 'msg', $tasu_col = 'tasu'): string {
    $m = preg_replace('/[^a-zA-Z0-9_.]/', '', (string)$msg_col);
    $t = preg_replace('/[^a-zA-Z0-9_.]/', '', (string)$tasu_col);
    if ($m === '') {
      $m = 'msg';
    }
    if ($t === '') {
      $t = 'tasu';
    }
    $사진 = function_exists('생타_사진메시지_SQL')
      ? 생타_사진메시지_SQL($m)
      : "TRIM(IFNULL({$m}, '')) LIKE '%사진을 보냈습니다%'";
    return "COALESCE(SUM(CASE WHEN {$사진} THEN 0 ELSE IFNULL({$t}, 0) END), 0)";
  }
}

/** @deprecated 시간대 경감 폐지 · 항상 false */
if (!function_exists('사진_경감타수_시간대인가')) {
  function 사진_경감타수_시간대인가(?int $hour = null): bool {
    unset($hour);
    return false;
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
require_once __DIR__ . '/item_bag.inc.php';
require_once __DIR__ . '/item_bag_enhance.inc.php';
require_once __DIR__ . '/item_kkobung_coin.inc.php';

/** .공커등록 시 tb_couple.sort — 활성(status=0) 중 MAX(sort)+1 */
function 공커등록_다음sort() {
  $row = db_select("SELECT COALESCE(MAX(sort), 0) AS maxSort FROM tb_couple WHERE status = 0");
  return (int)($row['maxSort'] ?? 0) + 1;
}

/** tb_couple.couple 에 💛 등 4바이트 이모지 저장 가능하도록 utf8mb4 보장 */
if (!function_exists('공커_couple컬럼_utf8mb4보장')) {
  function 공커_couple컬럼_utf8mb4보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $col = @db_select("SHOW FULL COLUMNS FROM tb_couple LIKE 'couple'");
    $coll = (string)($col['Collation'] ?? '');
    if ($coll !== '' && stripos($coll, 'utf8mb4') !== false) {
      return;
    }
    @db_query("ALTER TABLE tb_couple MODIFY `couple` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''");
  }
}

/** tb_progress.nick 에 🤝💛 등 4바이트 이모지 저장 가능하도록 utf8mb4 보장 */
if (!function_exists('진행_nick컬럼_utf8mb4보장')) {
  function 진행_nick컬럼_utf8mb4보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $col = @db_select("SHOW FULL COLUMNS FROM tb_progress LIKE 'nick'");
    $coll = (string)($col['Collation'] ?? '');
    if ($coll !== '' && stripos($coll, 'utf8mb4') !== false) {
      return;
    }
    $type = strtolower(trim((string)($col['Type'] ?? '')));
    if (!preg_match('/^(varchar\(\d+\)|char\(\d+\)|tinytext|text|mediumtext|longtext)/', $type)) {
      $type = 'varchar(191)';
    }
    @db_query("ALTER TABLE tb_progress MODIFY `nick` {$type} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''");
  }
}

/** `.공커등록 우서💛앙앙` — 하트/이모지 사이 두 닉 파싱 */
if (!function_exists('공커등록_커플파싱')) {
  function 공커등록_커플파싱($after): array {
    $after = trim((string)$after);
    $after = preg_replace('/[\x{FEFF}\x{200B}-\x{200D}\p{Cf}]+/u', '', $after);
    $after = trim((string)$after);
    if ($after === '') {
      return ['ok' => false, 'couple' => '', 'nicks' => []];
    }
    $compact = preg_replace('/\s+/u', '', $after);
    if (preg_match('/^([가-힣]{2})(.*?)([가-힣]{2})$/u', (string)$compact, $m)) {
      $n1 = $m[1];
      $n2 = $m[3];
      $heart = (string)($m[2] ?? '');
      $couple = ($heart === '') ? ($n1 . $n2) : ($n1 . $heart . $n2);
      return ['ok' => true, 'couple' => $couple, 'nicks' => [$n1, $n2]];
    }
    $nicks = function_exists('공커문자열_닉목록') ? 공커문자열_닉목록($after) : [];
    if (count($nicks) >= 2) {
      return ['ok' => true, 'couple' => $after, 'nicks' => array_slice($nicks, 0, 2)];
    }
    return ['ok' => false, 'couple' => $after, 'nicks' => $nicks];
  }
}

/**
 * .공커등록 닉1💛닉2 — 매칭 시 전송 후 exit.
 */
if (!function_exists('공커등록_명령_처리')) {
  function 공커등록_명령_처리($status, $두자리닉넴, $관리자 = []) {
    global $conn, $오늘;
    $status = trim((string)$status);
    if (function_exists('mb_convert_encoding')) {
      $fixed = @mb_convert_encoding($status, 'UTF-8', 'UTF-8');
      if (is_string($fixed) && $fixed !== '') {
        $status = $fixed;
      }
    }
    if (!preg_match('/^\.공커등록(?:\s+(.+))?$/u', $status, $match)) {
      return false;
    }
    $after = trim((string)($match[1] ?? ''));
    if ($after === '') {
      echo 전송("❌ 사용법: .공커등록 영수🖤하니\n예) .공커등록 우서💛앙앙");
      exit;
    }
    if (!in_array((string)$두자리닉넴, (array)$관리자, true)) {
      echo 전송("❌ 관리자만 사용할 수 있습니다.");
      exit;
    }

    $파싱 = 공커등록_커플파싱($after);
    if (empty($파싱['ok']) || count($파싱['nicks'] ?? []) < 2) {
      echo 전송("❌ 공커 닉을 확인해주세요.\n예) .공커등록 우서💛앙앙");
      exit;
    }
    $닉네임 = (string)$파싱['couple'];
    $닉목록 = $파싱['nicks'];
    foreach ($닉목록 as $nn) {
      $nn_esc = addslashes($nn);
      $회원 = db_select("SELECT name FROM tb_member WHERE name = '{$nn_esc}' AND IFNULL(status, 0) != 1 LIMIT 1");
      if (empty($회원['name'])) {
        echo 전송("❌ [{$nn}] 회원을 찾을 수 없어요.");
        exit;
      }
    }

    공커_couple컬럼_utf8mb4보장();
    $오늘값 = (isset($오늘) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$오늘))
      ? (string)$오늘
      : date('Y-m-d');
    $닉_esc = (isset($conn) && $conn instanceof mysqli)
      ? mysqli_real_escape_string($conn, $닉네임)
      : addslashes($닉네임);

    $데이터 = db_select("SELECT COUNT(*) AS cnt FROM tb_couple WHERE couple = '{$닉_esc}'");
    if (!empty($데이터['cnt'])) {
      $result = db_query("DELETE FROM tb_couple WHERE couple = '{$닉_esc}'");
      if ($result) {
        if (function_exists('공커연금_멤버_초기화')) {
          공커연금_멤버_초기화($닉네임);
        }
        if (function_exists('공커_멤버_oneroom_설정')) {
          공커_멤버_oneroom_설정($닉네임, 0);
        }
        echo 전송($닉네임 . ' 해제');
        exit;
      }
      echo 전송('❌ 공커 해제에 실패했어요.');
      exit;
    }

    $다음sort = 공커등록_다음sort();
    $sql = "INSERT INTO tb_couple SET couple = '{$닉_esc}', sdate = '{$오늘값}', edate = '{$오늘값}', sort = {$다음sort}, status = 0";
    $result = db_query($sql);
    if (!$result) {
      공커_couple컬럼_utf8mb4보장();
      $result = db_query($sql);
    }
    if (!$result) {
      $대체 = @preg_replace('/[\x{10000}-\x{10FFFF}]/u', '❤', $닉네임);
      if (is_string($대체) && $대체 !== '' && $대체 !== $닉네임) {
        $닉네임 = $대체;
        $닉_esc = (isset($conn) && $conn instanceof mysqli)
          ? mysqli_real_escape_string($conn, $닉네임)
          : addslashes($닉네임);
        $sql = "INSERT INTO tb_couple SET couple = '{$닉_esc}', sdate = '{$오늘값}', edate = '{$오늘값}', sort = {$다음sort}, status = 0";
        $result = db_query($sql);
      }
    }
    if (!$result) {
      $err = (isset($conn) && $conn instanceof mysqli) ? mysqli_error($conn) : '';
      echo 전송('❌ 공커 등록에 실패했어요.' . ($err !== '' ? "\n{$err}" : ''));
      exit;
    }
    if (function_exists('공커등록_일방정리')) {
      공커등록_일방정리($닉네임);
    }
    if (function_exists('공커_멤버_oneroom_설정')) {
      공커_멤버_oneroom_설정($닉네임, 2);
    }
    echo 전송($닉네임 . ' 축하해🎉');
    exit;
  }
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

/** 공커대실권 기준적립(전체냥×0.0001) — .공커 목록과 동일 */
if (!function_exists('공커_대실권_기준적립')) {
  function 공커_대실권_기준적립() {
    global $전체포인트;
    $총 = $전체포인트['total_point'] ?? 0;
    if (function_exists('시세기준_게임냥_문자열')) {
      $총 = 시세기준_게임냥_문자열();
    }
    if (function_exists('냥_나눗셈내림')) {
      // ×0.0001 = ÷10000
      $q = 냥_나눗셈내림($총, 10000);
      return function_exists('bccomp') && bccomp(냥_정수문자열($q), '1', 0) < 0 ? 1 : $q;
    }
    return max(1, (int)floor((float)$총 * 0.0001));
  }
}

/** sort·기준적립으로 커플 amount 산출 (.공커 갱신과 동일: 기준 + (sort-1)×1억) */
if (!function_exists('공커_대실권_amount_계산')) {
  function 공커_대실권_amount_계산($sort, $공커적립 = null) {
    $sort = max(1, (int)$sort);
    if ($공커적립 === null) {
      $공커적립 = 공커_대실권_기준적립();
    }
    $적립Str = function_exists('냥_정수문자열') ? 냥_정수문자열($공커적립) : (string)max(0, (int)$공커적립);
    $가산 = ($sort - 1) * 100000000;
    if (function_exists('bcadd')) {
      $raw = bcadd($적립Str, (string)$가산, 0);
    } else {
      $raw = (string)((int)$적립Str + $가산);
    }
    return function_exists('냥_앞3자리_뒤0')
      ? 냥_앞3자리_뒤0($raw)
      : $raw;
  }
}

/**
 * amount=0 인 활성 커플 → 다른 커플과 같은 공식으로 즉시 채움
 * @param array $커플 tb_couple 행
 * @return array amount 보정된 행
 */
if (!function_exists('공커_대실권_기준가_보장')) {
  function 공커_대실권_기준가_보장(array $커플): array {
    $idx = (int)($커플['idx'] ?? 0);
    if ($idx < 1) {
      return $커플;
    }
    $amt = function_exists('냥_정수문자열')
      ? 냥_정수문자열($커플['amount'] ?? 0)
      : (ltrim(preg_replace('/[^\d]/', '', (string)($커플['amount'] ?? 0)), '0') ?: '0');
    if ($amt !== '0' && (function_exists('bccomp') ? bccomp($amt, '0', 0) > 0 : (float)$amt > 0)) {
      $커플['amount'] = $amt;
      return $커플;
    }
    $sort = (int)($커플['sort'] ?? 0);
    if ($sort < 1) {
      if (function_exists('공커_sort_sdate재배치')) {
        공커_sort_sdate재배치();
      }
      $fresh = @db_select("SELECT * FROM tb_couple WHERE idx = {$idx} LIMIT 1");
      if (!empty($fresh['idx'])) {
        $커플 = $fresh;
        $sort = (int)($커플['sort'] ?? 0);
      }
    }
    $sort = max(1, $sort);
    $newAmt = 공커_대실권_amount_계산($sort);
    $newAmtStr = function_exists('냥_정수문자열') ? 냥_정수문자열($newAmt) : (string)(int)$newAmt;
    if ($newAmtStr !== '0') {
      @db_query("UPDATE tb_couple SET amount = {$newAmtStr}, sort = {$sort} WHERE idx = {$idx} LIMIT 1");
      $커플['amount'] = $newAmtStr;
      $커플['sort'] = $sort;
    }
    return $커플;
  }
}

/** .공커 적립금 기준 amount 갱신 — sort 1(가장 오래됨)=기준, sort마다 +1억 */
function 공커_amount_갱신($공커적립, $where = '') {
  if ($공커적립 === null || $공커적립 === '' || $공커적립 === 0 || $공커적립 === '0') {
    $공커적립 = function_exists('공커_대실권_기준적립') ? 공커_대실권_기준적립() : 0;
  }
  $적립Str = function_exists('냥_정수문자열') ? 냥_정수문자열($공커적립) : (string)max(0, (int)$공커적립);
  if ($적립Str === '0' || (function_exists('bccomp') && bccomp($적립Str, '0', 0) <= 0)) {
    return;
  }
  // sort=0 커플도 빠지지 않게 먼저 재배치
  if (function_exists('공커_sort_sdate재배치')) {
    공커_sort_sdate재배치($where);
  }
  $result = db_query("SELECT idx, sort FROM tb_couple WHERE status = 0 {$where} ORDER BY sort ASC, idx ASC");
  while ($row = db_fetch($result)) {
    $idx = (int)($row['idx'] ?? 0);
    $sort = max(1, (int)($row['sort'] ?? 0));
    if ($idx <= 0) {
      continue;
    }
    $amt = function_exists('공커_대실권_amount_계산')
      ? 공커_대실권_amount_계산($sort, $적립Str)
      : 냥_앞3자리_뒤0((int)$적립Str + ($sort - 1) * 100000000);
    $amtSql = function_exists('냥_정수문자열') ? 냥_정수문자열($amt) : (string)(int)$amt;
    db_query("UPDATE tb_couple SET amount = {$amtSql} WHERE idx = {$idx}");
  }
}

/** 공커 couple 문자열 → 참가자 닉 (영수🖤하니 · 우서💛앙앙 등) */
if (!function_exists('공커문자열_닉목록')) {
  function 공커문자열_닉목록($couple) {
    $couple = trim((string)$couple);
    if ($couple === '') {
      return [];
    }
    $compact = preg_replace('/\s+/u', '', $couple);
    if (preg_match('/^([가-힣]{2}).*?([가-힣]{2})$/u', (string)$compact, $m)) {
      $uniq = [];
      foreach ([$m[1], $m[2]] as $nn) {
        if ($nn !== '' && empty($uniq[$nn])) {
          $uniq[$nn] = true;
        }
      }
      if (count($uniq) >= 2) {
        return array_keys($uniq);
      }
    }
    $uniq = [];
    $parts = @preg_split('/[\p{Extended_Pictographic}\p{So}\p{Sk}]+/u', $couple, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts)) {
      $parts = [];
    }
    foreach ($parts as $part) {
      $part = trim((string)$part);
      if ($part === '') {
        continue;
      }
      $nn = function_exists('getTwoCharNick') ? getTwoCharNick($part) : $part;
      if ($nn !== '' && empty($uniq[$nn])) {
        $uniq[$nn] = true;
      }
    }
    if (count($uniq) >= 2) {
      return array_keys($uniq);
    }
    return 진행문자열_닉목록($couple);
  }
}

/**
 * .인원 / .솔로 목록 메시지
 * @param bool $솔로만 true면 status=0 AND oneroom=0 만
 *
 * .인원:
 *   SELECT ... FROM tb_member WHERE IFNULL(status,0)=0
 *   + tb_work 퇴근자 별도 표시
 *
 * .솔로:
 *   SELECT ... FROM tb_member
 *   WHERE IFNULL(status,0)=0 AND IFNULL(oneroom,0)=0
 */
if (!function_exists('인원목록_메시지')) {
  function 인원목록_메시지(bool $솔로만 = false): string {
    $솔로조건 = $솔로만 ? 'AND IFNULL(oneroom, 0) = 0' : '';
    $sql = "
      SELECT name, gender, content, regdate
      FROM tb_member
      WHERE IFNULL(status, 0) = 0
        {$솔로조건}
      ORDER BY RAND()
    ";
    $result = db_query($sql);

    $names1 = [];
    $names2 = [];
    $names3 = [];
    while ($row = db_fetch($result)) {
      $name = trim((string)($row['name'] ?? ''));
      if ($name === '') {
        continue;
      }

      $가입시각 = !empty($row['regdate']) ? strtotime($row['regdate']) : false;
      $신입보호중 = ($가입시각 !== false && $가입시각 > strtotime('-1 days'));

      if ($신입보호중) {
        $lines = explode("\n", $row['content'] ?? '');
        $second_line = $lines[1] ?? '';
        $사는곳 = preg_replace('/[🥕🍭❄️🤍🌸]\s*지역\(시•군\)\s*:\s*/u', '', $second_line);
        $사는곳 = trim($사는곳);
        $names3[] = $사는곳 !== '' ? $name . '(' . $사는곳 . ')' : $name;
      } elseif ((int)($row['gender'] ?? 0) === 1) {
        $names1[] = $name;
      } elseif ((int)($row['gender'] ?? 0) === 2) {
        $names2[] = $name;
      }
    }

    $cnt1 = count($names1);
    $cnt2 = count($names2);
    $cnt3 = count($names3);
    $총인원 = $cnt1 + $cnt2 + $cnt3;

    $html1 = "🚹 ({$cnt1}명)<br>";
    $html2 = "🚺 ({$cnt2}명)<br>";
    $html3 = "🐤 ({$cnt3}명)<br>";
    if (!empty($names1)) {
      $html1 .= implode(' ', $names1) . '<br>';
    }
    if (!empty($names2)) {
      $html2 .= implode(' ', $names2) . '<br>';
    }
    if (!empty($names3)) {
      $html3 .= implode(' ', $names3) . '<br>';
    }

    $제목 = $솔로만
      ? "📊 솔로 총 {$총인원}명 (공커·일방 제외)"
      : "📊 총 {$총인원}명";
    $msg = $제목 . "\n\n" . $html1 . "\n" . $html2 . "\n" . $html3;

    // .인원만: 퇴근자 별도 구역
    if (!$솔로만) {
      $퇴근이름 = [];
      $퇴근rs = @db_query("
        SELECT nick
        FROM tb_work
        WHERE status = '퇴근'
        ORDER BY regdate DESC
      ");
      if ($퇴근rs) {
        while ($w = db_fetch($퇴근rs)) {
          $nick = trim((string)($w['nick'] ?? ''));
          if ($nick !== '') {
            $퇴근이름[] = $nick;
          }
        }
      }
      $cnt퇴근 = count($퇴근이름);
      $html퇴근 = "🚌 퇴근자 ({$cnt퇴근}명)<br>";
      if ($cnt퇴근 > 0) {
        $html퇴근 .= implode(' ', $퇴근이름) . '<br>';
      }
      $msg .= "\n" . $html퇴근;
    }

    return $msg;
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

/** 공커 1일당 연금 단가(냥) — 본방냥(newpoint) 총합 × 0.001 (내림) */
if (!function_exists('공커연금_단가')) {
  function 공커연금_단가() {
    if (function_exists('newpoint비율계산')) {
      return max(0, (int)newpoint비율계산(0.001));
    }
    if (function_exists('전체보유newpoint합계')) {
      return max(0, (int)floor(전체보유newpoint합계() * 0.001));
    }
    $row = @db_select("SELECT COALESCE(SUM(newpoint), 0) AS total_np FROM tb_member WHERE status = 0");
    return max(0, (int)floor((float)($row['total_np'] ?? 0) * 0.001));
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
      $couple = (string)($row['couple'] ?? '');
      $참가자 = 공커문자열_닉목록($couple);
      if (in_array($닉, $참가자, true)) {
        return $row;
      }
      // 이모지 파싱 실패 대비 — 한글2글자 추출로 재확인
      if (function_exists('진행문자열_닉목록')) {
        $참가자2 = 진행문자열_닉목록($couple);
        if (in_array($닉, $참가자2, true)) {
          return $row;
        }
      }
    }
    return null;
  }
}

/** tb_member.oneroom 조회 — 2=공커등록, 1=일방, 0=일반 */
if (!function_exists('공커_oneroom_조회')) {
  function 공커_oneroom_조회($닉): int {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return 0;
    }
    $닉_esc = addslashes($닉);
    $row = @db_select("SELECT IFNULL(oneroom, 0) AS oneroom FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    return (int)($row['oneroom'] ?? 0);
  }
}

/**
 * .공커등록/.해제 시 참가자 oneroom 일괄 설정
 * @param string|list<string> $couple_or_nicks 피치💙주리 또는 닉 배열
 */
if (!function_exists('공커_멤버_oneroom_설정')) {
  function 공커_멤버_oneroom_설정($couple_or_nicks, int $oneroom): int {
    $oneroom = max(0, min(2, (int)$oneroom));
    if (is_array($couple_or_nicks)) {
      $닉목록 = [];
      foreach ($couple_or_nicks as $nn) {
        $nn = function_exists('getTwoCharNick') ? getTwoCharNick(trim((string)$nn)) : trim((string)$nn);
        if ($nn !== '') {
          $닉목록[] = $nn;
        }
      }
    } else {
      $닉목록 = function_exists('공커문자열_닉목록')
        ? 공커문자열_닉목록((string)$couple_or_nicks)
        : [];
    }
    $n = 0;
    foreach ($닉목록 as $nn) {
      $nn = trim((string)$nn);
      if ($nn === '') {
        continue;
      }
      $esc = addslashes($nn);
      if (@db_query("UPDATE tb_member SET oneroom = {$oneroom} WHERE name = '{$esc}' LIMIT 1")) {
        $n++;
      }
    }
    return $n;
  }
}

/**
 * 공커대실권 구매 대상 — 활성 tb_couple 참가자
 * · 자숙 공커(.추가 공커) 중이면 불가
 * · 구매 가능하면 oneroom=2 로 보정 (미설정으로 막히던 경우 해소)
 * @return array|null tb_couple 행
 */
if (!function_exists('공커_대실권_구매대상_조회')) {
  function 공커_대실권_구매대상_조회($닉) {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return null;
    }
    $커플 = 공커_활성_조회($닉);
    if (empty($커플['idx'])) {
      return null;
    }
    // .추가 공커 자숙 중이면 대실권 구매 불가
    $닉_esc = addslashes($닉);
    $자숙 = @db_select("
      SELECT idx FROM tb_self
      WHERE status = '공커' AND nick = '{$닉_esc}' AND enddate > NOW()
      LIMIT 1
    ");
    if (!empty($자숙['idx'])) {
      return null;
    }
    if (공커_oneroom_조회($닉) !== 2 && function_exists('공커_멤버_oneroom_설정')) {
      공커_멤버_oneroom_설정([$닉], 2);
    }
    // 피치처럼 amount=0 만 비어 있는 커플 → 다른 커플과 동일 공식으로 채움
    if (function_exists('공커_대실권_기준가_보장')) {
      $커플 = 공커_대실권_기준가_보장($커플);
    }
    return $커플;
  }
}

/**
 * 활성 커플 멤버 oneroom=2 동기화 (자숙 공커 중인 닉은 제외·0 유지)
 * .공커 목록 조회 시 호출해 기존 커플도 대실권 구매 가능하게
 */
if (!function_exists('공커_활성전원_oneroom동기화')) {
  function 공커_활성전원_oneroom동기화(): int {
    $rs = @db_query("SELECT couple FROM tb_couple WHERE status = 0");
    if (!$rs) {
      return 0;
    }
    $자숙공커 = [];
    $srs = @db_query("SELECT nick FROM tb_self WHERE status = '공커' AND enddate > NOW()");
    if ($srs) {
      while ($s = db_fetch($srs)) {
        $nn = function_exists('getTwoCharNick')
          ? getTwoCharNick(trim((string)($s['nick'] ?? '')))
          : trim((string)($s['nick'] ?? ''));
        if ($nn !== '') {
          $자숙공커[$nn] = true;
        }
      }
    }
    $n = 0;
    while ($row = db_fetch($rs)) {
      $목록 = 공커문자열_닉목록($row['couple'] ?? '');
      foreach ($목록 as $nn) {
        if (isset($자숙공커[$nn])) {
          continue;
        }
        $n += 공커_멤버_oneroom_설정([$nn], 2);
      }
    }
    return $n;
  }
}

/**
 * 공커 참가자 공커연금(수령 대기) 0으로 초기화 — .공커등록 해제 시
 * @param string|list<string> $couple_or_nicks
 */
if (!function_exists('공커연금_멤버_초기화')) {
  function 공커연금_멤버_초기화($couple_or_nicks): int {
    공커연금_컬럼_보장();
    if (is_array($couple_or_nicks)) {
      $닉목록 = [];
      foreach ($couple_or_nicks as $nn) {
        $nn = function_exists('getTwoCharNick') ? getTwoCharNick(trim((string)$nn)) : trim((string)$nn);
        if ($nn !== '') {
          $닉목록[] = $nn;
        }
      }
    } else {
      $닉목록 = function_exists('공커문자열_닉목록')
        ? 공커문자열_닉목록((string)$couple_or_nicks)
        : [];
    }
    $n = 0;
    foreach ($닉목록 as $nn) {
      $nn = trim((string)$nn);
      if ($nn === '') {
        continue;
      }
      $esc = addslashes($nn);
      if (@db_query("UPDATE tb_member SET gongkeo_pension = 0 WHERE name = '{$esc}' LIMIT 1")) {
        $n++;
      }
    }
    return $n;
  }
}

/**
 * 활성 공커 참가자에게 일당 공커연금 누적 (cron·수령 직전)
 * — 미수령분은 다음날에도 더해 짐. 놓친 날은 경과일 × 단가로 한 번에 반영.
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

      $닉목록 = 공커문자열_닉목록($row['couple'] ?? '');
      if ($닉목록 === []) {
        continue;
      }

      $추가 = $경과일 * $단가;
      foreach ($닉목록 as $nn) {
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
      echo 전송("💰 공커연금\n\n{$두자리닉넴} 님\n{$커플표시} ({$일수}일차)\n\n수령할 공커연금이 없어요.\n(1일당 " . number_format($단가) . "냥 · 본방냥×0.001 · 미수령은 다음날 누적)");
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

/** .공커대실권 — 설명 · 구매방법 */
if (!function_exists('공커대실권_안내문구')) {
  function 공커대실권_안내문구(): string {
    $msg = "🏠 공커대실권\n\n";
    $msg .= "※ 공커 기간(만료일)을 연장하는 아이템이 아니에요.\n\n";
    $msg .= "평소에는 공창에 잘 나오던 친구가 공커가 되면\n";
    $msg .= "방 활동이 적어지는 경우가 있어요.\n";
    $msg .= "그래서 방 활동을 같이 하자는 의미로\n";
    $msg .= "공커대실권을 만들었어요.\n\n";
    $msg .= "공커대실권 1개 구매 시\n";
    $msg .= "7일 동안 방에서 같이 활동할 수 있어요.\n\n";
    $msg .= "썸만 타러 오는 곳이 아니기 때문에\n";
    $msg .= "공커가 되어도 친구들과의 소통도\n";
    $msg .= "같이 참여해 줬으면 좋겠어요 ^^\n\n";
    $msg .= "• 공커 중 한 명만 구매하면 됩니다\n";
    $msg .= "• 공커대실권을 구매 못할 경우 → 졸업 권장\n\n";
    $msg .= "구매: `.구매 공커대실권 1`\n";
    $msg .= "시세: `.구매 공커대실권`";

    return $msg;
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

  $공커일방제한 = 지목_공커일방_제한_검사([$피지목자1, $피지목자2]);
  if ($공커일방제한 !== null) {
    echo 전송($공커일방제한);
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

  // 지목 1인당 하루 1회 제한 해제 — 아이템만 있으면 여러 번 가능

  $이모티콘랜덤 = array('🍭', '🪄', '🎈', '🚀');
  $이모티 = $이모티콘랜덤[array_rand($이모티콘랜덤)];
  $진행 = $피지목자1 . $이모티 . ' ' . $피지목자2;
  $진행_esc = addslashes($진행);
  $끝나는날 = date('Y-m-d H:i', strtotime('+6 hours'));

  $진행팀수 = 지목_진행팀수();
  $필요개수 = 지목_필요개수($진행팀수);
  $보유개수 = item_bag_qty_nick($지목소유자, '지목');
  $자동구매문구 = '';
  if ($보유개수 < $필요개수) {
    $부족 = $필요개수 - $보유개수;
    $자동 = 아이템_부족분_자동구매($지목소유자, '지목', $부족);
    if (empty($자동['ok'])) {
      $자동실패 = trim((string)($자동['msg'] ?? ''));
      $msg = "❌ {$지목소유자} 님은 지목 아이템이 부족해요. ({$보유개수}/{$필요개수})\n"
        . "현재 진행 {$진행팀수}팀 → 필요 {$필요개수}개";
      if ($자동실패 !== '') {
        $msg .= "\n" . $자동실패;
      }
      echo 전송($msg);
      exit;
    }
    $자동구매문구 = trim((string)($자동['msg'] ?? ''));
    $보유개수 = item_bag_qty_nick($지목소유자, '지목');
  }
  $지목차감 = item_bag_sub_nick($지목소유자, '지목', $필요개수);
  if (empty($지목차감['ok'])) {
    echo 전송(
      "❌ {$지목소유자} 님은 지목 아이템이 부족해요. ("
      . (int)($지목차감['qty'] ?? $보유개수) . "/{$필요개수})"
    );
    exit;
  }
  아이템사용_시세하락('지목', $필요개수);

  $result = db_query("INSERT INTO tb_progress SET status = '지목', nick = '{$진행_esc}', enddate = '{$끝나는날}', regdate = NOW()");
  if ($result) {
    지목_기록_등록($지목소유자);
    미션완료_기록_if_new($지목소유자, '일방', '지목');
    foreach (진행문자열_닉목록($진행) as $nn) {
      미션완료_기록_if_new($nn, '일방', '지목');
    }
    $완료본문 =
      "{$지목소유자} 지목아이템 {$필요개수}개 사용! (진행 {$진행팀수}팀)\n"
      . "{$진행} ({$끝나는날} 까지)\n"
      . "공창 자기야 꽁냥 시작!!";
    if ($자동구매문구 !== '') {
      $완료본문 = $자동구매문구 . "\n\n" . $완료본문;
    }
    $log_msg = addslashes($완료본문);
    $caster_esc = addslashes($지목소유자);
    db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$log_msg}', leverage = 0, item = '{$caster_esc}', regdate = NOW()");

    echo 전송($완료본문);
    exit;
  }
  echo 전송('❌ 지목 등록 처리 중 오류가 났어요.');
  exit;
}

/** 진행 중 지목 팀 수 (미만료) */
if (!function_exists('지목_진행팀수')) {
  function 지목_진행팀수(): int {
    $기준 = addslashes(date('Y-m-d H:i:s'));
    $row = db_select("
      SELECT COUNT(*) AS cnt
      FROM tb_progress
      WHERE status = '지목'
        AND (enddate IS NULL OR enddate = '' OR enddate > '{$기준}')
    ");
    return max(0, (int)($row['cnt'] ?? 0));
  }
}

/** 진행 팀 수에 따른 지목/지목강일 필요 개수: 0팀=1 · 1팀=2 · 2팀=3 · 3팀=4 · 4팀+=5 */
if (!function_exists('지목강일_진행팀_필요개수표')) {
  function 지목강일_진행팀_필요개수표(int $진행팀수): int {
    static $표 = [1, 2, 3, 4, 5];
    $진행팀수 = max(0, (int)$진행팀수);
    if ($진행팀수 >= count($표)) {
      return (int)$표[count($표) - 1];
    }
    return (int)$표[$진행팀수];
  }
}

/** 진행 팀 수에 따른 지목 아이템 필요 개수: 0팀=1 · 1팀=2 · 2팀=3 · 3팀=4 · 4팀+=5 */
if (!function_exists('지목_필요개수')) {
  function 지목_필요개수(int $진행팀수): int {
    return 지목강일_진행팀_필요개수표($진행팀수);
  }
}

/** 강일 취소/만료 후 재강일 제한 — tb_gangil_tile (edate = 해제 시각) */
if (!function_exists('강일취소_타일_등록')) {
  /** @return string 잠금 해제(일방신청 가능) 시각 Y-m-d H:i:s */
  function 강일취소_타일_등록(array $닉목록, $시간 = 12): string {
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
    return $edate;
  }
}

if (!function_exists('강일_일방신청가능_안내')) {
  /** 취소/종료 시각 + N시간 후 일방신청 가능 안내 */
  function 강일_일방신청가능_안내($edate, $시간 = 12): string {
    $ts = strtotime((string)$edate);
    if ($ts === false) {
      $시간 = max(1, (int)$시간);
      $ts = strtotime("+{$시간} hours");
    }
    $표시 = date('m-d H:i', $ts);
    $시간 = max(1, (int)$시간);
    return "일방신청 가능: {$표시} 이후 (지금+{$시간}시간)";
  }
}

if (!function_exists('강일_종료_본방알림')) {
  /**
   * .강일취소 / .강일종료 시 본방 알림
   * @param string[] $이름들
   * @param string|null $해제시각 Y-m-d H:i:s · null이면 쿨타임 없음(10분 이내)
   */
  function 강일_종료_본방알림(array $이름들, $해제시각 = null, $타일시간 = 12, $종류 = '종료'): void {
    if (!function_exists('본방알림_등록')) {
      return;
    }
    $이름들 = array_values(array_filter(array_map('trim', $이름들)));
    $누구 = $이름들 !== [] ? implode(' ', $이름들) : '참가자';
    $종류 = trim((string)$종류);
    if ($종류 === '') {
      $종류 = '종료';
    }
    $msg = "{$누구} 강일방이 {$종류}되었어요.";
    if ($해제시각 !== null && trim((string)$해제시각) !== '') {
      $msg .= "\n" . 강일_일방신청가능_안내($해제시각, $타일시간);
    } else {
      $msg .= "\n일방신청 제한 없음 (시작 10분 이내 종료)";
    }
    본방알림_등록($msg, '강일종료');
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

if (!function_exists('지목_기록_텍스트')) {
  function 지목_기록_텍스트($닉) {
    return '지목명령 ' . trim((string)$닉);
  }
}

if (!function_exists('지목_오늘보냄_여부')) {
  function 지목_오늘보냄_여부($닉) {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return false;
    }
    $text_esc = addslashes(지목_기록_텍스트($닉));
    $오늘 = date('Y-m-d');
    $row = db_select("SELECT idx FROM tb_gangil_tile WHERE text = '{$text_esc}' AND DATE(regdate) = '{$오늘}' LIMIT 1");
    return !empty($row['idx']);
  }
}

if (!function_exists('지목_일일제한_검사')) {
  /**
   * 1인당 하루 1회 제한 해제 — 항상 허용
   * @return string|null
   */
  function 지목_일일제한_검사($닉) {
    unset($닉);
    return null;
  }
}

if (!function_exists('지목_기록_등록')) {
  function 지목_기록_등록($닉) {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return;
    }
    $text_esc = addslashes(지목_기록_텍스트($닉));
    $edate = date('Y-m-d 23:59:59');
    db_query("INSERT INTO tb_gangil_tile SET text = '{$text_esc}', edate = '{$edate}', regdate = NOW()");
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
   * 1인당 하루 1회 제한 해제 — 항상 허용
   * @return string|null
   */
  function 지목강일_일일제한_검사($닉) {
    unset($닉);
    return null;
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

if (!function_exists('양도_마법_enddate_ts')) {
  /** tb_item_use 마법 enddate → unix timestamp (없으면 null) */
  function 양도_마법_enddate_ts($닉): ?int {
    $닉_esc = addslashes(trim((string)$닉));
    if ($닉_esc === '') {
      return null;
    }
    $row = db_select("SELECT enddate FROM tb_item_use WHERE nickname = '{$닉_esc}' AND item = '마법' AND enddate > NOW() ORDER BY enddate DESC LIMIT 1");
    if (empty($row['enddate'])) {
      return null;
    }
    $ts = strtotime((string)$row['enddate']);
    return ($ts === false) ? null : $ts;
  }
}

if (!function_exists('양도_마법적용중')) {
  function 양도_마법적용중($닉): bool {
    $ts = 양도_마법_enddate_ts($닉);
    return $ts !== null && $ts > time();
  }
}

if (!function_exists('양도_마법_남은시간_초')) {
  function 양도_마법_남은시간_초($닉): int {
    $ts = 양도_마법_enddate_ts($닉);
    if ($ts === null || $ts <= time()) {
      return 0;
    }
    return $ts - time();
  }
}

if (!function_exists('양도_마법_남은일수')) {
  /** 마법 enddate 기준 남은 일수(내림, 1일 단위 추가 양도 가능 횟수) */
  function 양도_마법_남은일수($닉): int {
    return (int)floor(양도_마법_남은시간_초($닉) / 86400);
  }
}

if (!function_exists('양도_마법_소비')) {
  /** 추가 양도 시 마법 종료시각 1일 차감. 남은 기간 없으면 행 삭제. */
  function 양도_마법_소비($닉): string {
    $닉_esc = addslashes(trim((string)$닉));
    if ($닉_esc === '') {
      return '';
    }
    $row = db_select("SELECT idx, enddate FROM tb_item_use WHERE nickname = '{$닉_esc}' AND item = '마법' AND enddate > NOW() ORDER BY enddate DESC LIMIT 1");
    if (empty($row['idx'])) {
      return '';
    }

    $종료_ts = strtotime((string)$row['enddate']);
    if ($종료_ts === false || $종료_ts <= time()) {
      db_query("DELETE FROM tb_item_use WHERE idx = " . (int)$row['idx']);
      return '(마법 1일 차감 · 버프 종료)';
    }

    $새종료_ts = $종료_ts - 86400;
    if ($새종료_ts <= time()) {
      db_query("DELETE FROM tb_item_use WHERE idx = " . (int)$row['idx']);
      return '(마법 1일 차감 · 버프 종료)';
    }

    $새종료 = date('Y-m-d H:i', $새종료_ts);
    $새종료_esc = addslashes($새종료);
    db_query("UPDATE tb_item_use SET enddate = '{$새종료_esc}' WHERE idx = " . (int)$row['idx']);
    return '(마법 1일 차감 · ' . date('m-d H:i', $새종료_ts) . ' 까지)';
  }
}

if (!function_exists('양도_추가양도_소비')) {
  /**
   * 추가 양도 버프 차감 — 지호(1시간) 우선, 없으면 마법(1일).
   */
  function 양도_추가양도_소비($닉): string {
    if (양도_지호_남은시간_시간($닉) >= 1 && 양도_지호적용중($닉)) {
      return 양도_지호_소비($닉);
    }
    if (양도_마법_남은일수($닉) >= 1 && 양도_마법적용중($닉)) {
      return 양도_마법_소비($닉);
    }
    return '';
  }
}

if (!function_exists('양도_일일제한_검사')) {
  /**
   * 본방냥·게임냥 합산 — 하루 1회 무료 + 지호 1시간당 추가 1회(없으면 마법 1일당 추가 1회)
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

    $남은일 = 양도_마법_남은일수($닉);
    if ($남은일 >= 1 && 양도_마법적용중($닉)) {
      return null;
    }

    return "❌ [ {$닉} ] 오늘 `.양도` {$완료수}회 사용했어요.\n본방·게임방 합산 하루 1회 무료 — 추가 양도는 지호 1시간 이상 또는 마법 1일 이상 필요 (지호 {$남은시간}시간 · 마법 {$남은일}일)";
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

/** .지목강일 필요 개수: 0팀=1 · 1팀=2 · 2팀=3 · 3팀=4 · 4팀+=5 */
if (!function_exists('지목강일_필요개수')) {
  function 지목강일_필요개수(int $진행팀수): int {
    return 지목강일_진행팀_필요개수표($진행팀수);
  }
}

/**
 * .강일 / .지목강일 명령
 * - .강일 (대상): 입력자 + 대상 강일, 강일 1개 사용
 * - .지목강일 (대상1) (대상2): 지정 2명 강일, 진행 팀 수에 따라 1·2·3·4·5 개 사용
 * tb_progress status=강일, 12시간. 처리 시 exit.
 */
function 강일_명령_처리($status, $두자리닉넴) {
  if (!function_exists('강일방_배정및알림')) {
    $path = __DIR__ . '/gangil_room.inc.php';
    if (is_file($path)) {
      require_once $path;
    }
  }
  $정리상태 = trim((string)$status);
  $명령유형 = '';
  $강일소유자 = $두자리닉넴;
  $대상1 = '';
  $대상2 = '';
  $필요개수 = 0;
  $진행팀수 = 0;

  if (preg_match('/^\.강일(?:[\s\p{Zs}]+)(.+)$/u', $정리상태, $강일m)) {
    $강일토큰 = preg_split('/[\s\p{Zs}]+/u', trim($강일m[1]), -1, PREG_SPLIT_NO_EMPTY);
    // .강일 취소 / .강일 종료 는 취소 명령으로 넘김
    if (count($강일토큰) === 1) {
      $첫토큰 = 지목_명령_토큰닉($강일토큰[0]);
      if ($첫토큰 === '취소' || $첫토큰 === '종료') {
        return;
      }
    }
    if (count($강일토큰) !== 1) {
      echo 전송("❌ 사용법: .강일 (대상닉)\n예) .강일 진우\n취소: .강일취소 · .강일 취소 · .강일종료");
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
    $필요개수 = 0; // 진행 팀 수 확인 후 산정
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

  // 지목강일 1인당 하루 1회 제한 해제 — 아이템만 있으면 여러 번 가능

  $제한문구 = 강일_취소제한_검사([$대상1, $대상2]);
  if ($제한문구 !== null) {
    echo 전송($제한문구);
    exit;
  }

  // 이미 강일 진행 중(본인·상대 포함)이면 등록/삭제 토글 불가 — .강일취소·.강일종료 사용
  $강일중제한 = 지목_강일중_제한_검사([$대상1, $대상2], '강일 등록할');
  if ($강일중제한 !== null) {
    echo 전송($강일중제한);
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

  if ($명령유형 === '.지목강일') {
    $진행팀수 = 강일_진행중_건수();
    $필요개수 = 지목강일_필요개수($진행팀수);
  }

  $강일소유자_esc = addslashes($강일소유자);
  $보유개수 = item_bag_qty_nick($강일소유자, '강일');
  $자동구매문구 = '';
  if ($보유개수 < $필요개수) {
    $부족 = $필요개수 - $보유개수;
    $자동 = 아이템_부족분_자동구매($강일소유자, '강일', $부족);
    if (empty($자동['ok'])) {
      $자동실패 = trim((string)($자동['msg'] ?? ''));
      $부족msg = "❌ {$강일소유자} 님은 강일 아이템이 부족해요. ({$보유개수}/{$필요개수})";
      if ($명령유형 === '.지목강일') {
        $부족msg .= "\n현재 진행 {$진행팀수}팀 → 필요 {$필요개수}개";
      }
      if ($자동실패 !== '') {
        $부족msg .= "\n" . $자동실패;
      }
      echo 전송($부족msg);
      exit;
    }
    $자동구매문구 = trim((string)($자동['msg'] ?? ''));
    $보유개수 = item_bag_qty_nick($강일소유자, '강일');
  }
  $강일차감 = item_bag_sub_nick($강일소유자, '강일', $필요개수);
  if (empty($강일차감['ok'])) {
    echo 전송("❌ {$강일소유자} 님은 강일 아이템이 부족해요. (" . (int)($강일차감['qty'] ?? $보유개수) . "/{$필요개수})");
    exit;
  }
  아이템사용_시세하락('강일', $필요개수);

  $이모티콘랜덤 = array('❤️', '💕', '🤝', '✨');
  $이모티 = $이모티콘랜덤[array_rand($이모티콘랜덤)];
  $진행 = $대상1 . $이모티 . ' ' . $대상2;
  $진행_esc = addslashes($진행);
  $끝나는날 = date('Y-m-d H:i', strtotime('+12 hours'));
  if (function_exists('진행_nick컬럼_utf8mb4보장')) {
    진행_nick컬럼_utf8mb4보장();
  }

  $result = db_query("INSERT INTO tb_progress SET status = '강일', nick = '{$진행_esc}', enddate = '{$끝나는날}', regdate = NOW()");
  if ($result) {
    $progress_idx = 0;
    if (function_exists('db_insert_id')) {
      $progress_idx = (int)db_insert_id();
    } elseif (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
      $progress_idx = (int)mysqli_insert_id($GLOBALS['conn']);
    }
    if ($progress_idx <= 0) {
      $pid행 = @db_select("SELECT idx FROM tb_progress WHERE status = '강일' AND nick = '{$진행_esc}' ORDER BY idx DESC LIMIT 1");
      $progress_idx = (int)($pid행['idx'] ?? 0);
    }

    $강일기록됨 = false;
    foreach (진행문자열_닉목록($진행) as $nn) {
      미션완료_기록_if_new($nn, '일방', '강일');
      $강일기록됨 = true;
    }
    if (!$강일기록됨) {
      미션완료_기록_if_new($강일소유자, '일방', '강일');
    }
    $팀문구 = ($명령유형 === '.지목강일') ? " (진행 {$진행팀수}팀)" : '';
    $log_msg = addslashes("{$강일소유자} {$명령유형} 강일아이템 {$필요개수}개 사용!{$팀문구} {$진행} ({$끝나는날} 까지) 강제일방 시작!");
    db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$log_msg}', leverage = 0, item = '{$강일소유자_esc}', regdate = NOW()");

    if ($명령유형 === '.지목강일') {
      지목강일_기록_등록($강일소유자);
    }

    $방안내 = '';
    if (function_exists('강일방_배정및알림')) {
      $방결과 = 강일방_배정및알림($대상1, $대상2, $progress_idx > 0 ? $progress_idx : null);
      $방안내 = trim((string)($방결과['안내'] ?? ''));
    }

    $완료msg = '';
    if ($자동구매문구 !== '') {
      $완료msg .= $자동구매문구 . "\n\n";
    }
    $완료msg .= "강일\n{$강일소유자} → {$진행} 등록완료!\n";
    if ($명령유형 === '.지목강일') {
      $완료msg .= "진행 {$진행팀수}팀 → 강일 {$필요개수}개 차감\n";
    }
    $완료msg .= "{$끝나는날} 까지";
    if ($방안내 !== '') {
      $완료msg .= "\n\n{$방안내}";
    }
    echo 전송($완료msg);
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
      if (!function_exists('강일방_해제_진행행')) {
        $path = __DIR__ . '/gangil_room.inc.php';
        if (is_file($path)) {
          require_once $path;
        }
      }
      if (function_exists('강일방_해제_진행행')) {
        강일방_해제_진행행($row);
      }
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

/** .강일사용방법 — 본인 강일 / 타인 지목강일 아이템 소모 안내 */
if (!function_exists('강일사용방법_문구')) {
  function 강일사용방법_문구(): string {
    $진행팀수 = function_exists('강일_진행중_건수') ? 강일_진행중_건수() : 0;
    $지목필요 = function_exists('지목강일_필요개수')
      ? 지목강일_필요개수($진행팀수)
      : 지목강일_진행팀_필요개수표($진행팀수);

    $msg = "💌 강일 사용방법 (아이템 소모)\n\n";

    $msg .= "【① 본인이 1명이랑 갈 때】\n";
    $msg .= "• 명령: `.강일 대상닉`\n";
    $msg .= "  예) `.강일 진우` → 본인 + 진우\n";
    $msg .= "• 소모: 강일 아이템 1개 (입력자 차감)\n";
    $msg .= "• 효과: 강제 일방 12시간\n\n";

    $msg .= "【② 본인이 다른 사람을 보낼 때】\n";
    $msg .= "• 명령: `.지목강일 대상1 대상2`\n";
    $msg .= "  예) `.지목강일 진우 우지` → 진우 + 우지\n";
    $msg .= "• 소모: 진행 중인 강일 팀 수에 따라 증가\n";
    $msg .= "  0팀=1개 · 1팀=2개 · 2팀=3개 · 3팀=4개 · 4팀+=5개\n";
    $msg .= "• 차감: 입력자(보내는 사람) 강일 아이템\n";
    $msg .= "  ※ 피지목자(대상)는 아이템을 쓰지 않아요\n";
    $msg .= "• 지금: 진행 {$진행팀수}팀 → `.지목강일` 필요 {$지목필요}개\n\n";

    $msg .= "【공통】\n";
    $msg .= "• 아이템이 없으면 게임냥으로 자동구매\n";
    $msg .= "  → 게임냥도 부족하면 본방냥 20% 스왑 후 구매\n";
    $msg .= "• 동시 진행 강일 최대 5팀\n";
    $msg .= "• 연장: `.강일연장 당사자닉` (강일 1개 추가)\n";
    $msg .= "• 취소: `.강일취소` · `.강일종료`\n";
    $msg .= "• 실제 등록은 연구실에서 입력";

    return $msg;
  }
}

/** .아이템사용방법 / .아이템 사용방법 */
if (!function_exists('아이템사용방법_문구')) {
  function 아이템사용방법_문구(): string {
    $msg = "아이템 사용방법\n";
    $msg .= "

【닉·색 변경】
• .닉변 현재닉 변경닉
• .색변 본인닉 번호

【지목】 진행 팀 수에 따라 차감 (0팀=1 · 1팀=2 · 2팀=3 · 3팀=4 · 4팀+=5) · 횟수 제한 없음
• .지목 대상1 대상2

【강일】 자세한 소모: `.강일사용방법`
• .강일 대상닉 — 강일 1개 (본인+대상)
• .지목강일 대상1 대상2 — 진행 팀 수에 따라 1·2·3·4·5개 · 횟수 제한 없음
• .강일취소 · .강일 취소 · .강일종료 — 본인 진행 중 강일 취소
• .강일종료 당사자닉 — 해당 닉 강일 종료 (당사자 전원 .기록 · 연금 없음)
• .강일연장 당사자닉 — 강일 +12시간 연장

【제한】 3시간 · 오늘 회차별 차감 (1회=1 · 2회=2 · 3회=3…)
• .채팅제한 닉네임
• .보룸제한 닉네임
• .게임제한 닉네임

【익명제한】 3시간 (본방 익명 공지 · 회차 차감 동일)
• .익명채팅제한 닉네임
• .익명보룸제한 닉네임
• .익명게임제한 닉네임
";
    return $msg;
  }
}

if (!function_exists('아이템사용방법_명령인가')) {
  function 아이템사용방법_명령인가($status): bool {
    return (bool)preg_match('/^\.아이템\s*사용방법\s*$/u', trim((string)$status));
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

/** 본방 채팅 알림 큐 (info1.php status=0 폴링) — 대기는 최신 1건만 유지 */
if (!function_exists('본방알림_대기_최신만')) {
  function 본방알림_대기_최신만($keepIdx = 0) {
    $keepIdx = (int)$keepIdx;
    if ($keepIdx < 1) {
      $row = @db_select("SELECT idx FROM tb_lotto_info WHERE status = 0 ORDER BY idx DESC LIMIT 1");
      $keepIdx = (int)($row['idx'] ?? 0);
    }
    if ($keepIdx < 1) {
      return 0;
    }
    @db_query("UPDATE tb_lotto_info SET status = 1 WHERE status = 0 AND idx < {$keepIdx}");
    return $keepIdx;
  }
}

if (!function_exists('본방알림_등록')) {
  function 본방알림_등록($msg, $item = 'system') {
    $msg = trim((string)$msg);
    if ($msg === '') {
      return false;
    }
    $msg_esc = addslashes($msg);
    $item_esc = addslashes((string)$item);
    $ok = (bool)@db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$msg_esc}', leverage = 0, item = '{$item_esc}', regdate = NOW()");
    if ($ok) {
      본방알림_대기_최신만();
    }
    return $ok;
  }
}

/** 홍보방(info2) 전용 알림 큐 테이블 */
if (!function_exists('info2알림_테이블_보장')) {
  function info2알림_테이블_보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    @db_query("
      CREATE TABLE IF NOT EXISTS tb_info2_alarm (
        idx INT UNSIGNED NOT NULL AUTO_INCREMENT,
        status TINYINT NOT NULL DEFAULT 0 COMMENT '0=대기 1=전송',
        msg TEXT NOT NULL,
        item VARCHAR(64) NOT NULL DEFAULT 'system',
        regdate DATETIME NOT NULL,
        PRIMARY KEY (idx),
        KEY ix_status_reg (status, regdate, idx)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
  }
}

/** 홍보방(info2) 알림 등록 — 대기는 최신 1건만 유지 */
if (!function_exists('info2알림_대기_최신만')) {
  function info2알림_대기_최신만($keepIdx = 0) {
    info2알림_테이블_보장();
    $keepIdx = (int)$keepIdx;
    if ($keepIdx < 1) {
      $row = @db_select("SELECT idx FROM tb_info2_alarm WHERE status = 0 ORDER BY idx DESC LIMIT 1");
      $keepIdx = (int)($row['idx'] ?? 0);
    }
    if ($keepIdx < 1) {
      return 0;
    }
    @db_query("UPDATE tb_info2_alarm SET status = 1 WHERE status = 0 AND idx < {$keepIdx}");
    return $keepIdx;
  }
}

if (!function_exists('info2알림_등록')) {
  function info2알림_등록($msg, $item = 'system') {
    $msg = trim((string)$msg);
    if ($msg === '') {
      return false;
    }
    info2알림_테이블_보장();
    $msg_esc = addslashes($msg);
    $item_esc = addslashes((string)$item);
    $ok = (bool)@db_query("INSERT INTO tb_info2_alarm SET status = 0, msg = '{$msg_esc}', item = '{$item_esc}', regdate = NOW()");
    if ($ok) {
      info2알림_대기_최신만();
    }
    return $ok;
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

/** tb_lotto_info 큐에서 최신 1건만 꺼냄. 그보다 먼저 쌓인 대기는 status=1 */
if (!function_exists('본방알림_큐_꺼내기')) {
  function 본방알림_큐_꺼내기() {
    global $conn;
    if (!($conn instanceof mysqli)) {
      return null;
    }
    if (!@mysqli_begin_transaction($conn)) {
      return null;
    }
    $row = db_select("SELECT idx, msg FROM tb_lotto_info WHERE status = 0 ORDER BY idx DESC LIMIT 1 FOR UPDATE");
    if (empty($row['idx'])) {
      @mysqli_rollback($conn);
      return null;
    }
    $idx = (int)$row['idx'];
    @db_query("UPDATE tb_lotto_info SET status = 1 WHERE status = 0 AND idx < {$idx}");
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

if (!function_exists('본방알림_적체_일괄완료')) {
  /** 이전에 쌓인 status=0 대기를 전부 전송완료(1) 처리. 한 번만. */
  function 본방알림_적체_일괄완료() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $표식 = '#queue_flush_20260818';
    $esc = addslashes($표식);
    $already = @db_select("SELECT idx FROM tb_lotto_info WHERE item = '{$esc}' LIMIT 1");
    if (!empty($already['idx'])) {
      return;
    }
    @db_query("INSERT INTO tb_lotto_info SET status = 1, msg = '본방 알림 큐 적체 일괄완료', leverage = 0, item = '{$esc}', regdate = NOW()");
    $got = @db_select("SELECT idx FROM tb_lotto_info WHERE item = '{$esc}' LIMIT 1");
    if (empty($got['idx'])) {
      return;
    }
    @db_query("UPDATE tb_lotto_info SET status = 1 WHERE status = 0");
  }
}

if (!function_exists('본방알림_큐_응답_시도')) {
  function 본방알림_큐_응답_시도() {
    본방알림_적체_일괄완료();

    // 종류×강화 중복(예: 마법 +31 2명) → 최신 1명만 유지, 기존 보유자 하향
    if (!function_exists('강화_독점중복_정리')) {
      $renewal = __DIR__ . '/enhance_renewal.inc.php';
      if (is_file($renewal)) {
        require_once $renewal;
      }
    }
    if (function_exists('강화_독점중복_정리')) {
      @강화_독점중복_정리();
    }

    $msg = 본방알림_큐_꺼내기();
    if ($msg === null) {
      return false;
    }
    echo 전송($msg);
    exit;
  }
}

if (!function_exists('info2알림_적체_일괄완료')) {
  /** 홍보방 큐에 이전에 쌓인 status=0 대기를 전부 전송완료(1) 처리. 한 번만. */
  function info2알림_적체_일괄완료() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    info2알림_테이블_보장();
    $표식 = '#queue_flush_20260818';
    $esc = addslashes($표식);
    $already = @db_select("SELECT idx FROM tb_info2_alarm WHERE item = '{$esc}' LIMIT 1");
    if (!empty($already['idx'])) {
      return;
    }
    @db_query("INSERT INTO tb_info2_alarm SET status = 1, msg = '홍보방 알림 큐 적체 일괄완료', item = '{$esc}', regdate = NOW()");
    $got = @db_select("SELECT idx FROM tb_info2_alarm WHERE item = '{$esc}' LIMIT 1");
    if (empty($got['idx'])) {
      return;
    }
    @db_query("UPDATE tb_info2_alarm SET status = 1 WHERE status = 0");
  }
}

/** tb_info2_alarm 큐에서 최신 1건만 꺼냄. 그보다 먼저 쌓인 대기는 status=1 */
if (!function_exists('info2알림_큐_꺼내기')) {
  function info2알림_큐_꺼내기() {
    global $conn;
    if (!($conn instanceof mysqli)) {
      return null;
    }
    info2알림_테이블_보장();
    if (!@mysqli_begin_transaction($conn)) {
      return null;
    }
    $row = db_select("SELECT idx, msg FROM tb_info2_alarm WHERE status = 0 ORDER BY idx DESC LIMIT 1 FOR UPDATE");
    if (empty($row['idx'])) {
      @mysqli_rollback($conn);
      return null;
    }
    $idx = (int)$row['idx'];
    @db_query("UPDATE tb_info2_alarm SET status = 1 WHERE status = 0 AND idx < {$idx}");
    db_query("UPDATE tb_info2_alarm SET status = 1 WHERE idx = {$idx} AND status = 0 LIMIT 1");
    if (mysqli_affected_rows($conn) < 1) {
      @mysqli_rollback($conn);
      return null;
    }
    @mysqli_commit($conn);
    $msg = trim((string)($row['msg'] ?? ''));
    return $msg !== '' ? $msg : null;
  }
}

/** 홍보방(info2) 폴링 — 보스 진행 틱 적재 후 알림 1건 전송 */
if (!function_exists('info2알림_큐_응답_시도')) {
  function info2알림_큐_응답_시도() {
    info2알림_적체_일괄완료();

    // 종류×강화 중복 정리 알림도 홍보방 큐로 적재
    if (!function_exists('강화_독점중복_정리')) {
      $renewal = __DIR__ . '/enhance_renewal.inc.php';
      if (is_file($renewal)) {
        require_once $renewal;
      }
    }
    if (function_exists('강화_독점중복_정리')) {
      @강화_독점중복_정리();
    }
    // 보스 진행 중이면 1분 간격 현황 알림을 info2 큐에 적재
    if (!function_exists('boss_raid_진행알림_틱')) {
      $bossPath = __DIR__ . '/game/boss_raid.inc.php';
      if (is_file($bossPath)) {
        require_once $bossPath;
      }
    }
    if (function_exists('boss_raid_진행알림_틱')) {
      @boss_raid_진행알림_틱();
    }
    $msg = info2알림_큐_꺼내기();
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
 * .등록 일방 닉 목록. `다운,제니` 콤마 형식 우선.
 * @return list<string>
 */
if (!function_exists('등록_일방_닉목록')) {
  function 등록_일방_닉목록($진행) {
    $진행 = trim((string)$진행);
    if ($진행 === '') {
      return [];
    }
    if (strpos($진행, ',') !== false) {
      $out = [];
      foreach (preg_split('/\s*,\s*/u', $진행) as $p) {
        $nn = trim((string)$p);
        if ($nn === '') {
          continue;
        }
        if (function_exists('getTwoCharNick')) {
          $nn = getTwoCharNick($nn);
        }
        if ($nn !== '' && !in_array($nn, $out, true)) {
          $out[] = $nn;
        }
      }
      if (count($out) >= 2) {
        return $out;
      }
    }
    return 진행문자열_닉목록($진행);
  }
}

/**
 * .등록 일방 신규 성사 시 두 닉 일방신청권 각 1개 차감.
 * @param list<string> $닉목록
 * @return array{ok:bool,msg:string,done?:list<array{nick:string,midx:int,qty:int}>}
 */
if (!function_exists('일방등록_신청권_양쪽차감')) {
  function 일방등록_신청권_양쪽차감(array $닉목록) {
    $닉목록 = array_values(array_unique(array_filter(array_map('trim', $닉목록), static function ($n) {
      return $n !== '';
    })));
    if (count($닉목록) < 2) {
      return ['ok' => false, 'msg' => "❌ 닉 2명이 필요해요.\n예) .등록 일방 다운,제니"];
    }
    $대상 = [$닉목록[0], $닉목록[count($닉목록) - 1]];
    if ($대상[0] === $대상[1]) {
      return ['ok' => false, 'msg' => '❌ 서로 다른 닉 2명이 필요해요.'];
    }

    $members = [];
    $부족 = [];
    foreach ($대상 as $nn) {
      $esc = addslashes($nn);
      $회원 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$esc}' LIMIT 1");
      $midx = (int)($회원['idx'] ?? 0);
      if ($midx < 1) {
        $부족[] = "{$nn}(회원 없음)";
        continue;
      }
      if (!function_exists('일방신청권_보유여부') || !일방신청권_보유여부($midx)) {
        $부족[] = $nn;
        continue;
      }
      $members[] = ['nick' => $nn, 'midx' => $midx];
    }
    if ($부족 !== []) {
      return ['ok' => false, 'msg' => '❌ ' . implode(', ', $부족) . ' 님은 일방신청권이 없어 등록할 수 없어요.'];
    }
    if (!function_exists('일방신청권_차감')) {
      return ['ok' => false, 'msg' => '❌ 일방신청권 차감 기능을 불러오지 못했어요.'];
    }

    $done = [];
    foreach ($members as $m) {
      $차감 = 일방신청권_차감((int)$m['midx'], (string)$m['nick'], 1);
      if (empty($차감['ok'])) {
        if (function_exists('일방등록_신청권_환급')) {
          일방등록_신청권_환급($done);
        }
        return ['ok' => false, 'msg' => '❌ ' . $m['nick'] . ' 님 일방신청권 차감에 실패했어요.'];
      }
      $done[] = [
        'nick' => $m['nick'],
        'midx' => (int)$m['midx'],
        'qty' => (int)($차감['qty'] ?? 0),
      ];
      if (function_exists('지급로그')) {
        지급로그('일방신청권-등록사용', $m['nick'], $m['nick'], 0, 1);
      }
    }
    $lines = [];
    foreach ($done as $d) {
      $lines[] = $d['nick'] . ' 남은 ' . $d['qty'] . '개';
    }
    return [
      'ok' => true,
      'done' => $done,
      'msg' => '일방신청권 각 1개 사용 (' . implode(' · ', $lines) . ')',
    ];
  }
}

if (!function_exists('일방등록_신청권_환급')) {
  /** @param list<array{nick?:string,midx?:int}> $done */
  function 일방등록_신청권_환급(array $done) {
    if (!function_exists('item_bag_add')) {
      return;
    }
    foreach ($done as $d) {
      $midx = (int)($d['midx'] ?? 0);
      $nick = trim((string)($d['nick'] ?? ''));
      if ($midx < 1 || $nick === '') {
        continue;
      }
      item_bag_add($midx, $nick, '일방신청권', 1);
    }
  }
}

/**
 * .등록 일방 영수(임티)영희 / 닉,닉 — 등록/삭제 토글. 매칭 시 전송 후 exit.
 * 신규 등록 시 두 닉 일방신청권 각 1개 차감.
 */
if (!function_exists('등록_일방_명령_처리')) {
  function 등록_일방_명령_처리($status, $두자리닉넴) {
    $status = trim((string)$status);
    if (!preg_match('/^\.등록(?:[\s\p{Zs}]+)일방(?:[\s\p{Zs}]+(.*))?$/us', $status, $match)) {
      return false;
    }
    $진행 = trim((string)($match[1] ?? ''));
    if ($진행 === '') {
      echo 전송("❌ 사용법: .등록 일방 다운,제니\n예) .등록 일방 영수❤️영희");
      exit;
    }
    $진행_esc = addslashes($진행);
    $닉목록 = 등록_일방_닉목록($진행);

    $data = db_select("select * from tb_progress where status = '일방' and nick = '{$진행_esc}' ");
    if (!empty($data['idx'])) {
      $result = db_query("delete from tb_progress where idx = {$data['idx']} ");
      if ($result) {
        if (count($닉목록) >= 2) {
          $커플앞 = $닉목록[0];
          $커플뒤 = $닉목록[count($닉목록) - 1];
          if ($커플앞 !== '' && $커플뒤 !== '') {
            $커플앞_esc = addslashes($커플앞);
            $커플뒤_esc = addslashes($커플뒤);
            db_query("UPDATE tb_member SET oneroom = 0 WHERE name = '{$커플앞_esc}'");
            db_query("UPDATE tb_member SET oneroom = 0 WHERE name = '{$커플뒤_esc}'");
          }
        }
      }
      echo 전송("일방 {$진행} 삭제완료!");
      exit;
    }

    $강일제한 = 지목_강일중_제한_검사($닉목록, '일방 등록할');
    if ($강일제한 !== null) {
      echo 전송($강일제한);
      exit;
    }

    $차감결과 = 일방등록_신청권_양쪽차감($닉목록);
    if (empty($차감결과['ok'])) {
      echo 전송((string)($차감결과['msg'] ?? '❌ 일방신청권 차감에 실패했어요.'));
      exit;
    }

    $끝나는날 = date('Y-m-d 23:59', strtotime('+7 days'));
    if (function_exists('진행_nick컬럼_utf8mb4보장')) {
      진행_nick컬럼_utf8mb4보장();
    }
    $result = db_query("insert into tb_progress set status = '일방', nick = '{$진행_esc}', enddate = '{$끝나는날}', regdate = now()  ");
    if ($result) {
      if (count($닉목록) >= 2) {
        $커플앞 = $닉목록[0];
        $커플뒤 = $닉목록[count($닉목록) - 1];
        if ($커플앞 !== '' && $커플뒤 !== '') {
          $커플앞_esc = addslashes($커플앞);
          $커플뒤_esc = addslashes($커플뒤);
          db_query("UPDATE tb_member SET oneroom = 1 WHERE name = '{$커플앞_esc}'");
          db_query("UPDATE tb_member SET oneroom = 1 WHERE name = '{$커플뒤_esc}'");
        }
      }
      $축하문구 = 일방등록_연금_지급및알림($진행, $두자리닉넴);
      $권문구 = trim((string)($차감결과['msg'] ?? ''));
      $권줄 = ($권문구 !== '') ? "\n{$권문구}" : '';
      echo 전송("일방\n{$진행} 등록완료!\n{$끝나는날} 까지{$축하문구}{$권줄}");
      exit;
    }
    if (function_exists('일방등록_신청권_환급')) {
      일방등록_신청권_환급($차감결과['done'] ?? []);
    }
    echo 전송('❌ 일방 등록에 실패했어요.');
    exit;
  }
}

/**
 * tb_progress 조회 — 정확 일치 · 1️⃣ 접미사 · 참가자 닉 2명 포함
 */
if (!function_exists('진행_상태행_조회')) {
  function 진행_상태행_조회($상태, $진행) {
    $상태 = trim((string)$상태);
    $진행 = trim((string)$진행);
    if ($상태 === '' || $진행 === '') {
      return null;
    }
    if (function_exists('진행_nick컬럼_utf8mb4보장')) {
      진행_nick컬럼_utf8mb4보장();
    }
    $상태_esc = addslashes($상태);
    $진행_esc = addslashes($진행);
    $진행1_esc = addslashes($진행 . '1️⃣');
    $data = db_select("SELECT * FROM tb_progress WHERE status = '{$상태_esc}' AND nick = '{$진행_esc}' LIMIT 1");
    if (!empty($data['idx'])) {
      return $data;
    }
    $data = db_select("SELECT * FROM tb_progress WHERE status = '{$상태_esc}' AND nick = '{$진행1_esc}' LIMIT 1");
    if (!empty($data['idx'])) {
      return $data;
    }
    $닉목록 = function_exists('등록_일방_닉목록') ? 등록_일방_닉목록($진행) : 진행문자열_닉목록($진행);
    if (count($닉목록) < 1) {
      return null;
    }
    $rs = db_query("SELECT * FROM tb_progress WHERE status = '{$상태_esc}' ORDER BY idx DESC");
    if (!$rs) {
      return null;
    }
    while ($row = db_fetch($rs)) {
      $참가자 = 진행문자열_닉목록($row['nick'] ?? '');
      $모두있음 = true;
      foreach ($닉목록 as $nn) {
        if (!in_array($nn, $참가자, true)) {
          $모두있음 = false;
          break;
        }
      }
      if ($모두있음) {
        return $row;
      }
    }
    return null;
  }
}

/**
 * .연장 일방 시 두 닉 일방연장권 각 1개 차감.
 * @param list<string> $닉목록
 * @return array{ok:bool,msg:string,done?:list<array{nick:string,midx:int,qty:int}>}
 */
if (!function_exists('일방연장권_양쪽차감')) {
  function 일방연장권_양쪽차감(array $닉목록) {
    $닉목록 = array_values(array_unique(array_filter(array_map('trim', $닉목록), static function ($n) {
      return $n !== '';
    })));
    if (count($닉목록) < 2) {
      return ['ok' => false, 'msg' => "❌ 닉 2명이 필요해요.\n예) .연장 일방 치즈🐸오리"];
    }
    $대상 = [$닉목록[0], $닉목록[count($닉목록) - 1]];
    if ($대상[0] === $대상[1]) {
      return ['ok' => false, 'msg' => '❌ 서로 다른 닉 2명이 필요해요.'];
    }

    $members = [];
    $부족 = [];
    foreach ($대상 as $nn) {
      $esc = addslashes($nn);
      $회원 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$esc}' LIMIT 1");
      $midx = (int)($회원['idx'] ?? 0);
      if ($midx < 1) {
        $부족[] = "{$nn}(회원 없음)";
        continue;
      }
      if (!function_exists('일방연장권_보유여부') || !일방연장권_보유여부($midx)) {
        $부족[] = $nn;
        continue;
      }
      $members[] = ['nick' => $nn, 'midx' => $midx];
    }
    if ($부족 !== []) {
      return ['ok' => false, 'msg' => '❌ ' . implode(', ', $부족) . ' 님은 일방연장권이 없어 연장할 수 없어요.'];
    }
    if (!function_exists('일방연장권_차감')) {
      return ['ok' => false, 'msg' => '❌ 일방연장권 차감 기능을 불러오지 못했어요.'];
    }

    $done = [];
    foreach ($members as $m) {
      $차감 = 일방연장권_차감((int)$m['midx'], (string)$m['nick'], 1);
      if (empty($차감['ok'])) {
        if (function_exists('일방연장권_환급')) {
          일방연장권_환급($done);
        }
        return ['ok' => false, 'msg' => '❌ ' . $m['nick'] . ' 님 일방연장권 차감에 실패했어요.'];
      }
      $done[] = [
        'nick' => $m['nick'],
        'midx' => (int)$m['midx'],
        'qty' => (int)($차감['qty'] ?? 0),
      ];
      if (function_exists('지급로그')) {
        지급로그('일방연장권-연장사용', $m['nick'], $m['nick'], 0, 1);
      }
    }
    $lines = [];
    foreach ($done as $d) {
      $lines[] = $d['nick'] . ' 남은 ' . $d['qty'] . '개';
    }
    return [
      'ok' => true,
      'done' => $done,
      'msg' => '일방연장권 각 1개 사용 (' . implode(' · ', $lines) . ')',
    ];
  }
}

if (!function_exists('일방연장권_환급')) {
  /** @param list<array{nick?:string,midx?:int}> $done */
  function 일방연장권_환급(array $done) {
    if (!function_exists('item_bag_add')) {
      return;
    }
    foreach ($done as $d) {
      $midx = (int)($d['midx'] ?? 0);
      $nick = trim((string)($d['nick'] ?? ''));
      if ($midx < 1 || $nick === '') {
        continue;
      }
      item_bag_add($midx, $nick, '일방연장권', 1);
    }
  }
}

/**
 * .연장 일방 치즈🐸오리 / .연장 강일 … / .연장 지목 …
 * 일방은 양쪽 일방연장권 각 1개 차감 후 +7일.
 */
if (!function_exists('연장_명령_처리')) {
  function 연장_명령_처리($status, $두자리닉넴 = '') {
    unset($두자리닉넴);
    $status = trim((string)$status);
    if (!preg_match('/^\.연장(?:[\s\p{Zs}]+)(\S+)(?:[\s\p{Zs}]+(.+))?$/u', $status, $match)) {
      return false;
    }
    $상태 = trim((string)($match[1] ?? ''));
    $진행 = trim((string)($match[2] ?? ''));
    if (!in_array($상태, ['일방', '강일', '지목'], true) || $진행 === '') {
      echo 전송("❌ 사용법: .연장 일방 치즈🐸오리\n예) .연장 일방 영수❤️영희");
      exit;
    }

    $data = 진행_상태행_조회($상태, $진행);
    if (empty($data['idx'])) {
      echo 전송("❌ {$상태} 진행을 찾을 수 없어요.\n{$진행}");
      exit;
    }

    $닉목록 = function_exists('등록_일방_닉목록') ? 등록_일방_닉목록($진행) : 진행문자열_닉목록($진행);
    if (count($닉목록) < 2) {
      $닉목록 = 진행문자열_닉목록($data['nick'] ?? '');
    }

    $차감결과 = null;
    if ($상태 === '일방') {
      $차감결과 = 일방연장권_양쪽차감($닉목록);
      if (empty($차감결과['ok'])) {
        echo 전송((string)($차감결과['msg'] ?? '❌ 일방연장권 차감에 실패했어요.'));
        exit;
      }
    }

    if ($상태 === '일방') {
      $끝나는날 = date('Y-m-d H:i', strtotime($data['enddate'] . ' +7 days'));
    } elseif ($상태 === '강일') {
      $끝나는날 = date('Y-m-d H:i', strtotime($data['enddate'] . ' +12 hours'));
    } else {
      $끝나는날 = date('Y-m-d H:i', strtotime($data['enddate'] . ' +6 hours'));
    }

    $원닉 = trim((string)($data['nick'] ?? $진행));
    $새닉 = (mb_strpos($원닉, '1️⃣', 0, 'UTF-8') === false) ? ($원닉 . '1️⃣') : $원닉;
    $새닉_esc = addslashes($새닉);
    $끝나는날_esc = addslashes($끝나는날);
    $idx = (int)$data['idx'];
    $result = db_query("UPDATE tb_progress SET nick = '{$새닉_esc}', enddate = '{$끝나는날_esc}' WHERE idx = {$idx} LIMIT 1");
    if ($result) {
      $권문구 = trim((string)($차감결과['msg'] ?? ''));
      $권줄 = ($권문구 !== '') ? "\n{$권문구}" : '';
      echo 전송("{$상태}\n{$새닉} 연장완료!\n{$끝나는날} 까지{$권줄}");
      exit;
    }
    if ($상태 === '일방' && function_exists('일방연장권_환급')) {
      일방연장권_환급($차감결과['done'] ?? []);
    }
    echo 전송("❌ {$상태} 연장에 실패했어요.");
    exit;
  }
}

/**
 * 치즈🐸오리 일방 +7일 · 양쪽 일방연장권 1개 차감 (1회)
 */
if (!function_exists('치즈오리_일방연장_1회적용')) {
  function 치즈오리_일방연장_1회적용() {
    static $ran = false;
    if ($ran) {
      return;
    }
    $ran = true;
    $flag = __DIR__ . '/_once_cheese_duck_ilbang.done';
    if (is_file($flag)) {
      return;
    }
    $lock = @fopen($flag, 'x');
    if ($lock === false) {
      return;
    }
    @fclose($lock);
    $진행 = '치즈🐸오리';
    $data = function_exists('진행_상태행_조회') ? 진행_상태행_조회('일방', $진행) : null;
    $연장됨 = false;
    $끝나는날 = '';
    if (!empty($data['idx'])) {
      $끝나는날 = date('Y-m-d H:i', strtotime($data['enddate'] . ' +7 days'));
      $원닉 = trim((string)($data['nick'] ?? $진행));
      $새닉 = (mb_strpos($원닉, '1️⃣', 0, 'UTF-8') === false) ? ($원닉 . '1️⃣') : $원닉;
      $idx = (int)$data['idx'];
      $ok = db_query("UPDATE tb_progress SET nick = '" . addslashes($새닉) . "', enddate = '" . addslashes($끝나는날) . "' WHERE idx = {$idx} LIMIT 1");
      $연장됨 = (bool)$ok;
    }
    $차감 = function_exists('일방연장권_양쪽차감')
      ? 일방연장권_양쪽차감(['치즈', '오리'])
      : ['ok' => false, 'msg' => '차감 함수 없음'];
    @file_put_contents($flag, date('c') . "\n" . json_encode([
      'extend' => $연장됨,
      'enddate' => $끝나는날,
      'sub' => $차감,
    ], JSON_UNESCAPED_UNICODE));
  }
}

/**
 * .등록 강일 / .등록 지목 — 등록/삭제 토글. 매칭 시 전송 후 exit.
 * 본방·연구실 공통. 이모지 닉(수수🤝 닉네) · 콤마(수수,닉네) 모두 허용.
 */
if (!function_exists('등록_강일지목_명령_처리')) {
  function 등록_강일지목_명령_처리($status, $두자리닉넴) {
    $status = trim((string)$status);
    if (!preg_match('/^\.\s*등록(?:[\s\p{Zs}]+)(강일|지목)(?:[\s\p{Zs}]+(.*))?$/us', $status, $match)) {
      return false;
    }
    $상태 = (string)$match[1];
    $진행 = trim((string)($match[2] ?? ''));
    if ($진행 === '') {
      echo 전송("❌ 사용법: .등록 {$상태} 수수🤝닉네\n예) .등록 {$상태} 영수❤️영희\n예) .등록 {$상태} 다운,제니");
      exit;
    }
    if (function_exists('진행_nick컬럼_utf8mb4보장')) {
      진행_nick컬럼_utf8mb4보장();
    }
    $진행_esc = addslashes($진행);
    $닉목록 = function_exists('등록_일방_닉목록') ? 등록_일방_닉목록($진행) : 진행문자열_닉목록($진행);

    $data = db_select("select * from tb_progress where status = '{$상태}' and nick = '{$진행_esc}' ");
    if (!empty($data['idx'])) {
      $result = db_query("delete from tb_progress where idx = {$data['idx']} ");
      if ($상태 === '강일' && $result) {
        if (!function_exists('강일방_해제_진행행')) {
          $gr = __DIR__ . '/gangil_room.inc.php';
          if (is_file($gr)) {
            require_once $gr;
          }
        }
        if (function_exists('강일방_해제_진행행')) {
          강일방_해제_진행행($data);
        }
        if (function_exists('강일취소_타일_등록')) {
          강일취소_타일_등록($닉목록);
        }
      }
      echo 전송($상태 . " {$진행} 삭제완료!");
      exit;
    }

    if ($상태 === '강일') {
      $끝나는날 = date('Y-m-d H:i', strtotime('+12 hours'));
      if (function_exists('강일_취소제한_검사')) {
        $제한문구 = 강일_취소제한_검사($닉목록);
        if ($제한문구 !== null) {
          echo 전송($제한문구);
          exit;
        }
      }
    } else {
      $끝나는날 = date('Y-m-d H:i', strtotime('+6 hours'));
      if (function_exists('지목_강일중_제한_검사')) {
        $강일제한 = 지목_강일중_제한_검사($닉목록, '지목 등록할');
        if ($강일제한 !== null) {
          echo 전송($강일제한);
          exit;
        }
      }
    }

    $result = db_query("insert into tb_progress set status = '{$상태}', nick = '{$진행_esc}', enddate = '{$끝나는날}', regdate = now()  ");
    if ($result) {
      if (function_exists('아이템_buy에_sell_누적')) {
        아이템_buy에_sell_누적($상태);
      }
      $기록됨 = false;
      foreach ($닉목록 as $nn) {
        if (function_exists('미션완료_기록_if_new')) {
          미션완료_기록_if_new($nn, '일방', $상태);
        }
        $기록됨 = true;
      }
      if (!$기록됨 && function_exists('미션완료_기록_if_new')) {
        미션완료_기록_if_new($두자리닉넴, '일방', $상태);
      }
      echo 전송($상태 . "\n{$진행} 등록완료!\n{$끝나는날} 까지");
      exit;
    }
    echo 전송("❌ {$상태} 등록에 실패했어요.\n닉·이모지를 확인한 뒤 다시 시도해주세요.");
    exit;
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

/** 대상이 강일 진행 중이면 차단 (.지목 / .등록 일방 등) */
if (!function_exists('지목_강일중_제한_검사')) {
  /**
   * @param string $동작 안내 문구용 (예: '지목할', '일방 등록할')
   * @return string|null 차단 시 안내 문구
   */
  function 지목_강일중_제한_검사(array $닉목록, $동작 = '지목할') {
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
    $동작 = trim((string)$동작);
    if ($동작 === '') {
      $동작 = '지목할';
    }
    return "❌ {$목록} 강일 진행 중이라 {$동작} 수 없어요.";
  }
}

/** 진행 중 일방(tb_progress) — 닉이 참가자인 1건 조회 */
if (!function_exists('일방_진행중_닉조회')) {
  function 일방_진행중_닉조회($닉) {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return null;
    }
    $닉_esc = addslashes($닉);
    $rs = db_query("SELECT idx, nick, regdate, enddate FROM tb_progress WHERE status = '일방' AND nick LIKE '%{$닉_esc}%' ORDER BY regdate DESC");
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

/** .지목 대상이 공커·일방이면 차단 (아이템 차감 전) */
if (!function_exists('지목_공커일방_제한_검사')) {
  /**
   * @return string|null 차단 시 안내 문구
   */
  function 지목_공커일방_제한_검사(array $닉목록) {
    $공커중 = [];
    $일방중 = [];
    foreach ($닉목록 as $닉) {
      $닉 = trim((string)$닉);
      if ($닉 === '') {
        continue;
      }
      $닉_esc = addslashes($닉);
      $mem = db_select("SELECT IFNULL(oneroom, 0) AS oneroom FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
      $oneroom = (int)($mem['oneroom'] ?? 0);

      $is공커 = (function_exists('공커_활성_조회') && 공커_활성_조회($닉)) || $oneroom === 2;
      if ($is공커) {
        $공커중[] = $닉;
        continue;
      }

      $일방row = 일방_진행중_닉조회($닉);
      $is일방 = ($일방row !== null) || $oneroom === 1;
      if ($is일방) {
        $일방중[] = $닉;
      }
    }

    $parts = [];
    if ($공커중 !== []) {
      $목록 = implode(', ', array_map(function ($n) {
        return "[ {$n} ]";
      }, $공커중));
      $parts[] = "{$목록} 공커 중이라 지목할 수 없어요.";
    }
    if ($일방중 !== []) {
      $목록 = implode(', ', array_map(function ($n) {
        return "[ {$n} ]";
      }, $일방중));
      $parts[] = "{$목록} 일방 중이라 지목할 수 없어요.";
    }
    if ($parts === []) {
      return null;
    }
    return '❌ ' . implode("\n", $parts);
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

if (!function_exists('강일_조기취소_무기록인가')) {
  /**
   * 강일 시작(regdate) 후 N분 미만이면 취소/종료 시 .기록·타일 잠금 미등록
   */
  function 강일_조기취소_무기록인가(array $대상, $분 = 10): bool {
    $분 = max(1, (int)$분);
    $reg = trim((string)($대상['regdate'] ?? ''));
    if ($reg === '') {
      return false;
    }
    $시작 = strtotime($reg);
    if ($시작 === false) {
      return false;
    }
    return (time() - $시작) < ($분 * 60);
  }
}

if (!function_exists('강일_진행_취소실행')) {
  function 강일_진행_취소실행(array $대상, $접두 = '강일취소', $완료접미 = '취소완료!') {
    if (empty($대상['idx'])) {
      return false;
    }
    if (!function_exists('강일방_해제_진행행')) {
      $path = __DIR__ . '/gangil_room.inc.php';
      if (is_file($path)) {
        require_once $path;
      }
    }
    $진행 = trim((string)($대상['nick'] ?? ''));
    $이름들 = 강일_진행_참가자목록($진행);
    $조기무기록 = 강일_조기취소_무기록인가($대상, 10);

    $idx = (int)$대상['idx'];
    if (function_exists('강일방_해제_진행행')) {
      강일방_해제_진행행($대상);
    }
    if ($조기무기록) {
      db_query("DELETE FROM tb_progress WHERE idx = {$idx}");
      강일_종료_본방알림($이름들, null, 12, '취소');
      echo 전송("{$접두}\n{$진행} {$완료접미}\n(시작 10분 이내 · .기록·재강일제한 없음)");
      exit;
    }

    $타일시간 = ((int)($대상['prolongation'] ?? 0) >= 1) ? 24 : 12;
    $해제시각 = 강일취소_타일_등록($이름들, $타일시간);
    강일기록_등록($대상, 'cancel');
    db_query("DELETE FROM tb_progress WHERE idx = {$idx}");
    $안내 = 강일_일방신청가능_안내($해제시각, $타일시간);
    강일_종료_본방알림($이름들, $해제시각, $타일시간, '취소');
    echo 전송("{$접두}\n{$진행} {$완료접미}\n{$안내}");
    exit;
  }
}

if (!function_exists('강일_진행_종료실행')) {
  /**
   * .강일종료 — .강일취소와 동일하게 참가자 전원 .기록(tb_gangil_tile) 등록, 강일연금 없음
   * (시작 10분 이내면 기록·타일 미등록)
   */
  function 강일_진행_종료실행(array $대상, $접두 = '강일종료', $완료접미 = '종료완료!') {
    if (empty($대상['idx'])) {
      return false;
    }
    if (!function_exists('강일방_해제_진행행')) {
      $path = __DIR__ . '/gangil_room.inc.php';
      if (is_file($path)) {
        require_once $path;
      }
    }
    $진행 = trim((string)($대상['nick'] ?? ''));
    $이름들 = 강일_진행_참가자목록($진행);
    $조기무기록 = 강일_조기취소_무기록인가($대상, 10);

    $idx = (int)$대상['idx'];
    if (function_exists('강일방_해제_진행행')) {
      강일방_해제_진행행($대상);
    }
    if ($조기무기록) {
      db_query("DELETE FROM tb_progress WHERE idx = {$idx}");
      강일_종료_본방알림($이름들, null, 12, '종료');
      echo 전송("{$접두}\n{$진행} {$완료접미}\n(시작 10분 이내 · .기록·재강일제한 없음)");
      exit;
    }

    $타일시간 = ((int)($대상['prolongation'] ?? 0) >= 1) ? 24 : 12;
    $해제시각 = 강일취소_타일_등록($이름들, $타일시간);
    강일기록_등록($대상, 'terminate');
    db_query("DELETE FROM tb_progress WHERE idx = {$idx}");
    $안내 = 강일_일방신청가능_안내($해제시각, $타일시간);
    강일_종료_본방알림($이름들, $해제시각, $타일시간, '종료');
    echo 전송("{$접두}\n{$진행} {$완료접미}\n{$안내}");
    exit;
  }
}

/**
 * .강일취소 / .강일 취소 / .강일종료 — 진행 중 강일 당사자가 취소
 * (tb_gangil_tile 등록 후 tb_progress 삭제)
 */
function 강일취소_명령_처리($status, $두자리닉넴) {
  $정리 = trim((string)$status);
  // .강일취소 · .강일 취소 · .강일종료 (인자 없음) → 본인 강일 취소
  if (!preg_match('/^\.강일(?:[\s\p{Zs}]*)(?:취소|종료)\s*$/u', $정리)) {
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
 * (인자 없는 .강일종료 는 강일취소_명령_처리에서 본인 취소로 처리)
 */
function 강일종료_명령_처리($status, $두자리닉넴) {
  if (!preg_match('/^\.강일종료(?:[\s\p{Zs}]+)(.+)$/u', trim((string)$status), $m)) {
    return;
  }

  $대상닉 = 지목_명령_토큰닉(trim($m[1]));
  if ($대상닉 === '') {
    echo 전송("❌ 사용법: .강일종료 (당사자닉)\n예) .강일종료 유하\n또는 .강일종료 / .강일취소 / .강일 취소 (본인 취소)");
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
  $보유개수 = item_bag_qty_nick($아이템닉, '강일');
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

  $연장차감 = item_bag_sub_nick($아이템닉, '강일', 1);
  if (empty($연장차감['ok'])) {
    echo 전송("❌ {$아이템닉} 님은 강일 아이템이 부족해요. (0/1)");
    exit;
  }
  아이템사용_시세하락('강일', 1);

  $끝나는날 = date('Y-m-d H:i', strtotime($대상['enddate'] . ' +12 hours'));
  $idx = (int)$대상['idx'];
  db_query("UPDATE tb_progress SET enddate = '{$끝나는날}', prolongation = 1 WHERE idx = {$idx}");

  $진행 = trim((string)($대상['nick'] ?? ''));
  echo 전송("강일\n{$진행} 12시간 연장!\n{$끝나는날} 까지\n({$아이템닉} 강일 1개 사용)");
  exit;
}

/** 연구실 봇(info3.php) 요청 여부 */
function 연구실봇_요청여부(): bool {
  $script = basename($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? '');
  return $script === 'info3.php';
}

/** .수호 — 연구실(info3) 항상 / 본방(info1)은 $ADMIN_ROOM_CMDS_IN_MAIN 일 때 */
function 수호_명령_허용여부(): bool {
  if (연구실봇_요청여부()) {
    return true;
  }
  global $ADMIN_ROOM_CMDS_IN_MAIN;
  $script = basename($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? '');
  return ($script === 'info1.php' && !empty($ADMIN_ROOM_CMDS_IN_MAIN));
}

/** 오픈채팅봇(비회원) 닉 여부 */
function 오픈채팅봇_여부($nick = null): bool {
  if ($nick === null && function_exists('nick_파라미터')) {
    $nick = nick_파라미터();
  }
  return trim((string)$nick) === '오픈채팅봇';
}

/**
 * 오픈채팅봇이 회원 검증 없이 쓸 수 있는 공개 조회 명령
 * (.일방신청 등 변형은 제외 — exact match)
 */
function 오픈채팅봇_공개명령인가($status): bool {
  return in_array(trim((string)$status), ['.일방', '.공커', '.진행', '.커플', '.요주의인물', '.위험인물', '.요주인물'], true);
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
    $단축문구 = ($상태 === '일방')
      ? '⏳일방 자숙 1일 단축⚡'
      : '⏳공커 자숙 1일 단축⚡';
  } elseif ($상태 === '보룸제한') {
    $새끝 = date('Y-m-d H:i:s', strtotime($대상['enddate'] . ' -3 hours'));
    $단축문구 = '🔇보이스룸 이용제한 3시간 단축⚡';
  } elseif ($상태 === '채팅제한') {
    $새끝 = date('Y-m-d H:i:s', strtotime($대상['enddate'] . ' -3 hours'));
    $단축문구 = '💬채팅금지(이모티콘 포함) 3시간 단축⚡';
  } elseif ($상태 === '지또제한' || $상태 === '게임제한') {
    $새끝 = date('Y-m-d H:i:s', strtotime($대상['enddate'] . ' -3 hours'));
    $단축문구 = '🎮게임 이용제한 3시간 단축⚡';
  } else {
    return null;
  }

  $수호횟수 = (int)($대상['suho'] ?? 0) + 1;
  $idx = (int)$대상['idx'];
  $만료됨 = (strtotime($새끝) <= time());

  if ($만료됨) {
    if (function_exists('tb_self_건삭제_모금정리')) {
      tb_self_건삭제_모금정리($idx);
    } else {
      db_query("DELETE FROM tb_self WHERE idx = {$idx}");
    }
  } else {
    db_query("UPDATE tb_self SET suho = {$수호횟수}, enddate = '{$새끝}' WHERE idx = {$idx}");
  }

  return [$단축문구, $만료됨, $새끝];
}

/**
 * .수호 (받을닉) [개수] — 연구실·본방(플래그 시). 수호 N개면 N회 단축(가장 최근 항목부터).
 */
function 수호_연구실_명령_처리($status, $두자리닉넴) {
  if (!preg_match('/^\.수호(?:\s|$)/u', trim((string)$status))) {
    return;
  }
  if (!function_exists('수호_명령_허용여부') || !수호_명령_허용여부()) {
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

  $보유개수 = item_bag_qty_nick($사용자, '수호');
  if ($보유개수 < 1) {
    echo 전송("{$사용자} 수호 없음!");
    exit;
  }
  if ($보유개수 < $요청개수) {
    echo 전송("{$사용자} 수호 부족! (보유 {$보유개수}개 · 요청 {$요청개수}개)");
    exit;
  }

  $수호선차감 = item_bag_sub_nick($사용자, '수호', $요청개수);
  if (empty($수호선차감['ok'])) {
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

  // 요청 개수만큼 항상 수호 소모 (제한이 먼저 끝나도 남은 개수는 그대로 차감) — 가방은 위에서 선차감
  for ($i = 0; $i < $요청개수; $i++) {
    $일방공커스킵 = $일방공커스킵 || $이번명령_일방공커적용;

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

    $수호기록 = addslashes("🛡️ {$사용자} 수호 아이템 사용 → {$받을닉} {$단축문구}");
    db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$수호기록}', leverage = 0, item = '{$사용자_esc}', regdate = NOW()");

    if ($상태 === '일방' || $상태 === '공커') {
      $이번명령_일방공커적용 = true;
      $일방공커스킵 = true;
    }

    if ($만료됨) {
      $적용결과[] = "🎉 {$단축문구} → ✨해제 완료!";
    } else {
      $적용결과[] = "✨ {$단축문구}\n📅 해제예정 " . date('m-d H:i', strtotime($새끝));
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
  $msg = "🛡️👼 수호 발동!{$개수표시}\n{$사용자} ➜ {$받을닉}";
  if (!empty($적용결과)) {
    $msg .= "\n\n" . implode("\n", $적용결과);
  }
  if ($소모만 > 0) {
    $msg .= "\n\n⚠️ 제한·자숙이 모두 해제된 뒤 수호 {$소모만}개가 추가로 소모됐어요.";
  }
  if ($남은문구 !== '') {
    $msg .= "\n\n📋 남은 제한/자숙{$남은문구}\n(다음 수호는 가장 최근 항목부터 적용)";
  }
  echo 전송($msg);
  exit;
}

/**
 * .닉변 현재닉 변경닉 — 닉변 아이템 1개 소모 후 회원·연관 테이블 nick 동기화.
 * 로또 구매(tb_game_lotto.nick)도 새 닉으로 이전 → `.로또 내역`·당첨 지급이 변경닉 기준.
 * 연구실(info3)·본방(info1, $ADMIN_ROOM_CMDS_IN_MAIN) 공통. 매칭 시 전송 후 exit.
 */
function 닉변_명령_처리($status) {
  $statusTrim = trim((string)$status);
  if (!preg_match('/^\.닉변(?:\s|$)/u', $statusTrim)) {
    return;
  }
  if (!preg_match('/^\.닉변\s+(\S+)\s+(\S+)\s*$/u', $statusTrim, $match)) {
    echo 전송("✅ 닉네임 변경방법\n\n.닉변 현재닉 변경닉\n예) .닉변 하리 이이");
    exit;
  }

  $기존닉 = trim((string)$match[1]);
  $변경닉 = trim((string)$match[2]);
  if ($기존닉 === '' || $변경닉 === '') {
    echo 전송("❌ 사용법: .닉변 현재닉 변경닉\n예) .닉변 하리 이이");
    exit;
  }
  if ($기존닉 === $변경닉) {
    echo 전송('❌ 현재닉과 변경닉이 같아요.');
    exit;
  }

  $기존닉_sql = addslashes($기존닉);
  $변경닉_sql = addslashes($변경닉);

  $받는친구 = db_select("SELECT * FROM tb_member WHERE name = '{$기존닉_sql}' LIMIT 1");
  if (empty($받는친구['idx'])) {
    echo 전송("{$기존닉} 존재하지 않는 닉네임");
    exit;
  }

  $중복 = db_select("SELECT idx FROM tb_member WHERE name = '{$변경닉_sql}' LIMIT 1");
  if (!empty($중복['idx'])) {
    echo 전송("❌ [ {$변경닉} ] 은(는) 이미 사용 중인 닉네임이에요.");
    exit;
  }

  if (!function_exists('item_bag_sub_nick') && is_file(__DIR__ . '/item_bag.inc.php')) {
    require_once __DIR__ . '/item_bag.inc.php';
  }
  if (!function_exists('item_bag_sub_nick')) {
    echo 전송('❌ 아이템 가방 기능을 불러올 수 없어요.');
    exit;
  }

  $보유개수 = function_exists('item_bag_qty_nick') ? item_bag_qty_nick($기존닉, '닉변') : 0;
  $자동구매문구 = '';
  if ($보유개수 < 1) {
    if (!function_exists('아이템_부족분_자동구매')) {
      echo 전송("{$기존닉} 닉변아이템 없음");
      exit;
    }
    $자동 = 아이템_부족분_자동구매($기존닉, '닉변', 1, true);
    if (empty($자동['ok'])) {
      $자동실패 = trim((string)($자동['msg'] ?? ''));
      $msg = "{$기존닉} 닉변아이템 없음";
      if ($자동실패 !== '') {
        $msg .= "\n" . $자동실패;
      }
      echo 전송($msg);
      exit;
    }
    $자동구매문구 = trim((string)($자동['msg'] ?? ''));
  }

  $닉변차감 = item_bag_sub_nick($기존닉, '닉변', 1);
  if (empty($닉변차감['ok'])) {
    echo 전송("{$기존닉} 닉변아이템 없음");
    exit;
  }
  if (function_exists('아이템사용_시세하락')) {
    아이템사용_시세하락('닉변', 1);
  }

  db_query("UPDATE tb_msg SET nickname = '{$변경닉_sql}' WHERE nickname = '{$기존닉_sql}'");
  db_query("UPDATE tb_attendance SET nickname = '{$변경닉_sql}' WHERE nickname = '{$기존닉_sql}'");
  db_query("UPDATE tb_winner SET nick = '{$변경닉_sql}' WHERE nick = '{$기존닉_sql}'");
  db_query("UPDATE tb_member_item SET nick = '{$변경닉_sql}' WHERE nick = '{$기존닉_sql}'");
  db_query("UPDATE tb_member_item_bag SET nick = '{$변경닉_sql}' WHERE nick = '{$기존닉_sql}' LIMIT 1");
  db_query("UPDATE tb_item_use SET nickname = '{$변경닉_sql}' WHERE nickname = '{$기존닉_sql}'");
  db_query("UPDATE tb_progress SET nick = '{$변경닉_sql}' WHERE nick = '{$기존닉_sql}'");
  db_query("UPDATE tb_couple SET couple = REPLACE(couple, '{$기존닉_sql}', '{$변경닉_sql}') WHERE couple LIKE '%{$기존닉_sql}%'");
  db_query("UPDATE tb_mission SET nick = '{$변경닉_sql}' WHERE nick = '{$기존닉_sql}'");
  db_query("UPDATE tb_lotto_info SET item = '{$변경닉_sql}' WHERE item = '{$기존닉_sql}'");
  db_query("UPDATE tb_game_lotto SET nick = '{$변경닉_sql}' WHERE nick = '{$기존닉_sql}'");
  db_query("UPDATE tb_gifticon SET seller_nick = '{$변경닉_sql}' WHERE seller_nick = '{$기존닉_sql}'");
  db_query("UPDATE tb_gifticon SET buyer_nick = '{$변경닉_sql}' WHERE buyer_nick = '{$기존닉_sql}'");

  require_once __DIR__ . '/game/mining_storage.inc.php';
  if (function_exists('mining_data_rename_nick')) {
    mining_data_rename_nick($기존닉, $변경닉);
  }

  db_query("UPDATE tb_gold_bar SET nick = '{$변경닉_sql}' WHERE nick = '{$기존닉_sql}'");

  foreach ([__DIR__ . '/../page/_gold_vault_lib.php',
            (string)($_SERVER['DOCUMENT_ROOT'] ?? '') . '/page/_gold_vault_lib.php'] as $gv_lib) {
    if ($gv_lib !== '' && is_file($gv_lib)) {
      include_once $gv_lib;
      break;
    }
  }
  if (function_exists('gv_만기원금잔액_닉변경')) {
    gv_만기원금잔액_닉변경($기존닉, $변경닉);
  }
  if (function_exists('gv_원장_닉변경')) {
    gv_원장_닉변경($기존닉, $변경닉);
  }

  $content = (string)($받는친구['content'] ?? '');
  $new_content = $content;
  if ($content !== '' && strpos($content, '닉네임•키') !== false) {
    $lines = explode("\n", $content);
    $first = $lines[0];
    $lines[0] = preg_replace(
      '/닉네임•키\s*:\s*' . preg_quote($기존닉, '/') . '(\s|$)/u',
      '닉네임•키 : ' . $변경닉 . '$1',
      $first,
      1
    );
    $new_content = implode("\n", $lines);
  }
  $content_esc = addslashes($new_content);
  $member_ok = db_query("UPDATE tb_member SET name = '{$변경닉_sql}', content = '{$content_esc}' WHERE name = '{$기존닉_sql}'");

  if ($member_ok) {
    $결과 = "✅{$기존닉} → {$변경닉} 변경완료(닉변아이템 1개 소멸)";
    if ($자동구매문구 !== '') {
      $결과 = $자동구매문구 . "\n\n" . $결과;
    }
    if (function_exists('본방알림_등록')) {
      본방알림_등록($결과, '닉변');
    }
    echo 전송($결과);
  } else {
    echo 전송('❌ 닉네임 최종 반영에 실패했어요. 잠시 후 다시 시도하거나 관리자에게 문의해주세요.');
  }
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

/**
 * 자숙 중 특정 행위: enddate +5시간만 (냥 차감 없음)
 *
 * @return array{applied:bool, notice:string, new_end:?string}
 */
function 자숙_시간연장위반_적용($닉, $라벨) {
  $닉_esc = addslashes(trim((string)$닉));
  $라벨 = trim((string)$라벨);
  if ($닉_esc === '' || $라벨 === '' || !자숙_활성여부($닉)) {
    return ['applied' => false, 'notice' => '', 'new_end' => null];
  }

  db_query("UPDATE tb_self SET enddate = DATE_ADD(enddate, INTERVAL 5 HOUR) WHERE nick = '{$닉_esc}' AND enddate > NOW()");

  $새끝행 = db_select("
    SELECT enddate FROM tb_self
    WHERE nick = '{$닉_esc}' AND enddate > NOW()
    ORDER BY enddate DESC
    LIMIT 1
  ");
  $새끝 = !empty($새끝행['enddate']) ? date('m-d H:i', strtotime($새끝행['enddate'])) : '';

  $기록 = "⚠️ {$닉} 자숙 중 {$라벨}\n자숙 +5시간";
  if ($새끝 !== '') {
    $기록 .= "\n해제예정: {$새끝}";
  }
  $기록_esc = addslashes($기록);
  @db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$기록_esc}', leverage = 0, item = '{$닉_esc}', regdate = NOW()");

  $notice = "⚠️ 자숙 중 {$라벨} 적발!\n자숙 +5시간";
  if ($새끝 !== '') {
    $notice .= " (해제예정 {$새끝})";
  }
  $notice .= "\n\n";

  return ['applied' => true, 'notice' => $notice, 'new_end' => $새끝 !== '' ? $새끝 : null];
}

/**
 * 자숙 중 채굴 장비 강화: enddate +5시간만 (냥 차감 없음 · 1회 강화/배치당 1회)
 *
 * @return array{applied:bool, notice:string, new_end:?string}
 */
function 자숙_채굴강화위반_적용($닉) {
  return 자숙_시간연장위반_적용($닉, '채굴강화');
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

/** 제한 사용 기록 키 (일일 회차 집계용 · tb_gangil_tile) */
if (!function_exists('제한_사용기록_텍스트')) {
  function 제한_사용기록_텍스트($닉): string {
    return '제한사용 ' . trim((string)$닉);
  }
}

/** 오늘 해당 닉이 제한을 사용한 횟수 */
if (!function_exists('제한_오늘사용횟수')) {
  function 제한_오늘사용횟수($닉): int {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return 0;
    }
    $text_esc = addslashes(제한_사용기록_텍스트($닉));
    $오늘 = date('Y-m-d');
    $row = db_select("
      SELECT COUNT(*) AS cnt
      FROM tb_gangil_tile
      WHERE text = '{$text_esc}'
        AND DATE(regdate) = '{$오늘}'
    ");
    return max(0, (int)($row['cnt'] ?? 0));
  }
}

/** 다음 사용에 필요한 개수: 1회=1 · 2회=2 · 3회=3 … */
if (!function_exists('제한_필요개수')) {
  function 제한_필요개수(int $오늘사용횟수): int {
    return max(1, $오늘사용횟수 + 1);
  }
}

if (!function_exists('제한_사용기록_등록')) {
  function 제한_사용기록_등록($닉): void {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return;
    }
    $text_esc = addslashes(제한_사용기록_텍스트($닉));
    $edate = date('Y-m-d 23:59:59');
    db_query("INSERT INTO tb_gangil_tile SET text = '{$text_esc}', edate = '{$edate}', regdate = NOW()");
  }
}

/** bag 우선 · 없으면 tb_member_item 미사용 로우 */
if (!function_exists('제한_아이템_보유수')) {
  function 제한_아이템_보유수($닉): int {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return 0;
    }
    if (function_exists('item_bag_member_by_nick') && function_exists('item_bag_qty')) {
      $mem = item_bag_member_by_nick($닉);
      if ($mem !== null) {
        $midx = (int)$mem['idx'];
        $bagRow = @db_select("SELECT midx FROM tb_member_item_bag WHERE midx = {$midx} LIMIT 1");
        if (!empty($bagRow['midx']) && function_exists('item_bag_tracked') && item_bag_tracked('제한')) {
          return item_bag_qty($midx, '제한');
        }
      }
    }
    $esc = addslashes($닉);
    $row = db_select("
      SELECT COUNT(*) AS c
      FROM tb_member_item
      WHERE nick = '{$esc}' AND itemname = '제한' AND status = 0
    ");
    return max(0, (int)($row['c'] ?? 0));
  }
}

/**
 * @return array{ok:bool,msg?:string}
 */
if (!function_exists('제한_아이템_차감')) {
  function 제한_아이템_차감($닉, int $개수): array {
    $닉 = trim((string)$닉);
    $개수 = max(1, $개수);
    if ($닉 === '') {
      return ['ok' => false, 'msg' => '닉네임 없음'];
    }

    if (function_exists('item_bag_member_by_nick') && function_exists('item_bag_sub_nick')) {
      $mem = item_bag_member_by_nick($닉);
      if ($mem !== null) {
        $midx = (int)$mem['idx'];
        $bagRow = @db_select("SELECT midx FROM tb_member_item_bag WHERE midx = {$midx} LIMIT 1");
        if (!empty($bagRow['midx']) && function_exists('item_bag_tracked') && item_bag_tracked('제한')) {
          $r = item_bag_sub_nick($닉, '제한', $개수);
          if (!empty($r['ok'])) {
            return ['ok' => true];
          }
          return ['ok' => false, 'msg' => $r['msg'] ?? '제한 아이템 부족'];
        }
      }
    }

    $esc = addslashes($닉);
    $rs = db_query("
      SELECT idx FROM tb_member_item
      WHERE nick = '{$esc}' AND itemname = '제한' AND status = 0
      ORDER BY idx ASC
      LIMIT {$개수}
    ");
    $ids = [];
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $ids[] = (int)$row['idx'];
      }
    }
    if (count($ids) < $개수) {
      return ['ok' => false, 'msg' => '제한 아이템이 부족해요. (' . count($ids) . "/{$개수})"];
    }
    $idList = implode(',', $ids);
    db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx IN ({$idList})");
    return ['ok' => true];
  }
}

/**
 * .채팅제한 / .익명채팅제한 등 — 연구실(info3) 전용.
 * 익명 명령은 본방(tb_lotto_info) 알림을 item=익명 으로 등록.
 * 차감: 오늘 1회차=1개 · 2회차=2개 · 3회차=3개 …
 */
function 제한_연구실_명령_처리($status, $두자리닉넴) {
  if (!preg_match('/^\.(?:익명)?(채팅|보룸|게임)제한\s+(\S+)\s*$/u', trim((string)$status), $match)) {
    return;
  }
  if (!function_exists('연구실봇_요청여부') || !연구실봇_요청여부()) {
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

  $오늘사용 = 제한_오늘사용횟수($두자리닉넴);
  $필요개수 = 제한_필요개수($오늘사용);
  $보유 = 제한_아이템_보유수($두자리닉넴);
  if ($보유 < $필요개수) {
    echo 전송(
      "❌ 제한 아이템이 부족해요. ({$보유}/{$필요개수})\n"
      . "오늘 {$두자리닉넴} 님 " . ($오늘사용 + 1) . "회차 → {$필요개수}개 필요"
    );
    exit;
  }

  $차감 = 제한_아이템_차감($두자리닉넴, $필요개수);
  if (empty($차감['ok'])) {
    echo 전송('❌ ' . ($차감['msg'] ?? '제한 아이템 차감 실패'));
    exit;
  }
  if (function_exists('아이템사용_시세하락')) {
    아이템사용_시세하락('제한', $필요개수);
  }
  제한_사용기록_등록($두자리닉넴);

  $data = db_select("SELECT * FROM tb_self WHERE status = '{$db상태}' AND nick = '{$진행_esc}' ");
  if (!empty($data['idx'])) {
    db_query("UPDATE tb_self SET enddate = DATE_ADD(enddate, INTERVAL 3 HOUR) WHERE idx = {$data['idx']}");
    $새끝 = db_select("SELECT enddate FROM tb_self WHERE idx = {$data['idx']}");
    $새끝날 = !empty($새끝['enddate']) ? date('Y-m-d H:i', strtotime($새끝['enddate'])) : '';
    $응답 = "{$표시명} {$진행} 3시간 연장!\n제한 {$필요개수}개 차감 (오늘 " . ($오늘사용 + 1) . "회차)\n해제예정: {$새끝날}";
    if ($익명 && function_exists('본방알림_등록')) {
      본방알림_등록($응답, '익명');
    }
    echo 전송($응답);
    exit;
  }

  $끝나는날 = date('Y-m-d H:i:s', strtotime('+3 hours'));
  db_query("INSERT INTO tb_self SET status = '{$db상태}', nick = '{$진행_esc}', enddate = '{$끝나는날}', regdate = NOW()");
  $끝표시 = date('Y-m-d H:i', strtotime($끝나는날));
  $응답 = "{$표시명}\n{$진행} 3시간 {$표시명} 등록완료!\n제한 {$필요개수}개 차감 (오늘 " . ($오늘사용 + 1) . "회차)\n{$끝표시} 까지";
  if ($익명 && function_exists('본방알림_등록')) {
    본방알림_등록($응답, '익명');
  }
  echo 전송($응답);
  exit;
}

/** .제한 / .익명제한 사용법 안내 (연구실) */
function 제한_연구실_안내_처리($status) {
  $trim = trim((string)$status);

  if ($trim === '.익명제한' || (strpos($trim, '.익명제한') === 0 && !preg_match('/^\.익명(?:채팅|보룸|게임)제한\s+\S+\s*$/u', $trim))) {
    echo 전송("익명 제한 아이템 사용법\n\n.익명채팅제한 닉네임\n.익명보룸제한 닉네임\n.익명게임제한 닉네임\n\n※ 본방 공지는 익명으로 전송됩니다.\n※ 닉네임은 우리방 2글자 닉 1개만 (예: .익명게임제한 소이)\n※ 차감: 오늘 1회차=1개 · 2회차=2개 · 3회차=3개 …");
    exit;
  }

  if (strpos($trim, '.제한') !== false && strpos($trim, '.익명') !== 0) {
    echo 전송("제한 아이템 사용법\n\n.채팅제한 닉네임\n.보룸제한 닉네임\n.게임제한 닉네임\n\n※ 닉네임은 우리방 2글자 닉 1개만 (예: .게임제한 소이)\n※ 차감: 오늘 1회차=1개 · 2회차=2개 · 3회차=3개 …\n\n익명(본방) 사용: .익명제한");
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
    // CONCAT('N', …) 접두 제거
    if (isset($s[0]) && ($s[0] === 'N' || $s[0] === 'n')) {
      $s = substr($s, 1);
      $s = trim($s);
      if ($s === '' || $s === '-' || $s === '+') {
        return '0';
      }
    }
    // 과학적 표기 문자열 (mysqli/JSON 등) — float 재변환 금지
    // ※ 반드시 소수점 절단보다 먼저 처리 (안 그러면 1.23e+20 → "1")
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

/** 부호 포함 정수 문자열 — 신불(마이너스) 보존. 예) -12억 → "-1200000000" */
if (!function_exists('냥_부호포함_정수문자열')) {
  function 냥_부호포함_정수문자열($v): string {
    $raw = trim((string)$v);
    $음수 = false;
    if ($raw !== '') {
      if (preg_match('/^N?-/i', $raw)) {
        $음수 = true;
      } elseif (is_numeric($raw) && (float)$raw < 0) {
        $음수 = true;
      } elseif (is_int($v) && $v < 0) {
        $음수 = true;
      } elseif (is_float($v) && is_finite($v) && $v < 0) {
        $음수 = true;
      }
    }
    $digits = function_exists('냥_정수문자열') ? 냥_정수문자열($v) : (ltrim(preg_replace('/[^\d]/', '', $raw), '0') ?: '0');
    if ($digits === '0') {
      return '0';
    }
    return $음수 ? ('-' . $digits) : $digits;
  }
}

/**
 * DB/CONCAT('N')/과학적표기 원문을 정수 문자열로.
 * 소수점 선절단 금지 — 1.23e+20 을 "1"로 깨던 버그 방지.
 */
if (!function_exists('냥_금액원문_정규화')) {
  function 냥_금액원문_정규화($raw): string {
    return 냥_정수문자열($raw);
  }
}

/** 큰 금액 비율 계산 (내림) — 예: 냥_비율내림($총량, 0.02) = 2% · float 곱 금지(천경↑ 깨짐) */
if (!function_exists('냥_비율내림')) {
  /** @return int|string */
  function 냥_비율내림($금액, float $비율) {
    $s = 냥_정수문자열($금액);
    if ($s === '0' || $비율 <= 0) {
      return 0;
    }
    // 0~1 비율 → 정수 % 후 ×pct/100 (0.70 → 70). float sprintf·(float)×금액 금지.
    $pctInt = (int)round($비율 * 100);
    if ($비율 <= 1.0 + 1e-12) {
      if ($pctInt < 0) {
        $pctInt = 0;
      }
      if ($pctInt > 100) {
        $pctInt = 100;
      }
    } elseif ($pctInt < 0) {
      $pctInt = 0;
    }
    if ($pctInt <= 0) {
      return 0;
    }
    if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bccomp')) {
      $q = bcdiv(bcmul($s, (string)$pctInt, 0), '100', 0);
      // 항상 문자열 반환 — (int) 캐스팅 시 922경 초과분·연쇄 계산이 깨짐
      return $q;
    }
    // bcmath 없음: 문자열 곱·나눗셈 (float 금지)
    if (function_exists('냥_금액_문자열곱') && function_exists('냥_문자열나눗셈내림')) {
      return 냥_문자열나눗셈내림(냥_금액_문자열곱($s, (string)$pctInt), '100');
    }
    if (function_exists('냥_나눗셈내림') && function_exists('냥_금액_문자열곱')) {
      return 냥_정수문자열(냥_나눗셈내림(냥_금액_문자열곱($s, (string)$pctInt), 100));
    }
    // 최후: 작은 금액만 float
    if (strlen($s) <= 15) {
      return (string)(int)floor(((float)$s * $pctInt) / 100.0);
    }
    return '0';
  }
}

/** 큰 정수 문자열 ÷ 작은 정수 (내림) — bcmath 없이도 경·해 단위 안전 */
if (!function_exists('냥_문자열나눗셈내림')) {
  function 냥_문자열나눗셈내림(string $피제수, string $제수): string {
    $피제수 = ltrim(preg_replace('/\D/', '', $피제수), '0') ?: '0';
    $제수 = ltrim(preg_replace('/\D/', '', $제수), '0') ?: '0';
    if ($피제수 === '0' || $제수 === '0') {
      return '0';
    }
    if (function_exists('bcdiv')) {
      $q = bcdiv($피제수, $제수, 0);
      return (is_string($q) && preg_match('/^\d+$/', $q)) ? (ltrim($q, '0') ?: '0') : '0';
    }
    // 제수가 PHP int 범위일 때만 장제법
    if (strlen($제수) > 18 || (strlen($제수) === 18 && $제수 > (string)PHP_INT_MAX)) {
      return '0';
    }
    $div = (int)$제수;
    if ($div <= 0) {
      return '0';
    }
    $out = '';
    $remain = 0;
    $len = strlen($피제수);
    $started = false;
    for ($i = 0; $i < $len; $i++) {
      $remain = $remain * 10 + (int)$피제수[$i];
      $digit = intdiv($remain, $div);
      $remain = $remain % $div;
      if ($started || $digit > 0) {
        $out .= (string)$digit;
        $started = true;
      }
    }
    return $out !== '' ? $out : '0';
  }
}

/** 큰 정수 문자열 ÷ 제수 (올림) — bcmath 없이도 안전 */
if (!function_exists('냥_문자열나눗셈올림')) {
  function 냥_문자열나눗셈올림(string $피제수, string $제수): string {
    $피제수 = ltrim(preg_replace('/\D/', '', $피제수), '0') ?: '0';
    $제수 = ltrim(preg_replace('/\D/', '', $제수), '0') ?: '0';
    if ($피제수 === '0' || $제수 === '0') {
      return '0';
    }
    $q = 냥_문자열나눗셈내림($피제수, $제수);
    $복원 = function_exists('냥_금액_문자열곱')
      ? 냥_금액_문자열곱($q, $제수)
      : (function_exists('bcmul') ? bcmul($q, $제수, 0) : '0');
    $복원 = ltrim(preg_replace('/\D/', '', (string)$복원), '0') ?: '0';
    // 피제수 > 복원 이면 나머지 있음 → +1
    $큼 = (strlen($피제수) > strlen($복원))
      || (strlen($피제수) === strlen($복원) && $피제수 > $복원);
    if ($큼) {
      if (function_exists('냥_금액_문자열합')) {
        return 냥_금액_문자열합($q, '1');
      }
      if (function_exists('bcadd')) {
        return bcadd($q, '1', 0);
      }
      // q가 PHP_INT_MAX 이하일 때만
      if (strlen($q) < strlen((string)PHP_INT_MAX)
        || (strlen($q) === strlen((string)PHP_INT_MAX) && $q < (string)PHP_INT_MAX)) {
        return (string)((int)$q + 1);
      }
    }
    return $q;
  }
}

/** 큰 금액 절반 (내림) — bcmath 없어도 문자열로 처리 */
if (!function_exists('냥_반액내림')) {
  function 냥_반액내림($금액): string {
    $s = function_exists('냥_정수문자열') ? 냥_정수문자열($금액) : preg_replace('/\D/', '', (string)$금액);
    $s = ltrim((string)$s, '0') ?: '0';
    if ($s === '0') {
      return '0';
    }
    return 냥_문자열나눗셈내림($s, '2');
  }
}

if (!function_exists('냥_나눗셈내림')) {
  /** @return string */
  function 냥_나눗셈내림($금액, $나누는수) {
    $s = 냥_정수문자열($금액);
    $d = 냥_정수문자열($나누는수);
    if ($s === '0' || $d === '0') {
      return '0';
    }
    return 냥_문자열나눗셈내림($s, $d);
  }
}

/** 정수 금액에서 앞 1자리만 남기고 나머지는 반올림해 0. 예) 612312312 → 600000000, 652 → 700 */
if (!function_exists('냥_앞한자리_반올림')) {
  function 냥_앞한자리_반올림($금액): string {
    $s = 냥_정수문자열($금액);
    if ($s === '0' || $s === '') {
      return '0';
    }
    $len = strlen($s);
    if ($len === 1) {
      return $s;
    }
    $first = (int)$s[0];
    $next = (int)$s[1];
    if ($next >= 5) {
      $first++;
    }
    if ($first >= 10) {
      return '1' . str_repeat('0', $len);
    }
    return (string)$first . str_repeat('0', $len - 1);
  }
}

/** 꼬벙·1주년·부루마블 1등 — 실시간 본방냥 시총 10% (앞자리 반올림) */
if (!function_exists('아이템_본방시총가_대상인가')) {
  function 아이템_본방시총가_대상인가(string $sname): bool {
    $n = trim($sname);
    if ($n === '꼬병기념주화' || $n === '🌶️꼬벙기념주화' || $n === '제1회🌶️꼬벙기념주화' || $n === '제1회꼬벙기념주화') {
      $n = '꼬벙기념주화';
    }
    if ($n === '꼬벙기념주화' || $n === '1주년기념주화') {
      return true;
    }
    return (bool)preg_match('/^제\d+회부루마블1등$/u', $n);
  }
}

if (!function_exists('아이템_본방시총가_단가')) {
  /** @return string 본방냥 정수 */
  function 아이템_본방시총가_단가(string $sname = ''): string {
    $총 = function_exists('시세기준_본방냥_문자열')
      ? 시세기준_본방냥_문자열()
      : 냥_정수문자열(function_exists('시세기준_본방냥') ? 시세기준_본방냥() : 0);
    if ($총 === '0' || $총 === '') {
      return '0';
    }
    if (function_exists('bcdiv')) {
      $원가 = bcdiv($총, '10', 0);
    } else {
      $원가 = (strlen($총) > 1) ? (ltrim(substr($총, 0, -1), '0') ?: '0') : '0';
    }
    $가격 = 냥_앞한자리_반올림($원가);
    return ($가격 === '0') ? '1' : $가격;
  }
}

/** SQL INSERT용 정수 리터럴 (PHP_INT_MAX 초과도 문자열 숫자로 유지) */
if (!function_exists('냥_SQL정수')) {
  function 냥_SQL정수($v): string {
    return 냥_정수문자열($v);
  }
}

/** 게임냥 표시 공통 — (int) 캐스팅 없이 축약 */
if (!function_exists('게임냥_안전표시')) {
  function 게임냥_안전표시($금액, $단위 = ''): string {
    if (function_exists('랭킹_게임냥표시')) {
      return 랭킹_게임냥표시($금액, $단위);
    }
    if (function_exists('냥_경조_축약표시')) {
      return 냥_경조_축약표시($금액, $단위);
    }
    return 냥_숫자콤마($금액) . $단위;
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
      '개인금고_만기원금누적' => "ADD COLUMN `개인금고_만기원금누적` DECIMAL(65,0) NOT NULL DEFAULT 0 COMMENT '개인금고 정상 만기 원금 누적(스왑 게임냥 제외용)'",
    ];
    $columnInfo = [];
    $columnRs = @db_query("SHOW COLUMNS FROM config");
    if ($columnRs) {
      while ($columnRow = db_fetch($columnRs)) {
        $field = (string)($columnRow['Field'] ?? $columnRow['FIELD'] ?? '');
        if ($field !== '') {
          $columnInfo[$field] = $columnRow;
        }
      }
    }
    foreach ($cols as $name => $ddl) {
      if (empty($columnInfo[$name])) {
        @db_query("ALTER TABLE config {$ddl}");
        $columnInfo[$name] = [
          'Field' => $name,
          'Type' => $name === '시세기준_게임냥' ? 'decimal(40,0)' : '',
        ];
      }
    }
    // BIGINT/좁은 DECIMAL → DECIMAL(40,0): PHP_INT_MAX·BIGINT(~922경) 초과 총량 보존
    $ptCol = $columnInfo['시세기준_게임냥'] ?? [];
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

/** status=0 회원 실시간 newpoint·point 합계 (게임냥 마이너스·신불 보유는 0으로 취급) */
if (!function_exists('시세기준_실시간합계')) {
  function 시세기준_실시간합계(): array {
    // CONCAT('N', CAST(DECIMAL AS CHAR)) — mysqli float/과학적표기 차단
    // GREATEST(...,0): 신불자 마이너스 point 가 총량·스왑·시세를 깎지 않도록
    $row = db_select("
      SELECT
        COALESCE(SUM(newpoint), 0) AS total_np,
        CONCAT('N', CAST(COALESCE(SUM(GREATEST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)), 0)), 0) AS CHAR)) AS total_pt
      FROM tb_member
      WHERE status = 0
    ");
    $pt = 냥_금액원문_정규화($row['total_pt'] ?? 'N0');
    // 합이 음수로 나오면(구버전 쿼리 등) 절댓값 취하지 않고 0
    if (isset($pt[0]) && $pt[0] === '-') {
      $pt = '0';
    }
    return [
      '본방냥' => (float)($row['total_np'] ?? 0),
      '게임냥' => $pt,
    ];
  }
}

/** 개인금고 정상 만기 원금 누적액 조회 (스왑 게임냥 제외용) */
if (!function_exists('개인금고_만기원금누적_조회')) {
  function 개인금고_만기원금누적_조회(): string {
    if (function_exists('시세기준_컬럼_보장')) {
      시세기준_컬럼_보장();
    } elseif (function_exists('gv_만기원금누적_컬럼보장')) {
      gv_만기원금누적_컬럼보장();
    }
    $row = @db_select("
      SELECT CONCAT('N', CAST(IFNULL(`개인금고_만기원금누적`, 0) AS CHAR)) AS amt
      FROM config
      LIMIT 1
    ");
    return 냥_금액원문_정규화($row['amt'] ?? 'N0');
  }
}

/**
 * 스왑/환율용 게임냥 = max(0, 실시간 합계 - 개인금고 만기 원금 누적)
 * 일반 보유·표시 총량은 바꾸지 않고 스왑 비율 계산에만 사용
 */
if (!function_exists('스왑_게임냥_조정')) {
  function 스왑_게임냥_조정($게임냥총합): string {
    $total = function_exists('냥_정수문자열')
      ? 냥_정수문자열($게임냥총합)
      : (ltrim(preg_replace('/\D/', '', (string)$게임냥총합), '0') ?: '0');
    $exclude = 개인금고_만기원금누적_조회();
    if ($exclude === '0' || $exclude === '') {
      return $total;
    }
    if (function_exists('냥_금액_문자열차감')) {
      return 냥_금액_문자열차감($total, $exclude);
    }
    if (function_exists('bcsub') && function_exists('bccomp')) {
      if (bccomp($total, $exclude, 0) < 0) {
        return '0';
      }
      return bcsub($total, $exclude, 0);
    }
    return '0';
  }
}

/** config 시세 스냅샷 갱신 — 크론·최초·1시간 경과 시 (자동은 $시세스냅샷_자동갱신_중지로 일시중지 가능) */
if (!function_exists('시세기준_스냅샷_자동갱신_중지인가')) {
  function 시세기준_스냅샷_자동갱신_중지인가(): bool {
    global $시세스냅샷_자동갱신_중지;
    if (isset($시세스냅샷_자동갱신_중지)) {
      return !empty($시세스냅샷_자동갱신_중지);
    }
    return defined('시세스냅샷_자동갱신_중지') && 시세스냅샷_자동갱신_중지;
  }
}
if (!defined('시세스냅샷_자동갱신_중지')) {
  /** config 미로드 환경(크론 등) 기본값 — 추석 자동 스냅샷 중지 */
  define('시세스냅샷_자동갱신_중지', true);
}

if (!function_exists('시세기준_스냅샷_갱신')) {
  /**
   * @param bool $강제 호환용
   * @param bool $수동 true = 관리방 `.스냅샷` 등 수동 (자동중지 중에도 갱신)
   */
  function 시세기준_스냅샷_갱신(bool $강제 = false, bool $수동 = false): array {
    시세기준_컬럼_보장();

    if (!$수동 && 시세기준_스냅샷_자동갱신_중지인가()) {
      $row = db_select("SELECT `시세기준_본방냥`,
          CONCAT('N', CAST(IFNULL(`시세기준_게임냥`, 0) AS CHAR)) AS `시세기준_게임냥`,
          `시세기준_갱신시각` FROM config LIMIT 1");
      $np = (float)($row['시세기준_본방냥'] ?? 0);
      $pt = function_exists('냥_금액원문_정규화')
        ? 냥_금액원문_정규화($row['시세기준_게임냥'] ?? 'N0')
        : 냥_정수문자열($row['시세기준_게임냥'] ?? 0);
      $at = trim((string)($row['시세기준_갱신시각'] ?? ''));
      return [
        'ok' => true,
        'paused' => true,
        '본방냥' => $np,
        '게임냥' => $pt,
        '갱신시각' => $at !== '' ? $at : date('Y-m-d H:i:s'),
        '강제' => $강제,
        '수동' => false,
      ];
    }

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
      '수동' => $수동,
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
    $row = db_select("SELECT `시세기준_본방냥`,
        CONCAT('N', CAST(`시세기준_게임냥` AS CHAR)) AS `시세기준_게임냥`,
        `시세기준_갱신시각` FROM config LIMIT 1");
    $np = (float)($row['시세기준_본방냥'] ?? 0);
    $pt = 냥_금액원문_정규화($row['시세기준_게임냥'] ?? 'N0');
    $at = trim((string)($row['시세기준_갱신시각'] ?? ''));

    $needsRefresh = ($np <= 0 && $pt === '0') || ($at === '');
    // 예전 BIGINT/int 상한(922경3372조)에 고정된 스냅샷이면 즉시 재집계
    if (!$needsRefresh && $pt === (string)PHP_INT_MAX) {
      $needsRefresh = true;
    }
    // 자동 갱신 중지(추석 등)면 1시간 경과·빈값 자동 재집계 안 함 — 기존값 유지
    if (시세기준_스냅샷_자동갱신_중지인가()) {
      $needsRefresh = false;
    } elseif (!$needsRefresh && $at !== '') {
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

/**
 * 요청 단위 실시간 합계 캐시 (회원 소수라 스냅샷 대신 매번 SUM)
 * @return array{본방냥: float, 게임냥: string}
 */
if (!function_exists('시세기준_실시간_로드')) {
  function 시세기준_실시간_로드(): array {
    static $cache = null;
    if (is_array($cache)) {
      return $cache;
    }
    $live = 시세기준_실시간합계();
    $cache = [
      '본방냥' => (float)($live['본방냥'] ?? 0),
      '게임냥' => 냥_정수문자열($live['게임냥'] ?? 0),
    ];
    return $cache;
  }
}

/** 경제 시세 산정용 본방냥 총량 (실시간 SUM · 요청당 1회) */
if (!function_exists('시세기준_본방냥_문자열')) {
  function 시세기준_본방냥_문자열(): string {
    $row = @db_select("
      SELECT CONCAT('N', CAST(FLOOR(COALESCE(SUM(IFNULL(newpoint, 0)), 0)) AS CHAR)) AS total_np
      FROM tb_member
      WHERE status = 0
    ");
    $s = function_exists('냥_금액원문_정규화')
      ? 냥_금액원문_정규화($row['total_np'] ?? 'N0')
      : 냥_정수문자열($row['total_np'] ?? 0);
    if ($s === '' || (isset($s[0]) && $s[0] === '-')) {
      return '0';
    }
    return $s;
  }
}

/** 경제 시세 산정용 본방냥 총량 (실시간 SUM · 요청당 1회) */
if (!function_exists('시세기준_본방냥')) {
  function 시세기준_본방냥(): float {
    if (function_exists('시세기준_본방냥_문자열')) {
      $s = 시세기준_본방냥_문자열();
      if (strlen($s) > 15) {
        return (float)$s;
      }
      return (float)$s;
    }
    $live = 시세기준_실시간_로드();
    return (float)$live['본방냥'];
  }
}

/** 경제 시세 산정용 게임냥 총량 문자열 (실시간 SUM · 신불 마이너스 제외 · 요청당 1회) */
if (!function_exists('시세기준_게임냥_문자열')) {
  function 시세기준_게임냥_문자열(): string {
    $live = 시세기준_실시간_로드();
    return 냥_정수문자열($live['게임냥'] ?? 0);
  }
}

/**
 * 경제 시세 산정용 게임냥 총량 (실시간)
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
    return '실시간';
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
    $전체보유냥 = 냥_정수문자열($전체보유냥);

    // percent% of total = total * percent / 100
    // percent를 1e8 스케일 정수로 (0.0015 → 150000) 후 ÷ (100*1e8)
    $pctScaled = (int)round(((float)$percent) * 100000000.0);
    if ($pctScaled <= 0 || $전체보유냥 === '0') {
      $금액 = '1';
    } elseif (function_exists('bcmul') && function_exists('bcadd') && function_exists('bccomp')) {
      $비율 = ((float)$percent) / 100;
      $ratioStr = sprintf('%.12F', $비율);
      $raw = bcmul($전체보유냥, $ratioStr, 12);
      $금액 = bcadd($raw, '0', 0);
      if (bccomp($raw, $금액, 12) > 0) {
        $금액 = bcadd($금액, '1', 0);
      }
      if (bccomp($금액, '1', 0) < 0) {
        $금액 = '1';
      }
    } elseif (function_exists('냥_금액_문자열곱') && function_exists('냥_문자열나눗셈올림')) {
      // ceil(total * pctScaled / 10_000_000_000) — float 금지 (bcmath 없는 서버)
      $분자 = 냥_금액_문자열곱($전체보유냥, (string)$pctScaled);
      $금액 = 냥_문자열나눗셈올림($분자, '10000000000');
      if ($금액 === '0') {
        $금액 = '1';
      }
    } else {
      // 최후: 15자리 이하만 float
      if (strlen($전체보유냥) <= 15) {
        $금액 = (string)max(1, (int)ceil(((float)$전체보유냥) * (((float)$percent) / 100.0)));
      } else {
        $금액 = '1';
      }
    }

    if ($앞두자리만유지 && function_exists('냥_앞두자리_뒤0')) {
      if (function_exists('bccomp') && bccomp($금액, (string)PHP_INT_MAX, 0) <= 0) {
        return 냥_앞두자리_뒤0((int)$금액);
      }
      if (strlen($금액) < strlen((string)PHP_INT_MAX)
        || (strlen($금액) === strlen((string)PHP_INT_MAX) && $금액 <= (string)PHP_INT_MAX)) {
        return 냥_앞두자리_뒤0((int)$금액);
      }
    }
    if (function_exists('bccomp')) {
      if (bccomp($금액, (string)PHP_INT_MAX, 0) <= 0) {
        return (int)$금액;
      }
      return $금액;
    }
    if (strlen($금액) < strlen((string)PHP_INT_MAX)
      || (strlen($금액) === strlen((string)PHP_INT_MAX) && $금액 <= (string)PHP_INT_MAX)) {
      return (int)$금액;
    }
    return $금액;
  }
}

/** 모금 1초당 필요 냥 = 전체 게임냥(또는 스냅샷) × 0.001% ÷ 12 (최소 1)
 *  구버전은 12초당(시총×0.001%)이었고, 1초 단가는 그 값을 12로 나눈 것.
 */
function 모금나눗값구하기()
{
  if (function_exists('전체냥기준금액')) {
    // 0.001%/12 = 0.000083333...% of total
    $q = 전체냥기준금액(0.001 / 12.0);
    $s = function_exists('냥_정수문자열') ? 냥_정수문자열($q) : (string)$q;
    if ($s === '' || $s === '0') {
      return 1;
    }
    if (function_exists('bccomp') && bccomp($s, (string)PHP_INT_MAX, 0) > 0) {
      return PHP_INT_MAX;
    }
    return max(1, (int)$s);
  }
  global $전체포인트;
  $총냥 = $전체포인트['total_point'] ?? 0;
  if (function_exists('시세기준_게임냥_문자열')) {
    $총냥 = 시세기준_게임냥_문자열();
  }
  return 모금나눗값_총냥기준($총냥);
}

/** 총 게임냥 기준 모금 1초당 단가 — PHP_INT_MAX 초과 시 문자열 */
function 모금나눗값_총냥기준($총냥)
{
  $총냥 = function_exists('냥_정수문자열') ? 냥_정수문자열($총냥) : (ltrim(preg_replace('/[^\d]/', '', (string)$총냥), '0') ?: '0');
  if ($총냥 === '0') {
    return 1;
  }
  // ceil(total × 0.001% / 12) = ceil(total × 0.00001 / 12)
  if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bcadd') && function_exists('bccomp')) {
    $raw = bcmul($총냥, '0.00001', 12);
    $raw = bcdiv($raw, '12', 12);
    $q = bcadd($raw, '0', 0);
    if (bccomp($raw, $q, 12) > 0) {
      $q = bcadd($q, '1', 0);
    }
    if (bccomp($q, '1', 0) < 0) {
      return 1;
    }
    if (bccomp($q, (string)PHP_INT_MAX, 0) > 0) {
      return $q;
    }
    return max(1, (int)$q);
  }
  if (strlen($총냥) <= 15) {
    return max(1, (int)ceil(((float)$총냥) * 0.00001 / 12.0));
  }
  return 1;
}

/** tb_self.mogum_unit / game_point_snap — 자숙(일방·공커) 입장 시점 모금 기준 */
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
      @db_query("ALTER TABLE tb_self ADD COLUMN `mogum_unit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '자숙 입장 시 모금 1초당 단가(스냅샷×0.001%÷12)'");
    }
    $rs2 = @db_query("SHOW COLUMNS FROM tb_self LIKE 'game_point_snap'");
    $row2 = $rs2 ? db_fetch($rs2) : null;
    if (empty($row2['Field'])) {
      @db_query("ALTER TABLE tb_self ADD COLUMN `game_point_snap` DECIMAL(65,0) NOT NULL DEFAULT 0 COMMENT '자숙 등록 시점 전체 게임냥 스냅샷'");
    }
    $rs3 = @db_query("SHOW COLUMNS FROM tb_self LIKE 'mogum_sec'");
    $row3 = $rs3 ? db_fetch($rs3) : null;
    if (empty($row3['Field'])) {
      @db_query("ALTER TABLE tb_self ADD COLUMN `mogum_sec` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '모금으로 단축된 누적 초'");
    }
    $rs4 = @db_query("SHOW COLUMNS FROM tb_self LIKE 'mogum_donors'");
    $row4 = $rs4 ? db_fetch($rs4) : null;
    if (empty($row4['Field'])) {
      @db_query("ALTER TABLE tb_self ADD COLUMN `mogum_donors` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '모금자 닉 목록(공백구분)'");
    }
  }
}

/** 현재 전체 게임냥 스냅샷 문자열 (모금·자숙 등록용) */
if (!function_exists('tb_self_게임냥스냅샷_현재')) {
  function tb_self_게임냥스냅샷_현재(): string {
    if (function_exists('시세기준_게임냥_문자열')) {
      $s = 시세기준_게임냥_문자열();
    } elseif (function_exists('시세기준_게임냥') && function_exists('냥_정수문자열')) {
      $s = 냥_정수문자열(시세기준_게임냥());
    } else {
      global $전체포인트;
      $s = (string)($전체포인트['total_point'] ?? 0);
    }
    $s = function_exists('냥_정수문자열') ? 냥_정수문자열($s) : preg_replace('/\D+/', '', (string)$s);
    return ($s === '' || $s === '0') ? '0' : $s;
  }
}

/**
 * 진행 중 일방·공커에 현재 전체 게임냥 스냅샷·1초 단가 일괄 기록
 * @return array{ok:bool, snap:string, unit:int, updated:int, msg:string}
 */
if (!function_exists('tb_self_게임냥스냅샷_일괄적용')) {
  function tb_self_게임냥스냅샷_일괄적용(): array {
    tb_self_모금단가_컬럼_보장();
    $snap = tb_self_게임냥스냅샷_현재();
    $snap_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($snap) : preg_replace('/\D+/', '', $snap);
    if ($snap_sql === '') {
      $snap_sql = '0';
    }
    $unit = (int)모금나눗값_총냥기준($snap);
    if ($unit < 1) {
      $unit = 1;
    }
    $cntRow = @db_select("
      SELECT COUNT(*) AS c
      FROM tb_self
      WHERE status IN ('일방', '공커')
        AND enddate > NOW()
    ");
    $before = (int)($cntRow['c'] ?? 0);
    @db_query("
      UPDATE tb_self
      SET game_point_snap = {$snap_sql},
          mogum_unit = {$unit}
      WHERE status IN ('일방', '공커')
        AND enddate > NOW()
    ");
    $fmt = function_exists('랭킹_게임냥표시')
      ? 랭킹_게임냥표시($snap, '냥')
      : (number_format((float)$snap) . '냥');
    return [
      'ok' => true,
      'snap' => $snap,
      'unit' => $unit,
      'updated' => $before,
      'msg' => "✅ 자숙 스냅샷 적용\n· 대상 {$before}명 (진행중 일방·공커)\n· 전체게임냥 {$fmt}\n· 1초 단가(0.001%÷12) " . number_format($unit) . '냥',
    ];
  }
}

/** 배포 직후 1회 — 진행중 자숙에 현재 시총 스냅샷 강제 기록 */
if (!function_exists('tb_self_게임냥스냅샷_원샷적용')) {
  function tb_self_게임냥스냅샷_원샷적용(): void {
    static $done = false;
    if ($done || !function_exists('db_query')) {
      return;
    }
    $done = true;
    $flag = __DIR__ . '/.game_point_snap_oneshot_20260805b';
    if (@is_file($flag)) {
      return;
    }
    $r = tb_self_게임냥스냅샷_일괄적용();
    @file_put_contents(
      $flag,
      date('c') . ' updated=' . (int)($r['updated'] ?? 0)
        . ' snap=' . (string)($r['snap'] ?? '')
        . ' unit=' . (int)($r['unit'] ?? 0) . "\n"
    );
  }
}

/**
 * 구버전(12초 단가) → 1초 단가 1회 전환
 * (잘못 저장된 1초=시총×0.001% 는 tb_self_모금단가_12배과대_보정 에서 스냅샷 기준으로 재계산)
 */
if (!function_exists('tb_self_모금단가_1초전환')) {
  function tb_self_모금단가_1초전환(): void {
    static $done = false;
    if ($done || !function_exists('db_query')) {
      return;
    }
    $done = true;
    $flag = __DIR__ . '/.mogum_unit_1sec_20260805';
    if (@is_file($flag)) {
      return;
    }
    tb_self_모금단가_컬럼_보장();
    // 저장값이 12초 단가였던 건 ÷12 로 1초 환산 (현재 시총으로 덮어쓰지 않음)
    @db_query("
      UPDATE tb_self
      SET mogum_unit = GREATEST(1, CEIL(mogum_unit / 12))
      WHERE IFNULL(mogum_unit, 0) > 0
    ");
    @file_put_contents($flag, date('c') . " divided_by_12\n");
  }
}

/**
 * 1초 단가가 시총×0.001%(÷12 누락)로 저장된 건 → 스냅샷×0.001%÷12 로 1회 재계산
 */
if (!function_exists('tb_self_모금단가_12배과대_보정')) {
  function tb_self_모금단가_12배과대_보정(): void {
    static $done = false;
    if ($done || !function_exists('db_query')) {
      return;
    }
    $done = true;
    $flag = __DIR__ . '/.mogum_unit_fix_div12_20260805';
    if (@is_file($flag)) {
      return;
    }
    tb_self_모금단가_컬럼_보장();
    @db_query("
      UPDATE tb_self
      SET mogum_unit = GREATEST(
        1,
        CEIL(CAST(game_point_snap AS DECIMAL(65,0)) * 0.00001 / 12)
      )
      WHERE status IN ('일방', '공커')
        AND enddate > NOW()
        AND IFNULL(game_point_snap, 0) > 0
    ");
    // 스냅샷 없는 진행건은 현재 시총 기준으로 채움
    $unit = (int)모금나눗값구하기();
    $snap = tb_self_게임냥스냅샷_현재();
    $snap_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($snap) : preg_replace('/\D+/', '', $snap);
    if ($snap_sql === '') {
      $snap_sql = '0';
    }
    if ($unit > 0) {
      @db_query("
        UPDATE tb_self
        SET mogum_unit = {$unit},
            game_point_snap = IF(IFNULL(game_point_snap, 0) = 0, {$snap_sql}, game_point_snap)
        WHERE status IN ('일방', '공커')
          AND enddate > NOW()
          AND IFNULL(game_point_snap, 0) = 0
      ");
    }
    @file_put_contents($flag, date('c') . " unit={$unit} snap={$snap_sql}\n");
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
    if (function_exists('tb_self_모금단가_1초전환')) {
      tb_self_모금단가_1초전환();
    }
    if (function_exists('tb_self_모금단가_12배과대_보정')) {
      tb_self_모금단가_12배과대_보정();
    }
    if (function_exists('tb_self_게임냥스냅샷_원샷적용')) {
      tb_self_게임냥스냅샷_원샷적용();
    }
    $unit = (int)모금나눗값구하기();
    $snap = tb_self_게임냥스냅샷_현재();
    $snap_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($snap) : preg_replace('/\D+/', '', $snap);
    if ($snap_sql === '') {
      $snap_sql = '0';
    }
    if ($unit > 0) {
      @db_query("
        UPDATE tb_self
        SET mogum_unit = {$unit},
            game_point_snap = IF(IFNULL(game_point_snap, 0) = 0, {$snap_sql}, game_point_snap)
        WHERE status IN ('일방', '공커')
          AND enddate > NOW()
          AND IFNULL(mogum_unit, 0) = 0
      ");
    }
    // 스냅샷 있는 건: 1초 단가 = 스냅샷 × 0.001% ÷ 12
    @db_query("
      UPDATE tb_self
      SET mogum_unit = GREATEST(
        1,
        CEIL(CAST(game_point_snap AS DECIMAL(65,0)) * 0.00001 / 12)
      )
      WHERE status IN ('일방', '공커')
        AND enddate > NOW()
        AND IFNULL(game_point_snap, 0) > 0
        AND IFNULL(mogum_unit, 0) = 0
    ");
  }
}

/** 일방·공커 tb_self INSERT 시 mogum_unit + game_point_snap 절 */
if (!function_exists('tb_self_일방공커_모금단가_sql')) {
  function tb_self_일방공커_모금단가_sql($status): string {
    $status = trim((string)$status);
    if ($status !== '일방' && $status !== '공커') {
      return '';
    }
    tb_self_모금단가_컬럼_보장();
    if (function_exists('tb_self_모금단가_1초전환')) {
      tb_self_모금단가_1초전환();
    }
    $snap = tb_self_게임냥스냅샷_현재();
    $snap_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($snap) : preg_replace('/\D+/', '', $snap);
    if ($snap_sql === '') {
      $snap_sql = '0';
    }
    // 스냅샷 × 0.001% ÷ 12 = 1초 단가
    $unit = (int)모금나눗값_총냥기준($snap);
    if ($unit < 1) {
      $unit = 1;
    }
    return ", mogum_unit = {$unit}, game_point_snap = {$snap_sql}";
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

/** .모금 금액 → 자숙 단축 초 (입장 시점 mogum_unit = 1초당 단가 · 천경·해 문자열 대응) */
if (!function_exists('모금_금액to초')) {
  function 모금_금액to초($money, ?string $targetNick = null): int {
    $moneyStr = function_exists('냥_정수문자열')
      ? 냥_정수문자열($money)
      : (ltrim(preg_replace('/[^\d]/', '', (string)$money), '0') ?: '0');
    if ($moneyStr === '0') {
      return 0;
    }
    $단가 = max(1, (int)모금_적용단가($targetNick));
    $단가Str = (string)$단가;
    if (function_exists('bcdiv') && function_exists('bccomp')) {
      // 1초 단가: floor(금액 / 단가) 초
      $sec = bcdiv($moneyStr, $단가Str, 0);
      if (bccomp($sec, (string)PHP_INT_MAX, 0) > 0) {
        return PHP_INT_MAX;
      }
      return (int)$sec;
    }
    $m = (float)$moneyStr;
    if ($m <= 0) {
      return 0;
    }
    return (int)floor($m / $단가);
  }
}

/**
 * .모금 닉 지정 시 — 해당 자숙인 mogum_unit(1초당) 미만이면 거부
 * @return array{ok:bool, msg?:string, 단가?:int}
 */
if (!function_exists('모금_대상단가_검증')) {
  function 모금_대상단가_검증($금액, ?string $targetNick): array {
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
    $금액Str = function_exists('냥_정수문자열')
      ? 냥_정수문자열($금액)
      : (ltrim(preg_replace('/[^\d]/', '', (string)$금액), '0') ?: '0');
    $단가Str = (string)$단가;
    $미만 = function_exists('bccomp')
      ? (bccomp($금액Str, $단가Str, 0) < 0)
      : ((float)$금액Str < (float)$단가);
    if ($미만) {
      $단가표 = function_exists('냥축약표시') ? 냥축약표시($단가) : number_format($단가) . "냥";
      return [
        'ok' => false,
        '단가' => $단가,
        'msg' => "❌ {$targetNick} 모금은 1초당 {$단가표} 이상이어야 해요.\n(입장 시점 단가 미만은 적용되지 않아요)",
      ];
    }
    $seconds = 모금_금액to초($금액Str, $targetNick);
    if ($seconds < 1) {
      $단가표 = function_exists('냥축약표시') ? 냥축약표시($단가) : number_format($단가) . "냥";
      return [
        'ok' => false,
        '단가' => $단가,
        'msg' => "❌ {$targetNick} 모금은 최소 {$단가표} (1초 단축) 이상이어야 해요.",
      ];
    }
    return ['ok' => true, '단가' => $단가];
  }
}

/** 인당 자숙 종료에 필요한 냥 (남은 초 × 모금 1초 단가) */
function 자숙인당종료필요냥(int $remainSec, ?int $모금단가 = null, bool $저장단가만 = false): float
{
  if ($remainSec <= 0) {
    return 0.0;
  }
  $units = $remainSec; // 1초 단위
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
    $fmt = function_exists('냥축약표시')
      ? function ($금액) { return 냥축약표시($금액); }
      : function ($금액) { return number_format((float)$금액) . '냥'; };

    $lines = "최소 모금: " . $fmt($최소) . "\n";
    $lines .= "전체 종료 예상: " . $fmt($필요) . "\n";

    return $lines;
  } catch (Throwable $e) {
    return "전체 종료 예상: (계산 불가 — mogum_unit 값 확인)\n";
  }
}

/** 초 → 일/시간/분/초 축약 표시 */
if (!function_exists('자숙_초_표시')) {
  function 자숙_초_표시(int $seconds): string {
    $seconds = max(0, $seconds);
    if ($seconds < 60) {
      return "{$seconds}초";
    }
    $days = intdiv($seconds, 86400);
    $seconds %= 86400;
    $hours = intdiv($seconds, 3600);
    $seconds %= 3600;
    $minutes = intdiv($seconds, 60);
    $sec = $seconds % 60;
    $parts = [];
    if ($days > 0) {
      $parts[] = "{$days}일";
    }
    if ($hours > 0) {
      $parts[] = "{$hours}시간";
    }
    if ($minutes > 0) {
      $parts[] = "{$minutes}분";
    }
    if ($sec > 0 && $days === 0) {
      $parts[] = "{$sec}초";
    }
    if (empty($parts)) {
      return '0초';
    }
    // 상위 2단위만 (예: 2일 5시간)
    return implode(' ', array_slice($parts, 0, 2));
  }
}

/**
 * tb_self.mogum_donors 에 모금자 닉 추가 (중복 제외 · 공백 구분)
 */
if (!function_exists('tb_self_모금기부닉_추가')) {
  function tb_self_모금기부닉_추가(int $idx, string $기부닉): string {
    if ($idx <= 0) {
      return '';
    }
    $기부닉 = trim($기부닉);
    if ($기부닉 === '') {
      return '';
    }
    tb_self_모금단가_컬럼_보장();
    $행 = @db_select("SELECT IFNULL(mogum_donors, '') AS mogum_donors FROM tb_self WHERE idx = {$idx} LIMIT 1");
    $cur = trim((string)($행['mogum_donors'] ?? ''));
    $list = $cur === '' ? [] : preg_split('/\s+/u', $cur, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($list as $n) {
      if ($n === $기부닉) {
        return $cur;
      }
    }
    $list[] = $기부닉;
    // VARCHAR(500) 한도 — 앞쪽(오래된)부터 유지, 넘치면 뒤 유지보다 최근 기부자 우선
    $joined = implode(' ', $list);
    while (strlen($joined) > 480 && count($list) > 1) {
      array_shift($list);
      $joined = implode(' ', $list);
    }
    $esc = addslashes($joined);
    db_query("UPDATE tb_self SET mogum_donors = '{$esc}' WHERE idx = {$idx} LIMIT 1");
    return $joined;
  }
}

/**
 * .모금 적용 — enddate 단축 + mogum_sec 누적 + 모금자 닉 수집 + 안내 문구
 * @return array{ok:bool, msg:string, seconds:int}
 */
if (!function_exists('모금_자숙단축_적용')) {
  function 모금_자숙단축_적용(string $기부닉, $금액, ?string $대상닉 = null): array {
    tb_self_모금단가_컬럼_보장();
    if (function_exists('tb_self_모금단가_미설정_보정')) {
      tb_self_모금단가_미설정_보정();
    }
    if (function_exists('tb_self_만료정리_모금포함')) {
      tb_self_만료정리_모금포함();
    }

    $금액Str = function_exists('냥_정수문자열') ? 냥_정수문자열($금액) : preg_replace('/\D+/', '', (string)$금액);
    if ($금액Str === '' || $금액Str === '0') {
      return ['ok' => false, 'msg' => '❌ 모금 금액을 확인해주세요.', 'seconds' => 0];
    }
    $금액_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($금액Str) : $금액Str;

    $대상닉 = $대상닉 !== null ? trim($대상닉) : '';
    if ($대상닉 === '') {
      return ['ok' => false, 'msg' => "❌ 자숙자 닉을 지정해주세요.\n예) .모금 가니 10000", 'seconds' => 0];
    }
    $seconds = function_exists('모금_금액to초')
      ? 모금_금액to초($금액Str, $대상닉)
      : 0;
    if ($seconds < 1) {
      return ['ok' => false, 'msg' => '❌ 단축 시간이 0초예요. 금액을 늘려주세요.', 'seconds' => 0];
    }

    // 자숙자 지정 — 남은 시간 초과분 절삭
    $남은표시목록 = [];
    $닉_esc = addslashes($대상닉);
    $행 = @db_select("
      SELECT idx, enddate, IFNULL(mogum_sec, 0) AS mogum_sec, IFNULL(mogum_donors, '') AS mogum_donors
      FROM tb_self
      WHERE nick = '{$닉_esc}'
        AND status IN ('일방', '공커')
        AND enddate > NOW()
      ORDER BY enddate DESC
      LIMIT 1
    ");
    if (empty($행['idx'])) {
      return ['ok' => false, 'msg' => "[ {$대상닉} ] 친구는 자숙중이 아닙니다..", 'seconds' => 0];
    }
    $remain = max(0, (int)(strtotime((string)$행['enddate']) - time()));
    if ($remain <= 0) {
      return ['ok' => false, 'msg' => "[ {$대상닉} ] 자숙이 이미 종료됐어요.", 'seconds' => 0];
    }
    if ($seconds > $remain) {
      $seconds = $remain;
    }
    $idx = (int)$행['idx'];
    db_query("
      UPDATE tb_self
      SET enddate = DATE_SUB(enddate, INTERVAL {$seconds} SECOND),
          mogum_sec = IFNULL(mogum_sec, 0) + {$seconds}
      WHERE idx = {$idx}
      LIMIT 1
    ");
    $기부목록 = tb_self_모금기부닉_추가($idx, $기부닉);
    $후 = @db_select("SELECT enddate, IFNULL(mogum_sec, 0) AS mogum_sec, IFNULL(mogum_donors, '') AS mogum_donors FROM tb_self WHERE idx = {$idx} LIMIT 1");
    $남음 = max(0, (int)(strtotime((string)($후['enddate'] ?? '')) - time()));
    $누적 = (int)($후['mogum_sec'] ?? 0);
    $남은표시목록[] = [
      'nick' => $대상닉,
      'remain' => $남음,
      'accum' => $누적,
      'donors' => trim((string)($후['mogum_donors'] ?? $기부목록)),
      'idx' => $idx,
      'ended' => ($남음 <= 0),
    ];

    if (function_exists('tb_loan_컬럼_보장')) {
      tb_loan_컬럼_보장();
    }
    // nick=모금자, target_nick=자숙대상
    if (function_exists('tb_loan_모금기록')) {
      tb_loan_모금기록($기부닉, $대상닉, $금액Str, $seconds);
    } else {
      $기부_esc = addslashes($기부닉);
      $대상_esc = addslashes($대상닉);
      $기존 = db_select("SELECT nick FROM tb_loan WHERE nick = '{$기부_esc}' AND target_nick = '{$대상_esc}' LIMIT 1");
      if (empty($기존['nick'])) {
        db_query("INSERT INTO tb_loan SET nick = '{$기부_esc}', target_nick = '{$대상_esc}', point = {$금액_sql}, times = {$seconds}, regdate = NOW()");
      } else {
        db_query("UPDATE tb_loan SET point = point + {$금액_sql}, times = times + {$seconds}, regdate = NOW() WHERE nick = '{$기부_esc}' AND target_nick = '{$대상_esc}'");
      }
    }

    // 모금으로 자숙이 끝난 경우 — 자숙 행 + 해당 대상 loan 삭제
    foreach ($남은표시목록 as $r) {
      if (empty($r['ended'])) {
        continue;
      }
      $eIdx = (int)($r['idx'] ?? 0);
      $eNick = trim((string)($r['nick'] ?? ''));
      if ($eIdx > 0 && function_exists('tb_self_건삭제_모금정리')) {
        tb_self_건삭제_모금정리($eIdx);
      } elseif ($eNick !== '' && function_exists('tb_loan_자숙대상_삭제')) {
        tb_loan_자숙대상_삭제($eNick);
        $esc = addslashes($eNick);
        db_query("DELETE FROM tb_self WHERE nick = '{$esc}' AND status IN ('일방','공커') AND enddate <= NOW()");
      }
    }

    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }
    $기부_esc = addslashes($기부닉);
    db_query("UPDATE tb_member SET point = point - {$금액_sql} WHERE name = '{$기부_esc}'");

    $변환냥 = function_exists('냥축약표시') ? 냥축약표시($금액Str) : (number_format((float)$금액Str) . '냥');
    $단축표시 = 자숙_초_표시($seconds);
    $msg = "👼{$기부닉} {$변환냥} 모금 고마워💕\n";
    $msg .= "{$대상닉} · {$단축표시} 단축";
    if (!empty($남은표시목록[0])) {
      $r0 = $남은표시목록[0];
      if (!empty($r0['ended'])) {
        $msg .= "\n🎉 자숙 종료! (관련 모금 기록 삭제)";
      } else {
        $msg .= "\n남은 자숙: " . 자숙_초_표시((int)$r0['remain']);
        $msg .= "\n누적 단축: " . 자숙_초_표시((int)$r0['accum']);
        $donors = trim((string)($r0['donors'] ?? ''));
        if ($donors !== '') {
          $msg .= "\n모금자: {$donors}";
        }
      }
    }

    return ['ok' => true, 'msg' => $msg, 'seconds' => $seconds];
  }
}

/** .자숙 목록 조회 명령 여부 (.자숙공지·.자숙추가·.자숙종료·.자숙끝 제외, . 자숙·.자숙(일방/…) 포함) */
if (!function_exists('자숙_명령_여부')) {
  function 자숙_명령_여부($status): bool {
    $t = function_exists('status_정규화') ? status_정규화($status) : trim((string)$status);
    if ($t === '') {
      return false;
    }
    if (preg_match('/^\.[\s\p{Zs}]*자숙(?:공지|추가|종료|끝|스냅샷)/u', $t)) {
      return false;
    }
    return (bool)preg_match('/^\.[\s\p{Zs}]*자숙(?!공지|추가|종료|끝|스냅샷)/u', $t);
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

/** .자숙끝 닉 / .자숙종료 닉 — 해당 닉의 자숙·제한·모금 기록 삭제 (관리자). 닉 없는 .자숙종료는 전체삭제 */
if (!function_exists('자숙끝_명령_처리')) {
  function 자숙끝_명령_처리($status, $두자리닉넴 = '', $nick = '', $관리자 = []): void {
    $t = function_exists('status_정규화') ? status_정규화($status) : trim((string)$status);
    $m끝 = [];
    $m종료닉 = [];
    $is끝 = (bool)preg_match('/^\.[\s\p{Zs}]*자숙끝(?:\s+(\S+))?\s*$/u', $t, $m끝);
    $is종료닉 = (bool)preg_match('/^\.[\s\p{Zs}]*자숙종료\s+(\S+)\s*$/u', $t, $m종료닉);
    if (!$is끝 && !$is종료닉) {
      return;
    }
    if (function_exists('관리자_명령_차단')) {
      관리자_명령_차단($두자리닉넴, $nick);
    }

    $대상원본 = $is끝
      ? trim((string)($m끝[1] ?? ''))
      : trim((string)($m종료닉[1] ?? ''));
    if ($대상원본 === '') {
      echo 전송("❌ 사용법: .자숙끝 닉네임\n예) .자숙끝 세은  /  .자숙종료 세은");
      exit;
    }

    $대상 = function_exists('getTwoCharNick') ? getTwoCharNick($대상원본) : $대상원본;
    if ($대상 === '') {
      $대상 = preg_replace('/\s+/u', '', $대상원본);
    }
    $대상_esc = addslashes($대상);

    $목록 = [];
    $rs = @db_query("
      SELECT idx, status, enddate
      FROM tb_self
      WHERE nick = '{$대상_esc}'
      ORDER BY enddate DESC, idx DESC
    ");
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $목록[] = $row;
      }
    }

    if (empty($목록)) {
      echo 전송("❌ [ {$대상} ] 님의 자숙/제한 기록이 없어요.");
      exit;
    }

    $활성수 = 0;
    $요약 = [];
    foreach ($목록 as $row) {
      $상태 = trim((string)($row['status'] ?? ''));
      $끝 = !empty($row['enddate']) ? date('m/d H:i', strtotime($row['enddate'])) : '-';
      $활성 = (!empty($row['enddate']) && strtotime($row['enddate']) > time());
      if ($활성) {
        $활성수++;
      }
      $요약[] = ($활성 ? '진행중' : '만료') . " · {$상태} · {$끝}";
    }

    db_query("DELETE FROM tb_self WHERE nick = '{$대상_esc}'");
    $모금삭제 = function_exists('tb_loan_자숙대상_삭제')
      ? tb_loan_자숙대상_삭제($대상)
      : 0;
    if ($모금삭제 <= 0) {
      $모금행 = @db_select("SELECT COUNT(*) AS c FROM tb_loan WHERE target_nick = '{$대상_esc}'");
      $모금삭제 = (int)($모금행['c'] ?? 0);
      if ($모금삭제 > 0) {
        db_query("DELETE FROM tb_loan WHERE target_nick = '{$대상_esc}'");
      }
    }

    $msg = "✅ [ {$대상} ] 자숙 종료\n\n";
    $msg .= '· tb_self ' . count($목록) . "건 삭제 (진행중 {$활성수}건)\n";
    if ($모금삭제 > 0) {
      $msg .= "· tb_loan {$모금삭제}건 삭제\n";
    }
    $msg .= "\n" . implode("\n", $요약);
    echo 전송($msg);
    exit;
  }
}

/** .자숙스냅샷 — 진행중 일방·공커에 현재 전체게임냥·1초단가 일괄 기록 (관리자) */
if (!function_exists('자숙스냅샷_명령_처리')) {
  function 자숙스냅샷_명령_처리($status, $두자리닉넴 = '', $nick = '', $관리자 = []): void {
    $t = function_exists('status_정규화') ? status_정규화($status) : trim((string)$status);
    if (!preg_match('/^\.[\s\p{Zs}]*자숙스냅샷\s*$/u', $t)) {
      return;
    }
    if (function_exists('관리자_명령_차단')) {
      관리자_명령_차단($두자리닉넴, $nick);
    }
    $r = tb_self_게임냥스냅샷_일괄적용();
    echo 전송((string)($r['msg'] ?? '✅ 스냅샷 적용 완료'));
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

/** 인당 자숙 전체종료 예상 냥 (남은시간 × mogum_unit, 없으면 현재 단가) */
if (!function_exists('자숙_인당종료예상_문구')) {
  function 자숙_인당종료예상_문구(array $row): string {
    $endTs = strtotime((string)($row['enddate'] ?? ''));
    $remain = ($endTs !== false) ? max(0, $endTs - time()) : 0;
    $unit = (int)($row['mogum_unit'] ?? 0);
    if ($remain <= 0) {
      $필요 = 0.0;
    } elseif ($unit > 0) {
      $필요 = 자숙인당종료필요냥($remain, $unit, true);
    } else {
      // 입장 단가 미저장 건은 현재 모금 단가로 표시
      $필요 = 자숙인당종료필요냥($remain, null, false);
    }
    $fmt = function_exists('냥축약표시')
      ? 냥축약표시($필요)
      : (number_format((float)$필요) . '냥');
    return "전체종료 예상 {$fmt}";
  }
}

/** .자숙 목록 본문 (본방·연구실 공통) */
if (!function_exists('자숙_목록_문구')) {
  function 자숙_목록_문구(): string {
    if (!isset($GLOBALS['전체포인트']) || !isset($GLOBALS['단위'])) {
      @include_once __DIR__ . '/config.php';
    }
    tb_self_모금단가_컬럼_보장();
    if (function_exists('tb_self_모금단가_12배과대_보정')) {
      tb_self_모금단가_12배과대_보정();
    }
    if (function_exists('tb_self_게임냥스냅샷_원샷적용')) {
      tb_self_게임냥스냅샷_원샷적용();
    }
    if (function_exists('tb_self_만료정리_모금포함')) {
      tb_self_만료정리_모금포함();
    }

    // 카톡 전체보기 유도 — 헤더 뒤 공백 패딩
    $msg = "✅ 자숙 - 일방/공커/제한\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
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
        $msg .= 자숙_인당종료예상_문구($row) . "\n";
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
        $msg .= 자숙_인당종료예상_문구($row) . "\n";
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
          $모금자 = trim((string)($row['nick'] ?? ''));
          $대상 = trim((string)($row['target_nick'] ?? ''));
          $대상표 = $대상 !== '' ? "→{$대상}" : '→전체';
          $msg .= "\n-{$times} {$모금자}{$대상표} {$금액표}";
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
  $digits = function_exists('냥_정수문자열') ? 냥_정수문자열($v) : preg_replace('/[^\d]/', '', (string)$v);
  if ($digits === '' || $digits === '0') {
    return 0;
  }
  // BIGINT 범위까지 허용 (구 INT 상한 2147483647 제거)
  $max = (string)PHP_INT_MAX;
  if (function_exists('bccomp')) {
    if (bccomp($digits, $max) > 0) {
      // 로그 컬럼이 BIGINT면 잘리지만, INSERT는 문자열로 별도 처리
      return PHP_INT_MAX;
    }
    return (int)$digits;
  }
  if (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && $digits > $max)) {
    return PHP_INT_MAX;
  }
  return (int)$digits;
}

/** tb_point_log BIGINT 컬럼용 — PHP_INT_MAX(≈922경) 초과 시 상한 */
if (!function_exists('지급로그_BIGINT안전')) {
  function 지급로그_BIGINT안전($v): string {
    $s = function_exists('냥_정수문자열') ? 냥_정수문자열($v) : preg_replace('/[^\d]/', '', (string)$v);
    $s = ltrim((string)$s, '0') ?: '0';
    $max = '9223372036854775807';
    if (function_exists('bccomp')) {
      return (bccomp($s, $max, 0) > 0) ? $max : $s;
    }
    if (strlen($s) > strlen($max) || (strlen($s) === strlen($max) && $s > $max)) {
      return $max;
    }
    return $s;
  }
}

/** tb_point_log 금액 컬럼 DECIMAL(40,0)+ 보장 — BIGINT(~922경) 캡 방지 (1해도 40자리면 충분) */
if (!function_exists('지급로그_컬럼_보장')) {
  function 지급로그_컬럼_보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    foreach (['point', 'tax', 'mypoint'] as $col) {
      $info = @db_select("SHOW COLUMNS FROM tb_point_log LIKE '{$col}'");
      if (empty($info['Field'])) {
        continue;
      }
      $type = strtolower((string)($info['Type'] ?? ''));
      // 이미 DECIMAL(40+)면 대규모 ALTER 금지 (테이블 락 방지)
      if (preg_match('/decimal\((\d+)/', $type, $m) && (int)$m[1] >= 40) {
        continue;
      }
      @db_query("ALTER TABLE tb_point_log MODIFY COLUMN `{$col}` DECIMAL(40,0) NOT NULL DEFAULT 0");
    }
  }
}

/** 지급로그 insert용 금액 문자열 — DECIMAL(40+)면 무제한, 아니면 BIGINT 상한 */
if (!function_exists('지급로그_금액_SQL')) {
  function 지급로그_금액_SQL($v, bool $allowNegative = false): string {
    지급로그_컬럼_보장();
    $raw = trim((string)$v);
    $neg = false;
    if ($allowNegative && isset($raw[0]) && $raw[0] === '-') {
      $neg = true;
      $raw = substr($raw, 1);
    }
    $s = function_exists('냥_정수문자열') ? 냥_정수문자열($raw) : (ltrim(preg_replace('/[^\d]/', '', $raw), '0') ?: '0');
    static $isWideDec = null;
    if ($isWideDec === null) {
      $info = @db_select("SHOW COLUMNS FROM tb_point_log LIKE 'point'");
      $type = strtolower((string)($info['Field'] ? ($info['Type'] ?? '') : ''));
      $isWideDec = (bool)preg_match('/decimal\((\d+)/', $type, $m) && (int)$m[1] >= 40;
    }
    if (!$isWideDec && function_exists('지급로그_BIGINT안전')) {
      $s = 지급로그_BIGINT안전($s);
    }
    return ($neg && $s !== '0') ? ('-' . $s) : $s;
  }
}

function 지급로그($상태, $두자리닉넴, $받는이, $수수료, $지급냥){
  global $conn;
  $상태원본 = trim((string)$상태);
  // 고빈도 로그 스킵 (tb_point_log 비대화 방지)
  static $지급로그_스킵 = [
    '채굴강화배치' => true,
    '홀짝도전-승' => true,
    '홀짝도전-패' => true,
    '홀짝도전-무승부' => true,
  ];
  if (isset($지급로그_스킵[$상태원본])) {
    return true;
  }
  지급로그_컬럼_보장();
  $닉_esc = addslashes((string)$두자리닉넴);
  $받는_esc = addslashes((string)$받는이);
  $상태_esc = addslashes($상태원본);
  // 큰 DECIMAL point 가 float/과학적표기로 깨지지 않게 CHAR 로 조회
  $my = db_select("SELECT CAST(CAST(IFNULL(point, 0) AS DECIMAL(40,0)) AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
  $mypoint = 지급로그_금액_SQL($my['point'] ?? 0);
  $수수료_sql = 지급로그_금액_SQL($수수료);
  $지급냥_sql = 지급로그_금액_SQL($지급냥, true);
  $sql = "insert into tb_point_log set ";
  $sql.= "status = '{$상태_esc}', ";
  $sql.= "nick = '{$닉_esc}', ";
  $sql.= "receiver = '{$받는_esc}', ";
  $sql.= "tax = {$수수료_sql}, ";
  $sql.= "point = {$지급냥_sql}, ";
  $sql.= "mypoint = {$mypoint}, ";
  $sql.= "regdate = now() ";
  $result = db_query($sql);

  return $result;
}

/**
 * 계급용 방 게임냥 총합 (요청당 1회 캐시)
 * SUM 실패·0이면 시세스냅샷·상위합산으로 폴백 (0이면 전원 토끼로 고정되는 문제 방지)
 */
function 계급_총게임냥(): string {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    // 주의: 1.23e+20 을 '.' 앞에서 자르면 "1"이 됨 → 냥_금액원문_정규화 사용
    $parsePt = static function ($raw): string {
        return function_exists('냥_금액원문_정규화')
            ? 냥_금액원문_정규화($raw)
            : 냥_정수문자열($raw);
    };
    $isPositive = static function (string $n): bool {
        $n = ltrim($n, '0');
        return $n !== '' && $n !== '0';
    };

    $total = '0';

    // 1) 랭킹과 동일 — 전체 회원 SUM (마이너스 보유 제외 · N접두로 큰수 보존)
    $row = @db_select("
        SELECT CONCAT('N', CAST(COALESCE(SUM(GREATEST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)), 0)), 0) AS CHAR)) AS total_pt
        FROM tb_member
    ");
    if (is_array($row)) {
        $total = $parsePt($row['total_pt'] ?? 'N0');
    }

    // 2) 활성 회원 실시간 합
    if (!$isPositive($total) && function_exists('우리방_실시간_총량')) {
        $live = 우리방_실시간_총량();
        $total = $parsePt($live['게임냥'] ?? '0');
    }

    // 3) 시세 스냅샷
    if (!$isPositive($total) && function_exists('시세기준_게임냥_문자열')) {
        $total = $parsePt(시세기준_게임냥_문자열());
    }

    // 4) 상위 보유분 PHP 합산 (SQL SUM이 깨질 때 · CHAR 원문→과학적표기 파싱)
    if (!$isPositive($total) && function_exists('db_query') && function_exists('냥_금액_문자열합')) {
        $rs = @db_query("
            SELECT CONCAT('N', CAST(IFNULL(point, 0) AS CHAR)) AS point
            FROM tb_member
            WHERE IFNULL(point, 0) > 0
            ORDER BY point + 0 DESC
            LIMIT 300
        ");
        $sum = '0';
        if ($rs) {
            while ($r = db_fetch($rs)) {
                $p = $parsePt($r['point'] ?? '0');
                if ($p !== '0') {
                    $sum = 냥_금액_문자열합($sum, $p);
                }
            }
        }
        $total = $sum;
    }

    $cached = $isPositive($total) ? $total : '0';
    return $cached;
}

/**
 * 보유/총냥 < threshold 인지 (bcmath 없이도 동작)
 * threshold 예: "0.01" = 1%
 */
function 계급_보유비중_미만(string $pointStr, string $total, string $threshold): bool {
    $pointStr = ltrim($pointStr, '0') ?: '0';
    $total = ltrim($total, '0') ?: '0';
    if ($total === '0' || $pointStr === '0') {
        return true;
    }

    if (function_exists('bcdiv') && function_exists('bccomp')) {
        return bccomp(bcdiv($pointStr, $total, 24), $threshold, 24) < 0;
    }

    // point/total < thr  ⇔  point * 10^scale < total * numer
    $threshold = trim($threshold);
    if (!preg_match('/^(\d*)\.?(\d*)$/', $threshold, $m)) {
        return true;
    }
    $intPart = ($m[1] !== '') ? $m[1] : '0';
    $frac = $m[2] ?? '';
    $scale = strlen($frac);
    $numer = ltrim($intPart . $frac, '0') ?: '0';
    if ($numer === '0') {
        return false;
    }

    $left = function_exists('냥_금액_문자열곱')
        ? 냥_금액_문자열곱($pointStr, '1' . str_repeat('0', $scale))
        : ($pointStr . str_repeat('0', $scale));
    $right = function_exists('냥_금액_문자열곱')
        ? 냥_금액_문자열곱($total, $numer)
        : '0';
    if ($right === '0' && !function_exists('냥_금액_문자열곱')) {
        // 곱 헬퍼 없으면 자릿수 비율로 대략 비교
        $pd = strlen($pointStr);
        $td = strlen($total);
        if ($pd + $scale !== $td + strlen($numer)) {
            return ($pd + $scale) < ($td + strlen($numer));
        }
        return strcmp($pointStr . str_repeat('0', $scale), $total . $numer) < 0;
    }
    if (strlen($left) !== strlen($right)) {
        return strlen($left) < strlen($right);
    }
    return $left < $right;
}

/**
 * 계급 — 방 총 게임냥 대비 보유 비중(%)으로 결정
 * 예) 총냥의 1%·5%·10% … 를 넘기면 상위 계급
 * 호칭은 name만 쓰면 됨.
 */
function 계급($point) {
    // [보유/총냥 미만, name, rank, add, sell] — 주석은 총냥 대비 비중
    static $tiers = [
        ['0.00000001', '🐰토끼', 5, 0, 50],      // < 0.000001%
        ['0.0000001',  '🐦참새', 12, 1, 50],     // < 0.00001%
        ['0.000001',   '🐿️다람', 25, 1, 50],     // < 0.0001%
        ['0.00001',    '🐸개구', 50, 1, 50],     // < 0.001%
        ['0.00005',    '🦔고슴', 75, 1, 50],     // < 0.005%
        ['0.0001',     '🦊여우', 100, 1, 50],    // < 0.01%
        ['0.0003',     '🦌사슴', 125, 2, 50],    // < 0.03%
        ['0.0005',     '🦦수달', 162, 2, 50],    // < 0.05%
        ['0.001',      '🐷돼지', 200, 2, 50],    // < 0.1%
        ['0.002',      '🦇박쥐', 225, 2, 50],    // < 0.2%
        ['0.003',      '🐆표범', 275, 2, 150],   // < 0.3%
        ['0.005',      '🦁사자', 325, 3, 150],   // < 0.5%
        ['0.007',      '🐯호랑', 375, 3, 150],   // < 0.7%
        ['0.01',       '🐻불곰', 425, 3, 150],   // < 1%
        ['0.015',      '🐼판다', 475, 3, 150],   // < 1.5%
        ['0.02',       '🦒기린', 525, 3, 300],   // < 2%
        ['0.03',       '🐘코끼', 600, 4, 300],   // < 3%
        ['0.04',       '🦛하마', 675, 4, 300],   // < 4%
        ['0.05',       '🦏코뿔', 750, 4, 300],   // < 5%
        ['0.07',       '🐊악어', 850, 4, 300],   // < 7%
        ['0.09',       '🐋고래', 950, 4, 450],   // < 9%
        ['0.10',       '🦈상어', 1050, 5, 450],  // < 10%
        ['0.12',       '🐬돌고', 1175, 5, 450],  // < 12%
        ['0.15',       '🐧펭귄', 1300, 5, 450],  // < 15%
        ['0.18',       '🐙문어', 1450, 5, 450],  // < 18%
        ['0.22',       '🦑오징', 1625, 5, 500],  // < 22%
        ['0.26',       '🐚낙지', 1825, 6, 500],  // < 26%
        ['0.30',       '🐟참치', 2050, 6, 500],  // < 30%
        ['0.40',       '🦪전복', 2300, 6, 500],  // < 40%
    ];
    $top = ['rank' => 2500, 'name' => '🥒해삼', 'add' => 8, 'sell' => 1000]; // ≥ 40%
    $rabbit = ['rank' => 5, 'name' => '🐰토끼', 'add' => 0, 'sell' => 50];

    // N접두 문자열(랭킹 쿼리) · 일반 숫자 모두 허용
    if (is_string($point) && isset($point[0]) && ($point[0] === 'N' || $point[0] === 'n')) {
        $point = substr($point, 1);
    }
    $pointStr = function_exists('냥_정수문자열')
        ? 냥_정수문자열($point)
        : preg_replace('/[^\d]/', '', (string)$point);
    $pointStr = ltrim((string)$pointStr, '0');
    if ($pointStr === '' || $pointStr === '0') {
        return $rabbit;
    }

    $total = 계급_총게임냥();
    $total = ltrim((string)$total, '0') ?: '0';
    if ($total === '0') {
        // 총냥을 못 구하면 비중 불가 — 절대 규모로만 대략 구분 (전원 토끼 방지)
        $len = strlen($pointStr);
        if ($len >= 21) { // ~1해↑
            return $top;
        }
        if ($len >= 19) { // ~1000경↑
            return ['rank' => 1825, 'name' => '🐚낙지', 'add' => 6, 'sell' => 500];
        }
        if ($len >= 17) { // ~10경↑
            return ['rank' => 1300, 'name' => '🐧펭귄', 'add' => 5, 'sell' => 450];
        }
        if ($len >= 15) { // ~0.1경↑
            return ['rank' => 850, 'name' => '🐊악어', 'add' => 4, 'sell' => 300];
        }
        if ($len >= 12) { // 1조↑
            return ['rank' => 425, 'name' => '🐻불곰', 'add' => 3, 'sell' => 150];
        }
        if ($len >= 8) { // 1억↑
            return ['rank' => 100, 'name' => '🦊여우', 'add' => 1, 'sell' => 50];
        }
        return $rabbit;
    }

    foreach ($tiers as $t) {
        if (계급_보유비중_미만($pointStr, $total, $t[0])) {
            return ['rank' => $t[2], 'name' => $t[1], 'add' => $t[3], 'sell' => $t[4]];
        }
    }
    return $top;
}


function 아이템보유유무($기존닉, $아이템명){
  $기존닉 = trim((string)$기존닉);
  $아이템명 = trim((string)$아이템명);
  if ($기존닉 === '' || $아이템명 === '') {
    return [];
  }
  if (function_exists('item_bag_qty_nick')) {
    $qty = item_bag_qty_nick($기존닉, $아이템명);
    if ($qty < 1) {
      return [];
    }
    $mem = item_bag_member_by_nick($기존닉);
    return [
      'idx' => -1,
      'midx' => (int)($mem['idx'] ?? 0),
      'nick' => $기존닉,
      'itemname' => $아이템명,
      'qty' => $qty,
      '_bag' => 1,
    ];
  }
  $sql = "select * from tb_member_item where nick = '{$기존닉}' and itemname = '{$아이템명}' and status = 0 ";
  $아이템 = db_select($sql);
  return $아이템 ?: [];
}

/**
 * 본방냥 보유분의 20% → 게임냥 스왑 (프변 자동구매용, 최소금액 검사 생략)
 * @return array{ok:bool,msg:string,차감_np?:float,지급_pt?:string}
 */
if (!function_exists('프변_자동_본방냥20퍼_스왑')) {
  function 프변_자동_본방냥20퍼_스왑(string $두자리닉넴): array {
    $닉 = trim($두자리닉넴);
    if ($닉 === '') {
      return ['ok' => false, 'msg' => '❌ 닉네임을 확인해주세요.'];
    }
    $swapPath = __DIR__ . '/game/swap.inc.php';
    if (is_file($swapPath)) {
      require_once $swapPath;
    }
    if (!function_exists('스왑_견적계산')) {
      return ['ok' => false, 'msg' => '❌ 스왑 기능을 불러올 수 없어요.'];
    }

    $닉_esc = addslashes($닉);
    $회원 = db_select("SELECT CAST(IFNULL(newpoint,0) AS CHAR) AS newpoint FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    $보유_np = round((float)($회원['newpoint'] ?? 0), 1);
    if ($보유_np < 0.1) {
      return ['ok' => false, 'msg' => '❌ 본방냥이 없어 스왑할 수 없어요.'];
    }

    $금액 = round($보유_np * 0.2, 1);
    if ($금액 < 0.1) {
      return ['ok' => false, 'msg' => '❌ 스왑할 본방냥(20%)이 너무 적어요.'];
    }

    $견적 = 스왑_견적계산('np2pt', $금액, false, null, false);
    if (empty($견적['ok'])) {
      return ['ok' => false, 'msg' => (string)($견적['msg'] ?? '❌ 스왑을 처리할 수 없어요.')];
    }

    $차감_np = (float)($견적['차감_np'] ?? 0);
    $지급_pt = function_exists('스왑_정수문자열')
      ? 스왑_정수문자열($견적['지급_pt'] ?? 0)
      : preg_replace('/[^\d]/', '', (string)($견적['지급_pt'] ?? '0'));
    $지급_pt = ltrim((string)$지급_pt, '0') ?: '0';
    if ($차감_np < 0.1 || $지급_pt === '0') {
      return ['ok' => false, 'msg' => '❌ 스왑 결과가 너무 적어 진행할 수 없어요.'];
    }
    if ($보유_np + 1e-9 < $차감_np) {
      return ['ok' => false, 'msg' => '❌ 본방냥이 부족해 스왑할 수 없어요.'];
    }

    if (function_exists('스왑_point_컬럼_보장')) {
      스왑_point_컬럼_보장();
    }
    $sqlPt = function_exists('냥_SQL정수') ? 냥_SQL정수($지급_pt) : $지급_pt;
    db_query("
      UPDATE tb_member
      SET newpoint = newpoint - {$차감_np},
          point = point + {$sqlPt}
      WHERE name = '{$닉_esc}' AND status = 0 AND newpoint >= {$차감_np}
      LIMIT 1
    ");
    global $conn;
    if (($conn instanceof mysqli) && (int)mysqli_affected_rows($conn) <= 0) {
      return ['ok' => false, 'msg' => '❌ 스왑 처리에 실패했어요.'];
    }

    $np표시 = function_exists('newpoint표시') ? newpoint표시($차감_np) : number_format($차감_np, 1) . '냥';
    $pt표시 = function_exists('스왑_게임냥_표시')
      ? 스왑_게임냥_표시($지급_pt, '냥')
      : ((function_exists('구매가_축약표시') ? 구매가_축약표시($지급_pt, '냥') : number_format((float)$지급_pt) . '냥'));
    return [
      'ok' => true,
      'msg' => "💱 본방냥 20% 자동스왑\n-{$np표시} → +{$pt표시}",
      '차감_np' => $차감_np,
      '지급_pt' => (string)$지급_pt,
    ];
  }
}

/**
 * 아이템 부족분 자동 확보: 게임냥 구매 → 부족하면 본방냥 20% 스왑 후 재구매
 * (프변·지목·강일 공용)
 * @return array{ok:bool,msg:string,lines:list<string>}
 */
if (!function_exists('아이템_부족분_자동구매')) {
  function 아이템_부족분_자동구매(string $두자리닉넴, string $아이템명, int $부족개수, bool $강제구매 = false): array {
    $부족개수 = max(1, (int)$부족개수);
    $아이템명 = trim($아이템명);
    $닉 = trim($두자리닉넴);
    if ($닉 === '' || $아이템명 === '') {
      return ['ok' => false, 'msg' => '❌ 닉네임/아이템을 확인해주세요.', 'lines' => []];
    }
    $닉_esc = addslashes($닉);
    // 천경·해 단위: CAST 없이 읽으면 구매 비교가 깨질 수 있음
    $정보 = db_select("SELECT idx, name,
            CAST(IFNULL(point, 0) AS CHAR) AS point,
            CAST(IFNULL(newpoint, 0) AS CHAR) AS newpoint
         FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    if (empty($정보['idx'])) {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.', 'lines' => []];
    }
    if (!function_exists('아이템_상점구매_실행')) {
      return ['ok' => false, 'msg' => '❌ 상점 구매 기능을 불러올 수 없어요.', 'lines' => []];
    }

    $lines = [];
    $구매 = 아이템_상점구매_실행($닉, $정보, $아이템명, $부족개수, $강제구매);
    if (!empty($구매['ok'])) {
      $lines[] = (string)($구매['msg'] ?? "► {$아이템명} {$부족개수}개 자동구매");
      return ['ok' => true, 'msg' => implode("\n", $lines), 'lines' => $lines];
    }

    $구매실패 = trim((string)($구매['msg'] ?? ''));
    // 시세/판매중지 등 자금 외 실패는 스왑하지 않음
    $자금부족 = ($구매실패 === '')
      || (mb_strpos($구매실패, '부족') !== false)
      || (mb_strpos($구매실패, '‼️') !== false);
    if (!$자금부족) {
      return ['ok' => false, 'msg' => $구매실패 !== '' ? $구매실패 : "❌ {$아이템명} {$부족개수}개 자동구매 실패", 'lines' => []];
    }

    // 게임냥 부족(또는 0) → 본방냥 20% 스왑 후 재구매
    $스왑 = 프변_자동_본방냥20퍼_스왑($닉);
    if (empty($스왑['ok'])) {
      $스왑실패 = trim((string)($스왑['msg'] ?? ''));
      $msg = "❌ {$아이템명} {$부족개수}개 자동구매 실패";
      if ($구매실패 !== '') {
        $msg .= "\n" . $구매실패;
      }
      if ($스왑실패 !== '') {
        $msg .= "\n" . $스왑실패;
      }
      return ['ok' => false, 'msg' => $msg, 'lines' => []];
    }
    $lines[] = (string)$스왑['msg'];

    $정보2 = db_select("SELECT idx, name,
            CAST(IFNULL(point, 0) AS CHAR) AS point,
            CAST(IFNULL(newpoint, 0) AS CHAR) AS newpoint
         FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    $구매2 = 아이템_상점구매_실행($닉, $정보2 ?: $정보, $아이템명, $부족개수, $강제구매);
    if (empty($구매2['ok'])) {
      $msg = implode("\n", $lines);
      $msg .= "\n" . trim((string)($구매2['msg'] ?? "❌ 스왑 후에도 {$아이템명} {$부족개수}개 구매에 실패했어요."));
      return ['ok' => false, 'msg' => $msg, 'lines' => $lines];
    }
    $lines[] = (string)($구매2['msg'] ?? "► {$아이템명} {$부족개수}개 자동구매");
    return ['ok' => true, 'msg' => implode("\n", $lines), 'lines' => $lines];
  }
}

/**
 * 프변 부족분 자동 확보 — 아이템_부족분_자동구매 위임
 * @return array{ok:bool,msg:string,lines:list<string>}
 */
if (!function_exists('프변_부족분_자동구매')) {
  function 프변_부족분_자동구매(string $두자리닉넴, int $부족개수): array {
    return 아이템_부족분_자동구매($두자리닉넴, '프변', $부족개수);
  }
}

/** .타이틀 카테고리 비중 — 개그62 · 귀여움10 · 일상음식12 · 판타지게임8 · 감성무협8 */
if (!function_exists('타이틀_카테고리비중')) {
  function 타이틀_카테고리비중(): array {
    return [
      '개그' => 62,
      '귀여움' => 10,
      '일상음식' => 12,
      '판타지게임' => 8,
      '감성무협' => 8,
    ];
  }
}

if (!function_exists('타이틀_가중키_뽑기')) {
  function 타이틀_가중키_뽑기(array $weights): string {
    $sum = 0;
    foreach ($weights as $w) {
      $sum += max(0, (int)$w);
    }
    if ($sum < 1) {
      $keys = array_keys($weights);
      return (string)($keys[0] ?? '');
    }
    $r = mt_rand(1, $sum);
    $acc = 0;
    foreach ($weights as $k => $w) {
      $acc += max(0, (int)$w);
      if ($r <= $acc) {
        return (string)$k;
      }
    }
    $keys = array_keys($weights);
    return (string)($keys[0] ?? '');
  }
}

if (!function_exists('타이틀_로컬풀_분류')) {
  /** @return array<string, string[]> */
  function 타이틀_로컬풀_분류(): array {
    return [
      '개그' => [
        '셀프강화의', '전남친의', '전여친의', '매국노의', '거지의', '똥개의', '햄스터의',
        '왕따의', '럭키의', '불운의', '대운의', '인생의', '운빨의', '실력의', '노답의',
        '황당의', '황당함의', '개그의', '병맛의', '드립의', '밈의', '짤의', '숏츠의', '유튜브의',
        '치맥충의', '게임폐인의', '밤샘의', '다이어트의', '치트의', '버그의', '패치의',
        '랜덤의', '술고래의', '해장의', '만취의', '취중의',
        '월급루팡의', '카드값의', '통장의', '잔고의', '마이너스의', '빚쟁이의', '신불의',
        '일론의', '재벌의', '흙수저의', '금수저의', '평범함의', '무난함의',
        '솔로의', '차단의', '읽씹의', '안읽씹의', '프사의', '닉네임의',
        '강화실패의', '연속실패의', '대박의', '쪽박의', '올인의', '올인실패의', '복구의',
        '홀짝의', '짝의', '홀의', '무승부의', '타짜의', '초보의', '고인물의', '뉴비의',
        '아재의', '할매의', '할배의', '조카의', '이모의', '삼촌의', '사촌의',
        '츤데레의', '얀데레의', '쿨데레의', '천재의', '바보의', '천재바보의', '4차원의',
        '출근의', '야근의', '월급의', '퇴근의', '점심의', '커피의', '졸음의', '월요일의',
        '금요일의', '주말의', '연차의', '지각의', '회의의', '보고서의',
        '본방의', '게임방의', '홍보방의', '상황실의', '오픈채팅의',
        '돼지의', '오리의', '참새의', '까마귀의', '두더지의', '고양이의', '멍멍이의',
      ],
      '귀여움' => [
        '뽀송뽀송의', '포근포근의', '말랑말랑의', '보송보송의', '달콤함의', '사랑스러움의',
        '귀여움의', '애교의', '수줍음의', '소녀의', '공주의', '천사의', '요정의',
        '하트의', '하트뿅의', '두근두근의', '솜사탕의', '마시멜로의', '별사탕의',
        '푸딩의', '케이크의', '쿠키의', '초코의', '딸기의', '벚꽃의', '꽃냄새의',
        '라벤더의', '무지개의', '첫눈의', '햇살의', '달달함의',
        '토끼의', '말티즈의', '비숑의', '병아리의', '아기고양이의', '아기강아지의', '펭귄의',
        '꼬마의', '아기의', '순둥이의', '말랑콩떡의', '보들보들의', '사랑둥이의',
        '설레임의', '심쿵의', '러블리의', '사랑가득의', '상큼함의', '포동포동의', '말랑이의',
      ],
      '일상음식' => [
        '국밥의', '치킨의', '냉면의', '라면의', '삼겹의', '치맥의', '떡볶이의', '곱창의',
        '마라의', '피자의', '햄버거의', '두부의', '김치의', '배달의', '야식의', '간식의',
        '냉장고의', '짜장의', '짬뽕의', '츄러스의', '붕어빵의', '호떡의', '순대의',
        '튀김의', '막창의', '소주의', '맥주의',
      ],
      '판타지게임' => [
        '흑룡의', '폭풍의', '마법사의', '성기사의', '암살자의', '용사의', '현자의', '광전사의',
        '뇌신의', '빙결의', '화염의', '그림자의', '전설의', '신화의',
        '보스의', '레이드의', '던전의', '파밍의', '강화의', '뽑기의', '랭커의', '챌린저의',
      ],
      '감성무협' => [
        '무림의', '검성의', '도사의', '짝사랑의', '첫사랑의', '운명의',
        '별빛의', '달빛의', '설렘의', '그리움의', '추억의', '평화의', '자유의', '연애의', '커플의', '썸의',
      ],
    ];
  }
}

if (!function_exists('타이틀_금지목록')) {
  /** 지금 많이 쓰이는 칭호 + 내 현재 칭호 — 반복 억제 */
  function 타이틀_금지목록($내닉, $내현재 = ''): array {
    $ban = [];
    $me = trim((string)$내현재);
    if ($me !== '') {
      $ban[$me] = true;
    }
    $rs = @db_query("
      SELECT style, COUNT(*) AS cnt
      FROM tb_member
      WHERE IFNULL(style, '') <> ''
      GROUP BY style
      ORDER BY cnt DESC, style ASC
      LIMIT 40
    ");
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $s = trim((string)($row['style'] ?? ''));
        if ($s !== '') {
          $ban[$s] = true;
        }
      }
    }
    return array_keys($ban);
  }
}

if (!function_exists('타이틀_풀에서뽑기')) {
  function 타이틀_풀에서뽑기(string $cat, array $ban = []): string {
    $pools = 타이틀_로컬풀_분류();
    $list = $pools[$cat] ?? [];
    if ($list === []) {
      foreach ($pools as $arr) {
        $list = array_merge($list, $arr);
      }
    }
    $banMap = [];
    foreach ($ban as $b) {
      $b = trim((string)$b);
      if ($b !== '') {
        $banMap[$b] = true;
      }
    }
    $ok = [];
    foreach ($list as $t) {
      if (!isset($banMap[$t])) {
        $ok[] = $t;
      }
    }
    if ($ok === []) {
      $ok = $list;
    }
    if ($ok === []) {
      return '';
    }
    return (string)$ok[array_rand($ok)];
  }
}

if (!function_exists('타이틀_힌트문구')) {
  function 타이틀_힌트문구(string $cat): string {
    $hints = [
      '개그' => [
        '개그/병맛 (황당·웃긴 일상)',
        '드립/밈 (인터넷 밈·짤 문화)',
        '동물 개그 (똥개·고양이·햄스터)',
        '운/확률 드립 (운빨·노답·대박·쪽박)',
        '직장/학교 (출근·야근·월급·지각)',
        '연애/썸 드립 (솔로·읽씹·전남친)',
        '술/해장/만취',
        '아재/밈 유행어',
        '4차원/천재바보',
        '완전 랜덤 황당 조합',
      ],
      '귀여움' => [
        '귀여움/사랑스러움 (뽀송·포근·하트·설렘)',
        '동물 귀여움 (토끼·말티즈·병아리·아기고양이)',
        '디저트/달달함 (솜사탕·케이크·딸기·푸딩)',
        '꽃·향기·계절 (벚꽃·라벤더·첫눈·햇살)',
      ],
      '일상음식' => [
        '음식 먹방 (국밥·치킨·라면·야식)',
        '디저트 말고 한식·야식·배달',
        '술/해장/치맥',
      ],
      '판타지게임' => [
        '게임 슬랭 (파밍·올인·뉴비·고인물)',
        '게임방/본방 문화',
        '판타지 보스풍 — 용사의·전설의·마법사는 쓰지 말 것. 더 특이한 보스/직업',
      ],
      '감성무협' => [
        '무협/검술풍 (검성·도사 말고 다른 무공·유파)',
        'K-드라마 감성',
        '신화/전설 — 전설의·신화의 반복 금지',
      ],
    ];
    $list = $hints[$cat] ?? $hints['개그'];
    return (string)$list[array_rand($list)];
  }
}

/**
 * .타이틀 — 게임냥 30만 차감, 로컬 풀(12%)·GPT(88%) 칭호를 style 에 저장.
 * 카테고리 실제 비중: 개그62 · 귀여움10 · 일상음식12 · 판타지게임8 · 감성무협8
 * (아주 예전: 본방냥 0.1이 아니라 tb_member.point 30만)
 * 매칭·처리 시 전송 후 exit, 미매칭 시 false.
 */
if (!function_exists('타이틀_명령_처리')) {
  function 타이틀_명령_처리($status, $두자리닉넴, $정보 = []) {
    if (trim((string)$status) !== '.타이틀') {
      return false;
    }
    $두자리닉넴 = trim((string)$두자리닉넴);
    if ($두자리닉넴 === '') {
      echo 전송('❌ 닉네임을 확인해주세요.');
      exit;
    }
    $타이틀비용 = '300000';
    $닉_타이틀_esc = addslashes($두자리닉넴);
    $보유행 = db_select("SELECT CONCAT('N', CAST(IFNULL(point, 0) AS CHAR)) AS point FROM tb_member WHERE name = '{$닉_타이틀_esc}' LIMIT 1");
    $보유_raw = (string)($보유행['point'] ?? 'N0');
    if (isset($보유_raw[0]) && ($보유_raw[0] === 'N' || $보유_raw[0] === 'n')) {
      $보유_raw = substr($보유_raw, 1);
    }
    $보유냥 = function_exists('냥_정수문자열')
      ? 냥_정수문자열($보유_raw !== '' ? $보유_raw : ($정보['point'] ?? 0))
      : (ltrim(preg_replace('/[^\d\-]/', '', (string)($보유_raw !== '' ? $보유_raw : ($정보['point'] ?? 0))), '0') ?: '0');
    if ($보유냥 === '' || $보유냥 === null) {
      $보유냥 = '0';
    }
    $부족 = function_exists('bccomp')
      ? bccomp($보유냥, $타이틀비용, 0) < 0
      : ((strlen(ltrim($보유냥, '-')) < strlen($타이틀비용))
        || (strlen(ltrim($보유냥, '-')) === strlen($타이틀비용) && ltrim($보유냥, '-') < $타이틀비용)
        || (isset($보유냥[0]) && $보유냥[0] === '-'));
    $보유표시 = function_exists('게임냥_안전표시')
      ? 게임냥_안전표시($보유냥, '냥')
      : ((function_exists('냥_숫자콤마') ? 냥_숫자콤마($보유냥) : $보유냥) . '냥');
    if ($부족) {
      echo 전송("❌ 타이틀 변경에 " . number_format(300000) . "냥이 필요해요.\n현재 보유: {$보유표시}");
      exit;
    }

    $랜덤멤버 = db_select("SELECT name FROM tb_member WHERE name IS NOT NULL AND name != '' ORDER BY RAND() LIMIT 1");
    $첫칭호 = (!empty($랜덤멤버['name'])) ? ($랜덤멤버['name'] . '의') : ($두자리닉넴 . '의');

    $내현재 = trim((string)($정보['style'] ?? ''));
    if ($내현재 === '') {
      $스타일행 = db_select("SELECT style FROM tb_member WHERE name = '{$닉_타이틀_esc}' LIMIT 1");
      $내현재 = trim((string)($스타일행['style'] ?? ''));
    }
    $금지 = function_exists('타이틀_금지목록') ? 타이틀_금지목록($두자리닉넴, $내현재) : array_filter([$내현재]);
    $카테고리 = function_exists('타이틀_가중키_뽑기')
      ? 타이틀_가중키_뽑기(타이틀_카테고리비중())
      : '개그';

    $선택칭호 = '';
    $로컬직행 = (mt_rand(1, 100) <= 12);
    if ($로컬직행 && function_exists('타이틀_풀에서뽑기')) {
      $선택칭호 = 타이틀_풀에서뽑기($카테고리, $금지);
    } else {
      $힌트 = function_exists('타이틀_힌트문구') ? 타이틀_힌트문구($카테고리) : $카테고리;
      $금지문구 = $금지 !== [] ? implode(', ', array_slice($금지, 0, 25)) : '(없음)';
      $gpt_system = "너는 한국 게임 칭호(스타일) 생성기야. 규칙:\n"
        . "1) 반드시 '~의' 로 끝나는 칭호 단어 하나만 출력한다.\n"
        . "2) 전체 길이는 한글 2~8글자 + '의'. 따옴표·마침표·설명·번호·공백 금지.\n"
        . "3) 이번 요청의 카테고리를 반드시 지켜라. 다른 톤으로 도망가지 마라.\n"
        . "4) 기본은 개그·병맛·드립이다. 판타지·용사·전설 톤으로 빠지지 마라.\n"
        . "5) 금지 목록에 있는 단어는 절대 쓰지 마라. 비슷한 말도 피하라.\n"
        . "6) 용사의, 마법사의, 전설의, 신화의, 흑룡의, 폭풍의, 검성의, 달빛의 는 너무 흔하니 쓰지 마라.\n"
        . "7) 욕설·혐오·선정·정치·실존인 실명 비하 금지. 가벼운 패러디는 OK.";
      $시드 = mt_rand(1000, 9999);
      $gpt_user = "[요청#{$시드}]\n필수 카테고리: {$카테고리}\n이번 힌트: {$힌트}\n"
        . "금지 칭호: {$금지문구}\n내 현재 칭호(반복 금지): " . ($내현재 !== '' ? $내현재 : '(없음)') . "\n"
        . "참고용 첫칭호(15% 확률로만 이걸 그대로 써도 됨): {$첫칭호}\n"
        . "카테고리에 맞는 새로운 칭호 1개만 출력해.";
      $gpt응답 = function_exists('callGPT') ? callGPT($gpt_user, $gpt_system, 40) : '';
      $gpt응답 = trim((string)$gpt응답);
      $gpt응답 = preg_replace('/["\'`\.\,\r\n\s]+/u', '', $gpt응답);
      $gpt응답 = preg_replace('/^.*?([가-힣A-Za-z0-9]+의).*$/u', '$1', $gpt응답);
      $길이 = mb_strlen($gpt응답, 'UTF-8');
      $금지맵 = array_fill_keys($금지, true);
      $뻔한 = ['용사의' => 1, '마법사의' => 1, '전설의' => 1, '신화의' => 1, '흑룡의' => 1, '폭풍의' => 1, '검성의' => 1, '달빛의' => 1];
      if ($gpt응답 !== '' && mb_substr($gpt응답, -1, 1, 'UTF-8') === '의' && $길이 >= 2 && $길이 <= 12
        && !isset($금지맵[$gpt응답]) && !isset($뻔한[$gpt응답])) {
        $선택칭호 = $gpt응답;
      }
    }
    if ($선택칭호 === '' && function_exists('타이틀_풀에서뽑기')) {
      $선택칭호 = 타이틀_풀에서뽑기($카테고리, $금지);
    }
    if ($선택칭호 === '' && function_exists('타이틀_풀에서뽑기')) {
      $선택칭호 = 타이틀_풀에서뽑기($카테고리, []);
    }
    if ($선택칭호 === '') {
      $선택칭호 = $첫칭호;
    }

    $칭호_esc = addslashes($선택칭호);
    $비용_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($타이틀비용) : '300000';
    db_query("UPDATE tb_member SET style = '{$칭호_esc}', point = point - {$비용_sql} WHERE name = '{$닉_타이틀_esc}' AND CAST(IFNULL(point, 0) AS DECIMAL(65,0)) >= {$비용_sql}");
    $잔액행 = db_select("SELECT CONCAT('N', CAST(IFNULL(point, 0) AS CHAR)) AS point FROM tb_member WHERE name = '{$닉_타이틀_esc}' LIMIT 1");
    $잔액_raw = (string)($잔액행['point'] ?? 'N0');
    if (isset($잔액_raw[0]) && ($잔액_raw[0] === 'N' || $잔액_raw[0] === 'n')) {
      $잔액_raw = substr($잔액_raw, 1);
    }
    $잔액냥 = function_exists('냥_정수문자열')
      ? 냥_정수문자열($잔액_raw)
      : (ltrim(preg_replace('/[^\d\-]/', '', (string)$잔액_raw), '0') ?: '0');
    $잔액표시 = function_exists('게임냥_안전표시')
      ? 게임냥_안전표시($잔액냥, '냥')
      : ((function_exists('냥_숫자콤마') ? 냥_숫자콤마($잔액냥) : $잔액냥) . '냥');
    echo 전송("✨ 타이틀 변경!\n• {$선택칭호} (" . number_format(300000) . "냥 차감)\n현재 보유: {$잔액표시}");
    exit;
  }
}

/**
 * .프변 [N] — 프로필변경 1개당 3일. N개 일괄 사용.
 * 부족 시: 게임냥으로 프변 자동구매 → 없으면 본방냥 20% 스왑 후 구매 → 사용.
 * $무응답=true 이면 성공/실패 모두 채팅 응답 없이 처리(본방용).
 */
function 프변_명령_처리($두자리닉넴, $개수 = 1, $무응답 = false) {
  $개수 = max(1, (int)$개수);
  $닉_esc = addslashes(trim((string)$두자리닉넴));
  if ($닉_esc === '') {
    if ($무응답) {
      exit;
    }
    echo 전송('❌ 닉네임을 확인해주세요.');
    exit;
  }

  $보유개수 = item_bag_qty_nick($두자리닉넴, '프변');
  $자동문구 = '';
  if ($보유개수 < $개수) {
    $부족 = $개수 - $보유개수;
    $자동 = 프변_부족분_자동구매($두자리닉넴, $부족);
    if (empty($자동['ok'])) {
      if ($무응답) {
        exit;
      }
      echo 전송((string)($자동['msg'] ?? "❌ 프변 아이템 부족! (요청 {$개수}개 · 보유 {$보유개수}개)"));
      exit;
    }
    $자동문구 = trim((string)($자동['msg'] ?? ''));
  }

  $프변차감 = item_bag_sub_nick($두자리닉넴, '프변', $개수);
  if (empty($프변차감['ok'])) {
    if ($무응답) {
      exit;
    }
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

  if (function_exists('아이템사용_시세하락')) {
    아이템사용_시세하락('프변', $개수);
  }

  if ($무응답) {
    return '';
  }

  $만료표시 = date('m-d H:i', strtotime($유효시간));
  if ($연장중) {
    if ($개수 > 1) {
      $결과 = "✅ 프로필변경 {$연장일}일 연장! (프변 {$개수}개)\n만료: {$만료표시}";
    } else {
      $결과 = "✅ 프로필변경 3일 연장!\n만료: {$만료표시}";
    }
  } elseif ($개수 > 1) {
    $결과 = "✅ 프로필변경 적용! (프변 {$개수}개 · {$연장일}일)\n만료: {$만료표시}";
  } else {
    $결과 = "✅ 프로필변경 적용! (3일)\n만료: {$만료표시}";
  }
  if ($자동문구 !== '') {
    $결과 = $자동문구 . "\n\n" . $결과;
  }
  return $결과;
}

/**
 * .지호 [개수] — 지호 1개=1시간 버프. 매칭·처리 시 전송 후 exit, 미매칭 시 return.
 */
if (!function_exists('지호_명령_처리')) {
  function 지호_명령_처리($status, $두자리닉넴) {
    $statusTrim = trim((string)$status);
    if (!preg_match('/^\.지호(?:\s+(\d+))?\s*$/u', $statusTrim, $지호매치)) {
      return;
    }
    if ($두자리닉넴 === '') {
      echo 전송('❌ 닉네임을 확인해주세요.');
      exit;
    }
    $사용개수 = 1;
    if (isset($지호매치[1]) && $지호매치[1] !== '') {
      $사용개수 = (int)$지호매치[1];
    }
    if ($사용개수 < 1) {
      echo 전송("❌ 사용 개수는 1개 이상만 가능합니다.\n예) .지호 · .지호 5");
      exit;
    }

    $전체메시지행 = db_select("SELECT COUNT(*) AS cnt FROM tb_msg");
    $전체행수 = (int)($전체메시지행['cnt'] ?? 0);
    if ($전체행수 < 100) {
      echo 전송("❌ 누적 타수 100 미만에서는 지호를 사용할 수 없습니다. (현재 {$전체행수}타)");
      exit;
    }
    if (!function_exists('item_bag_qty_nick') && is_file(__DIR__ . '/item_bag.inc.php')) {
      require_once __DIR__ . '/item_bag.inc.php';
    }
    $현재보유개수 = function_exists('item_bag_qty_nick') ? item_bag_qty_nick($두자리닉넴, '지호') : 0;
    if ($현재보유개수 < $사용개수) {
      echo 전송("지호 아이템 부족! (요청 {$사용개수}개 · 보유 {$현재보유개수}개)");
      exit;
    }
    if (!function_exists('item_bag_sub_nick')) {
      echo 전송("❌ 아이템 가방 기능을 불러올 수 없어요.");
      exit;
    }
    $차감 = item_bag_sub_nick($두자리닉넴, '지호', $사용개수);
    if (empty($차감['ok'])) {
      echo 전송("지호 아이템 없음!");
      exit;
    }
    if (function_exists('아이템사용_시세하락')) {
      아이템사용_시세하락('지호', $사용개수);
    }
    $추가시간 = (int)$사용개수;
    $닉_esc = addslashes($두자리닉넴);
    $사용중여부 = db_select("select * from tb_item_use where nickname = '{$닉_esc}' and item = '지호' ");
    if (!empty($사용중여부['idx'])) {
      $유효시간 = date("Y-m-d H:i", strtotime($사용중여부['enddate'] . " +{$추가시간} hours"));
      db_query("update tb_item_use set enddate = '{$유효시간}' where idx = {$사용중여부['idx']} ");
      $연장시간 = date("m-d H:i", strtotime($유효시간));
      echo 전송("►{$두자리닉넴} 지호 {$사용개수}개 적용\n{$연장시간} 까지");
      exit;
    }
    $유효시간 = date("Y-m-d H:i", strtotime("+{$추가시간} hours"));
    db_query("insert into tb_item_use set nickname = '{$닉_esc}', item = '지호', enddate = '{$유효시간}', regdate = now() ");
    $연장시간 = date("m-d H:i", strtotime($유효시간));
    echo 전송("►{$두자리닉넴} 지호 {$사용개수}개 사용\n{$연장시간} 까지 ");
    exit;
  }
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
 * 예) 5000000, 5000만, 100억, 5조, 2경, 5천조, 1조5000억, 30억5천, 5천, 5조냥
 * PHP_INT_MAX 초과·연산 오버플로 시 0 (파싱 실패)
 * @return int 파싱 실패 시 0
 */
function 냥_금액_파싱($text) {
  $s = 냥_금액_파싱_문자열($text);
  if ($s === '0') {
    return 0;
  }
  $intMaxStr = (string)PHP_INT_MAX;
  if (function_exists('bccomp')) {
    if (bccomp($s, $intMaxStr, 0) > 0) {
      return 0;
    }
    return (int)$s;
  }
  if (strlen($s) > strlen($intMaxStr)
    || (strlen($s) === strlen($intMaxStr) && $s > $intMaxStr)) {
    return 0;
  }
  return (int)$s;
}

/**
 * 맞다이 금액 표시 — 축약
 */
if (!function_exists('맞다이_금액표시')) {
  function 맞다이_금액표시($금액, $단위 = '냥'): string {
    if (function_exists('냥축약표시')) {
      return 냥축약표시($금액, $단위);
    }
    return number_format((float)$금액) . $단위;
  }
}

/**
 * 맞다이 깽값 파싱 — 숫자만이면 냥 그대로 (경 자동변환 없음)
 * @return array{ok:bool,amount:int,bare:bool,msg?:string}
 */
if (!function_exists('맞다이_금액파싱')) {
  function 맞다이_금액파싱($text): array {
    $raw = trim((string)$text);
    $raw = str_replace([',', ' '], '', $raw);
    if ($raw === '') {
      return ['ok' => false, 'amount' => 0, 'bare' => false, 'msg' => '금액 없음'];
    }
    $bare = (bool)preg_match('/^\d+$/u', $raw);
    if ($bare) {
      // 숫자만 → 냥 (예: 1000 → 1000냥, 1000경 아님)
      $digits = ltrim($raw, '0') ?: '0';
      if ($digits === '0') {
        return ['ok' => false, 'amount' => 0, 'bare' => true, 'msg' => '0'];
      }
      $intMax = (string)PHP_INT_MAX;
      if (function_exists('bccomp') && bccomp($digits, $intMax, 0) > 0) {
        return ['ok' => false, 'amount' => 0, 'bare' => true, 'msg' => '너무 큼'];
      }
      if (strlen($digits) > strlen($intMax)
        || (strlen($digits) === strlen($intMax) && $digits > $intMax)) {
        return ['ok' => false, 'amount' => 0, 'bare' => true, 'msg' => '너무 큼'];
      }
      return ['ok' => true, 'amount' => (int)$digits, 'bare' => true];
    }
    $parsed = 냥_금액_파싱($raw);
    $amount = is_numeric($parsed) ? (int)$parsed : 0;
    if ($amount <= 0) {
      return ['ok' => false, 'amount' => 0, 'bare' => false, 'msg' => '형식오류'];
    }
    return ['ok' => true, 'amount' => $amount, 'bare' => false];
  }
}

/**
 * 냥 금액 문자열 → 부호 없는 정수 문자열 (PHP_INT_MAX 초과 허용)
 * 예) 1000경 → "10000000000000000000", 500경 → "5000000000000000000"
 * @return string 파싱 실패 시 '0'
 */
function 냥_금액_문자열합(string $a, string $b): string {
  $a = ltrim(preg_replace('/[^\d]/', '', $a), '0') ?: '0';
  $b = ltrim(preg_replace('/[^\d]/', '', $b), '0') ?: '0';
  if (function_exists('bcadd')) {
    return bcadd($a, $b, 0);
  }
  // 학교식 덧셈 (bcmath 없을 때 경/해 지원)
  $a = strrev($a);
  $b = strrev($b);
  $len = max(strlen($a), strlen($b));
  $carry = 0;
  $out = '';
  for ($i = 0; $i < $len; $i++) {
    $sum = $carry + (int)($a[$i] ?? '0') + (int)($b[$i] ?? '0');
    $out .= (string)($sum % 10);
    $carry = intdiv($sum, 10);
  }
  if ($carry > 0) {
    $out .= (string)$carry;
  }
  return ltrim(strrev($out), '0') ?: '0';
}

/** a - b (음수면 '0') — (int) 캐스팅 금지 (922경≈PHP_INT_MAX 잘림 방지) */
function 냥_금액_문자열차감(string $a, string $b): string {
  $a = ltrim(preg_replace('/[^\d]/', '', $a), '0') ?: '0';
  $b = ltrim(preg_replace('/[^\d]/', '', $b), '0') ?: '0';
  if (function_exists('bcsub') && function_exists('bccomp')) {
    if (bccomp($a, $b, 0) < 0) {
      return '0';
    }
    return bcsub($a, $b, 0);
  }
  if (strlen($a) < strlen($b) || (strlen($a) === strlen($b) && $a < $b)) {
    return '0';
  }
  if ($b === '0') {
    return $a;
  }
  $a = strrev($a);
  $b = strrev($b);
  $len = strlen($a);
  $borrow = 0;
  $out = '';
  for ($i = 0; $i < $len; $i++) {
    $da = (int)$a[$i] - $borrow;
    $db = (int)($b[$i] ?? '0');
    if ($da < $db) {
      $da += 10;
      $borrow = 1;
    } else {
      $borrow = 0;
    }
    $out .= (string)($da - $db);
  }
  return ltrim(strrev($out), '0') ?: '0';
}

function 냥_금액_문자열곱(string $a, string $b): string {
  $a = ltrim(preg_replace('/[^\d]/', '', $a), '0') ?: '0';
  $b = ltrim(preg_replace('/[^\d]/', '', $b), '0') ?: '0';
  if ($a === '0' || $b === '0') {
    return '0';
  }
  if (function_exists('bcmul')) {
    return bcmul($a, $b, 0);
  }
  // b가 10^k 형태면 자릿수 붙이기
  if (preg_match('/^1(0+)$/', $b, $m)) {
    return $a . $m[1];
  }
  if (preg_match('/^1(0+)$/', $a, $m)) {
    return $b . $m[1];
  }
  // 일반 곱셈 (자리수 제한적으로 안전)
  $la = strlen($a);
  $lb = strlen($b);
  $res = array_fill(0, $la + $lb, 0);
  for ($i = $la - 1; $i >= 0; $i--) {
    for ($j = $lb - 1; $j >= 0; $j--) {
      $mul = ((int)$a[$i]) * ((int)$b[$j]);
      $p = $i + $j + 1;
      $sum = $res[$p] + $mul;
      $res[$p] = $sum % 10;
      $res[$p - 1] += intdiv($sum, 10);
    }
  }
  $s = ltrim(implode('', $res), '0');
  return $s !== '' ? $s : '0';
}

function 냥_금액_파싱_문자열($text): string {
  $text = trim((string)$text);
  if ($text === '') {
    return '0';
  }
  $text = str_replace([',', ' ', '냥', '원'], '', $text);
  if ($text === '') {
    return '0';
  }
  if (preg_match('/^\d+$/', $text)) {
    return ltrim($text, '0') ?: '0';
  }
  static $units = null;
  if ($units === null) {
    // 긴 단위 우선 매칭 (천경 > 천억 > 천, 천조 > 조 …)
    // 단독 '천'/'백'은 1000/100 — 천억·백억은 '천억'/'백억' 명시
    $units = [
      '천경' => '10000000000000000000',
      '해' => '100000000000000000000', // 1해 = 1만경 = 10^20
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
      '천' => '1000',
      '백' => '100',
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
    return '0';
  }
  $consumed = '';
  $useBc = function_exists('bcadd') && function_exists('bccomp') && function_exists('bcmul');
  if (!$useBc) {
    // bcmath 없으면 문자열 자릿수 곱셈으로 경/해 등 처리 (INT 캐스팅 금지)
    $total = '0';
    foreach ($matches as $m) {
      $consumed .= $m[0];
      $nStr = ltrim($m[1], '0') ?: '0';
      $unit = $m[2] ?? '';
      if ($unit !== '' && isset($units[$unit])) {
        $part = 냥_금액_문자열곱($nStr, $units[$unit]);
      } else {
        $part = $nStr;
      }
      $total = 냥_금액_문자열합($total, $part);
    }
    if ($consumed !== $text || $total === '0') {
      return '0';
    }
    return $total;
  }
  $total = '0';
  foreach ($matches as $m) {
    $consumed .= $m[0];
    // (int) 금지 — 계수·단위 모두 문자열 (923경 = 9.23e18 > PHP_INT_MAX)
    $nStr = ltrim($m[1], '0') ?: '0';
    $unit = $m[2] ?? '';
    if ($unit !== '' && isset($units[$unit])) {
      $part = bcmul($nStr, $units[$unit], 0);
      $total = bcadd($total, $part, 0);
    } else {
      $total = bcadd($total, $nStr, 0);
    }
  }
  if ($consumed !== $text) {
    return '0';
  }
  if (bccomp($total, '0', 0) <= 0) {
    return '0';
  }
  return $total;
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
 * .모금 금액·보유냥 검증 (천경·해 등 문자열 파싱 · (int) 금지)
 * @return array{ok:bool, 금액?:string, msg?:string}
 */
if (!function_exists('모금_입력검증')) {
  function 모금_입력검증(string $금액텍스트, $보유냥, $최소모금): array {
    $금액 = function_exists('냥_금액_파싱_문자열')
      ? 냥_금액_파싱_문자열($금액텍스트)
      : (function_exists('냥_금액_파싱') ? (string)(int)냥_금액_파싱($금액텍스트) : preg_replace('/[^\d]/', '', $금액텍스트));
    $금액 = ltrim((string)$금액, '0') ?: '0';
    $최소Str = function_exists('냥_정수문자열')
      ? 냥_정수문자열($최소모금)
      : (ltrim(preg_replace('/[^\d]/', '', (string)$최소모금), '0') ?: '0');

    if ($금액 === '0' || (function_exists('bccomp') ? bccomp($금액, '0', 0) <= 0 : (float)$금액 <= 0)) {
      return [
        'ok' => false,
        'msg' => "❌ 금액 형식을 확인해주세요.\n예) .모금 1조 / .모금 1천경 / .모금 1해 / .모금 진우 3천억",
      ];
    }
    $미만최소 = function_exists('bccomp')
      ? (bccomp($금액, $최소Str, 0) < 0)
      : ((float)$금액 < (float)$최소Str);
    if ($미만최소) {
      $최소표시 = function_exists('냥축약표시') ? 냥축약표시($최소Str) : number_format((float)$최소Str) . '냥';
      return ['ok' => false, 'msg' => "모금은 최소 {$최소표시} 이상 가능!"];
    }
    if (function_exists('냥_정수_미만') && 냥_정수_미만($보유냥, $금액)) {
      $보유표시 = function_exists('냥축약표시') ? 냥축약표시($보유냥) : number_format((float)$보유냥) . '냥';
      $요청표시 = function_exists('냥축약표시') ? 냥축약표시($금액) : number_format((float)$금액) . '냥';
      return ['ok' => false, 'msg' => "냥 부족! 보유 {$보유표시} · 요청 {$요청표시}"];
    }
    return ['ok' => true, '금액' => $금액];
  }
}

/**
 * 냥 금액 문자열 → 정수 (앞 -/+ 부호 + 만·억·조·경·천·백 축약)
 * 예) 1조, 1경, 1조5천억, 30억5천, 5천, -1조, +100억
 * @return int 파싱 실패 시 0
 */
function 냥_금액_파싱_부호포함($text) {
  $s = 냥_금액_파싱_부호포함_문자열($text);
  if ($s === '0') {
    return 0;
  }
  $음수 = isset($s[0]) && $s[0] === '-';
  $digits = $음수 ? substr($s, 1) : $s;
  $intMaxStr = (string)PHP_INT_MAX;
  if (function_exists('bccomp')) {
    if (bccomp($digits, $intMaxStr, 0) > 0) {
      return 0;
    }
    $n = (int)$digits;
    return $음수 ? -$n : $n;
  }
  if (strlen($digits) > strlen($intMaxStr)
    || (strlen($digits) === strlen($intMaxStr) && $digits > $intMaxStr)) {
    return 0;
  }
  $n = (int)$digits;
  return $음수 ? -$n : $n;
}

/**
 * 냥 금액 문자열 → 부호 포함 정수 문자열 (PHP_INT_MAX 초과 허용)
 * 예) 1000경 → "10000000000000000000", -500경 → "-5000000000000000000"
 * @return string 파싱 실패 시 '0'
 */
function 냥_금액_파싱_부호포함_문자열($text): string {
  $text = trim((string)$text);
  if ($text === '') {
    return '0';
  }
  // ASCII/유니코드/전각 마이너스 허용 (.겜냥 다오 -923경 / .본냥 다오 -100)
  $text = preg_replace('/^[\x{2212}\x{FF0D}\x{FE63}-]+/u', '-', $text);
  $음수 = false;
  if (isset($text[0]) && $text[0] === '-') {
    $음수 = true;
    $text = ltrim(substr($text, 1));
  } elseif (isset($text[0]) && $text[0] === '+') {
    $text = ltrim(substr($text, 1));
  }
  if ($text === '') {
    return '0';
  }
  $금액 = 냥_금액_파싱_문자열($text);
  if ($금액 === '0') {
    return '0';
  }
  return $음수 ? ('-' . $금액) : $금액;
}

/** 게임냥 입금/차감 SQL용 — 부호 문자열을 point ± 리터럴로 */
if (!function_exists('냥_입금_SQL증감')) {
  /**
   * @return array{ok:bool, sql?:string, abs?:string, sign?:int, msg?:string}
   * sign: 1 입금, -1 차감
   */
  function 냥_입금_SQL증감($금액문자열): array {
    $s = trim((string)$금액문자열);
    if ($s === '' || $s === '0' || $s === '-0') {
      return ['ok' => false, 'msg' => '금액 0'];
    }
    $sign = 1;
    if (isset($s[0]) && $s[0] === '-') {
      $sign = -1;
      $s = substr($s, 1);
    } elseif (isset($s[0]) && $s[0] === '+') {
      $s = substr($s, 1);
    }
    $abs = function_exists('냥_정수문자열') ? 냥_정수문자열($s) : (ltrim(preg_replace('/[^\d]/', '', $s), '0') ?: '0');
    if ($abs === '0') {
      return ['ok' => false, 'msg' => '금액 0'];
    }
    return [
      'ok' => true,
      'abs' => $abs,
      'sign' => $sign,
      'sql' => ($sign < 0) ? ("point - {$abs}") : ("point + {$abs}"),
    ];
  }
}

/**
 * 게임냥(point) 이체 — 마이너스(신불) 잔액도 정확히 가감.
 * 예) -12억 + 1억 = -11억 (PHP bcmath로 계산 후 SET)
 *
 * @return array{ok:bool, msg?:string, before?:string, after?:string, abs?:string, sign?:int, name?:string, idx?:int}
 */
if (!function_exists('게임냥_이체_적용')) {
  function 게임냥_이체_적용($받는닉, $지급양Str): array {
    $받는닉 = trim((string)$받는닉);
    if ($받는닉 === '') {
      return ['ok' => false, 'msg' => '대상 닉 없음'];
    }
    if (function_exists('getTwoCharNick')) {
      $파싱닉 = getTwoCharNick($받는닉);
      if ($파싱닉 !== '') {
        $받는닉 = $파싱닉;
      }
    }
    $증감 = 냥_입금_SQL증감($지급양Str);
    if (!$증감 || empty($증감['ok'])) {
      return ['ok' => false, 'msg' => (string)($증감['msg'] ?? '금액 오류')];
    }
    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }
    $abs양 = (string)$증감['abs'];
    $sign = (int)$증감['sign'];
    $받는닉_esc = addslashes($받는닉);
    $받는친구 = db_select("SELECT idx, name, CAST(IFNULL(point, 0) AS CHAR) AS point_str FROM tb_member WHERE name = '{$받는닉_esc}' LIMIT 1");
    if (empty($받는친구['idx'])) {
      $받는친구 = db_select("SELECT idx, name, CAST(IFNULL(point, 0) AS CHAR) AS point_str FROM tb_member WHERE TRIM(name) = '{$받는닉_esc}' LIMIT 1");
    }
    if (empty($받는친구['idx'])) {
      return ['ok' => false, 'msg' => '존재하지 않는 사용자 입니다.'];
    }
    $받는_idx = (int)$받는친구['idx'];
    $전액 = function_exists('냥_부호포함_정수문자열')
      ? 냥_부호포함_정수문자열($받는친구['point_str'] ?? '0')
      : (string)($받는친구['point_str'] ?? '0');
    if (!preg_match('/^-?\d+$/', $전액)) {
      $전액 = '0';
    }
    if (function_exists('bcadd') && function_exists('bcsub')) {
      $후액 = ($sign >= 0) ? bcadd($전액, $abs양, 0) : bcsub($전액, $abs양, 0);
    } else {
      // bcmath 없을 때: DECIMAL 리터럴 ± 로 처리 (가능하면 CAST SIGNED)
      $후액 = null;
    }
    global $conn;
    if ($후액 !== null && preg_match('/^-?\d+$/', (string)$후액)) {
      $후액_sql = (string)$후액;
      $ok_upd = db_query("UPDATE tb_member SET point = {$후액_sql} WHERE idx = {$받는_idx} LIMIT 1");
    } else {
      // fallback: MySQL 산술 (부호 있는 DECIMAL 전제)
      $expr = ($sign >= 0) ? "point + {$abs양}" : "point - {$abs양}";
      $ok_upd = db_query("UPDATE tb_member SET point = ({$expr}) WHERE idx = {$받는_idx} LIMIT 1");
    }
    $err = ($conn instanceof mysqli) ? trim((string)mysqli_error($conn)) : '';
    if ($ok_upd === false || $err !== '') {
      return ['ok' => false, 'msg' => '이체 SQL 실패' . ($err !== '' ? " ({$err})" : ''), 'before' => $전액];
    }
    $후행 = db_select("SELECT CAST(IFNULL(point, 0) AS CHAR) AS point_str FROM tb_member WHERE idx = {$받는_idx} LIMIT 1");
    $확인 = function_exists('냥_부호포함_정수문자열')
      ? 냥_부호포함_정수문자열($후행['point_str'] ?? '0')
      : (string)($후행['point_str'] ?? '0');
    if ($후액 !== null && preg_match('/^-?\d+$/', (string)$후액) && function_exists('bccomp') && bccomp($확인, (string)$후액, 0) !== 0) {
      return [
        'ok' => false,
        'msg' => "이체 후 잔액 불일치 (기대 {$후액} / 실제 {$확인})",
        'before' => $전액,
        'after' => $확인,
      ];
    }
    return [
      'ok' => true,
      'idx' => $받는_idx,
      'name' => (string)$받는친구['name'],
      'abs' => $abs양,
      'sign' => $sign,
      'before' => $전액,
      'after' => $확인,
    ];
  }
}

/** tb_member.게임포기 / 게임포기일시 — 0=플레이중 · 1=포기(바로가기 숨김) */
if (!function_exists('tb_member_게임포기_컬럼_보장')) {
  function tb_member_게임포기_컬럼_보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $col = @db_select("SHOW COLUMNS FROM tb_member LIKE '게임포기'");
    if (empty($col['Field'])) {
      @db_query("ALTER TABLE tb_member ADD COLUMN `게임포기` TINYINT NOT NULL DEFAULT 0 COMMENT '게임포기 플래그(0=정상 1=포기)'");
    }
    $colAt = @db_select("SHOW COLUMNS FROM tb_member LIKE '게임포기일시'");
    if (empty($colAt['Field'])) {
      @db_query("ALTER TABLE tb_member ADD COLUMN `게임포기일시` DATETIME DEFAULT NULL COMMENT '게임포기 시각(시작 쿨다운 기준)' AFTER `게임포기`");
    }
  }
}

/** 게임포기 쿨다운(일) — 일반 회원 */
if (!defined('게임포기_쿨다운일')) {
  define('게임포기_쿨다운일', 3);
}

if (!function_exists('게임포기_닉정규화')) {
  function 게임포기_닉정규화($nick): string {
    $nick = trim((string)$nick);
    if ($nick === '') {
      return '';
    }
    if (function_exists('getTwoCharNick')) {
      $p = getTwoCharNick($nick);
      if ($p !== '') {
        return $p;
      }
    }
    return $nick;
  }
}

if (!function_exists('게임포기_관리자인가')) {
  /** tb_member.admin=1 */
  function 게임포기_관리자인가($nick): bool {
    $nick = 게임포기_닉정규화($nick);
    if ($nick === '') {
      return false;
    }
    $esc = addslashes($nick);
    $row = @db_select("SELECT IFNULL(admin, 0) AS admin FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    if (!empty($row) && (int)($row['admin'] ?? 0) === 1) {
      return true;
    }
    // 전역 $관리자 목록 보조
    global $관리자;
    if (is_array($관리자) && in_array($nick, $관리자, true)) {
      return true;
    }
    return false;
  }
}

if (!function_exists('게임포기_상태')) {
  /**
   * @return array{quit:bool,quit_at:?string,can_start:bool,remain_sec:int,until:?string,is_admin:bool,lock_days:int,msg:string}
   */
  function 게임포기_상태($nick): array {
    if (function_exists('tb_member_게임포기_컬럼_보장')) {
      tb_member_게임포기_컬럼_보장();
    }
    $nick = 게임포기_닉정규화($nick);
    $lockDays = (int)게임포기_쿨다운일;
    $empty = [
      'quit' => false,
      'quit_at' => null,
      'can_start' => true,
      'remain_sec' => 0,
      'until' => null,
      'is_admin' => false,
      'lock_days' => $lockDays,
      'msg' => '',
    ];
    if ($nick === '') {
      return $empty;
    }
    $isAdmin = 게임포기_관리자인가($nick);
    $esc = addslashes($nick);
    $row = @db_select("
      SELECT IFNULL(게임포기, 0) AS q,
             게임포기일시 AS qa
      FROM tb_member
      WHERE name = '{$esc}'
      LIMIT 1
    ");
    $quit = (int)($row['q'] ?? 0) === 1;
    $quitAt = trim((string)($row['qa'] ?? ''));
    if ($quitAt === '' || $quitAt === '0000-00-00 00:00:00') {
      $quitAt = null;
    }
    $remain = 0;
    $until = null;
    $canStart = true;
    $msg = '';
    if ($quit) {
      if ($isAdmin) {
        $canStart = true;
        $msg = '관리자는 제한 없이 게임시작할 수 있어요.';
      } elseif ($quitAt !== null) {
        $untilTs = strtotime($quitAt . ' +' . $lockDays . ' days');
        if ($untilTs === false) {
          $untilTs = time();
        }
        $until = date('Y-m-d H:i:s', $untilTs);
        $remain = max(0, $untilTs - time());
        $canStart = ($remain <= 0);
        if (!$canStart) {
          $d = (int)floor($remain / 86400);
          $h = (int)floor(($remain % 86400) / 3600);
          $m = (int)floor(($remain % 3600) / 60);
          $parts = [];
          if ($d > 0) {
            $parts[] = $d . '일';
          }
          if ($h > 0 || $d > 0) {
            $parts[] = $h . '시간';
          }
          $parts[] = $m . '분';
          $msg = '게임시작까지 ' . implode(' ', $parts) . ' 남았어요.';
        } else {
          $msg = '쿨다운이 끝났어요. 게임시작을 눌러 주세요.';
        }
      } else {
        // 일시 없으면 즉시 시작 허용
        $canStart = true;
        $msg = '게임시작을 눌러 다시 이용할 수 있어요.';
      }
    }
    return [
      'quit' => $quit,
      'quit_at' => $quitAt,
      'can_start' => $canStart,
      'remain_sec' => $remain,
      'until' => $until,
      'is_admin' => $isAdmin,
      'lock_days' => $lockDays,
      'msg' => $msg,
    ];
  }
}

if (!function_exists('게임포기_실행')) {
  /** @return array{ok:bool,msg:string,state?:array} */
  function 게임포기_실행($nick): array {
    if (function_exists('tb_member_게임포기_컬럼_보장')) {
      tb_member_게임포기_컬럼_보장();
    }
    $nick = 게임포기_닉정규화($nick);
    if ($nick === '') {
      return ['ok' => false, 'msg' => '닉네임을 확인할 수 없어요.'];
    }
    $st = 게임포기_상태($nick);
    if (!empty($st['quit'])) {
      return ['ok' => false, 'msg' => '이미 게임포기 상태예요.', 'state' => $st];
    }
    $esc = addslashes($nick);
    db_query("UPDATE tb_member SET `게임포기` = 1, `게임포기일시` = NOW() WHERE name = '{$esc}' LIMIT 1");
    $st2 = 게임포기_상태($nick);
    $days = (int)($st2['lock_days'] ?? 게임포기_쿨다운일);
    if (!empty($st2['is_admin'])) {
      $msg = "게임포기 했어요.\n관리자는 언제든 게임시작할 수 있어요.";
    } else {
      $msg = "게임포기 했어요.\n앞으로 {$days}일간 게임시작을 할 수 없어요.\n(홀짝·냥카라·채굴·강화·보스·마켓·금고·마피아 숨김)";
    }
    if (function_exists('지급로그')) {
      지급로그('게임포기', $nick, !empty($st2['is_admin']) ? '관리자' : '일반', 0, 1);
    }
    return ['ok' => true, 'msg' => $msg, 'state' => $st2];
  }
}

if (!function_exists('게임포기_시작실행')) {
  /** @return array{ok:bool,msg:string,state?:array} */
  function 게임포기_시작실행($nick): array {
    if (function_exists('tb_member_게임포기_컬럼_보장')) {
      tb_member_게임포기_컬럼_보장();
    }
    $nick = 게임포기_닉정규화($nick);
    if ($nick === '') {
      return ['ok' => false, 'msg' => '닉네임을 확인할 수 없어요.'];
    }
    $st = 게임포기_상태($nick);
    if (empty($st['quit'])) {
      return ['ok' => false, 'msg' => '이미 게임 이용 중이에요.', 'state' => $st];
    }
    if (empty($st['can_start'])) {
      return ['ok' => false, 'msg' => (string)($st['msg'] ?: '아직 게임시작할 수 없어요.'), 'state' => $st];
    }
    $esc = addslashes($nick);
    db_query("UPDATE tb_member SET `게임포기` = 0 WHERE name = '{$esc}' LIMIT 1");
    $st2 = 게임포기_상태($nick);
    if (function_exists('지급로그')) {
      지급로그('게임시작', $nick, !empty($st['is_admin']) ? '관리자' : '일반', 0, 0);
    }
    return ['ok' => true, 'msg' => '게임을 다시 시작했어요. 바로가기가 복구됩니다.', 'state' => $st2];
  }
}

if (!function_exists('게임포기_바로가기숨김인가')) {
  function 게임포기_바로가기숨김인가($nick): bool {
    $st = 게임포기_상태($nick);
    return !empty($st['quit']);
  }
}

if (!function_exists('게임포기_숨김라벨목록')) {
  /** @return list<string> */
  function 게임포기_숨김라벨목록(): array {
    return ['홀짝', '냥카라', '채굴', '강화', '보스', '마켓', '개인금고', '마피아', '부루마블'];
  }
}

/** tb_member.point 를 DECIMAL(65,0)으로 확장 (천경·해 저장) — 1회 */
if (!function_exists('tb_member_point_컬럼_보장')) {
  function tb_member_point_컬럼_보장() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $col = @db_select("SHOW COLUMNS FROM tb_member LIKE 'point'");
    $type = strtolower((string)($col['Type'] ?? ''));
    if ($type === '') {
      return;
    }
    $need = false;
    // 신불(마이너스) 보존 — UNSIGNED 제거 필수
    if (strpos($type, 'unsigned') !== false) {
      $need = true;
    }
    if (strpos($type, 'decimal') === false) {
      $need = true;
    } elseif (preg_match('/decimal\((\d+)/', $type, $m) && (int)$m[1] < 65) {
      $need = true;
    }
    if ($need) {
      @db_query("ALTER TABLE tb_member MODIFY COLUMN `point` DECIMAL(65,0) NOT NULL DEFAULT 0 COMMENT '게임냥(신불 마이너스 허용)'");
    }
  }
}

/** tb_loan — point/times 확장 + target_nick(자숙 대상) */
if (!function_exists('tb_loan_컬럼_보장')) {
  function tb_loan_컬럼_보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $exists = @db_select("SHOW TABLES LIKE 'tb_loan'");
    if (empty($exists)) {
      return;
    }
    $pointCol = @db_select("SHOW COLUMNS FROM tb_loan LIKE 'point'");
    $pointType = strtolower((string)($pointCol['Type'] ?? ''));
    if ($pointType !== '') {
      $needPoint = (strpos($pointType, 'decimal') === false)
        || (preg_match('/decimal\((\d+)/', $pointType, $m) && (int)$m[1] < 65);
      if ($needPoint) {
        @db_query("ALTER TABLE tb_loan MODIFY COLUMN `point` DECIMAL(65,0) NOT NULL DEFAULT 0 COMMENT '모금냥'");
      }
    }
    $timesCol = @db_select("SHOW COLUMNS FROM tb_loan LIKE 'times'");
    $timesType = strtolower((string)($timesCol['Type'] ?? ''));
    if ($timesType !== '' && strpos($timesType, 'bigint') === false && strpos($timesType, 'decimal') === false) {
      @db_query("ALTER TABLE tb_loan MODIFY COLUMN `times` BIGINT NOT NULL DEFAULT 0 COMMENT '단축초'");
    }
    $tgtCol = @db_select("SHOW COLUMNS FROM tb_loan LIKE 'target_nick'");
    if (empty($tgtCol['Field'])) {
      @db_query("ALTER TABLE tb_loan ADD COLUMN `target_nick` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '자숙 대상 닉(모금 받는 사람)' AFTER `nick`");
    }
  }
}

/**
 * tb_loan 기록 — nick=모금자, target_nick=자숙대상
 * (동일 모금자+대상 쌍은 누적)
 */
if (!function_exists('tb_loan_모금기록')) {
  function tb_loan_모금기록(string $모금자, string $대상닉, $금액, int $seconds): void {
    tb_loan_컬럼_보장();
    $모금자 = trim($모금자);
    $대상닉 = trim($대상닉);
    if ($모금자 === '') {
      return;
    }
    $금액_sql = function_exists('냥_SQL정수')
      ? 냥_SQL정수($금액)
      : preg_replace('/\D+/', '', (string)$금액);
    if ($금액_sql === '' || $금액_sql === '0') {
      return;
    }
    $seconds = max(0, (int)$seconds);
    $모금_esc = addslashes($모금자);
    $대상_esc = addslashes($대상닉);
    $기존 = db_select("
      SELECT nick
      FROM tb_loan
      WHERE nick = '{$모금_esc}'
        AND target_nick = '{$대상_esc}'
      LIMIT 1
    ");
    if (empty($기존['nick'])) {
      db_query("
        INSERT INTO tb_loan
        SET nick = '{$모금_esc}',
            target_nick = '{$대상_esc}',
            point = {$금액_sql},
            times = {$seconds},
            regdate = NOW()
      ");
    } else {
      db_query("
        UPDATE tb_loan
        SET point = point + {$금액_sql},
            times = times + {$seconds},
            regdate = NOW()
        WHERE nick = '{$모금_esc}'
          AND target_nick = '{$대상_esc}'
        LIMIT 1
      ");
    }
  }
}

/** 자숙 대상(가니 등)에게 달린 모금 loan 전부 삭제 */
if (!function_exists('tb_loan_자숙대상_삭제')) {
  function tb_loan_자숙대상_삭제(string $대상닉): int {
    $대상닉 = trim($대상닉);
    if ($대상닉 === '') {
      return 0;
    }
    if (function_exists('tb_loan_컬럼_보장')) {
      tb_loan_컬럼_보장();
    }
    $esc = addslashes($대상닉);
    $cntRow = @db_select("SELECT COUNT(*) AS c FROM tb_loan WHERE target_nick = '{$esc}'");
    $cnt = (int)($cntRow['c'] ?? 0);
    if ($cnt > 0) {
      db_query("DELETE FROM tb_loan WHERE target_nick = '{$esc}'");
    }
    return $cnt;
  }
}

/**
 * 만료된 자숙(일방·공커) 정리 + 해당 대상 모금 loan 삭제
 * @return array{self:int, loan:int}
 */
if (!function_exists('tb_self_만료정리_모금포함')) {
  function tb_self_만료정리_모금포함(): array {
    $loanDel = 0;
    $selfDel = 0;
    $rs = @db_query("
      SELECT DISTINCT nick
      FROM tb_self
      WHERE status IN ('일방', '공커')
        AND enddate <= NOW()
        AND IFNULL(nick, '') <> ''
    ");
    $nicks = [];
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $n = trim((string)($row['nick'] ?? ''));
        if ($n !== '') {
          $nicks[$n] = true;
        }
      }
    }
    foreach (array_keys($nicks) as $nick) {
      // 아직 진행 중 일방/공커가 남아 있으면 loan 유지
      $esc = addslashes($nick);
      $활성 = @db_select("
        SELECT COUNT(*) AS c
        FROM tb_self
        WHERE nick = '{$esc}'
          AND status IN ('일방', '공커')
          AND enddate > NOW()
      ");
      if ((int)($활성['c'] ?? 0) === 0) {
        $loanDel += tb_loan_자숙대상_삭제($nick);
      }
    }
    $before = @db_select("SELECT COUNT(*) AS c FROM tb_self WHERE enddate <= NOW()");
    $selfDel = (int)($before['c'] ?? 0);
    if ($selfDel > 0) {
      db_query("DELETE FROM tb_self WHERE enddate <= NOW()");
    }
    return ['self' => $selfDel, 'loan' => $loanDel];
  }
}

/**
 * tb_self 1건 삭제. 해당 닉에 진행중 일방·공커가 더 없으면 loan도 삭제
 */
if (!function_exists('tb_self_건삭제_모금정리')) {
  function tb_self_건삭제_모금정리(int $idx): bool {
    if ($idx <= 0) {
      return false;
    }
    $행 = @db_select("SELECT nick, status FROM tb_self WHERE idx = {$idx} LIMIT 1");
    if (empty($행['nick'])) {
      return false;
    }
    $nick = trim((string)$행['nick']);
    $status = trim((string)($행['status'] ?? ''));
    db_query("DELETE FROM tb_self WHERE idx = {$idx} LIMIT 1");
    if ($nick !== '' && ($status === '일방' || $status === '공커')) {
      $esc = addslashes($nick);
      $활성 = @db_select("
        SELECT COUNT(*) AS c
        FROM tb_self
        WHERE nick = '{$esc}'
          AND status IN ('일방', '공커')
          AND enddate > NOW()
      ");
      if ((int)($활성['c'] ?? 0) === 0) {
        tb_loan_자숙대상_삭제($nick);
      }
    }
    return true;
  }
}

/** config.tax(금고) 를 DECIMAL(65,0)으로 확장 — BIGINT(~922경) 상한 돌파 */
if (!function_exists('config_tax_컬럼_보장')) {
  function config_tax_컬럼_보장() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $col = @db_select("SHOW COLUMNS FROM config LIKE 'tax'");
    $type = strtolower((string)($col['Type'] ?? ''));
    if ($type === '') {
      return;
    }
    $need = false;
    if (strpos($type, 'decimal') === false) {
      $need = true;
    } elseif (preg_match('/decimal\((\d+)/', $type, $m) && (int)$m[1] < 65) {
      $need = true;
    }
    if ($need) {
      @db_query("ALTER TABLE config MODIFY COLUMN `tax` DECIMAL(65,0) NOT NULL DEFAULT 0 COMMENT '금고(냥)'");
    }
  }
}

/** tb_sadari.amount 를 DECIMAL(65,0)으로 확장 — BIGINT(~922경) 상한 돌파 */
if (!function_exists('tb_sadari_amount_컬럼_보장')) {
  function tb_sadari_amount_컬럼_보장() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $col = @db_select("SHOW COLUMNS FROM tb_sadari LIKE 'amount'");
    $type = strtolower((string)($col['Type'] ?? ''));
    if ($type === '') {
      return;
    }
    $need = false;
    if (strpos($type, 'decimal') === false) {
      $need = true;
    } elseif (preg_match('/decimal\((\d+)/', $type, $m) && (int)$m[1] < 65) {
      $need = true;
    }
    if ($need) {
      @db_query("ALTER TABLE tb_sadari MODIFY COLUMN `amount` DECIMAL(65,0) NOT NULL DEFAULT 0 COMMENT '사다리 지급액'");
    }
  }
}

/**
 * .사다리 입력 가능 여부 — 매일 23시 이후 · 하루 1회
 * @return array{ok:bool,msg:string}
 */
if (!function_exists('사다리_입력가능검사')) {
  function 사다리_입력가능검사(): array {
    $현재시 = (int)date('H');
    if ($현재시 < 23) {
      return [
        'ok' => false,
        'msg' => "❌ .사다리는 매일 밤 11시(23시) 이후부터\n하루 1회만 입력할 수 있어요.",
      ];
    }
    $오늘 = date('Y-m-d');
    $이미 = @db_select("
      SELECT idx
      FROM tb_point_log
      WHERE status = '사다리시작'
        AND DATE(regdate) = '{$오늘}'
      LIMIT 1
    ");
    if (!empty($이미['idx'])) {
      return [
        'ok' => false,
        'msg' => "❌ 오늘은 이미 사다리를 진행했어요.\n내일 밤 11시 이후에 다시 가능합니다.",
      ];
    }
    return ['ok' => true, 'msg' => ''];
  }
}

/** 오늘 사다리 시작 기록 (하루 1회 잠금용) */
if (!function_exists('사다리_시작기록')) {
  function 사다리_시작기록(string $닉): void {
    if (function_exists('지급로그')) {
      지급로그('사다리시작', $닉, '', 0, 0);
    }
  }
}

/**
 * 금고 잔액 조회 — (int)/float 금지, mysqli 과학적표기 차단
 * @return string 부호 없는 정수 문자열
 */
if (!function_exists('금고_잔액_조회')) {
  function 금고_잔액_조회(): string {
    if (function_exists('config_tax_컬럼_보장')) {
      config_tax_컬럼_보장();
    }
    // CONCAT('N', ...) — CAST CHAR 가 native int 로 바뀌는 드라이버 대비
    $row = @db_select("SELECT CONCAT('N', CAST(IFNULL(tax, 0) AS CHAR)) AS tax FROM config LIMIT 1");
    $raw = (string)($row['tax'] ?? 'N0');
    if (isset($raw[0]) && ($raw[0] === 'N' || $raw[0] === 'n')) {
      $raw = substr($raw, 1);
    }
    return function_exists('냥_정수문자열') ? 냥_정수문자열($raw) : (ltrim(preg_replace('/[^\d]/', '', $raw), '0') ?: '0');
  }
}

/** 금고 금액 표시 — number_format/(int) 금지 */
if (!function_exists('금고_금액표시')) {
  function 금고_금액표시($금액, $단위접미 = null): string {
    global $단위;
    $u = ($단위접미 !== null && $단위접미 !== '') ? (string)$단위접미
      : ((isset($단위) && (string)$단위 !== '') ? (string)$단위 : '냥');
    $s = function_exists('냥_정수문자열') ? 냥_정수문자열($금액) : (ltrim(preg_replace('/[^\d]/', '', (string)$금액), '0') ?: '0');
    if (function_exists('게임냥_안전표시')) {
      return 게임냥_안전표시($s, $u);
    }
    if (function_exists('랭킹_게임냥표시')) {
      return 랭킹_게임냥표시($s, $u);
    }
    if (function_exists('냥축약표시')) {
      return 냥축약표시($s, $u);
    }
    return $s . $u;
  }
}

/**
 * 금지단어 사용 누적 통계 — 닉×단어별 횟수
 * (누가 어떤 단어를 제일 많이 쓰는지 집계용)
 */
if (!function_exists('금지단어_통계_테이블_보장')) {
  function 금지단어_통계_테이블_보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    @db_query("CREATE TABLE IF NOT EXISTS `tb_forbidden_word_stat` (
      `idx` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `nick` varchar(30) NOT NULL DEFAULT '',
      `word` varchar(64) NOT NULL DEFAULT '',
      `cnt` int(11) unsigned NOT NULL DEFAULT 0,
      `first_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `last_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`idx`),
      UNIQUE KEY `uk_nick_word` (`nick`, `word`),
      KEY `idx_cnt` (`cnt`),
      KEY `idx_word_cnt` (`word`, `cnt`),
      KEY `idx_nick_cnt` (`nick`, `cnt`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
  }
}

if (!function_exists('금지단어_통계_누적')) {
  /** 닉·단어 1회 누적 (대소문자 통일) */
  function 금지단어_통계_누적(string $닉, string $단어): void {
    $닉 = trim($닉);
    $단어 = trim($단어);
    if ($닉 === '' || $단어 === '') {
      return;
    }
    금지단어_통계_테이블_보장();
    if (function_exists('mb_strtolower')) {
      $단어 = mb_strtolower($단어, 'UTF-8');
    } else {
      $단어 = strtolower($단어);
    }
    if (function_exists('mb_substr')) {
      $단어 = mb_substr($단어, 0, 64, 'UTF-8');
    } else {
      $단어 = substr($단어, 0, 64);
    }
    if (function_exists('mb_substr')) {
      $닉 = mb_substr($닉, 0, 30, 'UTF-8');
    } else {
      $닉 = substr($닉, 0, 30);
    }
    $닉_esc = addslashes($닉);
    $단어_esc = addslashes($단어);
    @db_query("
      INSERT INTO `tb_forbidden_word_stat` (`nick`, `word`, `cnt`, `first_at`, `last_at`)
      VALUES ('{$닉_esc}', '{$단어_esc}', 1, NOW(), NOW())
      ON DUPLICATE KEY UPDATE `cnt` = `cnt` + 1, `last_at` = NOW()
    ");
  }
}

if (!function_exists('금지단어_통계_랭킹문구')) {
  /**
   * `.금지어` 응답 — 닉×단어 / 닉합계 / 단어합계 랭킹
   * @param int $limit 각 섹션 상위 N
   */
  function 금지단어_통계_랭킹문구(int $limit = 15): string {
    $limit = max(1, min(50, $limit));
    금지단어_통계_테이블_보장();

    $표시단어 = static function (string $w): string {
      if (function_exists('금지단어_표시용')) {
        return 금지단어_표시용($w);
      }
      return preg_replace('/(.)/u', '$1·', $w);
    };

    $쌍행 = [];
    $rs = @db_query("
      SELECT nick, word, cnt
      FROM tb_forbidden_word_stat
      WHERE cnt > 0
      ORDER BY cnt DESC, last_at DESC, nick ASC, word ASC
      LIMIT {$limit}
    ");
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $쌍행[] = $row;
      }
    }
    if ($쌍행 === []) {
      return "🚫 금지어 랭킹\n\n아직 기록이 없어요.";
    }

    $pad = str_repeat(' ', 200);
    $msg = "🚫 금지어 랭킹\n{$pad}\n";

    $msg .= "【닉×단어 TOP{$limit}】\n";
    $rank = 0;
    foreach ($쌍행 as $row) {
      $rank++;
      $nick = trim((string)($row['nick'] ?? ''));
      $word = trim((string)($row['word'] ?? ''));
      $cnt = (int)($row['cnt'] ?? 0);
      $msg .= "{$rank}. {$nick} · {$표시단어($word)} — {$cnt}회\n";
    }

    $닉행 = [];
    $rs2 = @db_query("
      SELECT nick, SUM(cnt) AS total
      FROM tb_forbidden_word_stat
      WHERE cnt > 0
      GROUP BY nick
      ORDER BY total DESC, nick ASC
      LIMIT {$limit}
    ");
    if ($rs2) {
      while ($row = db_fetch($rs2)) {
        $닉행[] = $row;
      }
    }
    if ($닉행 !== []) {
      $msg .= "\n【닉 합계 TOP{$limit}】\n";
      $rank = 0;
      foreach ($닉행 as $row) {
        $rank++;
        $nick = trim((string)($row['nick'] ?? ''));
        $total = (int)($row['total'] ?? 0);
        $msg .= "{$rank}. {$nick} — {$total}회\n";
      }
    }

    $단어행 = [];
    $rs3 = @db_query("
      SELECT word, SUM(cnt) AS total
      FROM tb_forbidden_word_stat
      WHERE cnt > 0
      GROUP BY word
      ORDER BY total DESC, word ASC
      LIMIT {$limit}
    ");
    if ($rs3) {
      while ($row = db_fetch($rs3)) {
        $단어행[] = $row;
      }
    }
    if ($단어행 !== []) {
      $msg .= "\n【단어 TOP{$limit}】\n";
      $rank = 0;
      foreach ($단어행 as $row) {
        $rank++;
        $word = trim((string)($row['word'] ?? ''));
        $total = (int)($row['total'] ?? 0);
        $msg .= "{$rank}. {$표시단어($word)} — {$total}회\n";
      }
    }

    return rtrim($msg);
  }
}

/**
 * 채팅 전송용 금지단어 표기 — 글자 사이에 특수문자 여러 개 삽입해 플랫폼 필터 회피
 * (검출·로그는 원문 유지, 사용자에게 보이는 문구만 사용)
 */
if (!function_exists('금지단어_표시용')) {
  function 금지단어_표시용(string $단어): string {
    $단어 = trim($단어);
    if ($단어 === '') {
      return '금··지··단··어';
    }
    $chars = preg_split('//u', $단어, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($chars) || $chars === []) {
      return '‹·' . $단어 . '·›';
    }
    // 다양한 구분자 (필터가 한두 종류만 제거해도 연속 문자열이 안 되게)
    $seps = [
      '·', '･', '‧', '∙', '⋅', '•', '․', '･',
      '﹡', '∗', '⋆', '✦', '✧', '✩',
      '･', '〜', '~', '˜', '˖', '﹢',
      '｜', '|', '¦', '┊', '╎',
      '｡', '°', '˚', '∘', '○',
      '‹', '›', '〈', '〉', '〔', '〕',
      '＊', '*', '＊', '＃', '#',
      '＿', '_', '－', '-', '‒',
      '※', '◎', '◇', '◆', '□',
    ];
    $sepCount = count($seps);
    $out = ['‹'];
    $n = count($chars);
    foreach ($chars as $i => $ch) {
      // 글자 양옆에도 짧게 감싸기
      $wrapL = $seps[($i * 3) % $sepCount];
      $wrapR = $seps[($i * 3 + 1) % $sepCount];
      if ($ch === ' ' || $ch === "\t") {
        $out[] = $wrapL . '␣' . $wrapR;
      } else {
        $out[] = $wrapL . $ch . $wrapR;
      }
      if ($i < $n - 1) {
        // 글자 사이에 구분자 3개
        $out[] = $seps[($i * 5) % $sepCount];
        $out[] = $seps[($i * 5 + 2) % $sepCount];
        $out[] = $seps[($i * 5 + 4) % $sepCount];
      }
    }
    $out[] = '›';
    return implode('', $out);
  }
}

/**
 * 금지단어 벌금: 전체 게임냥의 0.01% 차감 → config.tax(금고) 적립
 * - 게임냥 부족/없음: 본방냥(newpoint) 10% 강제 스왑 후 차감
 * - 그래도 부족하거나 둘 다 0: point 마이너스 차감 + 🆘신불자 부여
 * @return array{ok:bool,msg:string,penalty?:string,swapped?:bool,신용불량?:bool}
 */
if (!function_exists('금지단어_벌금처리')) {
  function 금지단어_벌금처리(string $닉, string $금칙단어 = ''): array {
    global $단위, $conn;
    $닉 = trim($닉);
    $단위표 = (isset($단위) && (string)$단위 !== '') ? (string)$단위 : '냥';
    if ($닉 === '') {
      return ['ok' => false, 'msg' => '❌ 닉네임을 확인할 수 없어요.'];
    }
    // 닉×단어 누적 (벌금 성패와 무관하게 검출 시점 기록)
    if (function_exists('금지단어_통계_누적')) {
      금지단어_통계_누적($닉, $금칙단어);
    }
    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }
    if (function_exists('config_tax_컬럼_보장')) {
      config_tax_컬럼_보장();
    }

    $벌금 = '1';
    if (function_exists('전체냥기준금액')) {
      $벌금 = 냥_정수문자열(전체냥기준금액(0.01));
    }
    if ($벌금 === '' || $벌금 === '0') {
      $벌금 = '1';
    }
    $벌금_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($벌금) : preg_replace('/[^\d]/', '', $벌금);
    if ($벌금_sql === '' || $벌금_sql === '0') {
      $벌금_sql = '1';
      $벌금 = '1';
    }

    $닉_esc = addslashes($닉);
    $행 = db_select("
      SELECT idx,
             CAST(IFNULL(point, 0) AS CHAR) AS point,
             CAST(IFNULL(newpoint, 0) AS CHAR) AS newpoint,
             IFNULL(title, '') AS title
      FROM tb_member
      WHERE name = '{$닉_esc}'
      LIMIT 1
    ");
    if (empty($행['idx'])) {
      return ['ok' => false, 'msg' => '❌ 회원을 찾을 수 없어요.'];
    }
    $midx = (int)$행['idx'];
    $point = 냥_정수문자열($행['point'] ?? 0);
    // newpoint는 소수 가능
    $newpoint = round((float)($행['newpoint'] ?? 0), 1);

    $표시 = function ($amt) use ($단위표) {
      if (function_exists('금고_금액표시')) {
        return 금고_금액표시($amt, $단위표);
      }
      if (function_exists('게임냥_안전표시')) {
        return 게임냥_안전표시($amt, $단위표);
      }
      return (function_exists('냥축약표시') ? 냥축약표시($amt, $단위표) : ($amt . $단위표));
    };

    $스왑함 = false;
    $스왑문구 = '';
    $원포인트표시 = trim((string)($행['point'] ?? '0'));
    $보유부족 = false;
    if (isset($원포인트표시[0]) && $원포인트표시[0] === '-') {
      $보유부족 = true;
    } elseif (function_exists('bccomp')) {
      $보유부족 = (bccomp($point, $벌금, 0) < 0);
    } else {
      $보유부족 = (strlen($point) < strlen($벌금)) || (strlen($point) === strlen($벌금) && $point < $벌금);
    }

    // 게임냥 부족/없음 → 본방냥 10% 강제 스왑
    if ($보유부족 && $newpoint >= 0.1) {
      $swapPath = __DIR__ . '/game/swap.inc.php';
      if (is_file($swapPath)) {
        require_once $swapPath;
      }
      if (function_exists('스왑_견적계산')) {
        $스왑액 = round($newpoint * 0.1, 1);
        if ($스왑액 >= 0.1) {
          $견적 = 스왑_견적계산('np2pt', $스왑액, false, null, false);
          if (!empty($견적['ok'])) {
            $차감_np = (float)($견적['차감_np'] ?? 0);
            $지급_pt = function_exists('스왑_정수문자열')
              ? 스왑_정수문자열($견적['지급_pt'] ?? 0)
              : (냥_정수문자열($견적['지급_pt'] ?? 0));
            if ($차감_np >= 0.1 && $지급_pt !== '0' && $newpoint + 1e-9 >= $차감_np) {
              if (function_exists('스왑_point_컬럼_보장')) {
                스왑_point_컬럼_보장();
              }
              $ptSql = function_exists('냥_SQL정수') ? 냥_SQL정수($지급_pt) : $지급_pt;
              db_query("
                UPDATE tb_member
                SET newpoint = newpoint - {$차감_np},
                    point = point + {$ptSql}
                WHERE idx = {$midx} AND newpoint >= {$차감_np}
                LIMIT 1
              ");
              $aff = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 1;
              if ($aff > 0) {
                $스왑함 = true;
                $np표시 = function_exists('newpoint표시') ? newpoint표시($차감_np) : (number_format($차감_np, 1) . $단위표);
                $pt표시 = $표시($지급_pt);
                $스왑문구 = "본방냥 10% 강제스왑 -{$np표시} → +{$pt표시}";
                $재조회 = db_select("SELECT CAST(IFNULL(point, 0) AS CHAR) AS point FROM tb_member WHERE idx = {$midx} LIMIT 1");
                $point = 냥_정수문자열($재조회['point'] ?? $point);
              }
            }
          }
        }
      }
    }

    // 벌금 전액 차감 (부족하면 마이너스 → 신불)
    db_query("UPDATE tb_member SET point = point - {$벌금_sql} WHERE idx = {$midx} LIMIT 1");
    db_query("UPDATE config SET tax = tax + {$벌금_sql}");

    $후행 = db_select("SELECT CAST(IFNULL(point, 0) AS CHAR) AS point, IFNULL(title, '') AS title FROM tb_member WHERE idx = {$midx} LIMIT 1");
    $후포인트 = trim((string)($후행['point'] ?? '0'));
    $신불 = (isset($후포인트[0]) && $후포인트[0] === '-');

    if ($신불) {
      $현재타이틀 = trim((string)($후행['title'] ?? ''));
      if ($현재타이틀 !== '🆘신불자') {
        db_query("
          UPDATE tb_member
          SET title = '🆘신불자',
              credit = 1,
              credit_debt_at = NOW(),
              credit_debt_entered_at = NOW(),
              credit_recovery_plus3_at = NULL,
              credit_debt_times = IFNULL(credit_debt_times, 0) + 1
          WHERE idx = {$midx}
          LIMIT 1
        ");
      } else {
        db_query("UPDATE tb_member SET credit = 1 WHERE idx = {$midx} LIMIT 1");
      }
    }

    if (function_exists('지급로그')) {
      지급로그('금지단어벌금', $닉, $금칙단어, 0, $벌금);
    }

    $벌금표시 = $표시($벌금);
    $금칙표기 = trim($금칙단어) !== ''
      ? ("' " . 금지단어_표시용($금칙단어) . " '")
      : '금·지·단·어';
    $msg = "금지 단어 {$금칙표기} 사용으로\n";
    $msg .= "{$닉}에게 전체 게임냥 0.01% 벌금 차감\n";
    $msg .= "차감 : {$벌금표시} → 금고 적립";
    if ($스왑함 && $스왑문구 !== '') {
      $msg .= "\n💱 {$스왑문구}";
    }
    if ($신불) {
      $msg .= "\n🆘 게임냥·본방냥 부족 → 신불자 처리 (마이너스 차감)";
    }

    return [
      'ok' => true,
      'msg' => $msg,
      'penalty' => $벌금,
      'penalty_disp' => $벌금표시,
      'swapped' => $스왑함,
      '신용불량' => $신불,
    ];
  }
}

/** `.선물`로 양도 가능한 아이템명 — 2글자 상점템 + 일방신청권·일방연장권·은총조각 (은총 본품 제외) */
if (!function_exists('선물_가능_아이템명')) {
  function 선물_가능_아이템명(string $itemName): bool {
    $itemName = trim($itemName);
    if ($itemName === '') {
      return false;
    }
    if ($itemName === '은총') {
      return false;
    }
    static $장문허용 = ['일방신청권', '일방연장권', '은총조각'];
    if (in_array($itemName, $장문허용, true)) {
      return true;
    }
    return mb_strlen($itemName, 'UTF-8') === 2;
  }
}

/**
 * .모금 명령 파싱 — 자숙자닉(필수) + 금액
 * @return array{nick: ?string, amount_text: string, need_nick?: bool}
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
  // .모금 10000 처럼 금액만 — 닉 필수 안내
  if (preg_match('/^\.모금\s+(\S+)\s*$/u', $trim, $m)) {
    return ['nick' => null, 'amount_text' => trim($m[1]), 'need_nick' => true];
  }
  return ['nick' => null, 'amount_text' => ''];
}

/**
 * .모금 도움말 — 자숙자 닉 필수 · 1초 단축 단가 포함
 */
if (!function_exists('모금_도움말_문구')) {
  function 모금_도움말_문구($최소모금 = null): string {
    if ($최소모금 === null && function_exists('전체냥기준금액')) {
      $최소모금 = 전체냥기준금액(0.01, true);
    }
    $최소Str = function_exists('냥_정수문자열')
      ? 냥_정수문자열($최소모금 ?? 1)
      : (ltrim(preg_replace('/[^\d]/', '', (string)($최소모금 ?? 1)), '0') ?: '1');
    $단가 = function_exists('모금_적용단가') ? (int)모금_적용단가(null) : 0;
    if ($단가 < 1 && function_exists('모금나눗값구하기')) {
      $단가 = (int)모금나눗값구하기();
    }
    $단가 = max(1, $단가);

    if (function_exists('bccomp') ? bccomp($최소Str, '1', 0) <= 0 : ((float)$최소Str <= 1)) {
      $최소Str = (string)$단가;
    }

    $fmt = static function ($금액) {
      return function_exists('냥축약표시')
        ? 냥축약표시($금액)
        : (number_format((float)$금액) . '냥');
    };
    $최소표시 = $fmt($최소Str);
    $단가표시 = $fmt($단가);

    return ".모금 가니 {$단가표시}
- '가니' 자숙만 단축 (닉 필수)

금액: 숫자 또는 만·억·조·경·천경·해 (예: 1조, 1천경, 1해, 3천억)
최소 모금: {$최소표시}
1초 단축: {$단가표시}";
  }
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

/** tb_member.newpoint 표시 (소수점 제외) — 큰 수도 콤마 유지 */
function newpoint표시($금액) {
  if (function_exists('냥_정수문자열') && function_exists('냥_숫자콤마')) {
    // 소수 본방냥: floor 후 표시
    if (is_float($금액) || (is_string($금액) && strpos($금액, '.') !== false)) {
      $금액 = floor((float)$금액);
    }
    return 냥_숫자콤마($금액);
  }
  $v = (int)floor((float)$금액);
  return number_format($v, 0, '.', ',');
}

/**
 * `.랭킹1` 보유냥(newpoint) 랭킹 문구
 * — SQL CAST + PHP bccomp 재정렬로 문자열/별칭 정렬 꼬임 방지
 *
 * @param int $limit 0이면 전체, 1+이면 상위 N
 * @param string $이모티콘 등수 뒤 이모지
 */
if (!function_exists('보유냥랭킹_문구')) {
  function 보유냥랭킹_문구(int $limit = 0, string $이모티콘 = ''): string {
    global $단위;
    $단위표 = isset($단위) ? (string)$단위 : '냥';
    $limit = max(0, $limit);

    $결과 = @db_query("
      SELECT name, level, title, point,
             CONCAT('N', CAST(FLOOR(CAST(IFNULL(newpoint, 0) AS DECIMAL(65,4))) AS CHAR)) AS np
      FROM tb_member
    ");
    $rows = [];
    if ($결과) {
      while ($row = db_fetch($결과)) {
        $raw = (string)($row['np'] ?? 'N0');
        if (isset($raw[0]) && ($raw[0] === 'N' || $raw[0] === 'n')) {
          $raw = substr($raw, 1);
        }
        $np = function_exists('냥_정수문자열')
          ? 냥_정수문자열($raw)
          : (ltrim(preg_replace('/[^\d]/', '', $raw), '0') ?: '0');
        $row['_np'] = $np;
        $rows[] = $row;
      }
    }

    usort($rows, static function ($a, $b) {
      $na = (string)($a['_np'] ?? '0');
      $nb = (string)($b['_np'] ?? '0');
      if (function_exists('bccomp')) {
        return bccomp($nb, $na, 0); // DESC
      }
      // 자릿수 먼저 비교 후 문자열 비교 (순수 숫자 문자열)
      $la = strlen($na);
      $lb = strlen($nb);
      if ($la !== $lb) {
        return $lb <=> $la;
      }
      return strcmp($nb, $na);
    });

    if ($limit > 0) {
      $rows = array_slice($rows, 0, $limit);
    }

    // 카톡 전체보기 유도: 상위 1~9명만 헤더 공백 패딩
    if ($limit > 0 && $limit < 10) {
      $msg = "✅ {$단위표} 보유랭킹\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
    } else {
      $msg = "✅ {$단위표} 보유랭킹\n\n";
    }

    $a = 1;
    foreach ($rows as $row) {
      $계급 = function_exists('계급') ? 계급($row['point'] ?? 0) : ['name' => ''];
      $title = trim((string)($row['title'] ?? ''));
      $호칭 = $title !== '' ? $title : (string)($계급['name'] ?? '');
      $보유냥 = newpoint표시($row['_np'] ?? 0);
      $msg .= $a . "등{$이모티콘} Lv " . (int)($row['level'] ?? 0) . " {$호칭} " . ($row['name'] ?? '') . " " . $보유냥 . "{$단위표}\n";
      $a++;
    }
    return $msg;
  }
}

/**
 * `.우리방` 표시용 — status=0 회원 본방·게임냥 실시간 합계 (스냅샷 미사용)
 * @return array{본방냥: float, 게임냥: string}
 */
if (!function_exists('우리방_실시간_총량')) {
  function 우리방_실시간_총량(): array {
    if (function_exists('시세기준_실시간합계')) {
      $live = 시세기준_실시간합계();
      $pt = function_exists('냥_정수문자열')
        ? 냥_정수문자열($live['게임냥'] ?? 0)
        : (string)($live['게임냥'] ?? '0');
    } else {
      $row = db_select("
        SELECT
          COALESCE(SUM(newpoint), 0) AS total_np,
          CONCAT('N', CAST(COALESCE(SUM(GREATEST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)), 0)), 0) AS CHAR)) AS total_pt
        FROM tb_member
        WHERE status = 0
      ");
      $live = ['본방냥' => (float)($row['total_np'] ?? 0)];
      $pt = 냥_금액원문_정규화($row['total_pt'] ?? 'N0');
    }
    return [
      '본방냥' => (float)($live['본방냥'] ?? 0),
      '게임냥' => 냥_금액원문_정규화($pt),
    ];
  }
}

/** `.우리방` 표시용 — 실시간 1보유냥당 게임냥 */
if (!function_exists('우리방_실시간_스왑율')) {
  function 우리방_실시간_스왑율($본방냥 = null, $게임냥 = null) {
    if ($본방냥 === null || $게임냥 === null) {
      $t = 우리방_실시간_총량();
      $본방냥 = $t['본방냥'];
      $게임냥 = $t['게임냥'];
    }
    $np = (float)$본방냥;
    $pt = function_exists('냥_정수문자열') ? 냥_정수문자열($게임냥) : (string)$게임냥;
    if ($np <= 0 || $pt === '0') {
      return '0';
    }
    if (function_exists('bcdiv')) {
      return bcdiv($pt, number_format($np, 4, '.', ''), 0);
    }
    return (string)max(0, (int)floor((float)$pt / $np));
  }
}

/** status=0 회원 newpoint 합계 — 실시간 합계 (주급·보급·신입 등) */
function 전체보유newpoint합계() {
  if (function_exists('우리방_실시간_총량')) {
    return (float)(우리방_실시간_총량()['본방냥'] ?? 0);
  }
  if (function_exists('시세기준_실시간합계')) {
    return (float)(시세기준_실시간합계()['본방냥'] ?? 0);
  }
  $row = db_select("SELECT COALESCE(SUM(newpoint), 0) AS total_np FROM tb_member WHERE status = 0");
  return (float)($row['total_np'] ?? 0);
}

/** 전체 newpoint 비율 계산 (소수점 버림) — 실시간 본방냥 합계 기준 */
function newpoint비율계산($비율) {
  return (int)floor(전체보유newpoint합계() * $비율);
}

/** 신입 지원금(해제 보상) — 실시간 본방냥 합계의 3% */
function 신입지원금_계산() {
  return newpoint비율계산(0.03);
}

/** 신입 담당 보상 — 실시간 본방냥 합계의 1% */
function 신입담당보상_계산() {
  return newpoint비율계산(0.01);
}

/** 신입 .색변 담당보상 회수 유예(초) — 이 시간 안 퇴사 시 담당자 보상 회수 */
if (!function_exists('신입담당보상_회수유예초')) {
  function 신입담당보상_회수유예초(): int {
    return 86400; // 24시간
  }
}

/** 조기퇴사 시 담당보상 회수 비율 (1.0=전액) — 기본 70%만 회수 */
if (!function_exists('신입담당보상_조기회수비율')) {
  function 신입담당보상_조기회수비율(): float {
    return 0.7;
  }
}

/** tb_member: 색변 시 담당자·담당보상·색변시각 저장 컬럼 */
if (!function_exists('신입담당보상_컬럼보장')) {
  function 신입담당보상_컬럼보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $cols = [
      '신입담당자' => "ALTER TABLE tb_member ADD COLUMN `신입담당자` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '.색변 시 담당 2글자닉'",
      '신입담당보상' => "ALTER TABLE tb_member ADD COLUMN `신입담당보상` DECIMAL(20,1) NOT NULL DEFAULT 0 COMMENT '.색변 시 담당자가 받은 본방냥'",
      '색변일시' => "ALTER TABLE tb_member ADD COLUMN `색변일시` DATETIME DEFAULT NULL COMMENT '.색변 완료 시각'",
    ];
    foreach ($cols as $name => $sql) {
      $c = @db_select("SHOW COLUMNS FROM tb_member LIKE '{$name}'");
      if (empty($c)) {
        @db_query($sql);
      }
    }
  }
}

/**
 * tb_member.색확정 — 신입 색변 대기 플래그
 * 0: 닉 생성 직후(색변 전) · 1: 기존멤버 / .색변 완료
 */
if (!function_exists('색확정_컬럼보장')) {
  function 색확정_컬럼보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $c = @db_select("SHOW COLUMNS FROM tb_member LIKE '색확정'");
    if (empty($c)) {
      @db_query("ALTER TABLE tb_member ADD COLUMN `색확정` TINYINT NOT NULL DEFAULT 1 COMMENT '0=신입색변대기 1=확정'");
      // 기존 전원 1, 아직 couple=2 신입만 0
      @db_query("UPDATE tb_member SET `색확정` = 1");
      @db_query("UPDATE tb_member SET `색확정` = 0 WHERE couple = 2 AND status != 1");
    }
  }
}

/**
 * tb_member.신입색순번 — 색표 만석 시 신입색(#1) 공유 순번 (1,2,3…)
 */
if (!function_exists('신입색순번_컬럼보장')) {
  function 신입색순번_컬럼보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $c = @db_select("SHOW COLUMNS FROM tb_member LIKE '신입색순번'");
    if (empty($c)) {
      @db_query("ALTER TABLE tb_member ADD COLUMN `신입색순번` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '신입색 공유 순번(0=미사용)'");
    }
  }
}

/** 다음에 부여할 신입색 순번 (1부터) */
if (!function_exists('신입색순번_다음번호')) {
  function 신입색순번_다음번호(): int {
    신입색순번_컬럼보장();
    $행 = @db_select("SELECT IFNULL(MAX(`신입색순번`), 0) AS mx FROM tb_member WHERE status != 1");
    return ((int)($행['mx'] ?? 0)) + 1;
  }
}

/**
 * 🌸/🤍 닉네임•키 공질 프로필 입력 → tb_member.content 저장 (본방·관리방 공통)
 * 최초 등록은 대리 가능, 수정은 본인·관리자만.
 * @return bool true면 이미 전송·처리 완료(호출부에서 exit)
 */
if (!function_exists('프로필_공질입력_처리')) {
  function 프로필_공질입력_처리($status, $두자리닉넴, $관리자 = null): bool {
    global $conn;
    $두자리닉넴 = trim((string)$두자리닉넴);
    if ($두자리닉넴 === '') {
      return false;
    }

    $프로필원본 = str_replace('\n', "\n", (string)$status);
    $프로필원본 = preg_replace('/\x{00A0}|\x{2000}-\x{200B}|\x{FEFF}/u', ' ', $프로필원본);
    $프로필원본 = str_replace('：', ':', $프로필원본);

    if (!preg_match('/닉네임\s*[•·・ㆍ]\s*키\s*:/u', $프로필원본)) {
      return false;
    }
    if (strpos($프로필원본, '🌸') === false && strpos($프로필원본, '🤍') === false) {
      return false;
    }

    $키값 = '';
    if (preg_match('/(?:🌸|🤍)?\s*닉네임\s*[•·・ㆍ]\s*키\s*:\s*([^\r\n🌸🤍]+)/u', $프로필원본, $키m)) {
      $키값 = trim($키m[1]);
    }
    if ($키값 === '' && preg_match('/(?:🌸|🤍)?\s*닉네임\s*[•·・ㆍ]\s*키\s*:\s*[\r\n]+\s*([^\r\n🌸🤍]+)/u', $프로필원본, $키m2)) {
      $키값 = trim($키m2[1]);
    }
    if ($키값 === '') {
      return false;
    }

    $프로필닉 = '';
    if (preg_match('/^\s*([가-힣]{2})/u', $키값, $닉m)) {
      $프로필닉 = $닉m[1];
    } elseif (function_exists('getTwoCharNick')) {
      $프로필닉 = getTwoCharNick($키값);
    }
    if ($프로필닉 === '') {
      return false;
    }

    $esc = function ($s) use ($conn) {
      $s = (string)$s;
      if (isset($conn) && $conn) {
        return mysqli_real_escape_string($conn, $s);
      }
      return addslashes($s);
    };

    $name_esc = $esc($프로필닉);
    $회원 = db_select("SELECT idx, IFNULL(welcome, 0) AS welcome, gender, content FROM tb_member WHERE name = '{$name_esc}' AND status = 0 LIMIT 1");
    if (empty($회원['idx'])) {
      return false;
    }

    $기존본문 = trim((string)($회원['content'] ?? ''));
    $수정요청 = ((int)$회원['welcome'] === 1) || $기존본문 !== '';
    $관리자여부 = in_array($두자리닉넴, (array)($관리자 ?? []), true);
    if ($수정요청 && $두자리닉넴 !== $프로필닉 && !$관리자여부) {
      echo 전송("❌ {$프로필닉} 프로필 수정은 본인만 가능해!");
      return true;
    }

    $본문 = str_replace('\n', "\n", trim((string)$status));
    if ($본문 !== '' && strpos($본문, "\n") === false && preg_match_all('/🌸[^🌸]+/u', $본문, $블록m)) {
      $본문 = implode("\n", array_map('trim', $블록m[0]));
    }

    $content_esc = $esc($본문);
    db_query("UPDATE tb_member SET content = '{$content_esc}' WHERE name = '{$name_esc}' AND status = 0");
    @db_query("INSERT INTO tb_member_profile SET name = '{$name_esc}', content = '{$content_esc}', regdate = NOW()");

    if ((int)$회원['welcome'] === 1) {
      echo 전송("{$프로필닉}!! 프로필 저장 완료 🌸");
      return true;
    }

    db_query("UPDATE tb_member SET welcome = 1 WHERE name = '{$name_esc}'");
    echo 전송("🎉 {$프로필닉} 공질 등록 완료 🌸\n\n이제 `.색표` 입력해서 색변도 해보자!");
    return true;
  }
}

/** 색변 대기 신입(색확정=0) 닉 1명 — 없으면 '' */
if (!function_exists('색확정_대기신입닉')) {
  function 색확정_대기신입닉(): string {
    색확정_컬럼보장();
    $행 = @db_select("SELECT name FROM tb_member WHERE `색확정` = 0 AND status != 1 ORDER BY regdate ASC, idx ASC LIMIT 1");
    return trim((string)($행['name'] ?? ''));
  }
}

/** .색변 성공 시 신입 회원에 담당 보상 정보 기록 */
if (!function_exists('신입담당보상_기록')) {
  function 신입담당보상_기록($신입닉, $담당닉, $담당보상): void {
    $신입닉 = trim((string)$신입닉);
    $담당닉 = trim((string)$담당닉);
    $담당보상 = round(max(0, (float)$담당보상), 1);
    if ($신입닉 === '' || $담당닉 === '') {
      return;
    }
    신입담당보상_컬럼보장();
    $신입_esc = addslashes($신입닉);
    $담당_esc = addslashes($담당닉);
    $보상_sql = number_format($담당보상, 1, '.', '');
    @db_query("
      UPDATE tb_member
      SET `신입담당자` = '{$담당_esc}',
          `신입담당보상` = {$보상_sql},
          `색변일시` = NOW()
      WHERE name = '{$신입_esc}'
      LIMIT 1
    ");
  }
}

/** config.신입담당 컬럼 보장 */
if (!function_exists('신입담당_config컬럼보장')) {
  function 신입담당_config컬럼보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $col = @db_select("SHOW COLUMNS FROM config LIKE '신입담당'");
    if (empty($col)) {
      @db_query("ALTER TABLE config ADD COLUMN `신입담당` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '신입 수령 담당 2글자닉'");
    }
  }
}

/**
 * .색변환 닉 번호 — 관리자 전용. 대상의 색변 아이템 1개 차감 후 색번호 변경.
 * 매칭·처리 시 전송 후 exit, 미매칭 시 false.
 */
if (!function_exists('색변환_명령_처리')) {
  function 색변환_명령_처리($status, $두자리닉넴, $관리자 = []) {
    $statusTrim = trim((string)$status);
    if (!preg_match('/^\.색변환(?:\s|$)/u', $statusTrim)) {
      return false;
    }
    if (!in_array((string)$두자리닉넴, (array)$관리자, true)) {
      echo 전송('❌ 관리자만 사용할 수 있습니다.');
      exit;
    }
    if (!preg_match('/^\.색변환\s+(\S+)\s+(\d+)\s*$/u', $statusTrim, $m)) {
      echo 전송("❌ 사용법: .색변환 닉네임 번호\n예) .색변환 우서 15");
      exit;
    }
    $대상원본 = trim((string)$m[1]);
    $색번호 = (int)$m[2];
    $대상닉 = function_exists('getTwoCharNick') ? getTwoCharNick($대상원본) : $대상원본;
    if ($대상닉 === '') {
      $대상닉 = $대상원본;
    }
    if ($대상닉 === '' || $색번호 < 1) {
      echo 전송("❌ 사용법: .색변환 닉네임 번호\n예) .색변환 우서 15");
      exit;
    }
    $대상_esc = addslashes($대상닉);
    $기존 = db_select("SELECT idx, name, num FROM tb_member WHERE name = '{$대상_esc}' AND IFNULL(status, 0) != 1 LIMIT 1");
    if (empty($기존['idx'])) {
      echo 전송("❌ [ {$대상닉} ] 회원이 없어요.");
      exit;
    }
    if (!function_exists('item_bag_sub_nick') && is_file(__DIR__ . '/item_bag.inc.php')) {
      require_once __DIR__ . '/item_bag.inc.php';
    }
    if (!function_exists('item_bag_sub_nick')) {
      echo 전송('❌ 아이템 가방 기능을 불러올 수 없어요.');
      exit;
    }
    $보유개수 = function_exists('item_bag_qty_nick') ? item_bag_qty_nick($대상닉, '색변') : 0;
    $자동구매문구 = '';
    if ($보유개수 < 1) {
      if (!function_exists('아이템_부족분_자동구매')) {
        echo 전송("❌ [ {$대상닉} ] 색변 아이템이 없어요.");
        exit;
      }
      $자동 = 아이템_부족분_자동구매($대상닉, '색변', 1, true);
      if (empty($자동['ok'])) {
        $자동실패 = trim((string)($자동['msg'] ?? ''));
        $msg = "❌ [ {$대상닉} ] 색변 아이템이 없어요.";
        if ($자동실패 !== '') {
          $msg .= "\n" . $자동실패;
        }
        echo 전송($msg);
        exit;
      }
      $자동구매문구 = trim((string)($자동['msg'] ?? ''));
    }
    $차감 = item_bag_sub_nick($대상닉, '색변', 1);
    if (empty($차감['ok'])) {
      echo 전송("❌ [ {$대상닉} ] 색변 아이템이 없어요.");
      exit;
    }
    $이전 = (int)($기존['num'] ?? 0);
    db_query("UPDATE tb_member SET num = '{$색번호}', couple = 0 WHERE name = '{$대상_esc}' LIMIT 1");
    if (function_exists('아이템사용_시세하락')) {
      아이템사용_시세하락('색변', 1);
    }
    $이전표시 = $이전 > 0 ? (string)$이전 : '없음';
    $결과 = "✅ [ {$대상닉} ] 색번호 {$이전표시} → {$색번호}\n(색변 아이템 1개 사용)";
    if ($자동구매문구 !== '') {
      $결과 = $자동구매문구 . "\n\n" . $결과;
    }
    if (function_exists('본방알림_등록')) {
      본방알림_등록($결과, '색변환');
    }
    echo 전송($결과);
    exit;
  }
}

/**
 * 신입 색변 완료: 색 적용 + 신입/담당 본방냥 지급 + 담당 자동 해제
 * (.색변 명령 · 색표 선점 API 공통)
 *
 * @param array $옵션 ['alarm'=>bool 본방알림 등록, 'require_damdang'=>bool 담당 없으면 실패]
 * @return array{ok:bool,paid:bool,msg:string,error?:string,신입담당?:string,색번호?:int}
 */
if (!function_exists('신입색변_완료처리')) {
  function 신입색변_완료처리($대상닉, $색번호, array $옵션 = []): array {
    $대상닉 = trim((string)$대상닉);
    $색번호 = (int)$색번호;
    $알람등록 = !empty($옵션['alarm']);
    $담당필수 = !empty($옵션['require_damdang']);

    if ($대상닉 === '' || $색번호 < 1) {
      return ['ok' => false, 'paid' => false, 'msg' => '', 'error' => '닉네임·색번호가 올바르지 않아요.'];
    }

    $대상_esc = addslashes($대상닉);
    if (function_exists('색확정_컬럼보장')) {
      색확정_컬럼보장();
    }
    if (function_exists('신입담당보상_컬럼보장')) {
      신입담당보상_컬럼보장();
    }
    신입담당_config컬럼보장();

    $회원 = db_select("SELECT idx, IFNULL(`색확정`, 1) AS 색확정, `색변일시` FROM tb_member WHERE name = '{$대상_esc}' AND status = 0 LIMIT 1");
    if (empty($회원['idx'])) {
      return ['ok' => false, 'paid' => false, 'msg' => '', 'error' => "[ {$대상닉} ] 회원이 없어요."];
    }

    $색확정 = (int)($회원['색확정'] ?? 1);
    $이미지급 = trim((string)($회원['색변일시'] ?? '')) !== '';

    // 이미 색확정(기존회원) 또는 신입 보상 지급 완료 → 색만 갱신, 중복 지급 금지
    if ($색확정 === 1 || $이미지급) {
      db_query("UPDATE tb_member SET num = '{$색번호}', couple = 0 WHERE name = '{$대상_esc}' LIMIT 1");
      if ($담당필수) {
        return [
          'ok' => false,
          'paid' => false,
          'msg' => '',
          'error' => "[ {$대상닉} ] 은(는) 이미 색변·신입 보상이 완료된 회원이에요.",
        ];
      }
      return [
        'ok' => true,
        'paid' => false,
        'msg' => "🎨 [ {$대상닉} ] #{$색번호} 색 적용",
        '색번호' => $색번호,
      ];
    }

    $설정 = db_select("SELECT `신입담당` FROM config LIMIT 1");
    $신입담당 = trim((string)($설정['신입담당'] ?? ''));
    if ($신입담당 === '') {
      if ($담당필수) {
        return ['ok' => false, 'paid' => false, 'msg' => '', 'error' => '아직 신입 담당자가 정해지지 않았어요.'];
      }
      // 담당 없으면 색만 선점 (지급·색확정은 담당 등록 후 .색변 또는 재선점)
      db_query("UPDATE tb_member SET num = '{$색번호}', couple = 0 WHERE name = '{$대상_esc}' LIMIT 1");
      return [
        'ok' => true,
        'paid' => false,
        'msg' => "🎨 [ {$대상닉} ] #{$색번호} 색 선점 (담당자 없어 보상 대기)",
        '색번호' => $색번호,
      ];
    }

    $신입보상 = function_exists('신입지원금_계산') ? 신입지원금_계산() : newpoint비율계산(0.03);
    $담당보상 = function_exists('신입담당보상_계산') ? 신입담당보상_계산() : newpoint비율계산(0.01);

    db_query("UPDATE tb_member SET num = '{$색번호}', couple = 0, `색확정` = 1 WHERE name = '{$대상_esc}' LIMIT 1");

    $담당_esc = addslashes($신입담당);
    if ($신입보상 > 0) {
      db_query("UPDATE tb_member SET newpoint = newpoint + {$신입보상} WHERE name = '{$대상_esc}'");
      if (function_exists('지급로그')) {
        지급로그('신입해제보상', $대상닉, $신입담당, 0, $신입보상);
      }
    }
    if ($담당보상 > 0) {
      db_query("UPDATE tb_member SET newpoint = newpoint + {$담당보상} WHERE name = '{$담당_esc}'");
      if (function_exists('지급로그')) {
        지급로그('신입담당보상', $신입담당, $대상닉, 0, $담당보상);
      }
    }
    if (function_exists('신입담당보상_기록')) {
      신입담당보상_기록($대상닉, $신입담당, $담당보상);
    }

    // 담당 자동 해제
    db_query("UPDATE config SET `신입담당` = '' LIMIT 1");

    $신입표시 = function_exists('newpoint표시') ? newpoint표시($신입보상) : number_format((float)$신입보상);
    $담당표시 = function_exists('newpoint표시') ? newpoint표시($담당보상) : number_format((float)$담당보상);
    $msg = "🎉✨ 환영합니다 ✨🎉\n"
      . "━━━━━━━━━━━━━━━━\n\n"
      . "👋 [ {$대상닉} ] 친구야!\n"
      . "우리 방에 온 걸 진심으로 환영해!! 🎊🎈\n\n"
      . "🎨 프로필 색 적용 완료! (번호 {$색번호})\n\n"
      . "💰 신입 해제 보상 {$신입표시}냥 지급!\n"
      . "🙋 담당 [ {$신입담당} ] 님\n"
      . "💰 신입 담당 보상 {$담당표시}냥 지급!\n"
      . "✅ 신입 담당 자동 해제\n\n"
      . "━━━━━━━━━━━━━━━━";

    if ($알람등록 && function_exists('본방알림_등록')) {
      본방알림_등록($msg, '신입색변');
    }

    return [
      'ok' => true,
      'paid' => true,
      'msg' => $msg,
      '신입담당' => $신입담당,
      '색번호' => $색번호,
      '신입보상' => $신입보상,
      '담당보상' => $담당보상,
    ];
  }
}

/**
 * 24시간 이내 퇴사 시 담당자 보상 회수 (지급액 × 조기회수비율, 기본 70%)
 * - 일반: 본방냥 우선 차감 → 부족분은 스왑환율로 게임냥 차감(부족 시 마이너스·신불 허용)
 * - 이미 신불자: 본방냥은 건드리지 않고 회수액을 게임냥 빚(-)으로 차감
 * @param array $신입회원 tb_member 행
 * @return array{clawed:bool,msg:string,sponsor:string,np:float,pt:string}
 */
if (!function_exists('신입담당보상_조기회수')) {
  function 신입담당보상_조기회수(array $신입회원): array {
    $empty = ['clawed' => false, 'msg' => '', 'sponsor' => '', 'np' => 0.0, 'pt' => '0'];
    신입담당보상_컬럼보장();

    $담당닉 = trim((string)($신입회원['신입담당자'] ?? ''));
    $원보상 = round(max(0, (float)($신입회원['신입담당보상'] ?? 0)), 1);
    $회수비율 = 신입담당보상_조기회수비율();
    if ($회수비율 < 0) {
      $회수비율 = 0.0;
    } elseif ($회수비율 > 1) {
      $회수비율 = 1.0;
    }
    $보상 = round($원보상 * $회수비율, 1);
    $색변일시 = trim((string)($신입회원['색변일시'] ?? ''));
    $신입닉 = trim((string)($신입회원['name'] ?? ''));

    if ($담당닉 === '' || $원보상 < 0.1 || $보상 < 0.1) {
      return $empty;
    }

    $기준시각 = $색변일시 !== '' ? strtotime($색변일시) : false;
    if ($기준시각 === false) {
      $reg = trim((string)($신입회원['regdate'] ?? ''));
      $기준시각 = $reg !== '' ? strtotime($reg) : false;
    }
    if ($기준시각 === false) {
      return $empty;
    }
    if ((time() - $기준시각) >= 신입담당보상_회수유예초()) {
      return $empty;
    }

    $담당_esc = addslashes($담당닉);
    $담당 = @db_select("
      SELECT name,
             CAST(IFNULL(newpoint, 0) AS CHAR) AS newpoint,
             CAST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)) AS CHAR) AS point,
             IFNULL(title, '') AS title,
             IFNULL(credit, 0) AS credit
      FROM tb_member
      WHERE name = '{$담당_esc}' AND status = 0
      LIMIT 1
    ");
    if (empty($담당['name'])) {
      return [
        'clawed' => false,
        'msg' => "⚠️ 담당 [ {$담당닉} ] 회원을 찾지 못해 담당보상 회수를 건너뛰었어요.",
        'sponsor' => $담당닉,
        'np' => 0.0,
        'pt' => '0',
      ];
    }

    $ptRaw = trim((string)($담당['point'] ?? '0'));
    $pt음수 = (isset($ptRaw[0]) && $ptRaw[0] === '-');
    $타이틀 = trim((string)($담당['title'] ?? ''));
    $신용 = (int)($담당['credit'] ?? 0);
    $이미신불 = (
      $타이틀 === '🆘신불자'
      || (bool)preg_match('/신불자/u', $타이틀)
      || $pt음수
      || ($신용 === 1 && $pt음수)
    );

    $필요_np = $보상;
    // 이미 신불자 → 본방냥 차감 없이 전액 게임냥 빚(-)
    if ($이미신불) {
      $차감_np = 0.0;
      $잔여_np = $필요_np;
    } else {
      $보유_np = max(0.0, round((float)($담당['newpoint'] ?? 0), 1));
      $차감_np = min($보유_np, $필요_np);
      $잔여_np = round($필요_np - $차감_np, 1);
      if ($잔여_np < 0) {
        $잔여_np = 0.0;
      }
    }

    $차감_pt = '0';
    if ($잔여_np >= 0.1) {
      $swapPath = __DIR__ . '/game/swap.inc.php';
      if (is_file($swapPath)) {
        require_once $swapPath;
      }
      if (function_exists('스왑_총량조회') && function_exists('스왑_np2pt_지급계산')) {
        $총량 = 스왑_총량조회();
        $rawPt = 스왑_np2pt_지급계산(
          $잔여_np,
          $총량['total_pt'] ?? '0',
          $총량['total_np'] ?? 0
        );
        $차감_pt = function_exists('스왑_정수문자열')
          ? 스왑_정수문자열($rawPt)
          : preg_replace('/\D+/', '', (string)$rawPt);
      }
      if ($차감_pt === '' || $차감_pt === '0') {
        // 환율 산출 실패해도 본방냥 회수는 진행
        $차감_pt = '0';
      }
    }

    if ($차감_np >= 0.1) {
      $np_sql = number_format($차감_np, 1, '.', '');
      db_query("
        UPDATE tb_member
        SET newpoint = IFNULL(newpoint, 0) - {$np_sql}
        WHERE name = '{$담당_esc}'
        LIMIT 1
      ");
    }
    if ($차감_pt !== '0' && $차감_pt !== '') {
      if (function_exists('tb_member_point_컬럼_보장')) {
        tb_member_point_컬럼_보장();
      }
      $pt_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($차감_pt) : preg_replace('/[^0-9]/', '', $차감_pt);
      if ($pt_sql === '' || $pt_sql === '-') {
        $pt_sql = '0';
      }
      if ($pt_sql !== '0') {
        db_query("
          UPDATE tb_member
          SET point = CAST(IFNULL(point, 0) AS DECIMAL(65,0)) - CAST('{$pt_sql}' AS DECIMAL(65,0))
          WHERE name = '{$담당_esc}'
          LIMIT 1
        ");
      }
    }

    // 게임냥이 마이너스면 신불자 유지/부여
    $이후 = @db_select("
      SELECT CAST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)) AS CHAR) AS point,
             IFNULL(title, '') AS title
      FROM tb_member
      WHERE name = '{$담당_esc}'
      LIMIT 1
    ");
    $이후pt = trim((string)($이후['point'] ?? '0'));
    $이후음수 = (isset($이후pt[0]) && $이후pt[0] === '-');
    if ($이후음수) {
      $이후타이틀 = trim((string)($이후['title'] ?? ''));
      if ($이후타이틀 !== '🆘신불자') {
        db_query("
          UPDATE tb_member
          SET title = '🆘신불자',
              credit = 1,
              credit_debt_at = NOW(),
              credit_debt_entered_at = NOW(),
              credit_recovery_plus3_at = NULL,
              credit_debt_times = IFNULL(credit_debt_times, 0) + 1
          WHERE name = '{$담당_esc}'
          LIMIT 1
        ");
      } else {
        db_query("UPDATE tb_member SET credit = 1 WHERE name = '{$담당_esc}' LIMIT 1");
      }
    }

    if (function_exists('지급로그')) {
      $비고 = '신입 ' . $신입닉 . ' 조기퇴사' . ($이미신불 ? ' · 신불빚' : '');
      if ($차감_np >= 0.1) {
        지급로그('신입담당회수-본방', $담당닉, $비고, 0, '-' . number_format($차감_np, 1, '.', ''));
      }
      if ($차감_pt !== '0' && $차감_pt !== '') {
        지급로그('신입담당회수-게임', $담당닉, $비고 . ' · 본방잔여' . number_format($잔여_np, 1, '.', ''), 0, '-' . $차감_pt);
      }
    }

    $np표시 = function_exists('newpoint표시') ? newpoint표시($차감_np) : number_format($차감_np, 1);
    $pt표시 = function_exists('냥축약표시')
      ? 냥축약표시($차감_pt)
      : (function_exists('스왑_게임냥_표시') ? 스왑_게임냥_표시($차감_pt) : number_format((float)$차감_pt));
    $parts = [];
    if ($차감_np >= 0.1) {
      $parts[] = "본방냥 -{$np표시}냥";
    }
    if ($차감_pt !== '0' && $차감_pt !== '') {
      $suffix = $이미신불 ? '(신불 · 스왑환산)' : (($잔여_np >= 0.1) ? '(스왑환산)' : '');
      $parts[] = "게임냥 -{$pt표시}" . $suffix;
    }
    $detail = $parts !== [] ? implode(' + ', $parts) : '차감 없음';
    $원보상표시 = function_exists('newpoint표시') ? newpoint표시($원보상) : number_format($원보상, 1);
    $회수표시 = function_exists('newpoint표시') ? newpoint표시($보상) : number_format($보상, 1);
    $비율퍼센트 = (int)round($회수비율 * 100);
    $신불문구 = $이미신불 ? ' · 신불자 → 게임냥 빚으로 회수' : '';

    return [
      'clawed' => true,
      'msg' => "💸 24시간 이내 퇴사 · 담당 [ {$담당닉} ] 보상 {$비율퍼센트}% 회수 (당시 {$원보상표시}냥 → 회수 {$회수표시}냥){$신불문구}\n→ {$detail}",
      'sponsor' => $담당닉,
      'np' => $차감_np,
      'pt' => $차감_pt,
    ];
  }
}

/** 입장(regdate) 후 본방냥 양도 가능까지 필요한 시간(시간) */
if (!function_exists('신입_본방냥양도_대기시간_시간')) {
  function 신입_본방냥양도_대기시간_시간() {
    return 3;
  }
}

/** 입장 후 3시간 미만이면 true (본방냥 양도 불가) */
if (!function_exists('신입_본방냥양도_미충족')) {
  function 신입_본방냥양도_미충족($regdate) {
    $regdate = trim((string)$regdate);
    if ($regdate === '') {
      return true;
    }
    $가입시각 = strtotime($regdate);
    if ($가입시각 === false) {
      return true;
    }
    return $가입시각 > strtotime('-' . 신입_본방냥양도_대기시간_시간() . ' hours');
  }
}

/**
 * 본방냥 양도 — 입장 3시간 미만 신입 차단.
 * @return string|null 차단 시 안내 문구
 */
if (!function_exists('신입_본방냥양도_검사')) {
  function 신입_본방냥양도_검사($닉) {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return null;
    }
    $닉_esc = addslashes($닉);
    $행 = db_select("SELECT regdate FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $regdate = (string)($행['regdate'] ?? '');
    if (!신입_본방냥양도_미충족($regdate)) {
      return null;
    }
    $가입시각 = strtotime($regdate);
    $대기 = 신입_본방냥양도_대기시간_시간();
    $가능시각 = ($가입시각 !== false)
      ? date('m/d H:i', $가입시각 + $대기 * 3600)
      : '';
    $가능문구 = $가능시각 !== '' ? "\n양도 가능: {$가능시각} 이후" : '';
    return "❌ [ {$닉} ] 입장 후 {$대기}시간이 지나야 본방냥 양도가 가능해요.{$가능문구}";
  }
}

/** newpoint 중 양도 가능 정수 냥 (소수점 제외) */
function newpoint양도가능($금액) {
  return (int)floor((float)$금액);
}

/** 주급·보급 공통 비율 (전체 본방냥 합계 기준) */
function 보조금_지급비율() {
  return 0.015; // 1.5%
}

/** 주급·보급을 같은 본방냥 총합(지급 전 스냅샷)으로 계산 */
function 보조금_동시지급() {
  $기준총합 = (float)전체보유newpoint합계();
  return [
    '주급' => 주급지급_실행($기준총합),
    '보급' => 보급지급_실행($기준총합),
  ];
}

/** 주급 지급 실행 (.보조금지급 등에서 사용) — ['ok'=>bool, 'msg'=>string] 반환 */
function 주급지급_실행($기준총합 = null) {
  $비율 = 보조금_지급비율();
  $비율표시 = rtrim(rtrim(number_format($비율 * 100, 2, '.', ''), '0'), '.') . '%';
  $설정 = db_select("SELECT 주급지급날짜 FROM config LIMIT 1");
  $다음주급일 = isset($설정['주급지급날짜']) ? trim($설정['주급지급날짜']) : null;
  $전체보유 = $기준총합 !== null ? (float)$기준총합 : (float)전체보유newpoint합계();

  $오늘 = date("Y-m-d");
  $지급가능 = false;
  if ($다음주급일 === null || $다음주급일 === '') {
    $지급가능 = true;
  } else {
    $지급가능 = (strtotime($오늘) >= strtotime($다음주급일));
  }

  if (!$지급가능) {
    $남은일 = $다음주급일 ? max(0, (strtotime($다음주급일) - strtotime($오늘)) / 86400) : 0;
    $예상주급 = (int)floor($전체보유 * $비율);
    $예상주급문구 = "현재 전체 보유 냥 합계 기준 주급({$비율표시}): " . newpoint표시($예상주급) . "냥";
    return [
      'ok' => false,
      'msg' => "❌ 아직 주급일이 아닙니다.\n다음 주급일: {$다음주급일} (" . (int)$남은일 . "일 남음)\n{$예상주급문구}",
    ];
  }

  $다음날짜 = date("Y-m-d", strtotime("+7 days"));
  db_query("UPDATE config SET 주급지급날짜 = '{$다음날짜}'");
  $주급금액 = (int)floor($전체보유 * $비율);
  if ($주급금액 <= 0) {
    return [
      'ok' => false,
      'msg' => "❌ 전체 보유 냥이 부족하여 주급({$비율표시})을 계산할 수 없습니다.\n다음 주급일: {$다음날짜}",
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
    'msg' => "✅ 주급 지급완료\n전체 보유 냥 총합의 {$비율표시}를 관리자 1인당 주급으로 지급했습니다.\n다음 주급일: {$다음날짜}\n관리자 {$지급인원}명 × " . newpoint표시($주급금액) . "냥 = 총 " . newpoint표시($총지급) . "냥 지급",
  ];
}

/** 보급 지급 실행 (.보조금지급 등에서 사용) — ['ok'=>bool, 'msg'=>string] 반환 */
function 보급지급_실행($기준총합 = null) {
  $비율 = 보조금_지급비율();
  $비율표시 = rtrim(rtrim(number_format($비율 * 100, 2, '.', ''), '0'), '.') . '%';
  $설정 = db_select("SELECT 보급지급날짜 FROM config LIMIT 1");
  $다음보급일 = isset($설정['보급지급날짜']) ? trim($설정['보급지급날짜']) : null;
  $전체보유 = $기준총합 !== null ? (float)$기준총합 : (float)전체보유newpoint합계();
  $오늘 = date("Y-m-d");
  $지급가능 = false;
  if ($다음보급일 === null || $다음보급일 === '') {
    $지급가능 = true;
  } else {
    $지급가능 = (strtotime($오늘) >= strtotime($다음보급일));
  }
  if (!$지급가능) {
    $남은일 = $다음보급일 ? max(0, (strtotime($다음보급일) - strtotime($오늘)) / 86400) : 0;
    $예상보급1인 = (int)floor($전체보유 * $비율);
    $예상문구 = "현재 전체 보유 냥 합계 기준 보급({$비율표시}): 1인당 " . newpoint표시($예상보급1인) . "냥";
    return [
      'ok' => false,
      'msg' => "❌ 아직 보급일이 아닙니다.\n다음 보급일: {$다음보급일} (" . (int)$남은일 . "일 남음)\n{$예상문구}",
    ];
  }

  $보급1인금액 = (int)floor($전체보유 * $비율);
  if ($보급1인금액 <= 0) {
    $다음날짜 = date("Y-m-d", strtotime("+7 days"));
    db_query("UPDATE config SET 보급지급날짜 = '{$다음날짜}'");
    return [
      'ok' => false,
      'msg' => "❌ 전체 보유 냥이 부족하여 보급({$비율표시})을 계산할 수 없습니다.\n다음 보급일: {$다음날짜}",
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
    'msg' => "✅ 보급 지급완료\n전체 tb_member 보유 냥 총합의 {$비율표시}를 회원 1인당 그대로 지급했습니다.(퇴근,신입 제외)\n다음 보급일: {$다음날짜}\n대상 {$지급인원}명 × " . newpoint표시($보급1인금액) . "냥 = 총 " . newpoint표시($총지급) . "냥 지급",
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
 * 경수·조수 → 해/경/조 표시 파트 (1해=1만경)
 * 해 미만 경은 천경으로 쪼개지 않음 — 6549경 · 2245경
 * 예) 36549경6243조 → ["3해","6549경","6243조"] · 1500경 → ["1500경"]
 * @return list<string>
 */
if (!function_exists('냥_해경조_표시파트')) {
  function 냥_해경조_표시파트($경수, $조수 = 0, $콤마 = true): array {
    $경s = function_exists('냥_정수문자열') ? 냥_정수문자열($경수) : (ltrim(preg_replace('/[^\d]/', '', (string)$경수), '0') ?: '0');
    $조s = function_exists('냥_정수문자열') ? 냥_정수문자열($조수) : (ltrim(preg_replace('/[^\d]/', '', (string)$조수), '0') ?: '0');
    $fmt = function ($n) use ($콤마) {
      return $콤마 ? 냥_숫자콤마($n) : (function_exists('냥_정수문자열') ? 냥_정수문자열($n) : (string)$n);
    };

    $해s = '0';
    $경나머지 = $경s;
    // 1해 = 10000경
    if (function_exists('bccomp') && bccomp($경s, '10000', 0) >= 0) {
      $해s = bcdiv($경s, '10000', 0);
      $경나머지 = bcmod($경s, '10000');
    } elseif (strlen($경s) > 4 || (strlen($경s) === 4 && $경s >= '10000')) {
      $해s = ltrim(substr($경s, 0, -4), '0') ?: '0';
      $경나머지 = ltrim(substr($경s, -4), '0') ?: '0';
    }

    $parts = [];
    if ($해s !== '0') {
      $parts[] = $fmt($해s) . '해';
    }
    if ($경나머지 !== '0') {
      $parts[] = $fmt($경나머지) . '경';
    }
    if ($조s !== '0') {
      $parts[] = $fmt($조s) . '조';
    }
    return $parts;
  }
}

/**
 * 경·조·억 3단 축약 — 1경 이상: 해+경+조(억 절사), 1조 이상: 조만, 1조 미만: 억만
 * 예) 10,436경 → 1해436경 / 10,785조 → 1경 785조
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
      $parts = 냥_해경조_표시파트($경수, $조수, true);
      return ($parts ? implode(' ', $parts) : '0') . $접미;
    }

    return 냥_조억_축약표시($digits, $단위);
  }
}

/**
 * 조 단위 이상만 축약 표시 (억·만 이하 절사)
 * 예) 626,422,821,508,973,312 → 62경 6,422조
 *     5,000억 → 0 / 3조 → 3조
 */
if (!function_exists('냥_조이상_축약표시')) {
  function 냥_조이상_축약표시($금액, $단위 = '') {
    $조단위 = '1000000000000';
    $digits = function_exists('냥_정수문자열')
      ? 냥_정수문자열($금액)
      : (ltrim(preg_replace('/[^\d]/', '', (string)$금액), '0') ?: '0');
    $접미 = ($단위 !== '') ? (string)$단위 : '';

    $조미만 = function_exists('bccomp')
      ? bccomp($digits, $조단위) < 0
      : (strlen($digits) < 12 || (strlen($digits) === 12 && $digits < $조단위));
    if ($조미만) {
      return '0' . $접미;
    }

    if (function_exists('냥_경조_축약표시')) {
      return 냥_경조_축약표시($digits, $단위);
    }
    if (function_exists('랭킹_게임냥표시')) {
      return 랭킹_게임냥표시($digits, $단위);
    }
    return $digits . $접미;
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
 * 냥 경·조·억 축약 문구 — 억 미만은 만·원 단위로 표시 (0으로 버리지 않음)
 * @param string $구분자 파트 사이 구분 (기본 붙여쓰기, ' ' 등)
 */
if (!function_exists('냥_경조억_축약문구')) {
  function 냥_경조억_축약문구($금액, $단위 = '냥', $구분자 = '') {
    $raw = trim((string)$금액);
    $음수 = (bool)preg_match('/^N?-/i', $raw);
    if (!$음수 && is_numeric($raw) && (float)$raw < 0) {
      $음수 = true;
    }
    $digits = function_exists('냥_정수문자열')
      ? 냥_정수문자열($금액)
      : (ltrim(preg_replace('/[^\d]/', '', $raw), '0') ?: '0');
    if ($digits === '0') {
      return '0' . $단위;
    }

    [$경수, $조수, $억수] = 냥_경조억_파트($digits);
    $경s = 냥_정수문자열($경수);
    $조s = 냥_정수문자열($조수);
    $억s = 냥_정수문자열($억수);

    // 1억 미만: 만·나머지(천 이하)까지
    if ($경s === '0' && $조s === '0' && $억s === '0') {
      if (function_exists('랭킹_게임냥표시')) {
        // 단위 중복 방지 — 랭킹이 접미 포함
        return 랭킹_게임냥표시($digits, $단위);
      }
      $만단위 = '10000';
      if (function_exists('bccomp') && bccomp($digits, $만단위, 0) >= 0) {
        $만수 = bcdiv($digits, $만단위, 0);
        $나머지 = bcmod($digits, $만단위);
        if ($나머지 === '0') {
          return 냥_숫자콤마($만수) . '만' . $단위;
        }
        return 냥_숫자콤마($만수) . '만' . 냥_숫자콤마($나머지) . $단위;
      }
      return 냥_숫자콤마($digits) . $단위;
    }

    $parts = 냥_해경조_표시파트($경수, $조수, $구분자 === '');
    if ($억s !== '0') {
      $parts[] = ($구분자 === '' ? 냥_숫자콤마($억s) : $억s) . '억';
    }
    // 조·억 아래 만·원 잔여
    if (function_exists('bcmod') && function_exists('bcdiv') && function_exists('bccomp')) {
      $rest = bcmod($digits, '100000000'); // 억 미만
      if (bccomp($rest, '0', 0) > 0) {
        if (bccomp($rest, '10000', 0) >= 0) {
          $만수 = bcdiv($rest, '10000', 0);
          $원 = bcmod($rest, '10000');
          $parts[] = ($구분자 === '' ? 냥_숫자콤마($만수) : $만수) . '만';
          if ($원 !== '0') {
            $parts[] = ($구분자 === '' ? 냥_숫자콤마($원) : $원);
          }
        } else {
          $parts[] = ($구분자 === '' ? 냥_숫자콤마($rest) : $rest);
        }
      }
    }
    $본문 = ($parts ? implode($구분자, $parts) : '0') . $단위;
    if ($음수 && $digits !== '0') {
      return '-' . $본문;
    }
    return $본문;
  }
}

/** 랭킹·시세용 게임냥 표시 — 해·경·조·억·만·원(천 이하)까지 · 음수(신불)는 앞에 - */
if (!function_exists('랭킹_게임냥표시')) {
  function 랭킹_게임냥표시($금액, $단위 = '냥') {
    $조단위 = '1000000000000';
    $억단위 = '100000000';
    $만단위 = '10000';
    $raw = trim((string)$금액);
    $음수 = (bool)preg_match('/^N?-/i', $raw);
    if (!$음수 && is_numeric($raw) && (float)$raw < 0) {
      $음수 = true;
    }
    $digits = function_exists('냥_정수문자열')
      ? 냥_정수문자열($금액)
      : (ltrim(preg_replace('/[^\d]/', '', $raw), '0') ?: '0');
    $접미 = (string)$단위;

    $본문 = '';
    $append만원 = static function (array &$parts, string $rest) use ($만단위): void {
      if ($rest === '' || $rest === '0') {
        return;
      }
      if (function_exists('bccomp') && function_exists('bcdiv') && function_exists('bcmod')) {
        if (bccomp($rest, $만단위, 0) >= 0) {
          $만수 = bcdiv($rest, $만단위, 0);
          $원 = bcmod($rest, $만단위);
          $parts[] = 냥_숫자콤마($만수) . '만';
          if ($원 !== '0') {
            $parts[] = 냥_숫자콤마($원);
          }
          return;
        }
      } elseif (strlen($rest) > 4) {
        $만수 = substr($rest, 0, -4);
        $원 = ltrim(substr($rest, -4), '0') ?: '0';
        $parts[] = 냥_숫자콤마($만수) . '만';
        if ($원 !== '0') {
          $parts[] = 냥_숫자콤마($원);
        }
        return;
      }
      $parts[] = 냥_숫자콤마($rest);
    };

    if (function_exists('bccomp') && function_exists('bcdiv') && function_exists('bcmod')) {
      if (bccomp($digits, $조단위) >= 0) {
        [$경수, $조수, $억수] = 냥_경조억_파트($digits);
        $parts = 냥_해경조_표시파트($경수, $조수, false);
        $억s = 냥_정수문자열($억수);
        if ($억s !== '0') {
          $parts[] = 냥_숫자콤마($억s) . '억';
        }
        $rest = bcmod($digits, $억단위);
        $append만원($parts, $rest);
        $본문 = $parts ? implode('', $parts) . $접미 : '0' . $접미;
      } elseif (bccomp($digits, $억단위) >= 0) {
        $parts = [냥_숫자콤마(bcdiv($digits, $억단위, 0)) . '억'];
        $rest = bcmod($digits, $억단위);
        $append만원($parts, $rest);
        $본문 = implode('', $parts) . $접미;
      } else {
        $parts = [];
        $append만원($parts, $digits);
        $본문 = ($parts ? implode('', $parts) : '0') . $접미;
      }
    } elseif (strlen($digits) > 12 || (strlen($digits) === 12 && $digits >= $조단위)) {
      [$경수, $조수, $억수] = 냥_경조억_파트($digits);
      $parts = 냥_해경조_표시파트($경수, $조수, false);
      $억s = 냥_정수문자열($억수);
      if ($억s !== '0') {
        $parts[] = 냥_숫자콤마($억s) . '억';
      }
      // 억 미만 잔여 (문자열)
      $len = strlen($digits);
      $rest = ($len > 8) ? (ltrim(substr($digits, -8), '0') ?: '0') : $digits;
      if ($억s !== '0' && $len > 8) {
        $rest = ltrim(substr($digits, -8), '0') ?: '0';
      }
      $append만원($parts, $rest);
      $본문 = $parts ? implode('', $parts) . $접미 : '0' . $접미;
    } elseif (strlen($digits) >= 8) {
      $억수 = substr($digits, 0, strlen($digits) - 8) ?: '0';
      $rest = ltrim(substr($digits, -8), '0') ?: '0';
      $parts = [];
      if ($억수 !== '0') {
        $parts[] = 냥_숫자콤마($억수) . '억';
      }
      $append만원($parts, $rest);
      $본문 = ($parts ? implode('', $parts) : '0') . $접미;
    }
    if ($본문 === '') {
      $parts = [];
      $append만원($parts, $digits);
      $본문 = ($parts ? implode('', $parts) : 냥_숫자콤마($digits)) . $접미;
    }

    if ($음수 && $digits !== '0') {
      return '-' . $본문;
    }
    return $본문;
  }
}

/**
 * 구매가 축약 표시 — 해·경·조·억·만까지 표시 (억 미만을 0으로 버리지 않음)
 */
if (!function_exists('구매가_축약표시')) {
  function 구매가_축약표시($금액, $단위 = '냥') {
    if (function_exists('랭킹_게임냥표시')) {
      return 랭킹_게임냥표시($금액, $단위);
    }
    if (function_exists('게임냥_안전표시')) {
      return 게임냥_안전표시($금액, $단위);
    }
    return 냥_경조억_축약문구($금액, $단위, '');
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

      // 시즌 초기화 등으로 level만 리셋되고 lotto_ticket_level이 남은 경우
      // → 0으로 되돌려 현재 레벨까지의 티켓을 다시 지급 (가방 기존 잔량은 유지·가산)
      if ($paid_ticket_level > $current_level) {
        db_query("UPDATE tb_member SET lotto_ticket_level = 0 WHERE idx = {$idx} LIMIT 1");
        $paid_ticket_level = 0;
      }

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
        // 레벨업 공창 알림은 미발송 (보상 지급만)
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

/** 연속 강화용 — 이력 N건을 한 번에 INSERT (왕복 DB 감소) */
if (!function_exists('강화_이력_기록_일괄')) {
  function 강화_이력_기록_일괄(array $rows): int {
    if (empty($rows)) {
      return 0;
    }
    $chunks = array_chunk($rows, 40);
    $ok = 0;
    foreach ($chunks as $chunk) {
      $values = [];
      foreach ($chunk as $opts) {
        if (!is_array($opts)) {
          continue;
        }
        $nick = trim((string)($opts['nick'] ?? ''));
        $result = (string)($opts['result'] ?? '');
        if ($nick === '' || !in_array($result, ['success', 'fail_protect', 'fail_break'], true)) {
          continue;
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
          $suho_left_sql = (string)max(0, (int)$opts['suho_left']);
        }
        $values[] = "('{$nick_esc}','{$channel}','{$item}','{$style}',{$enhance_before},{$enhance_after},'{$result}',{$cost},{$dice_roll},{$dice_num},{$dice_den},'{$rate_pct}',{$eunchong},{$challenge_mode},{$challenge_target_sql},{$challenge_steal_sql},{$suho_used},{$suho_left_sql},NOW())";
      }
      if (empty($values)) {
        continue;
      }
      $sql = "INSERT INTO tb_enhance_log
        (nick, channel, item, style, enhance_before, enhance_after, result, cost, dice_roll, dice_num, dice_den, rate_pct, eunchong, challenge_mode, challenge_target, challenge_steal, suho_used, suho_left, regdate)
        VALUES " . implode(',', $values);
      if (@db_query($sql)) {
        $ok += count($values);
      } else {
        // 일괄 실패 시 건별 fallback
        foreach ($chunk as $opts) {
          if (is_array($opts) && 강화_이력_기록($opts)) {
            $ok++;
          }
        }
      }
    }
    return $ok;
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

if (!function_exists('무기복구_전체게임냥_확보')) {
  /**
   * 전체 게임냥 — 랭킹·계급과 동일(계급_총게임냥) 우선, SUM 실패 시 상위합산 폴백
   */
  function 무기복구_전체게임냥_확보(): string {
    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }
    if (function_exists('시세기준_컬럼_보장')) {
      시세기준_컬럼_보장();
    }

    // 1) 랭킹·계급과 동일 총합 (전 회원 SUM + 폴백)
    if (function_exists('계급_총게임냥')) {
      $total = 냥_정수문자열(계급_총게임냥());
      if ($total !== '0') {
        return $total;
      }
    }

    // 2) 활성 회원 실시간 합
    if (function_exists('시세기준_실시간합계')) {
      $live = 시세기준_실시간합계();
      $pt = 냥_정수문자열($live['게임냥'] ?? 0);
      if ($pt !== '0') {
        return $pt;
      }
    }

    // 3) 시세 스냅샷 갱신 후 조회 (실시간 캐시가 아닌 스냅샷 로드 — 0 캐시 재사용 방지)
    if (function_exists('시세기준_스냅샷_갱신')) {
      시세기준_스냅샷_갱신(true);
      if (function_exists('시세기준_스냅샷_캐시_초기화')) {
        시세기준_스냅샷_캐시_초기화();
      }
    }
    if (function_exists('시세기준_스냅샷_로드')) {
      $snapRow = 시세기준_스냅샷_로드();
      $snap = 냥_정수문자열($snapRow['게임냥'] ?? 0);
      if ($snap !== '0') {
        return $snap;
      }
    }

    // 4) 전 회원 SUM (status 무관 · 마이너스 제외)
    $row = @db_select("
      SELECT CONCAT('N', CAST(COALESCE(SUM(GREATEST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)), 0)), 0) AS CHAR)) AS total_pt
      FROM tb_member
    ");
    $total = 냥_금액원문_정규화($row['total_pt'] ?? 'N0');
    if ($total !== '0') {
      return $total;
    }

    // 5) 상위 보유자 PHP 합산 (SQL SUM 깨질 때)
    // DECIMAL 캐스팅이 과학적표기 문자열을 깨뜨릴 수 있어 CHAR 원문 → PHP 파싱
    if (function_exists('db_query') && function_exists('냥_금액_문자열합')) {
      $rs = @db_query("
        SELECT CONCAT('N', CAST(IFNULL(point, 0) AS CHAR)) AS point
        FROM tb_member
        WHERE IFNULL(point, 0) > 0
        ORDER BY point + 0 DESC
        LIMIT 300
      ");
      $sum = '0';
      if ($rs) {
        while ($r = db_fetch($rs)) {
          $p = 냥_금액원문_정규화($r['point'] ?? 'N0');
          if ($p !== '0') {
            $sum = 냥_금액_문자열합($sum, $p);
          }
        }
      }
      if ($sum !== '0') {
        return $sum;
      }
    }

    return '0';
  }
}

if (!function_exists('무기복구_전체게임냥합계')) {
  function 무기복구_전체게임냥합계() {
    return function_exists('무기복구_전체게임냥_확보')
      ? 무기복구_전체게임냥_확보()
      : (function_exists('시세기준_게임냥_문자열')
        ? 시세기준_게임냥_문자열()
        : 냥_정수문자열(시세기준_게임냥()));
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

/** +20강 이상 파손 복구 — 전체 게임냥 대비 % (+20=1.2% · 강당 +0.1%p · +31=2.3%) */
if (!function_exists('무기복구_고강최소')) {
  function 무기복구_고강최소(): int {
    return 20;
  }
}

if (!function_exists('무기복구_고강비율퍼센트')) {
  /**
   * 전체 게임냥 대비 % (+20=1.2, +21=1.3, … +31=2.3)
   * @return float|null
   */
  function 무기복구_고강비율퍼센트($강화) {
    $강화 = (int)$강화;
    $min = 무기복구_고강최소();
    if ($강화 < $min) {
      return null;
    }
    // 1.2 + (강화-20)×0.1  ≡  강화 대비 0.1%p씩 (소수 1자리)
    return round(1.2 + ($강화 - $min) * 0.1, 1);
  }
}

if (!function_exists('무기복구_고강비율배수')) {
  /** @return float|null */
  function 무기복구_고강비율배수($강화) {
    $pct = 무기복구_고강비율퍼센트($강화);
    return $pct === null ? null : ($pct / 100.0);
  }
}

if (!function_exists('무기복구_비율적용_올림')) {
  /**
   * 전체 × 퍼센트(1.2=1.2%, 2.3=2.3%) — float/(int)캐스팅 금지
   * 소수 1자리 %는 0.1% 단위(천분율) 정수 연산: 전체 × (pct×10) ÷ 1000
   * @return string
   */
  function 무기복구_비율적용_올림($전체, $퍼센트): string {
    $전체 = 냥_정수문자열($전체);
    $pct = (float)$퍼센트;
    if ($전체 === '0' || $pct <= 0) {
      return '0';
    }
    // 1.2% → 12, 2.3% → 23 (0.1% 단위)
    $tenths = (int)round($pct * 10);
    if ($tenths < 1) {
      return '0';
    }
    $tenthsStr = (string)$tenths;

    if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bcmod') && function_exists('bcadd') && function_exists('bccomp')) {
      $prod = bcmul($전체, $tenthsStr, 0);
      $금액 = bcdiv($prod, '1000', 0);
      if (bccomp(bcmod($prod, '1000'), '0', 0) > 0) {
        $금액 = bcadd($금액, '1', 0); // 올림
      }
      if (bccomp($금액, '1', 0) < 0) {
        $금액 = '1';
      }
      return $금액;
    }

    // bcmath 없을 때: 문자열 곱셈 후 1000으로 나눔(올림)
    if (!function_exists('냥_금액_문자열합')) {
      return '0';
    }
    $sum = '0';
    for ($i = 0; $i < $tenths; $i++) {
      $sum = 냥_금액_문자열합($sum, $전체);
    }
    $len = strlen($sum);
    if ($len <= 3) {
      return ($sum === '0' || ltrim($sum, '0') === '') ? '0' : '1';
    }
    $q = ltrim(substr($sum, 0, $len - 3), '0') ?: '0';
    $r = (int)substr($sum, -3);
    if ($r > 0) {
      $q = 냥_금액_문자열합($q, '1');
    }
    return $q === '0' ? '1' : $q;
  }
}

if (!function_exists('무기복구_은총시세_로드')) {
  function 무기복구_은총시세_로드(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $path = __DIR__ . '/eunchong_shop_buy.inc.php';
    if (is_file($path)) {
      require_once $path;
    }
  }
}

if (!function_exists('무기복구_은총1개시세')) {
  /** 은총(게임냥) 현재 1개 시세 */
  function 무기복구_은총1개시세(): string {
    무기복구_은총시세_로드();
    if (function_exists('info3_은총2_구매단가')) {
      return 냥_정수문자열(info3_은총2_구매단가());
    }
    return '0';
  }
}

if (!function_exists('무기복구_파손시세')) {
  /**
   * N강 파손 시세 = 전체 게임냥 × 고강 복구% (A안: 1.2+(N-20)×0.1)
   * +10~+19는 기존 무기복구_비용 비율표 사용
   */
  function 무기복구_파손시세($파손강화): string {
    $강화 = (int)$파손강화;
    if ($강화 < 0) {
      return '0';
    }
    $min = (int)무기복구_고강최소();
    if ($강화 >= $min && function_exists('무기복구_고강비율퍼센트') && function_exists('무기복구_비율적용_올림')) {
      $pct = 무기복구_고강비율퍼센트($강화);
      if ($pct === null) {
        return '0';
      }
      $전체 = function_exists('무기복구_전체게임냥_확보')
        ? 무기복구_전체게임냥_확보()
        : 냥_정수문자열(무기복구_전체게임냥합계());
      if ($전체 === '0') {
        return '0';
      }
      return 냥_정수문자열(무기복구_비율적용_올림($전체, $pct));
    }
    if (function_exists('무기복구_비용')) {
      $c = 무기복구_비용($강화);
      return ($c === null) ? '0' : 냥_정수문자열($c);
    }
    return '0';
  }
}

/** @deprecated 강화비 아님 — 파손시세 사용 */
if (!function_exists('무기복구_강화시세')) {
  function 무기복구_강화시세($시세강화): string {
    return 무기복구_파손시세($시세강화);
  }
}

if (!function_exists('무기복구_고강_견적')) {
  /**
   * 파손 N강 → (N-1)강 파손시세로 복구 / 그 비용+은총1개로 N강 복구
   * @return array{
   *   broken:int, restore_base:int, restore_full:int,
   *   cost_base:string, cost_full:string, eunchong:string,
   *   cost_base_fmt:string, cost_full_fmt:string, eunchong_fmt:string
   * }|null
   */
  function 무기복구_고강_견적($파손강화): ?array {
    $파손 = (int)$파손강화;
    if ($파손 < (int)무기복구_고강최소()) {
      return null;
    }
    $시세강 = $파손 - 1;
    if ($시세강 < 0) {
      return null;
    }
    // (N-1)강으로 파손됐을 때의 복구비
    $cost_base = 무기복구_파손시세($시세강);
    $eun = 무기복구_은총1개시세();
    $cost_full = function_exists('냥_금액_문자열합')
      ? 냥_금액_문자열합($cost_base, $eun)
      : (function_exists('bcadd') ? bcadd($cost_base, $eun, 0) : (string)((int)$cost_base + (int)$eun));
    $fmt = static function ($n): string {
      if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($n, '냥');
      }
      return 냥_숫자콤마($n) . '냥';
    };
    return [
      'broken' => $파손,
      'restore_base' => $시세강,
      'restore_full' => $파손,
      'cost_base' => $cost_base,
      'cost_full' => $cost_full,
      'eunchong' => $eun,
      'cost_base_fmt' => $fmt($cost_base),
      'cost_full_fmt' => $fmt($cost_full),
      'eunchong_fmt' => $fmt($eun),
    ];
  }
}

/** 하위호환 — 견적의 풀복구(원강) 비용 */
if (!function_exists('무기복구_고강비용')) {
  function 무기복구_고강비용($강화, $전체게임냥 = null) {
    $견적 = 무기복구_고강_견적($강화);
    return $견적 ? (string)$견적['cost_full'] : null;
  }
}

if (!function_exists('무기복구_고강비용_표시')) {
  function 무기복구_고강비용_표시($강화, $단위 = '냥', $전체게임냥 = null) {
    $견적 = 무기복구_고강_견적($강화);
    if (!$견적) {
      return '—';
    }
    if (function_exists('랭킹_게임냥표시')) {
      return 랭킹_게임냥표시($견적['cost_full'], $단위);
    }
    return 냥_숫자콤마($견적['cost_full']) . $단위;
  }
}

/** @return array<int, array<string, mixed>> */
if (!function_exists('무기복구_고강_파손목록')) {
  function 무기복구_고강_파손목록($nick, $limit = 30, $전체게임냥 = null) {
    $nick_esc = addslashes(trim((string)$nick));
    if ($nick_esc === '') {
      return [];
    }
    $min = (int)무기복구_고강최소();
    $limit = max(1, min(50, (int)$limit));
    $rows = [];
    $rs = db_query("
      SELECT idx, nick, item, style, enhance_before, regdate
      FROM tb_enhance_log
      WHERE nick = '{$nick_esc}'
        AND result = 'fail_break'
        AND enhance_before >= {$min}
        AND IFNULL(restored, 0) = 0
      ORDER BY idx DESC
      LIMIT {$limit}
    ");
    while ($rs && $row = db_fetch($rs)) {
      $강화 = (int)($row['enhance_before'] ?? 0);
      $견적 = 무기복구_고강_견적($강화);
      if (!$견적) {
        continue;
      }
      $row['cost_base'] = $견적['cost_base'];
      $row['cost_full'] = $견적['cost_full'];
      $row['cost_base_fmt'] = $견적['cost_base_fmt'];
      $row['cost_full_fmt'] = $견적['cost_full_fmt'];
      $row['eunchong'] = $견적['eunchong'];
      $row['eunchong_fmt'] = $견적['eunchong_fmt'];
      $row['restore_base'] = $견적['restore_base'];
      $row['restore_full'] = $견적['restore_full'];
      // 하위호환 필드
      $row['cost'] = $견적['cost_full'];
      $row['cost_fmt'] = $견적['cost_full_fmt'];
      $row['cost_pct'] = null;
      $rows[] = $row;
    }
    return $rows;
  }
}

if (!function_exists('무기복구_로그_조회')) {
  function 무기복구_로그_조회($nick, $logIdx) {
    $nick_esc = addslashes(trim((string)$nick));
    $logIdx = (int)$logIdx;
    if ($nick_esc === '' || $logIdx < 1) {
      return null;
    }
    $row = db_select("
      SELECT idx, nick, item, style, enhance_before, regdate
      FROM tb_enhance_log
      WHERE idx = {$logIdx}
        AND nick = '{$nick_esc}'
        AND result = 'fail_break'
        AND IFNULL(restored, 0) = 0
      LIMIT 1
    ");
    return !empty($row['idx']) ? $row : null;
  }
}

/**
 * +20강 이상 파손 1건 복구 (웹 · 로그 idx 지정)
 * @param string $mode 'base'=(N-1)강 파손시세 복구 · 'full'=그 비용+은총1개로 원강 복구
 * @return array{ok:bool, msg:string, point?:string|int, point_fmt?:string}
 */
if (!function_exists('무기복구_고강_실행')) {
  function 무기복구_고강_실행($nick, $logIdx, $단위 = '냥', $mode = 'base') {
    $닉 = trim((string)$nick);
    if ($닉 === '') {
      return ['ok' => false, 'msg' => '❌ 닉네임을 확인해주세요.'];
    }
    $닉_esc = addslashes($닉);
    $로그idx = (int)$logIdx;
    if ($로그idx < 1) {
      return ['ok' => false, 'msg' => '❌ 복구 대상을 선택해주세요.'];
    }
    $mode = ($mode === 'full') ? 'full' : 'base';

    $회원 = db_select("SELECT idx, CAST(point AS CHAR) AS point, item, enhance FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    if (empty($회원['idx'])) {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.'];
    }
    if (trim((string)($회원['item'] ?? '')) !== '') {
      return ['ok' => false, 'msg' => '❌ 이미 무기를 보유 중이에요. 무기가 없을 때만 복구할 수 있어요.'];
    }

    $대상 = 무기복구_로그_조회($닉, $로그idx);
    if (empty($대상['idx'])) {
      return ['ok' => false, 'msg' => '❌ 복구할 파손 이력이 없거나 이미 복구됐어요.'];
    }

    $무기 = trim((string)($대상['item'] ?? ''));
    $스타일 = trim((string)($대상['style'] ?? ''));
    $파손강 = (int)($대상['enhance_before'] ?? 0);
    if ($무기 === '') {
      return ['ok' => false, 'msg' => '❌ 복구 대상 무기 정보가 없어요.'];
    }
    if ($파손강 < (int)무기복구_고강최소()) {
      return ['ok' => false, 'msg' => '❌ +20강 이상 파손만 이 페이지에서 복구할 수 있어요.'];
    }

    $견적 = 무기복구_고강_견적($파손강);
    if (!$견적) {
      return ['ok' => false, 'msg' => "❌ +{$파손강}강 무기는 복구 비용을 계산할 수 없어요."];
    }

    $복구강 = ($mode === 'full') ? (int)$견적['restore_full'] : (int)$견적['restore_base'];
    $비용_str = ($mode === 'full')
      ? 냥_정수문자열($견적['cost_full'])
      : 냥_정수문자열($견적['cost_base']);
    if ($비용_str === '0') {
      return ['ok' => false, 'msg' => '❌ 복구 시세를 불러오지 못했어요.'];
    }

    $보유냥 = 냥_정수문자열($회원['point'] ?? 0);
    $부족 = function_exists('bccomp')
      ? (bccomp($보유냥, $비용_str, 0) < 0)
      : (strlen($보유냥) < strlen($비용_str)
        || (strlen($보유냥) === strlen($비용_str) && $보유냥 < $비용_str));
    if ($부족) {
      $비용표시 = function_exists('랭킹_게임냥표시')
        ? 랭킹_게임냥표시($비용_str, $단위)
        : (냥_숫자콤마($비용_str) . $단위);
      $보유표시 = function_exists('랭킹_게임냥표시')
        ? 랭킹_게임냥표시($보유냥, $단위)
        : (냥_숫자콤마($보유냥) . $단위);
      return [
        'ok' => false,
        'msg' => "❌ 복구비가 부족해요.\n필요: {$비용표시} (보유: {$보유표시})",
      ];
    }

    $비용_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($비용_str) : $비용_str;
    $무기_esc = addslashes($무기);
    $스타일_esc = addslashes($스타일);
    $rs = db_query("
      UPDATE tb_member
      SET point = point - {$비용_sql},
          `item` = '{$무기_esc}',
          `enhance` = {$복구강},
          `style` = '{$스타일_esc}',
          강화성공시간 = NOW()
      WHERE name = '{$닉_esc}'
        AND (TRIM(COALESCE(`item`, '')) = '')
        AND point >= {$비용_sql}
      LIMIT 1
    ");
    if (!$rs) {
      return ['ok' => false, 'msg' => '❌ 무기 복구 처리에 실패했어요.'];
    }
    global $conn;
    if (($conn instanceof mysqli) && (int)mysqli_affected_rows($conn) <= 0) {
      return ['ok' => false, 'msg' => '❌ 복구비가 부족하거나 이미 무기를 보유 중이에요.'];
    }

    if ($복구강 >= 10 && function_exists('무기_최대내구도')) {
      $내구도 = (int)무기_최대내구도($무기, $복구강);
      if ($내구도 > 0) {
        db_query("UPDATE tb_member SET durability = {$내구도} WHERE name = '{$닉_esc}' LIMIT 1");
      }
    }

    db_query("UPDATE tb_enhance_log SET restored = 1, restored_at = NOW() WHERE idx = {$로그idx} AND IFNULL(restored, 0) = 0 LIMIT 1");

    if (function_exists('지급로그')) {
      $로그모드 = ($mode === 'full') ? '원강' : '시세강';
      지급로그("무기복구고강+{$복구강}|{$로그모드}", $닉, "+{$파손강}파손", $비용_sql, 0);
    }

    $잔액 = function_exists('bcsub') ? bcsub($보유냥, $비용_str, 0) : 냥_금액_문자열차감($보유냥, $비용_str);
    $냥표시 = function_exists('랭킹_게임냥표시')
      ? static function ($금액) use ($단위) { return 랭킹_게임냥표시($금액, $단위); }
      : static function ($금액) use ($단위) { return 냥_숫자콤마($금액) . $단위; };

    $무기표시 = $스타일 !== '' ? "{$스타일} {$무기}" : $무기;
    $파손시각 = !empty($대상['regdate']) ? date('m-d H:i', strtotime($대상['regdate'])) : '';
    $msg = "✅ 무기 복구 완료!\n";
    $msg .= "{$무기표시} +{$파손강} 파손 → +{$복구강} 복원 (파손 {$파손시각})\n";
    $msg .= "복구비: " . $냥표시($비용_str);
    return [
      'ok' => true,
      'msg' => $msg,
      'point' => $잔액,
      'point_fmt' => $냥표시($잔액),
      'restore_enhance' => $복구강,
      'mode' => $mode,
    ];
  }
}

/** `.수리` 내구도 1당 비율 — +20: 전체 게임냥 0.0000005%, 그 외(+10~19): 0.0000001% */
if (!function_exists('수리_비율퍼센트')) {
  function 수리_비율퍼센트($강화단계 = 0): float {
    return ((int)$강화단계 >= 20) ? 0.0000005 : 0.0000001;
  }
}

/** `.수리` 내구도 1당 냥 비용 (전체 게임냥 × 수리_비율퍼센트) — 문자열 (PHP_INT_MAX 초과 대응) */
if (!function_exists('수리_회당비용')) {
  function 수리_회당비용($강화단계 = 0) {
    $v = 전체냥기준금액(수리_비율퍼센트($강화단계));
    return function_exists('냥_정수문자열') ? 냥_정수문자열($v) : (string)$v;
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

/** `.강화 수호` 1회당 본방냥(newpoint) 비용 */
if (!function_exists('강화수호_회당비용')) {
  function 강화수호_회당비용(): float {
    return 0.1;
  }
}

/**
 * 회당 +1~+10 균등 랜덤 N회의 합 (대량은 다항분포 근사)
 * @return array{합:int, c1:int, c2:int, c3:int, c4:int, c5:int, c6:int, c7:int, c8:int, c9:int, c10:int}
 */
if (!function_exists('강화수호_랜덤증가_합산')) {
  function 강화수호_랜덤증가_합산(int $회수): array {
    $빈 = ['합' => 0, 'c1' => 0, 'c2' => 0, 'c3' => 0, 'c4' => 0, 'c5' => 0, 'c6' => 0, 'c7' => 0, 'c8' => 0, 'c9' => 0, 'c10' => 0];
    $회수 = max(0, (int)$회수);
    if ($회수 <= 0) {
      return $빈;
    }

    $counts = array_fill(1, 10, 0);

    // 소량: 실제 회차 롤 (정확)
    if ($회수 <= 3000) {
      for ($i = 0; $i < $회수; $i++) {
        $counts[mt_rand(1, 10)]++;
      }
    } else {
      // 대량: Multinomial(N, 1/10 …) — 순차 이항(정규근사)
      $이항 = static function (int $n, float $p): int {
        if ($n <= 0) {
          return 0;
        }
        if ($p <= 0) {
          return 0;
        }
        if ($p >= 1) {
          return $n;
        }
        if ($n <= 2000) {
          $c = 0;
          $lim = (int)floor($p * (mt_getrandmax() + 1.0));
          for ($i = 0; $i < $n; $i++) {
            if (mt_rand() < $lim) {
              $c++;
            }
          }
          return $c;
        }
        $mean = $n * $p;
        $sd = sqrt($n * $p * (1.0 - $p));
        $u1 = max(1e-12, mt_rand() / (mt_getrandmax() + 1.0));
        $u2 = mt_rand() / (mt_getrandmax() + 1.0);
        $z = sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
        return max(0, min($n, (int)round($mean + $sd * $z)));
      };

      $remaining = $회수;
      for ($k = 1; $k <= 9; $k++) {
        $leftCats = 11 - $k;
        $counts[$k] = $이항($remaining, 1.0 / $leftCats);
        $remaining -= $counts[$k];
      }
      $counts[10] = $remaining;
    }

    $합 = 0;
    $out = $빈;
    for ($k = 1; $k <= 10; $k++) {
      $c = (int)$counts[$k];
      $out['c' . $k] = $c;
      $합 += $k * $c;
    }
    $out['합'] = $합;
    return $out;
  }
}

/** 합산 카운트 문구 (+1×a +2×b …) */
if (!function_exists('강화수호_합산카운트_문구')) {
  function 강화수호_합산카운트_문구(array $합산): string {
    $parts = [];
    for ($k = 1; $k <= 10; $k++) {
      $c = (int)($합산['c' . $k] ?? 0);
      if ($c > 0) {
        $parts[] = "+{$k}×{$c}";
      }
    }
    return implode(' ', $parts);
  }
}

/** 맥스 구매 견적 서명 키 */
if (!function_exists('강화수호_맥스구매_시크릿')) {
  function 강화수호_맥스구매_시크릿(): string {
    $seed = '강화수호맥스v1';
    if (defined('DB_PASS')) {
      $seed .= (string)DB_PASS;
    }
    if (defined('DB_NAME')) {
      $seed .= (string)DB_NAME;
    }
    return hash('sha256', $seed . '|' . (__DIR__ ?? ''));
  }
}

/**
 * 본방 10냥 남기고 살 수 있는 횟수 + 회당 1~10 미리뽑기
 * @return array{회수:int,증가:int,비용:float,c1:int,c2:int,c3:int,c4:int,c5:int,c6:int,c7:int,c8:int,c9:int,c10:int,exp:int,token:string}
 */
if (!function_exists('강화수호_맥스구매_견적')) {
  function 강화수호_맥스구매_견적(float $본방냥, string $닉 = ''): array {
    $단위 = max(0.1, (float)강화수호_회당비용());
    $회수 = (int)floor(max(0.0, round($본방냥, 1) - 10.0) / $단위 + 1e-9);
    if ($회수 < 1) {
      return [
        '회수' => 0,
        '증가' => 0,
        '비용' => 0.0,
        'c1' => 0, 'c2' => 0, 'c3' => 0, 'c4' => 0, 'c5' => 0,
        'c6' => 0, 'c7' => 0, 'c8' => 0, 'c9' => 0, 'c10' => 0,
        'exp' => 0,
        'token' => '',
      ];
    }
    if ($회수 > 10000000) {
      $회수 = 10000000;
    }
    return 강화수호_지정회수_견적($회수, $닉, $단위);
  }
}

/**
 * 맥스 구매 가능 횟수의 비율(예: 0.1=10%)만큼 미리뽑기 견적
 * @return array{회수:int,증가:int,비용:float,c1:int,...,exp:int,token:string}
 */
if (!function_exists('강화수호_비율구매_견적')) {
  function 강화수호_비율구매_견적(float $본방냥, float $비율, string $닉 = ''): array {
    $빈 = [
      '회수' => 0, '증가' => 0, '비용' => 0.0,
      'c1' => 0, 'c2' => 0, 'c3' => 0, 'c4' => 0, 'c5' => 0,
      'c6' => 0, 'c7' => 0, 'c8' => 0, 'c9' => 0, 'c10' => 0,
      'exp' => 0, 'token' => '',
    ];
    $비율 = max(0.0, min(1.0, (float)$비율));
    if ($비율 <= 0) {
      return $빈;
    }
    $단위 = max(0.1, (float)강화수호_회당비용());
    $맥스회수 = (int)floor(max(0.0, round($본방냥, 1) - 10.0) / $단위 + 1e-9);
    $회수 = (int)floor($맥스회수 * $비율 + 1e-9);
    if ($회수 < 1) {
      return $빈;
    }
    if ($회수 > 10000000) {
      $회수 = 10000000;
    }
    return 강화수호_지정회수_견적($회수, $닉, $단위);
  }
}

/** 지정 회수 미리뽑기 + HMAC 토큰 */
if (!function_exists('강화수호_지정회수_견적')) {
  function 강화수호_지정회수_견적(int $회수, string $닉 = '', ?float $단위 = null): array {
    $빈 = [
      '회수' => 0, '증가' => 0, '비용' => 0.0,
      'c1' => 0, 'c2' => 0, 'c3' => 0, 'c4' => 0, 'c5' => 0,
      'c6' => 0, 'c7' => 0, 'c8' => 0, 'c9' => 0, 'c10' => 0,
      'exp' => 0, 'token' => '',
    ];
    $회수 = max(0, (int)$회수);
    if ($회수 < 1) {
      return $빈;
    }
    if ($단위 === null) {
      $단위 = max(0.1, (float)강화수호_회당비용());
    } else {
      $단위 = max(0.1, (float)$단위);
    }
    $합산 = 강화수호_랜덤증가_합산($회수);
    $증가 = (int)$합산['합'];
    $비용 = round($회수 * $단위, 1);
    $비용_str = number_format($비용, 1, '.', '');
    $exp = time() + 900;
    $닉_norm = trim($닉);
    $payload = $닉_norm . '|' . $회수 . '|' . $증가 . '|' . $비용_str . '|' . $exp;
    $token = hash_hmac('sha256', $payload, 강화수호_맥스구매_시크릿());
    $out = [
      '회수' => $회수,
      '증가' => $증가,
      '비용' => $비용,
      'exp' => $exp,
      'token' => $token,
    ];
    for ($k = 1; $k <= 10; $k++) {
      $out['c' . $k] = (int)($합산['c' . $k] ?? 0);
    }
    return $out;
  }
}

if (!function_exists('강화수호_맥스구매_검증')) {
  function 강화수호_맥스구매_검증(string $닉, int $회수, int $증가, $비용, int $exp, string $token): bool {
    if ($token === '' || $회수 < 1 || $증가 < $회수 || $증가 > $회수 * 10) {
      return false;
    }
    if ($exp < time()) {
      return false;
    }
    $비용_str = number_format(round((float)$비용, 1), 1, '.', '');
    $payload = trim($닉) . '|' . $회수 . '|' . $증가 . '|' . $비용_str . '|' . $exp;
    $expect = hash_hmac('sha256', $payload, 강화수호_맥스구매_시크릿());
    return hash_equals($expect, $token);
  }
}

/**
 * 미리뽑기 수치 그대로 본방냥 구매 (수호 아이템 미사용)
 * @return array{ok:bool,msg:string,enhance_suho?:int,point?:string,newpoint?:float,차감?:float,냥사용회수?:int,증가?:int}
 */
if (!function_exists('강화수호_본방고정구매')) {
  function 강화수호_본방고정구매(string $닉, int $회수, int $증가): array {
    $닉 = trim($닉);
    $회수 = max(1, (int)$회수);
    $증가 = max(0, (int)$증가);
    if ($닉 === '' || $증가 < $회수 || $증가 > $회수 * 10) {
      return ['ok' => false, 'msg' => '❌ 강화 수호 구매 수치가 올바르지 않아요.'];
    }

    $닉_esc = addslashes($닉);
    $회원 = db_select("SELECT idx, CAST(point AS CHAR) AS point, IFNULL(newpoint, 0) AS newpoint, enhance_suho FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    if (empty($회원['idx'])) {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.'];
    }

    $회당비용 = (float)강화수호_회당비용();
    $총차감 = round($회당비용 * $회수, 1);
    $보유본방 = round((float)($회원['newpoint'] ?? 0), 1);
    if ($보유본방 + 1e-9 < $총차감) {
      return [
        'ok' => false,
        'msg' => "❌ {$닉} 본방냥 부족\n필요: 본방 " . number_format($총차감, 1) . '냥 / 보유: 본방 ' . number_format($보유본방, 1) . '냥',
      ];
    }
    // 견적 기준: 구매 후 본방 10냥 이상 남김
    if ($보유본방 - $총차감 + 1e-9 < 10.0) {
      return ['ok' => false, 'msg' => "❌ 본방 10냥은 남겨야 해요. 새로고침 후 다시 구매해주세요."];
    }

    $현재수호 = function_exists('bag_강화수호_수량')
      ? bag_강화수호_수량($닉)
      : (int)($회원['enhance_suho'] ?? 0);
    $새수호 = $현재수호 + $증가;
    $idx = (int)$회원['idx'];
    $총차감_sql = number_format((float)$총차감, 1, '.', '');
    db_query("UPDATE tb_member SET newpoint = IFNULL(newpoint, 0) - {$총차감_sql} WHERE idx = {$idx} AND IFNULL(newpoint, 0) >= {$총차감_sql} LIMIT 1");
    global $conn;
    $ptOk = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
    if (!$ptOk) {
      return ['ok' => false, 'msg' => "❌ {$닉} 본방냥 차감에 실패했어요."];
    }
    if (function_exists('bag_강화수호_설정')) {
      bag_강화수호_설정($닉, $새수호);
    } else {
      db_query("UPDATE tb_member SET enhance_suho = {$새수호} WHERE name = '{$닉_esc}' LIMIT 1");
    }

    $보유게임냥 = function_exists('냥_정수문자열')
      ? 냥_정수문자열($회원['point'] ?? 0)
      : (string)(int)($회원['point'] ?? 0);
    $새본방 = round($보유본방 - $총차감, 1);
    $차감표시 = '본방 ' . number_format($총차감, 1) . '냥';

    return [
      'ok' => true,
      'msg' => "👼 {$닉} 강화 수호 {$회수}회 구매! ({$차감표시} 차감)\n"
        . "미리뽑기 적용: 총 +{$증가}\n무기 파손 방지 {$새수호}회 누적",
      'enhance_suho' => $새수호,
      'point' => $보유게임냥,
      'newpoint' => $새본방,
      '차감' => $총차감,
      '냥사용회수' => $회수,
      '증가' => $증가,
    ];
  }
}

/**
 * `.강화 수호 [N]` — 수호 아이템 우선 사용, 부족분만 본방냥(1회 0.1냥) 차감
 * @param int|null $냥회당증가 null이면 냥 1~10 랜덤, 숫자면 냥 구매 시 회당 고정 (+1). 수호 아이템 전환은 항상 1~10
 * @return array{ok:bool,msg:string,enhance_suho?:int,point?:int|string,newpoint?:float,차감?:float,아이템사용?:int,냥사용회수?:int}
 */
if (!function_exists('강화수호_냥적용')) {
  function 강화수호_냥적용(string $닉, int $수량, $단위 = '본방냥', $냥회당증가 = null): array {
    $닉 = trim($닉);
    $수량 = max(1, (int)$수량);
    if ($닉 === '') {
      return ['ok' => false, 'msg' => '❌ 닉네임을 확인해주세요.'];
    }

    $닉_esc = addslashes($닉);
    $회원 = db_select("SELECT idx, CAST(point AS CHAR) AS point, IFNULL(newpoint, 0) AS newpoint, enhance_suho FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    if (empty($회원['idx'])) {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.'];
    }

    $보유아이템 = item_bag_qty_nick($닉, '수호');
    $아이템사용 = min($수량, $보유아이템);
    $냥사용회수 = $수량 - $아이템사용;
    $회당비용 = (float)강화수호_회당비용();
    $총차감 = round($회당비용 * $냥사용회수, 1);
    $보유본방 = round((float)($회원['newpoint'] ?? 0), 1);
    $보유게임냥 = function_exists('냥_정수문자열')
      ? 냥_정수문자열($회원['point'] ?? 0)
      : (string)(int)($회원['point'] ?? 0);

    if ($냥사용회수 > 0 && $보유본방 + 1e-9 < $총차감) {
      $부족문구 = "❌ {$닉} 본방냥 부족";
      if ($아이템사용 > 0) {
        $부족문구 .= " (수호 아이템 {$아이템사용}개 사용 후 본방 {$냥사용회수}회 추가 필요)";
      }
      $보유표시 = number_format($보유본방, 1);
      $필요표시 = number_format($총차감, 1);
      $부족문구 .= "\n필요: 본방 {$필요표시}냥 / 보유: 본방 {$보유표시}냥";
      return ['ok' => false, 'msg' => $부족문구];
    }

    $현재수호 = function_exists('bag_강화수호_수량')
      ? bag_강화수호_수량($닉)
      : (int)($회원['enhance_suho'] ?? 0);
    $총증가수치 = 0;
    $회차결과 = [];
    $실제아이템사용 = 0;
    $아이템증가값 = function () {
      return rand(1, 10);
    };
    $냥증가값 = function () use ($냥회당증가) {
      if ($냥회당증가 !== null) {
        return max(1, (int)$냥회당증가);
      }
      return rand(1, 10);
    };

    for ($i = 1; $i <= $아이템사용; $i++) {
      $차감 = item_bag_sub_nick($닉, '수호', 1);
      if (empty($차감['ok'])) {
        break;
      }
      $증가 = $아이템증가값();
      $총증가수치 += $증가;
      $회차결과[] = "{$i}회차:+{$증가}(수호아이템)";
      $실제아이템사용++;
    }

    if ($실제아이템사용 > 0 && function_exists('아이템사용_시세하락')) {
      아이템사용_시세하락('수호', $실제아이템사용);
    }

    $냥회차시작 = $실제아이템사용 + 1;
    $실제냥사용회수 = max(0, $수량 - $실제아이템사용);
    $냥고정증가 = ($냥회당증가 !== null) ? max(1, (int)$냥회당증가) : null;

    // 냥 구매(회당 고정 +1 등) · 대량: 회차별 루프/문구 생략
    if ($실제냥사용회수 > 0 && $냥고정증가 !== null) {
      $총증가수치 += $실제냥사용회수 * $냥고정증가;
      if ($실제냥사용회수 <= 20) {
        for ($i = $냥회차시작; $i <= $수량; $i++) {
          $회차결과[] = "{$i}회차:+{$냥고정증가}(본방냥)";
        }
      } else {
        $회차결과[] = "본방냥 {$실제냥사용회수}회 ×+{$냥고정증가}";
      }
    } elseif ($실제냥사용회수 > 0) {
      if ($실제냥사용회수 <= 20) {
        for ($i = $냥회차시작; $i <= $수량; $i++) {
          $증가 = $냥증가값();
          $총증가수치 += $증가;
          $회차결과[] = "{$i}회차:+{$증가}(본방냥)";
        }
      } else {
        $합산 = 강화수호_랜덤증가_합산($실제냥사용회수);
        $총증가수치 += (int)$합산['합'];
        $카운트문구 = function_exists('강화수호_합산카운트_문구') ? 강화수호_합산카운트_문구($합산) : '';
        $회차결과[] = "본방냥 {$실제냥사용회수}회 (회당 +1~10" . ($카운트문구 !== '' ? " · {$카운트문구}" : '') . ')';
      }
    }
    $총차감 = round($회당비용 * $실제냥사용회수, 1);

    if ($총증가수치 <= 0) {
      return ['ok' => false, 'msg' => "❌ {$닉} 강화 수호를 처리할 수 없어요."];
    }

    $새수호 = $현재수호 + $총증가수치;
    $idx = (int)$회원['idx'];
    if ($총차감 > 0) {
      $총차감_sql = number_format((float)$총차감, 1, '.', '');
      db_query("UPDATE tb_member SET newpoint = IFNULL(newpoint, 0) - {$총차감_sql} WHERE idx = {$idx} AND IFNULL(newpoint, 0) >= {$총차감_sql} LIMIT 1");
      global $conn;
      $ptOk = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
      if (!$ptOk) {
        return ['ok' => false, 'msg' => "❌ {$닉} 본방냥 차감에 실패했어요."];
      }
    }
    if (function_exists('bag_강화수호_설정')) {
      bag_강화수호_설정($닉, $새수호);
    } else {
      db_query("UPDATE tb_member SET enhance_suho = {$새수호} WHERE name = '{$닉_esc}' LIMIT 1");
    }

    $상세 = implode(', ', $회차결과);
    $차감표시 = '본방 ' . number_format((float)$총차감, 1) . '냥';
    $사용문구 = '';
    if ($실제아이템사용 > 0 && $실제냥사용회수 > 0) {
      $사용문구 = "수호 아이템 {$실제아이템사용}개 + {$차감표시} 차감";
    } elseif ($실제아이템사용 > 0) {
      $사용문구 = "수호 아이템 {$실제아이템사용}개 사용";
    } else {
      $사용문구 = "{$차감표시} 차감";
    }

    $새본방 = round($보유본방 - $총차감, 1);

    return [
      'ok' => true,
      'msg' => "👼 {$닉} 강화 수호 {$수량}회 적용! ({$사용문구})\n"
        . "적용값: {$상세}\n총 +{$총증가수치} 적용\n무기 파손 방지 {$새수호}회 누적",
      'enhance_suho' => $새수호,
      'point' => $보유게임냥,
      'newpoint' => $새본방,
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
    return item_bag_qty_nick(trim($닉), '수호');
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

    $수호보유 = function_exists('bag_강화수호_수량')
      ? bag_강화수호_수량(trim($닉))
      : (int)($회원['enhance_suho'] ?? 0);
    if ($수호보유 >= 1) {
      if (function_exists('bag_강화수호_차감')) {
        $차감강화 = bag_강화수호_차감(trim($닉), 1);
        if (empty($차감강화['ok'])) {
          return null;
        }
        $남은 = (int)($차감강화['qty'] ?? max(0, $수호보유 - 1));
      } else {
        db_query("UPDATE tb_member SET enhance_suho = GREATEST(IFNULL(enhance_suho, 0) - 1, 0) WHERE name = '{$닉_esc}'");
        $남은 = $수호보유 - 1;
      }
      return [
        'enhance_suho' => $남은,
        'suho_item_used' => false,
        'suho_item_left' => 강화수호_아이템개수($닉),
        'msg_suffix' => $남은 > 0 ? " (수호 {$남은}회 남음)" : '',
      ];
    }

    $차감 = item_bag_sub_nick(trim($닉), '수호', 1);
    if (empty($차감['ok'])) {
      return null;
    }

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

/** 꼬맨 완료 보상 — 실시간 본방냥 합계의 1% */
if (!function_exists('꼬맨_보상_계산')) {
  function 꼬맨_보상_계산() {
    if (function_exists('newpoint비율계산')) {
      return (int)newpoint비율계산(0.01);
    }
    if (function_exists('전체보유newpoint합계')) {
      return (int)floor((float)전체보유newpoint합계() * 0.01);
    }
    return 1000;
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

/** 지갑용 일일 유사도 순위 테이블 */
if (!function_exists('꼬맨_순위_테이블보장')) {
  function 꼬맨_순위_테이블보장(): void {
    static $done = false;
    if ($done || !function_exists('db_query')) {
      return;
    }
    $done = true;
    @db_query("
      CREATE TABLE IF NOT EXISTS `tb_kkomaen_rank` (
        `nick` VARCHAR(50) NOT NULL COMMENT '2글자 닉네임',
        `play_date` DATE NOT NULL COMMENT '배정일',
        `similarity_rank` INT UNSIGNED NOT NULL COMMENT '유사도 순위 1~500',
        `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`nick`, `play_date`),
        KEY `idx_play_date` (`play_date`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='꼬맨틀 일일 유사도 순위 배정'
    ");
  }
}

/**
 * 지갑 접속 시 오늘 유사도 순위(1~500) 개인 배정 — 하루 1회 고정
 */
if (!function_exists('꼬맨_오늘순위_배정')) {
  function 꼬맨_오늘순위_배정($nick): int {
    $nick = trim((string)$nick);
    if ($nick === '' || !function_exists('db_select') || !function_exists('db_query')) {
      return 0;
    }
    꼬맨_순위_테이블보장();
    $nick_esc = addslashes($nick);
    $오늘 = date('Y-m-d');
    $row = db_select("
      SELECT similarity_rank
      FROM tb_kkomaen_rank
      WHERE nick = '{$nick_esc}' AND play_date = '{$오늘}'
      LIMIT 1
    ");
    if (!empty($row) && isset($row['similarity_rank'])) {
      $r = (int)$row['similarity_rank'];
      return max(1, min(500, $r));
    }
    $rank = random_int(1, 500);
    $ok = @db_query("
      INSERT INTO tb_kkomaen_rank (nick, play_date, similarity_rank, regdate)
      VALUES ('{$nick_esc}', '{$오늘}', {$rank}, NOW())
    ");
    if ($ok === false) {
      // 동시 접속 레이스 → 재조회
      $row2 = db_select("
        SELECT similarity_rank
        FROM tb_kkomaen_rank
        WHERE nick = '{$nick_esc}' AND play_date = '{$오늘}'
        LIMIT 1
      ");
      if (!empty($row2) && isset($row2['similarity_rank'])) {
        return max(1, min(500, (int)$row2['similarity_rank']));
      }
    }
    return $rank;
  }
}

/** 지갑 UI용 꼬맨틀 오늘 상태 */
if (!function_exists('꼬맨_지갑_페이로드')) {
  function 꼬맨_지갑_페이로드($nick): array {
    $한도 = function_exists('꼬맨_일일한도') ? (int)꼬맨_일일한도() : 5;
    $완료 = function_exists('꼬맨_오늘완료수') ? (int)꼬맨_오늘완료수() : 0;
    $잔여 = max(0, $한도 - $완료);
    $순위 = 꼬맨_오늘순위_배정($nick);
    $참여완료 = function_exists('꼬맨_오늘참여여부') ? 꼬맨_오늘참여여부($nick) : false;
    $보상 = function_exists('꼬맨_보상_계산') ? (int)꼬맨_보상_계산() : 0;
    $보상표시 = function_exists('newpoint표시') ? newpoint표시($보상) : (number_format($보상) . '냥');
    return [
      'rank' => $순위,
      'done' => $완료,
      'limit' => $한도,
      'remain' => $잔여,
      'completed' => $참여완료 ? 1 : 0,
      'full' => ($잔여 <= 0 && !$참여완료) ? 1 : 0,
      'reward' => $보상,
      'reward_fmt' => $보상표시,
    ];
  }
}

/**
 * @deprecated 웹 자체 보상 지급은 순위 조작 가능해 사용하지 않음.
 *             보상은 연구실 `.꼬맨완료` 로만 지급.
 * @param int $achieved_rank 게임에서 나온 유사도 순위 (목표 순위 이하 = 달성)
 * @return array{ok:bool,data:string,reward?:int,reward_fmt?:string,done?:int,limit?:int,remain?:int}
 */
if (!function_exists('꼬맨_웹완료_실행')) {
  function 꼬맨_웹완료_실행($nick, $achieved_rank = 0): array {
    return [
      'ok' => false,
      'data' => '웹에서는 보상을 받을 수 없어요. 목표 순위 달성 후 연구실에서 `.꼬맨완료` 로 신청해주세요.',
    ];
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

/** 일방신청권 보유 — tb_member_item_bag 우선, 없으면 레거시 tb_member_item */
if (!function_exists('일방신청권_보유여부')) {
  function 일방신청권_보유여부(int $midx): bool {
    if ($midx <= 0) {
      return false;
    }
    if (function_exists('item_bag_qty') && (int)item_bag_qty($midx, '일방신청권') > 0) {
      return true;
    }
    $row = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$midx} AND itemname = '일방신청권' AND status IN (0, 1) LIMIT 1");
    return (int)($row['cnt'] ?? 0) > 0;
  }
}

/**
 * 일방신청권 1개 차감 (.일방신청 시)
 * @return array{ok:bool,msg?:string,qty?:int}
 */
if (!function_exists('일방신청권_차감')) {
  function 일방신청권_차감(int $midx, string $nick, int $qty = 1): array {
    $midx = (int)$midx;
    $nick = trim($nick);
    $qty = max(1, (int)$qty);
    if ($midx <= 0 || $nick === '') {
      return ['ok' => false, 'msg' => '회원 정보가 없어요.', 'qty' => 0];
    }
    if (!function_exists('item_bag_sub') && is_file(__DIR__ . '/item_bag.inc.php')) {
      require_once __DIR__ . '/item_bag.inc.php';
    }
    if (function_exists('item_bag_ensure_column')) {
      item_bag_ensure_column('일방신청권');
    }
    if (function_exists('item_bag_sub') && function_exists('item_bag_qty')) {
      $보유 = (int)item_bag_qty($midx, '일방신청권');
      if ($보유 >= $qty) {
        $차감 = item_bag_sub($midx, $nick, '일방신청권', $qty);
        if (!empty($차감['ok'])) {
          return ['ok' => true, 'qty' => (int)($차감['qty'] ?? max(0, $보유 - $qty))];
        }
      }
    }
    // 레거시 tb_member_item 폴백
    $rows = db_query("
      SELECT idx FROM tb_member_item
      WHERE midx = {$midx} AND itemname = '일방신청권' AND status IN (0, 1)
      ORDER BY idx ASC
      LIMIT {$qty}
    ");
    $처리 = 0;
    if ($rows) {
      while ($row = db_fetch($rows)) {
        $idx = (int)($row['idx'] ?? 0);
        if ($idx > 0 && db_query("UPDATE tb_member_item SET status = 2, usedate = NOW() WHERE idx = {$idx} LIMIT 1")) {
          $처리++;
        }
      }
    }
    if ($처리 >= $qty) {
      $남음행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$midx} AND itemname = '일방신청권' AND status IN (0, 1)");
      return ['ok' => true, 'qty' => (int)($남음행['cnt'] ?? 0)];
    }
    return ['ok' => false, 'msg' => '일방신청권이 없어요.', 'qty' => 0];
  }
}

/** 일방연장권 보유 — tb_member_item_bag 우선, 없으면 레거시 tb_member_item */
if (!function_exists('일방연장권_보유여부')) {
  function 일방연장권_보유여부(int $midx): bool {
    if ($midx <= 0) {
      return false;
    }
    if (function_exists('item_bag_ensure_column')) {
      item_bag_ensure_column('일방연장권');
    }
    if (function_exists('item_bag_qty') && (int)item_bag_qty($midx, '일방연장권') > 0) {
      return true;
    }
    $row = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$midx} AND itemname = '일방연장권' AND status IN (0, 1) LIMIT 1");
    return (int)($row['cnt'] ?? 0) > 0;
  }
}

/**
 * 일방연장권 1개 차감
 * @return array{ok:bool,msg?:string,qty?:int}
 */
if (!function_exists('일방연장권_차감')) {
  function 일방연장권_차감(int $midx, string $nick, int $qty = 1): array {
    $midx = (int)$midx;
    $nick = trim($nick);
    $qty = max(1, (int)$qty);
    if ($midx <= 0 || $nick === '') {
      return ['ok' => false, 'msg' => '회원 정보가 없어요.', 'qty' => 0];
    }
    if (!function_exists('item_bag_sub') && is_file(__DIR__ . '/item_bag.inc.php')) {
      require_once __DIR__ . '/item_bag.inc.php';
    }
    if (function_exists('item_bag_ensure_column')) {
      item_bag_ensure_column('일방연장권');
    }
    if (function_exists('item_bag_sub') && function_exists('item_bag_qty')) {
      $보유 = (int)item_bag_qty($midx, '일방연장권');
      if ($보유 >= $qty) {
        $차감 = item_bag_sub($midx, $nick, '일방연장권', $qty);
        if (!empty($차감['ok'])) {
          return ['ok' => true, 'qty' => (int)($차감['qty'] ?? max(0, $보유 - $qty))];
        }
      }
    }
    $rows = db_query("
      SELECT idx FROM tb_member_item
      WHERE midx = {$midx} AND itemname = '일방연장권' AND status IN (0, 1)
      ORDER BY idx ASC
      LIMIT {$qty}
    ");
    $처리 = 0;
    if ($rows) {
      while ($row = db_fetch($rows)) {
        $idx = (int)($row['idx'] ?? 0);
        if ($idx > 0 && db_query("UPDATE tb_member_item SET status = 2, usedate = NOW() WHERE idx = {$idx} LIMIT 1")) {
          $처리++;
        }
      }
    }
    if ($처리 >= $qty) {
      $남음행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$midx} AND itemname = '일방연장권' AND status IN (0, 1)");
      $가방남음 = function_exists('item_bag_qty') ? (int)item_bag_qty($midx, '일방연장권') : 0;
      return ['ok' => true, 'qty' => $가방남음 + (int)($남음행['cnt'] ?? 0)];
    }
    return ['ok' => false, 'msg' => '일방연장권이 없어요.', 'qty' => 0];
  }
}

/**
 * 일방신청권 지급 시 조건·날짜 별도 기록 (tb_ilbang_ticket_log)
 * @param string $nick 받은 사람
 * @param string $reasonCode 생타3일연속|생타5일연속|구매|선물|관리자생성
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
        case '생타3일연속':
          $reasonText = '생타 200타 × 3일 연속 달성';
          break;
        case '생타5일연속':
          $reasonText = '생타 200타 × 5일 연속 달성';
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
 * 3일 200타(생타) 미션 완료 여부 — tb_mission 또는 일방신청권 보유(양도·구매·3일달성)
 * 권 보유 시 tb_mission(일방/200타) 기록도 남김 (구 400타·500타·300타 기록도 완료 인정)
 */
if (!function_exists('일방400타_미션_완료됨')) {
  function 일방400타_미션_완료됨(string $nick, ?int $midx = null): bool {
    $nick = trim($nick);
    if ($nick === '') {
      return false;
    }
    $map = 미션_완료_맵($nick);
    if (
      미션_완료됨($map, '일방', '200타')
      || 미션_완료됨($map, '일방', '400타')
      || 미션_완료됨($map, '일방', '500타')
      || 미션_완료됨($map, '일방', '300타')
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
      미션완료_기록_if_new($nick, '일방', '200타');
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
      $completed[] = ['label' => '3일간 200타(생타)'];
    } else {
      $missing[] = ['label' => '3일간 200타(생타)'];
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

/** `.본인인증` / `.본인인증 닉` / `.본인인증 닉 상대닉` — 첫 일방 셀카 안내. 매칭 시 전송 후 exit */
if (!function_exists('본인인증_명령_처리')) {
  function 본인인증_명령_처리($status, $두자리닉넴 = ''): bool {
    $본문 = trim((string)$status);
    if ($본문 === '' || !preg_match('/^\.본인인증(?:\s|$)/u', $본문)) {
      return false;
    }
    $신청닉 = trim((string)$두자리닉넴);
    $상대닉 = '';
    if (preg_match('/^\.본인인증(?:\s+(\S+)(?:\s+(\S+))?)?/u', $본문, $m)) {
      if (!empty($m[1])) {
        $신청닉 = function_exists('getTwoCharNick') ? getTwoCharNick(trim($m[1])) : trim($m[1]);
      }
      if (!empty($m[2])) {
        $상대닉 = function_exists('getTwoCharNick') ? getTwoCharNick(trim($m[2])) : trim($m[2]);
      }
    }

    $오늘 = date('Y-m-d');
    if ($신청닉 !== '' && $상대닉 !== '') {
      $msg = "준호썸\n{$오늘}\n{$신청닉}, {$상대닉} 일방신청";
    } elseif ($신청닉 !== '') {
      $msg = "준호썸\n{$오늘}\n{$신청닉} 일방신청";
    } else {
      $msg = "준호썸\n{$오늘}\n일방신청";
    }
    $msg .= "\n\n위 내용을 메모지에 작성 하거나,\n손바닥에 적은 뒤 얼굴 나오게 셀카1장 찍어줘! 도용,합성인지 확인하는것이니 얼굴은 전체 다 나오게! 확인 후 바로 삭제할게!\n\n인증이 끝나면 메모지는 잘 찢어 버리자!\n\n첫 일방 1회만 본인인증";
    echo 전송($msg);
    exit;
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

/** 퇴근 등록 직전: 채굴 적립 확정 후 정지 (tb_work insert 전에 호출) */
if (!function_exists('회원_퇴근_채굴정지')) {
  function 회원_퇴근_채굴정지($nick) {
    require_once __DIR__ . '/game/mining_sync.inc.php';
    if (function_exists('mining_pause_on_퇴근')) {
      mining_pause_on_퇴근($nick);
    }
  }
}

/** 출근 직전(tb_work 삭제 전): 퇴근 기간 채굴 미적립 확정 */
if (!function_exists('회원_출근_채굴재개')) {
  function 회원_출근_채굴재개($nick) {
    require_once __DIR__ . '/game/mining_sync.inc.php';
    if (function_exists('mining_resume_on_출근')) {
      mining_resume_on_출근($nick);
    }
  }
}

/** 회원 퇴사·삭제 시 홀짝 로그·집계·상태 전부 제거 */
if (!function_exists('회원_홀짝_전체삭제')) {
  function 회원_홀짝_전체삭제($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
      return ['ok' => false];
    }
    $nick_esc = addslashes($nick);
    @db_query("DELETE FROM tb_odd_even_log WHERE nick = '{$nick_esc}'");
    @db_query("DELETE FROM tb_odd_even_profit WHERE nick = '{$nick_esc}'");
    @db_query("DELETE FROM tb_odd_even_state WHERE nick = '{$nick_esc}'");
    return ['ok' => true];
  }
}

/**
 * 회원 퇴사·삭제 시 색표 장터 소유권 해제
 * - 같은 색에 남은 착용자가 있으면 그 사람에게 소유권 넘김
 * - 없으면 무주인(선점 가능)으로 되돌림
 */
if (!function_exists('회원_색표장터_퇴사해제')) {
  function 회원_색표장터_퇴사해제($nick) {
    $nick = trim((string)$nick);
    if ($nick === '') {
      return ['ok' => false, 'released' => []];
    }
    $esc = addslashes($nick);

    $lib = '';
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
      $lib = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\') . '/page/_color_market_lib.php';
    }
    if ($lib === '' || !is_file($lib)) {
      $lib = dirname(__DIR__) . '/page/_color_market_lib.php';
    }
    if (is_file($lib)) {
      include_once $lib;
      if (function_exists('cm_테이블보장')) {
        cm_테이블보장();
      }
    }

    $tbl = @db_select("SHOW TABLES LIKE 'tb_color_market'");
    if (empty($tbl)) {
      return ['ok' => false, 'released' => []];
    }

    $nums = [];
    $mem = @db_select("SELECT num FROM tb_member WHERE name = '{$esc}' AND status != 1 LIMIT 1");
    $wear = (int)($mem['num'] ?? 0);
    if ($wear >= 1 && $wear <= 45) {
      $nums[$wear] = true;
    }
    $rs = @db_query("SELECT color_num FROM tb_color_market WHERE owner_nick = '{$esc}'");
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $n = (int)($row['color_num'] ?? 0);
        if ($n >= 1 && $n <= 45) {
          $nums[$n] = true;
        }
      }
    }

    $released = [];
    foreach (array_keys($nums) as $n) {
      $n = (int)$n;
      if ($n === 1 || (function_exists('cm_예약색인가') && cm_예약색인가($n))) {
        if (function_exists('cm_예약색_장터반영')) {
          cm_예약색_장터반영();
        }
        continue;
      }
      $remain = [];
      $wrs = @db_query("SELECT name FROM tb_member WHERE status != 1 AND num = {$n} AND name != '{$esc}' ORDER BY idx ASC");
      if ($wrs) {
        while ($w = db_fetch($wrs)) {
          $wn = trim((string)($w['name'] ?? ''));
          if ($wn !== '' && !in_array($wn, $remain, true)) {
            $remain[] = $wn;
          }
        }
      }
      if (!empty($remain)) {
        $new_esc = addslashes($remain[0]);
        @db_query("UPDATE tb_color_market SET owner_nick = '{$new_esc}', updated_at = NOW()
          WHERE color_num = {$n} AND owner_nick = '{$esc}' LIMIT 1");
      } else {
        @db_query("UPDATE tb_color_market
          SET owner_nick = '', price = 0, listed = 0, updated_at = NOW()
          WHERE color_num = {$n} LIMIT 1");
        $released[] = $n;
      }
    }
    return ['ok' => true, 'released' => $released];
  }
}

/** 채굴에 무기 장착 중이면 .보호 차단 (.시전은 자동 장착) */
if (!function_exists('채굴_무기장착_차단문구')) {
  function 채굴_무기장착_차단문구($닉) {
    if (!function_exists('mining_weapon_equipped')) {
      require_once __DIR__ . '/game/mining_weapon.inc.php';
    }
    if (!mining_weapon_equipped($닉)) {
      return '';
    }
    return "❌ 채굴에 무기를 장착 중이에요.\n채굴 화면에서 해제 후 .보호를 사용할 수 있어요.";
  }
}

/**
 * .시전 시 해제(mount=0) 또는 채굴 결합 중이면 자동 장착.
 * 장착 해제는 .내무기로만 수동.
 * @return bool 이번에 장착으로 바꿨으면 true
 */
if (!function_exists('무기_시전전_자동장착')) {
  function 무기_시전전_자동장착($닉, $정보 = null): bool {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return false;
    }
    if (!is_array($정보) || !array_key_exists('item', $정보)) {
      $esc = addslashes($닉);
      $정보 = db_select("SELECT item, enhance, IFNULL(mount, 1) AS mount FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    }
    $item = trim((string)($정보['item'] ?? ''));
    if ($item === '') {
      return false;
    }

    $mw = __DIR__ . '/game/mining_weapon.inc.php';
    if (!function_exists('mining_weapon_sync_from_mount') && is_file($mw)) {
      require_once $mw;
    }

    $mount = (int)($정보['mount'] ?? 1);
    $채굴장착 = function_exists('mining_weapon_equipped') && mining_weapon_equipped($닉);
    if ($mount === 1 && !$채굴장착) {
      return false;
    }

    $esc = addslashes($닉);
    db_query("UPDATE tb_member SET mount = 1 WHERE name = '{$esc}' LIMIT 1");
    if (function_exists('mining_weapon_sync_from_mount')) {
      mining_weapon_sync_from_mount($닉, 1);
    }
    return true;
  }
}

/** 단소·활·마법 일일 한도 외 시전 상한 (지정 시전 포함, extra_uses 기준). 마법 .보호도 동일 카운터 공용 */

/**
 * 마법 .시전·.보호 무료 한도 공용화
 * - 사용량·구간창: magic_used / magic_window
 * - 한도외: extra_uses (구 protect_extra_uses 잔여 흡수)
 * - 구 protect_used 가 아직 구간 안이면 magic_used 에 흡수 후 0
 * @return array{used:int,window:?string}
 */
if (!function_exists('마법_시전보호_공용한도_동기화')) {
  function 마법_시전보호_공용한도_동기화(string $닉, int $쿨타임시간 = 1): array {
    $esc = addslashes(trim($닉));
    $행 = @db_select("SELECT magic_used, magic_window, IFNULL(extra_uses, 0) AS extra_uses, extra_reset_date FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    $지금 = time();
    $쿨초 = max(1, $쿨타임시간) * 3600;
    $magic_used = is_array($행) ? (int)($행['magic_used'] ?? 0) : 0;
    $magic_window = is_array($행) ? ($행['magic_window'] ?? null) : null;
    $protect_used = 0;
    $protect_window = null;
    $보호사용행 = @db_select("SELECT IFNULL(protect_used, 0) AS protect_used, protect_reset_date FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    if (is_array($보호사용행) && array_key_exists('protect_used', $보호사용행)) {
      $protect_used = (int)($보호사용행['protect_used'] ?? 0);
      $protect_window = $보호사용행['protect_reset_date'] ?? null;
    }

    $magic_active = ($magic_window !== null && $magic_window !== '' && $지금 < strtotime((string)$magic_window) + $쿨초);
    $protect_active = ($protect_window !== null && $protect_window !== '' && $지금 < strtotime((string)$protect_window) + $쿨초);

    if (!$magic_active) {
      $db_used = is_array($행) ? (int)($행['magic_used'] ?? 0) : 0;
      $magic_used = 0;
      $magic_window = null;
      // 한도를 다 안 써도 1시간 지나면 사용량 초기화
      if ($db_used !== 0) {
        @db_query("UPDATE tb_member SET magic_used = 0 WHERE name = '{$esc}'");
      }
    }
    if (!$protect_active) {
      $protect_used = 0;
    }

    // 구 분리 한도 → 공용 흡수 (보호만 썼던 구간도 시전 창에 합침)
    if ($protect_active && $protect_used > 0) {
      if (!$magic_active) {
        $magic_window = $protect_window;
        $magic_used = $protect_used;
        $magic_active = true;
      } else {
        $magic_used += $protect_used;
        if (strtotime((string)$protect_window) < strtotime((string)$magic_window)) {
          $magic_window = $protect_window;
        }
      }
      $mw_sql = ($magic_window !== null && $magic_window !== '')
        ? "'" . addslashes((string)$magic_window) . "'"
        : 'NOW()';
      @db_query("UPDATE tb_member SET magic_used = {$magic_used}, magic_window = {$mw_sql} WHERE name = '{$esc}'");
      @db_query("UPDATE tb_member SET protect_used = 0 WHERE name = '{$esc}'");
      $protect_used = 0;
    } elseif (!$magic_active) {
      $magic_used = 0;
      $magic_window = null;
    }

    // 구 보호 한도외 컬럼이 있으면 extra_uses 로 흡수
    $오늘 = date('Y-m-d');
    $보호과다행 = @db_select("SELECT IFNULL(protect_extra_uses, 0) AS protect_extra_uses, protect_extra_reset_date FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    if (is_array($보호과다행) && array_key_exists('protect_extra_uses', $보호과다행)) {
      $보호과다 = (int)($보호과다행['protect_extra_uses'] ?? 0);
      $보호과다리셋 = trim((string)($보호과다행['protect_extra_reset_date'] ?? ''));
      if ($보호과다 > 0 && $보호과다리셋 !== '' && $보호과다리셋 >= $오늘) {
        $과다 = is_array($행) ? (int)($행['extra_uses'] ?? 0) : 0;
        $과다리셋 = is_array($행) ? trim((string)($행['extra_reset_date'] ?? '')) : '';
        if ($과다리셋 === '' || $과다리셋 < $오늘) {
          $과다 = 0;
        }
        $합 = $과다 + $보호과다;
        @db_query("UPDATE tb_member SET extra_uses = {$합}, extra_reset_date = CURDATE(), protect_extra_uses = 0 WHERE name = '{$esc}'");
      }
    }

    return [
      'used' => (int)$magic_used,
      'window' => ($magic_window !== null && $magic_window !== '') ? (string)$magic_window : null,
    ];
  }
}

/**
 * 시전 1시간 구간.
 * - 첫 시전 시각부터 1시간
 * - 한도(강화×2)를 다 안 써도 1시간이 지나면 used=0 초기화
 * - 한도를 넘어도 창을 다시 시작하지 않음 (리셋 시각 고정)
 *
 * @param bool $시전시작 true면 만료/없음일 때 새 창을 NOW()로 연다
 * @return array{used:int,window:?string,reset_at:int,active:bool}
 */
if (!function_exists('무기_시전구간_적용')) {
  function 무기_시전구간_적용($닉, int $쿨타임시간 = 1, bool $시전시작 = false): array {
    $esc = addslashes(trim((string)$닉));
    $쿨초 = max(1, $쿨타임시간) * 3600;
    $행 = ($esc !== '')
      ? @db_select("SELECT magic_used, magic_window FROM tb_member WHERE name = '{$esc}' LIMIT 1")
      : null;
    $used = is_array($행) ? (int)($행['magic_used'] ?? 0) : 0;
    $window = is_array($행) ? ($행['magic_window'] ?? null) : null;
    $window = ($window !== null && $window !== '') ? (string)$window : null;
    $지금 = time();
    $시작ts = $window !== null ? (int)strtotime($window) : 0;
    $active = ($window !== null && $시작ts > 0 && $지금 < $시작ts + $쿨초);

    if (!$active) {
      $used = 0;
      if ($시전시작 && $esc !== '') {
        $window = date('Y-m-d H:i:s', $지금);
        $win_esc = addslashes($window);
        @db_query("UPDATE tb_member SET magic_used = 0, magic_window = '{$win_esc}' WHERE name = '{$esc}' LIMIT 1");
        return [
          'used' => 0,
          'window' => $window,
          'reset_at' => $지금 + $쿨초,
          'active' => true,
        ];
      }
      if ($esc !== '' && is_array($행) && (int)($행['magic_used'] ?? 0) !== 0) {
        @db_query("UPDATE tb_member SET magic_used = 0 WHERE name = '{$esc}' LIMIT 1");
      }
      return [
        'used' => 0,
        'window' => null,
        'reset_at' => 0,
        'active' => false,
      ];
    }

    return [
      'used' => $used,
      'window' => $window,
      'reset_at' => $시작ts + $쿨초,
      'active' => true,
    ];
  }
}

/** 마법 무기 여부 (시전·보호 공용 한도용) */
if (!function_exists('마법_시전보호_공용무기인가')) {
  function 마법_시전보호_공용무기인가($무기명): bool {
    $t = trim((string)$무기명);
    if ($t === '🪄마법' || $t === '🪄 마법') {
      return true;
    }
    return function_exists('무기_마법인가') ? (bool)무기_마법인가($무기명) : false;
  }
}

/**
 * 마법 시전+보호 합산 무료 한도 (각 한도를 더함 → 단독의 2배)
 * 예: 구간 한도 10 → 공용 20 (.보호 18 + .시전 2 가능)
 */
if (!function_exists('마법_시전보호_공용_무료한도')) {
  function 마법_시전보호_공용_무료한도($무기명, int $강화단계): int {
    if (!마법_시전보호_공용무기인가($무기명) || !function_exists('무기_시전한도_3시간')) {
      return function_exists('무기_시전한도_3시간') ? (int)무기_시전한도_3시간($무기명, $강화단계) : 0;
    }
    $단독 = (int)무기_시전한도_3시간($무기명, $강화단계);
    return $단독 > 0 ? ($단독 * 2) : 0;
  }
}

if (!function_exists('무기_한도외_일일상한')) {
  /**
   * @param string $무기명 optional · 종류 최고강화자면 기본한도 ×2
   * @param string $닉 optional
   * @return int|null null=무제한(레거시 · 현재 미사용)
   */
  function 무기_한도외_일일상한($강화단계, $무기명 = '', $닉 = '') {
    unset($강화단계, $무기명, $닉);
    return 0;
  }
}

/**
 * 시전 방어 시 보호 차감량 (보유 보호 한도 내)
 * - 일반: 공격자 강화 N 기준 mt_rand(N, N×2)  (예: +50 → 50~100)
 * - 크리티컬: 최대치 N×2 고정
 * - 최소 N 보장 (상대 보호가 부족하면 남은 수치만큼만)
 */
if (!function_exists('무기_시전_보호차감량')) {
  function 무기_시전_보호차감량($공격자강화, $보호수, $크리티컬 = false) {
    $보호수 = max(0, (int)$보호수);
    if ($보호수 <= 0) {
      return 0;
    }
    $강 = max(0, (int)$공격자강화);
    if ($강 <= 0) {
      return 0;
    }
    $최소 = $강;
    $최대 = $강 * 2;
    if ($크리티컬) {
      $기본 = $최대;
    } else {
      $기본 = mt_rand($최소, $최대);
    }
    return min($기본, $보호수);
  }
}

/**
 * 마법 .보호 1한도당 지급량
 * - 일반 60%: N ~ N×2  (예: +30 → 30~60)
 * - 크리티컬 40%: N ~ N×3 (예: +30 → 30~90)
 */
if (!function_exists('마법_보호_크리티컬인가')) {
  function 마법_보호_크리티컬인가(): bool {
    return mt_rand(1, 100) <= 40;
  }
}

if (!function_exists('마법_보호_지급량')) {
  function 마법_보호_지급량($강화단계, $크리티컬 = false): int {
    $강 = max(0, (int)$강화단계);
    if ($강 <= 0) {
      return 0;
    }
    $최소 = $강;
    $최대 = $크리티컬 ? ($강 * 3) : ($강 * 2);
    return mt_rand($최소, $최대);
  }
}

if (!function_exists('마법_보호_한도별_지급합계')) {
  /**
   * @return array{amount:int, crits:int, uses:int}
   */
  function 마법_보호_한도별_지급합계($강화단계, $한도횟수): array {
    $한도횟수 = max(0, (int)$한도횟수);
    $합 = 0;
    $크 = 0;
    for ($i = 0; $i < $한도횟수; $i++) {
      $크리 = 마법_보호_크리티컬인가();
      if ($크리) {
        $크++;
      }
      $합 += 마법_보호_지급량($강화단계, $크리);
    }
    return ['amount' => $합, 'crits' => $크, 'uses' => $한도횟수];
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

/** tb_work.leavedate — 실제 퇴근 시각 (regdate는 복귀 기한) */
if (!function_exists('tb_work_leavedate_컬럼_보장')) {
  function tb_work_leavedate_컬럼_보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $rs = @db_query("SHOW COLUMNS FROM tb_work LIKE 'leavedate'");
    if ($rs && @db_fetch($rs)) {
      return;
    }
    @db_query("ALTER TABLE tb_work ADD COLUMN `leavedate` DATETIME NULL DEFAULT NULL COMMENT '실제 퇴근 시각' AFTER `nick`");
  }
}

/**
 * 퇴근/장퇴 등록 SQL (leavedate=NOW, regdate=복귀기한)
 */
if (!function_exists('퇴근_등록_쿼리')) {
  function 퇴근_등록_쿼리(string $status, string $nick, string $deadline): string {
    tb_work_leavedate_컬럼_보장();
    $status_esc = addslashes(trim($status));
    $nick_esc = addslashes(trim($nick));
    $deadline_esc = addslashes(trim($deadline));
    return "INSERT INTO tb_work SET status = '{$status_esc}', nick = '{$nick_esc}', leavedate = NOW(), regdate = '{$deadline_esc}'";
  }
}

/** 퇴근 목록 표시용 — 실제 퇴근 시각 (없으면 기한에서 추정) */
if (!function_exists('퇴근_표시시각')) {
  function 퇴근_표시시각(array $row): string {
    $leave = trim((string)($row['leavedate'] ?? ''));
    if ($leave !== '' && strpos($leave, '0000-00-00') !== 0) {
      $ts = strtotime($leave);
      if ($ts !== false) {
        return date('m-d H:i', $ts);
      }
    }
    $deadline = strtotime((string)($row['regdate'] ?? ''));
    if ($deadline === false) {
      return '-';
    }
    $hours = ((string)($row['status'] ?? '') === '장퇴') ? 168 : 48;
    return date('m-d H:i', $deadline - ($hours * 3600));
  }
}

/**
 * 퇴근~현재 경과 초 (leavedate 우선 · legacy는 기한-48/168h)
 */
if (!function_exists('퇴근_경과초')) {
  function 퇴근_경과초(array $workRow): int {
    $leave = trim((string)($workRow['leavedate'] ?? ''));
    $leaveTs = false;
    if ($leave !== '' && strpos($leave, '0000-00-00') !== 0) {
      $leaveTs = strtotime($leave);
    }
    if ($leaveTs === false) {
      $deadline = strtotime((string)($workRow['regdate'] ?? ''));
      if ($deadline === false) {
        return 0;
      }
      $hours = ((string)($workRow['status'] ?? '') === '장퇴') ? 168 : 48;
      $leaveTs = $deadline - ($hours * 3600);
    }
    return max(0, time() - (int)$leaveTs);
  }
}

/**
 * .출근 시 자숙 중이면 퇴근 기간만큼 tb_self.enddate 연장
 * @return array{applied:bool, seconds:int, notice:string, new_end:?string}
 */
if (!function_exists('출근_자숙_퇴근기간연장')) {
  function 출근_자숙_퇴근기간연장($nick, array $workRow): array {
    $empty = ['applied' => false, 'seconds' => 0, 'notice' => '', 'new_end' => null];
    $nick = trim((string)$nick);
    if ($nick === '' || !function_exists('자숙_활성여부') || !자숙_활성여부($nick)) {
      return $empty;
    }
    $sec = 퇴근_경과초($workRow);
    if ($sec < 60) {
      // 1분 미만은 무시
      return $empty;
    }
    $닉_esc = addslashes($nick);
    $sec_i = (int)$sec;
    db_query("
      UPDATE tb_self
      SET enddate = DATE_ADD(enddate, INTERVAL {$sec_i} SECOND)
      WHERE nick = '{$닉_esc}' AND enddate > NOW()
    ");
    $새끝행 = db_select("
      SELECT enddate FROM tb_self
      WHERE nick = '{$닉_esc}' AND enddate > NOW()
      ORDER BY enddate DESC
      LIMIT 1
    ");
    $새끝 = !empty($새끝행['enddate']) ? date('m-d H:i', strtotime($새끝행['enddate'])) : '';
    $기간표시 = function_exists('자숙_초_표시') ? 자숙_초_표시($sec_i) : (round($sec_i / 3600, 1) . '시간');
    $notice = "⚠️ 자숙 중 퇴근 복귀 · 자숙 +{$기간표시}";
    if ($새끝 !== '') {
      $notice .= "\n해제예정: {$새끝}";
    }
    $기록 = "⚠️ {$nick} 자숙 중 퇴근 복귀\n자숙 +{$기간표시}";
    if ($새끝 !== '') {
      $기록 .= "\n해제예정: {$새끝}";
    }
    $기록_esc = addslashes($기록);
    @db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$기록_esc}', leverage = 0, item = '{$닉_esc}', regdate = NOW()");
    return [
      'applied' => true,
      'seconds' => $sec_i,
      'notice' => $notice,
      'new_end' => $새끝 !== '' ? $새끝 : null,
    ];
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

if (!function_exists('무기_시전_쿨타임시간')) {
  function 무기_시전_쿨타임시간(): int {
    global $쿨타임;
    $h = isset($쿨타임) ? (int)$쿨타임 : 1;
    return $h > 0 ? $h : 1;
  }
}

if (!function_exists('무기_한도외_상태')) {
  function 무기_한도외_상태($강화단계, $오늘과다횟수, $무기명 = '', $닉 = '') {
    unset($강화단계, $오늘과다횟수, $무기명, $닉);
    return array('allow_pay' => false, 'exhausted' => true, 'max' => 0, 'remain' => 0, 'unlimited' => false);
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

/**
 * 단소 강탈 비율(% 단위 — 전체냥기준금액에 그대로 넘김)
 * +10~19: 0.001 · +20~29: 0.002 · … · +100: 0.01  (기존 0.0005 계열의 2배)
 */
if (!function_exists('단소_강탈_전체냥비율')) {
  function 단소_강탈_전체냥비율($강화): float {
    $e = (int)$강화;
    if ($e < 10) {
      return 0.0;
    }
    $band = (int)floor($e / 10); // 1..10 (+10→1 … +100→10)
    if ($band > 10) {
      $band = 10;
    }
    return $band * 0.001;
  }
}

if (!function_exists('단소_강탈금액')) {
  /**
   * 단소 강탈액 — 전체 게임냥 × (band×0.001%)
   * = 전체 × band ÷ 100000 (정수·bcmath, float/(int) 금지)
   * 총량은 무기복구_전체게임냥_확보(폴백 포함) 우선.
   * @return string
   */
  function 단소_강탈금액($강화) {
    $e = (int)$강화;
    if ($e < 10) {
      return '0';
    }
    $band = (int)floor($e / 10);
    if ($band > 10) {
      $band = 10;
    }
    if ($band <= 0) {
      return '0';
    }

    $전체 = '0';
    if (function_exists('무기복구_전체게임냥_확보')) {
      $전체 = 냥_정수문자열(무기복구_전체게임냥_확보());
    } elseif (function_exists('시세기준_게임냥_문자열')) {
      $전체 = 냥_정수문자열(시세기준_게임냥_문자열());
    }
    if ($전체 === '0') {
      // 총량 확보 실패 시에만 최소 1 (상대 보유 min 에서 다시 잘림)
      return '1';
    }

    // ceil(전체 * band / 100000) — bcmath 없어도 문자열 곱·나눗셈 (서버 bcmath=no 대응)
    if (function_exists('냥_금액_문자열곱') && function_exists('냥_문자열나눗셈올림')) {
      $분자 = 냥_금액_문자열곱($전체, (string)$band);
      $금액 = 냥_문자열나눗셈올림($분자, '100000');
      if ($금액 === '0') {
        $금액 = '1';
      }
      return $금액;
    }
    if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bcmod') && function_exists('bcadd') && function_exists('bccomp')) {
      $분자 = bcmul($전체, (string)$band, 0);
      $금액 = bcdiv($분자, '100000', 0);
      if (bccomp(bcmod($분자, '100000'), '0', 0) > 0) {
        $금액 = bcadd($금액, '1', 0);
      }
      if (bccomp($금액, '1', 0) < 0) {
        $금액 = '1';
      }
      return $금액;
    }
    if (function_exists('전체냥기준금액')) {
      return 냥_정수문자열(전체냥기준금액($band * 0.001));
    }
    return '1';
  }
}

/**
 * 무기 시전 희귀 보너스 공통 — 0.7% 확률로 지정 팟의 1% 획득
 * @param string $source 'vault'(금고) | 'lotto'(로또누적)
 * @return array{ok:bool,msg:string,금고:string,로또:string,합:string}
 */
if (!function_exists('무기_시전_희귀팟보너스')) {
  function 무기_시전_희귀팟보너스(string $nick, string $source, string $weaponLabel, string $logTag): array {
    $empty = ['ok' => false, 'msg' => '', '금고' => '0', '로또' => '0', '합' => '0'];
    $nick = trim($nick);
    $source = ($source === 'lotto') ? 'lotto' : 'vault';
    if ($nick === '') {
      return $empty;
    }
    // 0.7% = 7/1000
    if (mt_rand(1, 1000) > 7) {
      return $empty;
    }

    if ($source === 'lotto' && !function_exists('로또누적_조회')) {
      $lottoPath = __DIR__ . '/game/lotto_amount.inc.php';
      if (is_file($lottoPath)) {
        require_once $lottoPath;
      }
    }

    $금고획득 = '0';
    $로또획득 = '0';

    if ($source === 'vault') {
      $금고잔액 = function_exists('금고_잔액_조회') ? 금고_잔액_조회() : '0';
      if (function_exists('냥_비율내림')) {
        $금고획득 = 냥_정수문자열(냥_비율내림($금고잔액, 0.01));
      } elseif (function_exists('bcmul') && function_exists('bcdiv')) {
        $금고획득 = bcdiv(bcmul(냥_정수문자열($금고잔액), '1', 0), '100', 0);
      }
      if (function_exists('bccomp') && bccomp($금고획득, 냥_정수문자열($금고잔액), 0) > 0) {
        $금고획득 = 냥_정수문자열($금고잔액);
      }
    } else {
      $로또잔액 = function_exists('로또누적_조회') ? 로또누적_조회() : '0';
      if (function_exists('냥_비율내림')) {
        $로또획득 = 냥_정수문자열(냥_비율내림($로또잔액, 0.01));
      } elseif (function_exists('bcmul') && function_exists('bcdiv')) {
        $로또획득 = bcdiv(bcmul(냥_정수문자열($로또잔액), '1', 0), '100', 0);
      }
      if (function_exists('bccomp') && bccomp($로또획득, 냥_정수문자열($로또잔액), 0) > 0) {
        $로또획득 = 냥_정수문자열($로또잔액);
      }
    }

    $합후보 = ($source === 'vault') ? $금고획득 : $로또획득;
    if ($합후보 === '0' || (function_exists('bccomp') && bccomp($합후보, '0', 0) <= 0)) {
      return $empty;
    }

    $nick_esc = addslashes($nick);
    $지급합 = '0';

    if ($source === 'vault') {
      $gSql = function_exists('냥_SQL정수') ? 냥_SQL정수($금고획득) : preg_replace('/\D/', '', $금고획득);
      if ($gSql !== '' && $gSql !== '0') {
        @db_query("UPDATE config SET tax = tax - {$gSql} WHERE tax >= {$gSql} LIMIT 1");
        global $conn;
        $ok = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
        if ($ok) {
          @db_query("UPDATE tb_member SET point = point + {$gSql} WHERE name = '{$nick_esc}' LIMIT 1");
          $지급합 = $금고획득;
        } else {
          $금고획득 = '0';
        }
      } else {
        $금고획득 = '0';
      }
    } else {
      $실제로또 = function_exists('로또누적_차감') ? 로또누적_차감($로또획득) : '0';
      $실제로또 = 냥_정수문자열($실제로또);
      if ($실제로또 !== '0' && !(function_exists('bccomp') && bccomp($실제로또, '0', 0) <= 0)) {
        $lSql = function_exists('냥_SQL정수') ? 냥_SQL정수($실제로또) : preg_replace('/\D/', '', $실제로또);
        if ($lSql !== '' && $lSql !== '0') {
          @db_query("UPDATE tb_member SET point = point + {$lSql} WHERE name = '{$nick_esc}' LIMIT 1");
          $지급합 = $실제로또;
          $로또획득 = $실제로또;
        } else {
          $로또획득 = '0';
        }
      } else {
        $로또획득 = '0';
      }
    }

    if ($지급합 === '0' || (function_exists('bccomp') && bccomp($지급합, '0', 0) <= 0)) {
      return $empty;
    }

    $로그메모 = ($source === 'vault') ? '금고1%' : '로또1%';
    if (function_exists('지급로그')) {
      지급로그($logTag, $nick, $로그메모, 0, $지급합);
    }

    $표시 = function ($v) {
      if (function_exists('게임냥_안전표시')) {
        return 게임냥_안전표시($v, '냥');
      }
      if (function_exists('냥축약표시')) {
        return 냥축약표시($v);
      }
      return (function_exists('냥_숫자콤마') ? 냥_숫자콤마($v) : $v) . '냥';
    };

    $팟라벨 = ($source === 'vault') ? '금고' : '로또';
    $획득표시 = ($source === 'vault') ? $표시($금고획득) : $표시($로또획득);
    if ($source === 'lotto') {
      $msg = "🎰 희귀! {$weaponLabel} 시전 보너스 (0.7%)\n시전자 {$nick} · 로또 누적에서 {$획득표시} 뺏어 게임냥으로 획득!";
    } else {
      $msg = "🎰 희귀! {$weaponLabel} 시전 보너스 (0.7%)\n시전자 {$nick} · {$팟라벨} {$획득표시} 획득!";
    }

    return [
      'ok' => true,
      'msg' => $msg,
      '금고' => $금고획득,
      '로또' => $로또획득,
      '합' => $지급합,
    ];
  }
}

/** 단소 시전 — 0.7% 확률로 커뮤니티 금고 1% 획득 */
if (!function_exists('단소_시전_희귀팟보너스')) {
  function 단소_시전_희귀팟보너스(string $nick): array {
    return 무기_시전_희귀팟보너스($nick, 'vault', '단소', '단소시전-희귀팟');
  }
}

/** 활 시전 — 0.7% 확률로 로또누적 1% 획득 */
if (!function_exists('활_시전_희귀팟보너스')) {
  function 활_시전_희귀팟보너스(string $nick): array {
    return 무기_시전_희귀팟보너스($nick, 'lotto', '활', '활시전-희귀팟');
  }
}

/**
 * 무기 반사 확률(%). 강화 1당 0.15 → +1=0.15% · +10=1.5% · +20=3% · +100=15%
 * (방어자 무기 강화 기준)
 */
if (!function_exists('무기_반사확률_퍼센트')) {
  function 무기_반사확률_퍼센트($강화): float {
    $e = max(0, (int)$강화);
    if ($e < 1) {
      return 0.0;
    }
    $maxLv = function_exists('강화_최대') ? (int)강화_최대() : 100;
    if ($e > $maxLv) {
      $e = $maxLv;
    }
    return $e * 0.15;
  }
}

/** 방어자 강화 기준 반사 판정 */
if (!function_exists('무기_반사판정')) {
  function 무기_반사판정($방어자강화): bool {
    $pct = 무기_반사확률_퍼센트($방어자강화);
    if ($pct <= 0) {
      return false;
    }
    // 0.01% 단위 (0.15% = 15)
    $확률만분율 = (int)round($pct * 100);
    if ($확률만분율 <= 0) {
      return false;
    }
    if ($확률만분율 >= 10000) {
      return true;
    }
    return mt_rand(1, 10000) <= $확률만분율;
  }
}

/**
 * 한도 외 시전 기준 = 1타 상당 기본냥(0.02)을 실시간 스왑율로 게임냥 환산
 * 회당 동일 단가 (등차 없음) · 보유 게임냥에서 차감
 */
if (!function_exists('무기_한도외_타수기본냥')) {
  function 무기_한도외_타수기본냥(): float {
    // 1타 = 0.02 기본냥 → 스왑 게임냥 (구 0.2 · 삭감 후 한도외 과다 완화)
    return 0.02;
  }
}

if (!function_exists('무기_한도외_기준단가_문자열')) {
  function 무기_한도외_기준단가_문자열(): string {
    $np = 무기_한도외_타수기본냥();
    $swapFile = __DIR__ . '/game/swap.inc.php';
    if (is_file($swapFile)) {
      require_once $swapFile;
    }
    if (function_exists('스왑_총량조회') && function_exists('스왑_np2pt_지급계산')) {
      $총 = 스왑_총량조회();
      $total_np = $총['total_np'] ?? 0;
      $total_pt = $총['total_pt'] ?? '0';
      if (function_exists('스왑_게임냥_조정')) {
        $total_pt = 스왑_게임냥_조정($total_pt);
      }
      $s = 스왑_np2pt_지급계산($np, $total_pt, $total_np);
      $s = function_exists('냥_정수문자열') ? 냥_정수문자열($s) : (ltrim(preg_replace('/\D/', '', (string)$s), '0') ?: '0');
      if ($s !== '0') {
        return $s;
      }
    }
    if (function_exists('우리방_실시간_스왑율')) {
      $rate = (string)우리방_실시간_스왑율();
      if (function_exists('bcmul') && $rate !== '' && $rate !== '0') {
        $s = bcmul($rate, sprintf('%.4F', $np), 0);
        $s = ltrim((string)$s, '0') ?: '0';
        if ($s !== '0') {
          return $s;
        }
      }
    }
    // 스왑 불가 시 기존 % 시세 폴백
    $fb = function_exists('전체냥기준금액') ? 전체냥기준금액(0.000001) : 0;
    return function_exists('냥_정수문자열') ? 냥_정수문자열($fb) : (string)max(0, (int)$fb);
  }
}

/** 단소·활·마법·보호 한도 외 시전 기준 단가 (1타→게임냥 환산, 가능하면 int) */
if (!function_exists('무기_한도외_기준단가')) {
  function 무기_한도외_기준단가() {
    $s = 무기_한도외_기준단가_문자열();
    if (function_exists('bccomp') && bccomp($s, (string)PHP_INT_MAX, 0) > 0) {
      return $s;
    }
    return (int)$s;
  }
}

/** 활·마법·보호 한도 외 시전 1회 비용 = 기준단가 고정 (오늘 횟수와 무관) */
if (!function_exists('무기_한도외_시전비용')) {
  function 무기_한도외_시전비용($오늘과다횟수 = 0) {
    unset($오늘과다횟수); // 호환용 인자 · 단가에 미사용
    $base = 무기_한도외_기준단가_문자열();
    if (function_exists('bccomp') && bccomp($base, (string)PHP_INT_MAX, 0) > 0) {
      return $base;
    }
    return (int)$base;
  }
}

/** 한도 외 N회 총 비용 = 기준단가 × N (등차 없음) */
if (!function_exists('무기_한도외_시전총비용')) {
  function 무기_한도외_시전총비용($오늘과다횟수, $횟수) {
    unset($오늘과다횟수); // 호환용 인자 · 단가에 미사용
    $횟수 = (int)$횟수;
    if ($횟수 <= 0) {
      return 0;
    }
    $기준 = 무기_한도외_기준단가_문자열();
    if (function_exists('bcmul')) {
      $sum = bcmul($기준, (string)$횟수, 0);
      $sum = ltrim((string)$sum, '0') ?: '0';
      if (function_exists('bccomp') && bccomp($sum, (string)PHP_INT_MAX, 0) > 0) {
        return $sum;
      }
      return (int)$sum;
    }
    return (int)$기준 * $횟수;
  }
}

/** 한도외 비용 SQL용 숫자 문자열 */
if (!function_exists('무기_한도외_비용sql')) {
  function 무기_한도외_비용sql($비용): string {
    if (function_exists('냥_정수문자열')) {
      return 냥_정수문자열($비용);
    }
    return ltrim(preg_replace('/\D/', '', (string)$비용), '0') ?: '0';
  }
}

/** 보유 게임냥이 한도외 비용 이상인지 */
if (!function_exists('무기_한도외_비용충분')) {
  function 무기_한도외_비용충분($보유, $비용): bool {
    $a = 무기_한도외_비용sql($보유);
    $b = 무기_한도외_비용sql($비용);
    if ($b === '0') {
      return true;
    }
    if (function_exists('bccomp')) {
      return bccomp($a, $b, 0) >= 0;
    }
    return ((float)$a) >= ((float)$b);
  }
}

/** 한도외 비용이 0보다 큰지 */
if (!function_exists('무기_한도외_비용양수')) {
  function 무기_한도외_비용양수($비용): bool {
    return 무기_한도외_비용sql($비용) !== '0';
  }
}

/**
 * 한도 외 시전 비용 분배 — 차감액의 50% 소멸 · 35% 금고 · 15% 로또
 * @return array{소멸:string|int,금고:string|int,로또:string|int}
 */
if (!function_exists('무기_한도외_비용분배')) {
  function 무기_한도외_비용분배($금액, $닉 = '', $로그유형 = '한도외시전') {
    $금액s = function_exists('냥_정수문자열')
      ? 냥_정수문자열($금액)
      : (ltrim(preg_replace('/\D/', '', (string)$금액), '0') ?: '0');
    if ($금액s === '0') {
      return ['소멸' => 0, '금고' => 0, '로또' => 0];
    }
    if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bcsub')) {
      $금고 = bcdiv(bcmul($금액s, '35', 0), '100', 0);
      $로또 = bcdiv(bcmul($금액s, '15', 0), '100', 0);
      $소멸 = bcsub(bcsub($금액s, $금고, 0), $로또, 0);
      $금고sql = preg_replace('/\D/', '', $금고) ?: '0';
      $로또sql = preg_replace('/\D/', '', $로또) ?: '0';
      if ($금고sql !== '0') {
        db_query("UPDATE config SET tax = tax + {$금고sql}");
      }
      if ($로또sql !== '0') {
        $amountInc = __DIR__ . '/game/lotto_amount.inc.php';
        if (is_file($amountInc)) {
          require_once $amountInc;
        }
        if (function_exists('로또누적_가산')) {
          로또누적_가산($로또sql);
        } else {
          $guards = __DIR__ . '/game/odd_even_guards.php';
          if (is_file($guards)) {
            require_once $guards;
          }
          if (function_exists('홀짝_로또수수료_적립')) {
            홀짝_로또수수료_적립($로또sql, trim((string)$닉) !== '' ? ('한도외:' . trim((string)$닉)) : '한도외시전');
          }
        }
      }
      $금고로또합 = bcadd($금고, $로또, 0);
      if (function_exists('지급로그') && $금고로또합 !== '0') {
        지급로그((string)$로그유형, (string)$닉, '', $금고로또합, $금액s);
      }
      return ['소멸' => $소멸, '금고' => $금고, '로또' => $로또];
    }
    $금액 = max(0, (int)$금액s);
    if ($금액 <= 0) {
      return ['소멸' => 0, '금고' => 0, '로또' => 0];
    }
    $금고 = (int)floor($금액 * 35 / 100);
    $로또 = (int)floor($금액 * 15 / 100);
    $소멸 = $금액 - $금고 - $로또;
    if ($금고 > 0) {
      db_query("UPDATE config SET tax = tax + {$금고}");
    }
    if ($로또 > 0) {
      $amountInc = __DIR__ . '/game/lotto_amount.inc.php';
      if (is_file($amountInc)) {
        require_once $amountInc;
      }
      if (function_exists('로또누적_가산')) {
        로또누적_가산((string)$로또);
      } else {
        $guards = __DIR__ . '/game/odd_even_guards.php';
        if (is_file($guards)) {
          require_once $guards;
        }
        if (function_exists('홀짝_로또수수료_적립')) {
          홀짝_로또수수료_적립($로또, trim((string)$닉) !== '' ? ('한도외:' . trim((string)$닉)) : '한도외시전');
        }
      }
    }
    if (function_exists('지급로그') && ($금고 + $로또) > 0) {
      지급로그((string)$로그유형, (string)$닉, '', $금고 + $로또, $금액);
    }
    return ['소멸' => $소멸, '금고' => $금고, '로또' => $로또];
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

/** 단소·활·마법 연속 시전 1회 명령 상한 = 1시간 시전 한도 */
if (!function_exists('무기_한도외_배치상한')) {
  function 무기_한도외_배치상한($강화단계, $무기명 = '') {
    $강화단계 = (int)$강화단계;
    if ($강화단계 < 1) {
      return null;
    }
    $무기명 = trim((string)$무기명);
    if ($무기명 === '') {
      $무기명 = function_exists('무기_표시아이템') ? 무기_표시아이템(2, 0) : '🪈단소';
    }
    if (!function_exists('무기_시전한도_3시간')) {
      return null;
    }
    $한도 = (int)무기_시전한도_3시간($무기명, $강화단계);
    if ($한도 <= 0) {
      return null;
    }
    if (function_exists('마법_시전보호_공용무기인가') && 마법_시전보호_공용무기인가($무기명)) {
      return $한도 * 2;
    }
    return $한도;
  }
}

/** 단소·활·마법 연속 시전 시 한도 외 포함 횟수가 배치 상한을 넘는지 검증 */
if (!function_exists('무기_시전_한도외배치_검증')) {
  /**
   * @param string $모드 '시전'(magic_used) | '보호'(protect_used)
   */
  function 무기_시전_한도외배치_검증($내무기, $강화단계, $시전횟수, $시전자닉, $쿨타임시간 = 1, $모드 = '시전') {
    $무기_trim = trim((string)$내무기);
    $시전횟수 = (int)$시전횟수;
    if ($시전횟수 <= 1) {
      return array('ok' => true);
    }
    $구간한도 = (function_exists('마법_시전보호_공용무기인가') && 마법_시전보호_공용무기인가($내무기) && function_exists('마법_시전보호_공용_무료한도'))
      ? 마법_시전보호_공용_무료한도($내무기, (int)$강화단계)
      : (function_exists('무기_시전한도_3시간') ? 무기_시전한도_3시간($내무기, (int)$강화단계) : 0);
    if ($구간한도 <= 0) {
      return array('ok' => true);
    }
    if ($시전횟수 <= $구간한도) {
      return array('ok' => true);
    }
    $무기표시맵 = array(
      '🪈단소' => '단소',
      '🏹활' => '활',
      '🏹 활' => '활',
      '🪄마법' => '마법',
      '🪄 마법' => '마법',
    );
    $무기표시 = $무기표시맵[$무기_trim] ?? ((function_exists('무기_타입값') && function_exists('무기_타입키')) ? (무기_타입키(무기_타입값($무기_trim)) ?: '무기') : '무기');
    $창 = max(1, (int)$쿨타임시간);
    if ($모드 === '보호') {
      $예 = "예) .보호 닉네임 {$구간한도}";
      $행위 = '보호';
    } else {
      $예 = "예) .시전 {$구간한도}";
      $행위 = '시전';
    }
    return array(
      'ok' => false,
      'msg' => "❌ +{$강화단계} {$무기표시} {$행위}은 {$창}시간에 최대 {$구간한도}회예요.\n" . $예,
      'batch_max' => $구간한도,
    );
  }
}

/** 한도 외 냥 차감 직후 안내 (사용횟수/상한). 보호·단소·활·마법 공통 */
if (!function_exists('무기_한도외_사용표시문구')) {
  function 무기_한도외_사용표시문구($강화단계, $오늘사용횟수, $무기명 = '', $닉 = '') {
    $오늘사용횟수 = (int)$오늘사용횟수;
    $상태 = 무기_한도외_상태($강화단계, $오늘사용횟수, $무기명, $닉);
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
    $기존 = db_select("SELECT attendance, newpoint, CAST(point AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
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
    // 마이너스 보유(🆘신불자)도 그대로 살려야 함 — 부호와 절댓값을 분리해서 계산
    $게임냥Raw = trim((string)($기존['point'] ?? '0'));
    $게임냥Str = function_exists('냥_정수문자열')
      ? 냥_정수문자열($게임냥Raw)
      : (ltrim(preg_replace('/\D/', '', $게임냥Raw), '0') ?: '0');
    $게임냥음수 = ($게임냥Str !== '0' && isset($게임냥Raw[0]) && $게임냥Raw[0] === '-');
    $기본차감 = (int)floor($기본냥 * 0.01);
    if (function_exists('냥_비율내림')) {
      $게임차감Str = 냥_정수문자열(냥_비율내림($게임냥Str, 0.01));
    } elseif (function_exists('bcdiv')) {
      $게임차감Str = bcdiv($게임냥Str, '100', 0);
    } else {
      $게임차감Str = (string)(int)floor(((float)$게임냥Str) / 100.0);
    }
    if ($게임차감Str === '' || $게임차감Str === '0') {
      $게임차감Str = '0';
    }
    // 마이너스 보유면 빚이 1% 더 늘어남 (절댓값 증가)
    $게임후Str = $게임냥음수
      ? 냥_금액_문자열합($게임냥Str, $게임차감Str)
      : 냥_금액_문자열차감($게임냥Str, $게임차감Str);
    $게임후음수 = ($게임냥음수 && $게임후Str !== '0');

    $게임표시 = function ($v, $음수 = false) {
      if (function_exists('랭킹_게임냥표시')) {
        $본문 = 랭킹_게임냥표시($v, '냥');
      } elseif (function_exists('냥_경조_축약표시')) {
        $본문 = 냥_경조_축약표시($v, '냥');
      } else {
        $본문 = number_format((float)$v) . '냥';
      }
      return $음수 ? ('-' . $본문) : $본문;
    };

    $set = ['attendance = 2'];
    if ($기본차감 > 0) {
      $set[] = "newpoint = newpoint - {$기본차감}";
    }
    if ($게임차감Str !== '0') {
      if (!preg_match('/^\d+$/', $게임차감Str)) {
        $게임차감Str = '0';
      } else {
        // GREATEST(0, ...) 금지 — 마이너스 보유가 0으로 리셋돼 🆘신불자가 풀려버림
        $set[] = "point = CAST(point AS DECIMAL(65,0)) - CAST('{$게임차감Str}' AS DECIMAL(65,0))";
      }
    }
    db_query("UPDATE tb_member SET " . implode(', ', $set) . " WHERE name = '{$닉_esc}'");

    $차감문구 = '';
    if ($기본차감 > 0) {
      $차감문구 .= "\n기본냥 -" . newpoint표시($기본차감) . "냥 (보유 1%)"
        . "\n" . newpoint표시($기본냥) . "냥 → " . newpoint표시($기본냥 - $기본차감) . "냥";
    }
    if ($게임차감Str !== '0') {
      $로그금액 = (function_exists('bccomp') && bccomp($게임차감Str, (string)PHP_INT_MAX, 0) > 0)
        ? PHP_INT_MAX
        : (int)$게임차감Str;
      지급로그('출석포기|게임냥', $닉, $닉, 0, $로그금액);
      $비율라벨 = $게임냥음수 ? '마이너스 1% 추가' : '보유 1%';
      $차감문구 .= "\n게임냥 -" . $게임표시($게임차감Str) . " ({$비율라벨})"
        . "\n" . $게임표시($게임냥Str, $게임냥음수) . " → " . $게임표시($게임후Str, $게임후음수);
      if ($게임냥음수) {
        $차감문구 .= "\n🆘 마이너스 보유 — 신불자 상태 유지";
      }
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
    $s = trim((string)$아이템sname);
    $제외 = ['공커대실권', '일방신청권', '일방연장권'];
    if (in_array($s, $제외, true)) {
      return true;
    }
    return function_exists('아이템_본방시총가_대상인가') && 아이템_본방시총가_대상인가($s);
  }
}

/** 정상 회원 전체 게임냥(point) 합계 — 항상 정수 문자열 (PHP_INT_MAX·922경 잘림 금지) */
if (!function_exists('아이템_총게임냥')) {
  /** @return string */
  function 아이템_총게임냥() {
    return function_exists('시세기준_게임냥_문자열')
      ? 시세기준_게임냥_문자열()
      : 냥_정수문자열(시세기준_게임냥());
  }
}

/**
 * 구매가 억 단위 절사 — 억 아래 자릿수 버림 (표시·실제 차감 공통) · 항상 문자열
 * · 1억 이상: 억 단위로 내림 (예: 1억5천만 → 1억)
 * · 1억 미만: 0으로 버리지 않고 원가 유지 (0.000001% 등 소액 시세 허용)
 */
if (!function_exists('아이템_구매단가_억절사')) {
  /** @param int|string $금액 @return string */
  function 아이템_구매단가_억절사($금액) {
    $s = 냥_정수문자열($금액);
    if ($s === '0') {
      return '0';
    }
    if (function_exists('bccomp') && function_exists('bcdiv') && function_exists('bcmul')) {
      if (bccomp($s, '100000000', 0) < 0) {
        return $s;
      }
      $억수 = bcdiv($s, '100000000', 0);
      return bcmul($억수, '100000000', 0);
    }
    // bcmath 없을 때만 — 922경 초과는 문자열 유지 불가하므로 자릿수 절삭
    if (strlen($s) < 9) {
      return $s;
    }
    return substr($s, 0, strlen($s) - 8) . '00000000';
  }
}

/**
 * tb_item.percent — 전체 총 게임냥 100% 중 해당 아이템 비율
 * 예) 총게임냥 100, percent 50 → 구매가 50
 * 계산 후 억 단위 절사(1억 미만은 원가 유지) — 48조4,370억26백… → 실제 48조4,370억 차감
 * percent 미설정(0 이하)이면 buy 컬럼 fallback
 * @return string 항상 정수 문자열 (PHP int 캐스팅·922경 상한 금지)
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
        // 0.000001% 등 — 소수 18자리까지 반영
        $원가 = bcdiv(bcmul($총, sprintf('%.18F', $percent), 18), '100', 0);
      } else {
        // float 경로는 15자리 한계 — bcmath 없는 환경 폴백
        $원가 = 냥_정수문자열((string)(int)round((float)$총 * $percent / 100));
      }
      return 아이템_구매단가_억절사($원가);
    }
    return 냥_정수문자열($itemRow['buy'] ?? 0);
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
  /** @return string */
  function 아이템_시세_조회_by_sname(string $sname): string {
    $sname_esc = addslashes(trim($sname));
    if ($sname_esc === '') {
      return '0';
    }
    $row = db_select("SELECT buy, percent FROM tb_item WHERE sname = '{$sname_esc}' AND buystatus = 0 LIMIT 1");
    if (empty($row)) {
      return '0';
    }
    return 냥_정수문자열(아이템_구매단가_계산($row));
  }
}

if (!function_exists('아이템_구매시세_단가')) {
  /** @return string 항상 문자열 — `: int` 반환 시 PHP_INT_MAX(≈922경)로 잘림 */
  function 아이템_구매시세_단가(string $itemName, array $itemRow): string {
    if ($itemName === '일방신청권') {
      return 냥_정수문자열(전체냥기준금액(0.01, true));
    }
    if ($itemName === '일방연장권') {
      return 냥_정수문자열(냥_앞두자리_뒤0(전체냥기준금액(0.01, true) * 7));
    }
    return 냥_정수문자열(아이템_구매단가_계산($itemRow));
  }
}

/** `.구매` 실행(아이템 구매) / `.판매` 차단 — `.구매` 시세표만 허용 · false로 바꾸면 복구 */
if (!defined('ITEM_BUY_SELL_CHAT_BLOCKED')) {
  define('ITEM_BUY_SELL_CHAT_BLOCKED', true);
}
if (!function_exists('아이템_구매판매_채팅_차단중')) {
  function 아이템_구매판매_채팅_차단중(): bool {
    return defined('ITEM_BUY_SELL_CHAT_BLOCKED') && ITEM_BUY_SELL_CHAT_BLOCKED;
  }
}
if (!function_exists('아이템_구매판매_채팅_차단_메시지')) {
  function 아이템_구매판매_채팅_차단_메시지(): string {
    return "❌ .판매는 현재 이용할 수 없습니다.";
  }
}
if (!function_exists('아이템_구매실행_차단_메시지')) {
  function 아이템_구매실행_차단_메시지(): string {
    return "❌ 지금은 아이템 구매가 중단됐어요.\n시세만 보려면 `.구매` 를 입력해 주세요.";
  }
}
/** `.구매` 만(시세표) · `.구매 아이템` (단가조회)이면 false, `.구매 아이템 수량` 실행이면 true */
if (!function_exists('아이템_구매실행_요청인가')) {
  function 아이템_구매실행_요청인가($status): bool {
    $t = trim((string)$status);
    if ($t === '' || !preg_match('/^\.구매\b/u', $t)) {
      return false;
    }
    // `.구매` 만 → 시세표
    if (preg_match('/^\.구매\s*$/u', $t)) {
      return false;
    }
    // `.구매 아이템` (수량 없음) → 단가 조회
    if (preg_match('/^\.구매\s+[^\s]+\s*$/u', $t)) {
      return false;
    }
    // `.구매 아이템 수량`
    return (bool)preg_match('/^\.구매\s+[^\s]+\s+\d+\s*$/u', $t);
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
      $ba = 냥_정수문자열($a['buy'] ?? 0);
      $bb = 냥_정수문자열($b['buy'] ?? 0);
      if (function_exists('bccomp')) {
        return -bccomp($ba, $bb, 0);
      }
      return strlen($bb) <=> strlen($ba) ?: ($bb <=> $ba);
    });

    $시세_msg = "🛒 구매 가능 아이템\n\n";
    foreach ($시세_목록 as $시세_item) {
      $시세_msg .= $시세_item['name'] . ' : ' . 구매가_축약표시($시세_item['buy'], $단위) . "<br>";
    }
    $시세_msg .= "\n※ 지금은 시세 조회만 가능 · 구매는 잠시 중단";
    $시세_msg .= "\n예) .구매 (시세표)";
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
  function 아이템_상점구매_실행($두자리닉넴, array $정보, $아이템명, $구매수량, $강제구매 = false) {
    global $단위;

    if (!$강제구매 && function_exists('아이템_구매판매_채팅_차단중') && 아이템_구매판매_채팅_차단중()) {
      return [
        'ok' => false,
        'msg' => function_exists('아이템_구매실행_차단_메시지')
          ? 아이템_구매실행_차단_메시지()
          : '❌ 지금은 아이템 구매가 중단됐어요.',
      ];
    }

    $아이템명 = trim((string)$아이템명);
    $구매수량 = max(1, (int)$구매수량);
    if ($아이템명 === '') {
      return ['ok' => false, 'msg' => '❌ 아이템명을 확인해주세요.'];
    }
    if (function_exists('아이템주식_본방시총가_정규sname')) {
      $아이템명 = 아이템주식_본방시총가_정규sname($아이템명);
    }
    if (function_exists('아이템_본방시총가_대상인가') && 아이템_본방시총가_대상인가($아이템명)) {
      $disp = function_exists('아이템_가방_표시명') ? 아이템_가방_표시명($아이템명) : $아이템명;
      return ['ok' => false, 'msg' => "❌ {$disp} 구매 불가 · 보유자만 팔 수 있어요."];
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
      $커플 = function_exists('공커_대실권_구매대상_조회')
        ? 공커_대실권_구매대상_조회($구매닉)
        : (function_exists('공커_활성_조회')
          ? 공커_활성_조회($구매닉)
          : db_select("SELECT * FROM tb_couple WHERE status = 0 AND couple LIKE '%{$닉_esc}%' ORDER BY idx DESC LIMIT 1"));
      if (empty($커플['idx'])) {
        $활성 = function_exists('공커_활성_조회') ? 공커_활성_조회($구매닉) : null;
        if (!empty($활성['idx'])) {
          return ['ok' => false, 'msg' => "❌ [{$구매닉}] 공커 자숙(.추가 공커) 중이라 대실권을 구매할 수 없어요."];
        }
        return ['ok' => false, 'msg' => '❌ 활성 공커만 공커대실권을 구매할 수 있어요.'];
      }
      $커플_amount = (int)($커플['amount'] ?? 0);
      if ($커플_amount < 1) {
        return ['ok' => false, 'msg' => '❌ 가격이 설정되지 않아 공커대실권을 구매할 수 없습니다.'];
      }
    }

    if ($아이템명 === '공커대실권') {
      $구매단가 = function_exists('아이템주식_공커대실권_단가')
        ? 아이템주식_공커대실권_단가($커플)
        : (string)(int)(ceil($커플_amount / 1000) * 1000);
      $총구매액 = function_exists('bcmul') ? bcmul($구매단가, (string)$구매수량, 0) : (string)((int)$구매단가 * $구매수량);
    } elseif ($아이템명 === '일방신청권') {
      $구매단가 = 냥_정수문자열(전체냥기준금액(0.01, true));
      $총구매액 = function_exists('bcmul') ? bcmul($구매단가, (string)$구매수량, 0) : (string)((int)$구매단가 * $구매수량);
    } elseif ($아이템명 === '일방연장권') {
      $구매단가 = 냥_정수문자열(냥_앞두자리_뒤0(전체냥기준금액(0.01, true) * 7));
      $총구매액 = function_exists('bcmul') ? bcmul($구매단가, (string)$구매수량, 0) : (string)((int)$구매단가 * $구매수량);
    } else {
      $구매단가 = 냥_정수문자열(아이템_구매단가_계산($구매아이템행));
      if ($구매단가 === '0' && 아이템_percent_시세여부($구매아이템행)) {
        return ['ok' => false, 'msg' => "❌ 현재 총 게임냥 기준 시세가 없어 {$아이템명}을(를) 구매할 수 없어요."];
      }
      $총구매액 = function_exists('bcmul') ? bcmul($구매단가, (string)$구매수량, 0) : (string)((int)$구매단가 * $구매수량);
    }

    $소량추가금 = '0';
    if ($구매수량 < 10) {
      if (function_exists('bcdiv') && function_exists('bcmul')) {
        $소량추가금 = bcdiv($총구매액, '100', 0); // 1% 내림 — ceil 대체(거액)
        if ($소량추가금 === '0' && $총구매액 !== '0') {
          $소량추가금 = '1';
        }
      } else {
        $소량추가금 = (string)(int)ceil((float)$총구매액 * 0.01);
      }
      $총구매액 = function_exists('bcadd') ? bcadd($총구매액, $소량추가금, 0) : (string)((int)$총구매액 + (int)$소량추가금);
    }

    $내idx = (int)($정보['idx'] ?? 0);
    if ($내idx <= 0) {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 찾을 수 없어요.'];
    }

    $보유행 = db_select("SELECT CAST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)) AS CHAR) AS point FROM tb_member WHERE idx = {$내idx} LIMIT 1");
    $보유원본 = (string)($보유행['point'] ?? '0');
    $보유음수 = (isset($보유원본[0]) && $보유원본[0] === '-' && ltrim(substr($보유원본, 1), '0') !== '');
    $보유냥 = 냥_정수문자열($보유원본);
    $부족 = $보유음수 || (function_exists('bccomp')
      ? (bccomp($보유냥, $총구매액, 0) < 0)
      : ((float)$보유냥 < (float)$총구매액));
    if ($부족) {
      if ($아이템명 === '공커대실권') {
        $단가표시 = (string)(int)(ceil($커플_amount / 1000) * 1000);
      } elseif ($아이템명 === '일방신청권') {
        $단가표시 = 냥_정수문자열(전체냥기준금액(0.01, true));
      } elseif ($아이템명 === '일방연장권') {
        $단가표시 = 냥_정수문자열(냥_앞두자리_뒤0(전체냥기준금액(0.01, true) * 7));
      } else {
        $단가표시 = 냥_정수문자열(아이템_구매단가_계산($구매아이템행));
      }
      $보유표시 = $보유음수
        ? ('-' . 구매가_축약표시($보유냥, $단위))
        : 구매가_축약표시($보유냥, $단위);
      $음수안내 = $보유음수 ? "\n※ 마이너스 게임냥 상태에서는 구매할 수 없어요." : '';
      return [
        'ok' => false,
        'msg' => "‼️보유 {$단위} 부족!\n{$아이템명} : " . 구매가_축약표시($총구매액, $단위)
          . " (단가 " . 구매가_축약표시($단가표시, $단위) . " × {$구매수량})\n현재 보유 : {$보유표시}{$음수안내}",
      ];
    }

    if ($아이템명 === '지호') {
      $지호보유한도 = 100000;
      $지호보유개수 = item_bag_qty($내idx, '지호');
      if ($지호보유개수 + $구매수량 > $지호보유한도) {
        $더구매가능 = max(0, $지호보유한도 - $지호보유개수);
        return [
          'ok' => false,
          'msg' => "❌ 지호 아이템은 최대 " . number_format($지호보유한도) . "개까지만 보유할 수 있어요.\n"
            . "현재 보유: " . number_format($지호보유개수) . "개 · 이번 구매 {$구매수량}개는 불가 (추가로 최대 " . number_format($더구매가능) . "개까지 구매 가능)",
        ];
      }
    }

    $가방적립 = true;
    if ($아이템명 === '공커대실권') {
      if (!empty($커플['idx']) && !empty($커플['edate'])) {
        $연장기간 = 7 * $구매수량;
        $칠일연장 = date('Y-m-d', strtotime($커플['edate'] . " +{$연장기간} days"));
        db_query("UPDATE tb_couple SET edate = '{$칠일연장}' WHERE idx = {$커플['idx']}");
      }
      $가방적립 = false; // 즉시 사용 처리(가방 미적립)
    } elseif ($아이템명 === '일방신청권') {
      $가방적립 = true;
    } elseif ($아이템명 === '일방연장권') {
      $가방적립 = true;
      $progress = db_select("SELECT * FROM tb_progress WHERE nick LIKE '%{$닉_esc}%'");
      if (!empty($progress['idx'])) {
        $progress['enddate'] = date('Y-m-d', strtotime($progress['enddate'] . ' +7 days'));
        $닉등록 = $progress['nick'] . '1️⃣';
        db_query("UPDATE tb_progress SET enddate = '{$progress['enddate']}', nick = '{$닉등록}' WHERE idx = {$progress['idx']}");
        $가방적립 = false;
      }
    }

    if ($가방적립) {
      $지급sname = trim((string)$구매아이템행['sname']);
      $bagAdd = item_bag_add($내idx, $구매닉, $지급sname, $구매수량);
      if (empty($bagAdd['ok'])) {
        return ['ok' => false, 'msg' => '❌ 아이템 지급 실패: ' . ($bagAdd['msg'] ?? '가방 오류')];
      }
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
        미션완료_기록_if_new($구매닉, '일방', '200타');
      }
    }
    $총구매액_sql = preg_replace('/[^\d]/', '', (string)$총구매액) ?: '0';
    db_query("UPDATE tb_member SET point = point - {$총구매액_sql} WHERE idx = {$내idx}");
    if (function_exists('아이템구매_시세상승')) {
      아이템구매_시세상승($아이템명, $구매단가, $구매수량);
    }
    if (function_exists('item_trade_log_구매')) {
      item_trade_log_구매($구매닉, $아이템명, $구매수량, $구매단가, $총구매액, $소량추가금, $내idx, 'web', '', 'point');
    }

    $잔여행 = db_select("SELECT CAST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)) AS CHAR) AS point FROM tb_member WHERE idx = {$내idx} LIMIT 1");
    $수량표시 = ($구매수량 > 1) ? " {$구매수량}개" : '';
    $추가금문구 = (냥_정수문자열($소량추가금) !== '0')
      ? "\n(10개 미만 1% 추가금: " . 구매가_축약표시($소량추가금, $단위) . ")"
      : '';

    return [
      'ok' => true,
      'msg' => "►[{$구매닉}] {$아이템명}{$수량표시} 구매 " . 구매가_축약표시($총구매액, $단위) . $추가금문구,
      '구매단가' => $구매단가,
      '총구매액' => $총구매액,
      '구매수량' => $구매수량,
      '소량추가금' => $소량추가금,
      'point' => 냥_정수문자열($잔여행['point'] ?? 0),
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
        if (function_exists('아이템주식_기념주화인가') && 아이템주식_기념주화인가($name)) {
          continue;
        }
        if ($name === '꼬벙기념주화' || $name === '1주년기념주화' || $name === '꼬병기념주화') {
          continue;
        }
        $price = 아이템_구매시세_단가($name, $row);
        $목록[] = [
          'name' => $name,
          'price' => $price,
          'price_fmt' => function_exists('구매가_축약표시') ? 구매가_축약표시($price, $단위) : (냥_정수문자열($price) . $단위),
          'buyable' => !(냥_정수문자열($price) === '0' && 아이템_percent_시세여부($row)),
        ];
      }
    }
    usort($목록, static function ($a, $b) {
      $ba = 냥_정수문자열($a['price'] ?? 0);
      $bb = 냥_정수문자열($b['price'] ?? 0);
      if (function_exists('bccomp')) {
        return -bccomp($ba, $bb, 0);
      }
      return strlen($bb) <=> strlen($ba) ?: ($bb <=> $ba);
    });
    return $목록;
  }
}

/**
 * 아이템 상점 교환: 일방신청권 5 + 일방연장권 5 → 은총 1
 * @return array{ok:bool,msg?:string,times?:int}
 */
if (!function_exists('아이템_교환_일방_은총_실행')) {
  function 아이템_교환_일방_은총_실행(string $닉, array $정보, int $횟수 = 1): array {
    $닉 = trim($닉);
    $횟수 = max(1, min(99, (int)$횟수));
    $midx = (int)($정보['idx'] ?? 0);
    if ($닉 === '' || $midx < 1) {
      return ['ok' => false, 'msg' => '❌ 회원 정보를 확인할 수 없어요.'];
    }
    if (!function_exists('item_bag_qty') || !function_exists('item_bag_sub') || !function_exists('item_bag_add')) {
      return ['ok' => false, 'msg' => '❌ 가방 기능을 불러올 수 없어요.'];
    }
    if (!function_exists('bag_은총_가산') && is_file(__DIR__ . '/item_bag_enhance.inc.php')) {
      require_once __DIR__ . '/item_bag_enhance.inc.php';
    }
    if (!function_exists('bag_은총_가산')) {
      return ['ok' => false, 'msg' => '❌ 은총 지급 기능을 불러올 수 없어요.'];
    }

    $필요신청 = 5 * $횟수;
    $필요연장 = 5 * $횟수;
    $신청보유 = (int)item_bag_qty($midx, '일방신청권');
    $연장보유 = (int)item_bag_qty($midx, '일방연장권');
    if ($신청보유 < $필요신청 || $연장보유 < $필요연장) {
      return [
        'ok' => false,
        'msg' => "❌ 재료가 부족해요.\n필요: 일방신청권 {$필요신청} · 일방연장권 {$필요연장}\n보유: 일방신청권 {$신청보유} · 일방연장권 {$연장보유}",
      ];
    }

    $차감1 = item_bag_sub($midx, $닉, '일방신청권', $필요신청);
    if (empty($차감1['ok'])) {
      return ['ok' => false, 'msg' => '❌ 일방신청권 차감에 실패했어요. ' . trim((string)($차감1['msg'] ?? ''))];
    }
    $차감2 = item_bag_sub($midx, $닉, '일방연장권', $필요연장);
    if (empty($차감2['ok'])) {
      item_bag_add($midx, $닉, '일방신청권', $필요신청);
      return ['ok' => false, 'msg' => '❌ 일방연장권 차감에 실패했어요. ' . trim((string)($차감2['msg'] ?? ''))];
    }
    $지급 = bag_은총_가산($닉, $횟수);
    if (empty($지급['ok'])) {
      item_bag_add($midx, $닉, '일방신청권', $필요신청);
      item_bag_add($midx, $닉, '일방연장권', $필요연장);
      return ['ok' => false, 'msg' => '❌ 은총 지급에 실패했어요. ' . trim((string)($지급['msg'] ?? ''))];
    }

    return [
      'ok' => true,
      'times' => $횟수,
      'msg' => "✨ 교환 완료!\n일방신청권 {$필요신청}개 + 일방연장권 {$필요연장}개 → 은총 {$횟수}개",
    ];
  }
}

/** 교환 탭 표시용 */
if (!function_exists('아이템_교환_목록')) {
  function 아이템_교환_목록(array $정보): array {
    $midx = (int)($정보['idx'] ?? 0);
    $신청 = ($midx > 0 && function_exists('item_bag_qty')) ? (int)item_bag_qty($midx, '일방신청권') : 0;
    $연장 = ($midx > 0 && function_exists('item_bag_qty')) ? (int)item_bag_qty($midx, '일방연장권') : 0;
    $은총 = 0;
    $닉 = trim((string)($정보['name'] ?? ''));
    if ($닉 !== '' && function_exists('bag_은총_수량')) {
      $은총 = (int)bag_은총_수량($닉);
    } elseif ($midx > 0 && function_exists('item_bag_qty')) {
      $은총 = (int)item_bag_qty($midx, '은총');
    }
    $가능횟수 = min((int)floor($신청 / 5), (int)floor($연장 / 5));

    $조각 = 0;
    $조각환율 = 10;
    $조각최대 = 500;
    if (!function_exists('mining_ore_shard_count') && is_file(__DIR__ . '/game/mining_ore.inc.php')) {
      require_once __DIR__ . '/game/mining_config.inc.php';
      require_once __DIR__ . '/game/mining_storage.inc.php';
      require_once __DIR__ . '/game/mining_ore.inc.php';
    }
    if ($닉 !== '' && function_exists('mining_ore_shard_count')) {
      $조각 = (int)mining_ore_shard_count($닉);
    }
    if (defined('MINING_ORE_EUNCHONG_EXCHANGE')) {
      $조각환율 = max(1, (int)MINING_ORE_EUNCHONG_EXCHANGE);
    }
    if (defined('MINING_ORE_EUNCHONG_SHARDS')) {
      $조각최대 = max(1, (int)MINING_ORE_EUNCHONG_SHARDS);
    }
    $조각가능 = (int)floor($조각 / $조각환율);
    $역교환성공률 = 60;
    if (defined('MINING_ORE_EUNCHONG_TO_SHARD_SUCCESS_PCT')) {
      $역교환성공률 = max(1, min(100, (int)MINING_ORE_EUNCHONG_TO_SHARD_SUCCESS_PCT));
    }
    $역교환가능 = ($은총 >= 1 && ($조각 + $조각환율) <= $조각최대);

    return [
      [
        'id' => 'shard_to_eunchong',
        'title' => '은총조각 → 은총',
        'desc' => "은총조각 {$조각환율}개 → 은총 1개 (1회 1개씩)",
        'cost' => [
          ['name' => '은총조각', 'qty' => $조각환율, 'have' => $조각, 'max' => $조각최대],
        ],
        'reward' => ['name' => '은총', 'qty' => 1, 'have' => $은총],
        'can_exchange' => $조각가능 >= 1,
        'max_times' => $조각가능 >= 1 ? 1 : 0,
        'fixed_times' => 1,
      ],
      [
        'id' => 'eunchong_to_shard',
        'title' => '은총 → 은총조각',
        'desc' => "은총 1개 → 은총조각 {$조각환율}개 · 성공 {$역교환성공률}% (실패 시 은총 소멸)",
        'cost' => [
          ['name' => '은총', 'qty' => 1, 'have' => $은총],
        ],
        'reward' => ['name' => '은총조각', 'qty' => $조각환율, 'have' => $조각, 'max' => $조각최대],
        'can_exchange' => $역교환가능,
        'max_times' => $역교환가능 ? 1 : 0,
        'fixed_times' => 1,
        'risky' => true,
        'success_pct' => $역교환성공률,
      ],
      [
        'id' => 'ilbang_to_eunchong',
        'title' => '일방권 → 은총',
        'desc' => '일방신청권 5개 + 일방연장권 5개 → 은총 1개',
        'cost' => [
          ['name' => '일방신청권', 'qty' => 5, 'have' => $신청],
          ['name' => '일방연장권', 'qty' => 5, 'have' => $연장],
        ],
        'reward' => ['name' => '은총', 'qty' => 1, 'have' => $은총],
        'can_exchange' => $가능횟수 >= 1,
        'max_times' => max(0, $가능횟수),
      ],
    ];
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
        $row['sell_price'] = $quote['실수령'] ?? 0;
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

/** 보유 아이템 (tb_member_item_bag) 집계 */
if (!function_exists('아이템_보유목록_집계')) {
  function 아이템_보유목록_집계(int $midx): array {
    return item_bag_list($midx);
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
    if (function_exists('아이템주식_본방시총가_정규sname')) {
      $아이템명 = 아이템주식_본방시총가_정규sname($아이템명);
    }

    $보유 = item_bag_qty($midx, $아이템명);
    if ($보유 < $수량) {
      return ['ok' => false, 'msg' => "{$아이템명} 보유수량 {$보유}개"];
    }

    if (function_exists('아이템_본방시총가_대상인가') && 아이템_본방시총가_대상인가($아이템명)) {
      $단가 = 아이템_본방시총가_단가($아이템명);
      if ($단가 === '0' || $단가 === '') {
        return ['ok' => false, 'msg' => '❌ 본방냥 시총을 읽지 못했어요.'];
      }
      $실수령 = function_exists('냥_금액_문자열곱')
        ? 냥_금액_문자열곱($단가, (string)$수량)
        : (function_exists('bcmul') ? bcmul($단가, (string)$수량, 0) : $단가);
      $fmt = function_exists('아이템판매_금액표시')
        ? 아이템판매_금액표시($실수령, 'newpoint')
        : $실수령;
      return [
        'ok' => true,
        '아이템명' => $아이템명,
        '수량' => $수량,
        '판매금액' => $실수령,
        '수수료' => '0',
        '수수료율' => 0,
        '실수령' => $실수령,
        '지급컬럼' => 'newpoint',
        '실수령_fmt' => $fmt,
        '수수료_fmt' => 아이템판매_금액표시('0', 'newpoint'),
      ];
    }

    $item = db_select("SELECT * FROM tb_item WHERE sname = '" . addslashes($아이템명) . "' LIMIT 1");
    if (empty($item['idx'])) {
      return ['ok' => false, 'msg' => "❌ [ {$아이템명} ] 존재하지 않는 아이템이에요."];
    }

    $basePrice = (int)($item['sell'] ?? 0) * $수량;

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
    $수수료 = $견적['수수료'] ?? 0;
    $수수료뺀금액 = $견적['실수령'] ?? 0;
    $지급컬럼 = preg_replace('/[^a-z]/', '', strtolower((string)($견적['지급컬럼'] ?? 'point'))) ?: 'point';
    $닉 = trim((string)($정보['name'] ?? $두자리닉넴));

    $bagSub = item_bag_sub($midx, $닉, $아이템명, $수량);
    if (empty($bagSub['ok'])) {
      return ['ok' => false, 'msg' => $bagSub['msg'] ?? '❌ 판매 처리 중 오류 (보유 수량 불일치). 다시 시도해주세요.'];
    }

    $닉_esc = addslashes($닉);
    $지급Sql = function_exists('냥_SQL정수') ? 냥_SQL정수($수수료뺀금액) : preg_replace('/\D/', '', (string)$수수료뺀금액);
    $수수료Sql = function_exists('냥_SQL정수') ? 냥_SQL정수($수수료) : preg_replace('/\D/', '', (string)$수수료);
    if (!preg_match('/^\d+$/', (string)$지급Sql)) {
      item_bag_add($midx, $닉, $아이템명, $수량);
      return ['ok' => false, 'msg' => '❌ 지급 금액을 확인하지 못했어요.'];
    }
    if (!preg_match('/^\d+$/', (string)$수수료Sql)) {
      $수수료Sql = '0';
    }
    db_query("UPDATE tb_member SET {$지급컬럼} = IFNULL({$지급컬럼}, 0) + {$지급Sql} WHERE name = '{$닉_esc}' LIMIT 1");
    if ($수수료Sql !== '0') {
      db_query("UPDATE config SET tax = tax + {$수수료Sql}");
    }
    지급로그('판매', $두자리닉넴, $아이템명, $수수료, $수수료뺀금액);
    if (function_exists('item_trade_log_판매')) {
      $판매금액 = (int)($견적['판매금액'] ?? ($수수료 + $수수료뺀금액));
      item_trade_log_판매($닉, $아이템명, $수량, $판매금액, $수수료, $수수료뺀금액, $midx, 'web', '', $지급컬럼);
    }

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
    return ['1주년기념주화', '꼬벙기념주화'];
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
        if (function_exists('아이템_본방시총가_대상인가') && 아이템_본방시총가_대상인가($itemName)) {
          $live = 아이템_본방시총가_단가($itemName);
          $목록[] = [
            'name' => $itemName,
            'price' => $live,
            'price_fmt' => 아이템판매_금액표시($live, 'newpoint'),
            'unit' => '본방냥',
          ];
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

/** .생성 — 회원 또는 전체(status=0)에게 아이템 지급 (가방 추적 아이템은 bag, 그 외는 tb_member_item) */
if (!function_exists('아이템_생성_레거시행추가')) {
  function 아이템_생성_레거시행추가(int $midx, string $nick, string $템명, int $개수): void {
    $nick_esc = addslashes($nick);
    $템_esc = addslashes($템명);
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
  }
}

if (!function_exists('아이템_생성_회원지급')) {
  /**
   * @return array{ok:bool,msg?:string,shard?:int,added?:int}
   */
  function 아이템_생성_회원지급(int $midx, string $nick, string $템명, int $개수): array {
    $템명 = trim($템명);
    $nick = trim($nick);
    $개수 = (int)$개수;
    if ($midx < 1 || $nick === '' || $템명 === '' || $개수 < 1) {
      return ['ok' => false, 'msg' => '지급 정보가 올바르지 않아요.'];
    }

    // 은총조각 — tb_member_mining.mining_eunchong_shard (가방/레거시 아이템 아님)
    $템정규 = preg_replace('/^✨/u', '', $템명);
    if ($템정규 === '은총조각') {
      $orePath = __DIR__ . '/game/mining_ore.inc.php';
      if (!function_exists('mining_ore_add_shards') && is_file($orePath)) {
        require_once $orePath;
      }
      if (!function_exists('mining_ore_add_shards')) {
        return ['ok' => false, 'msg' => '은총조각 지급 함수를 불러올 수 없어요.'];
      }
      $r = mining_ore_add_shards($nick, $개수);
      if (empty($r['ok'])) {
        return ['ok' => false, 'msg' => (string)($r['data'] ?? '은총조각 지급에 실패했어요.')];
      }
      $added = (int)($r['added'] ?? 0);
      $shard = (int)($r['shard'] ?? 0);
      $max = defined('MINING_ORE_EUNCHONG_SHARDS') ? (int)MINING_ORE_EUNCHONG_SHARDS : 500;
      if ($added < 1) {
        return [
          'ok' => true,
          'added' => 0,
          'shard' => $shard,
          'msg' => "은총조각 한도 도달 (보유 {$shard}/{$max})",
        ];
      }
      $cappedNote = !empty($r['capped']) ? " · 한도 {$max}개까지" : '';
      return [
        'ok' => true,
        'added' => $added,
        'shard' => $shard,
        'msg' => "은총조각 {$added}개 생성완료 (보유 {$shard}/{$max}){$cappedNote}",
      ];
    }

    // 가방 추적 아이템(색변·수호 등)은 bag에만 가산 — 가방/사용 명령이 bag 기준
    if (function_exists('item_bag_tracked') && item_bag_tracked($템명) && function_exists('item_bag_add')) {
      $r = item_bag_add($midx, $nick, $템명, $개수);
      if (empty($r['ok'])) {
        return ['ok' => false, 'msg' => (string)($r['msg'] ?? '가방 지급에 실패했어요.')];
      }
      return ['ok' => true];
    }
    if (function_exists('item_bag_ensure_column') && function_exists('item_bag_add')) {
      // tb_item에 없어도 관리자가 명시한 템명이면 bag 컬럼을 만들어 지급
      if (item_bag_ensure_column($템명)) {
        $r = item_bag_add($midx, $nick, $템명, $개수);
        if (!empty($r['ok'])) {
          return ['ok' => true];
        }
      }
    }
    아이템_생성_레거시행추가($midx, $nick, $템명, $개수);
    return ['ok' => true];
  }
}

if (!function_exists('아이템_생성_회원회수')) {
  /**
   * .생성 … -N → 보유 아이템 회수(삭제)
   * @return array{ok:bool,msg?:string,removed?:int,left?:int}
   */
  function 아이템_생성_회원회수(int $midx, string $nick, string $템명, int $개수): array {
    $템명 = trim($템명);
    $nick = trim($nick);
    $개수 = (int)$개수;
    if ($midx < 1 || $nick === '' || $템명 === '' || $개수 < 1) {
      return ['ok' => false, 'msg' => '회수 정보가 올바르지 않아요.'];
    }

    $템정규 = preg_replace('/^✨/u', '', $템명);
    if ($템정규 === '은총조각') {
      $orePath = __DIR__ . '/game/mining_ore.inc.php';
      if (!function_exists('mining_ore_spend_shards') && is_file($orePath)) {
        require_once $orePath;
      }
      if (!function_exists('mining_ore_spend_shards')) {
        return ['ok' => false, 'msg' => '은총조각 회수 함수를 불러올 수 없어요.'];
      }
      $r = mining_ore_spend_shards($nick, $개수);
      if (empty($r['ok'])) {
        return ['ok' => false, 'msg' => (string)($r['data'] ?? '은총조각이 부족해요.')];
      }
      $removed = (int)($r['spent'] ?? $개수);
      $left = (int)($r['shard'] ?? 0);
      $max = defined('MINING_ORE_EUNCHONG_SHARDS') ? (int)MINING_ORE_EUNCHONG_SHARDS : 500;
      return [
        'ok' => true,
        'removed' => $removed,
        'left' => $left,
        'msg' => "은총조각 {$removed}개 회수완료 (보유 {$left}/{$max})",
      ];
    }

    if (!function_exists('item_bag_sub') && is_file(__DIR__ . '/item_bag.inc.php')) {
      require_once __DIR__ . '/item_bag.inc.php';
    }

    // 가방 추적 아이템
    if (function_exists('item_bag_tracked') && item_bag_tracked($템명) && function_exists('item_bag_sub')) {
      $보유 = function_exists('item_bag_qty') ? (int)item_bag_qty($midx, $템명) : 0;
      if ($보유 < $개수) {
        return [
          'ok' => false,
          'msg' => $보유 < 1
            ? "{$템명} 없음!"
            : "{$템명} 보유 {$보유}개 · 요청 {$개수}개",
          'left' => $보유,
        ];
      }
      $r = item_bag_sub($midx, $nick, $템명, $개수);
      if (empty($r['ok'])) {
        return ['ok' => false, 'msg' => (string)($r['msg'] ?? "{$템명} 회수 실패"), 'left' => (int)($r['qty'] ?? $보유)];
      }
      return [
        'ok' => true,
        'removed' => $개수,
        'left' => (int)($r['qty'] ?? max(0, $보유 - $개수)),
        'msg' => "{$템명} {$개수}개 회수완료 (남은 " . (int)($r['qty'] ?? max(0, $보유 - $개수)) . '개)',
      ];
    }

    // 레거시 tb_member_item
    $nick_esc = addslashes($nick);
    $템_esc = addslashes($템명);
    $보유행 = db_select("
      SELECT COUNT(*) AS cnt FROM tb_member_item
      WHERE nick = '{$nick_esc}' AND itemname = '{$템_esc}' AND status = 0
    ");
    $보유 = (int)($보유행['cnt'] ?? 0);
    if ($보유 < $개수) {
      return [
        'ok' => false,
        'msg' => $보유 < 1
          ? "{$템명} 없음!"
          : "{$템명} 보유 {$보유}개 · 요청 {$개수}개",
        'left' => $보유,
      ];
    }
    $rs = db_query("
      SELECT idx FROM tb_member_item
      WHERE nick = '{$nick_esc}' AND itemname = '{$템_esc}' AND status = 0
      ORDER BY idx ASC
      LIMIT {$개수}
    ");
    $처리 = 0;
    while ($rs && ($row = db_fetch($rs))) {
      $idx = (int)($row['idx'] ?? 0);
      if ($idx > 0 && db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx = {$idx} AND status = 0 LIMIT 1")) {
        $처리++;
      }
    }
    if ($처리 < 1) {
      return ['ok' => false, 'msg' => "{$템명} 회수 실패"];
    }
    return [
      'ok' => true,
      'removed' => $처리,
      'left' => max(0, $보유 - $처리),
      'msg' => "{$템명} {$처리}개 회수완료 (남은 " . max(0, $보유 - $처리) . '개)',
    ];
  }
}

/** `.사용 닉 템명 개수` — 관리자가 해당 닉 가방 아이템 차감. 매칭·관리자면 전송 후 exit. 미해당·비관리자 false */
if (!function_exists('아이템사용처리_명령_처리')) {
  function 아이템사용처리_명령_처리($status, $두자리닉넴 = '', $nick = '', $관리자 = []): bool {
    $본문 = trim((string)$status);
    if ($본문 === '' || preg_match('/^\.사용설명서/u', $본문) || !preg_match('/^\.사용(?:\s|$)/u', $본문)) {
      return false;
    }
    $관리닉 = trim((string)$두자리닉넴);
    if ($관리닉 === '' && $nick !== '' && function_exists('getTwoCharNick')) {
      $관리닉 = getTwoCharNick($nick);
    }
    if (!in_array($관리닉, (array)$관리자, true)) {
      return false;
    }
    if (!preg_match('/^\.사용\s+(\S+)\s+(\S+)\s+(\d+)\s*$/u', $본문, $match)) {
      echo 전송(".사용 (닉네임) (템명) (처리개수)\n예) .사용 치즈 일방신청권 1");
      exit;
    }
    $닉네임 = function_exists('getTwoCharNick') ? getTwoCharNick($match[1]) : trim($match[1]);
    if ($닉네임 === '') {
      $닉네임 = trim($match[1]);
    }
    $템명 = trim($match[2]);
    $개수 = max(1, (int)$match[3]);

    $bag = __DIR__ . '/item_bag.inc.php';
    if (!function_exists('item_bag_sub_nick') && is_file($bag)) {
      require_once $bag;
    }

    if (function_exists('item_bag_sub_nick') && function_exists('item_bag_ensure_column')) {
      item_bag_ensure_column($템명);
      if (function_exists('item_bag_tracked') && item_bag_tracked($템명)) {
        $보유 = function_exists('item_bag_qty_nick') ? (int)item_bag_qty_nick($닉네임, $템명) : 0;
        if ($보유 < $개수) {
          echo 전송($보유 < 1
            ? "{$닉네임} {$템명} 없음!"
            : "{$닉네임} {$템명} 보유 {$보유}개 · 요청 {$개수}개");
          exit;
        }
        $차감 = item_bag_sub_nick($닉네임, $템명, $개수);
        if (empty($차감['ok'])) {
          echo 전송((string)($차감['msg'] ?? "{$닉네임} {$템명} 차감 실패"));
          exit;
        }
        if (function_exists('아이템사용_시세하락')) {
          아이템사용_시세하락($템명, $개수);
        }
        $남음 = isset($차감['qty']) ? (int)$차감['qty'] : max(0, $보유 - $개수);
        echo 전송("{$템명} {$개수}개 처리완료 (남은 {$남음}개)");
        exit;
      }
    }

    $닉_esc = addslashes($닉네임);
    $템_esc = addslashes($템명);
    $data = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE nick = '{$닉_esc}' AND itemname = '{$템_esc}' AND status = 0");
    if ((int)($data['cnt'] ?? 0) === 0) {
      echo 전송("{$닉네임} {$템명} 없음!");
      exit;
    }
    $tem_result = db_query("SELECT * FROM tb_member_item WHERE nick = '{$닉_esc}' AND itemname = '{$템_esc}' AND status = 0 LIMIT {$개수}");
    $처리 = 0;
    while ($tem_result && ($row = db_fetch($tem_result))) {
      if (db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx = {$row['idx']}")) {
        $처리++;
      }
    }
    if ($처리 > 0) {
      if (function_exists('아이템사용_시세하락')) {
        아이템사용_시세하락($템명, $처리);
      }
      echo 전송("{$템명} {$처리}개 처리완료");
      exit;
    }
    echo 전송("{$닉네임} {$템명} 처리 실패");
    exit;
  }
}

if (!function_exists('아이템_생성_지급')) {
  function 아이템_생성_지급($닉네임, $템명, $개수) {
    $개수원본 = (int)$개수;
    $템명 = trim((string)$템명);
    if ($개수원본 === 0 || $템명 === '') {
      return ['ok' => false, 'msg' => '❌ 템명·개수를 확인해주세요. (음수면 회수)'];
    }
    $회수모드 = ($개수원본 < 0);
    $개수 = abs($개수원본);
    $닉_trim = trim((string)$닉네임);

    $관리자닉 = '';
    if (isset($GLOBALS['두자리닉넴'])) {
      $관리자닉 = trim((string)$GLOBALS['두자리닉넴']);
    }

    if ($닉_trim === '전체') {
      $rs = db_query("SELECT idx, name FROM tb_member WHERE status = 0");
      $인원 = 0;
      $총처리 = 0;
      $실패 = 0;
      $템정규전체 = preg_replace('/^✨/u', '', $템명);
      while ($rs && $row = db_fetch($rs)) {
        $midx = (int)($row['idx'] ?? 0);
        $name = trim((string)($row['name'] ?? ''));
        if ($midx <= 0 || $name === '') {
          continue;
        }
        $인원++;
        if ($회수모드) {
          $처리 = 아이템_생성_회원회수($midx, $name, $템명, $개수);
          if (empty($처리['ok'])) {
            $실패++;
            continue;
          }
          $총처리 += max(0, (int)($처리['removed'] ?? $개수));
          continue;
        }
        $지급 = 아이템_생성_회원지급($midx, $name, $템명, $개수);
        if (empty($지급['ok'])) {
          $실패++;
          continue;
        }
        $실지급 = isset($지급['added']) ? (int)$지급['added'] : $개수;
        $총처리 += max(0, $실지급);
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
        return ['ok' => false, 'msg' => '❌ 대상 회원이 없습니다.'];
      }
      $라벨 = ($템정규전체 === '은총조각') ? '은총조각' : $템명;
      if ($회수모드) {
        $msg = "📣 전체 인원 {$인원}명에게 {$라벨} {$개수}개씩 회수 완료! (총 {$총처리}개)";
      } else {
        $msg = "📣 전체 인원 {$인원}명에게 {$라벨} {$개수}개씩 생성 완료! (총 {$총처리}개)";
      }
      if ($실패 > 0) {
        $msg .= "\n⚠️ 실패 {$실패}명";
      }
      return [
        'ok'  => true,
        'msg' => $msg,
      ];
    }

    $닉_esc = addslashes($닉_trim);
    $받는친구 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($받는친구['idx'])) {
      return ['ok' => false, 'msg' => '존재하지 않는 사용자 입니다.'];
    }
    $midx = (int)$받는친구['idx'];

    if ($회수모드) {
      $회수 = 아이템_생성_회원회수($midx, (string)$받는친구['name'], $템명, $개수);
      if (empty($회수['ok'])) {
        return ['ok' => false, 'msg' => '❌ ' . (string)($회수['msg'] ?? '회수에 실패했어요.')];
      }
      $템정규 = preg_replace('/^✨/u', '', $템명);
      if ($템정규 === '은총조각' && function_exists('지급로그')) {
        지급로그('은총조각회수', (string)$받는친구['name'], $관리자닉, 0, (int)($회수['removed'] ?? $개수));
      } elseif (function_exists('지급로그')) {
        지급로그('아이템회수', (string)$받는친구['name'], $관리자닉, 0, (int)($회수['removed'] ?? $개수));
      }
      if (!empty($회수['msg'])) {
        return ['ok' => true, 'msg' => (string)$회수['msg']];
      }
      return ['ok' => true, 'msg' => "{$템명} {$개수}개 회수완료"];
    }

    $지급 = 아이템_생성_회원지급($midx, (string)$받는친구['name'], $템명, $개수);
    if (empty($지급['ok'])) {
      return ['ok' => false, 'msg' => '❌ ' . (string)($지급['msg'] ?? '생성에 실패했어요.')];
    }
    if ($템명 === '일방신청권' && function_exists('일방신청권_지급기록')) {
      일방신청권_지급기록($받는친구['name'], '관리자생성', [
        'midx' => $midx,
        'from_nick' => $관리자닉,
        'reason_text' => ($관리자닉 !== '') ? "관리자 생성 ({$관리자닉})" : '관리자 생성',
        'qty' => $개수,
      ]);
    }
    $템정규 = preg_replace('/^✨/u', '', $템명);
    if ($템정규 === '은총조각' && function_exists('지급로그')) {
      지급로그('은총조각생성', (string)$받는친구['name'], $관리자닉, 0, (int)($지급['added'] ?? $개수));
    }
    if (!empty($지급['msg'])) {
      return ['ok' => true, 'msg' => (string)$지급['msg']];
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

if (!function_exists('회원_접속코드_재발급')) {
  /** 기존 code를 버리고 새 6자리 생성 후 저장 (.지갑변경) */
  function 회원_접속코드_재발급(string $name): string {
    $name_esc = addslashes(trim($name));
    if ($name_esc === '') {
      return '';
    }
    $멤버 = db_select("SELECT idx FROM tb_member WHERE name = '{$name_esc}' LIMIT 1");
    if (empty($멤버['idx'])) {
      return '';
    }
    $코드 = 회원_6자리코드_생성();
    $코드_esc = addslashes($코드);
    db_query("UPDATE tb_member SET code = '{$코드_esc}' WHERE name = '{$name_esc}' LIMIT 1");
    return $코드;
  }
}
