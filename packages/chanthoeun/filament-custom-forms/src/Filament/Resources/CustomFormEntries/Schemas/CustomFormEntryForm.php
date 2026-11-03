<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Schemas;

use App\Models\GeoLocation;
use App\Models\LimitFile;
use App\Support\DatePickerKeyboardInput;
use App\Support\FileUploadLimit;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class CustomFormEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Hidden::make('custom_form_id')
                    ->default(fn () => self::requestFormId())
                    ->required()
                    ->dehydrated()
                    ->afterStateHydrated(function ($state, callable $set): void {
                        self::copyDefaultSourceDataForForm($state, $set);
                    })
                    ->afterStateUpdated(function ($state, callable $set): void {
                        $set('data', []);
                        self::copyDefaultSourceDataForForm($state, $set);
                    }),

                Hidden::make('created_by')
                    ->default(fn () => auth()->id()),

                Group::make()
                    ->schema(fn (callable $get): array => self::buildDynamicFields($get('custom_form_id')))
                    ->columnSpanFull(),
            ]);
    }

    private static function requestFormId(): ?int
    {
        $fromFilter = data_get(request()->query('tableFilters'), 'custom_form_id.value');

        if (filled($fromFilter) && is_numeric($fromFilter)) {
            return (int) $fromFilter;
        }

        $fromQuery = request()->integer('custom_form_id');

        if ($fromQuery) {
            return $fromQuery;
        }

        $fromFormQuery = request()->integer('form_id');

        return $fromFormQuery ?: null;
    }

    public static function buildDynamicFields($customFormId, bool $forceEditable = false): array
    {
        if (! filled($customFormId)) {
            return [
                Section::make('Please select form first')
                    ->schema([
                        TextInput::make('empty_form_notice')
                            ->label('Please select form first')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ];
        }

        $form = CustomForm::query()
            ->with(['fields' => fn ($query) => $query->orderBy('sort')])
            ->find($customFormId);

        if (! $form) {
            return [
                Section::make('Form not found')
                    ->schema([
                        TextInput::make('missing_form_notice')
                            ->label('Form not found')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ];
        }

        $fields = $form->fields
            ->sortBy('sort')
            ->values();

        return self::buildTopLevelDynamicComponents(
            fields: $fields,
            defaultTitle: self::getDynamicFormTitle($customFormId),
            formSlug: (string) $form->slug,
            forceEditable: $forceEditable,
        );
    }

    private static function buildTopLevelDynamicComponents(
        $fields,
        string $defaultTitle,
        ?string $formSlug = null,
        bool $forceEditable = false,
    ): array {
        $items = $fields
            ->filter(fn ($field): bool => blank($field->parent_id))
            ->sortBy('sort')
            ->values();

        if (self::shouldRenderAsWizard($formSlug, $items)) {
            return self::makeWizardComponents($items, $fields, $formSlug, $defaultTitle, $forceEditable);
        }

        $components = [];
        $normalFields = [];

        foreach ($items as $field) {
            if (self::isWizardContainerField($field)) {
                if (! empty($normalFields)) {
                    $components[] = self::makeNormalFieldsSection($defaultTitle, $normalFields);
                    $normalFields = [];
                }

                $components[] = self::makeWizardContainerComponent($field, $fields, $forceEditable);

                continue;
            }

            if (self::isBuilderSectionField($field)) {
                if (! empty($normalFields)) {
                    $components[] = self::makeNormalFieldsSection($defaultTitle, $normalFields);
                    $normalFields = [];
                }

                $components[] = self::makeBuilderSectionComponent($field, $fields, forceEditable: $forceEditable);

                continue;
            }

            $normalFields[] = self::makeFieldComponent($field, $fields, forceEditable: $forceEditable);
        }

        if (! empty($normalFields)) {
            $components[] = self::makeNormalFieldsSection($defaultTitle, $normalFields);
        }

        if (empty($components)) {
            $components[] = Section::make($defaultTitle)
                ->schema([
                    TextInput::make('empty_dynamic_form_notice')
                        ->label('No fields in this form')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ])
                ->columnSpanFull();
        }

        return $components;
    }

    private static function shouldRenderAsWizard(?string $formSlug, $items): bool
    {
        if (in_array($formSlug, ['profile', 'profile-form', 'resume-form', 'student_profile', 'student-profile'], true)) {
            return false;
        }

        $hasStepFields = $items
            ->filter(fn ($field): bool => self::isWizardStepField($field))
            ->count() > 0;

        if ($hasStepFields) {
            return true;
        }

        if (! in_array($formSlug, ['national-exam', 'print-application', 'specialty-exam-form', 'enrollment'], true)) {
            return false;
        }

        return $items
            ->filter(fn ($field): bool => self::isBuilderSectionField($field))
            ->count() >= 2;
    }

    private static function makeWizardComponents(
        $items,
        $fields,
        ?string $formSlug = null,
        string $label = 'Form',
        bool $forceEditable = false,
    ): array {
        return [
            self::makeCleanWizardStyleComponent(),
            self::makeWizardComponent($items, $fields, $formSlug, $label, $forceEditable),
        ];
    }

    private static function makeWizardComponent(
        $items,
        $fields,
        ?string $formSlug = null,
        string $label = 'Form',
        bool $forceEditable = false,
    ): Wizard {
        $wizardItems = $items
            ->filter(fn ($field): bool => self::isWizardStepField($field))
            ->values();

        if ($wizardItems->isEmpty()) {
            $wizardItems = $items
                ->filter(fn ($field): bool => self::isBuilderSectionField($field))
                ->values();
        }

        $steps = $wizardItems
            ->map(fn ($field): Step => self::makeWizardStep($field, $fields, $forceEditable))
            ->values()
            ->all();

        return Wizard::make($steps)
            ->label($label)
            ->extraAttributes(['class' => 'cf-clean-profile-wizard'])
            ->nextAction(fn ($action) => $action
                ->label(__('filament-custom-forms::fcf.entry.action.next'))
                ->color('success')
                ->size('lg'))
            ->previousAction(fn ($action) => $action->label(__('filament-custom-forms::fcf.entry.action.back')))
            ->submitAction(new HtmlString(
                '<button type="submit" class="fi-btn fi-btn-size-md fi-color-primary">'.e(__('filament-custom-forms::fcf.entry.action.save')).'</button>'
            ))
            ->columnSpanFull();
    }

    private static function makeCleanWizardStyleComponent(): Placeholder
    {
        return Placeholder::make('clean_profile_wizard_style')
            ->hiddenLabel()
            ->content(new HtmlString(<<<'HTML'
<style>
    .cf-clean-wizard-style {
        display: none !important;
    }

    .cf-clean-profile-wizard.fi-sc-wizard {
        --cf-wizard-surface: #ffffff;
        --cf-wizard-muted-surface: #f8fafc;
        --cf-wizard-border: rgba(148, 163, 184, 0.24);
        --cf-wizard-border-strong: rgba(148, 163, 184, 0.32);
        --cf-wizard-text: #334155;
        --cf-wizard-muted-text: #64748b;
        --cf-wizard-active-bg: #f0fdf4;
        --cf-wizard-active-text: #14532d;
        --cf-info-text: #111827;
        --cf-info-body-text: #1f2937;
        overflow: hidden;
        border: 1px solid var(--cf-wizard-border);
        border-radius: 12px;
        background: var(--cf-wizard-surface);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
    }

    .dark .cf-clean-profile-wizard.fi-sc-wizard {
        --cf-wizard-surface: rgb(24, 24, 27);
        --cf-wizard-muted-surface: rgb(39, 39, 42);
        --cf-wizard-border: rgba(113, 113, 122, 0.42);
        --cf-wizard-border-strong: rgba(161, 161, 170, 0.44);
        --cf-wizard-text: #e5e7eb;
        --cf-wizard-muted-text: #a1a1aa;
        --cf-wizard-active-bg: rgba(22, 163, 74, 0.16);
        --cf-wizard-active-text: #bbf7d0;
        --cf-info-text: #f9fafb;
        --cf-info-body-text: #d4d4d8;
        box-shadow: none;
    }

    .cf-clean-profile-wizard .fi-sc-wizard-header {
        display: grid !important;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 10px;
        padding: 14px;
        border-bottom: 1px solid var(--cf-wizard-border);
        background: var(--cf-wizard-surface);
    }

    .cf-clean-profile-wizard .fi-sc-wizard-header-step {
        min-width: 0;
    }

    .cf-clean-profile-wizard .fi-sc-wizard-header-step-separator {
        display: none !important;
    }

    .cf-clean-profile-wizard .fi-sc-wizard-header-step-btn {
        width: 100%;
        min-height: 54px;
        gap: 12px;
        justify-content: flex-start;
        padding: 10px 12px !important;
        border: 1px solid var(--cf-wizard-border);
        border-radius: 10px;
        background: var(--cf-wizard-surface);
        color: var(--cf-wizard-muted-text);
        transition: border-color 150ms ease, box-shadow 150ms ease, background 150ms ease, color 150ms ease;
    }

    .cf-clean-profile-wizard .fi-sc-wizard-header-step-btn:hover {
        border-color: rgba(34, 197, 94, 0.36);
        background: var(--cf-wizard-muted-surface);
    }

    .cf-clean-profile-wizard .fi-sc-wizard-header-step-icon-ctn {
        width: 34px !important;
        height: 34px !important;
        flex: 0 0 34px;
        border: 1px solid var(--cf-wizard-border-strong);
        border-radius: 999px;
        background: var(--cf-wizard-surface);
        color: var(--cf-wizard-muted-text);
        box-shadow: none !important;
    }

    .cf-clean-profile-wizard .fi-sc-wizard-header-step-number,
    .cf-clean-profile-wizard .fi-sc-wizard-header-step-icon-ctn .fi-icon {
        font-size: 13px;
        font-weight: 700;
    }

    .cf-clean-profile-wizard .fi-sc-wizard-header-step-text {
        min-width: 0;
        gap: 2px;
    }

    .cf-clean-profile-wizard .fi-sc-wizard-header-step-label {
        overflow: hidden;
        color: var(--cf-wizard-text);
        font-size: 14px;
        font-weight: 650;
        line-height: 1.25;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cf-clean-profile-wizard .fi-sc-wizard-header-step.fi-active .fi-sc-wizard-header-step-btn,
    .cf-clean-profile-wizard .fi-sc-wizard-header-step.fi-completed .fi-sc-wizard-header-step-btn {
        border-color: rgba(34, 197, 94, 0.42);
        background: var(--cf-wizard-active-bg);
        color: var(--cf-wizard-active-text);
        box-shadow: 0 8px 18px rgba(22, 101, 52, 0.08);
    }

    .cf-clean-profile-wizard .fi-sc-wizard-header-step.fi-active .fi-sc-wizard-header-step-icon-ctn,
    .cf-clean-profile-wizard .fi-sc-wizard-header-step.fi-completed .fi-sc-wizard-header-step-icon-ctn {
        border-color: #16a34a;
        background: #16a34a;
        color: #ffffff;
    }

    .cf-clean-profile-wizard .fi-sc-wizard-header-step.fi-active .fi-sc-wizard-header-step-label,
    .cf-clean-profile-wizard .fi-sc-wizard-header-step.fi-completed .fi-sc-wizard-header-step-label {
        color: var(--cf-wizard-active-text);
    }

    .cf-clean-profile-wizard .fi-sc-wizard-step {
        padding: 24px !important;
        background: var(--cf-wizard-surface);
        color: var(--cf-wizard-text);
    }

    .cf-clean-profile-wizard .cf-info-placeholder {
        color: var(--cf-info-text);
    }

    .cf-clean-profile-wizard .cf-info-placeholder-body {
        color: var(--cf-info-body-text);
    }

    @media (max-width: 640px) {
        .cf-clean-profile-wizard .fi-sc-wizard-header {
            grid-template-columns: 1fr;
            padding: 10px;
        }

        .cf-clean-profile-wizard .fi-sc-wizard-step {
            padding: 16px !important;
        }
    }
</style>
HTML))
            ->extraAttributes(['class' => 'cf-clean-wizard-style'])
            ->columnSpanFull();
    }

    private static function makeWizardStep($field, $fields, bool $forceEditable = false): Step
    {
        $inputEnabled = $forceEditable || self::canInputField($field);

        $children = self::buildNestedDynamicComponents(
            fields: $fields,
            parentId: $field->id,
            parentInputEnabled: $inputEnabled,
            forceEditable: $forceEditable,
        );

        if (empty($children)) {
            $children = [
                TextInput::make('empty_section_'.$field->id)
                    ->label('No fields in this section')
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ];
        }

        return Step::make(self::getWizardStepTitle($field))
            ->schema($children)
            ->columns(self::getBuilderSectionColumns($field));
    }

    private static function buildNestedDynamicComponents(
        $fields,
        $parentId,
        bool $parentInputEnabled = true,
        bool $forceEditable = false,
    ): array {
        $items = $fields
            ->filter(fn ($field): bool => (int) $field->parent_id === (int) $parentId)
            ->sortBy('sort')
            ->values();

        $components = [];

        foreach ($items as $field) {
            if (self::isWizardContainerField($field)) {
                $components[] = self::makeWizardContainerComponent($field, $fields, $forceEditable);

                continue;
            }

            if (self::isBuilderSectionField($field)) {
                $components[] = self::makeBuilderSectionComponent(
                    $field,
                    $fields,
                    $parentInputEnabled,
                    $forceEditable,
                );

                continue;
            }

            $components[] = self::makeFieldComponent(
                $field,
                $fields,
                parentInputEnabled: $parentInputEnabled,
                forceEditable: $forceEditable,
            );

            if ((string) $field->name === 'parents_current_address_heading') {
                $components[] = self::makeSameAsCurrentAddressAction();
            }
        }

        return $components;
    }

    private static function makeSameAsCurrentAddressAction(): SchemaActions
    {
        return SchemaActions::make([
            Action::make('same_as_current_address')
                ->label('Same Address')
                ->icon('heroicon-o-home')
                ->color('gray')
                ->size('lg')
                ->action(function (Get $get, Set $set): void {
                    $addressFields = [
                        'parents_house_number' => 'current_house_number',
                        'parents_street_number' => 'current_street_number',
                        'parents_capital_province' => 'current_capital_province',
                        'parents_district_khan' => 'current_district_khan',
                        'parents_commune_sangkat' => 'current_commune_sangkat',
                        'parents_village' => 'current_village',
                    ];

                    foreach ($addressFields as $target => $source) {
                        $set('data.'.$target, $get('data.'.$source), shouldCallUpdatedHooks: true);
                    }
                }),
        ])
            ->columnSpanFull();
    }

    private static function makeNormalFieldsSection(string $title, array $fields): Section
    {
        return Section::make($title)
            ->schema($fields)
            ->columns(4)
            ->columnSpanFull();
    }

    private static function isBuilderSectionField($field): bool
    {
        $type = self::normalizeFieldType($field->type ?? '');

        return in_array($type, [
            'step',
            'section',
            'card',
            'fieldset',
            'group',
            'grid',
        ], true);
    }

    private static function isWizardStepField($field): bool
    {
        $type = self::normalizeFieldType($field->type ?? '');

        return in_array($type, [
            'step',
            'wizard_step',
        ], true);
    }

    private static function isWizardContainerField($field): bool
    {
        return self::normalizeFieldType($field->type ?? '') === 'wizard';
    }

    private static function makeWizardContainerComponent($field, $fields, bool $forceEditable = false): Wizard
    {
        $children = $fields
            ->filter(fn ($child): bool => (int) $child->parent_id === (int) $field->id)
            ->sortBy('sort')
            ->values();

        $steps = $children
            ->filter(fn ($child): bool => self::isWizardStepField($child))
            ->map(fn ($child): Step => self::makeWizardStep($child, $fields, $forceEditable))
            ->values()
            ->all();

        if (empty($steps)) {
            $steps = [
                Step::make(self::getWizardStepTitle($field))
                    ->schema(self::buildNestedDynamicComponents(
                        $fields,
                        $field->id,
                        forceEditable: $forceEditable,
                    ))
                    ->columns(self::getBuilderSectionColumns($field)),
            ];
        }

        return Wizard::make($steps)
            ->label(self::getBuilderSectionTitle($field))
            ->extraAttributes(['class' => 'cf-clean-profile-wizard'])
            ->nextAction(fn ($action) => $action
                ->label(__('filament-custom-forms::fcf.entry.action.next'))
                ->color('success')
                ->size('lg'))
            ->previousAction(fn ($action) => $action->label(__('filament-custom-forms::fcf.entry.action.back')))
            ->submitAction(new HtmlString(
                '<button type="submit" class="fi-btn fi-btn-size-md fi-color-primary">'.e(__('filament-custom-forms::fcf.entry.action.save')).'</button>'
            ))
            ->columnSpanFull();
    }

    private static function makeBuilderSectionComponent(
        $field,
        $fields,
        bool $parentInputEnabled = true,
        bool $forceEditable = false,
    ): Section {
        $title = self::getBuilderSectionTitle($field);
        $inputEnabled = $forceEditable || ($parentInputEnabled && self::canInputField($field));

        $children = self::buildNestedDynamicComponents(
            fields: $fields,
            parentId: $field->id,
            parentInputEnabled: $inputEnabled,
            forceEditable: $forceEditable,
        );

        if (empty($children)) {
            $children = [
                TextInput::make('empty_section_'.$field->id)
                    ->label('No fields in this section')
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ];
        }

        return Section::make($title)
            ->schema($children)
            ->columns(self::getBuilderSectionColumns($field))
            ->columnSpanFull();
    }

    private static function getBuilderSectionTitle($field): string
    {
        $fieldName = (string) ($field->name ?? '');
        $formSlug = self::getFormSlugById($field->custom_form_id ?? null);
        $label = self::stringifyLabel($field->label ?? null);
        $label = self::translateFormFieldLabel($fieldName, $label, $formSlug);

        if ($label !== '') {
            return $label;
        }

        return $fieldName !== ''
            ? Str::headline($fieldName)
            : 'Section';
    }

    private static function getWizardStepTitle($field): string
    {
        $title = self::getBuilderSectionTitle($field);

        $cleanTitle = Str::of($title)
            ->replaceMatches('/^\s*(?:[IVXLCDM]+|\d+)\s*[\.\)\-:]\s*/i', '')
            ->squish()
            ->toString();

        return $cleanTitle !== '' ? $cleanTitle : $title;
    }

    private static function getBuilderSectionColumns($field): int
    {
        $options = self::decodeOptions($field->options ?? []);

        $columns = null;

        if (is_array($options)) {
            $columns = $options['columns']
                ?? $options['schema_columns']
                ?? $options['grid_columns']
                ?? null;
        }

        if (is_numeric($columns)) {
            return max(1, min(12, (int) $columns));
        }

        return 2;
    }

    private static function translateFormFieldLabel(string $fieldName, string $fallback, ?string $formSlug = null): string
    {
        foreach (self::getTranslationFormSlugs($formSlug) as $slug) {
            foreach (self::getTranslationFieldNames($fieldName) as $translationFieldName) {
                $translationKey = "filament-custom-forms::fcf.form.fields.{$slug}.{$translationFieldName}";
                $translation = __($translationKey);

                if ($translation !== $translationKey) {
                    return (string) $translation;
                }
            }
        }

        return $fallback;
    }

    private static function translateFormFieldContent(string $fieldName, ?string $formSlug = null): string
    {
        foreach (self::getTranslationFormSlugs($formSlug) as $slug) {
            foreach (self::getTranslationFieldNames($fieldName) as $translationFieldName) {
                $translationKey = "filament-custom-forms::fcf.form.contents.{$slug}.{$translationFieldName}";
                $translation = __($translationKey);

                if ($translation !== $translationKey) {
                    return (string) $translation;
                }
            }
        }

        return '';
    }

    private static function translateFormFieldOptions(string $fieldName, array $options, ?string $formSlug = null): array
    {
        if (empty($options)) {
            return $options;
        }

        foreach ($options as $value => $label) {
            foreach (self::getTranslationFormSlugs($formSlug) as $slug) {
                foreach (self::getTranslationFieldNames($fieldName) as $translationFieldName) {
                    $translationKey = "filament-custom-forms::fcf.form.options.{$slug}.{$translationFieldName}.{$value}";
                    $translation = __($translationKey);

                    if ($translation !== $translationKey) {
                        $options[$value] = (string) $translation;

                        continue 3;
                    }
                }
            }
        }

        return $options;
    }

    private static function getTranslationFieldNames(string $fieldName): array
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

    private static function getTranslationFormSlugs(?string $formSlug = null): array
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

    private static function getDynamicFormTitle($customFormId): string
    {
        if (! filled($customFormId)) {
            return 'Please select form first';
        }

        $form = CustomForm::query()
            ->whereKey($customFormId)
            ->first(['id', 'name', 'slug']);

        if (! $form) {
            return 'Form not found';
        }

        return self::translateFormName($form);
    }

    private static function translateFormName(CustomForm $form): string
    {
        foreach (self::getTranslationFormSlugs((string) $form->slug) as $slug) {
            $translationKey = "filament-custom-forms::fcf.form.names.{$slug}";
            $translation = __($translationKey);

            if ($translation !== $translationKey) {
                return (string) $translation;
            }
        }

        return (string) $form->name;
    }

    private static function normalizeFieldType($type): string
    {
        $type = Str::of((string) $type)
            ->lower()
            ->replace(['-', ' ', '(', ')'], '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();

        return match ($type) {
            'text_input' => 'text_input',
            'text_area' => 'textarea',
            'select_dropdown' => 'select_dropdown',
            'date_picker' => 'date_picker',
            'time_picker' => 'time_picker',
            'date_time', 'date_time_picker', 'datetime_picker' => 'datetime_picker',
            'number_input' => 'number_input',
            'money_input' => 'money_input',
            'boolean_toggle', 'boolean', 'toggle' => 'toggle',
            'check_box', 'checkbox' => 'checkbox',
            'check_box_list', 'checkbox_list', 'multi_checkbox', 'multi_select' => 'checkbox_list',
            'file_upload', 'upload_file', 'file', 'upload', 'image', 'attachment', 'photo', 'document' => 'file_upload',
            'wizard_step', 'step' => 'step',
            default => $type !== '' ? $type : 'text_input',
        };
    }

    public static function makeFieldComponent(
        $field,
        $fields = null,
        string $statePrefix = 'data.',
        bool $parentInputEnabled = true,
        bool $forceEditable = false,
    ) {
        $fieldName = (string) $field->name;
        $name = $statePrefix.$fieldName;
        $formSlug = self::getFormSlugById($field->custom_form_id ?? null);

        $label = self::stringifyLabel($field->label ?? null);
        $label = $label !== '' ? $label : Str::headline($fieldName);
        $label = self::translateFormFieldLabel($fieldName, $label, $formSlug);

        $required = (bool) ($field->required ?? $field->is_required ?? false)
            && ! self::isProfileFormId($field->custom_form_id ?? null);

        $decodedOptions = self::decodeOptions($field->options ?? []);
        $optionConfig = is_array($decodedOptions) ? $decodedOptions : [];

        $selectOptions = self::translateFormFieldOptions(
            fieldName: $fieldName,
            options: self::normalizeOptions($decodedOptions),
            formSlug: $formSlug
        );
        $type = self::normalizeFieldType($field->type ?? 'text_input');

        $locationType = self::getLocationFieldType($fieldName, $optionConfig);
        $lookupTableConfig = self::getLookupTableConfig($fieldName, $optionConfig);
        $inputEnabled = $forceEditable || ($parentInputEnabled && self::canInputField($field));

        $component = match ($type) {
            'info', 'heading', 'label', 'display' => self::makeInfoPlaceholder($name, $fieldName, $label, $optionConfig, $formSlug),

            'repeater', 'repeatable' => Repeater::make($name)
                ->schema(self::buildRepeaterComponents($fields, $field->id, $inputEnabled, $forceEditable))
                ->columns(self::getBuilderSectionColumns($field))
                ->defaultItems(0)
                ->addActionLabel('Add '.$label),

            'textarea' => Textarea::make($name)
                ->rows(4),

            'number', 'numeric', 'number_input' => self::configureNumberInput(TextInput::make($name)->numeric(), $optionConfig),

            'email' => TextInput::make($name)
                ->email(),

            'password' => TextInput::make($name)
                ->password()
                ->revealable(),

            'date', 'date_picker' => DatePickerKeyboardInput::configure(DatePicker::make($name)),

            'datetime', 'date_time', 'datetime_picker' => DateTimePicker::make($name)
                ->native(false)
                ->seconds(false),

            'select', 'select_dropdown' => $locationType
                ? self::makeGeoSelect(
                    name: $name,
                    fieldName: $fieldName,
                    locationType: $locationType,
                    options: $optionConfig
                )
                : ($lookupTableConfig
                    ? self::makeLookupSelect(
                        name: $name,
                        config: $lookupTableConfig
                    )
                    : Select::make($name)
                        ->options(function (callable $get) use ($name, $fieldName, $selectOptions, $optionConfig): array {
                            $opts = $selectOptions;
                            $state = $get($name) ?? $get($fieldName);
                            if (filled($state) && ! is_array($state)) {
                                $valStr = (string) $state;
                                if (! isset($opts[$valStr])) {
                                    $label = self::resolveOptionLabel($valStr, $optionConfig, $fieldName);
                                    $opts[$valStr] = filled($label) ? $label : $valStr;
                                }
                            }

                            return array_unique($opts);
                        })
                        ->getOptionLabelUsing(function ($value) use ($optionConfig, $fieldName): ?string {
                            if ($value instanceof \Closure) {
                                $value = $value();
                            }
                            if (is_array($value)) {
                                $value = $value[0] ?? null;
                            }
                            if (blank($value)) {
                                return null;
                            }
                            $label = self::resolveOptionLabel($value, $optionConfig, $fieldName);

                            return filled($label) ? (string) $label : (string) $value;
                        })
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->in(function (Select $component) use ($selectOptions): array {
                            $state = $component->getState();
                            $allowed = array_merge(array_keys($selectOptions), array_values($selectOptions));
                            if (filled($state)) {
                                $allowed[] = $state;
                                $allowed[] = (string) $state;
                            }

                            return array_values(array_unique(array_filter($allowed, fn ($v) => is_string($v) || is_numeric($v))));
                        })),

            'radio' => Radio::make($name)
                ->options($locationType
                    ? fn (callable $get): array => self::getLocationOptions(
                        type: $locationType,
                        parentValue: self::getLocationParentValue($fieldName, $optionConfig, $get),
                        options: $optionConfig
                    )
                    : ($lookupTableConfig
                        ? fn (): array => self::getLookupTableOptions($lookupTableConfig)
                        : $selectOptions)),

            'checkbox_list', 'multi_select' => CheckboxList::make($name)
                ->selectAllAction(fn ($action) => $action->label(__('filament-custom-forms::fcf.admin.select_all')))
                ->deselectAllAction(fn ($action) => $action->label(__('filament-custom-forms::fcf.admin.deselect_all')))
                ->options($locationType
                    ? fn (callable $get): array => self::getLocationOptions(
                        type: $locationType,
                        parentValue: self::getLocationParentValue($fieldName, $optionConfig, $get),
                        options: $optionConfig
                    )
                    : ($lookupTableConfig
                        ? fn (): array => self::getLookupTableOptions($lookupTableConfig)
                        : $selectOptions))
                ->columns(2),

            'checkbox' => Checkbox::make($name),

            'toggle', 'boolean' => Toggle::make($name),

            'file', 'file_upload', 'upload', 'image', 'attachment', 'photo', 'document' => self::makeDynamicFileUpload($name, $optionConfig),

            default => (str_contains($name, '_image') || str_contains($name, '_file') || str_contains($name, '_attachment') || str_contains($fieldName, '_image') || str_contains($fieldName, '_file'))
                ? self::makeDynamicFileUpload($name, $optionConfig)
                : TextInput::make($name),
        };

        $component->label(self::formattedFieldLabel($label, (string) ($optionConfig['text_format'] ?? 'normal')));

        if (in_array($type, ['info', 'heading', 'label', 'display'], true) && method_exists($component, 'hiddenLabel')) {
            $component->hiddenLabel();
        }

        if (($optionConfig['is_hidden_label'] ?? false) && method_exists($component, 'hiddenLabel')) {
            $component->hiddenLabel();
        }

        if ($required && method_exists($component, 'required')) {
            $component->required();
        }

        if (($optionConfig['column_span_full'] ?? false) && method_exists($component, 'columnSpanFull')) {
            $component->columnSpanFull();
        } elseif (method_exists($component, 'columnSpan')) {
            $component->columnSpan(self::getColumnSpan($field->column_span ?? 1));
        }

        $defaultValue = self::getDefaultMappedValueForField($field->custom_form_id ?? null, $fieldName);

        if ($defaultValue !== null && method_exists($component, 'default')) {
            $component->default($defaultValue);
        }

        if (! $inputEnabled && method_exists($component, 'disabled')) {
            $component->disabled();

            if (method_exists($component, 'dehydrated')) {
                $component->dehydrated();
            }
        }

        return $component;
    }

    public static function resolveOptionLabel($value, array $options = [], string $name = ''): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (blank($value)) {
            return null;
        }

        $valStr = (string) $value;

        try {
            $geoLabel = self::getLocationLabel($value, $options);
            if (filled($geoLabel)) {
                return $geoLabel;
            }
        } catch (\Throwable $e) {
        }

        try {
            $lookupConfig = self::getLookupTableConfig($name, $options);
            if (! empty($lookupConfig)) {
                $lookupLabel = self::getLookupTableLabel($value, $lookupConfig);
                if (filled($lookupLabel)) {
                    return $lookupLabel;
                }
            }
        } catch (\Throwable $e) {
        }

        $translations = [
            'scholarship' => app()->getLocale() === 'km' ? 'អាហារូបករណ៍' : 'Scholarship',
            'full_payment' => app()->getLocale() === 'km' ? 'បង់ថ្លៃសិក្សា' : 'Full Payment',
            'male' => app()->getLocale() === 'km' ? 'ប្រុស' : 'Male',
            'female' => app()->getLocale() === 'km' ? 'ស្រី' : 'Female',
            'khmer' => app()->getLocale() === 'km' ? 'ខ្មែរ' : 'Khmer',
            'buddhism' => app()->getLocale() === 'km' ? 'ព្រះពុទ្ធ' : 'Buddhism',
            'single' => app()->getLocale() === 'km' ? 'នៅលីវ' : 'Single',
        ];

        if (isset($translations[strtolower($valStr)])) {
            return $translations[strtolower($valStr)];
        }

        $parsed = self::translateFormFieldOptions($name, self::normalizeOptions($options));
        if (isset($parsed[$valStr])) {
            return (string) $parsed[$valStr];
        }

        foreach ($parsed as $k => $v) {
            if (strcasecmp((string) $k, $valStr) === 0 || strcasecmp((string) $v, $valStr) === 0) {
                return (string) $v;
            }
        }

        return $valStr;
    }

    private static function canInputField($field): bool
    {
        return (bool) ($field->field_input ?? true);
    }

    private static function configureNumberInput(TextInput $component, array $config): TextInput
    {
        $isDecimal = (bool) ($config['is_decimal'] ?? true);
        $minValue = self::numberConfigValue($config, ['min_value', 'minimum_value', 'min', 'minimum']);
        $maxValue = self::numberConfigValue($config, ['max_value', 'maximum_value', 'max', 'maximum']);
        $attributes = [
            'inputmode' => $isDecimal ? 'decimal' : 'numeric',
        ];

        if (! $isDecimal && method_exists($component, 'integer')) {
            $component->integer();
        }

        if ($minValue !== null) {
            if (method_exists($component, 'minValue')) {
                $component->minValue($minValue);
            }

            $attributes['min'] = (string) $minValue;
        }

        if ($maxValue !== null) {
            if (method_exists($component, 'maxValue')) {
                $component->maxValue($maxValue);
            }

            $attributes['max'] = (string) $maxValue;
        }

        if ($minValue !== null || $maxValue !== null) {
            $attributes['oninput'] = "if (this.value === '' || this.value === '-' || this.value === '.') return; const value = Number(this.value); if (Number.isNaN(value)) return; const max = this.max === '' ? null : Number(this.max); if (max !== null && value > max) this.value = this.max;";
            $attributes['onblur'] = "if (this.value === '' || this.value === '-' || this.value === '.') return; const value = Number(this.value); if (Number.isNaN(value)) return; const min = this.min === '' ? null : Number(this.min); const max = this.max === '' ? null : Number(this.max); if (min !== null && value < min) this.value = this.min; if (max !== null && value > max) this.value = this.max;";
        }

        return $component->extraInputAttributes($attributes);
    }

    private static function numberConfigValue(array $config, array $keys): int|float|null
    {
        foreach ($keys as $key) {
            $value = $config[$key] ?? null;

            if ($value === '' || $value === null) {
                continue;
            }

            if (is_numeric($value)) {
                return str_contains((string) $value, '.') ? (float) $value : (int) $value;
            }
        }

        return null;
    }

    private static function makeInfoPlaceholder(string $name, string $fieldName, string $label, array $optionConfig, ?string $formSlug = null): Placeholder
    {
        $content = self::translateFormFieldContent($fieldName, $formSlug);
        $content = $content !== '' ? $content : self::stringifyLabel($optionConfig['content'] ?? null);
        $content = $content !== '' ? $content : $label;
        $isHtmlContent = self::looksLikeHtml($content);
        $style = self::infoTextStyle((string) ($optionConfig['text_format'] ?? 'normal'), $isHtmlContent);

        return Placeholder::make($name)
            ->content(new HtmlString($isHtmlContent
                ? '<div class="cf-info-placeholder-body" style="'.$style.'">'.$content.'</div>'
                : '<div class="cf-info-placeholder" style="'.$style.'"><span style="display:block;min-width:0;overflow-wrap:anywhere;">'.e($content).'</span></div>'
            ));
    }

    private static function infoTextStyle(string $format, bool $isHtmlContent = false): string
    {
        [$fontSize, $fontWeight, $lineHeight, $margin] = match (strtolower($format)) {
            'h1' => ['32px', '800', '1.15', '20px 0 12px'],
            'h2' => ['28px', '800', '1.18', '18px 0 12px'],
            'h3' => ['24px', '800', '1.2', '16px 0 10px'],
            'h4' => ['21px', '700', '1.25', '14px 0 10px'],
            'h5' => ['18px', '700', '1.35', '12px 0 8px'],
            'h6' => ['16px', '700', '1.45', '10px 0 8px'],
            'h7' => ['14px', '700', '1.5', '8px 0 6px'],
            default => $isHtmlContent
                ? ['15px', '500', '1.6', '12px 0 18px']
                : ['16px', '500', '1.5', '10px 0 8px'],
        };

        return "width:100%;margin:{$margin};font-size:{$fontSize};font-weight:{$fontWeight};line-height:{$lineHeight};";
    }

    private static function formattedFieldLabel(string $label, string $format): string|HtmlString
    {
        $style = self::labelTextStyle($format);

        if ($style === '') {
            return $label;
        }

        return new HtmlString('<span style="'.$style.'">'.e($label).'</span>');
    }

    private static function labelTextStyle(string $format): string
    {
        [$fontSize, $fontWeight, $lineHeight] = match (strtolower($format)) {
            'h1' => ['32px', '800', '1.15'],
            'h2' => ['28px', '800', '1.18'],
            'h3' => ['24px', '800', '1.2'],
            'h4' => ['21px', '700', '1.25'],
            'h5' => ['18px', '700', '1.35'],
            'h6' => ['16px', '700', '1.45'],
            'h7' => ['14px', '700', '1.5'],
            default => ['', '', ''],
        };

        return $fontSize === ''
            ? ''
            : "display:inline-block;font-size:{$fontSize};font-weight:{$fontWeight};line-height:{$lineHeight};";
    }

    private static function looksLikeHtml(string $value): bool
    {
        return $value !== strip_tags($value);
    }

    private static function makeDynamicFileUpload(string $name, array $optionConfig): FileUpload
    {
        $upload = FileUpload::make($name)
            ->disk((string) ($optionConfig['disk'] ?? config('filament-custom-forms.uploads.disk', 'public')))
            ->directory((string) ($optionConfig['directory'] ?? config('filament-custom-forms.uploads.directory', 'custom-form-uploads')))
            ->visibility((string) ($optionConfig['visibility'] ?? config('filament-custom-forms.uploads.visibility', 'public')))
            ->downloadable()
            ->openable();

        if (($optionConfig['image'] ?? false) === true && method_exists($upload, 'image')) {
            $upload->image();
        }

        if (($optionConfig['image_editor'] ?? false) === true && method_exists($upload, 'imageEditor')) {
            $upload->imageEditor();
        }

        if (($optionConfig['multiple'] ?? false) === true && method_exists($upload, 'multiple')) {
            $upload->multiple();
        }

        $acceptedFileTypes = self::getAcceptedFileTypes($optionConfig);

        if (! empty($acceptedFileTypes)) {
            $upload->acceptedFileTypes($acceptedFileTypes);
        }

        return FileUploadLimit::apply(
            $upload,
            self::getFileLimitKey($optionConfig),
            self::getFileDefaultMb($optionConfig)
        );
    }

    private static function getFileLimitKey(array $optionConfig): string
    {
        $key = $optionConfig['limit_key']
            ?? $optionConfig['file_limit_key']
            ?? $optionConfig['upload_limit_key']
            ?? null;

        return filled($key)
            ? (string) $key
            : LimitFile::KEY_DEFAULT;
    }

    private static function getFileDefaultMb(array $optionConfig): int
    {
        $defaultMb = (int) (
            $optionConfig['default_limit_mb']
            ?? $optionConfig['default_max_mb']
            ?? $optionConfig['max_mb']
            ?? 5
        );

        return $defaultMb > 0 ? $defaultMb : 5;
    }

    private static function getAcceptedFileTypes(array $optionConfig): array
    {
        $types = $optionConfig['accepted_file_types']
            ?? $optionConfig['acceptedFileTypes']
            ?? [];

        if (is_string($types)) {
            $types = array_map('trim', explode(',', $types));
        }

        if (! is_array($types)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($type): string => trim((string) $type),
            $types
        )));
    }

    private static function buildRepeaterComponents(
        $fields,
        $parentId,
        bool $parentInputEnabled = true,
        bool $forceEditable = false,
    ): array {
        if (! $fields) {
            return [];
        }

        return $fields
            ->filter(fn ($field): bool => (int) $field->parent_id === (int) $parentId)
            ->sortBy('sort')
            ->values()
            ->map(fn ($field) => self::makeFieldComponent(
                $field,
                $fields,
                '',
                $parentInputEnabled,
                $forceEditable,
            ))
            ->all();
    }

    private static function makeGeoSelect(
        string $name,
        string $fieldName,
        string $locationType,
        array $options = []
    ): Select {
        return Select::make($name)
            ->options(function (callable $get) use ($locationType, $fieldName, $options, $name): array {
                $parentValue = self::getLocationParentValue($fieldName, $options, $get);
                $opts = self::getLocationOptions(
                    type: $locationType,
                    parentValue: $parentValue,
                    options: $options
                );

                $state = $get($name) ?? $get($fieldName);
                if (filled($state) && ! is_array($state)) {
                    $valStr = (string) $state;
                    if (! isset($opts[$valStr]) && ! in_array($valStr, array_map('strval', array_values($opts)), true)) {
                        $label = self::getLocationLabel($valStr, $options);
                        $opts[$valStr] = filled($label) ? (string) $label : $valStr;
                    }
                }

                return array_unique($opts);
            })
            ->getSearchResultsUsing(fn (string $search, callable $get): array => self::getLocationOptions(
                type: $locationType,
                parentValue: self::getLocationParentValue($fieldName, $options, $get),
                options: $options,
                search: $search
            ))
            ->getOptionLabelUsing(function ($value) use ($options): ?string {
                if ($value instanceof \Closure) {
                    $value = $value();
                }
                if (blank($value)) {
                    return null;
                }
                if (is_array($value)) {
                    $value = $value[0] ?? null;
                }
                if (blank($value)) {
                    return null;
                }
                $label = self::getLocationLabel($value, $options);

                return filled($label) ? (string) $label : (string) $value;
            })
            ->searchable()
            ->preload()
            ->native(false)
            ->live()
            ->in(function (Select $component) use ($locationType, $fieldName, $options): array {
                $state = $component->getState();
                $get = fn ($path) => data_get($component->getLivewire(), $path);
                $parentValue = self::getLocationParentValue($fieldName, $options, $get);
                $opts = self::getLocationOptions($locationType, $parentValue, $options);
                $allowed = array_merge(array_keys($opts), array_values($opts));
                if (filled($state)) {
                    $allowed[] = $state;
                    $allowed[] = (string) $state;
                }

                return array_values(array_unique(array_filter($allowed, fn ($v) => is_string($v) || is_numeric($v))));
            })
            ->disabled(fn (callable $get): bool => $locationType !== 'province'
                && blank(self::getLocationParentValue($fieldName, $options, $get)))
            ->placeholder(match ($locationType) {
                'province' => 'Select province',
                'district' => 'Select district',
                'commune' => 'Select commune',
                'village' => 'Select village',
                default => 'Select location',
            })
            ->afterStateUpdated(function ($state, callable $set) use ($fieldName, $options, $name): void {
                $prefix = '';
                if (str_starts_with($name, 'entry_data_')) {
                    $prefix = 'entry_data_';
                } elseif (str_starts_with($name, 'data.')) {
                    $prefix = 'data.';
                }

                foreach (self::getChildLocationFields($fieldName, $options) as $childField) {
                    if ($prefix !== '') {
                        $set($prefix.$childField, null);
                    }
                    $set('entry_data_'.$childField, null);
                    $set('data.'.$childField, null);
                    $set($childField, null);
                }
            });
    }

    private static function makeLookupSelect(string $name, array $config): Select
    {
        return Select::make($name)
            ->options(function (callable $get) use ($name, $config): array {
                $opts = self::getLookupTableOptions($config, null, $get);
                $state = $get($name);
                if (filled($state) && ! is_array($state)) {
                    $valStr = (string) $state;
                    if (! isset($opts[$valStr])) {
                        $label = self::getLookupTableLabel($valStr, $config);
                        $opts[$valStr] = filled($label) ? (string) $label : $valStr;
                    }
                }

                return array_unique($opts);
            })
            ->getSearchResultsUsing(fn (string $search, callable $get): array => self::getLookupTableOptions($config, $search, $get))
            ->getOptionLabelUsing(function ($value) use ($config): ?string {
                if ($value instanceof \Closure) {
                    $value = $value();
                }
                if (blank($value)) {
                    return null;
                }
                if (is_array($value)) {
                    $value = $value[0] ?? null;
                }
                if (blank($value)) {
                    return null;
                }
                $label = self::getLookupTableLabel($value, $config);

                return filled($label) ? (string) $label : (string) $value;
            })
            ->searchable()
            ->preload()
            ->native(false)
            ->live()
            ->in(function (Select $component) use ($config): array {
                $state = $component->getState();
                $get = fn ($path) => data_get($component->getLivewire(), $path);
                $opts = self::getLookupTableOptions($config, null, $get);
                $allowed = array_merge(array_keys($opts), array_values($opts));
                if (filled($state)) {
                    $allowed[] = $state;
                    $allowed[] = (string) $state;
                }

                return array_values(array_unique(array_filter($allowed, fn ($v) => is_string($v) || is_numeric($v))));
            });
    }

    public static function getLookupTableConfig(string $fieldName, array $options = []): ?array
    {
        $hasStaticChoices = ! empty($options['choices']) || ! empty($options['options']) || ! empty($options['items']) || ! empty($options['values']);

        $definitions = self::lookupTableDefinitions();

        $table = $options['lookup_table']
            ?? $options['data_source_table']
            ?? $options['reference_table']
            ?? $options['source_table']
            ?? null;

        if ($hasStaticChoices && blank($table)) {
            return null;
        }

        $autoValueColumn = null;

        if (filled($table)) {
            $table = (string) $table;

            if (str_starts_with($table, 'geo_')) {
                return null;
            }

            $table = self::canonicalLookupTable($table);
        } else {
            $auto = self::guessLookupTableFromFieldName($fieldName);

            if (! $auto) {
                return null;
            }

            $table = $auto['table'];
            $autoValueColumn = $auto['value_column'] ?? null;
        }

        if (! isset($definitions[$table])) {
            return null;
        }

        if (! self::isSafeDbIdentifier($table) || ! DatabaseSchema::hasTable($table)) {
            return null;
        }

        $definition = $definitions[$table];

        $explicitValueColumn = $options['lookup_value_column']
            ?? $options['reference_value_column']
            ?? $options['value_column']
            ?? null;

        $valueColumn = filled($explicitValueColumn)
            ? (string) $explicitValueColumn
            : self::getDefaultDataSourceValueColumn(
                table: $table,
                fieldName: $fieldName,
                definition: $definition,
                autoValueColumn: $autoValueColumn,
            );

        $valueColumn = self::validLookupColumn($table, (string) $valueColumn)
            ? (string) $valueColumn
            : self::getDefaultDataSourceValueColumn(
                table: $table,
                fieldName: $fieldName,
                definition: $definition,
                autoValueColumn: $autoValueColumn,
            );

        $labelColumns = self::getLookupLabelColumns($table, $definition, $options);
        $searchColumns = self::getLookupSearchColumns($table, $definition, $labelColumns, $options);

        $activeColumn = $options['lookup_active_column']
            ?? $options['data_source_active_column']
            ?? $definition['active_column']
            ?? null;

        if ($activeColumn !== null && ! self::validLookupColumn($table, (string) $activeColumn)) {
            $activeColumn = null;
        }

        $orderColumn = $options['lookup_order_column']
            ?? $options['data_source_order_column']
            ?? $labelColumns[0]
            ?? $definition['order_column']
            ?? $valueColumn;

        if (! self::validLookupColumn($table, (string) $orderColumn)) {
            $orderColumn = $valueColumn;
        }

        return [
            'table' => $table,
            'value_column' => $valueColumn,
            'label_columns' => $labelColumns,
            'search_columns' => $searchColumns,
            'active_column' => $activeColumn,
            'active_value' => $options['lookup_active_value']
                ?? $options['data_source_active_value']
                ?? 1,
            'order_column' => $orderColumn,
        ];
    }

    private static function lookupTableDefinitions(): array
    {
        return [
            'academic_levels' => [
                'value_column' => 'id',
                'label_kh' => 'name_kh',
                'label_en' => 'name_en',
                'fallback_label_columns' => ['name_kh', 'name_en', 'code', 'id'],
                'search_columns' => ['name_kh', 'name_en', 'code'],
                'active_column' => 'is_active',
            ],
            'academic_years' => [
                'value_column' => 'id',
                'label_kh' => 'name_kh',
                'label_en' => 'name_en',
                'fallback_label_columns' => ['name_kh', 'name_en', 'code', 'id'],
                'search_columns' => ['name_kh', 'name_en', 'code'],
                'active_column' => 'is_active',
            ],
            'countries' => [
                'value_column' => 'code',
                'label_kh' => 'name_khmer',
                'label_en' => 'name_latin',
                'fallback_label_columns' => ['name_khmer', 'name_latin', 'code', 'id'],
                'search_columns' => ['name_khmer', 'name_latin', 'code'],
                'active_column' => 'is_active',
            ],
            'faculties' => [
                'value_column' => 'id',
                'label_kh' => 'title_kh',
                'label_en' => 'title_en',
                'fallback_label_columns' => ['title_kh', 'title_en', 'department_kh', 'department_en', 'code', 'id'],
                'search_columns' => ['title_kh', 'title_en', 'department_kh', 'department_en', 'code'],
                'active_column' => null,
            ],
            'fiscal_years' => [
                'value_column' => 'id',
                'label_kh' => 'name_kh',
                'label_en' => 'name_en',
                'fallback_label_columns' => ['name_kh', 'name_en', 'code', 'id'],
                'search_columns' => ['name_kh', 'name_en', 'code'],
                'active_column' => 'is_active',
            ],
            'programs' => [
                'value_column' => 'code',
                'label_kh' => 'title_kh',
                'label_en' => 'title_en',
                'fallback_label_columns' => ['title_kh', 'title_en', 'code', 'id'],
                'search_columns' => ['title_kh', 'title_en', 'code'],
                'active_column' => null,
            ],
        ];
    }

    private static function canonicalLookupTable(string $table): string
    {
        $table = Str::of($table)
            ->lower()
            ->replace(['-', ' '], '_')
            ->trim('_')
            ->toString();

        return [
            'academic_level' => 'academic_levels',
            'academic_year' => 'academic_years',
            'country' => 'countries',
            'faculty' => 'faculties',
            'fiscal_year' => 'fiscal_years',
            'program' => 'programs',
        ][$table] ?? $table;
    }

    private static function getDefaultDataSourceValueColumn(
        string $table,
        string $fieldName,
        array $definition = [],
        ?string $autoValueColumn = null,
    ): string {
        $fieldName = Str::of($fieldName)
            ->lower()
            ->replace(['-', ' '], '_')
            ->trim('_')
            ->toString();

        if (filled($autoValueColumn)) {
            return (string) $autoValueColumn;
        }

        if (Str::endsWith($fieldName, '_code') || $fieldName === 'code') {
            return 'code';
        }

        if ($table === 'programs') {
            return match ($fieldName) {
                'programs_id' => 'id',
                default => 'code',
            };
        }

        if (in_array($table, ['countries', 'academic_levels', 'academic_years', 'faculties', 'fiscal_years'], true)
            && Str::endsWith($fieldName, '_id')) {
            return 'id';
        }

        return (string) ($definition['value_column'] ?? 'id');
    }

    private static function guessLookupTableFromFieldName(string $fieldName): ?array
    {
        $lowerRaw = mb_strtolower((string) $fieldName);

        if (str_contains($lowerRaw, 'មហាវិទ្យាល័យ') || str_contains($lowerRaw, 'សាលា') || str_contains($lowerRaw, 'ដេប៉ាតឺម៉ង់') || str_contains($lowerRaw, 'faculty') || str_contains($lowerRaw, 'department')) {
            return ['table' => 'faculties', 'value_column' => 'id'];
        }

        if (str_contains($lowerRaw, 'ថ្នាក់') || str_contains($lowerRaw, 'កម្រិតសិក្សា') || str_contains($lowerRaw, 'academic_level') || str_contains($lowerRaw, 'grade') || str_contains($lowerRaw, 'class')) {
            return ['table' => 'academic_levels', 'value_column' => 'id'];
        }

        if (str_contains($lowerRaw, 'ឆ្នាំសិក្សា') || str_contains($lowerRaw, 'school_year') || str_contains($lowerRaw, 'academic_year')) {
            return ['table' => 'academic_years', 'value_column' => 'id'];
        }

        $fieldNameClean = Str::of($fieldName)
            ->lower()
            ->replace(['-', ' '], '_')
            ->trim('_')
            ->toString();

        $exact = [
            'academic_level' => ['table' => 'academic_levels', 'value_column' => 'id'],
            'academic_level_id' => ['table' => 'academic_levels', 'value_column' => 'id'],
            'academic_levels_id' => ['table' => 'academic_levels', 'value_column' => 'id'],

            'academic_year' => ['table' => 'academic_years', 'value_column' => 'id'],
            'academic_year_id' => ['table' => 'academic_years', 'value_column' => 'id'],
            'academic_years_id' => ['table' => 'academic_years', 'value_column' => 'id'],

            'country' => ['table' => 'countries', 'value_column' => 'code'],
            'country_id' => ['table' => 'countries', 'value_column' => 'id'],
            'nationality' => ['table' => 'countries', 'value_column' => 'code'],
            'nationality_id' => ['table' => 'countries', 'value_column' => 'id'],

            'faculty' => ['table' => 'faculties', 'value_column' => 'id'],
            'faculty_id' => ['table' => 'faculties', 'value_column' => 'id'],
            'faculties_id' => ['table' => 'faculties', 'value_column' => 'id'],
            'faculty_code' => ['table' => 'faculties', 'value_column' => 'code'],

            'fiscal_year' => ['table' => 'fiscal_years', 'value_column' => 'code'],
            'fiscal_year_id' => ['table' => 'fiscal_years', 'value_column' => 'id'],
            'fiscal_years_id' => ['table' => 'fiscal_years', 'value_column' => 'id'],

            'program' => ['table' => 'programs', 'value_column' => 'code'],
            'program_id' => ['table' => 'programs', 'value_column' => 'code'],
            'programs_id' => ['table' => 'programs', 'value_column' => 'id'],
            'program_code' => ['table' => 'programs', 'value_column' => 'code'],
        ];

        if (isset($exact[$fieldNameClean])) {
            return $exact[$fieldNameClean];
        }

        $suffixes = [
            '_academic_level_id' => ['table' => 'academic_levels', 'value_column' => 'id'],
            '_academic_year_id' => ['table' => 'academic_years', 'value_column' => 'id'],
            '_country_id' => ['table' => 'countries', 'value_column' => 'id'],
            '_nationality_id' => ['table' => 'countries', 'value_column' => 'id'],
            '_faculty_id' => ['table' => 'faculties', 'value_column' => 'id'],
            '_fiscal_year_id' => ['table' => 'fiscal_years', 'value_column' => 'id'],
            '_programs_id' => ['table' => 'programs', 'value_column' => 'id'],
            '_program_id' => ['table' => 'programs', 'value_column' => 'code'],
        ];

        foreach ($suffixes as $suffix => $config) {
            if (Str::endsWith($fieldNameClean, $suffix)) {
                return $config;
            }
        }

        return null;
    }

    private static function getLookupLabelColumns(string $table, array $definition, array $options): array
    {
        $requested = $options['lookup_label_column']
            ?? $options['data_source_label_column']
            ?? $options['reference_label_column']
            ?? $options['label_column']
            ?? null;

        $columns = [];

        if (filled($requested)) {
            $columns[] = (string) $requested;
        }

        $localeColumn = app()->getLocale() === 'km'
            ? ($definition['label_kh'] ?? null)
            : ($definition['label_en'] ?? null);

        if ($localeColumn) {
            $columns[] = $localeColumn;
        }

        foreach (($definition['fallback_label_columns'] ?? []) as $column) {
            $columns[] = $column;
        }

        return array_values(array_unique(array_filter(
            $columns,
            fn ($column): bool => self::validLookupColumn($table, (string) $column)
        )));
    }

    private static function getLookupSearchColumns(string $table, array $definition, array $labelColumns, array $options): array
    {
        $configured = $options['lookup_search_columns']
            ?? $options['data_source_search_columns']
            ?? null;

        $columns = [];

        if (is_string($configured)) {
            $columns = array_map('trim', explode(',', $configured));
        } elseif (is_array($configured)) {
            $columns = $configured;
        }

        $columns = array_merge($columns, $labelColumns, $definition['search_columns'] ?? []);

        return array_values(array_unique(array_filter(
            $columns,
            fn ($column): bool => self::validLookupColumn($table, (string) $column)
        )));
    }

    public static function getLookupTableOptions(array $config, ?string $search = null, ?callable $get = null): array
    {
        $table = $config['table'];
        $valueColumn = $config['value_column'];
        $labelColumns = $config['label_columns'] ?? [];
        $searchColumns = $config['search_columns'] ?? $labelColumns;

        if (! self::isSafeDbIdentifier($table) || ! DatabaseSchema::hasTable($table)) {
            return [];
        }

        $selectColumns = array_values(array_unique(array_filter(array_merge(
            [$valueColumn, $config['order_column'] ?? null],
            $labelColumns,
            $searchColumns
        ))));

        $query = DB::table($table);

        if ($get !== null && DatabaseSchema::hasColumn($table, 'faculty_id')) {
            $facultyVal = $get('faculty_id')
                ?? $get('faculty')
                ?? $get('entry_data_faculty_id')
                ?? $get('entry_data_faculty')
                ?? $get('entry_data_department')
                ?? $get('entry_data_department_id')
                ?? $get('entry_data_មហាវិទ្យាល័យ/សាលា/ដេប៉ាតឺម៉ង់');
            if (filled($facultyVal)) {
                $query->where('faculty_id', $facultyVal);
            }
        }

        if (filled($config['active_column'] ?? null)) {
            $query->where($config['active_column'], $config['active_value'] ?? 1);
        }

        if (filled($search) && ! empty($searchColumns)) {
            $search = trim((string) $search);
            $like = '%'.mb_strtolower($search).'%';

            $driver = DB::connection()->getDriverName();

            $query->where(function ($query) use ($searchColumns, $like, $driver): void {
                foreach ($searchColumns as $index => $column) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $castExpression = $driver === 'pgsql'
                        ? 'CAST('.$column.' AS TEXT)'
                        : 'CAST('.$column.' AS CHAR)';

                    $query->{$method}('LOWER(COALESCE('.$castExpression.", '')) LIKE ?", [$like]);
                }
            });
        }

        $results = $query
            ->orderBy($config['order_column'] ?? $valueColumn)
            ->limit(500)
            ->get($selectColumns);

        $options = [];
        foreach ($results as $row) {
            $rowArr = (array) $row;
            $val = $rowArr[$valueColumn] ?? null;

            if ($val === null) {
                continue;
            }

            $label = self::getLookupRowLabel($rowArr, $labelColumns);
            $options[(string) $val] = $label;
        }

        return array_unique($options);
    }

    public static function getLookupTableLabel($value, array $config): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $table = $config['table'];
        $valueColumn = $config['value_column'];
        $labelColumns = $config['label_columns'] ?? [];

        if (! self::isSafeDbIdentifier($table) || ! DatabaseSchema::hasTable($table)) {
            return null;
        }

        $selectColumns = array_values(array_unique(array_filter(array_merge(
            [$valueColumn],
            $labelColumns
        ))));

        $row = DB::table($table)
            ->where($valueColumn, $value)
            ->first($selectColumns);

        if (! $row) {
            return null;
        }

        return self::getLookupRowLabel((array) $row, $labelColumns);
    }

    private static function getLookupRowLabel(array $row, array $labelColumns): string
    {
        $label = null;
        foreach ($labelColumns as $column) {
            $value = $row[$column] ?? null;

            if (filled($value)) {
                $label = (string) $value;
                break;
            }
        }

        if (blank($label)) {
            $label = (string) ($row['code'] ?? $row['id'] ?? '');
        }

        return $label;
    }

    private static function validLookupColumn(string $table, string $column): bool
    {
        return self::isSafeDbIdentifier($column)
            && DatabaseSchema::hasTable($table)
            && DatabaseSchema::hasColumn($table, $column);
    }

    private static function isSafeDbIdentifier(string $identifier): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier);
    }

    private static function getColumnSpan($columnSpan): int|string|array
    {
        if (is_array($columnSpan)) {
            return $columnSpan;
        }

        if (is_string($columnSpan)) {
            $decoded = json_decode($columnSpan, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            if (is_numeric($columnSpan)) {
                return (int) $columnSpan;
            }

            return $columnSpan;
        }

        return is_numeric($columnSpan) ? (int) $columnSpan : 1;
    }

    private static function normalizeOptions($options): array
    {
        $options = self::decodeOptions($options);

        if (! is_array($options)) {
            return [];
        }

        if (isset($options['choices']) && is_array($options['choices'])) {
            return self::flattenOptions($options['choices']);
        }

        if (isset($options['options']) && is_array($options['options'])) {
            return self::flattenOptions($options['options']);
        }

        if (isset($options['items']) && is_array($options['items'])) {
            return self::flattenOptions($options['items']);
        }

        if (isset($options['values']) && is_array($options['values'])) {
            return self::flattenOptions($options['values']);
        }

        $configKeys = [
            'geo_location_type', 'geo_location_parent_field', 'geo_location_child_fields', 'geo_location_value_column',
            'lookup_table', 'data_source_table', 'reference_table', 'source_table',
            'lookup_value_column', 'data_source_value_column', 'reference_value_column', 'value_column',
            'lookup_label_column', 'data_source_label_column', 'reference_label_column', 'label_column',
            'lookup_search_columns', 'data_source_search_columns', 'lookup_order_column', 'data_source_order_column',
            'lookup_active_column', 'data_source_active_column', 'lookup_active_value', 'data_source_active_value',
            'is_required', 'required', 'is_hidden', 'hidden', 'is_hidden_label', 'hidden_label',
            'searchable', 'is_searchable', 'preload', 'is_preload', 'inline', 'is_inline',
            'column_span', 'columns', 'column_span_full', 'disk', 'directory', 'visibility',
            'limit_key', 'file_limit_key', 'upload_limit_key', 'default_limit_mb', 'default_max_mb', 'max_mb',
            'accepted_file_types', 'acceptedFileTypes', 'image', 'image_editor', 'multiple', 'text_format',
            'visible_when',
        ];

        foreach ($configKeys as $ck) {
            unset($options[$ck]);
        }

        return self::flattenOptions($options);
    }

    private static function decodeOptions($options): mixed
    {
        if (! is_string($options)) {
            return $options;
        }

        $decoded = json_decode($options, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $rows = preg_split('/\r\n|\r|\n/', $options);

        if (! is_array($rows)) {
            return [];
        }

        $result = [];

        foreach ($rows as $row) {
            $row = trim($row);

            if ($row === '') {
                continue;
            }

            if (str_contains($row, '=>')) {
                [$value, $label] = array_map('trim', explode('=>', $row, 2));
                $result[$value] = $label;

                continue;
            }

            if (str_contains($row, '|')) {
                [$value, $label] = array_map('trim', explode('|', $row, 2));
                $result[$value] = $label;

                continue;
            }

            $result[$row] = $row;
        }

        return $result;
    }

    private static function flattenOptions(array $options): array
    {
        $result = [];

        foreach ($options as $value => $label) {
            if (is_array($label) && self::isLocalizedOptionLabel($label)) {
                if (is_string($value) && filled($value)) {
                    $result[$value] = self::stringifyLabel($label);
                }

                continue;
            }

            if (is_array($label) && self::isOptionRecord($label)) {
                $optionValue = $label['value']
                    ?? $label['key']
                    ?? $label['id']
                    ?? (is_string($value) ? $value : null);

                $optionLabel = $label['label']
                    ?? $label['name']
                    ?? $label['name_en']
                    ?? $label['name_kh']
                    ?? $label['title']
                    ?? $optionValue;

                if (filled($optionValue) && ! is_array($optionValue)) {
                    $valStr = (string) $optionValue;
                    $lblStr = self::stringifyLabel($optionLabel);
                    $result[$valStr] = $lblStr;
                }

                continue;
            }

            if (is_array($label)) {
                foreach (self::flattenOptions($label) as $childValue => $childLabel) {
                    $valStr = (string) $childValue;
                    $lblStr = self::stringifyLabel($childLabel);
                    $result[$valStr] = $lblStr;
                }

                continue;
            }

            $valStr = (string) $value;
            $lblStr = self::stringifyLabel($label);
            if (filled($lblStr)) {
                $result[$valStr] = $lblStr;
            }
        }

        return $result;
    }

    private static function isLocalizedOptionLabel(array $label): bool
    {
        if (! array_key_exists('en', $label) && ! array_key_exists('km', $label)) {
            return false;
        }

        foreach ($label as $value) {
            if (is_array($value) || is_object($value)) {
                return false;
            }
        }

        return true;
    }

    private static function isOptionRecord(array $item): bool
    {
        return array_key_exists('value', $item)
            || array_key_exists('key', $item)
            || array_key_exists('id', $item)
            || array_key_exists('label', $item)
            || array_key_exists('name', $item)
            || array_key_exists('name_en', $item)
            || array_key_exists('name_kh', $item)
            || array_key_exists('title', $item);
    }

    public static function stringifyLabel($label): string
    {
        if (blank($label)) {
            return '';
        }

        if (is_string($label)) {
            $trimmed = trim($label);
            if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                $decoded = json_decode($label, true);
                if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                    $label = (array) $decoded;
                }
            }
        }

        if (is_array($label)) {
            $locale = app()->getLocale();
            $fallbackLocale = config('app.fallback_locale', 'en');

            $value = $label[$locale]
                ?? $label[$fallbackLocale]
                ?? $label['km']
                ?? $label['en']
                ?? $label['label']
                ?? $label['name']
                ?? $label['name_'.$locale]
                ?? $label['name_en']
                ?? $label['name_kh']
                ?? $label['title']
                ?? $label['text']
                ?? (count($label) > 0 ? reset($label) : null);

            if ($value !== null && ! is_array($value)) {
                return (string) $value;
            }

            if (is_array($value)) {
                return self::stringifyLabel($value);
            }

            return json_encode($label, JSON_UNESCAPED_UNICODE) ?: '';
        }

        if (is_bool($label)) {
            return $label ? 'Yes' : 'No';
        }

        return (string) $label;
    }

    public static function getLocationFieldType(string $fieldName, array $options = []): ?string
    {
        $dataSourceTable = $options['data_source_table'] ?? null;

        $geoDataSourceType = match ((string) $dataSourceTable) {
            'geo_province' => 'province',
            'geo_district' => 'district',
            'geo_commune' => 'commune',
            'geo_village' => 'village',
            default => null,
        };

        if ($geoDataSourceType) {
            return $geoDataSourceType;
        }

        $configuredType = $options['geo_location_type'] ?? null;

        if (in_array($configuredType, ['province', 'district', 'commune', 'village'], true)) {
            return $configuredType;
        }

        $fieldName = Str::lower($fieldName);

        $exactMap = [
            'province' => 'province',
            'province_id' => 'province',
            'pob_province_id' => 'province',
            'district' => 'district',
            'district_id' => 'district',
            'pob_district_id' => 'district',
            'commune' => 'commune',
            'commune_id' => 'commune',
            'pob_commune_id' => 'commune',
            'village' => 'village',
            'village_id' => 'village',
            'pob_village_id' => 'village',
        ];

        if (isset($exactMap[$fieldName])) {
            return $exactMap[$fieldName];
        }

        return match (true) {
            Str::endsWith($fieldName, ['_province', '_province_id', '_capital_province']) => 'province',
            Str::endsWith($fieldName, ['_district', '_district_id', '_district_khan']) => 'district',
            Str::endsWith($fieldName, ['_commune', '_commune_id', '_commune_sangkat']) => 'commune',
            Str::endsWith($fieldName, ['_village', '_village_id']) => 'village',
            default => null,
        };
    }

    public static function getLocationParentValue(string $fieldName, array $options, callable $get): mixed
    {
        if (filled($options['geo_location_parent_field'] ?? null)) {
            $parentField = (string) $options['geo_location_parent_field'];

            return $get($parentField)
                ?? $get('entry_data_'.$parentField)
                ?? $get('data.'.$parentField);
        }

        $parentField = self::guessParentLocationField($fieldName);

        if ($parentField) {
            return $get($parentField)
                ?? $get('entry_data_'.$parentField)
                ?? $get('data.'.$parentField);
        }

        return null;
    }

    protected static function guessParentLocationField(string $fieldName): ?string
    {
        $fieldName = Str::lower($fieldName);

        $parentField = match ($fieldName) {
            'district' => 'province',
            'commune' => 'district',
            'village' => 'commune',
            'district_id' => 'province_id',
            'commune_id' => 'district_id',
            'village_id' => 'commune_id',
            'pob_district_id' => 'pob_province_id',
            'pob_commune_id' => 'pob_district_id',
            'pob_village_id' => 'pob_commune_id',
            default => null,
        };

        if ($parentField) {
            return $parentField;
        }

        $patterns = [
            '_district_id' => '_province_id',
            '_commune_id' => '_district_id',
            '_village_id' => '_commune_id',
            '_district' => '_province',
            '_commune' => '_district',
            '_village' => '_commune',
        ];

        foreach ($patterns as $childSuffix => $parentSuffix) {
            if (Str::endsWith($fieldName, $childSuffix)) {
                return Str::beforeLast($fieldName, $childSuffix).$parentSuffix;
            }
        }

        return null;
    }

    protected static function getChildLocationFields(string $fieldName, array $options = []): array
    {
        // Old records may still have this value. New UI no longer asks the user
        // to type child field names; it auto-detects them from the field name.
        if (! empty($options['geo_location_child_fields'])) {
            $children = $options['geo_location_child_fields'];

            if (is_string($children)) {
                $children = array_map('trim', explode(',', $children));
            }

            return is_array($children)
                ? array_values(array_filter($children))
                : [];
        }

        $fieldName = Str::lower($fieldName);

        $exact = match ($fieldName) {
            'province' => ['district', 'commune', 'village'],
            'district' => ['commune', 'village'],
            'commune' => ['village'],
            'province_id' => ['district_id', 'commune_id', 'village_id'],
            'district_id' => ['commune_id', 'village_id'],
            'commune_id' => ['village_id'],
            'pob_province_id' => ['pob_district_id', 'pob_commune_id', 'pob_village_id'],
            'pob_district_id' => ['pob_commune_id', 'pob_village_id'],
            'pob_commune_id' => ['pob_village_id'],
            default => null,
        };

        if ($exact !== null) {
            return $exact;
        }

        // Generic auto-reset support:
        // current_province_id -> current_district_id,current_commune_id,current_village_id
        // birth_district_id   -> birth_commune_id,birth_village_id
        $patterns = [
            '_province_id' => ['_district_id', '_commune_id', '_village_id'],
            '_district_id' => ['_commune_id', '_village_id'],
            '_commune_id' => ['_village_id'],
            '_province' => ['_district', '_commune', '_village'],
            '_district' => ['_commune', '_village'],
            '_commune' => ['_village'],
        ];

        foreach ($patterns as $parentSuffix => $childSuffixes) {
            if (Str::endsWith($fieldName, $parentSuffix)) {
                $prefix = Str::beforeLast($fieldName, $parentSuffix);

                return array_map(
                    fn (string $childSuffix): string => $prefix.$childSuffix,
                    $childSuffixes
                );
            }
        }

        return [];
    }

    protected static function getDefaultGeoValueColumn(array $options = []): string
    {
        // New UI removed Geo Value Column. Always save geo ID because
        // geo_locations.parent_id uses ID for province -> district -> commune -> village.
        return 'id';
    }

    public static function getLocationOptions(
        string $type,
        mixed $parentValue = null,
        array $options = [],
        ?string $search = null
    ): array {
        if (! class_exists(GeoLocation::class) || ! DatabaseSchema::hasTable('geo_locations')) {
            return [];
        }

        if ($type !== 'province' && blank($parentValue)) {
            return [];
        }

        $locale = app()->getLocale();

        $nameColumn = ($locale === 'km' && DatabaseSchema::hasColumn('geo_locations', 'name_kh'))
            ? 'name_kh'
            : 'name_en';

        if (! DatabaseSchema::hasColumn('geo_locations', $nameColumn)) {
            $nameColumn = 'name_en';
        }

        $valueColumn = self::getDefaultGeoValueColumn($options);

        $query = GeoLocation::query()
            ->where('type', $type)
            ->where('is_active', true);

        if ($type !== 'province') {
            $parentId = self::getLocationParentId(
                value: $parentValue,
                childType: $type,
                valueColumn: $valueColumn
            );

            if ($parentId) {
                $query->where('parent_id', $parentId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (filled($search)) {
            $search = trim((string) $search);

            $query->where(function ($query) use ($search): void {
                $like = '%'.mb_strtolower($search).'%';

                $query
                    ->whereRaw('LOWER(COALESCE(name_en, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(name_kh, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(code, \'\')) LIKE ?', [$like]);
            });
        }

        $locations = $query
            ->orderBy($nameColumn)
            ->limit(500)
            ->get(['id', 'code', 'name_en', 'name_kh']);

        $options = [];
        foreach ($locations as $location) {
            $value = $valueColumn === 'id'
                ? (string) $location->id
                : (string) $location->code;

            $label = $location->{$nameColumn}
                ?: $location->name_kh
                ?: $location->name_en
                ?: $location->code;

            $options[$value] = (string) $label;
        }

        return array_unique($options);
    }

    public static function getLocationLabel($value, array $options = []): ?string
    {
        if (! filled($value)) {
            return null;
        }

        if (! class_exists(GeoLocation::class) || ! DatabaseSchema::hasTable('geo_locations')) {
            return null;
        }

        $locale = app()->getLocale();

        $nameColumn = ($locale === 'km' && DatabaseSchema::hasColumn('geo_locations', 'name_kh'))
            ? 'name_kh'
            : 'name_en';

        $valueColumn = self::getDefaultGeoValueColumn($options);

        $location = self::findLocationByValue($value, valueColumn: $valueColumn);

        if (! $location) {
            return null;
        }

        return $location->{$nameColumn}
            ?: $location->name_kh
            ?: $location->name_en
            ?: $location->code;
    }

    protected static function getLocationParentId(mixed $value, string $childType, string $valueColumn = 'code'): ?int
    {
        $parentType = match ($childType) {
            'district' => 'province',
            'commune' => 'district',
            'village' => 'commune',
            default => null,
        };

        if (! $parentType) {
            return null;
        }

        $location = self::findLocationByValue(
            value: $value,
            type: $parentType,
            valueColumn: $valueColumn
        );

        return $location ? (int) $location->id : null;
    }

    protected static function findLocationByValue(mixed $value, ?string $type = null, string $valueColumn = 'code'): ?GeoLocation
    {
        if (! filled($value)) {
            return null;
        }

        $value = (string) $value;
        $baseQuery = GeoLocation::query()
            ->when($type, fn ($query) => $query->where('type', $type));

        if ($valueColumn === 'id' && is_numeric($value)) {
            $location = (clone $baseQuery)
                ->whereKey((int) $value)
                ->first(['id', 'code', 'name_en', 'name_kh']);

            if ($location) {
                return $location;
            }
        }

        $location = (clone $baseQuery)
            ->where('code', $value)
            ->first(['id', 'code', 'name_en', 'name_kh']);

        if ($location) {
            return $location;
        }

        if (is_numeric($value)) {
            return (clone $baseQuery)
                ->whereKey((int) $value)
                ->first(['id', 'code', 'name_en', 'name_kh']);
        }

        return null;
    }

    private static function getSourceFormOptions($currentFormId): array
    {
        $allowedSourceSlugs = self::getAllowedSourceSlugsForCurrentForm($currentFormId);

        if (empty($allowedSourceSlugs)) {
            return [];
        }

        return CustomForm::query()
            ->where('is_active', true)
            ->whereIn('slug', self::getSlugVariants($allowedSourceSlugs))
            ->when(
                filled($currentFormId),
                fn ($query) => $query->where('id', '!=', $currentFormId)
            )
            ->orderByRaw(self::copySourceOrderSql($allowedSourceSlugs))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'slug'])
            ->mapWithKeys(fn (CustomForm $form): array => [
                (string) $form->id => self::translateFormName($form),
            ])
            ->toArray();
    }

    private static function searchSourceFormOptions($currentFormId, string $search): array
    {
        $search = trim($search);
        $allowedSourceSlugs = self::getAllowedSourceSlugsForCurrentForm($currentFormId);

        if (empty($allowedSourceSlugs)) {
            return [];
        }

        return CustomForm::query()
            ->where('is_active', true)
            ->whereIn('slug', self::getSlugVariants($allowedSourceSlugs))
            ->when(
                filled($currentFormId),
                fn ($query) => $query->where('id', '!=', $currentFormId)
            )
            ->when($search !== '', function ($query) use ($search): void {
                self::whereLike($query, 'name', $search);
            })
            ->orderByRaw(self::copySourceOrderSql($allowedSourceSlugs))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'slug'])
            ->mapWithKeys(fn (CustomForm $form): array => [
                (string) $form->id => self::translateFormName($form),
            ])
            ->toArray();
    }

    private static function getSourceFormLabel($value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $form = CustomForm::query()
            ->whereKey($value)
            ->first(['id', 'name', 'slug']);

        return $form ? self::translateFormName($form) : null;
    }

    private static function copyLatestEntryDataFromForm($sourceFormId, $currentFormId, callable $set): void
    {
        if (! filled($sourceFormId) || ! filled($currentFormId)) {
            return;
        }

        $currentForm = CustomForm::query()
            ->with(['fields' => fn ($query) => $query->orderBy('sort')])
            ->find($currentFormId);

        if (! $currentForm) {
            return;
        }

        $copySourceFormIds = self::getCopySourceFormIdsForFlow($sourceFormId, $currentForm);

        if (empty($copySourceFormIds)) {
            return;
        }

        $sourceData = [];

        foreach ($copySourceFormIds as $formId) {
            $entryData = self::getLatestEntryDataByFormId($formId);

            if (! empty($entryData)) {
                // Later forms override earlier forms.
                // Enrollment flow: Profile first, then Print Application overrides it.
                $sourceData = array_replace_recursive($sourceData, $entryData);
            }
        }

        if (empty($sourceData)) {
            return;
        }

        foreach ($currentForm->fields as $field) {
            if (self::isBuilderSectionField($field)) {
                continue;
            }

            $targetKey = (string) $field->name;
            $value = self::getMappedValue($sourceData, $targetKey);

            if ($value !== null) {
                $set('data.'.$targetKey, $value);
            }
        }
    }

    private static function isProfileFormId($customFormId): bool
    {
        return in_array(self::getFormSlugById($customFormId), [
            'profile',
            'profile-form',
            'student-profile',
            'resume-form',
        ], true);
    }

    private static function copyDefaultSourceDataForForm($currentFormId, callable $set): void
    {
        if (! self::shouldAutoCopySourceData($currentFormId)) {
            return;
        }

        $sourceFormId = self::getDefaultSourceFormIdForCurrentForm($currentFormId);

        if (! $sourceFormId) {
            return;
        }

        self::copyLatestEntryDataFromForm(
            sourceFormId: $sourceFormId,
            currentFormId: $currentFormId,
            set: $set
        );
    }

    private static function shouldAutoCopySourceData($currentFormId): bool
    {
        return filled($currentFormId)
            && request()->routeIs('filament.*.resources.custom-form-entries.create')
            && self::canCopyFromSourceForm($currentFormId);
    }

    private static function getDefaultSourceFormIdForCurrentForm($currentFormId): ?int
    {
        $allowedSourceSlugs = self::getAllowedSourceSlugsForCurrentForm($currentFormId);

        if (empty($allowedSourceSlugs)) {
            return null;
        }

        return self::getFirstActiveFormIdBySlugs($allowedSourceSlugs);
    }

    private static function canCopyFromSourceForm($currentFormId): bool
    {
        return ! empty(self::getAllowedSourceSlugsForCurrentForm($currentFormId));
    }

    private static function getAllowedSourceSlugsForCurrentForm($currentFormId): array
    {
        $currentSlug = self::getFormSlugById($currentFormId);

        return match ($currentSlug) {
            // Flow: Profile -> Print Application
            'national-exam', 'print-application', 'specialty-exam-form' => self::profileSourceSlugs(),

            // Flow: Profile -> Print Application -> Enrollment
            // UI shows only Print Application. When selected, system also copies Profile first.
            'enrollment' => self::printApplicationSourceSlugs(),

            default => [],
        };
    }

    private static function getCopySourceFormIdsForFlow($sourceFormId, CustomForm $currentForm): array
    {
        $sourceForm = CustomForm::query()->find($sourceFormId);

        if (! $sourceForm) {
            return [];
        }

        $currentSlug = self::normalizeFormSlug($currentForm->slug ?? null);
        $sourceSlug = self::normalizeFormSlug($sourceForm->slug ?? null);

        if (in_array($currentSlug, self::printApplicationSourceSlugs(), true) && in_array($sourceSlug, self::profileSourceSlugs(), true)) {
            return [(int) $sourceForm->id];
        }

        if ($currentSlug === 'enrollment' && in_array($sourceSlug, self::printApplicationSourceSlugs(), true)) {
            $profileFormId = self::getFirstActiveFormIdBySlugs(self::profileSourceSlugs());

            return array_values(array_filter([
                $profileFormId,
                (int) $sourceForm->id,
            ]));
        }

        // Safe fallback if you add another flow later.
        return [(int) $sourceForm->id];
    }

    private static function getLatestEntryDataByFormId(int $formId): array
    {
        $entry = CustomFormEntry::query()
            ->where('custom_form_id', $formId)
            ->where('created_by', auth()->id())
            ->latest('id')
            ->first();

        if (! $entry) {
            return [];
        }

        return is_array($entry->data ?? null) ? $entry->data : [];
    }

    private static function getDefaultMappedValueForField($currentFormId, string $targetKey)
    {
        if (! self::shouldAutoCopySourceData($currentFormId)) {
            return null;
        }

        return self::getMappedValue(
            sourceData: self::getDefaultSourceDataForCurrentForm($currentFormId),
            targetKey: $targetKey
        );
    }

    private static function getDefaultSourceDataForCurrentForm($currentFormId): array
    {
        static $cache = [];

        $cacheKey = (int) $currentFormId.':'.(int) auth()->id();

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $sourceFormId = self::getDefaultSourceFormIdForCurrentForm($currentFormId);

        if (! $sourceFormId) {
            return $cache[$cacheKey] = [];
        }

        $currentForm = CustomForm::query()
            ->find($currentFormId);

        if (! $currentForm) {
            return $cache[$cacheKey] = [];
        }

        $sourceData = [];

        foreach (self::getCopySourceFormIdsForFlow($sourceFormId, $currentForm) as $formId) {
            $entryData = self::getLatestEntryDataByFormId($formId);

            if (! empty($entryData)) {
                $sourceData = array_replace_recursive($sourceData, $entryData);
            }
        }

        return $cache[$cacheKey] = $sourceData;
    }

    private static function getFirstActiveFormIdBySlugs(array $slugs): ?int
    {
        return CustomForm::query()
            ->where('is_active', true)
            ->whereIn('slug', self::getSlugVariants($slugs))
            ->orderBy('id')
            ->value('id');
    }

    private static function profileSourceSlugs(): array
    {
        return [
            'profile',
            'profile-form',
            'resume-form',
            'student-profile',
            'student_profile',
        ];
    }

    private static function printApplicationSourceSlugs(): array
    {
        return [
            'national-exam',
            'print-application',
            'specialty-exam-form',
        ];
    }

    private static function getFormSlugById($formId): ?string
    {
        if (! filled($formId)) {
            return null;
        }

        $slug = CustomForm::query()
            ->whereKey($formId)
            ->value('slug');

        return self::normalizeFormSlug($slug);
    }

    private static function normalizeFormSlug($slug): ?string
    {
        if (! filled($slug)) {
            return null;
        }

        return Str::of((string) $slug)
            ->lower()
            ->replace(['_', ' '], '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->toString();
    }

    private static function getSlugVariants(array $slugs): array
    {
        $variants = [];

        foreach ($slugs as $slug) {
            $normalized = self::normalizeFormSlug($slug);

            if (! $normalized) {
                continue;
            }

            $variants[] = $normalized;
            $variants[] = str_replace('-', '_', $normalized);
            $variants[] = str_replace('-', ' ', $normalized);
        }

        return array_values(array_unique($variants));
    }

    private static function copySourceOrderSql(array $allowedSourceSlugs): string
    {
        $driver = DB::connection()->getDriverName();
        $cases = [];

        foreach (array_values($allowedSourceSlugs) as $index => $slug) {
            foreach (self::getSlugVariants([$slug]) as $variant) {
                $escaped = str_replace("'", "''", $variant);
                $cases[] = "WHEN slug = '{$escaped}' THEN {$index}";
            }
        }

        if (empty($cases)) {
            return 'name asc';
        }

        return 'CASE '.implode(' ', $cases).' ELSE 99 END';
    }

    private static function whereLike($query, string $column, string $search): void
    {
        $driver = DB::connection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

        $query->where($column, $operator, '%'.$search.'%');
    }

    private static function getMappedValue(array $sourceData, string $targetKey)
    {
        $map = [
            'first_name_kh' => ['first_name_kh', 'first_name_khmer', 'receipt_first_name_kh', 'exam_first_name_kh', 'name_khmer', 'name_kh'],
            'last_name_kh' => ['last_name_kh', 'last_name_khmer', 'receipt_last_name_kh', 'exam_last_name_kh'],
            'first_name_en' => ['first_name_en', 'first_name_english', 'receipt_first_name_en', 'exam_first_name_en', 'name_english', 'name_en'],
            'last_name_en' => ['last_name_en', 'last_name_english', 'receipt_last_name_en', 'exam_last_name_en'],
            'name_en' => ['name_en', 'name_english', 'student_name_en', 'full_name_en', 'first_name_en'],
            'name_kh' => ['name_kh', 'name_khmer', 'student_name_kh', 'full_name_kh', 'first_name_kh'],
            'gender' => ['gender', 'sex', 'receipt_gender', 'exam_gender', 'contract_gender'],
            'dob' => ['dob', 'date_of_birth', 'birth_date'],
            'date_of_birth' => ['date_of_birth', 'dob', 'birth_date', 'receipt_date_of_birth', 'exam_date_of_birth', 'contract_date_of_birth'],
            'phone' => ['phone', 'phone_number', 'tel', 'telephone'],
            'phone_number' => ['phone_number', 'phone', 'tel', 'telephone', 'personal_phone_number', 'receipt_phone_number', 'exam_phone_number'],
            'email' => ['email', 'email_address', 'exam_email'],
            'nationality' => ['nationality', 'receipt_nationality'],
            'place_of_birth' => ['place_of_birth', 'receipt_place_of_birth', 'exam_place_of_birth'],
            'current_degree' => ['current_degree', 'receipt_current_degree', 'exam_current_degree_level'],
            'university_school' => ['university_school', 'receipt_university_school', 'exam_from_university'],
            'specialty' => ['specialty', 'receipt_specialty', 'exam_specialization', 'contract_specialty'],
            'academic_year' => ['academic_year', 'receipt_year'],
            'year' => ['year', 'receipt_year', 'academic_year'],
            'parent_phone_number' => ['parent_phone_number', 'father_phone_number', 'mother_phone_number'],
            'guardian_phone_number' => ['guardian_phone_number', 'guardian_telephone_number'],
            'guardian_relationship' => ['guardian_relationship', 'guardian_must_be'],
            'guardian_name' => ['guardian_name', 'guardian_name_must_be'],
            'father_name' => ['father_name'],
            'father_date_of_birth' => ['father_date_of_birth', 'father_year_of_birth'],
            'father_occupation' => ['father_occupation'],
            'mother_name' => ['mother_name'],
            'mother_date_of_birth' => ['mother_date_of_birth', 'mother_year_of_birth'],
            'mother_occupation' => ['mother_occupation'],
            'ethnicity' => ['ethnicity'],
            'religion' => ['religion'],
            'married_status' => ['married_status'],
            'current_position' => ['current_position', 'current_occupation'],
            'university_organization' => ['university_organization', 'place_of_work'],

            'province' => ['province', 'province_id', 'pob_province_id', 'birth_province_city', 'current_capital_province', 'current_province'],
            'district' => ['district', 'district_id', 'pob_district_id', 'birth_district_khan', 'current_district_khan', 'current_district'],
            'commune' => ['commune', 'commune_id', 'pob_commune_id', 'birth_commune_sangkat', 'current_commune_sangkat', 'current_commune'],
            'village' => ['village', 'village_id', 'pob_village_id', 'birth_village', 'current_village'],

            'province_id' => ['province_id', 'province', 'pob_province_id', 'birth_province_city', 'current_capital_province', 'current_province'],
            'district_id' => ['district_id', 'district', 'pob_district_id', 'birth_district_khan', 'current_district_khan', 'current_district'],
            'commune_id' => ['commune_id', 'commune', 'pob_commune_id', 'birth_commune_sangkat', 'current_commune_sangkat', 'current_commune'],
            'village_id' => ['village_id', 'village', 'pob_village_id', 'birth_village', 'current_village'],

            'pob_province_id' => ['pob_province_id', 'province_id', 'province', 'birth_province_city'],
            'pob_district_id' => ['pob_district_id', 'district_id', 'district', 'birth_district_khan'],
            'pob_commune_id' => ['pob_commune_id', 'commune_id', 'commune', 'birth_commune_sangkat'],
            'pob_village_id' => ['pob_village_id', 'village_id', 'village', 'birth_village'],
            'application_province' => ['application_province', 'current_capital_province', 'current_province'],
            'current_house_number' => ['current_house_number'],
            'current_street_number' => ['current_street_number'],
            'current_province' => ['current_province', 'current_capital_province'],
            'current_district' => ['current_district', 'current_district_khan'],
            'current_commune' => ['current_commune', 'current_commune_sangkat'],
            'current_village' => ['current_village'],
        ];

        $keys = $map[$targetKey] ?? [$targetKey];

        return self::firstValue($sourceData, $keys);
    }

    private static function firstValue(array $data, array $keys)
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);

            if (is_array($value)) {
                continue;
            }

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }
}
