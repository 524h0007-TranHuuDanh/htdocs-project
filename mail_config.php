<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

function sendActivationEmail($toEmail, $displayName, $token)
{
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'trandanh020906@gmail.com';
        $mail->Password = 'jwlpqzuycmtidnli';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('trandanh020906@gmail.com', 'Note App');
        $mail->addAddress($toEmail, $displayName);

        $activationLink = "http://localhost/activate.php?token=" . $token;

        $mail->isHTML(true);
        $mail->Subject = 'Kích hoạt tài khoản Note App';

        $mail->Body = "
            <div style='font-family:Arial;line-height:1.6; max-width:600px;margin:auto;border:1px solid #eee;padding:20px;'>
                <h2 style='color:#0d6efd;'>Xin chào {$displayName}</h2>
                <p>Cảm ơn bạn đã đăng ký Note App.</p>
                <p>Click nút bên dưới để kích hoạt tài khoản:</p>
                <div style='text-align:center;margin:30px 0;'>
                    <a href='{$activationLink}' style='display:inline-block;padding:12px 30px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:5px;font-weight:bold;'>Kích hoạt ngay</a>
                </div>
            </div>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Activation Email Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Gửi OTP khôi phục mật khẩu
 */
function sendResetOTPEmail($toEmail, $displayName, $otp)
{
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'trandanh020906@gmail.com';
        $mail->Password = 'jwlpqzuycmtidnli';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->SMTPOptions = array(
            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)
        );

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('trandanh020906@gmail.com', 'Note App');
        $mail->addAddress($toEmail, $displayName);

        $mail->isHTML(true);
        $mail->Subject = 'Mã OTP khôi phục mật khẩu';

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px;border:1px solid #ddd;border-radius:8px;'>
                <h2 style='color:#0d6efd;'>Xin chào {$displayName}</h2>
                <p>Mã OTP của bạn là:</p>
                <h1 style='text-align:center;color:#0d6efd;letter-spacing:8px;'>{$otp}</h1>
                <p>Mã này có hiệu lực trong 15 phút.</p>
            </div>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Reset OTP Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Gửi Link Reset Password
 */
function sendResetLinkEmail($toEmail, $displayName, $reset_token)
{
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'trandanh020906@gmail.com';
        $mail->Password = 'jwlpqzuycmtidnli';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('trandanh020906@gmail.com', 'Note App');
        $mail->addAddress($toEmail, $displayName);

        $resetLink = "http://localhost/reset_password.php?token=" . $reset_token;

        $mail->isHTML(true);
        $mail->Subject = 'Đặt lại mật khẩu - Note App';

        $mail->Body = "
            <div style='font-family:Arial,sans-serif; max-width:600px; margin:auto; padding:25px; border:1px solid #ddd; border-radius:10px;'>
                <h2 style='color:#0d6efd;'>Xin chào {$displayName},</h2>
                <p>Bạn đã yêu cầu đặt lại mật khẩu cho tài khoản Note App.</p>
                <p>Vui lòng click vào nút bên dưới để đặt lại mật khẩu:</p>
                
                <div style='text-align:center; margin:35px 0;'>
                    <a href='{$resetLink}' 
                       style='display:inline-block; padding:14px 32px; background:#0d6efd; color:white; 
                              text-decoration:none; border-radius:6px; font-weight:bold; font-size:16px;'>
                        ĐẶT LẠI MẬT KHẨU
                    </a>
                </div>
                
                <p style='color:#666; font-size:14px;'>
                    Link này có hiệu lực trong <strong>15 phút</strong>.<br>
                    Nếu bạn không yêu cầu, vui lòng bỏ qua email này.
                </p>
            </div>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Reset Link Email Error: " . $e->getMessage());
        return false;
    }
}
/**
 * Gửi thông báo mật khẩu đã thay đổi
 */
function sendPasswordChangedNotification($toEmail, $displayName)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'trandanh020906@gmail.com';
        $mail->Password = 'jwlpqzuycmtidnli';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('trandanh020906@gmail.com', 'Note App');
        $mail->addAddress($toEmail, $displayName);

        $mail->isHTML(true);
        $mail->Subject = 'Thông báo: Mật khẩu tài khoản Note App đã được thay đổi';

        $mail->Body = "
            <div style='font-family:Arial,sans-serif; max-width:600px; margin:auto; padding:20px; border:1px solid #ddd; border-radius:8px;'>
                <h2 style='color:#0d6efd;'>Xin chào {$displayName},</h2>
                <p>Mật khẩu tài khoản Note App của bạn vừa được thay đổi thành công.</p>
                <p>Nếu bạn không thực hiện thay đổi này, vui lòng liên hệ ngay với chúng tôi.</p>
                <p>Trân trọng,<br>Đội ngũ Note App</p>
            </div>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log("Password Changed Notification Error: " . $e->getMessage());
    }
}
/**
 * Gửi email thông báo khi note được chia sẻ (Better Approach)
 */
function sendShareNotification($toEmail, $displayName, $sharerName, $noteTitle)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'trandanh020906@gmail.com';
        $mail->Password = 'jwlpqzuycmtidnli';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('trandanh020906@gmail.com', 'Note App');
        $mail->addAddress($toEmail, $displayName);

        $mail->isHTML(true);
        $mail->Subject = 'Bạn được chia sẻ một ghi chú';

        $mail->Body = "
            <div style='font-family:Arial,sans-serif; max-width:600px; margin:auto; padding:25px; border:1px solid #ddd; border-radius:10px;'>
                <h2 style='color:#0d6efd;'>Xin chào {$displayName},</h2>
                <p><strong>{$sharerName}</strong> đã chia sẻ một ghi chú với bạn:</p>
                <p style='font-size:18px; font-weight:600; color:#333;'>📝 {$noteTitle}</p>
                <p>Bạn có thể xem ngay trong phần <strong>Được chia sẻ</strong>.</p>
                <p style='color:#666;'>Trân trọng,<br>Đội ngũ Note App</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Share Notification Error: " . $e->getMessage());
        return false;
    }
}