<div class="new_win_detail">
    <div class="tit">
        <span><?=$game=="mm" ? "메가밀리언":"파워볼" ?></span><b><?=$round?></b>회차 당첨결과
    </div>
    <div class="date">
        <span><?= date("Y년 m월 d일", strtotime($data['ko_date'])) ?> <?=요일($data['ko_date']);?> <?=$서머타임["추첨시간"]?>시</span>(한국시간)
    </div>
    <div class="ball">
        <div><span><?=$data['ball1']?></span></div>
        <div><span><?=$data['ball2']?></span></div>
        <div><span><?=$data['ball3']?></span></div>
        <div><span><?=$data['ball4']?></span></div>
        <div><span><?=$data['ball5']?></span></div>
        <div><span><?=$data['ball6']?></span></div>
    </div>
    <div class="table">
        <table>
            <thead>
            <tr>
                <th>등수</th>
                <th>당첨조건</th>
                <th>당첨금</th>
                <th>당첨수</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>1등</td>
                <td>5개 화이트볼 + 1개 <?=$game=="mm" ? "메가":"파워" ?>볼</td>
                <td>$ <?= number_format($data['money1']) ?></td>
                <td><?=$data['win1']?>명</td>
            </tr>
            <tr>
                <td>2등</td>
                <td>5개 화이트볼</td>
                <td>$ <?= number_format($data['money2']) ?></td>
                <td><?=$data['win2']?>명</td>
            </tr>
            <tr>
                <td>3등</td>
                <td>4개 화이트볼 + 1개 <?=$game=="mm" ? "메가":"파워" ?>볼</td>
                <td>$ <?= number_format($data['money3']) ?></td>
                <td><?=$data['win3']?>명</td>
            </tr>
            <tr>
                <td>4등</td>
                <td>4개 화이트볼</td>
                <td>$ <?= number_format($data['money4']) ?></td>
                <td><?=$data['win4']?>명</td>
            </tr>
            <tr>
                <td>5등</td>
                <td>3개 화이트볼 + 1개 <?=$game=="mm" ? "메가":"파워" ?>볼</td>
                <td>$ <?= number_format($data['money5']) ?></td>
                <td><?= number_format($data['win5'])?>명</td>
            </tr>
            <tr>
                <td>6등</td>
                <td>3개 화이트볼</td>
                <td>$ <?= number_format($data['money6']) ?></td>
                <td><?= number_format($data['win6'])?>명</td>
            </tr>
            <tr>
                <td>7등</td>
                <td>2개 화이트볼 + 1개 <?=$game=="mm" ? "메가":"파워" ?>볼</td>
                <td>$ <?= number_format($data['money7']) ?></td>
                <td><?= number_format($data['win7'])?>명</td>
            </tr>
            <tr>
                <td>8등</td>
                <td>1개 화이트볼 + 1개 <?=$game=="mm" ? "메가":"파워" ?>볼</td>
                <td>$ <?= number_format($data['money8']) ?></td>
                <td><?= number_format($data['win8'])?>명</td>
            </tr>
            <tr>
                <td>9등</td>
                <td>1개 <?=$game=="mm" ? "메가":"파워" ?>볼</td>
                <td>$ <?= number_format($data['money9']) ?></td>
                <td><?= number_format($data['win9'])?>명</td>
            </tr>
            </tbody>
        </table>
    </div>
    <div class="tip">
        * 위 결과는 미국 오레건주만의 결과입니다.
    </div>
</div>
