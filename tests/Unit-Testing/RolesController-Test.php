<?php

use Mockery as m;
use Crater\Http\Controllers\V1\Admin\Role\RolesController;
use Crater\Http\Requests\RoleRequest;
use Crater\Http\Resources\RoleResource;
use Crater\Models\User;
use Silber\Bouncer\Database\Role;
use Illuminate\Http\Request;
use Silber\Bouncer\BouncerFacade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Http\JsonResponse;

// Helper for mocking global respondJson if it's not defined or we need to control its output
if (! function_exists('respondJson')) {
    function respondJson($key, $message, $status = 400)
    {
        return ResponseFacade::json(['key' => $key, 'message' => $message], $status);
    }
}

beforeEach(function () {
    m::close();
});

function createRolesControllerMock()
{
    return m::mock(RolesController::class)->makePartial()
        ->shouldAllowMockingProtectedMethods();
}

test('index method authorizes and returns roles without filters', function () {
    $request = Request::create('/roles');
    $mockRole = m::mock(Role::class);
    $mockRole->name = 'Admin';
    $mockRole->id = 1;

    $mockQuery = m::mock();
    $mockQuery->shouldReceive('when')->times(2)->andReturnSelf();
    $mockQuery->shouldReceive('get')->once()->andReturn(collect([$mockRole]));

    Role::shouldReceive('when')->once()->andReturn($mockQuery);

    RoleResource::shouldReceive('collection')
        ->once()
        ->with(m::subsetOf(collect([$mockRole])))
        ->andReturn('roles_collection');

    $controller = createRolesControllerMock();
    $controller->shouldReceive('authorize')->once()->with('viewAny', Role::class);

    $response = $controller->index($request);

    expect($response)->toBe('roles_collection');
});

test('index method authorizes and returns roles with orderBy filter', function () {
    $request = Request::create('/roles?orderByField=name&orderBy=asc');
    $request->merge(['orderByField' => 'name', 'orderBy' => 'asc']);

    $mockRole = m::mock(Role::class);
    $mockRole->name = 'Admin';
    $mockRole->id = 1;

    $mockQuery = m::mock();
    $mockQuery->shouldReceive('orderBy')->once()->with('name', 'asc')->andReturnSelf();
    $mockQuery->shouldReceive('when')->times(2)->andReturnSelf();
    $mockQuery->shouldReceive('get')->once()->andReturn(collect([$mockRole]));

    Role::shouldReceive('when')->once()->andReturn($mockQuery);

    RoleResource::shouldReceive('collection')
        ->once()
        ->with(m::subsetOf(collect([$mockRole])))
        ->andReturn('ordered_roles_collection');

    $controller = createRolesControllerMock();
    $controller->shouldReceive('authorize')->once()->with('viewAny', Role::class);

    $response = $controller->index($request);

    expect($response)->toBe('ordered_roles_collection');
});

test('index method authorizes and returns roles with company_id filter', function () {
    $request = Request::create('/roles?company_id=1');
    $request->merge(['company_id' => 1]);

    $mockRole = m::mock(Role::class);
    $mockRole->name = 'Admin';
    $mockRole->id = 1;

    $mockQuery = m::mock();
    $mockQuery->shouldReceive('where')->once()->with('scope', 1)->andReturnSelf();
    $mockQuery->shouldReceive('when')->times(2)->andReturnSelf();
    $mockQuery->shouldReceive('get')->once()->andReturn(collect([$mockRole]));

    Role::shouldReceive('when')->once()->andReturn($mockQuery);

    RoleResource::shouldReceive('collection')
        ->once()
        ->with(m::subsetOf(collect([$mockRole])))
        ->andReturn('company_roles_collection');

    $controller = createRolesControllerMock();
    $controller->shouldReceive('authorize')->once()->with('viewAny', Role::class);

    $response = $controller->index($request);

    expect($response)->toBe('company_roles_collection');
});

test('index method authorizes and returns empty collection when no roles found', function () {
    $request = Request::create('/roles');

    $mockQuery = m::mock();
    $mockQuery->shouldReceive('when')->times(2)->andReturnSelf();
    $mockQuery->shouldReceive('get')->once()->andReturn(collect([]));

    Role::shouldReceive('when')->once()->andReturn($mockQuery);

    RoleResource::shouldReceive('collection')
        ->once()
        ->with(m::subsetOf(collect([])))
        ->andReturn('empty_roles_collection');

    $controller = createRolesControllerMock();
    $controller->shouldReceive('authorize')->once()->with('viewAny', Role::class);

    $response = $controller->index($request);

    expect($response)->toBe('empty_roles_collection');
});

test('store method authorizes, creates role, syncs abilities and returns resource', function () {
    $request = m::mock(RoleRequest::class);
    $payload = ['name' => 'New Role', 'description' => 'A new role'];
    $abilities = [
        ['ability' => 'view-users', 'model' => 'User'],
        ['ability' => 'create-posts', 'model' => 'Post'],
    ];
    $request->shouldReceive('getRolePayload')->once()->andReturn($payload);
    $request->abilities = $abilities;

    $mockRole = m::mock(Role::class);
    $mockRole->name = 'New Role';
    $mockRole->id = 5;
    Role::shouldReceive('create')->once()->with($payload)->andReturn($mockRole);

    RoleResource::shouldReceive('__construct')
        ->once()
        ->with(m::subsetOf($mockRole))
        ->andReturnUsing(fn ($role) => new RoleResource($role));

    Config::shouldReceive('get')->with('abilities.abilities')->andReturn([
        ['ability' => 'view-users', 'model' => 'User'],
        ['ability' => 'create-posts', 'model' => 'Post'],
        ['ability' => 'delete-posts', 'model' => 'Post'],
    ]);

    BouncerFacade::shouldReceive('allow')->once()->with($mockRole)->andReturnSelf();
    BouncerFacade::shouldReceive('to')->once()->with('view-users', 'User')->andReturnNull();
    BouncerFacade::shouldReceive('allow')->once()->with($mockRole)->andReturnSelf();
    BouncerFacade::shouldReceive('to')->once()->with('create-posts', 'Post')->andReturnNull();
    BouncerFacade::shouldReceive('disallow')->once()->with($mockRole)->andReturnSelf();
    BouncerFacade::shouldReceive('to')->once()->with('delete-posts', 'Post')->andReturnNull();

    $controller = createRolesControllerMock();
    $controller->shouldReceive('authorize')->once()->with('create', Role::class);

    $response = $controller->store($request);

    expect($response)->toBeInstanceOf(RoleResource::class);
});

test('show method authorizes and returns specified role resource', function () {
    $mockRole = m::mock(Role::class);
    $mockRole->name = 'Editor';
    $mockRole->id = 2;

    RoleResource::shouldReceive('__construct')
        ->once()
        ->with(m::subsetOf($mockRole))
        ->andReturnUsing(fn ($role) => new RoleResource($role));

    $controller = createRolesControllerMock();
    $controller->shouldReceive('authorize')->once()->with('view', $mockRole);

    $response = $controller->show($mockRole);

    expect($response)->toBeInstanceOf(RoleResource::class);
});

test('update method authorizes, updates role, syncs abilities and returns resource', function () {
    $request = m::mock(RoleRequest::class);
    $payload = ['name' => 'Updated Role Name', 'description' => 'Updated desc'];
    $abilities = [
        ['ability' => 'edit-users', 'model' => 'User'],
    ];
    $request->shouldReceive('getRolePayload')->once()->andReturn($payload);
    $request->abilities = $abilities;

    $mockRole = m::mock(Role::class);
    $mockRole->name = 'Original Name';
    $mockRole->shouldReceive('update')->once()->with($payload)->andReturnTrue();

    RoleResource::shouldReceive('__construct')
        ->once()
        ->with(m::subsetOf($mockRole))
        ->andReturnUsing(fn ($role) => new RoleResource($role));

    Config::shouldReceive('get')->with('abilities.abilities')->andReturn([
        ['ability' => 'edit-users', 'model' => 'User'],
        ['ability' => 'view-posts', 'model' => 'Post'],
    ]);

    BouncerFacade::shouldReceive('allow')->once()->with($mockRole)->andReturnSelf();
    BouncerFacade::shouldReceive('to')->once()->with('edit-users', 'User')->andReturnNull();
    BouncerFacade::shouldReceive('disallow')->once()->with($mockRole)->andReturnSelf();
    BouncerFacade::shouldReceive('to')->once()->with('view-posts', 'Post')->andReturnNull();

    $controller = createRolesControllerMock();
    $controller->shouldReceive('authorize')->once()->with('update', $mockRole);

    $response = $controller->update($request, $mockRole);

    expect($response)->toBeInstanceOf(RoleResource::class);
});

test('destroy method authorizes and successfully deletes role when no users attached', function () {
    $mockRole = m::mock(Role::class);
    $mockRole->name = 'Deletable Role';
    $mockRole->shouldReceive('delete')->once()->andReturnTrue();

    User::shouldReceive('whereIs')->once()->with($mockRole->name)->andReturnSelf();
    User::shouldReceive('get')->once()->andReturn(collect([]));

    ResponseFacade::shouldReceive('json')
        ->once()
        ->with(['success' => true])
        ->andReturn(m::mock(JsonResponse::class));

    $controller = createRolesControllerMock();
    $controller->shouldReceive('authorize')->once()->with('delete', $mockRole);

    $response = $controller->destroy($mockRole);

    expect($response)->toBeInstanceOf(JsonResponse::class);
});

test('destroy method authorizes and prevents deletion if role is attached to users', function () {
    $mockRole = m::mock(Role::class);
    $mockRole->name = 'Attached Role';

    $mockUser = m::mock(User::class);
    User::shouldReceive('whereIs')->once()->with($mockRole->name)->andReturnSelf();
    User::shouldReceive('get')->once()->andReturn(collect([$mockUser]));

    ResponseFacade::shouldReceive('json')
        ->once()
        ->with(['key' => 'role_attached_to_users', 'message' => 'Roles Attached to user'], 400)
        ->andReturn(m::mock(JsonResponse::class));

    $controller = createRolesControllerMock();
    $controller->shouldReceive('authorize')->once()->with('delete', $mockRole);

    $response = $controller->destroy($mockRole);

    expect($response)->toBeInstanceOf(JsonResponse::class);
});

test('syncAbilities method allows and disallows abilities based on request', function () {
    $controller = new RolesController();

    $mockRole = m::mock(Role::class);

    $request = m::mock(RoleRequest::class);
    $request->abilities = [
        ['ability' => 'view-items', 'model' => 'Item'],
        ['ability' => 'edit-items', 'model' => 'Item'],
    ];

    Config::shouldReceive('get')->with('abilities.abilities')->andReturn([
        ['ability' => 'view-items', 'model' => 'Item'],
        ['ability' => 'create-items', 'model' => 'Item'],
        ['ability' => 'edit-items', 'model' => 'Item'],
        ['ability' => 'delete-items', 'model' => 'Item'],
    ]);

    BouncerFacade::shouldReceive('allow')->once()->with($mockRole)->andReturnSelf();
    BouncerFacade::shouldReceive('to')->once()->with('view-items', 'Item')->andReturnNull();

    BouncerFacade::shouldReceive('disallow')->once()->with($mockRole)->andReturnSelf();
    BouncerFacade::shouldReceive('to')->once()->with('create-items', 'Item')->andReturnNull();

    BouncerFacade::shouldReceive('allow')->once()->with($mockRole)->andReturnSelf();
    BouncerFacade::shouldReceive('to')->once()->with('edit-items', 'Item')->andReturnNull();

    BouncerFacade::shouldReceive('disallow')->once()->with($mockRole)->andReturnSelf();
    BouncerFacade::shouldReceive('to')->once()->with('delete-items', 'Item')->andReturnNull();

    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('syncAbilities');
    $method->setAccessible(true);

    $result = $method->invokeArgs($controller, [$request, $mockRole]);

    expect($result)->toBeTrue();
});

test('syncAbilities method disallows all abilities if none are in request', function () {
    $controller = new RolesController();

    $mockRole = m::mock(Role::class);

    $request = m::mock(RoleRequest::class);
    $request->abilities = [];

    Config::shouldReceive('get')->with('abilities.abilities')->andReturn([
        ['ability' => 'view-items', 'model' => 'Item'],
        ['ability' => 'create-items', 'model' => 'Item'],
    ]);

    BouncerFacade::shouldReceive('disallow')->once()->with($mockRole)->andReturnSelf();
    BouncerFacade::shouldReceive('to')->once()->with('view-items', 'Item')->andReturnNull();

    BouncerFacade::shouldReceive('disallow')->once()->with($mockRole)->andReturnSelf();
    BouncerFacade::shouldReceive('to')->once()->with('create-items', 'Item')->andReturnNull();

    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('syncAbilities');
    $method->setAccessible(true);

    $result = $method->invokeArgs($controller, [$request, $mockRole]);

    expect($result)->toBeTrue();
});

test('syncAbilities method allows all abilities if all are in request', function () {
    $controller = new RolesController();

    $mockRole = m::mock(Role::class);

    $request = m::mock(RoleRequest::class);
    $request->abilities = [
        ['ability' => 'view-items', 'model' => 'Item'],
        ['ability' => 'create-items', 'model' => 'Item'],
    ];

    Config::shouldReceive('get')->with('abilities.abilities')->andReturn([
        ['ability' => 'view-items', 'model' => 'Item'],
        ['ability' => 'create-items', 'model' => 'Item'],
    ]);

    BouncerFacade::shouldReceive('allow')->once()->with($mockRole)->andReturnSelf();
    BouncerFacade::shouldReceive('to')->once()->with('view-items', 'Item')->andReturnNull();

    BouncerFacade::shouldReceive('allow')->once()->with($mockRole)->andReturnSelf();
    BouncerFacade::shouldReceive('to')->once()->with('create-items', 'Item')->andReturnNull();

    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('syncAbilities');
    $method->setAccessible(true);

    $result = $method->invokeArgs($controller, [$request, $mockRole]);

    expect($result)->toBeTrue();
});

test('syncAbilities method handles empty abilities configuration', function () {
    $controller = new RolesController();

    $mockRole = m::mock(Role::class);

    $request = m::mock(RoleRequest::class);
    $request->abilities = [
        ['ability' => 'some-ability', 'model' => 'SomeModel'],
    ];

    Config::shouldReceive('get')->with('abilities.abilities')->andReturn([]);

    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('syncAbilities');
    $method->setAccessible(true);

    $result = $method->invokeArgs($controller, [$request, $mockRole]);

    expect($result)->toBeTrue();
});
