<?php
include "/home/lotto/public_html/admin/lib/function.php";

error_reporting(E_ALL);
ini_set("display_errors", 1);


function getWeightedRandomResult(array $probabilities) {
    $validProbabilities = array_filter($probabilities, function ($p) {
        return $p > 0;
    });

    if (empty($validProbabilities)) {
        return 2; // 유효한 확률이 없으면 null 반환 또는 다른 처리
    }

    $rand = mt_rand(1, array_sum($validProbabilities));
    $cumulativeProbability = 0;

    foreach ($validProbabilities as $value => $probability) {
        $cumulativeProbability += $probability;
        if ($rand <= $cumulativeProbability) {
            return $value;
        }
    }

    // 이 부분은 원래 확률 합이 100이 아닌 경우를 대비한 안전 장치
    return array_key_last($validProbabilities);
}

function getValidRandomLeverage(array $probabilities) {
    while (true) {
        $result = getWeightedRandomResult($probabilities);
        if ($result !== null && $probabilities[$result] > 0) {
            return $result;
        }
        // $probabilities 배열의 모든 값이 0 이하인 경우 무한 루프를 방지하기 위한 안전 장치
        if (count(array_filter($probabilities, function ($p) { return $p > 0; })) === 0) {
            return 2; // 또는 다른 기본값이나 오류 처리
        }
    }
}

$sql = " select ORDERS_NO, BALL1, BALL2, BALL3, BALL4, BALL5, REG_DATE from ORDERS where GUBUN = 'MM' and date_format(REG_DATE, '%Y-%m-%d %H:%i:%s') > '2025-08-20 09:00:00' ";
$result2 = db_query($sql);

foreach($result2 as $data){
    $레버리지 = db_select("select count(*) as cnt from ORDER_MULTIPLIER where ORDERS_NO = {$data['ORDERS_NO']} ");
    echo $data['ORDERS_NO']."__레버리지 정보가 있나? " .$레버리지['cnt']. " <Br>";

    if($레버리지['cnt']==0){

        $balls = [];
        for ($i = 1; $i <= 5; $i++) {
            if (!empty($data["BALL{$i}"])) {
                $balls[] = $data["BALL{$i}"];
            }
        }
        $count = count($balls);

        $ORDER_MEGA_MULTIPLIER = "INSERT INTO ORDER_MULTIPLIER SET ";
        $ORDER_MEGA_MULTIPLIER.= "ORDERS_NO = {$data['ORDERS_NO']} ";

        for($a=1;$a <=$count;$a++){
            $config2 = db_select("select * from SITE");
            $probabilities = [
                2 => $config2['레버리지2'],
                3 => $config2['레버리지3'],
                4 => $config2['레버리지4'],
                5 => $config2['레버리지5'],
                10 => $config2['레버리지10'],
            ];

            $randomResult = getValidRandomLeverage($probabilities);
            //echo $config['레버리지'.$randomResult]."\n";
            if($config2['레버리지2'] < 0){
                db_query("update SITE set 레버리지2 = 0");
            }
            if($config2['레버리지3'] < 0){
                db_query("update SITE set 레버리지3 = 0");
            }
            if($config2['레버리지4'] < 0){
                db_query("update SITE set 레버리지4 = 0");
            }
            if($config2['레버리지5'] < 0){
                db_query("update SITE set 레버리지5 = 0");
            }
            if($config2['레버리지10'] < 0){
                db_query("update SITE set 레버리지10 = 0");
            }

            $map = [
                1 => "A",
                2 => "B",
                3 => "C",
                4 => "D",
                5 => "E",
            ];

            $alpha = isset($map[$a]) ? $map[$a] : null; // 값 없으면 null

            $ORDER_MEGA_MULTIPLIER.= ", MULTI_".$alpha." = {$randomResult} ";
            syslog(LOG_DEBUG, $ORDER_MEGA_MULTIPLIER);


            // $레버리지 = db_select('select 레버리지2, 레버리지3, 레버리지4, 레버리지5, 레버리지10 from lr_config ');
            $csql = "update SITE set 레버리지{$randomResult} = 레버리지{$randomResult} - 1";
            db_query($csql);

            $config3 = db_select("select * from SITE");
            if($config3['레버리지2'] <= 0 and $config3['레버리지3'] <= 0 and $config3['레버리지4'] <= 0 and
                $config3['레버리지5'] <= 0 and $config3['레버리지10'] <= 0){

                $config_sql = "update SITE set ";
                $config_sql.= "레버리지2 = {$config3['set레버리지2']}, ";
                $config_sql.= "레버리지3 = {$config3['set레버리지3']}, ";
                $config_sql.= "레버리지4 = {$config3['set레버리지4']}, ";
                $config_sql.= "레버리지5 = {$config3['set레버리지5']}, ";
                $config_sql.= "레버리지10 = {$config3['set레버리지10']} ";
                db_query($config_sql);
            }
        }

        db_query($ORDER_MEGA_MULTIPLIER);

    }
}