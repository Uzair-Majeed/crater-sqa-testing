<?php

use Crater\Http\Controllers\V1\Admin\Auth\ConfirmPasswordController;
use Crater\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\ConfirmsPasswords;

test('ConfirmPasswordController can be instantiated', function () {
    $controller = new ConfirmPasswordController();
    expect($controller)->toBeInstanceOf(ConfirmPasswordController::class);
});

test('ConfirmPasswordController uses the ConfirmsPasswords trait', function () {
    // White-box test to ensure the trait is correctly applied to the class.
    expect(class_uses(ConfirmPasswordController::class))->toContain(ConfirmsPasswords::class);
});

test('ConfirmPasswordController has the correct redirectTo property value', function () {
    // White-box test to ensure the protected $redirectTo property is set as expected.
    $controller = new ConfirmPasswordController();
    expect($controller->redirectTo)->toBe(RouteServiceProvider::HOME);
});

test('ConfirmPasswordController applies the auth middleware in its constructor', function () {
    // To white-box test the constructor's internal call to the `middleware` method,
    // we create an anonymous class that extends `ConfirmPasswordController` and
    // overrides its `middleware` method to capture calls.
    $testController = new class extends ConfirmPasswordController {
        public array $middlewareCalls = [];

        // Override the base `middleware` method to act as a spy.
        // This allows us to observe what arguments it was called with.
        public function middleware($middleware, array $options = [])
        {
            $this->middlewareCalls[] = ['middleware' => $middleware, 'options' => $options];
            return $this; // Important for potential method chaining
        }
    };

    // Instantiate our anonymous test class. This will trigger the original
    // ConfirmPasswordController's constructor, which in turn calls our
    // overridden `middleware` method.
    $controllerInstance = new $testController();

    // Assert that the `middleware` method was called exactly once during construction.
    expect($controllerInstance->middlewareCalls)->toHaveCount(1);

    // Assert that it was called with the 'auth' middleware string.
    expect($controllerInstance->middlewareCalls[0]['middleware'])->toBe('auth');

    // Assert that no additional options were passed.
    expect($controllerInstance->middlewareCalls[0]['options'])->toBe([]);
});
