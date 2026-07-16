<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isNew ? 'New Email Enquiry' : 'New Reply on Enquiry' }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #334155; background: #f8fafc; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { background: #B45309; padding: 24px 28px; color: #fff; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 600; }
        .header p { margin: 4px 0 0; font-size: 13px; opacity: 0.9; }
        .body { padding: 28px; }
        .details { background: #f8fafc; border-radius: 6px; padding: 16px; margin-bottom: 20px; font-size: 13px; }
        .details dt { font-weight: 600; color: #64748b; margin-top: 8px; }
        .details dt:first-child { margin-top: 0; }
        .details dd { margin: 2px 0 0; color: #1e293b; }
        .preview { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin-bottom: 20px; font-size: 13px; color: #475569; white-space: pre-line; }
        .cta { text-align: center; margin: 24px 0; }
        .cta a { display: inline-block; background: #B45309; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; }
        .cta a:hover { background: #92400e; }
        .footer { background: #f8fafc; padding: 20px 28px; font-size: 12px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $isNew ? 'New Email Enquiry' : 'New Reply on Enquiry' }}</h1>
            <p>Quantock Van Tech — Staff Notification</p>
        </div>
        <div class="body">
            <div class="details">
                <dl>
                    <dt>From</dt>
                    <dd>{{ $customer->name ?? 'Unknown' }} &lt;{{ $customer->email ?? 'unknown' }}&gt;</dd>
                    <dt>Subject</dt>
                    <dd>{{ $subject }}</dd>
                    <dt>Enquiry</dt>
                    <dd>#{{ $enquiry->id }}{{ $enquiry->subject ? ' — '.$enquiry->subject : '' }}</dd>
                </dl>
            </div>

            <div class="preview">
                {{ $preview }}
            </div>

            <div class="cta">
                <a href="{{ $adminUrl }}">View in Staff Admin</a>
            </div>
        </div>
        <div class="footer">
            <p><strong>Quantock Van Tech</strong> — Staff Admin</p>
        </div>
    </div>
</body>
</html>
