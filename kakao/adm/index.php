<?php
if(isset($_COOKIE['redirect'])) {
    header("Location: ".$_COOKIE['redirect']);
    exit();
}
include_once "../adm/head.php";

$휴대폰인증완료 = 회원_휴대폰인증여부($member);
?>
<script>
var mb_hp = "<?= htmlspecialchars((string)($member['mb_hp'] ?? ''), ENT_QUOTES, 'UTF-8') ?>";
var hp_cert = <?= $휴대폰인증완료 ? '1' : '0' ?>;
if (!mb_hp) {
  alert('럭키뱅크의 원활한 서비스 이용을 위하여 회원님의 연락처를 입력해주세요.');
  top.location.href = "/adm/mypage.htm";
} else if (hp_cert != 1) {
  alert('휴대폰 번호 인증을 완료해야 이용 가능합니다.\n마이페이지에서 인증을 진행해주세요.');
  top.location.href = "/adm/mypage.htm";
}
</script>
    <?php include_once "../adm/nav.php";?>
    <div data-scroll-to-active="true" class="main-menu menu-fixed menu-dark menu-accordion menu-shadow">
      <div class="main-menu-header">
        <input type="text" placeholder="Search" class="menu-search form-control round"/>
      </div>
      <?php include_once "../adm/menu.php";?>
    </div>


    <div class="app-content content container-fluid">
      <div class="content-wrapper">


        <div class="content-header row">
        </div>
        <div class="content-body">
<?
include_once $_SERVER['DOCUMENT_ROOT']."/lib/sms_server_function.php";

  $현재달 = date("m");
  $현재_년월일 = date("Y-m-d");
  $현재_년월 = date("Y-m");

  $오늘신청건 = db_select("select count(*) as cnt from bank_request where member_no = {$member['mb_no']} and date_format(regdate, '%Y-%m-%d') = '{$현재_년월일}'  ");
  $현재달총신청건 = db_select("select count(*) as cnt from bank_request where member_no = {$member['mb_no']} and date_format(regdate, '%Y-%m') = '{$현재_년월}'  ");

  $오늘입금건 = sms_db_select("select count(*) as cnt from bank_data_detail where midx = {$member['mb_no']} and date_format(regdate, '%Y-%m-%d') = '{$현재_년월일}' ");
  $현재달입금건 = sms_db_select("select count(*) as cnt from bank_data_detail where midx = {$member['mb_no']} and date_format(regdate, '%Y-%m') = '{$현재_년월}' ");

  $is_admin = ($member['mb_id'] === 'admin');
  if ($is_admin) {
    $등록사이트수 = db_select("select count(*) as cnt from site ");
    $사이트_목록 = db_query("select s.idx, s.site, s.status, s.bank, s.member_no, m.mb_id, m.mb_10 from site s left join member m on s.member_no = m.mb_no order by s.status desc, s.regdate desc ");
  } else {
    $등록사이트수 = db_select("select count(*) as cnt from site where member_no = {$member['mb_no']} ");
    $사이트_목록 = db_query("select idx, site, status, bank, member_no from site where member_no = {$member['mb_no']} order by status desc, regdate desc ");
  }
  $종료날짜 = 서비스종료기간($member['mb_10']);
  $처리가능건 = round($member['mb_point'] / 100);
  $서비스만료 = (!$member['mb_10'] || $종료날짜 == 9);

  $panel_active_where = ' and COALESCE(status, 1) != 2';
  $panel_active_where .= ' and (COALESCE(db_delete, 0) = 0 OR (COALESCE(db_delete, 0) = 1 AND delete_time IS NOT NULL AND delete_time > NOW()))';
  $panel_active_where .= ' and member_no = ' . (int)$member['mb_no'];
  $패널_활성수 = db_select("select count(*) as cnt from tb_panel where 1=1 {$panel_active_where} ");
  $패널_목록 = db_query("select idx, panel_name_ko, panel_name_en, enddate, status, delete_time from tb_panel where 1=1 {$panel_active_where} order by COALESCE(enddate, '9999-12-31') asc, idx desc limit 5 ");
?>
<style>
.dashboard-usage-row {
  margin-top: 1.5rem;
}
.dashboard-usage-card {
  height: 100%;
  display: flex;
  flex-direction: column;
  border: 1px solid #e4e7ed;
  box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
  margin-bottom: 1rem;
}
.dashboard-usage-card .card-header {
  padding: 1rem 1.25rem;
  background: linear-gradient(180deg, #fafbfc 0%, #f5f7fa 100%);
  border-bottom: 1px solid #e8ecf1;
}
.dashboard-usage-card .card-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 600;
  color: #263238;
  display: flex;
  align-items: center;
  gap: 0.65rem;
}
.dashboard-usage-card .card-title i {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.1rem;
  height: 2.1rem;
  border-radius: 0.45rem;
  font-size: 1rem;
  flex-shrink: 0;
}
.dashboard-usage-card.bank .card-title i {
  background: #00bcd4;
  color: #fff;
}
.dashboard-usage-card.panel .card-title i {
  background: #7e57c2;
  color: #fff;
}
.dashboard-usage-card .card-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 1.25rem;
}
.dashboard-usage-summary-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.85rem;
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
}
.dashboard-usage-summary-head .label {
  font-size: 0.85rem;
  margin: 0;
}
.dashboard-usage-summary-head .count {
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1;
}
.dashboard-usage-summary-head.bank {
  background: #e0f7fa;
  border: 1px solid #b2ebf2;
}
.dashboard-usage-summary-head.bank .label {
  color: #006064;
}
.dashboard-usage-summary-head.bank .count {
  color: #00838f;
}
.dashboard-usage-summary-head.panel {
  background: #f3eefb;
  border: 1px solid #e6dcf5;
}
.dashboard-usage-summary-head.panel .label {
  color: #6a4c93;
}
.dashboard-usage-summary-head.panel .count {
  color: #5e35b1;
}
.dashboard-usage-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 0.85rem;
  margin: -0.35rem 0 0.85rem;
  font-size: 0.82rem;
  color: #607d8b;
}
.dashboard-usage-meta .tag {
  margin: 0;
}
.dashboard-usage-table-wrap {
  flex: 1;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  border: 1px solid #e8edf3;
  border-radius: 0.5rem;
  background: #fff;
}
.dashboard-usage-table {
  width: 100%;
  min-width: 360px;
  margin-bottom: 0;
}
.dashboard-usage-table thead th {
  background: #f5f7fa;
  color: #546e7a;
  font-size: 0.78rem;
  font-weight: 600;
  text-transform: none;
  letter-spacing: 0;
  border-bottom: 1px solid #e8edf3;
  padding: 0.75rem 1rem;
  white-space: nowrap;
}
.dashboard-usage-table tbody td {
  padding: 0.8rem 1rem;
  vertical-align: middle;
  border-top: 1px solid #f0f3f7;
  color: #37474f;
  font-size: 0.9rem;
}
.dashboard-usage-table tbody td a {
  color: #00838f;
  word-break: break-all;
}
.dashboard-usage-table tbody td a:hover {
  color: #006064;
}
.dashboard-usage-table tbody tr:nth-child(even) {
  background: #fafbfd;
}
.dashboard-usage-table tbody tr:hover {
  background: #f3f8ff;
}
.dashboard-usage-empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 8rem;
  padding: 1.5rem 1rem;
  text-align: center;
  color: #90a4ae;
  background: #f8fafc;
  border: 1px dashed #d8dee6;
  border-radius: 0.5rem;
}
.dashboard-usage-links {
  margin-top: auto;
  padding-top: 1rem;
  border-top: 1px solid #eef2f6;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.dashboard-usage-links a {
  display: inline-flex;
  align-items: center;
  padding: 0.45rem 0.9rem;
  border: 1px solid #d5dce3;
  border-radius: 0.35rem;
  background: #fff;
  color: #455a64;
  font-size: 0.84rem;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.15s ease;
}
.dashboard-usage-links a:hover {
  color: #00838f;
  border-color: #80deea;
  background: #f0fcfd;
  text-decoration: none;
}
.dashboard-usage-card.panel .dashboard-usage-links a:hover {
  color: #5e35b1;
  border-color: #d1c4e9;
  background: #f8f5ff;
}
</style>
          <div class="row">
              <div class="col-xl-3 col-lg-6 col-xs-12">
                  <div class="card">
                      <div class="card-body">
                          <div class="media">
                              <div class="p-2 text-xs-center bg-cyan bg-darken-2 media-left media-middle">
                                  <i class="icon-news font-large-2 white"></i>
                              </div>
                              <div class="p-2 bg-cyan white media-body">
                                  <h5>오늘 신청건</h5>
                                  <h5 class="text-bold-400"><?=$오늘신청건['cnt']?>건</h5>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
              <div class="col-xl-3 col-lg-6 col-xs-12">
                  <div class="card">
                      <div class="card-body">
                          <div class="media">
                              <div class="p-2 text-xs-center bg-deep-orange bg-darken-2 media-left media-middle">
                                  <i class="icon-banknote font-large-2 white"></i>
                              </div>
                              <div class="p-2 bg-deep-orange white media-body">
                                  <h5>오늘 입금</h5>
                                  <h5 class="text-bold-400"><?=$오늘입금건['cnt']?>건</h5>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
              <div class="col-xl-3 col-lg-6 col-xs-12">
                  <div class="card">
                      <div class="card-body">
                          <div class="media">
                              <div class="p-2 text-xs-center bg-teal bg-darken-2 media-left media-middle">
                                  <i class="icon-data font-large-2 white"></i>
                              </div>
                              <div class="p-2 bg-teal white media-body">
                                  <h5><?=$현재달?>월 총 신청</h5>
                                  <h5 class="text-bold-400"><?=$현재달총신청건['cnt']?>건</h5>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
              <div class="col-xl-3 col-lg-6 col-xs-12">
                  <div class="card">
                      <div class="card-body">
                          <div class="media">
                              <div class="p-2 text-xs-center bg-pink bg-darken-2 media-left media-middle">
                                  <i class="icon-banknote font-large-2 white"></i>
                              </div>
                              <div class="p-2 bg-pink white media-body">
                                  <h5><?=$현재달?>월 총 입금</h5>
                                  <h5 class="text-bold-400"><?=$현재달입금건['cnt']?>건</h5>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>

          <div class="row match-height dashboard-usage-row">
              <div class="col-xl-6 col-md-12 col-sm-12">
                  <div class="card dashboard-usage-card bank">
                      <div class="card-header">
                          <h4 class="card-title"><i class="icon-banknote"></i> 럭키뱅크 이용현황</h4>
                      </div>
                      <div class="card-body">
                          <div class="dashboard-usage-summary-head bank">
                              <span class="label"><?= $is_admin ? '전체 등록 웹사이트' : '등록 웹사이트' ?></span>
                              <span class="count"><?= (int)$등록사이트수['cnt'] ?>개</span>
                          </div>
                          <? if (!$is_admin) { ?>
                          <div class="dashboard-usage-meta">
                            <? if ($서비스만료) { ?>
                            <span class="tag tag-danger tag-sm">서비스 중지</span>
                            <? } else { ?>
                            <span class="tag tag-success tag-sm">서비스 이용중</span>
                            <? } ?>
                            <span>처리 가능 <?= (int)$처리가능건 ?>건</span>
                            <? if (!$서비스만료) { ?>
                            <span>남은 <?= (int)$종료날짜['days'] ?>일 <?= (int)$종료날짜['hours'] ?>시간</span>
                            <? } ?>
                          </div>
                          <? } ?>

                          <? if ((int)$등록사이트수['cnt'] > 0) { ?>
                          <div class="dashboard-usage-table-wrap">
                              <table class="table dashboard-usage-table">
                                  <thead>
                                      <tr>
                                          <? if ($is_admin) { ?><th>회원</th><? } ?>
                                          <th>웹사이트</th>
                                          <th>상태</th>
                                          <th>서비스 종료일</th>
                                      </tr>
                                  </thead>
                                  <tbody>
                                  <?
                                  $내_서비스종료일 = $member['mb_10'] ? date('Y-m-d', strtotime($member['mb_10'])) : '—';
                                  if ($사이트_목록) {
                                    while ($site_row = db_fetch($사이트_목록)) {
                                      $sitename = explode('//', $site_row['site']);
                                      $site_host = isset($sitename[1]) ? idn_to_utf8($sitename[1]) : $site_row['site'];
                                      $site_st = (int)$site_row['status'];
                                      if ($is_admin) {
                                        $site_end_raw = isset($site_row['mb_10']) ? trim($site_row['mb_10']) : '';
                                        $서비스종료일 = ($site_end_raw !== '') ? date('Y-m-d', strtotime($site_end_raw)) : '—';
                                      } else {
                                        $서비스종료일 = $내_서비스종료일;
                                      }
                                  ?>
                                      <tr>
                                          <? if ($is_admin) { ?>
                                          <td><?= htmlspecialchars(isset($site_row['mb_id']) ? $site_row['mb_id'] : ('#' . (int)$site_row['member_no']), ENT_QUOTES, 'UTF-8') ?></td>
                                          <? } ?>
                                          <td>
                                            <? if ($is_admin) { ?>
                                            <a href="/adm/siteview.htm?idx=<?= (int)$site_row['idx'] ?>"><?= htmlspecialchars($site_host, ENT_QUOTES, 'UTF-8') ?></a>
                                            <? } else { ?>
                                            <a href="https://<?= htmlspecialchars($site_host, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($site_host, ENT_QUOTES, 'UTF-8') ?></a>
                                            <? } ?>
                                          </td>
                                          <td>
                                            <? if ($site_st === 1) { ?>
                                            <span class="tag tag-success tag-sm">정상</span>
                                            <? } else { ?>
                                            <span class="tag tag-default tag-sm">중지</span>
                                            <? } ?>
                                          </td>
                                          <td><?= htmlspecialchars($서비스종료일, ENT_QUOTES, 'UTF-8') ?></td>
                                      </tr>
                                  <? } } ?>
                                  </tbody>
                              </table>
                          </div>
                          <? } else { ?>
                          <div class="dashboard-usage-empty">
                              등록된 웹사이트가 없습니다.
                          </div>
                          <? } ?>

                          <div class="dashboard-usage-links">
                              <a href="/adm/sitelist.htm">웹사이트 리스트</a>
                              <a href="/adm/list.htm">입금내역</a>
                              <a href="/adm/service2.html">이용료 결제</a>
                          </div>
                      </div>
                  </div>
              </div>

              <div class="col-xl-6 col-md-12 col-sm-12">
                  <div class="card dashboard-usage-card panel">
                      <div class="card-header">
                          <h4 class="card-title"><i class="icon-monitor3"></i> 럭키패널 이용현황</h4>
                      </div>
                      <div class="card-body">
                          <div class="dashboard-usage-summary-head panel">
                              <span class="label">현재 운영 중인 패널</span>
                              <span class="count"><?= (int)$패널_활성수['cnt'] ?>개</span>
                          </div>

                          <? if ((int)$패널_활성수['cnt'] > 0) { ?>
                          <div class="dashboard-usage-table-wrap">
                              <table class="table dashboard-usage-table">
                                  <thead>
                                      <tr>
                                          <th>패널명</th>
                                          <th>상태</th>
                                          <th>종료일</th>
                                      </tr>
                                  </thead>
                                  <tbody>
                                  <?
                                  if ($패널_목록) {
                                    while ($panel_row = db_fetch($패널_목록)) {
                                      $panel_end_raw = isset($panel_row['enddate']) ? trim($panel_row['enddate']) : '';
                                      $panel_end_disp = ($panel_end_raw !== '') ? date('Y-m-d', strtotime($panel_end_raw)) : '—';
                                      $panel_end_ts = ($panel_end_raw !== '') ? strtotime($panel_end_raw) : null;
                                      $panel_expired = ($panel_end_ts !== null && $panel_end_ts < time());
                                      $panel_del_raw = isset($panel_row['delete_time']) ? trim($panel_row['delete_time']) : '';
                                      $panel_st = isset($panel_row['status']) ? (int)$panel_row['status'] : 1;
                                      if ($panel_st !== 0) {
                                        $panel_st = 1;
                                      }
                                      $panel_pending_delete = ($panel_del_raw !== '');
                                  ?>
                                      <tr>
                                          <td><?= htmlspecialchars($panel_row['panel_name_ko'], ENT_QUOTES, 'UTF-8') ?></td>
                                          <td>
                                            <? if ($panel_pending_delete) { ?>
                                            <span class="tag tag-warning tag-sm">삭제 예정</span>
                                            <? } elseif ($panel_st === 1 && !$panel_expired) { ?>
                                            <span class="tag tag-success tag-sm">정상</span>
                                            <? } elseif ($panel_expired) { ?>
                                            <span class="tag tag-danger tag-sm">만료</span>
                                            <? } else { ?>
                                            <span class="tag tag-default tag-sm">중지</span>
                                            <? } ?>
                                          </td>
                                          <td><?= htmlspecialchars($panel_end_disp, ENT_QUOTES, 'UTF-8') ?></td>
                                      </tr>
                                  <? } } ?>
                                  </tbody>
                              </table>
                          </div>
                          <? } else { ?>
                          <div class="dashboard-usage-empty">
                              등록된 패널이 없습니다.
                          </div>
                          <? } ?>

                          <div class="dashboard-usage-links">
                              <a href="/adm/panel/list.html">패널 목록</a>
                              <a href="/adm/panel/register.htm">패널 등록</a>
                              <a href="/adm/panel/about.htm">럭키패널 소개</a>
                          </div>
                      </div>
                  </div>
              </div>
          </div>

        </div>
      </div>
    </div>
<style>
#emergencyNotice {
  display: none;
  width: 420px;
  max-width: 92vw;
  background: #fff;
  border-radius: 6px;
  overflow: hidden;
  box-shadow: 0 12px 40px rgba(0,0,0,.25);
}
#emergencyNotice .en-head {
  background: #c62828;
  color: #fff;
  padding: 14px 18px;
  font-size: 18px;
  font-weight: 700;
  text-align: center;
  letter-spacing: -0.02em;
}
#emergencyNotice .en-body {
  padding: 22px 20px 10px;
  color: #333;
  font-size: 14px;
  line-height: 1.7;
  text-align: center;
}
#emergencyNotice .en-body strong {
  color: #c62828;
}
#emergencyNotice .en-foot {
  padding: 12px 20px 20px;
  text-align: center;
}
#emergencyNotice .en-foot .btn {
  min-width: 100px;
}
</style>
<div id="emergencyNotice">
  <div class="en-head">긴급공지</div>
  <div class="en-body">
    <strong>임시 복구완료</strong><br><br>
    럭키뱅크를 사용해주신분들께서는<br>
    아래 카카오 상담 링크를 통하여 연락주시면<br>
    신규로 연결 도와드리겠습니다<br><br>
    불편을 드려 대단히 죄송합니다<br><br>
    <a href="https://open.kakao.com/o/sDxkhDIi" target="_blank" rel="noopener noreferrer">https://open.kakao.com/o/sDxkhDIi</a>
  </div>
  <div class="en-foot">
    <a href="https://open.kakao.com/o/sDxkhDIi" target="_blank" rel="noopener noreferrer" class="btn btn-danger">문의하기</a>
    <button type="button" class="btn btn-secondary" onclick="$('#emergencyNotice').bPopup().close();">닫기</button>
  </div>
</div>
<script>
  function go_form(){
    alert("준비중 입니다.");
    return false;
  }
</script>
<?php include_once "../adm/footer.php";?>
<script>
$(function(){
  $('#emergencyNotice').bPopup({
    modalClose: false,
    opacity: 0.55,
    follow: [false, false]
  });
});
</script>
