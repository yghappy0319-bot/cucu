<?
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
include_once $_SERVER['DOCUMENT_ROOT']."/api/item_bag.inc.php";

$공질주소 = "zzazz";
if (function_exists('item_bag_ensure_schema')) {
  item_bag_ensure_schema();
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
    .item_list a {color:#333; text-decoration: none; background-color: gainsboro; padding: 4px; display: inline-block; margin-bottom: 5px;}
    h2 { margin: 0px; font-size: 16px;}
    .atarget{text-align: left; padding-left: 5px;    height: 150px;
    overflow: scroll;}
    .atarget .regdate{}
    .user_label{ background-color: gainsboro; padding-top: 2px; border-radius: 4px; display: inline-block; margin: 3px;}
    .user_label input {  }
    .item_label{ background: lightpink; border-radius: 4px;     margin-bottom: 4px;
    display: inline-block;     width: 20%;}
    input[type='radio']  {margin: 0px;}
    .wrap {width: 500px; margin: 0px auto;}

    @media screen and (max-width: 500px) {
      .wrap {width: 100%;}
    }
  </style>
</head>
<body>

<div class="wrap" >

  <div class="item_search" >
    <div style="display: flex; justify-content: space-between;margin-bottom: 10px;" >
      <h2>아이템 보유목록</h2>
      <div style="" >
        <i class="fas fa-download" id="capture-btn" ></i>
      </div>
    </div>
    <div style="text-align: center; margin-bottom: 10px;" >
      <a href="/page/color.php">색표</a> | <a href="/page/item.php">아이템</a> |
      <!-- <a href="/page/bag.php">보유이력</a> | -->
      <a href="/page/search.php" target="_blank" >공통질문</a> |
      <a href="/page/danger.php" target="_blank" >요주인물</a> |
      <a href="/page/manager.php" target="_blank" >공질관리</a>
      <? if($a==1){?> |
      <a href="/page/item_randum.php">랜덤</a>
      <? } ?>
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


  <? if($a==1){?>
  <div class="item_search" >
    <form id="itemFrm" >
      <input type="hidden" name="a" value="<?=$a?>" />

      <div>
        <div>
          <?
          $sql = "select * from tb_member where gender = 1 order by regdate asc ";
            $result = db_query($sql);
            foreach($result as $member){
          ?>
          <label for="user_label_gender<?=$member['idx']?>" class="user_label">
          <input type="checkbox" name="midx[]" id="user_label_gender<?=$member['idx']?>" value="<?=$member['idx']?>" /><?=$member['name']?>
          </label>
          <? } ?>
        </div>

        <div style="border-top: 1px solid #ccc; border-bottom: 1px solid #ccc; margin: 5px 0px; padding: 5px 0px;" >
          <div>
            <label for="cnts1"><input type="radio" value="1" name="cnts" class="itemcnts" id="cnts1" checked />1개</label>
            <label for="cnts10"><input type="radio" value="10" name="cnts" class="itemcnts" id="cnts10" />10개</label>
          </div>
          <?
          $sql = "select * from tb_member where gender = 2 order by regdate asc ";
            $result = db_query($sql);
            foreach($result as $member){
          ?>
          <label for="user_label<?=$member['idx']?>" class="user_label">
          <input type="checkbox" name="midx[]" id="user_label<?=$member['idx']?>" value="<?=$member['idx']?>"  /><?=$member['name']?>
          </label>
          <? } ?>
        </div>
      </div>

      <div>
        <?
          $result = db_query("select * from tb_item where 1=1 and status = 0 order by sort asc ");
          foreach($result as $bitem){
        ?>
        <label for="user_label_item<?=$bitem['idx']?>" class=" item_label">
        <input type="radio" name="item" class="chkitem" id="user_label_item<?=$bitem['idx']?>" value="<?=$bitem['sname']?>" <?=$bitem['itemname']==$item ? "checked":"" ?> />
          <?=$bitem['sname']?>
        </label>
      <? } ?>
      </div>

      <button type="button" onclick="go_item_set()" >지급하기</button>

    </form>
  </div>
  <? } ?>

  <div style="overflow-x:auto; max-width:100%;">
    <table>
  <?php
  /************************
   * 1. 아이템 목록 (1회)
   ************************/
  $itemsRs = db_query("
      select *
      from tb_item
      where status = 0
      order by sort asc
  ");
  $items = [];
  if ($itemsRs) {
      while ($itRow = db_fetch($itemsRs)) {
          $items[] = $itRow;
      }
  }
  $아이템카운트 = count($items);
  /************************
   * 2. 회원 검색 조건
   ************************/
  $where = "";
  if($search){
      $where .= " and name = '{$search}' ";
  }
  if($gender){
      $where .= " and gender = {$gender} ";
  }

  /************************
   * 3. 회원 목록 (1회)
   ************************/
  $members = db_query("
      select *
      from tb_member
      where 1=1 {$where}
      order by gender asc, regdate asc
  ");

  /************************
   * 4~5. 회원별 × 아이템별 개수 / 합계 (tb_member_item_bag)
   ************************/
  $itemCountMap = [];
  $itemTotalMap = [];
  $bagCols = [];
  foreach ($items as $itTmp) {
      $sn = trim((string)($itTmp['sname'] ?? ''));
      if ($sn !== '' && function_exists('item_bag_col_exists') && item_bag_col_exists($sn)) {
          $bagCols[] = $sn;
          $itemTotalMap[$sn] = 0;
      }
  }
  if ($bagCols !== []) {
      $sel = ['midx'];
      foreach ($bagCols as $sn) {
          $sel[] = '`' . str_replace('`', '``', $sn) . '`';
      }
      $rows = db_query('SELECT ' . implode(', ', $sel) . ' FROM tb_member_item_bag');
      if ($rows) {
          while ($r = db_fetch($rows)) {
              $midxBag = (int)($r['midx'] ?? 0);
              if ($midxBag < 1) {
                  continue;
              }
              foreach ($bagCols as $sn) {
                  $cnt = (int)($r[$sn] ?? 0);
                  if ($cnt > 0) {
                      $itemCountMap[$midxBag][$sn] = $cnt;
                      $itemTotalMap[$sn] = ($itemTotalMap[$sn] ?? 0) + $cnt;
                  }
              }
          }
      }
  }
  ?>

  <!-- ================= THEAD ================= -->
  <thead>
  <tr>
      <th>닉네임</th>
      <?php foreach($items as $item){ ?>
          <th><div><?=$item['sname']?></div></th>
      <?php } ?>
  </tr>
  </thead>

  <!-- ================= TBODY ================= -->
  <tbody>
  <?php foreach($members as $data){ ?>
  <tr>
      <td>
          <?php if($a==1){ ?>
              <a href="javascript:go_copy(<?=$data['idx']?>)" class="nickname">
                  <?=$data['name']?>
              </a>
          <?php }else{ ?>
              <a href="javascript:;" class="nickname">
                  <?=$data['gender']==1 ? '남':'여'?> <?=$data['name']?>
              </a>
          <?php } ?>
      </td>

      <?php foreach($items as $item){
          $cnt = $itemCountMap[$data['idx']][$item['sname']] ?? '';
      ?>
          <td><?=$cnt?></td>
      <?php } ?>
  </tr>

  <!-- ===== 상세 아이템 리스트 ===== -->
  <tr class="item_list_box" id="copyTarget<?=$data['idx']?>" style="display:none;">
      <td colspan="<?= $아이템카운트+1 ?>">
          <div class="manage<?=$data['idx']?>" style="display:none;">
              <?php if($a==1){ ?>
                  <a href="/search/?search=<?=$data['name']?>">사용</a>
                  <a href="/page/bag.php?search=<?=$data['name']?>">보유</a>
                  <a href="javascript:go_delete('<?=$data['idx']?>')">삭제</a>
              <?php } ?>

              <div class="atarget">
              <?php
                  $midxDetail = (int)$data['idx'];
                  $list = function_exists('item_bag_list') ? item_bag_list($midxDetail) : [];
                  foreach ($list as $ilist) {
                      $iname = (string)($ilist['name'] ?? '');
                      $icnt = (int)($ilist['count'] ?? 0);
                      if ($iname === '' || $icnt < 1) {
                          continue;
                      }
                      $inameJs = htmlspecialchars($iname, ENT_QUOTES, 'UTF-8');
                      $rowKey = $midxDetail . '_' . md5($iname);
              ?>
                  <span class="item_list itemlist<?=$rowKey?>">
                      <a href="javascript:go_del(<?=$midxDetail?>, '<?=$inameJs?>')">
                          <?= htmlspecialchars((string)($ilist['label'] ?? $iname), ENT_QUOTES, 'UTF-8') ?><?=$icnt > 1 ? ' ×'.$icnt : ''?>
                      </a>
                  </span>
              <?php } ?>
              </div>
          </div>
      </td>
  </tr>
  <?php } ?>

  <!-- ================= 합계 ROW ================= -->
  <tr>
      <td>-</td>
      <?php foreach($items as $item){ ?>
          <td><?=$itemTotalMap[$item['sname']] ?? 0?></td>
      <?php } ?>
  </tr>
  </tbody>
  </table>

  </div>
</div>


<script>
  function go_copy(obj){
    var manage = $(".manage"+obj);
    var copy = $("#copyTarget" + obj);
    $(".item_list_box").css("display", "none");

    const container = document.getElementById("copyTarget" + obj);
    copy.show();
    manage.toggle();
    // 모든 <a> 태그의 텍스트를 추출
    const linkTexts = Array.from(container.querySelectorAll('a'))
                           .map(a => a.innerText.trim())
                           .join('\n');

    // 클립보드에 복사
    navigator.clipboard.writeText(linkTexts).then(() => {
      //alert("복사되었습니다:\n\n" + linkTexts);
    }).catch(err => {
      alert("복사 실패: " + err);
    });
  }

  function go_item_set(){
    const isItemCunt = document.querySelector('.itemcnts:checked') !== null;
    const isChecked = document.querySelector('.chkitem:checked') !== null;
    if (!isItemCunt) {
      alert("지급할 수량을 선택해주세요.");
      return false;
    }
    if (!isChecked) {
      alert("지급할 아이템을 선택해주세요.");
      return false;
    }


    var params = jQuery("#itemFrm").serialize();
    $.ajax({
      type : "POST",
      url: "../_set_item.php",
      async: false,
      data: params,
    success: function(result) {
    var r = result.trim();
    console.log(r);
      if(r==1){
        //location.reload();
        alert('지급완료');
        $("#itemFrm").submit();
        return false;
      }
      },
      error:function(e) {
        alert(e.responseText);
      }
    });
  }

  function go_del(midx, itemname){
    if(confirm("1개 차감할까요?")){
      $.ajax({
         type : "POST",
         url: "../_item_del.php",
         async: false,
         data: {
           midx : midx,
           item : itemname
         },
         success: function(result) {
            var r = result.trim();
            if(r==1){
              location.reload();
            }else{
              alert(r || '차감 실패');
            }
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
