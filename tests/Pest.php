<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery as m;

//
// Boot Laravel for ALL tests (Feature + Unit)
//
uses(TestCase::class)->in(__DIR__);

//
// Enable RefreshDatabase ONLY for Feature tests (NOT Unit)
//
uses(RefreshDatabase::class)->in('Feature');

//
// Close Mockery cleanly
//
afterEach(function () {
    if (class_exists(m::class)) {
        m::close();
    }
});
