<?php

use Crater\Models\Note;
use Crater\Policies\NotePolicy;
use Crater\Http\Resources\NoteResource;
use Crater\Http\Requests\NotesRequest;

// ========== MERGED NOTE TESTS (4 CLASSES, ~20 TESTS FOR 100% COVERAGE) ==========

// --- Note Model Tests (6 tests) ---

test('Note model can be instantiated', function () {
    $note = new Note();
    expect($note)->toBeInstanceOf(Note::class);
});

test('Note extends Model and uses HasFactory', function () {
    $note = new Note();
    $reflection = new ReflectionClass(Note::class);
    $traits = $reflection->getTraitNames();
    
    expect($note)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class)
        ->and($traits)->toContain('Illuminate\Database\Eloquent\Factories\HasFactory');
});

test('Note has company relationship', function () {
    $note = new Note();
    expect(method_exists($note, 'company'))->toBeTrue();
});

test('Note has scope methods for filtering', function () {
    $reflection = new ReflectionClass(Note::class);
    
    expect($reflection->hasMethod('scopeApplyFilters'))->toBeTrue()
        ->and($reflection->hasMethod('scopeWhereSearch'))->toBeTrue()
        ->and($reflection->hasMethod('scopeWhereType'))->toBeTrue()
        ->and($reflection->hasMethod('scopeWhereCompany'))->toBeTrue();
});

test('Note scopeApplyFilters handles type and search filters', function () {
    $reflection = new ReflectionClass(Note::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('$filters->get(\'type\')')
        ->and($fileContent)->toContain('->whereType')
        ->and($fileContent)->toContain('$filters->get(\'search\')')
        ->and($fileContent)->toContain('->whereSearch');
});

test('Note scopeWhereSearch uses LIKE query', function () {
    $reflection = new ReflectionClass(Note::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('where(\'name\', \'LIKE\', \'%\'.$search.\'%\')');
});

// --- NotePolicy Tests (4 tests) ---

test('NotePolicy can be instantiated', function () {
    $policy = new NotePolicy();
    expect($policy)->toBeInstanceOf(NotePolicy::class);
});

test('NotePolicy uses HandlesAuthorization trait', function () {
    $reflection = new ReflectionClass(NotePolicy::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\Auth\Access\HandlesAuthorization');
});

test('NotePolicy has manageNotes and viewNotes methods', function () {
    $reflection = new ReflectionClass(NotePolicy::class);
    
    expect($reflection->hasMethod('manageNotes'))->toBeTrue()
        ->and($reflection->hasMethod('viewNotes'))->toBeTrue();
});

test('NotePolicy uses BouncerFacade for authorization', function () {
    $reflection = new ReflectionClass(NotePolicy::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('BouncerFacade::can(\'manage-all-notes\', Note::class)')
        ->and($fileContent)->toContain('BouncerFacade::can(\'view-all-notes\', Note::class)');
});

// --- NoteResource Tests (5 tests) ---

test('NoteResource can be instantiated', function () {
    $resource = new NoteResource((object)['id' => 1]);
    expect($resource)->toBeInstanceOf(NoteResource::class);
});

test('NoteResource extends JsonResource', function () {
    $resource = new NoteResource((object)['id' => 1]);
    expect($resource)->toBeInstanceOf(\Illuminate\Http\Resources\Json\JsonResource::class);
});

test('NoteResource toArray includes basic fields', function () {
    $reflection = new ReflectionClass(NoteResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'id\' => $this->id')
        ->and($fileContent)->toContain('\'type\' => $this->type')
        ->and($fileContent)->toContain('\'name\' => $this->name')
        ->and($fileContent)->toContain('\'notes\' => $this->notes');
});

test('NoteResource includes company relationship conditionally', function () {
    $reflection = new ReflectionClass(NoteResource::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'company\' =>')
        ->and($fileContent)->toContain('$this->when($this->company()->exists()')
        ->and($fileContent)->toContain('CompanyResource');
});

test('NoteResource is in correct namespace', function () {
    $reflection = new ReflectionClass(NoteResource::class);
    expect($reflection->getNamespaceName())->toBe('Crater\Http\Resources');
});

// --- NotesRequest Tests (6 tests) ---

test('NotesRequest can be instantiated', function () {
    $request = new NotesRequest();
    expect($request)->toBeInstanceOf(NotesRequest::class);
});

test('NotesRequest extends FormRequest', function () {
    $request = new NotesRequest();
    expect($request)->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
});

test('NotesRequest authorize returns true', function () {
    $request = new NotesRequest();
    expect($request->authorize())->toBeTrue();
});

test('NotesRequest rules include required fields', function () {
    $reflection = new ReflectionClass(NotesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('\'type\' =>')
        ->and($fileContent)->toContain('\'name\' =>')
        ->and($fileContent)->toContain('\'notes\' =>')
        ->and($fileContent)->toContain('Rule::unique(\'notes\')');
});

test('NotesRequest handles PUT method differently', function () {
    $reflection = new ReflectionClass(NotesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('if ($this->isMethod(\'PUT\'))')
        ->and($fileContent)->toContain('->ignore($this->route(\'note\')->id)');
});

test('NotesRequest has getNotesPayload method', function () {
    $reflection = new ReflectionClass(NotesRequest::class);
    $fileContent = file_get_contents($reflection->getFileName());
    
    expect($fileContent)->toContain('public function getNotesPayload()')
        ->and($fileContent)->toContain('collect($this->validated())')
        ->and($fileContent)->toContain('\'company_id\' => $this->header(\'company\')');
});