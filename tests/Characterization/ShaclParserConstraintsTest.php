<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserShacl\ShaclParser;

describe('ShaclParser constraints', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
    });

    // ============================================================================
    // Task 10: Characterize property shape extraction with constraints (AC: #3)
    // Adapted for Story 6.2-6.3: ShaclShapeProcessor + ShaclPropertyAnalyzer.
    // ============================================================================

    describe('property shape extraction with constraints', function () {

        it('extracts sh:path as simple predicate path string', function () {
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

        it('extracts sh:datatype as full URI', function () {
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
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
        });

        it('extracts sh:minCount as string', function () {
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
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['minCount'])->toBe('1');
        });

        it('extracts sh:maxCount as string', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
        sh:maxCount 1 ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['maxCount'])->toBe('1');
        });

        it('extracts sh:minLength as string', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
        sh:minLength 1 ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['minLength'])->toBe('1');
        });

        it('extracts sh:maxLength as string', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
        sh:maxLength 100 ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['maxLength'])->toBe('100');
        });

        it('extracts sh:pattern as string', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:email ;
        sh:datatype xsd:string ;
        sh:pattern "^[a-z]+$" ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['pattern'])->toBe('^[a-z]+$');
        });

        it('extracts sh:flags as string', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:email ;
        sh:datatype xsd:string ;
        sh:pattern "^[a-z]+$" ;
        sh:flags "i" ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['flags'])->toBe('i');
        });

        it('extracts sh:class as full URI', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:address ;
        sh:class ex:Address ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['class'])->toBe('http://example.org/Address');
        });

        it('extracts sh:nodeKind as full URI', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:identifier ;
        sh:nodeKind sh:IRI ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['nodeKind'])->toBe('http://www.w3.org/ns/shacl#IRI');
        });

        it('extracts sh:in as array of values', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:gender ;
        sh:in ( "Male" "Female" ) ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['in'])->toBe(['Male', 'Female']);
        });

        it('extracts sh:hasValue as value', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:status ;
        sh:hasValue "active" ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['hasValue'])->toBe('active');
        });

        it('extracts sh:equals as URI', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:email ;
        sh:equals ex:primaryEmail ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['equals'])->toBe('http://example.org/primaryEmail');
        });

        it('extracts sh:disjoint as URI', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:nickname ;
        sh:disjoint ex:name ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['disjoint'])->toBe('http://example.org/name');
        });

        it('extracts sh:lessThan as URI', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:startDate ;
        sh:datatype xsd:date ;
        sh:lessThan ex:endDate ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['lessThan'])->toBe('http://example.org/endDate');
        });

        it('extracts sh:lessThanOrEquals as URI', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:minAge ;
        sh:datatype xsd:integer ;
        sh:lessThanOrEquals ex:maxAge ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['lessThanOrEquals'])->toBe('http://example.org/maxAge');
        });

        it('extracts sh:minExclusive as value', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:score ;
        sh:datatype xsd:integer ;
        sh:minExclusive 0 ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['minExclusive'])->toBe('0');
        });

        it('extracts sh:maxExclusive as value', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:score ;
        sh:datatype xsd:integer ;
        sh:maxExclusive 100 ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['maxExclusive'])->toBe('100');
        });

        it('extracts sh:minInclusive as value', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:age ;
        sh:datatype xsd:integer ;
        sh:minInclusive 0 ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['minInclusive'])->toBe('0');
        });

        it('extracts sh:maxInclusive as value', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:age ;
        sh:datatype xsd:integer ;
        sh:maxInclusive 150 ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['maxInclusive'])->toBe('150');
        });

        it('extracts sh:name as multilingual labels', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:name "name"@en, "naam"@nl ;
        sh:datatype xsd:string ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['name'])->toBeString();
            expect($ps['labels'])->toHaveKey('en');
            expect($ps['labels']['en'])->toBe('name');
            expect($ps['labels'])->toHaveKey('nl');
            expect($ps['labels']['nl'])->toBe('naam');
        });

        it('extracts sh:description as multilingual descriptions', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:description "The name"@en ;
        sh:datatype xsd:string ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['description'])->toBe('The name');
            expect($ps['descriptions'])->toHaveKey('en');
            expect($ps['descriptions']['en'])->toBe('The name');
        });

        it('extracts sh:order as string value', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:order 1 ;
        sh:datatype xsd:string ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['order'])->toBe('1');
        });

        it('extracts sh:group as URI', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:MyGroup a sh:PropertyGroup .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:group ex:MyGroup ;
        sh:datatype xsd:string ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['group'])->toBe('http://example.org/MyGroup');
        });

        it('extracts sh:defaultValue as value', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:status ;
        sh:defaultValue "active" ;
        sh:datatype xsd:string ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['defaultValue'])->toBe('active');
        });

        it('extracts sh:message as multilingual messages', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
        sh:minCount 1 ;
        sh:message "Name is required"@en ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['message'])->toBe('Name is required');
            expect($ps['messages'])->toBe(['Name is required']);
        });

        it('extracts sh:severity as mapped string on node shape', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:severity sh:Warning .';
            $result = $this->parser->parse($content);
            $shape = $result->shapes['http://example.org/PersonShape'];
            expect($shape['severity'])->toBe('warning');
            expect($shape['severity_iri'])->toBe('http://www.w3.org/ns/shacl#Warning');
        });

        it('extracts sh:deactivated as boolean', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:deactivated true .';
            $result = $this->parser->parse($content);
            $shape = $result->shapes['http://example.org/PersonShape'];
            expect($shape['deactivated'])->toBeTrue();
        });

        it('handles property shape with multiple constraints', function () {
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
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
            expect($ps['minCount'])->toBe('1');
            expect($ps['maxCount'])->toBe('1');
            expect($ps['minLength'])->toBe('1');
            expect($ps['maxLength'])->toBe('100');
        });

        it('handles property shape with no constraints beyond path', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['path'])->toBe('http://example.org/name');
            expect($ps)->not->toHaveKey('datatype');
            expect($ps)->not->toHaveKey('minCount');
        });
    });

    // ============================================================================
    // Task 11-17: All SHACL-specific constraint and logical constraint tests
    // ============================================================================

    describe('logical constraints (sh:and, sh:or, sh:not, sh:xone)', function () {

        it('extracts sh:and constraint', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:and (
            [ sh:datatype xsd:string ]
            [ sh:minLength 1 ]
        ) ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps)->toHaveKey('sh_and');
            expect($ps['sh_and'])->toHaveCount(2);
            expect($ps['sh_and'][0])->toHaveKey('datatype');
            expect($ps['sh_and'][1])->toHaveKey('minLength');
        });

        it('extracts sh:or constraint', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:date ;
        sh:or (
            [ sh:datatype xsd:date ]
            [ sh:datatype xsd:dateTime ]
        ) ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps)->toHaveKey('sh_or');
            expect($ps['sh_or'])->toHaveCount(2);
        });

        it('extracts sh:not constraint', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:value ;
        sh:not [ sh:datatype xsd:string ] ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps)->toHaveKey('sh_not');
            expect($ps['sh_not'])->toBeArray();
            expect($ps['sh_not']['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
        });

        it('extracts sh:xone constraint', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:id ;
        sh:xone (
            [ sh:datatype xsd:string ]
            [ sh:datatype xsd:integer ]
        ) ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps)->toHaveKey('sh_xone');
            expect($ps['sh_xone'])->toHaveCount(2);
        });
    });

    describe('closed shape constraints', function () {

        it('extracts shape with sh:closed flag as recognized shape', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:ClosedShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:closed true ;
    sh:ignoredProperties ( rdf:type ) ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
    ] .';
            $result = $this->parser->parse($content);
            expect($result->shapes)->toHaveKey('http://example.org/ClosedShape');
            $shape = $result->shapes['http://example.org/ClosedShape'];
            expect($shape['target_class'])->toBe('http://example.org/Person');
            // sh:closed and sh:ignoredProperties are node-level constraints,
            // but the shape is recognized and extracted with property shapes
            expect($shape['property_shapes'])->toHaveCount(1);
        });

        it('extracts shape with sh:ignoredProperties', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
ex:ClosedShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:closed true ;
    sh:ignoredProperties ( rdf:type ) ;
    sh:property [
        sh:path ex:name ;
    ] .';
            $result = $this->parser->parse($content);
            // The shape is recognized and has property_shapes
            $shape = $result->shapes['http://example.org/ClosedShape'];
            expect($shape['property_shapes'])->not->toBeEmpty();
        });
    });

    describe('SPARQL constraints', function () {

        it('recognizes shape with sh:sparql constraint parameter', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:SparqlShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:sparql [
        sh:select "SELECT $this WHERE { $this ex:name ?name }" ;
    ] .';
            $result = $this->parser->parse($content);
            // Shape is recognized due to sh:sparql constraint parameter (SHP-04)
            expect($result->shapes)->toHaveKey('http://example.org/SparqlShape');
        });
    });

    describe('severity handling', function () {

        it('extracts sh:severity as mapped string', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:severity sh:Warning .';
            $result = $this->parser->parse($content);
            $shape = $result->shapes['http://example.org/PersonShape'];
            expect($shape['severity'])->toBe('warning');
        });

        it('defaults severity to violation when not specified', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .';
            $result = $this->parser->parse($content);
            $shape = $result->shapes['http://example.org/PersonShape'];
            expect($shape['severity'])->toBe('violation');
            expect($shape['severity_iri'])->toBeNull();
        });
    });

    describe('sh:qualifiedValueShape constraints', function () {

        it('extracts sh:qualifiedValueShape as URI reference', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:address ;
        sh:qualifiedValueShape [ sh:class ex:HomeAddress ] ;
        sh:qualifiedMinCount 1 ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps)->toHaveKey('qualifiedValueShape');
            expect($ps['qualifiedValueShape'])->toBeString();
        });

        it('extracts sh:qualifiedMinCount as string', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:address ;
        sh:qualifiedValueShape [ sh:class ex:HomeAddress ] ;
        sh:qualifiedMinCount 1 ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['qualifiedMinCount'])->toBe('1');
        });

        it('extracts sh:qualifiedMaxCount as string', function () {
            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:address ;
        sh:qualifiedValueShape [ sh:class ex:HomeAddress ] ;
        sh:qualifiedMinCount 1 ;
        sh:qualifiedMaxCount 2 ;
    ] .';
            $result = $this->parser->parse($content);
            $ps = $result->shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['qualifiedMaxCount'])->toBe('2');
        });
    });
});
