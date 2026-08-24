<?php

// 저장 경로 설정
$targetDir = $_SERVER['DOCUMENT_ROOT']."/upload/SHOP/";
$domain = "https://sadmin.mekosystem.com";
if (isset($_FILES["image"])) {
    $fileName = basename($_FILES["image"]["name"]);
    $targetFile = time() . "_" . $fileName;

    if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetDir.$targetFile)) {
        // 업로드 성공 시 이미지 URL 반환
        echo $domain."/upload/SHOP/".$targetFile;
    } else {
        // 실패
        http_response_code(500);
        echo "파일 업로드 실패";
    }
}
?>
