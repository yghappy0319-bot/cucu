<?php
/**
 * DirectSend SMS 설정 예시
 *
 * 1) 복사: cp config/directsend.example.php config/directsend.php
 * 2) DirectSend 마이페이지에서 API Key 발급·발신번호 등록 후 아래 값 입력
 *
 * config/directsend.php 는 API Key가 들어가므로 저장소에 커밋하지 마세요.
 */
return [
    /** DirectSend 로그인 아이디 */
    'username' => 'dudrhks0319',
    /** DirectSend API Key */
    'key'      => 'zjuT7SQ1FZTgMjn',
    /** DirectSend에 등록된 발신번호 (숫자만, 예: 01012345678) */
    'sender'   => '01022934444',
    /** SMS 제목 (DirectSend API title) */
    'title'    => '휴대폰 인증',
    /**
     * SMS 본문. [$NOTE1] 자리에 6자리 인증번호가 들어갑니다.
     * 예: [Pokazone] 인증번호는 [$NOTE1] 입니다. 3분 내 입력해 주세요.
     */
    'message'  => '[Pokazone] 휴대폰 변경 인증번호는 [$NOTE1] 입니다. 3분 내 입력해 주세요.',
];
