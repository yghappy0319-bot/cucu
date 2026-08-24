<?php

/** @return list<string> */
function draw_card_grade_list(): array
{
    return ['일반', 'A', 'R', 'RR', 'RRR', 'AR', 'S', 'SR', 'SSR', 'HR', 'UR', 'SAR'];
}

function draw_card_grade_is_ascii_code(string $grade): bool
{
    return (bool) preg_match('/^[A-Z]+$/', $grade);
}

function draw_card_grade_normalize(?string $grade): string
{
    $grade = trim((string) $grade);
    if ($grade === '') {
        return '';
    }
    if ($grade === '어메이징레어') {
        return 'A';
    }
    foreach (draw_card_grade_list() as $allowed) {
        if (draw_card_grade_is_ascii_code($allowed)) {
            if (strtoupper($grade) === $allowed) {
                return $allowed;
            }
            continue;
        }
        if ($grade === $allowed) {
            return $allowed;
        }
    }
    return '';
}

function draw_expansion_cards_per_pack(): int
{
    return 5;
}

function draw_highclass_cards_per_pack(): int
{
    return 10;
}

function draw_cards_per_pack_for_type(string $pack_type): int
{
    if ($pack_type === 'highclass') {
        return draw_highclass_cards_per_pack();
    }
    return draw_expansion_cards_per_pack();
}

/** @return list<string> */
function draw_box_plan_pack_types(): array
{
    return ['expansion', 'highclass'];
}

function draw_pack_type_supports_box_plan(string $pack_type): bool
{
    return in_array($pack_type, draw_box_plan_pack_types(), true);
}

/**
 * @param list<list<string>> $packs
 */
function draw_box_place_grade_random(array &$packs, string $grade, int $count, int $cards_per_pack, bool $exclude_last_slot = true): int
{
    $pack_count = count($packs);
    if ($pack_count < 1 || $count < 1) {
        return 0;
    }

    $max_slot = $exclude_last_slot ? max(0, $cards_per_pack - 2) : $cards_per_pack - 1;
    $placed = 0;
    $attempts = 0;
    $max_attempts = max(500, $count * 50);

    while ($placed < $count && $attempts < $max_attempts) {
        $attempts++;
        $pi = mt_rand(0, $pack_count - 1);
        $si = mt_rand(0, $max_slot);
        if (($packs[$pi][$si] ?? '') === '일반') {
            $packs[$pi][$si] = $grade;
            $placed++;
        }
    }

    return $placed;
}

/**
 * @param list<list<string>> $packs
 */
function draw_box_count_grade(array $packs, string $grade = '일반'): int
{
    $n = 0;
    foreach ($packs as $pack) {
        foreach ($pack as $g) {
            if ($g === $grade) {
                $n++;
            }
        }
    }
    return $n;
}

function draw_box_session_key(int $dp_idx): string
{
    return 'draw_box_' . $dp_idx;
}

/**
 * 확장팩 상자 봉입 계획 (몬스터볼 1개 = 5장, SR은 항상 5번째 슬롯)
 *
 * @return array{packs: list<list<string>>, summary: array<string, int>}
 */
function draw_create_expansion_box_plan(int $pack_count): array
{
    $pack_count = max(1, $pack_count);
    $cards_per_pack = draw_expansion_cards_per_pack();

    $sr_count = mt_rand(1, 100) <= 15 ? 2 : 1;
    $sr_count = min($sr_count, $pack_count);

    $r_count = mt_rand(8, 12);
    $rr_count = mt_rand(4, 6);
    $rrr_count = mt_rand(2, 3);

    $packs = [];
    for ($i = 0; $i < $pack_count; $i++) {
        $packs[] = array_fill(0, $cards_per_pack, '일반');
    }

    $pack_indices = range(0, $pack_count - 1);
    shuffle($pack_indices);
    for ($i = 0; $i < $sr_count; $i++) {
        $packs[$pack_indices[$i]][$cards_per_pack - 1] = 'SR';
    }

    draw_box_place_grade_random($packs, 'R', $r_count, $cards_per_pack);
    draw_box_place_grade_random($packs, 'RR', $rr_count, $cards_per_pack);
    draw_box_place_grade_random($packs, 'RRR', $rrr_count, $cards_per_pack);

    return [
        'packs' => $packs,
        'summary' => [
            'pack_count' => $pack_count,
            'cards_per_pack' => $cards_per_pack,
            'R' => $r_count,
            'RR' => $rr_count,
            'RRR' => $rrr_count,
            'SR' => $sr_count,
            '일반' => draw_box_count_grade($packs, '일반'),
        ],
    ];
}

/**
 * 하이클래스팩(샤이니스타V 등) 1박스 10팩 — 상자 전체 봉입
 *
 * @return array{packs: list<list<string>>, summary: array<string, int>}
 */
function draw_create_highclass_box_plan(int $pack_count): array
{
    $pack_count = max(1, $pack_count);
    $cards_per_pack = draw_highclass_cards_per_pack();

    $s_count = mt_rand(3, 4);
    $ssr_count = 1;
    $rr_count = mt_rand(4, 5);
    $rrr_count = mt_rand(1, 2);
    $a_count = 1;
    $sr_count = mt_rand(1, 100) <= 20 ? 1 : 0;

    $packs = [];
    for ($i = 0; $i < $pack_count; $i++) {
        $packs[] = array_fill(0, $cards_per_pack, '일반');
    }

    $pack_indices = range(0, $pack_count - 1);
    shuffle($pack_indices);

    $ssr_pack = $pack_indices[0];
    $packs[$ssr_pack][$cards_per_pack - 1] = 'SSR';

    if ($sr_count > 0 && $pack_count > 1) {
        $sr_pack = $pack_indices[1];
        $packs[$sr_pack][$cards_per_pack - 1] = 'SR';
    }

    draw_box_place_grade_random($packs, 'S', $s_count, $cards_per_pack);
    draw_box_place_grade_random($packs, 'RR', $rr_count, $cards_per_pack);
    draw_box_place_grade_random($packs, 'RRR', $rrr_count, $cards_per_pack);
    draw_box_place_grade_random($packs, 'A', $a_count, $cards_per_pack);

    return [
        'packs' => $packs,
        'summary' => [
            'pack_count' => $pack_count,
            'cards_per_pack' => $cards_per_pack,
            'S' => $s_count,
            'SSR' => $ssr_count,
            'RR' => $rr_count,
            'RRR' => $rrr_count,
            'A' => $a_count,
            'SR' => $sr_count,
            '일반' => draw_box_count_grade($packs, '일반'),
        ],
    ];
}

function draw_create_box_plan(string $pack_type, int $pack_count): array
{
    if ($pack_type === 'highclass') {
        return draw_create_highclass_box_plan($pack_count);
    }
    return draw_create_expansion_box_plan($pack_count);
}

function draw_initialize_box_plan(int $dp_idx, string $pack_type, int $pack_count): void
{
    $plan = draw_create_box_plan($pack_type, $pack_count);
    $_SESSION[draw_box_session_key($dp_idx)] = [
        'pack_type' => $pack_type,
        'pack_total' => $pack_count,
        'summary' => $plan['summary'],
        'packs' => $plan['packs'],
        'opened_slots' => [],
        'completed' => false,
    ];
}

/**
 * @param array<string, int> $summary
 */
function draw_format_box_rate_line(string $pack_type, array $summary, int $remaining, int $pack_total): string
{
    $tail = ' (남은 팩 ' . number_format($remaining) . '/' . number_format($pack_total) . ')';

    if ($pack_type === 'highclass') {
        return '이 상자 봉입: S ' . (int) ($summary['S'] ?? 0) . '장 · '
            . 'SSR ' . (int) ($summary['SSR'] ?? 0) . '장 · '
            . 'RR ' . (int) ($summary['RR'] ?? 0) . '장 · '
            . 'RRR ' . (int) ($summary['RRR'] ?? 0) . '장 · '
            . 'A ' . (int) ($summary['A'] ?? 0) . '장 · '
            . 'SR ' . (int) ($summary['SR'] ?? 0) . '장' . $tail;
    }

    return '이 상자 봉입: R ' . (int) ($summary['R'] ?? 0) . '장 · '
        . 'RR ' . (int) ($summary['RR'] ?? 0) . '장 · '
        . 'RRR ' . (int) ($summary['RRR'] ?? 0) . '장 · '
        . 'SR ' . (int) ($summary['SR'] ?? 0) . '장' . $tail;
}

function draw_box_rate_subline(string $pack_type, bool $completed): string
{
    if ($completed) {
        return '상자 개봉 완료 · 몬스터볼을 누르면 새 봉입으로 시작합니다.';
    }
    if ($pack_type === 'highclass') {
        return 'SSR·SR은 해당 팩 맨 뒷장 · SR은 약 20% 확률(몇 박스에 1장) · 개봉한 팩은 되돌릴 수 없습니다.';
    }
    return 'SR은 팩당 맨 뒷장 · SR 2장 확률 약 15% · 개봉한 팩은 되돌릴 수 없습니다.';
}

/**
 * @return array{packs: list<list<string>>, summary: array<string, int>, pack_type: string}|null
 */
function draw_ensure_box_plan(int $dp_idx, string $pack_type, int $pack_count): ?array
{
    if ($dp_idx < 1 || !draw_pack_type_supports_box_plan($pack_type)) {
        return null;
    }

    if (!isset($_SESSION) || !is_array($_SESSION)) {
        return null;
    }

    $key = draw_box_session_key($dp_idx);
    $box = $_SESSION[$key] ?? null;

    $needs_new = !is_array($box)
        || !is_array($box['summary'] ?? null)
        || ($box['pack_type'] ?? '') !== $pack_type
        || (int) ($box['pack_total'] ?? 0) !== $pack_count;

    if ($needs_new) {
        draw_initialize_box_plan($dp_idx, $pack_type, $pack_count);
    }

    return $_SESSION[$key];
}

/**
 * @return array{packs: list<list<string>>, summary: array<string, int>, remaining: int, pack_total: int}|null
 */
function draw_box_session_summary(int $dp_idx, string $pack_type, int $pack_count): ?array
{
    $box = draw_ensure_box_plan($dp_idx, $pack_type, $pack_count);
    if ($box === null) {
        return null;
    }

    $remaining = is_array($box['packs'] ?? null) ? count($box['packs']) : 0;
    $opened_slots = is_array($box['opened_slots'] ?? null) ? $box['opened_slots'] : [];

    return [
        'summary' => is_array($box['summary'] ?? null) ? $box['summary'] : [],
        'remaining' => $remaining,
        'pack_total' => (int) ($box['pack_total'] ?? $pack_count),
        'completed' => !empty($box['completed']),
        'opened_slots' => $opened_slots,
        'opened' => count($opened_slots),
    ];
}

function draw_reset_box_plan(int $dp_idx): void
{
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        return;
    }
    $key = draw_box_session_key($dp_idx);
    unset($_SESSION[$key]);
}

/**
 * 몬스터볼(팩) 1개 개봉 — 되돌릴 수 없음. 상자 전부 개봉 후 다음 클릭 시에만 새 봉입.
 *
 * @param-out string|null $error
 * @return list<string>|null
 */
function draw_consume_pack_for_ball(int $dp_idx, string $pack_type, int $pack_count, int $ball_idx, ?string &$error = null): ?array
{
    $error = null;
    $box = draw_ensure_box_plan($dp_idx, $pack_type, $pack_count);
    if ($box === null) {
        $error = '상자 정보를 불러올 수 없습니다.';
        return null;
    }

    $key = draw_box_session_key($dp_idx);

    if (!empty($_SESSION[$key]['completed']) && empty($_SESSION[$key]['packs'])) {
        draw_initialize_box_plan($dp_idx, $pack_type, $pack_count);
        $_SESSION[$key]['new_box'] = true;
    }

    $opened_slots = is_array($_SESSION[$key]['opened_slots'] ?? null)
        ? $_SESSION[$key]['opened_slots']
        : [];

    if (in_array($ball_idx, $opened_slots, true)) {
        $error = '이미 개봉한 몬스터볼입니다.';
        return null;
    }

    if (empty($_SESSION[$key]['packs']) || !is_array($_SESSION[$key]['packs'])) {
        $error = '남은 팩이 없습니다.';
        return null;
    }

    $pack = array_shift($_SESSION[$key]['packs']);
    if (!is_array($pack) || empty($pack)) {
        $error = '팩 구성을 불러오지 못했습니다.';
        return null;
    }

    $_SESSION[$key]['opened_slots'][] = $ball_idx;
    sort($_SESSION[$key]['opened_slots']);

    if (empty($_SESSION[$key]['packs'])) {
        $_SESSION[$key]['completed'] = true;
    }

    return $pack;
}

/**
 * @return array<string, list<array{src: string, alt: string, dpc_idx: int}>>
 */
function draw_load_card_pools_by_grade(int $dp_idx): array
{
    $pools = [];
    foreach (draw_card_grade_list() as $grade) {
        $pools[$grade] = [];
    }
    $pools[''] = [];

    if ($dp_idx < 1 || !db_table_exists('tb_draw_product_card')) {
        return $pools;
    }

    $has_grade = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_draw_product_card LIKE 'dpc_grade'"));
    $grade_sel = $has_grade ? ', dpc_grade' : '';

    $rs = db_query("
        SELECT dpc_idx, dpc_image_path, dpc_orig_name{$grade_sel}
        FROM tb_draw_product_card
        WHERE dp_idx = {$dp_idx}
        ORDER BY dpc_idx ASC
    ");
    while ($r = db_assoc($rs)) {
        $img = trim((string) ($r['dpc_image_path'] ?? ''));
        if ($img === '') {
            continue;
        }
        $item = [
            'dpc_idx' => (int) ($r['dpc_idx'] ?? 0),
            'src' => public_url($img),
            'alt' => trim((string) ($r['dpc_orig_name'] ?? '')) !== ''
                ? trim((string) $r['dpc_orig_name'])
                : '카드',
        ];
        $grade = $has_grade ? draw_card_grade_normalize($r['dpc_grade'] ?? '') : '';
        if ($grade === '' || !isset($pools[$grade])) {
            $pools['일반'][] = $item;
            continue;
        }
        $pools[$grade][] = $item;
    }

    return $pools;
}

/**
 * @param array<string, list<array{src: string, alt: string, dpc_idx: int}>> $pools
 * @param list<string> $fallback_grades
 */
function draw_pick_from_grade_pool(array $pools, string $grade, array $fallback_grades = []): ?array
{
    $grade = draw_card_grade_normalize($grade);
    if ($grade === '') {
        $grade = '일반';
    }

    $try = array_merge([$grade], $fallback_grades, draw_card_grade_list());
    $seen = [];
    foreach ($try as $g) {
        $g = draw_card_grade_normalize($g);
        if ($g === '' || isset($seen[$g])) {
            continue;
        }
        $seen[$g] = true;
        if (empty($pools[$g])) {
            continue;
        }
        $pool = $pools[$g];
        return $pool[array_rand($pool)];
    }

    foreach ($pools as $pool) {
        if (!empty($pool)) {
            return $pool[array_rand($pool)];
        }
    }

    return null;
}

/**
 * @param list<string> $grade_slots
 * @return list<array{front_src: string, front_alt: string, grade: string}>
 */
function draw_build_slides_from_grades(array $grade_slots, array $pools): array
{
    $slides = [];
    foreach ($grade_slots as $grade) {
        $pick = draw_pick_from_grade_pool($pools, $grade);
        if ($pick === null) {
            continue;
        }
        $slides[] = [
            'front_src' => $pick['src'],
            'front_alt' => $pick['alt'],
            'grade' => draw_card_grade_normalize($grade) ?: '일반',
        ];
    }
    return $slides;
}

/**
 * @return array{cardback: string, slides: list<array{front_src: string, front_alt: string}>, box: array<string, mixed>|null}
 */
function draw_pick_random_cards(int $dp_idx, int $count, string $pack_type = 'expansion', int $pack_count = 30, int $ball_idx = -1): array
{
    $count = max(1, min(20, $count));
    $cardback = public_url('/assets/img/cardback.png');
    $pack_type = trim($pack_type) !== '' ? trim($pack_type) : 'expansion';
    $pack_count = $pack_count > 0 ? $pack_count : 30;

    $box_meta = null;

    $cards_per_pack = draw_cards_per_pack_for_type($pack_type);
    if (draw_pack_type_supports_box_plan($pack_type) && $count === $cards_per_pack && $ball_idx >= 0) {
        $open_err = null;
        $grade_slots = draw_consume_pack_for_ball($dp_idx, $pack_type, $pack_count, $ball_idx, $open_err);
        if ($grade_slots !== null) {
            $pools = draw_load_card_pools_by_grade($dp_idx);
            $slides = draw_build_slides_from_grades($grade_slots, $pools);
            $summary = draw_box_session_summary($dp_idx, $pack_type, $pack_count);
            if ($summary !== null) {
                $key = draw_box_session_key($dp_idx);
                $is_new_box = !empty($_SESSION[$key]['new_box']);
                if ($is_new_box) {
                    unset($_SESSION[$key]['new_box']);
                }
                $box_meta = [
                    'summary' => $summary['summary'],
                    'remaining' => $summary['remaining'],
                    'pack_total' => $summary['pack_total'],
                    'completed' => !empty($summary['completed']),
                    'opened' => (int) ($summary['opened'] ?? 0),
                    'new_box' => $is_new_box,
                ];
            }
            if (!empty($slides)) {
                return [
                    'cardback' => $cardback,
                    'slides' => $slides,
                    'box' => $box_meta,
                ];
            }
        }
        if ($open_err !== null && $open_err !== '') {
            return [
                'cardback' => $cardback,
                'slides' => [],
                'box' => null,
                'error' => $open_err,
            ];
        }
    }

    $slides = [];
    $pools = draw_load_card_pools_by_grade($dp_idx);
    $flat = [];
    foreach ($pools as $pool) {
        foreach ($pool as $item) {
            $flat[] = $item;
        }
    }

    if (!empty($flat)) {
        for ($i = 0; $i < $count; $i++) {
            $pick = $flat[array_rand($flat)];
            $slides[] = [
                'front_src' => $pick['src'],
                'front_alt' => $pick['alt'],
            ];
        }
    }

    if (empty($slides)) {
        for ($i = 0; $i < $count; $i++) {
            $slides[] = [
                'front_src' => $cardback,
                'front_alt' => '카드 ' . ($i + 1),
            ];
        }
    }

    return [
        'cardback' => $cardback,
        'slides' => $slides,
        'box' => $box_meta,
    ];
}

function draw_json_response(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
