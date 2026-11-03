<?php

namespace Chanthoeun\FilamentCustomForms\Tests;

use Chanthoeun\FilamentCustomForms\CustomFormServiceProvider;
use Filament\FilamentServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Livewire\LivewireServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Chanthoeun\\FilamentCustomForms\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );

        $this->setUpDatabase();

        session()->put('errors', new \Illuminate\Support\ViewErrorBag);

        $user = \Chanthoeun\FilamentCustomForms\Tests\Models\User::create([
            'name' => 'Test User',
            'email' => 'test' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($user);

        $panel = \Filament\Facades\Filament::getPanel('test');
        \Filament\Facades\Filament::setCurrentPanel($panel);
    }

    protected function setUpDatabase()
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('app.key', 'base64:Hupx3yAySly9Xiq9fR8P5lK3fS72xXpW4yL1k9xYvG8=');
        
        config()->set('auth.providers.users.model', \Chanthoeun\FilamentCustomForms\Tests\Models\User::class);
        config()->set('session.driver', 'array');

        $app['router']->aliasMiddleware('auth', \Illuminate\Auth\Middleware\Authenticate::class);

        $app['router']->pushMiddlewareToGroup('web', \Illuminate\Session\Middleware\StartSession::class);
        $app['router']->pushMiddlewareToGroup('web', \Illuminate\View\Middleware\ShareErrorsFromSession::class);
    }

    protected function getPackageProviders($app)
    {
        return [
            ActionsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            CustomFormServiceProvider::class,
            TestPanelProvider::class,
        ];
    }
}
