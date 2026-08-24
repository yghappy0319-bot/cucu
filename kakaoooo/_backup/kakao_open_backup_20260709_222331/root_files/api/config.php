<?php
$오늘 = date("Y-m-d");
$내일 = date("Y-m-d", strtotime("+1 day"));
$오늘시간 = date("Y-m-d H:i");
$단위 = "냥";

/**
 * LLM 연동: 'openai' = ChatGPT API, 'gemini' = Google Gemini API
 * (모든 callGPT 호출이 이 설정을 따름)
 */
$AI_LLM_PROVIDER = 'openai';

$OPENAI_API_KEY = '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';
$OPENAI_MODEL = 'gpt-4o-mini';

/** Google AI Studio(https://aistudio.google.com/apikey) 발급 API 키 — 신규 키는 gemini-2.0-flash 불가, 최신 플래시 권장 */
$GEMINI_API_KEY = 'AIzaSyBCTYQTx2mdaM2wZkKRYWmyhBbWxqgemEY';
$GEMINI_MODEL = 'gemini-2.5-flash';

$관리자 = array();
$관리자_result = db_query("SELECT name FROM tb_member WHERE admin = 1 ORDER BY name");
if ($관리자_result) {
  while ($row = db_fetch($관리자_result)) {
    $닉 = trim($row['name'] ?? '');
    if ($닉 !== '') $관리자[] = $닉;
  }
}
$관리자1 = array("지호");

$주사위참가비 = 100000000; // 1억
$주사위최대참가비 = 100000000000000; // 100조

$아이템전환냥 = 5;

$hour = date('G'); // 현재 시간 (0~23)
$타수제한 = 300;
$일반판매수수료율 = 30;

$설정 = db_select("select * from config ");
$length = mb_strlen($msg, 'UTF-8');
$깽값 = 50;
$계급출석비율 = 0.01;
$누적출석냥 = 10000;

//시전 쿨타임
$쿨타임 = 3;


list($두자리닉넴, $정보) = 회원정보_동기화(nick_파라미터(), $두자리닉넴 ?? '');
$계급 = 계급($정보['point'] ?? 0);

if (!empty($정보['title'])) {
  $호칭 = $정보['title'];
} else {
  $호칭 = $계급['name'] ?? '';
}


$오늘타수 = db_select("select sum(tasu) as cnt from tb_msg where nickname = '{$두자리닉넴}' AND DATE(regdate) = CURDATE()  ");

include_once __DIR__ . '/_bonbang.php';

/** 오늘 100타 출석(출석미션) — _auto_attendance.php 와 동일 기준 */
function 오늘100타출석상태($닉, $오늘 = null) {
    if ($오늘 === null) {
        $오늘 = date('Y-m-d');
    }
    $닉_esc = addslashes($닉);
    $출석 = db_select("
        SELECT COUNT(*) AS cnt
        FROM tb_attendance
        WHERE regdate = '{$오늘}' AND nickname = '{$닉_esc}'
    ");
    if ((int)($출석['cnt'] ?? 0) > 0) {
        return ['완료' => true, '현재' => 100];
    }
    $로우 = db_select("
        SELECT " . 생타_SQL_select_expr('msg') . " AS cnt
        FROM tb_msg
        WHERE nickname = '{$닉_esc}' AND DATE(regdate) = '{$오늘}' AND tasu != 0
    ");
    $현재 = (int)($로우['cnt'] ?? 0);
    return ['완료' => $현재 >= 100, '현재' => $현재];
}

// 전체 보유 냥 — 시세 스냅샷 기준 (모금·가격계산 등). 실시간 합계는 시세기준_실시간합계()
if (function_exists('시세기준_게임냥_문자열')) {
  $전체포인트 = ['total_point' => 시세기준_게임냥_문자열()];
} elseif (function_exists('시세기준_게임냥')) {
  $전체포인트 = ['total_point' => 시세기준_게임냥()];
} else {
  $row = db_select("select CAST(coalesce(sum(point), 0) AS CHAR) as total_point from tb_member where status = 0");
  $전체포인트 = ['total_point' => function_exists('냥_정수문자열') ? 냥_정수문자열($row['total_point'] ?? 0) : (int)($row['total_point'] ?? 0)];
}
if (empty($전체포인트) || !isset($전체포인트['total_point'])) {
  $전체포인트 = ['total_point' => 0];
}

function 가격계산($nyung) {

    if ($nyung <= 5000000)    return 0.85;
    if ($nyung <= 10000000)   return 0.9;
    if ($nyung <= 30000000)   return 0.95;
    if ($nyung <= 50000000)   return 1.0;
    if ($nyung <= 100000000)   return 1.05;
    if ($nyung <= 150000000)   return 1.1;
    if ($nyung <= 200000000)   return 1.15;
    if ($nyung <= 250000000)   return 1.2;
    if ($nyung <= 300000000)   return 1.25;
    if ($nyung <= 400000000)   return 1.3;
    if ($nyung <= 500000000)  return 1.35;
    if ($nyung <= 600000000)  return 1.4;
    if ($nyung <= 700000000)  return 1.45;
    if ($nyung <= 800000000)  return 1.5;
    if ($nyung <= 900000000)  return 1.55;
    if ($nyung <= 1000000000)  return 1.6;
    if ($nyung <= 1500000000)  return 1.65;
    if ($nyung <= 2000000000)  return 1.7;
    if ($nyung <= 2500000000)  return 1.75;

    return 1.8;
}

/** 양도 수수료율 — 레벨 무관 1% */
function 양도수수료율(int $level): float
{
    return 0.01;
}

/** 양도 수수료 (보낸 금액의 1%, 금고 적립) */
function 양도수수료계산(int $level, int $양도금액, bool $지호마법적용 = false): int
{
    if (function_exists('양도_수수료')) {
        return 양도_수수료($양도금액);
    }
    return (int)round($양도금액 * 0.01);
}

/** .양도 안내 문구 (본방·관리방 공통) */
function 양도수수료_안내문(): string
{
    return "\n보낸 금액의 1% 수수료 (금고) · 나머지만 상대 수령 · 레벨 무관";
}

function 초단위변환(int $money, ?string $targetNick = null): int
{
    if (function_exists('모금_금액to초')) {
        return 모금_금액to초($money, $targetNick);
    }

    global $전체포인트;

    $총냥 = (int)($전체포인트['total_point'] ?? 0);
    // 전체 보유 냥 ÷ 10만 = 모금 1단위에 필요한 냥 (예: 4천억 → 400만, 3천억 → 300만)
    $모금나눗값 = max(1, intdiv(max(0, $총냥), 100000));

    $unit = intdiv($money, $모금나눗값);
    return $unit * 12;
}

function callOpenAIChat($userMessage, $systemMessage = '너는 친절한 한국어 도우미야.', $maxTokens = 200) {
    global $OPENAI_API_KEY, $OPENAI_MODEL;

    $apiKey = trim((string)($OPENAI_API_KEY ?? ''));
    if ($apiKey === '') {
        return false;
    }

    $url = 'https://api.openai.com/v1/chat/completions';
    $model = trim((string)($OPENAI_MODEL ?? 'gpt-4o-mini'));
    if ($model === '') {
        $model = 'gpt-4o-mini';
    }

    $data = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user', 'content' => $userMessage],
        ],
        'temperature' => 0.7,
        'max_tokens' => max(16, min(2000, (int)$maxTokens)),
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    $result = json_decode($response, true);

    return $result['choices'][0]['message']['content'] ?? '';
}

/**
 * 일시 과부하·할당량 등으로 같은 요청을 잠시 뒤 다시 시도할 만한 오류인지
 */
function callGemini_error_is_retryable($httpCode, $message) {
    if ($httpCode === 429 || $httpCode === 503) {
        return true;
    }
    $m = strtolower((string)$message);
    $needles = ['high demand', 'try again', 'resource_exhausted', 'unavailable',
        'overloaded', 'too many requests', 'rate limit', 'deadline exceeded'];
    foreach ($needles as $n) {
        if ($n !== '' && strpos($m, $n) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * 이 모델 ID로는 재시도해도 소용없고, 다음 폴백 모델로 넘길 만한 오류인지
 */
function callGemini_error_try_other_model($httpCode, $message) {
    if ($httpCode === 404) {
        return true;
    }
    $m = strtolower((string)$message);
    $needles = ['not found', 'does not exist', 'invalid model', 'unknown model',
        'no longer available', 'is not supported', 'not supported for'];
    foreach ($needles as $n) {
        if ($n !== '' && strpos($m, $n) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Gemini generateContent 1회 호출. 반환: success, text, retryable, try_other_model, logMessage
 */
function callGemini_request_once($apiKey, $model, $userMessage, $systemMessage, $maxOut) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode($model) . ':generateContent';

    $body = [
        'systemInstruction' => [
            'parts' => [['text' => (string)$systemMessage]],
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [['text' => (string)$userMessage]],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => $maxOut,
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
        ],
    ];

    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_errno($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'text' => '', 'retryable' => true, 'try_other_model' => false,
            'logMessage' => 'curl error ' . $curlErr];
    }

    $result = is_string($response) ? json_decode($response, true) : null;
    if (!is_array($result)) {
        $raw = (string)$response;
        $tryOther = callGemini_error_try_other_model($httpCode, $raw);
        $retry = !$tryOther && callGemini_error_is_retryable($httpCode, $raw);
        return ['success' => false, 'text' => '', 'retryable' => $retry, 'try_other_model' => $tryOther,
            'logMessage' => 'invalid JSON HTTP ' . $httpCode];
    }

    if (!empty($result['error'])) {
        $msg = (string)($result['error']['message'] ?? json_encode($result['error']));
        $tryOther = callGemini_error_try_other_model($httpCode, $msg);
        $retry = !$tryOther && callGemini_error_is_retryable($httpCode, $msg);
        return ['success' => false, 'text' => '', 'retryable' => $retry, 'try_other_model' => $tryOther,
            'logMessage' => $msg];
    }

    if (!empty($result['promptFeedback']['blockReason'])) {
        $br = (string)$result['promptFeedback']['blockReason'];
        error_log('Gemini promptFeedback blockReason: ' . $br);
        return ['success' => false, 'text' => '', 'retryable' => false, 'try_other_model' => false,
            'logMessage' => 'blockReason ' . $br];
    }

    $c0 = $result['candidates'][0] ?? null;
    $parts = is_array($c0) ? ($c0['content']['parts'] ?? null) : null;
    if (!is_array($parts)) {
        $raw = (string)$response;
        $tail = $httpCode !== 200 ? ' HTTP ' . $httpCode . ' ' . mb_substr($raw, 0, 200) : '';
        $tryOther = callGemini_error_try_other_model($httpCode, $raw);
        $retry = !$tryOther && callGemini_error_is_retryable($httpCode, $raw);
        return ['success' => false, 'text' => '', 'retryable' => $retry, 'try_other_model' => $tryOther,
            'logMessage' => 'no candidates' . $tail];
    }

    $out = '';
    foreach ($parts as $part) {
        if (isset($part['text'])) {
            $out .= (string)$part['text'];
        }
    }

    return ['success' => true, 'text' => $out, 'retryable' => false, 'try_other_model' => false, 'logMessage' => ''];
}

/**
 * Google Gemini (Generative Language API) — AI Studio 키 사용
 * 과부하 시 짧게 재시도 후, 폴백 모델 순차 시도
 */
function callGemini($userMessage, $systemMessage = '너는 친절한 한국어 도우미야.', $maxTokens = 200) {
    global $GEMINI_API_KEY, $GEMINI_MODEL;

    $apiKey = trim((string)($GEMINI_API_KEY ?? ''));
    if ($apiKey === '') {
        return false;
    }

    $primary = trim((string)($GEMINI_MODEL ?? 'gemini-2.5-flash'));
    if ($primary === '') {
        $primary = 'gemini-2.5-flash';
    }

    /** 주 모델이 막힐 때 (용량·스파이크) 순서대로 시도 */
    $fallbacks = ['gemini-2.5-flash-lite', 'gemini-2.5-pro'];
    $models = array_values(array_unique(array_merge([$primary], $fallbacks)));

    $maxOut = max(16, min(8192, (int)$maxTokens));
    $attemptsPerModel = 4;
    $baseDelayMs = 600;

    $lastLog = '';

    foreach ($models as $model) {
        for ($a = 0; $a < $attemptsPerModel; $a++) {
            if ($a > 0) {
                usleep((int)($baseDelayMs * 1000 * (1 << ($a - 1))));
            }

            $res = callGemini_request_once($apiKey, $model, $userMessage, $systemMessage, $maxOut);
            $lastLog = '[' . $model . '] ' . $res['logMessage'];

            if (!empty($res['success'])) {
                return $res['text'];
            }

            if (!empty($res['try_other_model'])) {
                error_log('Gemini: skip model → try fallback: ' . mb_substr($lastLog, 0, 300));
                break;
            }

            if (empty($res['retryable'])) {
                error_log('Gemini API error: ' . mb_substr($lastLog, 0, 500));
                return false;
            }
        }
        error_log('Gemini: giving up on model after retries: ' . $model);
    }

    error_log('Gemini API error (all models): ' . mb_substr($lastLog, 0, 500));
    return false;
}

/**
 * 통합 LLM 호출 (설정 $AI_LLM_PROVIDER 에 따라 OpenAI 또는 Gemini)
 */
function callGPT($userMessage, $systemMessage = '너는 친절한 한국어 도우미야.', $maxTokens = 200) {
    global $AI_LLM_PROVIDER;

    $p = strtolower(trim((string)($AI_LLM_PROVIDER ?? 'openai')));
    if ($p === 'gemini' || $p === 'google') {
        return callGemini($userMessage, $systemMessage, $maxTokens);
    }

    return callOpenAIChat($userMessage, $systemMessage, $maxTokens);
}

/**
 * 무기 종류/강화 단계별 3시간당 시전 한도
 * - 1~20강: 강화 수치와 동일 (1강=1회 … 20강=20회)
 */
function 무기_시전한도_3시간($무기명, int $강화단계): int
{
    $무기_trim = trim($무기명);

    switch ($무기_trim) {
        case '🏹활':
        case '🏹 활':
        case '🪈단소':
        case '🪈 단소':
        case '🪄마법':
        case '🪄 마법':
            if ($강화단계 < 1) {
                return 0;
            }
            if ($강화단계 > 20) {
                return 20;
            }
            return $강화단계;

        default:
            return 0;
    }
}

/**
 * 무기 최대 내구도
 * - 최대 내구도 = (3시간당 시전 한도) * 60
 */
function 무기_최대내구도($무기명, int $강화단계): int
{
    $시전한도_3시간 = 무기_시전한도_3시간($무기명, $강화단계);
    if ($시전한도_3시간 <= 0) {
        return 0;
    }

    return $시전한도_3시간 * 60;
}

/** 은총 만료 시각이 미래이면 버프 활성 */
function 강화_은총_활성($은총값)
{
    return !empty($은총값) && (strtotime((string)$은총값) > time());
}

/**
 * 강화 1회 비용 (전역 50% 할인 또는 은총 버프 중이면 50%)
 */
function 강화비용_산출($현재강화, $강화비용표, $전역할인, $은총활성, $테스트모드 = false)
{
    if ($테스트모드) {
        return 100;
    }
    if ((int)$현재강화 >= 20) {
        return 0;
    }
    $기본 = isset($강화비용표[$현재강화]) ? (int)$강화비용표[$현재강화] : 350000;
    if ($전역할인 || $은총활성) {
        return (int)($기본 * 0.5);
    }
    return $기본;
}

/**
 * +19→+20 강화: 이미 나온 주사위(확률숫자) 재출현 방지 (파손·+20 성공 시까지 누적, enhance19_used_rolls LONGTEXT)
 */
function 강화19_오늘날짜()
{
    return date('Y-m-d');
}

function 강화19_사용주사위_정규화(array $rolls)
{
    $out = [];
    foreach ($rolls as $v) {
        $n = (int)$v;
        if ($n > 0) {
            $out[$n] = true;
        }
    }
    return array_keys($out);
}

function 강화19_사용주사위_목록($raw)
{
    if ($raw === null || $raw === '') {
        return [];
    }
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        return [];
    }
    // {"date":"Y-m-d","rolls":[...]} — 날짜 무관하게 누적
    if (isset($data['date'], $data['rolls']) && is_array($data['rolls'])) {
        return 강화19_사용주사위_정규화($data['rolls']);
    }
    // 예전 형식(숫자 배열만)
    return [];
}

function 강화19_중복없이_주사위(int $분모, array $사용목록)
{
    if ($분모 < 1) {
        return 1;
    }
    $사용_set = array_flip($사용목록);
    $남은 = $분모 - count($사용_set);
    if ($남은 <= 0) {
        for ($i = 1; $i <= $분모; $i++) {
            if (!isset($사용_set[$i])) {
                return $i;
            }
        }
        return 1;
    }
    if ($남은 === 1) {
        for ($i = 1; $i <= $분모; $i++) {
            if (!isset($사용_set[$i])) {
                return $i;
            }
        }
    }
    $idx = rand(1, $남은);
    for ($i = 1; $i <= $분모; $i++) {
        if (!isset($사용_set[$i])) {
            $idx--;
            if ($idx === 0) {
                return $i;
            }
        }
    }
    return 1;
}

/** +19 시도 주사위 (누적 실패·닉별 보정 반영) */
function 강화19_시도주사위(int $분모, array $사용목록, $닉 = '')
{
    $주사위 = 강화19_중복없이_주사위($분모, $사용목록);
    $닉 = trim((string)$닉);
    if ($닉 === '' || $분모 < 1) {
        return $주사위;
    }
    $보정닉 = ['가은'];
    if (!in_array($닉, $보정닉, true)) {
        return $주사위;
    }
    $임계 = 500;
    if (count($사용목록) >= $임계) {
        return 1;
    }
    return $주사위;
}

function 강화19_사용주사위_저장($닉_esc, array $사용목록)
{
    $사용목록 = 강화19_사용주사위_정규화($사용목록);
    sort($사용목록, SORT_NUMERIC);
    $payload = [
        'date'  => 강화19_오늘날짜(),
        'rolls' => $사용목록,
    ];
    $json = addslashes(json_encode($payload, JSON_UNESCAPED_UNICODE));
    db_query("UPDATE tb_member SET enhance19_used_rolls = '{$json}' WHERE name = '{$닉_esc}'");
}

/** 날짜 무관하게 누적 — 초기화하지 않음 */
function 강화19_사용주사위_날짜맞춤($닉_esc, $raw)
{
    // 누적 유지: 자정 초기화 없음
}

function 강화19_사용주사위_초기화($닉_esc)
{
    db_query("UPDATE tb_member SET enhance19_used_rolls = NULL WHERE name = '{$닉_esc}'");
}

/** 종류별 +20 슬롯 보유자 (본인 제외 가능) */
function 강화_무기종류키($무기아이템)
{
    $무기 = trim((string)$무기아이템);
    if ($무기 === '🪄마법' || $무기 === '🪄 마법') {
        return '🪄마법';
    }
    return $무기;
}

function 강화20_종류별보유자($무기아이템, $제외닉 = '')
{
    $키 = 강화_무기종류키($무기아이템);
    if ($키 === '') {
        return null;
    }
    if ($키 === '🪄마법') {
        $무기조건 = "(TRIM(COALESCE(item,'')) = '🪄마법' OR TRIM(COALESCE(item,'')) = '🪄 마법')";
    } else {
        $키_esc = addslashes($키);
        $무기조건 = "TRIM(COALESCE(item,'')) = '{$키_esc}'";
    }
    $제외조건 = '';
    $제외닉 = trim((string)$제외닉);
    if ($제외닉 !== '') {
        $제외_esc = addslashes($제외닉);
        $제외조건 = " AND name != '{$제외_esc}'";
    }
    $row = db_select("SELECT name, item, enhance FROM tb_member WHERE {$무기조건} AND enhance = 20{$제외조건} LIMIT 1");
    return !empty($row['name']) ? $row : null;
}

/**
 * +19→+20 도전 모드: 동일 무기 종류 +20 보유자가 있을 때 (탈취 도전)
 * @return array|null
 */
function 강화20_도전모드_정보($현재무기, $현재강화, $도전자닉 = '')
{
    if ((int)$현재강화 !== 19) {
        return null;
    }
    $보유자 = 강화20_종류별보유자($현재무기, $도전자닉);
    if ($보유자 === null) {
        return null;
    }
    return [
        '보유자닉'   => trim((string)$보유자['name']),
        '무기'       => trim((string)$보유자['item']),
        '비용'       => 100000000,  // 1억
        '보유자보상' => 50000000,   // 5천
        '소멸'       => 50000000,   // 5천
        '분모'       => 10000000,   // 0.00001%
        '분모_은총'  => 1000000,    // 0.0001%
    ];
}

function 강화20_도전모드_성공분모($은총활성, array $도전모드)
{
    return $은총활성 ? (int)$도전모드['분모_은총'] : (int)$도전모드['분모'];
}

function 강화20_도전모드_성공확률문구($은총활성, array $도전모드)
{
    $분모 = 강화20_도전모드_성공분모($은총활성, $도전모드);
    return rtrim(rtrim(number_format((1 / $분모) * 100, 6, '.', ''), '0'), '.') . '%';
}

/** 도전 시도마다 보유자 5천 지급, 나머지 5천은 소멸(지급 없음) */
function 강화20_도전_보유자보상지급($보유자_esc, $보유자보상 = 50000000)
{
    $보상 = (int)$보유자보상;
    if ($보상 > 0 && $보유자_esc !== '') {
        db_query("UPDATE tb_member SET point = point + {$보상} WHERE name = '{$보유자_esc}'");
    }
    return $보상;
}

/**
 * 도전 성공 시 기존 +20 보유자 → +19 하향
 */
function 강화20_도전_성공시_보유자하향($보유자닉, $보유자_esc, $무기아이템)
{
    db_query("UPDATE tb_member SET enhance = 19 WHERE name = '{$보유자_esc}'");
    강화19_사용주사위_초기화($보유자_esc);

    $보유자닉 = trim((string)$보유자닉);
    $무기아이템 = trim((string)$무기아이템);
    if ($보유자닉 !== '' && $무기아이템 !== '') {
        $보유자닉_log = addslashes($보유자닉);
        $로그 = addslashes("👑 [ {$보유자닉} ] +20 자리 빼앗김\n{$무기아이템} +20 → +19");
        db_query("INSERT INTO tb_lotto_info SET status=0, msg='{$로그}', leverage=0, item='{$보유자닉_log}', regdate=NOW()");
    }

    return 19;
}

/**
 * +20 도전 주사위 적중 후 탈취 성공 확률(%)
 */
function 강화20_도전_탈취성공확률_pct(): int
{
    return 49;
}

function 강화20_도전_역풍확률_pct(): int
{
    return 100 - 강화20_도전_탈취성공확률_pct();
}

/**
 * 도전 주사위 적중 후 역풍 시 본방 알림
 */
function 강화20_도전_역풍_본방알림($도전자닉, $보유자닉, $무기아이템)
{
    $도전자닉 = trim((string)$도전자닉);
    $보유자닉 = trim((string)$보유자닉);
    $무기아이템 = trim((string)$무기아이템);
    if ($도전자닉 === '' || $보유자닉 === '' || $무기아이템 === '') {
        return;
    }
    $무기명 = $무기아이템;
    $조사 = '를';
    if (preg_match('/(단소|활|마법)/u', $무기아이템, $m)) {
        $무기명 = $m[1];
        $조사 = ($무기명 === '활') ? '을' : '를';
    }
    $도전자닉_log = addslashes($도전자닉);
    $로그 = addslashes("⚡ [ {$보유자닉} ]의 +20강 {$무기명}{$조사} 탈취하려다 실패하여 [ {$도전자닉} ]의 무기가 +18강이 되었습니다.");
    db_query("INSERT INTO tb_lotto_info SET status=0, msg='{$로그}', leverage=0, item='{$도전자닉_log}', regdate=NOW()");
}

/**
 * 도전 주사위 적중 시: 49% +20 탈취 / 51% 역풍(+18)
 * @return array{enhance: int, 탈취성공: bool}
 */
function 강화20_도전_성공시_도전자강화()
{
    $탈취성공 = (rand(1, 100) <= 강화20_도전_탈취성공확률_pct());
    return [
        'enhance'   => $탈취성공 ? 20 : 18,
        '탈취성공'  => $탈취성공,
    ];
}
