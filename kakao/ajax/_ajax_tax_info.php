<?php
include_once('../common.php');

$data = db_select("select * from bank_request where id = {$id} ");
$sdix = $data['site_idx'];

?>
<style>
.tax_send_info {margin: 10px 0px;}
.tax_send_info p{}
.tax_bnt {width: 100%; border: 0px; font-size: 16px; padding: 8px; border-radius: 4px;}
.tax_status {    text-align: center; }
.tax_status p{    font-weight: bold; color: #333; font-size: 18px;}
</style>
<div>
  <div class="box">

    <div>
      <h2>현금영수증</h2>
    </div>

    <div>
      <div>
        신청번호 : <?=$data['tax1_phone']?>
      </div>
      <? if($data['tax_status']==0){ ?>
      <div>
        <button type="button" class="tax_bnt" onclick="go_cash_bill('<?=$id?>','<?=$sdix?>')" >현금영수증 발급</button>
      </div>
      <? } ?>
    </div>
  <? if($data['tax_status']==1){ ?>
    <div>
      <h2>현금영수증 발행 완료</h2>
    </div>
    <div>
      신청번호 : <?=$data['tax1_phone']?>
    </div>
  <? } ?>

  </div>

  <div class="tax2 " >
      <div>
        <h2>세금계산서</h2>
      </div>
      <div><span>회사명</span> <?=$data['biz_name1']?></div>
      <div><span>대표자명</span> <?=$data['biz_name2']?></div>
      <div><span>사업자번호</span> <?=$data['biz_number']?></div>
      <div><span>담당자연락처</span> <?=$data['biz_etc2']?></div>
      <div><span>이메일</span>  <?=$data['biz_email']?></div>
      <div class="tax_send_info" >
        <p>- 전자세금계산서 발급은 팝빌을 통해 진행됩니다.</p>
        <p>- 인증서가 등록되어 있어야 발행이 가능합니다.</p>
      </div>

    <? if($data['tax_status']==0){ ?>
      <button type="button" class="tax_bnt" onclick="go_biz_cash_bill('<?=$id?>','<?=$sdix?>')" >전자세금계산서 발급</button>
    <? } ?>
  <? if($data['tax_status']==2){ ?>
    <div>
      <h2>세금계산서 발행 완료</h2>
    </div>

    <div class="tax_status" >
      <p>
        국세청승인번호
      </p>
      <div>
        <?=$data['approval_number']?>
      </div>
    </div>
  <? } ?>

  </div>
  <div class="status_box">
    <button type="button" onclick="go_tax_ok('<?=$id?>')" >확인</button>
    <button type="button" onclick="go_tax_close()" >닫기</button>
  </div>
</div>
