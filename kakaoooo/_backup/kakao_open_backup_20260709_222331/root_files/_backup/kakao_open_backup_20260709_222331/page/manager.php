<?
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>공통질문(프로필) 조회</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

<body style="display:unset; padding:0px;" >

    <div class="container">

        <form id="profileFrm" method="post" >
          <input type="hidden" name="a" value="1" />
          <input type="hidden" name="code" value="<?=$code?>" />

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
              <input type="checkbox" <?=$profile['oneroom']==1 ? "checked":"" ?>  onclick="go_update('oneroom','<?=$profile['idx']?>')"  />일방
              <input type="checkbox" <?=$profile['couple']==1 ? "checked":"" ?> onclick="go_update('couple','<?=$profile['idx']?>')" />공커
              <?=date("Y-m-d",strtotime($profile['regdate']))?>
              <a href="javascript:go_delete('<?=$profile['idx']?>');">삭제</a>
            </div>
            <div>
              <textarea name="content[]" class="member<?=$profile['idx']?>" style="width: 100%; height: 200px; margin-top: 5px;" ><?= $profile['content']?></textarea>
            </div>

          </div>
        <? } ?>
        </form>

    </div>

    <div>
      <div class="search-container">
      <div style="position: fixed; bottom: 0; width: 100%; padding: 10px; background-color: black; " >
        <button type="button" onclick="go_all_update();" style="    width: 100%;" >저장하기</button>
      </div>
      </div>
    </div>

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

  function go_update(obj1,obj2,obj3){

    $.ajax({
       type : "POST",
       url: "../_status_update.php",
       async: false,
       data: {
         obj1 : obj1,
         obj2 : obj2
       },
       success: function(result) {
          var r = result.trim();
          location.reload();
       },
       error:function(e) {
          alert(e.responseText);
       }
    });
  }

  function go_delete(obj){
    if(confirm("닉네임을 삭제하시겠습니까? 보유아이템 전부소멸됨!")){
      $.ajax({
         type : "POST",
         url: "../_nick_del.php",
         async: false,
         data: {
           midx : obj
         },
         success: function(result) {
            var r = result.trim();
            if(r==1){
              location.reload();
            }
            console.log(r);
         },
         error:function(e) {
            alert(e.responseText);
         }
      });
    }
  }

</script>

</html>
