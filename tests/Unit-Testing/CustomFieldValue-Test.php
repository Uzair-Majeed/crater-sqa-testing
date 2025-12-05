<?php

use Carbon\Carbon;
use Crater\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// Test setTimeAnswerAttribute method
test('setTimeAnswerAttribute sets time_answer correctly for valid time strings', function () {
    $model = new CustomFieldValue();
    $model->setTimeAnswerAttribute('10:30 AM');
    expect($model->time_answer)->toBe('10:30:00');

    $model = new CustomFieldValue();
    $model->setTimeAnswerAttribute('14:45:15');
    expect($model->time_answer)->toBe('14:45:15');

    $model = new CustomFieldValue();
    $model->setTimeAnswerAttribute('2pm');
    expect($model->time_answer)->toBe('14:00:00');
});

test('setTimeAnswerAttribute sets time_answer to null for empty string', function () {
    $model = new CustomFieldValue();
    $model->setTimeAnswerAttribute('');
    expect($model->time_answer)->toBeNull();
});

test('setTimeAnswerAttribute sets time_answer to null for null input', function () {
    $model = new CustomFieldValue();
    $model->setTimeAnswerAttribute(null);
    expect($model->time_answer)->toBeNull();
});

test('setTimeAnswerAttribute handles various valid time formats', function () {
    $model = new CustomFieldValue();
    $model->setTimeAnswerAttribute('5:00 PM');
    expect($model->time_answer)->toBe('17:00:00');

    $model = new CustomFieldValue();
    $model->setTimeAnswerAttribute('08:00');
    expect($model->time_answer)->toBe('08:00:00');
});

test('setTimeAnswerAttribute handles midnight and noon', function () {
    $model = new CustomFieldValue();
    $model->setTimeAnswerAttribute('12:00 AM');
    expect($model->time_answer)->toBe('00:00:00');

    $model = new CustomFieldValue();
    $model->setTimeAnswerAttribute('12:00 PM');
    expect($model->time_answer)->toBe('12:00:00');
});

test('setTimeAnswerAttribute handles 24-hour format', function () {
    $model = new CustomFieldValue();
    $model->setTimeAnswerAttribute('23:59:59');
    expect($model->time_answer)->toBe('23:59:59');

    $model = new CustomFieldValue();
    $model->setTimeAnswerAttribute('00:00:00');
    expect($model->time_answer)->toBe('00:00:00');
});

// Test relationships
test('company relationship returns a BelongsTo instance', function () {
    $model = new CustomFieldValue();
    $relationship = $model->company();
    expect($relationship)->toBeInstanceOf(BelongsTo::class);
});

test('customField relationship returns a BelongsTo instance', function () {
    $model = new CustomFieldValue();
    $relationship = $model->customField();
    expect($relationship)->toBeInstanceOf(BelongsTo::class);
});

test('customFieldValuable relationship returns a MorphTo instance', function () {
    $model = new CustomFieldValue();
    $relationship = $model->customFieldValuable();
    expect($relationship)->toBeInstanceOf(MorphTo::class);
});

// Test model properties
test('guarded attributes are set correctly', function () {
    $model = new CustomFieldValue();
    $guardedProperty = (new ReflectionClass($model))->getProperty('guarded');
    $guardedProperty->setAccessible(true);
    $guarded = $guardedProperty->getValue($model);
    expect($guarded)->toContain('id');
});

test('dates attributes are set correctly', function () {
    $model = new CustomFieldValue();
    $datesProperty = (new ReflectionClass($model))->getProperty('dates');
    $datesProperty->setAccessible(true);
    $dates = $datesProperty->getValue($model);
    expect($dates)->toContain('date_answer');
    expect($dates)->toContain('date_time_answer');
});

test('appends attributes are set correctly', function () {
    $model = new CustomFieldValue();
    $appendsProperty = (new ReflectionClass($model))->getProperty('appends');
    $appendsProperty->setAccessible(true);
    $appends = $appendsProperty->getValue($model);
    expect($appends)->toContain('defaultAnswer');
});

// Test model instantiation
test('model can be instantiated without errors', function () {
    $model = new CustomFieldValue();
    expect($model)->toBeInstanceOf(CustomFieldValue::class);
});

// Test attribute setting
test('model can set and get text_answer attribute', function () {
    $model = new CustomFieldValue();
    $model->text_answer = 'Test text answer';
    expect($model->text_answer)->toBe('Test text answer');
});

test('model can set and get number_answer attribute', function () {
    $model = new CustomFieldValue();
    $model->number_answer = 12345;
    expect($model->number_answer)->toBe(12345);
});

test('model can set and get boolean_answer attribute', function () {
    $model = new CustomFieldValue();
    $model->boolean_answer = true;
    expect($model->boolean_answer)->toBeTrue();

    $model->boolean_answer = false;
    expect($model->boolean_answer)->toBeFalse();
});


// Test that model uses HasFactory trait
test('model uses HasFactory trait', function () {
    $model = new CustomFieldValue();
    $traits = class_uses($model);
    expect($traits)->toContain(\Illuminate\Database\Eloquent\Factories\HasFactory::class);
});

// Test relationship method existence
test('model has all required relationship methods', function () {
    $model = new CustomFieldValue();
    expect(method_exists($model, 'company'))->toBeTrue();
    expect(method_exists($model, 'customField'))->toBeTrue();
    expect(method_exists($model, 'customFieldValuable'))->toBeTrue();
});

// Test accessor method existence
test('model has getDefaultAnswerAttribute accessor', function () {
    $model = new CustomFieldValue();
    expect(method_exists($model, 'getDefaultAnswerAttribute'))->toBeTrue();
});

// Test mutator method existence
test('model has setTimeAnswerAttribute mutator', function () {
    $model = new CustomFieldValue();
    expect(method_exists($model, 'setTimeAnswerAttribute'))->toBeTrue();
});

// Test multiple time formats in sequence
test('setTimeAnswerAttribute can handle multiple calls with different formats', function () {
    $model = new CustomFieldValue();
    
    $model->setTimeAnswerAttribute('9:00 AM');
    expect($model->time_answer)->toBe('09:00:00');
    
    $model->setTimeAnswerAttribute('3:30 PM');
    expect($model->time_answer)->toBe('15:30:00');
    
    $model->setTimeAnswerAttribute('18:45');
    expect($model->time_answer)->toBe('18:45:00');
});

// Test edge cases for time
test('setTimeAnswerAttribute handles edge case times', function () {
    $model = new CustomFieldValue();
    
    $model->setTimeAnswerAttribute('11:59 PM');
    expect($model->time_answer)->toBe('23:59:00');
    
    $model = new CustomFieldValue();
    $model->setTimeAnswerAttribute('1:00 AM');
    expect($model->time_answer)->toBe('01:00:00');
});

// Test that model extends Model
test('CustomFieldValue extends Eloquent Model', function () {
    $model = new CustomFieldValue();
    expect($model)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class);
});

// Test type attribute can be set
test('model can set and get type attribute', function () {
    $model = new CustomFieldValue();
    $model->type = 'TEXT';
    expect($model->type)->toBe('TEXT');
    
    $model->type = 'NUMBER';
    expect($model->type)->toBe('NUMBER');
    
    $model->type = 'BOOLEAN';
    expect($model->type)->toBe('BOOLEAN');
});

// Test that guarded does not include commonly used fields
test('guarded does not prevent setting common fields', function () {
    $model = new CustomFieldValue();
    $guardedProperty = (new ReflectionClass($model))->getProperty('guarded');
    $guardedProperty->setAccessible(true);
    $guarded = $guardedProperty->getValue($model);
    
    expect($guarded)->not->toContain('text_answer');
    expect($guarded)->not->toContain('number_answer');
    expect($guarded)->not->toContain('boolean_answer');
    expect($guarded)->not->toContain('time_answer');
});