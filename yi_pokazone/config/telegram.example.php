<?php
/**
 * Telegram 관리자 알림 설정 예시
 *
 * 1) 복사: cp config/telegram.example.php config/telegram.php
 * 2) BotFather(@BotFather)에서 봇 생성 후 bot_token 입력
 * 3) 관리자 계정(또는 알림용 그룹)에서 봇과 대화를 시작한 뒤 chat_id 확인
 *    - 개인: 봇에게 /start 후 https://api.telegram.org/bot<토큰>/getUpdates 로 chat.id 확인
 *    - 그룹: 봇을 그룹에 추가 후 같은 방식으로 chat.id 확인 (보통 -100… 또는 -5… 형태)
 * 4) enabled 를 true 로 변경
 *
 * config/telegram.php 는 토큰이 들어가므로 저장소에 커밋하지 마세요.
 */
return [
    /** false 이면 알림을 보내지 않습니다 */
    'enabled'   => true,
    /** BotFather 에서 받은 봇 토큰 (예: 123456:ABC-DEF...) */
    'bot_token' => '8660497228:AAHl2Kin2cJDVeV8HmKEgvFdbOVBIEhwfwM',
    /**
     * 알림을 받을 chat_id 목록 (문자열 또는 숫자)
     * 여러 관리자에게 보내려면 배열에 추가하세요.
     */
    'chat_ids'  => [
        '-5009836796',
    ],
];
