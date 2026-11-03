<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Schemas;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Forms;
use Filament\Forms\Components\Builder as FormBuilder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class CustomFormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-custom-forms::fcf.form.details'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name_en')
                            ->label(__('filament-custom-forms::fcf.form.label_english'))
                            ->placeholder(__('filament-custom-forms::fcf.placeholder.form_name_en'))
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateHydrated(function ($component, $record): void {
                                $component->state(self::getNameLang($record?->getRawOriginal('name') ?? $record?->name, 'en'));
                            })
                            ->dehydrated(false)
                            ->maxLength(255),

                        TextInput::make('name_km')
                            ->label(__('filament-custom-forms::fcf.form.label_khmer'))
                            ->placeholder(__('filament-custom-forms::fcf.placeholder.form_name_km'))
                            ->required()
                            ->afterStateHydrated(function ($component, $record): void {
                                $component->state(self::getNameLang($record?->getRawOriginal('name') ?? $record?->name, 'km'));
                            })
                            ->dehydrated(false)
                            ->maxLength(255),

                        Forms\Components\Hidden::make('name')
                            ->dehydrateStateUsing(function ($state, $get): string {
                                return json_encode([
                                    'en' => $get('name_en'),
                                    'km' => $get('name_km'),
                                    'kh' => $get('name_km'),
                                ], JSON_UNESCAPED_UNICODE);
                            }),

                        TextInput::make('slug')
                            ->label(__('filament-custom-forms::fcf.form.slug'))
                            ->placeholder(__('filament-custom-forms::fcf.placeholder.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('menu_placement')
                            ->label(__('filament-custom-forms::fcf.form.menu_placement'))
                            ->options([
                                'sidebar' => __('filament-custom-forms::fcf.menu.sidebar'),
                                'sub_item' => __('filament-custom-forms::fcf.menu.sub_item'),
                            ])
                            ->default('sidebar')
                            ->live()
                            ->afterStateUpdated(function ($set): void {
                                $set('parent_sidebar', null);
                                $set('custom_form_id', null);
                            })
                            ->required(),
                        Forms\Components\Hidden::make('custom_form_id'),

                        Select::make('workflow_template_id')
                            ->label(__('Workflow Template'))
                            ->relationship('workflowTemplate', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText(__('Select the active workflow that starts when this form is submitted.')),

                        Select::make('parent_sidebar')
                            ->label(__('filament-custom-forms::fcf.menu.parent_sidebar'))
                            ->options(fn (): array => self::parentSidebarOptions())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateHydrated(function ($component, $state, ?CustomForm $record): void {
                                if ($record?->menu_placement === 'sub_item' && filled($record->custom_form_id)) {
                                    $component->state((string) $record->custom_form_id);
                                }
                            })
                            ->afterStateUpdated(function ($set, $state): void {
                                $set('custom_form_id', self::findParentForm($state)?->id);
                            })
                            ->visible(fn (Get $get): bool => $get('menu_placement') === 'sub_item')
                            ->required(fn (Get $get): bool => $get('menu_placement') === 'sub_item'),

                        Toggle::make('is_active')
                            ->label(__('filament-custom-forms::fcf.form.is_active'))
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }

    public static function getFormBlocks(bool $includeLayouts = true): array
    {
        $blocks = [
            FormBuilder\Block::make('text_input')
                ->label(__('filament-custom-forms::fcf.builder.blocks.text_input'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required()->helperText(__('filament-custom-forms::fcf.builder.fields.name_help')),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),
            FormBuilder\Block::make('textarea')
                ->label(__('filament-custom-forms::fcf.builder.blocks.textarea'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),
            FormBuilder\Block::make('number_input')
                ->label(__('filament-custom-forms::fcf.builder.blocks.number_input'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),
            FormBuilder\Block::make('money')
                ->label(__('filament-custom-forms::fcf.builder.blocks.money'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),
            FormBuilder\Block::make('email')
                ->label(__('filament-custom-forms::fcf.builder.blocks.email'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),
            FormBuilder\Block::make('phone')
                ->label(__('filament-custom-forms::fcf.builder.blocks.phone'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),
            FormBuilder\Block::make('password')
                ->label(__('filament-custom-forms::fcf.builder.blocks.password'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),
            FormBuilder\Block::make('date_picker')
                ->label(__('filament-custom-forms::fcf.builder.blocks.date_picker'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),
            FormBuilder\Block::make('time_picker')
                ->label(__('filament-custom-forms::fcf.builder.blocks.time_picker'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),
            FormBuilder\Block::make('boolean')
                ->label(__('filament-custom-forms::fcf.builder.blocks.boolean'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('default')->label(__('filament-custom-forms::fcf.builder.fields.default')),
                ]),
            FormBuilder\Block::make('image')
                ->label(__('filament-custom-forms::fcf.builder.blocks.image'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),
            FormBuilder\Block::make('file_upload')
                ->label(__('filament-custom-forms::fcf.builder.blocks.file_upload'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),            FormBuilder\Block::make('select')
                ->label(__('filament-custom-forms::fcf.builder.blocks.select'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Forms\Components\Repeater::make('options')
                        ->label(__('filament-custom-forms::fcf.builder.fields.choices'))
                        ->schema([
                            TextInput::make('value')->label(__('filament-custom-forms::fcf.builder.fields.value'))->required(),
                            TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                        ]),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),
        ];

        if ($includeLayouts) {
            $blocks[] = FormBuilder\Block::make('section')
                ->label(__('filament-custom-forms::fcf.builder.blocks.section'))
                ->schema([
                    TextInput::make('heading')->label(__('filament-custom-forms::fcf.builder.fields.heading')),
                    Select::make('columns')
                        ->label(__('filament-custom-forms::fcf.builder.fields.columns'))
                        ->options([
                            1 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 1),
                            2 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 2),
                            3 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 3),
                            4 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 4),
                        ])
                        ->default(2),
                    FormBuilder::make('schema')
                        ->label(__('filament-custom-forms::fcf.builder.fields.section_content'))
                        ->blocks(self::getFormBlocks(includeLayouts: false)), // Prevent infinite nesting for simplicity
                ]);

            $blocks[] = FormBuilder\Block::make('grid')
                ->label(__('filament-custom-forms::fcf.builder.blocks.grid'))
                ->schema([
                    Select::make('columns')
                        ->label(__('filament-custom-forms::fcf.builder.fields.columns'))
                        ->options([
                            2 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 2),
                            3 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 3),
                            4 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 4),
                        ])
                        ->default(2),
                    FormBuilder::make('schema')
                        ->label(__('filament-custom-forms::fcf.builder.fields.grid_content'))
                        ->blocks(self::getFormBlocks(includeLayouts: false)),
                ]);

            $blocks[] = FormBuilder\Block::make('fieldset')
                ->label(__('filament-custom-forms::fcf.builder.blocks.fieldset'))
                ->schema([
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.legend'))->required(),
                    Select::make('columns')
                        ->label(__('filament-custom-forms::fcf.builder.fields.columns'))
                        ->options([
                            1 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 1),
                            2 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 2),
                            3 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 3),
                        ])
                        ->default(2),
                    FormBuilder::make('schema')
                        ->label(__('filament-custom-forms::fcf.builder.fields.fieldset_content'))
                        ->blocks(self::getFormBlocks(includeLayouts: false)),
                ]);

            $blocks[] = FormBuilder\Block::make('repeater')
                ->label(__('filament-custom-forms::fcf.builder.blocks.repeater'))
                ->schema([
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required()->helperText(__('filament-custom-forms::fcf.builder.fields.name_help')),
                    Select::make('columns')
                        ->label(__('filament-custom-forms::fcf.builder.fields.columns'))
                        ->options([
                            1 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 1),
                            2 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 2),
                        ])
                        ->default(1),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                    FormBuilder::make('schema')
                        ->label(__('filament-custom-forms::fcf.builder.fields.repeater_fields'))
                        ->blocks(self::getFormBlocks(includeLayouts: false)),
                ]);
        }

        return $blocks;
    }

    protected static function parentSidebarOptions(): array
    {
        if (! DatabaseSchema::hasTable('custom_forms') || ! DatabaseSchema::hasColumn('custom_forms', 'menu_placement')) {
            return [];
        }

        return DB::table('custom_forms')
            ->where(function ($query): void {
                $query->where('menu_placement', 'sidebar')
                    ->orWhereNull('menu_placement');
            })
            ->whereNotNull('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->mapWithKeys(function (object $form): array {
                $fallback = 'form-'.$form->id;
                $name = self::localeText($form->name, $fallback);

                return [$form->id => $name ?: $fallback];
            })
            ->all();
    }

    protected static function findParentForm(mixed $parentSidebar): ?CustomForm
    {
        if (blank($parentSidebar) || ! DatabaseSchema::hasTable('custom_forms')) {
            return null;
        }

        $parentSidebar = (string) $parentSidebar;

        if (ctype_digit($parentSidebar)) {
            return CustomForm::query()
                ->where(function ($query): void {
                    $query->where('menu_placement', 'sidebar')
                        ->orWhereNull('menu_placement');
                })
                ->find((int) $parentSidebar);
        }

        return CustomForm::query()
            ->where(function ($query): void {
                $query->where('menu_placement', 'sidebar')
                    ->orWhereNull('menu_placement');
            })
            ->get()
            ->first(function (CustomForm $form) use ($parentSidebar): bool {
                $rawName = $form->getRawOriginal('name');

                return in_array($parentSidebar, array_filter([
                    (string) $rawName,
                    (string) $form->name,
                    self::englishText($rawName),
                    self::localeText($rawName),
                ]), true);
            });
    }

    protected static function getNameLang(mixed $value, string $locale): string
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return (string) (
                    $decoded[$locale]
                    ?? $decoded['en']
                    ?? $decoded['km']
                    ?? $decoded['kh']
                    ?? ''
                );
            }
        }

        if (is_array($value)) {
            return (string) (
                $value[$locale]
                ?? $value['en']
                ?? $value['km']
                ?? $value['kh']
                ?? ''
            );
        }

        return (string) $value;
    }

    protected static function localeText(mixed $value, string $fallback = ''): string
    {
        return self::displayText($value, $fallback);
    }

    protected static function englishText(mixed $value, string $fallback = ''): string
    {
        if (is_array($value)) {
            return self::displayText($value['en'] ?? null, '') ?: self::displayText($value, $fallback);
        }

        if (! is_string($value)) {
            return self::displayText($value, $fallback);
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return self::displayText($decoded['en'] ?? null, '') ?: self::displayText($decoded, $fallback);
        }

        return self::displayText($value, $fallback);
    }

    protected static function displayText(mixed $value, string $fallback = ''): string
    {
        if (is_array($value)) {
            foreach (array_unique([
                app()->getLocale(),
                config('app.fallback_locale', 'en'),
                'km',
                'en',
            ]) as $locale) {
                $text = self::displayText($value[$locale] ?? null, '');

                if (filled($text)) {
                    return $text;
                }
            }

            foreach ($value as $item) {
                $text = self::displayText($item, '');

                if (filled($text)) {
                    return $text;
                }
            }

            return $fallback;
        }

        if (is_object($value)) {
            return self::displayText(json_decode(json_encode($value), true), $fallback);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return self::displayText($decoded, $fallback);
            }

            return filled($value) ? $value : $fallback;
        }

        if (is_scalar($value)) {
            $text = (string) $value;

            return filled($text) ? $text : $fallback;
        }

        return $fallback;
    }
}
