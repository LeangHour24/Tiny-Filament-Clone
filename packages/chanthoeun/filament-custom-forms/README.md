# Filament Custom Forms

A powerful and simplified FilamentPHP plugin to manage and submit dynamic custom forms. Refactored for speed and ease of use in standalone environments.

## Features

- Dynamic form builder with custom fields.
- Submission management with a clean interface.
- Support for Filament v4 and v5.
- Easy integration as a standalone package.

## Installation

### 1. Requirements

- PHP 8.2+
- Filament v4.0 or v5.0

### 2. Install via Composer

```bash
composer require chanthoeun/filament-custom-forms
```
### 3. Publish Assets

```bash
php artisan vendor:publish --tag="filament-custom-forms-config"
php artisan vendor:publish --tag="filament-custom-forms-migrations"
```

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Register the Plugin

Add the plugin to your Filament Panel provider:

```php
use Chanthoeun\FilamentCustomForms\CustomFormPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            CustomFormPlugin::make()
                ->navigationGroup('Form Builder')
                ->navigationFormIcon('heroicon-o-document-duplicate')
                ->navigationEntryIcon('heroicon-o-clipboard-document-list')
        );
}
```

## Updates

To update the package to the latest version, run:

```bash
composer update chanthoeun/filament-custom-forms
```

If the update includes new migrations or changes to published assets, you may need to re-publish or run:

```bash
php artisan migrate
```

## Versioning

This project follows [Semantic Versioning](https://semver.org/). We use Git tags to manage releases.

To release a new version:
1.  Update `CHANGELOG.md`.
2.  Commit your changes.
3.  Tag the release: `git tag v1.0.1`.
4.  Push the tag: `git push origin v1.0.1`.

## Usage

1.  **Form Creation**: Navigate to the **Custom Forms** resource to create dynamic forms using the builder.
2.  **Data Collection**: Users can submit entries through the generated forms.
3.  **Entry Management**: View and export entries in the **Custom Form Entries** resource.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

