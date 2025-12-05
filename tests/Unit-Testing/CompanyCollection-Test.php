<?php

namespace Tests\Unit;

use Crater\Http\Resources\CompanyCollection;
use Crater\Http\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

// Mock a complete company model with all required properties and relationships
class TestCompanyModel extends Model
{
    protected $fillable = ['id', 'name', 'email', 'logo'];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        // Ensure all required properties exist
        $this->id = $attributes['id'] ?? null;
        $this->name = $attributes['name'] ?? null;
        $this->email = $attributes['email'] ?? null;
        $this->logo = $attributes['logo'] ?? null;
    }
    
    public function getKey()
    {
        return $this->id;
    }
    
    // Simulate address relationship
    public function address()
    {
        return new class {
            public function exists() { return false; }
            public function toArray() { return []; }
        };
    }
    
    // Simulate roles relationship  
    public function roles()
    {
        return new Collection();
    }
    
    // Make properties accessible as object properties
    public function __get($key)
    {
        if (property_exists($this, $key)) {
            return $this->$key;
        }
        
        // For relationships
        if (method_exists($this, $key)) {
            return $this->$key();
        }
        
        return null;
    }
}

beforeEach(function () {
    $this->request = new Request();
});

test('it returns an empty array when the collection is empty', function () {
    $collection = new Collection([]);
    $companyCollection = new CompanyCollection($collection);

    $result = $companyCollection->toArray($this->request);

    expect($result)->toBeArray()
        ->toBeEmpty();
});

test('it transforms a collection of objects correctly', function () {
    // Create objects with ALL required properties
    $item1 = new TestCompanyModel(['id' => 1, 'name' => 'Company A', 'email' => 'a@example.com', 'logo' => null]);
    $item2 = new TestCompanyModel(['id' => 2, 'name' => 'Company B', 'email' => 'b@example.com', 'logo' => null]);
    $data = [$item1, $item2];

    $companyCollection = new CompanyCollection($data);
    $result = $companyCollection->toArray($this->request);

    expect($result)->toBeArray()
        ->toHaveCount(2);
});


test('it correctly transforms collection items that are already JsonResource instances', function () {
    $company1 = new TestCompanyModel(['id' => 101, 'name' => 'Mock Company A', 'email' => 'mocka@example.com']);
    $company2 = new TestCompanyModel(['id' => 102, 'name' => 'Mock Company B', 'email' => 'mockb@example.com']);
    
    $resource1 = new CompanyResource($company1);
    $resource2 = new CompanyResource($company2);
    
    $collection = new Collection([$resource1, $resource2]);
    $companyCollection = new CompanyCollection($collection);

    $result = $companyCollection->toArray($this->request);

    expect($result)->toBeArray()
        ->toHaveCount(2);
});


test('it can be instantiated with various collection types', function ($collection) {
    $companyCollection = new CompanyCollection($collection);
    
    expect($companyCollection)->toBeInstanceOf(CompanyCollection::class);
    
    // For empty collection, should work
    if (empty($collection)) {
        expect($companyCollection->toArray($this->request))->toBeArray();
    }
})->with([
    'array' => [[]], // Only test empty array since arrays need special handling
    'collection' => [new Collection([])],
    'paginator' => [new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10, 1)],
    'empty' => [[]],
]);

// Test with proper objects
test('it transforms array of objects wrapped in CompanyResource', function () {
    $objects = [
        new TestCompanyModel(['id' => 1, 'name' => 'Test 1', 'email' => 'test1@example.com']),
        new TestCompanyModel(['id' => 2, 'name' => 'Test 2', 'email' => 'test2@example.com']),
    ];
    
    // Wrap in CompanyResource first
    $resources = array_map(function ($obj) {
        return new CompanyResource($obj);
    }, $objects);
    
    $companyCollection = new CompanyCollection($resources);
    $result = $companyCollection->toArray($this->request);
    
    expect($result)->toBeArray()
        ->toHaveCount(2);
});