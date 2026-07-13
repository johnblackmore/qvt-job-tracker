<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'subject', 'body_html', 'body_text', 'variables_json', 'is_active'];

    protected $casts = [
        'variables_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function emailsSent(): HasMany
    {
        return $this->hasMany(EmailSent::class, 'template_id');
    }

    public function getVariablesAttribute(): array
    {
        return $this->variables_json ?? [];
    }

    public function render(array $data = []): array
    {
        $subject = $this->subject;
        $html = $this->body_html;
        $text = $this->body_text;

        foreach ($data as $key => $value) {
            $placeholder = '{{ '.$key.' }}';
            $subject = str_replace($placeholder, (string) $value, $subject);
            $html = str_replace($placeholder, (string) $value, $html);
            if ($text) {
                $text = str_replace($placeholder, (string) $value, $text);
            }
        }

        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }
}
