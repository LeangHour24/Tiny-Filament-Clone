<?php

namespace Chanthoeun\FilamentCustomForms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomFormField extends Model
{
    use HasFactory;


    protected $fillable = [
        'custom_form_id',
        'parent_id',
        'name',
        'label',
        'type',
        'required',
        'field_input',
        'options',
        'sort',
    ];

    protected $casts = [
        'required' => 'boolean',
        'field_input' => 'boolean',
        'options' => 'array',
        'sort' => 'integer',
    ];



    public function form()
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')->orderBy('sort');
    }
}
