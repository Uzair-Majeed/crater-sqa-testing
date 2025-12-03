<?php

use Crater\Models\CompanySetting;
use Crater\Models\Currency;
use Crater\Models\CustomField;
use Crater\Models\Setting;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;
use Mockery as m;

// Mock Laravel's global helper `response()` for respondJson
// This is necessary because Pest runs in a context where global functions might not be redefined
// or accessible in the same way as a full Laravel boot.
if (!function_exists('response')) {
    function response() {
        return m::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
    }
}

beforeEach(function () {
    // Ensure mocks are reset before each test to prevent bleed-over
    m::close();
    // Reset global response mock if it was set
    if (isset($GLOBALS['response'])) {
        unset($GLOBALS['response']);
    }
});

// Test get_company_setting
test('get_company_setting returns null if database_created file does not exist', function () {
    Storage::shouldReceive('disk')->with('local')->andReturn(
        m::mock()->shouldReceive('has')->with('database_created')->andReturn(false)->getMock()
    );

    expect(get_company_setting('some_key', 1))->toBeNull();
});

test('get_company_setting returns setting value if database_created file exists', function () {
    Storage::shouldReceive('disk')->with('local')->andReturn(
        m::mock()->shouldReceive('has')->with('database_created')->andReturn(true)->getMock()
    );

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('company_name', 1)
        ->andReturn('Crater Inc.');

    expect(get_company_setting('company_name', 1))->toBe('Crater Inc.');
});

test('get_company_setting returns different setting for different key and company', function () {
    Storage::shouldReceive('disk')->with('local')->andReturn(
        m::mock()->shouldReceive('has')->with('database_created')->andReturn(true)->getMock()
    );

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('invoice_prefix', 2)
        ->andReturn('INV-C2-');

    expect(get_company_setting('invoice_prefix', 2))->toBe('INV-C2-');
});


// Test get_app_setting
test('get_app_setting returns null if database_created file does not exist', function () {
    Storage::shouldReceive('disk')->with('local')->andReturn(
        m::mock()->shouldReceive('has')->with('database_created')->andReturn(false)->getMock()
    );

    expect(get_app_setting('some_key'))->toBeNull();
});

test('get_app_setting returns setting value if database_created file exists', function () {
    Storage::shouldReceive('disk')->with('local')->andReturn(
        m::mock()->shouldReceive('has')->with('database_created')->andReturn(true)->getMock()
    );

    Setting::shouldReceive('getSetting')
        ->once()
        ->with('app_name')
        ->andReturn('My App');

    expect(get_app_setting('app_name'))->toBe('My App');
});

test('get_app_setting returns different setting for different key', function () {
    Storage::shouldReceive('disk')->with('local')->andReturn(
        m::mock()->shouldReceive('has')->with('database_created')->andReturn(true)->getMock()
    );

    Setting::shouldReceive('getSetting')
        ->once()
        ->with('app_version')
        ->andReturn('1.2.3');

    expect(get_app_setting('app_version'))->toBe('1.2.3');
});


// Test get_page_title
test('get_page_title returns null if database_created file does not exist', function () {
    Storage::shouldReceive('disk')->with('local')->andReturn(
        m::mock()->shouldReceive('has')->with('database_created')->andReturn(false)->getMock()
    );

    expect(get_page_title(1))->toBeNull();
});

test('get_page_title returns customer portal title if route is customer dashboard and setting exists', function () {
    Storage::shouldReceive('disk')->with('local')->andReturn(
        m::mock()->shouldReceive('has')->with('database_created')->andReturn(true)->getMock()
    );
    Route::shouldReceive('currentRouteName')->once()->andReturn('customer.dashboard');
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('customer_portal_page_title', 1)
        ->andReturn('Customer Dashboard Title');

    expect(get_page_title(1))->toBe('Customer Dashboard Title');
});

test('get_page_title returns default title if route is customer dashboard and setting is null', function () {
    Storage::shouldReceive('disk')->with('local')->andReturn(
        m::mock()->shouldReceive('has')->with('database_created')->andReturn(true)->getMock()
    );
    Route::shouldReceive('currentRouteName')->once()->andReturn('customer.dashboard');
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('customer_portal_page_title', 1)
        ->andReturn(null);

    expect(get_page_title(1))->toBe('Crater - Self Hosted Invoicing Platform');
});

test('get_page_title returns default title if route is customer dashboard and setting is empty string', function () {
    Storage::shouldReceive('disk')->with('local')->andReturn(
        m::mock()->shouldReceive('has')->with('database_created')->andReturn(true)->getMock()
    );
    Route::shouldReceive('currentRouteName')->once()->andReturn('customer.dashboard');
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('customer_portal_page_title', 1)
        ->andReturn('');

    expect(get_page_title(1))->toBe('Crater - Self Hosted Invoicing Platform');
});

test('get_page_title returns admin page title if route is not customer dashboard and setting exists', function () {
    Storage::shouldReceive('disk')->with('local')->andReturn(
        m::mock()->shouldReceive('has')->with('database_created')->andReturn(true)->getMock()
    );
    Route::shouldReceive('currentRouteName')->once()->andReturn('admin.dashboard');
    Setting::shouldReceive('getSetting')
        ->once()
        ->with('admin_page_title')
        ->andReturn('Admin Panel Title');

    expect(get_page_title(1))->toBe('Admin Panel Title');
});

test('get_page_title returns default title if route is not customer dashboard and setting is null', function () {
    Storage::shouldReceive('disk')->with('local')->andReturn(
        m::mock()->shouldReceive('has')->with('database_created')->andReturn(true)->getMock()
    );
    Route::shouldReceive('currentRouteName')->once()->andReturn('admin.dashboard');
    Setting::shouldReceive('getSetting')
        ->once()
        ->with('admin_page_title')
        ->andReturn(null);

    expect(get_page_title(1))->toBe('Crater - Self Hosted Invoicing Platform');
});

test('get_page_title returns default title if route is not customer dashboard and setting is empty string', function () {
    Storage::shouldReceive('disk')->with('local')->andReturn(
        m::mock()->shouldReceive('has')->with('database_created')->andReturn(true)->getMock()
    );
    Route::shouldReceive('currentRouteName')->once()->andReturn('admin.dashboard');
    Setting::shouldReceive('getSetting')
        ->once()
        ->with('admin_page_title')
        ->andReturn('');

    expect(get_page_title(1))->toBe('Crater - Self Hosted Invoicing Platform');
});


// Test set_active
test('set_active returns active string for matching path', function () {
    Request::shouldReceive('is')->once()->with('dashboard')->andReturn(true);
    expect(set_active('dashboard'))->toBe('active');
});

test('set_active returns custom active string for matching path', function () {
    Request::shouldReceive('is')->once()->with('dashboard')->andReturn(true);
    expect(set_active('dashboard', 'current'))->toBe('current');
});

test('set_active returns empty string for non-matching path', function () {
    Request::shouldReceive('is')->once()->with('dashboard')->andReturn(false);
    expect(set_active('dashboard'))->toBe('');
});

test('set_active works with array paths when matching', function () {
    Request::shouldReceive('is')->once()->with(['dashboard', 'home'])->andReturn(true);
    expect(set_active(['dashboard', 'home']))->toBe('active');
});

test('set_active works with array paths when not matching', function () {
    Request::shouldReceive('is')->once()->with(['users/*', 'roles/*'])->andReturn(false);
    expect(set_active(['users/*', 'roles/*']))->toBe('');
});


// Test is_url
test('is_url returns true for matching url', function () {
    Request::shouldReceive('is')->once()->with('dashboard')->andReturn(true);
    expect(is_url('dashboard'))->toBeTrue();
});

test('is_url returns false for non-matching url', function () {
    Request::shouldReceive('is')->once()->with('dashboard')->andReturn(false);
    expect(is_url('dashboard'))->toBeFalse();
});

test('is_url works with array of urls when matching', function () {
    Request::shouldReceive('is')->once()->with(['dashboard', 'home'])->andReturn(true);
    expect(is_url(['dashboard', 'home']))->toBeTrue();
});

test('is_url works with array of urls when not matching', function () {
    Request::shouldReceive('is')->once()->with(['users/*', 'roles/*'])->andReturn(false);
    expect(is_url(['users/*', 'roles/*']))->toBeFalse();
});


// Test getCustomFieldValueKey
test('getCustomFieldValueKey returns correct key for Input type', function () {
    expect(getCustomFieldValueKey('Input'))->toBe('string_answer');
});

test('getCustomFieldValueKey returns correct key for TextArea type', function () {
    expect(getCustomFieldValueKey('TextArea'))->toBe('string_answer');
});

test('getCustomFieldValueKey returns correct key for Phone type', function () {
    expect(getCustomFieldValueKey('Phone'))->toBe('number_answer');
});

test('getCustomFieldValueKey returns correct key for Url type', function () {
    expect(getCustomFieldValueKey('Url'))->toBe('string_answer');
});

test('getCustomFieldValueKey returns correct key for Number type', function () {
    expect(getCustomFieldValueKey('Number'))->toBe('number_answer');
});

test('getCustomFieldValueKey returns correct key for Dropdown type', function () {
    expect(getCustomFieldValueKey('Dropdown'))->toBe('string_answer');
});

test('getCustomFieldValueKey returns correct key for Switch type', function () {
    expect(getCustomFieldValueKey('Switch'))->toBe('boolean_answer');
});

test('getCustomFieldValueKey returns correct key for Date type', function () {
    expect(getCustomFieldValueKey('Date'))->toBe('date_answer');
});

test('getCustomFieldValueKey returns correct key for Time type', function () {
    expect(getCustomFieldValueKey('Time'))->toBe('time_answer');
});

test('getCustomFieldValueKey returns correct key for DateTime type', function () {
    expect(getCustomFieldValueKey('DateTime'))->toBe('date_time_answer');
});

test('getCustomFieldValueKey returns default key for unknown type', function () {
    expect(getCustomFieldValueKey('UnknownType'))->toBe('string_answer');
});

test('getCustomFieldValueKey is case sensitive', function () {
    expect(getCustomFieldValueKey('input'))->toBe('string_answer'); // Falls to default
    expect(getCustomFieldValueKey('INPUT'))->toBe('string_answer'); // Falls to default
});


// Test format_money_pdf
test('format_money_pdf formats money with provided currency and symbol before', function () {
    $currency = m::mock(Currency::class);
    $currency->precision = 2;
    $currency->decimal_separator = '.';
    $currency->thousand_separator = ',';
    $currency->symbol = '$';
    $currency->swap_currency_symbol = false; // Symbol before amount

    $expected = '<span style="font-family: DejaVu Sans;">$</span>123.45';
    expect(format_money_pdf(12345, $currency))->toBe($expected);
});

test('format_money_pdf formats money with provided currency and symbol after', function () {
    $currency = m::mock(Currency::class);
    $currency->precision = 2;
    $currency->decimal_separator = '.';
    $currency->thousand_separator = ',';
    $currency->symbol = '€';
    $currency->swap_currency_symbol = true; // Symbol after amount

    $expected = '123.45<span style="font-family: DejaVu Sans;">€</span>';
    expect(format_money_pdf(12345, $currency))->toBe($expected);
});

test('format_money_pdf fetches currency if not provided and symbol before', function () {
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', 1)
        ->andReturn(10); // Assume currency ID 10

    $currency = m::mock(Currency::class);
    $currency->precision = 2;
    $currency->decimal_separator = '.';
    $currency->thousand_separator = ',';
    $currency->symbol = '£';
    $currency->swap_currency_symbol = false;

    Currency::shouldReceive('findOrFail')->once()->with(10)->andReturn($currency);

    $expected = '<span style="font-family: DejaVu Sans;">£</span>123.45';
    expect(format_money_pdf(12345))->toBe($expected);
});

test('format_money_pdf fetches currency if not provided and symbol after', function () {
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('currency', 1)
        ->andReturn(10); // Assume currency ID 10

    $currency = m::mock(Currency::class);
    $currency->precision = 2;
    $currency->decimal_separator = '.';
    $currency->thousand_separator = ',';
    $currency->symbol = 'zł';
    $currency->swap_currency_symbol = true;

    Currency::shouldReceive('findOrFail')->once()->with(10)->andReturn($currency);

    $expected = '123.45<span style="font-family: DejaVu Sans;">zł</span>';
    expect(format_money_pdf(12345))->toBe($expected);
});

test('format_money_pdf handles different precision and separators', function () {
    $currency = m::mock(Currency::class);
    $currency->precision = 3;
    $currency->decimal_separator = ',';
    $currency->thousand_separator = '.';
    $currency->symbol = 'R';
    $currency->swap_currency_symbol = false;

    $expected = '<span style="font-family: DejaVu Sans;">R</span>1.234,568';
    expect(format_money_pdf(12345680, $currency))->toBe($expected);
});

test('format_money_pdf handles zero money', function () {
    $currency = m::mock(Currency::class);
    $currency->precision = 2;
    $currency->decimal_separator = '.';
    $currency->thousand_separator = ',';
    $currency->symbol = '$';
    $currency->swap_currency_symbol = false;

    $expected = '<span style="font-family: DejaVu Sans;">$</span>0.00';
    expect(format_money_pdf(0, $currency))->toBe($expected);
});

test('format_money_pdf handles negative money', function () {
    $currency = m::mock(Currency::class);
    $currency->precision = 2;
    $currency->decimal_separator = '.';
    $currency->thousand_separator = ',';
    $currency->symbol = '$';
    $currency->swap_currency_symbol = false;

    $expected = '<span style="font-family: DejaVu Sans;">$</span>-123.45';
    expect(format_money_pdf(-12345, $currency))->toBe($expected);
});

test('format_money_pdf handles large numbers', function () {
    $currency = m::mock(Currency::class);
    $currency->precision = 2;
    $currency->decimal_separator = '.';
    $currency->thousand_separator = ',';
    $currency->symbol = '$';
    $currency->swap_currency_symbol = false;

    $expected = '<span style="font-family: DejaVu Sans;">$</span>1,234,567,890.12';
    expect(format_money_pdf(123456789012, $currency))->toBe($expected);
});


// Helper to mock the CustomField query builder chain for getRelatedSlugs/clean_slug
function mockCustomFieldQuery(array $slugs): m\MockInterface
{
    $mockQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $mockCollection = m::mock(\Illuminate\Support\Collection::class);

    $mockQueryBuilder->shouldReceive('where')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('get')->andReturn($mockCollection);

    // Mock the `contains` method on the collection to simulate existing slugs
    $mockCollection->shouldReceive('contains')
        ->withArgs(function ($key, $value) use ($slugs) {
            return in_array($value, $slugs);
        })
        ->andReturnUsing(function ($key, $value) use ($slugs) {
            return in_array($value, $slugs);
        });

    CustomField::shouldReceive('select')->with('slug')->andReturn($mockQueryBuilder);
    return $mockQueryBuilder;
}


// Test getRelatedSlugs
test('getRelatedSlugs queries CustomField with correct parameters', function () {
    $mockQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $mockCollection = m::mock(\Illuminate\Support\Collection::class); // Only need to return a collection

    CustomField::shouldReceive('select')->once()->with('slug')->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('where')->once()->with('slug', 'like', 'CUSTOM_INVOICE_TEST%')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('where')->once()->with('model_type', 'Invoice')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('where')->once()->with('id', '<>', 0)->andReturnSelf();
    $mockQueryBuilder->shouldReceive('get')->once()->andReturn($mockCollection);

    $result = getRelatedSlugs('Invoice', 'CUSTOM_INVOICE_TEST', 0);
    expect($result)->toBe($mockCollection);
});

test('getRelatedSlugs handles non-zero id parameter', function () {
    $mockQueryBuilder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
    $mockCollection = m::mock(\Illuminate\Support\Collection::class);

    CustomField::shouldReceive('select')->once()->with('slug')->andReturn($mockQueryBuilder);
    $mockQueryBuilder->shouldReceive('where')->once()->with('slug', 'like', 'CUSTOM_INVOICE_TEST%')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('where')->once()->with('model_type', 'Invoice')->andReturnSelf();
    $mockQueryBuilder->shouldReceive('where')->once()->with('id', '<>', 5)->andReturnSelf(); // Check for ID
    $mockQueryBuilder->shouldReceive('get')->once()->andReturn($mockCollection);

    getRelatedSlugs('Invoice', 'CUSTOM_INVOICE_TEST', 5);
});


// Test clean_slug
test('clean_slug returns base slug if no related slugs exist', function () {
    Str::shouldReceive('upper')->once()->with('CUSTOM_Invoice_my_new_field')->andReturn('CUSTOM_INVOICE_MY_NEW_FIELD');
    Str::shouldReceive('slug')->once()->with('My New Field', '_')->andReturn('my_new_field');

    mockCustomFieldQuery([]); // No existing slugs

    expect(clean_slug('Invoice', 'My New Field'))->toBe('CUSTOM_INVOICE_MY_NEW_FIELD');
});

test('clean_slug returns slug_1 if base slug exists', function () {
    Str::shouldReceive('upper')->once()->with('CUSTOM_Invoice_test_field')->andReturn('CUSTOM_INVOICE_TEST_FIELD');
    Str::shouldReceive('slug')->once()->with('Test Field', '_')->andReturn('test_field');
    // For the loop
    Str::shouldReceive('upper')->with('CUSTOM_Invoice_test_field_1')->andReturn('CUSTOM_INVOICE_TEST_FIELD_1'); // Should not be called because Str::slug is only called once. This should be internal to clean_slug generation.

    mockCustomFieldQuery(['CUSTOM_INVOICE_TEST_FIELD']); // Base slug exists

    expect(clean_slug('Invoice', 'Test Field'))->toBe('CUSTOM_INVOICE_TEST_FIELD_1');
});

test('clean_slug returns slug_N if base and N-1 slugs exist', function () {
    Str::shouldReceive('upper')->once()->with('CUSTOM_Invoice_another_field')->andReturn('CUSTOM_INVOICE_ANOTHER_FIELD');
    Str::shouldReceive('slug')->once()->with('Another Field', '_')->andReturn('another_field');

    mockCustomFieldQuery([
        'CUSTOM_INVOICE_ANOTHER_FIELD',
        'CUSTOM_INVOICE_ANOTHER_FIELD_1',
        'CUSTOM_INVOICE_ANOTHER_FIELD_2',
    ]); // Base, _1, _2 exist

    expect(clean_slug('Invoice', 'Another Field'))->toBe('CUSTOM_INVOICE_ANOTHER_FIELD_3');
});

test('clean_slug throws exception if more than 10 variations exist', function () {
    Str::shouldReceive('upper')->once()->with('CUSTOM_Invoice_max_out')->andReturn('CUSTOM_INVOICE_MAX_OUT');
    Str::shouldReceive('slug')->once()->with('Max Out', '_')->andReturn('max_out');

    mockCustomFieldQuery([
        'CUSTOM_INVOICE_MAX_OUT',
        'CUSTOM_INVOICE_MAX_OUT_1',
        'CUSTOM_INVOICE_MAX_OUT_2',
        'CUSTOM_INVOICE_MAX_OUT_3',
        'CUSTOM_INVOICE_MAX_OUT_4',
        'CUSTOM_INVOICE_MAX_OUT_5',
        'CUSTOM_INVOICE_MAX_OUT_6',
        'CUSTOM_INVOICE_MAX_OUT_7',
        'CUSTOM_INVOICE_MAX_OUT_8',
        'CUSTOM_INVOICE_MAX_OUT_9',
        'CUSTOM_INVOICE_MAX_OUT_10',
    ]); // All 11 possible slugs (base + 10 variations) exist

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Can not create a unique slug');
    clean_slug('Invoice', 'Max Out');
});

test('clean_slug handles different models producing distinct slugs', function () {
    Str::shouldReceive('upper')->once()->with('CUSTOM_Client_client_name')->andReturn('CUSTOM_CLIENT_CLIENT_NAME');
    Str::shouldReceive('slug')->once()->with('Client Name', '_')->andReturn('client_name');

    // Simulate that 'CUSTOM_CLIENT_CLIENT_NAME' does not exist, but 'CUSTOM_INVOICE_CLIENT_NAME' might.
    // getRelatedSlugs uses model_type to filter, so this should not conflict.
    mockCustomFieldQuery([]);

    expect(clean_slug('Client', 'Client Name'))->toBe('CUSTOM_CLIENT_CLIENT_NAME');
});

test('clean_slug handles title with special characters and spaces', function () {
    Str::shouldReceive('upper')->once()->with('CUSTOM_Item_my_title')->andReturn('CUSTOM_ITEM_MY_TITLE');
    Str::shouldReceive('slug')->once()->with('My Title! @#$', '_')->andReturn('my_title');

    mockCustomFieldQuery([]);

    expect(clean_slug('Item', 'My Title! @#$'))->toBe('CUSTOM_ITEM_MY_TITLE');
});

test('clean_slug handles empty title gracefully by slugging to default', function () {
    Str::shouldReceive('upper')->once()->with('CUSTOM_Product_')->andReturn('CUSTOM_PRODUCT_');
    Str::shouldReceive('slug')->once()->with('', '_')->andReturn(''); // Str::slug('') returns ''

    mockCustomFieldQuery([]);

    expect(clean_slug('Product', ''))->toBe('CUSTOM_PRODUCT_');
});


// Test respondJson
test('respondJson returns a json response with error and message for true error', function () {
    $mockResponseFactory = m::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
    $mockJsonResponse = m::mock(\Illuminate\Http\JsonResponse::class);

    // Set the global `response` function to our mock for this test
    $GLOBALS['response'] = $mockResponseFactory;

    $mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => true, 'message' => 'Something went wrong'], 422)
        ->andReturn($mockJsonResponse);

    expect(respondJson(true, 'Something went wrong'))->toBe($mockJsonResponse);
});

test('respondJson returns a json response with error and message for false error', function () {
    $mockResponseFactory = m::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
    $mockJsonResponse = m::mock(\Illuminate\Http\JsonResponse::class);

    // Set the global `response` function to our mock for this test
    $GLOBALS['response'] = $mockResponseFactory;

    $mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => false, 'message' => 'Success message'], 422)
        ->andReturn($mockJsonResponse);

    expect(respondJson(false, 'Success message'))->toBe($mockJsonResponse);
});

test('respondJson returns a json response with empty message', function () {
    $mockResponseFactory = m::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
    $mockJsonResponse = m::mock(\Illuminate\Http\JsonResponse::class);

    // Set the global `response` function to our mock for this test
    $GLOBALS['response'] = $mockResponseFactory;

    $mockResponseFactory->shouldReceive('json')
        ->once()
        ->with(['error' => true, 'message' => ''], 422)
        ->andReturn($mockJsonResponse);

    expect(respondJson(true, ''))->toBe($mockJsonResponse);
});




afterEach(function () {
    Mockery::close();
});
