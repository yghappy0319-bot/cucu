<?php
/**
 * 야바위(주사위) — .야바위방법 · .신청/ㅅㅊ · .마감 · ㄷㄹ
 * 포함 전: $status, $두자리닉넴, $정보, $설정, $주사위참가비, $주사위최대참가비, $오늘
 * 선택: $YABAWI_ROOM_LABEL (본방|홍보방), $신불문구
 */
if (!isset($YABAWI_ROOM_LABEL) || trim((string)$YABAWI_ROOM_LABEL) === '') {
  $YABAWI_ROOM_LABEL = '홍보방';
}
$신불문구 = isset($신불문구) ? (string)$신불문구 : '';
$야바위단위 = isset($단위) ? (string)$단위 : '냥';

if (!function_exists('yabawi_bomb_reset_all')) {
  /** 야바위 폭탄: 참가자·config 초기화 (참가비 환급 없음) */
  function yabawi_bomb_reset_all() {
    db_query('DELETE FROM tb_run_member');
    db_query("UPDATE config SET 야바위강제마감 = '', 야바위게임시작 = NULL");
  }
}

if (!function_exists('yabawi_bomb_enabled')) {
  /** 참가자 10명 이상이면 폭탄 비활성 */
  function yabawi_bomb_enabled(): bool {
    $row = db_select('SELECT COUNT(*) AS cnt FROM tb_run_member');
    return (int)($row['cnt'] ?? 0) < 10;
  }
}

if (strpos($status, '.야바위방법') !== false) {

  $msg  = "🎲 야바위(주사위) 게임 방법 · {$YABAWI_ROOM_LABEL}\n\n";
  $msg .= "1️⃣ 참가 신청\n";
  $msg .= "- 명령어: `.신청 금액`\n";
  $msg .= "- 금액: 숫자 또는 만·억·조·천·백 축약 (예: 1억, 5조, 1조1천1백)\n";
  $msg .= "- 최소 참가금: ".야바위_금액표시($주사위참가비, $야바위단위)."\n";
  $msg .= "- 최대 참가금: ".야바위_금액표시($주사위최대참가비, $야바위단위)."\n";
  $msg .= "- 첫 신청자가 금액을 정하면, 이후 참여자는 그 금액 이상만 신청 가능해.\n";
  $msg .= "- 첫 신청자가 금액을 정한 뒤에는, 다음 신청자는 그냥 `ㅅㅊ`만 쳐도 같은 금액으로 자동 참가돼.\n\n";
  $msg .= "2️⃣ 마감 및 게임 시작\n";
  $msg .= "- 최소 2인 이상 신청해야 마감 가능해.\n";
  $msg .= "- `.마감` 입력하면 참가자 모집이 끝나고 게임이 시작돼.\n\n";
  $msg .= "3️⃣ 주사위 굴리기\n";
  $msg .= "- 게임이 시작되면 참가자들은 `ㄷㄹ` 을 **한 번** 입력하면 주사위 3번이 자동으로 굴려져 (1~10점, x2 보너스 가능).\n\n";
  $msg .= "4️⃣ 승리 조건\n";
  $msg .= "- `ㄷㄹ` 로 3번 굴린 뒤, 총점이 가장 높은 친구가 우승이야.\n";
  $msg .= "- 우승자에게는 참가금 총합(일부는 금고 적립) 기준으로 냥이 지급돼.\n\n";
  $msg .= "5️⃣ 참고\n";
  $msg .= "- 오늘 100타 출석(출석미션) 완료 후에만 `.신청` 가능해.\n";
  $msg .= "- `.신청` 없이 `ㄷㄹ` 을 치면 참가자가 아니라서 진행이 안 돼.\n";
  $msg .= "- 진행 중 게임 상황은 `.점수` 로 확인하면 돼.\n";
  $msg .= "- `ㄷㄹ` 주사위 **1회마다** 1% 💣폭탄(한 번 `ㄷㄹ` = 3회 체크) → 전부 초기화(참가비 소멸)\n";
  $msg .= "- 단, 참가자 **10명 이상**이면 폭탄이 터지지 않아.";
  echo 전송($msg);
  exit;
}

if (strpos($status, 'ㄷㄹ') !== false) {

  게임제한_차단($두자리닉넴);

  $종료 = db_select("select count(*) as cnt from tb_run_member where status = 1");
  if ($종료['cnt'] == 0) {
    echo 전송(".마감 입력해주세요.");
    exit;
  }

  if ($두자리닉넴) {

    $신청여부 = db_select("select idx,sort from tb_run_member where name = '{$두자리닉넴}'");
    if (!$신청여부['idx']) {
      echo 전송($두자리닉넴."는 다음게임에 참가해볼까?");
      exit;
    }

    $횟수 = (int)($신청여부['sort'] ?? 0);
    $rollMsg = '';

    if ($횟수 < 3) {
      $자숙위반 = function_exists('자숙_야바위위반_적용')
        ? 자숙_야바위위반_적용($두자리닉넴, isset($단위) ? $단위 : '냥')
        : ['notice' => ''];
      $자숙위반안내 = (string)($자숙위반['notice'] ?? '');

      $lines = [];
      $추가점수 = 0;
      $폭탄가능 = function_exists('yabawi_bomb_enabled') ? yabawi_bomb_enabled() : true;
      for ($roll = 1; $roll <= 3; $roll++) {
        if ($폭탄가능 && mt_rand(1, 100) <= 1) {
          $폭탄손실 = db_select("SELECT COALESCE(SUM(point), 0) AS total, COUNT(*) AS cnt FROM tb_run_member");
          $날아간금액 = (int)($폭탄손실['total'] ?? 0);
          $폭탄참가인원 = (int)($폭탄손실['cnt'] ?? 0);
          yabawi_bomb_reset_all();
          $금액표시 = 야바위_금액표시($날아간금액, $야바위단위);
          $msg = "💣 1%의 확률로 폭탄이 터졌습니다 ㅋㅋㅋㅋ\n\n";
          $msg .= "💥 폭탄 주인공: {$두자리닉넴}\n";
          $msg .= "({$roll}번째 주사위 굴리다가 터짐)\n\n";
          $msg .= "야바위 전부 초기화!\n";
          $msg .= "💸 참가비 총 {$금액표시} 날아감 ㅋㅋ";
          if ($폭탄참가인원 > 0) {
            $msg .= " ({$폭탄참가인원}명)";
          }
          $msg .= "\n처음부터 `.신청` 다시 해줘~";
          echo 전송($msg);
          exit;
        }

        $rand = mt_rand(1, 10);
        $multiplier = (mt_rand(1, 100) <= 51) ? 2 : 1;
        $point = $rand * $multiplier;
        $추가점수 += $point;
        $bonusMsg = ($multiplier === 2) ? ' 🎉x2!' : '';
        $lines[] = "{$roll}회 {$두자리닉넴} +{$rand}점{$bonusMsg}";
      }

      db_query("update tb_run_member set cnt = cnt + {$추가점수}, sort = 3, regdate = now() where name = '{$두자리닉넴}' ");
      db_query("UPDATE config SET 야바위강제마감 = ''");

      $신청여부 = db_select("select cnt, sort from tb_run_member where name = '{$두자리닉넴}'");
      $rollMsg = $자숙위반안내 . implode("\n", $lines) . "\n현재 {$신청여부['cnt']}점";
    }

    $진행 = db_select("select count(*) as cnt from tb_run_member");
    $종료 = db_select("select count(*) as cnt from tb_run_member where sort = 3");
    if ($진행['cnt'] == $종료['cnt']) {

      $result = db_query("select * from tb_run_member order by cnt desc, regdate asc ");
      $msg = "야바위 결과\n\n";

      $point = 0;
      $참가목록 = [];
      for ($i = 0; $row = db_fetch($result); $i++) {
        $참가목록[] = $row;
        $point += $row['point'];
        $msg .= $row['name']." ".$row['cnt']."점\n";
      }

      $winner = $참가목록[0] ?? null;
      $랭커목록 = array_slice($참가목록, 0, 3);
      if (!$winner) {
        echo 전송("야바위 참가자가 없어요.");
        exit;
      }

      $x2확률 = min(100, max(0, count($참가목록)));
      $isX2 = (mt_rand(1, 100) <= $x2확률);

      $multiplier = $isX2 ? 2 : 1;
      $reward = $point * $multiplier;

      $bonus10 = 0;
      if ($isX2) {
        $bonus10 = floor($reward * 0.1);
      }

      $totalReward = $reward - $bonus10;
      if ($totalReward < 0) {
        $totalReward = 0;
      }

      $참가자수 = count($참가목록);
      $분배목록 = array();
      if ($참가자수 >= 5 && count($랭커목록) >= 3) {
        $삼등지급 = (int)floor($totalReward * 0.10);
        $이등지급 = (int)floor($totalReward * 0.20);
        $일등지급 = (int)($totalReward - $삼등지급 - $이등지급);
        if ($일등지급 < 0) {
          $일등지급 = 0;
        }

        $분배목록[] = array('rank' => 1, 'name' => $랭커목록[0]['name'], 'cnt' => (int)$랭커목록[0]['cnt'], 'amount' => $일등지급);
        $분배목록[] = array('rank' => 2, 'name' => $랭커목록[1]['name'], 'cnt' => (int)$랭커목록[1]['cnt'], 'amount' => $이등지급);
        $분배목록[] = array('rank' => 3, 'name' => $랭커목록[2]['name'], 'cnt' => (int)$랭커목록[2]['cnt'], 'amount' => $삼등지급);
      } else {
        $분배목록[] = array('rank' => 1, 'name' => $winner['name'], 'cnt' => (int)$winner['cnt'], 'amount' => (int)$totalReward);
      }

      $msg .= "\n🏆 우승자 {$winner['name']} ({$winner['cnt']}점)";

      if ($isX2) {
        $msg .= "\n🎉 x2 당첨!";
        $msg .= "\n당첨금 10% ".야바위_금액표시($bonus10, $야바위단위)." 금고 적립!";
      }
      $msg .= "\n💰 총 획득 : ".야바위_금액표시($totalReward, $야바위단위);
      if (count($분배목록) >= 3) {
        $msg .= "\n\n💸 분배";
        $msg .= "\n1등 {$분배목록[0]['name']} : ".야바위_금액표시($분배목록[0]['amount'], $야바위단위);
        $msg .= "\n2등 {$분배목록[1]['name']} : ".야바위_금액표시($분배목록[1]['amount'], $야바위단위);
        $msg .= "\n3등 {$분배목록[2]['name']} : ".야바위_금액표시($분배목록[2]['amount'], $야바위단위);
      }

      $게임최고점 = 0;
      $게임최고자 = null;
      foreach ($참가목록 as $참가행) {
        $참가점 = (int)($참가행['cnt'] ?? 0);
        if ($참가점 > $게임최고점) {
          $게임최고점 = $참가점;
          $게임최고자 = $참가행;
        }
      }
      $기존캐시 = function_exists('야바위_최고점수_캐시_읽기')
        ? 야바위_최고점수_캐시_읽기($설정)
        : ['점수' => 0, '닉' => '', '상태' => '', '일시' => ''];
      $표시캐시 = $기존캐시;
      if ($게임최고점 > (int)($기존캐시['점수'] ?? 0) && $게임최고자) {
        $표시상태 = "주사위{$게임최고점}점";
        foreach ($분배목록 as $지급정보) {
          if ($지급정보['name'] === $게임최고자['name']) {
            $표시상태 = "주사위{$지급정보['cnt']}점".($isX2 ? ' x2' : '')." {$지급정보['rank']}등";
            break;
          }
        }
        $표시캐시 = [
          '점수' => $게임최고점,
          '닉' => $게임최고자['name'],
          '상태' => $표시상태,
          '일시' => date('Y-m-d H:i:s'),
        ];
      }
      if (function_exists('야바위_최고점수_표시문구')) {
        $msg .= 야바위_최고점수_표시문구($표시캐시);
      }

      if ($rollMsg !== '') {
        $msg = $rollMsg . "\n\n" . $msg;
      }
      echo 전송($msg);
      if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
      }

      if ($bonus10 > 0) {
        db_query("UPDATE config SET tax = tax + {$bonus10} ");
      }

      foreach ($분배목록 as $지급정보) {
        $지급닉 = addslashes($지급정보['name']);
        $지급금 = (int)$지급정보['amount'];
        if ($지급금 <= 0) {
          continue;
        }
        db_query("
                  update tb_member
                  set point = point + {$지급금}
                  where name = '{$지급닉}'
              ");
        지급로그(
          "주사위{$지급정보['cnt']}점".($isX2 ? ' x2' : '')." {$지급정보['rank']}등",
          $지급정보['name'],
          '',
          0,
          $지급금
        );
      }

      if ($게임최고점 > (int)($기존캐시['점수'] ?? 0) && $게임최고자 && function_exists('야바위_최고점수_캐시_갱신')) {
        $갱신상태 = trim((string)($표시캐시['상태'] ?? ''));
        if ($갱신상태 === '') {
          $갱신상태 = "주사위{$게임최고점}점";
        }
        야바위_최고점수_캐시_갱신($게임최고점, $게임최고자['name'], $갱신상태);
      }
      if (function_exists('야바위_최고점수_캐시_없으면_시드')) {
        야바위_최고점수_캐시_없으면_시드();
      }

      db_query("delete from tb_run_member");
      db_query("UPDATE config SET 야바위게임시작 = NULL");
      exit;
    }

    if ($rollMsg !== '') {
      echo 전송($rollMsg);
      exit;
    }

    $최종 = db_select("select cnt, sort from tb_run_member where name = '{$두자리닉넴}'");
    echo 전송("{$두자리닉넴} 종료! 3회 결과 {$최종['cnt']}점");
    exit;
  }

  return;
}

if (strpos($status, '.마감') !== false) {
  $첫신청자 = db_query("select * from tb_run_member order by idx asc limit 2");
  $names = [];

  while ($row = db_fetch($첫신청자)) {
    $names[] = $row['name'];
  }

  if (!in_array($두자리닉넴, $names, true)) {
    echo 전송("주사위 신청자 [ " . implode(', ', $names) . " ] 마감하자!");
    exit;
  }

  $참여자 = db_select("select count(*) as cnt from tb_run_member where status = 0");
  if ($참여자['cnt'] == 1) {
    echo 전송("최소 2인 이상 마감 가능!");
    exit;
  }

  if ($참여자['cnt'] > 0) {
    db_query("UPDATE tb_run_member SET status = 1, 신청일시 = DATE_ADD(NOW(), INTERVAL 30 MINUTE)");
    $야바위강제마감시간 = date('H:i', strtotime('+10 minutes'));
    db_query("UPDATE config SET 야바위강제마감 = '{$야바위강제마감시간}', 야바위게임시작 = NOW()");
    echo 전송("게임시작!\n`ㄷㄹ` 1회 입력(주사위 3연속) 후\n총 합이 높은 친구가 승리!\n야바위 강제마감: {$야바위강제마감시간}");
    exit;
  }

  return;
}

if (strpos($status, '.신청') !== false || strpos($status, 'ㅅㅊ') !== false) {

  게임제한_차단($두자리닉넴);

  if (preg_match('/(?:\.신청|ㅅㅊ)(?:\s+(.+?))?\s*$/u', $status, $match)) {
    $금액텍스트 = isset($match[1]) ? trim((string)$match[1]) : '';
    $금액 = $금액텍스트 !== '' ? 냥_금액_파싱($금액텍스트) : 0;

    $첫신청자 = db_select("select * from tb_run_member order by idx asc limit 1");

    if ($금액텍스트 !== '' && $금액 <= 0) {
      echo 전송("❌ 금액 형식을 확인해주세요.\n예) .신청 1억 / .신청 5조 / .신청 1조1천1백");
      exit;
    }

    if ($금액 <= 0) {
      if ($첫신청자 && $첫신청자['point'] > 0) {
        $금액 = $첫신청자['point'];
      }
    }

    if ($정보['point'] < $주사위참가비) {
      echo 전송("냥 부족! 보유 ".야바위_금액표시((int)$정보['point'], $야바위단위));
      exit;
    }

    $출석상태 = 오늘100타출석상태($두자리닉넴, $오늘);
    if (!$출석상태['완료']) {
      echo 전송("❌ `.야바위` 는 오늘 100타 출석(출석미션) 완료 후에 이용 가능해요!\n{$두자리닉넴} 현재 {$출석상태['현재']}타 / 100타");
      exit;
    }

    if ($첫신청자['idx']) {
      if ($금액 < $첫신청자['point']) {
        echo 전송("야바위 최소 참가 ".야바위_금액표시((int)$첫신청자['point'], $야바위단위)."!");
        exit;
      }
    }

    if ($금액 < $주사위참가비 || $금액 > $주사위최대참가비) {
      echo 전송("최소 참가금 ".야바위_금액표시($주사위참가비, $야바위단위)."\n최대 참가금 ".야바위_금액표시($주사위최대참가비, $야바위단위));
      exit;
    }

    $참여자 = db_select("select count(*) as cnt from tb_run_member where status = 1");
    if ($참여자['cnt'] > 0) {
      echo 전송("{$두자리닉넴} 다음 게임에 참가하자!");
      exit;
    }

    $신청여부 = db_select("select count(*) as cnt from tb_run_member where name = '{$두자리닉넴}'");
    if ($신청여부['cnt'] > 0) {
      echo 전송("{$두자리닉넴} 야바위 준비완료!");
      exit;
    }

    $자숙위반 = function_exists('자숙_야바위위반_적용')
      ? 자숙_야바위위반_적용($두자리닉넴, isset($단위) ? $단위 : '냥')
      : ['notice' => ''];
    $자숙위반안내 = (string)($자숙위반['notice'] ?? '');
    $갱신정보 = db_select("SELECT point FROM tb_member WHERE name = '{$두자리닉넴}' LIMIT 1");
    if ((int)($갱신정보['point'] ?? 0) < $금액) {
      echo 전송($자숙위반안내 . "냥 부족! 보유 " . 야바위_금액표시((int)($갱신정보['point'] ?? 0), $야바위단위));
      exit;
    }

    $신청결과 = db_query("insert into tb_run_member set status = 0, name = '{$두자리닉넴}', point = {$금액}, cnt = 0, sort = 0, regdate = now()");
    db_query("update tb_member set point = point - {$금액} where name = '{$두자리닉넴}' ");

    지급로그('주사위-신청', $두자리닉넴, '', 0, $금액);
    if ($신청결과) {
      미션완료_기록_if_new($두자리닉넴, '일방', '야바위');
      $전체참가자 = db_select("select count(*) as cnt from tb_run_member");
      $x2확률 = min(100, max(0, (int)($전체참가자['cnt'] ?? 0)));
      $msg = $자숙위반안내 . "🎲{$두자리닉넴} 야바위 참여완료🎲\n🎉 현재 x2확률 {$x2확률}%{$신불문구}";
      echo 전송($msg);
      exit;
    }
  }

  return;
}
