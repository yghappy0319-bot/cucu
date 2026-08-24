<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
include_once $_SERVER['DOCUMENT_ROOT']."/api/function.php";

if (function_exists('tb_member_게임포기_컬럼_보장')) {
  tb_member_게임포기_컬럼_보장();
}
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

if (!function_exists('전체냥기준금액')) {
  // function.php 시세 스냅샷 버전과 동일 (레거시 info.php 단독 로드 대비)
  function 전체냥기준금액($percent, $앞두자리만유지 = false) {
    if (function_exists('시세기준_게임냥_문자열')) {
      $전체보유냥 = 시세기준_게임냥_문자열();
    } elseif (function_exists('시세기준_게임냥')) {
      $전체보유냥 = function_exists('냥_정수문자열') ? 냥_정수문자열(시세기준_게임냥()) : (string)시세기준_게임냥();
    } else {
      $row = db_select("SELECT CAST(COALESCE(SUM(GREATEST(CAST(IFNULL(point, 0) AS DECIMAL(40,0)), 0)), 0) AS CHAR) AS total_point FROM tb_member WHERE status = 0");
      $전체보유냥 = function_exists('냥_정수문자열') ? 냥_정수문자열($row['total_point'] ?? 0) : (string)($row['total_point'] ?? 0);
    }
    $비율 = ((float)$percent) / 100;
    if (function_exists('bcmul') && function_exists('bcadd') && function_exists('bccomp')) {
      $raw = bcmul($전체보유냥, sprintf('%.12F', $비율), 12);
      $금액 = bcadd($raw, '0', 0);
      if (bccomp($raw, $금액, 12) > 0) {
        $금액 = bcadd($금액, '1', 0);
      }
      if (bccomp($금액, '1', 0) < 0) {
        $금액 = '1';
      }
      if ($앞두자리만유지 && function_exists('냥_앞두자리_뒤0')
        && bccomp($금액, (string)PHP_INT_MAX, 0) <= 0) {
        return 냥_앞두자리_뒤0((int)$금액);
      }
      if (bccomp($금액, (string)PHP_INT_MAX, 0) <= 0) {
        return (int)$금액;
      }
      return $금액;
    }
    $금액 = (int)ceil((float)$전체보유냥 * $비율);
    $금액 = max(1, $금액);
    if ($앞두자리만유지) {
      $자릿수 = strlen((string)$금액);
      if ($자릿수 > 2) {
        $배수 = (int)pow(10, $자릿수 - 2);
        $금액 = (int)(floor($금액 / $배수) * $배수);
      }
    }
    return $금액;
  }
}

$두자리닉넴 = getTwoCharNick($nick);
$status = $msg;

if (!api_동시요청_락_시작($nick ?? '', $status ?? '')) {
  exit;
}
register_shutdown_function('api_동시요청_락_해제');

include_once __DIR__ . "/_mutual.php";

if($두자리닉넴){
  include_once "config.php";
  if (empty($정보['idx'])) {
    // 오픈채팅봇 공개 조회(.일방/.공커 등)는 비회원 허용
    if (!(function_exists('오픈채팅봇_여부') && 오픈채팅봇_여부()
        && function_exists('오픈채팅봇_공개명령인가') && 오픈채팅봇_공개명령인가($status))) {
      exit;
    }
  }

  if(strpos($status, '.금고란') !== false){
    if (function_exists('config_tax_컬럼_보장')) {
      config_tax_컬럼_보장();
    }
    $tax = function_exists('금고_잔액_조회')
      ? 금고_잔액_조회()
      : (function_exists('냥_정수문자열') ? 냥_정수문자열($설정['tax'] ?? 0) : '0');
    $tax표시 = function_exists('금고_금액표시')
      ? 금고_금액표시($tax, $단위)
      : (function_exists('게임냥_안전표시') ? 게임냥_안전표시($tax, $단위) : $tax . $단위);
    
    $msg = "📦 금고란? 현재 " . $tax표시 . "\n\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
    
    $msg .= "1️⃣ 금고가 뭐야?\n";
    $msg .= "- 우리방 공동 통장 같은 곳이에요.\n";
    $msg .= "- 친구들의 활동에서 빠지는 각종 수수료, 패널티 냥이 여기 모여요.\n\n";
    $msg .= "2️⃣ 금고는 어떻게 채워져?\n";
    $msg .= "- 양도/상점/판매 등에서 빠지는 수수료가 설정에 따라 금고로 들어와요.\n";
    $msg .= "- 무기 .수리 비용의 10% 중 금고 70%, 로또 30%가 들어와요.\n";
    $msg .= "- .금고 시도마다 금고 총액 1%가 입장료로 차감되고\n";
    $msg .= "  그 중 50%가 다시 금고로 환수돼요.\n";
    $msg .= "- 0.1% 확률로 🔥불지옥 → 남은 냥 50%가 금고로 회수돼요.\n";
    $msg .= "- 퇴사 처리 등 일부 관리 명령으로도 남은 냥이 금고로 귀속될 수 있어요.\n\n";
    $msg .= "3️⃣ 금고는 어떻게 털어?\n";
    $msg .= "- 채팅창에 '.금고' 를 입력하면 금고털이를 시도해요.\n";
    $msg .= "- 매 시도 입장료: 금고 총액 1% (50% 소멸 / 50% 금고)\n";
    $msg .= "  냥이 부족하면 시도 불가 (신불자 방지)\n";
    $msg .= "- 1% 확률: 금고 20% 획득\n";
    $msg .= "- 2% 확률: 금고 10% 획득\n";
    $msg .= "- 3% 확률: 금고 5% 획득\n";
    $msg .= "- 0.01% 확률: 👑대도둑! 금고 전액 + 💎대도둑 칭호\n";
    $msg .= "- 0.1% 확률: 🔥불지옥 → 남은 냥 50% 회수(금고)\n";

    echo 전송($msg);
    exit;
  
  
  }else if (preg_match('/^\.벌점\s+(\S+)\s+(-?\d+)/u', trim($status), $m)) {
    $대상닉 = $m[1];
    $점수 = (int)$m[2];
    $대상닉_2자 = getTwoCharNick($대상닉);

    if ($대상닉_2자 === '') {
      echo 전송("❌ 닉네임을 확인해주세요.");
      exit;
    }

    $msg = "{$대상닉_2자}에게 {$점수}점을 부여합니다";
    echo 전송($msg);
    exit;

  }else if (strpos($status, '.지호') !== false) {

    $아이템 = 아이템보유유무($두자리닉넴, "지호");
    if(!$아이템['idx']){
      // 지호 미보유 시 자동구매 1개 시도 (.구매 지호 1과 동일한 가격 계산식)
      $지호아이템 = db_select("select * from tb_item where sname = '지호' ");
      if($지호아이템['idx']){
        $userNyung = (float)($정보['point'] ?? 0);
        $personalMultiplier = 가격계산($userNyung);
        $totalNyung = (float)($전체포인트['total_point'] ?? 0);
        $baseMarketNyung = 10000000; // 1천만 기준
        $marketMultiplier = log10(($totalNyung / $baseMarketNyung) + 1) * 0.1 + 1;
        $marketMultiplier = max(0.9, min($marketMultiplier, 1.2));

        $basePrice = (int)$지호아이템['buy'];
        if ($지호아이템['status']) {
            $finalPrice = $basePrice;
        } else {
            $finalPrice = round($basePrice * $marketMultiplier * (1 + ($personalMultiplier - 1) * 0.5));
            $minPrice = $basePrice * 0.8;
            $maxPrice = $basePrice * 1.5;
            $finalPrice = max($minPrice, min($finalPrice, $maxPrice));
        }
        $최종가격 = ceil($finalPrice / 1000) * 1000;

        if($userNyung >= $최종가격){
          item_bag_add((int)$정보['idx'], $두자리닉넴, '지호', 1);
          db_query("update tb_member set point = point - {$최종가격} where idx = {$정보['idx']} ");
          $정보['point'] = $userNyung - $최종가격;
          $아이템 = 아이템보유유무($두자리닉넴, "지호");
        }else{
          echo 전송("지호 아이템 없음! (자동구매 실패: 보유{$단위} 부족)");
          exit;
        }
      }
    }
    $msg = "";
    if($아이템['idx']){
        item_bag_sub_nick($두자리닉넴, '지호', 1);
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
          $msg.= "►{$두자리닉넴} 지호 사용 타수2배,{$단위}지급2배 양도,판매수수료 1{$단위} 적용 {$연장시간} 까지 ";
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

  
  }else if(strpos($status, '.야바위방법') !== false){

    $msg  = "🎲 야바위(주사위) 게임 방법\n\n";
    $msg .= "1️⃣ 참가 신청\n";
    $msg .= "- 명령어: `.신청 금액`\n";
    $msg .= "- 금액: 숫자 또는 만·억·조·천·백 축약 (예: 1억, 5조, 1조1천1백)\n";
    $msg .= "- 최소 참가금: ".number_format($주사위참가비) . "냥\n";
    if ((int)$주사위최대참가비 > 0) {
      $msg .= "- 최대 참가금: ".number_format($주사위최대참가비) . "냥\n";
    } else {
      $msg .= "- 최대 참가금: 상한 없음 (보유냥까지)\n";
    }
    $msg .= "- 첫 신청자가 금액을 정하면, 이후 참여자는 그 금액 이상만 신청 가능해.\n";
    $msg .= "- 첫 신청자가 금액을 정한 뒤에는, 다음 신청자는 그냥 `ㅅㅊ`만 쳐도 같은 금액으로 자동 참가돼.\n\n";
    $msg .= "2️⃣ 마감 및 게임 시작\n";
    $msg .= "- 최소 2인 이상 신청해야 마감 가능해.\n";
    $msg .= "- `.마감` 입력하면 참가자 모집이 끝나고 게임이 시작돼.\n\n";
    $msg .= "3️⃣ 주사위 굴리기\n";
    $msg .= "- 게임이 시작되면 참가자들은 `ㄷㄹ` 을 3번 입력해.\n";
    $msg .= "- 입력할 때마다 랜덤 점수가 나오고, 가끔 x2 보너스도 붙어.\n\n";
    $msg .= "4️⃣ 승리 조건\n";
    $msg .= "- 3회 모두 끝났을 때, 총점이 가장 높은 친구가 우승이야.\n";
    $msg .= "- 우승자에게는 참가금 총합(일부는 금고 적립) 기준으로 냥이 지급돼.\n\n";
    $msg .= "5️⃣ 참고\n";
    $msg .= "- `.신청` 없이 `ㄷㄹ` 을 치면 참가자가 아니라서 진행이 안 돼.\n";
    $msg .= "- 진행 중 게임 상황은 각자 점수 메시지로 확인하면 돼.";
    echo 전송($msg);
    exit;
  }else if($status==".맞다이방법"){
    $msg = "🥊 맞다이 이용 방법\n\n";
    $msg.= "1️⃣ 기본 개념\n";
    $msg.= "• 서로 같은 깽값(배팅 금액)을 걸고 숫자 승부를 보는 게임이에요.\n";
    $msg.= "• 이긴 사람은 깽값을 모두 가져가고, 비기면 냥 차감돼요.\n\n";
    $msg.= "2️⃣ 신청 방법 (선공)\n";
    $msg.= "• 형식: ㄱㄱ [숫자] [깽값]\n";
    $msg.= "  예) ㄱㄱ 3 1경  → 숫자 3, 1경 깽값으로 맞다이 신청\n";
    $msg.= "  예) ㄱㄱ 4 10만 → 10만냥 (숫자만 쓰면 냥 단위)\n";
    $minDisp = function_exists('냥축약표시') ? 냥축약표시($깽값) : (number_format((int)$깽값) . '냥');
    $msg.= "• 최소 깽값: {$minDisp}\n";
    $msg.= "• 금액: 숫자만=냥 / 단위 붙이면 만·억·조·경 (예: 1경, 5조)\n\n";
    $msg.= "3️⃣ 참여 방법 (후공)\n";
    $msg.= "• 누가 먼저 \"🥊[금액]냥 맞다이 뜰사람?\" 을 띄우면, 같은 깽값 이상으로 ㄱㄱ [숫자] 입력 시 참여돼요.\n";
    $msg.= "• 선공·후공 모두 보유 냥이 깽값보다 적으면 참여할 수 없어요.\n\n";
    $msg.= "4️⃣ 진행 & 결과\n";
    $msg.= "• 각자 입력한 숫자(1~9)에 시스템이 랜덤 숫자를 더해 최종 숫자를 만들어요.\n";
    $msg.= "• 최종 숫자가 더 큰 사람이 승리, 같으면 무승부입니다.\n";
    $msg.= "• 승리 시 두 사람이 걸어둔 깽값 전부를 가져가요.";
    echo 전송($msg);
    exit;

  }else if($status==".공질"){
  $msg = "🤍 닉네임•키 :
🤍 지역(시•군) :
🤍 프리(낮프•밤프•올프) :
🤍 기•미•돌 :
🤍 MBTI :
🤍 나의 매력 :
🤍 뭐 타고 왔어? :
";
    echo 전송($msg);
    exit;
 
  }else if($status==".등장" || $status=="등장"){
    $타이틀 = trim($정보['title'] ?? '');
    $내템 = trim($정보['item'] ?? '');
    $내강 = (int)($정보['enhance'] ?? 0);
    $오강이상 = ($내템 !== '' && $내강 >= 7);
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
  }else if($status==".샘플"){
  $msg = "🤍 닉네임•키 : 본인 닉네임•키
🤍 지역(시•군) : 사는곳
🤍 프리(낮프•밤프•너프) :
  낮에•밤에•너에게 언제든 프리
🤍 기•미•돌 : 기혼•미혼•돌싱
🤍 MBTI :
🤍 나의 매력 : 본인의매력,자기소개";
    echo 전송($msg);
    exit;
  }else if($status==".제발"){
    $msg = "쉿! 신입에게 집중해죠🫶🏻

1. 안녕~ 인사 먼저해줘
2. 프로필은 1번 노란색!
3. 닉네임 변경 (닉+성별)

1,2,3번 순서대로 해보자!!
";
    echo 전송($msg);
    exit;
  }else if(preg_match('/^\.미새모드\s+(on|off)$/u', trim($status), $m)){
    if ($m[1] === 'on') {
      echo 전송("미세먼지 제가 모드가 활성화 되었습니다. 공기청정을 시작합니다");
    } else {
      echo 전송("미세먼지 제거 모드가 종료되었습니다.");
    }
    exit;
  }else if($status==".신입"){
    // 실시간 본방냥 기준 신입 해제 보상(3%)
    $신입해제보상 = function_exists('신입지원금_계산')
      ? 신입지원금_계산()
      : (int)floor((float)전체보유newpoint합계() * 0.03);
    $예상보상문구 = function_exists('newpoint표시')
      ? newpoint표시($신입해제보상)
      : (콤마삽입($신입해제보상) . "{$단위}");

    $msg = "🐥신입기간🐥
24시간 동안은 강제일방, 일방
연구실(공질) 불가🙅🏿‍♂️

얼공,보룸은 가능해🙆‍♂️
보이스룸은 관을 포함해
총 3명 이상 시 이용가능!

🙅🏿‍♂️타수제한은 없어!
🚫자삭 절대 금지🚫
출근퇴근은 시닙기간
끝나야 가능해!

우리방은 매일 출석 미션이 있어!
100타 달성 시 자동 출석완료돼!

우리방은 .미션 이라고 입력해서
미션 수행을 해야만
일방을 갈 수 있어!

우리방 시스템 중
냥이라는 게 있어!
채팅 1개당 0.1냥 누적돼!

24시간 동안 대화가 1건도 없는경우
내보내기 처리되니 인사는 하고 살자!

24시간 동안은 출퇴 불가야!
적응기간 끝나면 출퇴 가능해!

프로필 색은 24시간이 지나면
관리자가 연구실로 불러줄게!

프로필 이모티콘은 아이템을 사용한거고
공커는 프로필색으로 알아볼 수 있어!
";
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

  // }else if(strpos($status, '.출석') !== false || strpos($status, 'ㅊㅅ') !== false || strpos($status, '찰싹') !== false ) {
  //   echo 전송("점검중이야요");
  //   exit;
  //   include "attendance.php";
  //   exit;

  }else if($status==".연두"){

    $sql = "SELECT count(*) as cnt FROM tb_member AS m
    LEFT JOIN tb_attendance AS a
    ON m.name = a.nickname AND a.regdate = CURDATE()
    WHERE a.nickname IS NULL and m.status = 0";
    $출석자 = db_select($sql);
    if($출석자['cnt']>0){
      $sql = "SELECT m.*
      FROM tb_member AS m
      LEFT JOIN tb_attendance AS a
        ON m.name = a.nickname
        AND a.regdate = CURDATE()
      WHERE a.nickname IS NULL and m.status = 0";

      $result = db_query($sql);

      $html = "✅ 연락두절 {$오늘}\n\n";
      while ($row = db_fetch($result)) {
        $html .= $row['name'] . " ";
      }

      $html.= "\n\n100타 달성 시 자동 출석완료!";
      $html.= "\n‼️전원 출석시 ".콤마삽입($설정['누적출석냥'])."{$단위} 전체 지급";
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
        db_query("update tb_member set point = point + {$설정['누적출석냥']}");

        $html= "\n\n전원 출석🎉 ".number_format($설정['누적출석냥']) . "냥 지급완료!";


        $추가 = $설정['누적출석냥'] + $누적출석냥;
        db_query("update config set 지급확인 = 1, 누적출석{$단위} = {$추가} ");

        $설정 = db_select("select * from config ");
        $html.= "\n내일 전체출석시 {$설정['누적출석냥']}{$단위} 지급";

        $html = trim($html);
        echo 전송($html);
        exit;
      }else{
        $기본출석냥 = $설정['누적출석냥'] - 5000;
        echo 전송("{$오늘} 전체출석냥 지급완료🎉");
        exit;
      }
    }

  }else if($status==".인원" || $status==".솔로"){
    echo 전송(인원목록_메시지($status === '.솔로'));
    exit;

  }else if($status==".버프타수"){
    $msg = ".버프타수 안내\n\n";
    $msg .= "1) `.구매 지호 1` 이렇게 쳐서 지호 1개 사.\n";
    $msg .= "2) 그 다음 `.지호` 입력하면 타수 2배로 올라가.\n\n";
    $msg .= "이렇게 하면 공창 말풍선 1개당 2타로 쳐줘.\n";
    $msg .= "타수는 최대 3배까지 가능하고, 3배는 마법 무기 가진 애들한테 시전 받아야 해.\n\n";
    $msg .= "내 버프 끝나는 시간은 `.버프` 입력하면 확인돼.\n";
    $msg .= "그리고 타수로 받은 랜덤박스는 `/게임방에가서` `.ㄹㄷ` 치면 게임냥만 받아.";
    echo 전송($msg);
    exit;

  }else if($status==".버프"){
    require_once __DIR__ . '/buff.inc.php';
    버프_명령_처리($status, $두자리닉넴, (array)($관리자 ?? []));

  ////////
}else if (trim($status) === '.양도왕') {

  $rows = db_query("
    SELECT nick,
           COUNT(*) AS cnt,
           SUM(COALESCE(tax,0) + COALESCE(point,0)) AS total_sent
    FROM tb_point_log
    WHERE status = '양도'
    GROUP BY nick
    ORDER BY total_sent DESC, cnt DESC
    LIMIT 20
  ");

  $msg = "🏆 양도왕 랭킹 🏆\n\n";
  $rank = 1;
  $any = false;
  while ($r = db_fetch($rows)) {
    $any = true;
    $nick2 = (string)($r['nick'] ?? '');
    $cnt = (int)($r['cnt'] ?? 0);
    $total = (int)($r['total_sent'] ?? 0);
    $msg .= "{$rank}등 {$nick2} : {$cnt}회, 총 ".number_format($total) . "냥\n";
    $rank++;
  }

  if (!$any) {
    $msg = "❌ 양도 내역이 없습니다.";
  }

  echo 전송(rtrim($msg, "\n"));
  exit;

  }else if (strpos($status, '.양도') !== false) {

    $보낼양 = 50;
    $수수료 = function_exists('양도수수료계산') ? 양도수수료계산(0, $보낼양) : max(1, (int)round($보낼양 * 0.01));
    $fail = "✅ {$단위} 양도\n\n.양도 받을닉 양도할 {$단위}수량";
    $fail.= "\n양도금액의 1% 수수료 (보낸 금액에서 차감 · 레벨 무관)";
    $fail.= "\n\n-유의사항";
    $fail.= "\n본방냥·게임냥 합쳐 하루 1회 무료";
    $fail.= "\n지호 적용 시 남은 시간(1시간)마다 추가 양도 가능 (추가 1회당 지호 -1시간)";
    $fail.= "\n지호 없으면 마법 버프 시간으로 추가 양도 가능 (추가 1회당 마법 -1일)";
    $fail.= "\n지호·마법 모두 없으면 추가 양도 불가";
    $fail.= "\n50{$단위} 전송시 수수료{$수수료}{$단위}을 제외한 ".($보낼양-$수수료)."{$단위} 양도됨";

    if (preg_match('/\.양도\s*(.+)/u', $status, $match)) {
        $after = trim($match[1]); // ".양도" 뒤 텍스트 추출

        // "도하 5" 형태인지 검사 + 그룹 분리
        if (preg_match('/^([가-힣A-Za-z]+)\s*(\d+)$/u', $after, $parts)) {
          //$수수료 = $계급['tax'];
          $받는이 = $parts[1]; // 도하
          $보내는양  = (int)$parts[2];
          $수수료 = function_exists('양도수수료계산') ? 양도수수료계산(0, $보내는양) : max(1, (int)round($보내는양 * 0.01));

          $양도제한 = function_exists('양도_일일제한_검사') ? 양도_일일제한_검사($두자리닉넴) : null;
          if ($양도제한 !== null) {
            echo 전송($양도제한);
            exit;
          }

          $지호소비 = function_exists('양도_지호추가양도_해당') && 양도_지호추가양도_해당($두자리닉넴);

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

          if(!$정보['point']){
            $msg = $호칭." {$두자리닉넴} 보유 포인트 없음";
            echo 전송($msg);
            exit;
          }

          $수수료제외 = $보내는양 - $수수료;
          if($수수료제외 < 1){
            echo 전송("양도 금액이 너무 적습니다.");
            exit;
          }
          if($정보['point'] < $보내는양){
            $msg = "✅ 보유 포인트 부족\n";
            $msg.= $호칭." {$두자리닉넴}\n보유{$단위} : {$정보['point']}{$단위}\n양도 요청 : {$보내는양}{$단위}";
            echo 전송($msg);
            exit;
          }else{
            db_query("update tb_member set point = point - {$보내는양} where name = '{$두자리닉넴}' ");
            db_query("update tb_member set point = point + {$수수료제외} where name = '{$받는이}' ");
            db_query("UPDATE config SET tax = tax + {$수수료} ");
            $지호차감문구 = ($지호소비 && function_exists('양도_추가양도_소비')) ? 양도_추가양도_소비($두자리닉넴) : '';
            지급로그('양도', $두자리닉넴, $받는이, $수수료, $실제받는양);
            지급로그('수수료', $두자리닉넴, '', $수수료, $수수료);
            $msg = "💰💰💰💰양도 알림💰💰💰💰\n보낸이 : {$두자리닉넴} ".콤마삽입($보내는양)."{$단위} (수수료 {$수수료}{$단위})\n받는이 : {$받는이} ".콤마삽입($수수료제외)."{$단위}";
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

  

  }else if (function_exists('아이템사용방법_명령인가') && 아이템사용방법_명령인가($status)) {
    echo 전송(아이템사용방법_문구());
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
• 자숙 1일 단축 
  (받는 사람 기준 1일 1회로 제한)
• 보이스룸 이용제한 3시간 단축
• 채팅금지(이모티콘 포함) 3시간 단축
• 게임 이용제한 3시간 단축";

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
# 본인 포함 특정인(단, 이성끼리) 2명 지목
# 일방, 공커중인 상대끼리 지목가능
# 지목인들끼리 원하지 않을 경우 효과상실 (재사용불가)
# 지목이벤트는 진행되나 활동여부는 피지목자들의 몫
# 지목이벤트 중 일방신청으로 인해 일방진행시 지목이벤트 종료";

  $아이템설명['지호'] = "💦 지호 (3시간)
• 타수2타 인정
• 오늘 양도 1회 무료 · 지호 1시간마다 추가 양도 1회 (추가 시 지호 -1시간 · 없으면 마법 -1일)
  아이템 판매 수수료 15%";

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
        $msg = "🔫 아이템 상세설명 🔫\n\n" . implode("\n\n", $아이템설명);


        $msg.= "\n\n- 고정적으로 주 1회 사다리 타기로 랜덤 아이템 지급
- 보유아이템 조회 및 사용이력은 /신청방 으로 올 것

🚫 주의 사항
• [선물]를 사용하여 선물하는것 외 템 양도 불가
• 제한-채금 이행안할시 말풍선 1개당 3분씩 추가";
    }



    echo 전송($msg);
    exit;
  }else if(strpos($status, '.궁금') !== false){

    if($정보['point'] < 0){
      exit;
    }

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

          if($두자리닉넴 != $닉네임){
            db_query("insert into tb_curious set nickname = '{$두자리닉넴}', youname = '{$닉네임}' ");
          }

          $sql = "select * from tb_member where name = '{$닉네임}' ";
          $받는친구 = db_select($sql);
          if(!$받는친구['idx']){
            echo 전송($닉네임." 없음.");
            exit;
          }
 

          $sql = "SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(content, '\n', 2), '\n', -1) AS second_line FROM tb_member WHERE name = '{$닉네임}' ";
          $지역 = db_select($sql);
          $사는곳 = preg_replace('/[🥕🍭❄️🤍]\s*지역\(시•군\)\s*:\s*/u', '', $지역['second_line']);

          $계급 = 계급($받는친구['point']);
          if($받는친구['title']){
            $호칭 = $받는친구['title'];
          }else{
            $호칭 = $계급['name'];
          }

          $msg = "Lv {$받는친구['level']} {$호칭} {$받는친구['name']}";
          $msg.= "\n지역 : {$사는곳}";
            // 갚아야 할 냥이 있는 경우(마이너스) 프로필은 열지 않고 갚아야할 냥만 표시
            if ((int)$받는친구['point'] < 0) {
            $msg.= "\n\n신용이 불량한 관계로\n정보를 볼 수 없습니다.\n빠른 시일내 상환하여 주십시오.";
            $갚아야할냥 = abs((int)$받는친구['point']);
            $msg.= "\n갚아야 할 냥 : -".number_format($갚아야할냥) . "냥";
            $msg.= "\n\n개인적으로 도와주시는 방법\n.양도 {$받는친구['name']} 금액";
            echo 전송($msg);
            exit;
          }
        

          $sql = "select 
          " . 생타_SQL_select_expr('msg') . " as cnt2,
          " . 버프타_SQL_select_expr('msg', 'tasu') . " as cnt 
          from tb_msg 
          where nickname = '{$닉네임}' AND 
          tasu != 0 AND
          regdate >= CURDATE() AND 
          regdate < CURDATE() + INTERVAL 1 DAY ";
          $타수 = db_select($sql);

          $msg.= "\n타수 : " . (int)($타수['cnt2'] ?? 0) . "/" . (int)($타수['cnt'] ?? 0) . "타";
          $내무기 = trim($받는친구['item'] ?? '');
          $내강 = (int)($받는친구['enhance'] ?? 0);
          $오강이상 = ($내무기 !== '' && $내강 >= 5);
          $스타일 = trim($받는친구['style'] ?? '');
          if ($내무기 === '') {
            $msg .= "\n무기 : 없음";
          } elseif ($오강이상 && $스타일 !== '') {
            $msg .= "\n무기 : +{$내강} {$스타일} {$내무기}\n";
          }

          // 오늘 일자 기준 수입/지출 로그 (타수냥, 럭키단어, 주사위, 일보상, 출석)
          $조회일자 = date('Y-m-d');
      
          $sql = "SELECT status, sum(point) as point, sum(bonus) as bonus FROM tb_point_log WHERE nick = '{$닉네임}' AND DATE_FORMAT(regdate, '%Y-%m-%d') = '{$조회일자}' AND status LIKE '%럭키단어%'";
          $럭단 = db_select($sql);
          if (!empty($럭단['point']) && (int)$럭단['point'] > 0) {
            $msg .= "\n⭐️럭키단어 : ".number_format($럭단['point']) . "냥";
          }

          $일일타수보상 = db_select("SELECT * FROM tb_point_log WHERE nick = '{$닉네임}' AND DATE_FORMAT(regdate, '%Y-%m-%d') = '{$조회일자}' AND status LIKE '%일보상%'");
          if (!empty($일일타수보상['point']) && (int)$일일타수보상['point'] > 0) {
            $msg .= "\n{$일일타수보상['status']} ".number_format($일일타수보상['point']) . "냥";
          }
          
          $result = db_query("SELECT * FROM tb_point_log WHERE nick = '{$닉네임}' AND DATE_FORMAT(regdate, '%Y-%m-%d') = '{$조회일자}' AND status LIKE '%출석%'");
          while ($row = db_fetch($result)) {
            $msg .= "\n{$row['status']} ".냥_짧게($row['point']);
          }

          $sql = "SELECT status, sum(point) as point, sum(bonus) as bonus FROM tb_point_log WHERE nick = '{$닉네임}' AND DATE_FORMAT(regdate, '%Y-%m-%d') = '{$조회일자}' AND status LIKE '%주사위-신청%'";
          $주사위신청 = db_select($sql);
          if (!empty($주사위신청['point']) && (int)$주사위신청['point'] > 0) {
            $msg .= "\n🎲주사위신청 : -".number_format($주사위신청['point']) . "냥";
          }
          $sql = "SELECT status, sum(point) as point, sum(bonus) as bonus FROM tb_point_log WHERE nick = '{$닉네임}' AND DATE_FORMAT(regdate, '%Y-%m-%d') = '{$조회일자}' AND status LIKE '%주사위%우승%'";
          $주사위우승 = db_select($sql);
          if (!empty($주사위우승['point']) && (int)$주사위우승['point'] > 0) {
            $msg .= "\n🎲주사위승리 : ".number_format($주사위우승['point']) . "냥";
          }

          // 활/단소 타수 조정은 msg='' 로 쌓이므로 실제 채팅만 표시
          $마지막카톡 = db_select("select msg, regdate from tb_msg where nickname = '{$닉네임}' AND TRIM(IFNULL(msg, '')) <> '' order by regdate desc limit 1 ");
          if (!empty($마지막카톡['regdate'])) {
          $last = $마지막카톡['regdate']; // 예: "2025-11-18 14:20:00"

          // DateTime 객체로 변환
          $lastTime = new DateTime($last);
          $now = new DateTime();
          // 시간 차이 계산
          $diff = $now->diff($lastTime);
          // 시간 + 분 계산
          $totalHours = ($diff->days * 24) + $diff->h; // 총 몇 시간
          $minutes = $diff->i; // 남은 분


          $msg.= "\n\n💬 마지막 메시지"."\n{$마지막카톡['msg']}\n({$totalHours}시간 {$minutes}분 전)";
          } else {
          $msg.= "\n\n💬 마지막 메시지\n(채팅 기록 없음)";
          }

          echo 전송($msg);
          exit;
        }
    }
    echo 전송(".궁금 친구닉네임");
    exit;

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
▫️일방 3일 / 공커 5일
▫️거절시 1일 셀프자숙 * 자숙과 동일하나
	보이스룸만 이용가능
▫️위 사항 적발시 3시간 간격으로 자숙기간 연장");
    exit;
  }else if(strpos($status, '.보룸공지') !== false){
    echo 전송("▪️ 보이스룸

▫️관을 포함해 총 3명 이상 시 이용가능
  (1인•2인인경우 이용 불가)
▫️보룸에서만 나이 공개
▫️음주 후 보이스룸 이용 자제
▫️보룸에서 나눈 대화 공창유출금지
▫️일방•공커 상대와 있었던 일 공유금지
▫️연락처•카톡아이디 공유시 이유불문 킥");
    exit;
  }else if(strpos($status, '.일방공지') !== false){
    echo 전송("▪️일방 및 공커

▫️건의방에 신청
▫️일방기간 : 7일 / 추가 연장 1회(7일) 가능
▫️일벙날짜가 결정되면 한 명이 연구실에 날짜와 대략적인 시간대 공지
① 일벙인증 : 두 사람 손하트 🫰🏻후 공창
② 공커인증 : 두 사람 손깍지 🤝🏻 후 공창");
    exit;

  }else if( strpos($status, '.진행') !== false ){

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

$msg = "❤️공지\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";

  $msg .= "

▪️ 공통

▫️신입기간 : 입장 후 24시간
/ 24시간 이후 연구실•공질•일방•강제일방 가능

▫️출퇴시 ”.퇴근 본인닉네임“ 공창에 입력
/ 48시간 내 미복귀시 공질•아이템•냥 자동삭제

▪️ 보이스룸

▫️관을 포함해 총 3명 이상 시 이용가능
  (1인•2인인경우 이용 불가)
▫️보룸에서만 나이 공개
▫️음주 후 보이스룸 이용 자제
▫️보룸에서 나눈 대화 공창유출금지
▫️일방•공커 상대와 있었던 일 공유금지
▫️연락처•카톡아이디 공유시 이유불문 킥

▪️일방 및 공커

▫️건의방에 신청
▫️일방기간 : 7일 / 추가 연장 1회(7일) 가능
▫️일벙날짜가 결정되면 한 명이 연구실에 날짜와 대략적인 시간대 공지
① 일벙인증 : 두 사람 손하트 🫰🏻후 공창
② 공커인증 : 두 사람 손깍지 🤝🏻후 공창

▪️ 자숙

▫️보이스룸•얼공•채굴•소통방•게임 금지
▫️자숙기간동안 공창에서 어필•플러팅 금지
▫️자숙중인 상대에게도 플러팅 금지
▫️일방 3일 / 공커 5일
▫️거절시 1일 셀프자숙 * 자숙과 동일하나
	보이스룸만 이용가능
▫️위 사항 적발시 자숙기간 연장(최대 1일)


▪️ 금지항목

▫️공창 자삭 절대 금지!
▫️여미새•남미새•여왕벌•불편러
▫️공창 자삭•타인이 불편해하는 언행
▫️투방•바커 (적발시 킥)
▫️공창 및 보룸에서 개인정보 유출
▫️공커 졸업시 한 명만 놀러오는 경우

▪️기타

▫️개인사정이 있을시 연구실에 전달 후
     7일 장기퇴근 가능
     일방중인 경우 장기퇴근 불가, 일반 출퇴 적용

여기까지 읽었으면..
공창에 .확인공지 명령어를 입력해보자!";
  echo 전송($msg);
  exit;

}else if(strpos($status, '.차단해제') !== false){

  if (preg_match('/\.차단해제\s*(.+)/u', $status, $match)) {
    $after = trim($match[1]); // ".양도" 뒤 텍스트 추출
    echo 전송("{$after} 차단 해제🎉");
    exit;
  }

}else if(strpos($status, '.차단') !== false){

  if (preg_match('/\.차단\s*(.+)/u', $status, $match)) {
    $after = trim($match[1]); // ".양도" 뒤 텍스트 추출
    echo 전송("{$after} 차단 완료🎉");
    exit;
  }
}else if(strpos($status, '.진하발') !== false){

  // .진하발 1~5
  $단계 = 5;
  if (preg_match('/\.진하발\s*([1-5])/u', $status, $m)) {
    $단계 = (int)$m[1];
  }
  $단계라벨 = [
    1 => '💣 전쟁',
    2 => '⚠️ 위험',
    3 => '🔶 경계',
    4 => '😈 도발',
    5 => '🟢 안전',
  ];
  $라벨 = $단계라벨[$단계] ?? '안전';
  echo 전송("현재 진하발 {$단계}단계({$라벨}) 입니다.");
  exit;

}else if(strpos($status, '.레상발') !== false){

  // .레상발 1~5
  $단계 = 5;
  if (preg_match('/\.레상발\s*([1-5])/u', $status, $m)) {
    $단계 = (int)$m[1];
  }
  $단계라벨 = [
    1 => '💣 전쟁',
    2 => '⚠️ 위험',
    3 => '🔶 경계',
    4 => '😈 도발',
    5 => '🟢 안전',
  ];
  $라벨 = $단계라벨[$단계] ?? '안전';
  echo 전송("현재 레상발 {$단계}단계({$라벨}) 입니다.");
  exit;
 }else if(strpos($status, '.확인공지') !== false){
    $회원 = db_select("SELECT idx, notichk FROM tb_member WHERE name = '{$두자리닉넴}' ");
    if (!$회원['idx']) {
      echo 전송("회원 정보를 찾을 수 없어요.");
      exit;
    }

    미션완료_기록_if_new($두자리닉넴, '공지', '공지끝까지읽기', true);

    if ((int)$회원['notichk'] === 0) {
      db_query("UPDATE tb_member SET point = point + 1000000, notichk = 1 WHERE idx = {$회원['idx']} ");
      echo 전송("{$두자리닉넴} 공지 확인 완료🎉 (100만냥 지급 완료)");
    } else {
      echo 전송("{$두자리닉넴} 이미 공지 확인 완료");
    }
    exit;

  }else if(strpos($status, '.모금') !== false){
    $최소모금 = 전체냥기준금액(0.01, true);
    $모금파싱 = function_exists('모금_명령_파싱') ? 모금_명령_파싱($status) : ['nick' => null, 'amount_text' => ''];
    $nick = $모금파싱['nick'];
    $금액텍스트 = $모금파싱['amount_text'];

    if ($금액텍스트 !== '') {
      if (empty($nick) || !empty($모금파싱['need_nick'])) {
        echo 전송("❌ 자숙자 닉을 지정해주세요.\n예) .모금 가니 10000");
        exit;
      }

      if (function_exists('냥_정수_미만') ? 냥_정수_미만($정보['point'] ?? 0, $최소모금) : ((float)($정보['point'] ?? 0) < (float)$최소모금)) {
        $보유표시 = function_exists('냥축약표시') ? 냥축약표시($정보['point']) : number_format((float)($정보['point'] ?? 0)) . "냥";
        echo 전송("냥 부족! 보유 {$보유표시}");
        exit;
      }

      $검증 = function_exists('모금_입력검증')
        ? 모금_입력검증($금액텍스트, $정보['point'] ?? 0, $최소모금)
        : ['ok' => false, 'msg' => '❌ 모금 검증을 사용할 수 없어요.'];
      if (empty($검증['ok'])) {
        echo 전송((string)($검증['msg'] ?? '❌ 모금 금액을 확인해주세요.'));
        exit;
      }
      $금액 = (string)($검증['금액'] ?? '0');

      if (function_exists('모금_대상단가_검증')) {
        $단가검증 = 모금_대상단가_검증($금액, $nick);
        if (empty($단가검증['ok'])) {
          echo 전송((string)($단가검증['msg'] ?? '❌ 모금 금액이 부족해요.'));
          exit;
        }
      }

      if (function_exists('모금_자숙단축_적용')) {
        $결과 = 모금_자숙단축_적용($두자리닉넴, $금액, $nick);
        echo 전송((string)($결과['msg'] ?? '모금 처리 완료'));
        exit;
      }

      echo 전송('❌ 모금 처리를 사용할 수 없어요.');
      exit;
    }

    $msg = function_exists('모금_도움말_문구')
      ? 모금_도움말_문구($최소모금)
      : ("금액: 숫자 또는 만·억·조·경·천경·해\n최소 모금: " . (function_exists('냥축약표시') ? 냥축약표시($최소모금) : number_format((float)$최소모금) . '냥'));
    echo 전송($msg);
    exit;

  }else if(strpos($status, '.선물') === 0){
    require_once __DIR__ . '/gift_chat.inc.php';
    선물_명령_처리($status, $두자리닉넴, isset($정보) && is_array($정보) ? (array)$정보 : []);
    exit;

  }else if (preg_match('/^\.(주가|주식|매수|매도)(\s|$)/u', trim($status))) {
    if (!function_exists('아이템주식_채팅명령_시도')) {
      require_once __DIR__ . '/item_stock_market.inc.php';
    }
    if (아이템주식_채팅명령_시도($status, $두자리닉넴, $정보, isset($단위) ? $단위 : '냥')) {
      exit;
    }

  }else if(strpos($status, '.구매') !== false){
    // 주식 시세/매수 우선 (.구매 / .구매 강일 / .구매 강일 1)
    if (!function_exists('아이템주식_채팅명령_시도')) {
      require_once __DIR__ . '/item_stock_market.inc.php';
    }
    if (아이템주식_채팅명령_시도($status, $두자리닉넴, $정보, isset($단위) ? $단위 : '냥')) {
      exit;
    }
    // 시세표(.구매) · 단가조회(.구매 템)는 허용 · 수량 구매만 차단 가능
    if (function_exists('아이템_구매판매_채팅_차단중') && 아이템_구매판매_채팅_차단중()
      && function_exists('아이템_구매실행_요청인가') && 아이템_구매실행_요청인가($status)) {
      echo 전송(아이템_구매실행_차단_메시지());
      exit;
    }
    $msg = "";

    // 관리자가 ".구매 닉네임" 형태로 입력하면 해당 닉네임 기준 상점 정보 조회
    // (아이템명이 아닌 회원닉 · 수량 없는 조회)
    if (preg_match('/^\.구매\s+([^\s]+)\s*$/u', trim($status), $m_admin)) {
      $조회닉 = $m_admin[1];
      $조회_esc = addslashes($조회닉);

      // 아이템이면 단가 조회 (관리자 닉조회보다 우선)
      $아이템행 = db_select("SELECT * FROM tb_item WHERE sname = '{$조회_esc}' AND buystatus = 0 LIMIT 1");
      if (!empty($아이템행['idx'])) {
        $단가 = function_exists('아이템_구매시세_단가')
          ? 아이템_구매시세_단가($조회닉, $아이템행)
          : (string)($아이템행['buy'] ?? '0');
        $단가표시 = function_exists('구매가_축약표시')
          ? 구매가_축약표시($단가, $단위)
          : (number_format((float)$단가) . $단위);
        echo 전송("🛒 【{$조회닉}】\n매수  {$단가표시}\n\n구매하려면: .구매 {$조회닉} 1");
        exit;
      }

      // 관리자만 닉 기준 상점 조회
      if (in_array($두자리닉넴, $관리자)) {
        $대상정보 = db_select("SELECT * FROM tb_member WHERE name = '{$조회_esc}' ");
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
      echo 전송("❌ 사용법:\n.구매 아이템이름 (시세)\n.구매 아이템이름 수량 (구매)\n예) .구매 강일\n예) .구매 강일 1");
      exit;
    }

    if (preg_match('/^\.구매\s+([가-힣A-Za-z]+)\s+(\d+)\s*$/u', trim($status), $match)) {
        $아이템명 = $match[1];                 // 지호
        $수량 = (int)$match[2];
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
            $msg .= "►[".$두자리닉넴."] {$item['sname']} {$수량}개 구매 ".number_format($최종가격) . "냥 ";
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

  }else if(strpos($status, '.퇴근') !== false || strpos($status, '.장퇴') !== false){
    $msg = "✅ 퇴근자 명단\n\n";
    $result = db_query("SELECT *
    FROM tb_work
    ORDER BY
        CASE
            WHEN status = '퇴근' THEN 1
            WHEN status = '장퇴' THEN 2
            ELSE 3
        END,
        regdate DESC");
    for($i=0;$row=db_fetch($result);$i++){
      $시각 = function_exists('퇴근_표시시각') ? 퇴근_표시시각($row) : date("m-d H:i", strtotime($row['regdate']));
      $msg.= $row['nick']." ".$시각."\n";
    }
    $msg.= "\n퇴근인원 총 {$i}명\n";

      if (preg_match('/\.(퇴근|장퇴)\s*([^\s]+)/u', $status, $match)) {
          $명령 = $match[1];
          $두자리닉넴 = $match[2];

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
            if($명령=="퇴근"){
              $타임 = 48;
              $이틀 = date("Y-m-d H:i", strtotime("+{$타임} hours"));
            }else if($명령=="장퇴"){
              $타임 = 168;
              $이틀 = date("Y-m-d H:i", strtotime("+{$타임} hours"));
            }

            if (function_exists('회원_퇴근_채굴정지')) {
              회원_퇴근_채굴정지($두자리닉넴);
            }
            if (function_exists('퇴근_등록_쿼리')) {
              $sql = 퇴근_등록_쿼리($명령, $두자리닉넴, $이틀);
            } else {
              $sql = "insert into tb_work set status = '{$명령}', nick = '{$두자리닉넴}', regdate = '{$이틀}'  ";
            }
            $result = db_query($sql);
            db_query("update tb_member set status = 3 where name = '{$두자리닉넴}' "); //퇴근
            if($result){
              $cnt_row = db_select("SELECT COUNT(*) AS cnt FROM tb_work");
              $퇴근인원 = (int)($cnt_row['cnt'] ?? 0);
              $msg = "{$두자리닉넴} 퇴근등록🚌\n{$타임}시간 내 돌아오자.\n";
              $msg.= "\n퇴근인원 총 {$퇴근인원}명";
              $msg.= "\n-미복귀시\n공질•아이템•{$단위} 자동삭제";
              $msg.= "\n\n우리방 검색어\n[ 냥살냥죽, 민호썸 ]";
              echo 전송($msg);
              exit;
            }
          }
      }
    $msg.= "\n- 출근시 .출근 닉네임";
    echo 전송($msg);
    exit;

  }else if(strpos($status, '.출근') !== false){
    $msg = "✅ 출근 완료\n\n";

    if (preg_match('/(?:\.출근)\s*(.*)/u', $status, $match)) {
        $출근대상 = (isset($match[1]) && trim($match[1]) !== '') ? trim($match[1]) : $두자리닉넴;
        $출근대상_esc = addslashes($출근대상);

        $sql = "select * from tb_member where name = '{$출근대상_esc}' ";
        $받는친구 = db_select($sql);
        if(!$받는친구['idx']){
          echo 전송("존재하지 않는 사용자 입니다.");
          exit;
        }

        // 출근자 본인 status → 0
        db_query("UPDATE tb_member SET status = 0 WHERE name = '{$출근대상_esc}' LIMIT 1");

        $data = db_select("select * from tb_work where nick = '{$출근대상_esc}' ");
        if($data['idx']){
          if (function_exists('회원_출근_채굴재개')) {
            회원_출근_채굴재개($출근대상);
          }
          $자숙연장문구 = '';
          if (function_exists('출근_자숙_퇴근기간연장')) {
            $연장 = 출근_자숙_퇴근기간연장($출근대상, $data);
            if (!empty($연장['applied']) && !empty($연장['notice'])) {
              $자숙연장문구 = "\n\n" . $연장['notice'];
            }
          }
          $result = db_query("delete from tb_work where nick = '{$출근대상_esc}' ");
          if($result){
            $msg = "{$출근대상} 어서와!!🎉" . $자숙연장문구;
            echo 전송($msg);
            exit;
          }
        }else{
          echo 전송("{$출근대상} 이미 출근 상태예요. (status 정상화)");
          exit;
        }

    }else{
      echo 전송("퇴근 원할시 .퇴근 {$두자리닉넴}");
      exit;
    }

 
  }else if(strpos($status, '.명령') !== false){
$msg = "✅ 우리방 명령어                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
  $msg .= "
.제발
.공질
.샘플(공질샘플)
.신입(주의사항)
.요주의인물 (위험인물 목록)
.인원(남여비율)
.솔로(공커·일방 제외)
.공지(공지사항)
.아이템
.평타(전체평균타수)
.평타사다리 10 6 (평타 상위 10명 중 6명 랜덤)
--------
.궁금 닉네임
.출석,ㅊㅅ,찰싹
.연두(미출석자)
.진행(일방/강일/지목)
.자숙(일방/강일/제한)
.임무({$단위}지급내역)
💰.mbti 닉
--------
.버프(프변,지호)
.버프타수
.상점 2글자템명(아이템구매)
.판매 2글자템명(아이템판매)
.주가 · .매수 템명 · .매도 템명 (시총% · 종류별 하루 10)
--------
.내{$단위}(보유 {$단위})
.랭킹 숫자({$단위} 보유순위)
💰.양도 ({$단위} 양도, 양도 내역)
.금고 (수수료모음)
--------
.ㄱㄱ(맞짱게임)
.신청(주사위게임)
.ㄹㄷ(랜덤박스까기)";
  echo 전송($msg);
  exit;
}else if(strpos($status, '.신청') !== false || strpos($status, 'ㅅㅊ') !== false) {

  if (preg_match('/(?:\.신청|ㅅㅊ)(?:\s+(.+?))?\s*$/u', $status, $match)) {
    $금액텍스트 = isset($match[1]) ? trim((string)$match[1]) : '';
    $금액 = $금액텍스트 !== '' ? 냥_금액_파싱($금액텍스트) : 0;

    // 금액 미입력 시 첫 신청자 금액 사용
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

      if($정보['point'] < $주사위참가비){
        echo 전송("냥 부족! 보유 {$정보['point']}냥");
        exit;
      }

      if($정보['couple']!=2){
        if($hour >= 7){
            if($오늘타수['cnt'] < $타수제한){
                echo 전송("{$타수제한}타 이상 야바위 참여가능\n{$두자리닉넴}의 현재 {$오늘타수['cnt']}타");
                exit;
            }
        }
      }

      if($첫신청자['idx']){
        if($금액 < $첫신청자['point']){
          echo 전송("야바위 최소 참가 냥 ".$첫신청자['point']."냥!");
          exit;
        }
      }

      if($금액 < $주사위참가비){
        echo 전송("최소 참가금 ".number_format($주사위참가비)."냥");
        exit;
      }
      if((int)$주사위최대참가비 > 0 && $금액 > $주사위최대참가비){
        echo 전송("최소 참가금 ".number_format($주사위참가비)."냥\n최대 참가금 ".number_format($주사위최대참가비)."냥");
        exit;
      }


      $참여자 = db_select("select count(*) as cnt from tb_run_member where status = 1");
      if($참여자['cnt'] > 0){
        echo 전송("{$두자리닉넴} 다음 게임에 참가하자!");
        exit;
      }

      $신청여부 = db_select("select count(*) as cnt from tb_run_member where name = '{$두자리닉넴}'");
      if($신청여부['cnt']>0){
        $msg = "{$두자리닉넴} 야바위 준비완료!";
        echo 전송($msg);
        exit;
      }


      $신청결과 = db_query("insert into tb_run_member set status = 0, name = '{$두자리닉넴}', point = {$금액}, cnt = 0, sort = 0, regdate = now()");
      db_query("update tb_member set point = point - {$금액} where name = '{$두자리닉넴}' ");
      지급로그('주사위-신청', $두자리닉넴, '', 0, $금액);
      if($신청결과){
          $msg = "🎲{$두자리닉넴} 야바위 참여완료🎲";
          echo 전송($msg);
          exit;
      }

  }
  // $msg = "주사위 게임 방법";
  // $msg.= "\n\n최소인원 2명";
  // $msg.= "\n명령어 .신청 최소100냥 최대{$주사위최대참가비}냥";
  // $msg.= "\n참여할 인원 없는경우 .마감 칠것!";
  // $msg.= "\nㄷㄹ 3회 입력!";
  // $msg.= "\n.점수 로 상황 확인!";
  // $msg.= "\n.총합이 제일 높은친구가 승리!";

  echo 전송($msg);
  exit;
  }else if(strpos($status, '.점수') !== false){
    $sql = "select * from tb_run_member order by cnt desc, regdate asc ";
    $result = db_query($sql);
    $msg = "야바위 참가자\n\n";

    $point = 0;
    for($i=0;$row=db_fetch($result);$i++){
      $point += $row['point'];
      if($i==0 and $row['cnt'] > 0){
        $메달 = "🥇";
      }else{
        $메달 = "";
      }
      $msg .= ($row['sort']==3 ? "마감) ":$row['sort']."회 ").$row['name']." ".$row['cnt']."점".$메달."\n";
    }
    $msg .= "\n참가자 ".$i."명";

    $첫신청자 = db_select("select * from tb_run_member order by idx asc limit 1");
    if($첫신청자['idx']){
      $msg.= "\n참여금 ".number_format($첫신청자['point']) . "냥";
    }

    $첫신청자 = db_query("select * from tb_run_member order by idx asc limit 2");
    $names = [];
    while ($row = db_fetch($첫신청자)) {
        $names[] = $row['name'];
    }
    $msg.= "\n마감가능자 " . implode(', ', $names);
    $msg.= "\n\n➡️ 참여방법 : ㅅㅊ 입력해줘!";

    echo 전송($msg);
    exit;
  }else if(strpos($status, '.공커등록') !== false){

    if (preg_match('/\.공커등록\s*(.+)/u', $status, $match)) {
        $after = trim($match[1]); // ".양도" 뒤 텍스트 추출
        if (in_array(getTwoCharNick($nick), $관리자1)) {
        // "도하 5" 형태인지 검사 + 그룹 분리
        if (preg_match('/^(.+)$/u', $after, $parts)) {
          $닉네임 = $parts[1]; // 도하
          $닉_esc = addslashes($닉네임);

          $데이터 = db_select("select count(*) as cnt from tb_couple where couple = '{$닉_esc}' ");
          if($데이터['cnt']){
            $result = db_query("delete from tb_couple where couple = '{$닉_esc}' ");
            if($result){
              $msg = $닉네임." 해제";
              if (function_exists('공커연금_멤버_초기화')) {
                공커연금_멤버_초기화($닉네임);
              }
              if (function_exists('공커_멤버_oneroom_설정')) {
                공커_멤버_oneroom_설정($닉네임, 0);
              }
            }
          }else{
            $다음sort = function_exists('공커등록_다음sort') ? 공커등록_다음sort() : 0;
            $sql = $다음sort > 0
              ? "INSERT INTO tb_couple SET couple = '{$닉_esc}', sdate = '{$오늘}', edate = '{$오늘}', sort = {$다음sort}, status = 0"
              : "INSERT INTO tb_couple SET couple = '{$닉_esc}', sdate = '{$오늘}', edate = '{$오늘}', status = 0";
            $result = db_query($sql);
            if($result){
              공커등록_일방정리($닉네임);
              if (function_exists('공커_멤버_oneroom_설정')) {
                공커_멤버_oneroom_설정($닉네임, 2);
              }
              $msg = $닉네임." 축하해🎉";
            }
          }
        }
      }else{
        echo 전송("🔒");
        exit;
      }
    }
    echo 전송($msg);
    exit;

  }else if (preg_match('/^\.공커대실권\s*$/u', trim($status))) {
    echo 전송(function_exists('공커대실권_안내문구') ? 공커대실권_안내문구() : '❌ 공커대실권 안내를 불러올 수 없어요.');
    exit;

  }else if (strpos($status, '.공커') !== false || strpos($status, '.커플') !== false) {

    $전체보유냥 = (float)($전체포인트['total_point'] ?? 0);
    $공커적립 = (int)floor($전체보유냥 * 0.0001);
    if ($공커적립 > 0) {
      $공커반 = (int)floor($공커적립 / 2);
      $공커_case = '';
      for ($s = 1; $s <= 10; $s++) {
        $공커_amt = 냥_앞3자리_뒤0($공커적립 + ($s - 1) * $공커반);
        $공커_case .= " WHEN {$s} THEN {$공커_amt}";
      }
      db_query("UPDATE tb_couple SET amount = CASE sort{$공커_case} ELSE amount END WHERE sort >= 1 AND sort <= 10");
    }
    if (function_exists('공커_활성전원_oneroom동기화')) {
      공커_활성전원_oneroom동기화();
    }
    if (function_exists('공커연금_누적갱신')) {
      공커연금_누적갱신();
    }
    $공커연금단가 = function_exists('공커연금_단가') ? 공커연금_단가() : 500;

      $msg = "👩‍❤️‍👨 공커.. 놀라지 않겠다고 약속해줘..\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
      $msg .= "공커연금 1일당 " . number_format($공커연금단가) . "냥 (본방냥×0.001)\n\n";
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
          $msg .= $row['couple']." +{$days}\n({$요일[$day]} {$row['edate']}{$imminent1} )\n";
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
                $sql = "SELECT " . 버프타_SQL_select_expr('msg', 'tasu') . " AS cnt
                        FROM tb_msg
                        WHERE nickname = '{$받는닉}'
                          AND regdate >= '{$date}'
                          AND regdate < '{$date}' + INTERVAL 1 DAY";
                $타수 = db_select($sql);

                // 메시지 조합
                $타수값 = (int)($타수['cnt'] ?? 0);
                $msg .= $date . "({$weekday}) {$타수값}\n";
            }
            echo 전송($msg);
            exit;
          }
      }
    }else{
      echo 전송("🔒");
      exit;
    }
  
  
  }else if ($status == ".프변") {
      if (function_exists('프변_명령_처리')) {
          echo 전송(프변_명령_처리($두자리닉넴, 1));
          exit;
      }
      $차감 = item_bag_sub_nick($두자리닉넴, '프변', 1);
      if (empty($차감['ok'])) {
          echo 전송("❌ 프변 아이템이 없어요.");
          exit;
      }
      $사용중여부 = db_select("select * from tb_item_use where nickname = '{$두자리닉넴}' and item = '프로필변경' ");
      if ($사용중여부['idx']) {
          $유효시간 = date("Y-m-d H:i:s", strtotime($사용중여부['enddate']." +3 days"));
          db_query("update tb_item_use set enddate = '{$유효시간}' where idx = {$사용중여부['idx']} ");
          $만료표시 = date("m-d H:i", strtotime($유효시간));
          if (function_exists('아이템사용_시세하락')) { 아이템사용_시세하락('프변', 1); }
          echo 전송("✅ 프로필변경 3일 연장!\n만료: {$만료표시}");
          exit;
      } else {
          $유효시간 = date("Y-m-d H:i:s", strtotime("+3 days"));
          db_query("insert into tb_item_use set nickname = '{$두자리닉넴}', item = '프로필변경', enddate = '{$유효시간}', regdate = now() ");
          $만료표시 = date("m-d H:i", strtotime($유효시간));
          if (function_exists('아이템사용_시세하락')) { 아이템사용_시세하락('프변', 1); }
          echo 전송("✅ 프로필변경 적용! (3일)\n만료: {$만료표시}");
          exit;
      }
  

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
                  " . 버프타_SQL_select_expr('msg', 'tasu') . " AS cnt
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
                  " . 버프타_SQL_select_expr('msg', 'tasu') . " AS cnt
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
  }else if(strpos($status, '.퇴사') !== false){
    if (in_array(getTwoCharNick($nick), $관리자)) {
      if (preg_match('/\.퇴사\s*(.+)/u', $status, $match)) {
          $방탈자 = trim($match[1]); // ".양도" 뒤 텍스트 추출
          $방탈_esc = addslashes($방탈자);
          $회원 = db_select("select * from tb_member where name = '{$방탈_esc}' ");
          if (empty($회원['idx'])) {
            echo 전송("없음");
            exit;
          }

          // 색변 후 24시간 이내 퇴사 → 담당자 보상 회수
          $회수안내 = '';
          if (function_exists('신입담당보상_조기회수')) {
            $회수 = 신입담당보상_조기회수($회원);
            if (!empty($회수['msg'])) {
              $회수안내 = "\n" . $회수['msg'];
            }
          }

          // 퇴사 시 보유 냥을 금고로 이관 (등록 후 24시간 지난 경우에만)
          $보유냥 = isset($회원['point']) ? (int)$회원['point'] : 0;
          $등록시각 = isset($회원['regdate']) ? strtotime($회원['regdate']) : 0;
          $금고이전냥 = 0;
          if ($보유냥 > 0 && $등록시각 && (time() - $등록시각) >= 86400) {
            db_query("update config set tax = tax + {$보유냥}");
            $금고이전냥 = $보유냥;
          }

          if (function_exists('회원_채굴_전체삭제')) {
            회원_채굴_전체삭제($회원['name']);
          }
          if (function_exists('회원_홀짝_전체삭제')) {
            회원_홀짝_전체삭제($회원['name']);
          }
          if (function_exists('회원_색표장터_퇴사해제')) {
            회원_색표장터_퇴사해제($회원['name']);
          }

          db_query("delete tb_member_item where midx = {$회원['idx']} ");
          db_query("delete tb_member_item where nick = '' ");
          db_query("delete from tb_point_log where nick = '{$회원['name']}'");
          db_query("delete from tb_work where nick = '{$회원['name']}'");
          db_query("delete from tb_msg where nickname = '{$회원['name']}'");
          db_query("delete from tb_item_use where nickname = '{$회원['name']}'");
          db_query("delete from tb_question where nick = '{$방탈_esc}'");
          $result = db_query("delete from tb_member where idx = {$회원['idx']} ");

          if($result){
            $이전안내 = $금고이전냥 > 0 ? "\n보유 냥 ".number_format($금고이전냥) . "냥이 금고로 이전되었어요." : "";
            echo 전송("{$방탈자} 잘가👋🏻 또만나자!{$회수안내}{$이전안내}");
            exit;
          }else{
            echo 전송("없음");
            exit;
          }
      }
    }

  

  }else if(strpos($status, '.평타') !== false){
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
            " . 버프타_SQL_select_expr('msg', 'tasu') . " AS cnt
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

    $msg.= "\n-임무";
    $msg .= "\n300단위 평타 누적 달성 시";
    $msg .= "\n전체 인원에게 30,000{$단위}씩 지급";

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
  
  }else if(strpos($status, '.우리방') !== false){
    $실시간 = function_exists('우리방_실시간_총량')
      ? 우리방_실시간_총량()
      : ['본방냥' => 0.0, '게임냥' => '0'];
    $전체본방냥 = (float)($실시간['본방냥'] ?? 0);
    $전체게임냥 = (string)($실시간['게임냥'] ?? '0');

    $msg = "📊 실시간 시세\n";
    $msg .= "✅ 게임 {$단위} : ". (function_exists('랭킹_게임냥표시') ? 랭킹_게임냥표시($전체게임냥, '') : 콤마삽입($전체게임냥))."\n";
    $msg .= "✅ 본방 {$단위} : ". (function_exists('newpoint표시') ? newpoint표시($전체본방냥) : 콤마삽입($전체본방냥))."\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
    
    $전체게임냥Str = function_exists('냥_정수문자열') ? 냥_정수문자열($전체게임냥) : (string)$전체게임냥;
    $선착순커피 = function_exists('냥_비율내림') ? 냥_비율내림($전체게임냥Str, 0.025) : (int)floor((float)$전체게임냥Str * 0.025);
    if (function_exists('bccomp') ? bccomp(냥_정수문자열($선착순커피), '100000000', 0) > 0 : $선착순커피 > 100000000) {
      $선착순커피 = 100000000; // 1억 상한
    }
    $msg.= "\n\n* 커피쏘기 주의사항 *\n선물하기 -> 두근두근 선물게임 -> 선착순 선물게임";
    $msg.= "\n\n선착순 커피 1만원 당 : ". 콤마삽입($선착순커피) . " {$단위}";
    $msg.= "\n1만원 당 전체인원에게 100만냥씩 추가 지급!";
    $msg.= "\n선착순게임 or 생일자에게 선물만 해당!";
    // 신입·주급 안내 — 실시간 본방냥 기준 (스냅샷/게임냥 미사용)
    $신입지원금 = function_exists('신입지원금_계산')
      ? 신입지원금_계산()
      : (int)floor($전체본방냥 * 0.03);
    $신입담당보상 = function_exists('신입담당보상_계산')
      ? 신입담당보상_계산()
      : (int)floor($전체본방냥 * 0.01);
    $관리자주급 = (int)floor($전체본방냥 * (function_exists('보조금_지급비율') ? 보조금_지급비율() : 0.015));
    $생일자보상 = (int)floor($전체본방냥 * 0.10);

    $msg.= "\n\n📋 보상 (실시간 본방냥 기준)";
    $msg.= "\n신입 지원금 3% : " . (function_exists('newpoint표시') ? newpoint표시($신입지원금) : 콤마삽입($신입지원금)) . "{$단위}";
    $msg.= "\n신입 담당 보상 1% : " . (function_exists('newpoint표시') ? newpoint표시($신입담당보상) : 콤마삽입($신입담당보상)) . "{$단위}";
    $msg.= "\n관리자 주급 1.5% : " . (function_exists('newpoint표시') ? newpoint표시($관리자주급) : 콤마삽입($관리자주급)) . "{$단위}";
    $생일자표시 = function_exists('newpoint표시') ? newpoint표시($생일자보상) : 콤마삽입($생일자보상);
    $msg.= "\n생일자 10% : {$생일자표시}{$단위}";
    
    echo 전송($msg);
    exit;
  }else if(strpos($status, 'ㄱㄱ') !== false){
    $msg = "";
    $hour = date('G'); // 현재 시간 (0~23)

    // 7시 이후부터 자정 전까지만 타수 제한 적용
    // if($정보['couple']!=2){
    //   if($오늘타수['cnt'] < $타수제한){
    //       echo 전송("일 타수 {$타수제한} 이상 맞다이 가능\n{$두자리닉넴}의 현재 타수 : {$오늘타수['cnt']}타");
    //       exit;
    //   }
    // }

    // 뒤에 붙은 글자(예: 명륜진사갈비)는 무시하고 ㄱㄱ 숫자 [금액] 부분만 처리
    // 금액: 숫자 또는 만·억·조·경 축약 (예: 1경, 5조)
    if (preg_match('/^ㄱㄱ\s+(\d+)(?:\s+([^\s]+))?/u', trim($status), $match)) {

        $번호 = (int)$match[1];

        // 🔹 먼저 걸린 배팅 확인
        $먼저건것 = db_select("SELECT * FROM tb_battle WHERE status = 0 LIMIT 1");

        if ($먼저건것 && $먼저건것['nyang1'] > 0) {

            $기존내기 = (int)$먼저건것['nyang1'];

            // 금액을 입력했을 경우
            if (isset($match[2]) && $match[2] !== '') {
                $파싱 = function_exists('맞다이_금액파싱')
                  ? 맞다이_금액파싱($match[2])
                  : ['ok' => true, 'amount' => (function_exists('냥_금액_파싱') ? (int)냥_금액_파싱($match[2]) : (int)$match[2]), 'bare' => false];
                $입력내기 = (int)($파싱['amount'] ?? 0);
                if (empty($파싱['ok']) || $입력내기 <= 0) {
                    echo 전송("❌ 금액 형식을 확인해주세요.\n예) ㄱㄱ 4 1000 (1000냥) / ㄱㄱ 4 1경");
                    exit;
                }

                // ❌ 기존보다 작으면 차단
                if ($입력내기 < $기존내기) {
                    $기존표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($기존내기) : (number_format($기존내기) . '냥');
                    echo 전송("❌ {$기존표}이 걸려있습니다.");
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
                echo 전송("❌ 내기 금액을 입력해주세요.\n예) ㄱㄱ 4 1000 (1000냥) / ㄱㄱ 4 1경");
                exit;
            }

            $파싱 = function_exists('맞다이_금액파싱')
              ? 맞다이_금액파싱($match[2])
              : ['ok' => true, 'amount' => (function_exists('냥_금액_파싱') ? (int)냥_금액_파싱($match[2]) : (int)$match[2]), 'bare' => false];
            $내기냥 = (int)($파싱['amount'] ?? 0);
            $금액숫자만 = !empty($파싱['bare']);
            if (empty($파싱['ok']) || $내기냥 <= 0) {
                echo 전송("❌ 금액 형식을 확인해주세요.\n예) ㄱㄱ 4 1000 (1000냥) / ㄱㄱ 4 1경");
                exit;
            }
        }

        // 여기부터 $번호, $내기냥 사용



        // 레벨별 최대 깽값 제한은 해제 (최소는 config $깽값 = 10만)


        if ($번호 < 1 || $번호 >= 10) {
          echo 전송("최소 1 ~ 10 숫자를 입력해줘!");
          exit;
        }

        if($내기냥 < $깽값){
          $min표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($깽값) : (number_format((int)$깽값) . $단위);
          $tip = (!empty($금액숫자만) || (isset($match[2]) && preg_match('/^\d+$/u', str_replace([',',' '], '', (string)$match[2]))))
            ? "\n(숫자만 쓰면 냥 단위예요. {$match[2]} → " . (function_exists('맞다이_금액표시') ? 맞다이_금액표시($내기냥) : ($내기냥 . '냥')) . " / 경은 `1경`처럼 단위를 붙여주세요)"
            : '';
          echo 전송("🥊맞다이 {$min표} 이상 입력해줘!{$tip}");
          exit;
        }

        if($정보['point'] < $내기냥){
          $보유표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($정보['point']) : ($정보['point'] . $단위);
          $msg = "✅ 맞다이 {$두자리닉넴} 깽값 부족...!\n";
          $msg.= "\n깽값 준비해와..!\n현재 보유 {$보유표}";
          echo 전송($msg);
          exit;
        }

        $체크 = db_select("select * from tb_battle where status = 0 and nick1 = '{$두자리닉넴}' "); //내가 신청한 상태라면
        if($체크['idx'] > 0){
          $대기표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($체크['nyang1']) : (number_format($체크['nyang1']) . '냥');
          $msg = "🥊{$체크['nick1']} 대기중 {$대기표}";
          echo 전송($msg);
          exit;
        }


        $선신청 = db_select("select * from tb_battle where status = 0 order by regdate limit 1 "); //먼저 신청한 상대의 배팅액확인
        if($내기냥 < $선신청['nyang1']){
          $선표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($선신청['nyang1']) : ($선신청['nyang1'] . $단위);
          echo 전송("맞다이 뜨고싶으면 {$선표} 준비해와..");
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
          $금액표시 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($내기냥) : (function_exists('냥축약표시') ? 냥축약표시($내기냥) : (number_format($내기냥) . '냥'));
          $msg = "🥊{$금액표시} 맞다이 뜰사람?";
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
                $승리표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($승리금액, $단위) : (number_format((float)$승리금액) . $단위);
                $msg = "🥊{$진행중['nick1']}({$진행중['addnum1']}) {$최종값1} vs {$진행중['nick2']}({$진행중['addnum2']}) {$최종값2}
    {$승리닉} {$승리표} 획득~🎉";
                $sql = "update tb_member set point = point + {$최종지급} where name = '{$승리닉}' ";
                db_query($sql);
                지급로그('맞짱-승리', $승리닉, $패배닉, 0, $승리금액);

              }else{ // 50%는 금고에 50%는 돌려주기
                $half1 = $진행중['nyang1'] * 0.5;
                $half2 = $진행중['nyang2'] * 0.5;

                $소멸 = $진행중['nyang1'] + $진행중['nyang2'];
                $소멸표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($소멸, $단위) : (number_format((float)$소멸) . $단위);

                $msg = "😱{$진행중['nick1']}({$진행중['addnum1']}) {$최종값1} vs {$진행중['nick2']}({$진행중['addnum2']}) {$최종값2}
무승부! {$소멸표} 소멸!";

                // db_query("update tb_member set point = point + {$half1} where name = '{$진행중['nick1']}' ");
                // db_query("update tb_member set point = point + {$half2} where name = '{$진행중['nick2']}' ");

                지급로그('맞짱-무승부', $진행중['nick1'], '', 0, $half1);
                지급로그('맞짱-무승부', $진행중['nick2'], '', 0, $half2);
                // 지급로그('맞짱-수수료', $진행중['nick1'], '', $half1, $half1);
                // 지급로그('맞짱-수수료', $진행중['nick2'], '', $half2, $half2);
                // db_query("UPDATE config SET tax = tax + {$half1} ");
                // db_query("UPDATE config SET tax = tax + {$half2} ");
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
    $msg .= 로또티켓_레벨업_안내문구() . "\n";
    if (function_exists('레벨업_보호_안내문구')) {
      $msg .= 레벨업_보호_안내문구();
    }
    $msg .= "※ 크론(`_auto_tasu_chk`)이 누적 타수 기준으로 자동 반영해요.";
    echo 전송($msg);
    exit;

  }else if (trim($status) === '.보조금지급') {
    if (!in_array($두자리닉넴, $관리자, true)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    $보조금 = 보조금_동시지급();
    $주급결과 = $보조금['주급'];
    $보급결과 = $보조금['보급'];
    $msg = "💰 보조금 지급\n\n";
    $msg .= "【주급】\n" . $주급결과['msg'] . "\n\n【보급】\n" . $보급결과['msg'];
    echo 전송($msg);
    exit;

}else if (preg_match('/^ㅇㄷ\s*(\d+)?$/u', trim($status), $updownMatch)) {
    $입력숫자 = isset($updownMatch[1]) ? (int)$updownMatch[1] : 0;
    if ($입력숫자 < 1 || $입력숫자 > 50) {
      echo 전송("❌ 사용법: ㅇㄷ 숫자\n숫자는 1~50 사이로 입력해줘!");
      exit;
    }

    $설정행 = db_select("SELECT `업다운값`, `업다운시도` FROM config LIMIT 1");
    $목표숫자 = (int)($설정행['업다운값'] ?? 0);
    $현재시도 = (int)($설정행['업다운시도'] ?? 0);
    if ($목표숫자 < 1 || $목표숫자 > 50) {
      $목표숫자 = 0;
    }
    if ($현재시도 < 0) {
      $현재시도 = 0;
    }

    if ($목표숫자 === 0) {
      $목표숫자 = mt_rand(1, 50);
      db_query("UPDATE config SET `업다운값` = {$목표숫자}, `업다운시도` = 0");
      $현재시도 = 0;
    }

    $이번시도 = $현재시도 + 1;
    db_query("UPDATE config SET `업다운시도` = {$이번시도}");

    if ($입력숫자 === $목표숫자) {
      $누적행 = db_select("SELECT `누적값` FROM config LIMIT 1");
      $누적상금 = (int)($누적행['누적값'] ?? 0);
      if ($누적상금 < 0) {
        $누적상금 = 0;
      }
      $첫시도보너스 = ($이번시도 === 1) ? 1000000 : 0;
      $총지급액 = $누적상금 + $첫시도보너스;

      if ($총지급액 > 0) {
        $닉_esc = addslashes($두자리닉넴);
        db_query("UPDATE tb_member SET point = point + {$총지급액} WHERE name = '{$닉_esc}'");
      }
      지급로그('업다운-정답', $두자리닉넴, '', 0, $총지급액);

      $다음목표숫자 = mt_rand(1, 50);
      db_query("UPDATE config SET `업다운값` = {$다음목표숫자}, `누적값` = 0, `업다운시도` = 0");
      $보너스문구 = ($첫시도보너스 > 0) ? "\n🎁 첫 시도 정답 보너스 +".number_format($첫시도보너스) . "냥!" : "";
      echo 전송("🎉 업다운 정답은 {$목표숫자} 입니다.\n🏆 {$두자리닉넴} ".number_format($총지급액) . "냥 획득!{$보너스문구}\n🔄 다음 업다운 숫자 지정 완료!");
      exit;
    }

    if ($입력숫자 < $목표숫자) {
      $차감액 = mt_rand(10000, 20000);
      $닉_esc = addslashes($두자리닉넴);
      db_query("UPDATE tb_member SET point = point - {$차감액} WHERE name = '{$닉_esc}'");
      db_query("UPDATE config SET `누적값` = COALESCE(`누적값`, 0) + {$차감액}");
      echo 전송("❌오답 {$두자리닉넴} 업! -".number_format($차감액) . "냥");
      exit;
    }

    $차감액 = mt_rand(10000, 20000);
    $닉_esc = addslashes($두자리닉넴);
    db_query("UPDATE tb_member SET point = point - {$차감액} WHERE name = '{$닉_esc}'");
    db_query("UPDATE config SET `누적값` = COALESCE(`누적값`, 0) + {$차감액}");
    echo 전송("❌오답 {$두자리닉넴} 다운! -".number_format($차감액) . "냥");
    exit;

  }else if(stripos($status, '.정답') !== false){
    preg_match('/\.정답\s*(.*)/ui', trim($status), $match);
    $사용자정답 = isset($match[1]) ? trim($match[1]) : '';

   // 진행 중인 문제 1개 조회 (status = 0)
   $현재문제 = db_select("SELECT idx, answer, point FROM tb_question WHERE status = 0 LIMIT 1");
   if (empty($현재문제['idx'])) {
       echo 전송("❌ 진행 중인 초성 퀴즈가 없어요!");
       exit;
   }

    if ($사용자정답 === '') {
        echo 전송("❌ .정답 (정답) 형식으로 입력해줘!\n예) .정답 부산행");
        exit;
    }

    $정답 = trim($현재문제['answer']);
    $포인트 = (int)$현재문제['point'];
    $문제idx = (int)$현재문제['idx'];

    // 정답 비교 (공백 정규화 후 비교)
    $비교입력 = preg_replace('/\s+/u', ' ', $사용자정답);
    $비교정답 = preg_replace('/\s+/u', ' ', $정답);
    if ($비교입력 !== $비교정답) {
        $닉_esc = addslashes($두자리닉넴);
        $차감액 = 10000;
        $새포인트 = $포인트 + $차감액;
        $새포인트표시 = number_format($새포인트);
        db_query("UPDATE tb_member SET point = point - {$차감액} WHERE name = '{$닉_esc}'");
        db_query("UPDATE tb_question SET point = point + {$차감액} WHERE idx = {$문제idx}");
        $차감표시 = number_format($차감액);
        echo 전송("{$두자리닉넴} ❌틀렸어요!\n{$차감표시}냥 차감. 해당 문제 상금 +{$차감표시}냥 누적 (총 {$새포인트표시}냥). 다시 시도해봐~");
        exit;
    }

    // 정답 처리: tb_question에 정답자 닉네임 업데이트 + status 완료
    $닉_esc = addslashes($두자리닉넴);
    db_query("UPDATE tb_question SET nick = '{$닉_esc}', status = 1 WHERE idx = {$문제idx}");

    // 정답자에게 포인트 지급
    db_query("UPDATE tb_member SET point = point + {$포인트} WHERE name = '{$닉_esc}'");

    $표시포인트 = number_format($포인트) . $단위;
    echo 전송("🎉 정답! [ {$두자리닉넴} ] {$표시포인트} 지급!");
    exit;

  }else if(stripos($status, '.초성초기화') !== false){
    $최근문제 = db_select("SELECT answer FROM tb_question ORDER BY idx DESC LIMIT 1");
    $정답문구 = (isset($최근문제['answer']) && trim($최근문제['answer']) !== '') ? "\n📌 직전 정답: " . trim($최근문제['answer']) : '';
    db_query("UPDATE tb_question SET status = 1 WHERE status = 0");
    echo 전송("✅ 초성 퀴즈 초기화 완료!{$정답문구}");
    exit;
    
    
  }else if(stripos($status, '.초성') !== false){
    $msg = "";

    // 초성 문제 출제는 관리자만 가능
    if (!in_array(getTwoCharNick($nick), $관리자)) {
      echo 전송("❌ 초성 문제 출제는 관리자만 가능해요.");
      exit;
    }

    // 진행 중인 초성 퀴즈(status = 0)가 있으면 새 문제 출제하지 않음
    $진행중퀴즈 = db_select("SELECT idx FROM tb_question WHERE status = 0 LIMIT 1");
    if (!empty($진행중퀴즈['idx'])) {
      echo 전송("❗ 이미 진행 중인 초성 퀴즈가 있어요!\n먼저 기존 문제를 마무리해줘!");
      exit;
    }

    // 이미 출제된 정답 목록 조회(최근 500개만) → 중복 방지용 정규화 목록
    $사용된정답들 = [];
    $res_used = db_query("
      SELECT answer
      FROM tb_question
      WHERE answer IS NOT NULL AND answer != ''
      ORDER BY idx DESC
      LIMIT 500
    ");
    if ($res_used) {
      while ($row = db_fetch($res_used)) {
        $title = trim($row['answer']);
        if ($title !== '') {
          $사용된정답들[] = $title;
        }
      }
    }
    // 정답 비교용 정규화 (공백 하나로, 앞뒤 trim, 소문자)
    $정답_정규화 = function($s) {
      $s = trim(preg_replace('/\s+/u', ' ', $s));
      return mb_strtolower($s, 'UTF-8');
    };
    $정답_정규화목록 = array_values(array_unique(array_map($정답_정규화, $사용된정답들)));
    $정답_정규화셋 = array_fill_keys($정답_정규화목록, true);

    // 테마: 국어사전 단어만 사용
    $테마번호 = 1;
    $테마이름 = '국어사전 단어';

    $정답단어 = "";
    $hint     = "";
    $최대시도 = 3;
    $시도정답들 = [];

    for ($시도 = 0; $시도 < $최대시도; $시도++) {
      // 국어사전·일상 단어 위주의 광범위한 문제 출제 (한 번에 여러 후보 요청)
      $gptPrompt = "한국어 국어사전·일상에서 쓰는 단어 후보 6개를 랜덤으로 골라줘. "
                 . "폭넓게 다양하게: 일상 단어, 동물·식물·사물 이름, 추상 개념, 속담·관용구(두 단어 이하), 신조어, 전문 용어, 지명·브랜드 등 누구나 알 만한 것. "
                 . "각 후보는 한 단어 또는 두세 단어까지 가능. ";
      // 프롬프트가 너무 길어지면 느려지므로 최근/직전 중복만 요약 전달
      $제외프롬프트 = array_slice(array_values(array_unique(array_merge($시도정답들, $사용된정답들))), 0, 60);
      if (!empty($제외프롬프트)) {
        $gptPrompt .= "단, 다음 단어들은 제외해줘: ".implode(', ', $제외프롬프트).". ";
      }
      $gptPrompt .= "반드시 아래 JSON 형식으로만 대답해. "
                 . "{\"items\":[{\"title\":\"단어\",\"hint\":\"힌트한줄\"}]} "
                 . "설명이나 다른 문장, 줄바꿈은 절대 넣지 마. "
                 . "items는 정확히 6개를 넣고, title은 서로 겹치지 않게 해줘. "
                 . "hint는 정답을 바로 알 수 없게 1줄로 짧게 써줘.";

      $answer = callGPT($gptPrompt);
      $quizData = json_decode($answer, true);
      if (json_last_error() !== JSON_ERROR_NONE || !is_array($quizData)) {
        continue;
      }
      $후보목록 = [];
      if (!empty($quizData['items']) && is_array($quizData['items'])) {
        $후보목록 = $quizData['items'];
      } else if (!empty($quizData['title'])) {
        // 혹시 구형 포맷이 오면 단일 후보도 처리
        $후보목록[] = ['title' => $quizData['title'], 'hint' => ($quizData['hint'] ?? '')];
      }

      foreach ($후보목록 as $cand) {
        $후보 = trim($cand['title'] ?? '');
        $candHint = trim($cand['hint'] ?? '');
        if ($후보 === '') {
          continue;
        }

        $후보_정규 = $정답_정규화($후보);
        if (isset($정답_정규화셋[$후보_정규])) {
          $시도정답들[] = $후보;
          continue;
        }

        // DB에서 한 번 더 확인 (대소문자/공백 보정)
        $후보_esc = addslashes($후보_정규);
        $중복행 = db_select("
          SELECT idx
          FROM tb_question
          WHERE LOWER(TRIM(REPLACE(answer, '  ', ' '))) = '{$후보_esc}'
          LIMIT 1
        ");
        if (!empty($중복행['idx'])) {
          $시도정답들[] = $후보;
          $정답_정규화셋[$후보_정규] = true;
          continue;
        }

        $정답단어 = $후보;
        $hint = $candHint;
        break 2;
      }
    }

    if ($정답단어 === '' && $시도 >= $최대시도) {
      $msg = "❌ 중복 없이 새 문제를 만들지 못했어요. 잠시 후 다시 시도해줘!";
    }
    if ($정답단어 !== '') {
      $question = toChosung($정답단어);
      $correct  = $정답단어;

      $랜덤포인트 = rand(10, 20) * 5000;

      $q_esc = addslashes($question);
      $a_esc = addslashes($correct);
      $h_db  = addslashes($hint);
      db_query("INSERT INTO tb_question SET status = 0, question = '{$q_esc}', answer = '{$a_esc}', hint = '{$h_db}', point = {$랜덤포인트}, regdate = NOW()");

      $msg .= "[초성 퀴즈] ({$테마이름})\n\n";
      $msg .= "문제: {$question}\n";
      if ($hint !== '') {
        $h_esc = htmlspecialchars($hint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $msg .= "힌트: {$h_esc}\n";
      }

      $표시포인트 = number_format($랜덤포인트);
      $msg .= "포인트: {$표시포인트}{$단위}\n\n";

      $msg .= "정답은 .정답 정답 형식으로 입력해줘😉\n\n #초성퀴즈";
    } else {
      if ($시도 < $최대시도) {
        $msg .= "\n".$answer."\n\n ";
      }
    }

    echo 전송($msg);
    exit;

  }else if(stripos($status, '.mbti') !== false){

    preg_match('/\.mbti\s*(.*)/ui', trim($status), $match);
    $값들 = isset($match[1]) ? preg_split('/\s+/u', trim($match[1]), -1, PREG_SPLIT_NO_EMPTY) : [];
    $지정자1 = $값들[0] ?? '';

    // 유효성 체크: 지정자1 필수
    if (empty($지정자1)) {
        echo 전송("❌ .mbti 닉네임 형식으로 입력해줘!\n예) .mbti 슬기");
        exit;
    }

    $상대1 = db_select("select * from tb_member where name = '{$두자리닉넴}' ");
    $상대2 = db_select("select * from tb_member where name = '{$지정자1}' ");

    // 유효성 체크: 상대방 회원 존재
    if (!$상대2['idx']) {
        echo 전송("❌ [ {$지정자1} ] 회원이 없어요!");
        exit;
    }

    $mbti1 = '';
    $mbti2 = '';
    if (!empty($상대1['content']) && preg_match('/MBTI\s*:\s*([A-Za-z0-9]+)/u', $상대1['content'], $m1)) {
        $mbti1 = trim($m1[1]);
    }
    if (!empty($상대2['content']) && preg_match('/MBTI\s*:\s*([A-Za-z0-9]+)/u', $상대2['content'], $m2)) {
        $mbti2 = trim($m2[1]);
    }

    $성별1 = ((int)($상대1['gender'] ?? 0) === 2) ? '여자' : '남자';
    $성별2 = ((int)($상대2['gender'] ?? 0) === 2) ? '여자' : '남자';

    // 유효성 체크: MBTI 등록 여부
    if (empty($mbti1)) {
        echo 전송("❌ {$두자리닉넴}님 MBTI가 등록되지 않았어요!");
        exit;
    }
    if (empty($mbti2)) {
        echo 전송("❌ {$지정자1}님 MBTI가 등록되지 않았어요!");
        exit;
    }

    if($두자리닉넴 == $지정자1){
      echo 전송("❌ 친구 닉네임을 입력해줘!");
      exit;
    }

    // 비용: 조회 상대가 공커면 전체 게임냥 0.001% · 솔로면 0.0001%
    $상대공커 = function_exists('공커_활성_조회') ? 공커_활성_조회($지정자1) : null;
    $mbti비율 = $상대공커 ? 0.001 : 0.0001;
    $mbti비율표시 = $상대공커 ? '0.001%' : '0.0001%';
    $mbti비용 = function_exists('전체냥기준금액') ? 전체냥기준금액($mbti비율) : 1;
    $mbti비용_sql = preg_replace('/[^\d]/', '', (string)$mbti비용) ?: '0';
    $보유부족 = function_exists('냥_정수_미만')
      ? 냥_정수_미만($정보['point'] ?? 0, $mbti비용)
      : ((float)($정보['point'] ?? 0) < (float)$mbti비용);
    if ($보유부족) {
        $비용표시 = function_exists('구매가_축약표시') ? 구매가_축약표시($mbti비용, $단위) : (number_format((float)$mbti비용) . $단위);
        $보유표시 = function_exists('냥축약표시') ? 냥축약표시($정보['point'] ?? 0) : (number_format((float)($정보['point'] ?? 0)) . $단위);
        $상대상태 = $상대공커 ? '공커' : '솔로';
        echo 전송("❌ MBTI 궁합 조회는 전체 게임냥 {$mbti비율표시}({$비용표시})가 필요해요! (상대 {$상대상태} · 현재 보유: {$보유표시})");
        exit;
    }

    $mbti오늘 = date('Y-m-d');
    $gpt시스템 = '너는 카카오 오픈채팅방 MBTI 궁합 해설가야. 점수표·번호 목록·항목명(케미/멘트팁/금기 등) 없이, 네 의견만 한국어로 자연스럽게 말해.';
    $gpt질문 = "{$mbti1} {$성별1} {$두자리닉넴} 와 {$mbti2} {$성별2} {$지정자1} 의 썸·연애 궁합에 대한 네 의견만 알려줘.\n"
      . "오늘 날짜: {$mbti오늘} — 같은 조합이어도 오늘 기준으로 느낌이 조금 달라도 돼.\n"
      . "번호 매기지 마. 점수·케미 등급·멘트팁·금기 같은 항목을 나누지 마. 네 생각만 몇 문장으로. 전체 350자 이내.";
    $answer = function_exists('callGPT') ? callGPT($gpt질문, $gpt시스템, 480) : false;
    $answer = is_string($answer) ? trim($answer) : '';
    if ($answer === '') {
      echo 전송("❌ GPT가 잠시 의견을 못 냈어. 냥은 안 나갔어!\n잠시 후 `.mbti {$지정자1}` 다시 해봐.");
      exit;
    }
    $answer = preg_replace("/\r\n|\r/", "\n", $answer);
    if (mb_strlen($answer, 'UTF-8') > 500) {
      $answer = mb_substr($answer, 0, 500, 'UTF-8') . '…';
    }

    db_query("UPDATE tb_member SET point = point - {$mbti비용_sql} WHERE name = '{$두자리닉넴}' ");

    echo 전송($answer);
    exit;



  }else if(strpos($status, '.사다리독식') !== false){

    // 타수 1등만, 23시 이후 — 금고 전액을 1등에게 지급
    $현재시 = (int)date('H');
    if ($현재시 < 23) {
      echo 전송("❌ .사다리독식 은 매일 밤 11시 이후에만 가능합니다.");
      exit;
    }

    $날짜 = date('Y-m-d');
    $일등 = db_select("
      SELECT nickname, " . 버프타_SQL_select_expr('msg', 'tasu') . " AS cnt
      FROM tb_msg
      WHERE regdate >= '{$날짜}' AND regdate < '{$날짜}' + INTERVAL 1 DAY
        AND nickname NOT IN ('오픈', '')
        AND msg NOT LIKE '%이모티콘을 보냈습니다.%'
      GROUP BY nickname
      ORDER BY cnt DESC
      LIMIT 1
    ");
    if (!$일등 || empty($일등['nickname'])) {
      echo 전송("오늘 타수 1등이 없습니다.");
      exit;
    }

    $일등닉 = trim((string)$일등['nickname']);
    $일등두자리 = function_exists('getTwoCharNick') ? getTwoCharNick($일등닉) : $일등닉;
    if ($두자리닉넴 !== $일등닉 && $두자리닉넴 !== $일등두자리) {
      echo 전송("❌ 타수 1등만 .사다리독식 을 사용할 수 있어요.\n현재 1등: {$일등닉} (" . number_format((int)$일등['cnt']) . "타)");
      exit;
    }

    if (function_exists('config_tax_컬럼_보장')) {
      config_tax_컬럼_보장();
    }
    $금고잔액 = function_exists('금고_잔액_조회')
      ? 금고_잔액_조회()
      : 냥_정수문자열(db_select("SELECT CONCAT('N', CAST(IFNULL(tax, 0) AS CHAR)) AS tax FROM config LIMIT 1")['tax'] ?? 'N0');
    if ($금고잔액 === '' || $금고잔액 === '0' || (function_exists('bccomp') ? bccomp($금고잔액, '0') <= 0 : (float)$금고잔액 <= 0)) {
      echo 전송("금고에 남은 금액이 없습니다.");
      exit;
    }

    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }
    $수상자 = addslashes($일등닉);
    $지급_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($금고잔액) : preg_replace('/[^\d]/', '', (string)$금고잔액);
    if ($지급_sql === '' || $지급_sql === '0') {
      echo 전송("금고에 남은 금액이 없습니다.");
      exit;
    }

    db_query("UPDATE tb_member SET point = point + {$지급_sql} WHERE name = '{$수상자}'");
    db_query("UPDATE config SET tax = tax - {$지급_sql}");
    // 금고 전액 지급이므로 진행중 사다리 대기/확정 데이터 정리
    db_query("DELETE FROM tb_sadari WHERE IFNULL(status, 0) IN (0, 1)");
    db_query("UPDATE config SET 사다리인원 = 0");
    지급로그('사다리독식', $일등닉, '', 0, $지급_sql);

    $지급표시 = function_exists('금고_금액표시')
      ? 금고_금액표시($지급_sql, $단위)
      : (number_format((float)$지급_sql) . $단위);
    $msg = "👑 사다리 독식!\n";
    $msg .= "타수 1등 {$일등닉} (" . number_format((int)$일등['cnt']) . "타)\n";
    $msg .= "금고 전액 {$지급표시} 지급 완료";
    echo 전송($msg);
    exit;

  }else if(strpos($status, '.사다리종료') !== false){

    // 진행자 기준, status=1(당첨 처리된) 사다리 금액 실제 지급
    if (function_exists('config_tax_컬럼_보장')) {
      config_tax_컬럼_보장();
    }
    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }
    if (function_exists('tb_sadari_amount_컬럼_보장')) {
      tb_sadari_amount_컬럼_보장();
    }
    $게스트 = addslashes($두자리닉넴);
    $sql = "
      SELECT nick, CAST(IFNULL(amount, 0) AS CHAR) AS amount
      FROM tb_sadari
      WHERE guest = '{$게스트}' AND status = 1
    ";
    $res = db_query($sql);

    $rows = [];
    $총지급 = '0';
    while ($r = db_fetch($res)) {
      $amt = function_exists('냥_정수문자열') ? 냥_정수문자열($r['amount'] ?? 0) : preg_replace('/\D/', '', (string)($r['amount'] ?? '0'));
      $r['amount'] = $amt;
      $rows[] = $r;
      // (int) 합산 금지 — 경 단위에서 UINT64/PHP_INT 오버플로로 총액이 1844경… 으로 깨짐
      if (function_exists('냥_금액_문자열합')) {
        $총지급 = 냥_금액_문자열합($총지급, $amt);
      } elseif (function_exists('bcadd')) {
        $총지급 = bcadd($총지급, $amt, 0);
      } else {
        $aa = strrev(ltrim(preg_replace('/\D/', '', (string)$총지급), '0') ?: '0');
        $bb = strrev(ltrim(preg_replace('/\D/', '', (string)$amt), '0') ?: '0');
        $len = max(strlen($aa), strlen($bb));
        $carry = 0;
        $out = '';
        for ($i = 0; $i < $len; $i++) {
          $sum = $carry + (int)($aa[$i] ?? '0') + (int)($bb[$i] ?? '0');
          $out .= (string)($sum % 10);
          $carry = intdiv($sum, 10);
        }
        if ($carry > 0) {
          $out .= (string)$carry;
        }
        $총지급 = ltrim(strrev($out), '0') ?: '0';
      }
    }
    $총지급 = function_exists('냥_정수문자열') ? 냥_정수문자열($총지급) : $총지급;

    if (empty($rows)) {
      echo 전송("지급할 사다리 당첨 내역이 없습니다.");
      exit;
    }

    // 금고 잔액 확인 ((int)/float 금지)
    $금고잔액 = function_exists('금고_잔액_조회') ? 금고_잔액_조회() : '0';
    if (function_exists('bccomp')) {
      $부족 = (bccomp($금고잔액, $총지급, 0) < 0);
    } else {
      $a = ltrim((string)$금고잔액, '0') ?: '0';
      $b = ltrim((string)$총지급, '0') ?: '0';
      $부족 = (strlen($a) !== strlen($b)) ? (strlen($a) < strlen($b)) : ($a < $b);
    }
    if ($부족) {
      $필요표시 = function_exists('금고_금액표시') ? 금고_금액표시($총지급, $단위) : ($총지급 . $단위);
      $현재표시 = function_exists('금고_금액표시') ? 금고_금액표시($금고잔액, $단위) : ($금고잔액 . $단위);
      echo 전송("❌ 금고 잔액이 부족합니다.\n필요: {$필요표시}\n현재: {$현재표시}");
      exit;
    }

    // 실제 지급 처리
    foreach ($rows as $r) {
      $닉 = addslashes($r['nick']);
      $amt = $r['amount'];
      if ($amt === '' || $amt === '0') {
        continue;
      }
      $amt_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($amt) : $amt;

      db_query("UPDATE tb_member SET point = point + {$amt_sql} WHERE name = '{$닉}'");
      지급로그('사다리지급', $닉, '', 0, $amt_sql);
    }
    $총지급_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($총지급) : $총지급;
    db_query("UPDATE config SET tax = tax - {$총지급_sql}");
    $금고차감후 = function_exists('금고_잔액_조회') ? 금고_잔액_조회() : '';

    // 지급된 내역은 삭제
    db_query("DELETE FROM tb_sadari WHERE guest = '{$게스트}' ");

    // 사다리 종료 시 config.사다리인원 0으로 초기화
    db_query("UPDATE config SET 사다리인원 = 0");

    $msg = "✅ 사다리 종료 및 지급 완료\n";
    $msg .= "진행자: {$두자리닉넴}\n\n";
    $msg .= "📦 개별 지급 내역\n";
    $사다리금액표시 = static function ($amt) use ($단위) {
      if (function_exists('금고_금액표시')) {
        return 금고_금액표시($amt, $단위);
      }
      if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($amt, $단위);
      }
      if (function_exists('게임냥_안전표시')) {
        return 게임냥_안전표시($amt, $단위);
      }
      return $amt . $단위;
    };
    foreach ($rows as $r) {
      $msg .= "- {$r['nick']} +" . $사다리금액표시($r['amount']) . "\n";
    }
    $msg .= "\n총 " . $사다리금액표시($총지급) . " 지급 (금고에서 차감)";
    if ($금고차감후 !== '') {
      $msg .= "\n금고 잔액: " . $사다리금액표시($금고잔액) . " → " . $사다리금액표시($금고차감후);
    }

    echo 전송($msg);
    exit;

  }else if(strpos($status, '.사다리현황') !== false){

    // 당첨 확정(status=1) 현황 — 동시 1건만 진행하므로 전체 조회
    $sql = "
      SELECT nick, CAST(IFNULL(amount, 0) AS CHAR) AS amount
      FROM tb_sadari
      WHERE status = 1
      ORDER BY idx ASC
    ";
    $res = db_query($sql);

    $rows = [];
    while ($r = db_fetch($res)) {
      $rows[] = $r;
    }

    if (empty($rows)) {
      echo 전송("현재 진행중인 사다리 당첨 내역이 없습니다.");
      exit;
    }

    $사다리금액표시 = static function ($amt) use ($단위) {
      if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($amt, $단위);
      }
      if (function_exists('게임냥_안전표시')) {
        return 게임냥_안전표시($amt, $단위);
      }
      return number_format((float)$amt) . $단위;
    };

    $msg = "🎯 사다리 현황\n\n";
    foreach ($rows as $r) {
      $msg .= "✅당첨 {$r['nick']} " . $사다리금액표시($r['amount']) . "\n";
    }

    echo 전송($msg);
    exit;  

}else if (strpos($status, '.사다리마감') !== false) {

    global $conn;
    $관리자강제 = in_array($두자리닉넴, (array)($관리자 ?? []), true);

    if ($관리자강제) {
      // 관리자: status=0(대기)만 전체 삭제 — 당첨확정(status=1)은 유지
      $진행중 = db_select("
        SELECT guest
        FROM tb_sadari
        WHERE IFNULL(status, 0) = 0
          AND guest IS NOT NULL
          AND guest <> ''
        GROUP BY guest
        ORDER BY MAX(idx) DESC
        LIMIT 1
      ");
      $진행자 = trim((string)($진행중['guest'] ?? ''));
      db_query("DELETE FROM tb_sadari WHERE IFNULL(status, 0) = 0");
      $행수 = mysqli_affected_rows($conn);
      // 대기분이 없고 당첨확정도 없으면 인원 초기화
      $남은 = db_select("SELECT COUNT(*) AS cnt FROM tb_sadari WHERE IFNULL(status,0) IN (0,1)");
      if ((int)($남은['cnt'] ?? 0) === 0) {
        db_query("UPDATE config SET 사다리인원 = 0");
      }
      if ($행수 > 0) {
        $who = ($진행자 !== '') ? " (진행자: {$진행자})" : '';
        echo 전송("✅ 관리자 마감{$who}\n대기(status=0) {$행수}개 삭제 · 당첨확정분은 유지됩니다.");
      } else {
        echo 전송("삭제할 사다리 대기 데이터가 없습니다.");
      }
    } else {
      // 일반: 본인(guest) 대기(status=0) 데이터만 삭제
      $게스트 = addslashes($두자리닉넴);
      db_query("DELETE FROM tb_sadari WHERE guest = '{$게스트}' and status = 0 ");
      $행수 = mysqli_affected_rows($conn);
      // 본인 진행분이 더 없으면 인원 설정 초기화 (다른 사람 시작 가능)
      $남은 = db_select("SELECT COUNT(*) AS cnt FROM tb_sadari WHERE guest = '{$게스트}' AND IFNULL(status,0) IN (0,1)");
      if ((int)($남은['cnt'] ?? 0) === 0) {
        db_query("UPDATE config SET 사다리인원 = 0");
      }
      if ($행수 > 0) {
        echo 전송("✅ {$두자리닉넴} 사다리 데이터 {$행수}개 삭제 완료");
      } else {
        echo 전송("삭제할 사다리 대기(status=0) 데이터가 없습니다.");
      }
    }
    exit;

}else if(strpos($status, '.사다리') !== false){
    $status_trim = trim($status);
    if (!preg_match('/\.사다리\s+(\d+)\s+(\d+)/u', $status_trim, $m)) {
      $msg = "✅ .사다리 사용법\n\n";
      $msg .= "· 평타(버프타) 순위 상위 N명 중 M명을 랜덤으로 뽑아요.\n\n";
      $msg .= "사용법: .사다리 [상위명수] [뽑을명수]\n";
      $msg .= "예시: .사다리 10 6  (매일 밤 11시 이후 · 하루 1회)\n";
      $msg .= "→ 평타 상위 10명(1등 포함) 중 6명 랜덤\n\n";
      $msg .= "사용법: .사다리 [상위명수] [뽑을명수] 1\n";
      $msg .= "예시: .사다리 10 6 1  (밤 11시 이후 · 하루 1회 · 버프타 1등만)\n";
      $msg .= "→ 1등에게 금고 30% 즉시 지급\n";
      $msg .= "→ 남은 70%는 상위 10명 중 1등 제외 6명 랜덤\n";
      echo 전송($msg);
      exit;
    }

    // 매일 23시 이후 · 하루 1회만 입력
    if (function_exists('사다리_입력가능검사')) {
      $사다리가능 = 사다리_입력가능검사();
      if (empty($사다리가능['ok'])) {
        echo 전송((string)($사다리가능['msg'] ?? '❌ 지금은 사다리를 입력할 수 없어요.'));
        exit;
      }
    } elseif ((int)date('H') < 23) {
      echo 전송("❌ .사다리는 매일 밤 11시(23시) 이후부터\n하루 1회만 입력할 수 있어요.");
      exit;
    }

    // 동시에 1명만 사다리 진행 가능 — status 0(대기)·1(당첨확정) 모두 진행중으로 간주
    // (.사다리종료로 데이터가 비어야 다른 사람 시작 가능)
    $현재진행자 = addslashes($두자리닉넴);
    $진행중 = db_select("
      SELECT guest
      FROM tb_sadari
      WHERE IFNULL(status, 0) IN (0, 1)
        AND guest IS NOT NULL
        AND guest <> ''
      GROUP BY guest
      ORDER BY MAX(idx) DESC
      LIMIT 1
    ");
    if ($진행중 && isset($진행중['guest']) && $진행중['guest'] !== '' && $진행중['guest'] !== $현재진행자) {
      echo 전송("❌ 현재 {$진행중['guest']} 님이 사다리를 진행중입니다.\n(진행자: .사다리마감 또는 .사다리종료 후 다시 시도)");
      exit;
    }

    $날짜 = date('Y-m-d');
    $상위N = (int)$m[1];
    $뽑을인원 = (int)$m[2];
    // 세 번째 인자: 1 = 1등 우대 모드 / YYYY-MM-DD = 날짜
    $일등우대모드 = (bool)preg_match('/\.사다리\s+\d+\s+\d+\s+1(?:\s|$)/u', $status_trim);
    if (!$일등우대모드 && preg_match('/\.사다리\s+\d+\s+\d+\s+(\d{4}-\d{2}-\d{2})/u', $status_trim, $m2)) {
      $날짜 = $m2[1];
    }
    $상위N = max(1, min(50, $상위N));

    // 최소 6명 이상만 사다리 진행 가능
    if ($뽑을인원 < 6) {
      echo 전송("❌ 사다리는 최소 6명 이상부터 진행 가능\n예) .사다리 10 6");
      exit;
    }
    $뽑을인원 = max(6, min($상위N, $뽑을인원));
    if ($일등우대모드 && $뽑을인원 >= $상위N) {
      echo 전송("❌ 1등 우대 모드는 1등을 제외하므로\n상위 명수가 뽑을 명수보다 커야 해요.\n예) .사다리 10 6 1");
      exit;
    }

    // 평타 = 버프타(SUM(tasu))만 사용 — 합타(버프타+생타)에서 생타 제외
    $sql = "WITH today_tasu AS (
        SELECT nickname, " . 버프타_SQL_select_expr('msg', 'tasu') . " AS cnt
        FROM tb_msg
        WHERE regdate >= '{$날짜}' AND regdate < '{$날짜}' + INTERVAL 1 DAY
          AND nickname NOT IN ('오픈', '')
          AND msg NOT LIKE '%이모티콘을 보냈습니다.%'
        GROUP BY nickname
    ),
    ranked AS (
        SELECT nickname, cnt, ROW_NUMBER() OVER (ORDER BY cnt DESC) AS rn
        FROM today_tasu
    )
    SELECT nickname AS 닉네임, cnt AS 타수, rn AS 순위
    FROM ranked
    WHERE rn <= {$상위N}
    ORDER BY rn";
    $res = db_query($sql);
    $순위목록 = array();
    while ($row = db_fetch($res)) {
      $순위목록[] = $row;
    }
    if (empty($순위목록)) {
      echo 전송("{$날짜} 평타 데이터가 없어요.");
      exit;
    }

    $일등행 = $순위목록[0];
    $일등닉 = trim((string)($일등행['닉네임'] ?? ''));
    $일등두자리 = function_exists('getTwoCharNick') ? getTwoCharNick($일등닉) : $일등닉;
    $일등즉시지급 = '0';

    if ($일등우대모드) {
      if ($두자리닉넴 !== $일등닉 && $두자리닉넴 !== $일등두자리) {
        echo 전송("❌ 버프타 1등만 .사다리 … 1 을 사용할 수 있어요.\n현재 1등: {$일등닉} (" . number_format((int)$일등행['타수']) . "타)");
        exit;
      }
      // 상위 N명 중 1등 제외 후 랜덤
      $추첨풀 = [];
      foreach ($순위목록 as $row) {
        if ((int)$row['순위'] === 1) {
          continue;
        }
        $추첨풀[] = $row;
      }
      if (count($추첨풀) < $뽑을인원) {
        echo 전송("❌ 1등 제외 후 후보가 {$뽑을인원}명 미만이에요. (후보 " . count($추첨풀) . "명)");
        exit;
      }
      shuffle($추첨풀);
      $뽑힌목록 = array_slice($추첨풀, 0, $뽑을인원);
    } else {
      shuffle($순위목록);
      $뽑힌목록 = array_slice($순위목록, 0, $뽑을인원);
    }

    // 이번 사다리에서 사용할 인원 수(랜덤 당첨자 수)를 config.사다리인원에 기록
    // 하루 1회 잠금 — 추첨 확정 직후 기록 (마감·재시도로 중복 방지)
    if (function_exists('사다리_입력가능검사')) {
      $사다리가능2 = 사다리_입력가능검사();
      if (empty($사다리가능2['ok'])) {
        echo 전송((string)($사다리가능2['msg'] ?? '❌ 오늘은 이미 사다리를 진행했어요.'));
        exit;
      }
    }
    if (function_exists('사다리_시작기록')) {
      사다리_시작기록((string)$두자리닉넴);
    }
    db_query("UPDATE config SET 사다리인원 = {$뽑을인원}");

    // 금고 잔액은 ALTER 전에 먼저 조회 (락·조회 실패로 0 되는 것 방지)
    $금고잔액 = function_exists('금고_잔액_조회')
      ? 금고_잔액_조회()
      : 냥_정수문자열(db_select("SELECT CONCAT('N', CAST(IFNULL(tax, 0) AS CHAR)) AS tax FROM config LIMIT 1")['tax'] ?? 'N0');
    if (isset($금고잔액[0]) && ($금고잔액[0] === 'N' || $금고잔액[0] === 'n')) {
      $금고잔액 = substr($금고잔액, 1);
    }
    $금고잔액 = function_exists('냥_정수문자열') ? 냥_정수문자열($금고잔액) : preg_replace('/\D/', '', (string)$금고잔액);
    if ($금고잔액 === '' || !preg_match('/^\d+$/', $금고잔액)) {
      $금고잔액 = '0';
    }

    if (function_exists('config_tax_컬럼_보장')) {
      config_tax_컬럼_보장();
    }
    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }
    if (function_exists('tb_sadari_amount_컬럼_보장')) {
      tb_sadari_amount_컬럼_보장();
    }

    $분배금고 = $금고잔액;
    $사다리금액표시_사전 = static function ($amt) use ($단위) {
      if (function_exists('금고_금액표시')) {
        return 금고_금액표시($amt, $단위);
      }
      if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($amt, $단위);
      }
      return (string)$amt . $단위;
    };
    if ($일등우대모드) {
      // 1등 30% — 냥_비율내림 (bcmath 없어도 경 단위 안전)
      $일등즉시지급 = function_exists('냥_비율내림')
        ? 냥_정수문자열(냥_비율내림($금고잔액, 0.3))
        : (function_exists('냥_문자열나눗셈내림') && function_exists('냥_금액_문자열곱')
          ? 냥_문자열나눗셈내림(냥_금액_문자열곱($금고잔액, '30'), '100')
          : '0');
      $일등즉시지급 = function_exists('냥_정수문자열') ? 냥_정수문자열($일등즉시지급) : preg_replace('/\D/', '', (string)$일등즉시지급);
      if ($일등즉시지급 === '' || $일등즉시지급 === '0') {
        echo 전송("❌ 금고 30% 지급액을 계산할 수 없습니다.\n현재 금고: " . $사다리금액표시_사전($금고잔액));
        exit;
      }
      $일등지급_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($일등즉시지급) : $일등즉시지급;
      $일등닉_esc = addslashes($일등닉);
      db_query("UPDATE tb_member SET point = point + {$일등지급_sql} WHERE name = '{$일등닉_esc}'");
      db_query("UPDATE config SET tax = tax - {$일등지급_sql}");
      지급로그('사다리1등', $일등닉, '', 0, $일등지급_sql);
      // 나머지 70% = 원금고 - 1등지급 (bcsub, int 차감 금지)
      if (function_exists('bcsub')) {
        $분배금고 = bcsub($금고잔액, $일등즉시지급, 0);
      } else {
        $분배금고 = function_exists('금고_잔액_조회') ? 금고_잔액_조회() : '0';
      }
      if (function_exists('냥_정수문자열')) {
        $분배금고 = 냥_정수문자열($분배금고);
      }
    }

    $나눌인원 = max(1, count($뽑힌목록));
    $개당금액 = function_exists('냥_나눗셈내림')
      ? 냥_정수문자열(냥_나눗셈내림($분배금고, $나눌인원))
      : (function_exists('bcdiv') ? bcdiv($분배금고, (string)$나눌인원, 0) : '0');
    $개당금액_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($개당금액) : $개당금액;
    $사다리금액표시 = static function ($amt) use ($단위) {
      if (function_exists('금고_금액표시')) {
        return 금고_금액표시($amt, $단위);
      }
      if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($amt, $단위);
      }
      return number_format((float)$amt) . $단위;
    };

    if ($일등우대모드) {
      $msg = "👑 버프타 1등 우대 사다리 ({$날짜})\n";
      $msg .= "1등 {$일등닉}: 금고 30% " . $사다리금액표시($일등즉시지급) . " 즉시 지급\n";
      $msg .= "남은 70%: 상위 {$상위N}명 중 1등 제외 {$뽑을인원}명 랜덤\n\n";
    } else {
      $msg = "✅ 평타 {$상위N}위 중 {$뽑을인원}명 랜덤 뽑기 ({$날짜})\n\n";
    }
    $msg .= "진행자: " . trim($nick) . "\n";

    // 금액 표시/저장 제어 변수
    // null  : 기본동작 (23시 이후에만 금액 표시, 23시 이전에만 tb_sadari 저장)
    // true  : 시간과 상관없이 금액 표시 + tb_sadari 저장
    // false : 시간과 상관없이 금액 숨김 + tb_sadari 미저장
    $금액표시강제 = null;

    $현재시 = (int)date('H');
    if ($금액표시강제 === null) {
      $금액표시 = ($현재시 >= 23);
      $사다리저장 = ($현재시 >= 23);
    } else {
      $금액표시 = (bool)$금액표시강제;
      $사다리저장 = (bool)$금액표시강제;
    }

    // tb_sadari 저장 여부: $사다리저장 이 true 일 때만 insert
    if ($사다리저장) {
      foreach ($뽑힌목록 as $row) {
        $닉네임 = addslashes($row['닉네임']);
        $등수 = (int)$row['순위'];
        $타수 = (int)$row['타수'];

        // 동일 진행자(guest) + 동일 닉네임이 이미 tb_sadari에 있으면 중복 insert 방지
        $중복체크 = db_select("
          SELECT idx
          FROM tb_sadari
          WHERE guest = '{$두자리닉넴}'
            AND nick = '{$닉네임}'
          LIMIT 1
        ");
        if ($중복체크 && isset($중복체크['idx'])) {
          continue;
        }

        $sql_insert = "
          insert into tb_sadari
          set 
            guest = '{$두자리닉넴}',
              nick = '{$닉네임}',
              rank = {$등수},
              tasu = {$타수},
              amount = {$개당금액_sql}
        ";
        db_query($sql_insert);
      }

    }

    if ($금액표시) {
      $msg .= "금고 잔액: " . $사다리금액표시($금고잔액) . "\n";
      if ($일등우대모드) {
        $msg .= "1등 즉시 지급(30%): " . $사다리금액표시($일등즉시지급) . "\n";
        $msg .= "랜덤 분배 풀(70%): " . $사다리금액표시($분배금고) . "\n";
      }
      $msg .= "1인당 지급 예정: " . $사다리금액표시($개당금액) . "\n\n";

      foreach ($뽑힌목록 as $i => $row) {
        $표시문구 = "";
        $닉네임표시 = addslashes($row['닉네임']);
        $당첨내역 = db_select("
          SELECT status
          FROM tb_sadari
          WHERE guest = '{$두자리닉넴}'
            AND nick = '{$닉네임표시}'
            AND status = 1
          LIMIT 1
        ");
        if ($당첨내역 && isset($당첨내역['status']) && (int)$당첨내역['status'] === 1) {
          $표시문구 = " ✅";
        }

        $msg .= ($i + 1) . ". {$표시문구}" . $row['닉네임'] . " ({$row['순위']}등 +" . $사다리금액표시($개당금액) . ")\n";
      }
    } else {
      foreach ($뽑힌목록 as $i => $row) {
        $표시문구 = "";
        $닉네임표시 = addslashes($row['닉네임']);
        $당첨내역 = db_select("
          SELECT status
          FROM tb_sadari
          WHERE guest = '{$두자리닉넴}'
            AND nick = '{$닉네임표시}'
            AND status = 1
          LIMIT 1
        ");
        if ($당첨내역 && isset($당첨내역['status']) && (int)$당첨내역['status'] === 1) {
          $표시문구 = " ✅";
        }

        $msg .= ($i + 1) . ". {$표시문구}" . $row['닉네임'] . " ({$row['순위']}등, {$row['타수']}타)\n";
      }
    }

    if ($사다리저장) {
      $msg .= "\n- 당첨자들은 채팅창에 냥살냥죽!!! 입력해보자(느낌표 포함)";
      $msg .= "\n당첨자가 나오지 않을 시";
      $msg .= "\n- 진행자가 .사다리마감 입력할것!";
    }else{
      $msg .= "\n- 매일 밤 11시 이후에 사다리 가능";
    }

    echo 전송($msg);
    exit;


  }else if(strpos($status, '냥살냥죽!!!') !== false){

    // 사다리 당첨자 개별 지급 처리
    $당첨닉 = addslashes($두자리닉넴);
    // 가장 최근 사다리 기록 1개 조회
    $row = db_select("
      SELECT *
      FROM tb_sadari
      WHERE nick = '{$당첨닉}'
      ORDER BY idx DESC
      LIMIT 1
    ");

    if (!$row || !$row['idx']) {
      echo 전송("❌ {$두자리닉넴} 사다리 당첨 내역이 없습니다.");
      exit;
    }

    // 이미 수령한 내역이면 종료 (status 컬럼이 있다고 가정)
    if (isset($row['status']) && (int)$row['status'] === 1) {
      echo 전송("❌ {$두자리닉넴} 이미 사다리 냥 지급예정 입니다.");
      exit;
    }

    $지급액 = function_exists('냥_정수문자열')
      ? 냥_정수문자열($row['amount'] ?? 0)
      : preg_replace('/\D/', '', (string)($row['amount'] ?? '0'));
    if ($지급액 === '' || $지급액 === '0') {
      echo 전송("❌ 당첨 내역이 없습니다. 관리자에게 확인해주세요.");
      exit;
    }

    // 진행자(guest) 기준으로 사다리 인원 제한
    // config.사다리인원 값(이번 사다리 뽑을 인원 수)을 기준으로,
    // 실제 지급(status=1)이 그 인원을 넘지 않도록 제한
    if (!empty($row['guest'])) {
      $guest = addslashes($row['guest']);

      // 이번 사다리에서 사용할 인원 수 (0이면 제한 없음으로 간주)
      $설정Row = db_select("SELECT 사다리인원 FROM config LIMIT 1");
      $사다리최대인원 = isset($설정Row['사다리인원']) ? (int)$설정Row['사다리인원'] : 0;

      if ($사다리최대인원 > 0) {
        $현재지급인원 = db_select("
          SELECT COUNT(*) AS cnt
          FROM tb_sadari
          WHERE guest = '{$guest}'
            AND status = 1
        ");
        $현재지급인원수 = isset($현재지급인원['cnt']) ? (int)$현재지급인원['cnt'] : 0;
        if ($현재지급인원수 >= $사다리최대인원) {
          echo 전송("❌ 사다리 당첨 인원({$사다리최대인원}명) 결정되었습니다.");
          exit;
        }
      }
    }

    // status=1로 변경 (중복 수령 방지)
    db_query("UPDATE tb_sadari SET status = 1 WHERE idx = {$row['idx']} AND (status IS NULL OR status <> 1)");
    global $conn;
    $행수 = mysqli_affected_rows($conn);
    if ($행수 < 1) {
      // 거의 동시에 두 번 눌렀을 때 등을 대비
      echo 전송("❌ {$두자리닉넴} 이미 당첨 내역이 있습니다.");
      exit;
    }
    
    $지급표시 = function_exists('금고_금액표시')
      ? 금고_금액표시($지급액, $단위)
      : (function_exists('랭킹_게임냥표시') ? 랭킹_게임냥표시($지급액, $단위) : ($지급액 . $단위));
    $msg = "🎉{$두자리닉넴} +" . $지급표시 . "\n";
    $msg .= " 사다리 당첨 지급 예정!\n";
    echo 전송($msg);
    exit;




    
  }else{
    // -------------------------------------------------
    // 특정 시간대 자동 메시지 (tb_lotto_info 기반, 각 시간대 1번만)
    // - 관리자가 tb_lotto_info 에 메시지(status=0, item='AUTO_MSG')를 미리 넣어두면
    //   해당 시간대에 데이터가 있을 때 1건만 꺼내서 전송 후 status=1로 변경
    // -------------------------------------------------
    $autoMsgEnabled = true;

    // ✅ 보낼 시간대 목록 (원하는 만큼 추가)
    //   - 각 시간대마다 고유 item 값을 지정
    //   - tb_lotto_info 에 미리 status=0, item=해당값, msg=보낼문구 1건만 넣어두면
    //     해당 시간대에 딱 1번만 INSERT 해서, 아래 공통 tb_lotto 처리에서 전송되도록 함
    $autoTimeWindows = [
      [
        'item'  => 'AUTO_MSG_1', // tb_lotto_info.item 값 (시간대 구분 코드)
        'start' => '13:25',      // 시작 (HH:MM)
        'end'   => '13:30',      // 끝   (HH:MM)
        'text'  => "오늘 출석 안 한 친구들은 .출석 한번 찍고 가자~!",
      ],
      // [
      //   'item'  => 'AUTO_MSG_2',
      //   'start' => '21:00',
      //   'end'   => '21:05',
      //   'text'  => "밤 공지 예시 문구입니다.",
      // ],
    ];

    if ($autoMsgEnabled && !empty($autoTimeWindows)) {
      $nowHM   = date('H:i');
      $today   = date('Y-m-d');
      $today00 = $today . ' 00:00:00';
      $today24 = date('Y-m-d', strtotime($today . ' +1 day')) . ' 00:00:00';

      foreach ($autoTimeWindows as $w) {
        $item  = isset($w['item'])  ? (string)$w['item']  : '';
        $start = isset($w['start']) ? (string)$w['start'] : '';
        $end   = isset($w['end'])   ? (string)$w['end']   : '';
        $text  = isset($w['text'])  ? (string)$w['text']  : '';
        if ($item === '' || $start === '' || $end === '' || $text === '') {
          continue;
        }

        // 지금 시간이 이 시간대 안이 아니면 패스
        if (!($nowHM >= $start && $nowHM <= $end)) {
          continue;
        }

        // 오늘 이 시간대용 item 으로 이미 1건 이상 insert 되었는지 확인
        $exists = db_select("
          SELECT idx
          FROM tb_lotto_info
          WHERE item = '{$item}'
            AND regdate >= '{$today00}'
            AND regdate < '{$today24}'
          ORDER BY idx ASC
          LIMIT 1
        ");

        if (!empty($exists['idx'])) {
          // 이미 오늘은 한 번 넣었으므로 건너뜀
          continue;
        }

        // 아직 안 넣었으면 오늘 최초 1건만 INSERT (status=0 으로 큐에 쌓음)
        $msg_esc  = addslashes($text);
        $item_esc = addslashes($item);
        db_query("
          INSERT INTO tb_lotto_info
          SET status  = 0,
              msg     = '{$msg_esc}',
              leverage= 0,
              item    = '{$item_esc}',
              regdate = NOW()
        ");
      }
    }

    본방알림_큐_응답_시도();

    // tb_self 에서 status 가 채팅제한인 데이터가 있을 때만 enddate +3분 연장
    $채팅제한확인 = db_select("SELECT COUNT(*) AS cnt FROM tb_self WHERE status = '채팅제한' AND nick = '{$두자리닉넴}' ");
    if (!empty($채팅제한확인['cnt']) && (int)$채팅제한확인['cnt'] > 0) {
        $채팅제한행 = db_select("SELECT enddate FROM tb_self WHERE status = '채팅제한' AND nick = '{$두자리닉넴}' LIMIT 1");
        $연장후시간 = !empty($채팅제한행['enddate']) ? date('m/d H:i', strtotime($채팅제한행['enddate']) + 180) : '';
        $레벨업안내 = "❌{$두자리닉넴} 채팅제한 3분 연장❌" . ($연장후시간 !== '' ? "\n(해제예정: {$연장후시간})" : '');
        $msg_esc = addslashes($레벨업안내);
        $item_esc = addslashes($두자리닉넴);
        db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$msg_esc}', leverage = 0, item = '{$item_esc}', regdate = NOW()");
        db_query("UPDATE tb_self SET enddate = DATE_ADD(enddate, INTERVAL 3 MINUTE) WHERE status = '채팅제한' AND nick = '{$두자리닉넴}'");
    }

  }


  

  //include "msg.php";
}