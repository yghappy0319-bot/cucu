<div class="col-md-12">
  <div class="mb-3">
    <nav aria-label="Page navigation">
      <ul class="pagination">

        <li class="page-item">
        <?php
        if($page <= 1){
        ?>
          <a class="page-link" href="javascript:go_page('1');" aria-label="Previous">
            <span aria-hidden="true">« Prev</span>
            <span class="sr-only">Previous</span>
          </a>

          <?php } else{ ?>
          <a class="page-link" href="javascript:go_page('<?php echo ($page-1); ?>');" aria-label="Previous">
            <span aria-hidden="true">« Prev</span>
            <span class="sr-only">Previous</span>
          </a>
          <?php };?>
        </li>

        <?php
        /* pager : 페이지 번호 출력 */
        for($print_page = $s_pageNum; $print_page <= $e_pageNum; $print_page++){
        ?>
        <li class="page-item <?=$page==$print_page ? 'active':'' ?>">
          <a class="page-link" href="javascript:go_page('<?php echo $print_page; ?>')"><?php echo $print_page; ?></a>
        </li>
        <?php };?>

        <li class="page-item">
          <?php
          /* paging : 다음 페이지 */
          if($page >= $total_page){
          ?>
          <a class="page-link" href="javascript:go_page('<?php echo $total_page; ?>');" aria-label="Next">
            <span aria-hidden="true">Next »</span>
            <span class="sr-only">Next</span>
          </a>
          <?php } else{ ?>
            <a class="page-link" href="javascript:go_page('<?php echo ($page+1); ?>');" aria-label="Next">
              <span aria-hidden="true">Next »</span>
              <span class="sr-only">Next</span>
            </a>
          <?php };?>
        </li>

      </ul>
    </nav>
  </div>
</div>


<script>
  function go_page(pg){
    $("#page").val(pg);
    $("#searchFrm").submit();
  }
</script>
