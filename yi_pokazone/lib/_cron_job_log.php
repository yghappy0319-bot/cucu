<?php
/**
 * 크론·웹훅 작업 실행 로그
 */

const CRON_JOB_TRADE_BANK_DEPOSIT_CONFIRM   = 'trade_bank_deposit_confirm';
const CRON_JOB_TRADE_PURCHASE_AUTO_CONFIRM  = 'trade_purchase_auto_confirm';
const CRON_JOB_SNKRDUNK_BOX_CRAWL           = 'snkrdunk_box_crawl';

function cron_job_log_table_ready(): bool
{
    return db_table_exists('tb_cron_job_log');
}

/** @return array<string, string> */
function cron_job_log_job_labels(): array
{
    return [
        CRON_JOB_TRADE_BANK_DEPOSIT_CONFIRM  => '무통장 입금 자동확인',
        CRON_JOB_TRADE_PURCHASE_AUTO_CONFIRM => '구매확정 자동처리',
        CRON_JOB_SNKRDUNK_BOX_CRAWL          => '스니덩크 미개봉박스 시세',
    ];
}

function cron_job_log_job_label(string $job): string
{
    $map = cron_job_log_job_labels();

    return $map[$job] ?? $job;
}

function cron_job_log_trigger_detect(): string
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
    if ($method === 'POST') {
        return 'post';
    }
    if (PHP_SAPI === 'cli') {
        return 'cli';
    }

    return 'web';
}

/**
 * @param array{
 *   ok?: bool,
 *   trigger?: string,
 *   message?: string,
 *   count_processed?: int,
 *   count_ok?: int,
 *   count_fail?: int,
 *   ref_id?: int,
 *   duration_ms?: int,
 *   detail?: array<string, mixed>
 * } $data
 */
function cron_job_log_write(string $job, array $data): void
{
    if ($job === '' || !cron_job_log_table_ready()) {
        return;
    }

    $ok          = !empty($data['ok']) ? 1 : 0;
    $trigger     = trim((string) ($data['trigger'] ?? cron_job_log_trigger_detect()));
    if (!in_array($trigger, ['cli', 'web', 'post'], true)) {
        $trigger = 'web';
    }
    $message     = mb_substr(trim((string) ($data['message'] ?? '')), 0, 500);
    $processed   = max(0, (int) ($data['count_processed'] ?? 0));
    $count_ok    = max(0, (int) ($data['count_ok'] ?? 0));
    $count_fail  = max(0, (int) ($data['count_fail'] ?? 0));
    $duration_ms = max(0, (int) ($data['duration_ms'] ?? 0));
    $ref_id      = (int) ($data['ref_id'] ?? 0);
    $ref_sql     = $ref_id > 0 ? (string) $ref_id : 'NULL';

    $detail_json = '';
    if (!empty($data['detail']) && is_array($data['detail'])) {
        $encoded = json_encode($data['detail'], JSON_UNESCAPED_UNICODE);
        if (is_string($encoded)) {
            $detail_json = mb_substr($encoded, 0, 1000);
        }
    }

    db_query("
        INSERT INTO tb_cron_job_log
            (cjl_job, cjl_ok, cjl_trigger, cjl_message,
             cjl_count_processed, cjl_count_ok, cjl_count_fail,
             cjl_ref_id, cjl_duration_ms, cjl_detail)
        VALUES
            ('" . db_escape($job) . "', {$ok}, '" . db_escape($trigger) . "', '" . db_escape($message) . "',
             {$processed}, {$count_ok}, {$count_fail},
             {$ref_sql}, {$duration_ms}, '" . db_escape($detail_json) . "')
    ");
}

/**
 * @return array{total: int, ok: int, fail: int, last_at: ?string, last_ok_at: ?string}
 */
function cron_job_log_summary(string $job, int $hours = 24): array
{
    $out = [
        'total'      => 0,
        'ok'         => 0,
        'fail'       => 0,
        'last_at'    => null,
        'last_ok_at' => null,
    ];
    if ($job === '' || !cron_job_log_table_ready()) {
        return $out;
    }

    $hours = max(1, min(168, $hours));
    $job_esc = db_escape($job);

    $row = db_assoc(db_query("
        SELECT
            COUNT(*) AS total_cnt,
            SUM(CASE WHEN cjl_ok = 1 THEN 1 ELSE 0 END) AS ok_cnt,
            SUM(CASE WHEN cjl_ok = 0 THEN 1 ELSE 0 END) AS fail_cnt,
            MAX(cjl_created_at) AS last_at
        FROM tb_cron_job_log
        WHERE cjl_job = '{$job_esc}'
          AND cjl_created_at >= DATE_SUB(NOW(), INTERVAL {$hours} HOUR)
    "));
    if ($row) {
        $out['total']   = (int) ($row['total_cnt'] ?? 0);
        $out['ok']      = (int) ($row['ok_cnt'] ?? 0);
        $out['fail']    = (int) ($row['fail_cnt'] ?? 0);
        $out['last_at'] = !empty($row['last_at']) ? (string) $row['last_at'] : null;
    }

    $last_ok = db_result("
        SELECT cjl_created_at
        FROM tb_cron_job_log
        WHERE cjl_job = '{$job_esc}' AND cjl_ok = 1
        ORDER BY cjl_idx DESC
        LIMIT 1
    ");
    if ($last_ok !== null && $last_ok !== '') {
        $out['last_ok_at'] = (string) $last_ok;
    }

    return $out;
}
