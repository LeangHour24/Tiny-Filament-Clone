# Tiny-Filament-Clone

Source snapshot of the customized **Custom Forms** and **Document Builder** plugins used by UHS AMS. This repository contains two Composer packages, not a complete Laravel application or the TinyEditor package.

## Packages

| Directory | Composer package |
| --- | --- |
| `packages/chanthoeun/filament-custom-forms` | `chanthoeun/filament-custom-forms` |
| `packages/chanthoeun/filament-document-builder` | `chanthoeun/filament-document-builder` |

The original namespaces, package metadata, authorship, and source files are preserved. TinyEditor, Filament, Laravel, mPDF, QR-code and barcode libraries remain Composer dependencies; their vendor directories are not included.

## What was copied

The tracked package files were copied from the working source in `UHS-AMS-1-old`, including PHP code, configuration, migrations, Blade templates, translations, JavaScript, fonts, and package tests. `SOURCE-MANIFEST.json` records the source commit and SHA-256 hashes of the copied files.

Application code, `.env` files, database contents, user uploads, application logs, and vendor directories are not included. Template records saved in the application database are not part of this source snapshot.

## Use in an existing AMS project

1. Clone this repository beside your Laravel project:

   ```shell
   git clone https://github.com/LeangHour24/Tiny-Filament-Clone.git
   ```

2. In the Laravel project's `composer.json`, configure both path repositories. These relative paths assume the clone and Laravel project are sibling directories. Merge these entries into the existing `repositories` object and replace any existing entries for these same two packages:

   ```json
   {
     "repositories": {
       "chanthoeun-filament-custom-forms": {
         "type": "path",
         "url": "../Tiny-Filament-Clone/packages/chanthoeun/filament-custom-forms",
         "options": {
           "symlink": true,
           "versions": {
             "chanthoeun/filament-custom-forms": "1.3.4"
           }
         }
       },
       "chanthoeun-filament-document-builder": {
         "type": "path",
         "url": "../Tiny-Filament-Clone/packages/chanthoeun/filament-document-builder",
         "options": {
           "symlink": true,
           "versions": {
             "chanthoeun/filament-document-builder": "1.3.8"
           }
         }
       }
     }
   }
   ```

   The version aliases match the source AMS project's path configuration; they are not release tags published by this repository.

3. Keep or add the package requirements, then resolve them from the Laravel project:

   ```shell
   composer require chanthoeun/filament-custom-forms:^1.1 chanthoeun/filament-document-builder:^1.1
   ```

   If Composer needs to change locked dependencies, review its proposed changes and resolve them against your application's supported PHP, Laravel, and Filament versions. Both packages declare PHP 8.2+ and Filament 4/5 constraints; the source AMS application uses PHP 8.3 and Filament 5.

4. Follow the package READMEs for panel registration, configuration, asset publishing, and migrations. Existing AMS projects may already have completed these steps.

With `symlink: true`, Composer links each application's vendor entry to this clone's package directory. Editing this clone then affects each application linked to it. Without linking, each application uses its own installed copy.

## AMS integration requirements

This is a snapshot of customized packages, not a promise of standalone compatibility. Custom Forms references host application classes such as:

- `App\Models\CustomFormEntry`, `StudentApplicationReview`, and `GeoLocation`;
- `App\Support\ClosingDateWorkflow`, `CustomFormTemplateData`, and `CustomFormEntryImageData`;
- `App\Filament\Support\RoleAccess` and `ResourceActionPermissions`;
- `App\Services\StudentWorkflowGate` and student panel pages.

An unrelated Laravel application must provide equivalent integrations or adapt those customizations. The student portal's `App\Services\DocumentRequestRenderer` override remains application code and is not included here.

## Future changes

1. Edit and test the appropriate package in this clone.
2. Review `git diff`.
3. Commit and push the package change:

   ```shell
   git add packages
   git commit -m "Describe the plugin change"
   git push origin main
   ```

4. Pull the commit on other machines. Linked local applications immediately see the updated source; copied installations need reinstalling or updating through Composer.

This is a monorepo containing two packages. Adding its URL as a single Composer VCS repository does not automatically install both nested packages. Use the path setup above, or publish each package separately if independent VCS/Packagist distribution is needed.

## Attribution and licensing

The two package manifests declare the MIT license and retain their original authorship. Refer to each package's existing metadata and documentation. This export does not change third-party dependency licenses.
