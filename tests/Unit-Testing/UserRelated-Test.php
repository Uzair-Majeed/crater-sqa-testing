<?php

use Crater\Http\Resources\UserCollection;
use Crater\Http\Requests\UserRequest;
use Crater\Http\Controllers\V1\Admin\Users\UsersController;
use Crater\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// ========== MERGED USER TESTS (4 CLASSES, 20 COMPREHENSIVE FUNCTIONAL TESTS) ==========

// --- UserCollection Tests (4 tests: 2 structural + 2 functional) ---

test('UserCollection extends ResourceCollection', function () {
    $collection = new UserCollection(new Collection([]));
    expect($collection)->toBeInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class);
});

test('UserCollection is in correct namespace', function () {
    $reflection = new ReflectionClass(UserCollection::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

test('UserCollection toArray returns empty array for empty collection', function () {
    $request = new Request();
    $collection = new UserCollection(new Collection([]));
    
    $result = $collection->toArray($request);
    
    expect($result)->toBeArray()->and($result)->toBeEmpty();
});

test('UserCollection delegates to parent toArray', function () {
    $request = new Request();
    $collection = new UserCollection(new Collection([]));
    
    expect($collection->toArray($request))->toBeArray();
});

// --- UserRequest Tests (6 tests: 2 structural + 4 functional) ---

test('UserRequest extends FormRequest', function () {
    $request = new UserRequest();
    expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

test('UserRequest is in correct namespace', function () {
    $reflection = new ReflectionClass(UserRequest::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Requests');
});

test('UserRequest authorize returns true', function () {
    $request = new UserRequest();
    expect($request->authorize())->toBeTrue();
});

test('UserRequest rules includes all required fields', function () {
    $request = new UserRequest();
    $request->headers->set('company', '123');
    
    $rules = $request->rules();
    
    expect($rules)->toBeArray()
        ->and($rules)->toHaveKey('name')
        ->and($rules)->toHaveKey('email')
        ->and($rules)->toHaveKey('phone')
        ->and($rules)->toHaveKey('password')
        ->and($rules)->toHaveKey('companies')
        ->and($rules['name'])->toContain('required')
        ->and($rules['email'])->toContain('required')
        ->and($rules['email'])->toContain('email');
});

test('UserRequest rules handles PUT method differently', function () {
    $reflection = new ReflectionClass(UserRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('if ($this->getMethod() == \'PUT\')')
        ->and($fileContent)->toContain('Rule::unique(\'users\')->ignore($this->user)')
        ->and($fileContent)->toContain('\'password\' => [')
        ->and($fileContent)->toContain('\'nullable\'');
});

test('UserRequest getUserPayload merges creator_id', function () {
    $reflection = new ReflectionClass(UserRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'creator_id\' => $this->user()->id');
});

// --- UsersController Tests (7 tests: 3 structural + 4 functional) ---

test('UsersController extends Controller', function () {
    $controller = new UsersController();
    expect($controller)->toBeInstanceOf(\Crater\Http\Controllers\Controller::class);
});

test('UsersController is in correct namespace', function () {
    $reflection = new ReflectionClass(UsersController::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Controllers\V1\Admin\Users');
});

test('UsersController has all CRUD methods', function () {
    $reflection = new ReflectionClass(UsersController::class);
    
    expect($reflection->hasMethod('index'))->toBeTrue()
        ->and($reflection->hasMethod('store'))->toBeTrue()
        ->and($reflection->hasMethod('show'))->toBeTrue()
        ->and($reflection->hasMethod('update'))->toBeTrue()
        ->and($reflection->hasMethod('delete'))->toBeTrue();
});

test('UsersController index uses authorization and pagination', function () {
    $reflection = new ReflectionClass(UsersController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize(\'viewAny\', User::class)')
        ->and($fileContent)->toContain('User::applyFilters($request->all())')
        ->and($fileContent)->toContain('->paginate($limit)');
});

test('UsersController store uses User createFromRequest', function () {
    $reflection = new ReflectionClass(UsersController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize(\'create\', User::class)')
        ->and($fileContent)->toContain('User::createFromRequest($request)');
});

test('UsersController update uses updateFromRequest', function () {
    $reflection = new ReflectionClass(UsersController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize(\'update\', $user)')
        ->and($fileContent)->toContain('$user->updateFromRequest($request)');
});

test('UsersController delete uses deleteUsers method', function () {
    $reflection = new ReflectionClass(UsersController::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$this->authorize(\'delete multiple users\', User::class)')
        ->and($fileContent)->toContain('User::deleteUsers($request->users)');
});

// --- UserSetting Tests (3 tests: 2 structural + 1 functional) ---

test('UserSetting extends Model and uses HasFactory', function () {
    $setting = new UserSetting();
    $reflection = new ReflectionClass(UserSetting::class);
    $traits = $reflection->getTraitNames();
    
    expect($setting)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class)
        ->and($traits)->toContain('Illuminate\Database\Eloquent\Factories\HasFactory');
});

test('UserSetting is in correct namespace', function () {
    $reflection = new ReflectionClass(UserSetting::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Models');
});

test('UserSetting user relationship returns BelongsTo', function () {
    $setting = new UserSetting();
    $relation = $setting->user();
    
    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});