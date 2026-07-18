<?php

namespace App\Models;

use Database\Factories\AiModelConfigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['label', 'provider', 'model', 'description', 'has_vision', 'api_type', 'supports_text', 'supports_audio', 'supports_file_uploads', 'input_price', 'output_price'])]
class AiModelConfig extends Model
{
    /** @use HasFactory<AiModelConfigFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'has_vision' => 'boolean',
            'supports_text' => 'boolean',
            'supports_audio' => 'boolean',
            'supports_file_uploads' => 'boolean',
        ];
    }

    public function resolvedProvider(): string
    {
        $apiType = $this->api_type ?? 'openai_compatible';

        if ($apiType === 'openai_compatible') {
            return $this->provider;
        }

        $typeSuffix = match ($apiType) {
            'openai' => '-openai',
            'anthropic' => '-anthropic',
            'google' => '-google',
            default => '',
        };

        if ($typeSuffix === '') {
            return $this->provider;
        }

        return $this->provider.$typeSuffix;
    }
}
