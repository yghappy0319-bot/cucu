#!/usr/bin/env php
<?php
/**
 * VAPID 키 쌍 생성 → config/webpush.php 의 public_key / private_key에 붙여 넣기
 * 사용: php tools/gen_vapid_keys.php
 */
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$keys = Minishlink\WebPush\VAPID::createVapidKeys();

echo "config/webpush.php 예시:\n\n";
echo "return [\n";
echo "    'subject'     => 'mailto:회신받을@이메일',\n";
echo "    'public_key'  => " . var_export($keys['publicKey'], true) . ",\n";
echo "    'private_key' => " . var_export($keys['privateKey'], true) . ",\n";
echo "];\n";
