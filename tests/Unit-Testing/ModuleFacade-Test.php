<?php

use Crater\Services\Module\ModuleFacade;

test('getFacadeAccessor returns the correct Module class name string', function () {
    // The getFacadeAccessor method should return the fully qualified class name
    // of the Module class as a string.
    $expectedAccessor = 'Crater\\Services\\Module\\Module';

    // Call the protected static method directly.
    // In Pest/PHPUnit, protected static methods are accessible from test files
    // for testing purposes, although not directly callable from consumer code.
    $actualAccessor = ModuleFacade::getFacadeAccessor();

    // Assert that the returned string precisely matches the expected class name.
    expect($actualAccessor)->toBe($expectedAccessor);
});




afterEach(function () {
    Mockery::close();
});
