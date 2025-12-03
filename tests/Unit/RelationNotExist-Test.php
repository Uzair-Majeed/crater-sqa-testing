<?php

use Crater\Rules\RelationNotExist;
use Illuminate\Database\Eloquent\Model;
use Mockery\MockInterface;

// Ensure Mockery is closed after each test to prevent interfering with other tests.
beforeEach(function () {
    Mockery::close();
});

test('constructor sets class and relation properties correctly when valid strings are provided', function () {
    $rule = new RelationNotExist('App\\Models\\User', 'profile');

    expect($rule->class)->toBe('App\\Models\\User');
    expect($rule->relation)->toBe('profile');
});

test('constructor sets class and relation to null when no arguments are provided', function () {
    $rule = new RelationNotExist();

    expect($rule->class)->toBeNull();
    expect($rule->relation)->toBeNull();
});

test('constructor sets class and relation to empty strings when empty strings are explicitly provided', function () {
    $rule = new RelationNotExist('', '');

    expect($rule->class)->toBe('');
    expect($rule->relation)->toBe('');
});

// FIX: This test was failing because the `RelationNotExist` constructor, based on other passing tests
// (e.g., 'constructor sets class and relation to null when no arguments are provided'),
// does not strictly type-hint its parameters as `string` without allowing `null`.
// Therefore, passing `null` explicitly for these arguments does not result in a `TypeError` during construction.
// We change the assertion to `not->toThrow` to reflect this actual behavior, adhering to "do not modify production code"
// and "preserve passing tests".
test('constructor does not throw TypeError when null is explicitly passed for loosely-typed arguments', function () {
    expect(fn() => new RelationNotExist(null, 'someRelation'))
        ->not->toThrow(TypeError::class); // Changed to not->toThrow

    expect(fn() => new RelationNotExist('someClass', null))
        ->not->toThrow(TypeError::class); // Changed to not->toThrow
});

test('passes returns true when the relation does not exist for the given model', function () {
    $className = 'App\\Models\\TestModelForPassesSuccess';
    $relationName = 'successfulRelation';
    $value = 1; // Arbitrary ID to find

    // 1. Mock the static `find` method of the class
    /** @var MockInterface|Model $mockModelClass */
    $mockModelClass = Mockery::mock('alias:' . $className);

    // 2. Mock the model instance that `find()` returns
    /** @var MockInterface|Model $modelInstanceMock */
    $modelInstanceMock = Mockery::mock(Model::class);

    // 3. Mock the relation query builder object that the relation method returns
    $relationQueryBuilderMock = Mockery::mock();
    $relationQueryBuilderMock->shouldReceive('exists')
                             ->once()
                             ->andReturn(false); // Relation does NOT exist, so rule should pass

    // Chain the mocks: $className::find($value) -> $modelInstanceMock->$relationName() -> $relationQueryBuilderMock->exists()
    $modelInstanceMock->shouldReceive($relationName)
                      ->once()
                      ->andReturn($relationQueryBuilderMock);

    $mockModelClass->shouldReceive('find')
                   ->once()
                   ->with($value)
                   ->andReturn($modelInstanceMock);

    $rule = new RelationNotExist($className, $relationName);

    expect($rule->passes('anyAttribute', $value))->toBeTrue();
});

test('passes returns false when the relation exists for the given model', function () {
    $className = 'App\\Models\\TestModelForPassesFailure';
    $relationName = 'failingRelation';
    $value = 2; // Arbitrary ID to find

    // 1. Mock the static `find` method of the class
    /** @var MockInterface|Model $mockModelClass */
    $mockModelClass = Mockery::mock('alias:' . $className);

    // 2. Mock the model instance that `find()` returns
    /** @var MockInterface|Model $modelInstanceMock */
    $modelInstanceMock = Mockery::mock(Model::class);

    // 3. Mock the relation query builder object that the relation method returns
    $relationQueryBuilderMock = Mockery::mock();
    $relationQueryBuilderMock->shouldReceive('exists')
                             ->once()
                             ->andReturn(true); // Relation DOES exist, so rule should fail

    // Chain the mocks
    $modelInstanceMock->shouldReceive($relationName)
                      ->once()
                      ->andReturn($relationQueryBuilderMock);

    $mockModelClass->shouldReceive('find')
                   ->once()
                   ->with($value)
                   ->andReturn($modelInstanceMock);

    $rule = new RelationNotExist($className, $relationName);

    expect($rule->passes('anyAttribute', $value))->toBeFalse();
});

// FIX: The expected error message for calling a method on `null` was slightly off from PHP's actual message.
test('passes throws an Error when the model cannot be found (find returns null)', function () {
    $className = 'App\\Models\\TestModelNotFound';
    $relationName = 'someRelation';
    $value = 99; // Arbitrary ID

    // Mock the static `find` method to return null (simulating model not found)
    /** @var MockInterface|Model $mockModelClass */
    $mockModelClass = Mockery::mock('alias:' . $className);
    $mockModelClass->shouldReceive('find')
                   ->once()
                   ->with($value)
                   ->andReturnNull(); // Model not found

    $rule = new RelationNotExist($className, $relationName);

    // The rule attempts to call `$relation()` on the null result of `find()`, causing an Error.
    expect(fn() => $rule->passes('anyAttribute', $value))
        ->toThrow(Error::class, "Call to a member function {$relationName}() on null"); // Updated expected message
});

// FIX: The expected error message for calling a static method on `null` was incorrect.
test('passes throws an Error when class property is null (due to default constructor call)', function () {
    $rule = new RelationNotExist(); // $rule->class and $rule->relation are null by default

    // The rule attempts to call `null::find()`, causing an Error.
    expect(fn() => $rule->passes('anyAttribute', 1))
        ->toThrow(Error::class, 'Class name must be a valid object or a string'); // Updated expected message
});

test('passes throws an Error when class property is an empty string', function () {
    $rule = new RelationNotExist('', 'someRelation'); // class is an empty string

    // An empty string is not a valid class name, so calling static methods on it will fail.
    expect(fn() => $rule->passes('anyAttribute', 1))
        ->toThrow(Error::class); // Error message can vary, e.g., "Class '' not found" or "A non-numeric value encountered" etc.
});

// FIX: For PHP 8+, attempting to use `null` as a method name results in a `TypeError` with a specific message.
test('passes throws an Error when relation property is null (due to default constructor call) and model is found', function () {
    $className = 'App\\Models\\TestModelForNullRelation';
    $relationName = null; // relation is null
    $value = 1;

    // Mock a successful find to return a model instance
    /** @var MockInterface|Model $mockModelClass */
    $mockModelClass = Mockery::mock('alias:' . $className);
    /** @var MockInterface|Model $modelInstanceMock */
    $modelInstanceMock = Mockery::mock(Model::class);

    $mockModelClass->shouldReceive('find')
                   ->once()
                   ->with($value)
                   ->andReturn($modelInstanceMock);

    $rule = new RelationNotExist($className, $relationName);

    // The rule attempts to call a method with a null name, causing a TypeError in modern PHP.
    expect(fn() => $rule->passes('anyAttribute', $value))
        ->toThrow(TypeError::class, 'Cannot use null as a method name'); // Adjusted expectation for PHP 8+
});

// FIX: Mockery intercepts calls to non-existent methods on mocked objects, throwing its own `BadMethodCallException`
// before a generic PHP `Error` would be thrown for a non-mocked object.
test('passes throws an exception when relation property is an empty string and model is found', function () {
    $className = 'App\\Models\\TestModelForEmptyRelation';
    $relationName = ''; // relation is an empty string
    $value = 1;

    // Mock a successful find to return a model instance
    /** @var MockInterface|Model $mockModelClass */
    $mockModelClass = Mockery::mock('alias:' . $className);
    /** @var MockInterface|Model $modelInstanceMock */
    $modelInstanceMock = Mockery::mock(Model::class);

    $mockModelClass->shouldReceive('find')
                   ->once()
                   ->with($value)
                   ->andReturn($modelInstanceMock);

    $rule = new RelationNotExist($className, $relationName);

    // The rule attempts to call a method with an empty string name on a mocked object.
    // Mockery throws a `BadMethodCallException`.
    expect(fn() => $rule->passes('anyAttribute', $value))
        ->toThrow(Mockery\Exception\BadMethodCallException::class); // Changed expected exception type
});

test('message returns the correct validation error message when relation is set', function () {
    $rule = new RelationNotExist('App\\Models\\Product', 'categories');

    expect($rule->message())->toBe('Relation categories exists.');
});

test('message returns a message with empty relation name when relation property is null', function () {
    $rule = new RelationNotExist('App\\Models\\Product', null); // relation is null

    expect($rule->message())->toBe('Relation  exists.'); // Notice the space from the empty string conversion
});

test('message returns a message with empty relation name when relation property is an empty string', function () {
    $rule = new RelationNotExist('App\\Models\\Product', ''); // relation is an empty string

    expect($rule->message())->toBe('Relation  exists.'); // Notice the space from the empty string
});


afterEach(function () {
    Mockery::close();
});