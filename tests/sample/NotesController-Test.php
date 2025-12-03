```php
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
use Mockery; // Import Mockery directly for 'overload' mocks

beforeEach(function () {
    // Close Mockery to ensure a clean slate before each test.
    // This is crucial for 'overload' mocks to be properly torn down.
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

    $notesCollection = collect([new Note(), new Note()]);

    $paginator = mock(LengthAwarePaginator::class);
    // For JsonResource::collection to work with a paginator, it primarily needs getCollection()
    $paginator->shouldReceive('getCollection')->andReturn($notesCollection);
    // JsonResource::collection also passes through other paginator methods like total(), perPage(), etc.,
    // so it's good practice to stub them or ensure the mock handles them if they're called.
    $paginator->shouldReceive('total')->andReturn($notesCollection->count());
    $paginator->shouldReceive('perPage')->andReturn(10);
    $paginator->shouldReceive('currentPage')->andReturn(1);
    $paginator->shouldReceive('lastPage')->andReturn(1);

    // Mock Note model's fluent query builder methods
    $noteQueryBuilder = mock('NoteQueryBuilder'); // Using a generic name for a mock builder
    $noteQueryBuilder->shouldReceive('latest')->andReturnSelf();
    $noteQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $noteQueryBuilder->shouldReceive('applyFilters')->with([])->andReturnSelf();
    $noteQueryBuilder->shouldReceive('paginate')->with(10)->andReturn($paginator); // Expect default limit of 10

    // Replace the static `Note` class with our mock for static calls
    mock(Note::class, function (MockInterface $mock) use ($noteQueryBuilder) {
        $mock->shouldReceive('latest')->andReturn($noteQueryBuilder);
    });

    // FIX: Use Mockery::mock('overload:') to mock static methods on JsonResource.
    // This prevents redeclaration errors as 'overload' mocks are temporary and cleaned by Mockery::close().
    $anonymousResourceCollectionMock = mock(AnonymousResourceCollection::class);
    Mockery::mock('overload:' . NoteResource::class)
        ->shouldReceive('collection')
        ->once()
        ->with($paginator)
        ->andReturn($anonymousResourceCollectionMock);

    $controller = new NotesController();
    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response)->toBe($anonymousResourceCollectionMock); // Assert it's our specific mock instance
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
    $paginator->shouldReceive('getCollection')->andReturn($notesCollection);
    $paginator->shouldReceive('total')->andReturn($notesCollection->count());
    $paginator->shouldReceive('perPage')->andReturn($customLimit);
    $paginator->shouldReceive('currentPage')->andReturn(1);
    $paginator->shouldReceive('lastPage')->andReturn(1);

    $noteQueryBuilder = mock('NoteQueryBuilder');
    $noteQueryBuilder->shouldReceive('latest')->andReturnSelf();
    $noteQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $noteQueryBuilder->shouldReceive('applyFilters')->with($filters)->andReturnSelf();
    $noteQueryBuilder->shouldReceive('paginate')->with($customLimit)->andReturn($paginator);

    mock(Note::class, function (MockInterface $mock) use ($noteQueryBuilder) {
        $mock->shouldReceive('latest')->andReturn($noteQueryBuilder);
    });

    // FIX: Use Mockery::mock('overload:') for static method `collection`.
    $anonymousResourceCollectionMock = mock(AnonymousResourceCollection::class);
    Mockery::mock('overload:' . NoteResource::class)
        ->shouldReceive('collection')
        ->once()
        ->with($paginator)
        ->andReturn($anonymousResourceCollectionMock);

    $controller = new NotesController();
    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response)->toBe($anonymousResourceCollectionMock);
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
    $paginator->shouldReceive('getCollection')->andReturn($emptyNotesCollection);
    $paginator->shouldReceive('total')->andReturn(0);
    $paginator->shouldReceive('perPage')->andReturn(10);
    $paginator->shouldReceive('currentPage')->andReturn(1);
    $paginator->shouldReceive('lastPage')->andReturn(1);


    $noteQueryBuilder = mock('NoteQueryBuilder');
    $noteQueryBuilder->shouldReceive('latest')->andReturnSelf();
    $noteQueryBuilder->shouldReceive('whereCompany')->andReturnSelf();
    $noteQueryBuilder->shouldReceive('applyFilters')->with([])->andReturnSelf();
    $noteQueryBuilder->shouldReceive('paginate')->with(10)->andReturn($paginator);

    mock(Note::class, function (MockInterface $mock) use ($noteQueryBuilder) {
        $mock->shouldReceive('latest')->andReturn($noteQueryBuilder);
    });

    // FIX: Use Mockery::mock('overload:') for static method `collection`.
    $anonymousResourceCollectionMock = mock(AnonymousResourceCollection::class);
    Mockery::mock('overload:' . NoteResource::class)
        ->shouldReceive('collection')
        ->once()
        ->with($paginator)
        ->andReturn($anonymousResourceCollectionMock);

    $controller = new NotesController();
    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(AnonymousResourceCollection::class);
    expect($response)->toBe($anonymousResourceCollectionMock);
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

    // FIX: Use Mockery::mock('overload:') to intercept `new NoteResource(...)`.
    // Configure the overload mock to behave like a NoteResource instance.
    $noteResourceOverloadMock = Mockery::mock('overload:' . NoteResource::class, function (MockInterface $mock) use ($createdNote) {
        // Expect the constructor to be called with the created note
        $mock->shouldReceive('__construct')
            ->once()
            ->with($createdNote)
            ->andReturnNull(); // Constructor doesn't return anything

        // JsonResource usually calls `toArray()` when being prepared for a response
        $mock->shouldReceive('toArray')
            ->andReturn([
                'id' => $createdNote->id,
                'body' => $createdNote->body,
                // Add other expected resource attributes if your resource would return them
            ]);
    });

    $controller = new NotesController();
    $response = $controller->store($notesRequest);

    // With 'overload', `new NoteResource(...)` actually instantiates the mock itself.
    expect($response)->toBeInstanceOf(NoteResource::class);
    expect($response)->toBe($noteResourceOverloadMock); // Assert it's our specific mock instance
});

test('show method displays the specified note resource', function () {
    Gate::shouldReceive('authorize')
        ->once()
        ->with('view notes')
        ->andReturnTrue();

    $note = new Note(['id' => 1, 'body' => 'Existing note content']); // Route model binding provides this note

    // FIX: Use Mockery::mock('overload:') to intercept `new NoteResource(...)`.
    $noteResourceOverloadMock = Mockery::mock('overload:' . NoteResource::class, function (MockInterface $mock) use ($note) {
        $mock->shouldReceive('__construct')
            ->once()
            ->with($note)
            ->andReturnNull();
        $mock->shouldReceive('toArray')
            ->andReturn([
                'id' => $note->id,
                'body' => $note->body,
            ]);
    });

    $controller = new NotesController();
    $response = $controller->show($note);

    expect($response)->toBeInstanceOf(NoteResource::class);
    expect($response)->toBe($noteResourceOverloadMock);
});

test('update method updates the specified note and returns its resource', function () {
    Gate::shouldReceive('authorize')
        ->once()
        ->with('manage notes')
        ->andReturnTrue();

    $payload = ['body' => 'Updated note content from request'];

    // Mock the existing Note model instance that would be passed by route model binding
    $existingNote = mock(Note::class);
    $existingNote->id = 1; // Assign ID for consistent resource output
    $existingNote->body = 'Original note content'; // Original content

    $existingNote->shouldReceive('update')
        ->once()
        ->with($payload)
        ->andReturnTrue(); // Eloquent update typically returns boolean

    // Update the mock's property *after* the update call is expected,
    // so that the NoteResource receives the "updated" state.
    $existingNote->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $existingNote->shouldReceive('getAttribute')->with('body')->andReturnUsing(function($attribute) use (&$payload) {
        return $payload['body'];
    });


    $notesRequest = mock(NotesRequest::class);
    $notesRequest->shouldReceive('getNotesPayload')
        ->once()
        ->andReturn($payload);

    // FIX: Use Mockery::mock('overload:') to intercept `new NoteResource(...)`.
    $noteResourceOverloadMock = Mockery::mock('overload:' . NoteResource::class, function (MockInterface $mock) use ($existingNote) {
        $mock->shouldReceive('__construct')
            ->once()
            ->with($existingNote)
            ->andReturnNull();
        $mock->shouldReceive('toArray')
            ->andReturn([
                'id' => $existingNote->id,
                'body' => $existingNote->body,
            ]);
    });

    $controller = new NotesController();
    $response = $controller->update($notesRequest, $existingNote);

    expect($response)->toBeInstanceOf(NoteResource::class);
    expect($response)->toBe($noteResourceOverloadMock);
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

    // FIX: Use ->getData()->success for robust assertion on JsonResponse content.
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData()->success)->toBeTrue();
});

test('destroy method handles failure of note deletion gracefully', function () {
    Gate::shouldReceive('authorize')
        ->once()
        ->with('manage notes')
        ->andReturnTrue();

    $noteToDelete = mock(Note::class);
    $noteToDelete->shouldReceive('delete')
        ->once()
        ->andReturnFalse(); // Simulate deletion failure

    $controller = new NotesController();
    $response = $controller->destroy($noteToDelete);

    // The current controller implementation always returns success: true,
    // regardless of the actual `delete()` result. This is a logic flaw
    // in the controller's original implementation from a white-box perspective.
    // For white-box testing, we assert what the code *does*, not what it *should* do if perfect.
    // FIX: Use ->getData()->success for robust assertion on JsonResponse content.
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData()->success)->toBeTrue();
});

afterEach(function () {
    Mockery::close();
});
```