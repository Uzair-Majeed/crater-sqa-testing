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
    $request = Mockery::mock(Request::class);
    $mockTemplates = [
        ['name' => 'Template A', 'id' => 'template-a'],
        ['name' => 'Template B', 'id' => 'template-b'],
    ];

    // Mock the static method on the Invoice model using Mockery alias
    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('invoiceTemplates')
        ->once()
        ->andReturn($mockTemplates);

    // Mock the controller's protected authorize method
    $controller = createInvoiceTemplatesController();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('viewAny', Invoice::class)
        ->andReturn(true); // Simulate successful authorization

    // Act
    $response = $controller->__invoke($request);

    // Assert
    expect($response->getStatusCode())->toBe(200);
    expect($response->original['invoiceTemplates'])->toBe($mockTemplates);
});

test('it returns an empty array when no invoice templates exist but is authorized', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $mockTemplates = []; // Empty array

    // Mock the static method on the Invoice model
    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldReceive('invoiceTemplates')
        ->once()
        ->andReturn($mockTemplates);

    // Mock the controller's protected authorize method
    $controller = createInvoiceTemplatesController();
    $controller->shouldReceive('authorize')
        ->once()
        ->with('viewAny', Invoice::class)
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
        ->with('viewAny', Invoice::class)
        ->andThrow(new AuthorizationException('Unauthorized to view invoice templates.'));

    // Ensure Invoice::invoiceTemplates() is NOT called if authorization fails
    Mockery::mock('alias:Crater\Models\Invoice')
        ->shouldNotReceive('invoiceTemplates');

    // Act & Assert
    $this->expectException(AuthorizationException::class);
    $controller->__invoke($request);
});

// Clean up mocks after each test




afterEach(function () {
    Mockery::close();
});
