<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Tables;

use App\Support\CustomFormTemplateData;
use App\Filament\Support\RoleAccess;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentCustomForms\Models\CustomFormField;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Tables\Actions\DownloadPdfAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use App\Models\GeoLocation;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class CustomFormEntriesTable
{
    public static function configure(Table $table): Table
    {
        $formId = self::getFormId($table);

        return $table
            ->columns(self::getColumns($formId))
            ->filters(self::getFilters($formId))
            ->recordActions(self::getRecordActions())
            ->modifyQueryUsing(fn(Builder $query) => self::applyQueryConstraints($query, $formId));
    }

    protected static function getFormId(Table $table): ?string
    {
        $livewire = $table->getLivewire();

        return data_get($livewire, 'tableFilters.custom_form_id.value')
            ?? data_get($livewire, 'activeFormId')
            ?? request()->input('tableFilters.custom_form_id.value')
            ?? data_get(request()->query('tableFilters'), 'custom_form_id.value')
            ?? request()->query('form_id');
    }

    protected static function getColumns(?string $formId): array
    {
        $columns = [
            TextColumn::make('row_number')
                ->label('No')
                ->state(fn ($rowLoop): int => $rowLoop->iteration)
                ->alignCenter()
                ->width('60px')
                ->sortable(false)
                ->searchable(false)
                ->toggleable(false),
        ];

        $formSlug = $formId ? CustomForm::query()->whereKey($formId)->value('slug') : null;

        $fieldsMetadata = CustomFormField::query()
            ->when($formId, fn ($query) => $query->where('custom_form_id', $formId))
            ->orderBy('sort')
            ->get()
            ->keyBy('name');

        $definedKeys = $fieldsMetadata->keys();

        $recentEntries = CustomFormEntry::query()
            ->when($formId, fn ($query) => $query->where('custom_form_id', $formId))
            ->latest()
            ->limit(20)
            ->get();

        $arrayDataKeys = $recentEntries
            ->flatMap(function ($entry): array {
                if (! is_array($entry->data)) {
                    return [];
                }

                return collect($entry->data)
                    ->filter(fn ($value): bool => is_array($value))
                    ->keys()
                    ->all();
            })
            ->unique()
            ->values();

        $dataKeys = $recentEntries
            ->flatMap(function ($entry): array {
                if (! is_array($entry->data)) {
                    return [];
                }

                return collect($entry->data)
                    ->reject(fn ($value): bool => is_array($value))
                    ->keys()
                    ->all();
            })
            ->unique();

        $keys = $definedKeys->merge($dataKeys)->unique();

        $sortOrder = $fieldsMetadata->pluck('sort', 'name');
        $fieldTypes = $fieldsMetadata->pluck('type', 'name');
        $fieldOptions = $fieldsMetadata->pluck('options', 'name');
        $fieldsById = $fieldsMetadata->keyBy('id');

        $sortedKeys = $keys->sortBy(fn ($key) => $sortOrder[$key] ?? 999999);

        /*
         |--------------------------------------------------------------------------
         | Clean dynamic table
         |--------------------------------------------------------------------------
         | Show only important fields in the list table.
         | Everything else is still available from the "View All" action modal.
         */
        $displayKeys = collect($sortedKeys)
            ->filter(function ($key) use ($arrayDataKeys, $fieldTypes, $fieldsMetadata, $fieldsById): bool {
                if ($arrayDataKeys->contains($key)) {
                    return false;
                }

                if (self::isContainerFieldType($fieldTypes[$key] ?? null)) {
                    return false;
                }

                $field = $fieldsMetadata[$key] ?? null;

                if ($field && $field->parent_id) {
                    $parent = $fieldsById[$field->parent_id] ?? null;

                    if ($parent && self::normalizeFieldType($parent->type ?? '') === 'repeater') {
                        return false;
                    }
                }

                return true;
            })
            ->sortByDesc(fn ($key): int => self::tablePriorityScore((string) $key, $fieldsMetadata[$key] ?? null))
            ->take(self::getMaxTableDynamicColumns())
            ->sortBy(fn ($key) => $sortOrder[$key] ?? 999999)
            ->values();

        foreach ($displayKeys as $key) {
            $field = $fieldsMetadata[$key] ?? null;
            $columnKey = "data.{$key}";
            $label = self::translateFieldLabel($field, $key, $formSlug);

            $column = TextColumn::make($columnKey)
                ->label($label)
                ->state(function (CustomFormEntry $record) use ($key) {
                    $state = data_get($record->data, $key);

                    return is_array($state) ? null : $state;
                })
                ->formatStateUsing(fn ($state) => self::formatTableFieldState(
                    fieldName: $key,
                    state: $state,
                    fieldType: $fieldTypes[$key] ?? null,
                    options: $fieldOptions[$key] ?? null,
                    formSlug: $formSlug
                ))
                ->limit(35)
                ->tooltip(fn (TextColumn $column): ?string => self::isFileFieldType($fieldTypes[$key] ?? null) ? null : (filled($column->getState()) ? (string) $column->getState() : null))
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: false);

            if (self::isFileFieldType($fieldTypes[$key] ?? null)) {
                $column
                    ->formatStateUsing(fn ($state): string => blank($state) ? '-' : 'Download file')
                    ->url(fn (CustomFormEntry $record): ?string => self::fileUrlForState(data_get($record->data, $key)))
                    ->openUrlInNewTab();
            }

            if (self::normalizeFieldType($fieldTypes[$key] ?? '') === 'number_input') {
                $column->numeric();
            }

            if (in_array(self::normalizeFieldType($fieldTypes[$key] ?? ''), ['money', 'money_input'], true)) {
                $currency = $fieldOptions[$key]['currency'] ?? 'USD';
                $column->money(strtoupper($currency));
            }

            if (self::normalizeFieldType($fieldTypes[$key] ?? '') === 'time_picker') {
                $column->time();
            }

            $columns[] = $column;
        }

        $columns[] = TextColumn::make('customForm.name')
            ->label('Form')
            ->formatStateUsing(fn (?string $state): string => $state ?: '-')
            ->badge()
            ->color('gray')
            ->toggleable(isToggledHiddenByDefault: true);

        $columns[] = TextColumn::make('created_at')
            ->label(__('filament-custom-forms::fcf.general.created_at'))
            ->since()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);

        $columns[] = TextColumn::make('updated_at')
            ->label('Updated')
            ->since()
            ->sortable();

        return $columns;
    }

    protected static function getFilters(?string $formId): array
    {
        $filters = [];

        if ($formId) {
            $formSchema = CustomForm::find($formId);
            if ($formSchema) {
                $schemaFields = $formSchema->fields()->orderBy('sort')->get();

                foreach ($schemaFields as $field) {
                    $jsonKey = $field->name;
                    $label = self::translateFieldLabel($field, $field->name, $formSchema->slug);

                    switch ($field->type) {
                        case 'boolean':
                            $filters[] = TernaryFilter::make($field->name)
                                ->label($label)
                                ->query(
                                    fn(Builder $query, array $data) =>
                                    $query->when(
                                        isset($data['value']),
                                        fn($q) => $q->where("data->{$jsonKey}", $data['value'] === '1' || $data['value'] === true)
                                    )
                                );
                            break;

                        case 'select':
                            $choices = self::getChoiceOptions($field->options ?? []);
                            if (!empty($choices)) {
                                $filters[] = SelectFilter::make($field->name)
                                    ->label($label)
                                    ->options($choices)
                                    ->query(
                                        fn(Builder $query, array $data) =>
                                        $query->when(
                                            $data['value'],
                                            fn($q) => $q->where("data->{$jsonKey}", $data['value'])
                                        )
                                    );
                            }
                            break;

                        case 'date_picker':
                            $filters[] = Filter::make($field->name)
                                ->label($label)
                                ->form([
                                    DatePicker::make('from')
                                        ->label($label . ' From')
                                        ->native(false)
                                        ->displayFormat('d-m-Y')
                                        ->format('Y-m-d'),
                                    DatePicker::make('until')
                                        ->label($label . ' Until')
                                        ->native(false)
                                        ->displayFormat('d-m-Y')
                                        ->format('Y-m-d'),
                                ])
                                ->query(function (Builder $query, array $data) use ($jsonKey) {
                                    return $query
                                        ->when($data['from'], fn($q) => $q->where("data->{$jsonKey}", '>=', $data['from']))
                                        ->when($data['until'], fn($q) => $q->where("data->{$jsonKey}", '<=', $data['until']));
                                });
                            break;
                    }
                }
            }
        }

        $filters[] = SelectFilter::make('custom_form_id')
            ->label(__('filament-custom-forms::fcf.form.single'))
            ->options(CustomForm::query()->get()->mapWithKeys(
                fn(CustomForm $form): array => [$form->id => self::translateFormName($form)]
            ))
            ->hidden();

        return $filters;
    }

    protected static function getRecordActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('view_all')
                    ->label('View All')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->authorize(fn (): bool => RoleAccess::hasSystemPermission('ViewAll:CustomFormEntry'))
                    ->modalHeading(fn (CustomFormEntry $record): string => 'View All - ' . self::translateFormName($record->customForm ?? CustomForm::find($record->custom_form_id)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('7xl')
                    ->modalContent(fn (CustomFormEntry $record): HtmlString => self::makeViewAllModalContent($record)),

                Action::make('export_resume_pdf')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->authorize(fn (): bool => RoleAccess::hasSystemPermission('ExportResumePdf:CustomFormEntry'))
                    ->visible(fn ($record): bool => filled(self::getPdfRouteName($record)))
                    ->url(fn ($record): string => route(self::getPdfRouteName($record), $record))
                    ->openUrlInNewTab(),

                DownloadPdfAction::make('download_template_pdf')
                    ->label('Download')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->authorize(fn (): bool => RoleAccess::hasSystemPermission('DownloadTemplatePdf:CustomFormEntry'))
                    ->visible(fn (): bool => class_exists(DocumentTemplate::class))
                    ->templateType(fn (CustomFormEntry $record): string => 'custom_form_' . $record->custom_form_id)
                    ->filename(fn (CustomFormEntry $record): string => 'application-' . $record->id . '.pdf'),

                Action::make('edit_entry')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (CustomFormEntry $record): bool => CustomFormEntryResource::canEdit($record))
                    ->url(fn (CustomFormEntry $record): string => CustomFormEntryResource::getUrl('edit', ['record' => $record])),

                DeleteAction::make()
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation(),
            ])
                ->label('')
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip('Actions')
                ->iconButton(),
        ];
    }

    protected static function getActiveFormIdFromRequest(): ?int
    {
        $formId = request()->input('tableFilters.custom_form_id.value')
            ?? data_get(request()->query('tableFilters'), 'custom_form_id.value')
            ?? request()->query('form_id');

        return $formId ? (int) $formId : null;
    }

    protected static function getResumeFormId(): ?int
    {
        static $resumeFormId = null;

        if ($resumeFormId === null) {
            $resumeFormId = CustomForm::query()
                ->whereIn('slug', ['profile', 'profile-form', 'resume-form', 'student_profile', 'student-profile'])
                ->value('id');
        }

        return $resumeFormId ? (int) $resumeFormId : null;
    }

    protected static function getPdfRouteName($record): ?string
    {
        $slug = $record->customForm?->slug ?? self::getFormSlug($record->custom_form_id);

        return match ($slug) {
            'enrollment' => 'enrollment.pdf',
            'profile', 'profile-form', 'resume-form', 'student_profile', 'student-profile' => 'student-profile.pdf',
            'specialty-exam-form', 'national-exam', 'print-application', 'National Exam', 'Print Application' => 'national-exam.pdf',
            default => 'custom-form-entry.pdf',
        };
    }

    protected static function applyQueryConstraints(Builder $query, ?string $formId): Builder
    {
        return $query->with(['creator', 'customForm'])
            ->when($formId, fn($q, $id) => $q->where('custom_form_id', $id));
    }

    protected static function translateFieldLabel(?CustomFormField $field, string $key, ?string $formSlug = null): string
    {
        $formSlug ??= $field ? self::getFormSlug($field->custom_form_id) : null;

        foreach (self::getTranslationFormSlugs($formSlug) as $slug) {
            foreach (self::getTranslationFieldNames($key) as $translationFieldName) {
                $translationKey = "filament-custom-forms::fcf.form.fields.{$slug}.{$translationFieldName}";
                $translation = __($translationKey);

                if ($translation !== $translationKey) {
                    return $translation;
                }
            }
        }

        return $field->label ?? Str::headline($key);
    }

    protected static function translateFormName(CustomForm $form): string
    {
        foreach (self::getTranslationFormSlugs($form->slug) as $slug) {
            $translationKey = "filament-custom-forms::fcf.form.names.{$slug}";
            $translation = __($translationKey);

            if ($translation !== $translationKey) {
                return $translation;
            }
        }

        return $form->name;
    }

    protected static function translateFieldState(string $fieldName, mixed $state, ?array $options = null, ?string $formSlug = null): mixed
    {
        if (blank($state)) {
            return $state;
        }

        if (is_array($state)) {
            if (! array_is_list($state) || collect($state)->contains(fn($value): bool => is_array($value))) {
                return '';
            }

            return collect($state)
                ->map(fn($value) => self::translateFieldState($fieldName, $value, $options, $formSlug))
                ->implode(', ');
        }

        if ($formSlug && is_scalar($state)) {
            foreach (self::getTranslationFormSlugs($formSlug) as $slug) {
                foreach (self::getTranslationFieldNames($fieldName) as $translationFieldName) {
                    $translationKey = "filament-custom-forms::fcf.form.options.{$slug}.{$translationFieldName}.{$state}";
                    $translation = __($translationKey);

                    if ($translation !== $translationKey) {
                        return $translation;
                    }
                }
            }
        }

        foreach (self::getChoiceOptions($options ?? []) as $choiceKey => $choice) {
            $value = is_array($choice) ? ($choice['value'] ?? $choiceKey) : $choiceKey;

            if ((string) $value === (string) $state) {
                return is_array($choice) ? ($choice['label'] ?? $state) : $choice;
            }
        }

        if (self::isLocationField($fieldName, $options) && is_scalar($state)) {
            return self::translateLocationCode((string) $state);
        }

        return $state;
    }

    protected static function formatTableFieldState(
        string $fieldName,
        mixed $state,
        ?string $fieldType = null,
        ?array $options = null,
        ?string $formSlug = null
    ): mixed {
        if (self::isDateFieldType($fieldType)) {
            return self::formatDateValue($state);
        }

        $displayLabel = CustomFormTemplateData::displayLabelForField(
            fieldName: $fieldName,
            rawValue: $state,
            fieldType: $fieldType,
            options: $options,
        );

        if (filled($displayLabel)) {
            return $displayLabel;
        }

        return self::translateFieldState($fieldName, $state, $options, $formSlug);
    }

    protected static function isDateFieldType(?string $fieldType): bool
    {
        return in_array(self::normalizeFieldType($fieldType), ['date', 'date_picker'], true);
    }

    protected static function isFileFieldType(?string $fieldType): bool
    {
        return in_array(self::normalizeFieldType($fieldType), ['file', 'file_upload', 'fileupload', 'document', 'attachment'], true);
    }

    protected static function fileUrlForState(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        if (is_array($state)) {
            $state = collect($state)
                ->filter(fn ($item): bool => is_scalar($item) && filled((string) $item))
                ->first();
        }

        if (! is_scalar($state) || blank((string) $state)) {
            return null;
        }

        return self::fileUrlForView((string) $state);
    }

    protected static function formatDateValue(mixed $value): mixed
    {
        if (blank($value) || is_array($value)) {
            return $value;
        }

        $value = trim((string) $value);

        foreach (['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date !== false) {
                    return $date->translatedFormat('d-F-Y');
                }
            } catch (\Throwable) {
                //
            }
        }

        try {
            return Carbon::parse($value)->translatedFormat('d-F-Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    protected static function isLocationField(string $fieldName, ?array $options = null): bool
    {
        if (in_array($options['geo_location_type'] ?? null, ['province', 'district', 'commune', 'village'], true)) {
            return true;
        }

        $fieldName = Str::lower($fieldName);

        return in_array($fieldName, [
            'province',
            'province_id',
            'district',
            'district_id',
            'commune',
            'commune_id',
            'village',
            'village_id',
            'pob_province_id',
            'pob_district_id',
            'pob_commune_id',
            'pob_village_id',
            'birth_province_city',
            'birth_district_khan',
            'birth_commune_sangkat',
            'birth_village',
            'receipt_place_of_birth',
            'exam_place_of_birth',
            'current_capital_province',
            'current_district_khan',
            'current_commune_sangkat',
            'exam_capital_province',
            'parents_capital_province',
            'parents_district_khan',
            'parents_commune_sangkat',
            'permanent_province',
            'permanent_district',
            'permanent_commune',
            'permanent_village',
            'current_province',
            'current_district',
            'current_commune',
            'current_village',
            'application_province',
        ], true)
            || Str::endsWith($fieldName, [
                '_province',
                '_province_id',
                '_capital_province',
                '_district',
                '_district_id',
                '_district_khan',
                '_commune',
                '_commune_id',
                '_commune_sangkat',
                '_village',
                '_village_id',
            ]);
    }

    protected static function translateLocationCode(string $code): string
    {
        if (! class_exists(GeoLocation::class) || ! DatabaseSchema::hasTable('geo_locations')) {
            return $code;
        }

        static $labels = [];

        $locale = app()->getLocale();
        $cacheKey = "{$locale}:{$code}";

        if (array_key_exists($cacheKey, $labels)) {
            return $labels[$cacheKey];
        }

        $location = GeoLocation::query()
            ->where('code', $code)
            ->first(['name_en', 'name_kh']);

        if (! $location && is_numeric($code)) {
            $location = GeoLocation::query()
                ->whereKey((int) $code)
                ->first(['name_en', 'name_kh']);
        }

        if (! $location) {
            return $labels[$cacheKey] = $code;
        }

        return $labels[$cacheKey] = $locale === 'km'
            ? ($location->name_kh ?: $location->name_en)
            : $location->name_en;
    }

    protected static function getMaxTableDynamicColumns(): int
    {
        return 6;
    }

    protected static function normalizeFieldType(?string $type): string
    {
        return Str::of((string) $type)
            ->lower()
            ->replace(['-', ' ', '(', ')'], '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();
    }

    protected static function isContainerFieldType(?string $type): bool
    {
        return in_array(self::normalizeFieldType($type), [
            'step',
            'wizard_step',
            'section',
            'grid',
            'fieldset',
            'repeater',
            'card',
            'group',
            'info',
        ], true);
    }

    protected static function tablePriorityScore(string $key, ?CustomFormField $field = null): int
    {
        $key = Str::lower($key);
        $label = Str::lower((string) ($field->label ?? ''));
        $text = "{$key} {$label}";

        // You can force show a field by putting this in field options:
        // {"show_in_table": true}
        if (($field->options['show_in_table'] ?? false) === true) {
            return 1000;
        }

        $importantPatterns = [
            'student_id' => 980,
            'student_code' => 970,
            'receipt_no' => 960,
            'application_no' => 950,
            'serial_number' => 940,
            'full_name' => 930,
            'name_kh' => 920,
            'name_en' => 910,
            'first_name' => 900,
            'last_name' => 890,
            'gender' => 780,
            'phone' => 770,
            'email' => 760,
            'nationality' => 740,
            'country' => 730,
            'program' => 710,
            'faculty' => 700,
            'academic_year' => 690,
            'academic_level' => 680,
            'status' => 670,
            'dob' => 650,
            'date_of_birth' => 640,
            'payment' => 620,
            'amount' => 610,
        ];

        foreach ($importantPatterns as $pattern => $score) {
            if (Str::contains($text, $pattern)) {
                return $score;
            }
        }

        return 100;
    }

    protected static function makeViewAllModalContent(CustomFormEntry $record): HtmlString
    {
        $form = $record->customForm ?? CustomForm::find($record->custom_form_id);
        $formSlug = $form?->slug ?? self::getFormSlug($record->custom_form_id);
        $formName = $form ? self::translateFormName($form) : 'Custom Form';

        $fields = CustomFormField::query()
            ->where('custom_form_id', $record->custom_form_id)
            ->orderBy('sort')
            ->get();

        $fieldsMetadata = $fields->keyBy('name');
        $fieldsById = $fields->keyBy('id');
        $data = is_array($record->data) ? $record->data : [];

        if (empty($data) && $fieldsMetadata->isEmpty()) {
            return new HtmlString(
                '<div class="cfv-empty">'
                . '<div class="cfv-empty-title">No data found</div>'
                . '<div class="cfv-empty-text">This entry does not have any saved data yet.</div>'
                . '</div>'
            );
        }

        $keys = $fieldsMetadata
            ->keys()
            ->merge(array_keys($data))
            ->unique()
            ->values();

        $rows = $keys
            ->map(function (string $key) use ($data, $fieldsMetadata, $fieldsById, $formSlug): ?array {
                $field = $fieldsMetadata[$key] ?? null;
                $hasValue = array_key_exists($key, $data);
                $value = $hasValue ? $data[$key] : null;
                $type = self::normalizeFieldType($field->type ?? '');

                if (self::isContainerFieldType($type) && ! $hasValue) {
                    return null;
                }

                return [
                    'key' => $key,
                    'sort' => $field->sort ?? 999999,
                    'section' => self::getViewSectionLabel($field, $fieldsById, $formSlug),
                    'section_sort' => self::getViewSectionSort($field, $fieldsById),
                    'label' => self::translateFieldLabel($field, $key, $formSlug),
                    'value' => self::formatValueForViewAll(
                        key: $key,
                        value: $value,
                        fieldType: $field->type ?? null,
                        options: $field->options ?? null,
                        formSlug: $formSlug
                    ),
                    'raw_value' => $value,
                    'is_empty' => self::isEmptyViewValue($value),
                    'priority' => self::tablePriorityScore($key, $field),
                ];
            })
            ->filter()
            ->sortBy([
                ['section_sort', 'asc'],
                ['sort', 'asc'],
                ['label', 'asc'],
            ])
            ->values();

        if ($rows->isEmpty()) {
            return new HtmlString(
                '<div class="cfv-empty">'
                . '<div class="cfv-empty-title">No visible fields</div>'
                . '<div class="cfv-empty-text">Please check the form builder fields.</div>'
                . '</div>'
            );
        }

        $filledCount = $rows->where('is_empty', false)->count();
        $emptyCount = $rows->where('is_empty', true)->count();
        $totalCount = $rows->count();

        $quickRows = $rows
            ->where('is_empty', false)
            ->sortByDesc('priority')
            ->take(6)
            ->values();

        $html = self::viewAllModalCss();
        $html .= '<div class="cfv-wrap">';

        $html .= '<div class="cfv-header">';
        $html .= '<div class="cfv-header-main">';
        $html .= '<div class="cfv-kicker">Entry Details</div>';
        $html .= '<div class="cfv-title">' . e($formName) . '</div>';
        $html .= '<div class="cfv-subtitle">Entry #' . e((string) $record->getKey()) . '</div>';
        $html .= '</div>';
        $html .= '<div class="cfv-stats">';
        $html .= self::makeViewStatCard('Total', (string) $totalCount, 'neutral');
        $html .= self::makeViewStatCard('Filled', (string) $filledCount, 'success');
        $html .= self::makeViewStatCard('Empty', (string) $emptyCount, 'warning');
        $html .= '</div>';
        $html .= '</div>';

        if ($quickRows->isNotEmpty()) {
            $html .= '<div class="cfv-quick-grid">';
            foreach ($quickRows as $row) {
                $html .= self::makeQuickInfoCard((string) $row['label'], (string) $row['value']);
            }
            $html .= '</div>';
        }

        $groups = $rows->groupBy('section');

        foreach ($groups as $sectionLabel => $sectionRows) {
            $sectionFilled = $sectionRows->where('is_empty', false)->count();
            $sectionTotal = $sectionRows->count();

            $html .= '<div class="cfv-section">';
            $html .= '<div class="cfv-section-head">';
            $html .= '<div>';
            $html .= '<div class="cfv-section-title">' . e((string) $sectionLabel) . '</div>';
            $html .= '<div class="cfv-section-subtitle">' . e((string) $sectionFilled) . ' of ' . e((string) $sectionTotal) . ' fields filled</div>';
            $html .= '</div>';
            $html .= '<span class="cfv-section-pill">' . e((string) $sectionFilled) . '/' . e((string) $sectionTotal) . '</span>';
            $html .= '</div>';

            $html .= '<div class="cfv-field-grid">';
            foreach ($sectionRows as $row) {
                $html .= self::makeViewFieldCard((string) $row['label'], (string) $row['value'], (bool) $row['is_empty']);
            }
            $html .= '</div>';

            $html .= '</div>';
        }

        $html .= '<div class="cfv-footer">';
        $html .= '<span>Created: ' . e(optional($record->created_at)->translatedFormat('d-F-Y H:i') ?? '-') . '</span>';
        $html .= '<span>Updated: ' . e(optional($record->updated_at)->translatedFormat('d-F-Y H:i') ?? '-') . '</span>';
        $html .= '</div>';

        $html .= '</div>';

        return new HtmlString($html);
    }

    protected static function viewAllModalCss(): string
    {
        return <<<'HTML'
<style>
    /*
     |--------------------------------------------------------------------------
     | View All modal colors
     |--------------------------------------------------------------------------
     | Default = light mode.
     | Dark mode = Filament usually adds .dark on the html/root element.
     | We use CSS variables so both light and dark mode stay readable.
     */
    .cfv-wrap {
        --cfv-page-text: #111827;
        --cfv-muted-text: #6b7280;
        --cfv-soft-text: #9ca3af;
        --cfv-title-text: #0f172a;
        --cfv-card-bg: #ffffff;
        --cfv-card-bg-soft: #f8fafc;
        --cfv-card-bg-muted: #f3f4f6;
        --cfv-field-bg: #ffffff;
        --cfv-border: rgba(15, 23, 42, 0.12);
        --cfv-border-strong: rgba(15, 23, 42, 0.18);
        --cfv-shadow: 0 14px 36px rgba(15, 23, 42, 0.08);
        --cfv-primary: #d97706;
        --cfv-primary-soft: rgba(245, 158, 11, 0.12);
        --cfv-primary-border: rgba(245, 158, 11, 0.28);
        --cfv-success: #16a34a;
        --cfv-success-soft: rgba(34, 197, 94, 0.10);
        --cfv-success-border: rgba(34, 197, 94, 0.28);
        --cfv-warning: #d97706;
        --cfv-warning-soft: rgba(245, 158, 11, 0.12);
        --cfv-warning-border: rgba(245, 158, 11, 0.30);
        --cfv-link: #2563eb;
        --cfv-json-bg: #f8fafc;
        --cfv-json-text: #374151;
        --cfv-empty: #9ca3af;

        display: flex;
        flex-direction: column;
        gap: 18px;
        color: var(--cfv-page-text);
    }

    :is(html.dark, body.dark, .dark) .cfv-wrap {
        --cfv-page-text: #f9fafb;
        --cfv-muted-text: #9ca3af;
        --cfv-soft-text: #6b7280;
        --cfv-title-text: #ffffff;
        --cfv-card-bg: #111827;
        --cfv-card-bg-soft: #1f2937;
        --cfv-card-bg-muted: #0f172a;
        --cfv-field-bg: rgba(15, 23, 42, 0.72);
        --cfv-border: rgba(148, 163, 184, 0.18);
        --cfv-border-strong: rgba(148, 163, 184, 0.28);
        --cfv-shadow: 0 16px 40px rgba(0, 0, 0, 0.22);
        --cfv-primary: #f59e0b;
        --cfv-primary-soft: rgba(245, 158, 11, 0.14);
        --cfv-primary-border: rgba(245, 158, 11, 0.34);
        --cfv-success: #86efac;
        --cfv-success-soft: rgba(34, 197, 94, 0.12);
        --cfv-success-border: rgba(34, 197, 94, 0.30);
        --cfv-warning: #fbbf24;
        --cfv-warning-soft: rgba(245, 158, 11, 0.12);
        --cfv-warning-border: rgba(245, 158, 11, 0.32);
        --cfv-link: #93c5fd;
        --cfv-json-bg: rgba(2, 6, 23, 0.58);
        --cfv-json-text: #d1d5db;
        --cfv-empty: #6b7280;
    }

    @media (prefers-color-scheme: dark) {
        .cfv-wrap:not(.cfv-force-light) {
            --cfv-page-text: #f9fafb;
            --cfv-muted-text: #9ca3af;
            --cfv-soft-text: #6b7280;
            --cfv-title-text: #ffffff;
            --cfv-card-bg: #111827;
            --cfv-card-bg-soft: #1f2937;
            --cfv-card-bg-muted: #0f172a;
            --cfv-field-bg: rgba(15, 23, 42, 0.72);
            --cfv-border: rgba(148, 163, 184, 0.18);
            --cfv-border-strong: rgba(148, 163, 184, 0.28);
            --cfv-shadow: 0 16px 40px rgba(0, 0, 0, 0.22);
            --cfv-primary: #f59e0b;
            --cfv-primary-soft: rgba(245, 158, 11, 0.14);
            --cfv-primary-border: rgba(245, 158, 11, 0.34);
            --cfv-success: #86efac;
            --cfv-success-soft: rgba(34, 197, 94, 0.12);
            --cfv-success-border: rgba(34, 197, 94, 0.30);
            --cfv-warning: #fbbf24;
            --cfv-warning-soft: rgba(245, 158, 11, 0.12);
            --cfv-warning-border: rgba(245, 158, 11, 0.32);
            --cfv-link: #93c5fd;
            --cfv-json-bg: rgba(2, 6, 23, 0.58);
            --cfv-json-text: #d1d5db;
            --cfv-empty: #6b7280;
        }
    }

    .cfv-header,
    .cfv-section,
    .cfv-empty {
        border: 1px solid var(--cfv-border);
        border-radius: 18px;
        background: var(--cfv-card-bg);
        box-shadow: var(--cfv-shadow);
    }

    .cfv-header {
        padding: 20px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
    }

    .cfv-kicker {
        color: var(--cfv-primary);
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .08em;
    }

    .cfv-title {
        margin-top: 6px;
        color: var(--cfv-title-text);
        font-size: 22px;
        font-weight: 800;
        line-height: 1.2;
    }

    .cfv-subtitle {
        margin-top: 6px;
        color: var(--cfv-muted-text);
        font-size: 13px;
        font-weight: 600;
    }

    .cfv-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(88px, 1fr));
        gap: 10px;
        min-width: 290px;
    }

    .cfv-stat {
        border: 1px solid var(--cfv-border);
        border-radius: 14px;
        background: var(--cfv-card-bg-soft);
        padding: 12px 14px;
        text-align: center;
    }

    .cfv-stat-value {
        color: var(--cfv-title-text);
        font-size: 20px;
        font-weight: 900;
        line-height: 1;
    }

    .cfv-stat-label {
        margin-top: 6px;
        color: var(--cfv-muted-text);
        font-size: 12px;
        font-weight: 700;
    }

    .cfv-stat-success {
        border-color: var(--cfv-success-border);
        background: var(--cfv-success-soft);
    }

    .cfv-stat-warning {
        border-color: var(--cfv-warning-border);
        background: var(--cfv-warning-soft);
    }

    .cfv-quick-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .cfv-quick-card {
        border: 1px solid var(--cfv-border);
        border-radius: 16px;
        background: var(--cfv-card-bg-soft);
        padding: 14px;
    }

    .cfv-quick-label,
    .cfv-field-label {
        color: var(--cfv-muted-text);
        font-size: 12px;
        font-weight: 800;
        line-height: 1.25;
    }

    .cfv-quick-value {
        margin-top: 6px;
        color: var(--cfv-title-text);
        font-size: 14px;
        font-weight: 700;
        line-height: 1.35;
        word-break: break-word;
    }

    .cfv-section {
        overflow: hidden;
    }

    .cfv-section-head {
        padding: 16px 18px;
        border-bottom: 1px solid var(--cfv-border);
        background: var(--cfv-card-bg-soft);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .cfv-section-title {
        color: var(--cfv-title-text);
        font-size: 16px;
        font-weight: 850;
        line-height: 1.2;
    }

    .cfv-section-subtitle {
        margin-top: 4px;
        color: var(--cfv-muted-text);
        font-size: 12px;
        font-weight: 600;
    }

    .cfv-section-pill {
        border-radius: 999px;
        padding: 7px 11px;
        color: var(--cfv-warning);
        background: var(--cfv-warning-soft);
        border: 1px solid var(--cfv-warning-border);
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .cfv-field-grid {
        padding: 16px;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        background: var(--cfv-card-bg);
    }

    .cfv-field-card {
        min-height: 88px;
        border: 1px solid var(--cfv-border);
        border-radius: 14px;
        background: var(--cfv-field-bg);
        padding: 14px;
    }

    .cfv-field-card-empty {
        border-style: dashed;
        opacity: .74;
    }

    .cfv-field-value {
        margin-top: 8px;
        color: var(--cfv-page-text);
        font-size: 14px;
        font-weight: 650;
        line-height: 1.45;
        word-break: break-word;
    }

    .cfv-empty-value {
        color: var(--cfv-empty);
        font-weight: 700;
    }

    .cfv-badge-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .cfv-badge {
        display: inline-flex;
        border-radius: 999px;
        background: var(--cfv-primary-soft);
        border: 1px solid var(--cfv-primary-border);
        color: var(--cfv-primary);
        padding: 5px 9px;
        font-size: 12px;
        font-weight: 750;
    }

    .cfv-boolean-yes,
    .cfv-boolean-no {
        display: inline-flex;
        border-radius: 999px;
        padding: 5px 9px;
        font-size: 12px;
        font-weight: 800;
        border: 1px solid transparent;
    }

    .cfv-boolean-yes {
        color: var(--cfv-success);
        background: var(--cfv-success-soft);
        border-color: var(--cfv-success-border);
    }

    .cfv-boolean-no {
        color: var(--cfv-muted-text);
        background: var(--cfv-card-bg-muted);
        border-color: var(--cfv-border);
    }

    .cfv-json {
        max-height: 300px;
        overflow: auto;
        white-space: pre-wrap;
        border-radius: 12px;
        border: 1px solid var(--cfv-border);
        background: var(--cfv-json-bg);
        color: var(--cfv-json-text);
        padding: 12px;
        font-size: 12px;
        line-height: 1.5;
    }

    .cfv-link {
        color: var(--cfv-link);
        font-weight: 800;
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .cfv-footer {
        display: flex;
        justify-content: flex-end;
        gap: 14px;
        color: var(--cfv-muted-text);
        font-size: 12px;
        font-weight: 600;
    }

    .cfv-empty {
        padding: 34px;
        text-align: center;
        color: var(--cfv-page-text);
    }

    .cfv-empty-title {
        color: var(--cfv-title-text);
        font-size: 16px;
        font-weight: 850;
    }

    .cfv-empty-text {
        margin-top: 6px;
        color: var(--cfv-muted-text);
        font-size: 13px;
    }

    @media (max-width: 900px) {
        .cfv-header { flex-direction: column; }
        .cfv-stats { width: 100%; min-width: 0; }
        .cfv-quick-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cfv-field-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 640px) {
        .cfv-quick-grid { grid-template-columns: 1fr; }
        .cfv-stats { grid-template-columns: 1fr; }
        .cfv-footer { flex-direction: column; align-items: flex-start; }
    }
</style>
HTML;
    }

    protected static function makeViewStatCard(string $label, string $value, string $type = 'neutral'): string
    {
        $typeClass = match ($type) {
            'success' => ' cfv-stat-success',
            'warning' => ' cfv-stat-warning',
            default => '',
        };

        return '<div class="cfv-stat' . $typeClass . '">'
            . '<div class="cfv-stat-value">' . e($value) . '</div>'
            . '<div class="cfv-stat-label">' . e($label) . '</div>'
            . '</div>';
    }

    protected static function makeQuickInfoCard(string $label, string $value): string
    {
        return '<div class="cfv-quick-card">'
            . '<div class="cfv-quick-label">' . e($label) . '</div>'
            . '<div class="cfv-quick-value">' . $value . '</div>'
            . '</div>';
    }

    protected static function makeViewFieldCard(string $label, string $value, bool $isEmpty): string
    {
        return '<div class="cfv-field-card' . ($isEmpty ? ' cfv-field-card-empty' : '') . '">'
            . '<div class="cfv-field-label">' . e($label) . '</div>'
            . '<div class="cfv-field-value">' . $value . '</div>'
            . '</div>';
    }

    protected static function getViewSectionLabel(?CustomFormField $field, $fieldsById, ?string $formSlug = null): string
    {
        if ($field && $field->parent_id) {
            $parent = $fieldsById[$field->parent_id] ?? null;

            if ($parent) {
                return self::translateFieldLabel($parent, $parent->name, $formSlug);
            }
        }

        return 'General Information';
    }

    protected static function getViewSectionSort(?CustomFormField $field, $fieldsById): int
    {
        if ($field && $field->parent_id) {
            $parent = $fieldsById[$field->parent_id] ?? null;

            if ($parent) {
                return (int) ($parent->sort ?? 0);
            }
        }

        return 0;
    }

    protected static function isEmptyViewValue(mixed $value): bool
    {
        return $value === null
            || $value === ''
            || (is_array($value) && count($value) === 0);
    }

    protected static function formatValueForViewAll(
        string $key,
        mixed $value,
        ?string $fieldType = null,
        ?array $options = null,
        ?string $formSlug = null
    ): string
    {
        if (self::isEmptyViewValue($value)) {
            return '<span class="cfv-empty-value">—</span>';
        }

        if (self::isDateFieldType($fieldType)) {
            return e((string) self::formatDateValue($value));
        }

        if (is_bool($value)) {
            return $value
                ? '<span class="cfv-boolean-yes">Yes</span>'
                : '<span class="cfv-boolean-no">No</span>';
        }

        if (is_array($value)) {
            if (array_is_list($value) && ! collect($value)->contains(fn ($item): bool => is_array($item))) {
                $badges = collect($value)
                    ->map(fn ($item) => self::formatTableFieldState($key, $item, $fieldType, $options, $formSlug))
                    ->filter(fn ($item) => ! self::isEmptyViewValue($item))
                    ->map(fn ($item) => '<span class="cfv-badge">' . e((string) $item) . '</span>')
                    ->implode('');

                return $badges !== '' ? '<div class="cfv-badge-wrap">' . $badges . '</div>' : '<span class="cfv-empty-value">—</span>';
            }

            return '<pre class="cfv-json">'
                . e(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                . '</pre>';
        }

        $translated = CustomFormTemplateData::displayLabelForField(
            fieldName: $key,
            rawValue: $value,
            fieldType: $fieldType,
            options: $options,
        ) ?? self::translateFieldState($key, $value, $options, $formSlug);

        $display = (string) $translated;

        if (self::looksLikeFilePath($display)) {
            return '<a class="cfv-link" href="' . e(self::fileUrlForView($display)) . '" target="_blank" rel="noopener noreferrer">Open file</a>';
        }

        return e($display);
    }

    protected static function looksLikeFilePath(string $value): bool
    {
        $lowerValue = Str::lower($value);

        return Str::startsWith($lowerValue, ['http://', 'https://', 'storage/', '/storage/'])
            || Str::endsWith($lowerValue, ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.pdf', '.doc', '.docx', '.xls', '.xlsx']);
    }

    protected static function fileUrlForView(string $value): string
    {
        if (Str::startsWith(Str::lower($value), ['http://', 'https://', '/storage/'])) {
            return $value;
        }

        if (Str::startsWith(Str::lower($value), 'storage/')) {
            return '/' . $value;
        }

        return '/storage/' . ltrim($value, '/');
    }

    protected static function getFormSlug(?int $formId): ?string
    {
        static $slugs = [];

        if (! $formId) {
            return null;
        }

        return $slugs[$formId] ??= CustomForm::query()->whereKey($formId)->value('slug');
    }

    protected static function getTranslationFormSlugs(?string $formSlug = null): array
    {
        $slugs = [];
        $normalizedSlug = self::normalizeFormSlug($formSlug);

        if ($normalizedSlug) {
            $slugs[] = $normalizedSlug;
        }

        if (in_array($normalizedSlug, ['profile', 'profile-form', 'student-profile', 'resume-form'], true)) {
            $slugs[] = 'resume-form';
        }

        if (in_array($normalizedSlug, ['national-exam', 'print-application', 'specialty-exam-form'], true)) {
            $slugs[] = 'national-exam';
            $slugs[] = 'print-application';
            $slugs[] = 'specialty-exam-form';
        }

        return array_values(array_unique($slugs));
    }

    protected static function getTranslationFieldNames(string $fieldName): array
    {
        $aliases = [
            'first_name_kh' => ['first_name_khmer', 'exam_first_name_kh', 'receipt_first_name_kh', 'contract_name'],
            'last_name_kh' => ['last_name_khmer', 'exam_last_name_kh', 'receipt_last_name_kh'],
            'first_name_en' => ['first_name_english', 'exam_first_name_en', 'receipt_first_name_en'],
            'last_name_en' => ['last_name_english', 'exam_last_name_en', 'receipt_last_name_en'],
            'gender' => ['exam_gender', 'receipt_gender', 'contract_gender'],
            'date_of_birth' => ['exam_date_of_birth', 'receipt_date_of_birth', 'contract_date_of_birth'],
            'place_of_birth' => ['exam_place_of_birth', 'receipt_place_of_birth'],
            'current_degree' => ['receipt_current_degree'],
            'current_degree_level' => ['exam_current_degree_level'],
            'university_school' => ['receipt_university_school'],
            'phone_number' => ['exam_phone_number', 'receipt_phone_number'],
            'specialty' => ['exam_specialization', 'receipt_specialty', 'contract_specialty'],
            'required_documents' => ['exam_required_documents'],
            'principle' => ['contract_principle'],
        ];

        return array_values(array_unique([
            $fieldName,
            ...($aliases[$fieldName] ?? []),
        ]));
    }

    protected static function normalizeFormSlug(?string $slug): ?string
    {
        if (! filled($slug)) {
            return null;
        }

        return Str::of($slug)
            ->lower()
            ->replace(['_', ' '], '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->toString();
    }

    protected static function getChoiceOptions(array $options): array
    {
        if (array_key_exists('choices', $options)) {
            return is_array($options['choices']) ? $options['choices'] : [];
        }

        return array_is_list($options) ? $options : [];
    }
}
