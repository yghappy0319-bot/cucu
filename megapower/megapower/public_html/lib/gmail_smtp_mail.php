<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // Composer로 설치했을 경우

function sendGmailSMTP($to, $toName, $subject, $htmlBody, $altBody = '') {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        // $mail->Username   = 'mepawor88@gmail.com';
        // $mail->Password   = 'vpujaswhjhxiblby'; // 앱 비밀번호
        $mail->Username   = 'megapowerworld1@gmail.com';
        $mail->Password   = 'lwdfpqvsoivsgtxs'; // 앱 비밀번호
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('mepawor88@gmail.com', 'Mega Power');
        $mail->addAddress($to, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "메일 전송 실패: " . $mail->ErrorInfo;
    }
}
