<?php
/**
 * `.버프` — info1·info2·info3·info 공통
 */

if (!function_exists('버프_명령_처리')) {
  /**
   * @param array $opts 버프_명령_응답 옵션 (예: ['마법라벨' => '마법(타수2배)'])
   * @return bool `.버프` 처리 시 true (내부에서 exit), 아니면 false
   */
  function 버프_명령_처리(string $status, string $두자리닉넴, array $관리자 = [], array $opts = []): bool {
    if (trim($status) !== '.버프') {
      return false;
    }
    if (!function_exists('버프_명령_응답')) {
      echo 전송('❌ 버프 기능을 불러올 수 없어요.');
      exit;
    }
    echo 전송(버프_명령_응답($두자리닉넴, $관리자, $opts));
    exit;
  }
}
