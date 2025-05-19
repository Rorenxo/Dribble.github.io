<?php
session_start();

function generateCaptcha() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $captcha = '';
    $length = 6;
    
    for ($i = 0; $i < $length; $i++) {
        $captcha .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $captcha;
}

$captcha = generateCaptcha();
$_SESSION['captcha'] = $captcha;


echo $captcha;
?>
