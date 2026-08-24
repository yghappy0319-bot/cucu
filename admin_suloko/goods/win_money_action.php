<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

$wininfo_no = $_POST["winno"];

if (trim($wininfo_no) == '') {
  alert_print("잘못된 접근입니다.");
  meta_go("/goods/win_money.html");
  exit;
}

$info = $db->get_data("SELECT * FROM WININFO WHERE WININFO_NO='".$wininfo_no."'");

if (!isset($info['WININFO_NO'])) {
  alert_print("해당회차가 존재하지 않습니다.");
  meta_go("/goods/win_money.html");
  exit;
}

if ($info['WININFO_NO'] == '') {
  alert_print("해당회차가 존재하지 않습니다.");
  meta_go("/goods/win_money.html");
  exit;
}

if ($info['BALL1'] == '' || $info['BALL2'] == '' || $info['BALL3'] == '' || $info['BALL4'] == '' || $info['BALL5'] == '' || $info['BALLP'] == '') {
  alert_print("당첨번호를 입력해 주세요.");
  meta_go("/goods/win_money.html");
  exit;
}

if ($info['PRIZ1'] == '' || $info['PRIZ2'] == '' || $info['PRIZ3'] == '' || $info['PRIZ4'] == '' || $info['PRIZ5'] == '' || $info['PRIZ6'] == '' || $info['PRIZ7'] == '' || $info['PRIZ8'] == '' || $info['PRIZ9'] == '') {
  alert_print("당첨금을 입력해 주세요.");
  meta_go("/goods/win_money.html");
  exit;
}

if ($info['PRIZ1'] == 0 || $info['PRIZ2'] == 0 || $info['PRIZ3'] == 0 || $info['PRIZ4'] == 0 || $info['PRIZ5'] == 0 || $info['PRIZ6'] == 0 || $info['PRIZ7'] == 0 || $info['PRIZ8'] == 0 || $info['PRIZ9'] == 0) {
  alert_print("당첨금을 입력해 주세요.");
  meta_go("/goods/win_money.html");
  exit;
}

if ($info['PRIZCNT8'] == 0 || $info['PRIZCNT9'] == 0 || $info['PRIZCNT8'] == '' || $info['PRIZCNT9'] == '') {
  alert_print("당첨자수를 입력해 주세요.");
  meta_go("/goods/win_money.html");
  exit;
}


$winD     = array();
$winP     = array();
$winD[0]  = intval($info['BALL1']);
$winD[1]  = intval($info['BALL2']);
$winD[2]  = intval($info['BALL3']);
$winD[3]  = intval($info['BALL4']);
$winD[4]  = intval($info['BALL5']);
$winP[0]  = intval($info['BALLP']);
$winNums  = array($winD[0],$winD[1],$winD[2],$winD[3],$winD[4],$winP[0]);
$codeLK	  = $info["GUBUN"];
$playDate = $info["PLAYDATE"];

$wininfo  = $info;

$winNum = implode(",", $winNums);

$list = $db->get_list("SELECT * FROM ORDERS WHERE GUBUN='".$codeLK."' AND PLAYDATE='".$playDate."' AND WIN_MONEY_YN NOT IN ('Y')");

if (!isset($list['ORDERS_NO'])) {
  alert_print("당첨자가 없습니다.");
  meta_go("/goods/win_money.html");
  exit;
}

for ($i = 0; $i < count($list['ORDERS_NO']); $i++) {
  $price = 0;
  $win = array(0, 0, 0, 0, 0, 0);

  for ($s = 1; $s <= 5; $s++) {
    if (!isset($list['BALL'.$s][$i])) {
      continue;
    }

    if ($list['BALL'.$s][$i] != '') {
      $winCntD = 0;
      $winCntP = 0;

      $ball   = $list['BALL'.$s][$i];
      $balls  = explode(",",$ball);
      $ballD  = array($balls[0], $balls[1], $balls[2], $balls[3], $balls[4]);
      $ballP  = array($balls[5]);

      if ($winP[0] == $balls[5]) {
        $winCntP++;
      }

      for ($k = 0; $k < sizeof($ballD); $k++) {
        if (in_array($ballD[$k], $winD)) {
          $winCntD++;
        }
      }

      if ($winCntP == 1 && $winCntD == 5) {
        $winNo = 1;
        $winprice = $wininfo['PRIZ'.$winNo];
      } else if ($winCntP == 0 && $winCntD == 5) {
        $winNo = 2;
        $winprice = $wininfo['PRIZ'.$winNo];
      } else if ($winCntP == 1 && $winCntD == 4) {
        $winNo = 3;
        $winprice = $wininfo['PRIZ'.$winNo];
      } else if ($winCntP == 0 && $winCntD == 4) {
        $winNo = 4;
        $winprice = $wininfo['PRIZ'.$winNo];
      } else if ($winCntP == 1 && $winCntD == 3) {
        $winNo = 5;
        $winprice = $wininfo['PRIZ'.$winNo];
      } else if ($winCntP == 1 && $winCntD == 2) {
        $winNo = 7;
        $winprice = $wininfo['PRIZ'.$winNo];
      } else if ($winCntP == 0 && $winCntD == 3) {
        $winNo = 6;
        $winprice = $wininfo['PRIZ'.$winNo];
      } else if ($winCntP == 1 && $winCntD == 1) {
        $winNo = 8;
        $winprice = $wininfo['PRIZ'.$winNo];
      } else if ($winCntP == 1 && $winCntD == 0) {
        $winNo = 9;
        $winprice = $wininfo['PRIZ'.$winNo];
      } else {
        $winNo = 0;
        $winprice = 0;
      }

      if ($winprice == '') {
        $winprice = 0;
      }

      $price += $winprice;
      $win[$s] = $winNo;
    }
  }

  // 환율
  $won = $db->get_data("SELECT * FROM EXCHANGE WHERE DATE = '".date("Y-m-d")."' ");

  if (!isset($won['WON'])) {
    $won = $db->get_data("SELECT * FROM EXCHANGE WHERE 1 ORDER BY DATE DESC");
  }

  $won_price = $price * $won['WON'];

  $win_tp = "N";

  if ($won_price > 0) {
    $win_tp = "Y";
  }

  $sql	=	"
    UPDATE ORDERS SET
      WIN1          = '".$win[1]."',
      WIN2          = '".$win[2]."',
      WIN3          = '".$win[3]."',
      WIN4          = '".$win[4]."',
      WIN5          = '".$win[5]."',
      WIN_MONEY     = '".$won_price."',
      WIN_MONEY_USD = '".$price."',
      WON           = '".$won['WON']."',
      WIN_YN        = '".$win_tp."',
      WIN_MONEY_YN  = 'Y'
    WHERE
      ORDERS_NO = '".$list['ORDERS_NO'][$i]."'
  ";
  $db->query($sql);

  if ($won_price > 0) {
    $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$list['USER_ID'][$i]."'");

    if ($mem['CASH'] == '') {
      $mem['CASH'] = 0;
    }

    $n_cash = $mem['CASH'] + $won_price;
    $o_cash = $mem['CASH'];

    $sql3 = "
      INSERT INTO T_CASH_LOG(
        LOG_NO,
        CASH_NO,
        ORDERS_NO,
        WINNING_NO,
        INVOCE_NO,
        CASH_LOG_NO,
        PRICE,
        CASH,
        N_CASH,
        O_CASH,
        USER_ID,
        MEMO,
        STATUS,
        REG_DATE
      ) VALUES (
        '',
        '0',
        '".$list['ORDERS_NO'][$i]."',
        '0',
        '0',
        '0',
        '0',
        '".$won_price."',
        '".$n_cash."',
        '".$o_cash."',
        '".$list['USER_ID'][$i]."',
        '당첨금 적립',
        'P',
        NOW()
      )
    ";
    $db->query($sql3);

    $sql2 = "
      UPDATE MEMBER SET
        CASH = CASH+'".$won_price."'
      WHERE
        USER_ID = '".$list['USER_ID'][$i]."'
    ";
    $db->query($sql2);
  }
}

alert_print("당첨금을 적용하였습니다.");
meta_go("/goods/win_money.html");
?>
