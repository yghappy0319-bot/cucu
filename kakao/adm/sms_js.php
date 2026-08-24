<script>
// setInterval(function() {
//     location.reload();
// }, 35000); // 10000 밀리초는 10초를 의미합니다.

function go_tax_info(obj){
  $(".tax_info_"+obj).toggle();
  console.log('click');
}

function go_cash_view(obj){
  $(".cash_"+obj).toggle();
}

function go_site_search(obj,acount){
    $("#site").val(obj);
    $("#acount").val(acount);
    $("#searchFrm").submit();
}

function go_tax(obj){
  $.ajax({
     type : "POST",
     url: "/ajax/_ajax_tax_info.php",
     async: false,
     data: {
       id : obj
     },
     success: function(result) {
        var r = result.trim();
        $("#tax").html(r);
        $('#tax').bPopup({
             modalClose : true
         });
     },
     error:function(e) {
        alert(e.responseText);
     }
  });
}

function go_tax_ok(obj){
  if(confirm("단순 확인용이며 확인처리시 어떠한 데이터도 전송하지 않습니다\n확인완료 처리 하시겠습니까?")){
    $.ajax({
       type : "POST",
       url: "/ajax/_ajax_tax_ok_chk.php",
       async: false,
       data: {
         id : obj
       },
       success: function(result) {
          var r = result.trim();
          console.log(r);
          if(r==1){
            alert("확인처리 되었습니다.");
            location.reload();
          }
       },
       error:function(e) {
          alert(e.responseText);
       }
    });
  }
}
function go_tax_close(){
  $('#tax').bPopup().close();
}

function go_show(obj){
  $(".message").css("display","none");
  $(".view"+obj).show();
}
function go_out(obj){
  $(".message").css("display","none");
}
function go_ok(id,obj,sidx){
  var status = "";
  if(obj=="ok"){
    status = "운영하는 사이트에 고객 포인트가 강제로 지급되는 처리입니다.\n 완료";
  }else{
    status = "삭제";
  }
  if(confirm(""+status + " 처리 하시겠습니까? ")){
    if(obj=="ok"){
        alert("최종 처리완료 메세지가 나올때까지 기다려주세요.");
    }

    $.ajax({
       type : "POST",
       url: "/ajax/_ajax_status.php",
       async: false,
       data: {
         id : id,
         status : obj,
         site_idx : sidx
       },
       success: function(result) {
          var r = result.trim();
          console.log(r);
          if(r==1){
            location.reload();
          }else if(r=="balance_added"){
              alert("처리완료");
              location.reload();
          }else if(r=="balance_fail"){
              alert("처리 실패 관리자에게 문의해주세요.");
              location.reload();
          }else if(r=="status_ok"){
              alert("이미 처리된 신청건 입니다.");
              location.reload();
          }
       },
       error:function(e) {
          alert(e.responseText);
       }
    });
  }
}

function go_page(obj){
  $("#page").val(obj);
  $("#searchFrm").submit();
}
function go_cash_bill_cancel(){
  if(confirm("현금영수증 발행처리를 취소 하시겠습니까?")){
    alert("개발중");
  }
}
function go_biz_cash_bill_cancel(){
  if(confirm("세금계산서 발행처리를 취소 하시겠습니까?")){
    alert("개발중");
  }
}


function go_cash_bill(id,sidx,types){
  if(confirm("현금영수증 "+types+" 발행처리를 하시겠습니까?")){
    $.ajax({
       type : "POST",
       url: "/Popbill/cash_receipt.php",
       async: false,
       data: {
         id : id,
         sidx : sidx,
         types : types
       },
       success: function(result) {
          var r = result.trim();
          if(r==1){
            alert('발행 완료');
            location.reload();
            return false;
          }else{
            alert(r);
            location.reload();
            return false;
          }
            console.log(r);
       },
       error:function(e) {
          alert(e.responseText);
       }
    });
  }
}





function go_pay_cancel(idx){
  if(!confirm("미결제 신청내역을 삭제하시겠습니까?")){
    return;
  }
  $.ajax({
    type: "POST",
    url: "/ajax/_ajax_pay_cancel.php",
    data: { idx: idx },
    success: function(result) {
      var r = String(result).trim();
      if(r === "1"){
        alert("삭제되었습니다.");
        location.reload();
      }else{
        alert(r || "처리에 실패했습니다.");
      }
    },
    error: function(e) {
      alert(e.responseText || "오류가 발생했습니다.");
    }
  });
}

function go_cash_bill2(id,sidx,types){
  if(confirm("신청 하시겠습니까?")){
    $.ajax({
       type : "POST",
       url: "/ajax/_ajax_tax_apply.php",
       async: false,
       data: {
         id : id,
         sidx : sidx,
         types : types
       },
       success: function(result) {
          var r = result.trim();
          if(r==1){
            alert('신청이 완료되었습니다');
            location.reload();
            return false;
          }else{
            alert(r);
            location.reload();
            return false;
          }
            console.log(r);
       },
       error:function(e) {
          alert(e.responseText);
       }
    });
  }
}

function go_receipt_issue(id, receiptType){
  var label = String(receiptType) === '2' ? '전자세금계산서' : '현금영수증';
  if(!confirm(label + '를 발행하시겠습니까?')){
    return;
  }
  $.ajax({
    type: "POST",
    url: "/ajax/_ajax_tax_issue.php",
    async: false,
    data: { id: id },
    success: function(result) {
      var r = String(result).trim();
      if(r === "1"){
        alert(label + " 발행이 완료되었습니다.");
        location.reload();
      }else{
        alert(r || "처리에 실패했습니다.");
      }
    },
    error: function(e) {
      alert(e.responseText || "오류가 발생했습니다.");
    }
  });
}


function go_biz_cash_bill(id,sidx,status){
  var taxmsg = "";
  if(status==0){
    taxmsg = "*주의* 입금확인이 되지 않은 건 입니다.\n전자세금계산서를 발행 하시겠습니까?";
  }else{
    taxmsg = "(입금확인완료)\n전자세금계산서를 발행 하시겠습니까?";
  }

  if(confirm(taxmsg)){

    $.ajax({
       type : "POST",
       url: "/Popbill/biz_cash_receipt.php",
       async: false,
       data: {
         id : id,
         sidx : sidx
       },
       success: function(result) {
          var r = result.trim();
          if(r==1){
            location.reload();
            return false;
          }else{
            alert(r);
            location.reload();
            return false;
          }
            console.log(r);
       },
       error:function(e) {
          alert(e.responseText);
       }
    });
  }
}
</script>
