<?php
/**
 * 홀짝 웹 페이백(payback_pool) 미수령 내역 · 뒤 N자리(0단위) 일괄 삭제
 */

if (!function_exists('홀짝페이백_금액문자')) {
  function 홀짝페이백_금액문자($v): string {
    if (function_exists('홀짝_웹_페이백_금액문자열')) {
      return 홀짝_웹_페이백_금액문자열($v);
    }
    if (function_exists('냥_정수문자열')) {
      return 냥_정수문자열($v);
    }
    return ltrim(preg_replace('/\D/', '', (string)$v) ?: '0', '0') ?: '0';
  }
}

if (!function_exists('홀짝페이백_표시')) {
  function 홀짝페이백_표시($v): string {
    $s = 홀짝페이백_금액문자($v);
    if (function_exists('랭킹_게임냥표시')) {
      return 랭킹_게임냥표시($s, '냥');
    }
    if (function_exists('게임냥_안전표시')) {
      return 게임냥_안전표시($s, '냥');
    }
    if (function_exists('냥_숫자콤마')) {
      return 냥_숫자콤마($s) . '냥';
    }
    return $s . '냥';
  }
}

if (!function_exists('홀짝페이백_자리파싱')) {
  /** @return array{ok:bool,자리?:int,div?:string,msg?:string} */
  function 홀짝페이백_자리파싱($zeros): array {
    $zeros = preg_replace('/\D/', '', (string)$zeros);
    if ($zeros === '' || !preg_match('/^0+$/', $zeros)) {
      return ['ok' => false, 'msg' => '0 / 00 / 000 / 0000 형식으로 입력하세요.'];
    }
    $자리 = strlen($zeros);
    if ($자리 < 1 || $자리 > 18) {
      return ['ok' => false, 'msg' => '0은 1~18개까지 가능합니다.'];
    }
    return ['ok' => true, '자리' => $자리, 'div' => '1' . str_repeat('0', $자리)];
  }
}

if (!function_exists('홀짝페이백_절삭미리')) {
  /** TRUNCATE(n / div, 0) 미리보기 */
  function 홀짝페이백_절삭미리(string $금액, string $div): string {
    $금액 = 홀짝페이백_금액문자($금액);
    $div = 홀짝페이백_금액문자($div);
    if ($금액 === '0' || $div === '0' || $div === '1') {
      return $금액;
    }
    if (function_exists('bcdiv')) {
      return 홀짝페이백_금액문자(bcdiv($금액, $div, 0));
    }
    // 자리수 절삭 폴백
    $cut = strlen($div) - 1;
    if ($cut < 1) {
      return $금액;
    }
    if (strlen($금액) <= $cut) {
      return '0';
    }
    return 홀짝페이백_금액문자(substr($금액, 0, -$cut));
  }
}

if (!function_exists('홀짝페이백_미수령목록')) {
  /**
   * payback_pool > 0 인 회원 목록
   * @return list<array{nick:string,pool:string,pool_fmt:string}>
   */
  function 홀짝페이백_미수령목록(): array {
    if (function_exists('홀짝_웹_페이백_컬럼_확보')) {
      홀짝_웹_페이백_컬럼_확보();
    }
    $out = [];
    if (!function_exists('db_query') || !function_exists('db_fetch')) {
      return $out;
    }
    $exists = @db_select("SHOW TABLES LIKE 'tb_odd_even_state'");
    if (empty($exists)) {
      return $out;
    }
    $col = @db_select("SHOW COLUMNS FROM tb_odd_even_state LIKE 'payback_pool'");
    if (empty($col['Field'])) {
      return $out;
    }
    $rs = @db_query("
      SELECT nick, CAST(IFNULL(payback_pool, 0) AS CHAR) AS payback_pool
      FROM tb_odd_even_state
      WHERE CAST(IFNULL(payback_pool, 0) AS DECIMAL(65,0)) > 0
      ORDER BY CAST(IFNULL(payback_pool, 0) AS DECIMAL(65,0)) DESC, nick ASC
    ");
    if (!$rs) {
      return $out;
    }
    while ($row = db_fetch($rs)) {
      $nick = trim((string)($row['nick'] ?? ''));
      if ($nick === '') {
        continue;
      }
      $pool = 홀짝페이백_금액문자($row['payback_pool'] ?? 0);
      if ($pool === '0') {
        continue;
      }
      $out[] = [
        'nick' => $nick,
        'pool' => $pool,
        'pool_fmt' => 홀짝페이백_표시($pool),
      ];
    }
    return $out;
  }
}

if (!function_exists('홀짝페이백_미리보기')) {
  /**
   * @return array{ok:bool,msg:string,자리:int,div:string,cnt:int,sum_before:string,sum_after:string,sum_before_fmt:string,sum_after_fmt:string,rows:list}
   */
  function 홀짝페이백_미리보기(string $zeros): array {
    $parsed = 홀짝페이백_자리파싱($zeros);
    if (empty($parsed['ok'])) {
      return [
        'ok' => false,
        'msg' => (string)($parsed['msg'] ?? '입력 오류'),
        '자리' => 0,
        'div' => '1',
        'cnt' => 0,
        'sum_before' => '0',
        'sum_after' => '0',
        'sum_before_fmt' => '0냥',
        'sum_after_fmt' => '0냥',
        'rows' => [],
      ];
    }
    $자리 = (int)$parsed['자리'];
    $div = (string)$parsed['div'];
    $list = 홀짝페이백_미수령목록();
    $sumB = '0';
    $sumA = '0';
    $rows = [];
    foreach ($list as $r) {
      $before = $r['pool'];
      $after = 홀짝페이백_절삭미리($before, $div);
      if (function_exists('bcadd')) {
        $sumB = bcadd($sumB, $before, 0);
        $sumA = bcadd($sumA, $after, 0);
      } else {
        $sumB = (string)((int)$sumB + (int)$before);
        $sumA = (string)((int)$sumA + (int)$after);
      }
      $changed = ($before !== $after);
      $rows[] = [
        'nick' => $r['nick'],
        'before' => $before,
        'after' => $after,
        'before_fmt' => 홀짝페이백_표시($before),
        'after_fmt' => 홀짝페이백_표시($after),
        'changed' => $changed,
      ];
    }
    $cnt = count($rows);
    $msg = $cnt < 1
      ? '미수령 페이백이 없습니다.'
      : "미수령 {$cnt}명 · 총합 " . 홀짝페이백_표시($sumB) . ' → ' . 홀짝페이백_표시($sumA) . " (÷{$div})";
    return [
      'ok' => $cnt > 0,
      'msg' => $msg,
      '자리' => $자리,
      'div' => $div,
      'cnt' => $cnt,
      'sum_before' => 홀짝페이백_금액문자($sumB),
      'sum_after' => 홀짝페이백_금액문자($sumA),
      'sum_before_fmt' => 홀짝페이백_표시($sumB),
      'sum_after_fmt' => 홀짝페이백_표시($sumA),
      'rows' => $rows,
    ];
  }
}

if (!function_exists('홀짝페이백_일괄삭감')) {
  /**
   * payback_pool ÷ 10^N (TRUNCATE)
   * @return array{ok:bool,msg:string,changed:int,자리:int,div:string,sum_before:string,sum_after:string}
   */
  function 홀짝페이백_일괄삭감(string $zeros, string $adminNick = ''): array {
    $미리 = 홀짝페이백_미리보기($zeros);
    if (empty($미리['ok'])) {
      return [
        'ok' => false,
        'msg' => (string)($미리['msg'] ?? '실행할 대상이 없습니다.'),
        'changed' => 0,
        '자리' => (int)($미리['자리'] ?? 0),
        'div' => (string)($미리['div'] ?? '1'),
        'sum_before' => '0',
        'sum_after' => '0',
      ];
    }
    $자리 = (int)$미리['자리'];
    $div = (string)$미리['div'];
    $sumBefore = (string)$미리['sum_before'];

    if (function_exists('홀짝_웹_페이백_컬럼_확보')) {
      홀짝_웹_페이백_컬럼_확보();
    }

    $ok = @db_query("
      UPDATE tb_odd_even_state
      SET payback_pool = TRUNCATE(CAST(IFNULL(payback_pool, 0) AS DECIMAL(65,0)) / {$div}, 0)
      WHERE CAST(IFNULL(payback_pool, 0) AS DECIMAL(65,0)) > 0
    ");
    if (!$ok) {
      return [
        'ok' => false,
        'msg' => '❌ DB 업데이트에 실패했습니다.',
        'changed' => 0,
        '자리' => $자리,
        'div' => $div,
        'sum_before' => $sumBefore,
        'sum_after' => $sumBefore,
      ];
    }
    global $conn;
    $changed = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : (int)$미리['cnt'];

    $후 = @db_select("
      SELECT CAST(COALESCE(SUM(CAST(IFNULL(payback_pool, 0) AS DECIMAL(65,0))), 0) AS CHAR) AS sum_v
      FROM tb_odd_even_state
    ");
    $sumAfter = 홀짝페이백_금액문자($후['sum_v'] ?? '0');

    if (function_exists('지급로그')) {
      지급로그('홀짝페이백삭감×' . $자리, (string)$adminNick, '전체', 0, $자리);
    }

    return [
      'ok' => true,
      'msg' => "✅ 페이백 뒤 {$자리}자리 일괄 삭제 (÷{$div})\n"
        . "변경 {$changed}명 · "
        . 홀짝페이백_표시($sumBefore) . ' → ' . 홀짝페이백_표시($sumAfter),
      'changed' => $changed,
      '자리' => $자리,
      'div' => $div,
      'sum_before' => $sumBefore,
      'sum_after' => $sumAfter,
    ];
  }
}
