<style>
.pg_wrap{ display: flex; justify-content: center; align-items: center; margin: 40px 0 0 0;}
.pg_wrap .pg{ display: flex; justify-content: center; align-items: center; grid-gap: 0 5px;}
.pg_wrap .pg .pg_page{ display: flex; justify-content: center; align-items: center; width: 35px; height: 35px; border-radius: 50%; border: 1px solid #e5e5e5; background: #fafafa; font-size: 14px; color: #333;}
.pg_wrap .pg .pg_current{ border-color: #317f70; background: #537db2; color: #FFF;}
.pg_wrap .pg .pg_first{ background-color: #FEDC00; border-radius: 50%; width: 35px; height: 35px; display: inline-block; line-height: 2rem;font-size: 16px; margin: 0px 3px;}
.pg_wrap .pg .pg_start{ background-color: #FEDC00; border-radius: 50%; width: 35px; height: 35px; display: inline-block; line-height: 2rem;font-size: 16px; margin: 0px 3px;}
.pg_wrap .pg .pg_end{ display: flex; justify-content: center; align-items: center; width: 35px; height: 35px; border-radius: 50%; border: 1px solid #e5e5e5; font-size: 14px;}

.pg_wrap .left_btn{ display: flex; justify-content: center; align-items: center; width: 35px; height: 35px; border-radius: 50%; border: 1px solid #e5e5e5; cursor: pointer; transition: all .2s;}
.pg_wrap .left_btn img{ display: block; height: 20px;}
.pg_wrap .left_btn:hover{ background: #f5f5f5;}
.pg_wrap .right_btn{ display: flex; justify-content: center; align-items: center; width: 35px; height: 35px; border-radius: 50%; border: 1px solid #e5e5e5; cursor: pointer; transition: all .2s;}
.pg_wrap .right_btn img{ display: block; height: 20px;}
.pg_wrap .right_btn:hover{ background: #f5f5f5;}
@media(max-width: 769px){
    .pg_wrap{ margin: 30px 0 0 0;}
    .pg_wrap .pg .pg_page{ width: 30px; height: 30px; font-size: 12px;}
    .pg_wrap .pg .pg_end{ width: 30px; height: 30px; font-size: 12px;}
    .pg_wrap .left_btn{ width: 30px; height: 30px;}
    .pg_wrap .left_btn img{ height: 15px;}
    .pg_wrap .right_btn{ width: 30px; height: 30px;}
    .pg_wrap .right_btn img{ height: 15px;}
}
</style>

<div class="pg_wrap">
      <span class="pg">
        <?php if($page > 1){ ?>
          <? if($page==$total_page){?>
            <a href="javascript:go_page(1)" class="left_btn" ><img src="/assets/icon/arrow_left.png" /></a>
          <? } ?>
          <a href="javascript:go_page(<?php echo ($page-1); ?>)" class="left_btn" ><img src="/assets/icon/left.png" /></a>
        <? } ?>

        <?php
        /* pager : 페이지 번호 출력 */
        for($print_page = $s_pageNum; $print_page <= $e_pageNum; $print_page++){
        ?>
        <a href="javascript:go_page(<?php echo $print_page; ?>)" class="pg_page <?=$print_page==$page? 'pg_current':'' ?>"><?php echo $print_page; ?></a>
        <?php };?>

        <? if($page == "1" and $total_page > 0){?>
          <a href="javascript:go_page(<?php echo ($page+1); ?>)" class="right_btn"  ><img src="/assets/icon/right.png" /></a>
        <? } ?>

        <?php if($page > 1){ ?>
          <? if($page!=$total_page){ ?>
            <a href="javascript:go_page(<?php echo ($page+1); ?>)" class="right_btn"  ><img src="/assets/icon/right.png" /></a>
          <a href="javascript:go_page(<?php echo $total_page; ?>)" class="right_btn" ><img src="/assets/icon/arrow_right.png" /></a>
          <? } ?>
        <?php } ?>
        </span>
</div>
