<?php
$__ad_title  = isset($title) && $title !== '' ? (string) $title : 'Admin';
$__ad_topbar = $ad_topbar ?? null; /* null: 로그인 등 | array: login_admin() */
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($__ad_title, ENT_QUOTES, 'UTF-8'); ?> | Pokazone Admin</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" type="image/png" sizes="48x48" href="/assets/img/favicon-48.png">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" as="style" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body class="ad-body">
<a class="ad-skip" href="#main">본문으로</a>

<header class="ad-topbar" role="banner">
    <a href="/admin/" class="ad-topbar__brand">Pokazone<em>Admin</em></a>
    <div class="ad-topbar__actions">
        <?php if (is_array($__ad_topbar) && !empty($__ad_topbar['ad_id'])): ?>
            <span class="ad-topbar__user"><?php echo htmlspecialchars($__ad_topbar['ad_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($__ad_topbar['ad_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            <a href="/" target="_blank" rel="noopener">사이트</a>
            <a href="/login.php" target="_blank" rel="noopener">사용자 로그인</a>
            <a href="/admin/logout.php">로그아웃</a>
        <?php else: ?>
            <a href="/">← 사이트</a>
        <?php endif; ?>
    </div>
</header>

<?php
$__ad_use_shell = is_array($__ad_topbar) && !empty($__ad_topbar['ad_id']);
?>

<main class="ad-main<?php echo $__ad_use_shell ? ' ad-main--shell' : ''; ?>" id="main" role="main">
<?php if ($__ad_use_shell): ?>
<div class="ad-shell">
<?php include __DIR__ . '/admin_sidebar.php'; ?>
<div class="ad-content">
<?php endif; ?>
