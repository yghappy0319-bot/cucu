<?
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

    }
    .nickname { margin-bottom: 10px; display: inline-block; }
    .item_list a {color:#333;    text-decoration: none;}
  </style>
</head>
<body>

<h2>아이템 보유목록</h2>
<div>
  <a href="/">색표</a>
  <a href="/item.php">아이템</a>
</div>

<div>
  <form >
    <input type="text" name="search" /><button onclick="go_search()" >조회</button>
    <a href="/item.php?gender=1">남</a>
    <a href="/item.php?gender=2">여</a>
  </form>
</div>

<div>
  <form id="itemFrm" >
    <select name="midx">
      <?
      $sql = "select * from tb_member where 1=1 order by gender asc, sort asc ";
        $result = db_query($sql);
        foreach($result as $member){
      ?>
      <option value="<?=$member['idx']?>" <?=$member['idx']==$midx ? "selected":"" ?> ><?=$member['gender']==1 ? "남":"여"?> <?=$member['name']?></option>
      <? } ?>
    </select>

    <select name="item">
      <?
        $result = db_query("select * from tb_item where 1=1 order by sort asc ");
        foreach($result as $bitem){
      ?>
      <option value="<?=$bitem['itemname']?>" <?=$bitem['itemname']==$item ? "selected":"" ?> ><?=$bitem['itemname']?></option>
    <? } ?>
    </select>
    <button type="button" onclick="go_item_set()" >등록</button>
  </form>
</div>

<div style="overflow-x:auto; max-width:100%;">
  <table>
    <thead>
      <tr>
        <th>닉네임</th>
        <?
          $result = db_query("select * from tb_item where 1=1 order by sort asc ");
          foreach($result as $items){
        ?>
        <th>
          <div><?=$items['sname']?></div>

        </th>
      <? } ?>
        <th>관리</th>
      </tr>
    </thead>
    <tbody>
      <?
      if($search){
        $where .= " and name = '{$search}' ";
      }

      if($gender){
        $where .= " and gender = {$gender} ";
      }

      $sql = "select * from tb_member where 1=1 {$where} order by gender asc, sort asc ";
        $result = db_query($sql);
        foreach($result as $data){
      ?>
      <tr>
        <td >
          <a href="javascript:go_copy(<?=$data['idx']?>)" class="nickname" >
            <?=$data['gender']==1 ? "남":"여"?> <?=$data['name']?>
          </a>
          <div id="copyTarget<?=$data['idx']?>" style="display:none;" >
            <?
              $itemctnsql1 = "select * from tb_member_item where midx = {$data['idx']} and status = 0  ";
              $itemlist = db_query($itemctnsql1);
              foreach($itemlist as $ilist){
                ?>
                  <div class="item_list itemlist<?=$ilist['idx']?>" >
                    <a href="javascript:go_del(<?=$ilist['idx']?>)"><?=$ilist['itemname']?></a>
                  </div>
                <?
              }
            ?>
          </div>

        </td>
        <?
          $result = db_query("select * from tb_item where 1=1 order by sort asc ");
          foreach($result as $item){
        ?>
        <td >
          <?
            $itemctnsql = "select count(*) as cnt from tb_member_item where midx = {$data['idx']} and itemname = '{$item['itemname']}' ";

            $itemcnt = db_select($itemctnsql);
            echo ($itemcnt['cnt'] > 0 ? $itemcnt['cnt']:"");
          ?>
        </td>
      <? } ?>
        <td>
          <a href="javascript:go_delete('<?=$data['idx']?>')">삭제</a>
        </td>

      </tr>
      <? } ?>

    </tbody>
  </table>
</div>


<script>
  function go_copy(obj){
    var copy = $("#copyTarget" + obj);
    const container = document.getElementById("copyTarget" + obj);
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
    var params = jQuery("#itemFrm").serialize();
    $.ajax({
      type : "POST",
      url: "_set_item.php",
      async: false,
      data: params,
    success: function(result) {
    var r = result.trim();
    console.log(r);
      if(r==1){
        //location.reload();
        $("#itemFrm").submit();
        return false;
      }
      },
      error:function(e) {
        alert(e.responseText);
      }
    });
  }

  function go_del(obj){
    if(confirm("삭제하시겠습니까?")){
      $.ajax({
         type : "POST",
         url: "_item_del.php",
         async: false,
         data: {
           idx : obj
         },
         success: function(result) {
            var r = result.trim();
            $(".itemlist"+obj).remove();
            console.log(r);
         },
         error:function(e) {
            alert(e.responseText);
         }
      });
    }
  }

  function go_delete(obj){
    if(confirm("닉네임을 삭제하시겠습니까? 보유아이템 전부소멸됨!")){
      $.ajax({
         type : "POST",
         url: "_nick_del.php",
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

</body>
</html>
