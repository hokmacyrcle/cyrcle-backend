<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset OTP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .otp-container {
            background-color: #e9ecef;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
            margin: 20px 0;
        }
        .otp-code {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
            letter-spacing: 5px;
            padding: 10px;
            background-color: white;
            border-radius: 5px;
            display: inline-block;
            min-width: 200px;
        }
        .footer {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Password Reset Request</h1>
    </div>

    <p>Hello,</p>

    <p>You have requested to reset your password. Please use the following OTP (One-Time Password) to complete the password reset process:</p>

    <div class="otp-container">
        <div class="otp-code">{{ $otp }}</div>
    </div>

    <p><strong>This OTP will expire in 10 minutes.</strong></p>

    <div class="warning">
        <strong>Important:</strong> Never share this OTP with anyone. Our support team will never ask for this code.
    </div>

    <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>

    <p>Best regards,<br>
    Your Application Team</p>

    <div class="footer">
        <p>This is an automated email. Please do not reply to this message.</p>
        <p>&copy; {{ date('Y') }} Your Application. All rights reserved.</p>
    </div>
</body>
</html>
