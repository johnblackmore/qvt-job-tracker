<?php

namespace App\Models;

use Database\Factories\AiModelConfigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['label', 'provider', 'model', 'description', 'has_vision', 'input_price', 'output_price'])]
class AiModelConfig extends Model
{
    /** @use HasFactory<AiModelConfigFactory> */
    use HasFactory;
}
