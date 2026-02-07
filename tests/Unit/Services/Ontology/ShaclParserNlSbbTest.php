<?php

use App\Services\Ontology\Parsers\ShaclParser;

beforeEach(function () {
    $this->parser = new ShaclParser;

    // Use local fixture to keep tests deterministic and framework-agnostic.
    $fixturePath = __DIR__.'/../../../Fixtures/Shacl/NlSbb/skos-ap-nl.ttl';

    if (! file_exists($fixturePath)) {
        $this->markTestSkipped('Could not load NL-SBB SHACL fixture');
    }

    $this->shaclContent = file_get_contents($fixturePath);
});

test('it does not extract classes from shacl shapes directly', function () {
    $result = $this->parser->parse($this->shaclContent);

    $classes = $result['classes'] ?? [];

    expect($classes)->toHaveCount(0, 'SHACL parser should not extract classes from shapes');

    $shapes = $result['shapes'] ?? [];
    expect(count($shapes))->toBeGreaterThan(0);

    $shapesWithTargetClass = array_filter($shapes, function ($shape) {
        return ! empty($shape['target_class']);
    });

    expect(count($shapesWithTargetClass))->toBeGreaterThan(0);

    foreach ($shapesWithTargetClass as $shape) {
        expect($shape['target_class'])->not->toBeEmpty();
        expect($shape['target_class'])->toContain('http://www.w3.org/2004/02/skos/core#');
    }
});

test('it identifies correct node shapes from nl sbb shacl', function () {
    $result = $this->parser->parse($this->shaclContent);

    $shapes = $result['shapes'] ?? [];

    expect(count($shapes))->toBeGreaterThan(4);

    $nodeShapes = array_filter($shapes, function ($shape) {
        $metadata = $shape['metadata'] ?? [];
        $types = $metadata['types'] ?? [];

        return in_array('http://www.w3.org/ns/shacl#NodeShape', $types);
    });

    expect(count($nodeShapes))->toBeGreaterThanOrEqual(4);
});

test('it extracts meaningful properties from nl sbb shacl', function () {
    $result = $this->parser->parse($this->shaclContent);

    $properties = $result['properties'] ?? [];

    expect(count($properties))->toBeGreaterThan(10);
    expect(count($properties))->toBeLessThan(50);

    foreach ($properties as $property) {
        expect($property['uri'])->not->toBeEmpty();
        expect($property['label'])->not->toBeEmpty();
    }
});

test('it correctly identifies custom namespace', function () {
    $result = $this->parser->parse($this->shaclContent);

    $metadata = $result['metadata'] ?? [];
    $prefixes = $result['prefixes'] ?? [];

    expect($prefixes)->toHaveKey('skosapnl');
    expect($prefixes['skosapnl'])->toBe('http://nlbegrip.nl/def/skosapnl#');

    expect($prefixes)->toHaveKey('skos');
    expect($prefixes)->toHaveKey('sh');
});
