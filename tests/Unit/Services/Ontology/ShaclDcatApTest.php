<?php

use App\Services\Ontology\Parsers\ShaclParser;

beforeEach(function () {
    $this->parser = new ShaclParser;

    // Load the DCAT-AP SHACL content for testing
    $fixturePath = __DIR__.'/../../../Fixtures/Shacl/DcatAp/dcat-ap_2.1.1.ttl';

    if (! file_exists($fixturePath)) {
        $this->markTestSkipped('DCAT-AP SHACL fixture file not found at: '.$fixturePath);
    }

    $this->shaclContent = file_get_contents($fixturePath);
});

test('it can parse dcat ap shacl content', function () {
    $result = $this->parser->parse($this->shaclContent);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('shapes');
    expect($result)->toHaveKey('prefixes');
    expect($result)->toHaveKey('metadata');

    $metadata = $result['metadata'];
    expect($metadata['type'])->toBe('shacl');
});

test('it detects dcat ap prefixes', function () {
    $result = $this->parser->parse($this->shaclContent);

    $prefixes = $result['prefixes'] ?? [];

    $expectedPrefixes = [
        'dcat' => 'http://www.w3.org/ns/dcat#',
        'dct' => 'http://purl.org/dc/terms/',
        'foaf' => 'http://xmlns.com/foaf/0.1/',
        'vcard' => 'http://www.w3.org/2006/vcard/ns#',
    ];

    foreach ($expectedPrefixes as $prefix => $namespace) {
        expect($prefixes)->toHaveKey($prefix);
        expect($prefixes[$prefix])->toBe($namespace);
    }
});

test('it extracts dcat ap node shapes', function () {
    $result = $this->parser->parse($this->shaclContent);

    $shapes = $result['shapes'] ?? [];
    expect($shapes)->not->toBeEmpty();

    $shapeUris = array_column($shapes, 'uri');

    $foundDatasetShape = false;
    $foundCatalogShape = false;

    foreach ($shapeUris as $uri) {
        if (str_contains($uri, 'Dataset')) {
            $foundDatasetShape = true;
        }
        if (str_contains($uri, 'Catalog')) {
            $foundCatalogShape = true;
        }
    }

    expect($foundDatasetShape || $foundCatalogShape)->toBeTrue();

    foreach ($shapes as $shape) {
        expect($shape['uri'])->not->toBeEmpty();
        expect($shape['metadata'])->toBeArray();

        if (! empty($shape['target_class'])) {
            expect($shape['target_class'])->toStartWith('http');
        }
    }
});

test('it handles dcat ap property constraints', function () {
    $result = $this->parser->parse($this->shaclContent);

    $shapes = $result['shapes'] ?? [];

    $shapesWithProperties = array_filter($shapes, function ($shape) {
        return ! empty($shape['property_shapes']) && count($shape['property_shapes']) > 0;
    });

    expect($shapesWithProperties)->not->toBeEmpty();

    foreach ($shapesWithProperties as $shape) {
        foreach ($shape['property_shapes'] as $propertyShape) {
            expect($propertyShape['path'])->not->toBeEmpty();

            $hasConstraint = ! empty($propertyShape['minCount']) ||
                           ! empty($propertyShape['maxCount']) ||
                           ! empty($propertyShape['datatype']) ||
                           ! empty($propertyShape['class']) ||
                           ! empty($propertyShape['nodeKind']);

            expect($hasConstraint)->toBeTrue();
        }
    }
});
