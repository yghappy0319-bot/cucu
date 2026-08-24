<?php
include_once "../../_chk.php";
$data = explode("/", $payinfo);
$pay = db_select("select * from tb_pay_config where idx = {$data[4]} ");

  if($pay['discount']>0){
    $discountRate = $pay['discount'];
    $decimalRate = $discountRate / 100;
    $할인가 = $pay['price'] * $decimalRate;
    $total_price = $pay['price'] - $할인가;
  }else{
    $total_price = $pay['price'];
  }

  $vat = $total_price * 0.1;
  $total_amount = $pay['price'];  //$total_price + $vat;

  $부가세별도안내 = "(VAT별도)";

if($data[5]=="bank"){ // 부가세별도 10% 추가
  $total_amount = $pay['price'];
  $부가세별도안내 = "";
}

?>
<div class="modal-header">
  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
    <span aria-hidden="true">×</span>
  </button>
  <h4 class="modal-title" id="myModalLabel1"><?=$pay['product']?></h4>
</div>
<div class="modal-body">
  <h5 class="popup_title"><?=$pay['product']?></h5>
  <div class="popup_content">
    <div>서비스 기간 : <?=$pay['service_day']?>일</div>
    <div>처리건수 : <?=$pay['service_sms']?>건</div>
  </div>

  <form id="bankFrm" >
  <? if($data[5]=="bank"){ ?>
    <?
      // 저장된 사업자/연락처가 있으면 라디오 기본 선택과 입력창 표시를 자동으로 맞춤
      $has_tax_invoice = isset($member['biz_no'], $member['company_name'], $member['ceo_name']) &&
                          trim((string)$member['biz_no']) !== '' &&
                          trim((string)$member['company_name']) !== '' &&
                          trim((string)$member['ceo_name']) !== '';
      $has_cash_receipt = isset($member['tel']) && trim((string)$member['tel']) !== '';

      // 2=세금계산서, 1=현금영수증, ''=신청안함
      $receipt_default_obj = $has_tax_invoice ? 2 : ($has_cash_receipt ? 1 : '');
    ?>
    <div>
        <div>
          <label for="receipt1">
          <input
            type="radio"
            name="receipt1"
            value=""
            id="receipt1"
            onclick="go_receipt()"
            <?= $receipt_default_obj === '' ? 'checked' : '' ?>
          />신청안함
          </label>
          <label for="receipt2">
          <input
            type="radio"
            name="receipt1"
            value="1"
            id="receipt2"
            onclick="go_receipt(1)"
            <?= $receipt_default_obj === 1 ? 'checked' : '' ?>
          />현금영수증
          </label>
          <label for="receipt3">
          <input
            type="radio"
            name="receipt1"
            value="2"
            id="receipt3"
            onclick="go_receipt(2)"
            <?= $receipt_default_obj === 2 ? 'checked' : '' ?>
          />세금계산서
          </label>
        </div>

        <div class="tax_info info1" style="display:<?= $receipt_default_obj === 1 ? 'block' : 'none' ?>;">
          <input
            type="text"
            name="cash_receipt"
            placeholder="휴대폰번호 혹은 사업자번호를 입력해주세요."
            value="<?= isset($member['tel']) ? htmlspecialchars((string)$member['tel']) : '' ?>"
          />
          <div>
            <button type="button" onclick="go_tax_info_save()" >저장</button>
          </div>
        </div>

        <div class="tax_info info2" style="display:<?= $receipt_default_obj === 2 ? 'block' : 'none' ?>;" >
          <input
            type="text"
            name="cash_receipt1"
            placeholder="회사명"
            value="<?= isset($member['company_name']) ? htmlspecialchars((string)$member['company_name']) : '' ?>"
          />
          <input
            type="text"
            name="cash_receipt2"
            placeholder="대표자명"
            value="<?= isset($member['ceo_name']) ? htmlspecialchars((string)$member['ceo_name']) : '' ?>"
          />
          <input
            type="text"
            name="cash_receipt3"
            placeholder="사업자번호"
            value="<?= isset($member['biz_no']) ? htmlspecialchars((string)$member['biz_no']) : '' ?>"
          />
          <input
            type="text"
            name="cash_receipt4"
            placeholder="담당자 연락처"
            value="<?= isset($member['tel']) ? htmlspecialchars((string)$member['tel']) : '' ?>"
          />
          <input
            type="text"
            name="cash_receipt5"
            placeholder="전자세금계산서 이메일"
            value="<?= isset($member['email']) ? htmlspecialchars((string)$member['email']) : '' ?>"
          />
          <div>
            <button type="button" onclick="go_tax_info_save()" >저장</button>
          </div>
        </div>
      </div>

    <div style="    background-color: #ccc; padding: 10px; font-size: 14px;">
      <div>계좌안내</div>
      <div><b>100124200276 케이뱅크 장영관(럭키루트)</b></div>
      <div>
        입금 후 1분 뒤 즉시 처리 됩니다.<br />즉시처리가 되지 않을 시 <a href="https://open.kakao.com/o/soV2H3gf" target="_blank" >카카오톡</a>으로 문의주시면 즉시처리 도와드리겠습니다.
      </div>
    </div>

  <? } ?>

    <div style="    text-align: right;" >
    <? if($pay['discount']>0){ ?>
      <div >
        정상가 :
        <b class='discount'><?= number_format($pay['price']) ?>원</b>
        <span class='discount_persent'><?=$pay['discount']?>%할인</span>
      </div>
      <div >
          <p>
            총 결제금액(<?=$pay['discount']?>%할인적용) : <?= number_format($total_price) ?>원
          </p>
          <? if($data[5]=="card"){ ?>
           + <?= number_format($vat) ?>원<?=$부가세별도안내?>
          <? } ?>
        <? }else{ ?>
          <div class="total_amount" >총 결제금액 : <?= number_format($total_amount) ?>원<?=$부가세별도안내?></div>
          <input type="hidden" value="<?=$total_amount?>" class="total" />
        <? } ?>
      </div>
    </div>
  </form>

</div>
<div class="modal-footer">
  <button type="button" class="btn grey btn-outline-secondary" data-dismiss="modal">Close</button>
  <? if($data[5]=="bank"){  ?>
    <button type="button" class="btn btn-outline-primary" onclick="go_bank_chk()">무통장 결제</button>
  <? }else{ ?>
<button type="button" class="btn btn-outline-primary" onclick="go_innopay()">신용카드로 결제하기</button>
  <? } ?>

</div>
<script>
  var pay_amount = "<?=$total_amount?>";
  var service_amount = parseFloat(pay_amount); // 숫자형으로 변환
  function go_receipt(obj, silent){
    if(obj==1 || obj ==2){
      var amount = tax(service_amount);

      var total_amount = amount.toLocaleString();
      if(!silent){
        alert("부가세별도가 적용되어 총 입금 금액은 ["+total_amount+"원] 입니다.");
      }
      $(".total_amount").html("<b>총 결제금액 : " + total_amount + "원</b>");
      $(".bank_total").val(amount);
      $(".total").val(amount);
      $("#inno_amt").val(amount);
    }else{
      var total_amount = service_amount.toLocaleString();
      $(".total_amount").html("<b>총 결제금액 : " + total_amount + "원</b>");
      $(".total").val(service_amount);
      $("#inno_amt").val(service_amount);
    }

    $(".tax_info").css("display","none");
    $(".info"+obj).show();
  }

  <? if(isset($receipt_default_obj) && ($receipt_default_obj === 1 || $receipt_default_obj === 2)){ ?>
  // 저장된 선택값이 있으면 자동으로 금액/표시를 맞춰줌
  go_receipt(<?=$receipt_default_obj?>, true);
  <? } ?>
</script>
