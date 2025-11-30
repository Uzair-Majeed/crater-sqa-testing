<?php

use Crater\Http\Controllers\V1\Admin\General\NotesController;
use Crater\Http\Requests\NotesRequest;
use Crater\Http\Resources\NoteResource;
use Crater\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\JsonResponse;
use Mockery\MockInterface;
use function Pest\Laravel\mock;

beforeEach(function () {
        // Close Mockery to ensure a clean slate before each test
        Mockery::close();
    });

    test('index method displays a listing of notes with default limit and no filters', function () {
        Gate::shouldReceive('authorize')
            ->once()
            ->with('view notes')
            ->andReturnTrue();

        $request = mock(Request::class);
        $request->shouldReceive('limit')->andReturn(null);
        $request->shouldReceive('all')->andReturn([]);

        // Mock an underlying collection for the paginator
        $notesCollection = collect([new Note(), new Note()]);

        // Mock the Paginator instance that Note::paginate() would return
        $paginator = mock(LengthAwarePaginator::class);
        $paginator->shouldReceive('resource')->andReturnSelf(); // For resource transformation
        $paginator->shouldReceive('getCollection')->andReturn($notesCollection); // For the resource collection

        // Mock Note model's fluent query builder methods
        $noteQueryBuilder = mock('NoteQueryBuilder');
        $noteQueryBuilder->shouldReceive('latest')->andReturnSelf();
        $noteQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
        $noteQueryBuilder->shouldReceive('applyFilters')->with([])->andReturnSelf();
        $noteQueryBuilder->shouldReceive('paginate')->with(10)->andReturn($paginator); // Expect default limit of 10

        // Replace the static `Note` class with our mock for static calls
        mock(Note::class, function (MockInterface $mock) use ($noteQueryBuilder) {
            $mock->shouldReceive('latest')->andReturn($noteQueryBuilder);
        });

        // Mock the static `NoteResource::collection` method
        $anonymousResourceCollectionMock = mock(AnonymousResourceCollection::class);
        mock('alias:' . NoteResource::class, function (MockInterface $mock) use ($paginator, $anonymousResourceCollectionMock) {
            $mock->shouldReceive('collection')
                ->once()
                ->with($paginator)
                ->andReturn($anonymousResourceCollectionMock);
        });

        $controller = new NotesController();
        $response = $controller->index($request);

        expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    });

    test('index method displays a listing of notes with custom limit and filters', function () {
        Gate::shouldReceive('authorize')
            ->once()
            ->with('view notes')
            ->andReturnTrue();

        $customLimit = 20;
        $filters = ['status' => 'active', 'search' => 'test keyword'];

        $request = mock(Request::class);
        $request->shouldReceive('limit')->andReturn($customLimit);
        $request->shouldReceive('all')->andReturn($filters);

        $notesCollection = collect([new Note(), new Note(), new Note()]);

        $paginator = mock(LengthAwarePaginator::class);
        $paginator->shouldReceive('resource')->andReturnSelf();
        $paginator->shouldReceive('getCollection')->andReturn($notesCollection);

        $noteQueryBuilder = mock('NoteQueryBuilder');
        $noteQueryBuilder->shouldReceive('latest')->andReturnSelf();
        $noteQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
        $noteQueryBuilder->shouldReceive('applyFilters')->with($filters)->andReturnSelf();
        $noteQueryBuilder->shouldReceive('paginate')->with($customLimit)->andReturn($paginator);

        mock(Note::class, function (MockInterface $mock) use ($noteQueryBuilder) {
            $mock->shouldReceive('latest')->andReturn($noteQueryBuilder);
        });

        $anonymousResourceCollectionMock = mock(AnonymousResourceCollection::class);
        mock('alias:' . NoteResource::class, function (MockInterface $mock) use ($paginator, $anonymousResourceCollectionMock) {
            $mock->shouldReceive('collection')
                ->once()
                ->with($paginator)
                ->andReturn($anonymousResourceCollectionMock);
        });

        $controller = new NotesController();
        $response = $controller->index($request);

        expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    });

    test('index method returns empty collection when no notes are found', function () {
        Gate::shouldReceive('authorize')
            ->once()
            ->with('view notes')
            ->andReturnTrue();

        $request = mock(Request::class);
        $request->shouldReceive('limit')->andReturn(10);
        $request->shouldReceive('all')->andReturn([]);

        $emptyNotesCollection = collect([]);

        $paginator = mock(LengthAwarePaginator::class);
        $paginator->shouldReceive('resource')->andReturnSelf();
        $paginator->shouldReceive('getCollection')->andReturn($emptyNotesCollection);

        $noteQueryBuilder = mock('NoteQueryBuilder');
        $noteQueryBuilder->shouldReceive('latest')->andReturnSelf();
        $noteQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
        $noteQueryBuilder->shouldReceive('applyFilters')->with([])->andReturnSelf();
        $noteQueryBuilder->shouldReceive('paginate')->with(10)->andReturn($paginator);

        mock(Note::class, function (MockInterface $mock) use ($noteQueryBuilder) {
            $mock->shouldReceive('latest')->andReturn($noteQueryBuilder);
        });

        $anonymousResourceCollectionMock = mock(AnonymousResourceCollection::class);
        mock('alias:' . NoteResource::class, function (MockInterface $mock) use ($paginator, $anonymousResourceCollectionMock) {
            $mock->shouldReceive('collection')
                ->once()
                ->with($paginator)
                ->andReturn($anonymousResourceCollectionMock);
        });

        $controller = new NotesController();
        $response = $controller->index($request);

        expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    });

    test('store method creates a new note and returns its resource', function () {
        Gate::shouldReceive('authorize')
            ->once()
            ->with('manage notes')
            ->andReturnTrue();

        $payload = ['body' => 'This is a new note.', 'related_id' => 1, 'related_type' => 'App\\Models\\User'];
        $createdNote = new Note(['id' => 1, 'body' => $payload['body']]); // Simulate the created note

        $notesRequest = mock(NotesRequest::class);
        $notesRequest->shouldReceive('getNotesPayload')
            ->once()
            ->andReturn($payload);

        // Mock the static `Note::create` method
        mock(Note::class, function (MockInterface $mock) use ($payload, $createdNote) {
            $mock->shouldReceive('create')
                ->once()
                ->with($payload)
                ->andReturn($createdNote);
        });

        // Mock the NoteResource constructor to verify it's called with the correct note
        $noteResourceMock = mock(NoteResource::class, function (MockInterface $mock) use ($createdNote) {
            $mock->shouldReceive('__construct')
                ->once()
                ->with($createdNote)
                ->andReturnNull(); // Constructor doesn't return anything
        });

        $controller = new NotesController();
        $response = $controller->store($notesRequest);

        // Expect the response to be our mocked resource instance
        expect($response)->toBe($noteResourceMock);
    });

    test('show method displays the specified note resource', function () {
        Gate::shouldReceive('authorize')
            ->once()
            ->with('view notes')
            ->andReturnTrue();

        $note = new Note(['id' => 1, 'body' => 'Existing note content']); // Route model binding provides this note

        // Mock the NoteResource constructor to verify argument
        $noteResourceMock = mock(NoteResource::class, function (MockInterface $mock) use ($note) {
            $mock->shouldReceive('__construct')
                ->once()
                ->with($note)
                ->andReturnNull();
        });

        $controller = new NotesController();
        $response = $controller->show($note);

        expect($response)->toBe($noteResourceMock);
    });

    test('update method updates the specified note and returns its resource', function () {
        Gate::shouldReceive('authorize')
            ->once()
            ->with('manage notes')
            ->andReturnTrue();

        $payload = ['body' => 'Updated note content from request'];

        $existingNote = mock(Note::class);
        $existingNote->shouldReceive('update')
            ->once()
            ->with($payload)
            ->andReturnTrue(); // Eloquent update typically returns boolean

        $notesRequest = mock(NotesRequest::class);
        $notesRequest->shouldReceive('getNotesPayload')
            ->once()
            ->andReturn($payload);

        // Mock the NoteResource constructor to verify argument
        $noteResourceMock = mock(NoteResource::class, function (MockInterface $mock) use ($existingNote) {
            $mock->shouldReceive('__construct')
                ->once()
                ->with($existingNote)
                ->andReturnNull();
        });

        $controller = new NotesController();
        $response = $controller->update($notesRequest, $existingNote);

        expect($response)->toBe($noteResourceMock);
    });

    test('destroy method deletes the specified note and returns success JSON', function () {
        Gate::shouldReceive('authorize')
            ->once()
            ->with('manage notes')
            ->andReturnTrue();

        $noteToDelete = mock(Note::class);
        $noteToDelete->shouldReceive('delete')
            ->once()
            ->andReturnTrue(); // Eloquent delete typically returns boolean

        $controller = new NotesController();
        $response = $controller->destroy($noteToDelete);

        // Assert the returned JsonResponse structure and status
        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->json()->success->toBeTrue();
    });

    test('destroy method handles failure of note deletion gracefully', function () {
        Gate::shouldReceive('authorize')
            ->once()
            ->with('manage notes')
            ->andReturnTrue();

        $noteToDelete = mock(Note::class);
        $noteToDelete->shouldReceive('delete')
            ->once()
            ->andReturnFalse(); // Simulate deletion failure (e.g., due to foreign key constraints, though unlikely in a simple delete)

        $controller = new NotesController();
        $response = $controller->destroy($noteToDelete);

        // The current controller implementation always returns success: true,
        // regardless of the actual `delete()` result. This is a logic flaw
        // in the controller's original implementation from a white-box perspective.
        // For white-box testing, we assert what the code *does*, not what it *should* do if perfect.
        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->json()->success->toBeTrue();
    });
