<?php
/**
 * `.선물` — info1/_mutual · info2 · info3 공용
 * 예) .선물 이지 강일 10
 * - 일반 아이템: 선물 토큰 1개 + 해당 아이템 N개 소모 → 상대에게 N개 지급 (N 최대 100)
 * - 선물 토큰 자체를 보낼 때만 1:1 (선물 N개 → N개)
 * - bag 추적 아이템은 tb_member_item_bag 사용
 */

if (!defined('선물_명령_최대수량')) {
  define('선물_명령_최대수량', 100);
}

if (!function_exists('선물_명령_행수량')) {
  function 선물_명령_행수량(string $닉, string $아이템명, int $midx = 0): int {
    $아이템명 = trim($아이템명);
    $닉 = trim($닉);
    if ($아이템명 === '' || $닉 === '') {
      return 0;
    }
    $닉_esc = addslashes($닉);
    $아이템_esc = addslashes($아이템명);
    if ($midx > 0) {
      $행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$midx} AND itemname = '{$아이템_esc}' AND status = 0");
    } else {
      $행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE nick = '{$닉_esc}' AND itemname = '{$아이템_esc}' AND status = 0");
    }
    return (int)($행['cnt'] ?? 0);
  }
}

if (!function_exists('선물_명령_통일수량_준비')) {
  /**
   * bag · tb_member_item 중 많은 쪽을 기준으로 bag을 맞춘 뒤 보유수량 반환
   * (미이관 행 재고 때문에 `.선물`이 실패하던 문제 보정)
   */
  function 선물_명령_통일수량_준비(string $닉, string $아이템명, int $midx = 0): int {
    $아이템명 = trim($아이템명);
    $닉 = trim($닉);
    if ($아이템명 === '' || $닉 === '') {
      return 0;
    }
    $행수량 = 선물_명령_행수량($닉, $아이템명, $midx);
    if (!(function_exists('item_bag_tracked') && item_bag_tracked($아이템명) && function_exists('item_bag_qty_nick'))) {
      return $행수량;
    }
    $bag수량 = (int)item_bag_qty_nick($닉, $아이템명);
    $통일 = max($bag수량, $행수량);
    if ($통일 > $bag수량 && function_exists('item_bag_member_by_nick') && function_exists('item_bag_ensure_member')) {
      $mem = item_bag_member_by_nick($닉);
      if ($mem !== null) {
        $idx = (int)$mem['idx'];
        item_bag_ensure_member($idx, (string)$mem['name']);
        $col = str_replace('`', '``', $아이템명);
        @db_query("UPDATE tb_member_item_bag SET `{$col}` = {$통일} WHERE midx = {$idx} LIMIT 1");
      }
    }
    return $통일;
  }
}

if (!function_exists('선물_명령_보유수량')) {
  function 선물_명령_보유수량(string $닉, string $아이템명, int $midx = 0): int {
    return 선물_명령_통일수량_준비($닉, $아이템명, $midx);
  }
}

if (!function_exists('선물_명령_차감')) {
  /** @return array{ok:bool,msg?:string} */
  function 선물_명령_차감(string $닉, string $아이템명, int $수량, int $midx = 0): array {
    $수량 = max(1, (int)$수량);
    $아이템명 = trim($아이템명);
    $닉 = trim($닉);
    선물_명령_통일수량_준비($닉, $아이템명, $midx);
    if (function_exists('item_bag_tracked') && item_bag_tracked($아이템명) && function_exists('item_bag_sub_nick')) {
      $r = item_bag_sub_nick($닉, $아이템명, $수량);
      if (!empty($r['ok'])) {
        // 행 재고도 같이 소모해 다음 동기화 때 다시 부풀어 오르지 않게 함
        $닉_esc = addslashes($닉);
        $아이템_esc = addslashes($아이템명);
        if ($midx > 0) {
          db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE midx = {$midx} AND itemname = '{$아이템_esc}' AND status = 0 ORDER BY idx ASC LIMIT {$수량}");
        } else {
          db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE nick = '{$닉_esc}' AND itemname = '{$아이템_esc}' AND status = 0 ORDER BY idx ASC LIMIT {$수량}");
        }
      }
      return ['ok' => !empty($r['ok']), 'msg' => (string)($r['msg'] ?? '')];
    }
    $닉_esc = addslashes($닉);
    $아이템_esc = addslashes($아이템명);
    if ($midx > 0) {
      db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE midx = {$midx} AND itemname = '{$아이템_esc}' AND status = 0 ORDER BY idx ASC LIMIT {$수량}");
    } else {
      db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE nick = '{$닉_esc}' AND itemname = '{$아이템_esc}' AND status = 0 ORDER BY idx ASC LIMIT {$수량}");
    }
    return ['ok' => true];
  }
}

if (!function_exists('선물_명령_지급')) {
  /** @return array{ok:bool,msg?:string} */
  function 선물_명령_지급(string $닉, string $아이템명, int $수량, int $midx = 0): array {
    $수량 = max(1, (int)$수량);
    $아이템명 = trim($아이템명);
    $닉 = trim($닉);
    if (function_exists('item_bag_tracked') && item_bag_tracked($아이템명) && function_exists('item_bag_add_nick')) {
      $r = item_bag_add_nick($닉, $아이템명, $수량);
      return ['ok' => !empty($r['ok']), 'msg' => (string)($r['msg'] ?? '')];
    }
    $닉_esc = addslashes($닉);
    $아이템_esc = addslashes($아이템명);
    $midx = max(0, (int)$midx);
    for ($i = 0; $i < $수량; $i++) {
      db_query("INSERT INTO tb_member_item SET midx = {$midx}, nick = '{$닉_esc}', status = 0, itemname = '{$아이템_esc}', regdate = NOW()");
    }
    return ['ok' => true];
  }
}

if (!function_exists('선물_명령_처리')) {
  /**
   * @param array $정보 tb_member 행 (idx 포함)
   */
  function 선물_명령_처리(string $status, string $보내는닉, array $정보): void {
    $선물_trim = trim($status);
    $보내는닉 = trim($보내는닉);
    $내idx = (int)($정보['idx'] ?? 0);

    // .선물 [받는사람] 은총 — 불가 (은총조각만 선물 가능)
    if (preg_match('/^\.선물\s+(\S+)\s+은총(?:\s+(\d+))?\s*$/u', $선물_trim)) {
      echo 전송("❌ 은총은 선물할 수 없어요.\n은총조각은 가능합니다.\n예) .선물 지호 은총조각 5");
      exit;
    }

    // .선물 [받는사람] 은총조각 [개수]
    if (preg_match('/^\.선물\s+(\S+)\s+은총조각(?:\s+(\d+))?\s*$/u', $선물_trim, $m_shard)) {
      $받는닉 = function_exists('getTwoCharNick') ? getTwoCharNick($m_shard[1]) : trim($m_shard[1]);
      if ($받는닉 === '') {
        $받는닉 = trim($m_shard[1]);
      }
      $수량 = (isset($m_shard[2]) && $m_shard[2] !== '') ? (int)$m_shard[2] : 1;
      $받는닉_esc = addslashes($받는닉);

      if ($수량 <= 0) {
        echo 전송("❌ 은총조각 개수는 1 이상 숫자로 입력해주세요.\n예) .선물 지호 은총조각 5");
        exit;
      }
      $선물최대 = (int)선물_명령_최대수량;
      if ($수량 > $선물최대) {
        echo 전송("❌ 한 번에 최대 {$선물최대}개까지 선물할 수 있어요.\n예) .선물 지호 은총조각 {$선물최대}");
        exit;
      }
      if ($받는닉 === $보내는닉) {
        echo 전송("❌ 본인에게는 선물할 수 없어요.");
        exit;
      }
      if ($내idx <= 0) {
        echo 전송("❌ 회원 정보를 찾을 수 없어요.");
        exit;
      }

      $orePath = __DIR__ . '/game/mining_ore.inc.php';
      if ((!function_exists('mining_ore_shard_count') || !function_exists('mining_ore_spend_shards') || !function_exists('mining_ore_add_shards'))
          && is_file($orePath)) {
        require_once $orePath;
      }
      if (!function_exists('mining_ore_shard_count') || !function_exists('mining_ore_spend_shards') || !function_exists('mining_ore_add_shards')) {
        echo 전송('❌ 은총조각 선물 기능을 불러올 수 없어요.');
        exit;
      }

      // 선물 토큰 1개로 최대 100개까지
      $선물보유 = 선물_명령_보유수량($보내는닉, '선물', $내idx);
      if ($선물보유 < 1) {
        echo 전송("❌ 선물 아이템이 없어요. (필요: 1개 · 보유: {$선물보유}개)\n※ 선물 1개로 은총조각 최대 {$선물최대}개까지 보낼 수 있어요.");
        exit;
      }
      $내조각 = (int)mining_ore_shard_count($보내는닉);
      if ($내조각 < $수량) {
        echo 전송("❌ 은총조각이 부족해요. (필요: {$수량}개 · 보유: {$내조각}개)");
        exit;
      }
      $받는사람 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$받는닉_esc}' LIMIT 1");
      if (empty($받는사람['idx'])) {
        echo 전송("❌ [ {$받는닉} ] 회원을 찾을 수 없어요.");
        exit;
      }

      $차감 = 선물_명령_차감($보내는닉, '선물', 1, $내idx);
      if (empty($차감['ok'])) {
        echo 전송('❌ 선물 아이템 차감에 실패했어요.');
        exit;
      }
      if (function_exists('아이템사용_시세하락')) {
        아이템사용_시세하락('선물', 1);
      }
      $조각차감 = mining_ore_spend_shards($보내는닉, $수량);
      if (empty($조각차감['ok'])) {
        echo 전송('❌ 은총조각 차감에 실패했어요. ' . trim((string)($조각차감['data'] ?? '')));
        exit;
      }
      $조각지급 = mining_ore_add_shards($받는닉, $수량);
      $완료문구 = "🎁 [ {$받는닉} ]에게 은총조각 {$수량}개 선물 완료!";
      $변환 = (int)($조각지급['eunchong_granted'] ?? 0);
      if ($변환 > 0) {
        $완료문구 .= "\n✨ 받는 분 은총조각이 모여 은총 {$변환}개로 변환됐어요.";
      }
      echo 전송($완료문구);
      exit;
    }

    // .선물 [받는사람] 강화수호 [개수]
    if (preg_match('/^\.선물\s+(\S+)\s+강화수호(?:\s+(\d+))?\s*$/u', $선물_trim, $m_suho)) {
      $받는닉 = function_exists('getTwoCharNick') ? getTwoCharNick($m_suho[1]) : trim($m_suho[1]);
      if ($받는닉 === '') {
        $받는닉 = trim($m_suho[1]);
      }
      $수량 = (isset($m_suho[2]) && $m_suho[2] !== '') ? (int)$m_suho[2] : 1;
      $받는닉_esc = addslashes($받는닉);
      $보내는_esc = addslashes($보내는닉);

      if ($수량 <= 0) {
        echo 전송("❌ 강화수호 개수는 1 이상 숫자로 입력해주세요.\n예) .선물 지호 강화수호 5");
        exit;
      }
      $선물최대 = (int)선물_명령_최대수량;
      if ($수량 > $선물최대) {
        echo 전송("❌ 한 번에 최대 {$선물최대}회까지 선물할 수 있어요.\n예) .선물 지호 강화수호 {$선물최대}");
        exit;
      }
      if ($받는닉 === $보내는닉) {
        echo 전송("❌ 본인에게는 선물할 수 없어요.");
        exit;
      }
      if ($내idx <= 0) {
        echo 전송("❌ 회원 정보를 찾을 수 없어요.");
        exit;
      }

      $선물보유 = 선물_명령_보유수량($보내는닉, '선물', $내idx);
      if ($선물보유 < 1) {
        echo 전송("❌ 선물 아이템이 없어요. (필요: 1개 · 보유: {$선물보유}개)\n※ 선물 1개로 강화수호 최대 {$선물최대}회까지 보낼 수 있어요.");
        exit;
      }
      if (!function_exists('bag_강화수호_차감') && is_file(__DIR__ . '/item_bag_enhance.inc.php')) {
        require_once __DIR__ . '/item_bag_enhance.inc.php';
      }
      $내수호 = function_exists('bag_강화수호_수량')
        ? bag_강화수호_수량($보내는닉)
        : (int)((db_select("SELECT IFNULL(enhance_suho, 0) AS suho FROM tb_member WHERE idx = {$내idx} LIMIT 1")['suho'] ?? 0));
      if ($내수호 < $수량) {
        echo 전송("❌ 강화수호가 부족해요. (필요: {$수량}회 · 보유: {$내수호}회)");
        exit;
      }
      $받는사람 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$받는닉_esc}' LIMIT 1");
      if (empty($받는사람['idx'])) {
        echo 전송("❌ [ {$받는닉} ] 회원을 찾을 수 없어요.");
        exit;
      }

      $차감 = 선물_명령_차감($보내는닉, '선물', 1, $내idx);
      if (empty($차감['ok'])) {
        echo 전송('❌ 선물 아이템 차감에 실패했어요.');
        exit;
      }
      if (function_exists('아이템사용_시세하락')) {
        아이템사용_시세하락('선물', 1);
      }
      if (function_exists('bag_강화수호_차감') && function_exists('bag_강화수호_가산')) {
        $차감수호 = bag_강화수호_차감($보내는닉, $수량);
        if (empty($차감수호['ok'])) {
          echo 전송('❌ 강화수호 차감에 실패했어요.');
          exit;
        }
        bag_강화수호_가산($받는닉, $수량);
      } else {
        db_query("UPDATE tb_member SET enhance_suho = GREATEST(IFNULL(enhance_suho, 0) - {$수량}, 0) WHERE name = '{$보내는_esc}'");
        db_query("UPDATE tb_member SET enhance_suho = IFNULL(enhance_suho, 0) + {$수량} WHERE name = '{$받는닉_esc}'");
      }
      echo 전송("🎁 [ {$받는닉} ]에게 강화수호 {$수량}회 선물 완료!");
      exit;
    }

    // .선물 [받는사람] [아이템] [수량]
    // 일반 아이템: 선물 1개 + 아이템 N개 (N≤100) · 선물 토큰 자체 전송만 1:1
    if (preg_match('/^\.선물\s+(\S+)\s+(\S+)(?:\s+(\d+))?\s*$/u', $선물_trim, $m)) {
      $받는닉 = function_exists('getTwoCharNick') ? getTwoCharNick($m[1]) : trim($m[1]);
      if ($받는닉 === '') {
        $받는닉 = trim($m[1]);
      }
      $선물아이템 = trim($m[2]);
      $수량 = isset($m[3]) && $m[3] !== '' ? (int)$m[3] : 1;
      if ($수량 <= 0) {
        $수량 = 1;
      }
      $선물최대 = (int)선물_명령_최대수량;
      if ($수량 > $선물최대) {
        echo 전송("❌ 한 번에 최대 {$선물최대}개까지 선물할 수 있어요.\n예) .선물 앙앙 지호 {$선물최대}");
        exit;
      }

      $받는닉_esc = addslashes($받는닉);
      $선물아이템_esc = addslashes($선물아이템);

      if (!function_exists('선물_가능_아이템명') || !선물_가능_아이템명($선물아이템)) {
        echo 전송("❌ [ {$선물아이템} ] 은(는) 선물할 수 없는 아이템이에요.\n(2글자 아이템 · 일방신청권 · 일방연장권 · 은총조각)\n※ 은총은 선물 불가");
        exit;
      }

      $선물아이템_검증 = db_select("SELECT idx FROM tb_item WHERE sname = '{$선물아이템_esc}' LIMIT 1");
      $장문선물 = in_array($선물아이템, ['일방신청권', '일방연장권', '은총조각'], true);
      if (empty($선물아이템_검증['idx']) && !$장문선물) {
        echo 전송("❌ [ {$선물아이템} ] 아이템을 찾을 수 없어요. 이름을 확인해 주세요.");
        exit;
      }

      if ($받는닉 === $보내는닉) {
        echo 전송("❌ 본인에게는 선물할 수 없어요.");
        exit;
      }
      if ($내idx <= 0) {
        echo 전송("❌ 회원 정보를 찾을 수 없어요.");
        exit;
      }

      // 선물 토큰을 보내는 경우만 N:N, 그 외는 선물 1개로 N개 전송
      $토큰소모 = ($선물아이템 === '선물') ? $수량 : 1;
      $토큰보유 = 선물_명령_보유수량($보내는닉, '선물', $내idx);
      if ($토큰보유 < $토큰소모) {
        if ($선물아이템 === '선물') {
          echo 전송("❌ 선물 아이템이 부족해요. (필요: {$토큰소모}개 · 보유: {$토큰보유}개)");
        } else {
          echo 전송("❌ 선물 아이템이 없어요. (필요: 1개 · 보유: {$토큰보유}개)\n※ 선물 1개로 [ {$선물아이템} ] 최대 {$선물최대}개까지 보낼 수 있어요.");
        }
        exit;
      }

      if ($선물아이템 !== '선물') {
        $실물보유 = 선물_명령_보유수량($보내는닉, $선물아이템, $내idx);
        if ($실물보유 < 1) {
          echo 전송("❌ [ {$선물아이템} ] 을(를) 보유하고 있지 않아요. (보유: 0개)");
          exit;
        }
        if ($실물보유 < $수량) {
          echo 전송("❌ [ {$선물아이템} ] 보유가 부족해요. (필요: {$수량}개 · 보유: {$실물보유}개)");
          exit;
        }
      }

      $받는사람 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$받는닉_esc}' LIMIT 1");
      if (empty($받는사람['idx'])) {
        echo 전송("❌ [ {$받는닉} ] 회원을 찾을 수 없어요.");
        exit;
      }
      $받는idx = (int)$받는사람['idx'];

      $토큰차감 = 선물_명령_차감($보내는닉, '선물', $토큰소모, $내idx);
      if (empty($토큰차감['ok'])) {
        echo 전송('❌ 선물 아이템 차감에 실패했어요.');
        exit;
      }
      if ($선물아이템 !== '선물') {
        $실물차감 = 선물_명령_차감($보내는닉, $선물아이템, $수량, $내idx);
        if (empty($실물차감['ok'])) {
          // 토큰만 쓰인 상태 — 가능하면 롤백은 생략하고 안내
          echo 전송('❌ [ ' . $선물아이템 . ' ] 차감에 실패했어요. 관리자에게 문의해주세요.');
          exit;
        }
      }

      if (function_exists('아이템사용_시세하락')) {
        아이템사용_시세하락('선물', $토큰소모);
        if ($선물아이템 !== '선물') {
          아이템사용_시세하락($선물아이템, $수량);
        }
      }

      $지급 = 선물_명령_지급($받는닉, $선물아이템, $수량, $받는idx);
      if (empty($지급['ok'])) {
        echo 전송('❌ 상대에게 아이템 지급에 실패했어요. 관리자에게 문의해주세요.');
        exit;
      }

      if ($선물아이템 === '일방신청권') {
        if (function_exists('일방신청권_지급기록')) {
          일방신청권_지급기록($받는닉, '선물', [
            'midx' => $받는idx,
            'from_nick' => $보내는닉,
            'reason_text' => "선물 수령 (보낸이: {$보내는닉})",
            'qty' => $수량,
          ]);
        }
        if (function_exists('미션완료_기록_if_new')) {
          미션완료_기록_if_new($받는닉, '일방', '200타');
        }
      }

      $수량문구 = ($수량 > 1) ? " x{$수량}" : '';
      $완료 = "🎁 [ {$받는닉} ]에게 {$선물아이템}{$수량문구} 아이템 선물 완료!";
      if ($선물아이템 !== '선물' && $토큰소모 === 1) {
        $완료 .= "\n(선물 아이템 1개 사용)";
      }
      echo 전송($완료);
      exit;
    }

    $선물최대 = (int)선물_명령_최대수량;
    echo 전송("❌ 사용법: .선물 (받는사람) (아이템) [수량]\n예) .선물 앙앙 지호 100\n예) .선물 이지 강일\n※ 선물 1개로 최대 {$선물최대}개까지\n강화수호: .선물 지호 강화수호 5\n은총조각: .선물 지호 은총조각 5\n※ 은총은 선물 불가");
    exit;
  }
}
