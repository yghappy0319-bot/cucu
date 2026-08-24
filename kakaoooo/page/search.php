<?
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
include_once $_SERVER['DOCUMENT_ROOT']."/_chk.php";



?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>공통질문(프로필) 조회</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      font-family: 'Arial', sans-serif;
      background-color: #f9f9f9;
      margin: 0;
      padding: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .container {
      width: 100%;
      max-width: 500px;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }

    .search-container {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .search-container input[type="text"] {
      padding: 12px 14px;
      font-size: 16px;
      border: 1px solid #ccc;
      border-radius: 8px;
      width: 100%;
    }

    .search-container button {
      padding: 12px;
      font-size: 16px;
      background-color: #4CAF50;
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.2s;
    }

    .search-container .first_btn {
      padding: 12px;
      font-size: 16px;
      background-color: black;
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.2s;
    }


    .search-container button:hover {
      background-color: #45a049;
    }

    .ranking-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }

    .ranking-table th,
    .ranking-table td {
      padding: 12px 14px;
      text-align: left;
      border-bottom: 1px solid #eee;
    }

    .ranking-table th {
      background-color: #f0f0f0;
    }

    /* 반응형 테이블: 작은 화면에서 글자 작게 */
    @media (max-width: 480px) {
      .search-container input[type="text"],
      .search-container button {
        font-size: 14px;
        padding: 10px;
      }

      .ranking-table th,
      .ranking-table td {
        padding: 10px;
        font-size: 14px;
      }
    }
  </style>
</head>

<body style="<?=$a==1 ? "display:unset; padding:0px; ":"" ?>" >

    <div class="container">

      <? if($a==1){ ?>

        <form id="profileFrm" method="post" >
          <input type="hidden" name="a" value="1" />

          <div class="search-container" style="margin-bottom: 20px;" >
            <input type="text" name="up_search" id="search" placeholder="닉네임 2글자" maxlength="2">
            <button type="button" onclick="go_search2();" >검색</button>
          </div>

          <?
            if($up_search){
              $where = " and name = '{$up_search}' ";
            }

            $profile_sql = "select * from tb_member where 1=1 {$where}  order by idx desc ";
            $profile_result = db_query($profile_sql);
            for($b=0;$profile=db_fetch($profile_result);$b++){
          ?>
          <div style="width: 100%;" >
            <div>
              <input type="hidden" name="nickname[]" value="<?=$profile['name']?>" />
              <?=$profile['name']?> <a href="javascript:go_hot('<?=$profile['idx']?>','<?=$profile['name']?>')">[요주의인물 등록하기]</a>
            </div>
            <div>
              <textarea name="content[]" class="member<?=$profile['idx']?>" style="width: 100%; height: 200px; margin-top: 5px;" ><?= $profile['content']?></textarea>
            </div>

          </div>
        <? } ?>
        </form>

      <? }else{ ?>

      <form id="searchFrm" >
        <p>공통질문(프로필) 조회</p>
        <div class="search-container">
          <input type="hidden" name="code" value="<?=$code?>" />
        <input type="text" name="search" id="search" placeholder="닉네임 2글자" maxlength="2">

      <div>
    <?php
    $count = 0;
    foreach ($_COOKIE as $key => $value) {
        if (strpos($key, 'n') === 0) {
          ?>
            <a href="javascript:go_nick_search('<?=htmlspecialchars($value)?>')"><?=htmlspecialchars($value)?></a>
            <?
            $count++;

            if ($count >= 3) break; // 최대 3개까지만 출력
        }
    }
    ?>
    </div>

          <button type="button" onclick="go_search();" >검색</button>
          <div style="    text-align: center;" >
            <a href="/page/danger.php" target="_blank" style="color: red;" >요주의인물</a>
          </div>
        </div>
      </form>

      <script>
      function go_search(){
        var search = $("#search").val();
        if(search==""){
          alert("닉네임을 입력해주세요.");
          return false;
        }
        $("#searchFrm").submit();
      }
      </script>

      <? if($search){ ?>
        <div style="">
          <table class="ranking-table">
            <thead>
              <tr>
                <th>공통질문</th>
              </tr>
            </thead>
            <tbody>
            <?
            $nick = db_select("select * from tb_member where name = '{$search}' ");
            if($nick['idx']>0){

              // 새로 넣을 값
              $new_cookie_key = "n" . $nick['idx'];
              $new_cookie_value = $search;
              $twoWeeks = time() + (60 * 60 * 24 * 14); // 2주

              // 1. 기존 쿠키 중 "n"으로 시작하는 것만 모음
              $n_cookies = [];
              foreach ($_COOKIE as $key => $value) {
                  if (strpos($key, 'n') === 0) {
                      $n_cookies[$key] = $value;
                  }
              }

              // 2. 3개 이상이면 오래된 것부터 삭제
              if (count($n_cookies) >= 3 && !isset($_COOKIE[$new_cookie_key])) {
                  // 정렬 없이 그냥 먼저 나온 순서대로 삭제 (PHP는 입력 순서를 유지함)
                  $to_delete = array_slice(array_keys($n_cookies), 0, count($n_cookies) - 2);

                  foreach ($to_delete as $del_key) {
                      // 쿠키 삭제는 만료시간을 과거로 설정
                      setcookie($del_key, '', time() - 3600, "/");
                  }
              }

              // 3. 새 쿠키 저장
              setcookie($new_cookie_key, $new_cookie_value, $twoWeeks, "/");


              $ranksql = "update tb_member set rank = rank + 1 where idx = {$nick['idx']} ";
              //echo $ranksql;
              db_query($ranksql);
            }

            ?>
              <tr>
                <td><?= nl2br($nick['content']) ?></td>
              </tr>

          </tbody>

        </table>
        </div>

        <div class="search-container">
          <button type="button" class="first_btn" onclick="location.href='/'" >처음으로</button>
        </div>
    <? } ?>
  <? } ?>
    </div>

    <? if($a==1){ ?>
    <div>
      <div class="search-container">
      <div style="position: fixed; bottom: 0; width: 100%; padding: 10px; background-color: black; " >
        <button type="button" onclick="go_all_update();" style="    width: 100%;" >저장하기</button>
      </div>
      </div>
    </div>
    <? } ?>

</body>

<script>
  function go_nick_search(obj){
    $("#search").val(obj);
    $("#searchFrm").submit();
  }

  function go_all_update(){
    // id="profileFrm"
    var params = jQuery("#profileFrm").serialize();
    $.ajax({
      type : "POST",
      url: "../_set_profile.php",
      async: false,
      data: params,
    success: function(result) {
    var r = result.trim();
      if(r==1){
        alert("저장완료");
        location.reload();
      }
      },
      error:function(e) {
        alert(e.responseText);
      }
    });
  }

  function go_search2(){
    $("#profileFrm").submit();
  }

  function go_hot(idx, obj){
    var content = $(".member"+idx).val();
    if(confirm("요주의 인물에 등록하시겠습니까?")){
      $.ajax({
         type : "POST",
         url: "../_danger.php",
         async: false,
         data: {
           name : obj,
           content : content
         },
         success: function(result) {
            var r = result.trim();
            if(r==1){
              alert("등록 되었습니다.");
              location.reload();
            }
         },
         error:function(e) {
            alert(e.responseText);
         }
      });
    }

  }
</script>

</html>
