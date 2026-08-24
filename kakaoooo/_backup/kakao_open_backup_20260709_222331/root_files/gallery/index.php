<?
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>조롱관</title>

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="자숙의 역사" />
  <meta property="og:description" content="자수기도 사람이다.." />
  <meta property="og:url" content="https://kakao1.iwinv.net/" />
  <meta property="og:image" content="https://kakao1.iwinv.net/" />

  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <!-- fancyBox CSS -->
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css"
  />

  <!-- fancyBox JS -->
  <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>

  <style>
    body {
      margin: 0;
      padding: 20px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #fafafa;
    }
    #gallery {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 20px;
      padding: 20px 0;
      max-width: 1200px;
      margin: 0 auto;
    }

    #gallery a {
      position: relative;
      display: block;
      width: 300px;
      border-radius: 10px;
      overflow: hidden;
      cursor: pointer;
      box-shadow: 0 2px 10px rgba(0,0,0,0.2);
      flex-shrink: 0;
      transition: transform 0.3s ease;
      background: white;
      text-decoration: none;
      color: inherit;
    }

    #gallery a img {
      display: block;
      width: 100%;
      height: auto;
      vertical-align: middle;
      /* 이미지 아래 여백 없애기 */
      margin-bottom: 0;
    }

    #gallery a::after {
      content: "▶";
      font-size: 48px;
      color: white;
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      opacity: 0.8;
      pointer-events: none;
      text-shadow: 0 0 8px rgba(0, 0, 0, 0.7);
      z-index: 2;
    }

    /* 타이틀 영역을 이미지 영역 아래쪽에 고정 */
    #gallery a .title {
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      background: #fff; /* 반투명 배경 */
      padding: 10px 5px;
      box-sizing: border-box;
      font-weight: 600;
      font-size: 1.2rem;
      z-index: 3;
      text-align: center;
    }

    #gallery a .title > div {
      font-weight: normal;
      font-size: 0.85rem;
      margin-top: 4px;
    }



    /* 모바일 반응형 */
    @media (max-width: 768px) {
      #gallery a {
        width: 45vw;
      }
    }
    @media (max-width: 480px) {
      #gallery a {
        width: 90vw;
      }
    }

    h1 {text-align: center;}
  </style>
</head>
<body>
  <div>
    <h1>조롱관</h1>
  </div>
  <div id="gallery">

    <?
      $sql = "select * from tb_video order by rdate desc";
      $result = db_query($sql);
      for($i=0;$row=db_fetch($result);$i++){
    ?>

    <a
      data-fancybox="video-gallery"
      href="/gallery/video/<?=$row['video']?>"
      data-caption="<?=$row['title']?>"
    >
      <img src="<?=$row['photo']?>" alt="유튜브 썸네일" />
      <div class="title">
        <?=$row['title']?>

        <div>
          <?=$row['rdate']?>
        </div>
        <!-- <div>
          <button type="button" onclick="go_view('<?=$row['idx']?>')" >view</button>
        </div> -->
      </div>

    </a>

  <? } ?>
  </div>
</body>

<script>
  function go_view(obj){
    location.href="/gallery/gallery.html?idx=" + obj;
  }
</script>

</html>
