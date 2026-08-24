<style>
.pg_wrap .pg .pg_page{ background-color: #F4F4F4;     text-align: center;width: 28px; height: 28px; display: inline-block; line-height: 2rem;font-size: 16px; margin: 0px 3px;}
.pg_wrap .pg .pg_current{ background-color: #194A9C;     text-align: center;width: 28px; height: 28px; display: inline-block; line-height: 2rem;font-size: 16px;color: #fff; margin: 0px 3px;}
.pg_wrap .pg .pg_first {  border-radius: 50%; width: 28px; height: 28px; display: inline-block; line-height: 2rem;font-size: 16px; margin: 0px 3px;}
.pg_wrap .pg .pg_start{  border-radius: 50%; width: 28px; height: 28px; display: inline-block; line-height: 2rem;font-size: 16px; margin: 0px 3px;}

.pg_wrap .pg .pg_end{  border-radius: 50%; width: 28px; height: 28px; display: inline-block; line-height: 2rem;font-size: 16px; margin: 0px 3px;}
.pg_wrap .pg .pg_end{  border-radius: 50%; width: 28px; height: 28px; display: inline-block; line-height: 2rem;font-size: 16px; margin: 0px 3px;}
</style>

<div class="pg_wrap">
  <span class="pg">
    <?php
    /* paging : 이전 페이지 */
    if($page <= 1){
    ?>
      <a href="javascript:go_page(1)" class="pg_first"> << </a>
    <?php } else{ ?>
      <a href="javascript:go_page(<?php echo ($page-1); ?>)" class=" pg_start"> < </a>
    </li>

    <?php };?>

    <?php
    /* pager : 페이지 번호 출력 */
    for($print_page = $s_pageNum; $print_page <= $e_pageNum; $print_page++){
    ?>
    <a href="javascript:go_page(<?php echo $print_page; ?>)" class="pg_page <?=$print_page==$page? 'pg_current':'' ?>"><?php echo $print_page; ?></a>
    <?php };?>

    <?php
    /* paging : 다음 페이지 */
    if($page >= $total_page){
    ?>
      <a href="javascript:go_page(<?php echo $total_page; ?>)" class=" pg_end"> > </a>
    <?php } else{ ?>
      <a href="javascript:go_page(<?php echo ($page+1); ?>)" class=" pg_end"> >> </a>
    <?php };?>
    </span>
</div>
