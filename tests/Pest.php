<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery as m;

//
// Boot Laravel for ALL tests (Integration + Unit)
//
uses(TestCase::class)->in(__DIR__);

//
// Enable RefreshDatabase ONLY for Integration tests (NOT Unit)
//
uses(RefreshDatabase::class)->in('Integration-Testing');

//
// Close Mockery cleanly
//
beforeEach(function () {
    if (class_exists(m::class)) {
        m::close();
    }
});
afterEach(function () {
    if (class_exists(m::class)) {
        m::close();
    }
});
