<?php

namespace App\Livewire\EmailTemplates;

use App\Models\EmailTemplate;
use Livewire\Component;
use Livewire\WithPagination;

class EmailTemplateList extends Component
{
    use WithPagination;

    public string $search = '';

    public function delete(int $id): void
    {
        EmailTemplate::find($id)?->delete();
    }

    public function render()
    {
        $templates = EmailTemplate::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('subject', 'like', "%{$this->search}%");
            })
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.email-templates.email-template-list', compact('templates'));
    }
}
