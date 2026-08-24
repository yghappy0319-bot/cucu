<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_community_html.php';
require_once __DIR__ . '/../lib/_community_meta.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode($_SERVER['REQUEST_URI']));
}

$categories = community_writable_categories();

$idx     = (int)($_GET['idx'] ?? 0);
$is_edit = $idx > 0;
$row     = null;

if ($is_edit) {
    $rs  = db_query("SELECT * FROM tb_community WHERE co_idx = {$idx} AND co_status = 1 LIMIT 1");
    $row = db_assoc($rs);
    if (!$row) {
        alert_goto('존재하지 않거나 삭제된 게시글입니다.', '/page/community.php');
    }
    if ((int)$row['mb_idx'] !== (int)$me['mb_idx'] && (int)$me['mb_level'] < 9) {
        alert_goto('수정 권한이 없습니다.', '/page/community_view.php?idx=' . $idx);
    }
}

$def_cat = $is_edit
    ? $row['co_category']
    : (isset($_GET['cat']) && array_key_exists($_GET['cat'], $categories) ? $_GET['cat'] : 'free');

$content_for_editor = '';
if ($is_edit) {
    $content_for_editor = community_html_apply_public_urls((string)$row['co_content']);
}

$page  = 'community';
$title = $is_edit ? '글 수정' : '글쓰기';
$meta_description = '포켓몬 카드 트레이너와 나누고 싶은 이야기를 자유롭게 작성해 보세요.';
$meta_noindex     = true;

$page_head_extra = <<<'PZHEAD'
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
PZHEAD;

$page_footer_extra = <<<'PZFOOT'
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-ko-KR.min.js"></script>
<script>
(function () {
  var $el = window.jQuery ? window.jQuery('#co_content') : null;
  if (!$el || !$el.length) return;
  var $ = window.jQuery;
  var pzPre = (typeof window.__PZ_PUBLIC_PREFIX__ === 'string' && window.__PZ_PUBLIC_PREFIX__ !== '')
    ? ('/' + window.__PZ_PUBLIC_PREFIX__.replace(/^\/+|\/+$/g, ''))
    : '';
  var uploadUrl = pzPre + '/page/community_editor_upload.php';

  var $pick = $('<input>', {
    type: 'file',
    accept: '.jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp',
    multiple: true,
    'aria-hidden': 'true',
    tabIndex: -1
  }).css({ position: 'absolute', width: '1px', height: '1px', padding: 0, margin: '-1px', overflow: 'hidden', clip: 'rect(0,0,0,0)', border: 0 });
  $('body').append($pick);

  function uploadOne(file) {
    var data = new FormData();
    data.append('file', file);
    $.ajax({
      url: uploadUrl,
      method: 'POST',
      data: data,
      processData: false,
      contentType: false,
      dataType: 'json'
    }).done(function (res) {
      if (res && res.ok && res.url) {
        $el.summernote('insertImage', res.url);
      } else {
        window.alert((res && (res.message || res.error)) ? res.message || res.error : '이미지 업로드에 실패했습니다.');
      }
    }).fail(function () {
      window.alert('이미지 업로드 요청에 실패했습니다.');
    });
  }

  $pick.on('change', function () {
    var files = this.files;
    if (!files || !files.length) return;
    for (var j = 0; j < files.length; j++) {
      uploadOne(files[j]);
    }
    this.value = '';
  });

  function pzCommunityImageButton(context) {
    var ui = $.summernote.ui;
    return ui.button({
      contents: '<i class="note-icon-picture"/>',
      tooltip: '이미지',
      click: function () {
        context.invoke('saveRange');
        $pick.trigger('click');
      }
    }).render();
  }

  $el.summernote({
    lang: 'ko-KR',
    placeholder: '내용을 입력하세요. 서로 존중하는 커뮤니티 문화를 만들어요.',
    tabsize: 2,
    height: 360,
    focus: false,
    toolbar: [
      ['style', ['style']],
      ['font', ['bold', 'underline', 'clear']],
      ['fontname', ['fontname']],
      ['color', ['color']],
      ['para', ['ul', 'ol', 'paragraph']],
      ['height', ['height']],
      ['table', ['table']],
      ['insert', ['pzCoImage']],
      ['view', ['fullscreen', 'help']]
    ],
    buttons: {
      pzCoImage: pzCommunityImageButton
    },
    callbacks: {
      onImageUpload: function (files) {
        for (var i = 0; i < files.length; i++) {
          uploadOne(files[i]);
        }
      }
    }
  });
})();
</script>
PZFOOT;

include __DIR__ . '/../include/header.php';
?>

<section class="community">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title"><?php echo $is_edit ? '글 수정' : '새 글 작성'; ?></h1>
                <p class="board-desc">다른 트레이너에게 도움이 되는 내용을 공유해 주세요.</p>
            </div>
        </div>

        <form class="post-form" method="post" action="/proc/community_write_proc.php" autocomplete="off">
            <?php if ($is_edit): ?>
                <input type="hidden" name="idx" value="<?php echo (int)$row['co_idx']; ?>">
                <input type="hidden" name="mode" value="edit">
            <?php else: ?>
                <input type="hidden" name="mode" value="insert">
            <?php endif; ?>

            <div class="field">
                <label for="co_category">분류</label>
                <select id="co_category" name="co_category" required>
                    <?php foreach ($categories as $k => $label): ?>
                        <option value="<?php echo htmlspecialchars($k); ?>"
                                <?php echo $def_cat === $k ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="co_title">제목</label>
                <input type="text" id="co_title" name="co_title" maxlength="150" required
                       placeholder="제목을 입력하세요 (최대 150자)"
                       value="<?php echo htmlspecialchars($is_edit ? $row['co_title'] : ''); ?>">
            </div>

            <div class="field">
                <label for="co_content">내용</label>
                <textarea id="co_content" name="co_content" rows="14" required
                          placeholder="내용을 입력하세요. 서로 존중하는 커뮤니티 문화를 만들어요."><?php echo htmlspecialchars($content_for_editor, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <p class="help">툴바의 이미지 버튼·또는 에디터에 끌어다 놓기·붙여넣기 (JPEG·PNG·GIF·WebP, 장당 최대 5MB)</p>
                <?php if (!$is_edit): ?>
                <p class="help">글 등록 시 <?php echo number_format(point_reward_community_new_post()); ?>P 지급 (하루 <?php echo number_format(point_reward_community_new_post_daily_limit()); ?>회까지)</p>
                <?php endif; ?>
                <p class="help">욕설·비방·광고·개인정보 노출 등은 통보 없이 삭제될 수 있습니다.</p>
            </div>

            <div class="form-actions">
                <a href="<?php echo $is_edit ? '/page/community_view.php?idx='.(int)$row['co_idx'] : '/page/community.php'; ?>"
                   class="btn btn-outline">취소</a>
                <button type="submit" class="btn btn-primary">
                    <?php echo $is_edit ? '수정하기' : '등록하기'; ?>
                </button>
            </div>
        </form>
    </div>
</section>

<?php include __DIR__ . '/../include/footer.php'; ?>
