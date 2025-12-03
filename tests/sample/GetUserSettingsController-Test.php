```php
<?php

use Crater\Http\Controllers\V1\Admin\Settings\GetUserSettingsController;
use Crater\Http\Requests\GetSettingsRequest;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;

// Clear Mockery mocks after each test to prevent test pollution
uses()->group('GetUserSettingsController')->afterEach(fn () => Mockery::close());

test('it returns user settings for a single requested key', function () {
    // Arrange
    $controller = new GetUserSettingsController();

    $mockUser = Mockery::mock(Authenticatable::class);
    $mockRequest = Mockery::mock(GetSettingsRequest::class);

    $expectedSettings = ['theme' => 'dark'];
    $requestedKey = 'theme';

    // Configure mocks
    $mockRequest->shouldReceive('user')->once()->andReturn($mockUser);
    $mockRequest->shouldReceive('settings')->once()->andReturn($requestedKey);
    // The controller might access request parameters via magic properties (e.g., $request->settings),
    // which internally calls $request->all(). We need to mock 'all()' to prevent Mockery exceptions.
    $mockRequest->shouldReceive('all')->once()->andReturn(['settings' => $requestedKey]);


    // Assume the User model implements a getSettings method
    $mockUser->shouldReceive('getSettings')
        ->with($requestedKey)
        ->once()
        ->andReturn($expectedSettings);

    // Act
    $response = $controller($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    // Wrap the JsonResponse in TestResponse for convenient assertions
    TestResponse::fromBaseResponse($response)
        ->assertStatus(200)
        ->assertJson($expectedSettings);
});

test('it returns user settings for multiple requested keys', function () {
    // Arrange
    $controller = new GetUserSettingsController();

    $mockUser = Mockery::mock(Authenticatable::class);
    $mockRequest = Mockery::mock(GetSettingsRequest::class);

    $expectedSettings = [
        'theme' => 'light',
        'notifications' => true,
    ];
    $requestedKeys = ['theme', 'notifications'];

    // Configure mocks
    $mockRequest->shouldReceive('user')->once()->andReturn($mockUser);
    $mockRequest->shouldReceive('settings')->once()->andReturn($requestedKeys);
    // Mock 'all()' to provide the 'settings' parameter as expected by the controller's internal logic.
    $mockRequest->shouldReceive('all')->once()->andReturn(['settings' => $requestedKeys]);


    $mockUser->shouldReceive('getSettings')
        ->with($requestedKeys)
        ->once()
        ->andReturn($expectedSettings);

    // Act
    $response = $controller($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    TestResponse::fromBaseResponse($response)
        ->assertStatus(200)
        ->assertJson($expectedSettings);
});

test('it returns all user settings when no specific keys are requested', function () {
    // Arrange
    $controller = new GetUserSettingsController();

    $mockUser = Mockery::mock(Authenticatable::class);
    $mockRequest = Mockery::mock(GetSettingsRequest::class);

    $allSettings = [
        'theme' => 'dark',
        'notifications' => false,
        'locale' => 'en',
        'timezone' => 'UTC'
    ];

    // Configure mocks
    $mockRequest->shouldReceive('user')->once()->andReturn($mockUser);
    // Simulate no 'settings' parameter in the request (e.g., it defaults to null)
    $mockRequest->shouldReceive('settings')->once()->andReturn(null);
    // Mock 'all()' to reflect that no specific 'settings' parameter was provided.
    $mockRequest->shouldReceive('all')->once()->andReturn(['settings' => null]);


    // Assume getSettings returns all settings when called with null
    $mockUser->shouldReceive('getSettings')
        ->with(null)
        ->once()
        ->andReturn($allSettings);

    // Act
    $response = $controller($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    TestResponse::fromBaseResponse($response)
        ->assertStatus(200)
        ->assertJson($allSettings);
});

test('it returns empty json for a non-existent single setting key', function () {
    // Arrange
    $controller = new GetUserSettingsController();

    $mockUser = Mockery::mock(Authenticatable::class);
    $mockRequest = Mockery::mock(GetSettingsRequest::class);

    $nonExistentKey = 'nonExistentSetting';

    // Configure mocks
    $mockRequest->shouldReceive('user')->once()->andReturn($mockUser);
    $mockRequest->shouldReceive('settings')->once()->andReturn($nonExistentKey);
    // Mock 'all()' to provide the non-existent key for 'settings'.
    $mockRequest->shouldReceive('all')->once()->andReturn(['settings' => $nonExistentKey]);


    // Assume getSettings returns an empty array for a non-existent single key
    $mockUser->shouldReceive('getSettings')
        ->with($nonExistentKey)
        ->once()
        ->andReturn([]);

    // Act
    $response = $controller($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    TestResponse::fromBaseResponse($response)
        ->assertStatus(200)
        ->assertJson([]); // Assumes an empty JSON object is returned
});

test('it returns partial data for multiple requested keys where some are non-existent', function () {
    // Arrange
    $controller = new GetUserSettingsController();

    $mockUser = Mockery::mock(Authenticatable::class);
    $mockRequest = Mockery::mock(GetSettingsRequest::class);

    $requestedKeys = ['theme', 'nonExistentSetting', 'notifications'];
    // Assume getSettings omits non-existent keys from the returned array
    $partialSettings = [
        'theme' => 'system',
        'notifications' => false,
    ];

    // Configure mocks
    $mockRequest->shouldReceive('user')->once()->andReturn($mockUser);
    $mockRequest->shouldReceive('settings')->once()->andReturn($requestedKeys);
    // Mock 'all()' to provide the mixed list of keys for 'settings'.
    $mockRequest->shouldReceive('all')->once()->andReturn(['settings' => $requestedKeys]);


    $mockUser->shouldReceive('getSettings')
        ->with($requestedKeys)
        ->once()
        ->andReturn($partialSettings);

    // Act
    $response = $controller($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    TestResponse::fromBaseResponse($response)
        ->assertStatus(200)
        ->assertJson($partialSettings);
});

test('it returns empty json when the user has no settings at all', function () {
    // Arrange
    $controller = new GetUserSettingsController();

    $mockUser = Mockery::mock(Authenticatable::class);
    $mockRequest = Mockery::mock(GetSettingsRequest::class);

    // Configure mocks
    $mockRequest->shouldReceive('user')->once()->andReturn($mockUser);
    // Request all settings
    $mockRequest->shouldReceive('settings')->once()->andReturn(null);
    // Mock 'all()' to indicate no specific 'settings' parameter.
    $mockRequest->shouldReceive('all')->once()->andReturn(['settings' => null]);


    // User's getSettings returns an empty array, indicating no settings
    $mockUser->shouldReceive('getSettings')
        ->with(null)
        ->once()
        ->andReturn([]);

    // Act
    $response = $controller($mockRequest);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    TestResponse::fromBaseResponse($response)
        ->assertStatus(200)
        ->assertJson([]);
});
```