<?php

namespace Chanthoeun\FilamentCustomForms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Chanthoeun\FilamentCustomForms\CustomFormPlugin;

class CustomFormEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'custom_form_id',
        'source_entry_id',
        'data',
        'created_by',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function customForm(): BelongsTo
    {
        return $this->belongsTo(CustomFormPlugin::get()->getFormModel());
    }

    public function creator(): BelongsTo
    {
        $userModel = CustomFormPlugin::get()->getUserModel();

        if (!class_exists($userModel)) {
            return $this->belongsTo(\Illuminate\Database\Eloquent\Model::class, 'created_by')->withDefault([
                'name' => 'Guest',
            ]);
        }

        return $this->belongsTo($userModel, 'created_by')->withDefault([
            'name' => 'Guest',
        ]);
    }

    public function sourceEntry()
    {
        return $this->belongsTo(self::class, 'source_entry_id');
    }

    public function copiedEntries()
    {
        return $this->hasMany(self::class, 'source_entry_id');
    }
}
