<?php

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
uses(\Mockery::class);

// Define the test suite for SearchUsersController
test('it authorizes, searches users by email, and returns them paginated', function () {
    // 1. Arrange - Prepare the dependencies and mocks

    // Define the email to search for
    $email = 'test@example.com';

    // Create a mock Request object with the email parameter
    $request = Request::create('/api/v1/admin/general/search-users', 'GET', ['email' => $email]);

    // Prepare mock user data that would be returned by the query
    $mockUserCollection = collect([
        (object)['id' => 1, 'name' => 'Test User 1', 'email' => $email],
        (object)['id' => 2, 'name' => 'Test User 2', 'email' => $email],
    ]);

    // Mock a Paginator instance to simulate the result of paginate(10)
    $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockPaginator->shouldReceive('toArray')->andReturn([ // Simulate toArray() for JSON response
        'data' => $mockUserCollection->toArray(),
        'current_page' => 1,
        'last_page' => 1,
        'per_page' => 10,
        'total' => 2,
    ]);

    // Mock the static methods of the User model using Mockery's alias feature
    Mockery::mock('alias:Crater\Models\User')
        ->shouldReceive('whereEmail')
        ->once() // Ensure whereEmail is called exactly once
        ->with($email) // Ensure it's called with the correct email
        ->andReturnSelf() // Allow method chaining (e.g., ->latest())
        ->shouldReceive('latest')
        ->once() // Ensure latest() is called exactly once
        ->andReturnSelf() // Allow method chaining (e.g., ->paginate(10))
        ->shouldReceive('paginate')
        ->once() // Ensure paginate() is called exactly once
        ->with(10) // Ensure it's called with the correct page size
        ->andReturn($mockPaginator); // Return our mock paginator

    // Create an anonymous class extending SearchUsersController to override and spy on `authorize`
    $controller = new class extends \Crater\Http\Controllers\V1\Admin\General\SearchUsersController {
        public $authorizeCalled = false;
        public $authorizeArgs = [];

        // Override the authorize method to capture arguments and prevent actual authorization logic
        public function authorize($ability, $arguments = [])
        {
            $this->authorizeCalled = true;
            $this->authorizeArgs = [$ability, $arguments];
            // Simulate successful authorization by not throwing an exception
        }
    };

    // 2. Act - Call the __invoke method of the controller
    $response = $controller->__invoke($request);

    // 3. Assert - Verify the outcomes
    // Assert that the response is an instance of JsonResponse
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    // Assert the HTTP status code is 200 OK
    expect($response->getStatusCode())->toBe(200);

    // Decode the JSON response content for further assertions
    $responseData = json_decode($response->getContent(), true);

    // Assert that the `authorize` method was called correctly
    expect($controller->authorizeCalled)->toBeTrue();
    expect($controller->authorizeArgs)->toEqual(['create', \Crater\Models\User::class]);

    // Assert the structure and content of the JSON response
    expect($responseData)->toHaveKey('users');
    expect($responseData['users']['data'])->toHaveCount(2); // Check number of users returned
    expect($responseData['users']['data'][0]['email'])->toBe($email); // Check data integrity
    expect($responseData['users']['total'])->toBe(2); // Check total count from paginator
});

test('it handles no email parameter in the request by querying for null email', function () {
    // Arrange - Create a request with no 'email' parameter
    $request = Request::create('/api/v1/admin/general/search-users', 'GET', []);

    // Mock an empty collection for the paginator, as `whereEmail(null)` might return empty if no users have null email
    $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockPaginator->shouldReceive('toArray')->andReturn([
        'data' => [],
        'current_page' => 1,
        'last_page' => 1,
        'per_page' => 10,
        'total' => 0,
    ]);

    // Mock the static methods on the User model
    Mockery::mock('alias:Crater\Models\User')
        ->shouldReceive('whereEmail')
        ->once()
        ->with(null) // Expect 'null' because $request->email would be null if not present
        ->andReturnSelf()
        ->shouldReceive('latest')
        ->once()
        ->andReturnSelf()
        ->shouldReceive('paginate')
        ->once()
        ->with(10)
        ->andReturn($mockPaginator);

    // Override authorize to always succeed for this test
    $controller = new class extends \Crater\Http\Controllers\V1\Admin\General\SearchUsersController {
        public function authorize($ability, $arguments = []) { /* success */ }
    };

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);

    $responseData = json_decode($response->getContent(), true);
    expect($responseData)->toHaveKey('users');
    expect($responseData['users']['data'])->toBeEmpty(); // Expect an empty data array
    expect($responseData['users']['total'])->toBe(0); // Expect total to be 0
});

test('it returns an empty list if no users match the provided email', function () {
    // Arrange - Define an email that won't match any users
    $email = 'nonexistent@example.com';
    $request = Request::create('/api/v1/admin/general/search-users', 'GET', ['email' => $email]);

    // Mock an empty collection for the paginator to simulate no matching users
    $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
    $mockPaginator->shouldReceive('toArray')->andReturn([
        'data' => [],
        'current_page' => 1,
        'last_page' => 1,
        'per_page' => 10,
        'total' => 0,
    ]);

    // Mock the static methods on the User model
    Mockery::mock('alias:Crater\Models\User')
        ->shouldReceive('whereEmail')
        ->once()
        ->with($email)
        ->andReturnSelf()
        ->shouldReceive('latest')
        ->once()
        ->andReturnSelf()
        ->shouldReceive('paginate')
        ->once()
        ->with(10)
        ->andReturn($mockPaginator);

    // Override authorize to always succeed for this test
    $controller = new class extends \Crater\Http\Controllers\V1\Admin\General\SearchUsersController {
        public function authorize($ability, $arguments = []) { /* success */ }
    };

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);

    $responseData = json_decode($response->getContent(), true);
    expect($responseData)->toHaveKey('users');
    expect($responseData['users']['data'])->toBeEmpty();
    expect($responseData['users']['total'])->toBe(0);
});

test('it does not query users if authorization fails', function () {
    // Arrange - Prepare a request
    $email = 'test@example.com';
    $request = Request::create('/api/v1/admin/general/search-users', 'GET', ['email' => $email]);

    // Mock the static methods on the User model, asserting that they are *not* called
    Mockery::mock('alias:Crater\Models\User')
        ->shouldNotReceive('whereEmail')
        ->shouldNotReceive('latest')
        ->shouldNotReceive('paginate');

    // Create an anonymous class to simulate authorization failure by throwing an exception
    $controller = new class extends \Crater\Http\Controllers\V1\Admin\General\SearchUsersController {
        public $authorizeCalled = false;
        public function authorize($ability, $arguments = [])
        {
            $this->authorizeCalled = true;
            // Simulate an AuthorizationException, which should stop further execution
            throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized action.');
        }
    };

    // Act & Assert - Expect the AuthorizationException to be thrown
    $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

    // Call the method under test
    $controller->__invoke($request);

    // Assert that authorize was indeed called before the exception was thrown
    expect($controller->authorizeCalled)->toBeTrue();
});
