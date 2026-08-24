<?php
/**
 * 지갑 웹 전용 — config.php 없이 양도 수수료만 제공
 */

if (!function_exists('양도수수료율')) {
    function 양도수수료율(int $level): float
    {
        return 0.01;
    }
}

if (!function_exists('양도수수료계산')) {
    function 양도수수료계산(int $level, int $양도금액, bool $지호마법적용 = false): int
    {
        if (function_exists('양도_수수료')) {
            return 양도_수수료($양도금액);
        }
        return (int)round($양도금액 * 0.01);
    }
}
