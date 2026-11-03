<?php

namespace Chanthoeun\FilamentCustomForms\Exports;

use Chanthoeun\FilamentCustomForms\Models\CustomFormField;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CustomFormEntrySqlExport
{
    protected Collection $records;
    protected ?string $formId;
    protected string $tableName;
    protected Collection $fields;

    public function __construct(Collection $records, ?string $formId, string $tableName)
    {
        $this->records = $records;
        $this->formId = $formId;
        $this->tableName = $tableName;
        $this->fields = CustomFormField::query()
            ->when($this->formId, fn($query) => $query->where('custom_form_id', $this->formId))
            ->orderBy('sort')
            ->get();
    }

    public function generate(): string
    {
        if ($this->records->isEmpty()) {
            return "-- No records found to export.\n";
        }

        $sql = "-- Custom Form Export: {$this->tableName}\n";
        $sql .= "-- Generated at: " . now()->toDateTimeString() . "\n\n";

        $columns = $this->fields->pluck('name')->toArray();
        $columns[] = 'created_at';
        
        $quotedColumns = array_map(fn($col) => "`{$col}`", $columns);
        $columnList = implode(', ', $quotedColumns);

        foreach ($this->records as $record) {
            $data = $record->data ?? [];
            $values = [];

            foreach ($this->fields as $field) {
                $value = $data[$field->name] ?? null;
                $values[] = $this->quoteValue($value);
            }

            $values[] = $this->quoteValue($record->created_at ? $record->created_at->toDateTimeString() : null);

            $valueList = implode(', ', $values);
            $sql .= "INSERT INTO `{$this->tableName}` ({$columnList}) VALUES ({$valueList});\n";
        }

        return $sql;
    }

    protected function quoteValue($value): string
    {
        if (is_null($value)) {
            return "NULL";
        }

        if (is_numeric($value) && !is_string($value)) {
            return $value;
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $value = str_replace(["\\", "'"], ["\\\\", "''"], $value);

        return "'{$value}'";
    }
}
