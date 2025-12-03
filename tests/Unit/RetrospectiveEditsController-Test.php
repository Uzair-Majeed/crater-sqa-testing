<?php

use Crater\Http\Controllers\V1\Admin\Config\RetrospectiveEditsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\JsonResponse;

test('it returns retrospective_edits as true when config is set to true', function () {
        // Arrange
        Config::shouldReceive('get')
            ->once()
            ->with('crater.retrospective_edits')
            ->andReturn(true);

        $request = Request::create('/');
        $controller = new RetrospectiveEditsController();

        // Act
        $response = $controller->__invoke($request);

        // Assert
        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toEqual(['retrospective_edits' => true])
            ->and($response->getStatusCode())->toBe(200);
    });

    test('it returns retrospective_edits as false when config is set to false', function () {
        // Arrange
        Config::shouldReceive('get')
            ->once()
            ->with('crater.retrospective_edits')
            ->andReturn(false);

        $request = Request::create('/');
        $controller = new RetrospectiveEditsController();

        // Act
        $response = $controller->__invoke($request);

        // Assert
        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toEqual(['retrospective_edits' => false])
            ->and($response->getStatusCode())->toBe(200);
    });

    test('it returns retrospective_edits as null when config is not set or null', function () {
        // Arrange
        Config::shouldReceive('get')
            ->once()
            ->with('crater.retrospective_edits')
            ->andReturn(null);

        $request = Request::create('/');
        $controller = new RetrospectiveEditsController();

        // Act
        $response = $controller->__invoke($request);

        // Assert
        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toEqual(['retrospective_edits' => null])
            ->and($response->getStatusCode())->toBe(200);
    });

    test('it returns retrospective_edits as a string when config is a string', function () {
        // Arrange
        $configValue = 'enabled';
        Config::shouldReceive('get')
            ->once()
            ->with('crater.retrospective_edits')
            ->andReturn($configValue);

        $request = Request::create('/');
        $controller = new RetrospectiveEditsController();

        // Act
        $response = $controller->__invoke($request);

        // Assert
        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toEqual(['retrospective_edits' => $configValue])
            ->and($response->getStatusCode())->toBe(200);
    });

    test('it returns retrospective_edits as an integer when config is an integer', function () {
        // Arrange
        $configValue = 1;
        Config::shouldReceive('get')
            ->once()
            ->with('crater.retrospective_edits')
            ->andReturn($configValue);

        $request = Request::create('/');
        $controller = new RetrospectiveEditsController();

        // Act
        $response = $controller->__invoke($request);

        // Assert
        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toEqual(['retrospective_edits' => $configValue])
            ->and($response->getStatusCode())->toBe(200);
    });

    test('it returns retrospective_edits as an array when config is an array (edge case)', function () {
        // Arrange
        $configValue = ['edit_period_days' => 30, 'allowed_roles' => ['admin']];
        Config::shouldReceive('get')
            ->once()
            ->with('crater.retrospective_edits')
            ->andReturn($configValue);

        $request = Request::create('/');
        $controller = new RetrospectiveEditsController();

        // Act
        $response = $controller->__invoke($request);

        // Assert
        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toEqual(['retrospective_edits' => $configValue])
            ->and($response->getStatusCode())->toBe(200);
    });




afterEach(function () {
    Mockery::close();
});
