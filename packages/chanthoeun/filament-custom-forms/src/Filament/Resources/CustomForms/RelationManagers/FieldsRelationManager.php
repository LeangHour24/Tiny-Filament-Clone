<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\RelationManagers;

use App\Support\CustomFormFieldSelection;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';
    protected static bool $isLazy = false;

    private const CONTAINER_TYPES = [
        'step',
        'section',
        'grid',
        'fieldset',
        'repeater',
        'wizard',
    ];

    private const CHOICE_TYPES = [
        'select',
        'select_dropdown',
        'radio',
        'checkbox_list',
        'multi_select',
    ];

    private const PARENT_FORM_TYPE = '__parent_form';
    private const FORM_TARGET_PREFIX = 'form:';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        \Filament\Forms\Components\ToggleButtons::make('creation_mode')
                            ->hiddenLabel()
                            ->options([
                                'creating' => __('filament-custom-forms::fcf.admin.creating_tab'),
                                'selection' => __('filament-custom-forms::fcf.admin.selection_tab'),
                            ])
                            ->default('creating')
                            ->inline()
                            ->live()
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'uhs-custom-field-mode-toggle'])
                            ->extraFieldWrapperAttributes(['style' => 'display:flex;flex-direction:column;align-items:center;'])
                            ->hidden(fn (?object $record = null): bool => filled($record)),

                        \Filament\Forms\Components\Select::make('parent_id')
                            ->label(__('filament-custom-forms::fcf.admin.parent_container'))
                            ->options(function ($livewire) {
                                return $livewire->getOwnerRecord()->fields()
                                    ->whereIn('type', self::CONTAINER_TYPES)
                                    ->orderBy('sort')
                                    ->get()
                                    ->mapWithKeys(fn ($field) => [
                                        $field->id => self::localeText($field->label ?? $field->name),
                                    ]);
                            })
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->visible(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get)),

                        \Filament\Forms\Components\TextInput::make('name')
                            ->label(__('filament-custom-forms::fcf.field.name'))
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule, $livewire) => $rule->where('custom_form_id', $livewire->getOwnerRecord()->id)
                            )
                            ->helperText(__('filament-custom-forms::fcf.builder.fields.name_help'))
                            ->required(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get))
                            ->visible(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get)),

                        \Filament\Forms\Components\Select::make('type')
                            ->label(__('filament-custom-forms::fcf.field.type'))
                            ->options([
                                'Containers' => [
                                    'step' => 'Step',
                                    'section' => 'Section',
                                    'grid' => 'Grid',
                                    'fieldset' => 'Fieldset',
                                    'repeater' => 'Repeater',
                                    'wizard' => 'Wizard',
                                ],
                                'Fields' => [
                                    'text_input' => 'Text Input',
                                    'textarea' => 'Text Area',
                                    'email' => 'Email',
                                    'number_input' => 'Number Input',
                                    'money' => 'Currency Input Box',
                                    'date_picker' => 'Date Picker',
                                    'time_picker' => 'Time Picker',
                                    'boolean' => 'Boolean (Toggle)',
                                    'select_dropdown' => 'Select Dropdown',
                                    'radio' => 'Radio',
                                    'checkbox' => 'Checkbox',
                                    'checkbox_list' => 'Checkbox List',
                                    'multi_select' => 'Multi Select',
                                    'info' => 'Info',
                                    'image' => 'Image',
                                    'file_upload' => 'File Upload',
                                    'password' => 'Password',
                                    'phone' => 'Phone Number',
                                ],
                            ])
                            ->default('text_input')
                            ->native()
                            ->columnSpan(2)
                            ->live()
                            ->required(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get))
                            ->visible(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get)),

                        \Filament\Schemas\Components\Grid::make(2)
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('options.min_value')
                                    ->label(__('filament-custom-forms::fcf.admin.minimum_value'))
                                    ->numeric()
                                    ->helperText(__('filament-custom-forms::fcf.admin.number_range_help')),

                                \Filament\Forms\Components\TextInput::make('options.max_value')
                                    ->label(__('filament-custom-forms::fcf.admin.maximum_value'))
                                    ->numeric()
                                    ->helperText(__('filament-custom-forms::fcf.admin.number_range_help')),
                            ])
                            ->columnSpanFull()
                            ->visible(fn ($get, ?object $record = null): bool => (filled($record) || self::isCreatingMode($get))
                                && in_array((string) $get('type'), ['number_input', 'number'], true)),

                        \Filament\Forms\Components\Toggle::make('required')
                            ->label(__('filament-custom-forms::fcf.field.is_required'))
                            ->default(false)
                            ->visible(fn ($get, ?object $record = null): bool => (filled($record) || self::isCreatingMode($get))
                                && ! in_array((string) $get('type'), self::CONTAINER_TYPES, true)),

                        \Filament\Forms\Components\Toggle::make('field_input')
                            ->label(__('filament-custom-forms::fcf.admin.field_input'))
                            ->default(true)
                            ->dehydrated(fn (): bool => self::hasCustomFormFieldColumn('field_input'))
                            ->visible(fn ($get, ?object $record = null): bool => (filled($record) || self::isCreatingMode($get))
                                && self::hasCustomFormFieldColumn('field_input')
                                && ! in_array((string) $get('type'), self::CONTAINER_TYPES, true)
                                && (string) $get('type') !== 'info'),

                        \Filament\Schemas\Components\Section::make(__('filament-custom-forms::fcf.admin.field_label_section'))
                            ->columns(2)
                            ->columnSpanFull()
                            ->visible(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get))
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('label_en')
                                    ->label(__('filament-custom-forms::fcf.admin.label_english'))
                                    ->required(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get))
                                    ->afterStateHydrated(function ($component, $record): void {
                                        $component->state(self::getLangValue($record?->label, 'en'));
                                    }),

                                \Filament\Forms\Components\TextInput::make('label_km')
                                    ->label(__('filament-custom-forms::fcf.admin.label_khmer'))
                                    ->required(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get))
                                    ->afterStateHydrated(function ($component, $record): void {
                                        $component->state(self::getLangValue($record?->label, 'km'));
                                    }),

                                \Filament\Forms\Components\Select::make('options.text_format')
                                    ->label(__('filament-custom-forms::fcf.admin.text_format'))
                                    ->options(self::textFormatOptions())
                                    ->default('normal')
                                    ->native(false),
                            ]),

                        \Filament\Schemas\Components\Section::make(__('filament-custom-forms::fcf.admin.dynamic_form_type_field'))
                            ->columnSpanFull()
                            ->columns(1)
                            ->visible(function ($livewire, $get): bool {
                                $owner = $livewire->getOwnerRecord();
                                return ! self::isSelectionMode($get) && $owner && in_array($owner->menu_placement, ['sidebar', 'sub_item'], true);
                            })
                            ->components([
                                \Filament\Forms\Components\CheckboxList::make('options.visible_when.values')
                                    ->label(__('filament-custom-forms::fcf.field.form_type'))
                                    ->options(fn (): array => self::dynamicFormTargetOptions())
                                    ->columns(2)
                                    ->bulkToggleable()
                                    ->selectAllAction(fn ($action) => $action->label(__('filament-custom-forms::fcf.admin.select_all')))
                                    ->deselectAllAction(fn ($action) => $action->label(__('filament-custom-forms::fcf.admin.deselect_all')))
                                    ->afterStateHydrated(function ($component, $state, $record): void {
                                        if (filled($state)) {
                                            return;
                                        }

                                        $oldValue = data_get($record?->options, 'visible_when.value');

                                        if (is_string($oldValue) && str_starts_with($oldValue, self::FORM_TARGET_PREFIX)) {
                                            $component->state([$oldValue]);

                                            return;
                                        }

                                        $ownerForm = $record?->form;

                                        if ($ownerForm && $ownerForm->menu_placement === 'sidebar') {
                                            $component->state([self::formTargetValue($ownerForm->id)]);

                                            return;
                                        }

                                        if (
                                            $ownerForm
                                            && $ownerForm->menu_placement === 'sub_item'
                                            && filled($ownerForm->sub_item_type)
                                        ) {
                                            $component->state([self::formTargetValue($ownerForm->id)]);
                                        }
                                    })
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set): void {
                                        if (filled($state)) {
                                            $set('options.visible_when.field', 'form_selection');
                                            $set('options.visible_when.operator', '=');
                                        } else {
                                            $set('options.visible_when.field', null);
                                            $set('options.visible_when.operator', null);
                                        }
                                    }),

                                \Filament\Forms\Components\Hidden::make('options.visible_when.field')
                                    ->default('form_selection'),

                                \Filament\Forms\Components\Hidden::make('options.visible_when.operator')
                                    ->default('='),
                            ]),

                        \Filament\Schemas\Components\Section::make(__('filament-custom-forms::fcf.admin.multiple_creating_selection_field'))
                            ->columnSpanFull()
                            ->columns(2)
                            ->visible(fn ($get, ?object $record = null): bool => blank($record) && self::isSelectionMode($get))
                            ->components([
                                \Filament\Forms\Components\Select::make('selection_source_forms')
                                    ->label(__('filament-custom-forms::fcf.admin.selection_form'))
                                    ->options(fn ($livewire): array => CustomFormFieldSelection::formOptions($livewire->getOwnerRecord()->id))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->columnSpanFull()
                                    ->afterStateUpdated(fn ($set) => $set('options.visible_when.fields', [])),

                                \Filament\Forms\Components\Select::make('options.visible_when.fields')
                                    ->label(__('filament-custom-forms::fcf.admin.select_field'))
                                    ->hintAction(
                                        \Filament\Actions\Action::make('selectAllFields')
                                            ->label(__('filament-custom-forms::fcf.admin.select_all'))
                                            ->disabled(fn ($get): bool => blank($get('selection_source_forms')))
                                            ->action(function ($component): void {
                                                $component->state(array_keys($component->getOptions()));
                                                $component->callAfterStateUpdated();
                                            })
                                    )
                                    ->options(fn ($livewire, $get): array => CustomFormFieldSelection::fieldOptions(
                                        $livewire->getOwnerRecord(),
                                        filled($get('selection_source_forms')) ? (int) $get('selection_source_forms') : null,
                                    ))
                                    ->required(fn ($get): bool => self::isSelectionMode($get))
                                    ->searchable()
                                    ->preload()
                                    ->multiple()
                                    ->native(false)
                                    ->live()
                                    ->columnSpanFull()
                                    ->afterStateUpdated(function ($state, $set): void {
                                        $set('options.visible_when.operator', 'in');
                                    }),
                            ]),

                        \Filament\Schemas\Components\Section::make(__('filament-custom-forms::fcf.admin.skip_logic'))
                            ->columnSpanFull()
                            ->columns(2)
                            ->visible(fn ($get, ?object $record = null): bool => (filled($record) || self::isCreatingMode($get))
                                && ! in_array((string) $get('type'), self::CONTAINER_TYPES, true))
                            ->schema([
                                \Filament\Forms\Components\Toggle::make('options.conditional_when.enabled')
                                    ->label(__('filament-custom-forms::fcf.admin.skip_logic_enabled'))
                                    ->helperText(__('filament-custom-forms::fcf.admin.skip_logic_enabled_help'))
                                    ->live()
                                    ->columnSpanFull()
                                    ->afterStateHydrated(function ($component, $state, ?object $record): void {
                                        if (filled($state)) {
                                            return;
                                        }

                                        $visibleField = data_get($record?->options, 'visible_when.field');

                                        if (filled($visibleField) && $visibleField !== 'form_selection') {
                                            $component->state(true);
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, $set): void {
                                        if ((bool) $state) {
                                            return;
                                        }

                                        $set('options.conditional_when.field', null);
                                        $set('options.conditional_when.values', []);
                                    }),

                                \Filament\Forms\Components\Select::make('options.conditional_when.field')
                                    ->label(__('filament-custom-forms::fcf.admin.skip_logic_field'))
                                    ->options(fn ($livewire, ?object $record = null): array => self::conditionalFieldOptions($livewire, $record))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->visible(fn ($get): bool => (bool) $get('options.conditional_when.enabled'))
                                    ->required(fn ($get): bool => (bool) $get('options.conditional_when.enabled'))
                                    ->afterStateUpdated(function ($set): void {
                                        $set('options.conditional_when.values', []);
                                    })
                                    ->afterStateHydrated(function ($component, $state, ?object $record): void {
                                        if (filled($state)) {
                                            return;
                                        }

                                        $visibleField = data_get($record?->options, 'visible_when.field');

                                        if (filled($visibleField) && $visibleField !== 'form_selection') {
                                            $component->state($visibleField);
                                        }
                                    }),

                                \Filament\Forms\Components\Select::make('options.conditional_when.values')
                                    ->label(__('filament-custom-forms::fcf.admin.skip_logic_values'))
                                    ->options(fn ($livewire, $get): array => self::conditionalValueOptions($livewire, $get('options.conditional_when.field')))
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->visible(fn ($get): bool => (bool) $get('options.conditional_when.enabled'))
                                    ->required(fn ($get): bool => (bool) $get('options.conditional_when.enabled') && filled($get('options.conditional_when.field')))
                                    ->disabled(fn ($get): bool => blank($get('options.conditional_when.field')))
                                    ->helperText(__('filament-custom-forms::fcf.admin.skip_logic_values_help'))
                                    ->afterStateHydrated(function ($component, $state, ?object $record): void {
                                        if (filled($state)) {
                                            return;
                                        }

                                        if (data_get($record?->options, 'visible_when.field') === 'form_selection') {
                                            return;
                                        }

                                        $visibleValue = data_get($record?->options, 'visible_when.value');

                                        if (is_array($visibleValue)) {
                                            $component->state($visibleValue);

                                            return;
                                        }

                                        if (filled($visibleValue)) {
                                            $component->state([$visibleValue]);
                                        }
                                    }),
                            ]),

                        \Filament\Schemas\Components\Section::make(__('filament-custom-forms::fcf.admin.configuration'))
                            ->columnSpanFull()
                            ->visible(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get))
                            ->components([
                                \Filament\Forms\Components\Select::make('options.columns')
                                    ->label(__('filament-custom-forms::fcf.admin.columns'))
                                    ->visible(fn ($get): bool => in_array((string) $get('type'), self::CONTAINER_TYPES, true))
                                    ->options([
                                        '1' => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 1),
                                        '2' => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 2),
                                        '3' => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 3),
                                        '4' => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 4),
                                    ])
                                    ->default('2')
                                    ->native(false),

                                \Filament\Forms\Components\Repeater::make('options.choice_rows')
                                    ->label(__('filament-custom-forms::fcf.admin.select_options'))
                                    ->visible(function ($get): bool {
                                        return in_array((string) $get('type'), self::CHOICE_TYPES, true)
                                            && blank($get('options.geo_location_type'))
                                            && blank($get('options.data_source_table'));
                                    })
                                    ->columns(3)
                                    ->schema([
                                        \Filament\Forms\Components\TextInput::make('key')
                                            ->label(__('filament-custom-forms::fcf.admin.key'))
                                            ->required(),

                                        \Filament\Forms\Components\TextInput::make('label_en')
                                            ->label(__('filament-custom-forms::fcf.admin.label_english'))
                                            ->required(),

                                        \Filament\Forms\Components\TextInput::make('label_km')
                                            ->label(__('filament-custom-forms::fcf.admin.label_khmer'))
                                            ->required(),
                                    ])
                                    ->afterStateHydrated(function ($component, $state, $record): void {
                                        if (filled($state)) {
                                            return;
                                        }

                                        $options = self::normalizeOptions($record?->options ?? []);
                                        $choices = $options['choices'] ?? [];

                                        $component->state(self::choicesToRows(is_array($choices) ? $choices : []));
                                    })
                                    ->dehydrated(true)
                                    ->helperText(__('filament-custom-forms::fcf.admin.choice_rows_help')),

                                \Filament\Forms\Components\Hidden::make('options.geo_location_type')
                                    ->default(''),

                                \Filament\Forms\Components\Hidden::make('options.geo_location_value_column')
                                    ->default('id'),

                                \Filament\Forms\Components\Select::make('options.data_source_table')
                                    ->label(__('filament-custom-forms::fcf.admin.data_source_table'))
                                    ->placeholder(__('filament-custom-forms::fcf.admin.select_an_option'))
                                    ->options(self::dataSourceTableOptions())
                                    ->default('')
                                    ->native(false)
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set): void {
                                        $geoType = match ((string) $state) {
                                            'geo_province' => 'province',
                                            'geo_district' => 'district',
                                            'geo_commune' => 'commune',
                                            'geo_village' => 'village',
                                            default => '',
                                        };

                                        $set('options.geo_location_type', $geoType);

                                        if (filled($geoType)) {
                                            $set('options.geo_location_value_column', 'id');
                                            $set('options.data_source_label_column', '');
                                        } else {
                                            $set('options.geo_location_parent_field', null);
                                            $set('options.geo_location_child_fields', null);
                                            $set('options.geo_location_value_column', 'id');
                                        }
                                    })
                                    ->helperText(__('filament-custom-forms::fcf.admin.data_source_helper'))
                                    ->visible(fn ($get): bool => in_array((string) $get('type'), self::CHOICE_TYPES, true)),

                                \Filament\Forms\Components\TextInput::make('options.geo_location_parent_field')
                                    ->label(__('filament-custom-forms::fcf.admin.parent_location_field_name'))
                                    ->placeholder(__('filament-custom-forms::fcf.admin.parent_location_field_placeholder'))
                                    ->visible(function ($get): bool {
                                        $source = (string) $get('options.data_source_table');

                                        return in_array($source, ['geo_district', 'geo_commune', 'geo_village'], true)
                                            || in_array((string) $get('options.geo_location_type'), ['district', 'commune', 'village'], true);
                                    })
                                    ->helperText(__('filament-custom-forms::fcf.admin.parent_location_field_helper')),

                                \Filament\Forms\Components\TextInput::make('options.geo_location_child_fields')
                                    ->label(__('filament-custom-forms::fcf.admin.child_location_field_names'))
                                    ->placeholder(__('filament-custom-forms::fcf.admin.child_location_field_placeholder'))
                                    ->visible(function ($get): bool {
                                        $source = (string) $get('options.data_source_table');

                                        return str_starts_with($source, 'geo_')
                                            || filled($get('options.geo_location_type'));
                                    })
                                    ->helperText(__('filament-custom-forms::fcf.admin.child_location_field_helper')),

                                \Filament\Forms\Components\Select::make('options.data_source_label_column')
                                    ->label(__('filament-custom-forms::fcf.admin.data_source_label_column'))
                                    ->placeholder(__('filament-custom-forms::fcf.admin.select_an_option'))
                                    ->options([
                                        '' => __('filament-custom-forms::fcf.admin.auto_label'),
                                        'name_kh' => 'name_kh',
                                        'name_en' => 'name_en',
                                        'name_khmer' => 'name_khmer',
                                        'name_latin' => 'name_latin',
                                        'title_kh' => 'title_kh',
                                        'title_en' => 'title_en',
                                        'code' => 'code',
                                    ])
                                    ->default('')
                                    ->native(false)
                                    ->searchable()
                                    ->helperText(__('filament-custom-forms::fcf.admin.data_source_label_helper'))
                                    ->visible(function ($get): bool {
                                        $source = (string) $get('options.data_source_table');

                                        return filled($source) && ! str_starts_with($source, 'geo_');
                                    }),

                                \Filament\Forms\Components\KeyValue::make('options.column_span')
                                    ->label(__('filament-custom-forms::fcf.admin.column_span_responsive'))
                                    ->helperText(__('filament-custom-forms::fcf.admin.column_span_responsive_helper'))
                                    ->keyLabel(__('filament-custom-forms::fcf.admin.breakpoint'))
                                    ->valueLabel(__('filament-custom-forms::fcf.admin.columns_value'))
                                    ->addActionLabel(__('filament-custom-forms::fcf.admin.add_row'))
                                    ->formatStateUsing(fn ($state) => is_array($state) ? $state : (empty($state) ? [] : ['default' => $state])),

                                \Filament\Forms\Components\Toggle::make('options.column_span_full')
                                    ->label(__('filament-custom-forms::fcf.admin.full_width'))
                                    ->default(false),

                                \Filament\Forms\Components\Toggle::make('options.image_editor')
                                    ->label(__('filament-custom-forms::fcf.admin.enable_image_editor'))
                                    ->visible(fn ($get): bool => $get('type') === 'image'),

                                \Filament\Forms\Components\Toggle::make('options.is_revealable')
                                    ->label(__('filament-custom-forms::fcf.admin.allow_password_reveal'))
                                    ->visible(fn ($get): bool => $get('type') === 'password'),

                                \Filament\Forms\Components\Toggle::make('options.is_copyable')
                                    ->label(__('filament-custom-forms::fcf.admin.allow_copy'))
                                    ->visible(fn ($get): bool => in_array((string) $get('type'), ['text_input', 'email', 'number_input', 'phone'], true)),

                                \Filament\Forms\Components\Toggle::make('options.is_decimal')
                                    ->label(__('filament-custom-forms::fcf.admin.allow_decimals'))
                                    ->visible(fn ($get): bool => in_array((string) $get('type'), ['number_input', 'number', 'money'], true))
                                    ->default(true),

                                \Filament\Forms\Components\Toggle::make('options.is_hidden_label')
                                    ->label(__('filament-custom-forms::fcf.admin.hide_label'))
                                    ->default(false),

                                \Filament\Forms\Components\Toggle::make('options.is_hidden_on_view')
                                    ->label(__('filament-custom-forms::fcf.admin.hide_in_view'))
                                    ->default(false),

                                \Filament\Forms\Components\Toggle::make('options.is_table')
                                    ->label(__('filament-custom-forms::fcf.admin.use_table_layout'))
                                    ->visible(fn ($get): bool => $get('type') === 'repeater')
                                    ->default(false),

                                \Filament\Forms\Components\Toggle::make('options.is_compact')
                                    ->label(__('filament-custom-forms::fcf.admin.compact_mode'))
                                    ->visible(fn ($get): bool => $get('type') === 'repeater')
                                    ->default(false),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-custom-forms::fcf.field.name'))
                    ->searchable(),

                TextColumn::make('label_en')
                    ->label(__('filament-custom-forms::fcf.admin.label_english'))
                    ->state(fn ($record): string => self::getLangValue($record->label, 'en'))
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder {
                        return $query->where('label', 'like', "%{$search}%");
                    }),

                TextColumn::make('label_km')
                    ->label(__('filament-custom-forms::fcf.admin.label_khmer'))
                    ->state(fn ($record): string => self::getLangValue($record->label, 'km'))
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder {
                        return $query->where('label', 'like', "%{$search}%");
                    }),


                ToggleColumn::make('required')
                    ->label(__('filament-custom-forms::fcf.field.is_required'))
                    ->onColor('success')
                    ->offColor('gray')
                    ->disabled(fn ($record) => in_array(
                        $record->type,
                        self::CONTAINER_TYPES,
                        true
                    )),

                ToggleColumn::make('field_input')
                    ->label(__('filament-custom-forms::fcf.admin.field_input'))
                    ->onColor('success')
                    ->offColor('gray')
                    ->visible(fn (): bool => self::hasCustomFormFieldColumn('field_input'))
                    ->disabled(fn ($record) => in_array(
                        $record->type,
                        self::CONTAINER_TYPES,
                        true
                    ) || (string) $record->type === 'info'),

                TextColumn::make('type')
                    ->label(__('filament-custom-forms::fcf.field.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::fieldTypeLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        'step', 'section', 'grid', 'fieldset', 'repeater', 'wizard' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('options.visible_when.value')
                    ->label(__('filament-custom-forms::fcf.field.form_type'))
                    ->state(function ($record): mixed {
                        $state = data_get($record->options, 'visible_when.value');

                        if (blank($state) || $state === false || $state === 'false') {
                            $ownerForm = $record->form;

                            if ($ownerForm && $ownerForm->menu_placement === 'sub_item' && filled($ownerForm->sub_item_type)) {
                                return $ownerForm->sub_item_type;
                            }

                            if ($ownerForm && $ownerForm->menu_placement === 'sidebar') {
                                return self::PARENT_FORM_TYPE;
                            }
                        }

                        return $state;
                    })
                    ->badge()
                    ->formatStateUsing(function ($state, $record) {
                        if (blank($state) || $state === false || $state === 'false') {
                            return app()->getLocale() === 'km' ? '—' : '—';
                        }

                        $stateString = (string) $state;

                        if ($stateString === self::PARENT_FORM_TYPE) {
                            return __('filament-custom-forms::fcf.admin.parent_form');
                        }

                        $ownerForm = $record->form;

                        if ($ownerForm) {
                            $rootFormId = $ownerForm->id;
                            if ($ownerForm->menu_placement === 'sub_item' && filled($ownerForm->custom_form_id)) {
                                $rootFormId = $ownerForm->custom_form_id;
                            }

                            $selectionField = \Chanthoeun\FilamentCustomForms\Models\CustomFormField::query()
                                ->where('custom_form_id', $rootFormId)
                                ->where('name', 'form_selection')
                                ->first();

                            if ($selectionField && !blank($selectionField->options)) {
                                $config = is_string($selectionField->options)
                                    ? json_decode($selectionField->options, true)
                                    : $selectionField->options;

                                $choices = $config['choices'] ?? [];
                                if (is_array($choices)) {
                                    foreach ($choices as $value => $label) {
                                        if (is_array($label) && isset($label['value']) && (string)$label['value'] === $stateString) {
                                            return self::localeText($label['label'] ?? $label['value']);
                                        }
                                        if ((string)$value === $stateString) {
                                            return self::localeText($label);
                                        }
                                    }
                                }
                            }
                        }

                        return self::formTypeDisplayLabel($stateString);
                    })
                    ->color(fn ($state): string => match ($state) {
                        'associate' => 'gray',
                        'bachelor' => 'info',
                        'master' => 'warning',
                        'phd' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('parent.label')
                    ->label(__('filament-custom-forms::fcf.admin.parent_container'))
                    ->badge()
                    ->default('—')
                    ->formatStateUsing(fn ($state): string => self::localeText($state)),
            ])
            ->reorderable('sort')
            ->defaultSort('sort', 'asc')
            ->headerActions([
                CreateAction::make()
                    ->modalHeading(__('filament-custom-forms::fcf.admin.create_custom_form_field'))
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('4xl')
                    ->using(function (array $data) {
                        if (blank($data['name'] ?? null) && filled(data_get($data, 'options.visible_when.fields'))) {
                            return CustomFormFieldSelection::copy(
                                $this->getOwnerRecord(),
                                data_get($data, 'options.visible_when.fields'),
                                filled($data['selection_source_forms'] ?? null) ? (int) $data['selection_source_forms'] : null,
                            );
                        }

                        $createdRecord = null;

                        foreach ($this->prepareFieldDataList($data) as $fieldData) {
                            $record = $this->createCustomFormField($fieldData);

                            if (! $createdRecord) {
                                $createdRecord = $record;
                            }
                        }

                        return $createdRecord;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading(__('filament-custom-forms::fcf.admin.edit_custom_form_field'))
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('4xl')
                    ->mutateRecordDataUsing(fn (array $data): array => self::hydrateConditionalWhenFormData($data))
                    ->using(function ($record, array $data) {
                        $record->update($this->prepareFieldData($data));

                        return $record;
                    }),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function createCustomFormField(array $fieldData): \Chanthoeun\FilamentCustomForms\Models\CustomFormField
    {
        try {
            return \Chanthoeun\FilamentCustomForms\Models\CustomFormField::create($fieldData);
        } catch (QueryException $exception) {
            if (! $this->isCustomFormFieldPrimaryKeySequenceError($exception)) {
                throw $exception;
            }

            $this->syncCustomFormFieldsIdSequence();

            return \Chanthoeun\FilamentCustomForms\Models\CustomFormField::create($fieldData);
        }
    }

    private function isCustomFormFieldPrimaryKeySequenceError(QueryException $exception): bool
    {
        return DB::getDriverName() === 'pgsql'
            && ($exception->errorInfo[0] ?? null) === '23505'
            && str_contains($exception->getMessage(), 'custom_form_fields_pkey');
    }

    private function syncCustomFormFieldsIdSequence(): void
    {
        DB::statement(<<<'SQL'
            SELECT setval(
                pg_get_serial_sequence('custom_form_fields', 'id'),
                (SELECT COALESCE(MAX(id), 1) FROM custom_form_fields),
                true
            )
        SQL);
    }

    private function prepareFieldData(array $data): array
    {
        $ownerForm = $this->getOwnerRecord();

        if (! array_key_exists('field_input', $data)) {
            $data['field_input'] = true;
        }

        if (! self::hasCustomFormFieldColumn('field_input')) {
            unset($data['field_input']);
        }

        $labelEn = trim((string) ($data['label_en'] ?? ''));
        $labelKm = trim((string) ($data['label_km'] ?? ''));

        $data['label'] = json_encode([
            'en' => $labelEn !== '' ? $labelEn : $labelKm,
            'km' => $labelKm !== '' ? $labelKm : $labelEn,
        ], JSON_UNESCAPED_UNICODE);

        unset($data['label_en'], $data['label_km']);

        $choiceRows = data_get($data, 'options.choice_rows');

        if (is_array($choiceRows)) {
            $choices = [];

            foreach ($choiceRows as $row) {
                $value = trim((string) ($row['key'] ?? ''));

                if ($value === '') {
                    continue;
                }

                $en = trim((string) ($row['label_en'] ?? ''));
                $km = trim((string) ($row['label_km'] ?? ''));

                $choices[$value] = [
                    'en' => $en !== '' ? $en : $km,
                    'km' => $km !== '' ? $km : $en,
                ];
            }

            data_set($data, 'options.choices', $choices);
            data_forget($data, 'options.choice_rows');
        }

        $conditionalEnabled = (bool) data_get($data, 'options.conditional_when.enabled', false);
        $conditionalField = data_get($data, 'options.conditional_when.field');
        $conditionalValues = data_get($data, 'options.conditional_when.values', []);

        if (! is_array($conditionalValues)) {
            $conditionalValues = filled($conditionalValues) ? [$conditionalValues] : [];
        }

        $conditionalValues = array_values(array_filter($conditionalValues, fn ($value): bool => filled($value)));

        if ($conditionalEnabled && filled($conditionalField) && ! empty($conditionalValues)) {
            data_set($data, 'options.visible_when.field', $conditionalField);
            data_set($data, 'options.visible_when.operator', 'in');
            data_set($data, 'options.visible_when.value', $conditionalValues);
            data_forget($data, 'options.visible_when.values');
            data_forget($data, 'options.conditional_when');

            $data['custom_form_id'] = $ownerForm->id;

            return $data;
        }

        data_forget($data, 'options.conditional_when');

        if (
            filled(data_get($data, 'options.visible_when.field'))
            && data_get($data, 'options.visible_when.field') !== 'form_selection'
        ) {
            data_forget($data, 'options.visible_when');
        }

        $selectedValues = data_get($data, 'options.visible_when.values');
        if (is_array($selectedValues)) {
            $selectedType = head(array_filter(
                $selectedValues,
                fn ($val) => filled($val) && $val !== false && $val !== 'false'
            ));
            data_forget($data, 'options.visible_when.values');
        } else {
            $selectedType = data_get($data, 'options.visible_when.value');
        }

        $rootFormId = $ownerForm->id;

        if ($ownerForm->menu_placement === 'sub_item' && filled($ownerForm->custom_form_id)) {
            $rootFormId = $ownerForm->custom_form_id;
        }

        $targetForm = self::formTargetFromValue($selectedType);

        if ($targetForm) {
            $data['custom_form_id'] = $targetForm->id;

            if ((string) $targetForm->id !== (string) $ownerForm->id) {
                $data['parent_id'] = null;
            }

            if ($targetForm->menu_placement === 'sub_item' && filled($targetForm->sub_item_type)) {
                data_set($data, 'options.visible_when.field', 'form_selection');
                data_set($data, 'options.visible_when.operator', '=');
                data_set($data, 'options.visible_when.value', $targetForm->sub_item_type);
            } else {
                data_forget($data, 'options.visible_when');
            }

            return $data;
        }

        if ($selectedType === self::PARENT_FORM_TYPE) {
            data_forget($data, 'options.visible_when');
            $data['custom_form_id'] = $rootFormId;

            if ((string) $rootFormId !== (string) $ownerForm->id) {
                $data['parent_id'] = null;
            }

            return $data;
        }

        if (blank($selectedType) || $selectedType === false || $selectedType === 'false') {
            if (filled($ownerForm->sub_item_type)) {
                $selectedType = $ownerForm->sub_item_type;

                data_set($data, 'options.visible_when.field', 'form_selection');
                data_set($data, 'options.visible_when.operator', '=');
                data_set($data, 'options.visible_when.value', $selectedType);
            } else {
                data_forget($data, 'options.visible_when');
            }
        } else {
            data_set($data, 'options.visible_when.field', 'form_selection');
            data_set($data, 'options.visible_when.operator', '=');
            data_set($data, 'options.visible_when.value', $selectedType);
        }

        $targetFormId = $ownerForm->id;

        if (filled($selectedType)) {
            $targetForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                ->where('custom_form_id', $rootFormId)
                ->whereRaw('LOWER(sub_item_type) = ?', [
                    strtolower(trim((string) $selectedType)),
                ])
                ->first();

            if ($targetForm) {
                $targetFormId = $targetForm->id;
                $data['parent_id'] = null;
            }
        }

        $data['custom_form_id'] = $targetFormId;

        return $data;
    }

    private static function hydrateConditionalWhenFormData(array $data): array
    {
        $visibleField = data_get($data, 'options.visible_when.field');

        if (blank($visibleField) || $visibleField === 'form_selection') {
            return $data;
        }

        $visibleValue = data_get($data, 'options.visible_when.value');

        if (! is_array($visibleValue)) {
            $visibleValue = filled($visibleValue) ? [$visibleValue] : [];
        }

        data_set($data, 'options.conditional_when.enabled', true);
        data_set($data, 'options.conditional_when.field', $visibleField);
        data_set($data, 'options.conditional_when.values', array_values(array_filter(
            $visibleValue,
            fn ($value): bool => filled($value)
        )));

        return $data;
    }

    private static function hasCustomFormFieldColumn(string $column): bool
    {
        return DatabaseSchema::hasTable('custom_form_fields')
            && DatabaseSchema::hasColumn('custom_form_fields', $column);
    }

    private static function textFormatOptions(): array
    {
        return [
            'normal' => __('filament-custom-forms::fcf.admin.text_format_options.normal'),
            'h1' => 'H1',
            'h2' => 'H2',
            'h3' => 'H3',
            'h4' => 'H4',
            'h5' => 'H5',
            'h6' => 'H6',
            'h7' => 'H7',
        ];
    }

    private static function fieldTypeLabel(string $state): string
    {
        return match ($state) {
            'step' => 'Step',
            'section' => 'Section',
            'grid' => 'Grid',
            'fieldset' => 'Fieldset',
            'repeater' => 'Repeater',
            'wizard' => 'Wizard',
            'text_input' => 'Text Input',
            'textarea' => 'Text Area',
            'email' => 'Email',
            'number_input' => 'Number Input',
            'money' => 'Currency Input Box',
            'date_picker' => 'Date Picker',
            'time_picker' => 'Time Picker',
            'boolean' => 'Boolean (Toggle)',
            'select_dropdown' => 'Select Dropdown',
            'radio' => 'Radio',
            'checkbox' => 'Checkbox',
            'checkbox_list' => 'Checkbox List',
            'multi_select' => 'Multi Select',
            'info' => 'Info',
            'image' => 'Image',
            'file_upload' => 'File Upload',
            'password' => 'Password',
            'phone' => 'Phone Number',
            default => $state,
        };
    }

    private static function formTypeDisplayLabel(string $state): string
    {
        return match ($state) {
            'associate' => __('filament-custom-forms::fcf.admin.form_type_options.associate'),
            'bachelor' => __('filament-custom-forms::fcf.admin.form_type_options.bachelor'),
            'master' => __('filament-custom-forms::fcf.admin.form_type_options.master'),
            'phd' => __('filament-custom-forms::fcf.admin.form_type_options.phd'),
            'exam' => __('filament-custom-forms::fcf.admin.form_type_options.exam'),
            'national_candidate' => __('filament-custom-forms::fcf.admin.form_type_options.national_candidate'),
            'general_candidate' => __('filament-custom-forms::fcf.admin.form_type_options.general_candidate'),
            'continuing_candidate' => __('filament-custom-forms::fcf.admin.form_type_options.continuing_candidate'),
            'master_candidate' => __('filament-custom-forms::fcf.admin.form_type_options.master_candidate'),
            default => ucfirst(str_replace(['_', '-'], ' ', $state)),
        };
    }

    private static function dataSourceTableOptions(): array
    {
        return [
            '' => __('filament-custom-forms::fcf.admin.not_data_source_field'),
            'geo_province' => __('filament-custom-forms::fcf.admin.data_source_options.geo_province'),
            'geo_district' => __('filament-custom-forms::fcf.admin.data_source_options.geo_district'),
            'geo_commune' => __('filament-custom-forms::fcf.admin.data_source_options.geo_commune'),
            'geo_village' => __('filament-custom-forms::fcf.admin.data_source_options.geo_village'),
            'academic_levels' => __('filament-custom-forms::fcf.admin.data_source_options.academic_levels'),
            'academic_years' => __('filament-custom-forms::fcf.admin.data_source_options.academic_years'),
            'countries' => __('filament-custom-forms::fcf.admin.data_source_options.countries'),
            'faculties' => __('filament-custom-forms::fcf.admin.data_source_options.faculties'),
            'fiscal_years' => __('filament-custom-forms::fcf.admin.data_source_options.fiscal_years'),
            'programs' => __('filament-custom-forms::fcf.admin.data_source_options.programs'),
        ];
    }

    private static function normalizeOptions(mixed $options): array
    {
        if (is_array($options)) {
            return $options;
        }

        if (is_string($options) && $options !== '') {
            $decoded = json_decode($options, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($options)) {
            return json_decode(json_encode($options), true) ?: [];
        }

        return [];
    }

    private static function isCreatingMode(callable $get): bool
    {
        return ($get('creation_mode') ?? 'creating') !== 'selection';
    }

    private static function isSelectionMode(callable $get): bool
    {
        return ($get('creation_mode') ?? 'creating') === 'selection';
    }

    private static function isFieldInputEnabled(mixed $options): bool
    {
        $options = self::normalizeOptions($options);

        if (! array_key_exists('is_field_input_enabled', $options)) {
            return true;
        }

        return ! in_array($options['is_field_input_enabled'], [false, 'false', 0, '0'], true);
    }

    private static function getLangValue(mixed $value, string $locale): string
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }

        if (is_array($value)) {
            return (string) (
                $value[$locale]
                ?? $value['km']
                ?? $value['kh']
                ?? $value['en']
                ?? collect($value)->first()
                ?? ''
            );
        }

        return (string) $value;
    }

    private static function englishText(mixed $value): string
    {
        return self::getLangValue($value, 'en');
    }

    private static function englishOptions(array $choices): array
    {
        return collect($choices)
            ->mapWithKeys(function ($label, $key): array {
                if (is_array($label) && array_key_exists('value', $label)) {
                    return [
                        (string) $label['value'] => self::englishText($label['label'] ?? $label['value']),
                    ];
                }

                return [
                    (string) $key => self::englishText($label),
                ];
            })
            ->toArray();
    }

    private static function conditionalFieldOptions($livewire, ?object $record = null): array
    {
        $ownerForm = $livewire->getOwnerRecord();

        if (! $ownerForm) {
            return [];
        }

        return $ownerForm->fields()
            ->where('type', 'select_dropdown')
            ->when($record?->id, fn ($query) => $query->whereKeyNot($record->id))
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('sort')
            ->get()
            ->mapWithKeys(fn ($field): array => [
                $field->name => $field->name . ' - ' . self::localeText($field->label ?? $field->name),
            ])
            ->toArray();
    }

    private static function conditionalValueOptions($livewire, mixed $fieldName): array
    {
        $fieldName = trim((string) $fieldName);

        if ($fieldName === '') {
            return [];
        }

        $ownerForm = $livewire->getOwnerRecord();

        if (! $ownerForm) {
            return [];
        }

        $field = $ownerForm->fields()
            ->where('name', $fieldName)
            ->first();

        if (! $field) {
            return [];
        }

        return self::conditionalValueOptionsForField($field);
    }

    private static function conditionalValueOptionsForField(object $field): array
    {
        $type = (string) ($field->type ?? '');
        $fieldName = (string) ($field->name ?? '');

        if (in_array($type, ['boolean', 'checkbox'], true)) {
            return [
                '1' => __('filament-custom-forms::fcf.admin.skip_logic_yes'),
                '0' => __('filament-custom-forms::fcf.admin.skip_logic_no'),
            ];
        }

        $options = self::normalizeOptions($field->options ?? []);
        $choices = data_get($options, 'choices', []);

        if (empty($choices)) {
            $choiceRows = data_get($options, 'choice_rows', []);

            if (is_array($choiceRows)) {
                foreach ($choiceRows as $row) {
                    $key = trim((string) ($row['key'] ?? ''));

                    if ($key === '') {
                        continue;
                    }

                    $choices[$key] = [
                        'en' => $row['label_en'] ?? $key,
                        'km' => $row['label_km'] ?? $key,
                    ];
                }
            }
        }

        if (! is_array($choices) || empty($choices)) {
            return self::conditionalDataSourceValueOptions($fieldName, $options);
        }

        return collect($choices)
            ->mapWithKeys(function (mixed $label, int|string $key): array {
                if (is_array($label) && array_key_exists('value', $label)) {
                    $value = (string) $label['value'];
                    $text = self::localeText($label['label'] ?? $value);

                    return [$value => $text !== '' ? $text : $value];
                }

                $value = (string) $key;
                $text = self::localeText($label);

                return [$value => $text !== '' ? $text : $value];
            })
            ->toArray();
    }

    private static function conditionalDataSourceValueOptions(string $fieldName, array $options): array
    {
        $source = (string) data_get($options, 'data_source_table', '');

        if ($source === '') {
            return [];
        }

        $geoTypes = [
            'geo_province' => 'province',
            'geo_district' => 'district',
            'geo_commune' => 'commune',
            'geo_village' => 'village',
        ];

        if (isset($geoTypes[$source])) {
            return self::geoLocationValueOptions($geoTypes[$source]);
        }

        $source = self::canonicalDataSourceTable($source);
        $definitions = self::dataSourceDefinitions();

        if (! isset($definitions[$source]) || ! DatabaseSchema::hasTable($source)) {
            return [];
        }

        $definition = $definitions[$source];
        $valueColumn = (string) (data_get($options, 'data_source_value_column') ?: self::defaultDataSourceValueColumn($source, $fieldName, $definition));

        if (! DatabaseSchema::hasColumn($source, $valueColumn)) {
            $valueColumn = (string) ($definition['value_column'] ?? 'id');
        }

        if (! DatabaseSchema::hasColumn($source, $valueColumn)) {
            return [];
        }

        $labelColumn = self::dataSourceLabelColumn($source, $definition, $options);

        if (! $labelColumn) {
            return [];
        }

        $query = DB::table($source)
            ->select([$valueColumn, $labelColumn])
            ->whereNotNull($valueColumn)
            ->orderBy($labelColumn)
            ->limit(500);

        $activeColumn = $definition['active_column'] ?? null;

        if ($activeColumn && DatabaseSchema::hasColumn($source, $activeColumn)) {
            $query->where($activeColumn, true);
        }

        return $query
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (string) $row->{$valueColumn} => (string) ($row->{$labelColumn} ?: $row->{$valueColumn}),
            ])
            ->toArray();
    }

    private static function geoLocationValueOptions(string $type): array
    {
        if (! DatabaseSchema::hasTable('geo_locations')) {
            return [];
        }

        $labelColumn = app()->getLocale() === 'km' && DatabaseSchema::hasColumn('geo_locations', 'name_kh')
            ? 'name_kh'
            : 'name_en';

        if (! DatabaseSchema::hasColumn('geo_locations', $labelColumn)) {
            $labelColumn = DatabaseSchema::hasColumn('geo_locations', 'name_kh') ? 'name_kh' : 'code';
        }

        return DB::table('geo_locations')
            ->select(['id', $labelColumn])
            ->where('type', $type)
            ->when(DatabaseSchema::hasColumn('geo_locations', 'is_active'), fn ($query) => $query->where('is_active', true))
            ->orderBy($labelColumn)
            ->limit(500)
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (string) $row->id => (string) ($row->{$labelColumn} ?: $row->id),
            ])
            ->toArray();
    }

    private static function dataSourceDefinitions(): array
    {
        return [
            'academic_levels' => [
                'value_column' => 'id',
                'label_kh' => 'name_kh',
                'label_en' => 'name_en',
                'fallback_label_columns' => ['name_kh', 'name_en', 'code', 'id'],
                'active_column' => 'is_active',
            ],
            'academic_years' => [
                'value_column' => 'id',
                'label_kh' => 'name_kh',
                'label_en' => 'name_en',
                'fallback_label_columns' => ['name_kh', 'name_en', 'code', 'id'],
                'active_column' => 'is_active',
            ],
            'countries' => [
                'value_column' => 'code',
                'label_kh' => 'name_khmer',
                'label_en' => 'name_latin',
                'fallback_label_columns' => ['name_khmer', 'name_latin', 'code', 'id'],
                'active_column' => 'is_active',
            ],
            'faculties' => [
                'value_column' => 'id',
                'label_kh' => 'title_kh',
                'label_en' => 'title_en',
                'fallback_label_columns' => ['title_kh', 'title_en', 'department_kh', 'department_en', 'code', 'id'],
                'active_column' => null,
            ],
            'fiscal_years' => [
                'value_column' => 'id',
                'label_kh' => 'name_kh',
                'label_en' => 'name_en',
                'fallback_label_columns' => ['name_kh', 'name_en', 'code', 'id'],
                'active_column' => 'is_active',
            ],
            'programs' => [
                'value_column' => 'code',
                'label_kh' => 'title_kh',
                'label_en' => 'title_en',
                'fallback_label_columns' => ['title_kh', 'title_en', 'code', 'id'],
                'active_column' => null,
            ],
        ];
    }

    private static function canonicalDataSourceTable(string $table): string
    {
        $table = trim(strtolower(str_replace(['-', ' '], '_', $table)), '_');

        return [
            'academic_level' => 'academic_levels',
            'academic_year' => 'academic_years',
            'country' => 'countries',
            'faculty' => 'faculties',
            'fiscal_year' => 'fiscal_years',
            'program' => 'programs',
        ][$table] ?? $table;
    }

    private static function defaultDataSourceValueColumn(string $table, string $fieldName, array $definition): string
    {
        $fieldName = trim(strtolower(str_replace(['-', ' '], '_', $fieldName)), '_');

        if ($table === 'programs') {
            return $fieldName === 'programs_id' ? 'id' : 'code';
        }

        if (str_ends_with($fieldName, '_code') || $fieldName === 'code') {
            return 'code';
        }

        return (string) ($definition['value_column'] ?? 'id');
    }

    private static function dataSourceLabelColumn(string $table, array $definition, array $options): ?string
    {
        $explicit = (string) data_get($options, 'data_source_label_column', '');

        if ($explicit !== '' && DatabaseSchema::hasColumn($table, $explicit)) {
            return $explicit;
        }

        $localeColumn = app()->getLocale() === 'km'
            ? ($definition['label_kh'] ?? null)
            : ($definition['label_en'] ?? null);

        if ($localeColumn && DatabaseSchema::hasColumn($table, $localeColumn)) {
            return $localeColumn;
        }

        foreach (($definition['fallback_label_columns'] ?? []) as $column) {
            if (DatabaseSchema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private static function choicesToRows(array $choices): array
    {
        $rows = [];

        foreach ($choices as $value => $label) {
            if (is_array($label) && array_key_exists('value', $label)) {
                $rows[] = [
                    'key' => (string) $label['value'],
                    'label_en' => self::getLangValue($label['label'] ?? '', 'en'),
                    'label_km' => self::getLangValue($label['label'] ?? '', 'km'),
                ];

                continue;
            }

            $rows[] = [
                'key' => (string) $value,
                'label_en' => self::getLangValue($label, 'en'),
                'label_km' => self::getLangValue($label, 'km'),
            ];
        }

        return $rows;
    }

    private static function formTargetValue(int|string $formId): string
    {
        return self::FORM_TARGET_PREFIX . $formId;
    }

    private static function formTargetFromValue(mixed $value): ?\Chanthoeun\FilamentCustomForms\Models\CustomForm
    {
        if (! is_string($value) || ! str_starts_with($value, self::FORM_TARGET_PREFIX)) {
            return null;
        }

        $formId = (int) substr($value, strlen(self::FORM_TARGET_PREFIX));

        if ($formId <= 0) {
            return null;
        }

        return \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->where('is_active', true)
            ->find($formId);
    }

    private static function dynamicFormTargetOptions(): array
    {
        $forms = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->where('is_active', true)
            ->whereIn('menu_placement', ['sidebar', 'sub_item'])
            ->orderBy('custom_form_id')
            ->orderBy('id')
            ->get(['id', 'name', 'menu_placement', 'custom_form_id', 'sub_item_type']);

        return $forms
            ->mapWithKeys(fn ($form): array => [
                self::formTargetValue($form->id) => self::localeText($form->name),
            ])
            ->toArray();
    }

    private function prepareFieldDataList(array $data): array
    {
        $selectedTypes = data_get($data, 'options.visible_when.values', []);

        if (! is_array($selectedTypes)) {
            $selectedTypes = filled($selectedTypes) ? [$selectedTypes] : [];
        }

        $selectedTypes = array_values(array_filter(
            $selectedTypes,
            fn ($value): bool => filled($value) && $value !== false && $value !== 'false'
        ));

        if (empty($selectedTypes)) {
            return [
                $this->prepareFieldData($data),
            ];
        }

        $items = [];

        foreach ($selectedTypes as $selectedType) {
            $copy = $data;

            data_set($copy, 'options.visible_when.field', 'form_selection');
            data_set($copy, 'options.visible_when.operator', '=');
            data_set($copy, 'options.visible_when.value', $selectedType);
            data_forget($copy, 'options.visible_when.values');

            $items[] = $this->prepareFieldData($copy);
        }

        return $items;
    }

    private static function localeText(mixed $value): string
    {
        return self::getLangValue($value, app()->getLocale());
    }
}
