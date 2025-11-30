<?php

uses(\Mockery::class);
use Illuminate\Http\Request;
use Crater\Http\Controllers\V1\Installation\LoginController;

beforeEach(function () {
    Mockery::close(); // Clean up Mockery expectations after each test
});

test('it successfully logs in a super admin user with an associated company', function () {
    // Mock a company object
    $mockCompany = (object)['id' => 101, 'name' => 'Test Company Inc.', 'uuid' => 'company-uuid-1'];

    // Mock a user object, including its companies relationship
    $mockUser = Mockery::mock(\Crater\Models\User::class);
    $mockUser->id = 1;
    $mockUser->name = 'Super Admin User';
    $mockUser->role = 'super admin';
    $mockUser->email = 'superadmin@example.com';
    $mockUser->setAppends(['company']); // Simulate the appended company attribute if it exists, for response serialization

    // Mock the companies relationship method call chain: $user->companies()->first()
    $mockUser->shouldReceive('companies')
             ->once()
             ->andReturn(Mockery::mock()
                 ->shouldReceive('first')
                 ->once()
                 ->andReturn($mockCompany) // User has an associated company
                 ->getMock() // Get the mock object for the relationship builder
             );

    // Mock the static call to User::where()->first()
    Mockery::mock('alias:Crater\Models\User')
        ->shouldReceive('where')
        ->with('role', 'super admin')
        ->once()
        ->andReturnSelf() // Allows chaining of `first()`
        ->shouldReceive('first')
        ->once()
        ->andReturn($mockUser);

    // Mock the Auth facade's login method
    Mockery::mock('alias:Auth')
        ->shouldReceive('login')
        ->once()
        ->with(Mockery::on(function ($arg) use ($mockUser) {
            // Verify that the login method is called with the mocked user object
            return $arg->id === $mockUser->id && $arg->email === $mockUser->email;
        }));

    // Instantiate the controller
    $controller = new LoginController();

    // Create a dummy request object
    $request = new Request();

    // Invoke the controller
    $response = $controller($request);

    // Assertions
    expect($response->getStatusCode())->toBe(200);
    $responseData = json_decode($response->getContent(), true);

    expect($responseData['success'])->toBeTrue();
    expect($responseData['user']['id'])->toBe($mockUser->id);
    expect($responseData['user']['name'])->toBe($mockUser->name);
    expect($responseData['user']['email'])->toBe($mockUser->email);
    expect($responseData['company']['id'])->toBe($mockCompany->id);
    expect($responseData['company']['name'])->toBe($mockCompany->name);
});

test('it successfully logs in a super admin user without an associated company', function () {
    // Mock a user object without a company
    $mockUser = Mockery::mock(\Crater\Models\User::class);
    $mockUser->id = 2;
    $mockUser->name = 'Admin No Company';
    $mockUser->role = 'super admin';
    $mockUser->email = 'nocompany@example.com';
    $mockUser->setAppends(['company']); // Simulate for response serialization

    // Mock the companies relationship to return null (no company found)
    $mockUser->shouldReceive('companies')
             ->once()
             ->andReturn(Mockery::mock()
                 ->shouldReceive('first')
                 ->once()
                 ->andReturn(null) // User has no associated company
                 ->getMock()
             );

    // Mock the static call to User::where()->first()
    Mockery::mock('alias:Crater\Models\User')
        ->shouldReceive('where')
        ->with('role', 'super admin')
        ->once()
        ->andReturnSelf()
        ->shouldReceive('first')
        ->once()
        ->andReturn($mockUser);

    // Mock the Auth facade's login method
    Mockery::mock('alias:Auth')
        ->shouldReceive('login')
        ->once()
        ->with(Mockery::on(function ($arg) use ($mockUser) {
            return $arg->id === $mockUser->id;
        }));

    // Instantiate the controller
    $controller = new LoginController();
    $request = new Request();

    // Invoke the controller
    $response = $controller($request);

    // Assertions
    expect($response->getStatusCode())->toBe(200);
    $responseData = json_decode($response->getContent(), true);

    expect($responseData['success'])->toBeTrue();
    expect($responseData['user']['id'])->toBe($mockUser->id);
    expect($responseData['user']['name'])->toBe($mockUser->name);
    expect($responseData['user']['email'])->toBe($mockUser->email);
    expect($responseData['company'])->toBeNull(); // Company should be null in the response
});

test('it throws a TypeError if no super admin user is found', function () {
    // Mock the static call to User::where()->first() to return null
    Mockery::mock('alias:Crater\Models\User')
        ->shouldReceive('where')
        ->with('role', 'super admin')
        ->once()
        ->andReturnSelf()
        ->shouldReceive('first')
        ->once()
        ->andReturn(null); // No super admin found

    // Mock the Auth facade to expect login(null)
    Mockery::mock('alias:Auth')
        ->shouldReceive('login')
        ->once()
        ->with(null);

    // Instantiate the controller
    $controller = new LoginController();
    $request = new Request();

    // Expect a TypeError when trying to call ->companies() on null,
    // as the `$user` variable will be null
    expect(fn () => $controller($request))
        ->toThrow(TypeError::class);
        // The specific error message can vary between PHP versions (e.g., "Attempt to call a method 'companies' on null" in PHP 8+,
        // or "Trying to get property 'companies' of non-object" in PHP 7.x).
        // Checking just the TypeError class is robust for unit testing.
});
