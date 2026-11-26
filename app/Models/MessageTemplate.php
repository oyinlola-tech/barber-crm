<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $business_id
 * @property string $name
 * @property string $language
 * @property string $category
 * @property string|null $purpose
 * @property string $status
 * @property string|null $provider_status
 * @property string|null $provider_template_id
 * @property bool $is_enabled
 * @property list<array<string, mixed>>|null $components
 * @property list<string>|null $variable_map
 */
#[Fillable(['business_id', 'provider_template_id', 'name', 'language', 'category', 'purpose', 'status', 'provider_status', 'is_enabled', 'components', 'variable_map'])]
class MessageTemplate extends Model
{
    protected function casts(): array
    {
        return ['components' => 'array', 'variable_map' => 'array', 'is_enabled' => 'boolean'];
    }
}
