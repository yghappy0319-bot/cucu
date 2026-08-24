<?php include_once "../lib/function.php";
include_once "../_chk.php";

if($historylist==1){
  $total = db_select("select count(*) as cnt from lr_point_log where midx = {$midx}");
  if($list_num > $total['cnt']){
    die("end"); //더 없음
  }
  $sql = "select idx,status, gubun, name, point, etc, regdate from lr_point_log
  where midx = {$midx} order by regdate desc limit {$list_num} ";
  //echo $sql;
  $diposit = db_query($sql);
  $_cnt = mysqli_num_rows($diposit);
  for($i=0;$dip=db_fetch($diposit);$i++){
  ?>
  <li>
    <div class="wrap">
        <div class="status po_rtive"></div>
        <div class="fl">
          <div class="tit">
            <span style="color:#194A9C;font-weight: 700;">
            <?=$dip['gubun']?>
            </span>
            <span class="amount">
              <b><?= 나의이용내역_지급사용색상($dip['gubun'], $dip['point'], ' P') ?></b>
            </span>
          </div>

          <div class="buy">
          <?=$dip['etc']?>
          </div>
        </div>
        <div class="date"><?= date("Y/m/d H:i", strtotime($dip['regdate'])) ?></div>
    </div>
  </li>
  <? } ?>
  <? if($_cnt==0){?>
  <tr>
      <div class="wrap">
          <p class="no_data">내역이 없습니다.</p>
      </div>
  </tr>
  <? } ?>
<? }else if($historylist==2){ ?>
  <?
  $total = db_select("select count(*) as cnt from lr_deposit_log where midx = {$midx}");
  if($list_num > $total['cnt']){
    die("end"); //더 없음
  }

  $sql = "select * from lr_deposit_log
  where midx = {$midx} order by regdate desc limit {$list_num} ";
  //echo $sql;
  $diposit = db_query($sql);
  $_cnt = mysqli_num_rows($diposit);
  for($i=0;$dip=db_fetch($diposit);$i++){
  ?>
  <li>
    <div class="wrap">
        <div class="status po_rtive"></div>
        <div class="fl">
          <div class="tit">
            <span style="color:#194A9C;font-weight: 700;">
              <? if($dip['status']==1){ $gubun = "입금대기"; ?>
                <?=$gubun?>
              <? }else if($dip['status']==2){ $gubun = "지급완료"; ?>
                <?=$gubun?>
              <? }else if($dip['status']==6){ $gubun = "사용"; ?>
                <?=$gubun?>
              <? }else{ $gubun = "입금취소"; ?>
                <?=$gubun?>
              <? } ?>
            </span>
            <span class="amount">
              <b>
                <?= 나의이용내역_지급사용색상($gubun, number_format($dip['point']), '캐시') ?>
              </b>
            </span>
          </div>

          <div class="buy">
          <? if($dip['deposit_type']=='reward'){ ?>
            보상캐시
          <? }else{ ?>
          <?=$dip['status']==6 ? "티켓 구매":"계좌이체 캐시 충전" ?>
          <? } ?>
          </div>
        </div>
        <div class="date">

          <? if($dip['status']==1){ ?>
            <?= date("Y/m/d H:i", strtotime($dip['regdate'])) ?>
          <? }else if($dip['point']==2){ ?>
            <?= date("Y/m/d H:i", strtotime($dip['deposit_date'])) ?>
          <? }else{ ?>
            <?= date("Y/m/d H:i", strtotime($dip['regdate'])) ?>
          <? } ?>
        </div>
    </div>
  </li>
  <? } ?>

<? }else if($historylist==3){ ?>
  <?
  $total = db_select("select count(*) as cnt from lr_prize_log where midx = {$midx}");
  if($list_num > $total['cnt']){
    die("end"); //더 없음
  }

  // 당첨금 1등 2등 3등 은 나오지 않는다
  $sql = "select * from lr_prize_log where midx = {$midx} order by regdate desc limit {$list_num} ";
  //echo $sql;
    $prize_result = db_query($sql);
    foreach($prize_result as $prize){
  ?>
  <li>
    <div class="wrap">
        <div class="status po_rtive"></div>
        <div class="fl">
          <div class="tit">
            <span style="color:#194A9C;font-weight: 700;">
              <?=$prize['gubun']?>
            </span>
            <span class="amount">
              <b>
                <?= 나의이용내역_지급사용색상($prize['gubun'], number_format($prize['prizemoney']), '당첨금') ?>
              </b>
            </span>
          </div>

          <div class="buy">
            <? if($prize['gubun']=="출금"){?>
              <? if($prize['status']){?>
                출금완료
              <? }else{ ?>
                출금신청
              <? } ?>

            <? }else{ ?>
              <?= $prize['ranking']>0 ? $prize['ranking']."등당첨":"포인트로 전환" ?>
            <? } ?>
          </div>
        </div>
        <div class="date">
          <?= date("Y/m/d H:i", strtotime($prize['regdate'])) ?>
        </div>
    </div>
  </li>
  <? } ?>
<? }else if($historylist==4){ ?>
  <?
  $total = db_select("select count(*) as cnt from lr_coupon_log_list where midx = {$midx}");
  if($list_num > $total['cnt']){
    die("end"); //더 없음
  }

  $sql = "select * from lr_coupon_log_list where midx = {$midx} order by regdate desc limit {$list_num} ";
  $coupon_result = db_query($sql);
  foreach($coupon_result as $data){
  ?>
  <li>
    <div class="wrap">
        <div class="status po_rtive"></div>
        <div class="fl">
          <div class="tit">
            <span style="color:#194A9C;font-weight: 700;">
              <? if($data['status']){ $gubun = "사용"; ?>
                <?=$gubun?>
              <? }else{ $gubun = "지급"; ?>
                <?=$gubun?>
              <? } ?>
            </span>
            <span class="amount">
              <b><?= 나의이용내역_지급사용색상($gubun, $data['name'], '') ?></b>
            </span>
          </div>

          <div class="buy">
            <? if($data['recommend_id']){?>
              <? if($data['status']==0){?>
                아이디:<?=$data['recommend_id']?>
              <? }else{ ?>
                티켓구매
              <? } ?>

            <? }else{ ?>
              <?=$data['usedate1']?>
            <? } ?>
          </div>
        </div>
        <div class="date">
          <?= date("Y/m/d H:i", strtotime($data['regdate'])) ?>
        </div>
    </div>
  </li>
  <? } ?>
<? }else{ ?>

  <?
  $new_countSql = "select count(*) as totalRecords  from (
  SELECT midx
  FROM lr_point_log
  UNION ALL
  SELECT midx
  FROM lr_deposit_log
  UNION ALL
  SELECT midx FROM lr_prize_log
  UNION ALL
  SELECT midx from lr_coupon_log
  ) as a
  where midx = {$midx} ";
  $total = db_select($new_countSql);
  if($list_num > $total['cnt']){
    die("end"); //더 없음
  }

  $sql = "
  select gubun, midx, status, point, title, regdate from (
  SELECT '메가파워월드포인트' as gubun, midx, status, etc as title, point, regdate
  FROM lr_point_log where name = '메가파워월드포인트'
  UNION ALL
  SELECT '당첨금' as gubun, midx, status, etc as title, point, regdate
  FROM lr_point_log where name = '당첨금'
  UNION ALL
  SELECT '캐시충전', midx, status,
  CASE
  WHEN deposit_type = 'reward' THEN '보상캐시'
  WHEN status = 1 THEN '입금대기'
  WHEN status = 2 THEN '지급완료'
    WHEN status = 5 THEN '입금취소'
    WHEN status = 6 THEN '사용'
    ELSE '기타'  -- 추가적으로 다른 상태값에 대한 처리 필요
   END AS title
  , point, regdate
  FROM lr_deposit_log
  UNION ALL
  SELECT '당첨금', midx, status,

   CASE
       WHEN gubun = '차감' THEN '포인트로 전환'
       WHEN gubun = '출금' THEN
           CASE
               WHEN status = 0 THEN '출금신청'
               WHEN status = 1 THEN '출금완료'
               ELSE '기타'
           END
       ELSE '기타'
   END AS title,
  prizemoney,
  regdate
  FROM lr_prize_log where gubun != '지급'
  UNION ALL
  SELECT '쿠폰', midx, status, name, discount, regdate from lr_coupon_log_list
  ) as a where midx = {$midx} ORDER BY regdate DESC limit $list_num ";
  //echo $sql;
  $result = db_query($sql);
  $_cnt = mysqli_num_rows($result);
  while($row=db_fetch($result)){
  ?>
        <li>
          <div class="wrap">
              <div class="status po_rtive"></div>
              <div class="fl">
                  <div class="tit">
                      <span style="color:#194A9C;font-weight: 700;">
                        <? if($row['gubun']=="쿠폰"){ ?>
                          <? if($row['status']){ ?>
                          사용
                          <? }else{ ?>
                          지급
                          <? } ?>

                          <? }else if($row['gubun']=="캐시충전"){ ?>
                            <? if($row['status']==6){ ?>
                            사용
                            <? }else if($row['status']==2){ ?>
                            지급
                          <? }else{ ?>
                            대기
                          <? } ?>

                        <? }else if($row['gubun']=="메가파워월드포인트"){ ?>
                          <? if($row['status']==2){ ?>
                          사용
                          <? }else{ ?>
                          지급
                          <? } ?>

                        <? }else{ ?>
                          지급
                        <? } ?>
                      </span>

                      <span class="amount">
                      <? if($row['gubun']=="쿠폰"){ ?>
                        <b><?=$row['title']?></b>
                      <? }else{ ?>
                        <b><?= number_format($row['point']) ?></b>
                      <? } ?>

                      <? if($row['gubun']=="쿠폰"){ ?>

                      <? }else if($row['gubun']=="메가파워월드포인트"){ ?>
                        P
                      <? }else if($row['gubun']=="당첨금"){ ?>
                        당첨금
                      <? }else{ ?>
                        캐시
                      <? } ?>

                      </span>
                  </div>

                  <div class="buy">
                  <? if($row['gubun']=="쿠폰"){ ?>
                    <?=$row['regdate']?>
                  <? }else if($row['gubun']=="캐시충전"){ ?>
                    <? if($row['status']==6){ ?>
                      티켓구매 사용
                    <? }else{ ?>
                      <?=$row['title']?>
                    <? } ?>
                  <? }else{ ?>
                    <?=$row['title']?>
                  <? } ?>
                  </div>
              </div>
              <div class="date"><?= date("Y/m/d H:i", strtotime($row['regdate'])) ?></div>
          </div>
        </li>
  <? } ?>

<? } ?>
