<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// upload.php
// PHP 7.4 클래식 스타일 예제

// 업로드 디렉토리 설정 (웹에서 쓰기 권한 필요)
$uploadDir = $_SERVER['DOCUMENT_ROOT'].'/gallery/video';

// 디렉토리 없으면 생성
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 허용 확장자 및 최대 파일 크기 (바이트)
$allowedExt = ['jpg','mp4'];
$maxFileSize = 100 * 1024 * 1024; // 100 MB
$galleryUrl = 'http://49.247.160.164/gallery';

function gallery_본방알림_등록($title, $videoIdx = 0) {
    global $galleryUrl;

    $title = trim((string)$title);
    if ($title === '') {
        $title = '(제목 없음)';
    }

    $msg = "🎬 역사관 신규 영상 업로드!\n";
    $msg .= "제목: {$title}\n";
    $msg .= $galleryUrl;

    $msg_esc = addslashes($msg);
    $item = 'gallery_video_' . ((int)$videoIdx > 0 ? (int)$videoIdx : time());
    $item_esc = addslashes($item);

    return (bool)@db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$msg_esc}', leverage = 0, item = '{$item_esc}', regdate = NOW()");
}

// 폼이 제출되었을 때 처리
$message = '';
$debugLogs = [];
$title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
$action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit_video') {
    $editIdx = isset($_POST['video_idx']) ? (int)$_POST['video_idx'] : 0;
    $editTitle = isset($_POST['edit_title']) ? trim((string)$_POST['edit_title']) : '';
    $editPhoto = isset($_POST['edit_photo']) ? trim((string)$_POST['edit_photo']) : '';
    if ($editIdx <= 0) {
        $message = '수정할 영상 번호가 올바르지 않습니다.';
    } else {
        $editVideoName = '';
        $editFile = $_FILES['edit_userfile'] ?? null;
        if (is_array($editFile) && (int)($editFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int)$editFile['error'] !== UPLOAD_ERR_OK) {
                $message = '수정 영상 업로드 중 오류가 발생했습니다. 에러코드: ' . (int)$editFile['error'];
            } elseif ((int)($editFile['size'] ?? 0) > $maxFileSize) {
                $message = '수정 영상 파일 크기가 허용 범위를 초과했습니다 (최대 100MB).';
            } else {
                $editExt = strtolower(pathinfo((string)($editFile['name'] ?? ''), PATHINFO_EXTENSION));
                if ($editExt !== 'mp4') {
                    $message = '수정용 영상 파일은 mp4만 업로드할 수 있습니다.';
                } else {
                    $safeBase = bin2hex(random_bytes(8));
                    $editVideoName = $safeBase . '.mp4';
                    $editTargetPath = $uploadDir . DIRECTORY_SEPARATOR . $editVideoName;
                    if (!move_uploaded_file($editFile['tmp_name'], $editTargetPath)) {
                        $message = '수정 영상 파일 저장에 실패했습니다.';
                        $editVideoName = '';
                    } else {
                        chmod($editTargetPath, 0644);
                        $debugLogs[] = '수정 영상 업로드 완료: ' . $editTargetPath;
                    }
                }
            }
        }

        if ($message === '') {
            $editTitleEsc = addslashes($editTitle);
            $editPhotoEsc = addslashes($editPhoto);
            $sql = "UPDATE tb_video SET title = '{$editTitleEsc}', photo = '{$editPhotoEsc}'";
            if ($editVideoName !== '') {
                $editVideoEsc = addslashes($editVideoName);
                $sql .= ", video = '{$editVideoEsc}'";
            }
            $sql .= " WHERE idx = {$editIdx} LIMIT 1";
            $debugLogs[] = 'DB update SQL: ' . $sql;
            $ok = db_query($sql);
            $message = $ok ? '영상 정보 수정 완료' : '영상 정보 수정 실패';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'edit_video' && isset($_FILES['userfile'])) {
    $file = $_FILES['userfile'];
    $debugLogs[] = '요청 수신: name=' . ($file['name'] ?? '') . ', size=' . (int)($file['size'] ?? 0) . ', error=' . (int)($file['error'] ?? -1);

    // 업로드 에러 체크
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $message = '파일이 너무 큽니다.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = '파일이 부분적으로만 업로드되었습니다.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = '파일이 선택되지 않았습니다.';
                break;
            default:
                $message = '업로드 중 오류가 발생했습니다. 에러코드: ' . $file['error'];
        }
    } else {
        // 파일 크기 체크
        if ($file['size'] > $maxFileSize) {
            $message = '파일 크기가 허용 범위를 초과했습니다 (최대 100MB).';
        } else {
            // 원본 파일명과 확장자
            $origName = $file['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            // 확장자 체크
            if (!in_array($ext, $allowedExt, true)) {
                $message = '허용되지 않는 파일 형식입니다.';
            } else {
                // 안전한 고유 파일명 생성 (충돌 방지)
                $safeBase = bin2hex(random_bytes(8)); // 랜덤 16진수 16자리
                $newName = $safeBase . '.' . $ext;
                $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $newName;
                $debugLogs[] = '검증 통과: ext=' . $ext . ', target=' . $targetPath;

                // 임시파일을 목적지로 이동
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // 권한 조정 (옵션)
                    chmod($targetPath, 0644);
                    $message = '업로드 성공: <a href="uploads/' . rawurlencode($newName) . '" target="_blank">파일보기</a>';
                    $오늘 = date("Y-m-d");
                    $sql = "insert into tb_video set ";
                    $sql.= "title = '{$title}', ";
                    $sql.= "photo = 'https://img.youtube.com/vi/ScMzIvxBSi4/hqdefault.jpg', ";
                    $sql.= "video = '{$newName}', ";
                    $sql.= "rdate = '{$오늘}' ";
                    $debugLogs[] = 'DB insert SQL: ' . $sql;
                    $dbResult = db_query($sql);
                    if (!$dbResult) {
                        $debugLogs[] = 'DB insert 실패';
                    } else {
                        $debugLogs[] = 'DB insert 성공';
                        if ($ext === 'mp4') {
                            global $conn;
                            $videoIdx = (int)mysqli_insert_id($conn);
                            $notifyOk = gallery_본방알림_등록($title, $videoIdx);
                            $debugLogs[] = $notifyOk ? '본방 알림 등록 성공' : '본방 알림 등록 실패';
                        }
                    }


                } else {
                    $message = '파일 저장에 실패했습니다.';
                    $debugLogs[] = 'move_uploaded_file 실패: tmp=' . ($file['tmp_name'] ?? '');
                }
            }
        }
    }
}

$videoRows = [];
$videoRs = db_query("SELECT idx, title, photo, video, rdate FROM tb_video ORDER BY idx DESC LIMIT 100");
if ($videoRs) {
    while ($row = db_fetch($videoRs)) {
        $videoRows[] = $row;
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>영상 업로드</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .box { max-width: 1024px; margin: 0 auto; }
        .msg { margin: 10px 0; color: #114; }
        .debug { margin-top: 16px; padding: 12px; background: #f5f5f5; border: 1px solid #ddd; font-size: 13px; white-space: pre-wrap; }
        .list { margin-top: 20px; }
        .list table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .list th, .list td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: middle; }
        .list th { background: #fafafa; }
        .thumb { width: 90px; height: 50px; object-fit: cover; border-radius: 4px; }
        .edit-form input[type="text"] { width: 100%; box-sizing: border-box; margin-bottom: 6px; }
        .edit-form input[type="file"] { width: 100%; margin-bottom: 6px; }
        .edit-form button { padding: 6px 10px; }
    </style>
</head>
<body>
<div class="box">
    <h1>영상 업로드</h1>

    <!-- 업로드 폼 (multipart/form-data 필수) -->
    <form action="" method="post" enctype="multipart/form-data">
        <div>
          <input type="text" name="title" />
        </div>
        <label for="userfile">파일 선택 (jpg, mp4 | max 100MB):</label><br>
        <input type="file" name="userfile" id="userfile" required><br><br>
        <button type="submit">업로드</button>
    </form>

    <?php if ($message !== ''): ?>
        <div class="msg"><?= $message ?></div>
    <?php endif; ?>

    <?php if (!empty($debugLogs)): ?>
        <div class="debug"><?= htmlspecialchars(implode("\n", $debugLogs), ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="list">
        <h2>업로드 목록</h2>
        <?php if (empty($videoRows)): ?>
            <div class="msg">등록된 영상이 없습니다.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>번호</th>
                        <th>제목</th>
                        <th>썸네일</th>
                        <th>영상</th>
                        <th>등록일</th>
                        <th>수정</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($videoRows as $v): ?>
                    <?php
                    $vIdx = (int)($v['idx'] ?? 0);
                    $vTitle = htmlspecialchars((string)($v['title'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $vPhoto = trim((string)($v['photo'] ?? ''));
                    $vVideo = trim((string)($v['video'] ?? ''));
                    $vDate = htmlspecialchars((string)($v['rdate'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $videoUrl = '/gallery/video/' . rawurlencode($vVideo);
                    ?>
                    <tr>
                        <td><?= $vIdx ?></td>
                        <td><?= $vTitle !== '' ? $vTitle : '-' ?></td>
                        <td>
                            <?php if ($vPhoto !== ''): ?>
                                <img class="thumb" src="<?= htmlspecialchars($vPhoto, ENT_QUOTES, 'UTF-8') ?>" alt="thumb">
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($vVideo !== ''): ?>
                                <a href="<?= htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank"><?= htmlspecialchars($vVideo, ENT_QUOTES, 'UTF-8') ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= $vDate ?></td>
                        <td>
                            <form class="edit-form" action="" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="edit_video">
                                <input type="hidden" name="video_idx" value="<?= $vIdx ?>">
                                <input type="text" name="edit_title" value="<?= htmlspecialchars((string)($v['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="제목">
                                <input type="text" name="edit_photo" value="<?= htmlspecialchars((string)($v['photo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="썸네일 URL">
                                <input type="file" name="edit_userfile" accept=".mp4">
                                <button type="submit">수정</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <hr>

</div>
</body>
</html>
