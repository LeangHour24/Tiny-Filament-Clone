<?php

namespace Chanthoeun\FilamentCustomForms\Tests;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\CustomFormResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableDeviceIdentifierCookie;
use Filament\Http\Middleware\InertiaRequests;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('test')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->resources([
                CustomFormResource::class,
                CustomFormEntryResource::class,
            ])
            ->authGuard('web')
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
