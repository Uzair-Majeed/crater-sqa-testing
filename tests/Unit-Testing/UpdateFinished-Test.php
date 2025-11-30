<?php

test('it can be instantiated with string values and properties are correctly assigned', function () {
    $oldVersion = '1.0.0';
    $newVersion = '1.0.1';

    $event = new \Crater\Events\UpdateFinished($oldVersion, $newVersion);

    expect($event->old)->toBe($oldVersion);
    expect($event->new)->toBe($newVersion);
});

test('it can be instantiated with integer values and properties are correctly assigned', function () {
    $oldVersion = 1;
    $newVersion = 2;

    $event = new \Crater\Events\UpdateFinished($oldVersion, $newVersion);

    expect($event->old)->toBe($oldVersion);
    expect($event->new)->toBe($newVersion);
});

test('it can be instantiated with array values and properties are correctly assigned', function () {
    $oldData = ['key' => 'value1'];
    $newData = ['key' => 'value2'];

    $event = new \Crater\Events\UpdateFinished($oldData, $newData);

    expect($event->old)->toBe($oldData);
    expect($event->new)->toBe($newData);
});

test('it can be instantiated with object values and properties are correctly assigned', function () {
    $oldObject = new \stdClass();
    $oldObject->version = '1.0.0';

    $newObject = new \stdClass();
    $newObject->version = '1.0.1';

    $event = new \Crater\Events\UpdateFinished($oldObject, $newObject);

    expect($event->old)->toBe($oldObject);
    expect($event->new)->toBe($newObject);
});

test('it can be instantiated with null values and properties are correctly assigned', function () {
    $oldValue = null;
    $newValue = null;

    $event = new \Crater\Events\UpdateFinished($oldValue, $newValue);

    expect($event->old)->toBeNull();
    expect($event->new)->toBeNull();
});

test('it can be instantiated with mixed types and properties are correctly assigned', function () {
    $oldValue = ['data' => 123];
    $newValue = 'latest';

    $event = new \Crater\Events\UpdateFinished($oldValue, $newValue);

    expect($event->old)->toBe($oldValue);
    expect($event->new)->toBe($newValue);
});

test('the UpdateFinished event uses the Dispatchable trait', function () {
    $uses = class_uses(\Crater\Events\UpdateFinished::class);

    expect($uses)->toContain(\Illuminate\Foundation\Events\Dispatchable::class);
});
