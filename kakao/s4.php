<?php
exit;
date_default_timezone_set('Asia/Seoul');
$wrote_at_y = (int) date('Y');
$wrote_at_m = (int) date('n');
$wrote_at_d = (int) date('j');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>반입사유서</title>
<style>
  * { box-sizing: border-box; }
  body {
    font-family: "Malgun Gothic", "Apple SD Gothic Neo", sans-serif;
    font-size: 13px;
    line-height: 1.45;
    color: #111;
    max-width: 210mm;
    margin: 0 auto;
    padding: 10mm;
    background: #eee;
  }
  .sheet {
    background: #fff;
    padding: 14mm 16mm;
    box-shadow: 0 1px 3px rgba(0,0,0,.08);
  }
  h1 {
    text-align: center;
    font-size: 18px;
    letter-spacing: 0.2em;
    margin: 0 0 4px;
  }
  .sub { text-align: center; font-size: 10px; color: #666; margin: 0 0 12px; }
  .hint { font-size: 10px; color: #666; margin: 0 0 8px; }
  @media print {
    body { background: #fff; padding: 0; }
    .sheet { box-shadow: none; }
    .no-print { display: none !important; }
  }
  table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  th, td { border: 1px solid #222; padding: 6px 8px; vertical-align: top; }
  th { width: 20%; background: #f0f0f0; font-weight: 600; text-align: center; font-size: 12px; }
  td.blank { min-height: 24px; height: 24px; }
  .small th { width: 14%; }
  .decl { font-size: 11px; border: 1px solid #333; padding: 8px 10px; margin: 10px 0 14px; }
  .sign { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; font-size: 12px; }
  .sign .line { display: inline-block; min-width: 140px; border-bottom: 1px solid #111; height: 20px; vertical-align: bottom; }
</style>
</head>
<body>
<div class="sheet">
  <h1>반 입 사 유 서</h1>
  <table>
    <tr>
      <th>성명</th>
      <td class="blank">장영관</td>
      <th>연락처</th>
      <td class="blank">010-2293-4444</td>
    </tr>
    <tr>
      <th>주소</th>
      <td colspan="3" class="blank">경기도 고양시 소원로 47 606-602호</td>
    </tr>
  </table>

  <table class="small">
    <tr>
      <th>품명</th>
      <th style="width:12%">수량</th>
      <th style="width:18%">금액(통화)</th>
    </tr>
    <tr>
        <td class="blank">포켓몬카드</td>
        <td class="blank">2개</td>
        <td class="blank">266,400원</td>
    </tr>
  </table>
  <table>
    <tr>
      <th>쇼핑몰·주문·송장</th>
      <td class="blank" colspan="3">5206555477031</td>
    </tr>
  </table>

  <table>
    <tr>
      <th>반입사유</th>
      <td class="blank" style="min-height:72px; height:auto">
        ☑ 자기소비　□ 선물　□ 기타(　　　　)<br><br>
        본 물품은 상업적 재판매·영업·재수출 등 영리 목적이 없으며, 개인 수집·소장을 위한 비상업적 용도로만 반입합니다.
      </td>
    </tr>
  </table>

  <div class="decl">
    위 기재는 사실이며, 허위 기재에 따른 책임은 본인에게 있음을 확인합니다.
  </div>

  <div class="sign">
    <span>작성일 <span class="line"><?= $wrote_at_y ?></span> 년 <span class="line" style="min-width:28px"><?= $wrote_at_m ?></span> 월 <span class="line" style="min-width:28px"><?= $wrote_at_d ?></span> 일</span>
    <span>신청인(서명) <span class="line" style="min-width:160px">장영관</span></span>
  </div>
</div>
</body>
</html>
