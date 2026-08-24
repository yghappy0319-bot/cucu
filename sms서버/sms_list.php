<?php
include("/home/sms/public_html/lib/function.php"); //db
include("/home/sms/public_html/lib/luckybank_function.php");

$selected_site = $_GET['site'] ?? '';

$site_sql = "select idx, site, bank, acount from site where website = 1 order by site asc";
$site_result = lb_db_query($site_sql);
$sites = [];
while ($row = lb_db_fetch($site_result)) {
    $sites[] = $row;
}
?>

<form action="bankdata2.php" method="post">
    <textarea name="msg" rows="3" cols="80" placeholder="[Web발신] [KB]06/14 13:49 538801**478 장성환 입금 6,600"></textarea><br />
    <input type="hidden" name="tel" value="test">
    <button type="submit">테스트 문자 전송</button>
</form>

<div>
<select id="site_select" name="site" onchange="location.href='?site='+encodeURIComponent(this.value)">
    <option value="">사이트 선택</option>
    <?php foreach ($sites as $s) { ?>
    <option value="<?= htmlspecialchars($s['site'], ENT_QUOTES, 'UTF-8') ?>" <?= $selected_site === $s['site'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($s['site'], ENT_QUOTES, 'UTF-8') ?>
    </option>
    <?php } ?>
</select>
</div>

<?php
$acount = '';
if ($selected_site) {
    foreach ($sites as $s) {
        if ($s['site'] === $selected_site) {
            $acount = $s['acount'];
            break;
        }
    }
}

$sql = "select * from bankdata ";
if ($acount) {
    $escaped_acount = mysqli_real_escape_string($conn, $acount);
    $sql .= "where sms like '%{$escaped_acount}%' ";
}
$sql .= "order by regdate desc limit 20";

$result = db_query($sql);
foreach($result as $data){
  echo $data['sms']."<br />";
}
?>

<script>
setInterval(function() {
    var site = document.getElementById('site_select').value;
    location.href = site ? '?site=' + encodeURIComponent(site) : location.pathname;
}, 10000);
</script>
