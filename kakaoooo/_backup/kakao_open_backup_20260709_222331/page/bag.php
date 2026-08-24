<?
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
$nick = db_select("select * from tb_member where name = '{$search}' ");

$주소 = "ACDSDFF";

?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>보유 아이템 조회</title>
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
      background-color: #0f0ff6;
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.2s;
    }

    .search-container .first_btn {
      padding: 12px;
      font-size: 16px;
      background-color: blue;
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.2s;
    }


    .search-container button:hover {
      background-color: #0f0ff6;
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

<body>

  <div class="container">
    <form id="searchFrm" >
      <p>보유 아이템 조회</p>
      <div class="search-container">
        <input type="text" name="search" id="search" placeholder="닉네임 2글자" maxlength="2">
        <button type="button" onclick="go_search();" >검색</button>
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
      <div style="height: 600px; overflow: scroll;">
        <div style="    margin-top: 10px;" >
          <?
          $sql1 = "select count(*) as cnt from tb_member_item where midx = {$nick['idx']} and status = 0 order by usedate desc ";
          //echo $sql;
          $total = db_select($sql1);
          ?>
          총 : <?=$total['cnt']?>개
        </div>

        <div style="    display: flex;justify-content: space-between;" >
          <?
            $sql = "select * from tb_item where status = 0 order by sort asc ";
            $result = db_query($sql);
            for($a=0;$it=db_fetch($result);$a++){
              $sqls = "select count(*) as cnt from tb_member_item where midx = {$nick['idx']} and status = 0 and itemname = '{$it['sname']}' ";
              $cnt = db_select($sqls);
              if($cnt['cnt']>0){
          ?>
            <div>
              <a href="?search=<?=$search?>&item=<?=$it['sname']?>"><?=$it['sname']?>(<?= $cnt['cnt'] ?>)</a>
            </div>
          <? } ?>
        <? } ?>
        </div>

        <table class="ranking-table">
          <thead>
            <tr>
              <th>번호</th>
              <th>닉네임</th>
              <th>아이템명</th>
              <th>지급일</th>
            </tr>
          </thead>
          <tbody>
          <?
          if($nick['idx']>0){

            if($item){
              $item_where = " and itemname = '{$item}' ";
            }

            $sql = "select * from tb_member_item where midx = {$nick['idx']} {$item_where} and status = 0 order by regdate desc ";
            //echo $sql;
            $result = db_query($sql);
            $cnt = mysqli_num_rows($result);
          }
            $aa = 1;
            for($a=0;$row=db_fetch($result);$a++){
          ?>
            <tr>
              <td>
                <?=$aa?>
              </td>
              <td><?=$nick['name']?></td>
              <td>
                <? if($row['itemname']=="프변"){?>
                  <a href="javascript:go_use('<?= $row['idx'] ?>')"><?= $row['itemname'] ?></a>
                <? }else{ ?>
                  <?= $row['itemname'] ?>
                <? } ?>
              </td>
              <td><?= date('y-m-d H:i', strtotime($row['regdate'])) ?></td>
            </tr>
        <?
        $aa++;
       } ?>

            <? if($cnt==0){ ?>
              <tr>
                <td>보유 내역이 없습니다.</td>
              </tr>
            <? } ?>

          </tbody>
        </table>
      </div>

      <div class="search-container">
        <button type="button" class="first_btn" onclick="location.href='/<?=$주소?>'" >처음으로</button>
      </div>

<? } ?>

  </div>

</body>

<script>
  function go_use(obj){
    if(confirm("사용처리 하시겠습니까? 본인 외 사용처리 절대금지!")){
      $.ajax({
         type : "POST",
         url: "../_item_use.php",
         async: false,
         data: {
           idx : obj
         },
         success: function(result) {
            var r = result.trim();
            if(r==1){
              alert("사용처리 완료!");
              location.reload();
            }else{
              alert("에러");
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
