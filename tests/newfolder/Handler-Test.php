<?php

use Crater\Exceptions\Handler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

// Helper function for accessing protected properties for white-box testing
function getProtectedProperty(object $object, string $property)
{
    $reflection = new ReflectionClass($object);
    $property = $reflection->getProperty($property);
    $property->setAccessible(true);
    return $property->getValue($object);
}

test('it has the correct dontReport property', function () {
    $mockApp = Mockery::mock(Application::class);
    $handler = new Handler($mockApp);
    $dontReport = getProtectedProperty($handler, 'dontReport');
    expect($dontReport)->toBeArray()->toBeEmpty();
});

test('it has the correct dontFlash property', function () {
    $mockApp = Mockery::mock(Application::class);
    $handler = new Handler($mockApp);
    $dontFlash = getProtectedProperty($handler, 'dontFlash');
    expect($dontFlash)->toBeArray()->toEqual(['password', 'password_confirmation']);
});

test('report method delegates to parent report and triggers logger and event interaction', function () {
    $mockApp = Mockery::mock(Application::class);
    $mockLogger = Mockery::mock(LoggerInterface::class);
    $mockConfig = Mockery::mock(Repository::class);
    $mockEvents = Mockery::mock(Dispatcher::class);
    $exception = new RuntimeException('Test exception for reporting');

    // Configure mock Application for constructor and report method dependencies
    $mockApp->shouldReceive('bound')->with(LoggerInterface::class)->andReturn(true);
    $mockApp->shouldReceive('make')->with(LoggerInterface::class)->andReturn($mockLogger);
    $mockApp->shouldReceive('offsetGet')->with('config')->andReturn($mockConfig);
    $mockApp->shouldReceive('offsetGet')->with('events')->andReturn($mockEvents);
    $mockApp->shouldReceive('offsetGet')->with('env')->andReturn('testing');
    $mockApp->shouldReceive('bound')->with('auth')->andReturn(false); // Prevent parent from trying to resolve auth
    $mockApp->shouldReceive('bound')->with('db')->andReturn(false); // Prevent parent from trying to resolve db
    $mockApp->shouldReceive('bound')->with('queue')->andReturn(false); // Prevent parent from trying to resolve queue

    // Configure mock Config
    $mockConfig->shouldReceive('get')->with('app.debug', false)->andReturn(false);
    $mockConfig->shouldReceive('get')->with('app.name')->andReturn('Crater');
    $mockConfig->shouldReceive('get')->with('logging.channels.stderr.tap', [])->andReturn([]); // for stderr logging

    // Expect the logger to receive an error call from parent::report
    $mockLogger->shouldReceive('error')
        ->once()
        ->with($exception);

    // Expect event dispatcher to dispatch an event (ExceptionReported in parent)
    $mockEvents->shouldReceive('dispatch')
        ->once()
        ->withArgs(function ($event) use ($exception) {
            return $event instanceof \Illuminate\Foundation\Events\ExceptionReported && $event->exception === $exception;
        });

    $handler = new Handler($mockApp);
    $handler->report($exception);
});

test('report method does not log or dispatch events for exceptions in dontReport array', function () {
    $mockApp = Mockery::mock(Application::class);
    $mockLogger = Mockery::mock(LoggerInterface::class);
    $mockConfig = Mockery::mock(Repository::class);
    $mockEvents = Mockery::mock(Dispatcher::class);

    // Create a custom handler instance that has a specific exception type in its dontReport array
    $handler = new class($mockApp) extends Handler {
        protected $dontReport = [
            \LogicException::class,
        ];
    };
    $exception = new \LogicException('Test exception for dontReport');

    // Configure mock Application for constructor and report method dependencies
    $mockApp->shouldReceive('bound')->with(LoggerInterface::class)->andReturn(true);
    $mockApp->shouldReceive('make')->with(LoggerInterface::class)->andReturn($mockLogger);
    $mockApp->shouldReceive('offsetGet')->with('config')->andReturn($mockConfig);
    $mockApp->shouldReceive('offsetGet')->with('events')->andReturn($mockEvents);
    $mockApp->shouldReceive('offsetGet')->with('env')->andReturn('testing');
    $mockApp->shouldReceive('bound')->with('auth')->andReturn(false);
    $mockApp->shouldReceive('bound')->with('db')->andReturn(false);
    $mockApp->shouldReceive('bound')->with('queue')->andReturn(false);

    // Configure mock Config
    $mockConfig->shouldReceive('get')->with('app.debug', false)->andReturn(false);
    $mockConfig->shouldReceive('get')->with('app.name')->andReturn('Crater');
    $mockConfig->shouldReceive('get')->with('logging.channels.stderr.tap', [])->andReturn([]);

    // Expect the logger NOT to receive an error call
    $mockLogger->shouldNotReceive('error');

    // Expect event dispatcher NOT to dispatch ExceptionReported for a dontReport exception
    $mockEvents->shouldNotReceive('dispatch');

    $handler->report($exception);
});

test('render method delegates to parent render and returns an http response for html requests', function () {
    $mockApp = Mockery::mock(Application::class);
    $mockRequest = Mockery::mock(Request::class);
    $mockException = new RuntimeException('Test exception for rendering');
    $mockConfig = Mockery::mock(Repository::class);
    $mockViewFactory = Mockery::mock(ViewFactory::class);
    $mockView = Mockery::mock(View::class);

    // Configure mock Application for constructor and render method dependencies
    $mockApp->shouldReceive('offsetGet')->with('config')->andReturn($mockConfig);
    $mockApp->shouldReceive('offsetGet')->with('view')->andReturn($mockViewFactory);
    $mockApp->shouldReceive('runningInConsole')->andReturn(false);
    $mockApp->shouldReceive('offsetGet')->with('env')->andReturn('testing');
    $mockApp->shouldReceive('bound')->with('auth')->andReturn(false);
    $mockApp->shouldReceive('bound')->with('session')->andReturn(false);
    $mockApp->shouldReceive('bound')->with('translator')->andReturn(false); // For parent handling messages

    // Configure mock Config
    $mockConfig->shouldReceive('get')->with('app.debug', false)->andReturn(false);
    $mockConfig->shouldReceive('get')->with('app.name')->andReturn('Crater');
    $mockConfig->shouldReceive('get')->with('errors.dont_flash', [])->andReturn([]);
    $mockConfig->shouldReceive('get')->with('view.paths', [])->andReturn([]); // For BaseExceptionHandler's view finding

    // Configure mock ViewFactory
    $mockViewFactory->shouldReceive('exists')->andReturn(false); // No custom error views by default
    $mockViewFactory->shouldReceive('exists')->with('errors::500')->andReturn(true); // Default 500 view
    $mockViewFactory->shouldReceive('make')->with('errors::500', Mockery::any(), Mockery::any())->andReturn($mockView);

    // Configure mock View
    $mockView->shouldReceive('render')->andReturn('<html>Error 500</html>');

    // Configure mock Request for HTML expectation
    $mockRequest->shouldReceive('expectsJson')->andReturn(false);
    $mockRequest->shouldReceive('header')->with('Accept')->andReturn('text/html');
    $mockRequest->shouldReceive('method')->andReturn('GET');
    $mockRequest->shouldReceive('url')->andReturn('http://localhost/error');

    $handler = new Handler($mockApp);
    $response = $handler->render($mockRequest, $mockException);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(500) // Default for RuntimeException from BaseExceptionHandler
        ->and($response->getContent())->toContain('Error 500'); // Based on our mock view rendering
});

test('render method delegates to parent render and returns a json response for json requests', function () {
    $mockApp = Mockery::mock(Application::class);
    $mockRequest = Mockery::mock(Request::class);
    $mockException = new RuntimeException('API error');
    $mockConfig = Mockery::mock(Repository::class);

    // Configure mock Application for constructor and render method dependencies
    $mockApp->shouldReceive('offsetGet')->with('config')->andReturn($mockConfig);
    $mockApp->shouldReceive('runningInConsole')->andReturn(false);
    $mockApp->shouldReceive('offsetGet')->with('env')->andReturn('testing');
    $mockApp->shouldReceive('bound')->with('auth')->andReturn(false);
    $mockApp->shouldReceive('bound')->with('session')->andReturn(false);
    $mockApp->shouldReceive('bound')->with('translator')->andReturn(false);

    // Configure mock Config
    $mockConfig->shouldReceive('get')->with('app.debug', false)->andReturn(false);
    $mockConfig->shouldReceive('get')->with('app.name')->andReturn('Crater');
    $mockConfig->shouldReceive('get')->with('errors.dont_flash', [])->andReturn([]);

    // Configure mock Request to expect JSON
    $mockRequest->shouldReceive('expectsJson')->andReturn(true);
    $mockRequest->shouldReceive('header')->with('Accept')->andReturn('application/json');
    $mockRequest->shouldReceive('method')->andReturn('GET');
    $mockRequest->shouldReceive('url')->andReturn('http://localhost/api/error');

    $handler = new Handler($mockApp);
    $response = $handler->render($mockRequest, $mockException);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->headers->get('Content-Type'))->toContain('application/json')
        ->and(json_decode($response->getContent(), true))->toMatchArray([
            'message' => 'API error' // Default message from BaseExceptionHandler for RuntimeException
        ]);
});

afterEach(function () {
    Mockery::close();
});