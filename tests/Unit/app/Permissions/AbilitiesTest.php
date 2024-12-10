<?php

namespace Tests\Unit\App\Permissions;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Permissions\Abilities;

class AbilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_returnsClientAbilitiesForClientRole()
    {
        $user = new User(['role' => 'client']);
        
        $expectedAbilities = [
            'client:create',
            'client:replace',
            'client:update',
            'client:delete',
            'client:only',
        ];

        $this->assertEquals($expectedAbilities, Abilities::getAbilities($user));
    }

    public function test_returnsAdminAbilitiesForAdminRole()
    {
        $user = new User(['role' => 'admin']);
        
        $expectedAbilities = [
            'admin:create',
            'admin:replace',
            'admin:update',
            'admin:delete',
            'admin:only',
        ];

        $this->assertEquals($expectedAbilities, Abilities::getAbilities($user));
    }

    public function test_returnsUserAbilitiesForUserRole()
    {
        $user = new User(['role' => 'user']);
        
        $expectedAbilities = [
            'user:create',
            'user:replace',
            'user:update',
            'user:delete',
            'user:only',
        ];

        $this->assertEquals($expectedAbilities, Abilities::getAbilities($user));
    }

    public function test_returns_null_for_invalid_role()
    {
        $user = new User(['role' => 'invalid_role']);
        
        $this->assertNull(Abilities::getAbilities($user));
    }
}