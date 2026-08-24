<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/session_start.php"; // session start
  require_once $_SERVER["DOCUMENT_ROOT"]."/_common/config.php";

  $VAL = $_POST;
  $RES = array("error" => False, "msg" => "");

  if ($VAL["mode"] == "qna_user_read") {
    // 작성자 ID 가져오기
    $info = $db->get_data("SELECT USER_ID,REPLY_YN FROM BBS WHERE GUBUN='QNA' AND BBS_NO='{$VAL["no"]}' LIMIT 1");

    // 작성자 본인이고 답변이 완료된 문의의 읽은 날짜 업데이트 (마지막 읽은 날짜로 처리됨)
    if ($info['USER_ID'] == $M_login['user_id'] && $info['REPLY_YN'] == 'Y') {
      $query = "UPDATE BBS SET USER_READ_DATE = NOW() WHERE GUBUN='QNA' AND BBS_NO='{$VAL["no"]}'";
      $db->query($query);
    }
    exit;
  } else if ($VAL["mode"] == "getReport") {
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_TIP_OFF.php";

    $add_query = " AND TYPE='V' AND USER_ID='{$M_login['user_id']}'";

    $html  = "";
    $limit = 10;

    $condition = array(
      "row"  => $limit,
      "page" => $VAL["page"],
      "add_query" => $add_query
    );

    $list = F_TIP_OFF_LIST($condition);

    if ($list["total"] <= 0) {
      $list["IDX"] = array();
    }

    for ($i = 0; $i < count($list["IDX"]); $i++) {
      $no = $list["total"] - (($page-1) * $list["row"]) - $i;

      $tp = "<p class='fw600'>접수중</p>";

      if ($list['STATUS'][$i] == 'Y') {
        $tp = "<p class='fw600'>접수완료 [내용보기]</p>";
      }

      $html .= '
        <div class="wrap_tb bt ls50 lh10">
          <table class="tb_type2">
            <tbody>
              <tr>
                <th>번호</th>
                <td>'.$no.'</td>
              </tr>
              <tr>
                <th>제목</th>
                <td>'.$list['SUBJECT'][$i].'</td>
              </tr>
              <tr>
                <th>등록일</th>
                <td>'.$list['CREATED_AT'][$i].'</td>
              </tr>
              <tr>
                <th>상태</th>
                <td class="detail_on" data-no="'.$list['IDX'][$i].'">'.$tp.'</td>
              </tr>
            </tbody>
          </table>
          <div class="bx_answer bx_gray" style="display: none;">
            <div class="wrap_q">
              <p class="title"><strong>제보내용</strong></p>
              <p>'.nl2br($list['CONTENT'][$i]).'</p>
            </div>
            <div class="wrap_a">
              <p class="title"><strong>첨부파일</strong></p>
              '.($list['ORIGIN_FILE1'][$i]).'<br/>
              '.($list['ORIGIN_FILE2'][$i]).'<br/>
              '.($list['ORIGIN_FILE3'][$i]).'
            </div>
          </div>
        </div>
      ';
    }
    $RES["html"] = $html;
    echo json_encode($RES);
    exit;
  }
?>
