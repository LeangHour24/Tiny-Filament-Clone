<?php

namespace Chanthoeun\FilamentDocumentBuilder\Services;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;
use Picqer\Barcode\BarcodeGeneratorPNG;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DocumentRenderer
{
    /**
     * Cache for extra data sources during the request lifecycle to prevent N+1 queries across multiple PDFs.
     */
    protected static array $extraDataCache = [];

    /**
     * Clear the extra data source cache. Call this between requests in long-running workers
     * (e.g. Octane, Horizon) to prevent stale data.
     */
    public static function clearCache(): void
    {
        self::$extraDataCache = [];
    }

    /**
     * Resolve a value by key, falling back through parent scopes if null.
     */
    protected function resolveWithParentScope(mixed $value, array|object $data, string $key): mixed
    {
        $currentContext = $data;
        while ($value === null && is_array($currentContext) && array_key_exists('_parent', $currentContext)) {
            $currentContext = $currentContext['_parent'];
            $value = data_get($currentContext, $key);
        }

        return $value;
    }

    /**
     * Parse the template HTML and extract relation paths for eager loading.
     */
    protected function parseRelationPaths(string $content): array
    {
        preg_match_all('/{{\s*(?:#foreach\s+)?([a-zA-Z0-9_\.]+)/', $content, $matches);

        $relationsToLoad = [];
        foreach ($matches[1] as $match) {
            $parts = explode('.', $match);
            if (count($parts) > 1) {
                array_pop($parts);
                $relationsToLoad[] = implode('.', $parts);
            }
        }

        return array_unique($relationsToLoad);
    }

    /**
     * Eager load relations used in the template to prevent N+1 queries.
     * Accepts a single Model or an Eloquent Collection.
     */
    protected function preloadRelations(?string $content, Model|Collection $data): void
    {
        if (empty($content)) {
            return;
        }

        $relationsToLoad = $this->parseRelationPaths($content);

        if (empty($relationsToLoad)) {
            return;
        }

        $referenceModel = $data instanceof Collection ? $data->first() : $data;

        if (! $referenceModel instanceof Model) {
            return;
        }

        $validRelations = [];
        foreach ($relationsToLoad as $relationPath) {
            $validPath = $this->validRelationPath($referenceModel, $relationPath);

            if ($validPath !== null) {
                $validRelations[] = $validPath;
            }
        }

        $validRelations = array_unique($validRelations);

        if (! empty($validRelations)) {
            $data->loadMissing($validRelations);
        }
    }


    protected function validRelationPath(Model $model, string $path): ?string
    {
        $currentModel = $model;
        $validParts = [];

        foreach (explode('.', $path) as $part) {
            if ($part === '' || ! method_exists($currentModel, $part)) {
                break;
            }

            try {
                $relation = $currentModel->{$part}();
            } catch (\Throwable) {
                break;
            }

            if (! $relation instanceof Relation) {
                break;
            }

            $validParts[] = $part;
            $currentModel = $relation->getRelated();
        }

        return $validParts === [] ? null : implode('.', $validParts);
    }

    /**
     * Replaces loop blocks like {{#foreach items as item}} ... {{/foreach}}
     */
    protected function replaceLoops(?string $content, array|object $data): ?string
    {
        if (empty($content)) {
            return $content;
        }

        // Use precise regex to prevent catastrophic backtracking and avoid swallowing preceding tags
        return preg_replace_callback(
            '/{{\s*#foreach\s+([a-zA-Z0-9_\.]+)\s+as\s+([a-zA-Z0-9_]+)\s*}}(.*?){{\s*\/foreach\s*}}/is',
            function ($matches) use ($data) {
                $arrayPath = trim(strip_tags($matches[1]));
                $arrayPath = html_entity_decode(str_replace('&nbsp;', '', $arrayPath));

                $itemName = trim(strip_tags($matches[2]));
                $itemName = html_entity_decode(str_replace('&nbsp;', '', $itemName));

                $blockContent = $matches[3];

                $items = $this->resolveWithParentScope(data_get($data, $arrayPath), $data, $arrayPath);

                if (! is_iterable($items)) {
                    return '';
                }

                $result = '';

                foreach ($items as $item) {
                    $loopData = [
                        '_parent' => $data,
                        $itemName => $item,
                    ];

                    $result .= $this->replaceVariables($blockContent, $loopData);
                }

                return $result;
            },
            $content
        );
    }

    /**
     * Generate a QR code image tag from a value.
     * Produces an SVG base64 data URI so mPDF can embed it without external requests.
     */
    protected function generateQrCodeTag(string $value, int $size): string
    {
        try {
            $svg = (string) QrCode::format('svg')->size($size)->generate($value);

            return '<img src="data:image/svg+xml;base64,'.base64_encode($svg).'" '
                .'width="'.$size.'" height="'.$size.'" style="display:inline-block;" />';
        } catch (\Throwable $e) {
            report($e);

            return '<!-- QR code generation failed -->';
        }
    }

    /**
     * Map of human-readable barcode type names to BarcodeGeneratorPNG constants.
     */
    protected static array $barcodeTypes = [
        'C128' => 'TYPE_CODE_128',
        'C128A' => 'TYPE_CODE_128_A',
        'C128B' => 'TYPE_CODE_128_B',
        'C128C' => 'TYPE_CODE_128_C',
        'C39' => 'TYPE_CODE_39',
        'C39+' => 'TYPE_CODE_39_CHECKSUM',
        'C93' => 'TYPE_CODE_93',
        'EAN13' => 'TYPE_EAN_13',
        'EAN8' => 'TYPE_EAN_8',
        'UPCA' => 'TYPE_UPC_A',
        'UPCE' => 'TYPE_UPC_E',
        'I25' => 'TYPE_INTERLEAVED_2_5',
        'S25' => 'TYPE_STANDARD_2_5',
        'CODABAR' => 'TYPE_CODABAR',
        'MSI' => 'TYPE_MSI',
    ];

    /**
     * Generate a barcode image tag from a value using the specified type.
     * Produces a PNG base64 data URI so mPDF can embed it without external requests.
     */
    protected function generateBarcodeTag(string $value, string $type, int $widthFactor, int $height): string
    {
        try {
            $generator = new BarcodeGeneratorPNG;
            $constant = self::$barcodeTypes[$type] ?? 'TYPE_CODE_128';
            $png = $generator->getBarcode($value, constant(BarcodeGeneratorPNG::class.'::'.$constant), $widthFactor, $height);

            return '<img src="data:image/png;base64,'.base64_encode($png).'" '
                .'style="display:inline-block; height:'.$height.'px;" />';
        } catch (\Throwable $e) {
            report($e);

            return '<!-- Barcode generation failed -->';
        }
    }

    /**
     * Replaces QR code blocks: {{#qrcode variable size=100}}
     */
    protected function replaceQrCodes(?string $content, array|object $data): ?string
    {
        if (empty($content)) {
            return $content;
        }

        return preg_replace_callback(
            '/{{\s*#qrcode\s+([a-zA-Z0-9_\.]+)(?:\s+size=(\d+))?\s*}}/i',
            function ($matches) use ($data) {
                $key = trim($matches[1]);
                $size = isset($matches[2]) ? (int) $matches[2] : 100;

                $value = $this->resolveWithParentScope(data_get($data, $key), $data, $key);

                if ($value === null || $value === '') {
                    return '';
                }

                return $this->generateQrCodeTag((string) $value, $size);
            },
            $content
        );
    }

    /**
     * Replaces barcode blocks: {{#barcode variable type=C128 width=2 height=30}}
     */
    protected function replaceBarcodes(?string $content, array|object $data): ?string
    {
        if (empty($content)) {
            return $content;
        }

        return preg_replace_callback(
            '/{{\s*#barcode\s+([a-zA-Z0-9_\.]+)(?:\s+type=([a-zA-Z0-9_]+))?(?:\s+width=(\d+))?(?:\s+height=(\d+))?\s*}}/i',
            function ($matches) use ($data) {
                $key = trim($matches[1]);
                $type = isset($matches[2]) ? strtoupper($matches[2]) : 'C128';
                $widthFactor = isset($matches[3]) ? (int) $matches[3] : 2;
                $height = isset($matches[4]) ? (int) $matches[4] : 30;

                $value = $this->resolveWithParentScope(data_get($data, $key), $data, $key);

                if ($value === null || $value === '') {
                    return '';
                }

                return $this->generateBarcodeTag((string) $value, $type, $widthFactor, $height);
            },
            $content
        );
    }

    /**
     * Replaces string variables like {{ variable.name }} with actual data.
     */
    protected function replaceVariables(?string $content, array|object $data): ?string
    {
        if (empty($content)) {
            return $content;
        }

        // Basic replacement logic for {{ variable }} or {{ variable.key }}
        return preg_replace_callback('/{{([^{}]*)}}/', function ($matches) use ($data) {
            $key = trim(strip_tags($matches[1]));
            $key = html_entity_decode(str_replace('&nbsp;', '', $key));
            $key = trim($key);

            if (str_starts_with($key, '#foreach') || str_starts_with($key, '/foreach')
                || str_starts_with($key, '#qrcode') || str_starts_with($key, '/qrcode')
                || str_starts_with($key, '#barcode') || str_starts_with($key, '/barcode')) {
                return $matches[0];
            }

            $value = $this->resolveWithParentScope(data_get($data, $key), $data, $key);

            if ($value === null || $value === '') {
                return ''; // Return empty string instead of debug text in production
            }
            if ($value instanceof \DateTimeInterface) {
                return \Illuminate\Support\Carbon::instance($value)->translatedFormat('d-F-Y');
            }
            if (is_array($value) || is_object($value)) {
                return ''; // Ignore array printing rather than crashing mPDF
            }

            return $this->formatTemplateValue($key, $value);
        }, $content);
    }

    protected function formatTemplateValue(string $key, mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (! $this->isTemplateDateValue($key, $value)) {
            return $value;
        }

        foreach (['Y-m-d', 'Y-m-d H:i:s', 'd-m-Y', 'd/m/Y', 'Y/m/d'] as $format) {
            try {
                $date = \Carbon\Carbon::createFromFormat($format, $value);

                if ($date !== false) {
                    return $date->translatedFormat(str_contains($format, 'H:i') ? 'd-F-Y H:i' : 'd-F-Y');
                }
            } catch (\Throwable) {
                //
            }
        }

        try {
            return \Carbon\Carbon::parse($value)->translatedFormat('d-F-Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    protected function isTemplateDateValue(string $key, string $value): bool
    {
        $normalizedKey = str($key)
            ->afterLast('.')
            ->lower()
            ->replace(['-', ' '], '_')
            ->snake()
            ->toString();

        $isDateKey = in_array($normalizedKey, ['date_of_birth', 'dob', 'birth_date', 'birthday', 'exam_date'], true)
            || str_ends_with($normalizedKey, '_date')
            || str_ends_with($normalizedKey, '_date_of_birth')
            || str_ends_with($normalizedKey, '_birth_date');

        return $isDateKey || preg_match('/^\d{4}[-\/]\d{2}[-\/]\d{2}(?:\s+\d{2}:\d{2}(?::\d{2})?)?$/', $value) === 1;
    }

    protected function processHtmlContent(DocumentTemplate $template, array|object $data = []): string
    {
        $htmlContent = $template->content ?? '';

        // Eager load relations to prevent N+1 performance bottlenecks
        if ($data instanceof Model || $data instanceof Collection) {
            $this->preloadRelations($htmlContent, $data);
        }

        $data = $this->resolveExtraDataSources($template, $data);

        // Process QR codes and barcodes before standard variable replacement
        $htmlContent = $this->replaceQrCodes($htmlContent, $data);
        $htmlContent = $this->replaceBarcodes($htmlContent, $data);

        $htmlContent = $this->replaceLoops($htmlContent, $data);
        $htmlContent = $this->replaceVariables($htmlContent, $data);

        $htmlContent = $this->applyMpdfPolyfills($htmlContent);

        return $htmlContent;
    }

    /**
     * Fetch extra data sources defined in the template and merge them into the data array.
     */
    protected function resolveExtraDataSources(DocumentTemplate $template, array|object $data): array|object
    {
        $extraData = [];
        if (! empty($template->extra_data_sources)) {
            foreach ($template->extra_data_sources as $source) {
                if (! empty($source['variable_name']) && ! empty($source['model_class']) && class_exists($source['model_class']) && is_a($source['model_class'], Model::class, true)) {
                    $method = $source['retrieval_method'] ?? 'first';
                    $cacheKey = md5($source['model_class'].'_'.$method);

                    if (! isset(self::$extraDataCache[$cacheKey])) {
                        if ($method === 'latest') {
                            self::$extraDataCache[$cacheKey] = $source['model_class']::latest()->first();
                        } else {
                            self::$extraDataCache[$cacheKey] = $source['model_class']::first();
                        }
                    }

                    $extraData[$source['variable_name']] = self::$extraDataCache[$cacheKey];
                }
            }
        }

        if (! empty($extraData)) {
            $data = array_merge(['_parent' => $data], $extraData);
        }

        return $data;
    }

    /**
     * Apply mPDF compatibility polyfills to HTML content.
     */
    protected function applyMpdfPolyfills(string $htmlContent): string
    {
        // CSS flexbox polyfills for mPDF
        $htmlContent = preg_replace('/display:\s*inline-flex;?/', 'display: inline-block;', $htmlContent);
        $htmlContent = preg_replace('/align-items:\s*center;?/', 'vertical-align: middle;', $htmlContent);
        $htmlContent = preg_replace('/justify-content:\s*center;?/', 'text-align: center;', $htmlContent);

        // Fix Zero Width Space (ZWSP) causing empty rectangle boxes in mPDF Khmer rendering
        $htmlContent = str_replace("\xE2\x80\x8B", '', $htmlContent); // ZWSP
        $htmlContent = str_replace("\xE2\x80\x8C", '', $htmlContent); // ZWNJ
        $htmlContent = str_replace("\xE2\x80\x8D", '', $htmlContent); // ZWJ
        $htmlContent = str_replace("\xEF\xBB\xBF", '', $htmlContent); // BOM
        $htmlContent = str_replace('&#8203;', '', $htmlContent);
        $htmlContent = str_replace('&#8204;', '', $htmlContent);
        $htmlContent = str_replace('&#8205;', '', $htmlContent);
        $htmlContent = str_replace('<wbr>', '', $htmlContent);
        $htmlContent = str_replace('<wbr/>', '', $htmlContent);

        // Add line-height to divs with explicit height for vertical centering
        $htmlContent = preg_replace_callback(
            '/<div[^>]*style="([^"]*height:\s*(\d+px)[^"]*)"[^>]*>/i',
            function ($matches) {
                $style = $matches[1];
                $height = $matches[2];
                if (strpos($style, 'line-height') === false) {
                    $newStyle = $style.' line-height: '.$height.';';

                    return str_replace($style, $newStyle, $matches[0]);
                }

                return $matches[0];
            },
            $htmlContent
        );

        // Strip remote Google Fonts @import rules to prevent massive mPDF network delays
        $htmlContent = preg_replace('/@import\s+url\([\'"]?https:\/\/fonts\.googleapis\.com.*?[\'"]?\);?/i', '', $htmlContent);

        // Rewrite /storage/ URLs to absolute filesystem paths for mPDF
        $appUrl = rtrim(config('app.url'), '/');

        $htmlContent = preg_replace(
            '/src=["\']('.preg_quote($appUrl, '/').')?\/storage\/(.*?)["\' ]/i',
            'src="'.public_path('storage/$2').'"',
            $htmlContent
        );

        return $htmlContent;
    }

    protected function generatePdfFromHtml(DocumentTemplate $template, string $htmlContent): mixed
    {
        /** @var view-string $viewName */
        $viewName = 'filament-document-builder::document';
        $settings = $template->getAttribute('page_settings') ?? [];

        $html = view($viewName, [
            'htmlContent' => $htmlContent,
            'settings' => $settings,
        ])->render();

        $format = strtoupper(data_get($settings, 'format', 'a4'));
        $orientation = data_get($settings, 'orientation', 'portrait');

        $pdfConfig = [
            'format' => $format,
            'orientation' => $orientation === 'landscape' ? 'L' : 'P',
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
            'default_font' => 'khmerbattambang',
            'custom_font_dir' => realpath(__DIR__.'/../../resources/fonts').'/',
            'custom_font_data' => [
                'khmerbattambang' => [
                    'R' => 'KhmerOSbattambang.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmerosbattambang' => [
                    'R' => 'KhmerOSbattambang.ttf',
                    'useOTL' => 0xFF,
                ],
                'battambang' => [
                    'R' => 'KhmerOSbattambang.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmermoullight' => [
                    'R' => 'KhmerOSmuollight.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmerosmuollight' => [
                    'R' => 'KhmerOSmuollight.ttf',
                    'useOTL' => 0xFF,
                ],
                'moul' => [
                    'R' => 'KhmerOSmuollight.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmersiemreap' => [
                    'R' => 'KhmerOSsiemreap.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmerossiemreap' => [
                    'R' => 'KhmerOSsiemreap.ttf',
                    'useOTL' => 0xFF,
                ],
                'siemreap' => [
                    'R' => 'KhmerOSsiemreap.ttf',
                    'useOTL' => 0xFF,
                ],
                'calibri' => [
                    'R' => 'FreeSans.ttf',
                    'B' => 'FreeSansBold.ttf',
                    'I' => 'FreeSansOblique.ttf',
                    'BI' => 'FreeSansBoldOblique.ttf',
                ],
                'arial' => [
                    'R' => 'FreeSans.ttf',
                    'B' => 'FreeSansBold.ttf',
                    'I' => 'FreeSansOblique.ttf',
                    'BI' => 'FreeSansBoldOblique.ttf',
                ],
                'timesnewroman' => [
                    'R' => 'FreeSerif.ttf',
                    'B' => 'FreeSerifBold.ttf',
                    'I' => 'FreeSerifItalic.ttf',
                    'BI' => 'FreeSerifBoldItalic.ttf',
                ],
            ],
        ];

        if (is_array($settings)) {
            foreach ($settings as $key => $value) {
                if (! in_array($key, ['format', 'orientation']) && $value !== null && $value !== '') {
                    $pdfConfig[$key] = is_numeric($value) ? (float) $value : $value;
                }
            }
        }

        return Pdf::loadHTML($html, $pdfConfig);
    }

    public function render(DocumentTemplate $template, array|object $data = []): mixed
    {
        $htmlContent = $this->processHtmlContent($template, $data);

        return $this->generatePdfFromHtml($template, $htmlContent);
    }

    public function renderMultiple(DocumentTemplate $template, iterable $records): mixed
    {
        $htmlContent = $template->content ?? '';

        // Convert to Eloquent Collection to enable bulk relation loading
        $eloquentCollection = $records instanceof Collection ? $records : new Collection($records);

        // Bulk load relations for the entire collection to prevent N+1 queries
        $this->preloadRelations($htmlContent, $eloquentCollection);

        $htmlContents = [];
        foreach ($eloquentCollection as $record) {
            $htmlContents[] = $this->processHtmlContent($template, $record);
        }

        // Join multiple records with a pagebreak
        $combinedHtml = implode('<pagebreak />', $htmlContents);

        return $this->generatePdfFromHtml($template, $combinedHtml);
    }
}
