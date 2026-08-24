
<nav class="header-navbar navbar navbar-with-menu navbar-fixed-top navbar-semi-dark navbar-shadow">
  <div class="navbar-wrapper">
    <div class="navbar-header">
      <ul class="nav navbar-nav">
        <li class="nav-item mobile-menu hidden-md-up float-xs-left">
          <a class="nav-link nav-menu-main menu-toggle hidden-xs"><i class="icon-menu5 font-large-1"></i></a></li>

        <li class="nav-item">
          <a href="/" class="navbar-brand nav-link">
          <img alt="branding logo" src="../../app-assets/images/logo/white_logo.png"
          data-expand="../../app-assets/images/logo/white_logo.png"
          data-collapse="../../app-assets/images/logo/white_small_logo.png" class="brand-logo"></a>
        </li>
        
        <li class="nav-item hidden-md-up float-xs-right"><a data-toggle="collapse" data-target="#navbar-mobile" class="nav-link open-navbar-container"><i class="icon-ellipsis pe-2x icon-icon-rotate-right-right"></i></a></li>
      </ul>
    </div>

    <div class="navbar-container content container-fluid">
      <div id="navbar-mobile" class="collapse navbar-toggleable-sm">
        <ul class="nav navbar-nav">
          <li class="nav-item hidden-sm-down"><a class="nav-link nav-menu-main menu-toggle hidden-xs"><i class="icon-menu5"></i></a></li>
          <li class="nav-item hidden-sm-down">
            <a href="/adm/cache_charge.htm" class="btn btn-info upgrade-to-pro" title="패널 캐시 충전">
              <i class="icon-wallet font-small-3"></i> 보유 캐시 <?= number_format(isset($member['mb_cash']) ? (int)$member['mb_cash'] : 0) ?>원
            </a>
          </li>
          
          <li class="nav-item hidden-sm-down">
          <?
          $종료날짜 = 서비스종료기간($member['mb_10']);
          if($member['mb_10']){?>
            <a href="/adm/service.htm" class="btn btn-success upgrade-to-pro">
            <?= round($member['mb_point']/100) ?>건 처리가능
            <?
              if ($종료날짜!=9) {
                  $종료 = " / 남은기간 : ".$종료날짜['days']."일 ".($종료날짜['hours']>0 ? $종료날짜['hours']."시간":"0시간 ")." ".($종료날짜['minutes'] > 0 ? $종료날짜['minutes']."분":"")." (".$member['mb_10'].")";
              }
            ?>
            <?=$종료?>
            </a>
          <? } ?>

          <? if ($종료날짜==9) { ?>
          <a href="" class="btn btn-danger upgrade-to-pro">서비스 상태 : 중지 (서비스 기간 만료)</a>
          <? } ?>
            <input type="hidden" id="enddate" value="<?=날짜비교($member['mb_10'])?>" />
          </li>
        </ul>
        <ul class="nav navbar-nav float-xs-right">

          <li class="dropdown dropdown-notification nav-item" style="display:none;">
            <a href="#" data-toggle="dropdown" class="nav-link nav-link-label"><i class="ficon icon-bell4"></i><span class="tag tag-pill tag-default tag-danger tag-default tag-up">5</span></a>
            <ul class="dropdown-menu dropdown-menu-media dropdown-menu-right">
              <li class="dropdown-menu-header">
                <h6 class="dropdown-header m-0"><span class="grey darken-2">Notifications</span><span class="notification-tag tag tag-default tag-danger float-xs-right m-0">5 New</span></h6>
              </li>
              <li class="list-group scrollable-container">
                <!-- <a href="javascript:void(0)" class="list-group-item">
                  <div class="media">
                    <div class="media-left valign-middle"><i class="icon-cart3 icon-bg-circle bg-cyan"></i></div>
                    <div class="media-body">
                      <h6 class="media-heading">You have new order!</h6>
                      <p class="notification-text font-small-3 text-muted">Lorem ipsum dolor sit amet, consectetuer elit.</p><small>
                        <time datetime="2015-06-11T18:29:20+08:00" class="media-meta text-muted">30 minutes ago</time></small>
                    </div>
                  </div></a><a href="javascript:void(0)" class="list-group-item">
                  <div class="media">
                    <div class="media-left valign-middle"><i class="icon-monitor3 icon-bg-circle bg-red bg-darken-1"></i></div>
                    <div class="media-body">
                      <h6 class="media-heading red darken-1">99% Server load</h6>
                      <p class="notification-text font-small-3 text-muted">Aliquam tincidunt mauris eu risus.</p><small>
                        <time datetime="2015-06-11T18:29:20+08:00" class="media-meta text-muted">Five hour ago</time></small>
                    </div>
                  </div></a><a href="javascript:void(0)" class="list-group-item">
                  <div class="media">
                    <div class="media-left valign-middle"><i class="icon-server2 icon-bg-circle bg-yellow bg-darken-3"></i></div>
                    <div class="media-body">
                      <h6 class="media-heading yellow darken-3">Warning notifixation</h6>
                      <p class="notification-text font-small-3 text-muted">Vestibulum auctor dapibus neque.</p><small>
                        <time datetime="2015-06-11T18:29:20+08:00" class="media-meta text-muted">Today</time></small>
                    </div>
                  </div>
                </a> -->

                  <a href="javascript:void(0)" class="list-group-item">
                  <div class="media">
                    <div class="media-left valign-middle"><i class="icon-check2 icon-bg-circle bg-green bg-accent-3"></i></div>
                    <div class="media-body">
                      <h6 class="media-heading">Complete the task</h6><small>
                        <time datetime="2015-06-11T18:29:20+08:00" class="media-meta text-muted">Last week</time></small>
                    </div>
                  </div>
                  </a>

                  <!-- <a href="javascript:void(0)" class="list-group-item">
                  <div class="media">
                    <div class="media-left valign-middle"><i class="icon-bar-graph-2 icon-bg-circle bg-teal"></i></div>
                    <div class="media-body">
                      <h6 class="media-heading">Generate monthly report</h6><small>
                        <time datetime="2015-06-11T18:29:20+08:00" class="media-meta text-muted">Last month</time></small>
                    </div>
                  </div>
                  </a> -->
                </li>
              <li class="dropdown-menu-footer"><a href="javascript:void(0)" class="dropdown-item text-muted text-xs-center">Read all notifications</a></li>
            </ul>
          </li>


          <li class="dropdown dropdown-user nav-item">
            <a href="#" data-toggle="dropdown" class="dropdown-toggle nav-link dropdown-user-link"><span class="avatar avatar-online"><img src="../../app-assets/images/portrait/small/avatar-s-1.png" alt="avatar"><i></i></span>
              <span class="user-name"><?=$member['mb_nick']?></span></a>
            <div class="dropdown-menu dropdown-menu-right">
              <a href="/adm/cache_charge.htm" class="dropdown-item"><i class="icon-wallet"></i> 보유 캐시 <?= number_format(isset($member['mb_cash']) ? (int)$member['mb_cash'] : 0) ?>원</a>
              <a href="mypage.htm" class="dropdown-item"><i class="icon-head"></i> 마이페이지</a>
              <a href="/adm/pay.html" class="dropdown-item"><i class="icon-clipboard2"></i> 결제내역</a>
              <div class="dropdown-divider"></div>
                <a href="/ajax/logout.php" class="dropdown-item"><i class="icon-power3"></i> 로그아웃</a>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</nav>
