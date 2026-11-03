<?php

namespace Chanthoeun\FilamentCustomForms;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CustomFormServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-custom-forms')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations([
                'create_custom_forms_table',
                'create_custom_form_fields_table',
                'create_custom_form_entries_table',
            ]);
    }
}
