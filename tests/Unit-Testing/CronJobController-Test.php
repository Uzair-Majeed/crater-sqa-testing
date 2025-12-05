<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Crater\Http\Controllers\V1\Webhook\CronJobController;

beforeEach(function () {
    // Mock the Artisan facade to isolate the controller from actual Artisan commands.
    // Artisan::swap() replaces the facade's underlying instance in the container
    // with our mock for the duration of the test, ensuring static calls are intercepted.
    Artisan::swap($artisanMock = Mockery::mock());
    $this->artisanMock = $artisanMock;
});


test('it calls artisan schedule:run and returns a success json response', function () {
    // Arrange
    // Expect the 'call' method on the Artisan facade mock to be called exactly once
    // with the specific argument 'schedule:run'.
    // We provide an `andReturn(0)` as Artisan::call typically returns an exit code,
    // though its value doesn't affect the controller's logic in this case.
    $this->artisanMock->shouldReceive('call')
                      ->with('schedule:run')
                      ->once()
                      ->andReturn(0);

    // Instantiate the controller.
    $controller = new CronJobController();

    // Create a dummy Request instance. The controller's __invoke method
    // does not actually use the request object, so a basic empty one is sufficient.
    $request = Request::create('/');

    // Act
    // Invoke the controller. This will execute the logic under test.
    $response = $controller($request);

    // Assert
    // Verify that the 'call' method on the Artisan mock was indeed invoked as expected.
    $this->artisanMock->shouldHaveReceived('call');

    // Assert the HTTP status code of the response is 200 (OK).
    expect($response->getStatusCode())->toBe(200);

    // Assert that the response content is valid JSON.
    expect($response->getContent())->toBeJson();

    // Decode the JSON content and assert it matches the expected array.
    expect(json_decode($response->getContent(), true))->toEqual(['success' => true]);
});
 

afterEach(function () {
    Mockery::close();
});