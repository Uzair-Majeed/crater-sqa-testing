<?php

use Carbon\Carbon;
use Crater\Models\Company;
use Crater\Models\CustomField;
use Crater\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Mockery as m;

// Mock the global function getCustomFieldValueKey for testing getDefaultAnswerAttribute
// This assumes getCustomFieldValueKey is defined globally and maps type strings to column names.
// In a real scenario, you might use a package like 'dg/bypass-finals' or 'runkit' for robust global function mocking.
// For simplicity in this context, we'll simulate its behavior by setting the model's 'type' and expected 'answer' attributes.
// The test will rely on the understanding of what getCustomFieldValueKey would return for different types.

beforeEach(function () {
    // Clean up Mockery expectations between tests
    m::close();
});

test('setTimeAnswerAttribute sets time_answer correctly for a valid time string', function () {
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

test('setTimeAnswerAttribute sets time_answer to null for an empty string', function () {
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

test('getDefaultAnswerAttribute returns the correct answer based on type for text', function () {
    $model = new CustomFieldValue();
    $model->type = 'TEXT';
    $model->text_answer = 'This is a text answer.';
    expect($model->defaultAnswer)->toBe('This is a text answer.');
});

test('getDefaultAnswerAttribute returns the correct answer based on type for number', function () {
    $model = new CustomFieldValue();
    $model->type = 'NUMBER';
    $model->number_answer = 12345;
    expect($model->defaultAnswer)->toBe(12345);
});

test('getDefaultAnswerAttribute returns the correct answer based on type for boolean true', function () {
    $model = new CustomFieldValue();
    $model->type = 'BOOLEAN';
    $model->boolean_answer = true;
    expect($model->defaultAnswer)->toBeTrue();
});

test('getDefaultAnswerAttribute returns the correct answer based on type for boolean false', function () {
    $model = new CustomFieldValue();
    $model->type = 'BOOLEAN';
    $model->boolean_answer = false;
    expect($model->defaultAnswer)->toBeFalse();
});

test('getDefaultAnswerAttribute returns the correct answer based on type for date', function () {
    $model = new CustomFieldValue();
    $model->type = 'DATE';
    $date = Carbon::now();
    $model->date_answer = $date;
    // When accessing through the accessor, it returns the Carbon instance due to $dates cast
    expect($model->defaultAnswer)->toEqual($date);
});

test('getDefaultAnswerAttribute returns the correct answer based on type for time', function () {
    $model = new CustomFieldValue();
    $model->type = 'TIME';
    $model->time_answer = '13:00:00';
    expect($model->defaultAnswer)->toBe('13:00:00');
});

test('getDefaultAnswerAttribute returns the correct answer based on type for datetime', function () {
    $model = new CustomFieldValue();
    $model->type = 'DATETIME';
    $datetime = Carbon::now();
    $model->date_time_answer = $datetime;
    expect($model->defaultAnswer)->toEqual($datetime);
});

test('getDefaultAnswerAttribute returns null if the corresponding answer attribute is not set', function () {
    $model = new CustomFieldValue();
    $model->type = 'TEXT';
    // text_answer is not set
    expect($model->defaultAnswer)->toBeNull();

    $model->type = 'NUMBER';
    // number_answer is not set
    expect($model->defaultAnswer)->toBeNull();
});

test('company relationship returns a BelongsTo instance', function () {
    $model = new CustomFieldValue();

    // Mock the belongsTo method on the base Model class
    $mockBelongsTo = m::mock(BelongsTo::class);
    $model->shouldReceive('belongsTo')
        ->once()
        ->with(Company::class)
        ->andReturn($mockBelongsTo);

    $relationship = $model->company();

    expect($relationship)->toBeInstanceOf(BelongsTo::class);
});

test('customField relationship returns a BelongsTo instance', function () {
    $model = new CustomFieldValue();

    // Mock the belongsTo method on the base Model class
    $mockBelongsTo = m::mock(BelongsTo::class);
    $model->shouldReceive('belongsTo')
        ->once()
        ->with(CustomField::class)
        ->andReturn($mockBelongsTo);

    $relationship = $model->customField();

    expect($relationship)->toBeInstanceOf(BelongsTo::class);
});

test('customFieldValuable relationship returns a MorphTo instance', function () {
    $model = new CustomFieldValue();

    // Mock the morphTo method on the base Model class
    $mockMorphTo = m::mock(MorphTo::class);
    $model->shouldReceive('morphTo')
        ->once()
        ->andReturn($mockMorphTo);

    $relationship = $model->customFieldValuable();

    expect($relationship)->toBeInstanceOf(MorphTo::class);
});

test('guarded attributes are set correctly', function () {
    $model = new CustomFieldValue();
    $guarded = (new ReflectionClass($model))->getProperty('guarded')->getValue($model);
    expect($guarded)->toContain('id');
});

test('dates attributes are set correctly', function () {
    $model = new CustomFieldValue();
    $dates = (new ReflectionClass($model))->getProperty('dates')->getValue($model);
    expect($dates)->toContain('date_answer');
    expect($dates)->toContain('date_time_answer');
});

test('appends attributes are set correctly', function () {
    $model = new CustomFieldValue();
    $appends = (new ReflectionClass($model))->getProperty('appends')->getValue($model);
    expect($appends)->toContain('defaultAnswer');
});

 

afterEach(function () {
    Mockery::close();
});
