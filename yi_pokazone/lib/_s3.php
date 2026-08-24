<?php
/**
 * AWS S3 업로드 헬퍼 (uploads/trade, uploads/auction, uploads/community, uploads/inquiry)
 */

const S3_TRADE_PREFIX     = 'uploads/trade';
const S3_AUCTION_PREFIX   = 'uploads/auction';
const S3_COMMUNITY_PREFIX = 'uploads/community';
const S3_INQUIRY_PREFIX   = 'uploads/inquiry';

/**
 * @return array{enabled: bool, region: string, bucket: string, access_key: string, secret_key: string, public_base_url: string, use_for_trade: bool, use_for_auction: bool, use_for_community: bool, use_for_inquiry: bool}
 */
function s3_config(): array
{
    global $s3_enabled, $s3_region, $s3_bucket, $s3_access_key, $s3_secret_key, $s3_public_base_url, $s3_use_for_trade, $s3_use_for_auction, $s3_use_for_community, $s3_use_for_inquiry;

    return [
        'enabled'          => !empty($s3_enabled),
        'region'           => (string) ($s3_region ?? 'ap-northeast-2'),
        'bucket'           => (string) ($s3_bucket ?? ''),
        'access_key'       => (string) ($s3_access_key ?? ''),
        'secret_key'       => (string) ($s3_secret_key ?? ''),
        'public_base_url'  => rtrim((string) ($s3_public_base_url ?? ''), '/'),
        'use_for_trade'    => !empty($s3_use_for_trade),
        'use_for_auction'  => !empty($s3_use_for_auction),
        'use_for_community'=> !empty($s3_use_for_community),
        'use_for_inquiry'  => !empty($s3_use_for_inquiry ?? $s3_use_for_trade ?? false),
    ];
}

function s3_is_configured(): bool
{
    $cfg = s3_config();
    return $cfg['enabled']
        && $cfg['bucket'] !== ''
        && $cfg['access_key'] !== ''
        && $cfg['secret_key'] !== ''
        && $cfg['public_base_url'] !== '';
}

function s3_sdk_ready(): bool
{
    if (!s3_is_configured()) {
        return false;
    }
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    return is_file($autoload);
}

function s3_use_for_trade_uploads(): bool
{
    $cfg = s3_config();
    return s3_sdk_ready() && $cfg['use_for_trade'];
}

function s3_use_for_auction_uploads(): bool
{
    $cfg = s3_config();
    return s3_sdk_ready() && $cfg['use_for_auction'];
}

function s3_use_for_community_uploads(): bool
{
    $cfg = s3_config();
    return s3_sdk_ready() && $cfg['use_for_community'];
}

function s3_use_for_inquiry_uploads(): bool
{
    $cfg = s3_config();
    return s3_sdk_ready() && $cfg['use_for_inquiry'];
}

function s3_should_use_for_base_dir(string $base_dir): bool
{
    $base = trim($base_dir, '/');
    if ($base === S3_TRADE_PREFIX) {
        return s3_use_for_trade_uploads();
    }
    if ($base === S3_AUCTION_PREFIX) {
        return s3_use_for_auction_uploads();
    }
    if ($base === S3_COMMUNITY_PREFIX) {
        return s3_use_for_community_uploads();
    }
    if ($base === S3_INQUIRY_PREFIX) {
        return s3_use_for_inquiry_uploads();
    }

    return false;
}

function s3_is_trade_web_path(string $path): bool
{
    if ($path === '') {
        return false;
    }
    $path = ltrim($path, '/');
    return strpos($path, S3_TRADE_PREFIX . '/') === 0;
}

function s3_is_auction_web_path(string $path): bool
{
    if ($path === '') {
        return false;
    }
    $path = ltrim($path, '/');
    return strpos($path, S3_AUCTION_PREFIX . '/') === 0;
}

function s3_is_community_web_path(string $path): bool
{
    if ($path === '') {
        return false;
    }
    $path = ltrim($path, '/');
    return strpos($path, S3_COMMUNITY_PREFIX . '/') === 0;
}

function s3_is_inquiry_web_path(string $path): bool
{
    if ($path === '') {
        return false;
    }
    $path = ltrim($path, '/');
    return strpos($path, S3_INQUIRY_PREFIX . '/') === 0;
}

function s3_is_managed_web_path(string $path): bool
{
    return (s3_use_for_trade_uploads() && s3_is_trade_web_path($path))
        || (s3_use_for_auction_uploads() && s3_is_auction_web_path($path))
        || (s3_use_for_community_uploads() && s3_is_community_web_path($path))
        || (s3_use_for_inquiry_uploads() && s3_is_inquiry_web_path($path));
}

function s3_web_path_to_key(string $web_path): string
{
    return ltrim($web_path, '/');
}

/**
 * @return object|null Aws\S3\S3Client
 */
function s3_client()
{
    static $client = null;
    static $failed = false;

    if ($failed || !s3_is_configured()) {
        return null;
    }
    if ($client !== null) {
        return $client;
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log('S3: composer vendor/autoload.php 가 없습니다. public_html 에서 composer install 을 실행하세요.');
        $failed = true;
        return null;
    }
    require_once $autoload;

    $cfg = s3_config();
    try {
        $client = new Aws\S3\S3Client([
            'version'     => 'latest',
            'region'      => $cfg['region'],
            'credentials' => [
                'key'    => $cfg['access_key'],
                'secret' => $cfg['secret_key'],
            ],
        ]);
    } catch (Throwable $e) {
        error_log('S3 client init failed: ' . $e->getMessage());
        $failed = true;
        return null;
    }

    return $client;
}

/**
 * @return array{ok: bool, error: string}
 */
function s3_upload_file(string $tmp_path, string $web_path, string $mime): array
{
    $fail = ['ok' => false, 'error' => 'S3 업로드에 실패했습니다.'];

    $client = s3_client();
    if ($client === null) {
        $fail['error'] = 'S3 설정이 완료되지 않았거나 SDK를 불러올 수 없습니다.';
        return $fail;
    }

    $cfg = s3_config();
    $key = s3_web_path_to_key($web_path);

    try {
        $client->putObject([
            'Bucket'      => $cfg['bucket'],
            'Key'         => $key,
            'SourceFile'  => $tmp_path,
            'ContentType' => $mime !== '' ? $mime : 'application/octet-stream',
        ]);
    } catch (Throwable $e) {
        error_log('S3 putObject failed [' . $key . ']: ' . $e->getMessage());
        $fail['error'] = 'S3 업로드 중 오류가 발생했습니다.';
        return $fail;
    }

    return ['ok' => true, 'error' => ''];
}

function s3_delete_web_path(string $web_path): void
{
    if (!s3_is_managed_web_path($web_path)) {
        return;
    }

    $client = s3_client();
    if ($client === null) {
        return;
    }

    $cfg = s3_config();
    $key = s3_web_path_to_key($web_path);

    try {
        $client->deleteObject([
            'Bucket' => $cfg['bucket'],
            'Key'    => $key,
        ]);
    } catch (Throwable $e) {
        error_log('S3 deleteObject failed [' . $key . ']: ' . $e->getMessage());
    }
}

/**
 * S3에 올린 첨부 경로면 img 도메인 공개 URL, 아니면 빈 문자열
 */
function s3_public_url_for_path(string $path): string
{
    if (!s3_is_managed_web_path($path)) {
        return '';
    }

    $cfg = s3_config();
    if ($cfg['public_base_url'] === '') {
        return '';
    }

    return $cfg['public_base_url'] . '/' . s3_web_path_to_key($path);
}

/**
 * S3에 올린 첨부 목록 롤백
 *
 * @param list<string> $web_paths
 */
function s3_rollback_uploaded_paths(array $web_paths): void
{
    foreach ($web_paths as $p) {
        if (is_string($p) && $p !== '') {
            s3_delete_web_path($p);
        }
    }
}
