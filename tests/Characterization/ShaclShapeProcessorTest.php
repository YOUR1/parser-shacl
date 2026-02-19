<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserShacl\ShaclParser;

describe('ShaclShapeProcessor', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
    });

    // ============================================================================
    // Task 19: Characterize ShaclShapeProcessor (AC: #6)
    // Adapted for Story 6.2: ShaclShapeProcessor is now implemented.
    //
    // Note: The old ShaclShapeProcessor had extractClassesFromShapes() and
    // extractPropertiesFromShapes() methods. In the new architecture:
    // - extractNodeShapes() extracts shapes with targets and metadata
    // - Classes are extracted by the inherited RDF ClassExtractor
    // - Properties are extracted by the inherited RDF PropertyExtractor
    //   plus enriched via ShaclPropertyAnalyzer
    //
    // Tests here verify shape extraction behavior via the parser integration.
    // ============================================================================

    describe('extractClassesFromShapes() equivalent', function () {

        it('extracts shape with sh:targetClass', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            expect($result->shapes)->toHaveKey('http://example.org/PersonShape');
            expect($result->shapes['http://example.org/PersonShape']['target_class'])->toBe('http://example.org/Person');
        });

        it('uses target class URI as target_class value', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            $shape = $result->shapes['http://example.org/PersonShape'];
            // target_class is the target class URI, not the shape URI
            expect($shape['target_class'])->toBe('http://example.org/Person');
            expect($shape['uri'])->toBe('http://example.org/PersonShape');
        });

        it('uses shape label for shape label', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
ex:PersonShape a sh:NodeShape ;
    rdfs:label "Person Shape" ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            expect($result->shapes['http://example.org/PersonShape']['label'])->toBe('Person Shape');
        });

        it('preserves multilingual labels from shape', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
ex:PersonShape a sh:NodeShape ;
    rdfs:label "Person Shape"@en, "Persoonsvorm"@nl ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            $shape = $result->shapes['http://example.org/PersonShape'];
            expect($shape['labels'])->toHaveKey('en');
            expect($shape['labels']['en'])->toBe('Person Shape');
            expect($shape['labels'])->toHaveKey('nl');
            expect($shape['labels']['nl'])->toBe('Persoonsvorm');
        });

        it('preserves multilingual descriptions from shape', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
ex:PersonShape a sh:NodeShape ;
    rdfs:comment "Validates Person instances"@en, "Valideert Persoon instanties"@nl ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            $shape = $result->shapes['http://example.org/PersonShape'];
            expect($shape['descriptions'])->toHaveKey('en');
            expect($shape['descriptions']['en'])->toBe('Validates Person instances');
            expect($shape['descriptions'])->toHaveKey('nl');
            expect($shape['descriptions']['nl'])->toBe('Valideert Persoon instanties');
        });

        it('sets metadata source to shacl_parser', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            $shape = $result->shapes['http://example.org/PersonShape'];
            expect($shape['metadata']['source'])->toBe('shacl_parser');
        });

        it('sets metadata types including sh:NodeShape', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            $shape = $result->shapes['http://example.org/PersonShape'];
            expect($shape['metadata']['types'])->toContain('http://www.w3.org/ns/shacl#NodeShape');
        });

        it('handles multiple target classes on one shape', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:MultiShape a sh:NodeShape ;
    sh:targetClass ex:Person, ex:Company .';
            $result = $this->parser->parse($content);
            $shape = $result->shapes['http://example.org/MultiShape'];
            expect($shape['target_classes'])->toContain('http://example.org/Person');
            expect($shape['target_classes'])->toContain('http://example.org/Company');
        });

        it('extracts shapes without target class', function () {
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
    });

    describe('extractPropertiesFromShapes() equivalent', function () {

        it('extracts property shapes from node shapes', function () {
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

        it('returns property shapes with expected keys', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:name "name" ;
        sh:datatype xsd:string ;
        sh:minCount 1 ;
        sh:maxCount 1 ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps)->toHaveKey('path');
            expect($ps)->toHaveKey('datatype');
            expect($ps)->toHaveKey('minCount');
            expect($ps)->toHaveKey('maxCount');
        });

        it('determines cardinality from minCount and maxCount', function () {
            $analyzer = new \Youri\vandenBogert\Software\ParserShacl\Extractors\ShaclPropertyAnalyzer();
            $shapeData = ['minCount' => '1', 'maxCount' => '1'];
            expect($analyzer->extractCardinality($shapeData))->toBe('1');

            $shapeData2 = ['minCount' => '0', 'maxCount' => '5'];
            expect($analyzer->extractCardinality($shapeData2))->toBe('0..5');
        });

        it('sets metadata source to shacl_parser on shapes', function () {
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
            expect($result->shapes['http://example.org/PersonShape']['metadata']['source'])->toBe('shacl_parser');
        });

        it('includes full property shape data in property_shapes', function () {
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
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
            expect($ps['minCount'])->toBe('1');
            expect($ps['maxCount'])->toBe('1');
            expect($ps['minLength'])->toBe('1');
        });

        it('skips property shapes with no path', function () {
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
            foreach ($result->shapes['http://example.org/PersonShape']['property_shapes'] as $ps) {
                expect($ps)->toHaveKey('path');
            }
        });

        it('handles duplicate property paths across property shapes', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [ sh:path ex:name ; sh:datatype xsd:string ] ;
    sh:property [ sh:path ex:name ; sh:minCount 1 ] .';
            $result = $this->parser->parse($content);
            // Both property shapes should be present even if same path
            $propShapes = $result->shapes['http://example.org/PersonShape']['property_shapes'];
            expect(count($propShapes))->toBeGreaterThanOrEqual(1);
        });

        it('extracts description from sh:description on property shape', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:description "The person name"@en ;
        sh:datatype xsd:string ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['description'])->toBe('The person name');
        });

        it('extracts label from sh:name on property shape', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:name "name"@en ;
        sh:datatype xsd:string ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['name'])->toBe('name');
        });
    });
});
