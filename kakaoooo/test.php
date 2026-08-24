<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>견적서</title>
<style>
    body {
        font-family: "맑은 고딕", Arial, sans-serif;
        margin: 40px;
        color: #333;
    }
    .container {
        max-width: 900px;
        margin: auto;
        border: 1px solid #ccc;
        padding: 30px;
    }
    h1 {
        text-align: center;
        margin-bottom: 40px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
    }
    th, td {
        border: 1px solid #ccc;
        padding: 10px;
        text-align: center;
    }
    th {
        background: #f5f5f5;
    }
    .info td {
        text-align: left;
    }
    .total {
        font-size: 18px;
        font-weight: bold;
        text-align: right;
    }
    .footer {
        margin-top: 40px;
        text-align: right;
    }
</style>
</head>

<body>
<div class="container">

    <h1>견 적 서</h1>

    <!-- 기본 정보 -->
    <table class="info">
        <tr>
            <th width="20%">견적번호</th>
            <td width="30%">EST-2026-001</td>
            <th width="20%">견적일자</th>
            <td width="30%">2026-01-23</td>
        </tr>
        <tr>
            <th>수신</th>
            <td>크몽</td>
            <th>발신</th>
            <td>럭키루트</td>
        </tr>
    </table>

    <!-- 견적 내역 -->
    <table>
        <tr>
            <th>No</th>
            <th>항목</th>
            <th>내용</th>
            <th>수량</th>
            <th>단가</th>
            <th>금액</th>
        </tr>
        <tr>
            <td>1</td>
            <td>웹사이트 제작(SMM패널)</td>
            <td>전체</td>
            <td>1</td>
            <td>2,200,000</td>
            <td>2,200,000</td>
        </tr>
        <!-- <tr>
            <td>2</td>
            <td>유지보수</td>
            <td>월 유지보수</td>
            <td>1</td>
            <td>200,000</td>
            <td>200,000</td>
        </tr> -->
    </table>

    <!-- 합계 -->
    <table>
        <tr>
            <th>공급가액</th>
            <td style="text-align:right;">2,200,000 원</td>
        </tr>
        <tr>
            <th>부가세 (10%)</th>
            <td style="text-align:right;">0 원</td>
        </tr>
        <tr>
            <th class="total">합계금액</th>
            <td class="total">2,200,000 원</td>
        </tr>
    </table>

    <table>
        <tr>
            <th rowspan="2" style="text-align: left;" >* 선금(70%) 1,540,000 / 잔금(30%) 660,000</th>
        </tr>

    </table>

    <!-- 하단 -->
    <div class="footer">
        <p>위와 같이 견적합니다.</p>
        <p>2026년 01월 23일</p>
        <p><strong>럭키루트 (인)</strong></p>
    </div>

</div>
</body>
</html>
