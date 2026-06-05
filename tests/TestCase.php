<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $compiledViewPath = sys_get_temp_dir().'/prenoting-views';
        if (! is_dir($compiledViewPath)) {
            mkdir($compiledViewPath, 0777, true);
        }
        config(['view.compiled' => $compiledViewPath]);

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    protected function assignRole(User $user, string $role): User
    {
        Role::findOrCreate($role);
        $user->assignRole($role);

        return $user;
    }
}
