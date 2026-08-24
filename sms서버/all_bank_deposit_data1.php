<?php
include("/home/sms/public_html/lib/function.php"); // sms db
include("/home/sms/public_html/lib/errorlist_function.php");
include("/home/sms/public_html/lib/luckybank_function.php");
include("/home/sms/public_html/lib/perfectpanel.php");
echo "문자내역_가공<br />";
lb_db_query("insert into bank_cron set text = '문자내역_가공처리_sms서버1234', regdate = now() ");

$bank_list_sql = "select a.*, b.mb_10
from site a left join member b
on a.member_no = b.mb_no
where a.errorlist_number != '' and mb_10 != ''  and a.acount != '' order by website asc ";
$bank_list_result = lb_db_query($bank_list_sql);

for($i=0;$row=lb_db_fetch($bank_list_result);$i++){
  if(luckypanel_bankdata_전송중단($row['luckypanel'], $row['luckypanel_bankdata'])){
    echo $row['site']."_luckypanel_bankdata 전송중단<br />";
    continue;
  }
  echo $row['site']."<br />";

    /** 포인트 부족 문자 안내 **/
    $회원sql = "select * from member where mb_no = {$row['member_no']}";
    $_mem = lb_db_select($회원sql);
    if(!$_mem['mb_10']){
      continue;
    }

    //입금내역
    $result = db_query("select * from bankdata where status = 0 and 가공 = 0 order by regdate desc "); //문자 원본 내역
        foreach($result as $data){ //문자 원본 입금자명, 금액을 분리작업

          $_계좌확인 = 포함여부($data['sms'], $row['acount']); //입금문자내용에 있는 계좌번호랑 비교
          if($_계좌확인){
            if($row['bank']=="농협은행"){
                $입금액 = 농협_입금_원사이금액($data['sms']);
                $extractedText = 농협_입금자명추출($data['sms'], $row['acount']);
                if ($extractedText !== null) {
                    $입금자명 = 소괄호한개이후문자제거($extractedText,"(");
                }
            }else if($row['bank']=="신한은행"){
                $depositInfo = 신한_입금뒤문자열만추출(신한_콤마전부제거($data['sms']));
                $신한 = 신한_금액_입금자명분리($depositInfo);
                $입금자명 = $신한['nonNumeric'];
                $입금액 = $신한['number'];

            }else if($row['bank']=="기업은행"){
                $result = 기업은행_잔액부분_추출($data['sms']);
                $data['sms'] = $result['rest'];
                $입금자명준비 = 기업은행_입금_이후문자열($data['sms']); // 입금문자에서..  (금액)정한나173***24801011기업 이부분만 추출함

                // 원 이 두번들어갈시 처리가 안되서 [10,000원] 부분만 추출
                $입금금액부분추출 = 기업은행_입금금액부분_추출($입금자명준비);

                $입금자명 = 기업은행_입금자명_추출($입금금액부분추출, $row['acount']);
                $입금액 = 기업은행_문자_입금금액(콤마제거($data['sms']));
                $잔액 = 콤마제거(rtrim($result['balance'], "원"));

            }else if($row['bank']=="국민은행"){ //67633704003736
                // [Web발신][KB]01/15 16:24 676337**736송선한전자금융입금100
                $_kb계좌번호_추출후문자열 = 국민_계좌번호_제거후_문자내용($data['sms'], $row['acount']);
                $_kb_입금상태제거 = 국민_입금자명_옆_입금상태제거($_kb계좌번호_추출후문자열);
                //echo $_kb_입금상태제거."<br />";
                $_특수문자기준으로분할 = explode(":", $_kb_입금상태제거);
                $_입금단어앞입금자명추출 = explode("입금", $_특수문자기준으로분할[1]);

                $입금자명 = 특정_단어_제거(문자열중간에소괄호체크(국민_입금자명만추출($_입금단어앞입금자명추출[0])));
                $입금액 = 국민_문자열맨끝_숫자형식만추출($_kb_입금상태제거);

            }else if($row['bank']=="우리은행"){
              $우리 = 하나_계좌번호부분_제외한문자열1($data['sms'], $row['acount']);
              $_입금뒤문자열만 = 신한_입금뒤문자열만추출($우리);
              // 잔액 15,359,734원 같이 잔액 정보가 포함된 부분 제거
              $_입금뒤문자열만 = preg_replace('/잔액\s*[\d,]+원/u', '', $_입금뒤문자열만);
              $result = 우리_추출_금액과_텍스트($_입금뒤문자열만);
              $입금자명 = $result['name'];
              $입금액 = 콤마제거($result['amount']);
              
            }else if($row['bank']=="하나은행"){
              $하나결과 = 하나은행20241113($data['sms']);

              $입금자명 = $하나결과['name'];
              $입금액 = 콤마제거($하나결과['amount']);

            }else if($row['bank']=="SC제일은행"){
              $내용 = explode("　",$data['sms']);
//              $내용분리1 = explode("　　　　　　　",$내용분리[1]);
              $입금자명 = $내용[1];
              $입금액 = SC제일은행_입금금액추출($내용[5]);
            }else if($row['bank']=="수협"){
              $수협 = 수협_계좌번호기준으로분리($data['sms'], $row['acount']);
              $수협결과 = 수협_문자열_금액_입금자추출1(신한_콤마전부제거($수협));
              $입금자명 = 수협_입금자명_옆_입금상태제거($수협결과['name']);
              $입금액 = $수협결과['amount'];

            }else if($row['bank']=="케이뱅크"){
              $입금문자 = 신한_콤마전부제거($data['sms']);
              $name = 케이뱅크_입금자명추출($입금문자);
              $amount = 케이뱅크_입금금액추출($입금문자);

              $입금자명 = $name;
              $입금액 = $amount;
            }else if($row['bank']=="우체국"){
              $parts = explode($row['acount'], $data['sms']);
              // '입금 ' 제거
              $after_deposit = str_replace("입금 ", "", $parts[1]);
              // '원' 기준으로 분리
              $parts = explode("원", $after_deposit, 2);

              $amount = $parts[0];
              $sender = $parts[1];

              $우체국결과 = array('name' => $sender, 'amount' => $amount);

              $입금자명 = $우체국결과['name'];
              $입금액 = $우체국결과['amount'];
              $잔액 = 0;
            }else if($row['bank']=="새마을금고"){
              $새마을 = 새마을금고_입금자명금액추출($data['sms']);
              $입금자명 = $새마을['name'] !== null ? $새마을['name'] : '';
              $입금액 = $새마을['amount'] !== null ? $새마을['amount'] : 0;
            }

            $_입금자명 = preg_replace('/\s|　+/u', '', $입금자명);


            $사이트 = lb_db_select("select member_no from site where site = '{$row['site']}' ");
            if($row['website']==1 || (isset($row['telegram']) && $row['telegram']==1)){
              site_bank_data_저장($data['id'], $사이트['member_no'], $row['site'], $row['acount'], $_입금자명, $입금액, $data['sms'], $data['regdate']);
              db_query("update bankdata set 가공 = 1 where id = {$data['id']} ");
            }else{
              $데이터분리 = "insert into bank_data_detail set
              data_idx = {$data['id']},
              midx = {$사이트['member_no']},
              site = '{$row['site']}',
              acount = '{$row['acount']}',
              name = '{$_입금자명}',
              amount = {$입금액},
              org_msg = '{$data['sms']}',
              deposit_date = '{$data['regdate']}',
              regdate = now() ";
              //echo $데이터분리."<br />";
              db_query($데이터분리); //입금건 가공하여 bank_data_detail에 저장함
              db_query("update bankdata set 가공 = 1 where id = {$data['id']} ");
            }
          }else{
            echo "계좌번호 불일치<br />";
            echo $data['id']."<br />";
            //db_query("delete from bankdata where id = {$data['id']} ");
          }
      }

}
