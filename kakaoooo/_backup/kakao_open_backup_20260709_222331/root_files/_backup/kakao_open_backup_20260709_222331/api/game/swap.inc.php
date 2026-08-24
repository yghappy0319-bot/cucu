<?php
/**
 * 본방↔게임방 통화 스왑 (전체 총량 비율 환율)
 * - info1: 보유냥(newpoint) → 게임냥(point) — `.스왑` 전액 (10% 소멸 · 90% 게임냥)
 * - info3: 보유냥(newpoint) → 게임냥(point) — info3에서 사용
 *   · `.스왑` — 본인 보유냥 전액 스왑 (스왑냥 10% 삭제, 90%만 환율 교환)
 *   · `.스왑 500` — 보유냥 500 스왑 (10% 삭제 후 90% 환율 교환, 최소 500냥 · 보유 500 이상)
 * - info2: 게임냥(point) → 보유냥(newpoint)
 *   · `.스왑` — 본인 게임냥 전액 스왑 (받을 본방냥 500냥 이상일 때만, 게임냥 20% 수수료)
 */

if (!function_exists('스왑_풀비율')) {
  /** 스왑·환율 메시지용 게임냥 표시 — 1경↑ 경+조, 1조↑ 조만, 1조↓ 억만 */
  function 스왑_게임냥_표시($금액, $단위접미 = '') {
    if (function_exists('냥_경조_축약표시')) {
      return 냥_경조_축약표시($금액, $단위접미);
    }
    if (function_exists('냥_조억_축약표시')) {
      return 냥_조억_축약표시($금액, $단위접미);
    }
    $digits = 스왑_정수문자열($금액);
    return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $digits) . $단위접미;
  }

  /** 큰 냥 금액 → 부호 없는 정수 문자열 (PHP int/float 한계 회피) */
  function 스왑_정수문자열($v): string {
    if (function_exists('냥_정수문자열')) {
      return 냥_정수문자열($v);
    }
    if (is_int($v)) {
      return $v < 0 ? (string)(-$v) : (string)$v;
    }
    $s = trim((string)$v);
    if ($s === '' || $s === '-' || $s === '+') {
      return '0';
    }
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

  /** 소수 포함 수량 문자열 (예: 9000000.0) — bcmath용 */
  function 스왑_소수문자열($v, int $scale = 1): string {
    if (is_string($v) && preg_match('/^-?\d+(?:\.\d+)?$/', $v)) {
      if (function_exists('bcadd')) {
        return bcadd($v, '0', $scale);
      }
      return number_format((float)$v, $scale, '.', '');
    }
    return number_format((float)$v, $scale, '.', '');
  }

  /**
   * 교환_np × total_pt ÷ total_np (내림) — PHP_INT_MAX 초과 가능, 정수 문자열 반환
   */
  function 스왑_np2pt_지급계산($교환_np, $total_pt, $total_np): string {
    $교환 = 스왑_소수문자열($교환_np, 1);
    $pt = 스왑_정수문자열($total_pt);
    $np = 스왑_소수문자열($total_np, 4);
    if ($교환 === '0.0' || $교환 === '0' || $pt === '0' || (float)$np <= 0) {
      return '0';
    }
    if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bccomp')) {
      if (bccomp($np, '0', 4) <= 0) {
        return '0';
      }
      $prod = bcmul($교환, $pt, 4);
      $q = bcdiv($prod, $np, 0);
      return 스왑_정수문자열($q);
    }
    $raw = (float)$교환_np * (float)$total_pt / (float)$total_np;
    if (!is_finite($raw) || $raw <= 0) {
      return '0';
    }
    if ($raw > (float)PHP_INT_MAX) {
      return sprintf('%.0f', floor($raw));
    }
    return (string)(int)floor($raw);
  }

  /** 1 보유냥당 게임냥 (문자열, 내림) */
  function 스왑_1보유냥당_게임냥_문자열(): string {
    $총량 = 스왑_총량조회();
    $total_np = $총량['total_np'];
    $total_pt = $총량['total_pt'];
    if ((float)$total_np <= 0) {
      return '0';
    }
    return 스왑_np2pt_지급계산(1, $total_pt, $total_np);
  }

  /** 원화 → 게임냥 (문자열). shop 환산 우선, 실패 시 시세 폴백 */
  function 스왑_환율_원화_게임냥(int $원화): array {
    $게임냥 = '0';
    $만원당 = '0';
    $shopFile = dirname(__DIR__, 2) . '/shop/_shop.php';
    if (is_file($shopFile)) {
      include_once $shopFile;
      if (function_exists('shop_원화_냥환산')) {
        $게임냥 = 스왑_정수문자열(shop_원화_냥환산($원화));
        if (function_exists('shop_만원당_냥')) {
          $만원당 = 스왑_정수문자열(shop_만원당_냥());
        }
      }
    }
    if ($게임냥 === '0' || (function_exists('bccomp') ? bccomp($게임냥, '0') <= 0 : (float)$게임냥 <= 0)) {
      if (function_exists('전체냥기준금액')) {
        $만원당 = 스왑_정수문자열(전체냥기준금액(1));
      }
      if ($만원당 === '0' || (function_exists('bccomp') ? bccomp($만원당, '0') <= 0 : (float)$만원당 <= 0)) {
        $total = function_exists('시세기준_게임냥')
          ? 스왑_정수문자열(시세기준_게임냥())
          : 스왑_정수문자열(db_select("SELECT COALESCE(SUM(point), 0) AS total FROM tb_member WHERE status = 0")['total'] ?? 0);
        if (function_exists('bcmul') && function_exists('bcdiv')) {
          $만원당 = bcdiv($total, '100', 0);
          if (function_exists('bccomp') && bccomp($만원당, '0') <= 0) {
            $만원당 = '1';
          }
        } else {
          $만원당 = (string)max(1, (int)ceil((float)$total * 0.01));
        }
      }
      if (function_exists('bcmul') && function_exists('bcdiv')) {
        $ratio = bcdiv((string)$원화, '10000', 20);
        $게임냥 = 스왑_정수문자열(bcmul($ratio, $만원당, 0));
      } else {
        $게임냥 = 스왑_정수문자열((int)round($원화 / 10000 * (float)$만원당));
      }
    }
    return ['게임냥' => $게임냥, '만원당' => $만원당];
  }

  function 스왑_풀비율(): float {
    return 1;
  }

  /** 본방 스왑 시 스왑냥 중 삭제(소각) 비율(%) */
  function 스왑_본방_삭제비율(): float {
    return 10;
  }

  /**
   * 스왑 금액을 차감·삭제·교환분으로 분리
   * @return array{차감_np: float, 삭제_np: float, 교환_np: float}
   */
  /** 홀짝 웹 빠른 스왑: 본방냥 전액 → 게임냥 (삭제 5%) */
  function 스왑_홀짝_본방_삭제비율(): float {
    return 5;
  }

  function 스왑_본방_삭제및교환분리(float $스왑금액, ?float $삭제비율_pct = null): array {
    $스왑금액 = round(max(0, $스왑금액), 1);
    if ($삭제비율_pct === null) {
      $삭제비율_pct = 스왑_본방_삭제비율();
    }
    $삭제_np = round($스왑금액 * $삭제비율_pct / 100, 1);
    $교환_np = round($스왑금액 - $삭제_np, 1);
    return [
      '차감_np' => $스왑금액,
      '삭제_np' => $삭제_np,
      '교환_np' => $교환_np,
    ];
  }

  /** 게임방 스왑 시 게임냥 중 수수료(삭제) 비율(%) */
  function 스왑_게임방_수수료비율(): float {
    return 20;
  }

  /**
   * 게임냥 스왑 금액을 차감·수수료·교환분으로 분리
   * @return array{차감_pt: int, 수수료_pt: int, 교환_pt: int}
   */
  function 스왑_게임방_수수료및교환분리(int $스왑금액): array {
    $스왑금액 = max(0, $스왑금액);
    $수수료_pt = (int)floor($스왑금액 * 스왑_게임방_수수료비율() / 100);
    $교환_pt = $스왑금액 - $수수료_pt;
    return [
      '차감_pt' => $스왑금액,
      '수수료_pt' => $수수료_pt,
      '교환_pt' => $교환_pt,
    ];
  }

  /** 1 게임냥 스왑 시 지급 보유냥 (현재 전체 총량 비율, 수수료 제외 전) */
  function 스왑_1게임냥당_보유냥(): float {
    $총량 = 스왑_총량조회();
    $total_np = $총량['total_np'];
    $total_pt = 스왑_정수문자열($총량['total_pt']);
    if ($total_pt === '0') {
      return 0;
    }
    if (function_exists('bcdiv')) {
      return (float)bcdiv(스왑_소수문자열($total_np, 4), $total_pt, 4);
    }
    return round((float)$total_np / (float)$total_pt, 4);
  }

  /** 상황실(본방) `.스왑` 이용 최소 보유냥 */
  function 스왑_본방_최소보유냥(): float {
    return 500;
  }

  /** 홍보방(게임방) `.스왑` 시 받을 본방냥 최소치 (비율 계산 후) */
  function 스왑_게임방_최소본방냥(): float {
    return 500;
  }

  /** `.스왑 금액` 지정 시 최소 스왑 보유냥 (본방) */
  function 스왑_지정_최소보유냥(): float {
    return 500;
  }

  /** @return array{total_np: float, total_pt: int} */
  function 스왑_총량조회(): array {
    static $cached = null;
    if (is_array($cached)) {
      return $cached;
    }
    if (function_exists('시세기준_본방냥') && function_exists('시세기준_게임냥_문자열')) {
      $cached = [
        'total_np' => (float)시세기준_본방냥(),
        'total_pt' => 시세기준_게임냥_문자열(),
      ];
      return $cached;
    }
    if (function_exists('시세기준_본방냥') && function_exists('시세기준_게임냥')) {
      $cached = [
        'total_np' => (float)시세기준_본방냥(),
        'total_pt' => 스왑_정수문자열(시세기준_게임냥()),
      ];
      return $cached;
    }
    $row = db_select("
      SELECT
        COALESCE(SUM(newpoint), 0) AS total_np,
        CAST(COALESCE(SUM(CAST(point AS DECIMAL(40,0))), 0) AS CHAR) AS total_pt
      FROM tb_member
      WHERE status = 0
    ");
    $cached = [
      'total_np' => (float)($row['total_np'] ?? 0),
      'total_pt' => 스왑_정수문자열($row['total_pt'] ?? 0),
    ];
    return $cached;
  }

  /** 1 보유냥 스왑 시 지급 게임냥 (현재 전체 총량 비율) — PHP_INT_MAX 초과 시 문자열 */
  function 스왑_1보유냥당_게임냥() {
    $s = 스왑_1보유냥당_게임냥_문자열();
    if ($s === '0') {
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
  }

  /**
   * @return array{
   *   ok: bool,
   *   msg?: string,
   *   차감_np?: float,
   *   지급_pt?: int,
   *   차감_pt?: int,
   *   지급_np?: float,
   *   total_np?: float,
   *   total_pt?: int
   * }
   */
  function 스왑_견적계산(string $방향, ?float $금액 = null, bool $전액모드 = false, ?float $삭제비율_pct = null, bool $최소금액검사 = true): array {
    $총량 = 스왑_총량조회();
    $total_np = $총량['total_np'];
    $total_pt = $총량['total_pt'];
    $비율 = 스왑_풀비율() / 100;

    if ($방향 === 'np2pt') {
      if ($total_np <= 0 || 스왑_정수문자열($total_pt) === '0') {
        return ['ok' => false, 'msg' => "❌ 스왑할 수 있는 전체 냥이 부족해요."];
      }

      if ($금액 === null) {
        return ['ok' => false, 'msg' => "❌ 스왑 금액을 확인할 수 없어요."];
      }

      $최소_np = 스왑_지정_최소보유냥();
      $스왑금액 = round(max(0, $금액), 1);
      if ($최소금액검사 && !$전액모드 && $스왑금액 < $최소_np) {
        return [
          'ok' => false,
          'msg' => "❌ 스왑 최소 금액은 보유냥 " . newpoint표시($최소_np) . "냥 이상이에요.",
        ];
      }

      $적용삭제비율 = ($삭제비율_pct === null) ? 스왑_본방_삭제비율() : $삭제비율_pct;
      $분리 = 스왑_본방_삭제및교환분리($스왑금액, $적용삭제비율);
      $차감_np = (float)$분리['차감_np'];
      $삭제_np = (float)$분리['삭제_np'];
      $교환_np = (float)$분리['교환_np'];
      // 삭제분 제외 후 환율 교환: 지급 = 교환분 × (전체게임냥÷전체보유냥)
      $지급_pt = 스왑_np2pt_지급계산($교환_np, $total_pt, $total_np);

      if ($차감_np < 0.1) {
        return ['ok' => false, 'msg' => "❌ 스왑할 보유냥이 없어요."];
      }
      if ($교환_np < 0.1) {
        return ['ok' => false, 'msg' => "❌ 스왑 금액이 너무 적어요. (삭제 {$적용삭제비율}% 후 교환분이 없어요)"];
      }
      $지급부족 = function_exists('bccomp')
        ? bccomp($지급_pt, '1', 0) < 0
        : ((int)$지급_pt < 1);
      if ($지급부족) {
        return ['ok' => false, 'msg' => "❌ 받을 게임냥이 1냥 미만이라 스왑할 수 없어요."];
      }
      return [
        'ok' => true,
        '차감_np' => $차감_np,
        '삭제_np' => $삭제_np,
        '교환_np' => $교환_np,
        '지급_pt' => $지급_pt,
        'total_np' => $total_np,
        'total_pt' => $total_pt,
        '금액지정' => !$전액모드,
        '전액모드' => $전액모드,
      ];
    }

    if ($방향 === 'pt2np') {
      if ($total_np <= 0 || 스왑_정수문자열($total_pt) === '0') {
        return ['ok' => false, 'msg' => "❌ 스왑할 수 있는 전체 냥이 부족해요."];
      }

      if ($금액 === null) {
        return ['ok' => false, 'msg' => "❌ 스왑 금액을 확인할 수 없어요."];
      }

      $스왑금액 = max(0, (int)$금액);
      $분리 = 스왑_게임방_수수료및교환분리($스왑금액);
      $차감_pt = (int)$분리['차감_pt'];
      $수수료_pt = (int)$분리['수수료_pt'];
      $교환_pt = (int)$분리['교환_pt'];
      // 수수료 제외 후 환율 교환: 지급 = 교환분 × (전체보유냥÷전체게임냥)
      $ptStr = 스왑_정수문자열($total_pt);
      if (function_exists('bcmul') && function_exists('bcdiv') && $ptStr !== '0') {
        $지급_np = (float)bcdiv(bcmul((string)$교환_pt, 스왑_소수문자열($total_np, 4), 4), $ptStr, 1);
      } else {
        $지급_np = round($교환_pt * $total_np / max(1.0, (float)$total_pt), 1);
      }

      if ($차감_pt < 1) {
        return ['ok' => false, 'msg' => "❌ 스왑할 게임냥이 없어요."];
      }
      if ($교환_pt < 1) {
        return [
          'ok' => false,
          'msg' => "❌ 스왑 금액이 너무 적어요. (수수료 " . 스왑_게임방_수수료비율() . "% 후 교환분이 없어요)",
        ];
      }
      if ($지급_np < 0.1) {
        return ['ok' => false, 'msg' => "❌ 받을 보유냥이 너무 적어서 스왑할 수 없어요."];
      }
      $최소지급_np = 스왑_게임방_최소본방냥();
      if ($지급_np < $최소지급_np) {
        return [
          'ok' => false,
          'msg' => "❌ 스왑할 수 없어요.\n"
            . "비율 계산 시 받을 본방냥이 " . newpoint표시($최소지급_np) . "냥 이상이어야 해요.\n"
            . "현재 기준 예상 지급: " . newpoint표시($지급_np) . "냥",
        ];
      }
      return [
        'ok' => true,
        '차감_pt' => $차감_pt,
        '수수료_pt' => $수수료_pt,
        '교환_pt' => $교환_pt,
        '지급_np' => $지급_np,
        'total_np' => $total_np,
        'total_pt' => $total_pt,
        '전액모드' => $전액모드,
      ];
    }

    return ['ok' => false, 'msg' => "❌ 잘못된 스왑 방향이에요."];
  }

  /** 단소 피해 시 게임냥 부족하면 피해자 본방냥 10을 게임냥으로 자동 스왑 (최소 500냥 검사 생략) */
  function 스왑_본방_단소_자동스왑(string $닉, float $스왑_np = 10): array {
    $닉_esc = addslashes(trim($닉));
    if ($닉_esc === '') {
      return ['ok' => false, '지급_pt' => 0];
    }

    $회원 = db_select("SELECT newpoint FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    if (empty($회원)) {
      return ['ok' => false, '지급_pt' => 0];
    }

    $보유_np = round((float)($회원['newpoint'] ?? 0), 1);
    if ($보유_np < $스왑_np) {
      return ['ok' => false, '지급_pt' => 0];
    }

    $견적 = 스왑_견적계산('np2pt', $스왑_np, false, null, false);
    if (empty($견적['ok'])) {
      return ['ok' => false, '지급_pt' => 0];
    }

    $차감_np = (float)$견적['차감_np'];
    $지급_pt = 스왑_정수문자열($견적['지급_pt'] ?? 0);
    db_query("
      UPDATE tb_member
      SET newpoint = newpoint - {$차감_np},
          point = point + {$지급_pt}
      WHERE name = '{$닉_esc}' AND status = 0
      LIMIT 1
    ");

    return [
      'ok' => true,
      '차감_np' => $차감_np,
      '지급_pt' => $지급_pt,
    ];
  }

  function 스왑_본방_실행(string $두자리닉넴, ?float $보유냥금액 = null, bool $간단출력 = false): void {
    $닉_esc = addslashes($두자리닉넴);

    $회원 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    if (empty($회원)) {
      echo 전송("❌ 회원 정보를 찾을 수 없어요.");
      exit;
    }

    $보유_np = round((float)($회원['newpoint'] ?? 0), 1);
    if ($간단출력) {
      if ($보유_np < 0.1) {
        echo 전송("❌ 스왑할 보유냥이 없어요.");
        exit;
      }
      $전액스왑 = true;
      $보유냥금액 = $보유_np;
    } else {
      $최소보유 = 스왑_본방_최소보유냥();
      if ($보유_np < $최소보유) {
        echo 전송(
          "❌ 스왑은 보유냥 " . newpoint표시($최소보유) . "냥 이상일 때만 가능해요.\n"
          . "현재: " . newpoint표시($보유_np) . "냥"
        );
        exit;
      }

      $전액스왑 = ($보유냥금액 === null);

      if ($전액스왑) {
        $보유냥금액 = $보유_np;
      }
    }

    $견적 = 스왑_견적계산('np2pt', $보유냥금액, $전액스왑);
    if (empty($견적['ok'])) {
      echo 전송($견적['msg'] ?? "❌ 스왑을 처리할 수 없어요.");
      exit;
    }

    $차감_np = (float)$견적['차감_np'];
    $삭제_np = (float)($견적['삭제_np'] ?? 0);
    $교환_np = (float)($견적['교환_np'] ?? 0);
    $지급_pt = 스왑_정수문자열($견적['지급_pt'] ?? 0);
    $삭제비율 = 스왑_본방_삭제비율();
    if ($보유_np < $차감_np) {
      $필요문구 = $전액스왑
        ? ("필요: " . newpoint표시($차감_np) . "냥 (보유냥 전액)")
        : ("필요: " . newpoint표시($차감_np) . "냥");
      echo 전송(
        "❌ 보유냥이 부족해요.\n"
        . $필요문구 . "\n"
        . "현재: " . newpoint표시($보유_np) . "냥"
      );
      exit;
    }

    db_query("
      UPDATE tb_member
      SET newpoint = newpoint - {$차감_np},
          point = point + {$지급_pt}
      WHERE name = '{$닉_esc}' AND status = 0
      LIMIT 1
    ");

    if ($간단출력) {
      echo 전송('게임냥 ' . 스왑_게임냥_표시($지급_pt, '냥') . '이 스왑되었습니다');
      exit;
    }

    $갱신 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $msg = "💱 스왑 완료 (보유냥 → 게임냥)\n\n";
    if ($전액스왑) {
      $msg .= "보유냥 전액 스왑 (스왑냥 {$삭제비율}% 삭제 · " . (100 - $삭제비율) . "% 환율 교환)\n";
    } else {
      $msg .= "지정 금액 스왑 (스왑냥 {$삭제비율}% 삭제 · " . (100 - $삭제비율) . "% 환율 교환)\n";
    }
    $msg .= "-" . newpoint표시($차감_np) . " 보유냥\n";
    $msg .= "  └ 삭제 " . newpoint표시($삭제_np) . "냥 · 교환 " . newpoint표시($교환_np) . "냥\n";
    $msg .= "+" . 스왑_게임냥_표시($지급_pt) . " 게임냥\n\n";
    $msg .= "현재 보유냥: " . newpoint표시($갱신['newpoint'] ?? 0) . "냥\n";
    $msg .= "현재 게임냥: " . 스왑_게임냥_표시((int)($갱신['point'] ?? 0));
    echo 전송($msg);
    exit;
  }

  /**
   * 홀짝 웹: 본방냥 전액 → 게임냥 (삭제 5% · 95% 환율 교환)
   * @return array{ok:bool, data?:string, point?:int, newpoint?:float, min_bet?:int, max_bet?:int, 지급_pt?:int}
   */
  function 스왑_홀짝_본방_실행_데이터(string $두자리닉넴): array {
    $닉_esc = addslashes($두자리닉넴);
    $회원 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    if (empty($회원)) {
      return ['ok' => false, 'data' => '❌ 회원 정보를 찾을 수 없어요.'];
    }

    $보유_np = round((float)($회원['newpoint'] ?? 0), 1);
    if ($보유_np < 0.1) {
      return ['ok' => false, 'data' => '❌ 스왑할 본방냥이 없어요.'];
    }

    $삭제비율 = 스왑_홀짝_본방_삭제비율();
    $견적 = 스왑_견적계산('np2pt', $보유_np, true, $삭제비율);
    if (empty($견적['ok'])) {
      return ['ok' => false, 'data' => $견적['msg'] ?? '❌ 스왑을 처리할 수 없어요.'];
    }

    $차감_np = (float)$견적['차감_np'];
    $삭제_np = (float)($견적['삭제_np'] ?? 0);
    $교환_np = (float)($견적['교환_np'] ?? 0);
    $지급_pt = 스왑_정수문자열($견적['지급_pt'] ?? 0);
    if ($보유_np < $차감_np) {
      return ['ok' => false, 'data' => '❌ 본방냥이 부족해요.'];
    }

    db_query("
      UPDATE tb_member
      SET newpoint = newpoint - {$차감_np},
          point = point + {$지급_pt}
      WHERE name = '{$닉_esc}' AND status = 0
      LIMIT 1
    ");

    $갱신 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $게임냥 = (int)($갱신['point'] ?? 0);
    $본방냥 = round((float)($갱신['newpoint'] ?? 0), 1);
    $교환비율 = 100 - $삭제비율;
    $msg = "💱 스왑 완료 (본방냥 → 게임냥)\n\n";
    $msg .= "본방냥 전액 스왑 ({$삭제비율}% 삭제 · {$교환비율}% 환율 교환)\n";
    $msg .= '-' . newpoint표시($차감_np) . " 본방냥\n";
    $msg .= "  └ 삭제 " . newpoint표시($삭제_np) . " · 교환 " . newpoint표시($교환_np) . "\n";
    $msg .= '+' . 스왑_게임냥_표시($지급_pt) . " 게임냥\n\n";
    $msg .= '현재 게임냥: ' . 스왑_게임냥_표시($게임냥, '냥');

    return [
      'ok' => true,
      'type' => 'swap_np',
      'data' => $msg,
      'point' => $게임냥,
      'newpoint' => $본방냥,
      '지급_pt' => $지급_pt,
      '차감_np' => $차감_np,
      '삭제_np' => $삭제_np,
    ];
  }

  function 스왑_게임방_실행(string $두자리닉넴): void {
    $닉_esc = addslashes($두자리닉넴);

    $회원 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    if (empty($회원)) {
      echo 전송("❌ 회원 정보를 찾을 수 없어요.");
      exit;
    }

    $보유_pt = (int)($회원['point'] ?? 0);
    if ($보유_pt < 1) {
      echo 전송("❌ 스왑할 게임냥이 없어요.\n현재: " . 스왑_게임냥_표시($보유_pt) . " 게임냥");
      exit;
    }

    $견적 = 스왑_견적계산('pt2np', (float)$보유_pt, true);
    if (empty($견적['ok'])) {
      echo 전송($견적['msg'] ?? "❌ 스왑을 처리할 수 없어요.");
      exit;
    }

    $차감_pt = (int)$견적['차감_pt'];
    $수수료_pt = (int)($견적['수수료_pt'] ?? 0);
    $교환_pt = (int)($견적['교환_pt'] ?? 0);
    $지급_np = (float)$견적['지급_np'];
    $수수료비율 = 스왑_게임방_수수료비율();

    if ($보유_pt < $차감_pt) {
      echo 전송(
        "❌ 게임냥이 부족해요.\n"
        . "필요: " . 스왑_게임냥_표시($차감_pt) . " 게임냥 (게임냥 전액)\n"
        . "현재: " . 스왑_게임냥_표시($보유_pt) . " 게임냥"
      );
      exit;
    }

    db_query("
      UPDATE tb_member
      SET point = point - {$차감_pt},
          newpoint = newpoint + {$지급_np}
      WHERE name = '{$닉_esc}' AND status = 0
      LIMIT 1
    ");

    $갱신 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $msg = "💱 스왑 완료 (게임냥 → 보유냥)\n\n";
    $msg .= "게임냥 전액 스왑 (수수료 {$수수료비율}% · " . (100 - $수수료비율) . "% 환율 교환)\n";
    $msg .= "-" . 스왑_게임냥_표시($차감_pt) . " 게임냥\n";
    $msg .= "  └ 수수료 " . 스왑_게임냥_표시($수수료_pt) . " · 교환 " . 스왑_게임냥_표시($교환_pt) . "\n";
    $msg .= "+" . newpoint표시($지급_np) . " 보유냥\n\n";
    $msg .= "현재 보유냥: " . newpoint표시($갱신['newpoint'] ?? 0) . "냥\n";
    $msg .= "현재 게임냥: " . 스왑_게임냥_표시((int)($갱신['point'] ?? 0));
    echo 전송($msg);
    exit;
  }

  /** 본방 `.스왑방법` 안내 (현재 총량·예상 교환액 포함) */
  function 스왑_방법문구_본방(?float $예시보유냥 = null): string {
    $총량 = 스왑_총량조회();
    $스왑1냥 = 스왑_1보유냥당_게임냥();

    $msg = "💱 스왑 이용 방법\n\n";
    $msg .= "1️⃣ 스왑이 뭐예요?\n";
    $msg .= "- 본방 보유냥(newpoint)과 게임방 게임냥(point)을 바꾸는 기능이에요.\n";
    $msg .= "- 환율은 고정이 아니라, 그때그때 전체 회원이 가진 냥 총량 비율로 정해져요.\n\n";

    $삭제비율 = 스왑_본방_삭제비율();
    $교환비율 = 100 - $삭제비율;
    $본방최소 = (int)스왑_본방_최소보유냥();
    $게임방최소본방 = (int)스왑_게임방_최소본방냥();

    $msg .= "2️⃣ 본방에서 어떻게 해요?\n";
    $msg .= "- `.스왑` → 본인 보유냥 전액 스왑 (보유 {$본방최소}냥 이상 · 스왑냥 {$삭제비율}% 삭제, {$교환비율}%만 환율 교환)\n";
    $msg .= "- `.스왑 {$본방최소}` → 보유냥 지정 스왑 (최소 {$본방최소}냥 · {$삭제비율}% 삭제 후 {$교환비율}% 환율 교환)\n";
    $msg .= "  (환율 ≈ 전체 게임냥 ÷ 전체 보유냥)\n\n";

    $msg .= "3️⃣ 게임방에서는?\n";
    $게임방_수수료 = 스왑_게임방_수수료비율();
    $게임방_교환 = 100 - $게임방_수수료;
    $msg .= "- `.스왑` 만 가능 (금액 지정 불가)\n";
    $msg .= "- 받을 본방냥이 비율 계산 후 {$게임방최소본방}냥 이상일 때만 가능\n";
    $msg .= "- `.스왑` → 본인 게임냥 전액 스왑 (수수료 {$게임방_수수료}%, {$게임방_교환}%만 환율 교환)\n";
    $msg .= "  (환율 ≈ 전체 보유냥 ÷ 전체 게임냥)\n\n";

    $msg .= "4️⃣ 환율이 왜 바뀌나요?\n";
    $msg .= "- 시세는 전체 총량 스냅샷 기준이며, 약 1시간마다 갱신돼요.\n";
    $msg .= "- 같은 시간대에는 지급·아이템·스왑 환율이 고정돼요.\n";
    $msg .= "- 대략 1 보유냥 ≈ (전체 게임냥 ÷ 전체 보유냥) 게임냥 비율이에요.\n\n";

    $msg .= "5️⃣ 주의\n";
    $msg .= "- 본방 스왑: 보유냥 {$본방최소}냥 이상 · 스왑냥 {$삭제비율}% 삭제, {$교환비율}%만 게임냥으로 바뀌어요.\n";
    $msg .= "- 게임방 스왑: 받을 본방냥 {$게임방최소본방}냥 이상(비율 계산 후) · 게임냥 {$게임방_수수료}% 수수료, {$게임방_교환}%만 보유냥으로 바뀌어요.\n";
    $msg .= "- 본인 잔액이 차감분보다 적으면 스왑할 수 없어요.\n";
    $msg .= "- 게임냥은 신불자 판정(point 마이너스)에 쓰여요. 스왑 후 게임냥 관리에 유의해요.\n\n";

    $msg .= "📊 지금 기준 (정상 회원 전체 합계)\n";
    $msg .= "- 전체 보유냥: " . newpoint표시($총량['total_np']) . "냥\n";
    $msg .= "- 전체 게임냥: " . 스왑_게임냥_표시($총량['total_pt'], '냥') . "\n";

    if ($스왑1냥 > 0) {
      $msg .= "- 약 1 보유냥 = " . 스왑_게임냥_표시($스왑1냥) . " 게임냥\n";
    }

    if ($예시보유냥 !== null && $예시보유냥 >= 0.1) {
      $견적 = 스왑_견적계산('np2pt', $예시보유냥, true);
      if (!empty($견적['ok'])) {
        $차감_np = (float)$견적['차감_np'];
        $삭제_np = (float)($견적['삭제_np'] ?? 0);
        $교환_np = (float)($견적['교환_np'] ?? 0);
        $지급_pt = 스왑_정수문자열($견적['지급_pt'] ?? 0);
        $msg .= "\n📌 지금 본인 `.스왑` 시 (보유냥 " . newpoint표시($예시보유냥) . "냥 전액)\n";
        $msg .= "- 차감 보유냥: " . newpoint표시($차감_np) . "냥\n";
        $msg .= "  └ 삭제 " . newpoint표시($삭제_np) . "냥 · 교환 " . newpoint표시($교환_np) . "냥\n";
        $msg .= "- 받는 게임냥: 약 " . 스왑_게임냥_표시($지급_pt, '냥');
      }
    } elseif ($총량['total_np'] <= 0 || 스왑_정수문자열($총량['total_pt']) === '0') {
      $msg .= "\n⚠️ 지금은 총량이 부족해 스왑이 어려울 수 있어요.";
    }

    return $msg;
  }

  /** 본방 `.환율 금액` — 보유냥 스왑·원화 기준 게임냥 예상 */
  function 스왑_환율_미리보기문구(string $입력, bool $원화모드 = false): string {
    $입력 = trim($입력);
    if ($입력 === '') {
      return "❌ 사용법: .환율 금액\n예) .환율 5000 · .환율 1억 · .환율 5000원";
    }

    $게임냥표시 = function ($금액) {
      return 스왑_게임냥_표시($금액, '냥');
    };
    $만원당표시 = function ($금액) {
      $digits = 스왑_정수문자열($금액);
      if (function_exists('냥_경조_축약표시') && function_exists('bccomp') && bccomp($digits, '100000000') >= 0) {
        return 냥_경조_축약표시($digits) . '냥';
      }
      return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $digits) . '냥';
    };

    if ($원화모드) {
      $원화 = (int)preg_replace('/[^\d]/', '', $입력);
      if ($원화 <= 0 && function_exists('냥_금액_파싱')) {
        $원화 = 냥_금액_파싱($입력);
      }
      if ($원화 <= 0) {
        return "❌ 금액 형식을 확인해주세요.\n예) .환율 5000원 · .환율 1만원";
      }

      $환산 = 스왑_환율_원화_게임냥($원화);
      $게임냥 = $환산['게임냥'];
      $만원당 = $환산['만원당'];

      $msg = "💱 환율 계산\n\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
      $msg .= "현금 " . number_format($원화) . "원\n";
      $msg .= "→ 게임냥 약 " . $게임냥표시($게임냥) . "\n\n";
      $msg .= "기준: 1만원 = 보유냥 1% × 스왑환율";
      if ($만원당 !== '0' && (function_exists('bccomp') ? bccomp($만원당, '0') > 0 : (float)$만원당 > 0)) {
        $msg .= " (1만원 ≈ " . $만원당표시($만원당) . ")";
      }
      return $msg;
    }

    $보유냥 = function_exists('냥_금액_파싱') ? (float)냥_금액_파싱($입력) : 0;
    if ($보유냥 <= 0 && preg_match('/^\d+(?:\.\d+)?$/', $입력)) {
      $보유냥 = (float)$입력;
    }
    if ($보유냥 <= 0) {
      return "❌ 금액 형식을 확인해주세요.\n예) .환율 5000 · .환율 1억 · .환율 5000원";
    }

    $총량 = 스왑_총량조회();
    if ($총량['total_np'] <= 0 || 스왑_정수문자열($총량['total_pt']) === '0') {
      return "❌ 지금은 전체 냥 총량이 부족해서 환율 계산을 할 수 없어요.";
    }

    $분리 = 스왑_본방_삭제및교환분리($보유냥);
    $삭제_np = (float)$분리['삭제_np'];
    $교환_np = (float)$분리['교환_np'];
    $지급_pt = 스왑_np2pt_지급계산($교환_np, $총량['total_pt'], $총량['total_np']);
    $스왑1냥 = 스왑_1보유냥당_게임냥_문자열();
    $삭제비율 = (int)스왑_본방_삭제비율();
    $최소보유 = 스왑_지정_최소보유냥();

    $msg = "💱 환율 계산\n\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
    $msg .= "보유냥 " . newpoint표시($보유냥) . " 스왑 시\n";
    $msg .= "· 삭제 {$삭제비율}% (" . newpoint표시($삭제_np) . "냥)\n";
    $msg .= "· 교환 " . newpoint표시($교환_np) . "냥\n";
    $msg .= "→ 게임냥 약 " . $게임냥표시($지급_pt) . "\n\n";
    if ($스왑1냥 !== '0' && (function_exists('bccomp') ? bccomp($스왑1냥, '0') > 0 : (float)$스왑1냥 > 0)) {
      $msg .= "현재: 1 보유냥 ≈ " . $게임냥표시($스왑1냥) . " (삭제 {$삭제비율}% 반영 전)\n";
    }
    $msg .= "※ 실제 지급은 `.스왑` 실행 시 (최소 " . newpoint표시($최소보유) . "냥)";
    if ($보유냥 < $최소보유) {
      $msg .= "\n⚠️ 입력 금액은 스왑 최소 미만이라 미리보기만 가능해요.";
    }
    return $msg;
  }
}
