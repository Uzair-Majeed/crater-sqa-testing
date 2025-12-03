<?php

use Carbon\Carbon;
use Crater\Models\CustomField;
use Crater\Models\CustomFieldValue;
use Crater\Traits\HasCustomFieldsTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Mockery as m; // Used for catching "Attempt to read property (.*) on null"

// Define the global helper function if it doesn't exist.
// This is crucial for running tests in isolation if the function isn't globally loaded.
if (!function_exists('getCustomFieldValueKey')) {
    function getCustomFieldValueKey(string $type): string
    {
        // A dummy implementation mirroring potential Crater logic for custom field types.
        // This maps a custom field type to the database column name where its value is stored.
        return match ($type) {
            'text', 'email', 'textarea', 'url', 'number', 'date', 'select' => 'string_value',
            'checkbox' => 'boolean_value',
            'file' => 'file_value',
            default => 'string_value', // Fallback for unknown types
        };
    }
}

// Dummy model to use the trait for testing purposes
class HasCustomFieldsTraitDummyModel extends Model
{
    use HasCustomFieldsTrait;

    // Required for updateCustomFields to have a value for `company_id`
    public $company_id = 1;
    // Minimal Eloquent setup to prevent errors during mocking
    protected $table = 'trait_dummy_models';
    public $timestamps = false;

    // Override the `boot` method to allow manual control in tests if needed,
    // though `booted` is handled by reflection here.
    protected static function boot()
    {
        parent::boot();
        // The trait's booted method is called here by Eloquent in a real app.
        // For testing, we call it manually via reflection for isolation.
    }
}

// Clean up Mockery mocks before each test to ensure isolation
beforeEach(function () {
    m::close();
    // Reset event listeners for the dummy model to prevent stacking observers.
    // This ensures that the booted test starts with a clean slate for event listeners.
    $dispatcher = HasCustomFieldsTraitDummyModel::getEventDispatcher();
    if ($dispatcher) {
        $reflection = new ReflectionClass($dispatcher);
        $property = $reflection->getProperty('listeners');
        $property->setAccessible(true);
        $property->setValue($dispatcher, []); // Clear all listeners
    }
});

test('fields method returns a morphMany relationship', function () {
    $model = new HasCustomFieldsTraitDummyModel();

    // Mock the internal 'morphMany' method call on the model itself
    $morphManyMock = m::mock(MorphMany::class);
    $model = m::mock($model)->makePartial(); // Use makePartial to allow calling unmocked methods
    $model->shouldReceive('morphMany')
        ->once()
        ->with('Crater\Models\CustomFieldValue', 'custom_field_valuable')
        ->andReturn($morphManyMock);

    $result = $model->fields();

    expect($result)->toBe($morphManyMock);
});

test('booted method registers deleting observer that deletes custom field values if they exist', function () {
    // Manually call the protected static booted method via reflection
    $reflectionMethod = new ReflectionMethod(HasCustomFieldsTraitDummyModel::class, 'booted');
    $reflectionMethod->setAccessible(true);
    $reflectionMethod->invoke(null); // Call static method on null object for a static method

    // Create a mock model instance that will be passed to the deleting event
    $modelInstance = new HasCustomFieldsTraitDummyModel();

    // Create a mock for the CustomFieldValue query builder that fields() would return
    $queryBuilderMock = m::mock(Builder::class);
    $queryBuilderMock->shouldReceive('exists')
        ->once()
        ->andReturn(true); // Simulate fields existing
    $queryBuilderMock->shouldReceive('delete')
        ->once()
        ->andReturn(1); // Simulate successful deletion

    // Mock the fields() method of the model instance to return our query builder mock
    $modelInstance = m::mock($modelInstance)->makePartial();
    $modelInstance->shouldReceive('fields')
        ->once()
        ->andReturn($queryBuilderMock);

    // Manually retrieve and trigger the 'deleting' event for the mock model instance
    // This mimics how Eloquent's event dispatcher would call the registered closure.
    $eventClosure = HasCustomFieldsTraitDummyModel::getEventDispatcher()->getListeners('eloquent.deleting: '.get_class($modelInstance))[0];
    $eventClosure[0]($modelInstance); // Invoke the closure with the mock model

    // Mockery assertions are implicit, they verify expectations when mocks are destructed.
    // A simple assertion ensures the test runs to completion.
    expect(true)->toBeTrue();
});

test('booted method registers deleting observer that does nothing if custom field values do not exist', function () {
    // Manually call the protected static booted method via reflection
    $reflectionMethod = new ReflectionMethod(HasCustomFieldsTraitDummyModel::class, 'booted');
    $reflectionMethod->setAccessible(true);
    $reflectionMethod->invoke(null);

    // Create a mock model instance
    $modelInstance = new HasCustomFieldsTraitDummyModel();

    // Create a mock for the CustomFieldValue query builder
    $queryBuilderMock = m::mock(Builder::class);
    $queryBuilderMock->shouldReceive('exists')
        ->once()
        ->andReturn(false); // Simulate no fields existing
    $queryBuilderMock->shouldNotReceive('delete'); // delete() should NOT be called

    // Mock the fields() method of the model instance
    $modelInstance = m::mock($modelInstance)->makePartial();
    $modelInstance->shouldReceive('fields')
        ->once()
        ->andReturn($queryBuilderMock);

    // Manually trigger the 'deleting' event
    $eventClosure = HasCustomFieldsTraitDummyModel::getEventDispatcher()->getListeners('eloquent.deleting: '.get_class($modelInstance))[0];
    $eventClosure[0]($modelInstance);

    expect(true)->toBeTrue();
});

test('addCustomFields creates custom field values from array input', function () {
    $model = new HasCustomFieldsTraitDummyModel();

    $customFieldData = [
        ['id' => 1, 'value' => 'Text Value'],
        ['id' => 2, 'value' => '99'],
        ['id' => 3, 'value' => 'true'], // Boolean as string
    ];

    // Mock CustomField::find() for each ID
    $customFieldMock1 = m::mock(CustomField::class);
    $customFieldMock1->id = 1;
    $customFieldMock1->type = 'text';
    $customFieldMock1->company_id = 100;

    $customFieldMock2 = m::mock(CustomField::class);
    $customFieldMock2->id = 2;
    $customFieldMock2->type = 'number';
    $customFieldMock2->company_id = 100;

    $customFieldMock3 = m::mock(CustomField::class);
    $customFieldMock3->id = 3;
    $customFieldMock3->type = 'checkbox';
    $customFieldMock3->company_id = 100;

    m::mock('alias:'.CustomField::class)
        ->shouldReceive('find')
        ->with(1)
        ->andReturn($customFieldMock1)
        ->once();
    m::mock('alias:'.CustomField::class)
        ->shouldReceive('find')
        ->with(2)
        ->andReturn($customFieldMock2)
        ->once();
    m::mock('alias:'.CustomField::class)
        ->shouldReceive('find')
        ->with(3)
        ->andReturn($customFieldMock3)
        ->once();

    // Mock the fields() relation and its create() method
    $morphManyMock = m::mock(MorphMany::class);
    $morphManyMock->shouldReceive('create')
        ->with([
            'type' => 'text',
            'custom_field_id' => 1,
            'company_id' => 100,
            'string_value' => 'Text Value', // Uses getCustomFieldValueKey
        ])
        ->once();
    $morphManyMock->shouldReceive('create')
        ->with([
            'type' => 'number',
            'custom_field_id' => 2,
            'company_id' => 100,
            'string_value' => '99', // Uses getCustomFieldValueKey
        ])
        ->once();
    $morphManyMock->shouldReceive('create')
        ->with([
            'type' => 'checkbox',
            'custom_field_id' => 3,
            'company_id' => 100,
            'boolean_value' => 'true', // Uses getCustomFieldValueKey
        ])
        ->once();

    $model = m::mock($model)->makePartial();
    $model->shouldReceive('fields')
        ->times(count($customFieldData)) // Called once for each field in the loop
        ->andReturn($morphManyMock);

    $model->addCustomFields($customFieldData);

    expect(true)->toBeTrue();
});

test('addCustomFields creates custom field values from object input', function () {
    $model = new HasCustomFieldsTraitDummyModel();

    $customFieldData = [
        (object)['id' => 1, 'value' => 'Object Value'],
    ];

    $customFieldMock = m::mock(CustomField::class);
    $customFieldMock->id = 1;
    $customFieldMock->type = 'url';
    $customFieldMock->company_id = 100;

    m::mock('alias:'.CustomField::class)
        ->shouldReceive('find')
        ->with(1)
        ->andReturn($customFieldMock)
        ->once();

    $morphManyMock = m::mock(MorphMany::class);
    $morphManyMock->shouldReceive('create')
        ->with([
            'type' => 'url',
            'custom_field_id' => 1,
            'company_id' => 100,
            'string_value' => 'Object Value',
        ])
        ->once();

    $model = m::mock($model)->makePartial();
    $model->shouldReceive('fields')
        ->once()
        ->andReturn($morphManyMock);

    $model->addCustomFields($customFieldData);

    expect(true)->toBeTrue();
});

test('addCustomFields handles empty custom fields array', function () {
    $model = new HasCustomFieldsTraitDummyModel();

    // None of the internal methods that interact with custom fields should be called
    m::mock('alias:'.CustomField::class)->shouldNotReceive('find');
    $model = m::mock($model)->makePartial();
    $model->shouldNotReceive('fields');

    $model->addCustomFields([]);

    expect(true)->toBeTrue();
});

test('addCustomFields throws error if CustomField not found', function () {
    $model = new HasCustomFieldsTraitDummyModel();

    $customFieldData = [
        ['id' => 999, 'value' => 'Invalid Field Value'],
    ];

    m::mock('alias:'.CustomField::class)
        ->shouldReceive('find')
        ->with(999)
        ->andReturn(null)
        ->once();

    $model = m::mock($model)->makePartial();
    $model->shouldNotReceive('fields'); // fields() should not be called if CustomField is null

    $this->expectException(Error::class);
    $this->expectExceptionMessageMatches('/Attempt to read property (.*) on null/');

    $model->addCustomFields($customFieldData);
});

test('updateCustomFields updates existing custom field values', function () {
    $model = new HasCustomFieldsTraitDummyModel();
    $model->company_id = 200; // Set model's company_id for firstOrCreate comparison

    $customFieldData = [
        ['id' => 1, 'value' => 'Updated Text Value'],
        ['id' => 2, 'value' => 'false'], // Boolean as string
    ];

    // Mock CustomField::find()
    $customFieldMock1 = m::mock(CustomField::class);
    $customFieldMock1->id = 1;
    $customFieldMock1->type = 'text';
    $customFieldMock1->company_id = 100; // This company_id is from CustomField model itself

    $customFieldMock2 = m::mock(CustomField::class);
    $customFieldMock2->id = 2;
    $customFieldMock2->type = 'checkbox';
    $customFieldMock2->company_id = 100;

    m::mock('alias:'.CustomField::class)
        ->shouldReceive('find')
        ->with(1)
        ->andReturn($customFieldMock1)
        ->once();
    m::mock('alias:'.CustomField::class)
        ->shouldReceive('find')
        ->with(2)
        ->andReturn($customFieldMock2)
        ->once();

    // Mock CustomFieldValue model for firstOrCreate and save
    $customFieldValueMock1 = m::mock(CustomFieldValue::class);
    $customFieldValueMock1->shouldReceive('setAttribute')
        ->with('string_value', 'Updated Text Value')
        ->once();
    $customFieldValueMock1->shouldReceive('save')
        ->once()
        ->andReturn(true);

    $customFieldValueMock2 = m::mock(CustomFieldValue::class);
    $customFieldValueMock2->shouldReceive('setAttribute')
        ->with('boolean_value', 'false')
        ->once();
    $customFieldValueMock2->shouldReceive('save')
        ->once()
        ->andReturn(true);

    // Mock the fields() relation and its firstOrCreate() method
    $morphManyMock = m::mock(MorphMany::class);
    $morphManyMock->shouldReceive('firstOrCreate')
        ->with([
            'custom_field_id' => 1,
            'type' => 'text',
            'company_id' => 200, // This is $this->company_id from the dummy model
        ])
        ->andReturn($customFieldValueMock1)
        ->once();
    $morphManyMock->shouldReceive('firstOrCreate')
        ->with([
            'custom_field_id' => 2,
            'type' => 'checkbox',
            'company_id' => 200,
        ])
        ->andReturn($customFieldValueMock2)
        ->once();

    $model = m::mock($model)->makePartial();
    $model->shouldReceive('fields')
        ->times(count($customFieldData))
        ->andReturn($morphManyMock);

    $model->updateCustomFields($customFieldData);

    expect(true)->toBeTrue();
});

test('updateCustomFields handles object input for fields', function () {
    $model = new HasCustomFieldsTraitDummyModel();
    $model->company_id = 200;

    $customFieldData = [
        (object)['id' => 1, 'value' => 'New Value From Object'],
    ];

    $customFieldMock = m::mock(CustomField::class);
    $customFieldMock->id = 1;
    $customFieldMock->type = 'select';
    $customFieldMock->company_id = 100;

    m::mock('alias:'.CustomField::class)
        ->shouldReceive('find')
        ->with(1)
        ->andReturn($customFieldMock)
        ->once();

    $customFieldValueMock = m::mock(CustomFieldValue::class);
    $customFieldValueMock->shouldReceive('setAttribute')
        ->with('string_value', 'New Value From Object')
        ->once();
    $customFieldValueMock->shouldReceive('save')
        ->once()
        ->andReturn(true);

    $morphManyMock = m::mock(MorphMany::class);
    $morphManyMock->shouldReceive('firstOrCreate')
        ->with([
            'custom_field_id' => 1,
            'type' => 'select',
            'company_id' => 200,
        ])
        ->andReturn($customFieldValueMock)
        ->once();

    $model = m::mock($model)->makePartial();
    $model->shouldReceive('fields')
        ->once()
        ->andReturn($morphManyMock);

    $model->updateCustomFields($customFieldData);

    expect(true)->toBeTrue();
});

test('updateCustomFields handles empty custom fields array', function () {
    $model = new HasCustomFieldsTraitDummyModel();

    m::mock('alias:'.CustomField::class)->shouldNotReceive('find');
    $model = m::mock($model)->makePartial();
    $model->shouldNotReceive('fields');

    $model->updateCustomFields([]);

    expect(true)->toBeTrue();
});

test('updateCustomFields throws error if CustomField not found', function () {
    $model = new HasCustomFieldsTraitDummyModel();

    $customFieldData = [
        ['id' => 999, 'value' => 'Invalid Field Value'],
    ];

    m::mock('alias:'.CustomField::class)
        ->shouldReceive('find')
        ->with(999)
        ->andReturn(null)
        ->once();

    $model = m::mock($model)->makePartial();
    $model->shouldNotReceive('fields');

    $this->expectException(Error::class);
    $this->expectExceptionMessageMatches('/Attempt to read property (.*) on null/');

    $model->updateCustomFields($customFieldData);
});

test('getCustomFieldBySlug returns custom field value when found', function () {
    $model = new HasCustomFieldsTraitDummyModel();
    $slug = 'test-field-slug';

    // Mock the CustomFieldValue model that would be returned by `first()`
    $customFieldValueMock = m::mock(CustomFieldValue::class);

    // Mock the Query Builder for `where` and `first` inside the `whereHas` closure
    $queryBuilderMock = m::mock(Builder::class);
    $queryBuilderMock->shouldReceive('where')
        ->once()
        ->with('slug', $slug)
        ->andReturnSelf();
    $queryBuilderMock->shouldReceive('first')
        ->once()
        ->andReturn($customFieldValueMock);

    // Mock the MorphMany relation to chain `with()` and `whereHas()`
    $morphManyMock = m::mock(MorphMany::class);
    $morphManyMock->shouldReceive('with')
        ->once()
        ->with('customField')
        ->andReturnSelf();
    $morphManyMock->shouldReceive('whereHas')
        ->once()
        ->with('customField', m::type(Closure::class))
        ->andReturnUsing(function ($relation, $callback) use ($queryBuilderMock) {
            // Invoke the callback with the mock query builder
            $callback($queryBuilderMock);
            return $queryBuilderMock; // Return the builder for chaining `first()`
        });

    $model = m::mock($model)->makePartial();
    $model->shouldReceive('fields')
        ->once()
        ->andReturn($morphManyMock);

    $result = $model->getCustomFieldBySlug($slug);

    expect($result)->toBe($customFieldValueMock);
});

test('getCustomFieldBySlug returns null when not found', function () {
    $model = new HasCustomFieldsTraitDummyModel();
    $slug = 'non-existent-slug';

    // Mock the Query Builder for `where` and `first`
    $queryBuilderMock = m::mock(Builder::class);
    $queryBuilderMock->shouldReceive('where')
        ->once()
        ->with('slug', $slug)
        ->andReturnSelf();
    $queryBuilderMock->shouldReceive('first')
        ->once()
        ->andReturn(null);

    // Mock the MorphMany relation for chaining
    $morphManyMock = m::mock(MorphMany::class);
    $morphManyMock->shouldReceive('with')
        ->once()
        ->with('customField')
        ->andReturnSelf();
    $morphManyMock->shouldReceive('whereHas')
        ->once()
        ->with('customField', m::type(Closure::class))
        ->andReturnUsing(function ($relation, $callback) use ($queryBuilderMock) {
            $callback($queryBuilderMock);
            return $queryBuilderMock;
        });

    $model = m::mock($model)->makePartial();
    $model->shouldReceive('fields')
        ->once()
        ->andReturn($morphManyMock);

    $result = $model->getCustomFieldBySlug($slug);

    expect($result)->toBeNull();
});

test('getCustomFieldValueBySlug returns defaultAnswer when custom field value is found', function () {
    $model = new HasCustomFieldsTraitDummyModel();
    $slug = 'value-slug';
    $expectedDefaultAnswer = 'The actual value of the custom field';

    // Mock the CustomFieldValue model that getCustomFieldBySlug would return
    $customFieldValueMock = m::mock(CustomFieldValue::class);
    // The trait accesses $value->defaultAnswer
    $customFieldValueMock->defaultAnswer = $expectedDefaultAnswer;

    // Mock the internal call to getCustomFieldBySlug()
    $model = m::mock($model)->makePartial();
    $model->shouldReceive('getCustomFieldBySlug')
        ->once()
        ->with($slug)
        ->andReturn($customFieldValueMock);

    $result = $model->getCustomFieldValueBySlug($slug);

    expect($result)->toBe($expectedDefaultAnswer);
});

test('getCustomFieldValueBySlug returns null when custom field value is not found', function () {
    $model = new HasCustomFieldsTraitDummyModel();
    $slug = 'not-found-slug';

    // Mock the internal call to getCustomFieldBySlug() to return null
    $model = m::mock($model)->makePartial();
    $model->shouldReceive('getCustomFieldBySlug')
        ->once()
        ->with($slug)
        ->andReturn(null);

    $result = $model->getCustomFieldValueBySlug($slug);

    expect($result)->toBeNull();
});




afterEach(function () {
    Mockery::close();
});
