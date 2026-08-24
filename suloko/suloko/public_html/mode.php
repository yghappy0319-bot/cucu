<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/session_start.php"; // session start
  require_once $_SERVER["DOCUMENT_ROOT"]."/_common/config.php";

  $VAL = $_POST;
  $RES = array("error" => true, "msg" => "");

  if ($VAL["mode"] == "purchaseInfo") {
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
