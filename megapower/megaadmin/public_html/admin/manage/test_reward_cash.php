<?php
include "../../admin/header.html";

$page_num = 10;
$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$list_num = 20;

$message = '';
$message_type = '';
$grant_result = null;
$default_amount = 5000;

$member_count = db_select("select count(*) as cnt from lr_member where cut_off = 0");
$total_members = (int)$member_count['cnt'];

if($_POST['action'] == 'grant_all'){
  set_time_limit(0);

  $amount = (int)$_POST['amount'];
  if($amount <= 0){
    $message = '지급 금액은 1 이상 입력해 주세요.';
    $message_type = 'error';
  }else if($total_members <= 0){
    $message = '지급 대상 회원이 없습니다.';
    $message_type = 'error';
  }else{
    $batch_memo = '보상캐시';
    $grant_result = 보상캐시_전체지급($amount, $batch_memo);

    if($grant_result['success'] > 0){
      $message = '전체 회원 '.number_format($grant_result['success']).'명에게 '.number_format($amount).'캐시 지급 완료 (총 '.number_format($grant_result['total_amount']).'캐시)';
      if($grant_result['fail'] > 0){
        $message .= ', 실패 '.number_format($grant_result['fail']).'명';
      }
      $message_type = 'success';
    }else{
      $message = '지급 처리에 실패했습니다.';
      $message_type = 'error';
    }
  }
}

$countSql = "
  select count(*) as totalRecords
  from lr_deposit_log a
  where a.deposit_type = 'reward'
";
include_once "../pager_result.php";

$sql = "
  select a.idx, a.midx, a.point, a.memo, a.regdate, b.id, b.name, b.phone
  from lr_deposit_log a
  left join lr_member b on a.midx = b.idx
  where a.deposit_type = 'reward'
  order by a.regdate desc, a.idx desc
  limit {$start}, {$list_num}
";
$history_list = db_query($sql);

$reward_summary = db_select("
  select count(*) as cnt, sum(point) as total_point
  from lr_deposit_log
  where deposit_type = 'reward'
");
?>
<style>
  .reward-box { max-width: 1100px; margin: 30px auto; }
  .reward-box .card-block {
    padding: 24px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #fff;
    margin-bottom: 24px;
  }
  .reward-box h2, .reward-box h3 { margin-top: 0; }
  .reward-box .alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 16px;
  }
  .reward-box .alert.success {
    background: #e8f5e9;
    color: #2e7d32;
    border: 1px solid #a5d6a7;
  }
  .reward-box .alert.error {
    background: #ffebee;
    color: #c62828;
    border: 1px solid #ef9a9a;
  }
  .reward-box .form-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin: 16px 0;
  }
  .reward-box .form-row label { font-weight: 600; }
  .reward-box .form-row input[type="number"] {
    width: 180px;
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
  }
  .reward-box .btn-grant {
    padding: 10px 24px;
    background: #537db2;
    color: #fff;
    border: 0;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
  }
  .reward-box .note {
    margin-top: 16px;
    padding: 12px;
    background: #f5f5f5;
    border-radius: 6px;
    font-size: 13px;
    color: #666;
    line-height: 1.7;
  }
  .reward-box .summary {
    margin: 12px 0;
    line-height: 1.8;
  }
  .reward-box table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
  }
  .reward-box table th,
  .reward-box table td {
    border: 1px solid #ddd;
    padding: 10px 8px;
    text-align: center;
  }
  .reward-box table th { background: #f7f7f7; }
  .reward-box .pager {
    margin-top: 16px;
    text-align: center;
  }
  .reward-box .pager a {
    display: inline-block;
    margin: 0 3px;
    padding: 4px 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #333;
  }
  .reward-box .pager a.on {
    background: #537db2;
    color: #fff;
    border-color: #537db2;
  }
</style>

<div class="reward-box">
  <div class="card-block">
    <h2>보상캐시 일괄 지급</h2>

    <? if($message){ ?>
      <div class="alert <?=$message_type?>"><?=$message?></div>
    <? } ?>

    <div class="summary">
      지급 대상: 탈퇴/차단 제외 전체 회원 <strong><?=number_format($total_members)?>명</strong><br>
      누적 보상캐시 지급 건수: <strong><?=number_format($reward_summary['cnt'])?>건</strong>
      / 총 지급액: <strong><?=number_format($reward_summary['total_point'])?>캐시</strong>
    </div>

    <form method="post" id="grantForm">
      <input type="hidden" name="action" value="grant_all">
      <div class="form-row">
        <label for="amount">보상 금액</label>
        <input type="number" name="amount" id="amount" min="1" step="1" value="<?=isset($_POST['amount']) ? (int)$_POST['amount'] : $default_amount?>" required>
        <span>캐시</span>
        <button type="submit" class="btn-grant">전체 회원 지급</button>
      </div>
    </form>

    <div class="note">
      * 전체 활성 회원(cut_off = 0)에게 동일 금액이 지급됩니다.<br>
      * 회원 마이페이지 &gt; 나의 이용내역에 <strong>보상캐시</strong>로 기록됩니다.<br>
      * 누적충전금액(등급)에는 영향을 주지 않습니다.<br>
      * 회원 수가 많을 경우 처리에 시간이 걸릴 수 있습니다.
    </div>
  </div>

  <div class="card-block">
    <h3>보상캐시 지급 내역</h3>

    <table>
      <thead>
        <tr>
          <th>번호</th>
          <th>midx</th>
          <th>아이디</th>
          <th>이름</th>
          <th>연락처</th>
          <th>지급금액</th>
          <th>메모</th>
          <th>지급일시</th>
        </tr>
      </thead>
      <tbody>
        <? if(mysqli_num_rows($history_list) == 0){ ?>
          <tr>
            <td colspan="8">지급 내역이 없습니다.</td>
          </tr>
        <? }else{
          $no = $num - $start;
          while($row = db_fetch($history_list)){ ?>
          <tr>
            <td><?=$no--?></td>
            <td><?=$row['midx']?></td>
            <td><?=$row['id']?></td>
            <td><?=$row['name']?></td>
            <td><?=$row['phone']?></td>
            <td><?=number_format($row['point'])?>캐시</td>
            <td><?=$row['memo']?></td>
            <td><?=$row['regdate']?></td>
          </tr>
        <? } } ?>
      </tbody>
    </table>

    <? if($total_page > 1){ ?>
      <div class="pager">
        <? if($page > 1){ ?>
          <a href="?page=<?=$page-1?>">이전</a>
        <? } ?>

        <? for($i = $s_pageNum; $i <= $e_pageNum; $i++){ ?>
          <a href="?page=<?=$i?>" class="<?=$page==$i ? 'on' : ''?>"><?=$i?></a>
        <? } ?>

        <? if($page < $total_page){ ?>
          <a href="?page=<?=$page+1?>">다음</a>
        <? } ?>
      </div>
    <? } ?>
  </div>
</div>

<script>
  document.getElementById('grantForm').addEventListener('submit', function(e){
    var amount = parseInt(document.getElementById('amount').value, 10) || 0;
    var members = <?= (int)$total_members ?>;
    var total = amount * members;

    if(amount <= 0){
      alert('지급 금액은 1 이상 입력해 주세요.');
      e.preventDefault();
      return false;
    }

    if(!confirm('전체 ' + members.toLocaleString() + '명에게 ' + amount.toLocaleString() + '캐시씩 지급합니다.\n총 ' + total.toLocaleString() + '캐시가 지급됩니다.\n진행하시겠습니까?')){
      e.preventDefault();
      return false;
    }
  });
</script>

<?php include "../../admin/footer.html"; ?>
