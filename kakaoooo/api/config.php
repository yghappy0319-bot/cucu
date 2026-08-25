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

/**
 * 관리방(info3) 전용 관리명령을 본방(info1)에서도 관리자만 실행 허용.
 * 다시 관리방만 쓰려면 false 로 되돌리면 됨 (코드 분리 불필요).
 */
$ADMIN_ROOM_CMDS_IN_MAIN = true;

/**
 * 시세 스냅샷 자동 갱신 일시중지 (추석 등).
 * true → 크론·1시간 자동·삭감 후 갱신 중지. 관리방 `.스냅샷` 만 갱신.
 * 재개: false
 */
$시세스냅샷_자동갱신_중지 = true;

/**
 * 야바위 참가비 — 전체 게임냥 × (설계절대액 / 설계총량)
 * 설계총량 9해7832경3661조 기준: 최소 1경 ≈ 총량의 0.001022%
 * (야바위는 게임냥(point) 사용)
 */
if (!defined('야바위_설계총량')) {
  define('야바위_설계총량', '978323661000000000000'); // 9해7832경3661조
}
if (!defined('야바위_설계최소')) {
  define('야바위_설계최소', '10000000000000000'); // 1경
}
if (!defined('야바위_설계최대')) {
  define('야바위_설계최대', '1000000000000000000'); // 100경 (최소×100)
}

if (!function_exists('야바위_참가비_산출')) {
  /**
   * @return int|string PHP_INT_MAX 이하면 int
   */
  function 야바위_참가비_산출(string $design_abs) {
    $total = '0';
    if (function_exists('시세기준_게임냥_문자열')) {
      $total = 시세기준_게임냥_문자열();
    } elseif (function_exists('시세기준_게임냥') && function_exists('냥_정수문자열')) {
      $total = 냥_정수문자열(시세기준_게임냥());
    }
    $design_total = (string)야바위_설계총량;
    $design_abs = preg_replace('/[^\d]/', '', $design_abs) ?: '0';

    if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bcmod')
      && function_exists('bcadd') && function_exists('bccomp')) {
      if (bccomp($total, '0', 0) <= 0 || bccomp($design_total, '0', 0) <= 0
        || bccomp($design_abs, '0', 0) <= 0) {
        $base = $design_abs;
      } else {
        $prod = bcmul($total, $design_abs, 0);
        $base = bcdiv($prod, $design_total, 0);
        $rem = bcmod($prod, $design_total);
        if (bccomp($rem, '0', 0) > 0) {
          $base = bcadd($base, '1', 0); // 올림
        }
        if (bccomp($base, '1', 0) < 0) {
          $base = '1';
        }
      }
      if (bccomp($base, (string)PHP_INT_MAX, 0) <= 0) {
        return (int)$base;
      }
      return $base;
    }

    // bcmath 없을 때: 설계절대액 폴백 (해 단위 float 오차 회피)
    if (function_exists('bccomp') && bccomp($design_abs, (string)PHP_INT_MAX, 0) <= 0) {
      return (int)$design_abs;
    }
    return $design_abs;
  }
}

$주사위참가비 = 100000; // 야바위(ㅅㅊ) 최소 10만냥
$주사위최대참가비 = 0; // 0 = 상한 없음

$아이템전환냥 = 5;

$hour = date('G'); // 현재 시간 (0~23)
$타수제한 = 300;
$일반판매수수료율 = 30;

$설정 = db_select("select * from config ");
$length = mb_strlen($msg, 'UTF-8');
$깽값 = 100000; // 맞다이 최소 깽값 10만냥
$계급출석비율 = 0.01;
$누적출석냥 = 10000;

//시전 쿨타임(시간) — 구간 한도 리셋
$쿨타임 = 1;


list($두자리닉넴, $정보) = 회원정보_동기화(nick_파라미터(), $두자리닉넴 ?? '');
$계급 = 계급($정보['point'] ?? 0);

if (!empty($정보['title'])) {
  $호칭 = $정보['title'];
} else {
  $호칭 = $계급['name'] ?? '';
}


$오늘타수 = db_select("select " . (function_exists('버프타_SQL_select_expr') ? 버프타_SQL_select_expr('msg', 'tasu') : 'sum(tasu)') . " as cnt from tb_msg where nickname = '{$두자리닉넴}' AND DATE(regdate) = CURDATE()  ");

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

function 초단위변환($money, ?string $targetNick = null): int
{
    if (function_exists('모금_금액to초')) {
        return 모금_금액to초((int)$money, $targetNick);
    }

    global $전체포인트;

    $총냥 = $전체포인트['total_point'] ?? 0;
    if (function_exists('시세기준_게임냥_문자열')) {
        $총냥 = 시세기준_게임냥_문자열();
    }
    // 전체 게임냥 × 0.001% ÷ 12 = 모금 1초 단축 단가
    $모금나눗값 = function_exists('모금나눗값_총냥기준')
      ? 모금나눗값_총냥기준($총냥)
      : max(1, (int)ceil((float)$총냥 * 0.00001 / 12.0));
    $모금나눗값 = max(1, (int)min((int)$모금나눗값, PHP_INT_MAX));

    // 1초 단가: floor(금액 / 단가) 초
    return intdiv((int)$money, $모금나눗값);
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

require_once __DIR__ . '/game/weapon_type.inc.php';
require_once __DIR__ . '/enhance_renewal.inc.php';

/**
 * 무기 종류/강화 단계별 1시간당 시전 한도 = 강화 × 2
 * (함수명 3시간은 호출처 호환)
 */
function 무기_시전한도_3시간($무기명, int $강화단계): int
{
    if (!function_exists('무기_시전가능인가') || !무기_시전가능인가($무기명)) {
        return 0;
    }
    if ($강화단계 < 1) {
        return 0;
    }
    $max = function_exists('강화_최대') ? 강화_최대() : 100;
    $e = min($강화단계, $max);
    return $e * 2;
}

/**
 * 활 시전 1회당 타수 흡수량 (랜덤)
 * +1~10:1 · +11~20:1~2 · +21~30:1~3 · +31~40:1~4 · +41~50:1~5 …
 * 10강 구간마다 최댓값 +1 (최솟값은 항상 1)
 */
function 활_흡수타수($강화단계): ?int
{
    $e = (int)$강화단계;
    if ($e < 1) {
        return null;
    }
    $max = (int)ceil($e / 10);
    return rand(1, $max);
}

/**
 * 무기 최대 내구도
 * - 시전 무기(+10↑): 강화 × 100 (예: +10=1000 … +100=10000)
 * - +9 이하·비상전 무기: 0
 */
function 무기_최대내구도($무기명, int $강화단계): int
{
    $시전한도 = 무기_시전한도_3시간($무기명, $강화단계);
    if ($시전한도 <= 0 || $강화단계 < 10) {
        return 0;
    }
    $max = function_exists('강화_최대') ? 강화_최대() : 100;
    $e = min(max(0, $강화단계), $max);
    return $e * 100;
}

/**
 * 구버전 +20 내구도 3000 일괄 보정 — 폐지 (내구도는 강화×100)
 * 함수명은 호출처 호환용으로 유지 (no-op)
 */
function 무기_내구도20_일괄3000_적용(): void
{
    // no-op: 예전 max(3000) 강제 보정은 강화×100 규칙과 충돌
}

/** 은총 만료 시각이 미래이면 버프 활성 */
function 강화_은총_활성($은총값)
{
    return !empty($은총값) && (strtotime((string)$은총값) > time());
}

/**
 * 강화 1회 비용 (전역 50% 할인 또는 은총 버프 중이면 50%)
 *
 * 구간표 (10강 단위): +0~9 / +10~19 / … / +90~99
 * - 구간 기준비 = ceil(유효시총 × parts / DEN)  → 게임냥 차감
 *   (유효시총 = 강화비용_유효시총 · 시세 스냅샷 게임냥의 30% 동적 하드캡)
 * - 구간 안: cost = 기준비 × (10 + 구간내offset) / 10
 * - +9/+19/+29… → 다음 구간 진입 강화비 ×3
 * - +30~39 / +60~69: 정액 계단 (기준비 × 1..10)
 */
/**
 * 강화 1회 비용 (전역 50% 할인 또는 은총 버프 중이면 50% — 은총할인은 플래그로 숨김 가능)
 *
 * 구간표 (10강 단위): +0~9 / +10~19 / … / +90~99
 * - 구간 기준비 = ceil(유효시총 × parts / DEN)  → 게임냥 차감
 *   (유효시총 = 강화비용_유효시총 · 시세 스냅샷 게임냥의 30% 동적 하드캡)
 * - 구간 안: cost = 기준비 × (10 + 구간내offset) / 10
 * - +9/+19/+29… → 다음 구간 진입 강화비 ×3
 * - +30~39 / +60~69: 정액 계단 (기준비 × 1..10)
 */
if (!defined('강화비용_비율_DEN')) {
    /** cost = ceil(유효시총 × parts / DEN) */
    define('강화비용_비율_DEN', '100000000000000000000'); // 1e20
}
if (!defined('강화비용_하드캡_시총비율_분자')) {
    /** 유효시총 = floor(시세 스냅샷 게임냥 × 분자 / 분모) · 기본 3/10 = 30% */
    define('강화비용_하드캡_시총비율_분자', 3);
}
if (!defined('강화비용_하드캡_시총비율_분모')) {
    define('강화비용_하드캡_시총비율_분모', 10);
}
if (!defined('강화비용_설계총량')) {
    /**
     * 시총 미로드(0) 폴백용 · 동적 하드캡의 기준 시총으로도 사용
     */
    define('강화비용_설계총량', '50000000000'); // 500억 게임냥
}

if (!function_exists('강화비용_숫자만')) {
    function 강화비용_숫자만($v): string {
        if (function_exists('냥_정수문자열')) {
            return 냥_정수문자열($v);
        }
        return ltrim(preg_replace('/[^\d]/', '', (string)$v) ?: '0', '0') ?: '0';
    }
}

if (!function_exists('강화비용_전체게임냥')) {
    /**
     * 강화비용용 전체 게임냥(시총) — config 시세 스냅샷(`시세기준_게임냥`) 기준.
     * (.스냅샷 / 크론으로 갱신) · 스냅샷이 비면 실시간·설계총량 폴백.
     * @return string
     */
    function 강화비용_전체게임냥(): string {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $total = '0';
        $fallback = false;

        // 1) config 시세 스냅샷 (수동 `.스냅샷` · 크론) — 실시간 SUM 사용 안 함
        if (function_exists('시세기준_컬럼_보장')) {
            시세기준_컬럼_보장();
        }
        if (function_exists('db_select')) {
            $row = @db_select("
                SELECT CONCAT('N', CAST(IFNULL(`시세기준_게임냥`, 0) AS CHAR)) AS snap_pt,
                       `시세기준_갱신시각` AS snap_at
                FROM config
                LIMIT 1
            ");
            $raw = $row['snap_pt'] ?? 'N0';
            if (function_exists('냥_금액원문_정규화')) {
                $total = 강화비용_숫자만(냥_금액원문_정규화($raw));
            } else {
                $total = 강화비용_숫자만($raw);
            }
            // 예전 BIGINT 상한 고착 스냅샷은 무효 처리
            if ($total !== '0' && $total === (string)PHP_INT_MAX) {
                $total = '0';
            }
        }

        // 2) 스냅샷 비어 있으면 로드 함수(필요 시 1회 갱신) 후 재조회
        if ($total === '0' && function_exists('시세기준_스냅샷_로드')) {
            $snap = 시세기준_스냅샷_로드();
            $total = 강화비용_숫자만($snap['게임냥'] ?? 0);
            if ($total === (string)PHP_INT_MAX) {
                $total = '0';
            }
        }

        // 3) 그래도 없으면 실시간 합계 폴백
        if ($total === '0' && function_exists('시세기준_실시간합계')) {
            $live = 시세기준_실시간합계();
            $total = 강화비용_숫자만($live['게임냥'] ?? 0);
        }

        if ($total === '0' && function_exists('db_select')) {
            $row = @db_select("
                SELECT CONCAT('N', CAST(COALESCE(SUM(GREATEST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)), 0)), 0) AS CHAR)) AS total_pt
                FROM tb_member
                WHERE status = 0
            ");
            $raw = $row['total_pt'] ?? 'N0';
            if (function_exists('냥_금액원문_정규화')) {
                $total = 강화비용_숫자만(냥_금액원문_정규화($raw));
            } else {
                $total = 강화비용_숫자만($raw);
            }
        }

        if ($total === '0') {
            $total = 강화비용_숫자만(강화비용_설계총량);
            $fallback = true;
        }

        $GLOBALS['__강화비용_시총_폴백'] = $fallback;
        $cached = $total;
        return $cached;
    }
}

if (!function_exists('강화비용_시총_폴백인가')) {
    function 강화비용_시총_폴백인가(): bool {
        강화비용_전체게임냥();
        return !empty($GLOBALS['__강화비용_시총_폴백']);
    }
}

/**
 * 큰 정수 문자열의 floor(sqrt(n)) — bcmath 이진 탐색
 */
if (!function_exists('강화비용_정수제곱근')) {
    function 강화비용_정수제곱근(string $n): string {
        $n = 강화비용_숫자만($n);
        if ($n === '0' || $n === '1') {
            return $n;
        }

        if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bccomp')
            && function_exists('bcadd') && function_exists('bcsub')) {
            $len = strlen($n);
            $highDigits = (int)ceil($len / 2);
            $high = '1' . str_repeat('0', $highDigits);
            if (bccomp($high, $n, 0) > 0) {
                $high = $n;
            }
            $low = '1';
            $ans = '1';
            while (bccomp($low, $high, 0) <= 0) {
                $mid = bcdiv(bcadd($low, $high, 0), '2', 0);
                if ($mid === '' || $mid === '0') {
                    $mid = '1';
                }
                $sq = bcmul($mid, $mid, 0);
                $cmp = bccomp($sq, $n, 0);
                if ($cmp === 0) {
                    return $mid;
                }
                if ($cmp < 0) {
                    $ans = $mid;
                    $low = bcadd($mid, '1', 0);
                } else {
                    $high = bcsub($mid, '1', 0);
                }
            }
            return $ans !== '' ? $ans : '1';
        }

        $f = (float)$n;
        if (!is_finite($f) || $f <= 0) {
            return '1';
        }
        return (string)max(1, (int)floor(sqrt($f)));
    }
}

/**
 * 강화비 계산용 유효시총 (동적 하드캡)
 * = floor(시세 스냅샷 게임냥 × 하드캡비율) · 기본 스냅샷 시총의 30%
 * (스냅샷 = config.시세기준_게임냥 · `.스냅샷`/크론 갱신)
 * 스냅샷 시총이 오르면 하드캡(유효시총)도 같이 상승
 */
if (!function_exists('강화비용_유효시총')) {
    function 강화비용_유효시총(): string {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $actual = 강화비용_전체게임냥();
        $num = max(1, (int)강화비용_하드캡_시총비율_분자);
        $den = max(1, (int)강화비용_하드캡_시총비율_분모);

        if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bccomp')) {
            $prod = bcmul($actual, (string)$num, 0);
            $half = bcdiv($prod, (string)$den, 0);
            if ($half === '' || bccomp($half, '1', 0) < 0) {
                $half = '1';
            }
            // 실제 시총을 넘지 않음
            if (bccomp($half, $actual, 0) > 0) {
                $half = $actual === '0' ? '1' : $actual;
            }
            $cached = $half;
            return $cached;
        }

        $a = (float)$actual;
        $eff = (int)floor(($a * $num) / $den);
        $cached = (string)max(1, $eff);
        return $cached;
    }
}

/**
 * 채굴 장비 강화비용 유효시총
 * — 무기와 동일한 동적 하드캡 (시세 스냅샷 게임냥 30%)
 */
if (!function_exists('강화비용_유효시총_채굴')) {
    function 강화비용_유효시총_채굴(): string {
        return 강화비용_유효시총();
    }
}

if (!function_exists('강화비용_구간비율분자표')) {
    /**
     * band 0..9 → 전체게임냥 비율 분자 (분모 = 강화비용_비율_DEN = 1e20)
     *
     * 현재 시총(~2~3천만 게임냥) 기준. 예) 시총 2,671만일 때 기준비 대략(÷100 반영):
     *   +0≈0.02 · +10≈0.09 · +20≈0.2 · +30≈0.4 · +40≈0.9
     *   +50≈1.3 · +60≈1.3 · +70≈2.6 · +80≈3.7 · +90≈4.4
     * (+9/+19/… 구간진입은 그 금액 ×3)
     *
     * ※ 비율 분자 추가 ÷10 (무기 강화비 10배 인하) · 전역 분자(강화비용_전역배율분자)로 재상향 가능
     *
     * @return array<int,string>
     */
    function 강화비용_구간비율분자표(): array {
        // 시총 연동 곡선 · 기존×0.2·÷10 후 추가 ÷10
        return [
            0 => '14062500000',             // +0~9
            1 => '65625000000',             // +10~19
            2 => '164062500000',            // +20~29
            3 => '328125000000',            // +30~39
            4 => '656250000000',            // +40~49
            5 => '976562500000',            // +50~59
            6 => '976562500000',            // +60~69
            7 => '1953125000000',           // +70~79
            8 => '2734375000000',           // +80~89
            9 => '3281250000000',           // +90~99
        ];
    }
}

if (!function_exists('강화비용_문자열곱')) {
    function 강화비용_문자열곱(string $a, string $b): string {
        $a = 강화비용_숫자만($a);
        $b = 강화비용_숫자만($b);
        if ($a === '0' || $b === '0') {
            return '0';
        }
        if (function_exists('bcmul')) {
            return bcmul($a, $b, 0);
        }
        if (function_exists('냥_금액_문자열곱')) {
            return 냥_금액_문자열곱($a, $b);
        }
        // 최후 장제 곱
        $la = strlen($a);
        $lb = strlen($b);
        $res = array_fill(0, $la + $lb, 0);
        for ($i = $la - 1; $i >= 0; $i--) {
            for ($j = $lb - 1; $j >= 0; $j--) {
                $sum = $res[$i + $j + 1] + ((int)$a[$i]) * ((int)$b[$j]);
                $res[$i + $j + 1] = $sum % 10;
                $res[$i + $j] += intdiv($sum, 10);
            }
        }
        $out = ltrim(implode('', $res), '0');
        return $out !== '' ? $out : '0';
    }
}

if (!function_exists('강화비용_ceil_시총비율')) {
    /**
     * ceil(total × parts / den). den=10^k 이면 자릿수 절삭(bcmath 불필요).
     * 1냥 붕괴 방지 — 분모 21자리(1e20)를 일반 장제나눗셈에 넣지 않음.
     */
    function 강화비용_ceil_시총비율(string $total, string $parts, string $den): string {
        $total = 강화비용_숫자만($total);
        $parts = 강화비용_숫자만($parts);
        $den = 강화비용_숫자만($den);
        if ($total === '0' || $parts === '0' || $den === '0') {
            return '1';
        }

        if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bcmod')
            && function_exists('bcadd') && function_exists('bccomp')) {
            $prod = bcmul($total, $parts, 0);
            $base = bcdiv($prod, $den, 0);
            $rem = bcmod($prod, $den);
            if (bccomp($rem, '0', 0) > 0) {
                $base = bcadd($base, '1', 0);
            }
            return (bccomp($base, '1', 0) < 0) ? '1' : $base;
        }

        // DEN = 10^k (채굴/무기 공통 1e20) — 곱한 뒤 뒤 k자리 절삭 + 나머지면 +1
        if (preg_match('/^1(0+)$/', $den, $m)) {
            $cut = strlen($m[1]);
            $prod = 강화비용_문자열곱($total, $parts);
            if ($prod === '0') {
                return '1';
            }
            if (strlen($prod) <= $cut) {
                return '1'; // 0 < prod/den < 1 → ceil = 1
            }
            $head = substr($prod, 0, -$cut);
            $tail = substr($prod, -$cut);
            $base = ltrim($head, '0') ?: '0';
            if ($tail !== '' && ltrim($tail, '0') !== '') {
                if (function_exists('bcadd')) {
                    $base = bcadd($base, '1', 0);
                } elseif (function_exists('냥_금액_문자열합')) {
                    $base = 냥_금액_문자열합($base, '1');
                } else {
                    // 문자열 +1
                    $i = strlen($base) - 1;
                    $carry = 1;
                    $chars = str_split($base);
                    while ($i >= 0 && $carry) {
                        $d = ((int)$chars[$i]) + $carry;
                        $chars[$i] = (string)($d % 10);
                        $carry = intdiv($d, 10);
                        $i--;
                    }
                    $base = ($carry ? '1' : '') . implode('', $chars);
                }
            }
            return ($base === '' || $base === '0') ? '1' : $base;
        }

        // 일반 분모: 올림 나눗셈 헬퍼 (분모가 PHP int 범위일 때만 안전)
        if (function_exists('냥_금액_문자열곱') && function_exists('냥_문자열나눗셈올림')
            && strlen($den) <= 18) {
            $base = 냥_문자열나눗셈올림(냥_금액_문자열곱($total, $parts), $den);
            return ($base === '' || $base === '0') ? '1' : $base;
        }

        return '1';
    }
}

if (!function_exists('강화비용_시총비율금액')) {
    /**
     * ceil(유효시총 × parts / DEN), 최소 1
     * — 실제 시총이 아니라 강화비용_유효시총(시세 스냅샷 게임냥 30% 하드캡) 기준
     * @return string
     */
    function 강화비용_시총비율금액(string $parts): string {
        return 강화비용_ceil_시총비율(
            강화비용_유효시총(),
            $parts,
            (string)강화비용_비율_DEN
        );
    }
}

if (!function_exists('강화비용_시총비율금액_채굴')) {
    /**
     * 채굴 장비 강화비: ceil(채굴유효시총 × parts / DEN)
     * — 무기와 동일한 동적 하드캡(시세 스냅샷 게임냥 30%)
     */
    function 강화비용_시총비율금액_채굴(string $parts): string {
        return 강화비용_ceil_시총비율(
            강화비용_유효시총_채굴(),
            $parts,
            (string)강화비용_비율_DEN
        );
    }
}

if (!function_exists('강화비용_구간기준비표')) {
    /**
     * band index 0..9 → 구간 첫 단계(+0,+10,+20…) 기준비 (현재 시총 기준)
     * @return array<int,string>
     */
    function 강화비용_구간기준비표(): array {
        $parts = 강화비용_구간비율분자표();
        $out = [];
        foreach ($parts as $band => $p) {
            $out[(int)$band] = 강화비용_시총비율금액((string)$p);
        }
        return $out;
    }
}

if (!function_exists('강화비용_고정표')) {
    /** @deprecated 시총 비율표로 대체 — 빈 배열 */
    function 강화비용_고정표(): array {
        return [];
    }
}

if (!function_exists('강화비용_설계절대표')) {
    /** @deprecated 시총 비율표로 대체 — 참고용 빈 배열 */
    function 강화비용_설계절대표(): array {
        return [];
    }
}

if (!function_exists('강화비용_금액캐스트')) {
    /** 큰 금액은 문자열 유지 — (int) 오버플로로 1냥 되는 것 방지 */
    function 강화비용_금액캐스트(string $base) {
        $base = ltrim($base, '0') ?: '0';
        if ($base === '0') {
            return 0;
        }
        // PHP_INT_MAX 이하여도 연쇄 계산 안전을 위해 문자열 우선 반환
        return $base;
    }
}

if (!function_exists('강화비용_구간라벨')) {
    function 강화비용_구간라벨(int $현재강화): string {
        $band = (int)floor(max(0, $현재강화) / 10);
        $from = $band * 10;
        $to = $from + 9;
        return '+' . $from . '~+' . $to . ' 구간';
    }
}

if (!function_exists('강화비용_비율문구')) {
    function 강화비용_비율문구(string $parts): string {
        $parts = 강화비용_숫자만($parts);
        if ($parts === '0') {
            return '0';
        }
        $den = 강화비용_숫자만((string)강화비용_비율_DEN);
        if (preg_match('/^1(0+)$/', $den, $m)) {
            $denExp = strlen($m[1]);
        } else {
            $denExp = max(0, strlen($den) - 1);
        }
        $len = strlen($parts);
        $exp = $len - 1 - $denExp;
        $lead = substr($parts, 0, 1);
        $frac = rtrim(substr($parts, 1, 3), '0');
        $mantissa = $frac !== '' ? ($lead . '.' . $frac) : $lead;
        if ($exp === 0) {
            return $mantissa;
        }
        $sup = ['0'=>'⁰','1'=>'¹','2'=>'²','3'=>'³','4'=>'⁴','5'=>'⁵','6'=>'⁶','7'=>'⁷','8'=>'⁸','9'=>'⁹'];
        $expAbs = (string)abs($exp);
        $expStr = '';
        for ($i = 0, $n = strlen($expAbs); $i < $n; $i++) {
            $expStr .= $sup[$expAbs[$i]] ?? $expAbs[$i];
        }
        return $mantissa . '×10' . ($exp < 0 ? '⁻' : '⁺') . $expStr;
    }
}

if (!function_exists('강화비용_시총표시')) {
    function 강화비용_시총표시(): string {
        $total = 강화비용_전체게임냥();
        if (function_exists('강화비용_표시')) {
            return 강화비용_표시($total, '냥');
        }
        if (function_exists('구매가_축약표시')) {
            return 구매가_축약표시($total, '냥');
        }
        return (function_exists('냥_숫자콤마') ? 냥_숫자콤마($total) : $total) . '냥';
    }
}

/** 강화비 계산에 쓰는 유효시총 표시 */
if (!function_exists('강화비용_유효시총표시')) {
    function 강화비용_유효시총표시(string $단위 = '냥'): string {
        $total = 강화비용_유효시총();
        if (function_exists('강화비용_표시')) {
            return 강화비용_표시($total, $단위);
        }
        if (function_exists('구매가_축약표시')) {
            return 구매가_축약표시($total, $단위);
        }
        return (function_exists('냥_숫자콤마') ? 냥_숫자콤마($total) : $total) . $단위;
    }
}

/** 채굴 장비 강화비용 유효시총 표시 */
if (!function_exists('강화비용_유효시총_채굴표시')) {
    function 강화비용_유효시총_채굴표시(string $단위 = '냥'): string {
        $total = 강화비용_유효시총_채굴();
        if (function_exists('강화비용_표시')) {
            return 강화비용_표시($total, $단위);
        }
        if (function_exists('구매가_축약표시')) {
            return 구매가_축약표시($total, $단위);
        }
        return (function_exists('냥_숫자콤마') ? 냥_숫자콤마($total) : $total) . $단위;
    }
}

/** 동적 하드캡(시세 스냅샷 게임냥 30%) 적용 여부 — 항상 적용 */
if (!function_exists('강화비용_시총완만화_적용중인가')) {
    function 강화비용_시총완만화_적용중인가(): bool {
        return true;
    }
}

if (!defined('강화_은총_비용할인_활성')) {
    /**
     * 은총(일반) 강화비 50% 할인 ON/OFF
     * false = 숨김(미적용) · 할인 코드(강화비용_반액 등)는 유지
     * true 로 바꾸면 은총 버프 중 강화비 50% 할인 재활성
     */
    define('강화_은총_비용할인_활성', false);
}

if (!defined('강화비용_전역배율분자')) {
    /** 기본 강화비 × 이 값 (4 = 기존 대비 4배 · 2배 후 추가 2배) */
    define('강화비용_전역배율분자', 4);
}
if (!defined('강화비용_전역배율분모')) {
    /** 기본 강화비 ÷ 이 값 (1 = 추가 인하 없음) */
    define('강화비용_전역배율분모', 1);
}

if (!function_exists('강화비용_기본금액')) {
    /**
     * 할인 전 기본 강화비 = 유효시총×비율 기준비 × 구간 내 배수
     * (유효시총 = 강화비용_유효시총 · 시세 스냅샷 게임냥 30% 동적 하드캡)
     * 최종 금액 = ×전역배율분자 ÷전역배율분모
     * @return string 정수 문자열 (1냥 붕괴 방지)
     */
    function 강화비용_기본금액($현재강화) {
        $현재강화 = (int)$현재강화;
        $max = function_exists('강화_최대') ? 강화_최대() : 100;
        if ($현재강화 < 0 || $현재강화 >= $max) {
            return '0';
        }

        $partsMap = 강화비용_구간비율분자표();
        $band = (int)floor($현재강화 / 10);
        $offset = $현재강화 % 10;
        if ($band < 0) {
            $band = 0;
        }
        if ($band > 9) {
            $band = 9;
        }
        $parts = (string)($partsMap[$band] ?? '1000000');
        $base = 강화비용_시총비율금액($parts);
        $step = (string)($offset + 1);

        $정액가산_밴드 = ($band === 3 || $band === 6);
        if ($정액가산_밴드) {
            $cost = 강화비용_문자열곱($base, $step);
        } else {
            $mult = (string)(10 + $offset);
            // 내림: base × (10+offset) / 10
            if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bccomp')) {
                $cost = bcdiv(bcmul($base, $mult, 0), '10', 0);
                if (bccomp($cost, '1', 0) < 0) {
                    $cost = '1';
                }
            } elseif (function_exists('냥_나눗셈내림')) {
                $cost = 냥_나눗셈내림(강화비용_문자열곱($base, $mult), '10');
            } else {
                $prod = 강화비용_문자열곱($base, $mult);
                $cost = (strlen($prod) <= 1) ? '0' : (ltrim(substr($prod, 0, -1), '0') ?: '0');
            }
        }

        if ($cost === '' || $cost === '0') {
            $cost = '1';
        }

        if ($offset === 9) {
            $cost = 강화비용_문자열곱($cost, '3');
            if ($cost === '' || $cost === '0') {
                $cost = '1';
            }
        }

        // 전역 배율: ×분자 ÷분모
        $mul = max(1, (int)강화비용_전역배율분자);
        if ($mul > 1) {
            $cost = 강화비용_문자열곱((string)$cost, (string)$mul);
            if ($cost === '' || $cost === '0') {
                $cost = '1';
            }
        }
        $div = max(1, (int)강화비용_전역배율분모);
        if ($div > 1) {
            if (function_exists('bcdiv') && function_exists('bccomp')) {
                $cost = bcdiv((string)$cost, (string)$div, 0);
                if (bccomp($cost, '1', 0) < 0) {
                    $cost = '1';
                }
            } elseif (function_exists('냥_나눗셈내림')) {
                $cost = 냥_나눗셈내림((string)$cost, (string)$div);
                if ($cost === '' || $cost === '0') {
                    $cost = '1';
                }
            } else {
                $cost = (string)max(1, (int)floor(((float)$cost) / $div));
            }
        }

        return 강화비용_금액캐스트($cost);
    }
}

if (!function_exists('강화비용_단계표')) {
    function 강화비용_단계표(): array {
        $max = function_exists('강화_최대') ? 강화_최대() : 100;
        $표 = [];
        for ($lv = 0; $lv < $max; $lv++) {
            $표[$lv] = 강화비용_기본금액($lv);
        }
        return $표;
    }
}

if (!function_exists('강화비용_비율금액_문자열')) {
    function 강화비용_비율금액_문자열(string $parts): string {
        return 강화비용_시총비율금액($parts);
    }
}

if (!function_exists('강화비용_반액')) {
    /** @param int|string $금액 @return string */
    function 강화비용_반액($금액) {
        $s = 강화비용_숫자만($금액);
        if ($s === '0') {
            return '0';
        }
        if (function_exists('bcdiv') && function_exists('bccomp')) {
            $half = bcdiv($s, '2', 0);
            if (bccomp($half, '1', 0) < 0) {
                $half = '1';
            }
            return $half;
        }
        if (function_exists('냥_나눗셈내림')) {
            $half = 냥_나눗셈내림($s, '2');
            return ($half === '' || $half === '0') ? '1' : $half;
        }
        // 문자열 장제 ÷2
        $out = '';
        $remain = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $remain = $remain * 10 + (int)$s[$i];
            $out .= (string)intdiv($remain, 2);
            $remain = $remain % 2;
        }
        $half = ltrim($out, '0') ?: '0';
        return ($half === '0') ? '1' : $half;
    }
}

if (!function_exists('강화비용_표시')) {
    /** 억 미만(10만·100만 등)도 0으로 깎지 않음 — 구매가_축약표시 사용 금지 */
    function 강화비용_표시($금액, $단위 = '냥'): string {
        if (function_exists('랭킹_게임냥표시')) {
            return 랭킹_게임냥표시($금액, $단위);
        }
        if (function_exists('게임냥_안전표시')) {
            return 게임냥_안전표시($금액, $단위);
        }
        $s = function_exists('냥_정수문자열') ? 냥_정수문자열($금액) : preg_replace('/[^\d]/', '', (string)$금액);
        $s = ltrim((string)$s, '0') ?: '0';
        return (function_exists('냥_숫자콤마') ? 냥_숫자콤마($s) : $s) . $단위;
    }
}

if (!function_exists('강화비용_포인트충분')) {
    function 강화비용_포인트충분($point, $cost): bool {
        $p = function_exists('냥_정수문자열') ? 냥_정수문자열($point) : preg_replace('/[^\d]/', '', (string)$point);
        $c = function_exists('냥_정수문자열') ? 냥_정수문자열($cost) : preg_replace('/[^\d]/', '', (string)$cost);
        $p = ltrim((string)$p, '0') ?: '0';
        $c = ltrim((string)$c, '0') ?: '0';
        if (function_exists('bccomp')) {
            return bccomp($p, $c, 0) >= 0;
        }
        if (strlen($p) !== strlen($c)) {
            return strlen($p) > strlen($c);
        }
        return $p >= $c;
    }
}

if (!function_exists('강화비용_sql')) {
    /** SQL 삽입용 숫자 문자열 */
    function 강화비용_sql($cost): string {
        $s = function_exists('냥_정수문자열') ? 냥_정수문자열($cost) : preg_replace('/[^\d]/', '', (string)$cost);
        return ltrim((string)$s, '0') ?: '0';
    }
}

/**
 * 강화비용 중 소멸분 재분배 — 소멸액의 50% 소멸 · 25% 금고 · 25% 로또
 * (도전 탈취 시에는 보유자 보상분을 뺀 소멸분만 넘길 것)
 * @return array{소멸:string,금고:string,로또:string}
 */
if (!function_exists('강화비용_소멸분배')) {
    function 강화비용_소멸분배($소멸금액, $닉 = '', $로그유형 = '강화소멸') {
        $금액 = function_exists('냥_정수문자열')
            ? 냥_정수문자열($소멸금액)
            : preg_replace('/[^\d]/', '', (string)$소멸금액);
        $금액 = ltrim((string)$금액, '0') ?: '0';
        if ($금액 === '0') {
            return ['소멸' => '0', '금고' => '0', '로또' => '0'];
        }
        if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bcsub')) {
            $금고 = bcdiv(bcmul($금액, '25', 0), '100', 0);
            $로또 = bcdiv(bcmul($금액, '25', 0), '100', 0);
            $소멸 = bcsub(bcsub($금액, $금고, 0), $로또, 0);
        } else {
            $총 = max(0, (int)$금액);
            $금고 = (string)(int)floor($총 * 25 / 100);
            $로또 = (string)(int)floor($총 * 25 / 100);
            $소멸 = (string)max(0, $총 - (int)$금고 - (int)$로또);
        }
        $금고_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($금고) : (function_exists('강화비용_sql') ? 강화비용_sql($금고) : $금고);
        $로또_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($로또) : (function_exists('강화비용_sql') ? 강화비용_sql($로또) : $로또);
        if ($금고_sql !== '' && $금고_sql !== '0') {
            db_query("UPDATE config SET tax = tax + {$금고_sql}");
        }
        if ($로또_sql !== '' && $로또_sql !== '0') {
            // config.로또누적 가산 (tb_game_lotto INSERT 안 함)
            $amountInc = __DIR__ . '/game/lotto_amount.inc.php';
            if (is_file($amountInc)) {
                require_once $amountInc;
            }
            if (function_exists('로또누적_가산')) {
                로또누적_가산($로또_sql);
            } else {
                $guards = __DIR__ . '/game/odd_even_guards.php';
                if (is_file($guards)) {
                    require_once $guards;
                }
                if (function_exists('홀짝_로또수수료_적립')) {
                    홀짝_로또수수료_적립($로또_sql, trim((string)$닉) !== '' ? ('강화:' . trim((string)$닉)) : '강화소멸');
                }
            }
        }
        if (function_exists('지급로그') && ($금고_sql !== '0' || $로또_sql !== '0')) {
            $합 = function_exists('bcadd') ? bcadd((string)$금고, (string)$로또, 0) : (string)((int)$금고 + (int)$로또);
            지급로그((string)$로그유형, (string)$닉, '', $합, $금액);
        }
        return ['소멸' => (string)$소멸, '금고' => (string)$금고, '로또' => (string)$로또];
    }
}

/**
 * @param array|null $강화비용표 하위호환(무시 가능) — 실제 금액은 시총 비율표
 * @param bool $본냥미션적용 본냥 전체미션 강화비 할인 적용 여부
 * @return int|string
 */
function 강화비용_산출($현재강화, $강화비용표 = null, $전역할인 = false, $은총활성 = false, $테스트모드 = false, $본냥미션적용 = true)
{
    if ($테스트모드) {
        return 100;
    }
    $max = function_exists('강화_최대') ? 강화_최대() : 100;
    if ((int)$현재강화 >= $max) {
        return 0;
    }
    $기본 = 강화비용_기본금액($현재강화);
    // 최종 안전 배율 (비율표 ÷10과 별도 · 기본 1)
    if (defined('강화비용_전역배율분모')) {
        $div = max(1, (int)강화비용_전역배율분모);
        if ($div > 1) {
            $s = function_exists('냥_정수문자열') ? 냥_정수문자열($기본) : preg_replace('/[^\d]/', '', (string)$기본);
            $s = ltrim((string)$s, '0') ?: '0';
            if ($s !== '0') {
                if (function_exists('bcdiv') && function_exists('bccomp')) {
                    $s = bcdiv($s, (string)$div, 0);
                    if (bccomp($s, '1', 0) < 0) {
                        $s = '1';
                    }
                } elseif (function_exists('냥_나눗셈내림')) {
                    $s = 냥_나눗셈내림($s, (string)$div);
                    if ($s === '' || $s === '0') {
                        $s = '1';
                    }
                } else {
                    $s = (string)max(1, (int)floor(((float)$s) / $div));
                }
                $기본 = $s;
            }
        }
    }
    // 은총 강화비 50% 할인 — 코드 유지, 강화_은총_비용할인_활성=false 면 미적용
    $은총할인적용 = $은총활성 && (!defined('강화_은총_비용할인_활성') || 강화_은총_비용할인_활성);
    if ($전역할인 || $은총할인적용) {
        $기본 = 강화비용_반액($기본);
    }
    // 본냥 전체미션(개인금고 예치 비율) 강화비 할인
    if ($본냥미션적용) {
        if (!function_exists('gv_본냥미션_비용할인')) {
            $missionInc = __DIR__ . '/game/vault_bon_mission.inc.php';
            if (is_file($missionInc)) {
                include_once $missionInc;
            }
        }
        if (function_exists('gv_본냥미션_비용할인')) {
            $기본 = gv_본냥미션_비용할인($기본);
        }
    }
    return $기본;
}

/** 무기 종류 키 정규화 (하위호환 헬퍼명: 강화20_종류별보유자) — 단소는 표시명이 달라도 동일 키 */
function 강화_무기종류키($무기아이템)
{
    if (function_exists('무기_타입값')) {
        $타입 = 무기_타입값($무기아이템);
        if ($타입 === 무기_타입_활) {
            return '🏹활';
        }
        if ($타입 === 무기_타입_단소) {
            return '🪈단소';
        }
        if ($타입 === 무기_타입_마법) {
            return '🪄마법';
        }
    }
    $무기 = trim((string)$무기아이템);
    if ($무기 === '🪄마법' || $무기 === '🪄 마법') {
        return '🪄마법';
    }
    return $무기;
}

function 강화20_종류별보유자($무기아이템, $제외닉 = '')
{
    // 하위호환: +20 좌석
    if (function_exists('강화_종류별레벨보유자')) {
        $row = 강화_종류별레벨보유자($무기아이템, 20, $제외닉);
        return (!empty($row['name'])) ? $row : null;
    }
    return null;
}

/**
 * 탈취 도전 비용 — 해당 단계 일반 강화비와 동일
 * @return int|string
 */
function 강화20_도전비용_기본()
{
    if (function_exists('강화비용_기본금액')) {
        // 하위호환: 과거 +19→+20 기준값
        return 강화비용_기본금액(19);
    }
    return defined('강화_탈취비용_1조') ? (string)강화_탈취비용_1조 : '1000000000000';
}

/**
 * 탈취 비용의 절반 (보유자 보상 / 소멸)
 * @param int|string $비용
 * @return int|string
 */
function 강화20_도전비용_절반($비용)
{
    if (function_exists('강화비용_반액')) {
        return 강화비용_반액($비용);
    }
    return max(0, (int)floor(((float)$비용) / 2));
}

/**
 * 도전 모드: 종류별 다음 강화(+1~+100) 좌석이 점유된 경우 탈취
 * @return array|null
 */
function 강화20_도전모드_정보($현재무기, $현재강화, $도전자닉 = '')
{
    // 리뉴얼: +1~+100 독점 좌석 점유 시 탈취 (확률·비용=해당 단계 일반 강화)
    if (function_exists('강화_도전모드_정보')) {
        return 강화_도전모드_정보($현재무기, $현재강화, $도전자닉);
    }
    return null;
}

function 강화20_도전모드_성공분모($은총활성, array $도전모드)
{
    if (function_exists('강화_도전모드_성공분모')) {
        return 강화_도전모드_성공분모($은총활성, $도전모드);
    }
    return $은총활성 ? (int)$도전모드['분모_은총'] : (int)$도전모드['분모'];
}

function 강화20_도전모드_성공확률문구($은총활성, array $도전모드)
{
    if (function_exists('강화_도전모드_성공확률문구')) {
        return 강화_도전모드_성공확률문구($은총활성, $도전모드);
    }
    $분자 = max(1, (int)($도전모드['분자'] ?? 1));
    $분모 = 강화20_도전모드_성공분모($은총활성, $도전모드);
    return rtrim(rtrim(number_format(($분자 / $분모) * 100, 8, '.', ''), '0'), '.') . '%';
}

/** 도전 시도마다 보유자에게 보상 지급 (나머지 절반은 소멸) */
function 강화20_도전_보유자보상지급($보유자_esc, $보유자보상 = 50000000)
{
    $보상_sql = function_exists('강화비용_sql')
        ? 강화비용_sql($보유자보상)
        : (string)max(0, (int)$보유자보상);
    if ($보상_sql !== '0' && $보유자_esc !== '') {
        db_query("UPDATE tb_member SET point = point + {$보상_sql} WHERE name = '{$보유자_esc}'");
    }
    return $보상_sql;
}

/**
 * 도전 성공 시 기존 +20 보유자 → +19 하향
 */
function 강화20_도전_성공시_보유자하향($보유자닉, $보유자_esc, $무기아이템, $보유강화 = 20, $탈취자닉 = '')
{
    $보유강화 = (int)$보유강화;
    if ($보유강화 < 1) {
        $보유강화 = 20;
    }
    if (function_exists('강화_탈취_보유자하향')) {
        return 강화_탈취_보유자하향($보유자닉, $보유자_esc, $무기아이템, $보유강화, $탈취자닉);
    }
    $to = max(0, $보유강화 - 1);
    db_query("UPDATE tb_member SET enhance = {$to} WHERE name = '{$보유자_esc}'");
    return $to;
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
    // 탈취 관련 본방 알림 사용 안 함
    return;
}

/**
 * 도전 주사위 적중 시: 목표 강화 탈취 (+ 대성공 가능)
 * @return array{enhance: int, 탈취성공: bool, crit?: bool, gain?: int, 안내?: string}
 */
function 강화20_도전_성공시_도전자강화($목표강화 = 20, $현재강화 = null)
{
    // 리뉴얼: 탈취 주사위 성공 = 목표 강화 (+대성공 시 +2/+3)
    $목표강화 = (int)$목표강화;
    if ($목표강화 < 1) {
        $목표강화 = 20;
    }
    $현재 = $현재강화 !== null && $현재강화 !== ''
        ? (int)$현재강화
        : max(0, $목표강화 - 1);
    if (function_exists('강화_탈취_성공시_도전자강화')) {
        return 강화_탈취_성공시_도전자강화($목표강화, $현재);
    }
    return ['enhance' => $목표강화, '탈취성공' => true, 'crit' => false, 'gain' => 1, '안내' => ''];
}
