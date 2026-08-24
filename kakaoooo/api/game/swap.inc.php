<?php
/**
 * 본방↔게임방 통화 스왑 (전체 총량 비율 환율)
 * - info1: 보유냥(newpoint) → 게임냥(point) — `.스왑` 전액 (10% 소멸 · 90% 게임냥)
 * - info3: 보유냥(newpoint) → 게임냥(point) — info3에서 사용
 *   · `.스왑` — 본인 보유냥 전액 스왑 (스왑냥 10% 삭제, 90%만 환율 교환)
 *   · `.스왑 500` — 보유냥 500 스왑 (10% 삭제 후 90% 환율 교환, 최소 500냥 · 보유 500 이상)
 * - info2 (홍보방):
 *   · `.스왑` — `.본냥스왑` / `.겜냥스왑` 안내
 *   · `.본냥스왑` — 본냥 전액 → 게임냥 (10% 삭제 · 90% 환율 · 잔여·최소 없음)
 *   · `.겜냥스왑` — 게임냥 1억 남기고 나머지 → 본냥 (수수료 20%)
 */

if (!function_exists('스왑_풀비율')) {
  /** 스왑·환율 메시지용 게임냥 표시 — 1경↑ 경+조, 1조↑ 조만, 1조↓ 억만 */
  function 스왑_게임냥_표시($금액, $단위접미 = '') {
    // 억·만 미만도 보이게 — 냥_조억_축약표시는 1억 미만을 0으로 절사함
    if (function_exists('랭킹_게임냥표시')) {
      return 랭킹_게임냥표시($금액, $단위접미);
    }
    if (function_exists('게임냥_안전표시')) {
      return 게임냥_안전표시($금액, $단위접미);
    }
    if (function_exists('냥_경조_축약표시')) {
      return 냥_경조_축약표시($금액, $단위접미);
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

  /**
   * 교환_pt × total_np ÷ total_pt (소수 1자리) — .환율(np2pt)의 역방향
   * 큰 수×큰 수 bcmul 대신 (pt÷np) 환율로 나눠 안정적으로 계산
   */
  function 스왑_pt2np_지급계산($교환_pt, $total_pt, $total_np): string {
    $교환 = 스왑_정수문자열($교환_pt);
    $pt = 스왑_정수문자열($total_pt);
    $np = 스왑_소수문자열($total_np, 4);
    if ($교환 === '0' || $pt === '0') {
      return '0.0';
    }
    if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bccomp')) {
      if (bccomp($np, '0', 4) <= 0) {
        return '0.0';
      }
      // 1 본방냥당 게임냥 (고정밀) — .환율과 동일 비율
      $rate = bcdiv($pt, $np, 24);
      if (bccomp($rate, '0', 24) <= 0) {
        return '0.0';
      }
      return bcdiv($교환, $rate, 1);
    }
    $npF = (float)$np;
    $ptF = (float)$pt;
    if ($npF <= 0 || $ptF <= 0) {
      return '0.0';
    }
    // float 경로: 비율 먼저 구해 중간 오버플로 완화
    $rate = $ptF / $npF;
    if ($rate <= 0 || !is_finite($rate)) {
      return '0.0';
    }
    $raw = (float)$교환 / $rate;
    if (!is_finite($raw) || $raw <= 0) {
      return '0.0';
    }
    return number_format($raw, 1, '.', '');
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
        $total = 스왑_정수문자열(스왑_총량조회()['total_pt'] ?? 0);
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
   * 게임냥 스왑 금액을 차감·수수료·교환분으로 분리 (해 단위 문자열 허용)
   * 주의: (float)/(int) 캐스팅 금지 — 해 단위에서 int64 래핑으로 교환분이 수백 경으로 붕괴함
   * @return array{차감_pt: string, 수수료_pt: string, 교환_pt: string}
   */
  function 스왑_게임방_수수료및교환분리($스왑금액): array {
    $스왑 = 스왑_정수문자열($스왑금액);
    $비율 = (int)스왑_게임방_수수료비율();
    if ($비율 < 0) {
      $비율 = 0;
    }
    if ($비율 > 100) {
      $비율 = 100;
    }
    $비율s = (string)$비율;

    if ($스왑 === '0') {
      return ['차감_pt' => '0', '수수료_pt' => '0', '교환_pt' => '0'];
    }

    if (function_exists('bcmul') && function_exists('bcdiv')) {
      $수수료_pt = bcdiv(bcmul($스왑, $비율s, 0), '100', 0);
      if (function_exists('bcsub')) {
        $교환_pt = bcsub($스왑, $수수료_pt, 0);
      } elseif (function_exists('bcadd')) {
        $교환_pt = bcadd($스왑, '-' . $수수료_pt, 0);
      } else {
        $교환비율 = (string)(100 - $비율);
        $교환_pt = bcdiv(bcmul($스왑, $교환비율, 0), '100', 0);
      }
    } elseif (function_exists('냥_비율내림')) {
      $수수료_pt = 스왑_정수문자열(냥_비율내림($스왑, $비율 / 100.0));
      // 교환 = 스왑 - 수수료 (문자열 자리수 뺄셈)
      $교환_pt = 스왑_문자열_빼기($스왑, $수수료_pt);
    } else {
      // bcmath 없음: 문자열 비율 내림 (float/int 캐스팅 금지)
      $수수료_pt = 스왑_문자열_비율내림($스왑, $비율);
      $교환_pt = 스왑_문자열_빼기($스왑, $수수료_pt);
    }

    $수수료_pt = 스왑_정수문자열($수수료_pt);
    $교환_pt = 스왑_정수문자열($교환_pt);
    // 방어: 교환+수수료가 차감보다 크면 교환을 차감-수수료로 재계산
    if (function_exists('bcadd') && function_exists('bccomp') && function_exists('bcsub')) {
      $합 = bcadd($수수료_pt, $교환_pt, 0);
      if (bccomp($합, $스왑, 0) > 0) {
        $교환_pt = bcsub($스왑, $수수료_pt, 0);
      }
    }

    return [
      '차감_pt' => $스왑,
      '수수료_pt' => $수수료_pt,
      '교환_pt' => $교환_pt,
    ];
  }

  /** 큰 정수 문자열 비율 내림 — floor(n * pct / 100), float 금지 */
  function 스왑_문자열_비율내림(string $금액, int $퍼센트): string {
    $금액 = 스왑_정수문자열($금액);
    if ($금액 === '0' || $퍼센트 <= 0) {
      return '0';
    }
    if ($퍼센트 >= 100) {
      return $금액;
    }
    if (function_exists('bcmul') && function_exists('bcdiv')) {
      return 스왑_정수문자열(bcdiv(bcmul($금액, (string)$퍼센트, 0), '100', 0));
    }
    // 긴 정수 × 퍼센트 / 100 (초등 곱셈)
    $p = (string)$퍼센트;
    $prod = '0';
    for ($i = 0; $i < strlen($p); $i++) {
      $d = (int)$p[$i];
      if ($d === 0) {
        $prod .= '0';
        continue;
      }
      $부분 = 스왑_문자열_곱하기작은수($금액, $d) . str_repeat('0', strlen($p) - 1 - $i);
      $prod = 스왑_문자열_더하기($prod, $부분);
    }
    // /100 내림 = 마지막 2자리 제거
    if (strlen($prod) <= 2) {
      return '0';
    }
    return ltrim(substr($prod, 0, -2), '0') ?: '0';
  }

  function 스왑_문자열_곱하기작은수(string $a, int $m): string {
    $a = 스왑_정수문자열($a);
    if ($m <= 0 || $a === '0') {
      return '0';
    }
    if ($m === 1) {
      return $a;
    }
    $carry = 0;
    $out = '';
    for ($i = strlen($a) - 1; $i >= 0; $i--) {
      $n = ((int)$a[$i]) * $m + $carry;
      $out = (string)($n % 10) . $out;
      $carry = intdiv($n, 10);
    }
    if ($carry > 0) {
      $out = (string)$carry . $out;
    }
    return ltrim($out, '0') ?: '0';
  }

  function 스왑_문자열_더하기(string $a, string $b): string {
    $a = 스왑_정수문자열($a);
    $b = 스왑_정수문자열($b);
    if (function_exists('bcadd')) {
      return 스왑_정수문자열(bcadd($a, $b, 0));
    }
    $i = strlen($a) - 1;
    $j = strlen($b) - 1;
    $carry = 0;
    $out = '';
    while ($i >= 0 || $j >= 0 || $carry > 0) {
      $da = $i >= 0 ? (int)$a[$i] : 0;
      $db = $j >= 0 ? (int)$b[$j] : 0;
      $s = $da + $db + $carry;
      $out = (string)($s % 10) . $out;
      $carry = intdiv($s, 10);
      $i--;
      $j--;
    }
    return ltrim($out, '0') ?: '0';
  }

  function 스왑_문자열_빼기(string $a, string $b): string {
    $a = 스왑_정수문자열($a);
    $b = 스왑_정수문자열($b);
    if (function_exists('bcsub')) {
      $r = bcsub($a, $b, 0);
      if (isset($r[0]) && $r[0] === '-') {
        return '0';
      }
      return 스왑_정수문자열($r);
    }
    if (function_exists('bcadd')) {
      $r = bcadd($a, '-' . $b, 0);
      if (isset($r[0]) && $r[0] === '-') {
        return '0';
      }
      return 스왑_정수문자열($r);
    }
    // a < b 이면 0
    if (strlen($a) < strlen($b) || (strlen($a) === strlen($b) && $a < $b)) {
      return '0';
    }
    $i = strlen($a) - 1;
    $j = strlen($b) - 1;
    $borrow = 0;
    $out = '';
    while ($i >= 0) {
      $da = (int)$a[$i] - $borrow;
      $db = $j >= 0 ? (int)$b[$j] : 0;
      if ($da < $db) {
        $da += 10;
        $borrow = 1;
      } else {
        $borrow = 0;
      }
      $out = (string)($da - $db) . $out;
      $i--;
      $j--;
    }
    return ltrim($out, '0') ?: '0';
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

  /** 연구실(본방) `.스왑` 이용 최소 보유냥 */
  function 스왑_본방_최소보유냥(): float {
    return 500;
  }

  /** 홍보방(게임방) `.스왑` 시 받을 본방냥 최소치 (비율 계산 후) */
  function 스왑_게임방_최소본방냥(): float {
    return 500;
  }

  /** 게임냥 → 본방냥 지정 스왑 최소 게임냥 (채팅 `.스왑` 등 게임냥 직접 입력용) */
  function 스왑_게임방_최소게임냥(): int {
    return 5000;
  }

  /**
   * 보유 게임냥 전액 스왑 시 받을 수 있는 최대 본방냥 (최소금액 검사 생략)
   * .환율과 동일 총량·비율의 역산
   */
  function 스왑_게임냥으로_최대본방냥($보유_pt): float {
    $보유 = 스왑_정수문자열($보유_pt);
    if ($보유 === '0') {
      return 0.0;
    }
    $총량 = 스왑_총량조회();
    $total_np = (float)$총량['total_np'];
    $total_pt = 스왑_정수문자열($총량['total_pt']);
    if ($total_np <= 0 || $total_pt === '0') {
      return 0.0;
    }
    $분리 = 스왑_게임방_수수료및교환분리($보유);
    $교환_pt = 스왑_정수문자열($분리['교환_pt'] ?? 0);
    if ($교환_pt === '0') {
      return 0.0;
    }
    return (float)스왑_pt2np_지급계산($교환_pt, $total_pt, $total_np);
  }

  /**
   * 받고 싶은 본방냥(실수령) → 필요한 게임냥(차감) 역산
   * 게임냥 20% 수수료 후 환율 교환으로 실수령 want 이상이 되게 계산
   * bcmath 없어도 동작 (보유 대비 최대수령 비율로 역산)
   * @return array{ok:bool, msg?:string, 차감_pt?:string, 원하는_np?:float, 최대_np?:float}
   */
  function 스왑_원하는본방냥_필요게임냥($원하는_np, $보유_pt = null): array {
    $총량 = 스왑_총량조회();
    $total_np = (float)$총량['total_np'];
    $total_pt = 스왑_정수문자열($총량['total_pt']);
    $want = round(max(0, (float)$원하는_np), 1);

    if ($total_np <= 0 || $total_pt === '0') {
      return ['ok' => false, 'msg' => '❌ 스왑할 수 있는 전체 냥이 부족해요.'];
    }
    if ($want < 0.1) {
      return ['ok' => false, 'msg' => '❌ 스왑할 본방냥을 입력해 주세요.'];
    }

    $보유 = ($보유_pt === null || $보유_pt === '') ? null : 스왑_정수문자열($보유_pt);
    $최대_np = 0.0;
    if ($보유 !== null && $보유 !== '0') {
      $최대_np = 스왑_게임냥으로_최대본방냥($보유);
      if ($want > $최대_np + 0.0001) {
        $최대표시 = function_exists('newpoint표시') ? newpoint표시($최대_np) : (string)$최대_np;
        return [
          'ok' => false,
          'msg' => "❌ 보유 게임냥으로 받을 수 있는 본방냥은 최대 {$최대표시}냥이에요.",
          '최대_np' => $최대_np,
        ];
      }
    }

    $수수료비율 = (int)스왑_게임방_수수료비율();
    $교환비율 = 100 - $수수료비율;
    if ($교환비율 < 1) {
      $교환비율 = 1;
    }

    $차감_pt = '0';

    // A안: 보유·최대수령이 있으면 비율로 역산 (bcmath 불필요, 해 단위 안전)
    if ($보유 !== null && $보유 !== '0' && $최대_np > 0.1) {
      if ($want + 0.0001 >= $최대_np) {
        $차감_pt = $보유;
      } else {
        // ceil(보유 × want / 최대) = floor((보유 × want_scaled + 최대_scaled - 1) / 최대_scaled)
        $차감_pt = 스왑_문자열_비례올림($보유, $want, $최대_np);
      }
    } else {
      // B안: 환율로 교환분 산출 후 수수료 역산
      $차감_pt = 스왑_본방수령_필요차감게임냥($want, $total_pt, $total_np, $교환비율);
    }

    $차감_pt = 스왑_정수문자열($차감_pt);
    if ($차감_pt === '0') {
      return ['ok' => false, 'msg' => '❌ 스왑 금액이 너무 적어요.'];
    }

    // 실수령 부족하면 차감 소량 증가 (최대 30회 — 배율 곱셈 금지)
    $wantStr = 스왑_소수문자열($want, 1);
    $가드 = 0;
    while ($가드 < 30) {
      $분리 = 스왑_게임방_수수료및교환분리($차감_pt);
      $지급 = (float)스왑_pt2np_지급계산($분리['교환_pt'] ?? 0, $total_pt, $total_np);
      if ($지급 + 0.0001 >= $want) {
        break;
      }
      if ($보유 !== null && $보유 !== '0' && 스왑_문자열_이상($차감_pt, $보유)) {
        break;
      }
      $차감_pt = 스왑_문자열_더하기($차감_pt, '1');
      $가드++;
    }

    // 보유 초과 시 클램프
    if ($보유 !== null && $보유 !== '0' && 스왑_문자열_이상($차감_pt, $보유)) {
      if ($want <= $최대_np + 0.0001) {
        $차감_pt = $보유;
      } else {
        $최대표시 = function_exists('newpoint표시') ? newpoint표시($최대_np) : (string)$최대_np;
        return [
          'ok' => false,
          'msg' => "❌ 보유 게임냥으로 받을 수 있는 본방냥은 최대 {$최대표시}냥이에요.",
          '최대_np' => $최대_np,
        ];
      }
    }

    return [
      'ok' => true,
      '차감_pt' => 스왑_정수문자열($차감_pt),
      '원하는_np' => $want,
      '최대_np' => $최대_np,
    ];
  }

  /** a >= b (정수 문자열) */
  function 스왑_문자열_이상(string $a, string $b): bool {
    $a = 스왑_정수문자열($a);
    $b = 스왑_정수문자열($b);
    if (function_exists('bccomp')) {
      return bccomp($a, $b, 0) >= 0;
    }
    if (strlen($a) !== strlen($b)) {
      return strlen($a) > strlen($b);
    }
    return $a >= $b;
  }

  /**
   * ceil(금액 × 분자 / 분모) — 분자·분모는 본방냥 규모(float), 금액은 해 단위 문자열
   */
  function 스왑_문자열_비례올림(string $금액, float $분자, float $분모): string {
    $금액 = 스왑_정수문자열($금액);
    if ($금액 === '0' || $분자 <= 0 || $분모 <= 0) {
      return '0';
    }
    if ($분자 + 0.0001 >= $분모) {
      return $금액;
    }
    // 스케일 10000으로 정수화
    $num = (int)round($분자 * 10000);
    $den = (int)round($분모 * 10000);
    if ($num < 1) {
      $num = 1;
    }
    if ($den < 1) {
      return '0';
    }
    if ($num >= $den) {
      return $금액;
    }

    if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bcadd')) {
      // ceil(금액*num/den) = floor((금액*num + den - 1)/den)
      $prod = bcmul($금액, (string)$num, 0);
      $prod = bcadd($prod, (string)($den - 1), 0);
      return 스왑_정수문자열(bcdiv($prod, (string)$den, 0));
    }

    $prod = 스왑_문자열_곱하기정수($금액, $num);
    $prod = 스왑_문자열_더하기($prod, (string)($den - 1));
    return 스왑_문자열_나누기내림($prod, $den);
  }

  /** 큰정수 × 작은정수(PHP int 범위) */
  function 스왑_문자열_곱하기정수(string $a, int $m): string {
    $a = 스왑_정수문자열($a);
    if ($m <= 0 || $a === '0') {
      return '0';
    }
    if ($m === 1) {
      return $a;
    }
    if (function_exists('bcmul')) {
      return 스왑_정수문자열(bcmul($a, (string)$m, 0));
    }
    // 자릿수 곱셈
    $mStr = (string)$m;
    $prod = '0';
    $mLen = strlen($mStr);
    for ($i = 0; $i < $mLen; $i++) {
      $d = (int)$mStr[$i];
      if ($d === 0) {
        continue;
      }
      $부분 = 스왑_문자열_곱하기작은수($a, $d) . str_repeat('0', $mLen - 1 - $i);
      $prod = 스왑_문자열_더하기($prod, $부분);
    }
    return $prod;
  }

  /** floor(큰정수 / 작은정수) */
  function 스왑_문자열_나누기내림(string $a, int $d): string {
    $a = 스왑_정수문자열($a);
    if ($d <= 0 || $a === '0') {
      return '0';
    }
    if ($d === 1) {
      return $a;
    }
    if (function_exists('bcdiv')) {
      return 스왑_정수문자열(bcdiv($a, (string)$d, 0));
    }
    // 장제법
    $결과 = '';
    $나머지 = 0;
    $len = strlen($a);
    for ($i = 0; $i < $len; $i++) {
      $나머지 = $나머지 * 10 + (int)$a[$i];
      $q = intdiv($나머지, $d);
      $결과 .= (string)$q;
      $나머지 = $나머지 % $d;
    }
    return ltrim($결과, '0') ?: '0';
  }

  /** want 본방 실수령에 필요한 차감 게임냥 (보유 비율 없이 환율만) */
  function 스왑_본방수령_필요차감게임냥(float $want, string $total_pt, float $total_np, int $교환비율): string {
    $wantStr = 스왑_소수문자열($want, 1);
    $npStr = 스왑_소수문자열($total_np, 4);
    $pt = 스왑_정수문자열($total_pt);
    if ($교환비율 < 1) {
      $교환비율 = 80;
    }

    if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bcadd') && function_exists('bccomp')) {
      $rate = bcdiv($pt, $npStr, 24);
      if (bccomp($rate, '0', 24) <= 0) {
        return '0';
      }
      $교환_pt = bcdiv(bcmul($wantStr, $rate, 12), '1', 0);
      if ($교환_pt === '' || $교환_pt === null || bccomp($교환_pt, '0', 0) <= 0) {
        $교환_pt = '1';
      }
      $가드 = 0;
      while ($가드 < 5) {
        $지급 = 스왑_pt2np_지급계산($교환_pt, $pt, $total_np);
        if (bccomp($지급, $wantStr, 1) >= 0) {
          break;
        }
        $교환_pt = bcadd($교환_pt, '1', 0);
        $가드++;
      }
      $차감 = bcdiv(bcadd(bcmul($교환_pt, '100', 0), (string)($교환비율 - 1), 0), (string)$교환비율, 0);
      return 스왑_정수문자열($차감);
    }

    // float 환율(본방냥 규모) × 문자열 게임총량 비율 — (int) 캐스팅 금지
    if ($total_np <= 0) {
      return '0';
    }
    // 교환 ≈ want * total_pt / total_np
    // = total_pt * want / total_np
    $교환_pt = 스왑_문자열_비례올림($pt, $want, $total_np);
    if ($교환_pt === '0') {
      $교환_pt = '1';
    }
    // 차감 = ceil(교환 * 100 / 교환비율)
    $차감 = 스왑_문자열_비례올림($교환_pt, 100.0, (float)$교환비율);
    return $차감 === '0' ? '1' : $차감;
  }

  /** `.스왑 금액` 지정 시 최소 스왑 보유냥 (본방) */
  function 스왑_지정_최소보유냥(): float {
    return 500;
  }

  /** 해(1만경+) 지급 시 BIGINT 오버플로 방지 — .입금과 동일하게 point DECIMAL(40,0) 보장 */
  function 스왑_point_컬럼_보장(): void {
    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }
  }

  /** @return array{total_np: float, total_pt: string} — status=0 실시간 합계 (스냅샷 미사용) */
  function 스왑_총량조회(): array {
    // 요청마다 실시간 합계 — 스냅샷/정적캐시로 .환율과 지갑 견적이 어긋나지 않게
    if (function_exists('시세기준_실시간합계')) {
      $live = 시세기준_실시간합계();
      return [
        'total_np' => (float)($live['본방냥'] ?? 0),
        'total_pt' => 스왑_정수문자열($live['게임냥'] ?? 0),
      ];
    }
    if (function_exists('우리방_실시간_총량')) {
      $live = 우리방_실시간_총량();
      return [
        'total_np' => (float)($live['본방냥'] ?? 0),
        'total_pt' => 스왑_정수문자열($live['게임냥'] ?? 0),
      ];
    }
    $row = db_select("
      SELECT
        COALESCE(SUM(newpoint), 0) AS total_np,
        CONCAT('N', CAST(COALESCE(SUM(CAST(IFNULL(point, 0) AS DECIMAL(65,0))), 0) AS CHAR)) AS total_pt
      FROM tb_member
      WHERE status = 0
    ");
    // 1.23e+20 을 '.' 앞에서 자르면 "1" — 정규화 함수에 원문 그대로
    $pt = function_exists('냥_금액원문_정규화')
      ? 냥_금액원문_정규화($row['total_pt'] ?? 'N0')
      : 스왑_정수문자열($row['total_pt'] ?? 'N0');
    return [
      'total_np' => (float)($row['total_np'] ?? 0),
      'total_pt' => $pt,
    ];
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
   * @param float|string|null $금액 np2pt: 보유냥(float), pt2np: 게임냥(문자열 허용·해 단위)
   */
  function 스왑_견적계산(string $방향, $금액 = null, bool $전액모드 = false, ?float $삭제비율_pct = null, bool $최소금액검사 = true): array {
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
      $스왑금액 = round(max(0, (float)$금액), 1);
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
      // 본방 전액 스왑(최소검사 생략)은 교환·지급 하한 없이 진행
      if ($최소금액검사 && !$전액모드) {
        if ($교환_np < 0.1) {
          return ['ok' => false, 'msg' => "❌ 스왑 금액이 너무 적어요. (삭제 {$적용삭제비율}% 후 교환분이 없어요)"];
        }
        $지급부족 = function_exists('bccomp')
          ? bccomp($지급_pt, '1', 0) < 0
          : ((int)$지급_pt < 1);
        if ($지급부족) {
          return ['ok' => false, 'msg' => "❌ 받을 게임냥이 1냥 미만이라 스왑할 수 없어요."];
        }
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

      if ($금액 === null || $금액 === '') {
        return ['ok' => false, 'msg' => "❌ 스왑 금액을 확인할 수 없어요."];
      }

      $스왑_pt = 스왑_정수문자열($금액);
      $최소_pt = (string)(int)스왑_게임방_최소게임냥();
      if ($최소금액검사 && !$전액모드) {
        $미만 = function_exists('bccomp')
          ? bccomp($스왑_pt, $최소_pt, 0) < 0
          : ((int)$스왑_pt < (int)$최소_pt);
        if ($미만) {
          return [
            'ok' => false,
            'msg' => "❌ 스왑 최소 금액은 게임냥 " . 스왑_게임냥_표시($최소_pt) . "냥 이상이에요.",
          ];
        }
      }

      $분리 = 스왑_게임방_수수료및교환분리($스왑_pt);
      $차감_pt = 스왑_정수문자열($분리['차감_pt']);
      $수수료_pt = 스왑_정수문자열($분리['수수료_pt']);
      $교환_pt = 스왑_정수문자열($분리['교환_pt']);
      // 수수료 제외 후 환율 교환 — .환율(np2pt)과 동일 비율의 역산
      $ptStr = 스왑_정수문자열($total_pt);
      $지급_np = (float)스왑_pt2np_지급계산($교환_pt, $ptStr, $total_np);

      $차감부족 = function_exists('bccomp')
        ? bccomp($차감_pt, '1', 0) < 0
        : ((int)$차감_pt < 1);
      if ($차감부족) {
        return ['ok' => false, 'msg' => "❌ 스왑할 게임냥이 없어요."];
      }
      $교환부족 = function_exists('bccomp')
        ? bccomp($교환_pt, '1', 0) < 0
        : ((int)$교환_pt < 1);
      if ($교환부족) {
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

  /**
   * 단소 시전 — 피해자 게임냥 0일 때 본방냥 10% → 게임냥 강제 스왑
   * (최소보유 검사 생략 · 기존 .스왑 환율·삭제 규칙)
   * @return array{ok:bool,차감_np?:float,지급_pt?:string,msg?:string}
   */
  function 스왑_본방_단소_자동스왑(string $닉, ?float $스왑_np = null): array {
    $닉 = trim($닉);
    $닉_esc = addslashes($닉);
    if ($닉_esc === '') {
      return ['ok' => false, '지급_pt' => '0'];
    }

    if (function_exists('스왑_point_컬럼_보장')) {
      스왑_point_컬럼_보장();
    }

    $회원 = db_select("
      SELECT CAST(IFNULL(newpoint, 0) AS CHAR) AS newpoint,
             CAST(IFNULL(point, 0) AS CHAR) AS point
      FROM tb_member
      WHERE name = '{$닉_esc}' AND status = 0
      LIMIT 1
    ");
    if (empty($회원)) {
      return ['ok' => false, '지급_pt' => '0'];
    }

    $보유_np = round((float)($회원['newpoint'] ?? 0), 1);
    if ($보유_np < 0.1) {
      return ['ok' => false, '지급_pt' => '0'];
    }

    // 명시 금액이 있으면 사용, 없으면 보유 본방냥의 10%
    if ($스왑_np !== null && $스왑_np > 0) {
      $금액 = round((float)$스왑_np, 1);
    } else {
      $금액 = round($보유_np * 0.1, 1);
    }
    if ($금액 < 0.1) {
      return ['ok' => false, '지급_pt' => '0'];
    }
    if ($금액 > $보유_np) {
      $금액 = $보유_np;
    }

    $견적 = 스왑_견적계산('np2pt', $금액, false, null, false);
    if (empty($견적['ok'])) {
      return ['ok' => false, '지급_pt' => '0'];
    }

    $차감_np = (float)($견적['차감_np'] ?? 0);
    $지급_pt = 스왑_정수문자열($견적['지급_pt'] ?? 0);
    $지급_pt = ltrim((string)$지급_pt, '0') ?: '0';
    if ($차감_np < 0.1 || $지급_pt === '0') {
      return ['ok' => false, '지급_pt' => '0'];
    }
    if ($보유_np + 1e-9 < $차감_np) {
      return ['ok' => false, '지급_pt' => '0'];
    }

    $sqlPt = function_exists('냥_SQL정수') ? 냥_SQL정수($지급_pt) : preg_replace('/[^\d]/', '', $지급_pt);
    db_query("
      UPDATE tb_member
      SET newpoint = newpoint - {$차감_np},
          point = point + {$sqlPt}
      WHERE name = '{$닉_esc}' AND status = 0 AND newpoint >= {$차감_np}
      LIMIT 1
    ");
    global $conn;
    $ok = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
    if (!$ok) {
      return ['ok' => false, '지급_pt' => '0'];
    }

    $npDisp = function_exists('newpoint표시') ? newpoint표시($차감_np) : number_format($차감_np, 1);
    $ptDisp = function_exists('냥축약표시')
      ? 냥축약표시($지급_pt)
      : (function_exists('게임냥_안전표시')
        ? 게임냥_안전표시($지급_pt, '냥')
        : ((function_exists('냥_숫자콤마') ? 냥_숫자콤마($지급_pt) : $지급_pt) . '냥'));
    // 단위 중복 방지
    $ptDisp = preg_replace('/냥\s*$/u', '', trim((string)$ptDisp));
    $npDisp = preg_replace('/냥\s*$/u', '', trim((string)$npDisp));

    if (function_exists('지급로그')) {
      지급로그('단소자동스왑', $닉, '본방10%', 0, $지급_pt);
    }

    return [
      'ok' => true,
      '차감_np' => $차감_np,
      '지급_pt' => $지급_pt,
      'msg' => "보유중인 본방냥 {$npDisp}가 게임냥 {$ptDisp}로 스왑되었다",
    ];
  }

  function 스왑_본방_실행(string $두자리닉넴, ?float $보유냥금액 = null, bool $간단출력 = false): void {
    $닉_esc = addslashes($두자리닉넴);

    $회원 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    if (empty($회원)) {
      if ($간단출력) {
        exit; // info1 `.스왑` — 무응답
      }
      echo 전송("❌ 회원 정보를 찾을 수 없어요.");
      exit;
    }

    $보유_np = round((float)($회원['newpoint'] ?? 0), 1);
    $최소보유 = 스왑_본방_최소보유냥();
    if ($간단출력) {
      // info1 `.스왑` — 500냥 이상이면 조건 없이 보유분 전액 스왑 · 응답 없음
      if ($보유_np < $최소보유) {
        exit;
      }
      $전액스왑 = true;
      $보유냥금액 = $보유_np;
    } else {
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

    // 본방 전액 스왑 — 최소금액·지급하한 등 추가 조건 생략
    $견적 = 스왑_견적계산('np2pt', $보유냥금액, $전액스왑, null, !$전액스왑);
    if (empty($견적['ok'])) {
      if ($간단출력) {
        exit;
      }
      echo 전송($견적['msg'] ?? "❌ 스왑을 처리할 수 없어요.");
      exit;
    }

    $차감_np = (float)$견적['차감_np'];
    $삭제_np = (float)($견적['삭제_np'] ?? 0);
    $교환_np = (float)($견적['교환_np'] ?? 0);
    $지급_pt = 스왑_정수문자열($견적['지급_pt'] ?? 0);
    $삭제비율 = 스왑_본방_삭제비율();
    if ($보유_np < $차감_np) {
      if ($간단출력) {
        exit;
      }
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

    스왑_point_컬럼_보장();
    db_query("
      UPDATE tb_member
      SET newpoint = newpoint - {$차감_np},
          point = point + {$지급_pt}
      WHERE name = '{$닉_esc}' AND status = 0
      LIMIT 1
    ");

    if ($간단출력) {
      // info1 `.스왑` — 스왑 실행 후 무응답
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
    $msg .= "현재 게임냥: " . 스왑_게임냥_표시($갱신['point'] ?? 0);
    echo 전송($msg);
    exit;
  }

  /**
   * 본냥 일부 남기고 나머지 → 게임냥
   * @return array{ok:bool, data?:string, point?:string, newpoint?:float, 지급_pt?:string, 차감_np?:float, 삭제_np?:float, 남김?:float}
   */
  function 스왑_본냥잔여_실행_데이터(string $두자리닉넴, float $남김 = 1000.0, ?float $삭제비율 = null): array {
    $닉_esc = addslashes($두자리닉넴);
    $회원 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    if (empty($회원)) {
      return ['ok' => false, 'data' => '❌ 회원 정보를 찾을 수 없어요.'];
    }

    $남김 = round(max(0, $남김), 1);
    $보유_np = round((float)($회원['newpoint'] ?? 0), 1);
    if ($보유_np <= $남김 + 1e-9) {
      return [
        'ok' => false,
        'data' => '❌ 본냥 ' . newpoint표시($남김) . "냥은 남겨야 해요.\n현재: " . newpoint표시($보유_np) . '냥',
      ];
    }

    $스왑금액 = round($보유_np - $남김, 1);
    if ($스왑금액 < 0.1) {
      return ['ok' => false, 'data' => '❌ 스왑할 본냥이 없어요.'];
    }

    if ($삭제비율 === null) {
      $삭제비율 = 스왑_홀짝_본방_삭제비율();
    }
    $삭제비율 = (float)$삭제비율;
    // 잔여 남기기 스왑 — 지정 최소금액 검사 생략
    $견적 = 스왑_견적계산('np2pt', $스왑금액, false, $삭제비율, false);
    if (empty($견적['ok'])) {
      return ['ok' => false, 'data' => $견적['msg'] ?? '❌ 스왑을 처리할 수 없어요.'];
    }

    $차감_np = (float)$견적['차감_np'];
    $삭제_np = (float)($견적['삭제_np'] ?? 0);
    $교환_np = (float)($견적['교환_np'] ?? 0);
    $지급_pt = 스왑_정수문자열($견적['지급_pt'] ?? 0);
    if ($보유_np + 1e-9 < $차감_np) {
      return ['ok' => false, 'data' => '❌ 본방냥이 부족해요.'];
    }

    스왑_point_컬럼_보장();
    db_query("
      UPDATE tb_member
      SET newpoint = newpoint - {$차감_np},
          point = point + {$지급_pt}
      WHERE name = '{$닉_esc}' AND status = 0
      LIMIT 1
    ");

    $갱신 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $게임냥 = 스왑_정수문자열($갱신['point'] ?? 0);
    $본방냥 = round((float)($갱신['newpoint'] ?? 0), 1);
    $교환비율 = 100 - $삭제비율;
    $msg = "💱 스왑 완료 (본냥 → 게임냥)\n\n";
    $msg .= '본냥 ' . newpoint표시($남김) . "냥 남김 · 나머지 스왑\n";
    $msg .= "({$삭제비율}% 삭제 · {$교환비율}% 환율 교환)\n";
    $msg .= '-' . newpoint표시($차감_np) . " 본냥\n";
    $msg .= '  └ 삭제 ' . newpoint표시($삭제_np) . ' · 교환 ' . newpoint표시($교환_np) . "\n";
    $msg .= '+' . 스왑_게임냥_표시($지급_pt) . " 게임냥\n\n";
    $msg .= '현재 본냥: ' . newpoint표시($본방냥) . "냥\n";
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
      '남김' => $남김,
    ];
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

    스왑_point_컬럼_보장();
    db_query("
      UPDATE tb_member
      SET newpoint = newpoint - {$차감_np},
          point = point + {$지급_pt}
      WHERE name = '{$닉_esc}' AND status = 0
      LIMIT 1
    ");

    $갱신 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $게임냥 = 스왑_정수문자열($갱신['point'] ?? 0);
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

    $보유_pt = 스왑_정수문자열($회원['point'] ?? 0);
    $보유부족 = function_exists('bccomp')
      ? bccomp($보유_pt, '1', 0) < 0
      : ((int)$보유_pt < 1);
    if ($보유부족) {
      echo 전송("❌ 스왑할 게임냥이 없어요.\n현재: " . 스왑_게임냥_표시($보유_pt) . " 게임냥");
      exit;
    }

    $견적 = 스왑_견적계산('pt2np', $보유_pt, true);
    if (empty($견적['ok'])) {
      echo 전송($견적['msg'] ?? "❌ 스왑을 처리할 수 없어요.");
      exit;
    }

    $차감_pt = 스왑_정수문자열($견적['차감_pt'] ?? 0);
    $수수료_pt = 스왑_정수문자열($견적['수수료_pt'] ?? 0);
    $교환_pt = 스왑_정수문자열($견적['교환_pt'] ?? 0);
    $지급_np = (float)$견적['지급_np'];
    $수수료비율 = 스왑_게임방_수수료비율();

    $잔액부족 = function_exists('bccomp')
      ? bccomp($보유_pt, $차감_pt, 0) < 0
      : ((float)$보유_pt < (float)$차감_pt);
    if ($잔액부족) {
      echo 전송(
        "❌ 게임냥이 부족해요.\n"
        . "필요: " . 스왑_게임냥_표시($차감_pt) . " 게임냥 (게임냥 전액)\n"
        . "현재: " . 스왑_게임냥_표시($보유_pt) . " 게임냥"
      );
      exit;
    }

    스왑_point_컬럼_보장();
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
    $msg .= "현재 게임냥: " . 스왑_게임냥_표시($갱신['point'] ?? 0);
    echo 전송($msg);
    exit;
  }

  /** @deprecated 본냥스왑은 전액 스왑 — 잔여 없음 */
  function 스왑_홍보방_본냥잔여(): float {
    return 0.0;
  }

  /** 홍보방 `.겜냥스왑` — 남길 게임냥 (1억) */
  function 스왑_홍보방_겜냥잔여(): string {
    return '100000000';
  }

  /** 홍보방 `.스왑` 안내 */
  function 스왑_홍보방_안내문구(): string {
    $겜냥남김 = 스왑_홍보방_겜냥잔여();
    $삭제비율 = (int)스왑_본방_삭제비율();
    $교환비율 = 100 - $삭제비율;
    $수수료 = (int)스왑_게임방_수수료비율();
    $수수료교환 = 100 - $수수료;
    $최소본방 = (int)스왑_게임방_최소본방냥();

    $msg = "💱 홍보방 스왑 안내\n\n";
    $msg .= "• `.본냥스왑`\n";
    $msg .= "  본냥 → 게임냥\n";
    $msg .= "  보유 본냥 전액 스왑 (잔여·최소 없음)\n";
    $msg .= "  (스왑분 {$삭제비율}% 삭제 · {$교환비율}% 환율 교환)\n\n";
    $msg .= "• `.겜냥스왑`\n";
    $msg .= "  게임냥 → 본냥\n";
    $msg .= "  게임냥 " . 스왑_게임냥_표시($겜냥남김, '냥') . "을 남기고 나머지 전부 스왑\n";
    $msg .= "  (수수료 {$수수료}% · {$수수료교환}% 환율 교환)\n";
    $msg .= "  ※ 받을 본냥이 {$최소본방}냥 이상일 때만 가능";
    return $msg;
  }

  /**
   * 홍보방 `.본냥스왑` — 본냥 전액 → 게임냥 (10% 삭제 · 90% 환율 · 잔여·최소 없음)
   */
  function 스왑_본냥스왑_실행(string $두자리닉넴): void {
    $닉_esc = addslashes($두자리닉넴);
    $회원 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    if (empty($회원)) {
      echo 전송("❌ 회원 정보를 찾을 수 없어요.");
      exit;
    }

    $보유_np = round((float)($회원['newpoint'] ?? 0), 1);
    if ($보유_np < 0.1) {
      echo 전송("❌ 스왑할 본냥이 없어요.\n현재: " . newpoint표시($보유_np) . "냥");
      exit;
    }

    // 전액 스왑 — 잔여·최소금액·지급하한 검사 생략
    $견적 = 스왑_견적계산('np2pt', $보유_np, true, null, false);
    if (empty($견적['ok'])) {
      echo 전송($견적['msg'] ?? "❌ 스왑을 처리할 수 없어요.");
      exit;
    }

    $차감_np = (float)$견적['차감_np'];
    $삭제_np = (float)($견적['삭제_np'] ?? 0);
    $교환_np = (float)($견적['교환_np'] ?? 0);
    $지급_pt = 스왑_정수문자열($견적['지급_pt'] ?? 0);
    $삭제비율 = 스왑_본방_삭제비율();
    if ($보유_np + 1e-9 < $차감_np) {
      echo 전송(
        "❌ 보유냥이 부족해요.\n"
        . "필요: " . newpoint표시($차감_np) . "냥\n"
        . "현재: " . newpoint표시($보유_np) . "냥"
      );
      exit;
    }

    스왑_point_컬럼_보장();
    db_query("
      UPDATE tb_member
      SET newpoint = newpoint - {$차감_np},
          point = point + {$지급_pt}
      WHERE name = '{$닉_esc}' AND status = 0
      LIMIT 1
    ");

    $갱신 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $msg = "💱 본냥스왑 완료 (본냥 → 게임냥)\n\n";
    $msg .= "본냥 전액 스왑\n";
    $msg .= "(스왑냥 {$삭제비율}% 삭제 · " . (100 - $삭제비율) . "% 환율 교환)\n";
    $msg .= "-" . newpoint표시($차감_np) . " 본냥\n";
    $msg .= "  └ 삭제 " . newpoint표시($삭제_np) . "냥 · 교환 " . newpoint표시($교환_np) . "냥\n";
    $msg .= "+" . 스왑_게임냥_표시($지급_pt) . " 게임냥\n\n";
    $msg .= "현재 본냥: " . newpoint표시($갱신['newpoint'] ?? 0) . "냥\n";
    $msg .= "현재 게임냥: " . 스왑_게임냥_표시($갱신['point'] ?? 0);
    echo 전송($msg);
    exit;
  }

  /**
   * 홍보방 `.겜냥스왑` — 게임냥 1억 남기고 나머지 → 본냥
   */
  function 스왑_겜냥스왑_실행(string $두자리닉넴): void {
    $닉_esc = addslashes($두자리닉넴);
    $회원 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' AND status = 0 LIMIT 1");
    if (empty($회원)) {
      echo 전송("❌ 회원 정보를 찾을 수 없어요.");
      exit;
    }

    $남김 = 스왑_홍보방_겜냥잔여();
    $보유_pt = 스왑_정수문자열($회원['point'] ?? 0);
    $남김가능 = function_exists('bccomp')
      ? bccomp($보유_pt, $남김, 0) > 0
      : ((float)$보유_pt > (float)$남김);
    if (!$남김가능) {
      echo 전송(
        "❌ 게임냥 " . 스왑_게임냥_표시($남김, '냥') . "은 남겨야 해요.\n"
        . "현재: " . 스왑_게임냥_표시($보유_pt) . " 게임냥"
      );
      exit;
    }

    $스왑_pt = function_exists('bcsub')
      ? 스왑_정수문자열(bcsub($보유_pt, $남김, 0))
      : 스왑_정수문자열(스왑_문자열_빼기($보유_pt, $남김));
    $스왑부족 = function_exists('bccomp')
      ? bccomp($스왑_pt, '1', 0) < 0
      : ((int)$스왑_pt < 1);
    if ($스왑부족) {
      echo 전송("❌ 스왑할 게임냥이 없어요.\n현재: " . 스왑_게임냥_표시($보유_pt) . " 게임냥");
      exit;
    }

    // 1억 잔여 남기기 — 지정 최소 게임냥 검사는 생략하되, 받을 본냥 최소는 유지
    $견적 = 스왑_견적계산('pt2np', $스왑_pt, false, null, false);
    if (empty($견적['ok'])) {
      echo 전송($견적['msg'] ?? "❌ 스왑을 처리할 수 없어요.");
      exit;
    }

    $차감_pt = 스왑_정수문자열($견적['차감_pt'] ?? 0);
    $수수료_pt = 스왑_정수문자열($견적['수수료_pt'] ?? 0);
    $교환_pt = 스왑_정수문자열($견적['교환_pt'] ?? 0);
    $지급_np = (float)$견적['지급_np'];
    $수수료비율 = 스왑_게임방_수수료비율();

    $잔액부족 = function_exists('bccomp')
      ? bccomp($보유_pt, $차감_pt, 0) < 0
      : ((float)$보유_pt < (float)$차감_pt);
    if ($잔액부족) {
      echo 전송(
        "❌ 게임냥이 부족해요.\n"
        . "필요: " . 스왑_게임냥_표시($차감_pt) . " 게임냥\n"
        . "현재: " . 스왑_게임냥_표시($보유_pt) . " 게임냥"
      );
      exit;
    }

    스왑_point_컬럼_보장();
    db_query("
      UPDATE tb_member
      SET point = point - {$차감_pt},
          newpoint = newpoint + {$지급_np}
      WHERE name = '{$닉_esc}' AND status = 0
      LIMIT 1
    ");

    $갱신 = db_select("SELECT newpoint, point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $msg = "💱 겜냥스왑 완료 (게임냥 → 본냥)\n\n";
    $msg .= "게임냥 " . 스왑_게임냥_표시($남김, '냥') . " 남김 · 나머지 스왑\n";
    $msg .= "(수수료 {$수수료비율}% · " . (100 - $수수료비율) . "% 환율 교환)\n";
    $msg .= "-" . 스왑_게임냥_표시($차감_pt) . " 게임냥\n";
    $msg .= "  └ 수수료 " . 스왑_게임냥_표시($수수료_pt) . " · 교환 " . 스왑_게임냥_표시($교환_pt) . "\n";
    $msg .= "+" . newpoint표시($지급_np) . " 본냥\n\n";
    $msg .= "현재 본냥: " . newpoint표시($갱신['newpoint'] ?? 0) . "냥\n";
    $msg .= "현재 게임냥: " . 스왑_게임냥_표시($갱신['point'] ?? 0);
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
    $msg .= "- 스왑 환율은 정상 회원 전체 본방·게임냥 실시간 합계 기준이에요.\n";
    $msg .= "- 누군가 스왑·지급·소각하면 바로 비율이 달라질 수 있어요.\n";
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
      if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($digits, '냥');
      }
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
