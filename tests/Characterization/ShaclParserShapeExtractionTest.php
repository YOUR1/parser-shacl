<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserShacl\ShaclParser;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedOntology;

describe('ShaclParser shape extraction', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
    });

    // ============================================================================
    // Task 8: Characterize node shape extraction (AC: #2)
    // ============================================================================

    describe('node shape extraction', function () {

        // 8.1: Shape recognized by explicit a sh:NodeShape type declaration
        it('recognizes shape by explicit sh:NodeShape type declaration (SHP-01)', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            expect($result->shapes)->not->toBeEmpty();
            $shapeUri = 'http://example.org/PersonShape';
            expect($result->shapes)->toHaveKey($shapeUri);
            expect($result->shapes[$shapeUri]['uri'])->toBe($shapeUri);
        });

        // 8.2: Shape recognized by a sh:PropertyShape type declaration
        it('recognizes shape by sh:PropertyShape type declaration (SHP-02)', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:NameShape a sh:PropertyShape ;
    sh:path ex:name ;
    sh:datatype xsd:string .';
            $result = $this->parser->parse($content);
            expect($result->shapes)->not->toBeEmpty();
            $shapeUris = array_keys($result->shapes);
            expect($shapeUris)->toContain('http://example.org/NameShape');
        });

        // 8.3: Shape recognized by target predicate without explicit type -- now implemented in Story 6.2
        it('recognizes shape by target predicate without explicit type (SHP-03)', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            expect($result->shapes)->not->toBeEmpty();
            expect($result->shapes)->toHaveKey('http://example.org/PersonShape');
        });

        // 8.4: Shape recognized by constraint parameter without explicit type -- now implemented in Story 6.2
        it('recognizes shape by constraint parameter without explicit type (SHP-04)', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape sh:property [
    sh:path ex:name ;
    sh:datatype xsd:string ;
] .';
            $result = $this->parser->parse($content);
            expect($result->shapes)->not->toBeEmpty();
            expect($result->shapes)->toHaveKey('http://example.org/PersonShape');
        });

        // 8.5: Blank nodes excluded from top-level shapes
        it('excludes blank nodes from SHP-03 and SHP-04 recognition', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
    ] .';
            $result = $this->parser->parse($content);
            // Only named shapes should appear
            foreach ($result->shapes as $uri => $shape) {
                expect($uri)->not->toStartWith('_:');
            }
        });

        // 8.6: Blank nodes with explicit sh:NodeShape type -- now implemented in Story 6.2
        it('handles blank nodes with explicit sh:NodeShape type (SHP-01)', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
[
    a sh:NodeShape ;
    sh:targetClass ex:Person
] .';
            $result = $this->parser->parse($content);
            expect($result->shapes)->toBeArray();
            // Blank node shapes with explicit type are included
            $blankNodeShapes = array_filter($result->shapes, fn (array $shape): bool => str_starts_with($shape['uri'], '_:'));
            expect(count($blankNodeShapes))->toBeGreaterThanOrEqual(1);
        });

        // 8.7: Shape key structure -- now implemented in Story 6.2
        it('extracts shape with all expected keys', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
ex:PersonShape a sh:NodeShape ;
    rdfs:label "Person Shape" ;
    rdfs:comment "Validates Person instances" ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            $shapeUri = 'http://example.org/PersonShape';
            expect($result->shapes)->toHaveKey($shapeUri);
            $shape = $result->shapes[$shapeUri];
            expect($shape)->toHaveKey('uri');
            expect($shape)->toHaveKey('label');
            expect($shape)->toHaveKey('labels');
            expect($shape)->toHaveKey('description');
            expect($shape)->toHaveKey('descriptions');
            expect($shape)->toHaveKey('target_class');
            expect($shape)->toHaveKey('target_classes');
            expect($shape)->toHaveKey('metadata');
        });

        // 8.8: Shape metadata -- now implemented in Story 6.2
        it('includes metadata with source and types', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            expect($result->shapes)->not->toBeEmpty();
            $shapeUri = 'http://example.org/PersonShape';
            expect($result->shapes[$shapeUri]['metadata']['source'])->toBe('shacl_parser');
            expect($result->shapes[$shapeUri]['metadata']['types'])->toContain('http://www.w3.org/ns/shacl#NodeShape');
        });

        // 8.9: Multiple shapes from same document
        it('extracts all shapes from a document with multiple shapes', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .
ex:CompanyShape a sh:NodeShape ;
    sh:targetClass ex:Company .';
            $result = $this->parser->parse($content);
            expect(count($result->shapes))->toBeGreaterThanOrEqual(2);
            $uris = array_keys($result->shapes);
            expect($uris)->toContain('http://example.org/PersonShape');
            expect($uris)->toContain('http://example.org/CompanyShape');
        });

        // 8.10: Shape without target class -- now implemented in Story 6.2
        it('returns shape without targetClass', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:NameShape a sh:NodeShape ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
    ] .';
            $result = $this->parser->parse($content);
            expect($result->shapes)->toHaveKey('http://example.org/NameShape');
            expect($result->shapes['http://example.org/NameShape']['target_class'])->toBeNull();
        });

        // 8.11: Shape URI is always a full URI string
        it('has full URI string for shape uri', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            $shapeUri = 'http://example.org/PersonShape';
            expect($result->shapes)->toHaveKey($shapeUri);
            expect($result->shapes[$shapeUri]['uri'])->toBeString();
            expect($result->shapes[$shapeUri]['uri'])->toBe($shapeUri);
        });
    });

    // ============================================================================
    // Task 9: Characterize target declaration extraction (AC: #4)
    // ============================================================================

    describe('target declaration extraction', function () {

        it('extracts single sh:targetClass value', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            $shapeUri = 'http://example.org/PersonShape';
            expect($result->shapes[$shapeUri]['target_class'])->toBe('http://example.org/Person');
            expect($result->shapes[$shapeUri]['target_classes'])->toBe(['http://example.org/Person']);
        });

        it('extracts multiple sh:targetClass values', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonCompanyShape a sh:NodeShape ;
    sh:targetClass ex:Person, ex:Company .';
            $result = $this->parser->parse($content);
            $shapeUri = 'http://example.org/PersonCompanyShape';
            expect($result->shapes[$shapeUri]['target_classes'])->toContain('http://example.org/Person');
            expect($result->shapes[$shapeUri]['target_classes'])->toContain('http://example.org/Company');
        });

        it('extracts single sh:targetNode value', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:AliceShape a sh:NodeShape ;
    sh:targetNode ex:Alice .';
            $result = $this->parser->parse($content);
            $shapeUri = 'http://example.org/AliceShape';
            expect($result->shapes[$shapeUri]['target_node'])->toBe('http://example.org/Alice');
            expect($result->shapes[$shapeUri]['target_nodes'])->toBe(['http://example.org/Alice']);
        });

        it('extracts multiple sh:targetNode values', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:NamedShape a sh:NodeShape ;
    sh:targetNode ex:Alice, ex:Bob .';
            $result = $this->parser->parse($content);
            $shapeUri = 'http://example.org/NamedShape';
            expect($result->shapes[$shapeUri]['target_nodes'])->toContain('http://example.org/Alice');
            expect($result->shapes[$shapeUri]['target_nodes'])->toContain('http://example.org/Bob');
        });

        it('extracts sh:targetSubjectsOf value', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:HasNameShape a sh:NodeShape ;
    sh:targetSubjectsOf ex:name .';
            $result = $this->parser->parse($content);
            $shapeUri = 'http://example.org/HasNameShape';
            expect($result->shapes[$shapeUri]['target_subjects_of'])->toBe(['http://example.org/name']);
        });

        it('extracts sh:targetObjectsOf value', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:KnowsObjectShape a sh:NodeShape ;
    sh:targetObjectsOf ex:knows .';
            $result = $this->parser->parse($content);
            $shapeUri = 'http://example.org/KnowsObjectShape';
            expect($result->shapes[$shapeUri]['target_objects_of'])->toBe(['http://example.org/knows']);
        });

        it('extracts multiple targeting mechanisms on same shape', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:MultiShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:targetNode ex:Alice ;
    sh:targetSubjectsOf ex:name ;
    sh:targetObjectsOf ex:knows .';
            $result = $this->parser->parse($content);
            $shapeUri = 'http://example.org/MultiShape';
            expect($result->shapes[$shapeUri]['target_class'])->toBe('http://example.org/Person');
            expect($result->shapes[$shapeUri]['target_node'])->toBe('http://example.org/Alice');
            expect($result->shapes[$shapeUri]['target_subjects_of'])->toBe(['http://example.org/name']);
            expect($result->shapes[$shapeUri]['target_objects_of'])->toBe(['http://example.org/knows']);
        });

        it('extracts property shapes with sh:path from node shape', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
    ] .';
            $result = $this->parser->parse($content);
            $shape = $result->shapes['http://example.org/PersonShape'];
            expect($shape['property_shapes'])->toHaveCount(1);
            expect($shape['property_shapes'][0]['path'])->toBe('http://example.org/name');
        });
    });

    // ============================================================================
    // Task 21: Characterize multilingual label and description extraction (AC: #1)
    // ============================================================================

    describe('multilingual label and description extraction', function () {

        it('extracts language-tagged labels keyed by language tag', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    rdfs:label "Person Shape"@en, "Persoonsvorm"@nl ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            $shapeUri = 'http://example.org/PersonShape';
            expect($result->shapes[$shapeUri]['labels'])->toHaveKey('en');
            expect($result->shapes[$shapeUri]['labels']['en'])->toBe('Person Shape');
            expect($result->shapes[$shapeUri]['labels'])->toHaveKey('nl');
            expect($result->shapes[$shapeUri]['labels']['nl'])->toBe('Persoonsvorm');
        });

        it('stores unlanguaged labels as en default', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    rdfs:label "Person Shape" ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            $shapeUri = 'http://example.org/PersonShape';
            expect($result->shapes[$shapeUri]['labels'])->toHaveKey('en');
            expect($result->shapes[$shapeUri]['labels']['en'])->toBe('Person Shape');
        });

        it('extracts labels from rdfs:label, sh:name, skos:prefLabel, dc:title, dcterms:title', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:name "Person Shape via sh:name"@en ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            $shapeUri = 'http://example.org/PersonShape';
            expect($result->shapes[$shapeUri]['labels'])->toHaveKey('en');
            expect($result->shapes[$shapeUri]['labels']['en'])->toBe('Person Shape via sh:name');
        });

        it('extracts descriptions from rdfs:comment, sh:description, skos:definition', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:description "Via sh:description"@en ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            $shapeUri = 'http://example.org/PersonShape';
            expect($result->shapes[$shapeUri]['descriptions'])->toHaveKey('en');
            expect($result->shapes[$shapeUri]['descriptions']['en'])->toBe('Via sh:description');
        });

        it('returns first non-null label from priority list', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    rdfs:label "RDFS Label"@en ;
    sh:name "SHACL Name"@en ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            $shapeUri = 'http://example.org/PersonShape';
            // rdfs:label has priority over sh:name
            expect($result->shapes[$shapeUri]['label'])->toBe('RDFS Label');
        });

        it('returns first non-null description from priority list', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    rdfs:comment "RDFS Comment"@en ;
    sh:description "SHACL Desc"@en ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            $shapeUri = 'http://example.org/PersonShape';
            // rdfs:comment has priority over sh:description
            expect($result->shapes[$shapeUri]['description'])->toBe('RDFS Comment');
        });
    });
});
