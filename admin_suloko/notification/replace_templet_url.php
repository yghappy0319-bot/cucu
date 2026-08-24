<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/inc/meta.html";
  require_once $_SERVER['DOCUMENT_ROOT']."/inc/header.html";

  include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly.php";

  $old = 'https://www.sulokolink.com';
  $new = 'https://surokolink.com';
  $old2 = 'http://www.sulokolink.com';

  function replace_templet_url($db, $table, $col, $old, $new) {
    $exists = (int)$db->get_data_one("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'");
    if ($exists < 1) {
      return array('ok' => false, 'msg' => 'table not found');
    }

    $has_col = (int)$db->get_data_one("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = '{$col}'");
    if ($has_col < 1) {
      return array('ok' => false, 'msg' => 'column not found');
    }

    $old_sql = addslashes($old);
    $new_sql = addslashes($new);
    $cnt = (int)$db->get_data_one("SELECT COUNT(*) FROM {$table} WHERE {$col} LIKE '%{$old_sql}%'");
    $nos = array();
    if ($cnt > 0) {
      $list = $db->get_list("SELECT TEMPLET_NO FROM {$table} WHERE {$col} LIKE '%{$old_sql}%'");
      if (!empty($list['TEMPLET_NO'])) {
        $nos = $list['TEMPLET_NO'];
      }
      $db->query("UPDATE {$table} SET {$col} = REPLACE({$col}, '{$old_sql}', '{$new_sql}') WHERE {$col} LIKE '%{$old_sql}%'");
    }
    $left = (int)$db->get_data_one("SELECT COUNT(*) FROM {$table} WHERE {$col} LIKE '%{$old_sql}%'");
    return array('ok' => true, 'cnt' => $cnt, 'left' => $left, 'nos' => $nos);
  }

  $jobs = array(
    array('SMSTEMPLET', 'SUBJECT'),
    array('SMSTEMPLET', 'CONTENT'),
    array('SMSTEMPLET', 'GUBUN'),
    array('EMAILTEMPLET', 'SUBJECT'),
    array('EMAILTEMPLET', 'CONTENT'),
    array('EMAILTEMPLET', 'GUBUN'),
  );

  $lines = array();
  $total = 0;
  foreach ($jobs as $job) {
    foreach (array($old, $old2) as $from) {
      $res = replace_templet_url($db, $job[0], $job[1], $from, $new);
      if (empty($res['ok']) || (int)$res['cnt'] < 1) {
        continue;
      }
      $total += (int)$res['cnt'];
      $nos = implode(', ', $res['nos']);
      $lines[] = "{$job[0]}.{$job[1]} : {$res['cnt']}건 (NO: {$nos})";
    }
  }
  $left = (int)$db->get_data_one("SELECT COUNT(*) FROM SMSTEMPLET WHERE CONCAT(IFNULL(SUBJECT,''), IFNULL(CONTENT,''), IFNULL(GUBUN,'')) LIKE '%www.sulokolink.com%'");
?>

<div class="app-content">
  <div class="side-app">
    <div class="page-header">
      <div>
        <h1 class="page-title">템플릿 도메인 일괄변경</h1>
      </div>
    </div>
    <div class="row row-sm">
      <div class="col-lg-12">
        <div class="card custom-card">
          <div class="card-body">
            <p><strong><?=htmlspecialchars($old)?></strong> → <strong><?=htmlspecialchars($new)?></strong></p>
            <?php if ($total < 1) { ?>
              <p>변경할 항목이 없습니다. 이미 바뀌었거나, 해당 주소가 템플릿에 없습니다.</p>
            <?php } else { ?>
              <p><?=$total?>건 수정했습니다.</p>
              <ul>
                <?php foreach ($lines as $line) { ?>
                  <li><?=htmlspecialchars($line)?></li>
                <?php } ?>
              </ul>
            <?php } ?>
            <p>SMSTEMPLET에 남은 www.sulokolink.com : <?=$left?>건</p>
            <a href="<?=admin_url('./templat.html')?>" class="btn btn-primary">알림템플릿 목록으로</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT']."/inc/footer.html"; ?>
