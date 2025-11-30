<?php
use Crater\Http\Requests\DeleteInvoiceRequest;
use Crater\Models\Invoice;
use Crater\Rules\RelationNotExist;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;


test('authorize method always returns true', function () {
    $request = new DeleteInvoiceRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules method returns correct validation rules', function () {
    $request = new DeleteInvoiceRequest();
    $rules = $request->rules();

    // Assert 'ids' rules
    expect($rules)->toHaveKey('ids');
    expect($rules['ids'])->toBe(['required']);

    // Assert 'ids.*' rules
    expect($rules)->toHaveKey('ids.*');
    expect($rules['ids.*'])->toHaveCount(3);
    expect($rules['ids.*'][0])->toBe('required');

    // Assert Rule::exists rule properties using reflection for white-box testing
    $existsRule = collect($rules['ids.*'])->first(fn($rule) => $rule instanceof Exists);
    expect($existsRule)->not->toBeNull('Rule::exists rule not found in ids.*');
    expect($existsRule)->toBeInstanceOf(Exists::class);

    $reflectionExistsRule = new ReflectionObject($existsRule);
    $tableProperty = $reflectionExistsRule->getProperty('table');
    $tableProperty->setAccessible(true);
    $columnProperty = $reflectionExistsRule->getProperty('column');
    $columnProperty->setAccessible(true);

    expect($tableProperty->getValue($existsRule))->toBe('invoices');
    expect($columnProperty->getValue($existsRule))->toBe('id');

    // Assert RelationNotExist rule properties using reflection for white-box testing
    $relationNotExistRule = collect($rules['ids.*'])->first(fn($rule) => $rule instanceof RelationNotExist);
    expect($relationNotExistRule)->not->toBeNull('RelationNotExist rule not found in ids.*');
    expect($relationNotExistRule)->toBeInstanceOf(RelationNotExist::class);

    $reflectionRelationRule = new ReflectionObject($relationNotExistRule);
    $modelProperty = $reflectionRelationRule->getProperty('model');
    $modelProperty->setAccessible(true);
    $relationProperty = $reflectionRelationRule->getProperty('relation');
    $relationProperty->setAccessible(true);

    expect($modelProperty->getValue($relationNotExistRule))->toBe(Invoice::class);
    expect($relationProperty->getValue($relationNotExistRule))->toBe('payments');
});
