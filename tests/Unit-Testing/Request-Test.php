<?php

use Crater\Http\Requests\Request;
use Illuminate\Foundation\Http\FormRequest;

test('the Request class is abstract', function () {
    $reflection = new ReflectionClass(Request::class);
    expect($reflection->isAbstract())->toBeTrue();
});

test('the Request class extends Illuminate\\Foundation\\Http\\FormRequest', function () {
    $reflection = new ReflectionClass(Request::class);
    expect($reflection->getParentClass()->getName())->toBe(FormRequest::class);
});
