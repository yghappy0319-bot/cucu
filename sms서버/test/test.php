<?php
include("/home/sms/public_html/lib/function.php");
include("/home/sms/public_html/lib/errorlist_function.php");
include("/home/sms/public_html/lib/perfectpanel.php");

function 퍼팩트패널v2($url, $data, $api_key_v2)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-Api-Key: {$api_key_v2}",
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $curl_error = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);

    if ($curl_error) {
        return ['error' => true, 'message' => $curl_error, 'raw' => $response];
    }

    $decoded = json_decode($response, true);
    if ($decoded === null && $response !== '' && $response !== 'null') {
        return ['error' => true, 'message' => 'JSON 파싱 실패', 'raw' => $response];
    }

    return $decoded;
}

$sites = [];
$site_result = db_query("select site, api_key_v2 from site where api_key_v2 != '' and luckypanel = 0 order by site asc");
while ($row = db_fetch($site_result)) {
    $sites[] = $row;
}

$result = null;
$request_info = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'charge') {
    $site = trim($_POST['site'] ?? '');
    $api_key_v2 = trim($_POST['api_key_v2'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $method = trim($_POST['method'] ?? 'Bonus');
    $memo = trim($_POST['memo'] ?? '계좌이체');
    $affiliate_commission = isset($_POST['affiliate_commission']);

    $site = rtrim($site, '/');
    $url = $site . '/adminapi/v2/payments/add';
    $adddata = [
        'username' => $username,
        'amount' => $amount,
        'method' => $method,
        'memo' => $memo,
        'affiliate_commission' => $affiliate_commission,
    ];

    $request_info = [
        'url' => $url,
        'headers' => [
            'Content-Type' => 'application/json',
            'X-Api-Key' => $api_key_v2,
        ],
        'body' => $adddata,
    ];

    if ($site === '' || $api_key_v2 === '' || $username === '' || $amount <= 0) {
        $result = ['error' => true, 'message' => '사이트 URL, API Key, 아이디, 금액을 모두 입력해 주세요.'];
    } else {
        $result = 퍼팩트패널v2($url, $adddata, $api_key_v2);
    }
}

$form = [
    'site' => $_POST['site'] ?? 'https://snseyes.net',
    'api_key_v2' => $_POST['api_key_v2'] ?? '',
    'username' => $_POST['username'] ?? '',
    'amount' => $_POST['amount'] ?? '1000',
    'method' => $_POST['method'] ?? 'Bonus',
    'memo' => $_POST['memo'] ?? '계좌이체',
    'affiliate_commission' => isset($_POST['affiliate_commission']) ? true : !isset($_POST['action']),
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>퍼팩트패널 v2 포인트 충전 테스트</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f4f6f8;
            color: #1f2937;
            margin: 0;
            padding: 24px;
        }
        .wrap {
            max-width: 760px;
            margin: 0 auto;
        }
        h1 {
            font-size: 22px;
            margin: 0 0 8px;
        }
        .desc {
            color: #6b7280;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 16px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
        }
        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 14px;
        }
        .row {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 14px;
        }
        .row input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        button {
            background: #2563eb;
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 12px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #1d4ed8; }
        pre {
            background: #111827;
            color: #e5e7eb;
            padding: 14px;
            border-radius: 8px;
            overflow: auto;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .ok { color: #059669; font-weight: 600; }
        .fail { color: #dc2626; font-weight: 600; }
        .hint {
            font-size: 12px;
            color: #6b7280;
            margin-top: -10px;
            margin-bottom: 14px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>퍼팩트패널 v2 포인트 충전 테스트</h1>
    <p class="desc">all_bank_v2.php 와 동일한 API(<code>/adminapi/v2/payments/add</code>)로 수동 충전을 테스트합니다.</p>

    <div class="card">
        <form method="post">
            <input type="hidden" name="action" value="charge">

            <?php if (count($sites) > 0) { ?>
            <label for="site_select">등록된 사이트 불러오기</label>
            <select id="site_select">
                <option value="">직접 입력</option>
                <?php foreach ($sites as $s) { ?>
                <option
                    value="<?= htmlspecialchars($s['site'], ENT_QUOTES, 'UTF-8') ?>"
                    data-api-key="<?= htmlspecialchars($s['api_key_v2'], ENT_QUOTES, 'UTF-8') ?>"
                ><?= htmlspecialchars($s['site'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php } ?>
            </select>
            <?php } ?>

            <label for="site">사이트 URL</label>
            <input type="text" id="site" name="site" value="<?= htmlspecialchars($form['site'], ENT_QUOTES, 'UTF-8') ?>" placeholder="https://example.com">
            <p class="hint">프로토콜 포함, 끝 슬래시 없이 입력</p>

            <label for="api_key_v2">API Key v2</label>
            <input type="text" id="api_key_v2" name="api_key_v2" value="<?= htmlspecialchars($form['api_key_v2'], ENT_QUOTES, 'UTF-8') ?>" placeholder="X-Api-Key 값">

            <label for="username">충전 대상 아이디 (username)</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($form['username'], ENT_QUOTES, 'UTF-8') ?>" required>

            <label for="amount">충전 포인트 (amount)</label>
            <input type="number" id="amount" name="amount" value="<?= htmlspecialchars($form['amount'], ENT_QUOTES, 'UTF-8') ?>" min="1" step="1" required>

            <label for="method">결제 수단 (method)</label>
            <input type="text" id="method" name="method" value="<?= htmlspecialchars($form['method'], ENT_QUOTES, 'UTF-8') ?>">

            <label for="memo">메모 (memo)</label>
            <input type="text" id="memo" name="memo" value="<?= htmlspecialchars($form['memo'], ENT_QUOTES, 'UTF-8') ?>">

            <div class="row">
                <input type="checkbox" id="affiliate_commission" name="affiliate_commission" value="1" <?= $form['affiliate_commission'] ? 'checked' : '' ?>>
                <label for="affiliate_commission" style="margin:0;">affiliate_commission 적용</label>
            </div>

            <button type="submit">포인트 충전 테스트</button>
        </form>
    </div>

    <?php if ($request_info !== null) { ?>
    <div class="card">
        <h2 style="font-size:16px;margin:0 0 12px;">요청 정보</h2>
        <pre><?= htmlspecialchars(json_encode($request_info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8') ?></pre>
    </div>
    <?php } ?>

    <?php if ($result !== null) { ?>
    <div class="card">
        <h2 style="font-size:16px;margin:0 0 12px;">API 응답</h2>
        <?php
        $payment_id = (is_array($result) && isset($result['data']['payment_id']))
            ? (int) $result['data']['payment_id'] : null;
        $success = $payment_id !== null && $payment_id >= 0;
        ?>
        <p class="<?= $success ? 'ok' : 'fail' ?>">
            <?= $success ? "충전 성공 (payment_id: {$payment_id})" : '충전 실패 또는 payment_id 없음' ?>
        </p>
        <pre><?= htmlspecialchars(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8') ?></pre>
    </div>
    <?php } ?>
</div>

<script>
(function () {
    var select = document.getElementById('site_select');
    if (!select) return;

    select.addEventListener('change', function () {
        var option = select.options[select.selectedIndex];
        if (!option.value) return;

        document.getElementById('site').value = option.value;
        document.getElementById('api_key_v2').value = option.getAttribute('data-api-key') || '';
    });
})();
</script>
</body>
</html>
