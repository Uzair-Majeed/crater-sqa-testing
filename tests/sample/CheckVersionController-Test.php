<?php
use Crater\Http\Controllers\V1\Admin\Update\CheckVersionController;
use Illuminate\Http\Request;

beforeEach(function () {
    // Reset Mockery and swap out Setting/Updater classes before each test
    Mockery::close();
    // Remove previously swapped facades
    if (app()->has('setting_mock')) {
        app()->forgetInstance('setting_mock');
    }
    if (app()->has('updater_mock')) {
        app()->forgetInstance('updater_mock');
    }
    // Unset any previously swapped facades
    if (class_exists('\Crater\Models\Setting') && isset($GLOBALS['__setting_original'])) {
        class_alias($GLOBALS['__setting_original'], '\Crater\Models\Setting', true);
        unset($GLOBALS['__setting_original']);
    }
    if (class_exists('\Crater\Space\Updater') && isset($GLOBALS['__updater_original'])) {
        class_alias($GLOBALS['__updater_original'], '\Crater\Space\Updater', true);
        unset($GLOBALS['__updater_original']);
    }
});

function assertJson(array $expected, string $responseContent): void
{
    $actual = json_decode($responseContent, true);
    // For flexibility: allow partial match on $expected if desired
    foreach ($expected as $key => $value) {
        expect($actual)->toHaveKey($key);
        expect($actual[$key])->toBe($value);
    }
}

test('it returns 401 if the user is not logged in', function () {
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn(null)->once(); // User is not authenticated

    $controller = new CheckVersionController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(401);
    assertJson([
        'success' => false,
        'message' => 'You are not allowed to update this app.'
    ], $response->getContent());
});

test('it returns 401 if the logged-in user is not an owner', function () {
    $user = Mockery::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
    $user->shouldReceive('isOwner')->andReturn(false)->once(); // User is authenticated but not an owner

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user)->once();

    $controller = new CheckVersionController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(401);
    assertJson([
        'success' => false,
        'message' => 'You are not allowed to update this app.'
    ], $response->getContent());
});

test('it successfully checks for updates when the user is an owner', function () {
    // Mock an authenticated owner user
    $user = Mockery::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
    $user->shouldReceive('isOwner')->andReturn(true)->once();

    // Mock the Request to return the owner user
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user)->once();

    // Mock the static call to Setting::getSetting
    // Swap out Setting class with a Mock class
    if (class_exists('\Crater\Models\Setting')) {
        $GLOBALS['__setting_original'] = '\Crater\Models\Setting';
        eval('namespace Crater\Models; class Setting { public static function getSetting($key) { return "1.0.0"; }}');
    }

    // Mock the static call to Updater::checkForUpdate
    $updateResult = ['new_version' => '1.0.1', 'status' => 'update_available', 'notes' => 'Some release notes.'];
    if (class_exists('\Crater\Space\Updater')) {
        $GLOBALS['__updater_original'] = '\Crater\Space\Updater';
        $jsonResult = json_encode($updateResult);
        eval('namespace Crater\Space; class Updater { public static function checkForUpdate($ver) { return json_decode(\'' . addslashes($jsonResult) . '\', true); }}');
    }

    $controller = new CheckVersionController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(200);
    assertJson($updateResult, $response->getContent());
});

test('it handles updater returning an error response gracefully', function () {
    // Mock an authenticated owner user
    $user = Mockery::mock(\Illuminate\Contracts\Auth\Authenticatable::class);
    $user->shouldReceive('isOwner')->andReturn(true)->once();

    // Mock the Request to return the owner user
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn($user)->once();

    // Mock the static call to Setting::getSetting
    if (class_exists('\Crater\Models\Setting')) {
        $GLOBALS['__setting_original'] = '\Crater\Models\Setting';
        eval('namespace Crater\Models; class Setting { public static function getSetting($key) { return "1.0.0"; }}');
    }

    // Mock the static call to Updater::checkForUpdate to simulate an error
    $errorResult = ['status' => 'error', 'message' => 'Could not connect to update server.'];
    if (class_exists('\Crater\Space\Updater')) {
        $GLOBALS['__updater_original'] = '\Crater\Space\Updater';
        $jsonResult = json_encode($errorResult);
        eval('namespace Crater\Space; class Updater { public static function checkForUpdate($ver) { return json_decode(\'' . addslashes($jsonResult) . '\', true); }}');
    }

    $controller = new CheckVersionController();
    $response = $controller->__invoke($request);

    expect($response->getStatusCode())->toBe(200); // The HTTP request itself succeeded, but the update check reported an internal error
    assertJson($errorResult, $response->getContent());
});

afterEach(function () {
    // Cleanup mocks and restore original classes (for test isolation)
    Mockery::close();
    if (class_exists('\Crater\Models\Setting') && isset($GLOBALS['__setting_original'])) {
        class_alias($GLOBALS['__setting_original'], '\Crater\Models\Setting', true);
        unset($GLOBALS['__setting_original']);
    }
    if (class_exists('\Crater\Space\Updater') && isset($GLOBALS['__updater_original'])) {
        class_alias($GLOBALS['__updater_original'], '\Crater\Space\Updater', true);
        unset($GLOBALS['__updater_original']);
    }
});