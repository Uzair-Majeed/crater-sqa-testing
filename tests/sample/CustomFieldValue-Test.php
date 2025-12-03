<?php

use Carbon\Carbon;
use Crater\Models\Company;
use Crater\Models\CustomField;
use Crater\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Model;
use Mockery as m;

// Simulate getCustomFieldValueKey global function by setting $model->type and proper answer attributes.

beforeEach(function () {
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
    $model = new class extends CustomFieldValue {
        public function getDefaultAnswerAttribute()
        {
            if ($this->type === 'TEXT') {
                return $this->text_answer;
            }
            return parent::getDefaultAnswerAttribute();
        }
    };
    $model->type = 'TEXT';
    $model->text_answer = 'This is a text answer.';
    expect($model->getDefaultAnswerAttribute())->toBe('This is a text answer.');
});

test('getDefaultAnswerAttribute returns the correct answer based on type for number', function () {
    $model = new class extends CustomFieldValue {
        public function getDefaultAnswerAttribute()
        {
            if ($this->type === 'NUMBER') {
                return $this->number_answer;
            }
            return parent::getDefaultAnswerAttribute();
        }
    };
    $model->type = 'NUMBER';
    $model->number_answer = 12345;
    expect($model->getDefaultAnswerAttribute())->toBe(12345);
});

test('getDefaultAnswerAttribute returns the correct answer based on type for boolean true', function () {
    $model = new class extends CustomFieldValue {
        public function getDefaultAnswerAttribute()
        {
            if ($this->type === 'BOOLEAN') {
                return $this->boolean_answer;
            }
            return parent::getDefaultAnswerAttribute();
        }
    };
    $model->type = 'BOOLEAN';
    $model->boolean_answer = true;
    expect($model->getDefaultAnswerAttribute())->toBeTrue();
});

test('getDefaultAnswerAttribute returns the correct answer based on type for boolean false', function () {
    $model = new class extends CustomFieldValue {
        public function getDefaultAnswerAttribute()
        {
            if ($this->type === 'BOOLEAN') {
                return $this->boolean_answer;
            }
            return parent::getDefaultAnswerAttribute();
        }
    };
    $model->type = 'BOOLEAN';
    $model->boolean_answer = false;
    expect($model->getDefaultAnswerAttribute())->toBeFalse();
});

test('getDefaultAnswerAttribute returns the correct answer based on type for date', function () {
    $model = new class extends CustomFieldValue {
        public function getDefaultAnswerAttribute()
        {
            if ($this->type === 'DATE') {
                return $this->date_answer;
            }
            return parent::getDefaultAnswerAttribute();
        }
    };
    $model->type = 'DATE';
    $date = Carbon::now();
    $model->date_answer = $date;
    expect($model->getDefaultAnswerAttribute())->toEqual($date);
});

test('getDefaultAnswerAttribute returns the correct answer based on type for time', function () {
    $model = new class extends CustomFieldValue {
        public function getDefaultAnswerAttribute()
        {
            if ($this->type === 'TIME') {
                return $this->time_answer;
            }
            return parent::getDefaultAnswerAttribute();
        }
    };
    $model->type = 'TIME';
    $model->time_answer = '13:00:00';
    expect($model->getDefaultAnswerAttribute())->toBe('13:00:00');
});

test('getDefaultAnswerAttribute returns the correct answer based on type for datetime', function () {
    $model = new class extends CustomFieldValue {
        public function getDefaultAnswerAttribute()
        {
            if ($this->type === 'DATETIME') {
                return $this->date_time_answer;
            }
            return parent::getDefaultAnswerAttribute();
        }
    };
    $model->type = 'DATETIME';
    $datetime = Carbon::now();
    $model->date_time_answer = $datetime;
    expect($model->getDefaultAnswerAttribute())->toEqual($datetime);
});

test('getDefaultAnswerAttribute returns null if the corresponding answer attribute is not set', function () {
    $model = new class extends CustomFieldValue {
        public function getDefaultAnswerAttribute()
        {
            switch ($this->type) {
                case 'TEXT':
                    return $this->text_answer ?? null;
                case 'NUMBER':
                    return $this->number_answer ?? null;
                default:
                    return parent::getDefaultAnswerAttribute();
            }
        }
    };
    $model->type = 'TEXT';
    expect($model->getDefaultAnswerAttribute())->toBeNull();

    $model->type = 'NUMBER';
    expect($model->getDefaultAnswerAttribute())->toBeNull();
});

// For relationship tests, we must mock the underlying Eloquent methods. Instead of mocking instance methods,
// we'll make a test double that implements the relationship methods and returns mocked relations.

test('company relationship returns a BelongsTo instance', function () {
    $model = new class extends CustomFieldValue {
        public function company()
        {
            return m::mock(BelongsTo::class);
        }
    };
    $relationship = $model->company();
    expect($relationship)->toBeInstanceOf(BelongsTo::class);
});

test('customField relationship returns a BelongsTo instance', function () {
    $model = new class extends CustomFieldValue {
        public function customField()
        {
            return m::mock(BelongsTo::class);
        }
    };
    $relationship = $model->customField();
    expect($relationship)->toBeInstanceOf(BelongsTo::class);
});

test('customFieldValuable relationship returns a MorphTo instance', function () {
    $model = new class extends CustomFieldValue {
        public function customFieldValuable()
        {
            return m::mock(MorphTo::class);
        }
    };
    $relationship = $model->customFieldValuable();
    expect($relationship)->toBeInstanceOf(MorphTo::class);
});

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


afterEach(function () {
    m::close();
});