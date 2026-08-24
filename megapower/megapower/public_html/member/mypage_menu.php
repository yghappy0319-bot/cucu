<div class="new_mypage_menu">
    <ul>
        <li>
            <a href="/buy/point.html">
                <img src="/images/t-01.png" />
                <p>캐시충전</p>
            </a>
        </li>
        <li>
            <a href="/member/history.html">
                <img src="/images/t-02.png" />
                <p>나의 이용내역</p>
            </a>
        </li>
        <li>
            <a href="/member/withdraw.html">
                <img src="/images/t-03.png" />
                <p>당첨금 전환/출금</p>
            </a>
        </li>
        <li>
            <a href="/buy/prize.html">
                <img src="/images/t-04.png" />
                <p>당첨 결과</p>
            </a>
        </li>
        <li>
            <a href="/member/buy.html?game=pb">
                <img src="/images/t-05.png" />
                <p>티켓 구매내역</p>
            </a>
        </li>
        <li>
            <? if(카카오가입자($member['id'])==1){
                        $url = "/member/info.html";
                        }else{
                        $url = "/member/passchk.html";
                        }
                    ?>
            <a href="<?=$url?>">
                <img src="/images/t-06.png" />
                <p>정보수정</p>
            </a>
        </li>
    </ul>
</div>
