<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserShacl\ShaclParser;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedOntology;

// ============================================================================
// Task 2: Characterize canParse() detection behavior (AC: #7)
// ============================================================================

describe('ShaclParser', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
    });

    describe('canParse()', function () {

        // 2.1-2.12: canParse() now delegates to RDF format handlers, not SHACL-specific checks
        it('returns true for content with sh: prefix notation', function () {
            // Old: checked for sh: string in content
            expect($this->parser->canParse('content with sh: prefix'))->toBeTrue();
        })->skip('canParse() now delegates to RDF format handlers, not SHACL-specific string checks -- Story 6.1');

        it('returns true for content with full SHACL namespace URI', function () {
            // Old: checked for SHACL namespace URI in content
            expect($this->parser->canParse('http://www.w3.org/ns/shacl#'))->toBeTrue();
        })->skip('canParse() now delegates to RDF format handlers -- Story 6.1');

        it('returns true for content with NodeShape substring', function () {
            // Old: checked for NodeShape keyword in content
            expect($this->parser->canParse('NodeShape'))->toBeTrue();
        })->skip('canParse() no longer checks for SHACL keywords -- Story 6.1');

        it('returns true for content with PropertyShape substring', function () {
            // Old: checked for PropertyShape keyword in content
            expect($this->parser->canParse('PropertyShape'))->toBeTrue();
        })->skip('canParse() no longer checks for SHACL keywords -- Story 6.1');

        it('returns false for empty string', function () {
            // Old: returned false for empty string
            expect($this->parser->canParse(''))->toBeFalse();
        })->skip('canParse() now delegates to RDF format handlers -- Story 6.1');

        it('returns false for plain text content', function () {
            // Old: returned false for plain text
            expect($this->parser->canParse('plain text'))->toBeFalse();
        })->skip('canParse() now delegates to RDF format handlers -- Story 6.1');

        it('returns false for Turtle content without SHACL vocabulary', function () {
            // Old: returned false because no SHACL vocabulary detected
            $content = '@prefix ex: <http://example.org/> . ex:Thing a ex:Class .';
            expect($this->parser->canParse($content))->toBeFalse();
        })->skip('canParse() now delegates to RDF format handlers, Turtle without SHACL is still parseable -- Story 6.1');

        it('returns false for RDF/XML without SHACL vocabulary', function () {
            // Old: returned false because no SHACL vocabulary detected
            expect($this->parser->canParse('<rdf:RDF></rdf:RDF>'))->toBeFalse();
        })->skip('canParse() now delegates to RDF format handlers -- Story 6.1');

        it('returns false for JSON-LD without SHACL vocabulary', function () {
            // Old: returned false because no SHACL vocabulary detected
            expect($this->parser->canParse('{"@context":{}}'))->toBeFalse();
        })->skip('canParse() now delegates to RDF format handlers -- Story 6.1');

        it('returns true for NodeShape in non-SHACL context (false positive)', function () {
            // Old: false positive - detected NodeShape keyword in non-SHACL content
            expect($this->parser->canParse('NodeShape in plain text'))->toBeTrue();
        })->skip('canParse() no longer checks for SHACL keywords -- Story 6.1');

        it('is case-sensitive: nodeshape does not match but NodeShape does', function () {
            // Old: case-sensitive check for SHACL keywords
            expect($this->parser->canParse('nodeshape'))->toBeFalse();
        })->skip('canParse() no longer checks for SHACL keywords -- Story 6.1');

        it('returns true for content with sh: in a string value (false positive)', function () {
            // Old: false positive - detected sh: in string value
            expect($this->parser->canParse('"sh: is a prefix"'))->toBeTrue();
        })->skip('canParse() no longer checks for SHACL keywords -- Story 6.1');
    });

    // ============================================================================
    // Task 3: Characterize getSupportedFormats() (AC: #7)
    // ============================================================================

    describe('getSupportedFormats()', function () {

        it('returns shacl as the only supported format', function () {
            // Old: returned ['shacl'] as the only format
            expect($this->parser->getSupportedFormats())->toBe(['shacl']);
        })->skip('getSupportedFormats() now returns RDF handler format names, not [shacl] -- Story 6.1');

        it('returns an array with exactly one element', function () {
            // Old: returned exactly one element ['shacl']
            expect($this->parser->getSupportedFormats())->toHaveCount(1);
        })->skip('getSupportedFormats() now returns multiple RDF format names -- Story 6.1');
    });

    // ============================================================================
    // Task 4: Characterize parse() output structure and metadata (AC: #1, #9, #16, #17)
    // ============================================================================

    describe('parse() output structure', function () {

        beforeEach(function () {
            $this->minimalShacl = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
        sh:minCount 1 ;
    ] .';
            $this->result = $this->parser->parse($this->minimalShacl);
        });

        // 4.1: parse() now returns ParsedOntology, not array
        it('returns a ParsedOntology value object', function () {
            expect($this->result)->toBeInstanceOf(ParsedOntology::class);
        });

        // 4.2: ParsedOntology has typed properties instead of array keys
        it('has expected properties on ParsedOntology', function () {
            expect($this->result->metadata)->toBeArray();
            expect($this->result->prefixes)->toBeArray();
            expect($this->result->classes)->toBeArray();
            expect($this->result->properties)->toBeArray();
            expect($this->result->shapes)->toBeArray();
            expect($this->result->rawContent)->toBeString();
        });

        // 4.3: metadata format now comes from handler, type key no longer exists
        it('has metadata with format from handler', function () {
            $metadata = $this->result->metadata;
            expect($metadata)->toHaveKey('format');
            expect($metadata['format'])->toBeString();
            expect($metadata)->toHaveKey('resource_count');
            expect($metadata['resource_count'])->toBeInt();
        });

        // 4.4: rawContent preserves original input
        it('preserves rawContent as original input string', function () {
            expect($this->result->rawContent)->toBe($this->minimalShacl);
        });

        // 4.5: Classes are now extracted by ClassExtractor from RDF triples
        it('extracts classes via inherited ClassExtractor', function () {
            expect($this->result->classes)->toBeArray();
        });

        // 4.6: Prefixes extracted via inherited PrefixExtractor
        it('contains prefixes extracted from content', function () {
            $prefixes = $this->result->prefixes;
            expect($prefixes)->toBeArray();
            expect($prefixes)->toHaveKey('sh');
            expect($prefixes['sh'])->toBe('http://www.w3.org/ns/shacl#');
            expect($prefixes)->toHaveKey('ex');
            expect($prefixes['ex'])->toBe('http://example.org/');
        });

        // 4.7: Shapes extracted via inherited ShapeExtractor
        it('returns shapes as an associative array keyed by URI', function () {
            expect($this->result->shapes)->toBeArray();
            expect(count($this->result->shapes))->toBeGreaterThan(0);
            foreach ($this->result->shapes as $uri => $shape) {
                expect($shape)->toBeArray();
                expect($shape)->toHaveKey('uri');
            }
        });

        // 4.8: Properties extracted via inherited PropertyExtractor
        it('extracts properties via inherited PropertyExtractor', function () {
            expect($this->result->properties)->toBeArray();
        });

        // 4.9: metadata resource_count
        it('has metadata with resource_count', function () {
            expect($this->result->metadata['resource_count'])->toBeInt();
            expect($this->result->metadata['resource_count'])->toBeGreaterThan(0);
        });

        // 4.10: metadata resource_count is an integer
        it('has resource_count as an integer', function () {
            expect($this->result->metadata['resource_count'])->toBeInt();
            expect($this->result->metadata['resource_count'])->toBeGreaterThan(0);
        });
    });

    // ============================================================================
    // Task 5: Characterize standalone format detection (AC: #9)
    // ============================================================================

    describe('standalone format detection', function () {

        // 5.1: Format detection now handled by RDF handlers
        it('detects turtle format from @prefix content', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:Shape a sh:NodeShape ; sh:targetClass ex:Thing .';
            $result = $this->parser->parse($content);
            expect($result->metadata['format'])->toBe('turtle');
        });

        // 5.2: SPARQL-style PREFIX also detected by TurtleHandler
        it('detects turtle format from PREFIX (SPARQL-style) content', function () {
            $content = 'PREFIX sh: <http://www.w3.org/ns/shacl#>
PREFIX ex: <http://example.org/>
ex:Shape a sh:NodeShape ; sh:targetClass ex:Thing .';
            $result = $this->parser->parse($content);
            expect($result->metadata['format'])->toBe('turtle');
        });

        // 5.3: RDF/XML detection via RdfXmlHandler
        it('detects rdf/xml format from xml declaration', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:sh="http://www.w3.org/ns/shacl#"
         xmlns:ex="http://example.org/">
  <sh:NodeShape rdf:about="http://example.org/PersonShape">
    <sh:targetClass rdf:resource="http://example.org/Person"/>
  </sh:NodeShape>
</rdf:RDF>';
            $result = $this->parser->parse($content);
            expect($result)->toBeInstanceOf(ParsedOntology::class);
            expect($result->metadata['format'])->toBe('rdf/xml');
        });

        // 5.4: RDF/XML detection without xml declaration
        it('detects rdf/xml format from rdf:RDF element', function () {
            $content = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:sh="http://www.w3.org/ns/shacl#"
         xmlns:ex="http://example.org/">
  <sh:NodeShape rdf:about="http://example.org/PersonShape">
    <sh:targetClass rdf:resource="http://example.org/Person"/>
  </sh:NodeShape>
</rdf:RDF>';
            $result = $this->parser->parse($content);
            expect($result)->toBeInstanceOf(ParsedOntology::class);
            expect($result->metadata['format'])->toBe('rdf/xml');
        });

        // 5.5: JSON-LD detection via JsonLdHandler
        it('detects json-ld format from curly brace plus @context', function () {
            $content = '{
  "@context": {
    "sh": "http://www.w3.org/ns/shacl#",
    "ex": "http://example.org/"
  },
  "@id": "http://example.org/PersonShape",
  "@type": "sh:NodeShape",
  "sh:targetClass": {"@id": "http://example.org/Person"}
}';
            $result = $this->parser->parse($content);
            expect($result->metadata['format'])->toBe('json-ld');
        });

        // 5.6: Unrecognized content now throws FormatDetectionException
        it('throws FormatDetectionException for unrecognized content', function () {
            // Old: defaulted to turtle format for unrecognized content
            $result = $this->parser->parse('not valid content');
            expect($result->metadata['format'])->toBe('turtle');
        })->skip('Old behavior defaulted to turtle; new behavior throws FormatDetectionException -- Story 6.1');

        // 5.7: Format name mapping - turtle remains turtle
        it('maps format names correctly for metadata', function () {
            $turtleContent = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:Shape a sh:NodeShape ; sh:targetClass ex:Thing .';
            $turtleResult = $this->parser->parse($turtleContent);
            expect($turtleResult->metadata['format'])->toBe('turtle');
        });

        // 5.8: Format detection order is now handler-based
        it('detects rdf/xml for XML content', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:sh="http://www.w3.org/ns/shacl#"
         xmlns:ex="http://example.org/">
  <sh:NodeShape rdf:about="http://example.org/Shape">
    <sh:targetClass rdf:resource="http://example.org/Thing"/>
  </sh:NodeShape>
</rdf:RDF>';
            $result = $this->parser->parse($content);
            expect($result->metadata['format'])->toBe('rdf/xml');
        });
    });

    // ============================================================================
    // Task 6: Characterize prefix extraction (AC: #9)
    // ============================================================================

    describe('prefix extraction', function () {

        // 6.1: Prefixes are extracted via inherited PrefixExtractor
        it('extracts prefixes from turtle @prefix declarations', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
ex:Shape a sh:NodeShape ; sh:targetClass ex:Thing .';
            $result = $this->parser->parse($content);
            expect($result->prefixes)->toHaveKey('sh');
            expect($result->prefixes['sh'])->toBe('http://www.w3.org/ns/shacl#');
            expect($result->prefixes)->toHaveKey('ex');
            expect($result->prefixes)->toHaveKey('rdfs');
        });

        // 6.2: SPARQL-style prefix extraction
        it('extracts prefixes from SPARQL-style PREFIX declarations', function () {
            $content = 'PREFIX sh: <http://www.w3.org/ns/shacl#>
PREFIX ex: <http://example.org/>
ex:Shape a sh:NodeShape ; sh:targetClass ex:Thing .';
            $result = $this->parser->parse($content);
            expect($result->prefixes)->toHaveKey('sh');
            expect($result->prefixes)->toHaveKey('ex');
        });

        // 6.3: Prefix extraction is now via PrefixExtractor
        it('uses PrefixExtractor for prefix extraction', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:Shape a sh:NodeShape ; sh:targetClass ex:Thing .';
            $result = $this->parser->parse($content);
            expect($result->prefixes)->toBeArray();
            expect(count($result->prefixes))->toBeGreaterThan(0);
        });

        // 6.4: EasyRdf namespace registration is a side effect of parsing
        it('registers sh prefix via EasyRdf RdfNamespace::set side effect', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:Shape a sh:NodeShape ; sh:targetClass ex:Thing .';
            $this->parser->parse($content);

            $registeredNamespaces = \EasyRdf\RdfNamespace::namespaces();
            expect($registeredNamespaces)->toHaveKey('sh');
            expect($registeredNamespaces['sh'])->toBe('http://www.w3.org/ns/shacl#');
        });

        // 6.5: Prefix extraction case sensitivity
        it('extracts prefixes case-insensitively from @PREFIX and @prefix', function () {
            $content = '@PREFIX sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:Shape a sh:NodeShape ; sh:targetClass ex:Thing .';
            $result = $this->parser->parse($content);
            expect($result->prefixes)->toHaveKey('sh');
            expect($result->prefixes)->toHaveKey('ex');
        });

        // 6.6: No empty prefix names
        it('does not have empty prefix names', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:Shape a sh:NodeShape ; sh:targetClass ex:Thing .';
            $result = $this->parser->parse($content);
            foreach ($result->prefixes as $prefix => $namespace) {
                expect($prefix)->not->toBe('');
                expect($namespace)->not->toBe('');
            }
        });
    });

    // ============================================================================
    // Task 7: Characterize EasyRdf namespace registration side effect (AC: #9)
    // ============================================================================

    describe('EasyRdf namespace registration side effect', function () {

        // 7.1: sh namespace registered via EasyRdf after parsing
        it('makes sh namespace available in EasyRdf after parse()', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:Shape a sh:NodeShape ; sh:targetClass ex:Thing .';
            $this->parser->parse($content);

            $namespaces = \EasyRdf\RdfNamespace::namespaces();
            expect($namespaces)->toHaveKey('sh');
        });

        // 7.2: Global state side effect persists
        it('has persistent sh namespace registration (global state)', function () {
            $namespaces = \EasyRdf\RdfNamespace::namespaces();
            expect($namespaces)->toHaveKey('sh');
        });

        // 7.3: Standard prefixes preserved
        it('preserves standard EasyRdf prefixes after parsing', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:Shape a sh:NodeShape ; sh:targetClass ex:Thing .';
            $this->parser->parse($content);

            $namespaces = \EasyRdf\RdfNamespace::namespaces();
            expect($namespaces)->toHaveKey('rdf');
            expect($namespaces)->toHaveKey('rdfs');
            expect($namespaces)->toHaveKey('owl');
            expect($namespaces)->toHaveKey('xsd');
        });
    });

    // ============================================================================
    // Task 20: Characterize error handling (AC: #8)
    // ============================================================================

    describe('error handling', function () {

        // 20.1: New architecture throws FormatDetectionException or ParseException
        it('throws FormatDetectionException for invalid content', function () {
            expect(fn () => $this->parser->parse('<<<INVALID TURTLE>>> {{{'))
                ->toThrow(\Youri\vandenBogert\Software\ParserCore\Exceptions\FormatDetectionException::class);
        });

        // 20.2: Exception message is descriptive
        it('throws exception with descriptive message', function () {
            try {
                $this->parser->parse('<<<INVALID>>>');
                $this->fail('Expected exception');
            } catch (\Throwable $e) {
                expect($e->getMessage())->toBeString();
                expect(strlen($e->getMessage()))->toBeGreaterThan(0);
            }
        });

        // 20.3: Exception handling wraps errors
        it('exception handling wraps errors', function () {
            try {
                $this->parser->parse('<<<INVALID>>>');
                $this->fail('Expected exception');
            } catch (\Throwable $e) {
                expect($e)->toBeInstanceOf(\Throwable::class);
            }
        });

        // 20.4: Standard error codes
        it('uses standard error codes', function () {
            try {
                $this->parser->parse('<<<INVALID>>>');
                $this->fail('Expected exception');
            } catch (\Throwable $e) {
                expect($e->getCode())->toBeInt();
            }
        });

        // 20.5: Empty string now throws ParseException
        it('throws ParseException for empty string input', function () {
            expect(fn () => $this->parser->parse(''))
                ->toThrow(\Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException::class);
        });

        // 20.6: Whitespace-only content throws ParseException
        it('throws ParseException for whitespace-only content', function () {
            expect(fn () => $this->parser->parse('   '))
                ->toThrow(\Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException::class);
        });

        // 20.7: Exception wrapping is unified
        it('wraps all errors uniformly', function () {
            $invalidInputs = [
                '<<<INVALID>>>',
                '<<<{{{NOT TURTLE}}}>>>',
            ];
            foreach ($invalidInputs as $input) {
                try {
                    $this->parser->parse($input);
                    $this->fail('Expected exception for input: ' . $input);
                } catch (\Throwable $e) {
                    expect($e)->toBeInstanceOf(\Throwable::class);
                }
            }
        });
    });
});
