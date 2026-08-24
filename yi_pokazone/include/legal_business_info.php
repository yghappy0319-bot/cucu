<?php
/**
 * 사업자·법적 고지 공통 블록
 *
 * @var string $legal_block_title 기본: 사업자 정보
 * @var string $legal_block_class 추가 class
 */
if (!function_exists('site_legal_business_rows')) {
    require_once __DIR__ . '/../lib/_site_legal.php';
}

$__legal_block_title = isset($legal_block_title) && $legal_block_title !== ''
    ? (string) $legal_block_title
    : '사업자 정보';
$__legal_block_class = trim('policy-contact policy-contact--compact ' . (string) ($legal_block_class ?? ''));
$__legal_rows        = site_legal_business_rows();
?>
<div class="<?php echo htmlspecialchars($__legal_block_class, ENT_QUOTES, 'UTF-8'); ?>">
    <p><strong><?php echo htmlspecialchars($__legal_block_title, ENT_QUOTES, 'UTF-8'); ?></strong></p>
    <ul class="policy-list">
        <?php foreach ($__legal_rows as $__legal_row): ?>
            <li>
                <?php echo htmlspecialchars($__legal_row['label'], ENT_QUOTES, 'UTF-8'); ?>:
                <?php if (!empty($__legal_row['href'])): ?>
                    <a href="<?php echo htmlspecialchars($__legal_row['href'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($__legal_row['value'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php else: ?>
                    <?php echo htmlspecialchars($__legal_row['value'], ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
