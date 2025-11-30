<?php

use Crater\Http\Requests\NotesRequest;
use Illuminate\Validation\Rule;
use Mockery as m;
use Illuminate\Database\Query\Builder as QueryBuilder;

// Reset Mockery after each test
beforeEach(function () {
    m::close();
});

test('authorize always returns true', function () {
    $request = new NotesRequest();
    expect($request->authorize())->toBeTrue();
});

test('rules returns correct rules for POST request (create)', function () {
    $companyId = 1;
    $type = 'client';

    /** @var NotesRequest|m\MockInterface $request */
    $request = m::mock(NotesRequest::class)->makePartial();
    $request->shouldReceive('isMethod')->with('PUT')->andReturn(false);
    $request->shouldReceive('header')->with('company')->andReturn($companyId);

    // Mock property access for $this->type
    // We can't directly mock properties on `makePartial`, so we set it via reflection.
    // In a real scenario, $this->type would come from request data.
    $typeProperty = new ReflectionProperty(NotesRequest::class, 'type');
    $typeProperty->setAccessible(true);
    $typeProperty->setValue($request, $type);

    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules)->toMatchArray([
            'type' => ['required'],
            'name' => ['required', m::capture($nameRule)], // Capture the unique rule
            'notes' => ['required'],
        ]);

    // Assert the captured Rule::unique instance
    expect($nameRule)->toBeInstanceOf(Rule::class);

    // Use reflection to inspect the private properties of the Rule object
    $reflection = new ReflectionClass($nameRule);

    $ignoreIdProperty = $reflection->getProperty('ignoreId');
    $ignoreIdProperty->setAccessible(true);
    expect($ignoreIdProperty->getValue($nameRule))->toBe(null); // No ignore ID for creation

    $tableProperty = $reflection->getProperty('table');
    $tableProperty->setAccessible(true);
    expect($tableProperty->getValue($nameRule))->toBe('notes');

    $columnProperty = $reflection->getProperty('column');
    $columnProperty->setAccessible(true);
    expect($columnProperty->getValue($nameRule))->toBe(null); // Default to column name

    $whereCallbacksProperty = $reflection->getProperty('whereCallbacks');
    $whereCallbacksProperty->setAccessible(true);
    $whereCallbacks = $whereCallbacksProperty->getValue($nameRule);

    expect($whereCallbacks)->toBeArray()->toHaveCount(2);

    // Assert that the where callbacks apply the correct conditions
    $mockQueryBuilder = m::mock(QueryBuilder::class);
    $mockQueryBuilder->shouldReceive('where')->with('company_id', $companyId)->once()->andReturnSelf();
    $mockQueryBuilder->shouldReceive('where')->with('type', $type)->once()->andReturnSelf();

    foreach ($whereCallbacks as $callback) {
        expect($callback)->toBeInstanceOf(Closure::class);
        $callback($mockQueryBuilder);
    }
});

test('rules returns correct rules for PUT request (update)', function () {
    $companyId = 2;
    $type = 'vendor';
    $noteId = 10;

    /** @var NotesRequest|m\MockInterface $request */
    $request = m::mock(NotesRequest::class)->makePartial();
    $request->shouldReceive('isMethod')->with('PUT')->andReturn(true);
    $request->shouldReceive('header')->with('company')->andReturn($companyId);

    // Mock $this->route('note')->id
    $routeMock = m::mock(\stdClass::class);
    $routeMock->id = $noteId;
    $request->shouldReceive('route')->with('note')->andReturn($routeMock);

    // Mock property access for $this->type
    $typeProperty = new ReflectionProperty(NotesRequest::class, 'type');
    $typeProperty->setAccessible(true);
    $typeProperty->setValue($request, $type);

    $rules = $request->rules();

    expect($rules)->toBeArray()
        ->and($rules)->toMatchArray([
            'type' => ['required'],
            'name' => ['required', m::capture($nameRule)], // Capture the unique rule
            'notes' => ['required'],
        ]);

    // Assert the captured Rule::unique instance
    expect($nameRule)->toBeInstanceOf(Rule::class);

    // Use reflection to inspect the private properties of the Rule object
    $reflection = new ReflectionClass($nameRule);

    $ignoreIdProperty = $reflection->getProperty('ignoreId');
    $ignoreIdProperty->setAccessible(true);
    expect($ignoreIdProperty->getValue($nameRule))->toBe($noteId); // Should ignore the note ID

    $tableProperty = $reflection->getProperty('table');
    $tableProperty->setAccessible(true);
    expect($tableProperty->getValue($nameRule))->toBe('notes');

    $columnProperty = $reflection->getProperty('column');
    $columnProperty->setAccessible(true);
    expect($columnProperty->getValue($nameRule))->toBe(null); // Default to column name

    $whereCallbacksProperty = $reflection->getProperty('whereCallbacks');
    $whereCallbacksProperty->setAccessible(true);
    $whereCallbacks = $whereCallbacksProperty->getValue($nameRule);

    expect($whereCallbacks)->toBeArray()->toHaveCount(2);

    // Assert that the where callbacks apply the correct conditions
    $mockQueryBuilder = m::mock(QueryBuilder::class);
    $mockQueryBuilder->shouldReceive('where')->with('type', $type)->once()->andReturnSelf();
    $mockQueryBuilder->shouldReceive('where')->with('company_id', $companyId)->once()->andReturnSelf();

    foreach ($whereCallbacks as $callback) {
        expect($callback)->toBeInstanceOf(Closure::class);
        $callback($mockQueryBuilder);
    }
});

test('getNotesPayload returns validated data merged with company_id', function () {
    $companyId = 3;
    $validatedData = [
        'type' => 'project',
        'name' => 'Project Alpha Notes',
        'notes' => 'Some notes for Project Alpha.',
    ];

    /** @var NotesRequest|m\MockInterface $request */
    $request = m::mock(NotesRequest::class)->makePartial();
    $request->shouldReceive('validated')->andReturn($validatedData);
    $request->shouldReceive('header')->with('company')->andReturn($companyId);

    $payload = $request->getNotesPayload();

    expect($payload)->toBeArray()
        ->and($payload)->toMatchArray(array_merge($validatedData, [
            'company_id' => $companyId,
        ]));
});

test('getNotesPayload returns empty array if no validated data but includes company_id', function () {
    $companyId = 4;
    $validatedData = []; // No validated data

    /** @var NotesRequest|m\MockInterface $request */
    $request = m::mock(NotesRequest::class)->makePartial();
    $request->shouldReceive('validated')->andReturn($validatedData);
    $request->shouldReceive('header')->with('company')->andReturn($companyId);

    $payload = $request->getNotesPayload();

    expect($payload)->toBeArray()
        ->and($payload)->toMatchArray([
            'company_id' => $companyId,
        ])
        ->and(count($payload))->toBe(1); // Only company_id should be present
});

test('getNotesPayload handles null company_id', function () {
    $companyId = null;
    $validatedData = [
        'type' => 'misc',
        'name' => 'Misc Notes',
    ];

    /** @var NotesRequest|m\MockInterface $request */
    $request = m::mock(NotesRequest::class)->makePartial();
    $request->shouldReceive('validated')->andReturn($validatedData);
    $request->shouldReceive('header')->with('company')->andReturn($companyId);

    $payload = $request->getNotesPayload();

    expect($payload)->toBeArray()
        ->and($payload)->toMatchArray(array_merge($validatedData, [
            'company_id' => $companyId,
        ]));
});
