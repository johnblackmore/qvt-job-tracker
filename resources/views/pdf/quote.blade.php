<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quote {{ $quote->reference_number }} — Quantock Van Tech</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            color: #334155;
            background: #fff;
        }
        .container { max-width: 700px; margin: 0 auto; padding: 40px; }
        .header { border-bottom: 2px solid #059669; padding-bottom: 20px; margin-bottom: 30px; }
        .header-top { display: flex; justify-content: space-between; align-items: flex-start; }
        .brand { display: flex; align-items: center; gap: 10px; }
        .brand-icon { width: 32px; height: 32px; background: #059669; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; }
        .brand-name { font-size: 18px; font-weight: 700; color: #0f172a; }
        .brand-tagline { font-size: 11px; color: #64748b; }
        .quote-meta { text-align: right; }
        .quote-ref { font-size: 14px; font-weight: 600; color: #0f172a; font-family: monospace; }
        .quote-date { font-size: 11px; color: #64748b; margin-top: 4px; }
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 6px;
        }
        .status-draft { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .status-sent { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
        .status-accepted { background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; }
        .status-declined { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }
        .status-expired { background: #fffbeb; color: #b45309; border: 1px solid #fef3c7; }

        .section { margin-bottom: 24px; }
        .section-title { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 8px; }
        .customer-details { font-size: 12px; }
        .customer-name { font-weight: 600; color: #0f172a; font-size: 13px; }
        .two-col { display: flex; gap: 40px; }
        .two-col > div { flex: 1; }

        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        thead th {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 10px;
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #64748b;
        }
        tbody td {
            padding: 10px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 12px;
            vertical-align: top;
        }
        tbody tr:last-child td { border-bottom: 1px solid #e2e8f0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .line-type {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .type-product { background: #eff6ff; color: #1d4ed8; }
        .type-labour { background: #fffbeb; color: #b45309; }
        .type-adhoc { background: #f1f5f9; color: #475569; }

        .totals { margin-top: 16px; width: 280px; margin-left: auto; }
        .totals-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 12px; }
        .totals-row.grand { border-top: 2px solid #059669; padding-top: 10px; margin-top: 4px; font-size: 14px; font-weight: 700; color: #047857; }

        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 10px; color: #94a3b8; text-align: center; }
        .footer a { color: #64748b; text-decoration: none; }
        .validity { background: #f8fafc; border-radius: 6px; padding: 10px 14px; font-size: 11px; color: #64748b; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        {{-- Header --}}
        <div class="header">
            <div class="header-top">
                <div class="brand">
                    <div class="brand-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                        </svg>
                    </div>
                    <div>
                        <div class="brand-name">Quantock Van Tech</div>
                        <div class="brand-tagline">Campervan Electrical Specialists</div>
                    </div>
                </div>
                <div class="quote-meta">
                    <div class="quote-ref">{{ $quote->reference_number }}</div>
                    <div class="quote-date">{{ $quote->created_at->format('d F Y') }}</div>
                    <span class="status-badge status-{{ $quote->status }}">{{ ucfirst($quote->status) }}</span>
                </div>
            </div>
        </div>

        {{-- Customer & Details --}}
        <div class="two-col section">
            <div>
                <div class="section-title">Quote To</div>
                <div class="customer-details">
                    <div class="customer-name">{{ $quote->customer->name }}</div>
                    @if($quote->customer->email)
                        <div>{{ $quote->customer->email }}</div>
                    @endif
                    @if($quote->customer->phone)
                        <div>{{ $quote->customer->phone }}</div>
                    @endif
                    @if($quote->customer->address)
                        <div style="margin-top:4px; white-space:pre-line;">{{ $quote->customer->address }}</div>
                    @endif
                </div>
            </div>
            <div>
                <div class="section-title">Quote Details</div>
                <div class="customer-details">
                    <div>Reference: <strong>{{ $quote->reference_number }}</strong></div>
                    <div>Date: {{ $quote->created_at->format('d F Y') }}</div>
                    @if($quote->valid_until)
                        <div>Valid until: {{ $quote->valid_until->format('d F Y') }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Line Items --}}
        <div class="section">
            <div class="section-title">Items</div>
            <table>
                <thead>
                    <tr>
                        <th style="width:40%">Description</th>
                        <th class="text-center">Type</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Unit</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($quote->lineItems as $item)
                        <tr>
                            <td>
                                {{ $item->description }}
                                @if($item->notes)
                                    <div style="font-size:10px; color:#94a3b8; margin-top:2px;">{{ $item->notes }}</div>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="line-type type-{{ $item->line_type === 'ad_hoc' ? 'adhoc' : $item->line_type }}">
                                    {{ str_replace('_', '-', $item->line_type) }}
                                </span>
                            </td>
                            <td class="text-right">{{ $item->quantity }}</td>
                            <td class="text-right">£{{ number_format($item->unit_retail_price, 2) }}</td>
                            <td class="text-right font-semibold">£{{ number_format($item->line_total_retail, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totals">
                <div class="totals-row">
                    <span>Subtotal</span>
                    <span>£{{ number_format($quote->total_retail, 2) }}</span>
                </div>
                <div class="totals-row">
                    <span>Labour</span>
                    <span>£{{ number_format($quote->labour_total, 2) }}</span>
                </div>
                <div class="totals-row grand">
                    <span>Grand Total</span>
                    <span>£{{ number_format($quote->grand_total, 2) }}</span>
                </div>
            </div>
        </div>

        @if($quote->valid_until)
            <div class="validity">
                This quote is valid until <strong>{{ $quote->valid_until->format('d F Y') }}</strong>. Please contact us to accept or discuss any changes.
            </div>
        @endif

        {{-- Footer --}}
        <div class="footer">
            <p><strong>Quantock Van Tech</strong> — Specialist campervan electrical installations in West Somerset</p>
            <p>https://quantockvantech.com | info@quantockvantech.com</p>
            <p style="margin-top:8px; font-size:9px;">All prices include VAT where applicable. Terms and conditions available on request.</p>
        </div>
    </div>
</body>
</html>
