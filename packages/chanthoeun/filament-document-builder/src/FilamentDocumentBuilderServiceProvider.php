<?php

namespace Chanthoeun\FilamentDocumentBuilder;

use Chanthoeun\FilamentDocumentBuilder\Commands\PublishResourceCommand;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentDocumentBuilderServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-document-builder')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigration('create_document_templates_table')
            ->hasCommand(PublishResourceCommand::class);
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Js::make('custom-shapes', __DIR__.'/../resources/js/custom-shapes.js'),
        ], 'chanthoeun/filament-document-builder');

        // Clear the extra data source cache between requests in long-running workers
        if (class_exists(RequestHandled::class)) {
            $this->app['events']->listen(RequestHandled::class, function () {
                DocumentRenderer::clearCache();
            });
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/Resources/DocumentTemplateResource.php' => app_path('Filament/Resources/DocumentTemplateResource.php'),
                __DIR__.'/Resources/DocumentTemplateResource' => app_path('Filament/Resources/DocumentTemplateResource'),
            ], 'filament-document-template');
        }
    }
}
