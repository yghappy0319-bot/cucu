/** 탭 */
$('.tab button').click(function(){
   // var tabCont = $(this).attr('data-tab');
   // $('#'+tabCont).show().siblings().hide();
   // $(this).parent().addClass('on').siblings().removeClass('on');

})

$('.pop_close').click(function(){
    console.log('asdasdas');
    $(this).parent().parent().parent('.pop_wrap').hide();
})

function alertPop(el){
    $(el).click(function(){
        var alertId = $(this).attr('data-alert');
        $('#'+alertId).show();
    })
}

function close_alert(el){
   $('#'+el).hide();
}



//파일첨부
function fileAttach(obj){
    var $this = $(obj).closest('.file');
    $this.find('[type=file]').trigger('click');
    $this.find('[type=file]').not('.is-evented').on('change', function(){
        var value = $(this).val();
		value=$(this)[0].files[0].name;
		//alert(value);
        $this.find('[type=text]').val(value);
    }).addClass('is-evented');
}

function pops(idxno){

 // alert(x);
$.ajax({   type: "POST",url: "edit.php",data: "idxno="+idxno,success:
     function(msg){ $('#pop_view').html(msg); }
 });

   popUpOpen('pops');

}
//=====================================
function popUpOpen(pop){
 const panel = pop;
 const popUp = document.getElementById(panel);
 popUp.style.display="block";
}


function popClose(pop){
    const panel = pop;
    const popUp = document.getElementById(panel);
    document.getElementsByTagName('body')[0].classList.remove('overflow');

    popUp.style.display="none";
}


function urlmove(v) {
  location.href=v;
}
function urlmover(v) {
  location.replace(v);
}

function numcheck(obj) {
  var val=obj.value;
	var re=/[^0-9]/gi;
	var b=val.replace(re,"");
    obj.value=b;
  //alert(a);
 }

  function allsub_check(obj) {

		 var par_check=eval("$('."+obj+"').is(':checked')");
		var checkboxes = eval("document.getElementsByClassName('"+obj+"1')");
        var n=checkboxes.length;

for(var i=0;i<n;i++) {
     if (par_check==true) { checkboxes[i].checked = true;
     } else { checkboxes[i].checked = false; }  }

 }

 function parent_check(obj) {
     checkboxes =  eval("document.getElementsByClassName('"+obj+"1')");
var n=checkboxes.length;
var chk=0;
var chk2=0;

for(var i=0;i<n;i++) {
     if ( checkboxes[i].checked==true) {  chk++; } else { chk2++; }
 }

   if (n==chk){ eval("$('."+obj+"').prop('checked',true)");
   } else if (n==chk2) { eval("$('."+obj+"').prop('checked',false)"); }
    // alert("3");
}
 function file_imgcheck() {
if( $("#file").val() != "" ){

var ext = $('#file').val().split('.').pop().toLowerCase();
    if($.inArray(ext, ['gif','png','jpg','jpeg']) == -1) {
     alert('gif,png,jpg,jpeg 파일만 업로드 할수 있습니다.');
     return;
      }
  }
 }

  function comma(str) {
        str = String(str);
        return str.replace(/(\d)(?=(?:\d{3})+(?!\d))/g, '$1,');
    }

function numcommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

//=============================
function cal_close(){

  $("html , body, .date-box .date").removeClass("fixed");
}

function pop_cals(y,m,d){
	// var frm0 = document.getElementById('search');
	  // frm0.sel_idxno.value=idxno;
   //alert(m+":월");
$.ajax({   type: "POST",url: "../inc/cal.php",data: "cal_type=1"+"&year="+y+"&month="+m+"&sday="+d,success:
    function(msg){ $('#cal_1').html(msg); }
 });  //pop-calendar

 $.ajax({   type: "POST",url: "../inc/cal.php",data: "cal_type=2"+"&year="+y+"&month="+m+"&sday="+d,success:
    function(msg){ $('#cal_2').html(msg); }
 });  //pop-calendar


}

function sel_cal(cal_type,dt,dt_str){
   var frm= document.getElementById('search');

   if(cal_type=="1"){
      $('#sdt').html(dt_str);
      frm.sear_sdate.value=dt;
   } else if(cal_type=="2"){
      $('#edt').html(dt_str);
      frm.sear_edate.value=dt;
   }

  $("html , body, .date-box .date").removeClass("fixed");
  pop_cals('','','');
}

function NewWindow(mypage, myname, w, h, scroll){
		var winl = (screen.width - w) / 2;
		var wint = (screen.height - h) / 2;
		winprops = 'height='+h+',width='+w+',top='+wint+',left='+winl+',scrollbars='+scroll+',noresize';
		win = window.open(mypage, myname, winprops)
		if (parseInt(navigator.appVersion) >= 4) { win.window.focus(); }
	}
