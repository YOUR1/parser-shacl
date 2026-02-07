<?php

use App\Services\Ontology\Parsers\ShaclParser;
use App\Services\Ontology\Shacl\ShaclShapeProcessor;

beforeEach(function () {
    $this->parser = new ShaclParser;
    $this->shaclProcessor = new ShaclShapeProcessor;

    // Use local fixture to keep tests deterministic and framework-agnostic.
    $fixturePath = __DIR__.'/../../../Fixtures/Shacl/NlSbb/skos-ap-nl.ttl';

    if (! file_exists($fixturePath)) {
        $this->markTestSkipped('Could not load NL-SBB SHACL fixture');
    }

    $this->shaclContent = file_get_contents($fixturePath);
});

test('it detects source document shape with target objects of', function () {
    $result = $this->parser->parse($this->shaclContent);

    $shapes = $result['shapes'] ?? [];

    $sourceDocShape = null;
    foreach ($shapes as $shape) {
        if (str_contains($shape['uri'], 'SourceDocument')) {
            $sourceDocShape = $shape;
            break;
        }
    }

    expect($sourceDocShape)->not->toBeNull();
    expect($sourceDocShape['uri'])->toBe('http://nlbegrip.nl/def/skosapnl#SourceDocument');
    expect($sourceDocShape['label'])->toBe('Brondocument');
    expect($sourceDocShape['target_objects_of'])->toBe('http://purl.org/dc/terms/source');
    expect($sourceDocShape['target_class'])->toBeNull();
    expect($sourceDocShape['property_shapes'])->toHaveCount(4);
});

test('it extracts source document as class from shape with other targeting', function () {
    $result = $this->parser->parse($this->shaclContent);
    $shapes = $result['shapes'] ?? [];

    $sourceDocShape = null;
    foreach ($shapes as $shape) {
        if (str_contains($shape['uri'], 'SourceDocument')) {
            $sourceDocShape = $shape;
            break;
        }
    }

    expect($sourceDocShape)->not->toBeNull();

    $processorReflection = new ReflectionClass($this->shaclProcessor);
    $extractTargetMethod = $processorReflection->getMethod('extractTargetClassesFromShape');
    $extractTargetMethod->setAccessible(true);

    $targetClasses = $extractTargetMethod->invoke($this->shaclProcessor, $sourceDocShape);

    expect($targetClasses)->toHaveCount(1);

    $targetClass = $targetClasses[0];
    expect($targetClass['uri'])->toBe('http://nlbegrip.nl/def/skosapnl#SourceDocument');
    expect($targetClass['label'])->toBe('Brondocument');
    expect($targetClass['targeting_mechanism'])->toBe('sh:targetObjectsOf');
    expect($targetClass['description'])->toContain('brondocument');
});

test('it preserves shape labels for target classes', function () {
    $result = $this->parser->parse($this->shaclContent);
    $shapes = $result['shapes'] ?? [];

    $conceptShape = null;
    foreach ($shapes as $shape) {
        if ($shape['uri'] === 'http://nlbegrip.nl/def/skosapnl#Concept') {
            $conceptShape = $shape;
            break;
        }
    }

    expect($conceptShape)->not->toBeNull();
    expect($conceptShape['target_class'])->toBe('http://www.w3.org/2004/02/skos/core#Concept');
    expect($conceptShape['label'])->toBe('Begrip');

    $processorReflection = new ReflectionClass($this->shaclProcessor);
    $extractTargetMethod = $processorReflection->getMethod('extractTargetClassesFromShape');
    $extractTargetMethod->setAccessible(true);

    $targetClasses = $extractTargetMethod->invoke($this->shaclProcessor, $conceptShape);

    expect($targetClasses)->toHaveCount(1);

    $targetClass = $targetClasses[0];
    expect($targetClass['uri'])->toBe('http://www.w3.org/2004/02/skos/core#Concept');
    expect($targetClass['label'])->toBe('Begrip');
    expect($targetClass['targeting_mechanism'])->toBe('sh:targetClass');
});

test('it correctly identifies semantic shapes without target class', function () {
    $processorReflection = new ReflectionClass($this->shaclProcessor);
    $isSemanticMethod = $processorReflection->getMethod('isSemanticShapeWithoutTargetClass');
    $isSemanticMethod->setAccessible(true);

    $wellDocumentedShape = [
        'uri' => 'http://example.org/WellDocumentedShape',
        'label' => 'Well Documented Shape',
        'description' => 'This is a well documented shape with clear purpose',
        'target_objects_of' => 'http://example.org/property',
        'property_shapes' => [
            ['path' => 'http://example.org/prop1'],
            ['path' => 'http://example.org/prop2'],
        ],
    ];

    expect($isSemanticMethod->invoke($this->shaclProcessor, $wellDocumentedShape))->toBeTrue();

    $poorlyDocumentedShape = [
        'uri' => 'http://example.org/PoorShape',
        'target_objects_of' => 'http://example.org/property',
        'property_shapes' => [
            ['path' => 'http://example.org/prop1'],
        ],
    ];

    expect($isSemanticMethod->invoke($this->shaclProcessor, $poorlyDocumentedShape))->toBeFalse();

    $shapeWithoutProperties = [
        'uri' => 'http://example.org/EmptyShape',
        'label' => 'Empty Shape',
        'description' => 'Shape without properties',
        'target_objects_of' => 'http://example.org/property',
        'property_shapes' => [],
    ];

    expect($isSemanticMethod->invoke($this->shaclProcessor, $shapeWithoutProperties))->toBeFalse();
});
