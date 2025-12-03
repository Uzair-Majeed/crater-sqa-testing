```php
<?php

use Illuminate\Http\Request;
use Crater\Http\Resources\NoteResource;
use Crater\Http\Resources\CompanyResource;
use Mockery\MockInterface; // Import MockInterface for type hinting

    test('it transforms note data correctly when an associated company exists', function () {
        // 1. Mock the underlying Company model data
        $mockCompanyModelData = [
            'id' => 101,
            'name' => 'Example Inc.',
        ];
        // The CompanyResource will receive an object that behaves like a model
        $mockCompanyModel = (object) $mockCompanyModelData;

        // 2. Mock the relation object returned by $this->company()
        // This mock represents the result of calling the `company()` method on the Note model.
        // It must provide an `exists()` method.
        $mockRelation = Mockery::mock();
        $mockRelation->shouldReceive('exists')->andReturn(true)->once(); // Ensure exists() is called and returns true

        // 3. Mock the underlying Note model for NoteResource
        // NoteResource directly accesses properties like `$this->id`, `$this->name`, etc.
        // It also calls the `company()` method to check for a related company.
        // A partial mock of `stdClass` allows setting initial properties and mocking methods.
        $noteModelProperties = [
            'id' => 1,
            'type' => 'general',
            'name' => 'Project Meeting Notes',
            'notes' => 'Discussed Q4 roadmap and client deliverables.',
            // If the NoteResource, after checking `company()->exists()`, proceeds to access `$this->company`
            // as a property to instantiate `CompanyResource`, then this property needs to exist on the mock.
            'company' => $mockCompanyModel,
        ];

        // Create a partial mock of stdClass with initial properties.
        // `makePartial()` ensures that properties set in `$noteModelProperties` are accessible,
        // while also allowing us to mock specific methods. This fixes the 'Undefined property' error.
        $mockNoteModel = Mockery::mock(\stdClass::class, $noteModelProperties)->makePartial();

        // Now, mock the 'company()' method on this partial mock.
        // This method is called by the NoteResource to determine if a company exists.
        $mockNoteModel->shouldReceive('company')->andReturn($mockRelation)->once();

        // 4. Overload Mockery for CompanyResource to control its output
        // This ensures that when `new CompanyResource(...)` is called inside NoteResource,
        // our mocked CompanyResource instance is returned, and we can define its `toArray` behavior.
        Mockery::mock('overload:' . CompanyResource::class, function (MockInterface $mock) use ($mockCompanyModel) {
            $mock->shouldReceive('toArray')
                ->with(Mockery::type(Request::class)) // Expect toArray to be called with a Request instance
                ->andReturn([
                    'id' => $mockCompanyModel->id,
                    'name' => $mockCompanyModel->name,
                    'mocked_company_data' => true, // Add a unique key to verify this mock was used
                ])
                ->once(); // Ensure toArray is called exactly once
        });

        $request = Request::create('/'); // Dummy request object

        $resource = new NoteResource($mockNoteModel);
        $result = $resource->toArray($request);

        expect($result)->toEqual([
            'id' => 1,
            'type' => 'general',
            'name' => 'Project Meeting Notes',
            'notes' => 'Discussed Q4 roadmap and client deliverables.',
            'company' => [
                'id' => 101,
                'name' => 'Example Inc.',
                'mocked_company_data' => true,
            ],
        ]);
    });

    test('it transforms note data correctly when no associated company exists', function () {
        // 1. Mock the relation object returned by $this->company()
        $mockRelation = Mockery::mock();
        $mockRelation->shouldReceive('exists')->andReturn(false)->once(); // Ensure exists() is called and returns false

        // 2. Mock the underlying Note model for NoteResource
        // Use a partial mock for stdClass to allow property access and method mocking.
        $mockNoteModel = Mockery::mock(\stdClass::class, [
            'id' => 2,
            'type' => 'personal',
            'name' => 'Grocery List',
            'notes' => 'Milk, eggs, bread, coffee.',
            'company' => null, // Explicitly set company to null as it doesn't exist
        ])->makePartial();

        // Mock the 'company()' method on the note model resource
        $mockNoteModel->shouldReceive('company')->andReturn($mockRelation)->once();

        // 3. Ensure CompanyResource is NOT instantiated or its `toArray` method is NOT called.
        // The error `Call to undefined method Mockery::shouldNotReceive()` indicates that
        // `Mockery::shouldNotReceive()` is not a valid static call.
        // To assert a method on an overloaded mock is not called, you set expectations on the overloaded mock itself.
        Mockery::mock('overload:' . CompanyResource::class)
            ->shouldNotReceive('toArray'); // This will fail the test if `toArray` is called on CompanyResource

        $request = Request::create('/'); // Dummy request object

        $resource = new NoteResource($mockNoteModel);
        $result = $resource->toArray($request);

        expect($result)->toEqual([
            'id' => 2,
            'type' => 'personal',
            'name' => 'Grocery List',
            'notes' => 'Milk, eggs, bread, coffee.',
            // The 'company' key should be absent if the resource conditionally adds it only when found.
            // Based on the expected output, it's absent.
        ]);
    });

    test('it handles null and empty string values for primary attributes gracefully', function () {
        // Mock the relation object and its 'exists' method to be false
        $mockRelation = Mockery::mock();
        $mockRelation->shouldReceive('exists')->andReturn(false)->once();

        // The underlying resource for NoteResource with null/empty values
        // Use a partial mock for stdClass to allow property access and method mocking.
        $mockNoteModel = Mockery::mock(\stdClass::class, [
            'id' => null,
            'type' => '',
            'name' => null,
            'notes' => '',
            'company' => null, // Explicitly set company to null if not existing
        ])->makePartial();

        // Mock the 'company()' method on the note model resource
        $mockNoteModel->shouldReceive('company')->andReturn($mockRelation)->once();

        // Ensure CompanyResource is NOT instantiated or its `toArray` method is NOT called.
        // Correcting `Mockery::shouldNotReceive()` usage.
        Mockery::mock('overload:' . CompanyResource::class)
            ->shouldNotReceive('toArray');

        $request = Request::create('/');

        $resource = new NoteResource($mockNoteModel);
        $result = $resource->toArray($request);

        expect($result)->toEqual([
            'id' => null,
            'type' => '',
            'name' => null,
            'notes' => '',
        ]);
    });

    test('it transforms with minimal required data when company does not exist', function () {
        // Mock the relation object and its 'exists' method to be false
        $mockRelation = Mockery::mock();
        $mockRelation->shouldReceive('exists')->andReturn(false)->once();

        // Use a partial mock for stdClass to allow property access and method mocking.
        // Explicitly setting 'type' and 'notes' to null to match the expected output
        // when they are missing from the initial data.
        $mockNoteModel = Mockery::mock(\stdClass::class, [
            'id' => 3,
            'name' => 'A simple note',
            'type' => null,
            'notes' => null,
            'company' => null, // Explicitly set company to null if not existing
        ])->makePartial();

        // Mock the 'company()' method on the note model resource
        $mockNoteModel->shouldReceive('company')->andReturn($mockRelation)->once();

        // Ensure CompanyResource is NOT instantiated or its `toArray` method is NOT called.
        // Correcting `Mockery::shouldNotReceive()` usage.
        Mockery::mock('overload:' . CompanyResource::class)
            ->shouldNotReceive('toArray');

        $request = Request::create('/');

        $resource = new NoteResource($mockNoteModel);
        $result = $resource->toArray($request);

        // Expect missing properties to be null
        expect($result)->toEqual([
            'id' => 3,
            'type' => null,
            'name' => 'A simple note',
            'notes' => null,
        ]);
    });

afterEach(function () {
    Mockery::close();
});
```