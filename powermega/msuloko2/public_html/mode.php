<?php
  require_once $_SERVER["DOCUMENT_ROOT"]."/_common/config.php";
  require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_TOPIC.php";
  require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_MEMBER.php";
  require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_MEMBER_TOKEN.php";
  require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_MEMBER_TOPIC.php";

  $VAL = $_POST;
  $RES = array("error" => true, "msg" => "");

  if ($VAL["mode"] == "sendTokenToServer") {
    /**
     * 토큰 설정 - 토큰 여부에 따른 처리
     */
    if (!isset($M_login["user_id"])) {
      $RES["msg"] = "로그인해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $USER_ID = $M_login["user_id"];
    $TOKEN = $VAL["token"];
    $DEVICE = $_SERVER["HTTP_USER_AGENT"];
    $RES["isNew"] = false;

    // 토큰 값 확인
    $isToken = $db->get_data_one("
      SELECT IDX
      FROM MEMBER_TOKEN
      WHERE USER_ID = '{$USER_ID}' AND TOKEN = '{$TOKEN}' AND DEVICE = '{$DEVICE}'
    ");

    if ($isToken <= 0) {
      F_MEMBER_TOKEN(array(
        "mode"    => "insert",
        "USER_ID" => $USER_ID,
        "TOKEN"   => $TOKEN,
        "DEVICE"  => $DEVICE
      ));
    }

    $isTopic = $db->get_data_one("SELECT count(*) FROM MEMBER_TOPIC WHERE USER_ID = '{$USER_ID}'");

    if ($isTopic <= 0) {
      // Get basic topic id
      $basicTopicIdx = $db->get_data_one("SELECT IDX FROM TOPIC WHERE BASIC = 'Y'");

      F_MEMBER_TOPIC(array(
        "mode"      => "insert",
        "USER_ID"   => $M_login["user_id"],
        "TOPIC_IDX" => $basicTopicIdx
      ));

      $RES["isNew"] = true;
    }

    $topicList = F_TOPIC_LIST(array(
      "USER_ID" => $USER_ID
    ));

    $RES["topics"] = $topicList;
    $RES["exists"] = false;
    $RES["error"]  = false;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "sendTopicToServer") {
    /**
     * 알림 설정 - 알림 동의 여부 확인 및 업데이트
     */
    $info = $db->get_data_one("
      SELECT COUNT(*)
      FROM MEMBER_TOPIC
      WHERE USER_ID = '{$M_login["user_id"]}' AND TOPIC_IDX = '{$VAL["topicIdx"]}'
    ");

    if ($info <= 0) {
      F_MEMBER_TOPIC(array(
        "mode"      => "insert",
        "USER_ID"   => $M_login["user_id"],
        "TOPIC_IDX" => $VAL["topicIdx"]
      ));
    } else {
      F_MEMBER_TOPIC(array(
        "mode"      => "update",
        "USER_ID"   => $M_login["user_id"],
        "TOPIC_IDX" => $VAL["topicIdx"],
        "STATUS"    => $VAL["status"]
      ));
    }
    $RES["error"] = false;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "updateMarketing") {
    /**
     * 마케팅 설정 - 마케팅 동의 여부 업데이트
     */
    if (!isset($M_login["user_id"])) {
      $RES["msg"] = "로그인해 주세요.";
      echo json_encode($RES);
      exit;
    }

    F_MEMBER(array(
      "mode"         => "update",
      "MEMBER_NO"    => $VAL["MEMBER_NO"],
      "IS_MARKETING" => $VAL["IS_MARKETING"]
    ));

    $RES["error"] = false;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "purchaseInfo") {
    $MM = timesss("MM");
    $PB = timesss("PB");

    if ($MM["price"] != "") {
      $MM["priceKRW"] = setPriceKRW($MM["price"]);
      $MM["priceUSD"] = number_format($MM["priceDollar"]);
      $MM["priceKRW_est"] = setPriceKRW($MM["price_est"]); // 예상 당첨금
      $MM["priceKRW_fst"] = setPriceKRW($MM["price_fst"]); // 초기 당첨금
    }
    if ($PB["price"] != "") {
      $PB["priceKRW"] = setPriceKRW($PB["price"]);
      $PB["priceUSD"] = number_format($PB["priceDollar"]);
      $PB["priceKRW_est"] = setPriceKRW($PB["price_est"]); // 예상 당첨금
      $PB["priceKRW_fst"] = setPriceKRW($PB["price_fst"]); // 초기 당첨금
    }

    $RES['error'] = false;
    $RES['MM'] = $MM;
    $RES['PB'] = $PB;
    echo json_encode($RES);
    exit;
  }
?>
