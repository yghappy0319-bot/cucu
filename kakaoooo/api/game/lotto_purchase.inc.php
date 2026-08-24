<?php
/**
 * 로또 수동·자동 구매 공통 처리
 * 포함 전제: config.php 로드됨, $두자리닉넴·$정보·$단위 사용 가능
 */

if (is_file(__DIR__ . '/lotto_amount.inc.php')) {
  require_once __DIR__ . '/lotto_amount.inc.php';
}
if (file_exists(__DIR__ . '/lotto_ticket.inc.php')) {
  require_once __DIR__ . '/lotto_ticket.inc.php';
}

if (!function_exists('로또_진행회차')) {
  /**
   * 구매·조회용 진행 회차
   * 추첨만 되고 미지급(status=0)인 회차도 이미 끝난 회차로 보고 +1
   * (추첨 후 같은 회차에 추가 구매되는 것 방지)
   */
  function 로또_진행회차() {
    $행 = db_select("
      SELECT IFNULL(MAX(drow), 0) AS max_drow
      FROM tb_game_lotto_result
    ");
    return (int)($행['max_drow'] ?? 0) + 1;
  }
}

/**
 * 삭제 금지 하한 회차 (이 회차 이상 행은 절대 삭제 안 함)
 * · 진행 회차(미추첨 구매 중)
 * · 미지급(추첨만 된 status=0) 회차
 */
if (!function_exists('로또_삭제보호하한')) {
  function 로또_삭제보호하한(): int {
    $진행회차 = (int)로또_진행회차();
    $보호하한 = $진행회차 > 0 ? $진행회차 : 1;
    $미지급행 = @db_select("
      SELECT IFNULL(MIN(drow), 0) AS d
      FROM tb_game_lotto_result
      WHERE status = 0
    ");
    $미지급회차 = (int)($미지급행['d'] ?? 0);
    if ($미지급회차 > 0 && $미지급회차 < $보호하한) {
      $보호하한 = $미지급회차;
    }
    return max(1, $보호하한);
  }
}

/**
 * drow < $삭제기준 배치 삭제 (700만 건 대응 · LIMIT 반복)
 *
 * @return array{ok:bool,삭제기준:int,보호하한:int,삭제건수:int,배치횟수:int,msg?:string}
 */
if (!function_exists('로또_회차이전_배치삭제')) {
  function 로또_회차이전_배치삭제(int $beforeDrow, int $batchSize = 50000, int $maxBatches = 500): array {
    global $conn;
    $beforeDrow = (int)$beforeDrow;
    $batchSize = max(1000, min(200000, (int)$batchSize));
    $maxBatches = max(1, min(2000, (int)$maxBatches));
    $보호하한 = 로또_삭제보호하한();

    // before=116 → drow < 116 삭제. 보호하한(116)을 넘지 못함
    $삭제기준 = $beforeDrow;
    if ($삭제기준 > $보호하한) {
      $삭제기준 = $보호하한;
    }
    if ($삭제기준 <= 1) {
      return [
        'ok' => true,
        '삭제기준' => $삭제기준,
        '보호하한' => $보호하한,
        '삭제건수' => 0,
        '배치횟수' => 0,
        'msg' => '삭제할 이전 회차가 없습니다.',
      ];
    }

    @set_time_limit(0);
    @ignore_user_abort(true);
    if ($conn instanceof mysqli) {
      @mysqli_query($conn, 'SET SESSION innodb_lock_wait_timeout = 120');
    }

    $삭제건수 = 0;
    $배치횟수 = 0;
    for ($i = 0; $i < $maxBatches; $i++) {
      $ok = @db_query("DELETE FROM tb_game_lotto WHERE drow < {$삭제기준} LIMIT {$batchSize}");
      if (!$ok) {
        return [
          'ok' => false,
          '삭제기준' => $삭제기준,
          '보호하한' => $보호하한,
          '삭제건수' => $삭제건수,
          '배치횟수' => $배치횟수,
          'msg' => 'DELETE 실패 — 잠시 후 같은 URL로 이어서 실행하세요.',
        ];
      }
      $affected = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
      $배치횟수++;
      $삭제건수 += $affected;
      if ($affected < $batchSize) {
        break; // 더 이상 없음
      }
      if (function_exists('db_ensure_connection')) {
        db_ensure_connection();
      }
      usleep(20000); // 20ms — 락·부하 완화
    }

    // 풀 카운터 오래된 회차도 정리
    @db_query("DELETE FROM tb_game_lotto_pool WHERE drow < {$삭제기준}");

    $남음 = @db_select("SELECT COUNT(*) AS c FROM tb_game_lotto WHERE drow < {$삭제기준}");
    $남은건 = (int)($남음['c'] ?? 0);
    $msg = "drow < {$삭제기준} 삭제 {$삭제건수}건 ({$배치횟수}배치)";
    if ($남은건 > 0) {
      $msg .= " · 남은 {$남은건}건 — 같은 URL 다시 실행";
    } else {
      $msg .= ' · 완료';
    }

    return [
      'ok' => true,
      '삭제기준' => $삭제기준,
      '보호하한' => $보호하한,
      '삭제건수' => $삭제건수,
      '배치횟수' => $배치횟수,
      '남은건수' => $남은건,
      'msg' => $msg,
    ];
  }
}

/**
 * 오래된 회차 tb_game_lotto 행 삭제
 * 예) 117회차 진행 · 보관 10 → drow < 107 만 삭제
 * beforeDrow > 0 이면 보관 대신 "그 회차 이전" 삭제 (예: 116 → drow < 116)
 *
 * @return array{ok:bool,기준회차:int,삭제기준:int,보호하한:int,삭제건수:int,배치횟수?:int,msg?:string}
 */
if (!function_exists('로또_오래된회차_정리')) {
  function 로또_오래된회차_정리(int $기준회차 = 0, int $보관회차수 = 10, int $beforeDrow = 0): array {
    $진행회차 = (int)로또_진행회차();
    if ($기준회차 <= 0) {
      $기준회차 = $진행회차;
    }
    $기준회차 = (int)$기준회차;
    $보호하한 = 로또_삭제보호하한();

    if ($beforeDrow > 0) {
      $삭제기준 = (int)$beforeDrow;
    } else {
      $보관회차수 = max(1, $보관회차수);
      $삭제기준 = $기준회차 - $보관회차수;
    }
    if ($삭제기준 > $보호하한) {
      $삭제기준 = $보호하한;
    }
    if ($삭제기준 <= 0) {
      return [
        'ok' => true,
        '기준회차' => $기준회차,
        '삭제기준' => $삭제기준,
        '보호하한' => $보호하한,
        '삭제건수' => 0,
        '배치횟수' => 0,
      ];
    }

    $r = 로또_회차이전_배치삭제($삭제기준);
    return [
      'ok' => !empty($r['ok']),
      '기준회차' => $기준회차,
      '삭제기준' => (int)($r['삭제기준'] ?? $삭제기준),
      '보호하한' => (int)($r['보호하한'] ?? $보호하한),
      '삭제건수' => (int)($r['삭제건수'] ?? 0),
      '배치횟수' => (int)($r['배치횟수'] ?? 0),
      '남은건수' => (int)($r['남은건수'] ?? 0),
      'msg' => (string)($r['msg'] ?? ''),
    ];
  }
}

/**
 * `.로또 추첨` 전 선행 회차 정리 여부 확인
 * @return array{ok:bool, msg?:string, target_drow?:int}
 */
if (!function_exists('로또_추첨_선행검사')) {
  function 로또_추첨_선행검사() {
    $미지급행 = db_select("
      SELECT drow
      FROM tb_game_lotto_result
      WHERE status = 0
      ORDER BY drow ASC
      LIMIT 1
    ");
    if (!empty($미지급행['drow'])) {
      $d = (int)$미지급행['drow'];
      return [
        'ok'  => false,
        'msg' => "❌ {$d}회차 추첨은 완료됐지만 아직 지급이 안 됐어요.\n먼저 `.로또 지급`을 실행해주세요.",
      ];
    }

    $대상회차 = 로또_진행회차();

    // 이전 미추첨 회차 — status=0 전체 MIN 스캔 금지(표 많으면 타임아웃)
    // 직전~최근 30회차만 drow 인덱스로 확인
    $from = max(1, $대상회차 - 30);
    for ($d = $대상회차 - 1; $d >= $from; $d--) {
      $이미결과 = db_select("SELECT drow FROM tb_game_lotto_result WHERE drow = {$d} LIMIT 1");
      if (!empty($이미결과['drow'])) {
        continue;
      }
      $티켓 = db_select("SELECT idx FROM tb_game_lotto WHERE drow = {$d} LIMIT 1");
      if (!empty($티켓['idx'])) {
        return [
          'ok'  => false,
          'msg' => "❌ {$d}회차 추첨이 아직 안 됐어요.\n{$대상회차}회차보다 이전 회차부터 처리해주세요.",
        ];
      }
    }

    $이미추첨 = db_select("SELECT drow FROM tb_game_lotto_result WHERE drow = {$대상회차} LIMIT 1");
    if (!empty($이미추첨['drow'])) {
      return [
        'ok'  => false,
        'msg' => "❌ {$대상회차}회차는 이미 추첨됐어요.",
      ];
    }

    return ['ok' => true, 'target_drow' => $대상회차];
  }
}

/**
 * 회차 등수 채점 (status=0 → rank/status=1).
 * 기본: 한 번의 UPDATE (대량 배치 루프보다 빠름)
 * @return int 처리 건수(대략)
 */
if (!function_exists('로또_회차_등수채점')) {
  function 로또_회차_등수채점(int $drow, int $num1, int $num2, int $num3, int $batch = 3000): int {
    global $conn;
    $drow = (int)$drow;
    $num1 = (int)$num1;
    $num2 = (int)$num2;
    $num3 = (int)$num3;
    if ($drow <= 0) {
      return 0;
    }
    $매치식 = "( (num1 IN ({$num1}, {$num2}, {$num3})) + (num2 IN ({$num1}, {$num2}, {$num3})) + (num3 IN ({$num1}, {$num2}, {$num3})) )";

    // 1차: 단일 UPDATE (가장 빠름)
    $ok = @db_query("
      UPDATE tb_game_lotto
      SET `rank` = CASE
            WHEN {$매치식} = 3 THEN 1
            WHEN {$매치식} = 2 THEN 2
            WHEN {$매치식} = 1 THEN 3
            ELSE 0
          END,
          status = 1
      WHERE drow = {$drow}
        AND status = 0
    ");
    if ($ok) {
      $affected = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
      if ($affected > 0) {
        return $affected;
      }
      $left = db_select("SELECT idx FROM tb_game_lotto WHERE drow = {$drow} AND status = 0 LIMIT 1");
      if (empty($left['idx'])) {
        return 0;
      }
    }

    // 폴백: 배치 (단일 UPDATE 실패·타임아웃 환경)
    $batch = max(500, (int)$batch);
    $done = 0;
    for ($i = 0; $i < 2000; $i++) {
      $ok = @db_query("
        UPDATE tb_game_lotto
        SET `rank` = CASE
              WHEN {$매치식} = 3 THEN 1
              WHEN {$매치식} = 2 THEN 2
              WHEN {$매치식} = 1 THEN 3
              ELSE 0
            END,
            status = 1
        WHERE drow = {$drow}
          AND status = 0
        LIMIT {$batch}
      ");
      if (!$ok) {
        break;
      }
      $affected = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
      if ($affected <= 0) {
        break;
      }
      $done += $affected;
      if ($affected < $batch) {
        break;
      }
    }
    return $done;
  }
}

if (!function_exists('로또_번호구매_실행')) {
  /**
   * @param array{요청개수:int,isAuto:bool,num1?:int,num2?:int,num3?:int,silent?:bool,useTicket?:bool} $opts
   */
  function 로또_번호구매_실행(array $opts) {
    global $두자리닉넴, $정보, $단위;

    $요청개수 = max(1, (int)($opts['요청개수'] ?? 1));
    $isAutoLotto = !empty($opts['isAuto']);
    // 로또 구매는 레벨업 티켓만 가능 (게임냥 구매 불가)
    $useTicket = true;
    if (array_key_exists('silent', $opts)) {
      $silent = !empty($opts['silent']);
    } else {
      $silent = !empty($GLOBALS['LOTTO_PURCHASE_SILENT']);
    }
    $compact = !empty($opts['compact']);
    $수동_num1 = (int)($opts['num1'] ?? 0);
    $수동_num2 = (int)($opts['num2'] ?? 0);
    $수동_num3 = (int)($opts['num3'] ?? 0);

    $진행회차 = 로또_진행회차();
    $닉_esc = addslashes($두자리닉넴);
    $내idx  = (int)($정보['idx'] ?? 0);
    if ($내idx <= 0) {
      echo 전송("❌ 회원 정보를 찾을 수 없어요.");
      exit;
    }

    // 게임포기 중이면 로또 구매 불가
    if (function_exists('게임포기_바로가기숨김인가') && 게임포기_바로가기숨김인가($두자리닉넴)) {
      echo 전송("❌ 게임포기 중에는 로또를 구매할 수 없어요.\n가방에서 게임시작 후 이용해 주세요.");
      exit;
    }

    $내회차구매수행 = db_select("SELECT COUNT(*) AS cnt FROM tb_game_lotto WHERE drow = {$진행회차} AND nick = '{$닉_esc}' AND status = 0");
    $누적구매수    = (int)($내회차구매수행['cnt'] ?? 0);
    // 보유냥 (int) 금지 — PHP_INT_MAX 초과 잔액 잘림 방지
    $보유냥행 = db_select("SELECT CAST(IFNULL(point, 0) AS CHAR) AS point FROM tb_member WHERE idx = {$내idx} LIMIT 1");
    $보유냥 = function_exists('냥_정수문자열')
      ? 냥_정수문자열($보유냥행['point'] ?? ($정보['point'] ?? 0))
      : preg_replace('/[^\d]/', '', (string)($보유냥행['point'] ?? '0'));
    if ($보유냥 === '' || $보유냥 === null) {
      $보유냥 = '0';
    }
    $보유티켓      = function_exists('로또티켓_조회') ? 로또티켓_조회($내idx) : 0;
    $냥부족 = function ($보유, $가격) {
      $보유 = (string)$보유;
      $가격 = (string)(int)$가격;
      if (function_exists('bccomp')) {
        return bccomp($보유, $가격, 0) < 0;
      }
      if (function_exists('냥_정수_미만')) {
        return 냥_정수_미만($보유, $가격);
      }
      return (float)$보유 < (float)$가격;
    };
    $냥차감 = function ($보유, $가격) {
      $보유 = (string)$보유;
      $가격 = (string)(int)$가격;
      if (function_exists('냥_금액_문자열차감')) {
        return 냥_금액_문자열차감($보유, $가격);
      }
      if (function_exists('bcsub')) {
        return bcsub($보유, $가격, 0);
      }
      return (string)max(0, (int)$보유 - (int)$가격);
    };
    $냥합 = function ($a, $b) {
      if (function_exists('냥_금액_문자열합')) {
        return 냥_금액_문자열합((string)$a, (string)$b);
      }
      if (function_exists('bcadd')) {
        return bcadd((string)$a, (string)$b, 0);
      }
      return (string)((int)$a + (int)$b);
    };
    $냥표시 = function ($n) use ($단위) {
      if (function_exists('냥축약표시')) {
        return 냥축약표시($n, $단위);
      }
      if (function_exists('게임냥_안전표시')) {
        return 게임냥_안전표시($n, $단위);
      }
      return number_format((int)$n) . $단위;
    };

    $실구매목록 = [];
    $총차감     = '0';
    $총티켓차감 = 0;
    $부족중단   = false;
    $부족필요가 = 0;
    $부족필요티켓 = 1;
    $자숙패널티 = ['횟수' => 0, '게임냥' => 0, '보유냥' => 0, '마지막끝' => ''];

    for ($ii = 0; $ii < $요청개수; $ii++) {
      $이번순번 = $누적구매수 + 1;
      $추가단계 = (int)floor(($이번순번 - 1) / 20);
      $로또가격 = 500000 + ($추가단계 * 500000);

      if ($useTicket) {
        if ($보유티켓 < 1) {
          $부족중단     = true;
          $부족필요티켓 = 1;
          break;
        }
      } elseif ($냥부족($보유냥, $로또가격)) {
        $부족중단   = true;
        $부족필요가 = $로또가격;
        break;
      }

      if (!$useTicket && function_exists('자숙_로또위반_적용')) {
        $자숙결과 = 자숙_로또위반_적용($두자리닉넴, $단위);
        if (!empty($자숙결과['applied'])) {
          $자숙패널티['횟수'] = (int)$자숙패널티['횟수'] + 1;
          $자숙패널티['게임냥'] = (int)$자숙패널티['게임냥'] + (int)($자숙결과['deduct'] ?? 0);
          if (!empty($자숙결과['new_end'])) {
            $자숙패널티['마지막끝'] = (string)$자숙결과['new_end'];
          }
          $보유행 = db_select("SELECT CAST(IFNULL(point, 0) AS CHAR) AS point FROM tb_member WHERE idx = {$내idx} LIMIT 1");
          $보유냥 = function_exists('냥_정수문자열')
            ? 냥_정수문자열($보유행['point'] ?? 0)
            : preg_replace('/[^\d]/', '', (string)($보유행['point'] ?? '0'));
          if ($보유냥 === '' || $보유냥 === null) {
            $보유냥 = '0';
          }
        }
      }

      if ($useTicket) {
        if ($보유티켓 < 1) {
          $부족중단     = true;
          $부족필요티켓 = 1;
          break;
        }
      } elseif ($냥부족($보유냥, $로또가격)) {
        $부족중단   = true;
        $부족필요가 = $로또가격;
        break;
      }

      if ($isAutoLotto) {
        $후보숫자 = range(1, 45);
        shuffle($후보숫자);
        $선택 = array_slice($후보숫자, 0, 3);
        sort($선택);
        $n1 = (int)$선택[0];
        $n2 = (int)$선택[1];
        $n3 = (int)$선택[2];
      } else {
        $n1 = $수동_num1;
        $n2 = $수동_num2;
        $n3 = $수동_num3;
      }

      db_query("
        INSERT INTO tb_game_lotto
        SET nick = '{$닉_esc}',
            drow = {$진행회차},
            num1 = {$n1},
            num2 = {$n2},
            num3 = {$n3},
            amount = " . ($useTicket ? 0 : $로또가격) . ",
            status = 0,
            regdate = NOW()
      ");

      if ($useTicket) {
        if (!로또티켓_차감($내idx, 1)) {
          $부족중단     = true;
          $부족필요티켓 = 1;
          break;
        }
        $보유티켓 -= 1;
        $총티켓차감 += 1;
      } else {
        db_query("UPDATE tb_member SET point = point - {$로또가격} WHERE idx = {$내idx}");
        $보유냥 = $냥차감($보유냥, $로또가격);
        $총차감 = $냥합($총차감, $로또가격);
      }

      $누적구매수 += 1;

      $표시1 = str_pad((string)$n1, 2, '0', STR_PAD_LEFT);
      $표시2 = str_pad((string)$n2, 2, '0', STR_PAD_LEFT);
      $표시3 = str_pad((string)$n3, 2, '0', STR_PAD_LEFT);
      $실구매목록[] = [
        'num' => "{$표시1},{$표시2},{$표시3}",
        'amt' => $useTicket ? 0 : $로또가격,
      ];
    }

    $실제구매수 = count($실구매목록);

    if ($실제구매수 === 0) {
      if ($useTicket) {
        echo 전송("❌ 로또 티켓 부족! 1장 필요 (현재 {$보유티켓}장)\n[{$두자리닉넴}]");
      } else {
        echo 전송("❌ 보유 {$단위} 부족! 로또 구매는 " . $냥표시($부족필요가) . " 필요 (현재 " . $냥표시($보유냥) . ")\n[{$두자리닉넴}]");
      }
      exit;
    }

    // 캐시 삭제(대량 SUM 유발) 대신 가산 — 본방 `.로또` 무응답 방지
    if (!$useTicket && $총차감 !== '0' && $총차감 !== 0 && function_exists('로또_회차금액_캐시가산')) {
      로또_회차금액_캐시가산($진행회차, $총차감);
    }

    // `.로또 자동` (티켓): 자숙 중이면 +5시간 (냥 차감 없음 · 명령 1회당 1번)
    $자숙안내 = '';
    if ($isAutoLotto && function_exists('자숙_시간연장위반_적용')) {
      $자숙결과 = 자숙_시간연장위반_적용($두자리닉넴, '로또자동');
      if (!empty($자숙결과['applied'])) {
        $자숙안내 = (string)($자숙결과['notice'] ?? '');
        if (!empty($자숙결과['new_end'])) {
          $자숙패널티['횟수'] = max(1, (int)$자숙패널티['횟수']);
          $자숙패널티['마지막끝'] = (string)$자숙결과['new_end'];
        }
      }
    }
    if ($자숙안내 === '' && function_exists('자숙_시전보호_패널티_요약문구')) {
      $자숙안내 = 자숙_시전보호_패널티_요약문구($자숙패널티, '로또', $단위);
    }

    if ($silent) {
      if ($자숙안내 !== '') {
        echo 전송(rtrim($자숙안내));
      }
      exit;
    }

    if ($요청개수 === 1) {
      $한건   = $실구매목록[0];
      $분할 = explode(',', $한건['num']);
      $첫줄 = "✅ 로또 {$진행회차}회차 번호 등록 완료: {$분할[0]},{$분할[1]},{$분할[2]}";
      $패딩 = function_exists('채팅_첫줄_뒤_공백') ? 채팅_첫줄_뒤_공백() : "\n";
      if ($useTicket) {
        echo 전송($자숙안내 . $첫줄 . $패딩 . "(회차 누적 {$누적구매수}건째 / 티켓 1장 차감 · 잔여 {$보유티켓}장)");
      } else {
        echo 전송($자숙안내 . $첫줄 . $패딩 . "(회차 누적 {$누적구매수}건째 / 구매금액 " . $냥표시($한건['amt']) . " 차감)");
      }
      exit;
    }

    $패딩 = function_exists('채팅_첫줄_뒤_공백') ? 채팅_첫줄_뒤_공백() : "\n";
    $msg = $자숙안내 . "✅ 로또 {$진행회차}회차 자동 {$실제구매수}개 구매 완료" . $패딩;
    $msg .= "─────────────\n";
    if ($compact || $실제구매수 > 20) {
      $msg .= "번호 {$실제구매수}개 등록 · `.로또 내역`에서 확인\n";
    } else {
      foreach ($실구매목록 as $i => $it) {
        $msg .= ($i + 1) . ") {$it['num']}\n";
      }
    }
    $msg .= "─────────────\n";
    $msg .= "📊 합계\n";
    if ($useTicket) {
      $msg .= "🎟️ 티켓 차감: {$총티켓차감}장 (잔여 {$보유티켓}장)\n";
    } else {
      $msg .= "💸 차감: " . $냥표시($총차감) . "\n";
    }
    $msg .= "📌 회차 누적: {$누적구매수}건";
    if ($부족중단) {
      $잔여요청 = $요청개수 - $실제구매수;
      if ($useTicket) {
        $msg .= "\n\n⚠️ 티켓 부족으로 {$잔여요청}개 구매 중단\n(다음 장 {$부족필요티켓}장 필요 · 현재 {$보유티켓}장)\n[{$두자리닉넴}]";
      } else {
        $msg .= "\n\n⚠️ 잔액 부족으로 {$잔여요청}개 구매 중단\n(다음 장 " . $냥표시($부족필요가) . " 필요 · 현재 " . $냥표시($보유냥) . ")\n[{$두자리닉넴}]";
      }
    }
    echo 전송($msg);
    exit;
  }
}

if (!function_exists('로또_티켓전부자동구매_실행')) {
  /** `.로또 구매 전부` — 보유 로또 티켓 전량 자동 구매 */
  function 로또_티켓전부자동구매_실행() {
    global $두자리닉넴, $정보;

    $내idx = (int)($정보['idx'] ?? 0);
    if ($내idx <= 0) {
      echo 전송("❌ 회원 정보를 찾을 수 없어요.");
      exit;
    }
    if (!function_exists('로또티켓_조회')) {
      $ticketInc = __DIR__ . '/lotto_ticket.inc.php';
      if (is_file($ticketInc)) {
        require_once $ticketInc;
      }
    }
    $보유 = function_exists('로또티켓_조회') ? 로또티켓_조회($내idx) : 0;
    if ($보유 < 1) {
      echo 전송("❌ 로또 티켓이 없어요. (현재 0장)\n[{$두자리닉넴}]");
      exit;
    }
    @set_time_limit(max(30, min(180, 15 + (int)$보유)));
    로또_번호구매_실행([
      '요청개수' => $보유,
      'isAuto' => true,
      'silent' => false,
      'useTicket' => true,
      'compact' => true,
    ]);
  }
}

if (!function_exists('로또_구매명령_사용법')) {
  function 로또_구매명령_사용법(): string {
    return "❌ 사용법\n`.로또 구매 1~100` (원하는 장수 · 예: `.로또 구매 3` · `.로또 구매 55`)\n`.로또 구매 전부` (보유 티켓 전량)";
  }
}

if (!function_exists('로또_구매명령_실행')) {
  /** `.로또 구매 1~100` · `.로또 구매 전부` */
  function 로또_구매명령_실행($arg = '') {
    $arg = trim((string)$arg);
    $최대일괄 = 100;
    if ($arg === '') {
      echo 전송(로또_구매명령_사용법());
      exit;
    }
    if (preg_match('/^(전부|전체|다|올인|all)$/ui', $arg)) {
      로또_티켓전부자동구매_실행();
      return;
    }
    if (!preg_match('/^\d+$/u', $arg)) {
      echo 전송(로또_구매명령_사용법());
      exit;
    }
    $요청개수 = (int)$arg;
    if ($요청개수 < 1) {
      echo 전송("❌ 구매 개수는 1~100장이에요.\n예) .로또 구매 3 · .로또 구매 55");
      exit;
    }
    if ($요청개수 > $최대일괄) {
      echo 전송("❌ 한 번에 최대 {$최대일괄}장까지예요. (요청: {$요청개수}장)\n전량은 `.로또 구매 전부`");
      exit;
    }
    @set_time_limit(max(30, min(180, 15 + $요청개수)));
    로또_번호구매_실행([
      '요청개수' => $요청개수,
      'isAuto' => true,
      'silent' => false,
      'useTicket' => true,
      'compact' => ($요청개수 > 20),
    ]);
  }
}
