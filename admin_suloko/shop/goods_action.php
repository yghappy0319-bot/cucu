<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_SHOP.php";

$VAL         = $_POST;
$banner_path = '/upload/SHOP/';
$save_dir    = $_SERVER['DOCUMENT_ROOT'].$banner_path;
$mode        = $VAL['mode'];

if ($mode != 'insert') {
    $info     = $db->get_data("SELECT * FROM PRODUCT WHERE idx ='{$VAL['IDX']}'");
    $pre_file = $info['product_img'];
    unset($info);
}

$f  = $_FILES['product_img']['tmp_name']; // 서버에 저장된 임시이름
$fn = $_FILES['product_img']['name']; // 파일의 원래이름
$fs = $_FILES['product_img']['size']; // 바이트 단위 크기

if ($fn != '' || $mode == 'delete') {
    $strrand = str_rand(); // 랜덤스트링 추가

    $R_info = F_file_upload_common(array(
        'mode'          => $mode,
        'del_file_path' => $save_dir.$pre_file, // 삭제할 파일 이름
        'save_dir'      => $save_dir, // 저장경로
        'f'             => $f,
        'fn'            => $fn,
        'fs'            => $fs,
        'nfn'           => "product{$strrand}", // 저장할 파일 이름
        'max_size'      => 3000000, // 허용 파일 크기
        'thumnail'      => false,
        'thum_width'    => 132,
        'thum_height'   => 96,
        'thum_path'     => '',
        'pre_file'      => '',
    ));
    $VAL['product_img'] = $R_info['real'];
} else {
    $VAL['product_img'] = $pre_file;
}

$result = F_PRODUCT($VAL);

if ($result) {
    if ($mode == 'delete') {
        $msg = "상품을 삭제 하였습니다.";
    } else {
        $msg = "상품 정보를 저장하였습니다.";
    }
} else {
    $msg = "배너이미지 정보 실패!!";
}
alert_print($msg);
meta_go("/shop/goods.html");

/*
공통으로 사용할 수 있도록 수정한 함수
지정한 경로에 지정한 이름으로 파일 업로드(저장)
나중에 common.php로 이동해야 함
*/
function F_file_upload_common($_L) {
    // 수정/삭제시 기존 첨부파일을 삭제한다.
    if ($_L['del_file_path'] && ($_L['mode'] == 'delete' || $_L['mode'] == 'update')) {
        @unlink($_L['del_file_path']);
    }

    // 파일첨부를 이용한 해킹 방지
    $php_str = $_L['fn'];

    // 이진 수정 (이미지 업로드시 파일 체크 오류 해결)
    $eArr = array(".ht", ".phtm", ".php", ".inc", ".pl", ".perl", "htaccess", "exe");
    for ($i = 0; $i < count($eArr); $i++) {
        if (strpos($php_str, $eArr[$i]) !== false) {
            unlink($_L['f']);
            alert_print("html, php 등의 파일은 첨부가 불가능합니다");
            break;
            return;
        }
    }

    if ($_L['fn'] != "" && $_L['fs'] > 1) {
        if (!file_exists($_L['save_dir'])) {
            mkdir($_L['save_dir']);
            chmod($_L['save_dir'], 0777);
        }

        $size1         = $_L['fs'];
        $filename_exe1 = explode('.',$_L['fn']);
        $file1         = $_L['nfn'].".".$filename_exe1[1];
        $file1_thum    = $_L['nfn']."_thum.".$filename_exe1[1];

        if ($size1 > $_L['max_size']) {
            alert_print('첨부된 파일 크기가 '.$_L['max_size'].'를 초과합니다.');
            return false;
        } else {
            $full_save1 = $_L['save_dir']."/".$file1; // 경로와 파일명 합치기 (이미지파일)
            if ($_L['f'] == "") {
                $_L['f']  = $_L['ftmp'];
            }

            move_uploaded_file($_L['f'],$full_save1);
            chmod($full_save1, 0777);
            @unlink($_L['f']);
            @unlink($_L['ftmp']);
            $R['real']  = $file1;
            $R['thum']  = $file1_thum;
            $R['down']  = $_L['fn'];
            // 썸네일
            if ($_L['thumnail'] == true) {
                $Resize_info[org_file_name]  = $_L['save_dir']."/".$file1;
                $Resize_info[save_file_name] = $_L['save_dir']."/".$file1_thum;
                $Resize_info[path]           = $_L['thum_path'];
                $Resize_info[width]          = $_L['thum_width'];
                $Resize_info[height]         = $_L['thum_height'];
                F_resize_img2($Resize_info);
            }
            return $R;
        }
    }
}
?>
