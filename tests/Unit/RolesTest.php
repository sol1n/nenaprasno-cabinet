<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Role;
use App\User;
use App\Services\RoleManager;
use App\Exceptions\Role\RoleNotFoundException;

class RolesTest extends TestCase
{
    private $roleManager;

    public function setUp()
    {
        parent::setUp();

        $user = User::Login([
            'login' => env('TEST_LOGIN'),
            'password' => env('TEST_PASSWORD')
        ], false);

        $this->withSession(['session-token' => $user->token()]);
        $this->roleManager = app(RoleManager::class);
    }

    public function test_can_create_role()
    {
        $data = ['id' => 'TestsTempRole'];
        $role = $this->roleManager->create($data);

        $this->assertInstanceOf(Role::Class, $role);
        $this->assertEquals($role->id, $data['id']);

        $this->roleManager->delete($role->id);
    }

    public function test_can_delete_role()
    {
        $data = ['id' => 'TestsTempRole'];
        $role = $this->roleManager->create($data);

        $this->roleManager->delete($role->id);

        $this->expectException(RoleNotFoundException::class);

        $this->roleManager->find($data['id']);
    }

    public function test_can_create_role_with_rights()
    {
        $data = ['id' => 'TestsTempRole', 'rights' => ['users.GET' => true]];
        $role = $this->roleManager->create($data);

        $this->assertInstanceOf(Role::Class, $role);
        $this->assertEquals(count($role->rights['adds']), 1);
        $this->assertEquals($role->rights['adds']['users.GET'], true);
        $this->roleManager->delete($role->id);
    }

    public function test_can_update_role()
    {
        $data = ['id' => 'TestsTempRole1'];
        $role = $this->roleManager->create($data);
        $role = $this->roleManager->save($role->id, ['baseRoleId' => 'Anonymous']);

        $this->assertEquals($role->baseRoleId, 'Anonymous');

        $this->roleManager->delete($role->id);
    }

    public function test_can_add_rights()
    {
        $data = ['id' => 'TestsTempRole', 'rights' => ['users.GET' => true]];
        $role = $this->roleManager->create($data);

        $data['rights']['users.POST'] = true;

        $role = $this->roleManager->save($role->id, $data);
        $this->assertEquals(count($role->rights['adds']), 2);
        $this->assertEquals($role->rights['adds']['users.POST'], true);
        $this->roleManager->delete($role->id);
    }

    public function test_can_remove_rights()
    {
        $data = ['id' => 'TestsTempRole', 'rights' => ['users.GET' => true, 'users.POST' => true]];
        $role = $this->roleManager->create($data);

        unset($data['rights']['users.POST']);

        $role = $this->roleManager->save($role->id, $data);
        $this->assertEquals(count($role->rights['adds']), 1);
        $this->roleManager->delete($role->id);
    }
}
