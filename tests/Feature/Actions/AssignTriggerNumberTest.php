<?php

namespace Tests\Feature\Actions;

use Mockery;
use App\Models\Tag;
use Tests\TestCase;
use App\Models\Product;
use App\Clients\Shopify;
use App\Models\ReleasedTrigger;
use App\Actions\AssignTriggerNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AssignTriggerNumberTest extends TestCase
{
    use RefreshDatabase;

    private AssignTriggerNumber $action;

    private $shopifyMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shopifyMock = Mockery::mock(Shopify::class);
        $this->action = new AssignTriggerNumber(
            $this->shopifyMock,
            new ReleasedTrigger
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_returns_existing_trigger_if_product_already_has_one(): void
    {
        $product = Product::factory()->create();
        $existingTrigger = 'A001';
        Tag::create([
            'name' => $existingTrigger,
            'product_id' => $product->id,
            'is_system_tag' => true,
        ]);

        $trigger = $this->action->execute($product, 'TEST-001');

        $this->assertEquals($existingTrigger, $trigger);
        $this->assertEquals(1, Tag::where('name', $existingTrigger)->count(), 'No new tags should have been created');
    }

    public function test_uses_released_trigger_if_available(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);
        $releasedTrigger = 'B001';
        ReleasedTrigger::create(['name' => $releasedTrigger]);

        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once()
            ->with($product->shopify_id, 'trigger-' . $releasedTrigger);

        // Use P9 SKU which maps to B, C, D sequence - so B001 released trigger should be used
        $trigger = $this->action->execute($product, 'P9-TEST-001');

        $this->assertEquals($releasedTrigger, $trigger);
        $this->assertDatabaseHas('tags', [
            'name' => $releasedTrigger,
            'product_id' => $product->id,
            'is_system_tag' => true,
        ]);
        $this->assertDatabaseMissing('released_triggers', [
            'name' => $releasedTrigger,
        ]);
    }

    public function test_generates_new_trigger_when_no_released_triggers_available(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);

        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once()
            ->with($product->shopify_id, Mockery::pattern('/trigger-[A-Z][0-9]{3}/'));

        $trigger = $this->action->execute($product, 'TEST-001');

        $this->assertMatchesRegularExpression('/^[A-Z][0-9]{3}$/', $trigger);
        $this->assertDatabaseHas('tags', [
            'name' => $trigger,
            'product_id' => $product->id,
            'is_system_tag' => true,
        ]);
    }

    public function test_skips_already_assigned_triggers_when_generating_new_one(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);

        Tag::create(['name' => 'A001', 'product_id' => Product::factory()->create()->id, 'is_system_tag' => true]);
        Tag::create(['name' => 'A002', 'product_id' => Product::factory()->create()->id, 'is_system_tag' => true]);

        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once();

        $trigger = $this->action->execute($product, 'TEST-001');

        $this->assertMatchesRegularExpression('/^[A-Z][0-9]{3}$/', $trigger);
        $this->assertNotEquals('A001', $trigger);
        $this->assertNotEquals('A002', $trigger);
        $this->assertDatabaseHas('tags', [
            'name' => $trigger,
            'product_id' => $product->id,
            'is_system_tag' => true,
        ]);
    }

    public function test_generates_trigger_within_valid_range(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);

        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once();

        $trigger = $this->action->execute($product, 'TEST-001');

        $letter = $trigger[0];
        $number = (int) substr($trigger, 1);

        $this->assertGreaterThanOrEqual('A', $letter);
        $this->assertLessThanOrEqual('Z', $letter);
        $this->assertGreaterThanOrEqual(1, $number);
        $this->assertLessThanOrEqual(999, $number);
    }

    public function test_generated_trigger_matches_required_format(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);

        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once();

        $trigger = $this->action->execute($product, 'TEST-001');

        $this->assertMatchesRegularExpression('/^[A-Z][0-9]{3}$/', $trigger);

        $this->assertDatabaseHas('tags', [
            'name' => $trigger,
            'product_id' => $product->id,
            'is_system_tag' => true,
        ]);
    }

    public function test_generates_trigger_based_on_p2_sku_prefix(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);

        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once();

        $trigger = $this->action->execute($product, 'P2-TEST-001');

        // Should start with N for P2 prefix
        $this->assertStringStartsWith('N', $trigger);
        $this->assertMatchesRegularExpression('/^[N][0-9]{3}$/', $trigger);
    }

    public function test_generates_trigger_based_on_p4_sku_prefix(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);

        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once();

        $trigger = $this->action->execute($product, 'P4-TEST-001');

        // Should start with R for P4 prefix
        $this->assertStringStartsWith('R', $trigger);
        $this->assertMatchesRegularExpression('/^[R][0-9]{3}$/', $trigger);
    }

    public function test_generates_trigger_based_on_p5_sku_prefix(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);

        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once();

        $trigger = $this->action->execute($product, 'P5-TEST-001');

        // Should start with E for P5 prefix
        $this->assertStringStartsWith('E', $trigger);
        $this->assertMatchesRegularExpression('/^[E][0-9]{3}$/', $trigger);
    }

    public function test_generates_trigger_based_on_p7_sku_prefix(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);

        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once();

        $trigger = $this->action->execute($product, 'P7-TEST-001');

        // Should start with H for P7 prefix
        $this->assertStringStartsWith('H', $trigger);
        $this->assertMatchesRegularExpression('/^[H][0-9]{3}$/', $trigger);
    }

    public function test_generates_trigger_based_on_p9_sku_prefix(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);

        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once();

        $trigger = $this->action->execute($product, 'P9-TEST-001');

        // Should start with B for P9 prefix
        $this->assertStringStartsWith('B', $trigger);
        $this->assertMatchesRegularExpression('/^[B][0-9]{3}$/', $trigger);
    }

    public function test_generates_trigger_with_default_sequence_for_unknown_sku_prefix(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);

        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once();

        $trigger = $this->action->execute($product, 'XY-TEST-001');

        // Should start with A for unknown prefix
        $this->assertStringStartsWith('A', $trigger);
        $this->assertMatchesRegularExpression('/^[A][0-9]{3}$/', $trigger);
    }

    public function test_ignores_released_trigger_that_does_not_match_sku_pattern(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);
        
        // Create a released trigger that doesn't match P4 pattern (should use R, S, T)
        ReleasedTrigger::create(['name' => 'A123']);
        
        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once()
            ->with($product->shopify_id, 'trigger-R001');

        // P4 SKU should generate R001, not use A123 released trigger
        $trigger = $this->action->execute($product, 'P4-TEST-001');

        $this->assertEquals('R001', $trigger);
        // A123 should still be in released_triggers since it wasn't used
        $this->assertDatabaseHas('released_triggers', [
            'name' => 'A123',
        ]);
    }

    public function test_uses_released_trigger_matching_sku_pattern_over_generating_new(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);
        
        // Create released triggers - one matching P4 pattern, one not
        ReleasedTrigger::create(['name' => 'A123']); // Doesn't match P4
        ReleasedTrigger::create(['name' => 'S456']); // Matches P4 (R, S, T)
        
        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once()
            ->with($product->shopify_id, 'trigger-S456');

        // Should use S456 released trigger since it matches P4 pattern
        $trigger = $this->action->execute($product, 'P4-TEST-001');

        $this->assertEquals('S456', $trigger);
        // S456 should be removed from released_triggers
        $this->assertDatabaseMissing('released_triggers', [
            'name' => 'S456',
        ]);
        // A123 should still be there
        $this->assertDatabaseHas('released_triggers', [
            'name' => 'A123',
        ]);
    }

    public function test_proceeds_to_next_letter_when_all_numbers_exhausted(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);
        
        // Fill up all R triggers (R001-R999) for P4 pattern
        for ($i = 1; $i <= 999; $i++) {
            Tag::create([
                'name' => 'R' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'product_id' => Product::factory()->create()->id,
                'is_system_tag' => true,
            ]);
        }
        
        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once()
            ->with($product->shopify_id, 'trigger-S001');

        // Should move to next letter in sequence (S)
        $trigger = $this->action->execute($product, 'P4-TEST-001');

        $this->assertEquals('S001', $trigger);
    }

    public function test_handles_case_insensitive_sku_prefix(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);
        
        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once()
            ->with($product->shopify_id, 'trigger-R001');

        // Lowercase p4 should still work
        $trigger = $this->action->execute($product, 'p4-test-001');

        $this->assertEquals('R001', $trigger);
    }

    public function test_sku_with_less_than_two_characters_uses_default(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);
        
        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once()
            ->with($product->shopify_id, 'trigger-A001');

        // Single character SKU should use default sequence
        $trigger = $this->action->execute($product, 'X');

        $this->assertEquals('A001', $trigger);
    }

    public function test_empty_sku_uses_default_sequence(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);
        
        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once()
            ->with($product->shopify_id, 'trigger-A001');

        // Empty SKU should use default sequence
        $trigger = $this->action->execute($product, '');

        $this->assertEquals('A001', $trigger);
    }

    public function test_released_trigger_selection_respects_alphabetical_order(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);
        
        // Create multiple released triggers for P2 pattern (N, M, P, Q) in non-alphabetical order
        ReleasedTrigger::create(['name' => 'P456']);
        ReleasedTrigger::create(['name' => 'N123']);
        ReleasedTrigger::create(['name' => 'M789']);
        
        $this->shopifyMock
            ->shouldReceive('addTagToProduct')
            ->once()
            ->with($product->shopify_id, 'trigger-M789');

        // Should use M789 (alphabetically first among matching triggers)
        $trigger = $this->action->execute($product, 'P2-TEST-001');

        $this->assertEquals('M789', $trigger);
        $this->assertDatabaseMissing('released_triggers', ['name' => 'M789']);
        $this->assertDatabaseHas('released_triggers', ['name' => 'N123']);
        $this->assertDatabaseHas('released_triggers', ['name' => 'P456']);
    }

    public function test_throws_exception_when_all_triggers_exhausted_for_sku_pattern(): void
    {
        $product = Product::factory()->create([
            'shopify_id' => 12345,
        ]);
        
        // Fill up all triggers for P7 pattern (H, I, J) = 2997 total triggers
        foreach (['H', 'I', 'J'] as $letter) {
            for ($i = 1; $i <= 999; $i++) {
                Tag::create([
                    'name' => $letter . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'product_id' => Product::factory()->create()->id,
                    'is_system_tag' => true,
                ]);
            }
        }
        
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('No available trigger numbers for SKU pattern: P7-TEST-001');

        $this->action->execute($product, 'P7-TEST-001');
    }
}
