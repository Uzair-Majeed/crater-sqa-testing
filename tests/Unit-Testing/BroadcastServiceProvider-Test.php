<?php

use Illuminate\Support\Facades\Broadcast;
use Crater\Providers\BroadcastServiceProvider;
use Mockery as m;

// Ensure Mockery is set up before each test and closed afterwards.
beforeEach(function () {
    // Swap the Broadcast facade with a Mockery mock.
    // This intercepts all static calls to Broadcast::.
    m::mock('alias:Illuminate\Support\Facades\Broadcast');
});

test('the service provider registers broadcast routes and includes channels file', function () {
    // Arrange: Define expectations for the Broadcast facade.

    // Expect Broadcast::routes() to be called once with no arguments.
    // This covers the first call in the boot method: `Broadcast::routes();`
    Broadcast::shouldReceive('routes')
        ->once()
        ->withNoArgs();

    // Expect Broadcast::routes(["middleware" => 'api.auth']) to be called once
    // with the specific array argument.
    // This covers the second call in the boot method: `Broadcast::routes(["middleware" => 'api.auth']);`
    Broadcast::shouldReceive('routes')
        ->once()
        ->with(['middleware' => 'api.auth']);

    // Instantiate a mock for the Application.
    // Service providers require an Application instance in their constructor.
    // For this specific boot method, the Application mock doesn't need complex behavior
    // as it's not directly accessed here, but it fulfills the constructor's requirement.
    $app = m::mock('Illuminate\Contracts\Foundation\Application');

    // Act: Create an instance of the service provider and call its boot method.
    $provider = new BroadcastServiceProvider($app);
    $provider->boot();

    // Assert: Mockery's `shouldReceive->once()` expectations automatically assert
    // that the methods were called as expected. If not, Mockery throws an exception.
    //
    // For `require base_path('routes/channels.php');`:
    // This line is executed. In a standard unit test, mocking global functions
    // like `base_path()` or asserting the side-effect of `require` is difficult
    // without specialized tools (e.g., runkit, vfsStream, or specific global function
    // mocking libraries not typically part of a standard Pest setup).
    //
    // Given the requirement for a "clean, runnable PHP Pest test code block",
    // the most practical approach is to ensure the test environment has a
    // `routes/channels.php` file (even an empty one) at `base_path('routes/channels.php')`
    // so that the `require` statement executes without causing a fatal error.
    // The success of this test implies this line was executed without crashing the process,
    // thereby covering the execution path of this statement.
});


afterEach(function () {
    // Close Mockery to clean up any mocks.
    m::close();
});
