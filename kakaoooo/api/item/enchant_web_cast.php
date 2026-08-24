<?php
/**
 * 무기 강화 웹 — 채팅 .시전/.보호 와 동일 한도·내구도 적용
 */

if (!function_exists('enchant_web_cast_한도표')) {
  function enchant_web_cast_한도표() {
    $표 = [];
    for ($i = 1; $i <= 100; $i++) {
      $표[$i] = $i * 2;
    }
    return $표;
  }
}

if (!function_exists('enchant_web_cast_status')) {
  function enchant_web_cast_status($닉, array $회원) {
    $닉 = trim((string)$닉);
    $esc = addslashes($닉);
    $무기 = trim((string)($회원['item'] ?? ''));
    $강화 = (int)($회원['enhance'] ?? 0);
    $쿨 = isset($GLOBALS['쿨타임']) ? (int)$GLOBALS['쿨타임'] : 1;

    $행 = db_select("SELECT magic_used, magic_window, durability, point FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    if ($무기 === '🪄마법' || $무기 === '🪄 마법') {
      if (function_exists('마법_시전보호_공용한도_동기화')) {
        마법_시전보호_공용한도_동기화($닉, $쿨);
      }
      $행 = db_select("SELECT magic_used, magic_window, durability, point FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    }
    $magic_used = (int)($행['magic_used'] ?? 0);
    $durability = ($행['durability'] ?? null) !== null ? (int)$행['durability'] : null;
    $cast_limit_single = function_exists('무기_시전한도_3시간') ? 무기_시전한도_3시간($무기, $강화) : 0;
    $is_magic = ($무기 === '🪄마법' || $무기 === '🪄 마법');
    $cast_limit = ($is_magic && function_exists('마법_시전보호_공용_무료한도'))
      ? 마법_시전보호_공용_무료한도($무기, $강화)
      : $cast_limit_single;
    // 마법: 시전·보호 합산 공용 한도 (단독×2)
    $protect_limit = $is_magic ? $cast_limit : 0;
    $protect_used = ($protect_limit > 0) ? $magic_used : 0;

    $지금 = time();
    $cast_reset_sec = 0;
    $구간 = function_exists('무기_시전구간_적용') ? 무기_시전구간_적용($닉, $쿨, false) : null;
    if (is_array($구간)) {
      $magic_used = (int)($구간['used'] ?? 0);
      $protect_used = ($protect_limit > 0) ? $magic_used : 0;
      $reset_at = (int)($구간['reset_at'] ?? 0);
      $cast_reset_sec = ($reset_at > $지금) ? ($reset_at - $지금) : 0;
    } else {
      $mw = $행['magic_window'] ?? null;
      if ($mw !== null && $mw !== '') {
        $cast_reset_sec = max(0, strtotime($mw) + ($쿨 * 3600) - $지금);
        if ($cast_reset_sec <= 0) {
          $magic_used = 0;
          $protect_used = 0;
        }
      }
    }
    $protect_reset_sec = ($protect_limit > 0) ? $cast_reset_sec : 0;

    return [
      'cast_used' => $magic_used,
      'cast_limit' => $cast_limit,
      'cast_left' => max(0, $cast_limit - $magic_used),
      'cast_reset_sec' => $cast_reset_sec,
      'protect_used' => $protect_used,
      'protect_limit' => $protect_limit,
      'protect_left' => max(0, $protect_limit - $protect_used),
      'protect_reset_sec' => $protect_reset_sec,
      'shared_pool' => ($protect_limit > 0),
      'durability' => $durability,
      'point' => (int)($행['point'] ?? 0),
    ];
  }
}

if (!function_exists('enchant_web_cast_item_include')) {
  function enchant_web_cast_item_include($file) {
    $GLOBALS['ENCHANT_WEB_CAST'] = true;
    try {
      include $file;
      return ['ok' => false, 'data' => '❌ 시전 처리가 완료되지 않았어요.'];
    } catch (Exception $e) {
      $msg = $e->getMessage();
      if (strpos($msg, 'ENCHANT_WEB_CAST:') !== 0) {
        throw $e;
      }
      $text = substr($msg, strlen('ENCHANT_WEB_CAST:'));
      $ok = (strpos($text, '❌') !== 0);
      return ['ok' => $ok, 'data' => $text];
    } finally {
      unset($GLOBALS['ENCHANT_WEB_CAST']);
    }
  }
}

if (!function_exists('enchant_web_cast_execute')) {
  function enchant_web_cast_execute($닉, array $회원, $cast_kind = '') {
    $닉 = trim((string)$닉);
    $esc = addslashes($닉);
    $정보 = db_select("SELECT * FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    if (empty($정보['name'])) {
      return ['ok' => false, 'data' => '❌ 회원을 찾을 수 없어요.'];
    }

    global $두자리닉넴, $내무기, $내강화, $스타일, $단위, $쿨타임;
    global $대상닉, $시전횟수, $지목횟수지정, $오늘타수;

    $두자리닉넴 = $닉;
    $스타일 = trim((string)($정보['style'] ?? ''));
    $내무기 = trim((string)($정보['item'] ?? ''));
    $내강화 = (int)($정보['enhance'] ?? 0);
    $내타이틀 = trim((string)($정보['title'] ?? ''));
    $내신용 = (int)($정보['credit'] ?? 0);
    $내게임냥 = (int)($정보['point'] ?? 0);

    if ($내무기 === '') {
      return ['ok' => false, 'data' => '❌ 보유 무기가 없어요.'];
    }
    if ($내강화 < 1) {
      return ['ok' => false, 'data' => '❌ +1강 이상부터 시전이 가능해요.'];
    }

    $kind = strtolower(trim((string)$cast_kind));
    $isProtect = ($kind === 'protect' || $kind === '보호');
    if (!$isProtect && function_exists('무기_시전전_자동장착')) {
      무기_시전전_자동장착($닉, $정보);
    } elseif ($isProtect && function_exists('채굴_무기장착_차단문구')) {
      $채굴차단 = 채굴_무기장착_차단문구($닉);
      if ($채굴차단 !== '') {
        return ['ok' => false, 'data' => $채굴차단];
      }
    }
    if (function_exists('게임제한_차단문구')) {
      $게임제한 = 게임제한_차단문구($닉);
      if ($게임제한 !== null) {
        return ['ok' => false, 'data' => $게임제한];
      }
    }

    if (function_exists('무기_타입_스키마보장')) {
      무기_타입_스키마보장();
    }
    $시전무기 = function_exists('무기_시전가능인가') ? 무기_시전가능인가($정보) : false;
    $신불자 = ($내타이틀 === '🆘신불자' || (bool)preg_match('/신불자/u', $내타이틀) || ($내신용 === 1 && $내게임냥 < 0));
    if ($시전무기 && $신불자) {
      return ['ok' => false, 'data' => '❌ 신불자는 단소·활·마법 시전이 불가해요.'];
    }

    $오늘타수 = db_select("SELECT " . 버프타_SQL_select_expr('msg', 'tasu') . " AS cnt FROM tb_msg WHERE nickname = '{$esc}' AND DATE(regdate) = CURDATE()");
    if (!(function_exists('무기_마법인가') ? 무기_마법인가($정보) : ($내무기 === '🪄마법' || $내무기 === '🪄 마법'))) {
      $타수제한 = 300;
      $현재타수 = (int)($오늘타수['cnt'] ?? 0);
      if ($현재타수 < $타수제한) {
        return ['ok' => false, 'data' => "버프 타수 {$타수제한}타 이상만 시전(공격) 가능해요.
{$닉}의 오늘 버프 타수 : {$현재타수}타"];
      }
    }

    $대상닉 = '';
    $시전횟수 = 1;
    $지목횟수지정 = false;
    $요청보호횟수 = 1;
    $base = dirname(__FILE__);

    if (function_exists('무기_단소인가') ? 무기_단소인가($정보) : false) {
      $버프타 = (int)($오늘타수['cnt'] ?? 0);
      if ($버프타 <= 100) {
        return ['ok' => false, 'data' => "❌ 단소 시전 불가
오늘 버프 타수 {$버프타}타 · 시전 후 100타 미만이 되는 시전은 할 수 없어요.
(시전 1회당 버프 타수 -1)"];
      }
      $결과 = enchant_web_cast_item_include($base . '/danso.php');
    } elseif (function_exists('무기_활인가') ? 무기_활인가($정보) : ($내무기 === '🏹활' || $내무기 === '🏹 활')) {
      $결과 = enchant_web_cast_item_include($base . '/bow.php');
    } elseif (function_exists('무기_마법인가') ? 무기_마법인가($정보) : ($내무기 === '🪄마법' || $내무기 === '🪄 마법')) {
      $kind = strtolower(trim((string)$cast_kind));
      if ($kind === 'protect' || $kind === '보호') {
        $결과 = enchant_web_cast_item_include($base . '/protect.php');
        if (!empty($결과['ok'])) {
          $결과['cast_kind'] = 'protect';
        }
      } elseif ($kind === '' || $kind === 'cast' || $kind === 'buff' || $kind === '시전') {
        $결과 = enchant_web_cast_item_include($base . '/magic.php');
        if (!empty($결과['ok'])) {
          $결과['cast_kind'] = 'buff';
        }
      } else {
        return ['ok' => false, 'data' => '❌ 시전 종류를 확인해주세요. (시전/보호)'];
      }
    } else {
      return ['ok' => false, 'data' => '❌ 단소·활·마법만 시전 가능해요.'];
    }

    if (empty($결과['ok'])) {
      return $결과;
    }

    $상태 = enchant_web_cast_status($닉, $정보);
    $결과['type'] = 'cast';
    $결과['cast_used'] = $상태['cast_used'];
    $결과['cast_limit'] = $상태['cast_limit'];
    $결과['cast_left'] = $상태['cast_left'];
    $결과['protect_used'] = $상태['protect_used'];
    $결과['protect_limit'] = $상태['protect_limit'];
    $결과['protect_left'] = $상태['protect_left'];
    $결과['durability'] = $상태['durability'];
    $결과['point'] = $상태['point'];
    if (function_exists('냥축약표시')) {
      $결과['point_fmt'] = 냥축약표시($상태['point'], '냥');
    }
    return $결과;
  }
}
