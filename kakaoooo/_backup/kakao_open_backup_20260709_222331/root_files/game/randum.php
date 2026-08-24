<?
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <title>아이템</title>
  <style>
    body {
      font-family: sans-serif;
      padding: 0px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      text-align: left;
      border: 1px solid #ccc;
      text-align: center;
    }

    th {
      background-color: #f5f5f5;
    }
    tr:hover {
       background-color: #f2f2f2; /* 마우스 올렸을 때 배경색 변경 */
     }
    @media screen and (max-width: 600px) {
      table {font-size: 12px;}
    }

    .nickname { margin: 5px; display: inline-block; }
    .item_list a {color:#333;    text-decoration: none;}
    h2 { margin: 0px; font-size: 16px;}
    .atarget{text-align: left; padding-left: 5px;    height: 150px;
    overflow: scroll;}
    .atarget .regdate{}
  </style>
</head>
<body>
<?
  $최근 = db_select("select * from tb_randum_log order by regdate desc limit 1 ");
?>
<div class="item_search" >
  <div style="display: flex; justify-content: space-between;margin-bottom: 10px;" >
    <h2>아이템 랜덤 보급 | 최근 : <?=$최근['weekday']?> <?=$최근['regdate']?></h2>
    <div style="" >
      <i class="fas fa-download" id="capture-btn" ></i> |
      <a href="/color">색표</a> | <a href="/item">아이템</a> |
      <a href="/search">사용</a> | <a href="/hold">보유</a>
    </div>
  </div>

  <div style="display:none;" >
      <? if($a==1){ ?>
        <input type="text" name="search" />
        <button onclick="go_search()" >조회</button>
        <a href="/item?gender=1&a=<?=$a?>">남</a>
        <a href="/item?gender=2&a=<?=$a?>">여</a>
      <? } ?>
  </div>
</div>

<div id="copyTarget">
  <div>
    아이템 :
    <?
    $sql = "SELECT * FROM tb_item WHERE status = 0";
    $result2 = db_query($sql);
      foreach($result2 as $ite){
    ?>
      <span style="" ><?=$ite['itemname']?></span>
    <? } ?>
  </div>

  <style>
    .assignments {
      display: flex;
      flex-wrap: wrap; /* 줄바꿈 허용 */
      gap: 10px; /* 아이템 간격 */
      margin-top: 20px;
    }

    .assignment-item {
      padding: 8px 12px;
      border-radius: 8px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      font-size: 14px;
      color: #333;
    }
  </style>
  <form id="itemFrm">
    <div class="assignments">


    </div>
  </form>

</div>

<div>
  <button type="button" onclick="go_item_set()" >지급하기</button>
</div>



<script>
  function go_copy(obj){
    var copy = $("#copyTarget");
    const container = document.getElementById("copyTarget");
    copy.show();
    // 모든 <a> 태그의 텍스트를 추출
    const linkTexts = Array.from(container.querySelectorAll('a'))
                           .map(a => a.innerText.trim())
                           .join('\n');

    // 클립보드에 복사
    navigator.clipboard.writeText(linkTexts).then(() => {
      alert("복사되었습니다:\n\n" + linkTexts);
    }).catch(err => {
      alert("복사 실패: " + err);
    });
  }

  function go_item_set(){
    if(confirm("지급하시겠습니까?")){
      var params = jQuery("#itemFrm").serialize();
      $.ajax({
        type : "POST",
        url: "../_set_randum1.php",
        async: false,
        data: params,
      success: function(result) {
          $(".assignments").html(result);
        },
        error:function(e) {
          alert(e.responseText);
        }
      });
    }
  }

</script>


<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<script>
  document.getElementById('capture-btn').addEventListener('click', function () {
    const captureBtn = document.getElementById('capture-btn');
    const target = document.body;

    // 캡처 전에 버튼 숨기기
    captureBtn.style.display = 'none';
    $(".item_search").css("display","none");

    // 현재 시간 가져오기 및 포맷팅
    const now = new Date();
    const ymdhis = now.getFullYear().toString().slice(2) +
      String(now.getMonth() + 1).padStart(2, '0') +
      String(now.getDate()).padStart(2, '0') +
      String(now.getHours()).padStart(2, '0') +
      String(now.getMinutes()).padStart(2, '0') +
      String(now.getSeconds()).padStart(2, '0');

    // 약간의 지연을 줘서 숨긴 후 렌더링 안정화
    setTimeout(function () {
      html2canvas(target, {
        useCORS: true,
        allowTaint: true,
        scale: 2
      }).then(function (canvas) {
        // 다운로드 링크 생성
        const link = document.createElement('a');
        link.href = canvas.toDataURL();
        link.download = `capture_${ymdhis}.png`; // 파일명에 ymdhis 적용
        link.click();
      }).catch(function (err) {
        console.error('캡처 실패:', err);
      }).finally(function () {
        // 캡처 후 버튼 다시 보이기
        captureBtn.style.display = 'inline-block';
        $(".item_search").show();
      });
    }, 100); // 100ms 딜레이 (렌더링 안정화용)
  });
</script>

</body>
</html>
