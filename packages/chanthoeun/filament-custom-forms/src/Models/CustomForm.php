<?php

namespace Chanthoeun\FilamentCustomForms\Models;

use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomForm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'custom_form_id',
        'workflow_template_id',
        'schema',
        'is_active',
        'allowed_roles',
        'panel_access',
        'menu_placement',
        'parent_sidebar',
        'sub_item_type',
        'sub_form_type',
        'display_order',
    ];

    protected $casts = [
        'schema' => 'array',
        'is_active' => 'boolean',
        'allowed_roles' => 'array',
        'panel_access' => 'array',
    ];

    protected static function booted(): void
    {
        static::created(function (CustomForm $customForm): void {
            if ($customForm->menu_placement === 'sidebar' && blank($customForm->custom_form_id)) {
                $customForm->forceFill([
                    'custom_form_id' => $customForm->id,
                    'parent_sidebar' => null,
                    'sub_item_type' => null,
                ])->saveQuietly();
            }
        });

        static::saving(function (CustomForm $customForm): void {
            if ($customForm->menu_placement === 'sidebar') {
                if ($customForm->exists) {
                    $customForm->custom_form_id = $customForm->id;
                }

                $customForm->parent_sidebar = null;
                $customForm->sub_item_type = null;
            }
        });
    }

    public function parentForm()
    {
        return $this->belongsTo(self::class, 'custom_form_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CustomFormEntry::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CustomFormField::class)->orderBy('sort');
    }

    public function workflowTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return self::localeText($this->name);
    }

    public static function localeText(mixed $value): string
    {
        $locale = app()->getLocale();

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return (string) (
                    $decoded[$locale]
                    ?? $decoded['km']
                    ?? $decoded['kh']
                    ?? $decoded['en']
                    ?? ''
                );
            }
        }

        if (is_array($value)) {
            return (string) (
                $value[$locale]
                ?? $value['km']
                ?? $value['kh']
                ?? $value['en']
                ?? ''
            );
        }

        return (string) $value;
    }
}
