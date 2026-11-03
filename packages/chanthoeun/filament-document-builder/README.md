# Filament Document Builder

A powerful and simplified Filament PHP plugin to build dynamic document templates (like invoices, certificates, and registration forms) using a full-featured TinyMCE editor, and automatically export them to PDFs with native support for complex scripts like Khmer.

It is designed to easily integrate with standalone Filament panels or with other plugins like `chanthoeun/filament-custom-forms`.

## Versioning

| Plugin Version | Filament | Laravel | PHP |
|---|---|---|---|
| **v1.4.x** (latest) | v4.0 or v5.0 | 10.x - 13.x | 8.2+ |
| v1.3.x | v4.0 or v5.0 | 10.x - 13.x | 8.2+ |
| v1.2.x | v4.0 or v5.0 | 10.x - 13.x | 8.2+ |
| v1.1.x | v4.0 or v5.0 | 10.x - 13.x | 8.2+ |
| v1.0.x | v4.0 or v5.0 | 10.x - 13.x | 8.2+ |

This plugin follows [Semantic Versioning](https://semver.org/). Patch releases (e.g. `1.4.0` -> `1.4.1`) are always backwards compatible.

## Features

- **Advanced HTML Designer:** Uses `amidesfahani/filament-tinyeditor` (TinyMCE) instead of the native Tiptap editor to provide robust support for complex HTML structures and native table editing.
- **Dynamic Variables:** Inject runtime database values directly into your text blocks (e.g., `{{ customer.name }}`). Fallbacks safely to a blank space if data is missing (perfect for printing blank forms).
- **QR Codes & Barcodes:** Generate QR codes (`{{#qrcode variable size=100}}`) and barcodes (`{{#barcode variable type=C128 width=2 height=30}}`) directly in your templates.
- **Native PDF Engine:** Powered by `carlos-meneses/laravel-mpdf` (mPDF) for pure PHP generation.
- **Complex Text Shaping:** Pre-configured with `autoScriptToLang` and `autoLangToFont` to perfectly render complex alphabets like Khmer natively, without needing headless Chrome or Puppeteer.
- **Easy Integration:** Drop a simple `Action` onto any Filament table to export the current record as a PDF.
- **Support for Filament v4 & v5 (Laravel 10-13).**

---

## Installation

### 1. Requirements
- PHP 8.2+
- Filament v4.0 or v5.0
- TinyMCE (`amidesfahani/filament-tinyeditor`)
- mPDF (`carlos-meneses/laravel-mpdf`)

*(Note: Node.js, Puppeteer, and Chromium are **no longer required**!)*

### 2. Install via Composer
```bash
composer require chanthoeun/filament-document-builder
```

### 3. Publish & Run Migrations
You can publish the migration file to customize the table prefix or structure before running it:
```bash
php artisan vendor:publish --tag="filament-document-builder-migrations"
```
After publishing, run the migrations to create the `document_templates` table:
```bash
php artisan migrate
```

### 4. Register the Plugin
Add the plugin to your Filament Panel provider (`app/Providers/Filament/AdminPanelProvider.php`):

```php
use Chanthoeun\FilamentDocumentBuilder\DocumentBuilderPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            DocumentBuilderPlugin::make()
                // Optional: Customize or remove the navigation group
                // ->navigationGroup('Custom Group') // Move to another group
                // ->navigationGroup(false) // Remove from groups entirely
                // ->navigationIcon('heroicon-o-document-text') // Change the navigation icon
        );
}
```

---

## Configuration & Publishing

Because this plugin is built with `Spatie\LaravelPackageTools`, all publishing logic is wired up natively.

### 1. Publishing the Config File
To customize default paper sizes, orientations, margins, and the predefined TinyMCE templates (like layout tables and shapes), publish the config file:
```bash
php artisan vendor:publish --tag="filament-document-builder-config"
```
This will create `config/filament-document-builder.php` in your application where you can edit the `templates` array to add your own custom document layouts.

### 2. Publishing Translations
To override the default English and Khmer language files, run:
```bash
php artisan vendor:publish --tag="filament-document-builder-translations"
```

### 3. Publishing the Document Template Resource
If you want complete control over the `DocumentTemplateResource` (e.g., to change its location, permissions, or logic), you can safely publish it to your application by running:
```bash
php artisan filament-document-builder:publish-resource
```
This command intelligently copies the resource files into your `app/Filament/Resources` directory and automatically updates all namespaces. The plugin will automatically detect that you've published the resource and will gracefully yield control, preventing any conflicts.

---

## Updating the Plugin

> **Note:** This plugin is updated independently. Updating `filament-document-builder` does **not** update any other plugin (e.g. `filament-custom-forms`, `filament-tinyeditor`, or `laravel-mpdf`). You must update each plugin separately.

### Update Steps

1. Pull the latest version:
   ```bash
   composer update chanthoeun/filament-document-builder
   ```
2. Run any new migrations (if applicable):
   ```bash
   php artisan migrate
   ```
3. Rebuild Filament assets to sync any cached scripts or editor plugins:
   ```bash
   php artisan filament:assets
   ```

### After Updating

- **Default configuration:** If you have not published the config or resource, no further action is needed.
- **Published config:** If you previously published the config (`config/filament-document-builder.php`), check the [Changelog](#changelog) for new options. You can diff your local file against the new default:
  ```bash
  # View the new default config
  cat vendor/chanthoeun/filament-document-builder/config/filament-document-builder.php
  ```
- **Published resource:** If you previously published the resource via `php artisan filament-document-builder:publish-resource`, your local copy in `app/Filament/Resources/` will **not** automatically receive new updates. You will need to either manually merge the changes or re-publish the resource (which will overwrite your local customizations).

---

## Usage

### 1. Creating a Template
1. Navigate to the **Document Builder** resource in your Filament sidebar.
2. Click **Create Document Template**.
3. Set the layout rules (A4, Portrait) in the Template Details tab.
4. Construct your document using the **Document Designer** tab. Because it uses TinyMCE, you can easily insert and modify highly complex HTML tables.
5. If you plan to inject dynamic variables, type them using standard brace syntax (e.g., `Hello {{ name }}!`).

#### Built-in Shapes and Forms Support
This plugin adds several pre-configured shapes to the TinyMCE editor to make designing forms significantly easier:
- Click the **Templates** dropdown in the toolbar to insert pre-made shapes like a **Circle Logo Placeholder**, a **4x6 Photo Box**, a **Rounded Rectangle**, or a **Signature Line**, as well as full document layouts.
- **Keyboard Shortcuts (Text Patterns):** You can also quickly insert shapes by typing the following codes and pressing Space:
    - `#logo` -> Circular Logo Box
    - `#box` -> Square Box
    - `#photo` -> 4x6 Rectangle Photo Box
    - `#checkbox` -> Small Checkbox
    - `#rounded` -> Rounded Rectangle
    - `#oval` -> Oval Shape
    - `#sign` -> Signature Area with "ហត្ថលេខា / Signature"

#### Flexbox Support in mPDF
By default, mPDF does not support modern CSS Flexbox (`display: inline-flex`), which often breaks designs created in TinyMCE. This plugin **automatically polyfills** Flexbox behaviors (such as `inline-flex`, `align-items`, and `justify-content`) directly in PHP during the export process. Your TinyMCE layouts will render pixel-perfect in the final PDF without requiring complex CSS hacks!

### 2. Looping over Data (Table Repeaters)
If you need to iterate over an array or Eloquent relationship (like line items on an invoice), you can use the built-in `{{#foreach}}` syntax directly in your template.

Switch your TinyMCE editor to **Source Code (`<>`)** view and wrap your repeating elements like this:
```html
<tbody>
    {{#foreach items as item}}
    <tr>
        <td>{{ item.description }}</td>
        <td>{{ item.qty }}</td>
        <td>{{ item.price }}</td>
        <td>{{ item.total }}</td>
    </tr>
    {{/foreach}}
</tbody>
```
- The engine will automatically loop through the `items` array or relationship.
- You can access properties of each item using the prefix you define (e.g., `item.`).
- **Bonus:** Inside the loop, you still have full access to global variables from the parent record!

#### Handling Calculations (e.g., Invoices)
The document rendering engine intentionally avoids executing arbitrary PHP code within the template for security and performance reasons. Therefore, you cannot perform inline math like `{{ item.qty * item.price }}` inside the TinyMCE editor.

To handle calculations such as line totals, taxes, or grand totals, you should use **Laravel Eloquent Accessors** directly on your models. 

**Example (Line Item Total):**
On your `InvoiceItem` model, add an accessor:
```php
public function getTotalAttribute() {
    return $this->qty * $this->price;
}
```
Now, you can simply use `{{ item.total }}` inside your `{{#foreach}}` loop!

**Example (Invoice Grand Total):**
On your parent `Invoice` model:
```php
public function getGrandTotalAttribute() {
    return $this->items->sum('total') + $this->tax_amount;
}
```
Then, anywhere in your document, you can output the grand total using `{{ grand_total }}`.

### 3. Additional Data Sources (Multi-Model Support)
If your document requires data from multiple independent models (e.g., a student application form that needs global `SchoolSettings` or a specific `Principal` user), you can map them entirely through the UI without touching any code!

1. In the **Template Details** tab, click **Add to additional data sources**.
2. Give it a Variable Name (e.g., `school`).
3. Select the Database Model (e.g., `App\Models\SchoolSetting`) and choose "First Record" or "Latest Record".
4. The TinyMCE editor will instantly update its **Insert Variable** dropdown to include all the fields from your new model (e.g., `school.name`, `school.address`).

The PDF Engine will automatically execute these queries at runtime and securely merge the data into your document!

### 4. Exporting PDFs from your Resources

To allow users to download a PDF of a specific record or bulk download multiple records, use the built-in Action classes.

#### Row Action (Single PDF)
Add `DownloadPdfAction` to your resource's table actions to download individual records:

```php
use Chanthoeun\FilamentDocumentBuilder\Tables\Actions\DownloadPdfAction;

public static function table(Table $table): Table
{
    return $table
        // ... columns ...
        ->actions([
            DownloadPdfAction::make('download_pdf')
                ->templateType('invoice') // The type string of the template you created
                // Optional: Customize the filename
                ->filename(fn ($record) => 'invoice-' . $record->id . '.pdf')
                // Optional: Pass custom data (defaults to the $record itself)
                ->recordData(fn ($record) => [
                    'name' => $record->customer_name,
                    'total' => $record->total_amount,
                ])
        ]);
}
```

#### Page Header Action (Bulk PDF)
Add `DownloadAllPdfAction` to your List page header to download multiple records at once (combined into a single PDF with pagebreaks):

```php
use Chanthoeun\FilamentDocumentBuilder\Actions\DownloadAllPdfAction;

protected function getHeaderActions(): array
{
    return [
        DownloadAllPdfAction::make('download_all_pdf')
            ->templateType('invoice')
            ->records(fn () => $this->getFilteredTableQuery()->get()) // Provide the records to export
            ->filename('invoices-export.pdf')
    ];
}
```

---

## Changelog

### v1.4.0
- **Feature**: QR code and barcode support in templates (SVG and PNG base64 for mPDF embedding).
- **Feature**: Model class validation and data source caching to prevent N+1 queries across bulk PDFs.
- **Feature**: Replaced removed TinyMCE template plugin with a custom templates dropdown.
- **Improvement**: Moved TinyMCE templates to the config file so they can be published and customized.
- **Fix**: TinyMCE editor reactive to page settings (height/format).
- **Fix**: Template icon, toolbar conflicts, and font rendering issues.

### v1.3.0
- **Feature**: TinyMCE templates are now publishable and configurable via `config/filament-document-builder.php`.

### v1.2.0
- **Feature**: Support custom TinyMCE fonts for PDF export.
- **Fix**: Khmer font rendering issues and image resolution in PDF exports.
- **Fix**: `autoScriptToLang` enabled for complex script shaping while keeping `autoLangToFont` disabled.
- **Fix**: Code style resolved with Laravel Pint.

### v1.1.0
- **Performance**: Optimized `DocumentRenderer` to prevent N+1 queries and regex backtracking.
- **Feature**: Added advanced layout templates to TinyMCE editor.
- **Improvement**: Improved type safety, decoupled optional dependencies, and cleaned up template rendering logic.
- **Fix**: Removed hardcoded font-family styles to support mPDF auto-font selection for complex scripts.

### v1.0.15
- **Bug Fix**: Fixed an issue where the TinyMCE editor did not dynamically resize based on the live `page_settings` configuration.
- **Bug Fix**: Bypassed Filament TinyEditor's `wire:ignore` caching to ensure that the "Insert Variable" dropdown accurately populates when `extra_data_sources` are modified without requiring a page refresh.
- **Improvement**: Set `format` to uppercase prior to sending it to mPDF to ensure strict compatibility.

### v1.0.13
- Update README with migration publishing instructions

### v1.0.12
- Hide label and remove required validation from content editor

### v1.0.11
- Add navigationSort to allow changing menu order

### v1.0.10
- Rename data method to recordData to avoid conflict with Filament Actions

### v1.0.9
- Fix namespace for Action inheritance

### v1.0.8
- Update README with new action instructions

### v1.0.7
- Create reusable PDF action classes

### v1.0.6
- Add renderMultiple to support bulk PDF generation

### v1.0.5
- Fix variable extraction to use CustomFormField model

### v1.0.4
- Fix Array to string conversion

### v1.0.3
- Fix Action namespace for Filament v4/v5 compatibility

### v1.0.2
- Add publish resource command and improve custom forms integration

### v1.0.1
- Fix Filament v4 Action namespaces

### v1.0.0
- Update composer.json keywords and prepare for Packagist release

---

## License

The MIT License (MIT).

