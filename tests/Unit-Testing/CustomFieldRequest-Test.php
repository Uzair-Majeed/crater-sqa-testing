<?php

use Crater\Http\Requests\CustomFieldRequest;

test('authorize method always returns true', function () {
        $request = new CustomFieldRequest();
        expect($request->authorize())->toBeTrue();
    });

    test('rules method returns the predefined validation rules', function () {
        $request = new CustomFieldRequest();
        $expectedRules = [
            'name' => 'required',
            'label' => 'required',
            'model_type' => 'required',
            'order' => 'required',
            'type' => 'required',
            'is_required' => 'required|boolean',
            'options' => 'array',
            'placeholder' => 'string|nullable',
        ];
        expect($request->rules())->toEqual($expectedRules);
    });

