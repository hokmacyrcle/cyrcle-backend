<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Verify your email</title></head>
<body style="margin:0;padding:24px;background:#F6F4EC;font-family:Arial,sans-serif;color:#01153E">
  <div style="max-width:480px;margin:0 auto;background:#fff;border-radius:16px;padding:28px">
    <h1 style="margin:0 0 12px;font-size:22px">Verify your email</h1>
    <p style="margin:0 0 20px;line-height:1.5">Hi{{ $firstName ? ' ' . $firstName : '' }}, enter this code in the Cyrcle app to confirm your email address.</p>
    <div style="font-size:34px;font-weight:bold;letter-spacing:8px;text-align:center;padding:16px;background:#E8E3CF;border-radius:12px">{{ $code }}</div>
    <p style="margin:20px 0 0;font-size:13px;color:#5B6478">The code expires in 10 minutes. If you did not create a Cyrcle account, you can ignore this email.</p>
  </div>
</body>
</html>
