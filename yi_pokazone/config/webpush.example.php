<?php
/**
 * Web Push VAPID 설정 예시
 *
 * 1) 복사: cp config/webpush.example.php config/webpush.php
 * 2) 키 생성: php tools/gen_vapid_keys.php
 * 3) 아래 public_key / private_key / subject(연락용 mailto: 또는 https URL) 입력
 *
 * config/webpush.php 는 비밀키가 들어가므로 저장소에 커밋하지 마세요.
 */
return [
    'subject'     => 'mailto:admin@example.com',
    'public_key'  => '',
    'private_key' => '',
];
