
<?php

use Crater\Space\SiteApi;
use Crater\Models\Setting;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use Mockery\MockInterface;
use Illuminate\Support\Facades\Config; // Added for Facade::fake()
use Illuminate\Support\Facades\URL;    // Added for Facade::fake()

// Helper class to make the protected static method accessible
class TestSiteApiClass
{
    use SiteApi;

    public static function callGetRemote($url, $data = [], $token = null)
    {
        return self::getRemote($url, $data, $token);
    }
}

beforeEach(function () {
    // Clear mocks before each test to ensure isolation
    Mockery::close();

    // Mock Setting - assuming it's a class with static method, not a real Laravel Facade.
    // 'allow-public-static-methods' allows mocking static methods on a loaded class,
    // which resolves the "class already exists" error for non-facade static mocks.
    $this->settingMock = Mockery::mock('allow-public-static-methods:'.Setting::class);
    $this->settingMock->shouldReceive('getSetting')
        ->with('version')
        ->andReturn('1.0.0')
        ->byDefault();

    // Mock the config helper value using Laravel's Facade::fake() for robustness.
    Config::fake();
    Config::shouldReceive('get')
        ->with('crater.base_url')
        ->andReturn('http://test.api.com')
        ->byDefault();

    // Mock the url helper value using Laravel's Facade::fake() for robustness.
    URL::fake();
    URL::shouldReceive('to')
        ->with('/')
        ->andReturn('http://test.app.com')
        ->byDefault();

    // Mock the Guzzle Client instance.
    // This mock is set for `Client::class` in the container.
    // While the trait directly instantiates `new Client()`, this setup allows us
    // to mock the `get` method that will be called on a Guzzle Client instance.
    // The test specifically for constructor arguments will use Mockery's `overload`.
    $this->clientMock = Mockery::mock(Client::class);
    app()->instance(Client::class, $this->clientMock);
});


test('it sends a GET request successfully with all parameters', function () {
    $url = 'some-endpoint';
    $data = ['param1' => 'value1', 'param2' => 'value2'];
    $token = 'test-token-123';

    $expectedResponse = new Response(200, [], json_encode(['status' => 'success']));

    /** @var MockInterface $this->clientMock */
    $this->clientMock->shouldReceive('get')
        ->once()
        ->withArgs(function ($argUrl, $argData) use ($url, $data, $token) {
            expect($argUrl)->toBe($url);

            // Check merged data and headers
            expect($argData)->toBeArray();
            expect($argData['http_errors'])->toBeFalse(); // Should be explicitly set to false by the trait
            // Ensure custom data is present
            expect($argData)->toHaveKey('param1');
            expect($argData['param1'])->toBe('value1');
            expect($argData)->toHaveKey('param2');
            expect($argData['param2'])->toBe('value2');
            expect($argData['headers']['Accept'])->toBe('application/json');
            expect($argData['headers']['Referer'])->toBe('http://test.app.com');
            expect($argData['headers']['crater'])->toBe('1.0.0');
            expect($argData['headers']['Authorization'])->toBe("Bearer {$token}");

            return true;
        })
        ->andReturn($expectedResponse);

    $result = TestSiteApiClass::callGetRemote($url, $data, $token);

    expect($result)->toBeInstanceOf(Response::class);
    expect($result)->toBe($expectedResponse);
    expect($result->getStatusCode())->toBe(200);
    expect($result->getBody()->getContents())->toBe(json_encode(['status' => 'success']));
});

test('it sends a GET request successfully with default data and null token', function () {
    $url = 'another-endpoint';
    $token = null; // Explicitly null

    $expectedResponse = new Response(200, [], json_encode(['message' => 'no data no token']));

    /** @var MockInterface $this->clientMock */
    $this->clientMock->shouldReceive('get')
        ->once()
        ->withArgs(function ($argUrl, $argData) use ($url, $token) {
            expect($argUrl)->toBe($url);

            // Check merged data and headers
            expect($argData)->toBeArray();
            expect($argData['http_errors'])->toBeFalse();
            // No custom data params
            expect($argData)->not->toHaveKey('data'); // No 'data' key for GET requests with no explicit data
            expect($argData['headers']['Accept'])->toBe('application/json');
            expect($argData['headers']['Referer'])->toBe('http://test.app.com');
            expect($argData['headers']['crater'])->toBe('1.0.0');
            expect($argData['headers']['Authorization'])->toBe("Bearer "); // Token is null, so "Bearer "

            return true;
        })
        ->andReturn($expectedResponse);

    $result = TestSiteApiClass::callGetRemote($url, [], $token); // Pass empty array for data

    expect($result)->toBeInstanceOf(Response::class);
    expect($result)->toBe($expectedResponse);
    expect($result->getStatusCode())->toBe(200);
});

test('it returns RequestException when Guzzle client throws an exception', function () {
    $url = 'error-endpoint';
    $token = 'error-token';

    // A concrete Request instance is needed for RequestException
    $request = new Request('GET', 'http://test.api.com/' . $url);
    $expectedException = new RequestException('Error message', $request);

    /** @var MockInterface $this->clientMock */
    $this->clientMock->shouldReceive('get')
        ->once()
        ->andThrow($expectedException);

    $result = TestSiteApiClass::callGetRemote($url, [], $token);

    expect($result)->toBeInstanceOf(RequestException::class);
    expect($result)->toBe($expectedException);
    expect($result->getMessage())->toBe('Error message');
});

test('it merges provided data with http_errors and headers correctly, prioritizing internal values', function () {
    $url = 'merge-endpoint';
    // Provided data has http_errors=true, but trait explicitly sets it to false.
    // Also provides custom headers, some of which should be overridden.
    $data = [
        'custom_param' => 'custom_value',
        'http_errors' => true, // This should be overridden to false by the trait
        'headers' => [
            'User-Agent' => 'Custom Agent',
            'Accept' => 'text/plain', // This should be overridden by trait's 'application/json'
        ],
    ];
    $token = 'merge-token';

    $expectedResponse = new Response(200, [], json_encode(['status' => 'merged']));

    /** @var MockInterface $this->clientMock */
    $this->clientMock->shouldReceive('get')
        ->once()
        ->withArgs(function ($argUrl, $argData) use ($url, $token) {
            expect($argUrl)->toBe($url);

            expect($argData)->toBeArray();
            // http_errors should always be false, overriding the provided `true`
            expect($argData['http_errors'])->toBeFalse();
            expect($argData['custom_param'])->toBe('custom_value');

            // Check headers:
            // `array_merge($data, $headers)` means the trait's `$headers['headers']` will
            // overwrite any 'headers' key from the original `$data`.
            expect($argData['headers'])->toBeArray();
            expect($argData['headers']['Accept'])->toBe('application/json'); // Overridden by trait's value
            expect($argData['headers']['Referer'])->toBe('http://test.app.com');
            expect($argData['headers']['crater'])->toBe('1.0.0');
            expect($argData['headers']['Authorization'])->toBe("Bearer {$token}");
            expect($argData['headers'])->not->toHaveKey('User-Agent'); // Lost due to merge strategy

            return true;
        })
        ->andReturn($expectedResponse);

    $result = TestSiteApiClass::callGetRemote($url, $data, $token);

    expect($result->getStatusCode())->toBe(200);
});


// Test specifically for Guzzle Client constructor parameters using Mockery's `overload`
test('it constructs Guzzle client with correct base_uri and verify option', function () {
    // Clear all existing mocks, especially because `overload` changes how classes are resolved globally
    Mockery::close();

    // Re-mock dependencies needed for `Client` constructor arguments and headers
    // Use 'allow-public-static-methods' for Setting, and Facade::fake() for Config/URL.
    Mockery::mock('allow-public-static-methods:'.Setting::class)
        ->shouldReceive('getSetting')->with('version')->andReturn('1.0.0')->byDefault();

    Config::fake();
    Config::shouldReceive('get')->with('crater.base_url')->andReturn('http://test.api.com')->byDefault();

    URL::fake();
    URL::shouldReceive('to')->with('/')->andReturn('http://test.app.com')->byDefault();

    // Use `overload` to intercept `new Client()` calls within this test's scope
    $clientOverloadMock = Mockery::mock('overload:' . Client::class);

    // Expect the constructor to be called exactly once with the specified arguments
    $clientOverloadMock->shouldReceive('__construct')
        ->once()
        ->withArgs(function ($config) {
            expect($config)->toBeArray();
            expect($config['verify'])->toBeFalse(); // Explicitly set in trait
            expect($config['base_uri'])->toBe('http://test.api.com/'); // From config helper, with trailing slash
            return true;
        });

    // Also, the `get` method will be called on this overloaded instance, so mock its behavior
    $clientOverloadMock->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], json_encode(['status' => 'constructor_test_success'])));

    // Call the method under test, which triggers `new Client()`
    TestSiteApiClass::callGetRemote('test-constructor-endpoint');

    // Mockery will verify the `__construct` and `get` method calls. If expectations are not met,
    // Mockery will throw an exception, failing the test.
});


afterEach(function () {
    Mockery::close();
});