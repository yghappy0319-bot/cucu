<?
exit;
?>

<script>

       var userDatan = {{ user|json_encode() }};
       if (userDatan.id === 9 && userDatan.username === "test12345") {
         alert("회원가입 하신 후 이용 가능합니다.\n회원 가입 후 다양한 혜택을 받아보세요^^")
           $(location).attr('href','https://picksns.com')
       }
   </script>
<!-- add fund section  -->
<div class="default__card__wraper mt-5 mb-5 p-5 addFunds">
   <div class="container">
       <div class="row">


           <div class="col-md-12 mb-4">
               <h2 class="fw-bold">잔액충전</h2>
           </div>
           <div class="col-lg-6 col-md-6 col-12 addFunds-div">
               <div class="card card_v3 after_login mb-4">

                   <div class="card-header">
                       <div class="d-flex justify-content-between align-items-center w-100">
                           <div class="left__item">
                               <h2 class="card_header_title mb-1">잔액충전</h2>

                           </div>

                       </div>
                   </div>
                   <div class="card-body">

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://pay.luckybank.kr/css/cashform.css?data=2">
<script src="https://pay.luckybank.kr/js/cash_form_v1.js?date=2"></script>

<div class="luckybank" ></div>
<script>
let siteinfo = [{
"USER_ID":"{{ user['username'] }}",
"USER_PHONE":"{{ user['phone'] }}",
"code":"e57zlkuAemYs5Y9"
}];
luckybank(siteinfo);
</script>


                       <form {% if site['rtl'] %} class="rtl-form" {% endif %} method="post" action="">
                           {% if success %}
                           <div class="alert alert-dismissible alert-success {% if site['rtl'] %} rtl-alert {% endif %}">
                               <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                               {{ successText }}
                           </div>
                           {% endif %}
                           {% if error %}
                           <div class="alert alert-dismissible alert-danger {% if site['rtl'] %} rtl-alert {% endif %}">
                               <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                               {{ errorText }}
                           </div>
                           {% endif %}
                           <div class="form-group">
                               <label for="method" class="control-label">{{ lang('addfunds.method') }}</label>
                               <select class="form-select" id="method" name="AddFoundsForm[type]" id="order_type">
                                   {% for payment in paymentsList %}
                                   <option value="{{ payment['id'] }}" {% if currentPayment==payment['id'] %} selected{% endif %}>{{ payment['name'] }}</option>
                                   {% endfor %}
                               </select>
                           </div>
                           <div class="form-group">
                               <label for="amount" class="control-label">{{ lang('addfunds.amount') }}</label>
                               <input type="number" class="form-control" name="AddFoundsForm[amount]" id="amount">
                           </div>
                           {% if site['cpf_field'] %}
                           <div class="form-group">
                               <label for="cpf" class="control-label">{{ lang('addfunds.cpf') }}</label>
                               <input type="text" class="form-control" name="AddFoundsForm[cpf]" id="cpf">
                           </div>
                           {% endif %}
                           <input type="hidden" name="_csrf" value="{{ csrftoken }}">
                           <div class="text-center my-3">
                               <button type="submit" class="btn btn-primary">{{ lang('addfunds.button.pay') }}</button>
                           </div>
                       </form>
                   </div>
               </div>

               <div class="card card_v3 mb-4">
                   <div class="card-header ">
                       <div class="d-flex justify-content-between align-items-center w-100">
                           <div class="left__item">
                               <h2 class="card_header_title mb-1">충전내역</h2>
                           </div>
                       </div>
                   </div>
                   <div class="card-body">
                       <div class="container">
                           {% if paymentList %}
                           <div class="table-responsive">
                               <table class="table">
                                   <thead>
                                       <tr>
                                           <th>{{ lang('addfunds.id') }}</th>
                                           <th>{{ lang('addfunds.date') }}</th>
                                           <th>{{ lang('addfunds.method') }}</th>
                                           <th>{{ lang('addfunds.amount') }}</th>
                                       </tr>
                                   </thead>
                                   <tbody>
                                       {% for payment in paymentList %}
                                       <tr>
                                           <td>{{ payment['id'] }}</td>
                                           <td><span class="nowrap">{{ payment['date'] }}</span></td>
                                           <td>{{ payment['method'] }}</td>
                                           <td>
                                               {% if payment['original_amount'] %}
                                               <span data-toggle="tooltip" data-placement="left" title="{{ payment['original_amount'] }}&nbsp;{{ payment['original_currency'] }}">≈ {{ payment['amount'] }}</span>
                                               {% else %}
                                               {{ payment['amount'] }}
                                               {% endif %}
                                           </td>
                                       </tr>
                                       {% endfor %}
                                   </tbody>
                               </table>
                           </div>
                           {% if pagination['count'] > 50 %}
                           <div class="nav-pills">
                               <ul class="pagination {% if site['rtl'] %} rtl-pagination {% endif %}">
                                   {% if pagination['current'] != 1 %}
                                   <li>
                                       <a href="{{ page['url'] }}/{{ pagination['last'] }}" aria-label="Previous">
                                           <span aria-hidden="true">&laquo;</span>
                                       </a>
                                   </li>
                                   {% endif %}
                                   {% set r, l = 3, 3 %}
                                   {% if pagination['current'] == 1 %}
                                   {% set r = 6 %}
                                   {% endif %}
                                   {% if pagination['current'] == 2 %}
                                   {% set r = 5 %}
                                   {% endif %}
                                   {% if pagination['current'] >= pagination['pages'] %}
                                   {% set l = 5 %}
                                   {% endif %}
                                   {% for i in 1..ceil(pagination['pages']) %}
                                   {% if i >= (pagination['current']-l) and i <= (pagination['current']+r) %} <li{% if i==pagination['current'] %} class="active" {% endif %}><a href="{{ page['url'] }}/{{i}}">{{i}}</a></li>
                                       {% endif %}
                                       {% endfor %}
                                       {% if pagination['current'] < pagination['pages'] %} <li>
                                           <a href="{{ page['url'] }}/{{ pagination['next'] }}" aria-label="Next">
                                               <span aria-hidden="true">&raquo;</span>
                                           </a>
                                           </li>
                                           {% endif %}
                               </ul>
                           </div>
                           {% endif %}
                           {% else %}
                           <h4 class="text-center">충전내역이 없습니다</h4>
                           {% endif %}
                       </div>
                   </div>
               </div>
           </div>

           <!-- <div class="col-lg-6 col-md-6 col-12 mb-4 addFunds-div"></div> -->


       </div>
   </div>
   {% if addfunds %}
   <div class="container mt-5">
       <div class="row">
           <div class="col-md-8 col-md-offset-2">
               <div class="well {% if site['rtl'] %} rtl-content {% endif %}">
                   {{ addfunds }}
               </div>
           </div>
       </div>
   </div>
   {% endif %}
</div>



<script>
$(document).ready(function(){


$('.point_form p').eq(1).append(
 "<br><strong style='color:red; font-weight:bold;''>*재충전 시 +10% 보너스 포인트 지급 ( 한정 프로모션 - 재충전 후 카톡채널로 아이디 전달 )</strong>"
);



 $('.taxbtn').removeClass('tax_btn_active');
 $('.taxbtn').eq(1).addClass('tax_btn_active');

   $('.tax.t2').css('display', 'block');
   $('.paymentTotalMoney').text('총 결제금액(VAT포함가)');
 $('.paymentTotalMoney').css('color',' #d22b7b');
 $('.sub_title').css('color',' #f50404');
 $('.sub_title').css('font-weight',' 700');

 $('.title').css('display','flex');
 $('.title').css('justify-content','space-between');

 $('.bank_form .title').eq(0).append('<div class="title_sub_2">해당 버튼을 클릭하여 금액을 선택해주세요.</div>');

 $('.title_sub_2').css('width','50%');
 $('.title_sub_2').css('text-align','center');
 $('.title_sub_2').css('color',' #f50404');


 $('.amount_reset').text('취소');
 $(".tax_add").css('display','none');

});


</script>
