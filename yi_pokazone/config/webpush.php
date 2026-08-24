<?php
/**
 * Web Push VAPID — 실 서버용
 * php tools/gen_vapid_keys.php 로 키 생성 후 채워 넣으세요.
 */
return [
    'subject'     => getenv('WEBPUSH_SUBJECT') ?: 'mailto:dudrhks0319@gmail.com',
    'public_key'  => getenv('WEBPUSH_PUBLIC_KEY') ?: 'BJxM-b_4uGeMTJtRbjnZp5zMap2-0iZdBAaTgfeGzRQxKtBZ_XrW5KAS6-MFSicStRYfWJPCTnvfF6PVS-pnh_Y',
    'private_key' => getenv('WEBPUSH_PRIVATE_KEY') ?: 'dE7onCseMoxpkFlZL7ktH6bnI411d8NH_6eMQJz_Tfo',
];
