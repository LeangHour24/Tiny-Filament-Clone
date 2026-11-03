<?php

namespace Chanthoeun\FilamentDocumentBuilder\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'name',
        'type',
        'model_class',
        'content',
        'page_settings',
        'extra_data_sources',
    ];

    protected $casts = [
        'page_settings' => 'array',
        'extra_data_sources' => 'array',
    ];

    public function getContentAttribute(mixed $value): string
    {
        return self::contentFromStoredValue($value);
    }

    public function setContentAttribute(mixed $value): void
    {
        $this->attributes['content'] = self::contentFromStoredValue($value);
    }

    private static function contentFromStoredValue(mixed $value): string
    {
        $decoded = self::decodeStoredValue($value);

        if (is_array($decoded)) {
            foreach (['html', 'content'] as $key) {
                if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
                    return (string) $decoded[$key];
                }
            }

            foreach ($decoded as $item) {
                if (is_scalar($item)) {
                    return (string) $item;
                }
            }

            return '';
        }

        return (string) ($decoded ?? '');
    }

    private static function decodeStoredValue(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }

        for ($i = 0; $i < 3 && is_string($value); $i++) {
            $trimmed = trim($value);

            if ($trimmed === '' || ! in_array($trimmed[0], ['"', '{', '['], true)) {
                return $value;
            }

            $decoded = json_decode($trimmed, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $value;
            }

            $value = $decoded;
        }

        return $value;
    }
}
