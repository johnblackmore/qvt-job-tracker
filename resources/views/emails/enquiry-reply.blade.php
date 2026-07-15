<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #334155; background: #f8fafc; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { background: #B45309; padding: 24px 28px; color: #fff; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 600; }
        .header p { margin: 4px 0 0; font-size: 13px; opacity: 0.9; }
        .body { padding: 28px; }
        .message { font-size: 14px; color: #1e293b; line-height: 1.7; white-space: pre-line; }
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 24px 0; }
        .reference { background: #f8fafc; border-left: 3px solid #B45309; padding: 12px 16px; font-size: 12px; color: #64748b; border-radius: 0 4px 4px 0; margin-top: 24px; }
        .footer { background: #f8fafc; padding: 20px 28px; font-size: 12px; color: #64748b; text-align: center; }
        .footer a { color: #B45309; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Quantock Van Tech</h1>
            <p>Campervan Electrical Specialists</p>
        </div>
        <div class="body">
            <div class="message">
                {{ $body }}
            </div>

            <hr class="divider">

            <div class="reference">
                <p><strong>Quantock Van Tech</strong><br>
                Specialist supply &amp; fit campervan electrical systems<br>
                West Somerset</p>
            </div>
        </div>
        <div class="footer">
            <p><strong>Quantock Van Tech</strong></p>
            <p><a href="https://quantockvantech.com/">quantockvantech.com</a></p>
        </div>
    </div>
</body>
</html>
