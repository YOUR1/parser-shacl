<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Exceptions\FormatDetectionException;
use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedOntology;
use Youri\vandenBogert\Software\ParserRdf\RdfParser;
use Youri\vandenBogert\Software\ParserShacl\ShaclParser;

describe('ShaclParser', function () {
    beforeEach(function () {
        $this->parser = new ShaclParser();

        $this->turtleContent = <<<'TURTLE'
@prefix owl: <http://www.w3.org/2002/07/owl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ex:Person a owl:Class ;
    rdfs:label "Person"@en .

ex:name a owl:DatatypeProperty ;
    rdfs:label "name"@en ;
    rdfs:domain ex:Person ;
    rdfs:range xsd:string .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
        sh:minCount 1 ;
    ] .
TURTLE;

        $this->rdfxmlContent = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#">
  <owl:Class rdf:about="http://example.org/Animal">
    <rdfs:label xml:lang="en">Animal</rdfs:label>
  </owl:Class>
</rdf:RDF>
XML;
    });

    describe('class hierarchy', function () {
        it('extends RdfParser', function () {
            $reflection = new ReflectionClass(ShaclParser::class);
            expect($reflection->getParentClass()->getName())->toBe(RdfParser::class);
            expect($this->parser)->toBeInstanceOf(RdfParser::class);
        });

        it('is not a final class', function () {
            $reflection = new ReflectionClass(ShaclParser::class);
            expect($reflection->isFinal())->toBeFalse();
        });
    });

    describe('canParse()', function () {
        it('returns true for valid Turtle content', function () {
            expect($this->parser->canParse($this->turtleContent))->toBeTrue();
        });

        it('returns true for valid RDF/XML content', function () {
            expect($this->parser->canParse($this->rdfxmlContent))->toBeTrue();
        });

        it('returns false for invalid content', function () {
            expect($this->parser->canParse('this is not valid RDF content at all'))->toBeFalse();
        });
    });

    describe('getSupportedFormats()', function () {
        it('returns same formats as RdfParser', function () {
            $rdfParser = new RdfParser();
            expect($this->parser->getSupportedFormats())
                ->toBe($rdfParser->getSupportedFormats());
        });

        it('includes multiple format names', function () {
            $formats = $this->parser->getSupportedFormats();
            expect($formats)->toBeArray();
            expect(count($formats))->toBeGreaterThanOrEqual(3);
        });
    });

    describe('parse()', function () {
        it('returns ParsedOntology instance', function () {
            $result = $this->parser->parse($this->turtleContent);
            expect($result)->toBeInstanceOf(ParsedOntology::class);
        });

        it('extracts classes from RDF content', function () {
            $result = $this->parser->parse($this->turtleContent);
            expect($result->classes)->toBeArray()->not->toBeEmpty();

            $personUri = 'http://example.org/Person';
            expect($result->classes)->toHaveKey($personUri);
            expect($result->classes[$personUri]['uri'])->toBe($personUri);
        });

        it('extracts properties from RDF content', function () {
            $result = $this->parser->parse($this->turtleContent);
            expect($result->properties)->toBeArray()->not->toBeEmpty();

            $nameUri = 'http://example.org/name';
            expect($result->properties)->toHaveKey($nameUri);
            expect($result->properties[$nameUri]['uri'])->toBe($nameUri);
        });

        it('extracts prefixes from RDF content', function () {
            $result = $this->parser->parse($this->turtleContent);
            expect($result->prefixes)->toBeArray()->not->toBeEmpty();
            expect($result->prefixes)->toHaveKey('sh');
            expect($result->prefixes['sh'])->toBe('http://www.w3.org/ns/shacl#');
        });

        it('extracts shapes from RDF content with SHACL shapes', function () {
            $result = $this->parser->parse($this->turtleContent);
            expect($result->shapes)->toBeArray()->not->toBeEmpty();

            $shapeUri = 'http://example.org/PersonShape';
            expect($result->shapes)->toHaveKey($shapeUri);
            expect($result->shapes[$shapeUri]['uri'])->toBe($shapeUri);
        });

        it('includes metadata with format and resource_count', function () {
            $result = $this->parser->parse($this->turtleContent);
            expect($result->metadata)->toBeArray();
            expect($result->metadata)->toHaveKey('format');
            expect($result->metadata['format'])->toBeString();
            expect($result->metadata)->toHaveKey('resource_count');
            expect($result->metadata['resource_count'])->toBeInt();
        });

        it('preserves rawContent', function () {
            $result = $this->parser->parse($this->turtleContent);
            expect($result->rawContent)->toBe($this->turtleContent);
        });
    });

    describe('error handling', function () {
        it('throws FormatDetectionException for unrecognizable content', function () {
            expect(fn () => $this->parser->parse('not valid rdf content whatsoever'))
                ->toThrow(FormatDetectionException::class);
        });

        it('throws ParseException for empty content', function () {
            expect(fn () => $this->parser->parse(''))
                ->toThrow(ParseException::class);
        });

        it('throws ParseException for whitespace-only content', function () {
            expect(fn () => $this->parser->parse('   '))
                ->toThrow(ParseException::class);
        });
    });
});
