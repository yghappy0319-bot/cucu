<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../auction/lib/_member_auction_ban.php';
require_once __DIR__ . '/../lib/_member_cash.php';

$title     = '회원 수정';
$ad_topbar = $ad;
$ad_menu   = 'members';

$idx = (int) ($_GET['idx'] ?? 0);
if ($idx < 1) {
    header('Location: /admin/members.php');
    exit;
}

$ready = db_table_exists('tb_member');
$row   = null;
$cash_ready = false;

$status_labels = [
    '0' => '휴면',
    '1' => '정상',
    '2' => '정지',
    '3' => '탈퇴',
];

if ($ready) {
    $auction_ban_ready = member_auction_ban_column_ready();
    $auction_ban_cols  = $auction_ban_ready
        ? ', mb_auction_banned, mb_auction_banned_at, mb_auction_ban_memo'
        : '';
    $cash_ready  = member_cash_column_ready();
    $cash_select = $cash_ready ? ', mb_cash' : '';
    $rs  = db_query("
        SELECT mb_idx, mb_id, mb_name, mb_nick, mb_email, mb_phone, mb_level, mb_status,
               mb_point, mb_created_at, mb_last_login_at{$auction_ban_cols}{$cash_select}
        FROM tb_member
        WHERE mb_idx = {$idx}
        LIMIT 1
    ");
    $row = db_assoc($rs);
}

$auction_ban = ($row && member_auction_ban_column_ready())
    ? member_auction_ban_info((int) ($row['mb_idx'] ?? 0))
    : ['banned' => false, 'banned_at' => null, 'memo' => ''];

include __DIR__ . '/include/admin_header.php';
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">tb_member 테이블이 없습니다.</div>
    <?php elseif (!$row): ?>
        <div class="ad-alert ad-alert--error">존재하지 않는 회원입니다.</div>
        <p class="ad-p"><a href="/admin/members.php" class="ad-btn ad-btn--ghost">← 회원 목록</a></p>
    <?php else: ?>
        <div class="ad-card" style="margin-bottom:1.25rem;">
            <div class="ad-actions" style="margin-bottom:1rem;">
                <a href="/admin/members.php" class="ad-btn ad-btn--ghost">← 회원 목록</a>
                <a href="/admin/coupon_issue.php?mb_idx=<?php echo (int) $row['mb_idx']; ?>" class="ad-btn ad-btn--ghost">쿠폰 발행</a>
            </div>
            <h1 class="ad-title">회원 수정 · #<?php echo (int) $row['mb_idx']; ?></h1>
            <p class="ad-p ad-muted" style="margin-top:0;">
                가입 <?php echo htmlspecialchars(substr((string) $row['mb_created_at'], 0, 16), ENT_QUOTES, 'UTF-8'); ?>
                <?php if (!empty($row['mb_last_login_at'])): ?>
                    · 최근 로그인 <?php echo htmlspecialchars(substr((string) $row['mb_last_login_at'], 0, 16), ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </p>
            <?php
            $mb_point_view = (int) ($row['mb_point'] ?? 0);
            $mb_cash_view  = (int) ($row['mb_cash'] ?? 0);
            $cash_log_ok   = function_exists('member_cash_log_table_ready') && member_cash_log_table_ready();
            $point_log_ok  = db_table_exists('tb_point_log');
            ?>
            <div class="ad-stat-grid" style="margin-top:1rem;">
                <?php if ($cash_ready): ?>
                <div class="ad-stat ad-stat--accent">
                    <div class="ad-stat__val">₩<?php echo number_format($mb_cash_view); ?></div>
                    <div class="ad-stat__label">보유 캐시</div>
                    <?php if ($cash_log_ok): ?>
                        <div class="ad-stat__sub"><a href="/admin/cash_logs.php?q=<?php echo (int) $row['mb_idx']; ?>">내역</a></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="ad-stat">
                    <div class="ad-stat__val"><?php echo number_format($mb_point_view); ?> P</div>
                    <div class="ad-stat__label">보유 포인트</div>
                    <?php if ($point_log_ok): ?>
                        <div class="ad-stat__sub"><a href="/admin/points.php?q=<?php echo (int) $row['mb_idx']; ?>">내역</a></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $adjust_return = '/admin/member_edit.php?idx=' . (int) $row['mb_idx'];
            $can_adjust_cash  = $cash_ready && $cash_log_ok;
            $can_adjust_point = $point_log_ok;
            ?>
            <?php if ($can_adjust_cash || $can_adjust_point): ?>
            <div class="ad-split ad-split--2" style="margin-top:1.25rem;">
                <?php if ($can_adjust_cash): ?>
                <form class="ad-form" action="/proc/admin_member_cash_proc.php" method="post">
                    <input type="hidden" name="mb_idx" value="<?php echo (int) $row['mb_idx']; ?>">
                    <input type="hidden" name="return" value="<?php echo htmlspecialchars($adjust_return, ENT_QUOTES, 'UTF-8'); ?>">
                    <h2 class="ad-title" style="font-size:1.05rem;">캐시 지급·차감</h2>
                    <p class="ad-p ad-muted" style="margin-top:0;">보유 ₩<?php echo number_format($mb_cash_view); ?> · 차감은 잔액을 넘을 수 없습니다.</p>
                    <div class="ad-field">
                        <label for="cash_amount">금액 (원)</label>
                        <input type="number" id="cash_amount" name="amount" min="1" step="1" required placeholder="예: 10000">
                    </div>
                    <div class="ad-field">
                        <label for="cash_memo">사유</label>
                        <input type="text" id="cash_memo" name="memo" maxlength="200" placeholder="예: 오지급 회수">
                    </div>
                    <div class="ad-actions" style="margin-top:0.75rem;">
                        <button type="submit" name="op" value="grant" class="ad-btn"
                                onclick="return confirm('이 회원에게 캐시를 지급할까요?');">지급</button>
                        <button type="submit" name="op" value="deduct" class="ad-btn ad-btn--danger"
                                onclick="return confirm('이 회원의 캐시를 차감할까요?\n보유액보다 많으면 처리되지 않습니다.');">차감</button>
                    </div>
                </form>
                <?php endif; ?>
                <?php if ($can_adjust_point): ?>
                <form class="ad-form" action="/proc/admin_member_point_proc.php" method="post">
                    <input type="hidden" name="mb_idx" value="<?php echo (int) $row['mb_idx']; ?>">
                    <input type="hidden" name="return" value="<?php echo htmlspecialchars($adjust_return, ENT_QUOTES, 'UTF-8'); ?>">
                    <h2 class="ad-title" style="font-size:1.05rem;">포인트 지급·차감</h2>
                    <p class="ad-p ad-muted" style="margin-top:0;">보유 <?php echo number_format($mb_point_view); ?> P · 차감은 잔액을 넘을 수 없습니다.</p>
                    <div class="ad-field">
                        <label for="point_amount">포인트</label>
                        <input type="number" id="point_amount" name="amount" min="1" step="1" required placeholder="예: 100">
                    </div>
                    <div class="ad-field">
                        <label for="point_memo">사유</label>
                        <input type="text" id="point_memo" name="memo" maxlength="200" placeholder="예: 이벤트 회수">
                    </div>
                    <div class="ad-actions" style="margin-top:0.75rem;">
                        <button type="submit" name="op" value="grant" class="ad-btn"
                                onclick="return confirm('이 회원에게 포인트를 지급할까요?');">지급</button>
                        <button type="submit" name="op" value="deduct" class="ad-btn ad-btn--danger"
                                onclick="return confirm('이 회원의 포인트를 차감할까요?\n보유액보다 많으면 처리되지 않습니다.');">차감</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <form class="ad-form" action="/proc/admin_member_detail_proc.php" method="post" style="margin-top:1.25rem;">
                <input type="hidden" name="mb_idx" value="<?php echo (int) $row['mb_idx']; ?>">

                <div class="ad-split ad-split--2">
                    <div class="ad-field">
                        <label for="mb_id">아이디</label>
                        <input type="text" id="mb_id" name="mb_id" required maxlength="20"
                               value="<?php echo htmlspecialchars($row['mb_id'], ENT_QUOTES, 'UTF-8'); ?>"
                               pattern="[A-Za-z0-9_]{4,20}"
                               title="영문·숫자·밑줄 4~20자">
                    </div>
                    <div class="ad-field">
                        <label for="mb_name">이름</label>
                        <input type="text" id="mb_name" name="mb_name" required maxlength="20"
                               value="<?php echo htmlspecialchars($row['mb_name'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="ad-field">
                        <label for="mb_nick">닉네임</label>
                        <input type="text" id="mb_nick" name="mb_nick" required maxlength="20"
                               value="<?php echo htmlspecialchars($row['mb_nick'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="ad-field">
                        <label for="mb_email">이메일</label>
                        <input type="email" id="mb_email" name="mb_email" required maxlength="100"
                               value="<?php echo htmlspecialchars($row['mb_email'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="ad-field">
                        <label for="mb_phone">휴대폰 (비우면 미등록)</label>
                        <input type="text" id="mb_phone" name="mb_phone" maxlength="20"
                               value="<?php echo htmlspecialchars((string) ($row['mb_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="예: 010-1234-5678">
                    </div>
                    <div class="ad-field">
                        <label for="mb_status">상태</label>
                        <select id="mb_status" name="mb_status">
                            <?php foreach ($status_labels as $k => $lab): ?>
                                <option value="<?php echo (int) $k; ?>"<?php echo (int) $row['mb_status'] === (int) $k ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ad-field">
                        <label for="mb_level">등급 (1~9)</label>
                        <input type="number" id="mb_level" name="mb_level" min="1" max="9" required
                               value="<?php echo (int) $row['mb_level']; ?>">
                        <p class="ad-p ad-muted" style="margin:6px 0 0;">1 일반 · 3 인증(경매 조건 예외) · 5 VIP · 9 관리자</p>
                    </div>
                    <div class="ad-field">
                        <label for="mb_pw_new">새 비밀번호 (변경 시만 입력)</label>
                        <input type="password" id="mb_pw_new" name="mb_pw_new" minlength="8" maxlength="50" autocomplete="new-password"
                               placeholder="비워두면 유지">
                    </div>
                    <div class="ad-field">
                        <label for="mb_pw_new_confirm">새 비밀번호 확인</label>
                        <input type="password" id="mb_pw_new_confirm" name="mb_pw_new_confirm" minlength="8" maxlength="50" autocomplete="new-password">
                    </div>
                </div>

                <div class="ad-actions" style="margin-top:1rem;">
                    <button type="submit" class="ad-btn">저장</button>
                </div>
            </form>
        </div>

        <?php
        $login_block_ready_ad = member_login_block_columns_ready();
        $login_block_ad = ($row && $login_block_ready_ad)
            ? member_login_block_info((int) $row['mb_idx'])
            : ['blocked' => false, 'until' => null, 'days' => 0, 'reason' => ''];
        if ($login_block_ready_ad):
        ?>
        <div class="ad-card" style="margin-bottom:1.25rem;<?php echo !empty($login_block_ad['blocked']) ? 'border:1px solid #fecaca;background:#fef2f2;' : ''; ?>">
            <h2 class="ad-title" style="font-size:1.05rem;">로그인 접속 제한</h2>
            <?php if ((int) ($row['mb_level'] ?? 0) >= 9): ?>
                <p class="ad-p" style="margin-top:0;">관리자 계정에는 로그인 제한을 적용할 수 없습니다.</p>
            <?php elseif (!empty($login_block_ad['blocked'])): ?>
                <p class="ad-p" style="margin-top:0;">
                    <strong style="color:#b91c1c;">제한 중</strong>
                    <?php if (!empty($login_block_ad['until'])): ?>
                        · <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string) $login_block_ad['until'])), ENT_QUOTES, 'UTF-8'); ?>까지
                    <?php endif; ?>
                    <?php if ((int) $login_block_ad['days'] > 0): ?>
                        (<?php echo (int) $login_block_ad['days']; ?>일)
                    <?php endif; ?>
                </p>
                <?php if (($login_block_ad['reason'] ?? '') !== ''): ?>
                    <p class="ad-p ad-muted">사유: <?php echo htmlspecialchars((string) $login_block_ad['reason'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <form class="ad-form" action="/proc/admin_member_login_block_proc.php" method="post"
                      onsubmit="return confirm('이 회원의 로그인 접속 제한을 해제할까요?');">
                    <input type="hidden" name="mb_idx" value="<?php echo (int) $row['mb_idx']; ?>">
                    <input type="hidden" name="from" value="admin">
                    <input type="hidden" name="action" value="lift">
                    <div class="ad-actions" style="margin-top:1rem;">
                        <button type="submit" class="ad-btn">접속 제한 해제</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="ad-p" style="margin-top:0;">기간을 선택해 로그인을 막을 수 있습니다. 해당 회원에게는 사유가 경고로 안내됩니다.</p>
                <form class="ad-form" action="/proc/admin_member_login_block_proc.php" method="post"
                      onsubmit="return confirm('이 회원에게 로그인 접속 제한을 적용할까요?');">
                    <input type="hidden" name="mb_idx" value="<?php echo (int) $row['mb_idx']; ?>">
                    <input type="hidden" name="from" value="admin">
                    <input type="hidden" name="action" value="apply">
                    <div class="ad-field">
                        <label>제한 기간</label>
                        <div style="display:flex;flex-wrap:wrap;gap:8px 14px;padding-top:6px;">
                            <?php foreach (member_login_block_day_options() as $i => $d): ?>
                                <label style="display:inline-flex;align-items:center;gap:4px;font-size:13px;">
                                    <input type="radio" name="days" value="<?php echo (int) $d; ?>"<?php echo $i === 0 ? ' checked' : ''; ?>>
                                    <?php echo (int) $d; ?>일
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="ad-field" style="max-width:480px;">
                        <label for="login_block_reason">제한 사유</label>
                        <textarea id="login_block_reason" name="reason" rows="2" maxlength="400" required
                                  placeholder="예: 타 플랫폼 거래 유도"></textarea>
                    </div>
                    <div class="ad-actions" style="margin-top:1rem;">
                        <button type="submit" class="ad-btn" style="background:#b91c1c;border-color:#b91c1c;">접속 제한 적용</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (member_auction_ban_column_ready()): ?>
        <div class="ad-card" style="margin-bottom:1.25rem;<?php echo !empty($auction_ban['banned']) ? 'border:1px solid #fcd34d;background:#fffbeb;' : ''; ?>">
            <h2 class="ad-title" style="font-size:1.05rem;">경매 이용 제한</h2>
            <?php if (!empty($auction_ban['banned'])): ?>
                <p class="ad-p" style="margin-top:0;">
                    <strong style="color:#b45309;">제한 중</strong>
                    <?php if (!empty($auction_ban['banned_at'])): ?>
                        · <?php echo htmlspecialchars(substr((string) $auction_ban['banned_at'], 0, 16), ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </p>
                <?php if (($auction_ban['memo'] ?? '') !== ''): ?>
                    <p class="ad-p ad-muted">사유: <?php echo htmlspecialchars((string) $auction_ban['memo'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <p class="ad-p ad-muted">낙찰 주문 취소 등으로 자동 제한된 경우 관리자가 해제할 수 있습니다.</p>
                <form class="ad-form" action="/proc/admin_member_auction_ban_proc.php" method="post"
                      onsubmit="return confirm('이 회원의 경매 이용 제한을 해제할까요?');">
                    <input type="hidden" name="mb_idx" value="<?php echo (int) $row['mb_idx']; ?>">
                    <input type="hidden" name="action" value="lift">
                    <div class="ad-actions" style="margin-top:1rem;">
                        <button type="submit" class="ad-btn">경매 이용 제한 해제</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="ad-p" style="margin-top:0;">현재 경매 이용 제한이 없습니다.</p>
                <form class="ad-form" action="/proc/admin_member_auction_ban_proc.php" method="post"
                      onsubmit="return confirm('이 회원에게 경매 이용 제한을 적용할까요?');">
                    <input type="hidden" name="mb_idx" value="<?php echo (int) $row['mb_idx']; ?>">
                    <input type="hidden" name="action" value="apply">
                    <div class="ad-field" style="max-width:480px;">
                        <label for="auction_ban_memo">제한 사유 (선택)</label>
                        <input type="text" id="auction_ban_memo" name="memo" maxlength="200"
                               placeholder="예: 관리자 경매 이용 제한">
                    </div>
                    <div class="ad-actions" style="margin-top:1rem;">
                        <button type="submit" class="ad-btn" style="background:#b45309;border-color:#b45309;">경매 이용 제한 적용</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="ad-card" style="border:1px solid #fecaca;background:#fef2f2;">
            <h2 class="ad-title" style="font-size:1.05rem;color:#b91c1c;">회원 삭제</h2>
            <p class="ad-p">거래글·커뮤니티 글·포인트 내역·출석 기록 등이 함께 정리됩니다. 문의 내역은 비회원 처리됩니다. 되돌릴 수 없습니다.</p>
            <form class="ad-form" action="/proc/admin_member_delete_proc.php" method="post"
                  onsubmit="return confirm('정말 이 회원을 삭제할까요?');">
                <input type="hidden" name="mb_idx" value="<?php echo (int) $row['mb_idx']; ?>">
                <div class="ad-field" style="max-width:320px;">
                    <label for="confirm_mb_id">삭제 확인 · 아이디 재입력</label>
                    <input type="text" id="confirm_mb_id" name="confirm_mb_id" required autocomplete="off"
                           placeholder="<?php echo htmlspecialchars($row['mb_id'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="ad-actions" style="margin-top:1rem;">
                    <button type="submit" class="ad-btn" style="background:#b91c1c;border-color:#b91c1c;">회원 삭제</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
