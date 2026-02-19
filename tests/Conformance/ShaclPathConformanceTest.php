<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserShacl\ShaclParser;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedOntology;

// =============================================================================
// W3C SHACL Conformance Tests - Property Paths
// Verifies extraction of SHACL property path types per W3C SHACL Section 2.3.
// =============================================================================

describe('W3C SHACL Conformance - Property Paths', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
        $this->fixturesDir = __DIR__ . '/../Fixtures/W3c';
    });

    it('extracts simple predicate path as URI string [W3C path-predicate-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/path-predicate-001.ttl');
        $result = $this->parser->parse($content);

        expect($result)->toBeInstanceOf(ParsedOntology::class);

        $shape = $result->shapes['http://example.org/PredicatePathShape'];
        expect($shape['property_shapes'])->toHaveCount(1);

        $ps = $shape['property_shapes'][0];
        expect($ps['path'])->toBeString();
        expect($ps['path'])->toBe('http://example.org/name');
        expect($ps['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
        expect($ps['minCount'])->toBe('1');
    });

    it('extracts inverse path as array with type and path [W3C path-inverse-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/path-inverse-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/InversePathShape'];
        expect($shape['property_shapes'])->toHaveCount(1);

        $ps = $shape['property_shapes'][0];
        expect($ps['path'])->toBeArray();
        expect($ps['path']['type'])->toBe('inverse');
        expect($ps['path']['path'])->toBe('http://example.org/knows');
        expect($ps['class'])->toBe('http://example.org/Person');
    });

    it('extracts sequence path as array with type and paths [W3C path-sequence-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/path-sequence-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/SequencePathShape'];
        expect($shape['property_shapes'])->toHaveCount(1);

        $ps = $shape['property_shapes'][0];
        expect($ps['path'])->toBeArray();
        expect($ps['path']['type'])->toBe('sequence');
        expect($ps['path']['paths'])->toBe([
            'http://example.org/address',
            'http://example.org/city',
        ]);
        expect($ps['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
    });

    it('extracts alternative path as array with type and paths [W3C path-alternative-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/path-alternative-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/AlternativePathShape'];
        expect($shape['property_shapes'])->toHaveCount(1);

        $ps = $shape['property_shapes'][0];
        expect($ps['path'])->toBeArray();
        expect($ps['path']['type'])->toBe('alternative');
        expect($ps['path']['paths'])->toBe([
            'http://example.org/name',
            'http://example.org/label',
        ]);
        expect($ps['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
        expect($ps['minCount'])->toBe('1');
    });

    it('extracts zero-or-more path as array with type and path [W3C path-zeroOrMore-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/path-zeroOrMore-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/ZeroOrMorePathShape'];
        expect($shape['property_shapes'])->toHaveCount(1);

        $ps = $shape['property_shapes'][0];
        expect($ps['path'])->toBeArray();
        expect($ps['path']['type'])->toBe('zeroOrMore');
        expect($ps['path']['path'])->toBe('http://example.org/knows');
        expect($ps['class'])->toBe('http://example.org/Person');
    });

    it('extracts one-or-more path as array with type and path [W3C path-oneOrMore-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/path-oneOrMore-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/OneOrMorePathShape'];
        expect($shape['property_shapes'])->toHaveCount(1);

        $ps = $shape['property_shapes'][0];
        expect($ps['path'])->toBeArray();
        expect($ps['path']['type'])->toBe('oneOrMore');
        expect($ps['path']['path'])->toBe('http://example.org/parent');
        expect($ps['class'])->toBe('http://example.org/Person');
    });

    it('extracts zero-or-one path as array with type and path [W3C path-zeroOrOne-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/path-zeroOrOne-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/ZeroOrOnePathShape'];
        expect($shape['property_shapes'])->toHaveCount(1);

        $ps = $shape['property_shapes'][0];
        expect($ps['path'])->toBeArray();
        expect($ps['path']['type'])->toBe('zeroOrOne');
        expect($ps['path']['path'])->toBe('http://example.org/spouse');
        expect($ps['class'])->toBe('http://example.org/Person');
    });
});
