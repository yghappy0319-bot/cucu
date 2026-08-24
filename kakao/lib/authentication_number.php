<?
function 신한_인증번호($message) {
    // "신한은행"과 "인증번호"가 모두 포함되어 있는지 확인
    if (strpos($message, '신한은행') !== false && strpos($message, '인증번호') !== false) {
        // 정규식 패턴 설정: [] 사이의 숫자 추출
        $pattern = '/인증번호\[(\d+)\]/';

        // 정규식으로 매칭된 부분을 찾습니다.
        if (preg_match($pattern, $message, $matches)) {
            // 매칭된 부분에서 인증번호를 반환합니다.
            return $matches[1]; // 인증번호만 반환
        }
    }
    // 조건을 만족하지 않거나 인증번호가 없는 경우 null 반환
    return null;
}

function 기업_인증번호($message) {
    // "기업은행"과 "인증번호"가 모두 포함되어 있는지 확인
    if (strpos($message, '기업은행') !== false && strpos($message, '인증번호') !== false) {
        // 정규식: 대괄호 안에 있는 6자리 숫자를 추출
        $pattern = '/\[(\d{6})\]/';

        if (preg_match($pattern, $message, $matches)) {
            return $matches[1];  // 6자리 인증번호 반환
        }
    }

    return null;  // 해당되지 않으면 null
}


function 농협_인증번호($message) {
    // "농협알림"과 "인증번호"가 모두 포함되어 있는지 확인
    if (strpos($message, '농협알림') !== false && strpos($message, '인증번호') !== false) {
        // 정규식 패턴 설정: [] 사이의 6자리 숫자 추출
        $pattern = '/\[([0-9]{6})\]/';

        // 정규식으로 매칭된 부분을 찾습니다.
        if (preg_match($pattern, $message, $matches)) {
            // 매칭된 부분에서 인증번호를 반환합니다.
            return $matches[1]; // 인증번호만 반환
        }
    }

    // 조건을 만족하지 않거나 인증번호가 없는 경우 null 반환
    return null;
}
function SC제일_인증번호($message) {
    // "농협알림"과 "인증번호"가 모두 포함되어 있는지 확인
    if (strpos($message, 'SC제일은행') !== false && strpos($message, '인증번호') !== false) {
        // 정규식 패턴 설정: [] 사이의 6자리 숫자 추출
        $pattern = '/\[([0-9]{6})\]/';

        // 정규식으로 매칭된 부분을 찾습니다.
        if (preg_match($pattern, $message, $matches)) {
            // 매칭된 부분에서 인증번호를 반환합니다.
            return $matches[1]; // 인증번호만 반환
        }
    }

    // 조건을 만족하지 않거나 인증번호가 없는 경우 null 반환
    return null;
}

function 우리_인증번호($message) {
    // "우리은행"과 "인증번호"가 모두 포함되어 있는지 확인
    if (strpos($message, '우리은행') !== false && strpos($message, '인증번호') !== false) {
        // 정규식 패턴 설정: [] 안의 4자리 숫자 또는 대괄호 없는 4자리 숫자 추출
        $pattern = '/(?:\[(\d{4})\]|\s(\d{4})\s)/';

        // 정규식으로 매칭된 부분을 찾습니다.
        if (preg_match($pattern, $message, $matches)) {
            // 대괄호가 있는 경우 matches[1], 없는 경우 matches[2]에 숫자가 있음
            return $matches[1] ? $matches[1] : $matches[2];
        }
    }

    // 조건을 만족하지 않거나 인증번호가 없는 경우 null 반환
    return null;
}


function 기업은행_계좌_마스킹($accountNumber) {
    // 하이픈 제거
    $cleanNumber = str_replace('-', '', $accountNumber);

    // 계좌 번호 길이 확인
    $numberLength = strlen($cleanNumber);

    // 계좌 번호가 예상 길이(14자리)인지 확인
    if ($numberLength !== 14) {
        return "잘못된 계좌 번호 형식입니다.";
    }

    // 마스킹 처리
    // 처음 3자리 + *** + 마지막 8자리
    $maskedNumber = substr($cleanNumber, 0, 3) . '***' . substr($cleanNumber, 6);

    return $maskedNumber;
}
function 농협은행_계좌_마스킹($accountNumber) {
    // 하이픈으로 문자열을 나누어 배열로 변환
    $parts = explode('-', $accountNumber);

    // 부분 배열의 개수가 4인지 확인
    if (count($parts) !== 4) {
        return "잘못된 계좌 번호 형식입니다.";
    }

    // 중간 부분을 '****'로 마스킹
    $parts[1] = '****';

    // 마스킹된 부분들을 하이픈으로 연결하여 최종 문자열 생성
    $maskedAccountNumber = implode('-', $parts);

    return $maskedAccountNumber;
}
function 신한은행_계좌_마스킹($number) {
    // 하이픈으로 문자열을 나누어 배열로 변환
    $parts = explode('-', $number);

    // 부분 배열의 개수가 3인지 확인
    if (count($parts) !== 3) {
        return "잘못된 번호 형식입니다.";
    }

    // 중간 부분을 '***'로 마스킹
    $parts[1] = '***';

    // 마스킹된 부분들을 하이픈으로 연결하여 최종 문자열 생성
    $maskedNumber = implode('-', $parts);

    return $maskedNumber;
}
function 국민은행_계좌_마스킹($accountNumber) {
    // 하이픈으로 문자열을 나누어 배열로 변환
    $parts = explode('-', $accountNumber);

    // 부분 배열의 개수가 3인지 확인
    if (count($parts) !== 3) {
        return "잘못된 계좌 번호 형식입니다.";
    }

    // 각 부분 추출
    $part1 = $parts[0]; // 573101
    $part2 = $parts[1]; // 01
    $part3 = $parts[2]; // 599077

    // 마스킹 처리
    $maskedNumber = $part1 . '**' . substr($part3, -3);

    return $maskedNumber;
}
function 우리은행_계좌_마스킹($accountNumber) {
    // 하이픈으로 문자열을 나누어 배열로 변환
    $parts = explode('-', $accountNumber);

    // 부분 배열의 개수가 3인지 확인
    if (count($parts) !== 3) {
        return "잘못된 계좌 번호 형식입니다.";
    }

    // 필요한 부분 추출
    $part3 = $parts[2]; // 세 번째 부분 (246717)

    // 마스킹 처리
    $maskedNumber = '*' . $part3;

    return $maskedNumber;
}
function 하나은행_계좌_마스킹($number) {
  // 하이픈 제거
  $cleanNumber = str_replace('-', '', $accountNumber);

  // 전체 문자열의 길이를 확인
  $length = strlen($cleanNumber);

  // 문자열의 길이가 적절한지 확인 (예상 길이: 14자리)
  if ($length !== 14) {
      return "잘못된 번호 형식입니다.";
  }

  // 앞부분 (첫 3자리), 마스킹 부분 (6자리), 뒷부분 (마지막 5자리)
  $start = substr($cleanNumber, 0, 3);   // "263"
  $masked = '******';                   // 6개의 '*'
  $end = substr($cleanNumber, -5);      // "89807"

  // 마스킹된 번호 조합
  $maskedNumber = $start . $masked . $end;

  return $maskedNumber;
}
function SC제일은행_계좌_마스킹($number) {
    // 하이픈 제거
    $cleanNumber = str_replace('-', '', $number);

    // 전체 문자열의 길이를 확인
    $length = strlen($cleanNumber);

    // 문자열의 길이가 적절한지 확인 (최소 4자리 이상)
    if ($length < 4) {
        return "잘못된 번호 형식입니다.";
    }

    // 마지막 4자리 추출
    $lastFourDigits = substr($cleanNumber, -4);

    return $lastFourDigits;
}
function 케이뱅크_계좌_마스킹($number) {
    // 하이픈 제거
    $cleanNumber = str_replace('-', '', $number);

    // 전체 문자열의 길이를 확인
    $length = strlen($cleanNumber);

    // 문자열의 길이가 적절한지 확인 (최소 4자리 이상)
    if ($length < 4) {
        return "잘못된 번호 형식입니다.";
    }

    // 마지막 4자리 추출
    $lastFourDigits = substr($cleanNumber, -4);

    return $lastFourDigits;
}


function 수협_인증번호($message) {
  // "농협알림"과 "인증번호"가 모두 포함되어 있는지 확인
  if (strpos($message, '수협') !== false && strpos($message, '인증번호') !== false) {
      // 정규식 패턴 설정: [] 사이의 6자리 숫자 추출
      $pattern = '/\[([0-9]{6})\]/';

      // 정규식으로 매칭된 부분을 찾습니다.
      if (preg_match($pattern, $message, $matches)) {
          // 매칭된 부분에서 인증번호를 반환합니다.
          return $matches[1]; // 인증번호만 반환
      }
  }

  // 조건을 만족하지 않거나 인증번호가 없는 경우 null 반환
  return null;
}

function 케이뱅크_인증번호($message) {
  // "농협알림"과 "인증번호"가 모두 포함되어 있는지 확인
  if (strpos($message, '케이뱅크') !== false && strpos($message, '인증번호') !== false) {
      // 정규식 패턴 설정: [] 사이의 6자리 숫자 추출
      $pattern = '/\[([0-9]{6})\]/';

      // 정규식으로 매칭된 부분을 찾습니다.
      if (preg_match($pattern, $message, $matches)) {
          // 매칭된 부분에서 인증번호를 반환합니다.
          return $matches[1]; // 인증번호만 반환
      }
  }

  // 조건을 만족하지 않거나 인증번호가 없는 경우 null 반환
  return null;
}
