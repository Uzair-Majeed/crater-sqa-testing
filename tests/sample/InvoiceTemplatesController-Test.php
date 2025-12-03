```php
<?php

use Illuminate\Http\Request;
use Crater\Models\Invoice;
use Illuminate\Auth\Access\AuthorizationException;

// Helper function to create a partial mock of the controller for isolation
function createInvoiceTemplatesController()
{
    // Create a partial mock to allow mocking of the protected 'authorize' method
    // while still executing the real '__invoke' method.
    return Mockery::mock(
        Crater\Http\Controllers\V1\Admin\Invoice\InvoiceTemplatesController::class
    )->makePartial();
}

test('it successfully returns invoice templates when authorized', function () {
    // Arrange

    // Mock the static method on the Invoice model using Mockery alias.
    // Alias mocks MUST be set up BEFORE the real class is loaded or referenced
    // by any code that might trigger its autoloading.
    $invoiceMock = Mockery::mock('alias:Crater\Models\Invoice');
    $mockTemplates = [
        ['name' => 'Template A', 'id' => 'template-a'],
        ['name' => 'Template B', 'id' => 'template-b'],
    ];
    $invoiceMock->shouldReceive('invoiceTemplates')
        ->once()
        ->andReturn($mockTemplates);

    $request = Mockery::mock(Request::class);

    // Mock the controller's protected authorize method
    $controller = createInvoiceTemplatesController();
    $controller->shouldReceive('authorize')
        ->once()
        // Use the fully qualified class name as a string to avoid any potential
        // issues with the ::class constant resolving *after* the alias is set up,
        // although Invoice::class generally just evaluates to the string.
        ->with('viewAny', 'Crater\Models\Invoice')
        ->andReturn(true); // Simulate successful authorization

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response->getStatusCode())->toBe(200);
    expect($response->original['invoiceTemplates'])->toBe($mockTemplates);
});

test('it returns an empty array when no invoice templates exist but is authorized', function () {
    // Arrange

    // Mock the static method on the Invoice model using Mockery alias.
    // Alias mocks MUST be set up BEFORE the real class is loaded.
    $invoiceMock = Mockery::mock('alias:Crater\Models\Invoice');
    $mockTemplates = []; // Empty array
    $invoiceMock->shouldReceive('invoiceTemplates')
        ->once()
        ->andReturn($mockTemplates);

    $request = Mockery::mock(Request::class);

    // Mock the controller's protected authorize method
    $controller = createInvoiceTemplatesController();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('viewAny', 'Crater\Models\Invoice')
        ->andReturn(true); // Simulate successful authorization

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response->getStatusCode())->toBe(200);
    expect($response->original['invoiceTemplates'])->toBe($mockTemplates);
});

test('it throws an authorization exception if the user is not authorized', function () {
    // Arrange
    $request = Mockery::mock(Request::class);

    // Mock the controller's protected authorize method to throw an exception
    $controller = createInvoiceTemplatesController();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('viewAny', 'Crater\Models\Invoice')
        ->andThrow(new AuthorizationException('Unauthorized to view invoice templates.'));

    // Mock the static method on the Invoice model using Mockery alias.
    // Alias mocks MUST be set up BEFORE the real class is loaded, even if shouldNotReceive.
    $invoiceMock = Mockery::mock('alias:Crater\Models\Invoice');
    // Ensure Invoice::invoiceTemplates() is NOT called if authorization fails
    $invoiceMock->shouldNotReceive('invoiceTemplates');

    // Act & Assert
    $this->expectException(AuthorizationException::class);
    $controller->__invoke($request);
});

afterEach(function () {
    Mockery::close();
});

```