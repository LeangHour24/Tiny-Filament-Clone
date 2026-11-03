<?php

namespace Chanthoeun\FilamentCustomForms\Tests\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use Notifiable;

    protected $guarded = [];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
