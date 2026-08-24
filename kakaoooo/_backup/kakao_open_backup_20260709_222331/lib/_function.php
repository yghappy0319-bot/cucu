<?php
header('Content-Type: text/html; charset=UTF-8');
$dir = __DIR__;

$desiredPath = str_replace('/lib', '', $dir);
include_once $desiredPath."/site_info.php";

@session_start();
extract($_GET);
extract($_POST);
date_default_timezone_set('Asia/Seoul');

/* ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    ┃ 데이터베이스 관련 함수                                                                                                                           ┃
    ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛*/

$conn = mysqli_connect($db_admin_host, $db_admin_user, $db_admin_pass, $db_admin_database);
if ($conn instanceof mysqli) {
  mysqli_set_charset($conn, 'utf8mb4');
}
//DB 접속
// function db_connect($db_host, $db_user, $db_pass, $db_name){
//     $result = mysqli_connect($db_host, $db_user, $db_pass) or die(mysql_error());
//     mysqli_select_db($db_name) or die(mysql_error());
//     mysqli_query($conn, "set names utf8");
//     return $result;
// }

//SQL 쿼리 실행 함수
function db_query($sql){
    global $conn;
    $rs = mysqli_query($conn, $sql);
    return $rs;
}

//개별데이터
function db_select($sql, $params = []){
    $rs = db_query($sql, $params);
    return db_fetch($rs);
}

//데이터를 배열로 가져오기
function db_fetch($rs){
    return @mysqli_fetch_array($rs);
}
function db_assoc($rs){
    return @mysqli_fetch_assoc($rs);
}

function db_result($sql){
    $rs = db_query($sql);
    $row = mysqli_fetch_array($rs);
    if($row ) return @$row[0];
}

function 업로드_파일명_랜덤($length = 16) {
    $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function 파일업로드($file, $uploadDir, $allowedExtensions = ['svg', 'jpg', 'jpeg', 'png', 'gif', 'ico'], $maxFileSize = 2048576) { // 1MB = 1048576 bytes
    $path = "/home/kleam/public_html/data/" . $uploadDir . "/";
    // 디렉토리의 소유자를 웹 서버 사용자로 변경
    // sudo chown www-data:www-data /home/upload
    if ($file['error'] == 0) {
        // 파일 정보
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];

        // 파일 크기 검사
        if ($fileSize > $maxFileSize) {
            $currentFileSizeMB = number_format($fileSize / 1048576, 2); // 현재 파일 크기를 MB로 변환
            $maxFileSizeMB = number_format($maxFileSize / 1048576, 2); // 최대 허용 크기를 MB로 변환
            $data = array(
                'success' => false,
                'message' => "파일 크기가 {$maxFileSizeMB}MB를 초과합니다. 현재 파일 크기: {$currentFileSizeMB}MB"
            );
            return json_encode($data);
        }

        // 파일 확장자 추출
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                $data = array('success' => false, 'message' => '업로드 디렉토리를 생성할 수 없습니다.');
                return json_encode($data);
            }
        }

        if (!is_dir($path) || !is_writable($path)) {
            $data = array('success' => false, 'message' => '업로드 디렉토리가 존재하지 않거나 쓰기 권한이 없습니다.');
            return json_encode($data);
        }

        // 확장자 검사
        if (in_array($fileExt, $allowedExtensions)) {
            // 임의의 파일명 생성
            $randomFileName = 업로드_파일명_랜덤() . '.' . $fileExt;

            // 업로드 파일 경로 설정
            $uploadFile = $path . $randomFileName;

            // 파일을 업로드 디렉터리로 이동
            if (move_uploaded_file($fileTmpName, $uploadFile)) {
                chmod($uploadFile, 0777);
                $data = array(
                    'success' => true,
                    'message' => '파일이 정상적으로 업로드되었습니다',
                    'filename' => $fileName,
                    'upfilename' => $randomFileName,
                    'uploadpath' => "/" . $uploadDir . "/" . $randomFileName,
                    'filesize' => $fileSize
                );

                return json_encode($data);
            } else {
                $data = array('success' => false, 'message' => '파일 업로드 중 오류가 발생했습니다.');
                return json_encode($data);
            }
        } else {
            $data = array('success' => false, 'message' => '허용되지 않은 파일 형식입니다. 허용되는 형식: ' . implode(', ', $allowedExtensions));
            return json_encode($data);
        }
    } else {
        $data = array('success' => false, 'message' => '파일이 업로드되지 않았거나 오류가 발생했습니다.');
        return json_encode($data);
    }
}

function 파일업로드배열($FILES, $name, $uploadDir){

  $response = []; // 결과 저장 배열

  // 디렉토리가 없으면 생성
  if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0777, true);
  }

  if (!empty($FILES[$name]['name'][0])) {
      foreach ($FILES[$name]['name'] as $index => $fileName) {
          $fileTmpName = $FILES[$name]['tmp_name'][$index];
          $fileError = $FILES[$name]['error'][$index];

          // 오류 없이 파일이 업로드되었는지 확인
          if ($fileError === UPLOAD_ERR_OK) {
              // 파일 확장자 가져오기
              $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
              // 랜덤 파일명 생성
              $randomFileName = bin2hex(random_bytes(8)) . '.' . $fileExt;
              $filePath = $uploadDir . $randomFileName;

              // 파일 이동
              if (move_uploaded_file($fileTmpName, $filePath)) {
                  // 업로드 결과 배열에 추가
                  $response[] = [
                      'original_name' => $fileName,
                      'new_name' => $randomFileName
                  ];
              } else {
                  $response[] = [
                      'original_name' => $fileName,
                      'error' => 'Failed to upload file'
                  ];
              }
          } else {
              $response[] = [
                  'original_name' => $fileName,
                  'error' => 'Error uploading file'
              ];
          }
      }
  } else {
      $response[] = ['error' => 'No files uploaded'];
  }

  return json_encode($response);
}


function 관리자정보($midx){
  $sql = "select * from tb_manager where idx = {$midx} ";
  $manager = db_select($sql);
  return $manager;
}
function 회원가입_아이디길이체크($username) {
    $length = strlen($username);
    return !($length >= 5 && $length <= 20);
}
function isPasswordValid($password) {
    // 비밀번호가 6자 이상인지 확인
    return strlen($password) >= 6;
}

function 권한($grade){
  if($grade==1){
    return "일반";
  }else if($grade==2){
    return "총판";
  }else{
    return "관리자";
  }
}
