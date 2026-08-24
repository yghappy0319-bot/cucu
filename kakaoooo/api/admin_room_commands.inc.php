<?php
/**
 * 관리방(info3) 관리 명령 공통 모듈.
 *
 * - info3: 항상 호출 (room=admin)
 * - info1: $ADMIN_ROOM_CMDS_IN_MAIN === true 일 때만 호출 (room=main, 관리자만)
 *
 * 본방 허용을 끄려면 config.php 의 $ADMIN_ROOM_CMDS_IN_MAIN = false.
 * 모듈 자체를 info3에서만 include 하면 완전 분리도 가능.
 *
 * 이미 본방·관리방에 각각 있는 .겜냥(구.이체) / .본냥(구.입금) 등은 여기 넣지 않음.
 */

if (!function_exists('관리방명령_처리')) {
  /**
   * @param string $status
   * @param string $두자리닉넴
   * @param string $nick
   * @param array  $정보
   * @param array  $관리자
   * @param array  $opts room: 'admin'|'main'
   * @return bool 매칭·처리 시 true(보통 exit). 미매칭 false.
   */
  function 관리방명령_처리($status, $두자리닉넴, $nick = '', $정보 = [], $관리자 = [], array $opts = []) {
    if (function_exists('치즈오리_일방연장_1회적용')) {
      치즈오리_일방연장_1회적용();
    }
    $room = (string)($opts['room'] ?? 'admin'); // admin | main
    $isMain = ($room === 'main');
    $statusTrim = trim((string)$status);
    if ($statusTrim === '') {
      return false;
    }

    // ── 민호 전용 임시/특수 ──────────────────────────────
    if (preg_match('/^\.한도외초기화(?:\s+(.+))?\s*$/u', $statusTrim, $한도외매치)) {
      if ($두자리닉넴 !== '민호') {
        echo 전송('❌ `.한도외초기화`는 [ 민호 ] 님만 사용할 수 있어요.');
        exit;
      }
      $대상목록 = [];
      $인자 = trim((string)($한도외매치[1] ?? ''));
      if ($인자 === '') {
        $대상목록 = ['대성', '미미', '다오'];
      } else {
        foreach (preg_split('/[\s,]+/u', $인자, -1, PREG_SPLIT_NO_EMPTY) as $닉토큰) {
          $닉토큰 = trim((string)$닉토큰);
          if ($닉토큰 !== '') {
            $대상목록[] = $닉토큰;
          }
        }
      }
      $대상목록 = array_values(array_unique($대상목록));
      if ($대상목록 === []) {
        echo 전송("❌ 사용법: `.한도외초기화` 또는 `.한도외초기화 닉1 닉2`\n(닉 생략 시 대성·미미·다오)");
        exit;
      }
      $완료 = [];
      $없음 = [];
      $보호한도외컬럼 = @db_select("SHOW COLUMNS FROM tb_member LIKE 'protect_extra_uses'");
      $보호한도외있음 = !empty($보호한도외컬럼);
      foreach ($대상목록 as $대상닉) {
        $esc = addslashes($대상닉);
        $행 = db_select("SELECT name, IFNULL(extra_uses, 0) AS extra_uses FROM tb_member WHERE name = '{$esc}' LIMIT 1");
        if (empty($행['name'])) {
          $없음[] = $대상닉;
          continue;
        }
        $이전 = (int)($행['extra_uses'] ?? 0);
        @db_query("
          UPDATE tb_member
          SET extra_uses = 0,
              extra_reset_date = CURDATE()
          WHERE name = '{$esc}'
          LIMIT 1
        ");
        if ($보호한도외있음) {
          @db_query("
            UPDATE tb_member
            SET protect_extra_uses = 0,
                protect_extra_reset_date = CURDATE()
            WHERE name = '{$esc}'
            LIMIT 1
          ");
        }
        $완료[] = "{$대상닉}(이전기록 {$이전})";
      }
      $msg = "✅ 한도외 초기화 완료\n" . implode(' · ', $완료);
      if ($없음 !== []) {
        $msg .= "\n❌ 없음: " . implode(' · ', $없음);
      }
      echo 전송($msg);
      exit;
    }

    if ($statusTrim === '.강화보정복구') {
      if ($두자리닉넴 !== '민호') {
        echo 전송('❌ `.강화보정복구`는 [ 민호 ] 님만 사용할 수 있어요.');
        exit;
      }
      $복구대상 = [];
      $복구rs = @db_query("
        SELECT name
        FROM tb_member
        WHERE name IN ('가니', '로이')
          AND CAST(IFNULL(enhance, 0) AS UNSIGNED) = 30
      ");
      if ($복구rs) {
        while ($복구행 = db_fetch($복구rs)) {
          $복구대상[] = trim((string)($복구행['name'] ?? ''));
        }
      }
      @db_query("
        UPDATE tb_member
        SET enhance = 20,
            durability = LEAST(IFNULL(durability, 2000), 2000)
        WHERE name IN ('가니', '로이')
          AND CAST(IFNULL(enhance, 0) AS UNSIGNED) = 30
      ");
      @db_query("
        DELETE FROM tb_lotto_info
        WHERE item = '#boost:20to30'
           OR msg LIKE '🎉 +20강 일회 보정%'
      ");
      $복구닉 = array_values(array_filter($복구대상));
      $복구문구 = $복구닉 ? implode(' · ', $복구닉) : '복구 대상 없음';
      echo 전송("✅ +20→+30 보정 복구 완료\n{$복구문구}\n강화 +20 및 최대 내구도 2000으로 복구했습니다.");
      exit;
    }

    if ($statusTrim === '.우리방초기화') {
      if ($두자리닉넴 !== '민호') {
        echo 전송('❌ `.우리방초기화`는 [ 민호 ] 님만 사용할 수 있어요.');
        exit;
      }
      require_once __DIR__ . '/room_reset.inc.php';
      $결과 = 우리방초기화_실행(['출처' => '수동', '월마커갱신' => true]);
      echo 전송((string)($결과['msg'] ?? '✅ 우리방 초기화 완료'));
      exit;
    }

    // ── 보스 관리 ──────────────────────────────────────
    if ($statusTrim === '.보스출현') {
      관리자_명령_차단($두자리닉넴, $nick ?? '');
      require_once __DIR__ . '/game/boss_raid.inc.php';
      $결과 = boss_raid_강제소환($두자리닉넴);
      echo 전송((string)($결과['data'] ?? '🐉 보스 출현!'));
      exit;
    }
    if ($statusTrim === '.보스초기화') {
      관리자_명령_차단($두자리닉넴, $nick ?? '');
      require_once __DIR__ . '/game/boss_raid.inc.php';
      $결과 = boss_raid_초기화($두자리닉넴);
      echo 전송((string)($결과['data'] ?? '🔄 보스 초기화!'));
      exit;
    }
    if (preg_match('/^\.보스처치풀초기화\s*$/u', $statusTrim)) {
      관리자_명령_차단($두자리닉넴, $nick ?? '');
      require_once __DIR__ . '/game/boss_raid.inc.php';
      $결과 = boss_raid_처치풀_초기화($두자리닉넴);
      echo 전송((string)($결과['data'] ?? '💰 보스처치풀 초기화!'));
      exit;
    }
    if ($statusTrim === '.보스변경') {
      관리자_명령_차단($두자리닉넴, $nick ?? '');
      require_once __DIR__ . '/game/boss_raid.inc.php';
      $결과 = boss_raid_종류_변경();
      echo 전송((string)($결과['data'] ?? '🎲 보스 종류 변경!'));
      exit;
    }
    if ($statusTrim === '.보스딜') {
      관리자_명령_차단($두자리닉넴, $nick ?? '');
      require_once __DIR__ . '/game/boss_raid.inc.php';
      $t = boss_raid_무기유저_전부크리총합();
      $snap = boss_raid_HP고정범위_보장();
      $partPct = (int)round((float)BOSS_RAID_PARTICIPATION_RATE * 100);
      $msg = "🗡️ 무기 전체 딜 추정\n";
      $msg .= "무기 보유 " . (int)$t['weapon_n'] . "명 · 공격가능 " . (int)$t['attacker_n'] . "명\n\n";
      $msg .= "① 전원 1타(평균)\n";
      $msg .= "· 기본 " . number_format((int)($t['one_hit_raw'] ?? 0)) . "\n";
      $msg .= "· 대박·크리 기대포함 " . number_format((int)($t['one_hit_expected'] ?? 0)) . "\n\n";
      $msg .= "② 전원 풀커밋(횟수×1타기대)\n";
      $msg .= "· " . number_format((int)$t['sum']) . "\n\n";
      $msg .= "③ 현재 HP 스냅샷\n";
      $msg .= "· 중앙 " . number_format((int)$snap['center']) . " (기대딜×{$partPct}%)\n";
      $msg .= "· 범위 " . number_format((int)$snap['min']) . " ~ " . number_format((int)$snap['max']) . "\n";
      $msg .= "· 스냅샷 기대딜 " . number_format((int)$snap['sum']) . " / 무기 " . (int)$snap['weapon_n'] . "명\n";
      $msg .= "※ 1타 기본 = 강화×10+50 (범위 강화×10~+100)\n";
      $msg .= "※ 채굴 공격·활+10%·밤크리(10%)는 HP산정 미포함";
      echo 전송($msg);
      exit;
    }
    if ($statusTrim === '.보스HP스냅샷') {
      관리자_명령_차단($두자리닉넴, $nick ?? '');
      require_once __DIR__ . '/game/boss_raid.inc.php';
      $r = boss_raid_HP고정범위_재계산();
      $t = boss_raid_무기유저_전부크리총합();
      $partPct = (int)round((float)BOSS_RAID_PARTICIPATION_RATE * 100);
      $msg = "🐉 보스 HP 스냅샷 고정\n";
      $msg .= "무기 보유 " . (int)$r['weapon_n'] . "명 · 공격가능 " . (int)$r['attacker_n'] . "명\n";
      $msg .= "1타 합(평균) " . number_format((int)($t['one_hit_raw'] ?? 0)) . "\n";
      $msg .= "1타 합(대박·크리기대) " . number_format((int)($t['one_hit_expected'] ?? 0)) . "\n";
      $msg .= "풀커밋 기대딜 " . number_format((int)$r['sum']) . "\n";
      $msg .= "×참여율 {$partPct}% 중앙 " . number_format((int)$r['center']) . "\n";
      $msg .= "고정 범위 " . number_format((int)$r['min']) . " ~ " . number_format((int)$r['max']) . " (±15%)\n";
      $msg .= "※ 이후 보스 종류 생성·`.보스변경` 시 이 범위에서 HP 추첨";
      echo 전송($msg);
      exit;
    }

    // ── 시세/홀짝 ──────────────────────────────────────
    if ($statusTrim === '.스냅샷') {
      관리자_명령_차단($두자리닉넴, $nick ?? '');
      if (!function_exists('시세기준_스냅샷_갱신')) {
        echo 전송('❌ 시세 스냅샷 기능을 불러오지 못했어요.');
        exit;
      }
      $r = 시세기준_스냅샷_갱신(true, true);
      $np = (float)($r['본방냥'] ?? 0);
      $pt = (string)($r['게임냥'] ?? '0');
      $at = (string)($r['갱신시각'] ?? date('Y-m-d H:i:s'));
      $np표시 = function_exists('newpoint표시') ? newpoint표시($np) : (number_format($np, 4) . '냥');
      $pt표시 = function_exists('게임냥_안전표시')
        ? 게임냥_안전표시($pt, '냥')
        : (function_exists('랭킹_게임냥표시') ? 랭킹_게임냥표시($pt, '냥') : (number_format((float)$pt) . '냥'));
      $msg = "📸 시세 스냅샷 저장 완료\n";
      $msg .= "· 본방냥(시세기준_본방냥): {$np표시}\n";
      $msg .= "· 게임냥(시세기준_게임냥): {$pt표시}\n";
      $msg .= "· 갱신시각: {$at}\n";
      if (function_exists('시세기준_스냅샷_자동갱신_중지인가') && 시세기준_스냅샷_자동갱신_중지인가()) {
        $msg .= "※ 자동 갱신 중지 중 · 수동 `.스냅샷` 만 반영\n";
      }
      $msg .= "※ status=0 회원 실시간 합계를 config에 기록했어요";
      echo 전송($msg);
      exit;
    }

    if (preg_match('/^\.(깍기|깎기)변경$/u', $statusTrim)) {
      관리자_명령_차단($두자리닉넴, $nick ?? '');
      require_once __DIR__ . '/game/odd_even_odds.php';
      if (!function_exists('홀짝_깎기_전체재배정')) {
        echo 전송('❌ 깎기 재배정 기능을 불러오지 못했어요.');
        exit;
      }
      $r = 홀짝_깎기_전체재배정();
      if (empty($r['ok'])) {
        echo 전송('❌ ' . (string)($r['msg'] ?? '깎기 재배정에 실패했어요.'));
        exit;
      }
      $cnt = (int)($r['count'] ?? 0);
      $sp = (int)($r['special'] ?? 0);
      $inv = (int)($r['inverted'] ?? 0);
      $rnd = (int)($r['random'] ?? 0);
      $min = (int)($r['min'] ?? 3);
      $max = (int)($r['max'] ?? 8);
      $today = (string)($r['slot'] ?? $r['today'] ?? date('Y-m-d H:i:s'));
      $특별 = defined('홀짝_깎기_특별퍼센트') ? (int)홀짝_깎기_특별퍼센트 : 1;
      $특별닉 = defined('홀짝_깎기_특별닉') ? (string)홀짝_깎기_특별닉 : '새아';
      $hours = function_exists('홀짝_깎기_갱신주기초') ? max(1, (int)round(홀짝_깎기_갱신주기초() / 3600)) : 6;
      $msg = "🎲 홀짝 깎기 전체 재배정\n";
      $msg .= "· 대상: 전체 회원 {$cnt}명 (출퇴근 무관)\n";
      $msg .= "· 일반: {$min}~{$max}% · 높음↔낮음 반전 {$inv}명";
      if ($rnd > 0) {
        $msg .= " · 신규랜덤 {$rnd}명";
      }
      $msg .= "\n";
      $msg .= "· 특별({$특별닉}): 고정 {$특별}% · {$sp}명\n";
      $msg .= "· 구간시작: {$today}\n";
      $msg .= "※ 수동 반전 · 이후 {$hours}시간마다(0·6·12·18시)는 자동 랜덤 갱신";
      echo 전송($msg);
      exit;
    }

    // ── 채굴/광물 강제 ─────────────────────────────────
    if (preg_match('/^\.채굴\s+(\S+)\s+(.+)$/u', $statusTrim, $채굴장비매치)) {
      관리자_명령_차단($두자리닉넴, $nick ?? '');
      require_once __DIR__ . '/game/mining_tool.inc.php';
      $대상닉 = trim((string)$채굴장비매치[1]);
      $장비명 = trim((string)$채굴장비매치[2]);
      if (function_exists('getTwoCharNick')) {
        $파싱닉 = getTwoCharNick($대상닉);
        if ($파싱닉 !== '') {
          $대상닉 = $파싱닉;
        }
      }
      $대상_esc = addslashes($대상닉);
      $회원확인 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
      if (empty($회원확인['idx'])) {
        echo 전송("❌ [ {$대상닉} ] 회원을 찾을 수 없습니다.\n예) .채굴 다오 중장비");
        exit;
      }
      $결과 = mining_tool_admin_set_for_nick($대상닉, $장비명);
      if (!empty($결과['ok']) && function_exists('지급로그')) {
        지급로그('채굴장비변경', $두자리닉넴, $대상닉, 0, (int)($결과['level'] ?? 0));
      }
      echo 전송((string)($결과['data'] ?? '❌ 처리 실패'));
      exit;
    }

    if (preg_match('/^\.광물\s+(\S+)\s+(\S+)(?:\s+(\d+))?\s*$/u', $statusTrim, $광물매치)) {
      관리자_명령_차단($두자리닉넴, $nick ?? '');
      require_once __DIR__ . '/game/mining_ore.inc.php';
      $대상닉 = trim((string)$광물매치[1]);
      $광물명 = trim((string)$광물매치[2]);
      $개수 = isset($광물매치[3]) ? (int)$광물매치[3] : 1;
      if (function_exists('getTwoCharNick')) {
        $파싱닉 = getTwoCharNick($대상닉);
        if ($파싱닉 !== '') {
          $대상닉 = $파싱닉;
        }
      }
      $대상_esc = addslashes($대상닉);
      $회원확인 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
      if (empty($회원확인['idx'])) {
        echo 전송("❌ [ {$대상닉} ] 회원을 찾을 수 없습니다.\n예) .광물 민호 철");
        exit;
      }
      $대상실명 = (string)($회원확인['name'] ?? $대상닉);
      $결과 = mining_ore_admin_spawn_for_nick($대상실명, $광물명, $개수);
      if (!empty($결과['ok']) && function_exists('지급로그')) {
        지급로그('광물강제스폰', $두자리닉넴, $대상실명, 0, max(1, $개수));
      }
      echo 전송((string)($결과['data'] ?? '❌ 처리 실패'));
      exit;
    }

    // ── 공커연금 관리자 조정 (.공커연금 닉 금액) — 수령(.공커연금)과 구분 ──
    if (preg_match('/^\.공커연금\s+(\S+)\s+(-?[0-9,]+)\s*$/u', $statusTrim, $공커연금매치)) {
      관리자_명령_차단($두자리닉넴, $nick ?? '');
      $대상닉 = trim((string)$공커연금매치[1]);
      if (function_exists('getTwoCharNick')) {
        $파싱닉 = getTwoCharNick($대상닉);
        if ($파싱닉 !== '') {
          $대상닉 = $파싱닉;
        }
      }
      $변동액 = (int)str_replace(',', '', (string)$공커연금매치[2]);
      if ($대상닉 === '' || $변동액 === 0) {
        echo 전송("❌ 사용법: .공커연금 닉네임 금액\n예) .공커연금 대성 7500\n예) .공커연금 대성 -7500");
        exit;
      }
      $대상닉_esc = addslashes($대상닉);
      $받는친구 = db_select("SELECT idx, name, IFNULL(gongkeo_pension, 0) AS gongkeo_pension FROM tb_member WHERE name = '{$대상닉_esc}' LIMIT 1");
      if (empty($받는친구['idx'])) {
        echo 전송("❌ [ {$대상닉} ] 회원을 찾을 수 없습니다.");
        exit;
      }
      if (function_exists('공커연금_컬럼_보장')) {
        공커연금_컬럼_보장();
      }
      $이전 = (int)($받는친구['gongkeo_pension'] ?? 0);
      $잔액 = max(0, $이전 + $변동액);
      db_query("UPDATE tb_member SET gongkeo_pension = {$잔액} WHERE name = '{$대상닉_esc}' LIMIT 1");
      $실제변동 = $잔액 - $이전;
      $차감여부 = ($변동액 < 0);
      if (function_exists('지급로그')) {
        지급로그($차감여부 ? '공커연금차감' : '공커연금추가', $두자리닉넴, $받는친구['name'], 0, abs($실제변동));
      }
      $부호표시 = ($실제변동 >= 0 ? '+' : '-') . number_format(abs($실제변동));
      $동작라벨 = $차감여부 ? '차감' : '추가';
      echo 전송("✅ 공커연금 {$동작라벨}\n\n{$받는친구['name']} {$부호표시}냥\n수령대기 " . number_format($잔액) . "냥");
      exit;
    }

    // ── .지목 / .강일 / .지목강일 / 취소·종료·연장 (아이템 명령, 본방·관리방 공통) ──
    // ※ `.강일연금`·`.지목연금` 과 접두 충돌 없게 매칭 (연금 처리는 아래에서)
    if (
      preg_match('/^\.지목강일(?:\s|$)/u', $statusTrim)
      || preg_match('/^\.지목(?:\s|$)/u', $statusTrim)
      || preg_match('/^\.강일(?:취소|종료|연장)(?:\s|$)/u', $statusTrim)
      || preg_match('/^\.강일(?:\s|$)/u', $statusTrim)
    ) {
      if (function_exists('지목_명령_처리')) {
        지목_명령_처리($status, $두자리닉넴);
      }
      if (function_exists('강일취소_명령_처리')) {
        강일취소_명령_처리($status, $두자리닉넴);
      }
      if (function_exists('강일종료_명령_처리')) {
        강일종료_명령_처리($status, $두자리닉넴);
      }
      if (function_exists('강일연장_명령_처리')) {
        강일연장_명령_처리($status, $두자리닉넴);
      }
      // `.강일` 단독 → 강제일방 사용중/빈방 목록
      if (preg_match('/^\.강일\s*$/u', $statusTrim)) {
        if (!function_exists('강일방_사용중목록_문구')) {
          $gr = __DIR__ . '/gangil_room.inc.php';
          if (is_file($gr)) {
            require_once $gr;
          }
        }
        if (function_exists('강일방_사용중목록_문구')) {
          echo 전송(강일방_사용중목록_문구());
          exit;
        }
      }
      if (function_exists('강일_명령_처리')) {
        강일_명령_처리($status, $두자리닉넴);
      }
    }

    // ── .연장 일방/강일/지목 (본방 관리자 · 연구실) ──
    if (preg_match('/^\.연장(?:[\s\p{Zs}]+|$)/u', $statusTrim)) {
      if ($isMain) {
        관리자_명령_차단($두자리닉넴, $nick ?? '');
      }
      if (function_exists('연장_명령_처리')) {
        연장_명령_처리($status, $두자리닉넴);
      }
      echo 전송("❌ 사용법: .연장 일방 치즈🐸오리\n예) .연장 일방 영수❤️영희");
      exit;
    }

    // ── .수호 닉 [개수] — 자숙/제한 단축 (아이템 소모 · 본방·연구실) ──
    if (preg_match('/^\.수호(?:\s|$)/u', $statusTrim)) {
      if (function_exists('수호_연구실_명령_처리')) {
        수호_연구실_명령_처리($status, $두자리닉넴);
      }
      // 허용 방이 아니면 return 만 하고 아래로 진행 → mutual 등에서 안내
    }

    // ── .닉변 현재닉 변경닉 — 닉변 아이템 소모 (본방·연구실) ──
    if (preg_match('/^\.닉변(?:\s|$)/u', $statusTrim)) {
      if (function_exists('닉변_명령_처리')) {
        닉변_명령_처리($status);
      }
    }

    // ── .색변환 닉 번호 — 색변 아이템 1개 차감 후 색번호 변경 (관리자 · 본방·관리방) ──
    if (preg_match('/^\.색변환(?:\s|$)/u', $statusTrim)) {
      if (function_exists('색변환_명령_처리')) {
        색변환_명령_처리($status, $두자리닉넴, $관리자);
      }
    }

    // ── .프변 [개수] — 프로필변경 (1개=3일 · 본방·관리방 공통) ──
    if (preg_match('/^\.프변(?:\s|$)/u', $statusTrim)) {
      if (!preg_match('/^\.프변(?:\s+(\d+))?\s*$/u', $statusTrim, $프변m)) {
        echo 전송("❌ 사용법: .프변 · .프변 1 · .프변 5");
        exit;
      }
      $개수 = isset($프변m[1]) ? (int)$프변m[1] : 1;
      if ($개수 < 1) {
        echo 전송("❌ 사용 개수는 1 이상으로 입력해주세요.\n예) .프변 1 · .프변 5");
        exit;
      }
      if (function_exists('프변_명령_처리')) {
        echo 전송(프변_명령_처리($두자리닉넴, $개수));
        exit;
      }
    }

    // ── .지호 [개수] — 지호 버프 (1개=1시간 · 본방·관리방 공통) ──
    if (preg_match('/^\.지호(?:\s|$)/u', $statusTrim)) {
      if (function_exists('지호_명령_처리')) {
        지호_명령_처리($status, $두자리닉넴);
      }
    }

    // ── .지갑변경 — 본인 지갑 CODE 재발급 (본방·관리방 공통) ──
    if (preg_match('/^\.지갑변경\s*$/u', $statusTrim)) {
      if ($두자리닉넴 === '') {
        echo 전송('❌ 회원 정보를 찾을 수 없어요.');
        exit;
      }
      if (!function_exists('회원_접속코드_재발급')) {
        echo 전송('❌ 지갑 변경 기능을 불러오지 못했어요.');
        exit;
      }
      $코드 = 회원_접속코드_재발급($두자리닉넴);
      if ($코드 === '') {
        echo 전송('❌ 지갑 주소를 변경하지 못했어요.');
        exit;
      }
      $url = "http://49.247.160.164/page/wallet.php?code={$코드}";
      echo 전송("🔑 지갑 주소가 변경되었어요.\n\n{$두자리닉넴} 새 지갑 링크\n{$url}\n\n이전 링크는 사용할 수 없어요.\n새 링크를 따로 저장해 두세요.");
      exit;
    }

    // ── .지갑 / .지갑배정 / .가방배정 — 지갑 링크 (본방·관리방 공통, 누구나) ──
    if (preg_match('/^\.(?:가방배정|지갑배정|지갑)(?:\s+(\S+))?\s*$/u', $statusTrim, $지갑매치)) {
      $지정닉 = !empty($지갑매치[1]) ? getTwoCharNick(trim($지갑매치[1])) : $두자리닉넴;
      if ($지정닉 === '') {
        echo 전송("❌ 닉네임을 확인해주세요.\n예) .지갑 / .지갑 스리");
        exit;
      }
      $지정닉_esc = addslashes($지정닉);
      $멤버 = db_select("SELECT idx, name, code FROM tb_member WHERE name = '{$지정닉_esc}' LIMIT 1");
      if (empty($멤버['idx'])) {
        echo 전송("'{$지정닉}' 회원이 없습니다.");
        exit;
      }
      if (!function_exists('회원_접속코드_발급')) {
        echo 전송('❌ 지갑 링크 기능을 불러오지 못했어요.');
        exit;
      }
      $코드 = 회원_접속코드_발급($지정닉);
      $url = "http://49.247.160.164/page/wallet.php?code={$코드}";
      $안내 = $isMain
        ? "{$지정닉} 지갑 링크\n{$url}\n\n지갑 링크는 따로 저장해 두세요."
        : "{$지정닉} 가방 링크\n{$url}\n\n가방 링크 따로 저장할것\n추가 용무 없을시 연구실은 나갈것";
      echo 전송($안내);
      exit;
    }

    // ── .추가 일방/공커/제한 닉 (자숙 등록·토글) ──
    // 본방: 관리자만 / 관리방: 기존과 동일
    if (strpos($statusTrim, '.추가') !== false) {
      if ($isMain) {
        관리자_명령_차단($두자리닉넴, $nick ?? '');
      }
      관리방명령_추가자숙($status);
      // 매칭되면 내부에서 exit
    }

    // ── 강일/지목 연금: 관리자 조정 (+ 관리방에서만 목록) ──
    if (strpos($statusTrim, '.강일연금') === 0 || strpos($statusTrim, '.지목연금') === 0) {
      $종류 = (strpos($statusTrim, '.지목연금') === 0) ? '지목' : '강일';
      관리방명령_횟수연금($종류, $statusTrim, $두자리닉넴, $nick, $isMain);
      // 목록만 처리하고 return 한 경우 / 미매칭은 함수 내부에서 exit 또는 return
    }

    // ── 꼬맨완료 ───────────────────────────────────────
    // 본방·관리방 공통: 본인 완료 가능 / `.꼬맨완료 닉` 대리는 관리자만
    if (preg_match('/^\.꼬맨완료(?:\s+(\S+))?\s*$/u', $statusTrim, $꼬맨매치)) {
      if ($두자리닉넴 === '') {
        echo 전송('❌ 회원 정보를 찾을 수 없어요.');
        exit;
      }
      $대상닉 = $두자리닉넴;
      $관리자대리 = false;
      if (!empty($꼬맨매치[1])) {
        if (!in_array($두자리닉넴, (array)$관리자, true)) {
          echo 전송("❌ `.꼬맨완료 닉네임` 은 관리자만 사용할 수 있어요.\n예) .꼬맨완료 가은");
          exit;
        }
        $대상닉 = trim($꼬맨매치[1]);
        if (function_exists('getTwoCharNick')) {
          $파싱닉 = getTwoCharNick($대상닉);
          if ($파싱닉 !== '') {
            $대상닉 = $파싱닉;
          }
        }
        $관리자대리 = true;
      }
      $대상_esc = addslashes($대상닉);
      $대상행 = db_select("SELECT name FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
      if (empty($대상행['name'])) {
        echo 전송("❌ {$대상닉} 회원을 찾을 수 없어요.");
        exit;
      }
      if (!$관리자대리 && trim((string)($정보['name'] ?? '')) === '') {
        echo 전송('❌ 등록된 회원만 참여할 수 있어요.');
        exit;
      }
      if (꼬맨_오늘참여여부($대상닉)) {
        echo 전송("❌ {$대상닉} 님은 오늘 이미 꼬맨을 완료했어요.\n본방냥 중복 지급은 되지 않아요.");
        exit;
      }
      if (꼬맨_오늘잔여() <= 0) {
        $한도 = 꼬맨_일일한도();
        echo 전송("❌ 오늘 꼬맨 참여 인원이 마감됐어요. ({$한도}/{$한도}명 완료)\n내일 다시 도전해주세요!");
        exit;
      }
      $지급냥 = function_exists('꼬맨_보상_계산') ? 꼬맨_보상_계산() : (function_exists('newpoint비율계산') ? (int)newpoint비율계산(0.01) : 1000);
      if ($지급냥 < 1) {
        echo 전송('❌ 지급할 본방냥을 계산할 수 없어요.');
        exit;
      }
      if (!꼬맨_완료_기록($대상닉, $지급냥)) {
        if (꼬맨_오늘참여여부($대상닉)) {
          echo 전송("❌ {$대상닉} 님은 오늘 이미 꼬맨을 완료했어요.\n본방냥 중복 지급은 되지 않아요.");
        } else {
          echo 전송('❌ 꼬맨 완료 처리에 실패했어요. 잠시 후 다시 시도해주세요.');
        }
        exit;
      }
      if (꼬맨_오늘완료수() > 꼬맨_일일한도()) {
        꼬맨_완료_취소($대상닉);
        $한도 = 꼬맨_일일한도();
        echo 전송("❌ 오늘 꼬맨 참여 인원이 마감됐어요. ({$한도}/{$한도}명 완료)\n내일 다시 도전해주세요!");
        exit;
      }
      db_query("UPDATE tb_member SET newpoint = newpoint + {$지급냥} WHERE name = '{$대상_esc}'");
      if (function_exists('지급로그')) {
        if ($관리자대리) {
          지급로그('꼬맨완료|관리자', $두자리닉넴, $대상닉, 0, $지급냥);
        } else {
          지급로그('꼬맨완료', $대상닉, '', 0, $지급냥);
        }
      }
      $잔여 = 꼬맨_오늘잔여();
      $완료 = 꼬맨_오늘완료수();
      $한도 = 꼬맨_일일한도();
      $지급표시 = function_exists('newpoint표시') ? newpoint표시($지급냥) : number_format($지급냥) . '냥';
      $결과 = "✅ 꼬맨 완료!\n\n{$대상닉} 님 본방냥 {$지급표시} 지급 (전체 1%)";
      if ($관리자대리) {
        $결과 .= "\n(관리자 {$두자리닉넴} 등록)";
      }
      $결과 .= "\n오늘 남은 참여 가능: {$잔여}명 ({$완료}/{$한도}명 완료)";
      echo 전송($결과);
      exit;
    }

    // ── 신입생성 ───────────────────────────────────────
    if (strpos($statusTrim, '.신입생성') !== false) {
      관리자_명령_차단($두자리닉넴, $nick ?? '');
      if (!preg_match('/^\.신입생성\s+([가-힣]{2})\s+(남|여)\s*$/u', $statusTrim, $신입생성m)) {
        echo 전송("❌ 사용법: .신입생성 닉네임 성별\n예) .신입생성 길동 남");
        exit;
      }
      $닉네임 = $신입생성m[1];
      $성별 = $신입생성m[2];
      $gender = ($성별 === '남') ? 1 : 2;
      $닉_esc = addslashes($닉네임);

      $중복 = db_select("SELECT idx FROM tb_member WHERE name = '{$닉_esc}' AND status != 1 LIMIT 1");
      if (!empty($중복['idx'])) {
        echo 전송("❌ [ {$닉네임} ] 은(는) 이미 등록된 닉이에요.");
        exit;
      }

      global $conn;
      if (function_exists('색확정_컬럼보장')) {
        색확정_컬럼보장();
      }
      $높번 = db_select("SELECT MAX(num) AS nmax FROM tb_member");
      $다음번호 = (int)($높번['nmax'] ?? 0) + 1;
      $신규코드 = function_exists('회원_6자리코드_생성') ? 회원_6자리코드_생성() : substr(str_shuffle('0123456789'), 0, 6);
      $신규코드_esc = isset($conn) && $conn
        ? mysqli_real_escape_string($conn, $신규코드)
        : addslashes($신규코드);

      $result = db_query("INSERT INTO tb_member SET
        status = 0,
        code = '{$신규코드_esc}',
        couple = 2,
        `색확정` = 0,
        num = '{$다음번호}',
        gender = {$gender},
        name = '{$닉_esc}',
        content = '',
        newpoint = 0.0,
        getto = 0,
        max_getto = 0,
        regdate = NOW()");

      if ($result) {
        echo 전송("🌸 신입 [ {$닉네임} ] {$성별} 등록 완료!\n이제 `.색표` 로 색 골라보자!");
        exit;
      }
      echo 전송("❌ 신입 등록에 실패했어요.");
      exit;
    }

    // ── .관리 / .관리주소 (안내) ────────────────────────
    // 본방: 관리자만 · 연구실: 기존과 동일
    if (preg_match('/^\.관리주소\s*$/iu', $statusTrim)) {
      if ($isMain) {
        관리자_명령_차단($두자리닉넴, $nick ?? '');
      }
      echo 전송("아이템 : http://49.247.160.164/page/item.php?a=1\n프로필 : http://49.247.160.164/page/color.php");
      exit;
    }
    if (preg_match('/^\.관리\s*$/u', $statusTrim)) {
      if ($isMain) {
        관리자_명령_차단($두자리닉넴, $nick ?? '');
      }
      echo 전송(관리명령어_안내문구());
      exit;
    }

    // ── .관리자 닉 (토글) / 목록 ────────────────────────
    // 본방: 목록은 info1 기존 핸들러 유지 → 토글만 처리
    // 관리방: 목록+토글
    if (strpos($statusTrim, '.관리자') !== false) {
      if (preg_match('/^\.관리자\s+(\S+)/u', $statusTrim, $match)) {
        // 본방에서는 관리자만 토글 가능 (관리방은 방 특성상 기존과 동일하게 허용하되 관리자 체크 권장)
        관리자_명령_차단($두자리닉넴, $nick ?? '');
        $대상닉 = trim($match[1]);
        $대상_2자 = getTwoCharNick($대상닉);
        if ($대상_2자 === '') {
          echo 전송("❌ 닉네임을 확인해주세요.");
          exit;
        }
        $대상_esc = addslashes($대상_2자);
        $멤버 = db_select("SELECT idx, admin FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
        if (empty($멤버['idx'])) {
          echo 전송("❌ 해당 회원을 찾을 수 없습니다.");
          exit;
        }
        if ((int)$멤버['admin'] === 1) {
          db_query("UPDATE tb_member SET admin = 0 WHERE name = '{$대상_esc}'");
          echo 전송("✅ 관리자 해제완료 ({$대상_2자})");
        } else {
          db_query("UPDATE tb_member SET admin = 1 WHERE name = '{$대상_esc}'");
          echo 전송("✅ 관리자 등록완료 ({$대상_2자})");
        }
        exit;
      }
      if (!$isMain && ($statusTrim === '.관리자' || preg_match('/^\.관리자\s*$/u', $statusTrim))) {
        $admin_result = db_query("SELECT name FROM tb_member WHERE admin = 1 ORDER BY name");
        $목록 = array();
        while ($row = db_fetch($admin_result)) {
          $목록[] = trim($row['name']);
        }
        if (count($목록) > 0) {
          echo 전송("👑 현재 관리자\n" . implode(", ", $목록));
        } else {
          echo 전송("👑 등록된 관리자가 없습니다.");
        }
        exit;
      }
      if (!$isMain) {
        echo 전송("❌ 사용법: .관리자 (목록)\n.관리자 닉네임 (등록)\n예) .관리자 지호");
        exit;
      }
      // 본방: 목록/사용법은 info1 기존 분기에서 처리
      return false;
    }

    return false;
  }
}

if (!function_exists('관리명령어_안내문구')) {
  function 관리명령어_안내문구() {
    return "-관리 명령어
                                                                                                                                                                                                                                                               
. 오늘 (오늘타수)
. 주별 (현재주차타수)

. 궁금 닉네임 ( 냥소진❌ )
. 정보 닉네임 ( 냥소진❌ 연구실에서만 사용‼️)
. 최근대화 닉네임 [개수] (예: .최근대화 미니 50)

. 닉변 (현재닉) (변경할닉)
. 색변 닉네임 색번호
. 프변 (프변 3일 사용/연장)

. 수호 (받을닉) [개수] — 가장 최근 자숙/제한부터 단축 (수호 1개당 1회)

. 조회 닉네임 템명
. 생성 닉네임 템명 개수(템생성, 음수=회수) 예) .생성 가이 색변 -12
. 생성 전체 템명 개수(전원 지급/회수) 예) .생성 전체 1주년기념주화 1
. 사용 닉네임 템명 개수(템사용처리)

. 퇴사 닉네임 (전부 삭제)
‼️보유냥 확인 후 금고처리 후 퇴사처리

. 금고 (퇴사자 보유냥 금액) ➡️ 금고로 귀속
. 금고 ( - 사다리 금액) ➡️ 금고 금액 사용처리
. 지급 닉네임 냥금액 ➡️ 개인 냥 지급
. 지급 전체 냥금액 ➡️ 전체 냥 지급
. 삭감 닉네임 퍼센트 ➡️ 해당 닉 보유 냥의 N% 삭감 (예: .삭감 우주 80)
. 게임냥삭감 0/00/000 ➡️ 전원 게임냥 뒤 N자리 일괄 삭제 (마이너스 포함 · ÷10/100/1000)
. 본방냥삭감 0/00/000 ➡️ 전원 본방냥 뒤 N자리 일괄 삭제 (채굴적립·마켓선매입가 포함 · 마이너스 포함)
. 채굴냥삭감 0/00/000 ➡️ 채굴기 적립만 뒤 N자리 삭제 (본방냥 미변경)
. 선매입삭감 0/00/000 ➡️ 마켓 선매입 본방냥만 뒤 N자리 삭제 (본방냥 미변경)
. 보조금지급 ➡️ 주급·보급 동시 지급 (7일 주기)

. 기록 (강일딜레이시간)
. 신입생성 닉 성별
. 메모 짧은내용 시간

. 평타사다리 (상위명수) (당첨명수)
예) . 평타사다리 10 6
→ 평타 상위 10명 중 4명 랜덤 뽑기

. 강화배정 (닉네임)
. 홀짝배정 (닉네임)
. 가방배정 (닉네임)
. 지갑배정 (닉네임)
. 지갑변경 (본인 CODE 재발급)
. 마켓배정 (닉네임)


-자숙/제한 등록방법
예) . 추가 (일방/공커/제한) (닉네임) 
. 추가 일방 닉네임
. 추가 공커 닉네임
. 추가 보룸제한 닉네임
. 추가 채팅제한 닉네임
. 추가 게임제한 닉네임

-일방 등록방법 (본방·연구실)
예) . 등록 일방 영수(임티)영희

-강일/지목 등록방법
예) . 등록 강일 영수(임티)영희

-일방/강일/지목 연장방법
예) . 연장 일방 영수(임티)영희
예) . 강일연장 진우 (본인 강일 1개 · 진우 강일 12시간 연장)
예) . 강일연장 대성 진우 (대성 강일 1개 · 진우 강일 12시간 연장)

- 공커등록방법
예) . 공커등록 예)영수🖤하니
----------
. 아템조회
. 공질조회
. 주의강일
. 주의일방
. 관리주소
";
  }
}

if (!function_exists('관리방명령_횟수연금')) {
  /**
   * @param '강일'|'지목' $종류
   * @param bool $isMain 본방이면 목록은 건너뛰고 관리자 조정만
   */
  function 관리방명령_횟수연금($종류, $status, $두자리닉넴, $nick, $isMain = false) {
    $라벨 = ($종류 === '지목') ? '지목연금' : '강일연금';
    $명령 = '.' . $라벨;
    $컬럼 = ($종류 === '지목') ? 'jimok_times' : 'gangil_times';
    $단가 = ($종류 === '지목')
      ? (function_exists('지목연금_단가') ? 지목연금_단가() : 500)
      : (function_exists('강일연금_단가') ? 강일연금_단가() : 1000);
    $status = trim((string)$status);

    // 목록 — 관리방에서만 (본방은 info1 기존 핸들러)
    if ($status === $명령) {
      if ($isMain) {
        return;
      }
      if ($종류 === '지목') {
        지목연금_명령_처리($status, $두자리닉넴);
      } else {
        강일연금_명령_처리($status, $두자리닉넴);
      }
      return;
    }

    // 관리자 추가/차감: .강일연금 대성 7000 / .강일연금 대성 -7000
    if (!preg_match('/^\.' . preg_quote($라벨, '/') . '\s+(\S+)\s+(-?[0-9,]+)\s*$/u', $status, $m)) {
      return;
    }
    관리자_명령_차단($두자리닉넴, $nick ?? '');
    $대상닉 = trim((string)$m[1]);
    if (function_exists('getTwoCharNick')) {
      $파싱닉 = getTwoCharNick($대상닉);
      if ($파싱닉 !== '') {
        $대상닉 = $파싱닉;
      }
    }
    $변동액 = (int)str_replace(',', '', (string)$m[2]);
    if ($대상닉 === '' || $변동액 === 0) {
      echo 전송("❌ 사용법: {$명령} 닉네임 금액\n예) {$명령} 대성 7000\n예) {$명령} 대성 -7000\n(1회당 " . number_format($단가) . "냥)");
      exit;
    }
    if ($단가 < 1) {
      echo 전송("❌ {$라벨} 단가 설정 오류");
      exit;
    }
    if (abs($변동액) % $단가 !== 0) {
      echo 전송("❌ {$라벨}은 1회당 " . number_format($단가) . "냥 단위로만 추가/차감할 수 있어요.\n예) {$명령} 대성 " . number_format($단가) . " / {$명령} 대성 -" . number_format($단가 * 2));
      exit;
    }
    $횟수변동 = (int)($변동액 / $단가);

    $대상닉_esc = addslashes($대상닉);
    $받는친구 = db_select("SELECT idx, name, IFNULL({$컬럼}, 0) AS times FROM tb_member WHERE name = '{$대상닉_esc}' LIMIT 1");
    if (empty($받는친구['idx'])) {
      echo 전송("❌ [ {$대상닉} ] 회원을 찾을 수 없습니다.");
      exit;
    }
    if ($종류 === '지목') {
      지목횟수_컬럼_보장();
    } else {
      강일횟수_컬럼_보장();
    }
    $이전횟수 = (int)($받는친구['times'] ?? 0);
    $이후횟수 = max(0, $이전횟수 + $횟수변동);
    db_query("UPDATE tb_member SET {$컬럼} = {$이후횟수} WHERE name = '{$대상닉_esc}' LIMIT 1");
    $실제횟수변동 = $이후횟수 - $이전횟수;
    $실제냥 = $실제횟수변동 * $단가;
    $차감여부 = ($실제냥 < 0);
    if (function_exists('지급로그')) {
      지급로그($차감여부 ? ($라벨 . '차감') : ($라벨 . '추가'), $두자리닉넴, $받는친구['name'], 0, abs($실제냥));
    }
    $부호표시 = ($실제냥 >= 0 ? '+' : '-') . number_format(abs($실제냥));
    $동작라벨 = $차감여부 ? '차감' : '추가';
    $이전연금 = $이전횟수 * $단가;
    $이후연금 = $이후횟수 * $단가;
    echo 전송("✅ {$라벨} {$동작라벨}\n\n{$받는친구['name']} {$부호표시}냥\n({$이전횟수}회 · " . number_format($이전연금) . "냥 → {$이후횟수}회 · " . number_format($이후연금) . "냥)");
    exit;
  }
}

if (!function_exists('관리방명령_추가자숙')) {
  /** .추가 일방/공커/제한 닉 — 자숙 등록·삭제 토글. 매칭 시 전송 후 exit */
  function 관리방명령_추가자숙($status) {
    $msg = "자숙인 등록방법";
    $msg .= "\n\n명령어 .추가 (일방/공커/제한) (닉네임) ";
    $msg .= "\n.추가 일방 길동";
    $msg .= "\n.추가 공커 길동";
    $msg .= "\n.추가 보룸제한 길동";
    $msg .= "\n.추가 채팅제한 길동";
    $msg .= "\n.추가 게임제한 길동";

    if (preg_match('/\.추가\s+(\S+)\s+(.+)/u', $status, $match)) {
      $상태 = $match[1];
      $진행 = trim($match[2]);
      $진행닉 = function_exists('getTwoCharNick') ? getTwoCharNick($진행) : $진행;
      if ($진행닉 === '') {
        $진행닉 = $진행;
      }
      $상태_esc = addslashes($상태);
      $진행_esc = addslashes($진행);

      $data = db_select("select * from tb_self where status = '{$상태_esc}' and nick = '{$진행_esc}' ");
      if (!empty($data['idx'])) {
        $delIdx = (int)$data['idx'];
        if (function_exists('tb_self_건삭제_모금정리')) {
          $result = tb_self_건삭제_모금정리($delIdx);
        } else {
          $result = db_query("delete from tb_self where idx = {$delIdx}");
        }
        // 공커 자숙 삭제 시 — 아직 활성 커플이면 oneroom=2 복구
        if ($상태 === '공커' && $result && function_exists('공커_멤버_oneroom_설정')) {
          if (function_exists('공커_활성_조회') && 공커_활성_조회($진행닉)) {
            공커_멤버_oneroom_설정([$진행닉], 2);
          } else {
            공커_멤버_oneroom_설정([$진행닉], 0);
          }
        }
        echo 전송($상태 . " {$진행} 삭제완료!");
        exit;
      }

      if ($상태 == "일방") {
        $끝나는날 = date("Y-m-d H:i:s", strtotime("+3 days"));
      } else if ($상태 == "공커") {
        $끝나는날 = date("Y-m-d H:i:s", strtotime("+5 days"));
      } else if ($상태 == "보룸제한") {
        $끝나는날 = date("Y-m-d H:i:s", strtotime("+3 hours"));
      } else if ($상태 == "채팅제한") {
        $끝나는날 = date("Y-m-d H:i:s", strtotime("+3 hours"));
      } else if ($상태 == "지또제한" || $상태 == "게임제한") {
        $끝나는날 = date("Y-m-d H:i:s", strtotime("+3 hours"));
      } else {
        echo 전송($msg);
        exit;
      }

      $sql = "insert into tb_self set status = '{$상태_esc}',nick = '{$진행_esc}', enddate = '{$끝나는날}', regdate = now()" . (function_exists('tb_self_일방공커_모금단가_sql') ? tb_self_일방공커_모금단가_sql($상태) : '');
      $result = db_query($sql);
      if ($result) {
        // .추가 공커 닉 → oneroom=0 (대실권 구매 불가)
        if ($상태 === '공커' && function_exists('공커_멤버_oneroom_설정')) {
          공커_멤버_oneroom_설정([$진행닉], 0);
        }
        echo 전송($상태 . "\n{$진행} {$상태} 자숙 등록완료!\n{$끝나는날} 까지");
        exit;
      }
    }

    echo 전송($msg);
    exit;
  }
}
