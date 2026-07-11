<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        EmailTemplate::create([
            'name' => 'Quote Sent',
            'slug' => 'quote-sent',
            'subject' => 'Your Quote from Quantock Van Tech — {{ quote_reference }}',
            'body_html' => '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Helvetica Neue, Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #334155; background: #f8fafc; margin: 0; padding: 20px;">
<div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
  <div style="background: #059669; padding: 24px 28px; color: #fff;">
    <h1 style="margin: 0; font-size: 18px; font-weight: 600;">Quantock Van Tech</h1>
    <p style="margin: 4px 0 0; font-size: 13px; opacity: 0.9;">Your Quote — {{ quote_reference }}</p>
  </div>
  <div style="padding: 28px;">
    <p style="font-size: 15px; margin-bottom: 16px;">Hi {{ customer_name }},</p>
    <p>Please find your quote attached. If you have any questions or would like to discuss any of the items, just reply to this email.</p>
    <p style="margin-top: 16px; font-size: 15px; font-weight: 700; color: #047857;">Grand Total: {{ grand_total }}</p>
  </div>
  <div style="background: #f8fafc; padding: 20px 28px; font-size: 12px; color: #64748b; text-align: center;">
    <p><strong>Quantock Van Tech</strong> — Campervan Electrical Specialists</p>
    <p><a href="https://quantockvantech.com" style="color: #059669; text-decoration: none;">quantockvantech.com</a></p>
  </div>
</div>
</body>
</html>',
            'body_text' => "Hi {{ customer_name }},\n\nPlease find your quote attached. If you have any questions or would like to discuss any of the items, just reply to this email.\n\nGrand Total: {{ grand_total }}\n\nQuantock Van Tech\nhttps://quantockvantech.com",
            'variables_json' => ['quote_reference', 'customer_name', 'grand_total', 'valid_until'],
            'is_active' => true,
        ]);
    }
}
