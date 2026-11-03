<?php

namespace Chanthoeun\FilamentDocumentBuilder\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PublishResourceCommand extends Command
{
    protected $signature = 'filament-document-builder:publish-resource {--force : Overwrite existing files}';

    protected $description = 'Publish the DocumentTemplateResource to your application';

    public function handle()
    {
        $sourcePath = __DIR__.'/../Resources';
        $targetPath = app_path('Filament/Resources');

        if (! File::exists($targetPath)) {
            File::makeDirectory($targetPath, 0755, true);
        }

        $resourceFile = 'DocumentTemplateResource.php';
        $resourceDir = 'DocumentTemplateResource';

        if (File::exists($targetPath.'/'.$resourceFile) && ! $this->option('force')) {
            $this->error("{$resourceFile} already exists in your application. Use --force to overwrite.");

            return;
        }

        // Copy Resource File
        if (File::exists($sourcePath.'/'.$resourceFile)) {
            $content = File::get($sourcePath.'/'.$resourceFile);
            $content = str_replace('namespace Chanthoeun\FilamentDocumentBuilder\Resources;', 'namespace App\Filament\Resources;', $content);
            $content = str_replace('Chanthoeun\FilamentDocumentBuilder\Resources', 'App\Filament\Resources', $content);
            File::put($targetPath.'/'.$resourceFile, $content);
            $this->info("Published {$resourceFile}");
        }

        // Copy Resource Directory
        if (File::exists($sourcePath.'/'.$resourceDir)) {
            $targetResourceDir = $targetPath.'/'.$resourceDir;
            if (! File::exists($targetResourceDir)) {
                File::makeDirectory($targetResourceDir, 0755, true);
            }

            $files = File::allFiles($sourcePath.'/'.$resourceDir);
            foreach ($files as $file) {
                $content = File::get($file->getPathname());

                // Replace namespaces
                $content = str_replace('namespace Chanthoeun\FilamentDocumentBuilder\Resources', 'namespace App\Filament\Resources', $content);
                $content = str_replace('use Chanthoeun\FilamentDocumentBuilder\Resources', 'use App\Filament\Resources', $content);

                $relativePath = $file->getRelativePathname();
                $targetFilePath = $targetResourceDir.'/'.$relativePath;

                $targetFileDir = dirname($targetFilePath);
                if (! File::exists($targetFileDir)) {
                    File::makeDirectory($targetFileDir, 0755, true);
                }

                File::put($targetFilePath, $content);
                $this->info("Published {$resourceDir}/{$relativePath}");
            }
        }

        $this->newLine();
        $this->info('DocumentTemplateResource has been published to your application!');
        $this->warn('Make sure to comment out or remove `DocumentTemplateResource::class` from your `DocumentBuilderPlugin::make()->resources([...])` so it doesn\'t conflict with your published copy!');
    }
}
