<div class="new_mypage_head">
    <div class="info">
        <div class="add">
            구매금액의 <b><?=$지급퍼센트?>%</b> 메가파워월드포인트 적립
        </div>
        <? if($member['code']){?>
        <div class="code">
            <input type="hidden" class="my-code" value="https://melottokorea.com/member/join.html?code=<?=$member['code']?>" />
            나의 추천인 코드 <b><?=$member['code']?></b><span>50%할인쿠폰</span>
            <a href="javascript:go_my_code_copy()">공유하기</a>
        </div>
        <? } ?>
    </div>
    <div class="ft">
        <div class="logo">
            <img src="/images/logo_my.png">
        </div>
        <div class="fr">
            <div class="name">
                <?
                    if(카카오가입자($member['id'])==1){
                        $_email = explode("@",$member['email']
                    );
                ?>
                <b><?= $_email[0] ?></b>님 안녕하세요.
                <? }else{ ?>
                <b><?=$member['id']?></b>님 안녕하세요.
                <? } ?>
            </div>
            <div class="level">나의 <b style="color: #317f70;">메가파워월드</b> 등급 <b><?=$member['grade']?></b></div>
        </div>
    </div>
    <div class="data">
        <div class="wrap">
            <div class="dt"><b><?=number_format($member['point'])?></b>캐시</div>
            <p>나의 캐시</p>
        </div>
        <div class="wrap">
            <div class="dt"><b><?=number_format($member['prize_money'])?></b>원</div>
            <p>나의 당첨금</p>
        </div>
        <div class="wrap">
            <div class="dt"><b><?=number_format($member['lucky_point'])?></b>P</div>
            <p>메가파워월드포인트</p>
        </div>
        <?
          $_사용가능쿠폰 = db_select("select count(*) as cnt from lr_coupon_log where midx = {$member['idx']} and status = 0  ");
        ?>
        <div class="wrap">
            <div class="dt">
              <a href="/member/coupon.html?my=coupon"><b><?= $_사용가능쿠폰['cnt'] > 0 ? $_사용가능쿠폰['cnt']:"0"; ?></b>장</a>

            </div>
            <p>보유쿠폰</p>
        </div>
        <div class="wrap">
            <?
                $scratch = db_select("select count(*) as cnt from event_scratch where midx = {$member['idx']} and status = 0  ");
            ?>
            <div class="dt"><b><?=$scratch['cnt']?></b>장</div>
            <p>메가파워월드스크래치 사용권</p>
        </div>
    </div>
</div>
