<?php

function extractText($inputString) {
    $startString = "065901**547";
    $endStrings = array("오픈뱅킹", "전자금융", "스마트폰", "FBS", "CD공동", "인터넷", "리브", "연계");

    $startIndex = strpos($inputString, $startString);
    $endIndex = false;

    foreach ($endStrings as $endString) {
        $endIndex = strpos($inputString, $endString, $startIndex + strlen($startString));
        if ($endIndex !== false) {
            break;
        }
    }
    if ($startIndex === false || $endIndex === false) {
        return "";
    }

    $extractedText = substr($inputString, $startIndex + strlen($startString), $endIndex - ($startIndex + strlen($startString)));
    return $extractedText;
}

function 입금날짜시간($inputString) {
    $pattern = '/\d{2}\/\d{2}\s\d{2}:\d{2}/';
    preg_match($pattern, $inputString, $matches);

    if (empty($matches)) {
        return "";
    }

    $extractedText = $matches[0];
    return $extractedText;
}

function extractText_test($inputString) {
    $startString = "017***16604012";
    $endStrings = array("오픈뱅킹", "전자금융", "스마트폰", "FBS", "CD공동", "인터넷", "리브", "ATM이체");

    $startIndex = strpos($inputString, $startString);
    $endIndex = false;

    foreach ($endStrings as $endString) {
        $endIndex = strpos($inputString, $endString, $startIndex + strlen($startString));
        if ($endIndex !== false) {
            break;
        }
    }

    if ($startIndex === false || $endIndex === false) {
        return "";
    }

    $extractedText = substr($inputString, $startIndex + strlen($startString), $endIndex - ($startIndex + strlen($startString)));
    return $extractedText;
}
function removePattern($string) {
    $pattern = array("(미","(황","(대","(구","(그","(한" );

    return str_replace($pattern, "", $string);
}

function removeAfterCharacter($string, $character) {
    $pos = strpos($string, $character);
    if ($pos !== false) {
        $string = substr($string, 0, $pos);
    }
    return $string;
}
function removeWhitespaceBetweenStrings($string) {
    $pattern = '/\s+/';
    $replacement = '';
    $result = preg_replace($pattern, $replacement, $string);
    return $result;
}

function 기업은행_문자_입금자명($inputString) {
    // 정규 표현식을 사용하여 "원"과 "017" 사이의 문자열을 추출합니다.
    preg_match('/원(.*?)017/', $inputString, $matches);

    if (!empty($matches[1])) {
        $extractedString = trim($matches[1]); // 공백 제거
        return $extractedString;
    } else {
        return "일치하는 문자열을 찾을 수 없습니다.";
    }
}

function 기업은행_문자_입금금액($inputString) {
    $pattern = '/(?:입금이|입금)\s*(\d+)/u';
    preg_match_all($pattern, $inputString, $matches);

    if (empty($matches[1])) {
        return "";
    }

    $extractedText = implode("", $matches[1]);
    return $extractedText;
}

function 신한_콤마전부제거($string) {
    return str_replace(',', '', $string);
}

function 신한_문자_금액만추출($message) {
  $pattern = '/입금\s*(\d+)\s*([\p{Hangul}]+)/u';
  preg_match($pattern, $message, $matches);

  if (isset($matches[1]) && isset($matches[2])) {
      $amount = intval($matches[1]); // 숫자 추출
      $description = $matches[2]; // 한글 추출

      return ['amount' => $amount, 'description' => $description];
  } else {
      return null;
  }
}







//KB
function 국민_계좌번호_제거후_문자내용($inputString, $pattern) {
    $outputString = preg_replace('/' . preg_quote($pattern, '/') . '/', '', $inputString);
    return $outputString;
}



function 국민_입금자명_옆_입금상태제거($inputString) {
    // 함수 내부에 키워드 정의
    $keywordsToRemove = array("전자금융", "간편이체", "스마트폰", "타행", "CD공동","오픈뱅킹");

    // 키워드를 순회하면서 해당 부분 제거
    foreach ($keywordsToRemove as $keyword) {
        $inputString = str_ireplace($keyword, '', $inputString);
    }

    return $inputString;
}


function 국민_문자열맨끝_숫자형식만추출($inputString) {
    // 정규식을 사용하여 문자열의 맨 끝에 있는 숫자형식 데이터 추출 (콤마 포함)
    preg_match('/[\d,]+$/', $inputString, $matches);

    // 추출된 숫자를 정리하여 반환, 없을 경우 기본값인 0 반환
    if (isset($matches[0])) {
        $numberString = str_replace(',', '', $matches[0]); // 콤마 제거
        return (int)$numberString;
    } else {
        return 0;
    }
}

function 국민_입금출금_금액잔액추출($inputString) {
    $result = array('type' => 'deposit', 'amount' => 0, 'balance' => 0);

    if (preg_match('/출금/u', $inputString)) {
        $result['type'] = 'withdrawal';
        if (preg_match('/출금([\d,]+)잔액([\d,]+)/u', $inputString, $matches)) {
            $result['amount'] = (int) str_replace(',', '', $matches[1]);
            $result['balance'] = (int) str_replace(',', '', $matches[2]);
            return $result;
        }
        if (preg_match('/출금([\d,]+)/u', $inputString, $matches)) {
            $result['amount'] = (int) str_replace(',', '', $matches[1]);
            return $result;
        }
    }

    if (preg_match('/입금([\d,]+)잔액([\d,]+)/u', $inputString, $matches)) {
        $result['amount'] = (int) str_replace(',', '', $matches[1]);
        $result['balance'] = (int) str_replace(',', '', $matches[2]);
        return $result;
    }

    if (preg_match('/입금([\d,]+)/u', $inputString, $matches)) {
        $result['amount'] = (int) str_replace(',', '', $matches[1]);
        return $result;
    }

    $result['amount'] = 국민_문자열맨끝_숫자형식만추출($inputString);
    return $result;
}

function 국민_입금자명만추출($inputString) {
    // 정규식을 사용하여 맨 앞의 2자리 숫자 제거
    $outputString = preg_replace('/^\d{2}/', '', $inputString);

    return $outputString;
}


function 문자열중간에소괄호체크($inputString) {
  $posParenthesis = strpos($inputString, '(');
  $posFBS = strpos($inputString, 'FBS');

  // 둘 중 어떤 것이 먼저 나오는지 확인
  if ($posParenthesis !== false && ($posFBS === false || $posParenthesis < $posFBS)) {
      // 소괄호가 먼저 나오는 경우
      return substr($inputString, 0, $posParenthesis);
  } elseif ($posFBS !== false && ($posParenthesis === false || $posFBS < $posParenthesis)) {
      // "FBS"가 먼저 나오는 경우, "FBS" 이전의 부분을 추출
      return substr($inputString, 0, $posFBS);
  }

  // 둘 다 없거나 동시에 나온 경우
  return $inputString;
}

function 하나은행20241113($message) {
    // "입금"을 기준으로 문자열을 나누기
    $parts = explode("입금", $message);

    // 금액과 이름이 포함된 부분 추출
    if (count($parts) < 2) {
        return ["amount" => null, "name" => null];
    }

    // 입금 이후 부분을 다루기
    $amountAndName = trim($parts[1]);

    // 첫 번째 "원"을 기준으로 나누기
    $splitByWon = explode("원", $amountAndName, 2);
    $amount = trim($splitByWon[0]); // 금액 부분
    $name = isset($splitByWon[1]) ? trim($splitByWon[1]) : null; // 이름 부분

    return ["amount" => $amount, "name" => $name];
}

//농협
function 농협_입금_원사이금액($message) {
    // "입금"과 "원" 사이의 금액을 정규식을 사용하여 추출
    $pattern = '/입금([0-9,]+)원/';
    preg_match($pattern, $message, $matches);

    if (isset($matches[1])) {
        // 추출된 금액에서 쉼표(,) 제거 후 정수로 변환
        $amount = intval(str_replace(',', '', $matches[1]));
        return $amount;
    } else {
        // 일치하는 패턴이 없는 경우 또는 금액 추출에 실패한 경우
        return null;
    }
}
function 농협_입금자명추출($message, $delimiter) {
  // 주어진 구분자를 기준으로 문자열을 분리
  $parts = explode($delimiter, $message);
  $입금자명 = explode(" ",$parts[1]);
  // 배열의 첫 번째 요소 반환
  return $입금자명[1];
}

function 우리_계좌번호부분_제외한문자열1($message,$계좌) {
    // 카드 번호 부분 찾기
    $cardNumberPosition = strpos($message, $계좌);

    // 카드 번호를 찾은 경우
    if ($cardNumberPosition !== false) {
        // 문자열에서 카드 번호 제외
        $result = substr_replace($message, '', $cardNumberPosition, strlen($계좌));
    } else {
        // 카드 번호를 찾지 못한 경우 원래 문자열 반환
        $result = $message;
    }

    return $result;
}
function 우리_입금뒤문자열만추출($input) {
  // '입금' 다음의 문자열을 찾음
   $depositPattern = '/입금\s*(.+)$/u';
   preg_match($depositPattern, $input, $textMatches);

   // 결과 반환
   $result = !empty($textMatches) ? trim($textMatches[1]) : '';

   return $result;
}
function 우리_추출_금액과_텍스트($text) {

    $data = explode("원", $text);

    // 반환할 연관 배열 생성
    $result = array(
        'amount' => $data[0],
        'name' => $data[1]
    );

    return $result;
}

function 수협_계좌번호기준으로분리($message, $계좌) {
    // 카드 번호 부분 찾기
    $cardNumberPosition = strpos($message, $계좌);
    // 카드 번호를 찾은 경우
    if ($cardNumberPosition !== false) {
        // 문자열에서 카드 번호 제외
        $result = explode($계좌, $message);
    } else {
        // 카드 번호를 찾지 못한 경우 원래 문자열 반환
        $result = $message;
    }

    return $result[1];
}
function 수협_문자열_금액_입금자추출1($string) {
    // 정규 표현식을 사용하여 "입금"과 "원" 사이의 숫자, 그리고 "원" 뒤의 텍스트를 추출
    if (preg_match('/입금(\d+)원(.*)/', $string, $matches)) {
        $amount = $matches[1]; // "입금"과 "원" 사이의 숫자
        $afterText = trim($matches[2]); // "원" 뒤의 텍스트, 앞뒤 공백 제거

        return [
          'name' => $afterText,
          'amount' => $amount
        ];
    }

    return null; // 매칭되지 않으면 null 반환
}
function 수협_입금자명_옆_입금상태제거($inputString) {
    // 함수 내부에 키워드 정의
    $keywordsToRemove = array("전자금융", "간편이체", "오픈뱅킹");

    // 키워드를 순회하면서 해당 부분 제거
    foreach ($keywordsToRemove as $keyword) {
        $inputString = str_ireplace($keyword, '', $inputString);
    }

    return $inputString;
}
function 기업은행_입금_이후문자열($input) {
  // 정규식 패턴
   $pattern = '/입금\s+(.+)/u';

   // 정규식을 사용하여 매칭된 부분을 찾음
   preg_match($pattern, $input, $matches);

   // 매칭된 부분이 있다면 반환, 없다면 빈 문자열 반환
   return isset($matches[1]) ? trim($matches[1]) : '';
}
function 기업은행_입금금액부분_추출($input) {
  // 첫 번째 '원' 이전의 문자열을 찾음
  $position = strpos($input, '원');

  if ($position !== false) {
      // '원' 이전의 문자열을 제거하고 나머지를 반환
      $result = substr($input, $position + 3);
      return $result;
  } else {
      // '원'이 없으면 원래 문자열을 그대로 반환
      return $input;
  }
}
function 기업은행_입금자명_추출($input, $identifier) {
    // 정규식 패턴
    $pattern = '/(.+)' . preg_quote($identifier, '/') . '/u';

    // 정규식을 사용하여 매칭된 부분을 찾음
    preg_match($pattern, $input, $matches);

    // 매칭된 부분이 있다면 반환, 없다면 빈 문자열 반환
    return isset($matches[1]) ? trim($matches[1]) : '';
}

function 기업은행_잔액부분_추출($message) {
    // 잔액 389,080원 / 잔액389,080원 모두 지원
    preg_match('/잔액\s*([\d,]+)원/u', $message, $balanceMatch);

    $balance = isset($balanceMatch[1]) ? $balanceMatch[1] . '원' : null;
    $rest = preg_replace('/잔액\s*[\d,]+원/u', '', $message);

    return [
        'balance' => $balance,
        'rest' => trim($rest)
    ];
}

function 텔레그램_봇토큰_형식인가($value) {
    return preg_match('/^\d+:[A-Za-z0-9_-]+$/', trim($value));
}

function 텔레그램_설정_파싱($api_url) {
    $api_url = trim($api_url);
    $bot_token = null;
    $chat_id = null;

    if ($api_url === '') {
        return array('bot_token' => null, 'chat_id' => null);
    }

    if (preg_match('#https?://api\.telegram\.org/bot([^/]+)/sendMessage#i', $api_url, $matches)) {
        $bot_token = $matches[1];
        if (preg_match('/[?&]chat_id=([^&]+)/', $api_url, $chat_matches)) {
            $chat_id = urldecode($chat_matches[1]);
        }
        return array('bot_token' => $bot_token, 'chat_id' => $chat_id);
    }

    $json = json_decode($api_url, true);
    if (is_array($json)) {
        if (!empty($json['bot_token'])) {
            $bot_token = trim($json['bot_token']);
        } else if (!empty($json['token'])) {
            $bot_token = trim($json['token']);
        }
        if (!empty($json['chat_id'])) {
            $chat_id = trim($json['chat_id']);
        }
        return array('bot_token' => $bot_token, 'chat_id' => $chat_id);
    }

    $parts = preg_split('/[\|\/,\n\r\t ]+/', $api_url, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($parts as $part) {
        $part = trim($part);
        if (텔레그램_봇토큰_형식인가($part)) {
            $bot_token = $part;
        } else if ($part !== '') {
            $chat_id = $part;
        }
    }

    if (count($parts) === 1) {
        if (텔레그램_봇토큰_형식인가($api_url)) {
            $bot_token = trim($api_url);
            $chat_id = null;
        } else if (preg_match('/^-?\d+$/', trim($api_url)) || preg_match('/^@\w/', trim($api_url))) {
            $chat_id = trim($api_url);
        }
    }

    return array('bot_token' => $bot_token, 'chat_id' => $chat_id);
}

function 텔레그램_메시지전송($bot_token, $chat_id, $text) {
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
        'chat_id' => $chat_id,
        'text' => $text,
    )));

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return array('ok' => false, 'description' => $error);
    }
    curl_close($ch);

    $result = json_decode($response, true);
    if (!is_array($result)) {
        return array('ok' => false, 'description' => $response);
    }

    if (empty($result['ok'])
        && !empty($result['parameters']['migrate_to_chat_id'])) {
        $new_chat_id = $result['parameters']['migrate_to_chat_id'];
        $retry = 텔레그램_메시지전송($bot_token, $new_chat_id, $text);
        if (!empty($retry['ok'])) {
            $retry['migrated_from_chat_id'] = $chat_id;
            $retry['migrated_to_chat_id'] = $new_chat_id;
        }
        return $retry;
    }

    return $result;
}

function 텔레그램_입금알림전송($api_url, $data) {
    $설정 = 텔레그램_설정_파싱($api_url);

    if (!$설정['bot_token']) {
        echo 'Telegram: bot token을 api_url에서 찾을 수 없습니다.<br />';
        return false;
    }
    if (!$설정['chat_id']) {
        echo 'Telegram: chat_id(그룹방 ID)를 api_url에서 찾을 수 없습니다. (예: bot_token|chat_id)<br />';
        return false;
    }

    $balance = isset($data['balance']) ? (int) $data['balance'] : 0;
    $amount = isset($data['amount']) ? (int) $data['amount'] : 0;
    $name = isset($data['name']) ? $data['name'] : '';
    $msg = isset($data['msg']) ? $data['msg'] : '';
    $type = (isset($data['type']) && $data['type'] === 'withdrawal') ? 'withdrawal' : 'deposit';

    if ($type === 'withdrawal') {
        $title = '출금알림';
        $name_label = '출금자';
    } else {
        $title = '입금알림';
        $name_label = '입금자';
    }

    $text = "{$title}\n";
    $text .= "{$name_label}: {$name}\n";
    $text .= "금액: " . number_format($amount) . "원\n";
    if ($balance) {
        $text .= "잔액: " . number_format($balance) . "원\n";
    }
    $text .= "\n원문:\n{$msg}";

    $result = 텔레그램_메시지전송($설정['bot_token'], $설정['chat_id'], $text);

    if (!empty($result['ok'])) {
        echo 'Telegram Response: OK<br />';
        if (!empty($result['migrated_to_chat_id'])) {
            echo 'Telegram: 그룹이 슈퍼그룹으로 변경됨. api_url chat_id를 <b>' . $result['migrated_to_chat_id'] . '</b> 로 업데이트해주세요.<br />';
        }
    } else {
        $desc = isset($result['description']) ? $result['description'] : 'unknown';
        echo 'Telegram Error: ' . $desc . '<br />';
        if (!empty($result['parameters']['migrate_to_chat_id'])) {
            $new_chat_id = $result['parameters']['migrate_to_chat_id'];
            echo 'Telegram: 새 chat_id = <b>' . $new_chat_id . '</b> (api_url에 반영해주세요)<br />';
        }
    }

    return $result;
}
