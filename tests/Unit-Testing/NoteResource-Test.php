<?php

use Illuminate\Http\Request;
use Crater\Http\Resources\NoteResource;
use Crater\Http\Resources\CompanyResource; // Make sure this class is available for the test environment to resolve


    test('it transforms note data correctly when an associated company exists', function () {
        // 1. Mock the underlying Company model for the CompanyResource
        $mockCompanyModel = (object) [
            'id' => 101,
            'name' => 'Example Inc.',
        ];

        // 2. Mock the relation object returned by $this->company()
        $mockRelation = Mockery::mock();
        $mockRelation->shouldReceive('exists')->andReturn(true)->once(); // Ensure exists() is called and returns true

        // 3. Mock the underlying Note model for NoteResource
        $mockNoteModel = (object) [
            'id' => 1,
            'type' => 'general',
            'name' => 'Project Meeting Notes',
            'notes' => 'Discussed Q4 roadmap and client deliverables.',
            'company' => $mockCompanyModel, // This property is accessed when creating CompanyResource
        ];

        // Mock the 'company()' method on the note model resource
        $mockNoteModel = Mockery::mock($mockNoteModel);
        $mockNoteModel->shouldReceive('company')->andReturn($mockRelation)->once();

        // 4. Overload Mockery for CompanyResource to control its output
        // This ensures that when `new CompanyResource(...)` is called, our mock is used.
        Mockery::mock('overload:' . CompanyResource::class)
            ->shouldReceive('toArray')
            ->with(Mockery::type(Request::class)) // Expect toArray to be called with a Request instance
            ->andReturn([
                'id' => $mockCompanyModel->id,
                'name' => $mockCompanyModel->name,
                'mocked_company_data' => true, // Add a unique key to verify this mock was used
            ])
            ->once(); // Ensure toArray is called exactly once

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
        $mockNoteModel = (object) [
            'id' => 2,
            'type' => 'personal',
            'name' => 'Grocery List',
            'notes' => 'Milk, eggs, bread, coffee.',
            // No 'company' property needed on the model itself as company()->exists() is false
        ];

        // Mock the 'company()' method on the note model resource
        $mockNoteModel = Mockery::mock($mockNoteModel);
        $mockNoteModel->shouldReceive('company')->andReturn($mockRelation)->once();

        // 3. Ensure CompanyResource is NOT instantiated or used when company does not exist
        Mockery::shouldNotReceive('overload:' . CompanyResource::class);

        $request = Request::create('/'); // Dummy request object

        $resource = new NoteResource($mockNoteModel);
        $result = $resource->toArray($request);

        expect($result)->toEqual([
            'id' => 2,
            'type' => 'personal',
            'name' => 'Grocery List',
            'notes' => 'Milk, eggs, bread, coffee.',
        ]);
    });

    test('it handles null and empty string values for primary attributes gracefully', function () {
        // Mock the relation object and its 'exists' method to be false
        $mockRelation = Mockery::mock();
        $mockRelation->shouldReceive('exists')->andReturn(false)->once();

        // The underlying resource for NoteResource with null/empty values
        $mockNoteModel = (object) [
            'id' => null,
            'type' => '',
            'name' => null,
            'notes' => '',
        ];

        // Mock the 'company()' method on the note model resource
        $mockNoteModel = Mockery::mock($mockNoteModel);
        $mockNoteModel->shouldReceive('company')->andReturn($mockRelation)->once();

        // Ensure CompanyResource is NOT instantiated
        Mockery::shouldNotReceive('overload:' . CompanyResource::class);

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

        $mockNoteModel = (object) [
            'id' => 3,
            'name' => 'A simple note',
            // 'type' and 'notes' are missing, expecting null or empty if not set
        ];

        // Mock the 'company()' method on the note model resource
        $mockNoteModel = Mockery::mock($mockNoteModel);
        $mockNoteModel->shouldReceive('company')->andReturn($mockRelation)->once();

        // Ensure CompanyResource is NOT instantiated
        Mockery::shouldNotReceive('overload:' . CompanyResource::class);

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
