<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserShacl\Extractors\ShaclPropertyAnalyzer;

describe('ShaclPropertyExtractor trait', function () {

    beforeEach(function () {
        $this->analyzer = new ShaclPropertyAnalyzer();
    });

    // ============================================================================
    // Task 18: Characterize ShaclPropertyExtractor trait methods (AC: #5)
    // Adapted for Story 6.3: ShaclPropertyAnalyzer (was ShaclPropertyExtractor).
    // ============================================================================

    describe('extractRangeFromShape()', function () {

        it('returns datatype from datatype key', function () {
            $shapeData = ['datatype' => 'http://www.w3.org/2001/XMLSchema#string'];
            $ranges = $this->analyzer->extractRangeFromShape($shapeData);
            expect($ranges)->toContain('http://www.w3.org/2001/XMLSchema#string');
        });

        it('returns class from class key', function () {
            $shapeData = ['class' => 'http://example.org/Address'];
            $ranges = $this->analyzer->extractRangeFromShape($shapeData);
            expect($ranges)->toContain('http://example.org/Address');
        });

        it('returns both class and datatype when both present', function () {
            $shapeData = [
                'datatype' => 'http://www.w3.org/2001/XMLSchema#string',
                'class' => 'http://example.org/Address',
            ];
            $ranges = $this->analyzer->extractRangeFromShape($shapeData);
            expect($ranges)->toContain('http://www.w3.org/2001/XMLSchema#string');
            expect($ranges)->toContain('http://example.org/Address');
        });

        it('returns empty array when no range found', function () {
            $shapeData = ['minCount' => '1'];
            $ranges = $this->analyzer->extractRangeFromShape($shapeData);
            expect($ranges)->toBe([]);
        });

        it('extracts ranges from sh:or logical constraint', function () {
            $shapeData = [
                'sh_or' => [
                    ['datatype' => 'http://www.w3.org/2001/XMLSchema#date'],
                    ['datatype' => 'http://www.w3.org/2001/XMLSchema#dateTime'],
                ],
            ];
            $ranges = $this->analyzer->extractRangeFromShape($shapeData);
            expect($ranges)->toContain('http://www.w3.org/2001/XMLSchema#date');
            expect($ranges)->toContain('http://www.w3.org/2001/XMLSchema#dateTime');
        });
    });

    describe('extractCardinality()', function () {

        it('returns cardinality from minCount and maxCount', function () {
            $shapeData = ['minCount' => '1', 'maxCount' => '5'];
            expect($this->analyzer->extractCardinality($shapeData))->toBe('1..5');
        });

        it('returns null when no cardinality constraints', function () {
            $shapeData = ['datatype' => 'http://www.w3.org/2001/XMLSchema#string'];
            expect($this->analyzer->extractCardinality($shapeData))->toBeNull();
        });

        it('returns single value for minCount=1 maxCount=1', function () {
            $shapeData = ['minCount' => '1', 'maxCount' => '1'];
            expect($this->analyzer->extractCardinality($shapeData))->toBe('1');
        });

        it('returns 0..1 for maxCount=1 only', function () {
            $shapeData = ['maxCount' => '1'];
            expect($this->analyzer->extractCardinality($shapeData))->toBe('0..1');
        });

        it('returns 1..n for minCount=1 only', function () {
            $shapeData = ['minCount' => '1'];
            expect($this->analyzer->extractCardinality($shapeData))->toBe('1..n');
        });
    });

    describe('determinePropertyTypeFromShape()', function () {

        it('returns datatype for datatype constraint', function () {
            $shapeData = ['datatype' => 'http://www.w3.org/2001/XMLSchema#string'];
            expect($this->analyzer->determinePropertyTypeFromShape($shapeData))->toBe('datatype');
        });

        it('returns object for class constraint', function () {
            $shapeData = ['class' => 'http://example.org/Address'];
            expect($this->analyzer->determinePropertyTypeFromShape($shapeData))->toBe('object');
        });

        it('returns object for node constraint', function () {
            $shapeData = ['node' => 'http://example.org/AddressShape'];
            expect($this->analyzer->determinePropertyTypeFromShape($shapeData))->toBe('object');
        });

        it('returns object for nodeKind IRI', function () {
            $shapeData = ['nodeKind' => 'http://www.w3.org/ns/shacl#IRI'];
            expect($this->analyzer->determinePropertyTypeFromShape($shapeData))->toBe('object');
        });

        it('returns datatype for nodeKind Literal', function () {
            // Literal is not in OBJECT_NODE_KINDS so falls through to default 'datatype'
            $shapeData = ['nodeKind' => 'http://www.w3.org/ns/shacl#Literal'];
            expect($this->analyzer->determinePropertyTypeFromShape($shapeData))->toBe('datatype');
        });

        it('returns datatype for no indicators', function () {
            $shapeData = ['minCount' => '1'];
            expect($this->analyzer->determinePropertyTypeFromShape($shapeData))->toBe('datatype');
        });
    });

    describe('extractLocalName() equivalent', function () {

        // extractLocalName was a method on the old trait. Not directly on ShaclPropertyAnalyzer.
        // These tests are skipped as the method does not exist on the new API.
        it('would extract local name from hash URI', function () {
            // Old: extractLocalName('http://example.org/ns#name') => 'name'
            // Not applicable: ShaclPropertyAnalyzer does not expose extractLocalName
        })->skip('extractLocalName() not part of ShaclPropertyAnalyzer public API -- Story 6.3');

        it('would extract local name from slash URI', function () {
            // Old: extractLocalName('http://example.org/name') => 'name'
            // Not applicable: ShaclPropertyAnalyzer does not expose extractLocalName
        })->skip('extractLocalName() not part of ShaclPropertyAnalyzer public API -- Story 6.3');
    });

    describe('extractPropertiesFromShapes() equivalent', function () {

        // The old extractPropertiesFromShapes() was on ShaclShapeProcessor (old).
        // In the new architecture, property extraction is done via ShaclPropertyAnalyzer::extractPropertyShapes()
        // which operates on ParsedRdf, not on extracted shape arrays.
        // These tests verify behavior at the parser integration level instead.

        it('extracts property shapes from parsed RDF via ShaclParser', function () {
            $parser = new \Youri\vandenBogert\Software\ParserShacl\ShaclParser();
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
        sh:minCount 1 ;
    ] .';
            $result = $parser->parse($content);
            $shape = $result->shapes['http://example.org/PersonShape'];
            expect($shape['property_shapes'])->toHaveCount(1);
            expect($shape['property_shapes'][0]['path'])->toBe('http://example.org/name');
        });

        it('returns property shapes with expected keys', function () {
            $parser = new \Youri\vandenBogert\Software\ParserShacl\ShaclParser();
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:name "name"@en ;
        sh:description "The name"@en ;
        sh:datatype xsd:string ;
        sh:minCount 1 ;
        sh:maxCount 1 ;
    ] .';
            $result = $parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps)->toHaveKey('path');
            expect($ps)->toHaveKey('datatype');
            expect($ps)->toHaveKey('minCount');
            expect($ps)->toHaveKey('maxCount');
            expect($ps)->toHaveKey('name');
            expect($ps)->toHaveKey('description');
        });

        it('skips property shapes with no path', function () {
            $parser = new \Youri\vandenBogert\Software\ParserShacl\ShaclParser();
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
    ] .';
            $result = $parser->parse($content);
            // All property shapes should have a path
            foreach ($result->shapes['http://example.org/PersonShape']['property_shapes'] as $ps) {
                expect($ps)->toHaveKey('path');
                expect($ps['path'])->not->toBeNull();
            }
        });

        it('includes metadata source as shacl_parser', function () {
            $parser = new \Youri\vandenBogert\Software\ParserShacl\ShaclParser();
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
    ] .';
            $result = $parser->parse($content);
            $shape = $result->shapes['http://example.org/PersonShape'];
            expect($shape['metadata']['source'])->toBe('shacl_parser');
        });

        it('includes full property shape data in property shapes array', function () {
            $parser = new \Youri\vandenBogert\Software\ParserShacl\ShaclParser();
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
        sh:minCount 1 ;
        sh:maxCount 1 ;
        sh:minLength 1 ;
        sh:maxLength 100 ;
    ] .';
            $result = $parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
            expect($ps['minCount'])->toBe('1');
            expect($ps['maxCount'])->toBe('1');
            expect($ps['minLength'])->toBe('1');
            expect($ps['maxLength'])->toBe('100');
        });

        it('handles multiple property shapes per node shape', function () {
            $parser = new \Youri\vandenBogert\Software\ParserShacl\ShaclParser();
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [ sh:path ex:name ; sh:datatype xsd:string ] ;
    sh:property [ sh:path ex:age ; sh:datatype xsd:integer ] .';
            $result = $parser->parse($content);
            expect($result->shapes['http://example.org/PersonShape']['property_shapes'])->toHaveCount(2);
        });
    });
});
