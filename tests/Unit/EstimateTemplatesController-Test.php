<?php

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Crater\Models\Estimate;
use Crater\Http\Controllers\V1\Admin\Estimate\EstimateTemplatesController;

test('it handles authorization and returns estimate templates successfully', function () {
    $mockEstimate = Mockery::mock('alias:' . Estimate::class);
    $expectedTemplates = collect([
        ['id' => 1, 'name' => 'Template A'],
        ['id' => 2, 'name' => 'Template B'],
    ]);

    $mockEstimate->shouldReceive('estimateTemplates')
                 ->once()
                 ->andReturn($expectedTemplates);

    $controller = new class extends EstimateTemplatesController {
        public $authorizeCalled = false;
        public $authorizeAbility = null;
        public $authorizeArguments = null;

        public function authorize($ability, $arguments = [])
        {
            $this->authorizeCalled = true;
            $this->authorizeAbility = $ability;
            $this->authorizeArguments = $arguments;
            return true;
        }
    };

    $request = Request::create('/');

    $response = $controller($request);

    expect($controller->authorizeCalled)->toBeTrue();
    expect($controller->authorizeAbility)->toBe('viewAny');
    expect($controller->authorizeArguments)->toBe(Estimate::class);
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['estimateTemplates' => $expectedTemplates->toArray()]);

    Mockery::close();
});

test('it handles authorization and returns an empty array when no templates exist', function () {
    $mockEstimate = Mockery::mock('alias:' . Estimate::class);
    $expectedTemplates = collect([]);

    $mockEstimate->shouldReceive('estimateTemplates')
                 ->once()
                 ->andReturn($expectedTemplates);

    $controller = new class extends EstimateTemplatesController {
        public $authorizeCalled = false;
        public $authorizeAbility = null;
        public $authorizeArguments = null;

        public function authorize($ability, $arguments = [])
        {
            $this->authorizeCalled = true;
            $this->authorizeAbility = $ability;
            $this->authorizeArguments = $arguments;
            return true;
        }
    };

    $request = Request::create('/');

    $response = $controller($request);

    expect($controller->authorizeCalled)->toBeTrue();
    expect($controller->authorizeAbility)->toBe('viewAny');
    expect($controller->authorizeArguments)->toBe(Estimate::class);
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual(['estimateTemplates' => []]);

    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});