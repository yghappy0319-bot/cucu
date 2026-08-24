<?php
require_once __DIR__ . '/_bootstrap.php';
include_once __DIR__ . '/_bonbang.php';
// 관리용

// 한글 문자열을 초성 문자열로 변환하는 유틸 함수들
if (!function_exists('unicode_ord')) {
  function unicode_ord($ch) {
    $h = ord($ch[0]);
    if ($h <= 0x7F) return $h;
    if ($h < 0xC2) return null;
    if ($h <= 0xDF) return (($h & 0x1F) << 6) | (ord($ch[1]) & 0x3F);
    if ($h <= 0xEF) return (($h & 0x0F) << 12) | ((ord($ch[1]) & 0x3F) << 6) | (ord($ch[2]) & 0x3F);
    if ($h <= 0xF4) return (($h & 0x07) << 18) | ((ord($ch[1]) & 0x3F) << 12)
                                   | ((ord($ch[2]) & 0x3F) << 6)
                                   | (ord($ch[3]) & 0x3F);
    return null;
  }
}

if (!function_exists('toChosung')) {
  function toChosung($str) {
    $chosungList = ['ㄱ','ㄲ','ㄴ','ㄷ','ㄸ','ㄹ','ㅁ','ㅂ','ㅃ','ㅅ','ㅆ','ㅇ','ㅈ','ㅉ','ㅊ','ㅋ','ㅌ','ㅍ','ㅎ'];
    $result = '';
    $len = mb_strlen($str, 'UTF-8');

    for ($i = 0; $i < $len; $i++) {
      $ch = mb_substr($str, $i, 1, 'UTF-8');
      $code = unicode_ord($ch);

      // 한글 음절 범위 (가 ~ 힣)
      if ($code !== null && $code >= 0xAC00 && $code <= 0xD7A3) {
        $base = $code - 0xAC00;
        $choIndex = (int)($base / (21 * 28));
        $result .= $chosungList[$choIndex] ?? $ch;
      } else {
        // 한글이 아니면 그대로 유지 (공백/기호 등)
        $result .= $ch;
      }
    }

    return $result;
  }
}

$nick = nick_파라미터($nick ?? '');
$두자리닉넴 = getTwoCharNick($nick);
$status = function_exists('status_정규화')
  ? status_정규화(msg_파라미터($msg ?? ''))
  : trim(msg_파라미터($msg ?? ''));
list($두자리닉넴, $정보) = 회원정보_동기화($nick, $두자리닉넴);
if ($두자리닉넴 === '' && trim((string)($정보['name'] ?? '')) !== '') {
  $두자리닉넴 = trim((string)$정보['name']);
}
include_once __DIR__ . '/config.php';

// .자숙 — 최우선 (회원·닉 무관, SQL 오류 시에도 응답)
if (function_exists('자숙_명령_처리')) {
  자숙_명령_처리($status);
}
if (function_exists('자숙종료_명령_처리')) {
  자숙종료_명령_처리($status, $두자리닉넴, $nick ?? '', $관리자 ?? []);
}

if (trim((string)$status) === '.우리방초기화') {
  if ($두자리닉넴 !== '진우') {
    echo 전송('❌ `.우리방초기화`는 [ 진우 ] 님만 사용할 수 있어요.');
    exit;
  }
  db_query('DELETE FROM tb_msg');
  db_query("UPDATE tb_member SET title = '', level = 0, tasu = 0, stasu = 0");
  db_query('DELETE FROM tb_question');
  db_query('DELETE FROM tb_battle');
  echo 전송("✅ 우리방 초기화 완료\n\n· tb_msg 전체 삭제\n· tb_member title·level·tasu·stasu(누적타수) 초기화\n· tb_question 전체 삭제\n· tb_battle 전체 삭제");
  exit;
}

// info3 전용 .이체 — 게임냥(point) 지급/차감 (관리자)
if (strpos($status, '.이체') !== false) {
  관리자_명령_차단($두자리닉넴, $nick ?? '');
  if (!preg_match('/\.이체\s*(.+)/u', $status, $이체매치)) {
    echo 전송("❌ 사용법: .이체 닉네임 금액\n예) .이체 진우 1조 / .이체 뮤뮤 26경8천조 / .이체 진우 -1조");
    exit;
  }
  $after = trim($이체매치[1]);
  if (!preg_match('/^(\S+)\s+(.+)$/u', $after, $parts)) {
    echo 전송("❌ 사용법: .이체 닉네임 금액\n예) .이체 진우 1조 / .이체 뮤뮤 26경8천조 / .이체 진우 -1조");
    exit;
  }
  $받는닉 = trim($parts[1]);
  $지급양 = function_exists('냥_금액_파싱_부호포함') ? 냥_금액_파싱_부호포함(trim($parts[2])) : 0;
  if ($지급양 === 0) {
    echo 전송("❌ 금액 형식을 확인해주세요.\n예) .이체 진우 1조 / .이체 뮤뮤 26경8천조 / .이체 진우 -1조");
    exit;
  }
  $받는닉_esc = addslashes($받는닉);
  $받는친구 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$받는닉_esc}' LIMIT 1");
  if (empty($받는친구['idx'])) {
    echo 전송("존재하지 않는 사용자 입니다.");
    exit;
  }
  $금액표시 = function_exists('게임냥_경조억표시') ? 게임냥_경조억표시(abs($지급양)) : number_format(abs($지급양));
  if ($지급양 < 0) {
    $차감양 = abs($지급양);
    db_query("UPDATE tb_member SET point = point - {$차감양} WHERE name = '{$받는닉_esc}'");
    $지급여부 = '차감';
    $msg = "💰 {$받는친구['name']}에게 {$금액표시} 게임냥 이체 차감!";
  } else {
    db_query("UPDATE tb_member SET point = point + {$지급양} WHERE name = '{$받는닉_esc}'");
    $지급여부 = '이체';
    $msg = "💰 {$받는친구['name']}에게 {$금액표시} 게임냥 이체 지급!";
  }
  지급로그($지급여부, $두자리닉넴, $받는친구['name'], 0, $지급양);
  echo 전송($msg);
  exit;
}

// info3 .입금 — 본방냥(newpoint) 지급/차감 (_mutual.php 게임냥 입금보다 먼저 처리)
if (strpos($status, '.입금') !== false && preg_match('/\.입금\s*(.+)/u', $status, $입금매치)) {
  관리자_명령_차단($두자리닉넴, $nick ?? '');
  $after = trim($입금매치[1]);
  if (preg_match('/^(\S+)\s+(.+)$/u', $after, $parts)) {
    $받는닉 = trim($parts[1]);
    $지급양 = function_exists('냥_금액_파싱_부호포함') ? 냥_금액_파싱_부호포함(trim($parts[2])) : (float)trim($parts[2]);
    if ($지급양 !== 0) {
    $받는닉_esc = addslashes($받는닉);
    if ($받는닉 === '전체') {
      if ($지급양 < 0) {
        $차감양 = abs($지급양);
        db_query("UPDATE tb_member SET newpoint = newpoint - {$차감양} WHERE status = 0");
      } else {
        db_query("UPDATE tb_member SET newpoint = newpoint + {$지급양} WHERE status = 0");
      }
      $msg = "📣 전체 인원에게 " . newpoint표시($지급양) . " 본방냥 지급!";
      $받는친구명 = '전체';
      $지급여부 = $지급양 < 0 ? '차감' : '입금';
    } elseif ($받는닉 === '랜덤박스') {
      $지급개수 = (int)$지급양;
      if ($지급개수 < 1) {
        echo 전송("❌ 랜덤박스 개수는 1 이상으로 입력해줘.");
        exit;
      }
      $result = db_query("SELECT name FROM tb_member WHERE status = 0");
      while ($row = db_fetch($result)) {
        $대상_esc = addslashes($row['name']);
        for ($i = 0; $i < $지급개수; $i++) {
          db_query("
            INSERT INTO tb_member_item
            SET nick = '{$대상_esc}', itemname = '랜덤박스', status = 0, regdate = NOW()
          ");
        }
      }
      $msg = "📣 전체 인원에게 랜덤박스 {$지급개수}개씩 입금 완료!";
      $받는친구명 = '전체';
      $지급여부 = '입금';
      $지급양 = $지급개수;
    } else {
      $받는친구 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$받는닉_esc}' LIMIT 1");
      if (empty($받는친구['idx'])) {
        echo 전송("존재하지 않는 사용자 입니다.");
        exit;
      }
      if ($지급양 < 0) {
        $차감양 = abs($지급양);
        db_query("UPDATE tb_member SET newpoint = newpoint - {$차감양} WHERE name = '{$받는닉_esc}'");
        $지급여부 = '차감';
        $msg = "💰 {$받는친구['name']}에게 " . newpoint표시($차감양) . " 본방냥 차감!";
      } else {
        db_query("UPDATE tb_member SET newpoint = newpoint + {$지급양} WHERE name = '{$받는닉_esc}'");
        $지급여부 = '입금';
        $msg = "💰 {$받는친구['name']}에게 " . newpoint표시($지급양) . " 본방냥 입금!";
      }
      $받는친구명 = $받는친구['name'];
    }
    지급로그($지급여부, $두자리닉넴, $받는친구명, 0, $지급양);
    echo 전송($msg);
    exit;
    }
  }

  $일괄문자열 = trim(preg_replace('/\s+/u', ' ', $after));
  $일괄토큰 = $일괄문자열 !== '' ? preg_split('/\s+/u', $일괄문자열) : [];
  if (count($일괄토큰) >= 2) {
    $마지막토큰 = (string)end($일괄토큰);
    $지급양 = function_exists('냥_금액_파싱_부호포함') ? 냥_금액_파싱_부호포함($마지막토큰) : 0;
    if ($지급양 !== 0) {
      array_pop($일괄토큰);
      $닉목록 = array_values(array_filter($일괄토큰, static function ($t) {
        return trim((string)$t) !== '';
      }));
      if (count($닉목록) < 1) {
        echo 전송("❌ 일괄 입금할 닉네임을 입력해주세요.");
        exit;
      }

      $성공목록 = [];
      $실패목록 = [];
      $중복스킵 = [];
      $이미처리 = [];
      $지급여부 = $지급양 < 0 ? '차감' : '입금';

      foreach ($닉목록 as $받는닉) {
        $받는닉 = trim((string)$받는닉);
        if ($받는닉 === '') {
          continue;
        }
        if (isset($이미처리[$받는닉])) {
          $중복스킵[] = $받는닉;
          continue;
        }
        $이미처리[$받는닉] = true;

        if ($받는닉 === '전체' || $받는닉 === '랜덤박스') {
          $실패목록[] = $받는닉 . '(일괄 불가)';
          continue;
        }

        $받는닉_esc = addslashes($받는닉);
        $받는친구 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$받는닉_esc}' LIMIT 1");
        if (empty($받는친구['idx'])) {
          $실패목록[] = $받는닉;
          continue;
        }

        if ($지급양 < 0) {
          $차감양 = abs($지급양);
          db_query("UPDATE tb_member SET newpoint = newpoint - {$차감양} WHERE name = '{$받는닉_esc}'");
        } else {
          db_query("UPDATE tb_member SET newpoint = newpoint + {$지급양} WHERE name = '{$받는닉_esc}'");
        }
        지급로그($지급여부, $두자리닉넴, $받는친구['name'], 0, $지급양);
        $성공목록[] = $받는친구['name'];
      }

      if (count($성공목록) < 1) {
        $msg = "❌ 일괄 {$지급여부} 실패 — 처리된 인원이 없어요.";
        if (count($실패목록) > 0) {
          $msg .= "\n없는 닉: " . implode(', ', $실패목록);
        }
        echo 전송($msg);
        exit;
      }

      $msg = "💰 일괄 본방냥 {$지급여부} 완료 (" . count($성공목록) . "명 × " . newpoint표시(abs($지급양)) . ")\n";
      $msg .= implode(', ', $성공목록);
      if (count($실패목록) > 0) {
        $msg .= "\n\n❌ 없는 닉: " . implode(', ', $실패목록);
      }
      if (count($중복스킵) > 0) {
        $msg .= "\n⚠️ 중복 제외: " . implode(', ', $중복스킵);
      }
      echo 전송($msg);
      exit;
    }
  }

  echo 전송("❌ 사용법: .입금 닉네임 금액 (본방냥)\n예) .입금 진우 50000 / .입금 뮤뮤 26경8천조\n\n일괄 예)\n.입금 여름 나비 스리 50000");
  exit;
}

// info3 .내냥 — 본방냥 + 게임냥(축약 표시)
if (trim((string)$status) === '.내냥' && trim((string)($정보['name'] ?? '')) !== '') {
  $계급 = 계급($정보['point'] ?? 0);
  $호칭 = !empty($정보['title']) ? $정보['title'] : ($계급['name'] ?? '');
  $게임냥 = (int)($정보['point'] ?? 0);
  $게임냥표시 = function_exists('랭킹_게임냥표시')
    ? 랭킹_게임냥표시($게임냥, $단위)
    : number_format($게임냥) . $단위;
  $msg1 = $호칭 . " {$두자리닉넴} " . newpoint표시($정보['newpoint'] ?? 0) . "{$단위}";
  $msg1 .= "\n게임냥 : " . $게임냥표시;
  echo 전송($msg1);
  exit;
}

// info3 전용 — 꼬맨틀 완료 (일 5명·본방냥 1,000) / 관리자: .꼬맨완료 닉네임
if (preg_match('/^\.꼬맨완료(?:\s+(\S+))?\s*$/u', trim($status), $꼬맨매치)) {
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
  $지급냥 = 1000;
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
  $결과 = "✅ 꼬맨 완료!\n\n{$대상닉} 님 본방냥 ".number_format($지급냥) . "냥 지급";
  if ($관리자대리) {
    $결과 .= "\n(관리자 {$두자리닉넴} 등록)";
  }
  $결과 .= "\n오늘 남은 참여 가능: {$잔여}명 ({$완료}/{$한도}명 완료)";
  echo 전송($결과);
  exit;
}

$MUTUAL_SKIP_DEPOSIT = true;
list($두자리닉넴, $정보) = 회원정보_동기화($nick, $두자리닉넴);
if ($두자리닉넴 === '' && trim((string)($정보['name'] ?? '')) !== '') {
  $두자리닉넴 = trim((string)$정보['name']);
}
include_once __DIR__ . "/_mutual.php";

if($두자리닉넴){
  list($두자리닉넴, $정보) = 회원정보_동기화($nick, $두자리닉넴);
  
  if (trim((string)($정보['name'] ?? '')) === '') {
    if ($status === '' || strpos(trim((string)$status), '.') !== 0) {
      exit;
    }
  }

  if (preg_match('/^\.스왑(?:\s+(\d+(?:\.\d+)?))?$/u', trim($status), $스왑매치)) {
    if (!function_exists('스왑_본방_실행')) {
      require_once __DIR__ . '/game/swap.inc.php';
    }
    $스왑금액 = (isset($스왑매치[1]) && $스왑매치[1] !== '') ? (float)$스왑매치[1] : null;
    스왑_본방_실행($두자리닉넴, $스왑금액);
  }

  if (trim($status) === '.스왑방법') {
    if (!function_exists('스왑_방법문구_본방')) {
      require_once __DIR__ . '/game/swap.inc.php';
    }
    $내_보유냥 = round((float)($정보['newpoint'] ?? 0), 1);
    echo 전송(스왑_방법문구_본방($내_보유냥 >= 0.1 ? $내_보유냥 : null));
    exit;
  }

  // 상황실 .환율 — 보유냥 스왑·원화 기준 게임냥 예상 (본방 info1과 동일)
  if (preg_match('/^\.환율(?:\s+(.+))?$/u', trim($status), $환율매치)) {
    if (!function_exists('스왑_환율_미리보기문구')) {
      require_once __DIR__ . '/game/swap.inc.php';
    }
    $입력 = trim((string)($환율매치[1] ?? ''));
    if ($입력 === '') {
      echo 전송("❌ 사용법: .환율 금액\n예) .환율 5000 · .환율 1억 · .환율 5000원");
      exit;
    }
    $원화모드 = (bool)preg_match('/원(?:화)?$/u', $입력);
    if ($원화모드) {
      $입력 = trim(preg_replace('/원(?:화)?$/u', '', $입력));
    }
    echo 전송(스왑_환율_미리보기문구($입력, $원화모드));
    exit;
  }

  if (trim($status) === '.주문') {
    require_once dirname(__DIR__) . '/shop/_shop.php';
    echo 전송(shop_채팅_주문목록_문구());
    exit;
  }

  if (preg_match('/^\.마켓큰손(?:\s+(\d+))?\s*$/u', trim($status), $마켓큰손m)) {
    require_once dirname(__DIR__) . '/shop/_shop.php';
    $상위 = (isset($마켓큰손m[1]) && $마켓큰손m[1] !== '') ? (int)$마켓큰손m[1] : 10;
    echo 전송(shop_채팅_마켓큰손_문구($상위));
    exit;
  }

  수호_상황실_명령_처리($status, $두자리닉넴);
  제한_상황실_명령_처리($status, $두자리닉넴);
  지목_명령_처리($status, $두자리닉넴);
  강일_명령_처리($status, $두자리닉넴);
  강일취소_명령_처리($status, $두자리닉넴);
  강일종료_명령_처리($status, $두자리닉넴);
  강일연장_명령_처리($status, $두자리닉넴);

  if(strpos($status, '.메모') !== false){
      if (preg_match('/^\.메모\s+(.+)\s+(\d+)$/u', trim($status), $match)) {
          $텍스트 = trim($match[1]);
          $시간 = (int)$match[2];
          if ($텍스트 === '' || $시간 < 1) {
              echo 전송("❌ .메모 텍스트 시간(숫자) 형식으로 입력해줘!\n예) .메모 텍스트 20");
              exit;
          }
          $edate = date("Y-m-d H:i:s", strtotime("+{$시간} hours"));
          $text_esc = addslashes($텍스트);
          db_query("insert into tb_gangil_tile set text = '{$text_esc}', edate = '{$edate}', regdate = now() ");
          $만료표시 = date("m-d H:i", strtotime($edate));
          echo 전송("✅ 메모 등록!\n• {$텍스트}\n만료: {$만료표시} (+{$시간}시간)");
          exit;
      }
      echo 전송("❌ .메모 텍스트 시간(숫자) 형식으로 입력해줘!\n예) .메모 텍스트 20");
      exit;

  }else if($status==".기록"){
      $result = db_query("SELECT text, edate, regdate FROM tb_gangil_tile ORDER BY regdate DESC ");
      $msg = "📋 기록\n\n";
      if ($result && mysqli_num_rows($result) > 0) {
          while ($row = db_fetch($result)) {
              $등록 = $row['regdate'] ? date("m-d H:i", strtotime($row['regdate'])) : '-';
              $만료 = $row['edate'] ? date("m-d H:i", strtotime($row['edate'])) : '-';
              $msg .= "• {$row['text']} {$만료}\n";
          }
      } else {
          $msg .= "(기록 없음)";
      }
      echo 전송($msg);
      exit;

  }else if (strpos($status, '.본인인증') !== false) {
    $본문 = trim($status);
    $신청닉 = $두자리닉넴;
    $상대닉 = '';
    if (preg_match('/^\.본인인증(?:\s+(\S+)(?:\s+(\S+))?)?/u', $본문, $m)) {
      if (!empty($m[1])) {
        $신청닉 = getTwoCharNick(trim($m[1]));
      }
      if (!empty($m[2])) {
        $상대닉 = getTwoCharNick(trim($m[2]));
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
    $msg.= "\n\n위 내용을 메모지에 작성 하거나,\n손바닥에 적은 뒤 얼굴 나오게 셀카1장 찍어줘! 도용,합성인지 확인하는것이니 얼굴은 전체 다 나오게! 확인 후 바로 삭제할게!\n\n인증이 끝나면 메모지는 잘 찢어 버리자!\n\n첫 일방 1회만 본인인증";
    echo 전송($msg);
    exit;
  }else if (preg_match('/^\.인증완료\s+(\S+)\s*$/u', trim($status), $인증완료m)) {
    if (!in_array($두자리닉넴, (array)$관리자, true)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    $대상닉 = getTwoCharNick(trim($인증완료m[1]));
    if ($대상닉 === '') {
      $대상닉 = preg_replace('/\s+/u', '', (string)$인증완료m[1]);
    }
    $대상_esc = addslashes($대상닉);
    $대상행 = db_select("SELECT idx, IFNULL(`본인인증`, 0) AS bonin FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
    if (empty($대상행['idx'])) {
      echo 전송("❌ {$대상닉} 회원을 찾을 수 없어요.");
      exit;
    }
    if ((int)$대상행['bonin'] === 1) {
      echo 전송("✅ {$대상닉} — 이미 본인인증 완료 상태예요.");
      exit;
    }
    db_query("UPDATE tb_member SET `본인인증` = 1 WHERE name = '{$대상_esc}' LIMIT 1");
    echo 전송("✅ {$대상닉} 본인인증 완료 처리했어요.");
    exit;
  }else if (strpos($status, '.파손방지') !== false) {
    if (!in_array($두자리닉넴, $관리자)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    if (!preg_match('/\.파손방지?\s+(.+)/u', trim($status), $m)) {
      echo 전송("❌ 사용법: .파손방지제 [닉네임] 수량  또는  .파손방지제 수량 (전체)");
      exit;
    }
    $rest = trim($m[1]);
    $parts = preg_split('/\s+/u', $rest, 2);
    $대상닉 = null;
    $수량 = 0;
    if (count($parts) === 2 && is_numeric(trim($parts[1]))) {
      $대상닉 = trim($parts[0]);
      $수량 = (int)$parts[1];
    } elseif (count($parts) === 1 && is_numeric(trim($parts[0]))) {
      $수량 = (int)$parts[0];
    }

    if ($대상닉 !== null && $대상닉 !== '') {
      // 특정 닉네임 대상
      $대상_esc = addslashes($대상닉);
      if ($수량 >= 0) {
        db_query("UPDATE tb_member SET enhance_suho = IFNULL(enhance_suho, 0) + {$수량} WHERE name = '{$대상_esc}'");
        echo 전송("👼 파손방지제 적용: {$대상닉} → 수호 {$수량}회 추가");
      } else {
        $감소 = abs($수량);
        db_query("UPDATE tb_member SET enhance_suho = GREATEST(IFNULL(enhance_suho, 0) - {$감소}, 0) WHERE name = '{$대상_esc}'");
        echo 전송("👼 파손방지제 적용: {$대상닉} → 수호 {$감소}회 차감");
      }
    } else {
      // 전체 인원 대상
      if ($수량 >= 0) {
        db_query("UPDATE tb_member SET enhance_suho = IFNULL(enhance_suho, 0) + {$수량}");
        echo 전송("👼 파손방지제 전체 적용: 모든 인원 수호 {$수량}회 추가");
      } else {
        $감소 = abs($수량);
        db_query("UPDATE tb_member SET enhance_suho = GREATEST(IFNULL(enhance_suho, 0) - {$감소}, 0)");
        echo 전송("👼 파손방지제 전체 적용: 모든 인원 수호 {$감소}회 차감");
      }
    }
    exit;    
  }else if($status==".등장" || $status=="등장"){
    $타이틀 = trim($정보['title'] ?? '');
    $내템 = trim($정보['item'] ?? '');
    $내강 = (int)($정보['enhance'] ?? 0);
    $오강이상 = ($내템 !== '' && $내강 >= 5);
    $스타일문구 = ($오강이상 && trim($정보['style'] ?? '') !== '') ? "+".$내강." ".trim($정보['style']) . ' ' : '';
    // 5강 이상 아이템 있음 → 스타일+아이템 (타이틀 있으면 타이틀까지)
    if ($오강이상) {
      if ($타이틀 !== '') {
        echo 전송("{$스타일문구}{$내템} {$타이틀} {$두자리닉넴} 등장!");
      } else {
        echo 전송("{$스타일문구}{$내템} {$두자리닉넴} 등장!");
      }
      exit;
    }
    // 타이틀만 있으면 타이틀만
    if ($타이틀 !== '') {
      echo 전송($타이틀." {$두자리닉넴} 등장!");
      exit;
    }
    exit; // 5강 무기·타이틀 둘 다 없으면 아무것도 안 나옴

  }else if(preg_match('/^\.미새모드\s+(on|off)$/u', trim($status), $m)){
    if ($m[1] === 'on') {
      echo 전송("미세먼지 제가 모드가 활성화 되었습니다. 공기청정을 시작합니다");
    } else {
      echo 전송("미세먼지 제거 모드가 종료되었습니다.");
    }
    exit;
  }else if($status==".신입"){
    $msg = "🐥신입기간🐥
들어온 시간으로부터 24시간 동안은
상황실(공질)가는것, 강제일방, 일방
불가야🙅🏿‍♂️

신입기간동안 얼공,보룸은 가능해🙆‍♂️
보이스룸은 봇 제외 3인 
이상시 마이크 ON!

🚫자삭 절대 금지🚫

🙅🏿‍♂️타수제한은 없어!
💁🏼‍♀️꼭 해줘야하는것!
1. 매일 한번 출석,ㅊㅅ,찰싹 입력하기!
2. 인사는 하고 살자!

우리방은 .미션 이라고 입력해서
미션 수행을 해야만
일방을 갈 수 있어!

우리방 시스템 중
냥이라는 게 있어!
채팅 1개당 0.1냥 누적돼!

일방은 .진행
공커는 .공커
입력해보면돼!

입장시간은 .궁금 본인닉네임 확인해보자!
신입기간 끝나면 상황실에 색 받으러와!";
    echo 전송($msg);
    exit;
  // }else if($status==".채굴"){
  //   $msg = "유사도 🔥200위~250위🔥
  //
  // -평일
  // 새벽 00시~06시 3명
  // 오전 06시~12시 3명
  //
  // -주말
  // ⭐️시간상관없이 6명
  //
  // 캡쳐 후 정답 가리고 공창인증🤲
  // https://semantle-ko.newsjel.ly";
  //   echo 전송($msg);
  //   exit;

  }else if (strpos(trim($status), '.강제출석') === 0) {
    if (!in_array($두자리닉넴, $관리자)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    $parts = preg_split('/\s+/u', trim($status), 2);
    $대상닉 = isset($parts[1]) ? trim($parts[1]) : '';
    $결과 = 강제출석_적용($대상닉);
    echo 전송($결과['msg']);
    exit;

  }else if(preg_match('/^\.출석포기(?:\s+(.+))?$/u', trim($status), $출석포기m)){
    $출석포기사유 = isset($출석포기m[1]) ? trim($출석포기m[1]) : '';
    if ($출석포기사유 === '') {
      echo 전송("❌ 사용법: .출석포기 (사유)\n예) .출석포기 오늘은 쉴래요");
      exit;
    }
    $결과 = 출석포기_적용($두자리닉넴, $출석포기사유);
    echo 전송($결과['msg']);
    exit;

  }else if($status==".연두"){
    관리자전용_확인($두자리닉넴, $관리자);

    $sql = "SELECT count(*) as cnt FROM tb_member AS m
    LEFT JOIN tb_attendance AS a
    ON m.name = a.nickname AND a.regdate = CURDATE()
    WHERE a.nickname IS NULL and m.status = 0
    AND IFNULL(m.attendance, 0) != 2";
    $출석자 = db_select($sql);
    if($출석자['cnt']>0){
      $sql = "SELECT m.*
      FROM tb_member AS m
      LEFT JOIN tb_attendance AS a
        ON m.name = a.nickname
        AND a.regdate = CURDATE()
      WHERE a.nickname IS NULL and m.status = 0
      AND IFNULL(m.attendance, 0) != 2";

      $result = db_query($sql);

      $html = "✅ 연락두절 {$오늘}\n\n";
      while ($row = db_fetch($result)) {
        $html .= $row['name'] . " ";
      }

      $html.= "\n\n.출석, ㅊㅅ, 찰싹 해보자!";
      $html.= "\n‼️전원 출석시 ".newpoint표시($설정['누적출석냥'])."{$단위} 전체 지급";
      $html = trim($html);
      echo 전송($html);
      exit;

    }else{
      $오늘지급여부 = db_select("SELECT COUNT(*) AS cnt FROM tb_point_log WHERE status = CONCAT('전체출석|', CURDATE())");
      if($오늘지급여부['cnt']==0){
        $sql = "SELECT m.*
        FROM tb_member AS m
        LEFT JOIN tb_attendance AS a
          ON m.name = a.nickname
          AND a.regdate = CURDATE()
        WHERE a.nickname IS not NULL and m.status = 0";

        $result = db_query($sql);

        while ($row = db_fetch($result)) {
          지급로그('전체출석', $row['name'], '', 0, $설정['누적출석냥']);
        }

        지급로그('전체출석|'.$오늘, "전체", '', 0, $설정['누적출석냥']);
        db_query("update tb_member set newpoint = newpoint + {$설정['누적출석냥']}");

        $html= "\n\n전원 출석🎉 ".newpoint표시($설정['누적출석냥'])."냥 지급완료!";

        db_query("update config set 지급확인 = 1, 누적출석{$단위} = 0 LIMIT 1");

        $설정 = db_select("select * from config ");
        $html.= "\n누적 출석냥 초기화됨 · 이후 일출석 시부터 다시 쌓입니다";

        $html = trim($html);
        echo 전송($html);
        exit;
      }else{
        $기본출석냥 = $설정['누적출석냥'] - 5000;
        echo 전송("{$오늘} 전체출석냥 지급완료🎉");
        exit;
      }
    }

  }else if($status==".인원"){
    $sql = "SELECT * FROM tb_member where name != '지유' and name != '민호' ORDER BY RAND(); ";
    $result = db_query($sql);

    $names1 = [];
    $names2 = [];
    $names3 = [];
    while ($row = db_fetch($result)) {
      if ($row['gender'] == 1 and $row['couple']!=2) {
        $names1[] = $row['name'];
      } elseif ($row['gender'] == 2 and $row['couple']!=2) {
        $names2[] = $row['name'];
      }else{
        $names3[] = $row['name'];
      }
    }

    $cnt1 = count($names1);
    $cnt2 = count($names2);
    $cnt3 = count($names3);
    $총인원 = $cnt1 + $cnt2 + $cnt3;

    $html1 = "🙆‍♂️ ({$cnt1}명)<br>";
    $html2 = "🙆‍♀️ ({$cnt2}명)<br>";
    $html3 = "🐥 ({$cnt3}명)<br>";

    if (!empty($names1)) {
      $html1 .= implode(' ', $names1) . '<br>';
    }

    if (!empty($names2)) {
      $html2 .= implode(' ', $names2) . '<br>';
    }

    if (!empty($names3)) {
      $html3 .= implode(' ', $names3) . '<br>';
    }

    $html = "📊 총 {$총인원}명\n\n".$html1."\n".$html2."\n".$html3;

    echo 전송($html);
    exit;


  }else if($status==".버프"){
    require_once __DIR__ . '/buff.inc.php';
    버프_명령_처리($status, $두자리닉넴, (array)($관리자 ?? []));

  ////////
}else if (strpos($status, '.양도내역') === 0) {
  // .양도내역 닉 — 해당 닉이 남에게 양도한 내역 전부(날짜 포함) 출력
  if (preg_match('/^\.양도내역\s+([^\s]+)(?:\s+([^\s]+))?\s*$/u', trim($status), $m)) {
    // 예)
    // - .양도내역 로라
    // - .양도내역 로라 도하
    $조회닉_원문 = trim($m[1]);
    $받는닉_필터 = isset($m[2]) ? trim($m[2]) : '';

    $조회닉_리스트 = array_values(array_filter(explode('|', $조회닉_원문)));
    if (empty($조회닉_리스트)) {
      echo 전송("❌ 닉네임을 확인해주세요.");
      exit;
    }

    $receiverCondition = '';
    if ($받는닉_필터 !== '') {
      $받는닉_2자 = getTwoCharNick($받는닉_필터);
      $받는닉_값 = ($받는닉_2자 !== '') ? $받는닉_2자 : $받는닉_필터;
      $받는닉_값_esc = addslashes($받는닉_값);
      $receiverCondition = " AND receiver = '{$받는닉_값_esc}' ";
    }

    $msg = '';
    $grandTotal = 0; // 조회한 닉들이 보낸 양(수수료+실수령) 합
    $totalCnt = 0;

    foreach ($조회닉_리스트 as $조회닉) {
      $조회닉 = trim($조회닉);
      if ($조회닉 === '') continue;

      $조회닉_2자 = getTwoCharNick($조회닉);
      if ($조회닉_2자 === '') {
        continue;
      }
      $조회닉_2자_esc = addslashes($조회닉_2자);

      $rows = db_query("
        SELECT regdate, receiver, tax, point
        FROM tb_point_log
        WHERE nick = '{$조회닉_2자_esc}'
          AND status = '양도'
          {$receiverCondition}
        ORDER BY regdate DESC
      ");

      $cnt = 0;
      $header = "🧾 {$조회닉_2자} 양도내역\n\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";

      if ($받는닉_필터 !== '') {
        $header .= " (→ {$받는닉_필터})";
      }
      $msg .= $header . "\n\n";

      while ($r = db_fetch($rows)) {
        $cnt++;
        $totalCnt++;
        $날짜 = date('Y-m-d H:i', strtotime($r['regdate']));
        $받는이 = (string)($r['receiver'] ?? '');
        $수수료 = (int)($r['tax'] ?? 0);
        $실제받는양 = (int)($r['point'] ?? 0);
        $양도금액 = $수수료 + $실제받는양; // 로그에 저장된 값으로 원금(보낸양) 복원
        $grandTotal += $양도금액;
        $msg .= "{$날짜}  {$조회닉_2자} → {$받는이} : {$양도금액}냥 (실수령 {$실제받는양}냥, 수수료 {$수수료}냥)\n";
      }

      if ($cnt === 0) {
        $msg .= "❌ 해당 내역이 없습니다.\n";
      }
      $msg .= "\n";
    }

    $msg = trim($msg);
    if ($totalCnt === 0) {
      echo 전송("❌ 내역이 없습니다.");
      exit;
    }

    $msg .= "\n총 보낸 냥: " . number_format((int)$grandTotal);
    echo 전송($msg);
    exit;
  }

  echo 전송("❌ 사용법: .양도내역 (닉네임) [받는닉]\n예) .양도내역 로라\n예) .양도내역 로라 도하");
  exit;

  }else if (strpos($status, '.양도') !== false) {

  $보낼양 = 50;
  $수수료 = 양도수수료계산((int)$정보['level'], $보낼양);
  $fail = "✅ {$단위} 양도\n\n.양도 받을닉 양도할 {$단위}수량";
  $fail.= "\n양도금액 기준 수수료 (금고 적립)";
  $fail.= 양도수수료_안내문();
  $fail.= "\n\n-유의사항";
  $fail.= "\n본방냥·게임냥 합쳐 하루 1회 무료";
  $fail.= "\n지호 적용 시 남은 시간(1시간)마다 추가 양도 가능 (추가 1회당 지호 -1시간)";
  $fail.= "\n현재 예상 수수료 {$수수료}{$단위} (50{$단위} 전송 시 수령 ".(50 - $수수료)."{$단위})";


  // $sql = "
  //     SELECT *
  //     FROM tb_point_log
  //     WHERE nick = '{$두자리닉넴}'
  //       AND date_format(regdate, '%Y-%m-%d') = '{$오늘}' and (status = '양도' || status = '수수료')
  //     ORDER BY regdate DESC";
  //
  // $result = db_query($sql);
  //
  // $fail .= "\n\n✅ 양도 내역({$오늘})\n\n";
  //
  // while ($row = db_fetch($result)) {
  //     $fail .= $row['status']." ".$row['receiver']." ".$row['point']."{$단위} ".date("H:i", strtotime($row['regdate']))."\n";
  // }

    if (preg_match('/\.양도\s*(.+)/u', $status, $match)) {
        $after = trim($match[1]); // ".양도" 뒤 텍스트 추출

        // "도하 5" 형태인지 검사 + 그룹 분리
        if (preg_match('/^([가-힣A-Za-z]+)\s*(\d+)$/u', $after, $parts)) {
          //$수수료 = $계급['tax'];
          $받는이 = $parts[1]; // 도하
          $보내는양  = (int)$parts[2];

          $양도제한 = function_exists('양도_일일제한_검사') ? 양도_일일제한_검사($두자리닉넴) : null;
          if ($양도제한 !== null) {
            echo 전송($양도제한);
            exit;
          }

          $지호소비 = function_exists('양도_지호추가양도_해당') && 양도_지호추가양도_해당($두자리닉넴);
          $수수료 = 양도수수료계산((int)$정보['level'], $보내는양);

          $실제받는양 = $parts[2];

          $sql = "select * from tb_member where name = '{$받는이}' ";
          $받는친구 = db_select($sql);
          if(!$받는친구['idx']){
            echo 전송("[ {$받는이} ] 친구는 없음");
            exit;
          }

          if($두자리닉넴==$받는이){
            echo 전송("본인에게 보낼 수 없음");
            exit;
          }

          $보유냥 = newpoint양도가능($정보['newpoint'] ?? 0);
          if($보유냥 <= 0){
            $msg = $호칭." {$두자리닉넴} 양도 가능 {$단위} 없음 (보유 ".newpoint표시($정보['newpoint'] ?? 0)."{$단위})";
            echo 전송($msg);
            exit;
          }

          $수수료제외 = $보내는양 - $수수료;
          if($수수료제외 < 1){
            echo 전송("양도 금액이 너무 적습니다.");
            exit;
          }
          if($보유냥 < $보내는양){
            $msg = "✅ 보유 {$단위} 부족\n";
            $msg.= $호칭." {$두자리닉넴}\n보유{$단위} : ".newpoint표시($정보['newpoint'] ?? 0)."{$단위}\n양도 요청 : ".number_format($보내는양) . "{$단위}";
            echo 전송($msg);
            exit;
          }else{
            db_query("update tb_member set newpoint = newpoint - {$보내는양} where name = '{$두자리닉넴}' ");
            db_query("update tb_member set newpoint = newpoint + {$수수료제외} where name = '{$받는이}' ");
            db_query("UPDATE config SET tax = tax + {$수수료} ");
            $지호차감문구 = ($지호소비 && function_exists('양도_지호_소비')) ? 양도_지호_소비($두자리닉넴) : '';
            지급로그('양도', $두자리닉넴, $받는이, $수수료, $실제받는양);
            지급로그('수수료', $두자리닉넴, '', $수수료, $수수료);
            $msg = "💰 {$두자리닉넴}→{$받는이} ".냥축약표시($수수료제외)." (수수료 ".냥축약표시($수수료).")";
            if ($지호차감문구 !== '') {
              $msg .= "\n" . $지호차감문구;
            }
            echo 전송($msg);
            exit;
          }

        } else {
          echo 전송($fail);
          exit;
        }
    } else {
        echo 전송($fail);
        exit;
    }
  
  }else if (preg_match('/^\.아이템\s*사용방법\s*$/u', trim($status))) {
    $msg = "아이템 사용방법\n";
    $msg .= "

【닉·색 변경】
• .닉변 현재닉 변경닉
• .색변 본인닉 번호

【지목】 지목 1개 차감
• .지목 대상1 대상2

【강일】
• .강일 대상닉 — 강일 1개 (본인+대상)
• .지목강일 대상1 대상2 — 강일 10개
• .강일취소 — 진행 중 당사자만
• .강일종료 당사자닉 — 해당 닉 강일 종료 (당사자 전원 .기록 · 연금 없음)
• .강일연장 당사자닉 — 강일 +12시간 연장

【제한】 3시간
• .채팅제한 닉네임
• .보룸제한 닉네임
• .게임제한 닉네임

【익명제한】 3시간 (본방 익명 공지)
• .익명채팅제한 닉네임
• .익명보룸제한 닉네임
• .익명게임제한 닉네임
";
        echo 전송($msg);
        exit;
  }else if (strpos($status, '.아이템') === 0) {
    // 명령어 파싱
    $parts = explode(' ', trim($status), 2);
    $옵션 = isset($parts[1]) ? trim($parts[1]) : '';
    // ======================
    // 아이템 설명 정의
    // ======================
    $아이템설명 = [];

    $아이템설명['제한'] = "👹제한
# 지정인에게 아래의 사항 중 한가지 적용
• 자숙 6시간
• 보이스룸 이용제한 3시간
• 채팅 금지(이모티콘 포함) 3시간
 (게임이용 제한 포함)";

    $아이템설명['수호'] = "👼 수호
# 지정인에게 아래의 사항 중 한가지를 적용
• 일방/공커 자숙 1일 단축 (받는 사람 기준 하루 1회)
• 보이스룸 이용제한 3시간 단축
• 채팅금지(이모티콘 포함) 3시간 단축
• 게임 이용제한 3시간 단축
(보룸/채팅/게임 제한은 하루 횟수 제한 없음)";

  $아이템설명['선물'] = "🎁 선물
# 보유중인 아이템 1개를 지정인에게 선물할 수 있다.";

  $아이템설명['닉변'] = "🪪 닉네임 변경 (지속)
# 중복되지 않는 닉네임을 1회 변경할 수 있다.";

  $아이템설명['강일'] = "💌 강제 일방(12시간)
# 아래의 조건의 일대일 채팅방을 상대방 동의 없이
강제로 개설할 수 있다.(공커, 일방에게는 불가)
• 보룸금지(모니터링이 되지 않는 이슈)
• 참여자 중 1인이라도 종료 시 강제 일방 종료
• 방장 참관조건 단, 문제가 없을 경우 모니터링 ❌
• 10개 사용 시 A, B를 지명해서 사용 가능
강일 이후 12시간 추가 강일,일방신청 제한";

  $아이템설명['프변'] = "🖍 프로필 변경
# 아래의 사항 중 한가지를 적용할 수 있다.
• 프로필 내 이모티콘 등록 3일
• 프로필 내 텍스트 등록 3일
• 닉네임 뒤 이모티콘 또는 텍스트 등록 3일";

  $아이템설명['색변'] = "📝 색 등본
# 지정한 프로필 색으로 1회 변경 및 방어가 가능하다.";

  $아이템설명['교환'] = "♻ 교환
# 본인이 소유한 지정 아이템 1개를 1회
  다른 아이템으로 교환할 수 있다.";

  $아이템설명['지목'] = "🫵 지목 (6시간)
# 1:1 공창 자기야 꽁냥
# 상황실: .지목 (보내는닉) (대상1) (대상2) 예) .지목 소이 하윤 돌풍
# 본인 포함 특정인(단, 이성끼리) 2명 지목
# 공커 및 일방, 강제일방 인원으로 선택 시 지목정지 되고 아이템 소멸
# 지목인들끼리 원하지 않을 경우 효과상실 (재사용불가)
# 지목이벤트는 진행되나 활동여부는 피지목자들의 몫
# 지목이벤트 중 일방신청으로 인해 일방진행시 지목이벤트 종료";

  $아이템설명['지호'] = "💦 지호 (3시간)
# 아래의 사항 중 한가지를 적용할 수 있다.
• 냥이벤 효과(타수2타, 냥지급2배)
• 오늘 양도 1회 무료 · 지호 1시간마다 추가 양도 1회 (추가 시 지호 -1시간)
  아이템 판매,급처 수수료 15%";

    // 👉 여기서 다른 아이템도 계속 추가 가능
    // $아이템설명['선물'] = "...";

    // ======================
    // 출력 분기
    // ======================
    if ($옵션 && isset($아이템설명[$옵션])) {

        // 🔹 특정 아이템만
        $msg = "🔫 아이템 상세설명 🔫\n\n" . $아이템설명[$옵션];

    } else if ($옵션) {

        // 🔹 없는 아이템
        $msg = "❌ '{$옵션}' 아이템은 존재하지 않습니다.";

    } else {

        // 🔹 전체 아이템
        $msg = "🔫 아이템 상세설명 🔫\n\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
        $msg.= implode("\n\n", $아이템설명);
        $msg.= "\n\n- 보유아이템 조회 명령어 : .가방

🚫 주의 사항
• [선물]를 사용하여 선물하는것 외 템 양도 불가
• 제한-채금 이행안할시 말풍선 1개당 3분씩 추가";
    }



    echo 전송($msg);
    exit;

  }else if(strpos($status, '.궁금') !== false){

    if (preg_match('/\.궁금\s*(.*)/u', $status, $match)) {
        $after = trim($match[1]); // ".궁금" 뒤 텍스트 추출 (없으면 본인 조회)

        // 빈칸이면 본인 닉네임, 아니면 한 단어 닉네임만 허용
        if ($after === '') {
          $닉네임 = $두자리닉넴;
        } elseif (preg_match('/^([가-힣A-Za-z]+)$/u', $after, $parts)) {
          $닉네임 = $parts[1];
        } else {
          $닉네임 = null;
        }

        if ($닉네임 !== null) {

          $sql = "select * from tb_member where name = '{$닉네임}' ";
          $받는친구 = db_select($sql);
          if(!$받는친구['idx']){
            echo 전송($닉네임." 없음.");
            exit;
          }

          $계급 = 계급($받는친구['point']);
          if($받는친구['title']){
            $호칭 = $받는친구['title'];
          }else{
            $호칭 = $계급['name'];
          }
          
          $msg = "Lv {$받는친구['level']} {$호칭} {$받는친구['name']}\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               ";

          // 본인 .궁금일 때만 오늘 tb_lotto_info 일일 타수·보상(100/500·계급, 1000·2000·3000 로우 보상) 내역

            $sql = "select 
            " . 생타_SQL_select_expr('msg') . " as cnt2,
            sum(tasu) as cnt 
            from tb_msg 
            where nickname = '{$닉네임}' AND 
            tasu != 0 AND
            regdate >= CURDATE() AND 
            regdate < CURDATE() + INTERVAL 1 DAY ";
            $타수 = db_select($sql);
  
            $msg.= "\n타수 : {$타수['cnt2']}/{$타수['cnt']}타";
            $msg.= "\n보유 : ".newpoint표시($받는친구['newpoint'] ?? 0)."냥";
            $msg.= "\n게임냥 : ".number_format((int)($받는친구['point'] ?? 0));
            $내무기 = trim($받는친구['item'] ?? '');
            $내강 = (int)($받는친구['enhance'] ?? 0);
            $오강이상 = ($내무기 !== '' && $내강 >= 5);
            $스타일 = trim($받는친구['style'] ?? '');
            if ($내무기) {
              $msg .= "\n무기 : +{$내강} {$스타일} {$내무기}";
            }

            $msg.= "\n\n".$받는친구['content']."\n";
            $joined = new DateTime($받는친구['regdate']);  // 가입일
            $now = new DateTime();                         // 현재
            $diff = $now->getTimestamp() - $joined->getTimestamp();
            $days = floor($diff / 86400);           // 하루 = 86400초
            $hours = floor(($diff % 86400) / 3600); // 남은 시간 계산
        
            if ($days > 0) {
                // 1일 이상
                $result = "{$days}일 {$hours}시간째";
            } else {
                // 1일 미만이면 시간만 표시
                $result = "{$hours}시간째";
            }
            $msg.= "\n입장 : {$result}";
            
            // 오늘 일자 기준 수입/지출 로그 (타수냥, 주사위, 일보상, 출석)
            $조회일자 = date('Y-m-d');

            $마지막카톡 = db_select("select msg, regdate from tb_msg where nickname = '{$닉네임}' order by regdate desc limit 1 ");
            $last = $마지막카톡['regdate']; // 예: "2025-11-18 14:20:00"

            // DateTime 객체로 변환
            $lastTime = new DateTime($last);
            $now = new DateTime();
            // 시간 차이 계산
            $diff = $now->diff($lastTime);
            // 시간 + 분 계산
            $totalHours = ($diff->days * 24) + $diff->h; // 총 몇 시간
            $minutes = $diff->i; // 남은 분

            $msg.= "\n💬 마지막 메시지"."\n{$마지막카톡['msg']}\n({$totalHours}시간 {$minutes}분 전)\n";

            $대상_esc = addslashes($닉네임);
            $조회자_esc = addslashes($두자리닉넴);

            db_query("insert into tb_curious set nickname = '{$조회자_esc}', youname = '{$대상_esc}' ");

            $닉_sql = addslashes($닉네임);
            $오늘보상일 = date('Y-m-d');
            $보상_rs = db_query("
                SELECT msg, regdate
                FROM tb_lotto_info
                WHERE item = '{$닉_sql}'
                  AND DATE(regdate) = '{$오늘보상일}'
                  AND (
                    (msg LIKE '%100타 달성%' AND msg LIKE '%출석미션%')
                    OR msg LIKE '%500타 달성%'
                    OR msg LIKE '%랜덤박스 30개 지급%'
                    OR msg LIKE '%랜덤박스 60개 지급%'
                    OR msg LIKE '%랜덤박스 100개 지급%'
                  )
                ORDER BY
                  CASE
                    WHEN msg LIKE '%출석미션%' AND msg LIKE '%100타 달성%' THEN 1
                    WHEN msg LIKE '%500타 달성%' THEN 2
                    WHEN msg LIKE '%랜덤박스 30개 지급%' THEN 3
                    WHEN msg LIKE '%랜덤박스 60개 지급%' THEN 4
                    WHEN msg LIKE '%랜덤박스 100개 지급%' THEN 5
                    ELSE 99
                  END,
                  regdate ASC
            ");
            if ($보상_rs && mysqli_num_rows($보상_rs) > 0) {
              $msg .= "\n📋 오늘 타수·보상 내역\n";
              while ($br = db_fetch($보상_rs)) {
                if (!empty($br['msg'])) {
                  $msg .= '· ' . trim($br['msg']) . "\n";
                }
              }
            }

            

          echo 전송($msg);
          exit;
        }
    }
    echo 전송(".궁금 친구닉네임");
    exit;

  }else if(strpos($status, '.생타') !== false){
    require_once __DIR__ . '/game/saengta.inc.php';
    생타_명령_처리($status, $두자리닉넴, $관리자);

  }else if (strpos($status, '.색변') !== false) {

    if (preg_match('/^\.색변\s+(\S+)\s+(\d+)/u', trim($status), $match)) {
        $닉네임 = trim($match[1]);
        $num값 = (int)$match[2];
        if (empty($닉네임) || $num값 < 1) {
            echo 전송("❌ .색변 닉네임 번호 형식으로 입력해줘!\n예) .색변 바보 40");
            exit;
        }

        // 색변 아이템 보유 여부 확인 (아이템명: 색변)
        $아이템 = 아이템보유유무($닉네임, "색변");
        if (empty($아이템['idx'])) {
            echo 전송("❌ [ {$닉네임} ] 색변 아이템이 없어요.");
            exit;
        }

        $기존 = db_select("select idx, num from tb_member where name = '{$닉네임}' ");
        if (empty($기존['idx'])) {
            echo 전송("❌ [ {$닉네임} ] 회원이 없어요.");
            exit;
        }
        // 번호 변경 및 커플 해제
        db_query("update tb_member set num = '{$num값}', couple = 0 where name = '{$닉네임}' ");
        // 색변 아이템 사용 처리
        db_query("update tb_member_item set status = 1, usedate = now() where idx = {$아이템['idx']} ");
        아이템사용_시세하락('색변', 1);

        echo 전송("✅ [ {$닉네임} ] 번호를 {$num값}(으)로 수정했어요.\n(색변 아이템 1개 사용)");
        exit;
    }
    echo 전송("❌ .색변 닉네임 번호 형식으로 입력해줘!\n예) .색변 바보 40");
    exit;

  }else if (strpos($status, '.입사') !== false) {
    exit;
    if (preg_match('/^\.입사\s+(\S+)\s+(\S+)\s+(.*)$/us', $status, $match)) {
        $닉네임 = $match[1];   // 도현
        $성별   = $match[2];   // 남
        $프로필 = trim($match[3]); // 🍭 닉네임•키 : 도현 180 ...

          if($성별=="남"){
            $gender = 1;
          }else{
            $gender = 2;
          }

          $높번 = db_select("select max(num) as nmax from tb_member");
          $다음번호 = $높번['nmax'] + 1;

          $sqls1 = "insert into tb_member set status = 0, code = '0', couple = 2, num = '{$다음번호}', gender = '{$gender}',
          name = '{$닉네임}', content = '{$프로필}', regdate = now() ";
          $result = db_query($sqls1);
          if($result){
            echo 전송($닉네임." 등록완료!");
            exit;
          }
      }

  }else if(strpos($status, '.금지공지') !== false){
    echo 전송("▪️ 금지항목

▫️공창 자삭 절대 금지!
▫️여미새•남미새•여왕벌•불편러
▫️공창 자삭•타인이 불편해하는 언행
▫️투방•바커 (적발시 킥)
▫️공창 및 보룸에서 개인정보 유출
▫️공커 졸업시 한 명만 놀러오는 경우");
    exit;
  }else if(strpos($status, '.자숙공지') !== false){
    echo 전송("▪️ 자숙

▫️보이스룸•얼공•채굴•소통방•게임 금지
▫️자숙기간동안 공창에서 어필•플러팅 금지
▫️자숙중인 상대에게도 플러팅 금지
▫️자숙중엔 공창 대화만 할 것...(셀자제외)
▫️일방 3일 / 공커 5일
▫️거절시 1일 셀프자숙 * 자숙과 동일하나
	보이스룸만 이용가능
▫️위 사항 적발시 3시간 간격으로 자숙기간 연장");
    exit;
  }else if(strpos($status, '.보룸공지') !== false){
    echo 전송("▪️ 보이스룸

▫️자유롭게 개설 및 이용가능
▫️3인 이상시 마이크 (1인•2인인경우 마이크 OFF)
▫️봇 제외 3인 마이크 ON 
▫️보룸에서만 나이 공개
▫️음주 후 보이스룸 이용 자제
▫️보룸에서 나눈 대화 공창유출금지
▫️일방•공커 상대와 있었던 일 공유금지
▫️연락처•카톡아이디 공유시 이유불문 킥");
    exit;

  }else if (preg_match('/^\.일방신청(?:\s+(\S+)(?:\s+(\S+))?)?$/u', trim($status), $일방신청매치)) {
    $신청닉 = $두자리닉넴;
    $상대닉 = '';
    $arg1 = trim((string)($일방신청매치[1] ?? ''));
    $arg2 = trim((string)($일방신청매치[2] ?? ''));
    if ($arg1 !== '') {
      if (in_array($두자리닉넴, $관리자, true)) {
        $신청닉 = getTwoCharNick($arg1);
        if ($arg2 !== '') {
          $상대닉 = getTwoCharNick($arg2);
        }
      } else {
        $상대닉 = getTwoCharNick($arg1);
      }
    }
    if ($신청닉 === '' || trim((string)($정보['name'] ?? '')) === '') {
      echo 전송('❌ 등록된 회원만 `.일방신청`을 사용할 수 있어요.');
      exit;
    }
    $신청회원 = ($신청닉 === $두자리닉넴)
      ? $정보
      : (function_exists('회원정보_조회') ? 회원정보_조회($신청닉) : []);
    if (trim((string)($신청회원['name'] ?? '')) === '') {
      echo 전송("❌ {$신청닉} 님을 찾을 수 없어요.");
      exit;
    }
    $점검 = 일방필수미션_점검($신청닉, (int)($신청회원['idx'] ?? 0));
    if (empty($점검['ok'])) {
      $msg = "❌ {$신청닉} 님 미완료 미션\n\n";
      foreach ($점검['missing'] as $row) {
        $msg .= '❌ ' . ($row['label'] ?? '') . "\n";
      }
      echo 전송(trim($msg));
      exit;
    }
    if (function_exists('일방본인인증_대기중') && 일방본인인증_대기중($신청닉, $신청회원)) {
      $msg = "✅ {$신청닉} 님 일방 필수 미션 전부 완료!\n\n";
      $msg .= "미션은 완료되었고 신청자 본인인지 확인이 필요해!\n\n";
      if ($상대닉 !== '') {
        $msg .= "`.본인인증 {$신청닉} {$상대닉}`";
      } else {
        $msg .= "`.본인인증 {$신청닉} 상대닉`";
      }
      echo 전송($msg);
      exit;
    }
    $midx = (int)($신청회원['idx'] ?? 0);
    $권보유 = function_exists('일방신청권_보유여부') && 일방신청권_보유여부($midx);
    if ($권보유) {
      echo 전송("일방성공시 성공임티\n일방실패시 실패임티\n공창에 띄워줄테니까\n잠시 건의방 나가있어줘!");
      exit;
    }
    $msg = "✅ {$신청닉} 님 일방 필수 미션 전부 완료!\n\n건의방에서 일방 신청해 주세요.";
    $msg .= "\n\n⚠️ 일방신청권 미보유 — 5일 400타 달성 또는 .구매·.선물로 확보";
    echo 전송($msg);
    exit;

  }else if (preg_match('/^\.일방신청권내역(?:\s+(\S+))?\s*$/u', trim($status), $일방신청권내역매치)) {
    // .일방신청권내역 / .일방신청권내역 닉 — tb_ilbang_ticket_log (조건·날짜)
    $조회닉 = $두자리닉넴;
    $arg닉 = trim((string)($일방신청권내역매치[1] ?? ''));
    if ($arg닉 !== '') {
      $조회닉 = getTwoCharNick($arg닉);
    }
    if ($조회닉 === '') {
      echo 전송("❌ 사용법: .일방신청권내역 또는 .일방신청권내역 (닉네임)\n예) .일방신청권내역 다인");
      exit;
    }
    $조회닉_esc = addslashes($조회닉);
    $회원행 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$조회닉_esc}' LIMIT 1");
    if (empty($회원행['idx'])) {
      echo 전송("❌ {$조회닉} 회원을 찾을 수 없어요.");
      exit;
    }
    $midx = (int)$회원행['idx'];
    $보유행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$midx} AND itemname = '일방신청권' AND status IN (0, 1)");
    $보유중 = (int)($보유행['cnt'] ?? 0);

    $줄들 = [];
    $rs = @db_query("
      SELECT reason_code, reason_text, from_nick, qty, regdate
      FROM tb_ilbang_ticket_log
      WHERE nick = '{$조회닉_esc}' OR midx = {$midx}
      ORDER BY regdate DESC, idx DESC
      LIMIT 30
    ");
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $지급시각 = trim((string)($row['regdate'] ?? ''));
        $지급표시 = ($지급시각 !== '' && $지급시각 !== '0000-00-00 00:00:00')
          ? date('Y-m-d H:i', strtotime($지급시각))
          : '-';
        $조건 = trim((string)($row['reason_text'] ?? ''));
        if ($조건 === '') {
          $조건 = trim((string)($row['reason_code'] ?? ''));
        }
        $qty = (int)($row['qty'] ?? 1);
        $수량문구 = ($qty > 1) ? " ×{$qty}" : '';
        $줄들[] = "· {$지급표시} — {$조건}{$수량문구}";
      }
    }

    // 로그 테이블 없거나 과거 건만 있을 때: 아이템 지급시각으로 보조 표시
    if (count($줄들) === 0) {
      $rs2 = db_query("
        SELECT regdate, status
        FROM tb_member_item
        WHERE midx = {$midx} AND itemname = '일방신청권'
        ORDER BY regdate DESC, idx DESC
        LIMIT 30
      ");
      if ($rs2) {
        while ($row = db_fetch($rs2)) {
          $지급시각 = trim((string)($row['regdate'] ?? ''));
          $지급표시 = ($지급시각 !== '' && $지급시각 !== '0000-00-00 00:00:00')
            ? date('Y-m-d H:i', strtotime($지급시각))
            : '-';
          $줄들[] = "· {$지급표시} — (구기록·조건 미상)";
        }
      }
    }

    if (count($줄들) === 0) {
      echo 전송("📋 {$조회닉} 일방신청권 내역\n\n지급 기록이 없어요.");
      exit;
    }
    $msg = "📋 {$조회닉} 일방신청권 내역\n";
    $msg .= "(최근 " . count($줄들) . "건 · 현재 보유 {$보유중}개)\n\n";
    $msg .= implode("\n", $줄들);
    echo 전송($msg);
    exit;

  }else if(stripos($status, '.일방성공') !== false){
    echo 전송("공창에 멘션 후\n[ 성공 ] 이모티콘을 띄워줄게!\n건의방으로 다시 오도록!");
    exit;
  }else if(stripos($status, '.일방실패') !== false){
    echo 전송("공창에 멘션 후\n[ 실패 ] 이모티콘을 띄워줄게!\n24시간 셀프자숙 하자!");
    exit;

  }else if(strpos($status, '.일방공지') !== false){
    echo 전송("▪️일방 및 공커

▫️건의방에 신청
▫️일방기간 : 7일 / 추가 연장 1회(7일) 가능
▫️일벙날짜가 결정되면 한 명이 상황실에 날짜와 대략적인 시간대 공지
① 일벙인증 : 두 사람 손하트 🫰🏻후 공창
② 공커인증 : 두 사람 손깍지 🤝🏻 후 공창");
    exit;

  }else if( strpos($status, '.진행') !== false || strpos($status, '.일방') !== false ){

    $msg = "✅ 일방/강일/지목\n\n"; // 누적 저장

    // 일방
    $카운트 = 0;
    $result1 = db_query("select * from tb_progress where status = '일방' order by enddate asc ");
    $일방개수 = mysqli_num_rows($result1);
    if ($일방개수 > 0) {
        $msg .= "❤️1:1대화중(일방)\n";
        while ($row = db_fetch($result1)) {
            $msg .= $row['nick']." ".date("m/d", strtotime($row['enddate']))."\n";
            $카운트++;
        }
        $msg .= "\n";
    }

    if($일방개수 >= 5){
      $msg .= "🔥일방마감\n\n";
    }

    // 강일
    $result2 = db_query("select * from tb_progress where status = '강일' order by enddate asc ");
    $강일개수 = mysqli_num_rows($result2);
    if ($강일개수 > 0) {
        $msg .= "🤼‍♀️ 강제일방(12시간)\n";
        while ($row = db_fetch($result2)) {
            $msg .= $row['nick']." ".date("d일 H:i", strtotime($row['enddate']))."\n";
            $카운트++;
        }
        $msg .= "\n";
    }

    if($강일개수 >= 5){
      $msg .= "🔥강일마감\n\n";
    }

    // 지목
    $result3 = db_query("select * from tb_progress where status = '지목' order by enddate asc ");
    $지목개수 = mysqli_num_rows($result3);
    if ($지목개수 > 0) {
        $msg .= "👩‍❤️‍👨 지목(6시간 공창커플)\n";
        while ($row = db_fetch($result3)) {
            $msg .= $row['nick']." ".date("d일 H:i", strtotime($row['enddate']))."\n";
            $카운트++;
        }
        $msg .= "\n";
    }

    if(!$카운트){
      $msg .= "-없음";
    }

    echo 전송(trim($msg)); // 마지막 공백 제거
    exit;
}else if(strpos($status, '.공지') !== false){
  require_once __DIR__ . '/notice.inc.php';
  echo 전송(공지_안내_문구());
  exit;

  }else if (strpos($status, '.지호') !== false) {
    $사용개수 = 1;
    if (preg_match('/^\.지호(?:\s+(\d+))?/u', trim($status), $지호매치)) {
      if (isset($지호매치[1]) && $지호매치[1] !== '') {
        $사용개수 = (int)$지호매치[1];
      }
    }
    if ($사용개수 < 1) {
      echo 전송("❌ 사용 개수는 1개 이상만 가능합니다.");
      exit;
    }
    $전체메시지행 = db_select("SELECT COUNT(*) AS cnt FROM tb_msg");
    $전체행수 = (int)($전체메시지행['cnt'] ?? 0);
    if ($전체행수 < 100) {
      echo 전송("❌ 누적 타수 100 미만에서는 지호를 사용할 수 없습니다. (현재 {$전체행수}타)");
      exit;
    }

    $보유행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE nick = '{$두자리닉넴}' AND itemname = '지호' AND status = 0");
    $현재보유개수 = (int)($보유행['cnt'] ?? 0);
    if ($현재보유개수 < $사용개수) {
      echo 전송("지호 아이템 부족! (요청 {$사용개수}개 · 보유 {$현재보유개수}개)");
      exit;
    }

    $아이템목록 = [];
    $아이템rs = db_query("SELECT idx FROM tb_member_item WHERE nick = '{$두자리닉넴}' AND itemname = '지호' AND status = 0 ORDER BY idx ASC LIMIT {$사용개수}");
    while ($아이템rs && $r = db_fetch($아이템rs)) {
      $아이템목록[] = (int)$r['idx'];
    }
    if (count($아이템목록) < $사용개수) {
      echo 전송("지호 아이템 없음!");
      exit;
    }

    $아이템인절 = implode(',', $아이템목록);
    db_query("UPDATE tb_member_item SET status = 1 WHERE idx IN ({$아이템인절})");
    아이템사용_시세하락('지호', $사용개수);

    $추가시간 = (int)$사용개수;
    $사용중여부 = db_select("select * from tb_item_use where nickname = '{$두자리닉넴}' and item = '지호' ");
    if($사용중여부['idx']){
      $유효시간 = date("Y-m-d H:i", strtotime($사용중여부['enddate']." +{$추가시간} hours"));
      $sql = "update tb_item_use set enddate = '{$유효시간}' where idx = {$사용중여부['idx']} ";
      db_query($sql);
      $연장시간 = date("m-d H:i", strtotime($유효시간));
      $msg = "►{$두자리닉넴} 지호 {$사용개수}개 적용\n{$연장시간} 까지";
      echo 전송($msg);
      exit;
    }else{
      $유효시간 = date("Y-m-d H:i", strtotime("+{$추가시간} hours"));
      $sql = "insert into tb_item_use set nickname = '{$두자리닉넴}', item = '지호', enddate = '{$유효시간}', regdate = now() ";
      db_query($sql);
      $연장시간 = date("m-d H:i", strtotime($유효시간));
      $msg = "►{$두자리닉넴} 지호 {$사용개수}개 사용\n{$연장시간} 까지 ";
      echo 전송($msg);
      exit;
    }

  }else if(strpos($status, '.자숙추가') !== false){
      if (preg_match('/^\.자숙추가\s+(\S+)\s+(\d+)/u', trim($status), $match)) {
          $닉네임 = trim($match[1]);
          $닉_esc = addslashes($닉네임);
          $시간 = (int)$match[2];
          if ($시간 < 1) {
              echo 전송("❌ .자숙추가 닉네임 시간(숫자) 형식으로 입력해줘!\n예) .자숙추가 민호 5");
              exit;
          }
          $data = db_select("select idx, nick, enddate from tb_self where nick = '{$닉_esc}' limit 1 ");
          if (empty($data['idx'])) {
              $끝날 = date("Y-m-d H:i:s", strtotime("+{$시간} hours"));
              db_query("INSERT INTO tb_self SET status = '일방', nick = '{$닉_esc}', enddate = '{$끝날}', regdate = NOW()" . tb_self_일방공커_모금단가_sql('일방'));
              $끝날표시 = date("Y-m-d H:i", strtotime($끝날));
              echo 전송("✅ [ {$닉네임} ] 자숙 등록완료 (현재시간 기준 +{$시간}시간)\n만료: {$끝날표시}");
              exit;
          }
          db_query("UPDATE tb_self SET enddate = DATE_ADD(enddate, INTERVAL {$시간} HOUR) WHERE nick = '{$닉_esc}' ");
          $새끝 = db_select("select enddate from tb_self where nick = '{$닉_esc}' order by enddate desc limit 1 ");
          $끝날표시 = $새끝['enddate'] ? date("Y-m-d H:i", strtotime($새끝['enddate'])) : '-';
          echo 전송("✅ [ {$닉네임} ] 자숙 만료시간 +{$시간}시간 연장\n만료: {$끝날표시}");
          exit;
      }
      echo 전송("❌ .자숙추가 닉네임 시간(숫자) 형식으로 입력해줘!\n예) .자숙추가 민호 5");
      exit;

  }else if(strpos(trim($status), '.복구') === 0){
    if (!in_array($두자리닉넴, $관리자)) {
      echo 전송("❌ .복구는 관리자만 사용할 수 있어요.");
      exit;
    }
    // .복구 닉네임 무기 강화수  예) .복구 지호 활 9
    if (preg_match('/^\.복구\s+(\S+)\s+(활|단소|마법)\s+(\d+)$/u', trim($status), $match)) {
      $대상닉 = trim($match[1]);
      $무기명 = $match[2];
      $강화수 = (int)$match[3];
      $무기맵 = array('단소' => '🪈단소', '활' => '🏹활', '마법' => '🪄마법');
      if ($강화수 < 0 || $강화수 > 20) {
        echo 전송("❌ .복구 닉네임 무기 강화수 (강화는 0~15)\n예) .복구 지호 활 9");
        exit;
      }
      $대상_esc = addslashes($대상닉);
      $대상행 = db_select("SELECT idx, name, item, enhance FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
      if (empty($대상행['idx'])) {
        echo 전송("❌ [ {$대상닉} ] 회원을 찾을 수 없어요.");
        exit;
      }
      $무기값 = $무기맵[$무기명];
      $무기_esc = addslashes($무기값);
      db_query("UPDATE tb_member SET `item` = '{$무기_esc}', `enhance` = {$강화수} WHERE name = '{$대상_esc}'");
      echo 전송("✅ [ {$대상닉} ]에게 {$무기값} +{$강화수} 등록 완료!");
      exit;
    }
    echo 전송("❌ .복구 닉네임 무기 강화수 형식으로 입력해줘!\n예) .복구 지호 활 9\n무기: 활, 단소, 마법 / 강화: 0~15");
    exit;

  }else if(trim($status) === '.자동교환강화수호'){
    // 교환·지호 중 적은 개수만큼만 교환 → enhance_suho 증가 (지호 없으면 선물로 짝 차감)
    $midx = (int)($정보['idx'] ?? 0);
    if ($midx <= 0) {
      echo 전송("❌ 회원 정보를 찾을 수 없어요.");
      exit;
    }
    $교환행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$midx} AND itemname = '교환' AND status = 0");
    $지호행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$midx} AND itemname = '지호' AND status = 0");
    $선물행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$midx} AND itemname = '선물' AND status = 0");
    $교환개수 = (int)($교환행['cnt'] ?? 0);
    $지호개수 = (int)($지호행['cnt'] ?? 0);
    $선물개수 = (int)($선물행['cnt'] ?? 0);
    if ($교환개수 <= 0) {
      echo 전송("❌ 보유한 교환 아이템이 없어요.");
      exit;
    }
    if ($지호개수 > 0) {
      $짝아이템표시 = '지호';
      $적용개수 = min($교환개수, $지호개수);
      $짝쿼리 = "UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE midx = {$midx} AND itemname = '지호' AND status = 0 LIMIT {$적용개수}";
    } else {
      if ($선물개수 <= 0) {
        echo 전송("❌ 보유한 지호·선물 아이템이 없어요. (지호가 없을 때 선물로 대체돼요)");
        exit;
      }
      $짝아이템표시 = '선물';
      $적용개수 = min($교환개수, $선물개수);
      $짝쿼리 = "UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE midx = {$midx} AND itemname = '선물' AND status = 0 LIMIT {$적용개수}";
    }
    $시전자_esc = addslashes($두자리닉넴);
    db_query("UPDATE tb_member SET enhance_suho = IFNULL(enhance_suho, 0) + {$적용개수} WHERE name = '{$시전자_esc}'");
    db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE midx = {$midx} AND itemname = '교환' AND status = 0 LIMIT {$적용개수}");
    db_query($짝쿼리);
    아이템사용_시세하락('교환', $적용개수);
    아이템사용_시세하락($짝아이템표시, $적용개수);
    echo 전송("✅ 자동교환강화수호 완료!\n교환 {$적용개수}개 + {$짝아이템표시} {$적용개수}개 → 강화 수호 +{$적용개수}");
    exit;

  }else if(strpos($status, '.조회') !== false){

    if (preg_match('/\.조회\s+(\S+)\s+(.+)/u', $status, $match)) {
        $닉네임 = $match[1];
        $템명 = $match[2];

        $sql = "select count(*) as cnt from tb_member_item where nick = '{$닉네임}' and itemname = '{$템명}' and status = 0 ";
        $data = db_select($sql);
        if($data['cnt']==0){
          $msg = "{$닉네임} {$템명} 없음!";
          echo 전송($msg);
          exit;
        }else{
          $msg = "{$닉네임} {$템명} {$data['cnt']}개";
          echo 전송($msg);
          exit;
        }

    }

  }else if (preg_match('/^\.지갑(?:\s+(\S+))?\s*$/u', trim($status), $match)) {
    $지정닉 = !empty($match[1]) ? getTwoCharNick(trim($match[1])) : $두자리닉넴;
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
    $코드 = 회원_접속코드_발급($지정닉);
    $url = "http://49.247.160.164/page/wallet.php?code={$코드}";
    echo 전송("{$지정닉} 지갑 링크\n{$url}");
    exit;

  }else if (strpos($status, '.마켓배정') !== false) {
    if (preg_match('/^\.마켓배정\s*(.*)$/u', trim($status), $match)) {
      $인자 = isset($match[1]) ? trim($match[1]) : '';
      if ($인자 === '') {
        $지정닉 = $두자리닉넴;
      } else {
        $토큰 = preg_split('/[\s\p{Zs}]+/u', $인자, 2);
        $지정닉 = getTwoCharNick(trim($토큰[0]));
      }
      $지정닉_esc = addslashes($지정닉);
      $멤버 = db_select("SELECT idx, name, code FROM tb_member WHERE name = '{$지정닉_esc}' LIMIT 1");
      if (empty($멤버['idx'])) {
        echo 전송("'{$지정닉}' 회원이 없습니다.");
        exit;
      }
      $코드 = 회원_접속코드_발급($지정닉);
      $url = "http://49.247.160.164/shop/?code={$코드}";
      echo 전송("🎫 아영이네 마켓\n{$url}");
      exit;
    }

  }else if(strpos($status, '.생성') !== false){
    관리자_명령_차단($두자리닉넴, $nick ?? '');
    if (preg_match('/\.생성\s+(\S+)\s+(\S+)\s+(\d+)/u', $status, $match)) {
      $생성결과 = 아이템_생성_지급($match[1], $match[2], $match[3]);
      echo 전송($생성결과['msg']);
      exit;
    }
    echo 전송("❌ 사용법: .생성 닉네임 템명 개수\n예) .생성 진우 수호 5\n예) .생성 전체 1주년기념주화 1");
    exit;
  }else if(strpos($status, '.사용') !== false){

    if (in_array(getTwoCharNick($nick), $관리자)) {
      if (preg_match('/\.사용\s+(\S+)\s+(\S+)\s+(\d+)/u', $status, $match)) {
        $닉네임 = $match[1];
        $템명 = $match[2];
        $개수 = $match[3];

        // if($템명=="프변"){
        //   die(전송("프로필변경은 사이트에서 처리할것!"));
        // }

        $sql = "select count(*) as cnt from tb_member_item where nick = '{$닉네임}' and itemname = '{$템명}' and status = 0 ";
        $data = db_select($sql);
        if($data['cnt']==0){
          $msg = "{$닉네임} {$템명} 없음!";
          echo 전송($msg);
          exit;
        }else{
          if(!$개수){
            die(전송("개수를 입력할것"));
          }
          $sql = "select * from tb_member_item where nick = '{$닉네임}' and itemname = '{$템명}' and status = 0 limit {$개수} ";
          $tem_result = db_query($sql);
          for($i=0;$row=db_fetch($tem_result);$i++){
            $result = db_query("update tb_member_item set status = 1, usedate = now() where idx = {$row['idx']} ");
          }
          if($result){
            아이템사용_시세하락($템명, (int)$개수);
            die(전송("{$템명} 처리완료"));
          }
        }
      }
      die(전송(".사용 (닉네임) (2글자탬명) (처리개수)"));
    }
  }else if (strpos($status, '.알림') === 0) {
    // .알림 메시지 → tb_lotto_info.msg에 저장 (알림 큐로 전송됨)
    if (!in_array($두자리닉넴, $관리자)) {
      echo 전송("❌ 관리자만 사용할 수 있습니다.");
      exit;
    }
    $알림_입력 = trim(mb_substr($status, mb_strlen('.알림', 'UTF-8'), null, 'UTF-8'));
    if ($알림_입력 === '') {
      echo 전송("사용법: .알림 (전송할 메시지)\n예) .알림 테스트");
      exit;
    }
    $msg_esc = addslashes($알림_입력);
    $item_esc = addslashes($두자리닉넴);
    db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$msg_esc}', leverage = 0, item = '{$item_esc}', regdate = NOW()");
    echo 전송("✅ 알림이 등록되었습니다.");
    exit;
    
  }else if (strpos($status, '.구매') !== false) {
    if (function_exists('구매_사용완료아이템_삭제')) {
      구매_사용완료아이템_삭제();
    }
    // .구매 만 입력 시 — tb_item.percent 기준 총 게임냥 비율 시세
    $구매_trim = trim($status);
    if (preg_match('/^\.구매\s*$/u', $구매_trim)) {
      echo 전송(아이템_구매시세_목록_문구($단위));
      exit;
    }
  
    // .구매 아이템sname [수량] — 일반: tb_item.percent 기준, 공커대실권 등은 별도 (예: .구매 강일 3)
    if (!preg_match('/^\.구매\s+([^\s]+)(?:\s+(\d+))?$/u', $구매_trim, $m구매)) {
      echo 전송("❌ 사용법: .구매 아이템이름 [수량]\n예) .구매 강일\n예) .구매 강일 5\n시세만: .구매");
      exit;
    }
    $구매아이템명 = trim($m구매[1]);
    $구매수량 = isset($m구매[2]) ? (int)$m구매[2] : 1;
    if ($구매수량 < 1) {
      $구매수량 = 1;
    }
  
    $구매아이템_esc = addslashes($구매아이템명);
    $닉_esc = addslashes($두자리닉넴);
  
    $구매아이템행 = db_select("
      SELECT idx, sname, buy, percent, buystatus
      FROM tb_item
      WHERE sname = '{$구매아이템_esc}' AND buystatus = 0
      LIMIT 1
    ");
    if (empty($구매아이템행['idx'])) {
      echo 전송("❌ 구매할 수 없거나 없는 아이템이에요.");
      exit;
    }
  
    $커플 = null;
    if ($구매아이템명 === '공커대실권') {
      $커플 = db_select("select * from tb_couple where couple like '%{$두자리닉넴}%'");
      if (empty($커플['idx'])) {
        echo 전송("❌ 정보가 없어 공커대실권을 구매할 수 없습니다.");
        exit;
      }
      $커플_amount = (int)($커플['amount'] ?? 0);
      if ($커플_amount < 1) {
        echo 전송("❌ 가격이 설정되지 않아 공커대실권을 구매할 수 없습니다.");
        exit;
      }
    }
  
    if ($구매아이템명 === '공커대실권') {
      $구매단가 = (int)(ceil($커플_amount / 1000) * 1000);
      $총구매액 = $구매단가 * $구매수량;
    } elseif ($구매아이템명 === '일방신청권') {
      $구매단가 = 전체냥기준금액(0.01, true);
      $총구매액 = $구매단가 * $구매수량;
    } elseif ($구매아이템명 === '일방연장권') {
      $구매단가 = 냥_앞두자리_뒤0(전체냥기준금액(0.01, true) * 7);
      $총구매액 = $구매단가 * $구매수량;
    } else {
      $구매단가 = 아이템_구매단가_계산($구매아이템행);
      if ($구매단가 <= 0 && 아이템_percent_시세여부($구매아이템행)) {
        echo 전송("❌ 현재 총 게임냥 기준 시세가 1억 미만이라 {$구매아이템명}을(를) 구매할 수 없어요.");
        exit;
      }
      $총구매액 = $구매단가 * $구매수량;
    }
  
    $소량추가금 = 0;
    // 소량 구매(10개 미만) 시 구매 총액에 1% 추가
    if ($구매수량 < 10) {
      $소량추가금 = (int)ceil($총구매액 * 0.01);
      $총구매액 += $소량추가금;
    }

    $보유냥 = (int)($정보['point'] ?? 0);
    if ($보유냥 < $총구매액) {
      if ($구매아이템명 === '공커대실권') {
        $단가표시 = (int)(ceil($커플_amount / 1000) * 1000);
      } elseif ($구매아이템명 === '일방신청권') {
        $단가표시 = 전체냥기준금액(0.01, true);
      } elseif ($구매아이템명 === '일방연장권') {
        $단가표시 = 냥_앞두자리_뒤0(전체냥기준금액(0.01, true) * 7);
      } else {
        $단가표시 = 아이템_구매단가_계산($구매아이템행);
      }
      echo 전송(
        "‼️보유 {$단위} 부족!\n{$구매아이템명} : " . 구매가_축약표시($총구매액, $단위)
        . " (단가 " . 구매가_축약표시($단가표시, $단위) . " × {$구매수량})\n현재 보유 : " . 구매가_축약표시($보유냥, $단위)
      );
      exit;
    }
  
    $내idx = (int)($정보['idx'] ?? 0);
    if ($내idx <= 0) {
      echo 전송("❌ 회원 정보를 찾을 수 없어요.");
      exit;
    }
  
    if ($구매아이템명 === '지호') {
      $지호보유한도 = 100000;
      $지호보유행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$내idx} AND itemname = '지호' AND status = 0");
      $지호보유개수 = (int)($지호보유행['cnt'] ?? 0);
      if ($지호보유개수 + $구매수량 > $지호보유한도) {
        $더구매가능 = max(0, $지호보유한도 - $지호보유개수);
        echo 전송(
          "❌ 지호 아이템은 최대 " . number_format($지호보유한도) . "개까지만 보유할 수 있어요.\n"
          . "현재 보유: " . number_format($지호보유개수) . "개 · 이번 구매 {$구매수량}개는 불가 (추가로 최대 " . number_format($더구매가능) . "개까지 구매 가능)"
        );
        exit;
      }
    }
  
    $itemStatus = 0;
    if ($구매아이템명 === '공커대실권') {
      if (!empty($커플['idx']) && !empty($커플['edate'])) {
        $연장기간 = 7 * $구매수량;
        $칠일연장 = date("Y-m-d", strtotime($커플['edate'] . " +{$연장기간} days"));
        db_query("update tb_couple set edate = '{$칠일연장}' where idx = {$커플['idx']} ");
      }
      $itemStatus = 1;
    } elseif ($구매아이템명 === '일방신청권') {
      $itemStatus = 0;
    } elseif ($구매아이템명 === '일방연장권') {
      $itemStatus = 1;
      $progress = db_select("select * from tb_progress where nick like '%{$두자리닉넴}%' ");
      if (!empty($progress['idx'])) {
        $progress['enddate'] = date("Y-m-d", strtotime($progress['enddate'] . " +7 days"));
        $닉등록 = $progress['nick'] . "1️⃣";
        db_query("update tb_progress set enddate = '{$progress['enddate']}', nick = '{$닉등록}' where idx = {$progress['idx']} ");
      }
    }
  
    $지급sname = trim((string)$구매아이템행['sname']);
    $지급sname_esc = addslashes($지급sname);
    for ($gi = 0; $gi < $구매수량; $gi++) {
      db_query("
        INSERT INTO tb_member_item
        SET midx = {$내idx}, nick = '{$닉_esc}', status = {$itemStatus}, itemname = '{$지급sname_esc}', regdate = NOW()
      ");
    }
    if ($구매아이템명 === '일방신청권' && function_exists('일방신청권_지급기록')) {
      일방신청권_지급기록($두자리닉넴, '구매', [
        'midx' => $내idx,
        'reason_text' => '상점 구매',
        'qty' => $구매수량,
      ]);
    }
    db_query("UPDATE tb_member SET point = point - {$총구매액} WHERE idx = {$내idx}");
    if (function_exists('아이템구매_시세상승')) {
      아이템구매_시세상승($구매아이템명, $구매단가, $구매수량);
    }

    $수량표시 = ($구매수량 > 1) ? " {$구매수량}개" : '';
    $추가금문구 = ($소량추가금 > 0) ? "\n(10개 미만 1% 추가금: " . 구매가_축약표시($소량추가금, $단위) . ")" : '';
    echo 전송("►[{$두자리닉넴}] {$구매아이템명}{$수량표시} 구매 " . 구매가_축약표시($총구매액, $단위) . $추가금문구);
    exit;

  }else if(strpos($status, '.판매') !== false){
    $아이템명 = "";
    if (preg_match('/\.판매\s*(.+)/u', $status, $match)) {
        $after = trim($match[1]); // "수호 5" / "1주년기념주화 1" / "수호"
        if (preg_match('/^(.+?)\s+(\d+)$/u', $after, $parts)) {
            $아이템명 = trim($parts[1]);
            $수량 = max(1, (int)$parts[2]);
        } else {
            $아이템명 = $after;
            $수량 = 1;
        }
        if ($아이템명 !== '') {

            $보유확인sql = "select count(*) as cnt from tb_member_item where midx = {$정보['idx']} and itemname = '{$아이템명}' and status = 0 ";
            $보유개수 = db_select($보유확인sql); //보유템확인
            if($수량 > $보유개수['cnt']){
              echo 전송("{$아이템명} 보유수량 {$보유개수['cnt']}개");
              exit;
            }else{
              $msg1 = "✅ 아이템 판매\n\n";
              $sql = "SELECT
                          COALESCE(SUM(t.sell), 0) AS total_sell
                      FROM (
                          SELECT i.sell
                          FROM tb_member_item AS mi
                          JOIN tb_item AS i
                              ON mi.itemname = i.sname
                          WHERE mi.midx = {$정보['idx']}
                            AND mi.itemname = '{$아이템명}'
                            AND mi.status = 0
                          LIMIT {$수량}
                      ) AS t";
              $판매기준가 = db_select($sql);
     

              $item = db_select("select * from tb_item where sname = '{$아이템명}' ");
              if (empty($item['idx'])) {
                  echo 전송("❌ [ {$아이템명} ] 존재하지 않는 아이템이에요.");
                  exit;
              }

              $사용중여부 = db_select("select idx from tb_item_use where nickname = '{$두자리닉넴}' and item = '지호' ");
              if($사용중여부['idx']){ // 지호2 버프 사용시 수수료 15%
                $판매수수료율 = 15;
              }else{
                $판매수수료율 = $일반판매수수료율;
              }

              $userNyung = $정보['point'];
              // 개인 배율 계산
              $personalMultiplier = 가격계산($userNyung);
              $totalNyung = $전체포인트['total_point'];
              // 기준 냥 (이 값 기준으로 시장 배율 1.0)
              $baseMarketNyung = 10000000; // 1천만 기준
              // 시장 배율 계산 (완만한 로그 곡선)
              $marketMultiplier = log10(($totalNyung / $baseMarketNyung) + 1) * 0.1 + 1;

              // 시장 배율 안전장치
              $marketMultiplier = max(0.9, min($marketMultiplier, 1.2));

              // 수량 반영한 기본 가격 (판매가는 tb_item.sell 기준)
              $basePrice = (int)($판매기준가['total_sell'] ?? 0);
  
              if (!empty($item['status'])) {
                  // 고정 가격 아이템
                  $finalPrice = $basePrice;
              } else {
                  // 최종 가격 = 기본가 × 개인 배율 × 시장 배율
                  $finalPrice = round($basePrice * $marketMultiplier * (1 + ($personalMultiplier - 1) * 0.5));

                  // 안전장치 (최소 80% ~ 최대 150%)
                  $minPrice = $basePrice * 0.8;
                  $maxPrice = $basePrice * 1.5;
                  $finalPrice = max($minPrice, min($finalPrice, $maxPrice));
              }

              // 🔥 백 단위 올림 (뒤 두 자리 전부 올림)
              $판매금액 = ceil($finalPrice / 100) * 100;

              // 수수료 금액 (판매가 * 30% 반올림)
              $수수료 = round($판매금액 * ($판매수수료율 / 100));
              $수수료뺀금액 = $판매금액 - $수수료;

              // 1) 먼저 판매할 아이템 idx 조회 후 사용 처리 (냥 지급 전에 아이템 차감)
              $sql = "SELECT mi.idx
                  FROM tb_member_item AS mi
                  JOIN tb_item AS i ON mi.itemname = i.sname
                  WHERE mi.midx = {$정보['idx']}
                    AND mi.itemname = '{$아이템명}'
                    AND mi.status = 0
                  ORDER BY mi.idx ASC
                  LIMIT {$수량}";
              $사용처리 = db_query($sql);
              $판매할idx = array();
              while ($템 = db_fetch($사용처리)) {
                  $판매할idx[] = (int)$템['idx'];
              }
              if (count($판매할idx) !== $수량) {
                  echo 전송("❌ 판매 처리 중 오류 (보유 수량 불일치). 다시 시도해주세요.");
                  exit;
              }
              $idx목록 = implode(',', $판매할idx);
              db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx IN ({$idx목록})");

              // 2) 냥 지급 (type=newpoint → newpoint, 그 외 point)
              $지급컬럼 = 아이템판매_지급컬럼($item);
              $sql = "UPDATE tb_member SET {$지급컬럼} = {$지급컬럼} + {$수수료뺀금액} WHERE name = '{$정보['name']}'";
              db_query($sql);
              $보유 = db_select("SELECT {$지급컬럼} FROM tb_member WHERE name = '{$정보['name']}' LIMIT 1");

              $msg1.= "판매자 : {$호칭} {$정보['name']}\n";
              $msg1.= "판매 아이템 : {$아이템명}\n";
              $msg1.= "판매가 : ".아이템판매_금액표시($수수료뺀금액, $지급컬럼)."{$단위}\n";
              $msg1.= "수수료 : ".아이템판매_금액표시($수수료, $지급컬럼)."{$단위}\n";
              $msg1.= "보유{$단위} : ".아이템판매_금액표시($보유[$지급컬럼] ?? 0, $지급컬럼)."{$단위}";
              db_query("UPDATE config SET tax = tax + {$수수료} ");
              지급로그('판매', $두자리닉넴, $아이템명, $수수료, $수수료뺀금액);
              if (function_exists('아이템판매_시세하락')) {
                아이템판매_시세하락($아이템명, (int)($item['buy'] ?? 0), $수량);
              }
              echo 전송($msg1);
              exit;
            }
        }
    }

    $시세 = 아이템_상점판매_시세목록($정보);
    $msg = "✅ 아이템 판매\n\n";
    foreach ($시세['items'] as $row) {
      $msg .= "{$row['name']} : {$row['price_fmt']}{$row['unit']}<br>";
    }
    $msg.= "\n판매 수수료 {$일반판매수수료율}%";
    $msg.= "\n지호 2 적용시 15%";

    echo 전송($msg);
    exit;    


  }else if(strpos($status, '.구매') !== false){
    $msg = "";

    // 관리자가 ".구매 닉네임" 형태로 입력하면 해당 닉네임 기준 상점 정보 조회
    if (preg_match('/^\.구매\s+([^\s]+)\s*$/u', trim($status), $m_admin)) {
      $조회닉 = $m_admin[1];

      // 관리자만 사용 가능
      if (in_array($두자리닉넴, $관리자)) {
        $대상정보 = db_select("SELECT * FROM tb_member WHERE name = '{$조회닉}' ");
        if (empty($대상정보) || empty($대상정보['idx'])) {
          echo 전송("❌ {$조회닉} 회원 정보를 찾을 수 없습니다.");
          exit;
        }

        // 대상 유저 보유 냥
        $userNyung = (float)($대상정보['point'] ?? 0);
        // 개인 배율 계산
        $personalMultiplier = 가격계산($userNyung);
        // 전체 보유 냥 (친구들 합산)
        $totalNyung = (float)($전체포인트['total_point'] ?? 0);
        // 기준 냥 (이 값 기준으로 시장 배율 1.0)
        $baseMarketNyung = 10000000; // 1천만 기준
        // 시장 배율 계산 (완만한 로그 곡선)
        $marketMultiplier = log10(($totalNyung / $baseMarketNyung) + 1) * 0.1 + 1;

        // 시장 배율 안전장치
        $marketMultiplier = max(0.9, min($marketMultiplier, 1.2));

        // 아이템 목록 조회
        $sql = "SELECT * FROM tb_item WHERE buystatus = 0 ORDER BY sell DESC";
        $result = db_query($sql);

        $msg = "🛒 상점 (기준: {$조회닉})\n\n";

        // 아이템 반복 처리
        while ($item = db_fetch($result)) {
          $itemName  = $item['sname'];
          if ($itemName === '공커대실권') {
            continue;
          }
          $basePrice = (int)$item['buy'];

          if ($item['status']) {
            // 고정 가격 아이템
            $finalPrice = $basePrice;
          } else {
            // 최종 가격 = 기본가 × 개인 배율 × 시장 배율
            $finalPrice = round($basePrice * $marketMultiplier * (1 + ($personalMultiplier - 1) * 0.5));

            // 안전장치 (최소 80% ~ 최대 150%)
            $minPrice = $basePrice * 0.8;
            $maxPrice = $basePrice * 1.5;
            $finalPrice = max($minPrice, min($finalPrice, $maxPrice));
          }
          // 🔥 천 단위 올림
          $finalPrice = ceil($finalPrice / 1000) * 1000;
          // 출력
          $msg .= "{$itemName} : " . number_format($finalPrice) . "{$단위}\n";
        }

        echo 전송($msg);
        exit;
      }
      // 관리자가 아니면 아래 기존 .구매 로직으로 진행
    }

    if (preg_match('/^\.구매\s+([가-힣A-Za-z]+)(?:\s+(\d+))?/u', trim($status), $match)) {
        $아이템명 = $match[1];                 // 지호
        $수량 = isset($match[2]) ? (int)$match[2] : 1;
        if ($수량 < 1) $수량 = 1;

        $item = db_select("select * from tb_item where sname = '{$아이템명}' ");
        if($item['idx']){
          $커플 = null;
          if ($아이템명 === "공커대실권") {
            $커플 = db_select("select * from tb_couple where couple like '%{$두자리닉넴}%'");
            if (empty($커플['idx'])) {
              echo 전송("❌ {$두자리닉넴} 닉네임이 포함된 정보가 없어 공커대실권을 구매할 수 없습니다.");
              exit;
            }
            $커플_amount = (int)($커플['amount'] ?? 0);
            if ($커플_amount < 1) {
              echo 전송("❌ 가격이 설정되지 않아 공커대실권을 구매할 수 없습니다.");
              exit;
            }
          }

          // 유저 보유 냥
          $userNyung = (float)($정보['point'] ?? 0);

          if ($아이템명 === "공커대실권") {
            $finalPrice = $커플_amount;
          } else {
            // 개인 배율 계산
            $personalMultiplier = 가격계산($userNyung);
            // 전체 보유 냥 (친구들 합산) - 숫자 보정으로 시장 배율 반영 보장
            $totalNyung = (float)($전체포인트['total_point'] ?? 0);
            // 기준 냥 (이 값 기준으로 시장 배율 1.0)
            $baseMarketNyung = 10000000; // 1천만 기준
            // 시장 배율 계산 (완만한 로그 곡선)
            $marketMultiplier = log10(($totalNyung / $baseMarketNyung) + 1) * 0.1 + 1;

            // 시장 배율 안전장치
            $marketMultiplier = max(0.9, min($marketMultiplier, 1.2));

            // 수량 반영한 기본 가격
            $basePrice = (int)$item['buy'];

            if ($item['status']) {
                // 고정 가격 아이템
                $finalPrice = $basePrice;
            } else {
                // 최종 가격 = 기본가 × 개인 배율 × 시장 배율
                $finalPrice = round($basePrice * $marketMultiplier * (1 + ($personalMultiplier - 1) * 0.5));

                // 안전장치 (최소 80% ~ 최대 150%)
                $minPrice = $basePrice * 0.8;
                $maxPrice = $basePrice * 1.5;
                $finalPrice = max($minPrice, min($finalPrice, $maxPrice));
            }
          }
          // 🔥 백 단위 올림 (뒤 두 자리 전부 올림)
          $합산가격 = ceil($finalPrice / 1000) * 1000;
          $최종가격 = $합산가격 * $수량;
          $소량추가금 = 0;
          // 소량 구매(10개 미만) 시 구매 총액에 1% 추가
          if ($수량 < 10) {
            $소량추가금 = (int)ceil($최종가격 * 0.01);
            $최종가격 += $소량추가금;
          }

          $구매후 = $userNyung - $최종가격;
          if($userNyung < $최종가격){
            $msg.= "‼️보유 {$단위} 부족!\n{$item['sname']} : ".number_format($최종가격) . "{$단위}\n현재 보유 {$단위} : ".number_format($userNyung) . "{$단위}";
            echo 전송($msg);
            exit;
          }

          if($아이템명=="공커대실권"){
            if($커플['idx']){
              if($커플['edate']){
                $연장기간 = 7 * $수량;
                $칠일연장 = date("Y-m-d", strtotime($커플['edate']." +{$연장기간} days"));
                db_query("update tb_couple set edate = '{$칠일연장}' where idx = {$커플['idx']} ");
              }
            }
            $status = " status = 1, ";
          }else if($아이템명=="일방신청권"){
            $status = " status = 1, ";

          }else if($아이템명=="일방연장권"){
            $status = " status = 1, ";
            
            $progress = db_select("select * from tb_progress where nick like '%{$두자리닉넴}%' ");
            if($progress['idx']){
              $progress['enddate'] = date("Y-m-d", strtotime($progress['enddate']." +7 days"));
              $닉등록 = $progress['nick']."1️⃣";
              db_query("update tb_progress set enddate = '{$progress['enddate']}', nick = '{$닉등록}' where idx = {$progress['idx']} ");
            }

          }else{
            $status = " status = 0, ";
          }

          for ($i = 0; $i < $수량; $i++) {
               db_query("
                   insert into tb_member_item
                   set
                       midx = {$정보['idx']},
                       nick = '{$두자리닉넴}',
                       {$status}
                       itemname = '{$item['sname']}',
                       regdate = now()
               ");
           }
          if ($아이템명 === '일방신청권' && function_exists('일방신청권_지급기록')) {
            일방신청권_지급기록($두자리닉넴, '구매', [
              'midx' => (int)$정보['idx'],
              'reason_text' => '상점 구매',
              'qty' => (int)$수량,
            ]);
          }

          $result = db_query("update tb_member set point = {$구매후} where name = '{$두자리닉넴}' ");

          if($result){
            $추가금문구 = ($소량추가금 > 0) ? "\n(10개 미만 1% 추가금: " . number_format($소량추가금) . "냥)" : '';
            $msg .= "►[".$두자리닉넴."] {$item['sname']} {$수량}개 구매 ".number_format($최종가격) . "냥".$추가금문구;
            echo 전송($msg);
            exit;
          }

        }else{
          $msg = "존재하지 않는 아이템";
          echo 전송($msg);
          exit;
        }
    }

      // 유저 보유 냥
      $userNyung = (float)($정보['point'] ?? 0);
      // 개인 배율 계산
      $personalMultiplier = 가격계산($userNyung);
      // 전체 보유 냥 (친구들 합산)
      $totalNyung = (float)($전체포인트['total_point'] ?? 0);
      // 기준 냥 (이 값 기준으로 시장 배율 1.0)
      $baseMarketNyung = 10000000; // 1천만 기준
      // 시장 배율 계산 (완만한 로그 곡선)
      $marketMultiplier = log10(($totalNyung / $baseMarketNyung) + 1) * 0.1 + 1;

      // 시장 배율 안전장치
      $marketMultiplier = max(0.9, min($marketMultiplier, 1.2));

      //////////////////////////////////////////////////////
      // 아이템 목록 조회
      //////////////////////////////////////////////////////

      $sql = "SELECT * FROM tb_item WHERE buystatus = 0 ORDER BY sort asc";
      $result = db_query($sql);

      $msg = "🛒 상점\n\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               ";


      // 아이템 반복 처리
      while ($item = db_fetch($result)) {

          $itemId    = (int)$item['idx'];
          $itemName  = $item['sname'];
          $basePrice = (int)$item['buy'];

          if ($item['status']) {
              // 고정 가격 아이템
              $finalPrice = $basePrice;
          } else {
              // 최종 가격 = 기본가 × 개인 배율 × 시장 배율
              $finalPrice = round($basePrice * $marketMultiplier * (1 + ($personalMultiplier - 1) * 0.5));

              // 안전장치 (최소 80% ~ 최대 150%)
              $minPrice = $basePrice * 0.8;
              $maxPrice = $basePrice * 1.5;
              $finalPrice = max($minPrice, min($finalPrice, $maxPrice));
          }
          // 🔥 백 단위 올림 (뒤 두 자리 전부 올림)
          $finalPrice = ceil($finalPrice / 1000) * 1000;
          // 출력
          $msg .= "{$itemName} : ".number_format($finalPrice) . "냥<br>";
      }

      // $msg .= "
      // 아이템: {$itemName}
      // 기본가: {$basePrice}
      // 내냥: {$userNyung}
      // 개인배율: {$personalMultiplier}
      // 전체냥: {$totalNyung}
      // 시장배율: {$marketMultiplier}
      // 최종가: {$finalPrice}
      // <br><br>";

    // $msg.= "\n(기본 + 전체보유냥1%) - 내냥10%";
    echo 전송($msg);
    exit;

  }else if(strpos($status, '.익명제한') !== false || strpos($status, '.제한') !== false){
      제한_상황실_안내_처리($status);

  }else if(strpos($status, '.추가') !== false){
      $msg = "자숙인 등록방법";
      $msg.= "\n\n명령어 .추가 (일방/공커/제한) (닉네임) ";
      $msg.= "\n.추가 일방 길동";
      $msg.= "\n.추가 공커 길동";
      $msg.= "\n.추가 보룸제한 길동";
      $msg.= "\n.추가 채팅제한 길동";
      $msg.= "\n.추가 게임제한 길동";

      if (preg_match('/\.추가\s+(\S+)\s+(.+)/u', $status, $match)) {
          $상태 = $match[1];
          $진행 = $match[2];

          $data = db_select("select * from tb_self where status = '{$상태}' and nick = '{$진행}' ");
          if($data['idx']){
            $result = db_query("delete from tb_self where idx = {$data['idx']} ");
            echo 전송($상태." {$진행} 삭제완료!");
            exit;
          }else{
            if($상태=="일방"){
              $끝나는날 = date("Y-m-d H:i:s", strtotime("+3 days"));
            }else if($상태=="공커"){
              $끝나는날 = date("Y-m-d H:i:s", strtotime("+5 days"));
            }else if($상태=="보룸제한"){
              $끝나는날 = date("Y-m-d H:i:s", strtotime("+3 hours"));
            }else if($상태=="채팅제한"){
              $끝나는날 = date("Y-m-d H:i:s", strtotime("+3 hours"));
            }else if($상태=="지또제한" || $상태=="게임제한"){
              $끝나는날 = date("Y-m-d H:i:s", strtotime("+3 hours"));
            }

            $sql = "insert into tb_self set status = '{$상태}',nick = '{$진행}', enddate = '{$끝나는날}', regdate = now()" . tb_self_일방공커_모금단가_sql($상태);
            $result = db_query($sql);
            if($result){
              echo 전송($상태."\n{$진행} {$상태} 자숙 등록완료!\n{$끝나는날} 까지");
              exit;
            }
          }
      }

    echo 전송($msg);
    exit;

  }else if(strpos($status, '.연장') !== false){

    if (preg_match('/\.연장\s+(\S+)\s+(.+)/u', $status, $match)) {
        $상태 = $match[1];
        $진행 = trim($match[2]);
        $연장닉 = ($상태 == "일방") ? $진행 . "1️⃣" : $진행;

        $data = db_select("select * from tb_progress where status = '{$상태}' and nick = '{$진행}' ");
        if(!$data['idx'] && $상태 == "일방"){
          $data = db_select("select * from tb_progress where status = '{$상태}' and nick = '{$연장닉}' ");
        }
        if($data['idx']){

          if($상태=="일방"){
            $끝나는날 = date("Y-m-d H:i", strtotime($data['enddate']." +7 days"));
          }else if($상태=="강일"){
            $끝나는날 = date("Y-m-d H:i", strtotime($data['enddate']." +12 hours"));
          }else if($상태=="지목"){
            $끝나는날 = date("Y-m-d H:i", strtotime($data['enddate']." +6 hours"));
          }

          $sql = "update tb_progress set status = '{$상태}', nick = '{$연장닉}', enddate = '{$끝나는날}' where idx = {$data['idx']}  ";
          $result = db_query($sql);
          if($result){
            echo 전송($상태."\n{$연장닉} 연장완료!\n{$끝나는날} 까지");
            exit;
          }
        }
    }
    echo 전송($msg);
    exit;

  }else if(strpos($status, '.등록') !== false){

    // 일반 공백·전각 공백 등: \p{Zs}. 종류는 (일방|강일|지목) 고정으로 앞 토큰 오인식 방지. (.+)는 개행 포함 *us*
    if (preg_match('/\.등록(?:[\s\p{Zs}]+)(일방|강일|지목)(?:[\s\p{Zs}]+)(.*)/us', trim($status), $match)) {
        $상태 = $match[1];
        $진행 = trim($match[2]);
        if ($진행 === '') {
          echo 전송($msg);
          exit;
        }
        $진행_esc = addslashes($진행);

        $data = db_select("select * from tb_progress where status = '{$상태}' and nick = '{$진행_esc}' ");
        if($data['idx']){
          $result = db_query("delete from tb_progress where idx = {$data['idx']} ");
          if ($상태 === '강일' && $result) {
            강일취소_타일_등록(진행문자열_닉목록($진행));
          }

          if ($상태 === '일방' && $result) {
            $닉목록 = 진행문자열_닉목록($진행);
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
          echo 전송($상태." {$진행} 삭제완료!");
          exit;
        }else{

          if($상태=="일방"){
            $끝나는날 = date("Y-m-d 23:59", strtotime("+7 days"));
          }else if($상태=="강일"){
            $끝나는날 = date("Y-m-d H:i", strtotime("+12 hours"));
          }else if($상태=="지목"){
            $끝나는날 = date("Y-m-d H:i", strtotime("+6 hours"));
          }

          if ($상태 === '강일') {
            $제한문구 = 강일_취소제한_검사(진행문자열_닉목록($진행));
            if ($제한문구 !== null) {
              echo 전송($제한문구);
              exit;
            }
          }

          $sql = "insert into tb_progress set status = '{$상태}',nick = '{$진행_esc}', enddate = '{$끝나는날}', regdate = now()  ";
          $result = db_query($sql);
          if($result){
            if ($상태 === '지목' || $상태 === '강일') {
              아이템_buy에_sell_누적($상태);
            }
            if ($상태 === '지목') {
              $닉목록 = 진행문자열_닉목록($진행);
              $지목기록됨 = false;
              foreach ($닉목록 as $nn) {
                미션완료_기록_if_new($nn, '일방', '지목');
                $지목기록됨 = true;
              }
              if (!$지목기록됨) {
                미션완료_기록_if_new($두자리닉넴, '일방', '지목');
              }
            } elseif ($상태 === '강일') {
              $닉목록 = 진행문자열_닉목록($진행);
              $강일기록됨 = false;
              foreach ($닉목록 as $nn) {
                미션완료_기록_if_new($nn, '일방', '강일');
                $강일기록됨 = true;
              }
              if (!$강일기록됨) {
                미션완료_기록_if_new($두자리닉넴, '일방', '강일');
              }
            }
            if ($상태 === '일방') {
              $닉목록 = 진행문자열_닉목록($진행);
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
            }
            $축하문구 = '';
            if ($상태 === '일방') {
              $축하문구 = 일방등록_연금_지급및알림($진행, $두자리닉넴);
            }
            echo 전송($상태."\n{$진행} 등록완료!\n{$끝나는날} 까지{$축하문구}");
            exit;
          }


        }
    }
    echo 전송($msg);
    exit;

  }else if(strpos($status, '.퇴근') !== false){
    $msg = "✅ 퇴근자 명단\n\n";
    $result = db_query("SELECT *
    FROM tb_work
    ORDER BY
        CASE
            WHEN status = '퇴근' THEN 1
            ELSE 2
        END,
        regdate DESC");
    for($i=0;$row=db_fetch($result);$i++){
      $msg.= $row['nick']." ".date("m-d H:i", strtotime($row['regdate']))."\n";
    }

      if (preg_match('/\.퇴근\s*([^\s]+)/u', $status, $match)) {
          $명령 = '퇴근';
          $두자리닉넴 = $match[1];

          $sql = "select * from tb_member where name = '{$두자리닉넴}' ";
          $받는친구 = db_select($sql);
          if(!$받는친구['idx']){
            echo 전송("⛵️");
            exit;
          }

          if (퇴근_24시간_미충족($받는친구['regdate'] ?? '')) {
            echo 전송(퇴근_24시간_안내($받는친구['regdate'] ?? ''));
            exit;
          }

          $data = db_select("select * from tb_work where nick = '{$두자리닉넴}' ");
          if($data['idx']){
            echo 전송("퇴근 후 다시올땐 .출근 {$두자리닉넴}");
            exit;
          }else{
            $타임 = 48;
            $이틀 = date("Y-m-d H:i", strtotime("+{$타임} hours"));

            $sql = "insert into tb_work set status = '{$명령}', nick = '{$두자리닉넴}', regdate = '{$이틀}'  ";
            $result = db_query($sql);
            db_query("update tb_member set status = 3 where name = '{$두자리닉넴}' "); //퇴근
            if($result){
              $msg = "{$두자리닉넴} 퇴근등록🚌\n{$타임}시간 내 돌아오자.\n";
              $msg.= "\n-미복귀시\n공질•아이템•{$단위} 자동삭제";
              $msg.= "\n\n우리방 검색어\n[ 지호썸, 냥살냥죽 ]";
              echo 전송($msg);
              exit;
            }
          }
      }
    $msg.= "\n- 퇴근시 .퇴근 닉네임";
    echo 전송($msg);
    exit;

  }else if(strpos($status, '.출근') !== false){
  $msg = "✅ 출근 완료\n\n";

    if (preg_match('/(?:\.출근)\s*(.*)/u', $status, $match)) {
        $출근대상 = (isset($match[1]) && trim($match[1]) !== '') ? trim($match[1]) : $두자리닉넴;

        $sql = "select * from tb_member where name = '{$출근대상}' ";
        $받는친구 = db_select($sql);
        if(!$받는친구['idx']){
          echo 전송("존재하지 않는 사용자 입니다.");
          exit;
        }

        $data = db_select("select * from tb_work where nick = '{$출근대상}' ");
        if($data['idx']){
          $result = db_query("delete from tb_work where nick = '{$출근대상}' ");
          db_query("update tb_member set status = 0 where name = '{$출근대상}' "); //출근
          if($result){
            $msg = "{$출근대상} 어서와!!🎉";
            echo 전송($msg);
            exit;
          }
        }else{
          echo 전송("퇴근 원할시 .퇴근 {$출근대상} 할 것!!");
          exit;
        }

    }else{
      echo 전송("퇴근 원할시 .퇴근 {$두자리닉넴}");
      exit;
    }

  }else if (strpos($status, '.임무') !== false) {
      $msg = "";

      // 기본값
      $조회닉 = $두자리닉넴;
      $조회일자 = date("Y-m-d");

      // .내역 뒤 파라미터 추출
      if (preg_match('/\.임무\s*(.*)/u', $status, $match)) {
          $input = trim($match[1]); // 전체 입력

          if ($input !== "") {

              $parts = explode(" ", $input);

              // 입력이 2개일 때 → 닉네임 + 날짜
              if (count($parts) == 2) {
                  $조회닉 = $parts[1 - 1];
                  $조회일자 = $parts[2 - 1];
              }

              // 입력이 1개일 때 → 닉네임 또는 날짜
              else if (count($parts) == 1) {
                  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $parts[0])) {
                      // 날짜만 입력
                      $조회일자 = $parts[0];
                  } else {
                      // 닉네임만 입력
                      $조회닉 = $parts[0];
                  }
              }
          }
      }

      // 닉네임 존재 여부 검사
      $sql = "SELECT * FROM tb_member WHERE name = '{$조회닉}' ";
      $받는친구 = db_select($sql);
      if (!$받는친구['idx']) {
          echo 전송("존재하지 않는 사용자 입니다.");
          exit;
      }
      $정보 = db_select("select * from tb_member where name = '{$조회닉}' ");
      $계급 = 계급($정보['point']);

      // 로그 조회
      $sql = "select sum(tasu) as total_tasu from tb_msg where nickname = '{$조회닉}' and DATE(regdate) = '{$조회일자}'";
      $타수 = db_select($sql);

      $msg.= "{$호칭} {$조회닉}\n\n";
      if($타수['total_tasu']){
        $msg.= "⌨️타수로 받은 {$타수['total_tasu']}냥\n";
      }

      $sql = "SELECT status, sum(point) as point FROM tb_point_log WHERE nick = '{$조회닉}' AND DATE_FORMAT(regdate, '%Y-%m-%d') = '{$조회일자}' AND status LIKE '럭키단어[%' GROUP BY status";
      $럭키_rs = db_query($sql);
      if ($럭키_rs) {
        while ($럭키_row = db_fetch($럭키_rs)) {
          if ((float)($럭키_row['point'] ?? 0) > 0) {
            $msg .= '🍀' . ($럭키_row['status'] ?? '럭키단어') . ' ' . number_format((float)$럭키_row['point'], 1) . "\n";
          }
        }
      }

      $sql = "SELECT status, sum(point) as point, sum(bonus) as bonus FROM tb_point_log WHERE nick = '{$조회닉}' AND DATE_FORMAT(regdate, '%Y-%m-%d') = '{$조회일자}' AND status LIKE '%주사위-신청%'";
      $주사위신청 = db_select($sql);
      if($주사위신청['point']>0){
          $msg.= "🎲주사위신청 : ".($주사위신청['point'] > 0 ? "-".콤마삽입($주사위신청['point']):"0")."냥\n";
      }


      $sql = "SELECT status, sum(point) as point, sum(bonus) as bonus FROM tb_point_log WHERE nick = '{$조회닉}' AND DATE_FORMAT(regdate, '%Y-%m-%d') = '{$조회일자}' AND status LIKE '%주사위%우승%'";
      $주사위우승 = db_select($sql);
      if($주사위우승['point']>0){
          $msg.= "🎲주사위승리 : ".number_format($주사위우승['point']) . "냥\n";
      }

      $sql = "SELECT * FROM tb_point_log WHERE nick = '{$조회닉}' AND DATE_FORMAT(regdate, '%Y-%m-%d') = '{$조회일자}' AND status LIKE '%일보상%'";
      $일일타수보상 = db_select($sql);
      if($일일타수보상['point']>0){
          $msg.= "{$일일타수보상['status']} ".number_format($일일타수보상['point']) . "냥\n";
      }

      $sql = "SELECT * FROM tb_point_log WHERE nick = '{$조회닉}' AND DATE_FORMAT(regdate, '%Y-%m-%d') = '{$조회일자}' AND status LIKE '%출석%'";
    //LIMIT 15
      $result = db_query($sql);
      while ($row = db_fetch($result)) {
          $msg .= "\n{$row['status']} {$row['point']}냥";
      }

      echo 전송($msg);
      exit;

  }else if(strpos($status, '.지급') === 0){
    // 관리자 전용: .지급 [닉네임] [칭호(OO의)] [무기이름] → 칭호는 style, 무기는 5강 검으로 지급 (예: .지급 삐요 삐요의 🎋목도)
    if (!in_array(getTwoCharNick($nick), $관리자)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    if (preg_match('/\.지급\s+([가-힣A-Za-z0-9_]+)\s+([가-힣A-Za-z0-9_]+의)\s+(.+)/u', trim($status), $m)) {
      $대상닉 = trim($m[1]);
      $칭호 = trim($m[2]);   // 스타일용 (예: 삐요의)
      $무기명 = trim($m[3]); // 검 이름 (예: 🎋목도)
      $대상닉_esc = addslashes($대상닉);
      $칭호_esc = addslashes($칭호);
      $무기명_esc = addslashes($무기명);
      $대상 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$대상닉_esc}' LIMIT 1");
      if (empty($대상['idx'])) {
        echo 전송("❌ 해당 회원을 찾을 수 없습니다.");
        exit;
      }
      $강화수 = 5; // 5강으로 지급
      db_query("UPDATE tb_member SET style = '{$칭호_esc}', item = '{$무기명_esc}', enhance = {$강화수} WHERE name = '{$대상닉_esc}'");
      echo 전송("✅ [ {$대상닉} ] 칭호 '{$칭호}' + 무기 {$무기명} +{$강화수}강 지급 완료!");
      exit;
    }
  

  }else if (strpos($status, '.삭감') !== false) {
    require_once __DIR__ . '/game/sakgam.inc.php';
    삭감_명령_처리($status, $두자리닉넴, $nick ?? '', $관리자 ?? []);

  
  }else if(strpos($status, '.명령') !== false){
$msg = "✅ 우리방 명령어                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
  $msg .= "
.제발
.공질
.샘플(공질샘플)
.신입(주의사항)
.인원(남여비율)
.공지(공지사항)
.아이템
.평타(전체평균타수)
.생타(오늘 생타순위 · 생타/버프타)
.닉추천(GPT 2글자 닉)
.평타사다리 10 4 (평타 상위 10명 중 4명 랜덤)
--------
.궁금 닉네임
💰.점메추천(5만냥·GPT 점심)
💰.맛집 지역(5만냥·GPT 맛집 3곳)
.출석,ㅊㅅ,찰싹
.연두(미출석자)
.진행(일방/강일/지목)
.자숙(일방/강일/제한)
.일방준비(일방 신청 조건)
.일방신청(필수 미션 확인)
.임무({$단위}지급내역)
💰.mbti 닉
--------
.버프(프변,지호)
.구매 2글자템명(아이템구매)
.판매 2글자템명(아이템판매)
.가방 (보유아이템확인) · .가방 닉 (타인 조회)
--------
.내{$단위}(보유 {$단위})
.랭킹1 숫자({$단위} 보유순위) · .랭킹2 숫자(게임{$단위} 순위) · .랭킹3 숫자(채굴 장비 순위)
💰.양도 ({$단위} 양도, 양도 내역)
.금고 (수수료모음)
--------
.도전 금액(홀짝 1·2·3·연승)
.ㄱㄱ(맞짱게임)
.신청(주사위게임)
.ㄹㄷ(랜덤박스까기)";
  echo 전송($msg);
  exit;




    }else if (strpos($status, '.지호') !== false) {

    $아이템 = 아이템보유유무($두자리닉넴, "지호");
    $msg = "";
    if($아이템['idx']){
        db_query("update tb_member_item set status = 1 where idx = {$아이템['idx']} ");
        아이템사용_시세하락('지호', 1);
        $유효시간 = date("Y-m-d H:i", strtotime("+3 hours"));

        $사용중여부 = db_select("select * from tb_item_use where nickname = '{$두자리닉넴}' and item = '지호' ");
        if($사용중여부['idx']){
            $유효시간 = date("Y-m-d H:i", strtotime($사용중여부['enddate']." +3 hours"));
            $sql = "update tb_item_use set enddate = '{$유효시간}' where idx = {$사용중여부['idx']} ";
            db_query($sql);
            $연장시간 = date("H:i", strtotime($유효시간));
            $msg.= "►{$두자리닉넴} 지호 연장 {$연장시간}";
            echo 전송($msg);
            exit;
        }else{
            $유효시간 = date("Y-m-d H:i", strtotime("+3 hours"));
            $sql = "insert into tb_item_use set nickname = '{$두자리닉넴}', item = '지호', enddate = '{$유효시간}', regdate = now() ";
            db_query($sql);
            $연장시간 = date("H:i", strtotime($유효시간));
            $msg.= "►{$두자리닉넴} 지호 사용 타수2배,{$단위}지급2배 (Lv21~양도1{$단위}) 판매수수료 15% {$연장시간} 까지 ";
            echo 전송($msg);
            exit;
        }

    }else{
        $msg = "지호 아이템 없음!";
        echo 전송($msg);
        exit;
    }

    echo 전송($msg);
    exit;   
  }else if (strpos($status, '.공커') !== false || strpos($status, '.커플') !== false) {

    if (!isset($where)) $where = "";
    공커_sort_sdate재배치($where);

    $전체보유냥 = (float)($전체포인트['total_point'] ?? 0);
    $공커적립 = (int)floor($전체보유냥 * 0.0001);
    공커_amount_갱신($공커적립, $where);
    if (function_exists('공커연금_누적갱신')) {
      공커연금_누적갱신();
    }
    $공커연금단가 = function_exists('공커연금_단가') ? 공커연금_단가() : 500;

      $msg = "👩‍❤️‍👨 공커.. 놀라지 않겠다고 약속해줘..\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
      $msg .= "공커연금 1일당 " . number_format($공커연금단가) . "냥\n\n";
      $result = db_query("SELECT * FROM tb_couple WHERE status = 0 {$where} ORDER BY sdate desc; ");
      for ($i = 0; $row = db_fetch($result); $i++) {
        $now1 = new DateTime();
        $end1 = new DateTime($row['edate']);
        $diffHours1 = ($end1->getTimestamp() - $now1->getTimestamp()) / 3600;
        $imminent1 = ($diffHours1 <= 24 && $diffHours1 > 0) ? "⚠️" : "";

        $date = $row['sdate'];
        $day = date('w', strtotime($date));
        $요일 = ['일', '월', '화', '수', '목', '금', '토'];

          $days = abs((new DateTime())->diff(new DateTime($row['sdate']))->format("%r%a"));
          $days = $days + 1;
          $msg .= $row['couple']." +{$days}\n({$요일[$day]} {$row['edate']}{$imminent1} )\n".공커대실권_표시($row['amount'])."\n";
          if (function_exists('공커연금_커플_표시문구')) {
            $msg .= 공커연금_커플_표시문구($row['couple']) . "\n";
          }
          $msg .= "\n";
      }
      echo 전송($msg);
      exit;

  }else if(strpos($status, '.오늘') !== false){
    if (in_array(getTwoCharNick($nick), $관리자)) {
      if (preg_match('/\.오늘\s*(.+)/u', $status, $match)) {
          $날짜 = trim($match[1]); // ".양도" 뒤 텍스트 추출
      }
      $msg = "✅ 일타수체크\n\n";
      // 날짜 조건 설정
      $where = "";
      if ($설정['타수이벤종료']) {
          $where = "AND msg.regdate >= '{$설정['타수이벤시작']}' AND msg.regdate <= '{$설정['타수이벤종료']}' ";
      }else{
        $다음 = date("Y-m-d", strtotime("+1 days"));
        $where = "AND msg.regdate >= '{$오늘} 00:00:00' AND msg.regdate <  '{$다음} 00:00:00'  ";
      }

      if($날짜){
        $msg.="{$날짜}\n\n";
        $다음 = date("Y-m-d", strtotime($날짜." +1 days"));
        $where = "AND msg.regdate >= '{$날짜} 00:00:00' AND msg.regdate <  '{$다음} 00:00:00'  ";
      }

      // SQL 쿼리
      $sql = "
          SELECT
              m.name AS nickname,
              SUM(msg.tasu) AS total_msg_count,
              SUM(
                  CASE
                      WHEN msg.msg != '이모티콘을 보냈습니다.'
                      THEN msg.tasu
                      ELSE 0
                  END
              ) AS text_msg_count

          FROM tb_member AS m
          LEFT JOIN tb_msg AS msg
              ON m.name = msg.nickname
              {$where}
          GROUP BY m.name
          ORDER BY text_msg_count DESC
      ";

      $result = db_query($sql);
      $a=1;
      $html = "";
      for($i=0;$row=db_fetch($result);$i++){
        $임티개수 = $row['total_msg_count'] - $row['text_msg_count'];
        $msg .= $a."등 ".$row['nickname']." ".$row['text_msg_count']." (임티 ".$임티개수.")\n";
        $a++;
      }
      echo 전송($msg);
      exit;
    }

  }else if(strpos($status, '.주별') !== false){
    if (in_array(getTwoCharNick($nick), $관리자)) {
      $msg = "✅ 주차별 타수체크\n\n";
      // 주차 번호 파싱 (.주차 1)
      preg_match('/\.주별\s*(\d+)/', $status, $match);
      $targetWeek = $match[1] ?? null;  // null이면 현재 주차

      $today = new DateTime();
      $year = $today->format('Y');
      $month = $today->format('m');
      $firstDay = new DateTime("$year-$month-01");
      $lastDay = (clone $firstDay)->modify('last day of this month');

      // --- 월 전체 주차 나누기 ---
      $weeks = [];
      $weekNumber = 1;
      $startOfWeek = clone $firstDay;

      if ($startOfWeek->format('N') != 1) {
          $startOfWeek = (clone $startOfWeek)->modify('last monday');
      }

      while ($startOfWeek <= $lastDay) {
          $endOfWeek = (clone $startOfWeek)->modify('next sunday');
          if ($endOfWeek > $lastDay) $endOfWeek = clone $lastDay;

          $weeks[$weekNumber] = [
              'start' => $startOfWeek->format('Y-m-d'),
              'end'   => $endOfWeek->format('Y-m-d')
          ];

          $startOfWeek = (clone $endOfWeek)->modify('+1 day');
          $weekNumber++;
      }

      // --- 사용할 주차 선택 ---
      if ($targetWeek) {
          // 사용자가 주차 직접 요청한 경우
          if (!isset($weeks[$targetWeek])) {
              echo 전송("해당 주차는 없습니다.");
              exit;
          }
          $selectedWeek = $targetWeek;
          $currentRange = $weeks[$targetWeek];

      } else {
          // 기존처럼 현재 주차 자동 계산
          $selectedWeek = 0;
          foreach ($weeks as $weekNum => $range) {
              if ($today >= new DateTime($range['start']) && $today <= new DateTime($range['end'])) {
                  $selectedWeek = $weekNum;
                  $currentRange = $range;
                  break;
              }
          }
      }

      // --- 날짜만 추출 ---
      $startDay = date('j', strtotime($currentRange['start']));
      $endDay   = date('j', strtotime($currentRange['end']));

      // --- 멤버 타수 계산 ---
      $membersData = [];
      $members = db_query("SELECT name FROM tb_member");
      foreach ($members as $member) {
          $name = $member['name'];
          $sql = "
              SELECT SUM(tasu) AS sum_tasu
              FROM tb_msg
              WHERE nickname = '{$name}'
                AND regdate >= '{$currentRange['start']}'
                AND regdate <= '{$currentRange['end']}'
          ";
          $sum = db_select($sql);
          $tasu = $sum['sum_tasu'] ?? 0;
          $membersData[] = [
              'name' => $name,
              'tasu' => $tasu
          ];
      }

      // --- 정렬 ---
      usort($membersData, function($a, $b) {
          return $b['tasu'] <=> $a['tasu'];
      });

      // --- 출력 ---
      $msg .= "{$month}월 {$selectedWeek}주차\n{$startDay}일~{$endDay}일 타수\n\n";
      foreach ($membersData as $m) {
          $msg .= "{$m['name']} - {$m['tasu']}\n";
      }

      echo 전송($msg);
      exit;

    }else{
      echo 전송("🔒");
      exit;
    }

  }else if(strpos($status, '.칠타') !== false){
    if (in_array(getTwoCharNick($nick), $관리자)) {

      if (preg_match('/\.칠타\s*(.+)/u', $status, $match)) {
          $after = trim($match[1]); // ".지급" 뒤 텍스트 추출

          // "도하 5" 형태인지 검사 + 그룹 분리
          if (preg_match('/^([가-힣A-Za-z]+)$/u', $after, $parts)) {
            $받는닉 = $parts[1]; // 닉네임
            $지급양 = $parts[2]; // 지급양

            $dates = [];
            for ($i = 0; $i <= 6; $i++) {  // 오늘 포함 7일치, 오늘부터 6일 전
                $dates[] = date('Y-m-d', strtotime("-$i days"));
            }

            // 테스트: 출력
            $msg = "✅ 7일간 타수 - {$받는닉}\n\n";
            foreach ($dates as $date) {
                // 요일 구하기 (한글)
                $weekday = ['일','월','화','수','목','금','토'][date('w', strtotime($date))];

                // 합계 쿼리
                $sql = "SELECT SUM(tasu) AS cnt
                        FROM tb_msg
                        WHERE nickname = '{$받는닉}'
                          AND regdate >= '{$date}'
                          AND regdate < '{$date}' + INTERVAL 1 DAY";
                $타수 = db_select($sql);

                // 메시지 조합
                $msg .= $date . "({$weekday}) {$타수['cnt']}\n";
            }
            echo 전송($msg);
            exit;
          }
      }
    }else{
      echo 전송("🔒");
      exit;
    }

  }else if(strpos($status, '.닉변') !== false){

      $msg = "✅ 닉네임 변경방법\n\n.닉변 현재닉 변경닉";
      if (preg_match('/\.닉변\s+([^\s]+)\s+([^\s]+)/u', $status, $match)) {
          $기존닉 = trim($match[1]);  // 기존 닉네임
          $변경닉  = trim($match[2]);  // 변경할 닉네임

          $sql = "select * from tb_member where name = '{$기존닉}' ";
          $받는친구 = db_select($sql);
          if(!$받는친구['idx']){
            echo 전송($기존닉." 존재하지 않는 닉네임");
            exit;
          }

          $아이템 = 아이템보유유무($기존닉, "닉변");
          if($아이템['idx']){
            db_query("update tb_member_item set status = 1 where idx = {$아이템['idx']} ");
            아이템사용_시세하락('닉변', 1);
          }else{
            echo 전송($기존닉." 닉변아이템 없음");
            exit;
          }

            if(!empty($기존닉)){
              $기존닉_sql = addslashes($기존닉);
              $변경닉_sql = addslashes($변경닉);
              db_query("update tb_msg set nickname = '{$변경닉_sql}' where nickname = '{$기존닉_sql}'");
              db_query("update tb_attendance set nickname = '{$변경닉_sql}' where nickname = '{$기존닉_sql}'");
              //db_query("update tb_point_log set nick = '{$변경닉_sql}' where nick = '{$기존닉_sql}'");
              db_query("update tb_winner set nick = '{$변경닉_sql}' where nick = '{$기존닉_sql}'");
              db_query("update tb_member_item set nick = '{$변경닉_sql}' where nick = '{$기존닉_sql}'");
              db_query("update tb_item_use set nickname = '{$변경닉_sql}' where nickname = '{$기존닉_sql}'");
              db_query("update tb_progress set nick = '{$변경닉_sql}' where nick = '{$기존닉_sql}'");
              db_query("update tb_couple set couple = REPLACE(couple, '{$기존닉_sql}', '{$변경닉_sql}') where couple like '%{$기존닉_sql}%'");
              db_query("update tb_mission set nick = '{$변경닉_sql}' where nick = '{$기존닉_sql}'");
              db_query("update tb_lotto_info set item = '{$변경닉_sql}' where item = '{$기존닉_sql}'");
              db_query("update tb_gifticon set seller_nick = '{$변경닉_sql}' where seller_nick = '{$기존닉_sql}'");

              // 프로필 첫 줄(닉네임•키)에 있는 닉네임도 변경
              $content = $받는친구['content'] ?? '';
              $new_content = $content;
              if ($content !== '' && strpos($content, '닉네임•키') !== false) {
                  $lines = explode("\n", $content);
                  $first = $lines[0];
                  $lines[0] = preg_replace('/닉네임•키\s*:\s*' . preg_quote($기존닉, '/') . '(\s|$)/u', '닉네임•키 : ' . $변경닉 . '$1', $first, 1);
                  $new_content = implode("\n", $lines);
              }
              $content_esc = addslashes($new_content);
              $member_ok = db_query("update tb_member set name = '{$변경닉_sql}', content = '{$content_esc}' where name = '{$기존닉_sql}'");

              if ($member_ok) {
                  $msg = "✅".$기존닉." → ".$변경닉." 변경완료(닉변아이템 1개 소멸)";
              } else {
                  $msg = "❌ 닉네임 최종 반영에 실패했어요. 잠시 후 다시 시도하거나 관리자에게 문의해주세요.";
              }
              echo 전송($msg);
              exit;
              
            }else{
              echo 전송("변경할 닉네임을 입력해줘!");
              exit;
            }
      }
      echo 전송($msg);
      exit;
  
  }else if (preg_match('/^\.프변(?:\s+(\d+))?\s*$/u', trim($status), $프변m)) {
      $개수 = isset($프변m[1]) ? (int)$프변m[1] : 1;
      if ($개수 < 1) {
          echo 전송("❌ 사용 개수는 1 이상으로 입력해주세요.\n예) .프변 5");
          exit;
      }
      echo 전송(프변_명령_처리($두자리닉넴, $개수));
      exit;

    }else if(strpos($status, '.정산') !== false){

    if (in_array(getTwoCharNick($nick), $관리자1)) {

      $날짜 = date('Y-m-d');
      if (preg_match('/\.정산\s+(\d{4}-\d{2}-\d{2})/u', $status, $matches)) {
            $날짜 = $matches[1]; // 캡처된 날짜
        }

      if($설정['평타냥지급여부']==0){
        $msg = "일 타수 1위~10위 {$단위}보상 지급\n\n";
        $sql = "WITH today_tasu AS (
            SELECT
                nickname,
                SUM(tasu) AS cnt
            FROM tb_msg
            WHERE regdate >= '{$날짜}'
              AND regdate < '{$날짜}' + INTERVAL 1 DAY
              AND nickname NOT IN ('오픈', '신입', '왕벌', '왕남', '', '요원', '지또', '우지')
              AND msg NOT LIKE '%이모티콘을 보냈습니다.%'
            GROUP BY nickname
        )
        SELECT
            (SELECT AVG(cnt) FROM today_tasu) AS 평균타수
         ";
        $data = db_select($sql);
        $sql = "WITH today_tasu AS (
            SELECT
                nickname,
                SUM(tasu) AS cnt
            FROM tb_msg
            WHERE regdate >= '{$날짜}'
              AND regdate < '{$날짜}' + INTERVAL 1 DAY
              AND nickname NOT IN ('오픈', '신입', '왕벌', '왕남', '', '요원', '지또', '우지')
              AND msg NOT LIKE '%이모티콘을 보냈습니다.%'
            GROUP BY nickname
        ),
        stat AS (
            SELECT SUM(cnt) AS total_cnt
            FROM today_tasu
        ),
        ranked AS (
            SELECT
                nickname,
                cnt,
                ROW_NUMBER() OVER (ORDER BY cnt DESC) AS rn
            FROM today_tasu
        )
        SELECT
            r.nickname AS 닉네임,
            r.cnt AS 타수,
            ROUND(r.cnt / s.total_cnt * 100, 2) AS 전체비중퍼센트,
            r.rn AS 순위,
            FLOOR(r.cnt * 0.2) AS 타수_20퍼_정수   -- ← 추가된 부분
        FROM ranked r
        CROSS JOIN stat s
        WHERE r.rn <= 10
        ORDER BY r.rn;
        ";
        $result = db_query($sql);
        foreach($result as $순위){

          if($순위['순위']==1){
            $일타수지급보상 = $순위['타수'] * 70;
            $표시 = "(x70)";
          }else if($순위['순위']==2){
            $이십퍼 = floor($순위['타수'] * 50);
            $일타수지급보상 = $이십퍼;
            $표시 = "(x50)";
          }else if($순위['순위']==3){
            $이십퍼 = floor($순위['타수'] * 40);
            $일타수지급보상 = $이십퍼;
            $표시 = "(x40)";
          }else if($순위['순위']==4){
            $이십퍼 = floor($순위['타수'] * 30);
            $일타수지급보상 = $이십퍼;
            $표시 = "(x30)";
          }else if($순위['순위']==5){
            $이십퍼 = floor($순위['타수'] * 25);
            $일타수지급보상 = $이십퍼;
            $표시 = "(x25)";
          }else if($순위['순위']==6){
            $이십퍼 = floor($순위['타수'] * 20);
            $일타수지급보상 = $이십퍼;
            $표시 = "(x20)";
          }else if($순위['순위']==7){
            $이십퍼 = floor($순위['타수'] * 15);
            $일타수지급보상 = $이십퍼;
            $표시 = "(x15)";
          }else if($순위['순위']==8){
            $이십퍼 = floor($순위['타수'] * 10);
            $일타수지급보상 = $이십퍼;
            $표시 = "(x10)";
          }else{
            $이십퍼 = floor($순위['타수'] * 0.2);
            $일타수지급보상 = $이십퍼;
            $표시 = "";
          }


          $sql = "update tb_member set point = point + {$일타수지급보상} where name = '{$순위['닉네임']}'\n";
          db_query($sql);

          // 순위별 이모지 결정
          switch ((int)$순위['순위']) {
              case 1:
                  $rankEmoji = "🥇";
                  break;
              case 2:
                  $rankEmoji = "🥈";
                  break;
              case 3:
                  $rankEmoji = "🥉";
                  break;
              default:
                  if ($순위['순위'] <= 10) {
                      $rankEmoji = "🏆";
                  } else {
                      $rankEmoji = "";
                  }
          }

          $msg .= $순위['순위']."등 ".$순위['닉네임']." {$순위['타수']}타{$표시} {$일타수지급보상}냥\n";
          지급로그("{$rankEmoji}일보상{$순위['순위']}등", $순위['닉네임'], '', 0, $일타수지급보상);
        }
        $msg.= "\n{$날짜} 고생많으셨습니다.";
        db_query("UPDATE config SET 평타냥지급여부 = 0");
        echo 전송($msg);
        exit;
      }else{
        echo 전송($날짜." 지급완료");
        exit;
      }
    }

  }else if(strpos($status, '.평타') !== false){
    관리자전용_확인($두자리닉넴, $관리자);
    $날짜 = date('Y-m-d');
    if (preg_match('/\.평타\s+(\d{4}-\d{2}-\d{2})/u', $status, $matches)) {
          $날짜 = $matches[1]; // 캡처된 날짜
      }

    $sql = "
    SELECT
        AVG(total_tasu) AS avg_total_tasu
    FROM (
        SELECT
            tm.name AS nickname,
            SUM(m.tasu) AS total_tasu
        FROM tb_member tm
        JOIN tb_msg m
            ON tm.name = m.nickname
        WHERE m.regdate >= '{$날짜}'
          AND m.regdate < '{$날짜}' + INTERVAL 1 DAY
        GROUP BY tm.name
    ) t";


     // echo 전송($sql);
     // exit;
    $data = db_select($sql);
    $현재평균타수 = number_format($data['avg_total_tasu'],2);
    $msg = "✅우리방 채팅 평균 - {$현재평균타수}\n\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";

    $msg.= "   {$현재평균타수} 타(임티제외)\n\n";

    $sql = "WITH today_tasu AS (
        SELECT
            nickname,
            SUM(tasu) AS cnt
        FROM tb_msg
        WHERE regdate >= '{$날짜}'
          AND regdate < '{$날짜}' + INTERVAL 1 DAY
          AND nickname NOT IN ('오픈', '신입', '왕벌', '왕남', '', '요원', '지또', '우지')
          AND msg NOT LIKE '%이모티콘을 보냈습니다.%'
        GROUP BY nickname
    ),
    stat AS (
        SELECT SUM(cnt) AS total_cnt
        FROM today_tasu
    ),
    ranked AS (
        SELECT
            nickname,
            cnt,
            ROW_NUMBER() OVER (ORDER BY cnt DESC) AS rn
        FROM today_tasu
    )
    SELECT
        r.nickname AS 닉네임,
        r.cnt AS 타수,
        r.rn AS 순위,
        FLOOR(r.cnt * 0.2) AS 타수_20퍼_정수   -- ← 추가된 부분
    FROM ranked r
    CROSS JOIN stat s
    WHERE r.rn <= 15
    ORDER BY r.rn ";
    $msg .= "순위|닉네임|타수|보상\n";
    $result = db_query($sql);
    foreach($result as $순위){

      if($순위['순위']==1){
        $일타수지급보상 = $순위['타수'] * 70;
        $표시 = "(x70)";
      }else if($순위['순위']==2){
        $이십퍼 = floor($순위['타수'] * 50);
        $일타수지급보상 = $이십퍼;
        $표시 = "(x50)";
      }else if($순위['순위']==3){
        $이십퍼 = floor($순위['타수'] * 40);
        $일타수지급보상 = $이십퍼;
        $표시 = "(x40)";
      }else if($순위['순위']==4){
        $이십퍼 = floor($순위['타수'] * 30);
        $일타수지급보상 = $이십퍼;
        $표시 = "(x30)";
      }else if($순위['순위']==5){
        $이십퍼 = floor($순위['타수'] * 25);
        $일타수지급보상 = $이십퍼;
        $표시 = "(x25)";
      }else if($순위['순위']==6){
        $이십퍼 = floor($순위['타수'] * 20);
        $일타수지급보상 = $이십퍼;
        $표시 = "(x20)";
      }else if($순위['순위']==7){
        $이십퍼 = floor($순위['타수'] * 15);
        $일타수지급보상 = $이십퍼;
        $표시 = "(x15)";
      }else if($순위['순위']==8){
        $이십퍼 = floor($순위['타수'] * 10);
        $일타수지급보상 = $이십퍼;
        $표시 = "(x10)";
      }else{
        $이십퍼 = floor($순위['타수'] * 0.2);
        $일타수지급보상 = $이십퍼;
        $표시 = "";
      }

      $msg .= $순위['순위']."등 ".$순위['닉네임']." {$순위['타수']}타{$표시} {$일타수지급보상}냥\n";
    }

    if($설정['냥두배']==2){
      $msg .= "\n⚡️{$단위}두배이벤중\n(기본 2타 인정, 타수 지급{$단위} 두배)";
    }

    echo 전송($msg);
    exit;
  }else if(strpos($status, '.아템신청') !== false){
    $msg = "🗡닉네임 :
🗡아이템 명 :
🗡지정자(있는경우) :

😷익명사용 원할시 건의방 올 것";
    echo 전송($msg);
    exit;
  }else if(strpos($status, '.아템조회') !== false){
    $msg = "http://49.247.160.164/ACDSDFF";
    echo 전송($msg);
    exit;
  }else if(strpos($status, '.공질조회') !== false){
    $msg = "http://49.247.160.164/zzazz";
    echo 전송($msg);
    exit;
  }else if(strpos($status, '.주의일방') !== false){
    $msg = "필독! - 일방/일벙/공커/자숙

  가. 자숙
   # 일방신청 : 상대방 거절 시 1일(셀프)
   # 일방 : 종료 시 3일
   # 공커 : 종료 시 5일
   # 자숙기간동안 보룸, 얼공, 채굴, 소통방, 모임 금지
   # 단 셀프 자숙은 보룸가능
   # 자숙기간동안 공창에서 본인어필 / 플러팅 금지
   또한 자숙중인 상대에게 플러팅 금지
   (위 사항 적발시 3시간 간격으로 자숙기간 연장)
   # 이성친구와 나눴던 말, 있었던 일 절대 공유하지마!
   # 자숙시 진행중이던 일방은 모두 폭파할것.
   # 일방에서 연락처를 주고 받았더라도 개인적인 연락 절대금지
     깔끔하게 끝내자 

  나. 일방 및 공커
   # 정식 일방 신청은 건의방에 와서 해줘
   # 일방은 7일간 가능하고 연장 1회(7일) 가능해

‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️
‼️‼️아래 절차는 꼭 이행해줄것‼️‼️
‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️

필수1)일벙이 결정되면 상황실 혹은 건의방에 날짜를 이야기해주기

필수2)🦄일벙인증 - 손하트🫰🏻
  손하트🫰🏻인증 앵글에 모두 잡히도록 
  사진 찍은 후 공창에 올려줘!
- 만났을때 바로 해주는게 제일좋아!
  
필수3)🦄공커인증 - 손깍지🤝
   손깍지🤝끼고 사진찍어서 공창에 올려줘!🙏🏼

🚨 필수1,2,3 인증 중 하나라도 건너뛴다면 
룰 미이행으로 90% 냥 삭감.


🚨 첫 일벙시 당일 밤 12시 이전에 
연장 or 공커 or 자숙 결정 후
공창에 이야기 해줘!
이야기 없이 넘어갈시 90% 냥 삭감.";
    echo 전송($msg);
    exit;
  }else if(strpos($status, '.주의강일') !== false){
    $msg = "필독! - ⛺️강제일방

∙ 강일방에서는 방장 상주 (모니터링 하지 않음)
∙ 상대방이 불쾌할만한 언행 금지
∙ 개인정보 공유는 나이까지만 가능, 그 외 금지
∙ 보이스룸 금지 (모니터링 불가 이슈)
∙ 자삭 금지 (개인정보 오픈 후 자삭 이슈)
∙ 참여자 중 1인이라도 종료 시 강제일방 종료
∙ 강제일방은 커피벙/술벙 등 이성과의 만남(일벙) 약속 금지
∙ 이성과 만나고 싶다면 정식일방 신청 필수

‼️ 강제일방 도중 강일템 사용 시 12시간 연장 가능
‼️ 강제일방 도중 정식일방 전환 불가
‼️ 강제일방 종료 후 12시간 뒤 정식일방 신청 가능
‼️ 강제일방 종료 후 셀자/자숙 없음
🐸 이벤트 강일은 종료 후 12시간 락 없이 일방 전환 가능

그 외 얼공 및 기타 활동은 가능하나, 염려된다면 얼공방 이용을 추천드립니다.

🐸 이벤트로 개설되는 1:1방은 일반 일방이 아닌 강일방과 동일 규정이 적용됩니다.
(자삭, 보이스룸, 개인정보 공유 금지)

🐸 운영진은 안전 이슈로 상주하며, 종료 후 보이스룸 및 자삭 여부를 확인합니다.
🐸 운영진은 대화 내용을 일일이 확인하지 않지만, 룰 위반 정황 발생 시 확인이 진행될 수 있습니다.
🐸 운영진이 있는 공간에서 하기 어려운 깊은 대화 및 아쉬움은 일방 시스템을 통해 자유롭게 이용 가능합니다.";
    echo 전송($msg);
    exit;
  }else if (strpos($status, '.우리방') !== false && trim((string)$status) !== '.우리방초기화') {
    $전체보유 = (float)전체보유newpoint합계();
    $전체게임냥 = function_exists('시세기준_게임냥_문자열')
      ? 시세기준_게임냥_문자열()
      : (function_exists('시세기준_게임냥') ? 시세기준_게임냥() : '0');

    $msg = "📊 시세 기준 (".시세기준_갱신시각_표시().")\n";
    $msg .= "✅ 본방 {$단위} : " . newpoint표시($전체보유) . "\n";
    $msg .= "✅ 게임 {$단위} : " . 랭킹_게임냥표시($전체게임냥, '') . "\n";
    if (!function_exists('스왑_1보유냥당_게임냥')) {
      require_once __DIR__ . '/game/swap.inc.php';
    }
    $스왑1냥 = function_exists('스왑_1보유냥당_게임냥_문자열')
      ? 스왑_1보유냥당_게임냥_문자열()
      : 스왑_1보유냥당_게임냥();
    $스왑표시가능 = function_exists('bccomp')
      ? bccomp((string)$스왑1냥, '0', 0) > 0
      : ((float)$스왑1냥 > 0);
    if ($스왑표시가능) {
      $msg .= "💱 1 보유냥 스왑 시 게임냥 : " . 랭킹_게임냥표시($스왑1냥, '') . "\n";
    }

    $선착순커피 = (int)floor($전체보유 * 0.01); // 전체 보유냥(newpoint) 1%
    $msg.= "\n* 커피쏘기 주의사항 *\n나에게 선물하기로 구매\n아영이네 마켓에 올릴 것!";
    $msg.= "\n기존처럼 선착순 선물로 쏘게될시 냥지급 없음";

    // $만원당전체지급 = (int)floor($전체보유 * 0.001); // 전체의 0.1%
    // $msg.= "\n1만원 당 전체인원에게 : " . newpoint표시($만원당전체지급) . "{$단위} (전체의 0.1%)";

    $관리자주급 = (int)floor($전체보유 * 0.005); // 전체의 0.5%
    $일반보급 = (int)floor($전체보유 * 0.005);   // 전체의 0.5%
    $신입지원금 = (int)floor($전체보유 * 0.02); // 전체의 2%
    $신입담당보상 = (int)floor($전체보유 * 0.005); // 전체의 0.5%
    $생일자보상 = (int)floor($전체보유 * 0.10); // 전체 본방냥 10%

    $msg.= "\n\n📋 보상 (현재 기준)";
    $msg.= "\n신입 지원금 2% : " . newpoint표시($신입지원금) . "{$단위}";
    $msg.= "\n신입 담당 보상 0.5% : " . newpoint표시($신입담당보상) . "{$단위} (.색변 시)";
    $msg.= "\n생일자 : " . newpoint표시($생일자보상) . "{$단위}";

    echo 전송($msg);
    exit;
  }else if(strpos($status, 'ㄱㄱ') !== false){
    $msg = "";
    $hour = date('G'); // 현재 시간 (0~23)

    게임제한_차단($두자리닉넴);

    // 7시 이후부터 자정 전까지만 타수 제한 적용
    // if($정보['couple']!=2){
    //   if($오늘타수['cnt'] < $타수제한){
    //       echo 전송("일 타수 {$타수제한} 이상 맞다이 가능\n{$두자리닉넴}의 현재 타수 : {$오늘타수['cnt']}타");
    //       exit;
    //   }
    // }

    // 뒤에 붙은 글자(예: 명륜진사갈비)는 무시하고 ㄱㄱ 숫자 [금액] 부분만 처리
    if (preg_match('/^ㄱㄱ\s+(\d+)(?:\s+(\d+))?/u', trim($status), $match)) {

        $번호 = (int)$match[1];

        // 🔹 먼저 걸린 배팅 확인
        $먼저건것 = db_select("SELECT * FROM tb_battle WHERE status = 0 LIMIT 1");

        if ($먼저건것 && $먼저건것['nyang1'] > 0) {

            $기존내기 = (int)$먼저건것['nyang1'];

            // 금액을 입력했을 경우
            if (isset($match[2]) && $match[2] !== '') {
                $입력내기 = (int)$match[2];

                // ❌ 기존보다 작으면 차단
                if ($입력내기 < $기존내기) {
                    echo 전송("❌ {$기존내기}냥이 걸려있습니다.");
                    exit;
                }

                // 같거나 크면 입력값 사용
                $내기냥 = $입력내기;

            } else {
                // 🔥 금액 미입력 → 기존 금액 자동 사용
                $내기냥 = $기존내기;
            }

        } else {
            // 아직 아무도 안 걸었을 때
            if (!isset($match[2]) || $match[2] === '') {
                echo 전송("❌ 내기 금액을 입력해주세요. 예) ㄱㄱ 4 30000");
                exit;
            }

            $내기냥 = (int)$match[2];
        }

        // 여기부터 $번호, $내기냥 사용



        if ($정보['level'] >= 0) {
            $level = (int)$정보['level'];
            $금액  = (int)$내기냥; // 비교할 금액 변수

            $최대금액 = 0;
            if ($level <= 10) {
                $최대금액 = 200000;
            } else if ($level <= 20) {
                $최대금액 = 400000;
            } else if ($level <= 30) {
                $최대금액 = 600000;
            } else if ($level <= 40) {
                $최대금액 = 800000;
            } else {
                $최대금액 = 0; // 제한 없음
            }

            // 🔒 제한 체크
            if ($최대금액 > 0 && $금액 > $최대금액) {
                echo 전송("❌ 레벨 {$level}에서는 최대 " . number_format($최대금액) . "냥까지만 가능합니다.");
                exit;
            }
        }


        if ($번호 < 1 || $번호 >= 10) {
          echo 전송("최소 1 ~ 10 숫자를 입력해줘!");
          exit;
        }

        if($내기냥 < $깽값){
          echo 전송("🥊맞다이 {$깽값}{$단위} 이상 입력해줘!");
          exit;
        }

        if($정보['point'] < $내기냥){
          $msg = "✅ 맞다이 {$두자리닉넴} 깽값 부족...!\n";
          $msg.= "\n깽값 준비해와..!\n현재 보유 {$정보['point']}{$단위}";
          echo 전송($msg);
          exit;
        }

        $체크 = db_select("select * from tb_battle where status = 0 and nick1 = '{$두자리닉넴}' "); //내가 신청한 상태라면
        if($체크['idx'] > 0){
          $msg = "🥊{$체크['nick1']} 대기중 ".number_format($체크['nyang1']) . "냥";
          echo 전송($msg);
          exit;
        }


        $선신청 = db_select("select * from tb_battle where status = 0 order by regdate limit 1 "); //먼저 신청한 상대의 배팅액확인
        if($내기냥 < $선신청['nyang1']){
          echo 전송("맞다이 뜨고싶으면 {$선신청['nyang1']}{$단위} 준비해와..");
          exit;
        }

        // if($선신청['idx']){
        //   $신청한금액에열배이상 = $선신청['nyang1'] * 10;
        //   if($신청한금액에열배이상 < $내기냥){
        //     echo 전송("최대 깽값은 {$신청한금액에열배이상}냥 이야! 깽값을 수정해줘!");
        //     exit;
        //   }
        // }


        $결과 = db_select("select * from tb_battle where status = 1 order by regdate limit 1");
        if($결과['idx'] > 0){
          $msg = "✅ {$결과['nick1']} vs {$결과['nick2']} 맞짱! 누가이길까? .결투";
          echo 전송($msg);
          exit;
        }

        $숫자1 = rand(0, 9);
        $진행중 = db_select("select * from tb_battle where status = 0 order by regdate desc limit 1 ");
        if(!$진행중['nick1']){
          $msg = "🥊".number_format($내기냥) . "냥 맞다이 뜰사람?";
          $sql = "insert into tb_battle set status = 0, nick1 = '{$두자리닉넴}', number1 = {$번호}, nyang1 = {$내기냥}, addnum1 = {$숫자1}, regdate = now() ";
          $result = db_query($sql);
          db_query("update tb_member set point = point - {$내기냥} where name = '{$두자리닉넴}' ");
          echo 전송($msg);
          exit;
        }

        if($진행중['nick1']!=""){
          if(!$진행중['nick2']){
            $대결 = db_select("select * from tb_battle where status = 0 order by regdate desc limit 1 ");
            $sql = "update tb_battle set status = 1, nick2 = '{$두자리닉넴}', number2 = {$번호}, nyang2 = {$내기냥}, addnum2 = {$숫자1} where idx = {$대결['idx']} ";
            $result = db_query($sql);
            db_query("update tb_member set point = point - {$내기냥} where name = '{$두자리닉넴}' ");

            $진행중 = db_select("select * from tb_battle where status = 1 order by regdate desc limit 1 ");
            if($진행중['idx']){

            $최종값1 = ($진행중['number1'] + $진행중['addnum1']) % 10;
            $최종값2 = ($진행중['number2'] + $진행중['addnum2']) % 10;


              if($최종값1 > $최종값2){ //1이 이김
                $승리닉 = $진행중['nick1'];
                $패배닉 = $진행중['nick2'];
                $승리금액 = $진행중['nyang2'];
                $status = 2;
                지급로그('맞짱-패배', $진행중['nick2'], '', 0, $진행중['nyang2']);
              }
              if($최종값2 > $최종값1){ //2가 이김
                $승리닉 = $진행중['nick2'];
                $패배닉 = $진행중['nick1'];
                $승리금액 = $진행중['nyang1'];
                $status = 2;
                지급로그('맞짱-패배', $진행중['nick1'], '', 0, $진행중['nyang1']);
              }
              $최종지급 = $진행중['nyang1'] + $진행중['nyang2']; //처음에 걸어논 돈도 줘야함

              if($최종값2 == $최종값1){
                $승리닉 = $진행중['nick2'];
                $status = 3;
              }

              if($status==2){
                db_query("update tb_battle set status = 2, win = '{$승리닉}' where idx = {$진행중['idx']} ");
                $msg = "🥊{$진행중['nick1']}({$진행중['addnum1']}) {$최종값1} vs {$진행중['nick2']}({$진행중['addnum2']}) {$최종값2}
    {$승리닉} {$승리금액}{$단위} 획득~🎉";
                $sql = "update tb_member set point = point + {$최종지급} where name = '{$승리닉}' ";
                db_query($sql);
                지급로그('맞짱-승리', $승리닉, $패배닉, 0, $승리금액);

              }else{ // 각 내분의 50% 금고 · 50% 환급
                $냥1 = (int)$진행중['nyang1'];
                $냥2 = (int)$진행중['nyang2'];
                $금고1 = (int)floor($냥1 * 50 / 100);
                $금고2 = (int)floor($냥2 * 50 / 100);
                $환급1 = $냥1 - $금고1;
                $환급2 = $냥2 - $금고2;
                $총금고 = $금고1 + $금고2;

                $msg = "😱{$진행중['nick1']}({$진행중['addnum1']}) {$최종값1} vs {$진행중['nick2']}({$진행중['addnum2']}) {$최종값2}
🤝 무승부!
{$진행중['nick1']} +".number_format($환급1) . "{$단위} (".number_format($금고1) . "{$단위} 금고)
{$진행중['nick2']} +".number_format($환급2) . "{$단위} (".number_format($금고2) . "{$단위} 금고)";

                if ($환급1 > 0) {
                  db_query("update tb_member set point = point + {$환급1} where name = '{$진행중['nick1']}' ");
                }
                if ($환급2 > 0) {
                  db_query("update tb_member set point = point + {$환급2} where name = '{$진행중['nick2']}' ");
                }
                if ($총금고 > 0) {
                  db_query("UPDATE config SET tax = tax + {$총금고} ");
                }

                지급로그('맞짱-무승부', $진행중['nick1'], '', $금고1, $환급1);
                지급로그('맞짱-무승부', $진행중['nick2'], '', $금고2, $환급2);

                db_query("update tb_battle set status = 2, win = '무승부' where idx = {$진행중['idx']} ");
              }

            }else{
              $msg = "진행중인 맞짱 없음!";
            }
          }
        }
        echo 전송($msg);
        exit;
    }

    // $sql = "
    // SELECT *
    // FROM tb_point_log
    // WHERE nick = '{$두자리닉넴}'
    // AND DATE_FORMAT(regdate, '%Y-%m-%d') = '{$오늘}'
    // AND (status = '맞짱-승리' || status = '맞짱-패배' || status = '맞짱-무승부' || status = '맞짱-수수료')
    // ORDER BY regdate DESC limit 15";
    // //LIMIT 15
    // $result = db_query($sql);
    //
    // $msg = "✅ 맞짱 결과\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               ";
    // $msg .= "";
    // while ($row = db_fetch($result)) {
    //     $msg .= $row['status']." ".$row['receiver']." ".$row['point']."{$단위} ".date("H:i", strtotime($row['regdate']))."\n";
    // }
    // echo 전송($msg);
    // exit;
  }else if(stripos($status, '.레벨업') !== false){
    require_once __DIR__ . '/game/lotto_ticket.inc.php';
    $msg  = "📈 레벨업 기준 안내\n\n";
    $msg .= "Lv 0 ~ 10   : 150타당 1레벨 상승\n";
    $msg .= "Lv 11 ~ 30  : 300타당 1레벨 상승\n";
    $msg .= "Lv 31 ~ 100 : 500타당 1레벨 상승\n";
    $msg .= "Lv 101 ~ 150: 1000타당 1레벨 상승\n";
    $msg .= "Lv 151 ~ 200: 2000타당 1레벨 상승\n";
    $msg .= "Lv 201 ~ 300: 3000타당 1레벨 상승\n";
    $msg .= "\n최대 레벨은 300레벨입니다.\n";
    $msg .= "🎁 레벨업 보상: 도달 레벨 숫자만큼 본방냥 (Lv1=1냥, Lv2=2냥 … 여러 단 오르면 합산)\n";
    $msg .= 로또티켓_레벨업_안내문구();
    if (function_exists('레벨업_보호_안내문구')) {
      $msg .= "\n" . 레벨업_보호_안내문구();
    }
    echo 전송($msg);
    exit;

  }else if (strpos($status, '.관리자') !== false) {
    $status_trim = trim($status);
    if (preg_match('/^\.관리자\s+(\S+)/u', $status_trim, $match)) {
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
    // .관리자 만 입력 시 현재 관리자 목록
    if (trim($status) === '.관리자' || preg_match('/^\.관리자\s*$/u', $status_trim)) {
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
    echo 전송("❌ 사용법: .관리자 (목록)\n.관리자 닉네임 (등록)\n예) .관리자 지호");
    exit;

  }else if(strpos($status, '.관리') !== false){
    $msg = "-관리 명령어\n
. 오늘 (오늘타수)
. 주별 (현재주차타수)

. 궁금 닉네임 ( 냥소진❌ )
. 정보 닉네임 ( 냥소진❌ 상황실에서만 사용‼️)

. 닉변 (현재닉) (변경할닉)
. 색변 닉네임 색번호
. 프변 (프변 3일 사용/연장)

. 수호 (받을닉) [개수] — 가장 최근 자숙/제한부터 단축 (수호 1개당 1회)

. 조회 닉네임 템명
. 생성 닉네임 템명 개수(템생성)
. 생성 전체 템명 개수(전원 지급) 예) .생성 전체 1주년기념주화 1
. 사용 닉네임 템명 개수(템사용처리)

. 퇴사 닉네임 (전부 삭제)
‼️보유냥 확인 후 금고처리 후 퇴사처리

. 금고 (퇴사자 보유냥 금액) ➡️ 금고로 귀속
. 금고 ( - 사다리 금액) ➡️ 금고 금액 사용처리
. 지급 닉네임 냥금액 ➡️ 개인 냥 지급
. 지급 전체 냥금액 ➡️ 전체 냥 지급
. 삭감 닉네임 퍼센트 ➡️ 해당 닉 보유 냥의 N% 삭감 (예: .삭감 우주 80)
. 보조금지급 ➡️ 주급·보급 동시 지급 (7일 주기)

. 기록 (강일딜레이시간)
. 신입생성 닉 성별
. 메모 짧은내용 시간

. 평타사다리 (상위명수) (당첨명수)
예) . 평타사다리 10 4
→ 평타 상위 10명 중 4명 랜덤 뽑기

. 강화배정 (닉네임)
. 홀짝배정 (닉네임)
. 지갑 (닉네임)
. 지갑배정 (닉네임)
. 마켓배정 (닉네임)


-자숙/제한 등록방법
예) . 추가 (일방/공커/제한) (닉네임) 
. 추가 일방 닉네임
. 추가 공커 닉네임
. 추가 보룸제한 닉네임
. 추가 채팅제한 닉네임
. 추가 게임제한 닉네임

-일방/강일/지목 등록방법
예) . 등록 일방 영수(임티)영희

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
    // .타수 닉(개인타수체크)
    echo 전송($msg);
    exit;

  }else if (trim($status) === '.보조금지급') {
    if (!in_array($두자리닉넴, $관리자, true)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    $주급결과 = 주급지급_실행();
    $보급결과 = 보급지급_실행();
    $msg = "💰 보조금 지급\n\n";
    $msg .= "【주급】\n" . $주급결과['msg'] . "\n\n【보급】\n" . $보급결과['msg'];
    echo 전송($msg);
    exit;

  }else if(stripos($status, '.관리주소') !== false){
    $msg = "아이템 : http://49.247.160.164/page/item.php?a=1";
    $msg .= "\n프로필 : http://49.247.160.164/page/color.php";

    echo 전송($msg);
    exit;
  }else if(strpos($status, '.관리') !== false){
    $msg = "-관리 명령어\n
. 오늘 (오늘타수)
. 주별 (현재주차타수)
. 타수 닉네임 숫자 (강제타수 추가, -30 입력 시 -30타)

. 궁금 닉네임 ( 냥소진❌ )
. 정보 닉네임 ( 냥소진❌ 상황실에서만 사용‼️)

. 닉변 (현재닉) (변경할닉)
. 색변 닉네임 색번호
. 프변 (프변 3일 사용/연장)

. 수호 (받을닉) [개수] — 가장 최근 자숙/제한부터 단축 (수호 1개당 1회)

. 조회 닉네임 템명
. 생성 닉네임 템명 개수(템생성)
. 생성 전체 템명 개수(전원 지급) 예) .생성 전체 1주년기념주화 1
. 사용 닉네임 템명 개수(템사용처리)

. 퇴사 닉네임 (전부 삭제)
‼️보유냥 확인 후 금고처리 후 퇴사처리

. 금고 (퇴사자 보유냥 금액) ➡️ 금고로 귀속
. 금고 ( - 사다리 금액) ➡️ 금고 금액 사용처리
. 지급 닉네임 냥금액 ➡️ 개인 냥 지급
. 지급 전체 냥금액 ➡️ 전체 냥 지급
. 삭감 닉네임 퍼센트 ➡️ 해당 닉 보유 냥의 N% 삭감 (예: .삭감 우주 80)
. 보조금지급 ➡️ 주급·보급 동시 지급 (7일 주기)

. 기록 (강일딜레이시간)
. 신입생성 닉 성별
. 메모 짧은내용 시간

. 평타사다리 (상위명수) (당첨명수)
예) . 평타사다리 10 4
→ 평타 상위 10명 중 4명 랜덤 뽑기

. 강화배정 (닉네임)
. 홀짝배정 (닉네임)
. 지갑 (닉네임)
. 지갑배정 (닉네임)
. 마켓배정 (닉네임)


-자숙/제한 등록방법
예) . 추가 (일방/공커/제한) (닉네임) 
. 추가 일방 닉네임
. 추가 공커 닉네임
. 추가 보룸제한 닉네임
. 추가 채팅제한 닉네임
. 추가 게임제한 닉네임

-일방/강일/지목 등록방법
예) . 등록 일방 영수(임티)영희

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
    // .타수 닉(개인타수체크)
    echo 전송($msg);
    exit;

  }else if(preg_match('/^\.타수\s+(\S+)\s+(-?\d+)\s*$/u', trim($status), $타수_match)){
    // .타수 닉네임 숫자 → tb_msg에 강제타수 추가 (음수면 -30타 등 차감)
    if (!in_array($두자리닉넴, $관리자)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    $타수_닉 = trim($타수_match[1]);
    $타수_값 = (int)$타수_match[2];
    $타수_닉_esc = addslashes($타수_닉);
    db_query("INSERT INTO tb_msg (nickname, msg, tasu, regdate) VALUES ('{$타수_닉_esc}', '', {$타수_값}, NOW())");
    $부호 = $타수_값 >= 0 ? '+' : '';
    echo 전송("✅ 강제타수 반영\n{$타수_닉} {$부호}{$타수_값}타");
    exit;

  }else if (preg_match('/^\.진우썸초기화\s*$/u', trim($status))) {
    if (!in_array($두자리닉넴, $관리자)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    db_query("DELETE FROM tb_msg");
    db_query("DELETE FROM tb_title_bonus_track");
    db_query("UPDATE tb_member SET title = '', tasu = 0, level = 0");
    db_query("DELETE FROM tb_battle");
    db_query("DELETE FROM tb_question");
    db_query("DELETE FROM tb_lotto_info");
    db_query("DELETE FROM tb_winner");
    db_query("delete from tb_damege");
    db_query("DELETE FROM tb_point_log");
    db_query("DELETE FROM tb_odd_even_log");
    db_query("DELETE FROM tb_odd_even_profit");
    echo 전송("✅ 진우썸 초기화 완료\n전체 회원: title·tasu·level·day_voice_time·voice_time 리셋\n비운 테이블: tb_battle, tb_lotto_info, tb_msg, tb_winner, tb_point_log, tb_odd_even_log, tb_odd_even_profit");
    exit;

  }else if(stripos($status, '.초성초기화') !== false){
    $최근문제 = db_select("SELECT answer FROM tb_question ORDER BY idx DESC LIMIT 1");
    $정답문구 = (isset($최근문제['answer']) && trim($최근문제['answer']) !== '') ? "\n📌 직전 정답: " . trim($최근문제['answer']) : '';
    db_query("UPDATE tb_question SET status = 1 WHERE status = 0");
    echo 전송("✅ 초성 퀴즈 초기화 완료!{$정답문구}");
    exit;

  }


  if (preg_match('/^\.프변(?:\s+(\d+))?\s*$/u', trim($status), $프변m)) {
    $개수 = isset($프변m[1]) ? (int)$프변m[1] : 1;
    if ($개수 < 1) {
        echo 전송("❌ 사용 개수는 1 이상으로 입력해주세요.\n예) .프변 5");
        exit;
    }
    echo 전송(프변_명령_처리($두자리닉넴, $개수));
    exit;
  }
  

  if(strpos($status, '.정산') !== false){

    if (in_array(getTwoCharNick($nick), $관리자1)) {

      $날짜 = date('Y-m-d');
      if (preg_match('/\.정산\s+(\d{4}-\d{2}-\d{2})/u', $status, $matches)) {
            $날짜 = $matches[1]; // 캡처된 날짜
        }

      if($설정['평타냥지급여부']==0){
        $msg = "일 타수 1위~10위 {$단위}보상 지급\n\n";
        $sql = "WITH today_tasu AS (
            SELECT
                nickname,
                SUM(tasu) AS cnt
            FROM tb_msg
            WHERE regdate >= '{$날짜}'
              AND regdate < '{$날짜}' + INTERVAL 1 DAY
              AND nickname NOT IN ('오픈', '신입', '왕벌', '왕남', '', '요원', '지또', '우지')
              AND msg NOT LIKE '%이모티콘을 보냈습니다.%'
            GROUP BY nickname
        )
        SELECT
            (SELECT AVG(cnt) FROM today_tasu) AS 평균타수
        ";
        $data = db_select($sql);
        $sql = "WITH today_tasu AS (
            SELECT
                nickname,
                SUM(tasu) AS cnt
            FROM tb_msg
            WHERE regdate >= '{$날짜}'
              AND regdate < '{$날짜}' + INTERVAL 1 DAY
              AND nickname NOT IN ('오픈', '신입', '왕벌', '왕남', '', '요원', '지또', '우지')
              AND msg NOT LIKE '%이모티콘을 보냈습니다.%'
            GROUP BY nickname
        ),
        stat AS (
            SELECT SUM(cnt) AS total_cnt
            FROM today_tasu
        ),
        ranked AS (
            SELECT
                nickname,
                cnt,
                ROW_NUMBER() OVER (ORDER BY cnt DESC) AS rn
            FROM today_tasu
        )
        SELECT
            r.nickname AS 닉네임,
            r.cnt AS 타수,
            ROUND(r.cnt / s.total_cnt * 100, 2) AS 전체비중퍼센트,
            r.rn AS 순위,
            FLOOR(r.cnt * 0.2) AS 타수_20퍼_정수   -- ← 추가된 부분
        FROM ranked r
        CROSS JOIN stat s
        WHERE r.rn <= 10
        ORDER BY r.rn;
        ";
        $result = db_query($sql);
        foreach($result as $순위){

          if($순위['순위']==1){
            $일타수지급보상 = $순위['타수'] * 20000;
          }else if($순위['순위']==2){
            $이십퍼 = floor($순위['타수'] * 10000);
            $일타수지급보상 = $이십퍼;
          }else if($순위['순위']==3){
            $이십퍼 = floor($순위['타수'] * 5000);
            $일타수지급보상 = $이십퍼;
          }else if($순위['순위']==4){
            $이십퍼 = floor($순위['타수'] * 3000);
            $일타수지급보상 = $이십퍼;
          }else if($순위['순위']==5){
            $이십퍼 = floor($순위['타수'] * 1000);
            $일타수지급보상 = $이십퍼;
          }else if($순위['순위']==6){
            $이십퍼 = floor($순위['타수'] * 800);
            $일타수지급보상 = $이십퍼;
          }else if($순위['순위']==7){
            $이십퍼 = floor($순위['타수'] * 600);
            $일타수지급보상 = $이십퍼;
          }else if($순위['순위']==8){
            $이십퍼 = floor($순위['타수'] * 400);
            $일타수지급보상 = $이십퍼;
          }else if($순위['순위']==9){
            $이십퍼 = floor($순위['타수'] * 200);
            $일타수지급보상 = $이십퍼;
          }else if($순위['순위']==10){
            $이십퍼 = floor($순위['타수'] * 100);
            $일타수지급보상 = $이십퍼;
          }else{
            $이십퍼 = 0;
            $일타수지급보상 = $이십퍼;
          }

          $sql = "update tb_member set point = point + {$일타수지급보상} where name = '{$순위['닉네임']}'\n";
          db_query($sql);

          // 순위별 이모지 결정
          switch ((int)$순위['순위']) {
              case 1:
                  $rankEmoji = "🥇";
                  break;
              case 2:
                  $rankEmoji = "🥈";
                  break;
              case 3:
                  $rankEmoji = "🥉";
                  break;
              default:
                  if ($순위['순위'] <= 10) {
                      $rankEmoji = "🏆";
                  } else {
                      $rankEmoji = "";
                  }
          }

          $msg .= $순위['순위']."등 ".$순위['닉네임']." {$순위['타수']}타 ".number_format($일타수지급보상) . "냥\n";
          지급로그("{$rankEmoji}일보상{$순위['순위']}등", $순위['닉네임'], '', 0, $일타수지급보상);
        }
        $msg.= "\n{$날짜} 고생많으셨습니다.";
        db_query("UPDATE config SET 평타냥지급여부 = 0");
        echo 전송($msg);
        exit;
      }else{
        echo 전송($날짜." 지급완료");
        exit;
      }
    }
  }

  //include "msg.php";
}