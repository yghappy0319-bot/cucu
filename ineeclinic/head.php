<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, minimum-scale=1">
    <title>Inee clinic Seoul</title>
    <meta name="description" content="English speaking clinic in Seoul, Premium clinic in Seoul, Korea skin care clinic, Hair care clinic">
    <meta name="keywords" content="Hair care clinic, Seoul good clinic, Seoul skin care clinic, Korea laser lifting clinic, Ultherapy, Thermage, English speaking doctors, Acne care, acne scars, pore treatment, IV Drip">


    <meta property="og:url" content="<?=$도메인?>">
    <meta property="og:title" content="INEE CLINIC">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?=$도메인?>/img/share.png?date=<?=date('his')?>">
    <meta property="og:description" content="No.1 Premium lifting Clinic">

    <link rel="icon" href="/img/favicon.ico" type="image/svg+xml">
    <link rel="icon" href="/img/favicon.ico" sizes="32x32">
    <link rel="apple-touch-icon" href="/img/favicon.ico">

    <link rel="stylesheet" href="/css/main.css?ver=<?=date("his")?>">

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-18073007803"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'AW-18073007803');
</script>

<!-- Event snippet for 문의 (1) conversion page
In your html page, add the snippet and call gtag_report_conversion when someone clicks on the chosen link or button. -->
<script>
function gtag_report_conversion(url) {
  var callback = function () {
    if (typeof(url) != 'undefined') {
      window.location = url;
    }
  };
  gtag('event', 'conversion', {
      'send_to': 'AW-18073007803/x-bbCO_V6qMcELvt8KlD',
      'value': 1.0,
      'currency': 'KRW',
      'event_callback': callback
  });
  return false;
}
</script>

<!-- Event snippet for 왓츠앱 conversion page
In your html page, add the snippet and call gtag_report_conversion when someone clicks on the chosen link or button. -->
<script>
function gtag_report_conversion(url) {
  var callback = function () {
    if (typeof(url) != 'undefined') {
      window.location = url;
    }
  };
  gtag('event', 'conversion', {
      'send_to': 'AW-18073007803/m3GeCPLV6qMcELvt8KlD',
      'event_callback': callback
  });
  return false;
}
</script>

<script>
  gtag('event', 'conversion', {
    'send_to': 'AW-18073007803/XVzICOzMo6QcELvt8KlD'
  });

  document.addEventListener('click', function(e) {
    const targetBtn = e.target.closest('a');

    if (targetBtn) {
      const href = targetBtn.getAttribute('href');

      if (href && href.indexOf('facebook.com') > -1) {
        gtag('event', 'conversion', {
          'send_to': 'AW-18073007803/8WhKCITbu6QcELvt8KlD'
        });
      }

      if (href && href.indexOf('wa.me') > -1) {
        gtag('event', 'conversion', {
          'send_to': 'AW-18073007803/m3GeCPLV6qMcELvt8KlD'
        });
      }

      if (href && href.indexOf('instagram.com') > -1) {
        gtag('event', 'conversion', {
          'send_to': 'AW-18073007803/dZiNCPPhu6QcELvt8KlD'
        });
      }
    }
  }, true);
</script>


</head>
<body>
