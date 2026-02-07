<?php

use App\Services\Ontology\Parsers\ShaclParser;

beforeEach(function () {
    $this->parser = new ShaclParser;

    // Load the ADMS-AP SHACL content for testing
    $fixturePath = __DIR__.'/../../../Fixtures/Shacl/AdmsAp/adms-ap_2.0.0.ttl';

    if (! file_exists($fixturePath)) {
        $this->markTestSkipped('ADMS-AP SHACL fixture file not found at: '.$fixturePath);
    }

    $this->shaclContent = file_get_contents($fixturePath);
});

test('it can parse adms ap shacl content', function () {
    $result = $this->parser->parse($this->shaclContent);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('shapes');
    expect($result)->toHaveKey('prefixes');
    expect($result)->toHaveKey('metadata');

    $metadata = $result['metadata'];
    expect($metadata['type'])->toBe('shacl');

    $shapes = $result['shapes'] ?? [];
    expect($shapes)->not->toBeEmpty();
});

test('it detects adms ap prefixes', function () {
    $result = $this->parser->parse($this->shaclContent);

    $prefixes = $result['prefixes'] ?? [];

    $expectedPrefixes = [
        'adms' => 'http://www.w3.org/ns/adms#',
        'dct' => 'http://purl.org/dc/terms/',
        'foaf' => 'http://xmlns.com/foaf/0.1/',
        'vcard' => 'http://www.w3.org/2006/vcard/ns#',
        'skos' => 'http://www.w3.org/2004/02/skos/core#',
    ];

    foreach ($expectedPrefixes as $prefix => $namespace) {
        if (isset($prefixes[$prefix])) {
            expect($prefixes[$prefix])->toBe($namespace, "Should have correct namespace for {$prefix}");
        }
    }

    $foundPrefixes = array_intersect_key($expectedPrefixes, $prefixes);
    expect($foundPrefixes)->not->toBeEmpty();
});

test('it extracts adms ap asset shapes', function () {
    $result = $this->parser->parse($this->shaclContent);

    $shapes = $result['shapes'] ?? [];

    $assetShapes = array_filter($shapes, function ($shape) {
        return str_contains($shape['uri'], 'Asset') ||
               (isset($shape['target_class']) && str_contains($shape['target_class'], 'Asset'));
    });

    if (! empty($assetShapes)) {
        $assetShape = reset($assetShapes);

        expect($assetShape['uri'])->not->toBeEmpty();

        if (! empty($assetShape['property_shapes'])) {
            expect($assetShape['property_shapes'])->toBeArray();

            foreach ($assetShape['property_shapes'] as $propertyShape) {
                expect($propertyShape['path'])->not->toBeEmpty();
            }
        }
    } else {
        expect($shapes)->not->toBeEmpty();
    }
});

test('it handles multilingual content', function () {
    $result = $this->parser->parse($this->shaclContent);

    $shapes = $result['shapes'] ?? [];

    $multilingualShapes = array_filter($shapes, function ($shape) {
        $hasMultilingualLabels = ! empty($shape['labels']) && is_array($shape['labels']) && count($shape['labels']) > 1;
        $hasMultilingualDescriptions = ! empty($shape['descriptions']) && is_array($shape['descriptions']) && count($shape['descriptions']) > 1;

        return $hasMultilingualLabels || $hasMultilingualDescriptions;
    });

    if (empty($multilingualShapes)) {
        // No multilingual shapes found - assert we at least parsed some shapes
        expect($shapes)->not->toBeEmpty();

        return;
    }
    $multilingualShape = reset($multilingualShapes);

    if (! empty($multilingualShape['labels'])) {
        expect($multilingualShape['labels'])->toBeArray();

        foreach ($multilingualShape['labels'] as $lang => $label) {
            expect($lang)->toBeString();
            expect($label)->toBeString();
            expect($label)->not->toBeEmpty();
        }
    }
});

test('it handles adms ap specific constraints', function () {
    $result = $this->parser->parse($this->shaclContent);

    $shapes = $result['shapes'] ?? [];

    foreach ($shapes as $shape) {
        if (empty($shape['property_shapes'])) {
            continue;
        }
        foreach ($shape['property_shapes'] as $propertyShape) {
            if (! empty($propertyShape['path']) && is_string($propertyShape['path'])) {
                expect($propertyShape['path'])->toStartWith('http');
            }

            foreach (['minCount', 'maxCount', 'minLength', 'maxLength'] as $constraint) {
                if (isset($propertyShape[$constraint])) {
                    expect($propertyShape[$constraint])->toBeNumeric();
                }
            }

            if (! empty($propertyShape['datatype'])) {
                expect($propertyShape['datatype'])->toStartWith('http');
            }

            if (! empty($propertyShape['class'])) {
                expect($propertyShape['class'])->toStartWith('http');
            }
        }
    }

    $constrainedShapes = array_filter($shapes, function ($shape) {
        return ! empty($shape['property_shapes']) && count($shape['property_shapes']) > 0;
    });

    expect($constrainedShapes)->not->toBeEmpty();
});

test('it preserves shape severity and messages', function () {
    $result = $this->parser->parse($this->shaclContent);

    $shapes = $result['shapes'] ?? [];

    foreach ($shapes as $shape) {
        // Severity can be a built-in value or a custom IRI
        expect($shape['severity'])->toBeString();

        if (isset($shape['message'])) {
            expect($shape['message'])->toBeString();
        }

        if (empty($shape['property_shapes'])) {
            continue;
        }
        foreach ($shape['property_shapes'] as $propertyShape) {
            if (isset($propertyShape['message'])) {
                expect($propertyShape['message'])->toBeString();
            }
        }
    }
});
