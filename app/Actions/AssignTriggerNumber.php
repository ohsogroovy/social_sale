<?php

namespace App\Actions;

use App\Models\Tag;
use App\Models\Product;
use App\Clients\Shopify;
use App\Models\ReleasedTrigger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class AssignTriggerNumber
{
    /**
     * SKU prefix to letter sequence mapping
     */
    private const SKU_LETTER_MAPPING = [
        'P2' => ['N', 'M', 'P', 'Q'],  // skip O
        'P4' => ['R', 'S', 'T'],
        'P5' => ['E', 'F', 'G'],
        'P7' => ['H', 'I', 'J'],
        'P9' => ['B', 'C', 'D'],
        'default' => ['A', 'Z', 'Y', 'X'], // fallback sequence
    ];

    private const MIN_NUMBER = 1;
    private const MAX_NUMBER = 999;

    public function __construct(
        private Shopify $shopifyClient,
        private ReleasedTrigger $releasedTriggerModel
    ) {}

    /**
     * Assigns a unique trigger number to a product based on SKU pattern.
     */
    public function execute(Product $product, ?string $sku = null): string
    {
        $existingTrigger = $this->getExistingTrigger($product);
        if ($existingTrigger) {
            Log::info('Auto Trigger: Existing trigger found', [
                'product_id' => $product->id,
                'existing_trigger' => $existingTrigger
            ]);
            return $existingTrigger;
        }

        // Get SKU if not provided
        if (!$sku) {
            $sku = $product->variants()->whereNotNull('sku')->value('sku');
        }

        Log::info('Auto Trigger: Starting trigger assignment', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $sku
        ]);

        $triggerNumber = $this->getNextTriggerNumber($sku);

        Tag::create(['name' => $triggerNumber, 'product_id' => $product->id, 'is_system_tag' => true]);
        $this->shopifyClient->addTagToProduct($product->shopify_id, 'trigger-' . $triggerNumber);

        Log::info('Auto Trigger: Successfully assigned trigger', [
            'product_id' => $product->id,
            'trigger_number' => $triggerNumber,
            'sku' => $sku
        ]);

        return $triggerNumber;
    }

    /**
     * Get existing trigger for a product
     */
    private function getExistingTrigger(Product $product): ?string
    {
        return $product->tags()
            ->whereRaw("name REGEXP '^[A-Z][0-9]{3}$'")
            ->value('name');
    }

    /**
     * Get the next available trigger number based on SKU pattern
     */
    private function getNextTriggerNumber(?string $sku): string
    {
        $letterSequence = $this->getLetterSequenceForSku($sku);
        
        // First try to use a released trigger that matches the SKU pattern
        $releasedTrigger = $this->getMatchingReleasedTrigger($letterSequence);
        if ($releasedTrigger) {
            $releasedTrigger->delete();

            Log::info('Auto Trigger: Using matching released trigger', [
                'trigger' => $releasedTrigger->name,
                'sku' => $sku,
                'letter_sequence' => $letterSequence
            ]);

            return $releasedTrigger->name;
        }

        // Generate new trigger based on SKU pattern
        return $this->generateNewTriggerNumber($sku);
    }

    /**
     * Get a released trigger that matches the letter sequence for the SKU
     */
    private function getMatchingReleasedTrigger(array $letterSequence): ?ReleasedTrigger
    {
        // Create a pattern to match any trigger starting with letters from the sequence
        $letterPattern = '[' . implode('', $letterSequence) . ']';
        
        return $this->releasedTriggerModel
            ->whereRaw("name REGEXP '^{$letterPattern}[0-9]{3}$'")
            ->orderBy('name', 'asc')
            ->first();
    }

    /**
     * Generate a new trigger number based on SKU pattern
     */
    private function generateNewTriggerNumber(?string $sku): string
    {
        $letterSequence = $this->getLetterSequenceForSku($sku);
        $assignedTriggers = $this->getAssignedTriggers();

        Log::info('Auto Trigger: Generating new trigger', [
            'sku' => $sku,
            'letter_sequence' => $letterSequence,
            'assigned_triggers_count' => count($assignedTriggers)
        ]);

        // Try each letter in the sequence
        foreach ($letterSequence as $letter) {
            for ($number = self::MIN_NUMBER; $number <= self::MAX_NUMBER; $number++) {
                $trigger = $letter . str_pad($number, 3, '0', STR_PAD_LEFT);

                if (!isset($assignedTriggers[$trigger])) {
                    Log::info('Auto Trigger: Found available trigger', [
                        'trigger' => $trigger,
                        'letter' => $letter,
                        'number' => $number,
                        'sku' => $sku
                    ]);
                    return $trigger;
                }
            }
        }

        Log::error('Auto Trigger: No available trigger numbers', [
            'sku' => $sku,
            'letter_sequence' => $letterSequence
        ]);

        throw new ModelNotFoundException('No available trigger numbers for SKU pattern: ' . $sku);
    }

    /**
     * Get the letter sequence based on SKU prefix
     */
    private function getLetterSequenceForSku(?string $sku): array
    {
        if (!$sku) {
            Log::info('Auto Trigger: No SKU provided, using default sequence');
            return self::SKU_LETTER_MAPPING['default'];
        }

        $prefix = strtoupper(substr($sku, 0, 2));

        if (isset(self::SKU_LETTER_MAPPING[$prefix])) {
            Log::info('Auto Trigger: SKU prefix matched', [
                'sku' => $sku,
                'prefix' => $prefix,
                'letter_sequence' => self::SKU_LETTER_MAPPING[$prefix]
            ]);
            return self::SKU_LETTER_MAPPING[$prefix];
        }

        Log::info('Auto Trigger: SKU prefix not matched, using default', [
            'sku' => $sku,
            'prefix' => $prefix,
            'letter_sequence' => self::SKU_LETTER_MAPPING['default']
        ]);

        return self::SKU_LETTER_MAPPING['default'];
    }

    /**
     * Get all currently assigned triggers as a flipped array for fast lookup
     */
    private function getAssignedTriggers(): array
    {
        return Tag::whereRaw("name REGEXP '^[A-Z][0-9]{3}$'")
            ->pluck('name')
            ->flip()
            ->toArray();
    }
}
