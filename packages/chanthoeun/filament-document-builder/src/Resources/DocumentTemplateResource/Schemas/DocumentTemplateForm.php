<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Schemas;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class DocumentTemplateForm
{
    protected static ?array $modelOptionsCache = null;

    protected static function getModelOptions(): array
    {
        return static::$modelOptionsCache ??= Cache::remember(
            'filament-document-builder.model_options',
            60,
            function () {
                $models = [];
                $path = app_path('Models');
                if (is_dir($path)) {
                    foreach (scandir($path) as $file) {
                        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                            $class = 'App\\Models\\'.pathinfo($file, PATHINFO_FILENAME);
                            if (class_exists($class)) {
                                $models[$class] = class_basename($class);
                            }
                        }
                    }
                }
                if (class_exists('Chanthoeun\FilamentCustomForms\Models\CustomFormEntry')) {
                    $models['Chanthoeun\FilamentCustomForms\Models\CustomFormEntry'] = 'Custom Form Entry';
                }

                return $models;
            }
        );
    }

    protected static function validateModelClass(?string $value): bool
    {
        if (empty($value)) {
            return true;
        }

        return class_exists($value) && is_a($value, Model::class, true);
    }

    public static function schema(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Wizard::make([
                    Step::make(__('filament-document-builder::document-builder.labels.template_details'))->schema([
                        Grid::make(3)->schema([
                            Forms\Components\TextInput::make('name')
                                ->label(__('filament-document-builder::document-builder.labels.template_name'))
                                ->required()
                                ->afterStateHydrated(function ($component, $state, ?Model $record): void {
                                    $component->state(static::localizedName($record?->getRawOriginal('name') ?? $state));
                                })
                                ->dehydrateStateUsing(function ($state, ?Model $record): string {
                                    return static::dehydrateLocalizedName($state, $record?->getRawOriginal('name'));
                                })
                                ->maxLength(255),
                            Forms\Components\TextInput::make('type')
                                ->label(__('filament-document-builder::document-builder.labels.template_type'))
                                ->placeholder(__('filament-document-builder::document-builder.labels.type_placeholder'))
                                ->maxLength(255),
                            Forms\Components\Select::make('model_class')
                                ->label(__('filament-document-builder::document-builder.labels.database_model'))
                                ->options(fn () => static::getModelOptions())
                                ->live()
                                ->placeholder(__('filament-document-builder::document-builder.labels.model_placeholder'))
                                ->rules(fn () => [
                                    function (string $attribute, mixed $value, \Closure $fail) {
                                        if (! empty($value) && ! static::validateModelClass($value)) {
                                            $fail(__('filament-document-builder::document-builder.validation.invalid_model'));
                                        }
                                    },
                                ]),
                        ]),
                        Forms\Components\KeyValue::make('page_settings')
                            ->label(__('filament-document-builder::document-builder.labels.page_settings'))
                            ->keyLabel(__('filament-document-builder::document-builder.labels.setting'))
                            ->valueLabel(__('filament-document-builder::document-builder.labels.value'))
                            ->default([
                                'format' => config('filament-document-builder.default_paper_size', 'a4'),
                                'orientation' => config('filament-document-builder.default_orientation', 'portrait'),
                                'default_font' => 'calibri',
                                'margin_left' => config('filament-document-builder.default_margins.left', '15'),
                                'margin_right' => config('filament-document-builder.default_margins.right', '15'),
                                'margin_top' => config('filament-document-builder.default_margins.top', '16'),
                                'margin_bottom' => config('filament-document-builder.default_margins.bottom', '16'),
                                'margin_header' => config('filament-document-builder.default_margins.header', '9'),
                                'margin_footer' => config('filament-document-builder.default_margins.footer', '9'),
                            ])
                            ->live(),
                        Forms\Components\Repeater::make('extra_data_sources')
                            ->label(__('filament-document-builder::document-builder.labels.additional_data_sources'))
                            ->schema([
                                Forms\Components\TextInput::make('variable_name')
                                    ->label(__('filament-document-builder::document-builder.labels.variable_name'))
                                    ->required()
                                    ->placeholder(__('filament-document-builder::document-builder.labels.variable_placeholder')),
                                Forms\Components\Select::make('model_class')
                                    ->label(__('filament-document-builder::document-builder.labels.database_model'))
                                    ->required()
                                    ->options(fn () => static::getModelOptions())
                                    ->searchable()
                                    ->rules(fn () => [
                                        function (string $attribute, mixed $value, \Closure $fail) {
                                            if (! empty($value) && ! static::validateModelClass($value)) {
                                                $fail(__('filament-document-builder::document-builder.validation.invalid_model'));
                                            }
                                        },
                                    ]),
                                Forms\Components\Select::make('retrieval_method')
                                    ->label(__('filament-document-builder::document-builder.labels.retrieval_method'))
                                    ->required()
                                    ->options([
                                        'first' => __('filament-document-builder::document-builder.labels.first_record'),
                                        'latest' => __('filament-document-builder::document-builder.labels.latest_record'),
                                    ])
                                    ->default('first'),
                            ])
                            ->columns(3)
                            ->live()
                            ->itemLabel(fn (array $state): ?string => $state['variable_name'] ?? null),
                    ]),
                    Step::make(__('filament-document-builder::document-builder.labels.document_designer'))->schema([
                        Group::make([
                            TinyEditor::make('content')
                                ->label(__('filament-document-builder::document-builder.labels.document_designer'))
                                ->hiddenLabel()
                                ->required(false)
                                ->columnSpanFull()
                                ->fileAttachmentsDisk('public')
                                ->fileAttachmentsDirectory('document-templates')
                                ->profile('full')
                                ->toolbarMode('wrap')
                                // Keep the wrapped toolbar inside the designer so it cannot cover the Filament navigation.
                                ->toolbarSticky(false)
                                ->setCustomConfigs(function (Get $get) {
                                    $vars = [];
                                    $modelClass = $get('model_class');
                                    if ($modelClass && class_exists($modelClass)) {
                                        $model = new $modelClass;
                                        $vars = array_merge(['id', 'created_at', 'updated_at'], $model->getFillable());

                                        $type = $get('type');
                                        /** @phpstan-ignore-next-line */
                                        if ($modelClass === 'Chanthoeun\FilamentCustomForms\Models\CustomFormEntry' && $type && str_starts_with($type, 'custom_form_')) {
                                            $formId = str_replace('custom_form_', '', $type);
                                            $customFormClass = 'Chanthoeun\FilamentCustomForms\Models\CustomForm';
                                            $customForm = class_exists($customFormClass) ? $customFormClass::find($formId) : null;
                                            if ($customForm) {
                                                $customFields = [];
                                                if ($customForm->fields()->count() > 0) {
                                                    foreach ($customForm->fields as $field) {
                                                        if (! in_array($field->type, ['section', 'grid', 'fieldset', 'wizard']) && ! empty($field->name)) {
                                                            $customFields[] = 'data.'.$field->name;
                                                        }
                                                    }
                                                } elseif (is_array($customForm->schema)) {
                                                    $extractFields = function ($schema) use (&$extractFields, &$customFields) {
                                                        foreach ($schema as $block) {
                                                            $bType = $block['type'] ?? null;
                                                            $data = $block['data'] ?? [];
                                                            if (in_array($bType, ['section', 'grid', 'fieldset', 'repeater'])) {
                                                                if (! empty($data['schema'])) {
                                                                    $extractFields($data['schema']);
                                                                }
                                                            } elseif (! empty($data['name'])) {
                                                                $customFields[] = 'data.'.$data['name'];
                                                            }
                                                        }
                                                    };
                                                    $extractFields($customForm->schema);
                                                }
                                                $vars = array_merge($vars, $customFields);
                                            }
                                        }
                                    }

                                    $extraSources = $get('extra_data_sources') ?? [];
                                    foreach ($extraSources as $source) {
                                        if (! empty($source['variable_name'])) {
                                            $vars[] = $source['variable_name'];

                                            if (! empty($source['model_class']) && class_exists($source['model_class'])) {
                                                $extraModel = new $source['model_class'];
                                                $fields = array_merge(['id', 'created_at', 'updated_at'], $extraModel->getFillable());
                                                foreach ($fields as $field) {
                                                    $vars[] = $source['variable_name'].'.'.$field;
                                                }
                                            }
                                        }
                                    }

                                    $vars = array_merge($vars, static::registrationCardVariables($get('type')));

                                    /** @var array<string> $vars */
                                    $vars = array_values(array_unique(array_filter($vars)));
                                    sort($vars);

                                    $settings = $get('page_settings') ?? [];
                                    $format = $settings['format'] ?? 'a4';
                                    $orientation = $settings['orientation'] ?? 'portrait';
                                    $marginTop = $settings['margin_top'] ?? '16';
                                    $marginBottom = $settings['margin_bottom'] ?? '16';
                                    $marginLeft = $settings['margin_left'] ?? '15';
                                    $marginRight = $settings['margin_right'] ?? '15';

                                    $sizes = [
                                        'a3' => ['width' => 297, 'height' => 420],
                                        'a4' => ['width' => 210, 'height' => 297],
                                        'a5' => ['width' => 148, 'height' => 210],
                                        'letter' => ['width' => 215.9, 'height' => 279.4],
                                        'legal' => ['width' => 215.9, 'height' => 355.6],
                                    ];

                                    $dimensions = $sizes[strtolower($format)] ?? $sizes['a4'];
                                    $width = $orientation === 'landscape' ? $dimensions['height'] : $dimensions['width'];
                                    $minHeight = $orientation === 'landscape' ? $dimensions['width'] : $dimensions['height'];

                                    $contentStyle = '@import url("https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&family=Moul&family=Siemreap&display=swap"); '.
                                        'html { background: #f3f4f6 !important; padding: 20px 0 !important; } '.
                                        'body { font-family: Calibri, "Battambang", Arial, sans-serif; background: #fff !important; '.
                                        'width: '.$width.'mm !important; max-width: '.$width.'mm !important; min-height: '.$minHeight.'mm !important; '.
                                        'padding: '.$marginTop.'mm '.$marginRight.'mm '.$marginBottom.'mm '.$marginLeft.'mm !important; '.
                                        'margin: 0 auto !important; box-shadow: 0 0 10px rgba(0,0,0,0.1) !important; box-sizing: border-box !important; } '.
                                        'p { margin-top: 0 !important; }';

                                    return [
                                        'document_variables' => $vars,
                                        'menubar' => 'file edit view insert format tools table help',
                                        'font_family_formats' => 'Arial=arial,helvetica,sans-serif; Calibri=calibri,sans-serif; Times New Roman="times new roman",times,serif; Khmer Battambang=Battambang,sans-serif; Khmer Moul="Khmer OS Muol Light",Moul,cursive; Khmer Siemreap=Siemreap,sans-serif;',
                                        'content_style' => $contentStyle,
                                        'min_height' => ceil($minHeight * 3.7795275591) + 40, // Convert mm to px and add some padding
                                        'plugins' => 'custom_shapes accordion autoresize codesample directionality advlist autolink link image lists charmap preview anchor pagebreak searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media table emoticons help',
                                        'toolbar' => 'undo redo removeformat | fontfamily fontsize fontsizeinput font_size_formats styles | bold italic underline | rtl ltr | alignjustify alignright aligncenter alignleft | numlist bullist outdent indent accordion | forecolor backcolor | blockquote table toc hr | image link anchor media codesample emoticons document_templates insert_variable insert_qrcode insert_barcode | visualblocks print preview wordcount fullscreen help',
                                        'document_templates' => config('filament-document-builder.templates', []),
                                        'text_patterns' => [
                                            ['start' => '#logo', 'replacement' => '<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; border-radius: 50%; text-align: center; line-height: 80px;">LOGO</div>'],
                                            ['start' => '#box', 'replacement' => '<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; text-align: center; line-height: 80px;">BOX</div>'],
                                            ['start' => '#photo', 'replacement' => '<div style="display: inline-block; width: 80px; height: 100px; border: 1px solid #000; text-align: center; padding-top: 30px; box-sizing: border-box;">រូបថត<br>៤x៦</div>'],
                                            ['start' => '#checkbox', 'replacement' => '<div style="display: inline-block; width: 16px; height: 16px; border: 1px solid #000; text-align: center;"></div>'],
                                            ['start' => '#rounded', 'replacement' => '<div style="display: inline-block; width: 120px; height: 60px; border: 1px solid #000; border-radius: 10px; text-align: center; line-height: 60px;">TEXT</div>'],
                                            ['start' => '#oval', 'replacement' => '<div style="display: inline-block; width: 120px; height: 60px; border: 1px solid #000; border-radius: 50%; text-align: center; line-height: 60px;">OVAL</div>'],
                                            ['start' => '#sign', 'replacement' => '<div style="display: inline-block; width: 200px; text-align: center; border-bottom: 1px solid #000; padding-bottom: 5px; margin-top: 40px;">ហត្ថលេខា / Signature</div>'],
                                        ],
                                    ];
                                }),
                        ])->key(fn (Get $get) => 'tinymce-group-'.md5(json_encode($get('extra_data_sources')).$get('model_class').$get('type').json_encode($get('page_settings')))),
                    ]),
                ])->columnSpanFull()->skippable(),
            ])
            ->columns(1);
    }

    protected static function registrationCardVariables(?string $type): array
    {
        if (! in_array($type, ['registration_card', 'registration_card_receipt'], true)) {
            return [];
        }

        return array_merge(
            static::registrationCardSystemVariables(),
            static::registrationCardFieldVariables(),
            static::registrationReceiptVariables($type),
            static::endOfYearRegistrationEntryVariables(),
        );
    }

    protected static function registrationCardSystemVariables(): array
    {
        return [
            'registration_card_data.current_date',
            'registration_card_data.current_date_en',
            'registration_card_data.current_date_km',
            'registration_card_data.review_by',
            'registration_card_data.reviewed_by_name',
            'registration_card_data.reviewer_name',
        ];
    }

    protected static function registrationReceiptVariables(?string $type): array
    {
        if ($type !== 'registration_card_receipt') {
            return [];
        }

        return [
            'registration_card_data.receipt_certificate',
            'registration_card_data.receipt_diploma',
        ];
    }

    protected static function registrationCardFieldVariables(): array
    {
        if (! class_exists(\App\Models\RegistrationCardField::class)) {
            return [];
        }

        return \App\Models\RegistrationCardField::query()
            ->where('is_active', true)
            ->whereNotNull('name')
            ->orderBy('sort')
            ->orderBy('id')
            ->pluck('name')
            ->filter()
            ->map(fn (string $name): string => 'registration_card_data.'.$name)
            ->all();
    }

    protected static function endOfYearRegistrationEntryVariables(): array
    {
        $customFormClass = 'Chanthoeun\FilamentCustomForms\Models\CustomForm';

        if (! class_exists($customFormClass)) {
            return [];
        }

        $customForm = $customFormClass::query()
            ->where('slug', 'end-of-year-registration')
            ->first();

        if (! $customForm) {
            return [];
        }

        $variables = [];

        foreach ($customForm->fields as $field) {
            if (! in_array($field->type, ['section', 'grid', 'fieldset', 'wizard'], true) && filled($field->name)) {
                $variables[] = 'entry.data.'.$field->name;
            }
        }

        if (empty($variables) && is_array($customForm->schema)) {
            static::extractCustomFormSchemaVariables($customForm->schema, $variables);
        }

        return $variables;
    }

    protected static function extractCustomFormSchemaVariables(array $schema, array &$variables): void
    {
        foreach ($schema as $block) {
            $type = $block['type'] ?? null;
            $data = $block['data'] ?? [];

            if (in_array($type, ['section', 'grid', 'fieldset', 'repeater', 'wizard'], true)) {
                if (! empty($data['schema']) && is_array($data['schema'])) {
                    static::extractCustomFormSchemaVariables($data['schema'], $variables);
                }

                continue;
            }

            if (! empty($data['name'])) {
                $variables[] = 'entry.data.'.$data['name'];
            }
        }
    }

    protected static function localizedName(mixed $value): string
    {
        $decoded = static::decodeLocalizedValue($value);

        if (! is_array($decoded)) {
            return (string) ($decoded ?? '');
        }

        $locale = app()->getLocale();

        return static::firstLocalizedText($decoded, [$locale, 'km', 'kh', 'en']);
    }

    protected static function dehydrateLocalizedName(mixed $state, mixed $original): string
    {
        $name = trim((string) $state);
        $decoded = static::decodeLocalizedValue($original);

        if (! is_array($decoded)) {
            return $name;
        }

        $locale = app()->getLocale();
        $decoded[$locale] = $name;

        if (in_array($locale, ['km', 'kh'], true)) {
            $decoded['km'] = $name;
            $decoded['kh'] = $name;
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }

    protected static function decodeLocalizedValue(mixed $value): mixed
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_object($value)) {
            return json_decode(json_encode($value), true);
        }

        return $value;
    }

    protected static function firstLocalizedText(array $value, array $preferredKeys): string
    {
        foreach (array_unique($preferredKeys) as $key) {
            if (isset($value[$key]) && trim((string) $value[$key]) !== '') {
                return (string) $value[$key];
            }
        }

        foreach ($value as $text) {
            if (is_scalar($text) && trim((string) $text) !== '') {
                return (string) $text;
            }
        }

        return '';
    }
}
