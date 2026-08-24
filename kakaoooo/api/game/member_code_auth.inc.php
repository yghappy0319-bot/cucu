<?php
/**
 * 홀짝웹·지갑웹 공통 tb_member.code 인증
 * (game_auth 와 동일 쿼리)
 */
if (!function_exists('member_code_auth')) {
    function member_code_auth($code) {
        if (function_exists('wallet_game_auth_row')) {
            return wallet_game_auth_row($code);
        }
        $code = is_string($code) ? trim($code) : '';
        if ($code === '') {
            return null;
        }
        $esc = addslashes($code);
        $row = db_select("SELECT idx, name, CAST(point AS CHAR) AS point FROM tb_member WHERE code = '{$esc}' LIMIT 1");
        if (empty($row['name'])) {
            return null;
        }
        return $row;
    }
}
