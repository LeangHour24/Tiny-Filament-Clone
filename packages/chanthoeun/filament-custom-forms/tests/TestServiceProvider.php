<?php

namespace Chanthoeun\FilamentCustomForms\Tests;

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
use Chanthoeun\FilamentCustomForms\CustomFormPlugin;
use Illuminate\Support\ServiceProvider;
use Filament\Facades\Filament;

class TestServiceProvider extends ServiceProvider
{
    public function register()
    {
        Filament::registerPanel(
            fn (): Panel => Panel::make()
                ->default()
                ->id('admin')
                ->path('admin')
                ->colors([
                    'primary' => Color::Amber,
                ])
                ->widgets([])
                ->middleware([
                    EncryptCookies::class,
                    AddQueuedCookiesToResponse::class,
                    StartSession::class,
                    AuthenticateSession::class,
                    ShareErrorsFromSession::class,
                    VerifyCsrfToken::class,
                    SubstituteBindings::class,
                ])
                ->plugin(CustomFormPlugin::make()),
        );
    }
}
