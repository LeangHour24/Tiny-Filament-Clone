<?php

namespace Chanthoeun\FilamentDocumentBuilder\Tests;

use Chanthoeun\FilamentDocumentBuilder\FilamentDocumentBuilderServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            FilamentDocumentBuilderServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filament-document-builder.default_paper_size', 'a4');
        $app['config']->set('filament-document-builder.default_orientation', 'portrait');
        $app['config']->set('filament-document-builder.default_margins', [
            'top' => 16,
            'bottom' => 16,
            'left' => 15,
            'right' => 15,
            'header' => 9,
            'footer' => 9,
        ]);
    }
}
