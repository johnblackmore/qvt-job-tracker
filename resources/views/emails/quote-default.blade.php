<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $quote->reference_number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #334155; background: #f8fafc; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { background: #B45309; padding: 24px 28px; color: #fff; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 600; }
        .header p { margin: 4px 0 0; font-size: 13px; opacity: 0.9; }
        .body { padding: 28px; }
        .greeting { font-size: 15px; margin-bottom: 16px; }
        .message-box { background: #f8fafc; border-left: 3px solid #B45309; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; border-radius: 0 4px 4px 0; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 13px; }
        th { text-align: left; padding: 8px; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #64748b; font-size: 11px; text-transform: uppercase; }
        td { padding: 8px; border-bottom: 1px solid #f1f5f9; }
        .text-right { text-align: right; }
        .total-row { font-weight: 700; font-size: 15px; color: #115E59; border-top: 2px solid #B45309; border-bottom: none; }
        .footer { background: #f8fafc; padding: 20px 28px; font-size: 12px; color: #64748b; text-align: center; }
        .footer a { color: #B45309; text-decoration: none; }
        .validity { background: #fffbeb; border: 1px solid #fef3c7; padding: 10px 14px; border-radius: 6px; font-size: 12px; color: #92400e; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Quantock Van Tech</h1>
            <p>Your Quote — {{ $quote->reference_number }}</p>
        </div>
        <div class="body">
            <p class="greeting">Hi {{ $quote->customer->name }},</p>

            @if($customMessage)
                <div class="message-box">{{ $customMessage }}</div>
            @else
                <p>Please find your quote attached. If you have any questions or would like to discuss any of the items, just reply to this email.</p>
            @endif

            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Unit</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {!! $linesHtml !!}
                    <tr class="total-row">
                        <td colspan="3">Grand Total</td>
                        <td class="text-right">£{{ number_format($quote->grand_total, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            @if($quote->valid_until)
                <div class="validity">
                    This quote is valid until <strong>{{ $quote->valid_until->format('d F Y') }}</strong>.
                </div>
            @endif
        </div>
        <div class="footer">
            <p><strong>Quantock Van Tech</strong> — Campervan Electrical Specialists</p>
            <p><a href="https://quantockvantech.com">quantockvantech.com</a></p>
        </div>
    </div>
</body>
</html>
