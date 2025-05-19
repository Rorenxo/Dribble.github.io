<?php 
require 'vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$factory = (new Factory)
    ->withServiceAccount($_ENV['FIREBASE_CREDENTIALS_PATH']);

$auth = $factory->createAuth();
$database = $factory->createDatabase();

// Function to send custom verification email
function sendCustomVerificationEmail($email, $name, $verificationLink) {
    // Email subject
    $subject = "Verify Your Email Address for Dribble";
    
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Dribble <noreply@dribble.com>" . "\r\n";
    
    // Email template
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
            }
            .container {
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
            .header {
                background-color: #007bff;
                color: white;
                padding: 15px;
                text-align: center;
                border-radius: 5px 5px 0 0;
            }
            .content {
                padding: 20px;
            }
            .button {
                display: inline-block;
                background-color: #007bff;
                color: white;
                padding: 12px 25px;
                text-decoration: none;
                border-radius: 5px;
                margin: 20px 0;
                font-weight: bold;
            }
            .footer {
                font-size: 12px;
                color: #777;
                text-align: center;
                margin-top: 30px;
                padding-top: 10px;
                border-top: 1px solid #ddd;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Email Verification</h2>
            </div>
            <div class="content">
                <p>Hello ' . htmlspecialchars($name) . ',</p>
                <p>Thank you for registering with Dribble. To complete your registration and activate your account, please verify your email address by clicking the button below:</p>
                
                <div style="text-align: center;">
                    <a href="' . $verificationLink . '" class="button">Verify Email Address</a>
                </div>
                
                <p>If the button above doesn\'t work, you can also verify your email by copying and pasting the following link into your browser:</p>
                <p style="word-break: break-all;">' . $verificationLink . '</p>
                
                <p>This verification link will expire in 24 hours.</p>
                
                <p>If you did not create an account with Dribble, please ignore this email.</p>
                
                <p>Best regards,<br>The Dribble Team</p>
            </div>
            <div class="footer">
                <p>This is an automated email. Please do not reply to this message.</p>
                <p>&copy; ' . date('Y') . ' Dribble. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // Send email
    return mail($email, $subject, $message, $headers);
}

// Function to send password reset email
function sendPasswordResetEmail($auth, $email) {
    try {
        $auth->sendPasswordResetLink($email);
        return true;
    } catch (Exception $e) {
        error_log("Failed to send password reset email: " . $e->getMessage());
        return false;
    }
}
?>
