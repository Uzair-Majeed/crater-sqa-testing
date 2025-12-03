<?php

use Crater\Models\Estimate;
use Crater\Models\EstimateItem;
use Crater\Models\Item;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('estimate item belongs to estimate', function () {
    $estimateItem = EstimateItem::factory()->forEstimate()->create();

    $this->assertTrue($estimateItem->estimate()->exists());
});

test('estimate item belongs to item', function () {
    $item = Item::factory()->create();
    $estimate = Estimate::factory()->create();

    $estimateItem = EstimateItem::factory()->create([
        'item_id' => $item->id,
        'estimate_id' => $estimate->id,
    ]);

    $this->assertTrue($estimateItem->item()->exists());
});

test('estimate item has many taxes', function () {
    $estimate = Estimate::factory()->create();
    $estimateItem = EstimateItem::factory()
        ->for($estimate)
        ->hasTaxes(5)
        ->create();

    $this->assertCount(5, $estimateItem->taxes);

    $this->assertTrue($estimateItem->taxes()->exists());
});