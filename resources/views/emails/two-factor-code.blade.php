<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GovSupply Connect Verification Code</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Good day {{ $user->name }},</p>

    <p>
        @if (($purpose ?? 'login') === 'email_change')
            Your GovSupply Connect verification code for confirming an email address change is:
        @else
            Your GovSupply Connect two-factor verification code is:
        @endif
    </p>

    <p style="font-size: 28px; font-weight: 700; letter-spacing: 6px; color: #b91c1c; margin: 16px 0;">{{ $code }}</p>

    <p>This code will expire in {{ $expiresInMinutes }} minutes.</p>

    <p>
        @if (($purpose ?? 'login') === 'email_change')
            If you did not request an email address change, please ignore this email and contact your system administrator.
        @else
            If you did not initiate this login attempt, please ignore this email and contact your system administrator.
        @endif
    </p>

    <p>Regards,<br>GovSupply Connect Security</p>
</body>
</html>
