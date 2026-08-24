<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

/*
 * 단일 프린트
*/

$info	=	$db->get_data("SELECT * FROM AGENT WHERE AGENT_NO='".$_GET['no']."'");


?>
<!DOCTYPE html>
<html lang="ko">
<head>

<script src="/assets/js/jquery.min.js?ver=1.2"></script>
<script>
window.print();
window.setTimeout(function (){
	window.close();
}, 2500);



</script>

<style>

body {
	padding:0;
	margin:0;
}

p::after {clear:both; display:block; content:'';}

</style>
<style>
html,body,h1,h2,h3,h4,h5,h6,div,p,blockquote,pre,code,address,ul,ol,li,menu,nav,section,article,aside,
dl,dt,dd,table,thead,tbody,tfoot,label,caption,th,td,form,fieldset,legend,hr,input,button,textarea,object,figure,figcaption {margin:0;padding:0;}
body,input,select,textarea,button {border:none;}
table{border-spacing:0;border-collapse:collapse; margin:0px 0px 0px 0px;}
img,fieldset{border:0;}
label,img,input,select,textarea,button{vertical-align:middle;}


</style>

</head>

<body>
	<div style="width:100%;text-align:center;">
		<img src="/upload/qrcode/<?=$info["QRCODE"]?>">
	</div>
</body>
</html>
