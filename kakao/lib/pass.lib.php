<?php

define('PBKDF2_COMPAT_HASH_ALGORITHM', 'SHA256');
define('PBKDF2_COMPAT_ITERATIONS', 12000);
define('PBKDF2_COMPAT_SALT_BYTES', 24);
define('PBKDF2_COMPAT_HASH_BYTES', 24);

function create_hash($password, $force_compat = false)
{
    // Generate the salt.

    if (function_exists('mcrypt_create_iv') && version_compare( PHP_VERSION, '7.2' , '<' ) ) {
        $salt = base64_encode(mcrypt_create_iv(PBKDF2_COMPAT_SALT_BYTES, MCRYPT_DEV_URANDOM));
    } elseif (@file_exists('/dev/urandom') && $fp = @fopen('/dev/urandom', 'r')) {
        $salt = base64_encode(fread($fp, PBKDF2_COMPAT_SALT_BYTES));
    } else {
        $salt = '';
        for ($i = 0; $i < PBKDF2_COMPAT_SALT_BYTES; $i += 2) {
            $salt .= pack('S', mt_rand(0, 65535));
        }
        $salt = base64_encode(substr($salt, 0, PBKDF2_COMPAT_SALT_BYTES));
    }

    // Determine the best supported algorithm and iteration count.

    $algo = strtolower(PBKDF2_COMPAT_HASH_ALGORITHM);
    $iterations = PBKDF2_COMPAT_ITERATIONS;
    if ($force_compat || !function_exists('hash_algos') || !in_array($algo, hash_algos())) {
        $algo = false;                         // This flag will be detected by pbkdf2_default()
        $iterations = round($iterations / 5);  // PHP 4 is very slow. Don't cause too much server load.
    }

    // Return format: algorithm:iterations:salt:hash

    $pbkdf2 = pbkdf2_default($algo, $password, $salt, $iterations, PBKDF2_COMPAT_HASH_BYTES);
    $prefix = $algo ? $algo : 'sha1';
    return $prefix . ':' . $iterations . ':' . $salt . ':' . base64_encode($pbkdf2);
}

function slow_equals($a, $b)
{
    $diff = strlen($a) ^ strlen($b);
    for($i = 0; $i < strlen($a) && $i < strlen($b); $i++) {
        $diff |= ord($a[$i]) ^ ord($b[$i]);
    }
    return $diff === 0;
}
function pbkdf2_default($algo, $password, $salt, $count, $key_length)
{
    // Sanity check.

    if ($count <= 0 || $key_length <= 0) {
        trigger_error('PBKDF2 ERROR: Invalid parameters.', E_USER_ERROR);
    }

    // Check if we should use the fallback function.

    if (!$algo) return pbkdf2_fallback($password, $salt, $count, $key_length);

    // Check if the selected algorithm is available.

    $algo = strtolower($algo);
    if (!function_exists('hash_algos') || !in_array($algo, hash_algos())) {
        if ($algo === 'sha1') {
            return pbkdf2_fallback($password, $salt, $count, $key_length);
        } else {
            trigger_error('PBKDF2 ERROR: Hash algorithm not supported.', E_USER_ERROR);
        }
    }

    // Use built-in function if available.

    if (function_exists('hash_pbkdf2')) {
        return hash_pbkdf2($algo, $password, $salt, $count, $key_length, true);
    }

    // Count the blocks.

    $hash_length = strlen(hash($algo, '', true));
    $block_count = ceil($key_length / $hash_length);

    // Hash it!

    $output = '';
    for ($i = 1; $i <= $block_count; $i++) {
        $last = $salt . pack('N', $i);                               // $i encoded as 4 bytes, big endian.
        $last = $xorsum = hash_hmac($algo, $last, $password, true);  // first iteration.
        for ($j = 1; $j < $count; $j++) {                            // The other $count - 1 iterations.
            $xorsum ^= ($last = hash_hmac($algo, $last, $password, true));
        }
        $output .= $xorsum;
    }

    // Truncate and return.

    return substr($output, 0, $key_length);
}
function validate_password($password, $hash)
{
    // Split the hash into 4 parts.

    $params = explode(':', $hash);
    if (count($params) < 4) return false;

    // Recalculate the hash and compare it with the original.

    $pbkdf2 = base64_decode($params[3]);
    $pbkdf2_check = pbkdf2_default($params[0], $password, $params[2], (int)$params[1], strlen($pbkdf2));
    return slow_equals($pbkdf2, $pbkdf2_check);
}

// 비밀번호 비교
function check_password($pass, $hash)
{
    if(defined('STRING_ENCRYPT_FUNCTION') && STRING_ENCRYPT_FUNCTION === 'create_hash') {
        return validate_password($pass, $hash);
    }

    $password = get_encrypt_string($pass);

    return ($password === $hash);
}
function sql_password($value)
{
    // mysql 4.0x 이하 버전에서는 password() 함수의 결과가 16bytes
    // mysql 4.1x 이상 버전에서는 password() 함수의 결과가 41bytes
    $row = sql_fetch(" select password('$value') as pass ");

    return $row['pass'];
}
function login_password_check($mb, $pass, $hash)
{
    global $g5;

    $mb_id = isset($mb['mb_id']) ? $mb['mb_id'] : '';

    if(!$mb_id)
        return false;

    return check_password($pass, $hash);
}

// 문자열 암호화
function get_encrypt_string($str)
{
    if(defined('STRING_ENCRYPT_FUNCTION') && STRING_ENCRYPT_FUNCTION) {
        $encrypt = call_user_func(STRING_ENCRYPT_FUNCTION, $str);
    } else {
        $encrypt = sql_password($str);
    }

    return $encrypt;
}
