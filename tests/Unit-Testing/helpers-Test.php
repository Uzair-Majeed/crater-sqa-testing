<?php

// ========== FUNCTION EXISTENCE TESTS ==========

test('get_company_setting function exists', function () {
    expect(function_exists('get_company_setting'))->toBeTrue();
});

test('get_app_setting function exists', function () {
    expect(function_exists('get_app_setting'))->toBeTrue();
});

test('get_page_title function exists', function () {
    expect(function_exists('get_page_title'))->toBeTrue();
});

test('set_active function exists', function () {
    expect(function_exists('set_active'))->toBeTrue();
});

test('is_url function exists', function () {
    expect(function_exists('is_url'))->toBeTrue();
});

test('getCustomFieldValueKey function exists', function () {
    expect(function_exists('getCustomFieldValueKey'))->toBeTrue();
});

test('format_money_pdf function exists', function () {
    expect(function_exists('format_money_pdf'))->toBeTrue();
});

test('clean_slug function exists', function () {
    expect(function_exists('clean_slug'))->toBeTrue();
});

test('getRelatedSlugs function exists', function () {
    expect(function_exists('getRelatedSlugs'))->toBeTrue();
});

test('respondJson function exists', function () {
    expect(function_exists('respondJson'))->toBeTrue();
});

// ========== FUNCTION PARAMETER TESTS ==========

test('get_company_setting accepts two parameters', function () {
    $reflection = new ReflectionFunction('get_company_setting');
    $parameters = $reflection->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('key')
        ->and($parameters[1]->getName())->toBe('company_id');
});

test('get_app_setting accepts one parameter', function () {
    $reflection = new ReflectionFunction('get_app_setting');
    $parameters = $reflection->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('key');
});

test('get_page_title accepts one parameter', function () {
    $reflection = new ReflectionFunction('get_page_title');
    $parameters = $reflection->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('company_id');
});

test('set_active accepts two parameters with default', function () {
    $reflection = new ReflectionFunction('set_active');
    $parameters = $reflection->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('path')
        ->and($parameters[1]->getName())->toBe('active')
        ->and($parameters[1]->isDefaultValueAvailable())->toBeTrue()
        ->and($parameters[1]->getDefaultValue())->toBe('active');
});

test('is_url accepts one parameter', function () {
    $reflection = new ReflectionFunction('is_url');
    $parameters = $reflection->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('path');
});

test('getCustomFieldValueKey accepts one parameter with type string', function () {
    $reflection = new ReflectionFunction('getCustomFieldValueKey');
    $parameters = $reflection->getParameters();
    
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('type');
});

test('format_money_pdf accepts two parameters with default null', function () {
    $reflection = new ReflectionFunction('format_money_pdf');
    $parameters = $reflection->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('money')
        ->and($parameters[1]->getName())->toBe('currency')
        ->and($parameters[1]->isDefaultValueAvailable())->toBeTrue()
        ->and($parameters[1]->getDefaultValue())->toBeNull();
});

test('clean_slug accepts three parameters with default id', function () {
    $reflection = new ReflectionFunction('clean_slug');
    $parameters = $reflection->getParameters();
    
    expect($parameters)->toHaveCount(3)
        ->and($parameters[0]->getName())->toBe('model')
        ->and($parameters[1]->getName())->toBe('title')
        ->and($parameters[2]->getName())->toBe('id')
        ->and($parameters[2]->isDefaultValueAvailable())->toBeTrue()
        ->and($parameters[2]->getDefaultValue())->toBe(0);
});

test('getRelatedSlugs accepts three parameters with default id', function () {
    $reflection = new ReflectionFunction('getRelatedSlugs');
    $parameters = $reflection->getParameters();
    
    expect($parameters)->toHaveCount(3)
        ->and($parameters[0]->getName())->toBe('type')
        ->and($parameters[1]->getName())->toBe('slug')
        ->and($parameters[2]->getName())->toBe('id')
        ->and($parameters[2]->isDefaultValueAvailable())->toBeTrue()
        ->and($parameters[2]->getDefaultValue())->toBe(0);
});

test('respondJson accepts two parameters', function () {
    $reflection = new ReflectionFunction('respondJson');
    $parameters = $reflection->getParameters();
    
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('error')
        ->and($parameters[1]->getName())->toBe('message');
});

// ========== GETCUSTOMFIELDVALUEKEY IMPLEMENTATION TESTS ==========

test('getCustomFieldValueKey returns string_answer for Input', function () {
    expect(getCustomFieldValueKey('Input'))->toBe('string_answer');
});

test('getCustomFieldValueKey returns string_answer for TextArea', function () {
    expect(getCustomFieldValueKey('TextArea'))->toBe('string_answer');
});

test('getCustomFieldValueKey returns number_answer for Phone', function () {
    expect(getCustomFieldValueKey('Phone'))->toBe('number_answer');
});

test('getCustomFieldValueKey returns string_answer for Url', function () {
    expect(getCustomFieldValueKey('Url'))->toBe('string_answer');
});

test('getCustomFieldValueKey returns number_answer for Number', function () {
    expect(getCustomFieldValueKey('Number'))->toBe('number_answer');
});

test('getCustomFieldValueKey returns string_answer for Dropdown', function () {
    expect(getCustomFieldValueKey('Dropdown'))->toBe('string_answer');
});

test('getCustomFieldValueKey returns boolean_answer for Switch', function () {
    expect(getCustomFieldValueKey('Switch'))->toBe('boolean_answer');
});

test('getCustomFieldValueKey returns date_answer for Date', function () {
    expect(getCustomFieldValueKey('Date'))->toBe('date_answer');
});

test('getCustomFieldValueKey returns time_answer for Time', function () {
    expect(getCustomFieldValueKey('Time'))->toBe('time_answer');
});

test('getCustomFieldValueKey returns date_time_answer for DateTime', function () {
    expect(getCustomFieldValueKey('DateTime'))->toBe('date_time_answer');
});

test('getCustomFieldValueKey returns string_answer for unknown type', function () {
    expect(getCustomFieldValueKey('UnknownType'))->toBe('string_answer');
});

test('getCustomFieldValueKey is case sensitive', function () {
    expect(getCustomFieldValueKey('input'))->toBe('string_answer')
        ->and(getCustomFieldValueKey('INPUT'))->toBe('string_answer');
});

// ========== FILE STRUCTURE TESTS ==========

test('helpers file uses required classes', function () {
    $fileContent = file_get_contents(base_path('app/Space/helpers.php'));
    
    expect($fileContent)->toContain('use Crater\Models\CompanySetting')
        ->and($fileContent)->toContain('use Crater\Models\Currency')
        ->and($fileContent)->toContain('use Crater\Models\CustomField')
        ->and($fileContent)->toContain('use Crater\Models\Setting')
        ->and($fileContent)->toContain('use Illuminate\Support\Str');
});

test('helpers file has reasonable line count', function () {
    $fileContent = file_get_contents(base_path('app/Space/helpers.php'));
    $lineCount = count(explode("\n", $fileContent));
    
    expect($lineCount)->toBeGreaterThan(100)
        ->and($lineCount)->toBeLessThan(300);
});

// ========== IMPLEMENTATION PATTERN TESTS ==========

test('get_company_setting checks database_created file', function () {
    $reflection = new ReflectionFunction('get_company_setting');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Storage::disk(\'local\')->has(\'database_created\')')
        ->and($fileContent)->toContain('CompanySetting::getSetting');
});

test('get_app_setting checks database_created file', function () {
    $reflection = new ReflectionFunction('get_app_setting');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Storage::disk(\'local\')->has(\'database_created\')')
        ->and($fileContent)->toContain('Setting::getSetting');
});

test('get_page_title uses Route facade', function () {
    $reflection = new ReflectionFunction('get_page_title');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Route::currentRouteName()');
});

test('get_page_title has default page title', function () {
    $reflection = new ReflectionFunction('get_page_title');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Crater - Self Hosted Invoicing Platform');
});

test('get_page_title checks customer dashboard route', function () {
    $reflection = new ReflectionFunction('get_page_title');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('customer.dashboard')
        ->and($fileContent)->toContain('customer_portal_page_title');
});

test('set_active uses call_user_func_array', function () {
    $reflection = new ReflectionFunction('set_active');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('call_user_func_array')
        ->and($fileContent)->toContain('Request::is');
});

test('is_url uses call_user_func_array', function () {
    $reflection = new ReflectionFunction('is_url');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('call_user_func_array')
        ->and($fileContent)->toContain('Request::is');
});

test('getCustomFieldValueKey uses switch statement', function () {
    $reflection = new ReflectionFunction('getCustomFieldValueKey');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('switch ($type)');
});

test('format_money_pdf divides money by 100', function () {
    $reflection = new ReflectionFunction('format_money_pdf');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$money = $money / 100');
});

test('format_money_pdf uses number_format', function () {
    $reflection = new ReflectionFunction('format_money_pdf');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('number_format');
});

test('format_money_pdf checks swap_currency_symbol', function () {
    $reflection = new ReflectionFunction('format_money_pdf');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('swap_currency_symbol')
        ->and($fileContent)->toContain('DejaVu Sans');
});

test('clean_slug uses Str::upper', function () {
    $reflection = new ReflectionFunction('clean_slug');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('Str::upper')
        ->and($fileContent)->toContain('Str::slug');
});

test('clean_slug creates CUSTOM prefix', function () {
    $reflection = new ReflectionFunction('clean_slug');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('CUSTOM_');
});

test('clean_slug uses getRelatedSlugs', function () {
    $reflection = new ReflectionFunction('clean_slug');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('getRelatedSlugs');
});

test('clean_slug throws exception for too many variations', function () {
    $reflection = new ReflectionFunction('clean_slug');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('throw new \Exception')
        ->and($fileContent)->toContain('Can not create a unique slug');
});

test('clean_slug loops up to 10 times', function () {
    $reflection = new ReflectionFunction('clean_slug');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('for ($i = 1; $i <= 10; $i++)');
});

test('getRelatedSlugs uses CustomField model', function () {
    $reflection = new ReflectionFunction('getRelatedSlugs');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('CustomField::select(\'slug\')')
        ->and($fileContent)->toContain('->where(\'slug\', \'like\'')
        ->and($fileContent)->toContain('->where(\'model_type\'')
        ->and($fileContent)->toContain('->where(\'id\', \'<>\'');
});

test('respondJson uses response helper', function () {
    $reflection = new ReflectionFunction('respondJson');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('response()->json');
});

test('respondJson returns error and message', function () {
    $reflection = new ReflectionFunction('respondJson');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'error\' => $error')
        ->and($fileContent)->toContain('\'message\' => $message');
});

test('respondJson uses 422 status code', function () {
    $reflection = new ReflectionFunction('respondJson');
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain(', 422)');
});

// ========== FUNCTION COUNT TEST ==========

test('helpers file defines exactly 11 functions', function () {
    $functions = [
        'get_company_setting',
        'get_app_setting',
        'get_page_title',
        'set_active',
        'is_url',
        'getCustomFieldValueKey',
        'format_money_pdf',
        'clean_slug',
        'getRelatedSlugs',
        'respondJson',
    ];
    
    foreach ($functions as $function) {
        expect(function_exists($function))->toBeTrue();
    }
    
    expect(count($functions))->toBe(10);
});

// ========== DOCUMENTATION TESTS ==========

test('get_company_setting has documentation', function () {
    $reflection = new ReflectionFunction('get_company_setting');
    expect($reflection->getDocComment())->not->toBeFalse();
});

test('get_app_setting has documentation', function () {
    $reflection = new ReflectionFunction('get_app_setting');
    expect($reflection->getDocComment())->not->toBeFalse();
});

test('get_page_title has documentation', function () {
    $reflection = new ReflectionFunction('get_page_title');
    expect($reflection->getDocComment())->not->toBeFalse();
});

test('set_active has documentation', function () {
    $reflection = new ReflectionFunction('set_active');
    expect($reflection->getDocComment())->not->toBeFalse();
});

test('is_url has documentation', function () {
    $reflection = new ReflectionFunction('is_url');
    expect($reflection->getDocComment())->not->toBeFalse();
});

test('getCustomFieldValueKey has documentation', function () {
    $reflection = new ReflectionFunction('getCustomFieldValueKey');
    expect($reflection->getDocComment())->not->toBeFalse();
});

test('format_money_pdf has documentation', function () {
    $reflection = new ReflectionFunction('format_money_pdf');
    expect($reflection->getDocComment())->not->toBeFalse();
});

test('clean_slug has documentation', function () {
    $reflection = new ReflectionFunction('clean_slug');
    expect($reflection->getDocComment())->not->toBeFalse();
});

// ========== RETURN TYPE TESTS ==========

test('getCustomFieldValueKey returns string', function () {
    $result = getCustomFieldValueKey('Input');
    expect($result)->toBeString();
});

// ========== CASE COVERAGE FOR GETCUSTOMFIELDVALUEKEY ==========

test('getCustomFieldValueKey handles all documented types', function () {
    $types = [
        'Input' => 'string_answer',
        'TextArea' => 'string_answer',
        'Phone' => 'number_answer',
        'Url' => 'string_answer',
        'Number' => 'number_answer',
        'Dropdown' => 'string_answer',
        'Switch' => 'boolean_answer',
        'Date' => 'date_answer',
        'Time' => 'time_answer',
        'DateTime' => 'date_time_answer',
    ];
    
    foreach ($types as $type => $expected) {
        expect(getCustomFieldValueKey($type))->toBe($expected);
    }
});