<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedOntology;
use Youri\vandenBogert\Software\ParserShacl\ShaclParser;

describe('ShaclParser shape integration', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
    });

    it('returns ParsedOntology with shapes populated from SHACL content', function () {
        $content = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
ex:PersonShape a sh:NodeShape ;
    rdfs:label "Person Shape"@en ;
    sh:targetClass ex:Person .
TTL;
        $result = $this->parser->parse($content);
        expect($result)->toBeInstanceOf(ParsedOntology::class);
        expect($result->shapes)->not->toBeEmpty();

        $shapeUri = 'http://example.org/PersonShape';
        expect($result->shapes)->toHaveKey($shapeUri);
        expect($result->shapes[$shapeUri]['uri'])->toBe($shapeUri);
    });

    it('shapes contain target class information', function () {
        $content = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .
TTL;
        $result = $this->parser->parse($content);
        $shapeUri = 'http://example.org/PersonShape';

        expect($result->shapes)->toHaveKey($shapeUri);
        expect($result->shapes[$shapeUri]['target_class'])->toBe('http://example.org/Person');
        expect($result->shapes[$shapeUri]['target_classes'])->toBe(['http://example.org/Person']);
    });

    it('shapes contain all target declaration types', function () {
        $content = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:MultiShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:targetNode ex:Alice ;
    sh:targetSubjectsOf ex:name ;
    sh:targetObjectsOf ex:knows .
TTL;
        $result = $this->parser->parse($content);
        $shapeUri = 'http://example.org/MultiShape';

        expect($result->shapes)->toHaveKey($shapeUri);
        $shape = $result->shapes[$shapeUri];
        expect($shape['target_class'])->toBe('http://example.org/Person');
        expect($shape['target_node'])->toBe('http://example.org/Alice');
        expect($shape['target_subjects_of'])->toBe('http://example.org/name');
        expect($shape['target_objects_of'])->toBe('http://example.org/knows');
    });

    it('reflects implicit target classes in shapes', function () {
        $content = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .
ex:Person a sh:NodeShape, rdfs:Class .
TTL;
        $result = $this->parser->parse($content);
        $shapeUri = 'http://example.org/Person';

        expect($result->shapes)->toHaveKey($shapeUri);
        expect($result->shapes[$shapeUri]['target_class'])->toBe('http://example.org/Person');
        expect($result->shapes[$shapeUri]['target_classes'])->toContain('http://example.org/Person');
    });

    it('shapes have metadata with source shacl_parser', function () {
        $content = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .
TTL;
        $result = $this->parser->parse($content);
        $shapeUri = 'http://example.org/PersonShape';

        expect($result->shapes[$shapeUri]['metadata']['source'])->toBe('shacl_parser');
        expect($result->shapes[$shapeUri]['metadata']['types'])->toContain('http://www.w3.org/ns/shacl#NodeShape');
    });

    it('recognizes shapes by SHP-03 target predicate without explicit type', function () {
        $content = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape sh:targetClass ex:Person .
TTL;
        $result = $this->parser->parse($content);

        expect($result->shapes)->toHaveKey('http://example.org/PersonShape');
        expect($result->shapes['http://example.org/PersonShape']['target_class'])->toBe('http://example.org/Person');
    });

    it('extracts multilingual labels through full pipeline', function () {
        $content = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    rdfs:label "Person Shape"@en, "Persoonsvorm"@nl ;
    sh:targetClass ex:Person .
TTL;
        $result = $this->parser->parse($content);
        $shapeUri = 'http://example.org/PersonShape';

        expect($result->shapes[$shapeUri]['labels'])->toHaveKey('en');
        expect($result->shapes[$shapeUri]['labels']['en'])->toBe('Person Shape');
        expect($result->shapes[$shapeUri]['labels'])->toHaveKey('nl');
        expect($result->shapes[$shapeUri]['labels']['nl'])->toBe('Persoonsvorm');
    });

    it('preserves other ParsedOntology data alongside shapes', function () {
        $content = <<<'TTL'
@prefix owl: <http://www.w3.org/2002/07/owl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:Person a owl:Class ;
    rdfs:label "Person"@en .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .
TTL;
        $result = $this->parser->parse($content);

        // Classes should still be extracted
        expect($result->classes)->toHaveKey('http://example.org/Person');
        // Shapes should also be present
        expect($result->shapes)->toHaveKey('http://example.org/PersonShape');
        // Prefixes should be present
        expect($result->prefixes)->not->toBeEmpty();
        // rawContent preserved
        expect($result->rawContent)->toBe($content);
    });
});
