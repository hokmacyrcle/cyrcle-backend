<?php

require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Mail;

echo "Testing Email Configuration...\n\n";

try {
    // Test basic email sending
    Mail::raw('This is a test email from Laravel ABS Systems', function($message) {
        $message->to('test@example.com')
                ->subject('Test Email from ABS Systems');
    });
    
    echo "✅ Email configuration is working!\n";
    echo "Check your email provider's sent folder or spam folder.\n\n";
    
} catch (Exception $e) {
    echo "❌ Email configuration error:\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    echo "Please check your .env file configuration:\n";
    echo "- MAIL_MAILER=smtp\n";
    echo "- MAIL_HOST=smtp.gmail.com (or your SMTP server)\n";
    echo "- MAIL_PORT=587\n";
    echo "- MAIL_USERNAME=your-email@gmail.com\n";
    echo "- MAIL_PASSWORD=your-app-password\n";
    echo "- MAIL_ENCRYPTION=tls\n";
    echo "- MAIL_FROM_ADDRESS=your-email@gmail.com\n";
    echo "- MAIL_FROM_NAME=\"ABS Systems\"\n\n";
    
    echo "For Gmail users:\n";
    echo "1. Enable 2-Factor Authentication\n";
    echo "2. Generate an App Password\n";
    echo "3. Use the App Password in MAIL_PASSWORD\n\n";
}

echo "To test OTP functionality, use:\n";
echo "curl -X POST http://127.0.0.1:8000/api/forgot-password/send-otp \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '{\"email\": \"your-email@example.com\"}'\n"; 