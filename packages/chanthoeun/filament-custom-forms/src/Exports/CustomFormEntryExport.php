<?php

namespace Chanthoeun\FilamentCustomForms\Exports;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormField;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CustomFormEntryExport implements FromCollection, WithHeadings, WithMapping
{
    protected Collection $records;
    protected ?string $formId;
    protected Collection $fields;

    public function __construct(Collection $records, ?string $formId = null)
    {
        $this->records = $records;
        $this->formId = $formId;
        $this->fields = CustomFormField::query()
            ->when($this->formId, fn($query) => $query->where('custom_form_id', $this->formId))
            ->orderBy('sort')
            ->get();
    }

    public function collection()
    {
        return $this->records;
    }

    public function headings(): array
    {
        $headings = $this->fields->pluck('label')->toArray();
        if (empty($headings)) {
            $headings = $this->fields->pluck('name')->toArray();
        }

        $headings[] = __('filament-custom-forms::fcf.general.created_at');

        return $headings;
    }

    public function map($record): array
    {
        $data = $record->data ?? [];
        $row = [];

        foreach ($this->fields as $field) {
            $value = $data[$field->name] ?? '';
            
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $row[] = $value;
        }

        $row[] = $record->created_at ? $record->created_at->toDateTimeString() : '';

        return $row;
    }
}
