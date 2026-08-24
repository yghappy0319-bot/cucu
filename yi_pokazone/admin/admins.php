<?php
require_once __DIR__ . '/include/admin_init.php';

$title     = '관리자관리';
$ad_topbar = $ad;
$ad_menu   = 'admins';
include __DIR__ . '/include/admin_header.php';

$rows  = [];
$ready = db_table_exists('tb_admin');
if ($ready) {
    $rs = db_query('SELECT * FROM tb_admin ORDER BY ad_idx ASC');
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">tb_admin 테이블이 없습니다. <code>sql/tb_admin.sql</code>을 실행해 주세요.</div>
    <?php else: ?>
        <div class="ad-card" style="margin-bottom:1.25rem;">
            <h2 class="ad-title" style="font-size:1.1rem;">관리자 추가</h2>
            <p class="ad-p">새 백오피스 계정을 만듭니다. 비밀번호는 안전하게 보관하세요.</p>
            <form class="ad-form" action="/proc/admin_admin_add_proc.php" method="post" autocomplete="off">
                <div class="ad-split ad-split--2">
                    <div class="ad-field">
                        <label for="ad_id">아이디</label>
                        <input type="text" id="ad_id" name="ad_id" maxlength="30" required autocomplete="off">
                    </div>
                    <div class="ad-field">
                        <label for="ad_name">이름</label>
                        <input type="text" id="ad_name" name="ad_name" maxlength="50" required>
                    </div>
                    <div class="ad-field">
                        <label for="ad_pw">비밀번호</label>
                        <input type="password" id="ad_pw" name="ad_pw" maxlength="100" required autocomplete="new-password">
                    </div>
                </div>
                <div class="ad-actions" style="margin-top:1rem;">
                    <button type="submit" class="ad-btn">계정 추가</button>
                </div>
            </form>
        </div>

        <div class="ad-card">
            <h1 class="ad-title">관리자 목록</h1>
            <p class="ad-lead">비활성 계정은 로그인할 수 없습니다.</p>
            <div class="ad-table-wrap">
                <table class="ad-table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>아이디</th>
                        <th>이름</th>
                        <th class="ad-num">등급</th>
                        <th>상태</th>
                        <th class="ad-nowrap">마지막 로그인</th>
                        <th>처리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $is_self = (int) $r['ad_idx'] === (int) $ad['ad_idx'];
                        $ok      = (int) $r['ad_status'] === 1;
                        ?>
                        <tr>
                            <td><?php echo (int) $r['ad_idx']; ?></td>
                            <td><?php echo htmlspecialchars($r['ad_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($r['ad_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="ad-num"><?php echo (int) $r['ad_level']; ?></td>
                            <td>
                                <span class="ad-badge <?php echo $ok ? 'ad-badge--ok' : 'ad-badge--bad'; ?>">
                                    <?php echo $ok ? '정상' : '비활성'; ?>
                                </span>
                            </td>
                            <td class="ad-muted ad-nowrap"><?php echo $r['ad_last_login_at'] ? htmlspecialchars($r['ad_last_login_at'], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                            <td>
                                <?php if ($is_self): ?>
                                    <span class="ad-muted">현재 계정</span>
                                <?php else: ?>
                                    <form class="ad-form ad-form--inline" action="/proc/admin_admin_proc.php" method="post" onsubmit="return confirm('이 계정의 활성 상태를 바꿀까요?');">
                                        <input type="hidden" name="ad_idx" value="<?php echo (int) $r['ad_idx']; ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <button type="submit" class="ad-btn"><?php echo $ok ? '비활성화' : '활성화'; ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
