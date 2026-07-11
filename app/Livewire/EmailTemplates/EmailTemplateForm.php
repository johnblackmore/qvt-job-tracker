<?php

namespace App\Livewire\EmailTemplates;

use App\Models\EmailTemplate;
use Illuminate\Support\Str;
use Livewire\Component;

class EmailTemplateForm extends Component
{
    public ?EmailTemplate $template = null;

    public string $name = '';

    public string $slug = '';

    public string $subject = '';

    public string $body_html = '';

    public string $body_text = '';

    public string $variables_json = '';

    public bool $is_active = true;

    public function mount(?int $templateId = null): void
    {
        if ($templateId) {
            $this->template = EmailTemplate::findOrFail($templateId);
            $this->name = $this->template->name;
            $this->slug = $this->template->slug;
            $this->subject = $this->template->subject;
            $this->body_html = $this->template->body_html;
            $this->body_text = $this->template->body_text ?? '';
            $this->variables_json = $this->template->variables_json ? json_encode($this->template->variables_json, JSON_PRETTY_PRINT) : '';
            $this->is_active = $this->template->is_active;
        }
    }

    public function updatedName(): void
    {
        if (! $this->template) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:email_templates,slug'.($this->template ? ','.$this->template->id : '')],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'body_text' => ['nullable', 'string'],
            'variables_json' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['variables_json'] = $this->variables_json
            ? json_decode($this->variables_json, true)
            : null;

        if ($this->template) {
            $this->template->update($validated);
        } else {
            EmailTemplate::create($validated);
        }

        $this->dispatch('notify', message: $this->template ? 'Template updated.' : 'Template created.', type: 'success');
        $this->redirect(route('email-templates.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.email-templates.email-template-form');
    }
}
