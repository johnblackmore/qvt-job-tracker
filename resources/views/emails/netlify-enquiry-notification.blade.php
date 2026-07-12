<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Website Enquiry</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #334155; background: #f8fafc; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { background: #B45309; padding: 24px 28px; color: #fff; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 600; }
        .header p { margin: 4px 0 0; font-size: 13px; opacity: 0.9; }
        .body { padding: 28px; }
        .field { margin-bottom: 16px; }
        .field-label { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; margin-bottom: 2px; }
        .field-value { font-size: 14px; color: #1e293b; }
        .message-box { background: #f8fafc; border-left: 3px solid #B45309; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; border-radius: 0 4px 4px 0; white-space: pre-wrap; }
        .actions { margin-top: 24px; }
        .btn { display: inline-block; padding: 10px 20px; background: #B45309; color: #fff; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: 600; margin-right: 8px; }
        .btn-secondary { background: #0F766E; }
        .footer { background: #f8fafc; padding: 20px 28px; font-size: 12px; color: #64748b; text-align: center; }
        .footer a { color: #B45309; text-decoration: none; }
        hr { border: none; border-top: 1px solid #e2e8f0; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Website Enquiry</h1>
            <p>{{ $customer->name }} — {{ $customer->email }}</p>
        </div>
        <div class="body">
            <div class="field">
                <div class="field-label">Name</div>
                <div class="field-value">{{ $customer->name }}</div>
            </div>
            <div class="field">
                <div class="field-label">Email</div>
                <div class="field-value">{{ $customer->email }}</div>
            </div>
            @if($customer->phone)
                <div class="field">
                    <div class="field-label">Phone</div>
                    <div class="field-value">{{ $customer->phone }}</div>
                </div>
            @endif
            @if($customer->address)
                <div class="field">
                    <div class="field-label">Location</div>
                    <div class="field-value">{{ $customer->address }}</div>
                </div>
            @endif

            @php
                $data = $submission['data'] ?? [];
            @endphp
            @if(!blank($data['vanType'] ?? null))
                <div class="field">
                    <div class="field-label">Vehicle Type</div>
                    <div class="field-value">{{ $data['vanType'] }}</div>
                </div>
            @endif
            @if(!blank($data['services'] ?? null))
                <div class="field">
                    <div class="field-label">Service Required</div>
                    <div class="field-value">{{ $data['services'] }}</div>
                </div>
            @endif

            <hr>

            <div class="field-label">Project Details</div>
            <div class="message-box">{{ $enquiry->message }}</div>

            <div class="actions">
                <a href="{{ route('enquiries.edit', $enquiry) }}" class="btn">View Enquiry</a>
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-secondary">View Customer</a>
            </div>
        </div>
        <div class="footer">
            <p><strong>Quantock Van Tech</strong> — Job Tracker</p>
            <p><a href="{{ route('dashboard') }}">Go to Dashboard</a></p>
        </div>
    </div>
</body>
</html>
