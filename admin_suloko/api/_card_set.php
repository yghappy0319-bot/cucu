<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_POINT_LOG.php";
// Telegram sendMessage (처리완료 알림)
if (!defined('BOT_TOKEN')) define('BOT_TOKEN', '8574625673:AAFbaSw7BBFpqiUB10IGOLznrULl-_EyJSg');
if (!defined('API_URL'))  define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');
require_once $_SERVER['DOCUMENT_ROOT']."/SMS/function_telegram.php";

// 입력: "결제완료 노성현 66000 7792" (결제완료 이름 금액 카드뒤4자리)
// 입력: "결제오류 원정숙 66000 1535 할부한도초과" (결제오류 이름 금액 카드뒤4자리 에러메시지)
$update = json_decode(file_get_contents("php://input"), true);
$text = '';

if (isset($update['message']['text'])) {
    $text = trim($update['message']['text']);
} elseif (isset($_GET['text'])) {
    $text = trim($_GET['text']);
} elseif (isset($_POST['text'])) {
    $text = trim($_POST['text']);
}

if ($text !== '') {
    $db->query("INSERT INTO CRON_LOG SET FILE = '".addslashes($text)."', REG_DATE = NOW()");
    $parts = preg_split('/\s+/', trim($text));
    $n = count($parts);
    // 결제완료/결제오류 이름 금액 카드뒤4자리 [에러메시지] — 이름에 띄어쓰기 허용(영문 등)
    $user_name = '';
    $price = '';
    $card_tail = '';
    $card_status = '';
    $price_idx = null;
    if ($n >= 4 && ($parts[0] === '결제완료' || $parts[0] === '결제오류')) {
        // 끝에서 두 번째·마지막 숫자 필드 = 금액, 카드뒤4자리 → 그 앞까지가 이름
        for ($i = $n - 1; $i >= 2; $i--) {
            $last_clean = preg_replace('/[^0-9]/', '', $parts[$i]);
            $prev_clean = preg_replace('/[^0-9]/', '', $parts[$i - 1]);
            if (strlen($last_clean) === 4 && $last_clean === $parts[$i] && $prev_clean !== '' && $prev_clean === $parts[$i - 1]) {
                $card_tail = $parts[$i];
                $price     = $prev_clean;
                $price_idx = $i - 1;
                $user_name = implode(' ', array_slice($parts, 1, $price_idx - 1));
                if ($parts[0] === '결제오류' && $i + 1 < $n) {
                    $card_status = implode(' ', array_slice($parts, $i + 1));
                }
                break;
            }
        }
    }
    // 결제완료 이름 금액 카드뒤4자리
    if ($price_idx !== null && $parts[0] === '결제완료') {

        if ($user_name !== '' && $price !== '' && $card_tail !== '') {
            $user_name_esc = addslashes($user_name);
            $price_esc     = (int)$price;
            $card_tail_esc = addslashes($card_tail);

            // CASH + MEMBER 조인: NAME, PRICE, CARDNO 앞4자리, CARDNAME='카드' 일치하는 제일 최신 1건의 CASH_NO 조회
            $sql = "SELECT c.CASH_NO, c.CASH, c.USER_ID, c.IS_USE, m.NAME, c.IS_USE
                    FROM CASH AS c
                    INNER JOIN MEMBER AS m ON m.USER_ID = c.USER_ID
                    WHERE m.NAME = '{$user_name_esc}'
                      AND c.PRICE = {$price_esc}
                      AND LEFT(TRIM(c.CARDNO), 4) = '{$card_tail_esc}'
                      AND c.CARDNAME = '카드' AND c.IS_USE = ''
                    ORDER BY c.CASH_NO DESC
                    LIMIT 1";
            $row = $db->get_data($sql);

            if (!empty($row['CASH_NO'])) {
                $cash_no   = (int)$row['CASH_NO'];
                $cash_amt  = (int)$row['CASH'];
                $user_id   = $row['USER_ID'];
                $mem       = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID = '".addslashes($user_id)."'");

                $db->query("UPDATE CASH SET IS_USE = 'Y' WHERE CASH_NO = {$cash_no}");
                // 회원 캐시 적립
                $db->query("UPDATE MEMBER SET CASH = CASH + {$cash_amt} WHERE USER_ID = '".addslashes($user_id)."'");

                // 텔레그램 방으로 처리완료 메시지 전송 (웹훅으로 들어온 경우 같은 채팅방에 reply)
                $telegram_chat_id = isset($update['message']['chat']['id']) ? $update['message']['chat']['id'] : null;
                if ($telegram_chat_id && BOT_TOKEN !== '') {
                    $done_msg = "✅ 처리완료 : ".$user_name;
                    telegramApiRequest("sendMessage", array('chat_id' => $telegram_chat_id, 'text' => $done_msg));
                }

                // CASH_CONFIG의 CASH와 비교하여 POINT 조회 (캐시충전 보너스포인트)
                $config = $db->get_data("SELECT POINT FROM CASH_CONFIG WHERE CASH = {$cash_amt} LIMIT 1");
                $point  = (!empty($config) && isset($config['POINT'])) ? (int)$config['POINT'] : 0;
                if ($point > 0 && !empty($mem)) {
                    $point_log = array();
                    $point_log["mode"]         = "insert";
                    $point_log["COUPON_NO"]    = 0;
                    $point_log["ORDERS_NO"]    = 0;
                    $point_log["CASH_LOG_NO"] = $cash_no;
                    $point_log["POINT"]        = $point;
                    $point_log["N_POINT"]     = (int)$mem['POINT'] + $point;
                    $point_log["O_POINT"]     = (int)$mem['POINT'];
                    $point_log["USER_ID"]     = $user_id;
                    $point_log["MEMO"]        = "캐시충전보너스";
                    $point_log["STATUS"]      = "P";
                    F_T_POINT_LOG($point_log);
                    $db->query("UPDATE MEMBER SET POINT = POINT + {$point} WHERE USER_ID = '".addslashes($user_id)."'");
                }

            
                $TEMPLET_NO   = 24;
                $SMSTEMPLET   = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");
              
                $from         = $fromHP;
                $to           = $mem["HP"];
                $CODESK       = "S";
                $MEMBER_NO    = $mem['MEMBER_NO'];
                $MEMBER_NAME  = $mem['NAME'];
                $SUBJECT      = $SMSTEMPLET['SUBJECT'];
                $SENDMSG      = $SMSTEMPLET['CONTENT'];
                $SENDMSG      = str_replace('{USERID}', $mem['USER_ID'], $SENDMSG);
                $SENDMSG      = str_replace('{PRICE}', number_format($cash_amt), $SENDMSG);
                $SENDMSG      = addslashes($SENDMSG);
                $sms          = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG,"LMS");
              


                echo json_encode(['ok' => true, 'message' => "CASH_NO {$cash_no} IS_USE = Y 처리 완료", 'CASH' => $cash_amt, 'POINT' => $point]);
            } else {
                echo json_encode(['ok' => false, 'message' => '조건에 맞는 CASH 건이 없습니다.']);
            }
        } else {
            echo json_encode(['ok' => false, 'message' => '이름/금액/카드뒤4자리 형식 확인 필요.']);
        }
    } elseif ($price_idx !== null && $parts[0] === '결제오류' && $user_name !== '' && $price !== '' && $card_tail !== '') {
        $user_name_esc = addslashes($user_name);
        $price_esc     = (int)$price;
        $card_tail_esc = addslashes($card_tail);
        $card_status_esc = addslashes($card_status);

        $sql = "SELECT c.CASH_NO, c.CASH, c.USER_ID, c.IS_USE
                FROM CASH AS c
                INNER JOIN MEMBER AS m ON m.USER_ID = c.USER_ID
                WHERE m.NAME = '{$user_name_esc}'
                  AND c.PRICE = {$price_esc}
                  AND LEFT(TRIM(c.CARDNO), 4) = '{$card_tail_esc}'
                  AND c.CARDNAME = '카드' AND c.IS_USE = ''
                ORDER BY c.CASH_NO DESC
                LIMIT 1";
        $row = $db->get_data($sql);

        $telegram_chat_id = isset($update['message']['chat']['id']) ? $update['message']['chat']['id'] : null;
        if ($telegram_chat_id && BOT_TOKEN !== '') {
            $done_msg = "❌ 오류처리 완료 : ".$user_name." / ".$card_status;
            telegramApiRequest("sendMessage", array('chat_id' => $telegram_chat_id, 'text' => $done_msg));
        }

        if (!empty($row['CASH_NO'])) {
            $cash_no   = (int)$row['CASH_NO'];
            $user_id   = $row['USER_ID'];

            $db->query("UPDATE CASH SET IS_USE = 'N', CONTENT = '{$card_status_esc}' WHERE CASH_NO = {$cash_no}");
            $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID = '".addslashes($user_id)."'");
            if (!empty($mem)) {
                $TEMPLET_NO   = 52;
                $SMSTEMPLET   = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");
                $from         = $fromHP;
                $to           = $mem["HP"];
                $CODESK       = "S";
                $MEMBER_NO    = $mem['MEMBER_NO'];
                $MEMBER_NAME  = $mem['NAME'];
                $SUBJECT      = $SMSTEMPLET['SUBJECT'];
                $SENDMSG      = $SMSTEMPLET['CONTENT'];
                $SENDMSG      = str_replace('{STATUS}', $card_status, $SENDMSG);
                $SENDMSG      = addslashes($SENDMSG);
                $sms          = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG,"LMS");
            }
            echo json_encode(['ok' => true, 'message' => "CASH_NO {$cash_no} IS_USE = N 처리 완료"]);
        } else {
            echo json_encode(['ok' => false, 'message' => '조건에 맞는 CASH 건이 없습니다.']);
        }
    } else {
        echo json_encode(['ok' => false, 'message' => '형식: 결제완료 이름 금액 카드뒤4자리 (이름에 띄어쓰기 가능)']);
    }
}

