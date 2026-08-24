<?
if(!isset($_SERVER["HTTPS"])) {
header('Location: https://'.$_SERVER["HTTP_HOST"].$_SERVER['REQUEST_URI']);
}

$도메인 = "https://baidclinicinseoul.com";

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, minimum-scale=1">
    <title>BAID CLINIC</title>
    <meta name="description" content="No.1 skin care clinic in Korea">
    <meta name="keywords" content="Apgujeong plastic surgery,Gangnam dermatology clinic,Best clinic in Seoul for foreigners,English speaking doctors Seoul,Japanese friendly clinic Seoul,Korean aesthetic clinic for tourists,Seoul beauty clinic near Apgujeong,Botox filler laser clinic Korea,Anti-aging treatment Seoul,Skin rejuvenation Korea,Ultherapy Thermage Seoul,Acne scar treatment Seoul,Whitening skin laser Korea,Foreigner tax refund clinic Korea,Medical tourism Korea aesthetic,Luxury beauty clinic Apgujeong,24 hour English support clinic Seoul,Cosmetic surgery Seoul for foreigners,Korean beauty trend treatments">


    <meta property="og:url" content="<?=$도메인?>">
    <meta property="og:title" content="BAID CLINIC">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?=$도메인?>/img/img.png">
    <meta property="og:description" content="No.1 skin care clinic in Korea">

    <link rel="stylesheet" href="/css/main.css?ver=<?=date("his")?>">

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-17544040162"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'AW-17544040162');
    </script>

    <script>
    function gtag_report_conversion(url) {
      var callback = function () {
        if (typeof(url) != 'undefined') {
          window.location = url;
        }
      };
      gtag('event', 'conversion', {
          'send_to': 'AW-17544040162/zPzyCMX8p5cbEOKd061B',
          'value': 1.0,
          'currency': 'KRW',
          'event_callback': callback
      });
      return false;
    }
    </script>
</head>
<body>

    <header></header>
    <nav></nav>
    <section>
      <div class="wrap" >
        <a href="/promotion.html" target="_blank" ><img src="/img/pc.jpg?ver=<?=date("His")?>" class="pc_img" /></a>
        <a href="/promotion.html" target="_blank" ><img src="/img/mo.jpg?ver=<?=date("His")?>" class="mo_img" style="display:none;" /></a>

        <div class="sns_right_bnt_box" >
          <ul class="sns_link_list">
            <li class="sns_icon">
              <a href="javascript:go_wa('https://wa.me/821048817810')"><img src="/img/btn2.png" /></a>
            </li>
            <li class="sns_icon">
              <a href="" target="_blank" ><img src="/img/btn1.png" /></a>
            </li>
            <li class="sns_icon">
              <a href="" target="_blank"><img src="/img/btn3.png" /></a>
            </li>
            <li class="sns_icon">
              <a href="" target="_blank"><img src="/img/btn4.png" /></a>
            </li>

          </ul>
        </div>
      </div>

    </section>
    <footer>

    </footer>
</body>

<script>
  function go_wa(obj){
    gtag_report_conversion('https://baidclinicinseoul.com');
    window.open(obj);

  }

function gtag_report_conversion(url) {
  var callback = function () {
    if (typeof(url) != 'undefined') {
      window.location = url;
    }
  };
  gtag('event', 'conversion', {
      'send_to': 'AW-17544040162/zPzyCMX8p5cbEOKd061B',
      'value': 1.0,
      'currency': 'KRW',
      'event_callback': callback
  });
  return false;
}
</script>
</html>
