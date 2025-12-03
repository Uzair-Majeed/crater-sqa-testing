<?php

use Illuminate\Http\Request;
use Crater\Http\Controllers\V1\Installation\LoginController;
use Crater\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    Mockery::close(); // Clean up Mockery expectations after each test
});

test('it successfully logs in a super admin user with an associated company', function () {
    // Mock a company object (simple stdClass as it's just data)
    $mockCompany = (object)['id' => 101, 'name' => 'Test Company Inc.', 'uuid' => 'company-uuid-1'];

    // Mock a user object, including its companies relationship
    $mockUser = Mockery::mock(User::class);

    // Instead of direct property assignment which calls __set and then setAttribute,
    // mock the __get or getAttribute method for properties that are read.
    $mockUser->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $mockUser->shouldReceive('getAttribute')->with('name')->andReturn('Super Admin User');
    $mockUser->shouldReceive('getAttribute')->with('role')->andReturn('super admin');
    $mockUser->shouldReceive('getAttribute')->with('email')->andReturn('superadmin@example.com');

    // setAppends is a method call, ensure it's mocked correctly and returns self for chaining.
    $mockUser->shouldReceive('setAppends')->with(['company'])->andReturnSelf();

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
    Mockery::mock('alias:'.User::class)
        ->shouldReceive('where')
        ->with('role', 'super admin')
        ->once()
        ->andReturnSelf() // Allows chaining of `first()`
        ->shouldReceive('first')
        ->once()
        ->andReturn($mockUser);

    // Mock the Auth facade's login method
    Mockery::mock('alias:'.Auth::class)
        ->shouldReceive('login')
        ->once()
        ->with(Mockery::on(function ($arg) use ($mockUser) {
            // Verify that the login method is called with the mocked user object
            return $arg->id === $mockUser->id && $arg->email === $mockUser->email;
        }));

    // Instantiate the controller
    $controller = new LoginController();

    // Create a dummy request object (no specific parameters needed for this test)
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
    $mockUser = Mockery::mock(User::class);
    // Mock properties via getAttribute as direct assignment calls setAttribute
    $mockUser->shouldReceive('getAttribute')->with('id')->andReturn(2);
    $mockUser->shouldReceive('getAttribute')->with('name')->andReturn('Admin No Company');
    $mockUser->shouldReceive('getAttribute')->with('role')->andReturn('super admin');
    $mockUser->shouldReceive('getAttribute')->with('email')->andReturn('nocompany@example.com');
    // setAppends is a method call
    $mockUser->shouldReceive('setAppends')->with(['company'])->andReturnSelf();

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
    Mockery::mock('alias:'.User::class)
        ->shouldReceive('where')
        ->with('role', 'super admin')
        ->once()
        ->andReturnSelf()
        ->shouldReceive('first')
        ->once()
        ->andReturn($mockUser);

    // Mock the Auth facade's login method
    Mockery::mock('alias:'.Auth::class)
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
    Mockery::mock('alias:'.User::class)
        ->shouldReceive('where')
        ->with('role', 'super admin')
        ->once()
        ->andReturnSelf()
        ->shouldReceive('first')
        ->once()
        ->andReturn(null); // No super admin found

    // If the controller throws a TypeError because $user is null,
    // then Auth::login will never be called.
    // So, we should *not* expect Auth::login to be called.
    // Mockery would complain if we had an expectation for Auth::login and it wasn't met.
    // No need to mock Auth::login here.

    // Instantiate the controller
    $controller = new LoginController();
    $request = new Request();

    // Expect a TypeError when trying to call ->companies() on null,
    // as the `$user` variable will be null
    expect(fn () => $controller($request))
        ->toThrow(TypeError::class);
});


afterEach(function () {
    Mockery::close();
});
