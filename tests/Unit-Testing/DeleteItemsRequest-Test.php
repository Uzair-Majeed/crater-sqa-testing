<?php
use Crater\Http\Requests\DeleteItemsRequest;
use Crater\Models\Item;
use Crater\Rules\RelationNotExist;
use Illuminate\Validation\Rules\Exists;

/**
     * Test the authorize method to ensure it always returns true.
     */
    test('authorize method always returns true', function () {
        $request = new DeleteItemsRequest();
        expect($request->authorize())->toBeTrue();
    });

    /**
     * Test the rules method to ensure it returns the correct validation rules structure and content.
     */
    test('rules method returns correct validation rules', function () {
        $request = new DeleteItemsRequest();
        $rules = $request->rules();

        // Assert top-level structure of the rules array
        expect($rules)->toBeArray()
            ->toHaveKeys(['ids', 'ids.*']);

        // Assert rules for 'ids' field
        expect($rules['ids'])->toBeArray()
            ->toContain('required');

        // Assert rules for 'ids.*' field
        expect($rules['ids.*'])->toBeArray()
            ->toContain('required');

        // Assert the presence and correct configuration of the Rule::exists('items', 'id')
        $existsRuleFound = false;
        /** @var Exists|null $foundExistsRule */
        $foundExistsRule = null;
        foreach ($rules['ids.*'] as $rule) {
            if ($rule instanceof Exists) {
                $existsRuleFound = true;
                $foundExistsRule = $rule;
                break;
            }
        }
        expect($existsRuleFound)->toBeTrue('Did not find an instance of the Exists rule.');

        // Use reflection to verify the private/protected properties of the found Exists rule
        $reflectionExistsRule = new ReflectionClass($foundExistsRule);

        $tableProperty = $reflectionExistsRule->getProperty('table');
        $tableProperty->setAccessible(true); // Make private/protected property accessible
        expect($tableProperty->getValue($foundExistsRule))->toBe('items', 'Exists rule table property is incorrect.');

        $columnProperty = $reflectionExistsRule->getProperty('column');
        $columnProperty->setAccessible(true); // Make private/protected property accessible
        expect($columnProperty->getValue($foundExistsRule))->toBe('id', 'Exists rule column property is incorrect.');


        // Assert the presence and correct configuration of custom RelationNotExist rules
        $expectedRelationNotExists = [
            new RelationNotExist(Item::class, 'invoiceItems'),
            new RelationNotExist(Item::class, 'estimateItems'),
            new RelationNotExist(Item::class, 'taxes'),
        ];

        $foundRelationNotExists = [];

        foreach ($rules['ids.*'] as $rule) {
            if ($rule instanceof RelationNotExist) {
                $foundRelationNotExists[] = $rule;
            }
        }

        expect($foundRelationNotExists)->toHaveCount(3, 'Expected 3 instances of RelationNotExist rules, but found ' . count($foundRelationNotExists) . '.');

        // Compare each found RelationNotExist instance with the expected ones using object equality.
        // PHP's `==` operator checks if two objects are instances of the same class and have the same attribute values.
        foreach ($expectedRelationNotExists as $expectedRule) {
            $matchFound = false;
            foreach ($foundRelationNotExists as $index => $foundRule) {
                if ($foundRule == $expectedRule) {
                    $matchFound = true;
                    // Remove matched rule from the list to ensure each expected rule has a unique match
                    unset($foundRelationNotExists[$index]);
                    break;
                }
            }

            if (!$matchFound) {
                // If a match is not found, use reflection to get details for a descriptive error message
                $reflectionExpectedRule = new ReflectionClass($expectedRule);
                $expectedModelProp = $reflectionExpectedRule->getProperty('model');
                $expectedModelProp->setAccessible(true);
                $expectedRelationProp = $reflectionExpectedRule->getProperty('relation');
                $expectedRelationProp->setAccessible(true);

                fail("A matching RelationNotExist rule was not found for expected rule (Model: " . $expectedModelProp->getValue($expectedRule) . ", Relation: " . $expectedRelationProp->getValue($expectedRule) . ").");
            }
        }

        // After all expected rules have been matched and removed, the $foundRelationNotExists array should be empty.
        expect($foundRelationNotExists)->toBeEmpty('There were unexpected or duplicate RelationNotExist rules found that did not match any expected rule.');
    });
