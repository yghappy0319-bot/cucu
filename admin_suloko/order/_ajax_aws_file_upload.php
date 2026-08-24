<?php
require $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';
include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

echo $print;
exit;

// AWS 설정
$s3 = new S3Client([
    'version' => 'latest',
    'region'  => 'ap-northeast-2', // 서울 리전
    'credentials' => [
        'key'    => 'AKIAS4MXFN5O6HNQAR76',
        'secret' => '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',
    ],
]);

$bucket = 'suimg';
// 업로드된 파일 확인
if (!isset($_FILES['uploadfiles'])) {
    die('파일이 없습니다.');
}
$file = $_FILES['uploadfiles'];
try {
    $result = $s3->putObject([
        'Bucket' => $bucket,
        'Key'    => '_test/' . time() . '_' . $file['name'], // S3 저장 경로
        'SourceFile' => $file['tmp_name'],
        'ContentType' => $file['type'],
    ]);
    echo "업로드 성공<br>";
    echo "파일 URL: " . $result['ObjectURL'];
} catch (AwsException $e) {
    echo "업로드 실패: " . $e->getMessage();
}
