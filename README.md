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

## ដំឡើងចាប់ពីសូន្យសម្រាប់ project ទូទៅ

ការណែនាំនេះសម្រាប់ Windows PowerShell៖ បង្កើត Laravel project ថ្មី ដំឡើង Filament ហើយភ្ជាប់ plugin ពី repository នេះ។ មិនចាំបាច់ប្រើ project AMS ដើម្បីចាប់ផ្ដើម Laravel និង Filament ទេ។

> **ស្ថានភាពរបស់ plugin បច្ចុប្បន្ន:** repository នេះជា snapshot នៃ plugin ដែលបានកែសម្រាប់ AMS។ ការដំឡើងតាម Composer មិនទាន់ធានាថាមុខងារទាំងអស់ដំណើរការក្នុង Laravel project ទទេបានទេ។ Custom Forms នៅហៅ AMS application classes មួយចំនួន។ ត្រូវកែ integration ទាំងនោះនៅជំហានទី 7 មុនបើកប្រើមុខងារទាំងអស់។ ការណែនាំនេះមិនមែនជាការបញ្ជាក់ថា plugin ត្រូវបានសាកល្បង standalone រួចហើយទេ។

### 1. រៀបចំកម្មវិធីចាំបាច់

ត្រូវមាន PHP 8.3+, Composer 2, Git និង Node.js ជំនាន់ LTS ដែលនៅមានការគាំទ្រ។ បើក PHP extensions ដែល Composer តម្រូវឱ្យមាន និង `pdo_sqlite` ប្រសិនបើប្រើ SQLite។

ពិនិត្យក្នុង PowerShell៖

```powershell
php -v
composer --version
git --version
node -v
npm -v
```

### 2. បង្កើត Laravel project ថ្មី

ឧទាហរណ៍ឈ្មោះ `my-project`៖

```powershell
cd C:\xampp\htdocs
composer create-project laravel/laravel my-project "13.*"
cd my-project
```

អាចប្រើ SQLite ដែល Laravel រៀបចំឱ្យសម្រាប់ចាប់ផ្ដើម ឬកំណត់ database ផ្សេងក្នុង `.env`។ មុន migrate ត្រូវពិនិត្យថា `.env` ចង្អុលទៅ database របស់ project ថ្មី។

```powershell
php artisan migrate
npm install
npm run build
```

### 3. ដំឡើង Filament និងបង្កើតគណនី

ប្រើ `~5.0` សម្រាប់ version constraint ក្នុង PowerShell៖

```powershell
composer require "filament/filament:~5.0"
php artisan filament:install --panels
php artisan make:filament-user
```

ជ្រើស panel ID ជា `admin` ប្រសិនបើ installer សួរ។ បញ្ចូលឈ្មោះ អ៊ីមែល និងពាក្យសម្ងាត់របស់គណនី។ Installer បង្កើត `app/Providers/Filament/AdminPanelProvider.php`។

ឯកសារយោង៖ [Filament 5 installation](https://filamentphp.com/docs/5.x/introduction/installation), [Laravel installation](https://laravel.com/docs/13.x/installation)។

### 4. Clone plugin នៅក្បែរ Laravel project

```powershell
cd C:\xampp\htdocs
git clone https://github.com/LeangHour24/Tiny-Filament-Clone.git
```

បើមាន clone រួចហើយ ប្រើ `git pull` នៅក្នុង clone នោះជំនួសការបង្កើត folder ដដែលម្ដងទៀត។

```text
C:\xampp\htdocs\
├── my-project\
└── Tiny-Filament-Clone\
    └── packages\
        └── chanthoeun\
            ├── filament-custom-forms\
            └── filament-document-builder\
```

### 5. ភ្ជាប់ packages តាម Composer

បើក `my-project/composer.json` ហើយបន្ថែម property `repositories` នៅកម្រិតដូច `require`។ បើមាន `repositories` រួចហើយ ត្រូវបញ្ចូល entries ទាំងពីរទៅក្នុងរចនាសម្ព័ន្ធដែលមានស្រាប់ ជំនួសការបង្កើត property ឈ្មោះដូចគ្នាពីរដង។

```json
"repositories": [
    {
        "type": "path",
        "url": "../Tiny-Filament-Clone/packages/chanthoeun/filament-custom-forms",
        "options": {
            "symlink": true,
            "versions": {
                "chanthoeun/filament-custom-forms": "1.3.4"
            }
        }
    },
    {
        "type": "path",
        "url": "../Tiny-Filament-Clone/packages/chanthoeun/filament-document-builder",
        "options": {
            "symlink": true,
            "versions": {
                "chanthoeun/filament-document-builder": "1.3.8"
            }
        }
    }
]
```

នេះជាផ្នែកសម្រាប់បញ្ចូលក្នុង JSON object ដែលមានស្រាប់ មិនមែនជាឯកសារ `composer.json` ទាំងមូលទេ។ ត្រូវដាក់ comma ឱ្យត្រឹមត្រូវរវាង properties។ លេខ version ទាំងនេះជា local aliases មិនមែនជា release tags របស់ repository នេះទេ។

```powershell
cd C:\xampp\htdocs\my-project
composer validate
composer require "chanthoeun/filament-custom-forms:~1.3" "chanthoeun/filament-document-builder:~1.3"
```

### 6. Publish configuration និង migrations

```powershell
php artisan vendor:publish --tag=filament-custom-forms-config
php artisan vendor:publish --tag=filament-custom-forms-migrations
php artisan vendor:publish --tag=filament-document-builder-config
php artisan vendor:publish --tag=filament-document-builder-migrations
php artisan migrate
php artisan storage:link
php artisan filament:assets
```

### 7. រៀបចំ plugin ឱ្យប្រើបានក្នុង project ទូទៅ

Custom Forms បច្ចុប្បន្នមានការហៅ classes ដូចជា `App\Support\ClosingDateWorkflow`, `App\Models\StudentApplicationReview`, `App\Filament\Support\RoleAccess` និង AMS workflow helpers ផ្សេងទៀត។ Laravel project ថ្មីមិនមាន classes ទាំងនេះទេ។

មុន register និងប្រើ Custom Forms ត្រូវកែ plugin ឱ្យការពឹងផ្អែកទាំងនេះក្លាយជា configurable integrations ឬ optional callbacks ហើយមាន default behavior ដែលមិនតម្រូវ AMS។ ការពិនិត្យតែ `class_exists()` មួយកន្លែងមិនគ្រប់គ្រាន់ទេ ប្រសិនបើ method ផ្សេងៗនៅតែហៅ classes ទាំងនោះ។ ត្រូវសាកល្បងបង្កើត form, submit entry, edit entry និង export PDF ក្នុង project ថ្មី។

ការកែឱ្យ standalone នេះមិនទាន់បានធ្វើក្នុង snapshot បច្ចុប្បន្នទេ។ កុំបង្កើត AMS classes ទទេដើម្បីលាក់ error ព្រោះអាចបាត់ validation ឬ permission behavior។ សូមមើល [AMS integration requirements](#ams-integration-requirements) សម្រាប់បញ្ជី dependencies។

### 8. Register plugin ក្នុង Filament panel

បន្ទាប់ពីរៀបចំ integration ដែលត្រូវការរួច បើក `app/Providers/Filament/AdminPanelProvider.php` ហើយបន្ថែម imports៖

```php
use Chanthoeun\FilamentCustomForms\CustomFormPlugin;
use Chanthoeun\FilamentDocumentBuilder\DocumentBuilderPlugin;
```

ក្នុង method `panel()` បន្ថែមទៅក្នុង chain ដែលមានស្រាប់៖

```php
->plugins([
    CustomFormPlugin::make(),
    DocumentBuilderPlugin::make(),
])
```

រក្សា configuration ផ្សេងៗដែល Filament បានបង្កើត។ បើមាន `plugins()` រួចហើយ ត្រូវបន្ថែម plugin ទាំងពីរទៅក្នុង array នោះ។ អាច register តែ Document Builder ប្រសិនបើមិនទាន់រៀបចំ Custom Forms integration រួច។

### 9. ដំណើរការ និងប្រើប្រាស់

```powershell
php artisan optimize:clear
php artisan serve
```

បើក `http://127.0.0.1:8000/admin` ហើយចូលដោយគណនីដែលបានបង្កើត។ ប្រសិនបើ port 8000 ត្រូវបានប្រើ អាចរត់ `php artisan serve --port=8003` ហើយបើក address តាម port នោះ។

នៅពេល integrations ដំណើរការរួច៖

1. ប្រើ **Custom Forms** ដើម្បីបង្កើតទម្រង់ និង fields។
2. Submit ទម្រង់ ហើយពិនិត្យទិន្នន័យក្នុង **Custom Form Entries**។
3. ប្រើ **Document Builder** ដើម្បីបង្កើត PDF template និងបញ្ចូល variables ដូចជា `{{ name }}`។
4. ផ្ដល់ data ដែលមាន key ត្រូវគ្នាទៅ renderer ឬភ្ជាប់ template ជាមួយ model តាមការកំណត់របស់ package។ ការបង្កើត template តែមួយមិនភ្ជាប់វាជាមួយ custom form ដោយស្វ័យប្រវត្តិទេ។

សម្រាប់ការកែ plugin ពេលក្រោយ ត្រូវកែ source ក្នុង `Tiny-Filament-Clone/packages/chanthoeun/`។ ជាមួយ `symlink: true` applications ដែលភ្ជាប់ទៅ clone ដូចគ្នានឹងឃើញការកែ source នោះ។ ការកែ file ក្នុង `app/` របស់ project មួយនៅតែជាការកែរបស់ project នោះផ្ទាល់។

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
