<?
if($_GET['fp']){ //광고유입
  $_SESSION['ads'] = $_GET['fp'];
}
if($_GET['code']=="0"){ //추천인코드
  $_SESSION['code'] = "";
}else{
  $_SESSION['code'] = $_GET['code'];
}

if (strpos($_SERVER['HTTP_HOST'], 'megalotto.world') !== false) {
  $_SESSION['ads'] = "mega";
}
if (strpos($_SERVER['HTTP_HOST'], 'powerlotto.world') !== false) {
  $_SESSION['ads'] = "power";
}

//  메가파워월드() - <?=$header_title ? $header_title:"미국복권구매대행(메가파워월드)"
?>
<meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<meta http-equiv="X-UA-Compatible" content="ie=edge">

<? if (function_exists('SEO키워드숨김') && SEO키워드숨김()) { ?>
<title>메가파워월드</title>
<meta name="description" content="메가파워월드">
<meta property="og:url" content="https://www.megapower.world">
<meta property="og:title" content="메가파워월드">
<meta property="og:type" content="website">
<meta property="og:site_name" content="메가파워월드">
<meta property="og:image" content="/images/og_image.png?data=<?=date('his')?>">
<meta property="og:description" content="메가파워월드">
<? } else { ?>
<title>메가파워월드 - 미국복권구매대행</title>
<meta name="description" content="미국복권구매대행은 고객만족도100% 메가파워월드와 함께 하세요. 미국복권|미국로또|미국파워볼|메가밀리언|메가밀리언구매대행|미국파워볼구매대행|미국로또구매대행|미국복권구매대행|메가로또월드|파워로또월드">
<meta name="keywords" content="미국복권구매대행은 고객만족도100% 메가파워월드와 함께 하세요. 미국복권|미국로또|미국파워볼|메가밀리언|메가밀리언구매대행|미국파워볼구매대행|미국로또구매대행|미국복권구매대행|메가로또월드|파워로또월드">
<meta property="og:url" content="https://www.megapower.world">
<meta property="og:title" content="미국복권구매대행 고객만족도100% 메가파워월드">
<meta property="og:type" content="website">
<meta property="og:site_name" content="메가파워월드 : 미국복권 구매대행 서비스 메가파워월드 파워볼 메가밀리언 미국로또 메가로또월드 파워로또월드">
<meta property="og:image" content="/images/og_image.png?data=<?=date('his')?>">
<meta property="og:description" content="미국복권구매대행은 고객만족도100% 메가파워월드와 함께 하세요. 미국복권|미국로또|미국파워볼|메가밀리언|메가밀리언구매대행|미국파워볼구매대행|미국로또구매대행|미국복권구매대행|메가로또월드|파워로또월드">
<? } ?>

<? /**  
<meta name="naver-site-verification" content="3127bb4920bd773523ef310a47a01e04f6d0d9ec" />
*/
?>

<link rel="apple-touch-icon" sizes="57x57" href="/assets/icon/apple-icon-57x57.png">
<link rel="apple-touch-icon" sizes="60x60" href="/assets/icon/apple-icon-60x60.png">
<link rel="apple-touch-icon" sizes="72x72" href="/assets/icon/apple-icon-72x72.png">
<link rel="apple-touch-icon" sizes="76x76" href="/assets/icon/apple-icon-76x76.png">
<link rel="apple-touch-icon" sizes="114x114" href="/assets/icon/apple-icon-114x114.png">
<link rel="apple-touch-icon" sizes="120x120" href="/assets/icon/apple-icon-120x120.png">
<link rel="apple-touch-icon" sizes="144x144" href="/assets/icon/apple-icon-144x144.png">
<link rel="apple-touch-icon" sizes="152x152" href="/assets/icon/apple-icon-152x152.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/icon/apple-icon-180x180.png">

<link rel="icon" type="image/png" sizes="192x192"  href="/assets/icon/android-icon-192x192.png">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/icon/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="96x96" href="/assets/icon/favicon-96x96.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/icon/favicon-16x16.png">
<link rel="manifest" href="/assets/icon/manifest.json">
<meta name="msapplication-TileColor" content="#ffffff">
<meta name="msapplication-TileImage" content="/assets/icon/ms-icon-144x144.png">
<meta name="theme-color" content="#ffffff">

<link rel="canonical" href="https://www.megapower.world">

<link rel="stylesheet" href="/assets/css/default.css?date=<?=date("His")?>">
<link rel="stylesheet" href="/assets/css/main.css?date=<?=date("His")?>">
<link rel="stylesheet" href="/assets/css/sub.css?date=<?=date("His")?>">
<!--<link rel="stylesheet" href="/assets/font/static/pretendard.css">-->
<!-- new -->
<link rel="shortcut icon" type="image/x-icon" href="/images/favicon.ico">
<link rel="stylesheet" href="/common/fonts/PretendardGOV/pretendard-gov-subset.css">
<link rel="stylesheet" href="/common/fonts/XEIcon-master/xeicon.min.css">
<link rel="stylesheet" href="/common/css/style.css?date=<?=date("His")?>">
<link rel="stylesheet" href="/common/plugins/swiper/swiper-bundle.min.css">
<!--<link rel="stylesheet" href="//cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />-->
<!--<script src="//cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>-->
<script src="//code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="/assets/js/main.js?date=<?=date("His")?>"></script>
<script src="/assets/js/bpopup.js"></script>
<script src="//unpkg.com/webtonative@1.0.47/webtonative.min.js"></script>

<!-- new -->
<script src="/common/plugins/swiper/swiper-bundle.min.js"></script>
<script src="/common/js/script.js"></script>

<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
  window.OneSignalDeferred = window.OneSignalDeferred || [];
  OneSignalDeferred.push(async function(OneSignal) {
    await OneSignal.init({
      appId: "1e96fb13-46bd-497f-b04b-66097b1334fe",
      notifyButton: { enable: false }
    });
  });
</script>

<? if (strpos($_SERVER['HTTP_HOST'], 'powerlotto.world') !== false) { ?>
<script type="text/javascript" src="//wcs.naver.net/wcslog.js"></script>
<script type="text/javascript">
if(!wcs_add) var wcs_add = {};
wcs_add["wa"] = "788e19e9cc8ec0";
if(window.wcs) {
  wcs_do();
}
</script>
<? } elseif (strpos($_SERVER['HTTP_HOST'], 'megalotto.world') !== false) { ?>
<script type="text/javascript" src="//wcs.naver.net/wcslog.js"></script>
<script type="text/javascript">
if(!wcs_add) var wcs_add = {};
wcs_add["wa"] = "788e346acddea8";
if(window.wcs) {
  wcs_do();
}
</script>
<? } ?>

<!-- <?=$_SESSION['ads']?> -->
