<?php

use Crater\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

test('the controller can be instantiated', function () {
    $controller = new Controller();
    expect($controller)->toBeInstanceOf(Controller::class);
});

test('the controller uses the AuthorizesRequests trait', function () {
    $reflectionClass = new ReflectionClass(Controller::class);
    expect($reflectionClass->getTraitNames())->toContain(AuthorizesRequests::class);
});

test('the controller uses the DispatchesJobs trait', function () {
    $reflectionClass = new ReflectionClass(Controller::class);
    expect($reflectionClass->getTraitNames())->toContain(DispatchesJobs::class);
});

test('the controller uses the ValidatesRequests trait', function () {
    $reflectionClass = new ReflectionClass(Controller::class);
    expect($reflectionClass->getTraitNames())->toContain(ValidatesRequests::class);
});

test('the controller extends Illuminate\'s BaseController', function () {
    $reflectionClass = new ReflectionClass(Controller::class);
    expect($reflectionClass->getParentClass()->getName())->toBe(BaseController::class);
});

afterEach(function () {
    if (class_exists(\Mockery::class)) {
        \Mockery::close();
    }
});