```php
<?php

use Crater\Models\Company;
use Crater\Models\Note;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;
use Mockery\MockInterface;

// Helper function to create a mock query builder for scopes
function createMockQueryBuilder(): MockInterface
{
    return Mockery::mock(Builder::class);
}

test('note has a company relationship defined correctly', function () {
    $note = new Note();

    // Call the company method to get the relationship object
    $relation = $note->company();

    // Assert that the returned object is an instance of BelongsTo
    expect($relation)->toBeInstanceOf(BelongsTo::class);

    // Assert that the related model is Company::class
    expect($relation->getRelated())->toBeInstanceOf(Company::class);
    expect($relation->getForeignKeyName())->toBe('company_id'); // Default foreign key
    expect($relation->getOwnerKeyName())->toBe('id'); // Default owner key
});

test('scopeApplyFilters applies type filter when "type" is present', function () {
    $mockQuery = createMockQueryBuilder();
    $typeValue = 'expense';

    // Expect the whereType scope to be called exactly once with the correct value
    $mockQuery->shouldReceive('whereType')
              ->once()
              ->with($typeValue)
              ->andReturnSelf(); // Ensure the chain continues for other potential scopes

    $note = new Note();
    $note->scopeApplyFilters($mockQuery, ['type' => $typeValue]);

    // Mockery's expectations will fail the test if `whereType` is not called as expected.
});

test('scopeApplyFilters applies search filter when "search" is present', function () {
    $mockQuery = createMockQueryBuilder();
    $searchValue = 'search term';

    // Expect the whereSearch scope to be called exactly once with the correct value
    $mockQuery->shouldReceive('whereSearch')
              ->once()
              ->with($searchValue)
              ->andReturnSelf();

    $note = new Note();
    $note->scopeApplyFilters($mockQuery, ['search' => $searchValue]);
});

test('scopeApplyFilters applies both type and search filters when both are present', function () {
    $mockQuery = createMockQueryBuilder();
    $typeValue = 'income';
    $searchValue = 'another term';

    // Expect both scopes to be called
    $mockQuery->shouldReceive('whereType')
              ->once()
              ->with($typeValue)
              ->andReturnSelf();

    $mockQuery->shouldReceive('whereSearch')
              ->once()
              ->with($searchValue)
              ->andReturnSelf();

    $note = new Note();
    $note->scopeApplyFilters($mockQuery, [
        'type' => $typeValue,
        'search' => $searchValue,
    ]);
});

test('scopeApplyFilters applies no filters when filters array is empty', function () {
    $mockQuery = createMockQueryBuilder();

    // Expect neither whereType nor whereSearch to be called
    $mockQuery->shouldNotReceive('whereType');
    $mockQuery->shouldNotReceive('whereSearch');

    $note = new Note();
    $note->scopeApplyFilters($mockQuery, []);
});

test('scopeApplyFilters applies no filters when filter values are empty strings', function () {
    $mockQuery = createMockQueryBuilder();

    // Empty strings are falsy in PHP's `if` condition, so the scopes should not be called
    $mockQuery->shouldNotReceive('whereType');
    $mockQuery->shouldNotReceive('whereSearch');

    $note = new Note();
    $note->scopeApplyFilters($mockQuery, ['type' => '', 'search' => '']);
});

test('scopeApplyFilters applies no filters when filter values are null', function () {
    $mockQuery = createMockQueryBuilder();

    // Null values are falsy in PHP's `if` condition, so the scopes should not be called
    $mockQuery->shouldNotReceive('whereType');
    $mockQuery->shouldNotReceive('whereSearch');

    $note = new Note();
    $note->scopeApplyFilters($mockQuery, ['type' => null, 'search' => null]);
});

test('scopeWhereSearch applies correct LIKE clause for a valid search term', function () {
    $mockQuery = createMockQueryBuilder();
    $searchTerm = 'test note content';
    $expectedLike = '%' . $searchTerm . '%';

    // Expect the 'where' method on the query builder to be called with correct arguments
    $mockQuery->shouldReceive('where')
              ->once()
              ->with('name', 'LIKE', $expectedLike)
              ->andReturnSelf();

    $note = new Note();
    $note->scopeWhereSearch($mockQuery, $searchTerm);
});

test('scopeWhereSearch handles an empty search term', function () {
    $mockQuery = createMockQueryBuilder();
    $searchTerm = '';
    $expectedLike = '%' . $searchTerm . '%';

    $mockQuery->shouldReceive('where')
              ->once()
              ->with('name', 'LIKE', $expectedLike)
              ->andReturnSelf();

    $note = new Note();
    $note->scopeWhereSearch($mockQuery, $searchTerm);
});

test('scopeWhereType applies correct equality WHERE clause for a valid type', function () {
    $mockQuery = createMockQueryBuilder();
    $typeValue = 'general';

    // Expect the 'where' method on the query builder to be called with correct arguments
    $mockQuery->shouldReceive('where')
              ->once()
              ->with('type', $typeValue)
              ->andReturnSelf();

    $note = new Note();
    $result = $note->scopeWhereType($mockQuery, $typeValue);

    // Ensure the query builder instance is returned for chaining
    expect($result)->toBe($mockQuery);
});

test('scopeWhereType handles an empty type value', function () {
    $mockQuery = createMockQueryBuilder();
    $typeValue = '';

    $mockQuery->shouldReceive('where')
              ->once()
              ->with('type', $typeValue)
              ->andReturnSelf();

    $note = new Note();
    $note->scopeWhereType($mockQuery, $typeValue);
});

test('scopeWhereCompany applies filter with a present company header', function () {
    $mockQuery = createMockQueryBuilder();
    $companyId = '123-abc-company-id';

    // Mock the Request facade to control the header method's return value
    Request::shouldReceive('header')
           ->once()
           ->with('company')
           ->andReturn($companyId);

    // FIX: Add an expectation for 'setUserResolver'. This method is called internally
    // by Laravel's AuthServiceProvider during request rebinding when a Request
    // instance (which is our mock in this case) is resolved from the container.
    // Without this, Mockery throws a BadMethodCallException because it's an
    // unexpected call on the mock.
    Request::shouldReceive('setUserResolver')->andReturnSelf();

    // Expect the 'where' method on the query builder to be called
    $mockQuery->shouldReceive('where')
              ->once()
              ->with('notes.company_id', $companyId)
              ->andReturnSelf();

    $note = new Note();
    $note->scopeWhereCompany($mockQuery);
});

test('scopeWhereCompany applies filter with a null company header', function () {
    $mockQuery = createMockQueryBuilder();
    $companyId = null; // Simulate the header not being present or empty

    // Mock the Request facade to return null for the 'company' header
    Request::shouldReceive('header')
           ->once()
           ->with('company')
           ->andReturn($companyId);

    // FIX: Add an expectation for 'setUserResolver' as explained in the previous test.
    Request::shouldReceive('setUserResolver')->andReturnSelf();

    // Even if the header is null, the where clause should still be applied with null
    $mockQuery->shouldReceive('where')
              ->once()
              ->with('notes.company_id', $companyId)
              ->andReturnSelf();

    $note = new Note();
    $note->scopeWhereCompany($mockQuery);
});

// Clean up Mockery expectations after each test to prevent conflicts
afterEach(function () {
    Mockery::close();
});
```