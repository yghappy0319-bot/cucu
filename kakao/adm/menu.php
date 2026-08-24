<div class="main-menu-content">
  <ul id="main-menu-navigation" data-menu="menu-navigation" class="navigation navigation-main">
<? if($member['mb_id']=="admin"){ ?>
    <li class=" nav-item">
      <a href="/">
        <i class="icon-folder2"></i>
        <span data-i18n="nav.member.main" class="menu-title">관리메뉴</span>
        <!-- <span class="tag tag tag-primary tag-pill float-xs-right mr-2">2</span> -->
      </a>
      <ul class="menu-content">
        <li <? if (strpos($uri, '/adm/member.htm') !== false) echo 'class="active"'; ?> ><a href="/adm/member.htm" data-i18n="nav.member.main" class="menu-item">회원 관리</a></li>
        <li <? if (strpos($uri, '/adm/notice/') !== false) echo 'class="active"'; ?> ><a href="/adm/notice/list.htm" class="menu-item">공지사항 관리</a></li>
        <li <? if (strpos($uri, '/adm/sms_send') !== false) echo 'class="active"'; ?> ><a href="/adm/sms_send.htm" class="menu-item">전체문자 보내기</a></li>
      </ul>
    </li>
<? } ?>

    <li class=" nav-item">
  
      <a href="/">
        <i class="icon-banknote"></i>
        <span class="menu-title">럭키뱅크</span>
      </a>
      <ul class="menu-content">
        <li>
          <a href="/" class="menu-item"><span data-i18n="nav.dash.main">사이트 관리</span></a>
          <ul class="menu-content">
            <li <? if (strpos($uri, '/adm/sitelist.htm') !== false) echo 'class="active"'; ?> >
              <a href="/adm/sitelist.htm" data-i18n="nav.site.main" class="menu-item">웹사이트 리스트</a>
            </li>
            <li <? if (strpos($uri, '/adm/tax_mng.htm') !== false) echo 'class="active"'; ?> >
              <a href="/adm/tax_mng.htm" data-i18n="nav.site.main" class="menu-item">세금 기능 관리</a>
            </li>
            <li <? if (strpos($uri, '/adm/pay_mng.htm') !== false) echo 'class="active"'; ?> >
              <a href="/adm/pay_mng.htm" data-i18n="nav.site.main" class="menu-item">결제 폼 관리</a>
            </li>
          </ul>
        </li>
        <li>
          <a href="/" class="menu-item"><span data-i18n="nav.dash.main">입금/신청내역</span></a>
          <ul class="menu-content">
            <li <? if (strpos($uri, '/adm/list.htm') !== false) echo 'class="active"'; ?> ><a href="/adm/list.htm" data-i18n="nav.dash.main" class="menu-item">입금내역 기준</a></li>
            <li <? if (strpos($uri, '/adm/list2.htm') !== false) echo 'class="active"'; ?> ><a href="/adm/list2.htm" data-i18n="nav.dash.main" class="menu-item">신청서 기준</a></li>
          </ul>
        </li>


        <li <? if (strpos($uri, '/adm/service2.html') !== false) echo 'class="active"'; ?> >
          <a href="/adm/service2.html" data-i18n="nav.pay.main1" class="menu-item">이용료 결제</a>
        </li>

        <li <? if (strpos($uri, '/adm/pay_form_sample.htm') !== false) echo 'class="active"'; ?> >
          <a href="/adm/pay_form_sample.htm" data-i18n="nav.pay.main" class="menu-item">결제 폼 샘플</a>
        </li>
      </ul>
    </li>

    <li class=" nav-item">
      <a href="/">
        <i class="icon-monitor3"></i>
        <span class="menu-title">럭키패널</span>
      </a>
      <ul class="menu-content">
        <li <? if (strpos($uri, '/adm/panel/about.htm') !== false) echo 'class="active"'; ?>>
          <a href="/adm/panel/about.htm" class="menu-item">럭키패널 소개</a>
        </li>
        <li <? if (strpos($uri, '/adm/panel/list.html') !== false) echo 'class="active"'; ?>>
          <a href="/adm/panel/list.html" class="menu-item">패널 목록</a>
        </li>
      </ul>
    </li>

    <li class="nav-item<? if (strpos($uri, '/adm/cache_charge.htm') !== false) echo ' active'; ?>">
      <a href="/adm/cache_charge.htm">
        <i class="icon-credit-card"></i>
        <span class="menu-title">캐시 충전</span>
      </a>
    </li>

    <li class="nav-item<? if (strpos($uri, '/adm/pay.html') !== false) echo ' active'; ?>">
      <a href="/adm/pay.html">
        <i class="icon-clipboard2"></i>
        <span class="menu-title">결제내역</span>
      </a>
    </li>

    <li class="nav-item<? if (strpos($uri, '/adm/notice.htm') !== false) echo ' active'; ?>">
      <a href="/adm/notice.htm">
        <i class="icon-news"></i>
        <span class="menu-title">공지사항</span>
      </a>
    </li>

    <li class="nav-item<? if (strpos($uri, '/adm/inquiry.htm') !== false) echo ' active'; ?>">
      <a href="/adm/inquiry.htm">
        <i class="icon-mail6"></i>
        <span class="menu-title">문의하기</span>
      </a>
    </li>


    
<!-- 
<? if($member['mb_id']=="admin"){ ?>
    <li class=" nav-item">
      <a href="#">
        <i class="icon-stack-2"></i>
        <span data-i18n="nav.page_layouts.main" class="menu-title">Page layouts</span>
      </a>
      <ul class="menu-content">
        <li><a href="/html/ltr/layout-1-column.html" data-i18n="nav.page_layouts.1_column" class="menu-item">1 column</a>
        </li>
        <li><a href="/html/ltr/layout-2-columns.html" data-i18n="nav.page_layouts.2_columns" class="menu-item">2 columns</a>
        </li>
        <li><a href="/html/ltr/layout-boxed.html" data-i18n="nav.page_layouts.boxed_layout" class="menu-item">Boxed layout</a>
        </li>
        <li><a href="/html/ltr/layout-static.html" data-i18n="nav.page_layouts.static_layout" class="menu-item">Static layout</a>
        </li>
        <li class="navigation-divider"></li>
        <li><a href="/html/ltr/layout-light.html" data-i18n="nav.page_layouts.light_layout" class="menu-item">Light layout</a>
        </li>
        <li><a href="/html/ltr/layout-dark.html" data-i18n="nav.page_layouts.dark_layout" class="menu-item">Dark layout</a>
        </li>
        <li><a href="/html/ltr/layout-semi-dark.html" data-i18n="nav.page_layouts.semi_dark_layout" class="menu-item">Semi dark layout</a>
        </li>
      </ul>
    </li>
    <li class=" nav-item"><a href="#"><i class="icon-briefcase4"></i><span data-i18n="nav.project.main" class="menu-title">Pages</span></a>
      <ul class="menu-content">
        <li><a href="/html/ltr/invoice-template.html" data-i18n="nav.invoice.invoice_template" class="menu-item">Invoice Template</a>
        </li>
        <li><a href="/html/ltr/gallery-grid.html" data-i18n="nav.gallery_pages.gallery_grid" class="menu-item">Gallery Grid</a>
        </li>
        <li><a href="/html/ltr/search-page.html" data-i18n="nav.search_pages.search_page" class="menu-item">Search Page</a>
        </li>
        <li><a href="/html/ltr/search-website.html" data-i18n="nav.search_pages.search_website" class="menu-item">Search Website</a>
        </li>
        <li><a href="/html/ltr/login-simple.html" data-i18n="nav.login_register_pages.login_simple" class="menu-item">Login Simple</a>
        </li>
        <li><a href="/html/ltr/register-simple.html" data-i18n="nav.login_register_pages.register_simple" class="menu-item">Register Simple</a>
        </li>
        <li><a href="/html/ltr/unlock-user.html" data-i18n="nav.login_register_pages.unlock_user" class="menu-item">Unlock User</a>
        </li>
        <li><a href="/html/ltr/recover-password.html" data-i18n="nav.login_register_pages.recover_password" class="menu-item">Recover Password</a>
        </li>
        <li><a href="#" data-i18n="nav.error_pages.main" class="menu-item">Error</a>
          <ul class="menu-content">
            <li><a href="/html/ltr/error-400.html" data-i18n="nav.error_pages.error_400" class="menu-item">Error 400</a>
            </li>
            <li><a href="/html/ltr/error-401.html" data-i18n="nav.error_pages.error_401" class="menu-item">Error 401</a>
            </li>
            <li><a href="/html/ltr/error-403.html" data-i18n="nav.error_pages.error_403" class="menu-item">Error 403</a>
            </li>
            <li><a href="/html/ltr/error-404.html" data-i18n="nav.error_pages.error_404" class="menu-item">Error 404</a>
            </li>
            <li><a href="/html/ltr/error-500.html" data-i18n="nav.error_pages.error_500" class="menu-item">Error 500</a>
            </li>
          </ul>
        </li>
        <li><a href="/html/ltr/coming-soon-flat.html" data-i18n="nav.other_pages.coming_soon.coming_soon_flat" class="menu-item">Coming Soon</a>
        </li>
        <li><a href="/html/ltr/under-maintenance.html" data-i18n="nav.other_pages.under_maintenance" class="menu-item">Maintenance</a>
        </li>
      </ul>
    </li>

    <li class=" nav-item"><a href="#"><i class="icon-ios-albums-outline"></i><span data-i18n="nav.cards.main" class="menu-title">Cards</span></a>
      <ul class="menu-content">
        <li><a href="/html/ltr/card-bootstrap.html" data-i18n="nav.cards.card_bootstrap" class="menu-item">Bootstrap Cards</a>
        </li>
        <li><a href="/html/ltr/card-actions.html" data-i18n="nav.cards.card_actions" class="menu-item">Card Action</a>
        </li>
      </ul>
    </li>
    <li class=" nav-item"><a href="#"><i class="icon-whatshot"></i><span data-i18n="nav.advance_cards.main" class="menu-title">Advance Cards</span></a>
      <ul class="menu-content">
        <li><a href="/html/ltr/card-statistics.html" data-i18n="nav.cards.card_statistics" class="menu-item">Statistics</a>
        </li>
        <li><a href="/html/ltr/card-charts.html" data-i18n="nav.cards.card_charts" class="menu-item">Charts</a>
        </li>
      </ul>
    </li>

    <li class=" nav-item"><a href="#"><i class="icon-grid2"></i><span data-i18n="nav.components.main" class="menu-title">Components</span></a>
      <ul class="menu-content">
        <li><a href="/html/ltr/component-alerts.html" data-i18n="nav.components.component_alerts" class="menu-item">Alerts</a>
        </li>
        <li><a href="/html/ltr/component-buttons-basic.html" data-i18n="nav.components.components_buttons.component_buttons_basic" class="menu-item">Basic Buttons</a>
        </li>
        <li><a href="/html/ltr/component-carousel.html" data-i18n="nav.components.component_carousel" class="menu-item">Carousel</a>
        </li>
        <li><a href="/html/ltr/component-collapse.html" data-i18n="nav.components.component_collapse" class="menu-item">Collapse</a>
        </li>
        <li><a href="/html/ltr/component-dropdowns.html" data-i18n="nav.components.component_dropdowns" class="menu-item">Dropdowns</a>
        </li>
        <li><a href="/html/ltr/component-list-group.html" data-i18n="nav.components.component_list_group" class="menu-item">List Group</a>
        </li>
        <li><a href="/html/ltr/component-modals.html" data-i18n="nav.components.component_modals" class="menu-item">Modals</a>
        </li>
        <li><a href="/html/ltr/component-pagination.html" data-i18n="nav.components.component_pagination" class="menu-item">Pagination</a>
        </li>
        <li><a href="/html/ltr/component-navs-component.html" data-i18n="nav.components.component_navs_component" class="menu-item">Navs Component</a>
        </li>
        <li><a href="/html/ltr/component-tabs-component.html" data-i18n="nav.components.component_tabs_component" class="menu-item">Tabs Component</a>
        </li>
        <li><a href="/html/ltr/component-pills-component.html" data-i18n="nav.components.component_pills_component" class="menu-item">Pills Component</a>
        </li>
        <li><a href="/html/ltr/component-tooltips.html" data-i18n="nav.components.component_tooltips" class="menu-item">Tooltips</a>
        </li>
        <li><a href="/html/ltr/component-popovers.html" data-i18n="nav.components.component_popovers" class="menu-item">Popovers</a>
        </li>
        <li><a href="/html/ltr/component-tags.html" data-i18n="nav.components.component_tags" class="menu-item">Tags</a>
        </li>
        <li><a href="/html/ltr/component-pill-tags.html" class="menu-item">Pill Tags</a>
        </li>
        <li><a href="/html/ltr/component-progress.html" data-i18n="nav.components.component_progress" class="menu-item">Progress</a>
        </li>
        <li><a href="/html/ltr/component-media-objects.html" data-i18n="nav.components.component_media_objects" class="menu-item">Media Objects</a>
        </li>
      </ul>
    </li>
    <li class=" nav-item"><a href="#"><i class="icon-eye6"></i><span data-i18n="nav.icons.main" class="menu-title">Icons</span></a>
      <ul class="menu-content">
        <li><a href="/html/ltr/icons-feather.html" data-i18n="nav.icons.icons_feather" class="menu-item">Feather</a>
        </li>
        <li><a href="/html/ltr/icons-ionicons.html" data-i18n="nav.icons.icons_ionicons" class="menu-item">Ionicons</a>
        </li>
        <li><a href="/html/ltr/icons-fps-line.html" data-i18n="nav.icons.icons_fps_line" class="menu-item">FPS Line Icons</a>
        </li>
        <li><a href="/html/ltr/icons-ico-moon.html" data-i18n="nav.icons.icons_ico_moon" class="menu-item">Ico Moon</a>
        </li>
        <li><a href="/html/ltr/icons-font-awesome.html" data-i18n="nav.icons.icons_font_awesome" class="menu-item">Font Awesome</a>
        </li>
        <li><a href="/html/ltr/icons-meteocons.html" data-i18n="nav.icons.icons_meteocons" class="menu-item">Meteocons</a>
        </li>
        <li><a href="/html/ltr/icons-evil.html" data-i18n="nav.icons.icons_evil" class="menu-item">Evil Icons</a>
        </li>
        <li><a href="/html/ltr/icons-linecons.html" data-i18n="nav.icons.icons_linecons" class="menu-item">Linecons</a>
        </li>
      </ul>
    </li>

    <li class=" nav-item"><a href="/html/ltr/form-layout-basic.html"><i class="icon-paper"></i><span data-i18n="nav.form_layouts.form_layout_basic" class="menu-title">Basic Forms</span></a>
    </li>
    <li class=" nav-item"><a href="/html/ltr/table-basic.html"><i class="icon-table2"></i><span data-i18n="nav.bootstrap_tables.table_basic" class="menu-title">Basic Tables</span></a>
    </li>
<? } ?> -->

  </ul>
</div>
