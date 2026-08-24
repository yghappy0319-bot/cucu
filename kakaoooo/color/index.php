<?
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
//$data = db_select("select * from kakao where idx = 1 ");

$썸명 = "민호썸";
// 파스텔톤 색상 배열
$pastel_colors = [
    "#fbd3e6", // 연핑크
    "#c2f0c2", // 연녹색
    "#cce5ff", // 연하늘색
    "#ffd9b3", // 살구색
    "#e6ccff", // 연보라
    "#fff5cc", // 연노랑
    "#ffcccc", // 연핑크빨강
    "#ccf2ff", // 민트계열
    "#ffe0cc", // 살구오렌지
    "#e0e0eb"  // 연회색
];

// 랜덤 색상 선택
$random_color = $pastel_colors[array_rand($pastel_colors)];

?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>프로필</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<link href="/css/style.css" rel="stylesheet">
</head>
<style>
html, body {
  height: 100%;
  margin: 0;
  padding: 0;
}

body {background: <?=$random_color?>;}
.commuter {position: absolute;
  bottom: 3px;
  background-color: fuchsia;
  border-radius: 4px;
  font-size: 12px;
  padding: 0px 6px;
  color: #fff;
  font-weight: 400;}
.hex {
  aspect-ratio: 1 / 1.15;
  background: #ddd;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  font-size: 18px;
  color: #000;
  position: relative;
  border-radius: 50%;
  border: 1px solid !important;
  width: 50px;
  height: 50px;
  margin-bottom: 14px;
}
.hex .cnt {}
.hex .label {
  position: absolute;
  font-size: 18px;
}
.wrap { }

.container{
  grid-template-columns: repeat(6, 1fr);
}

.mnull {border: 3px solid white !important;}
.mok {border: 3px solid $bgColor !important;}

</style>

<body >

  <div class="wrap">
    <div style="display: flex;justify-content: space-between;position: relative;align-items: center; margin-bottom: 10px;">
      <div></div>
      <div id="page_tab" >
        <i class="fas fa-download" id="capture-btn" ></i> |
        <a href="/color" style="font-size: 18px;" >색표</a> |
        <a href="/item" style="font-size: 18px;">아이템</a> |
        <a href="/search" style="font-size: 18px;">조회</a>
      </div>
    </div>

    <div class="container" >
      <?php
      $colors = [
        '#FAE100', '#F14F4A', '#EC7F5A', '#EE9830', '#8DBC30',
        '#4AA366', '#4EA698', '#4EA5B2', '#4D9DD8', '#4469A0',
        '#735FA7', '#9158B6', '#D35497', '#D7456A', '#FBF28C',
        '#EA9A93', '#ED9D8E', '#ECB273', '#B3C270', '#7FCF90',
        '#89CAC2', '#96C7CF', '#7EB9DB', '#88A2D4', '#AC9CDA',
        '#B785CB', '#E480B6', '#EB9EAE', '#F8F5D5', '#F8DAD8',
        '#F7D7C7', '#EFDBB7', '#E7F5BA', '#B9EBC2', '#CCEFF1',
        '#C6EEEE', '#C5E1EF', '#CADCEA', '#D8D9ED', '#E0D0EB',
        '#F7D5E6', '#F9E0E5', '#ffffff', '#C3C3C3', '#000'
      ];

      for ($i = 1; $i <= 45; $i++) {
          $bgColor = $colors[$i - 1];
          if(!$bgColor){
            $bgColor = "#FAE100";
          }


          $style = "background:{$bgColor};";
          if ($i == 45) { $style2 = 'color:#fff;';
          }else{ $style2 = ' '; }

          $table = "tb_member";
          $sql = "SELECT * FROM {$table} WHERE num = {$i} and status != 1";
          $result = db_query($sql);

          $items = [];
          while ($row = db_fetch($result)) {
              $items[] = $row;
          }
          ?>
          <div class="hex <?=count($items)==0 ? "mnull":"mok" ?>" data-number="<?= $i ?>" data-nick="<?= $data["name"] ?>" style="<?= $style ?>">
            <? if(count($items)==0){?>
              <div class="cnt" >
                <? if($i==1){?>
                <a href="javascript:go_create();">추가</a>
              <? }else if($i==44){?>
                <a href="javascript:;" style="color:#333;" >봇</a>
              <? }else{ ?>
                <a href="javascript:alert('신입 등록 하는 과정으로 진행할것!')" style="color: unset;" ><?= $i ?></a>
              <? } ?>
              </div>
            <? } ?>
              <div class="label" style="<?= $style2 ?> <?= $count == 1 ? '':'' ?> " >
                <? foreach ($items as $item) { ?>
                    <a href="javascript:go_update('<?=$item['idx']?>')" style="color: unset;" ><?=$item['name']?></a><div></div>
                <? } ?>
              </div>
          </div>
          <?php } ?>

          <?php
      $sql2 = "SELECT * FROM tb_member WHERE couple = 2 and status != 1";
      $result2 = db_query($sql2);
      $style2 = "background:#FAE100;";
      while ($row2 = db_fetch($result2)) {
      ?>
      <div class="hex" style="<?= $style2 ?>">
          <div class="label">
                  <a href="javascript:go_update('<?=$row2['idx']?>')" style="color: unset;">
                      <?=$row2['name']?>
                  </a>
          </div>
      </div>
    <?php } ?>



    </div>
  </div>
</body>

<!-- 팝업 -->
<div id="popupLayer" class="popup-overlay">
</div>

<div id="popupLayer" class="popup-overlay member_create" style="display:none;" >
  <div class="popup-box">
    <form id="orderFrm">
      <div>
        <div style="padding: 5px 0px;" >
          <input type="radio" name="couple" value="2" checked />신입
            <input type="radio" name="gender" class="gender" id="gender1" value="1" />남
            <input type="radio" name="gender" class="gender" id="gender2" value="2" />여
            <input type="text" class="name" name="name" id="name1" placeholder="닉네임"/>
        </div>



        <div>
          <textarea name="content" id="content1" style="width: 100%; height: 200px; margin-top: 5px;" ></textarea>
        </div>
      </div>
    </form>

    <div style="display: flex; justify-content: space-between;">
      <button id="closePopup">닫기</button>
      <button type="button" onclick="_insert()" >저장</button>
    </div>

  </div>
</div>

<script>
function setNames(input) {
 var names = input.split('|');
 document.getElementById('name1').value = names[0] || '';
 if (names.length > 1) {
   document.getElementById('name2').value = names[1] || '';
 } else {
   document.getElementById('name2').value = '';
 }
}

function go_create(){
    $('.member_create').fadeIn();
}

function _insert(){
  var name1 = $("#name1").val();
  var gender = $(".gender:checked").val();

  if(!name1){
    alert("닉네임을 입력해주세요.");
    return false;
  }

  if (!gender) {
      alert("성별을 선택해주세요.");
      return false;  // 전송 막기
  }

  var params = jQuery("#orderFrm").serialize();
    $.ajax({
      type : "POST",
      url: "../_new_insert.php",
      async: false,
      data: params,
    success: function(result) {
        var r = result.trim();
        if(r==1){
          location.reload();
        }
      },
      error:function(e) {
        alert(e.responseText);
      }
    });
}

function go_update(obj){
    var number = obj;
    var nick = $(this).data('nick');
    $(".number_title").html(number + "번 변경");
    $("#number").val(number);

    $.ajax({
       type : "POST",
       url: "../_get.php",
       async: false,
       data: {
         code : number,
         nick : nick
       },
       success: function(result) {
         $("#popupLayer").html(result);
       },
       error:function(e) {
          alert(e.responseText);
       }
    });

    $('#popupLayer').fadeIn();
}

$(function() {

  $('#closePopup, #popupLayer').click(function(e) {
    // 팝업 바깥쪽 클릭 시 닫기
    if (e.target.id === 'popupLayer' || e.target.id === 'closePopup') {
      $('#popupLayer').fadeOut();
    }
  });

  $('#closePopup, .member_create').click(function(e) {
    // 팝업 바깥쪽 클릭 시 닫기
    if (e.target.id === 'popupLayer' || e.target.id === 'closePopup') {
      $('.member_create').fadeOut();
    }


  });

});

function _set(){
  var number = $("#number").val();
  var name1 = $("#name1").val();
  var name2 = $("#name2").val();
  var name3 = $("#name3").val();//기존닉
  var gender = $(".gender:checked").val();
  var commuter = $(".commuter_chk:checked").val();
  var couple = $(".couple:checked").val();
  var content = $("#content1").val();

  if(!name1){
    alert("닉네임을 입력해주세요.");
    return false;
  }
  if (!gender) {
      alert("성별을 선택해주세요.");
      return false;  // 전송 막기
  }

  console.log("성별 :: " + gender);
  $.ajax({
     type : "POST",
     url: "../_new_set.php",
     async: false,
     data: {
      number : number,
      name : name1,
      name3 : name3,
      num : name2,
      couple : couple,
      commuter : commuter,
      gender : gender,
      content : content
     },
     success: function(result) {
        var r = result.trim();
        console.log(r);
        if(r==1){
          location.reload();
        }else{
          alert(r);
          return false;
        }
     },
     error:function(e) {
        alert(e.responseText);
     }
  });
}
</script>


<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<script>
  document.getElementById('capture-btn').addEventListener('click', function () {
    const captureBtn = document.getElementById('capture-btn');
    const page_tab = document.getElementById('page_tab');
    const target = document.body;
    $(".wrap").css("padding-top", "50px");
    // 캡처 전에 버튼 숨기기
    captureBtn.style.display = 'none';
    page_tab.style.display = 'none';

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
        page_tab.style.display = 'inline-block';
    $(".wrap").css("padding-top", "0px");
      });
    }, 100); // 100ms 딜레이 (렌더링 안정화용)
  });
</script>

</html>
