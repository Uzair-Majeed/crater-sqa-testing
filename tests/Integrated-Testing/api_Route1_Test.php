<?php
// tests/Integrated-Testing/CraterRoutesTest.php

use Tests\TestCase;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(TestCase::class);

beforeEach(function () {
    // Fresh session for each test
    Session::flush();
});

test('GET /login shows login page', function () {
    get('/login')
        ->assertStatus(200)
        ->assertViewIs('app'); // Crater uses Vue SPA
});

test('POST /login with empty data redirects back with errors', function () {
    post('/login', [])
        ->assertStatus(302)
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email', 'password']);
});

test('GET /admin redirects to login when not authenticated', function () {
    get('/admin')
        ->assertStatus(302)
        ->assertRedirect('/login');
});

test('GET / shows home page for guests', function () {
    get('/')
        ->assertStatus(200)
        ->assertSee('<!DOCTYPE html>', false); // Check it returns HTML
});

test('GET /installation shows installation page', function () {
    get('/installation')
        ->assertStatus(200)
        ->assertSee('Installation', false);
});

test('customer portal route requires company slug', function () {
    // Test with a fake company slug
    get('/test-company/customer')
        ->assertStatus(200)
        ->assertSee('app', false);
});