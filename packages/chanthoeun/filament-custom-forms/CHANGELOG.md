# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
 
 ## [v1.0.8] - 2026-03-04
 ### Fixed
 - Fixed translation fallback logic in `ListCustomFormEntries` to correctly show database names when translations are missing.
 - Added missing `names` array to `fcf.php` locale files for dynamic form name translations.
 
 ## [v1.0.7] - 2026-03-04
 ### Fixed
 - Fixed `Class "App\Models\CustomForm" not found` by removing hardcoded project-level namespaces.
 - Implemented `CustomFormEntryExport` and `CustomFormEntrySqlExport` within the plugin to make exports standalone.
 - Removed hardcoded `PrintAction` from `EditCustomFormEntry` header actions.
 
 ## [v1.0.6] - 2026-03-04
 ### Fixed
 - Added `navigationOpsGroup()` and `getNavigationOpsGroup()` back to `CustomFormPlugin` for backward compatibility.
 
 ## [v1.0.5] - 2026-03-04
 ### Fixed
 - Fixed `Call to undefined method CustomFormPlugin::getNavigationOpsGroup()` in `CustomFormEntryResource.php`.
 
 ## [v1.0.4] - 2026-03-04
 ### Changed
 - Consolidated all translation files into a single `fcf.php` per locale.
 - Shortened translation keys from `filament-custom-forms::filament_custom_form.*` to `filament-custom-forms::fcf.*`.
 - Renamed "Form Operation" to "Form Entry" throughout the plugin for better clarity.
 - Updated `README.md` with a simplified 5-step installation guide.
 
 ### Added
 - Added missing `wizard` block translation key.
 - Made navigation group name customizable in the plugin configuration.

## [v1.0.3] - 2026-03-04
### Fixed
- Updated `filament/schemas` version constraint to `^4.0|^5.0` to resolve dependency resolution errors.


## [1.0.2] - 2026-03-04
### Fixed
- Changed table action namespaces from `Filament\Tables\Actions` to `Filament\Actions` (Filament v5 compatibility).
- Added `filament-custom-forms::` prefix to all translation calls for package context.
- Added missing translation keys (`access_denied`, `upgrade_required`) and `tenant.php` (later removed).
- Removed hardcoded `\App\Enums\Currency` dependency; money fields now default to USD.

### Removed
- Removed multi-tenancy (tenant) logic and feature checks.
- Deleted `src/Enums/Currency.php`.

## [1.0.0] - 2026-03-04
### Added
- Initial release of Chanthoeun Custom Forms plugin.
- Support for dynamic form building and entry management in Filament.
- Khmer and English translation support.
- Published migrations as stubs for fresh project integration.
