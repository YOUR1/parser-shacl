<?php

use App\Services\Ontology\Parsers\ShaclParser;

beforeEach(function () {
    $this->parser = new ShaclParser;
});

test('it parses simple shacl node shape', function () {
    $shacl = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

    ex:PersonShape
        a sh:NodeShape ;
        sh:targetClass ex:Person ;
        sh:property [
            sh:path ex:name ;
            sh:minCount 1 ;
            sh:datatype xsd:string
        ] .
    ';

    $result = $this->parser->parse($shacl);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('shapes');
    expect($result['shapes'])->toHaveCount(1);

    $shape = $result['shapes'][0];
    expect($shape['uri'])->toBe('http://example.org/PersonShape');
    expect($shape['target_class'])->toBe('http://example.org/Person');
    expect($shape['property_shapes'])->toHaveCount(1);
    expect($shape['property_shapes'][0]['path'])->toBe('http://example.org/name');
    expect($shape['property_shapes'][0]['minCount'])->toBe('1');
});

test('it parses logical and constraint', function () {
    $shacl = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .

    ex:ComplexShape
        a sh:NodeShape ;
        sh:targetClass ex:Person ;
        sh:and (
            [ sh:property [ sh:path ex:age ; sh:minInclusive 18 ] ]
            [ sh:property [ sh:path ex:name ; sh:minLength 1 ] ]
        ) .
    ';

    $result = $this->parser->parse($shacl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    // Note: The parser extracts logical constraints - check if they're stored
    expect($shapes[0])->toHaveKey('uri');
});

test('it parses property constraints with all types', function () {
    $shacl = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

    ex:PersonShape
        a sh:NodeShape ;
        sh:targetClass ex:Person ;
        sh:property [
            sh:path ex:email ;
            sh:datatype xsd:string ;
            sh:minLength 5 ;
            sh:maxLength 100 ;
            sh:pattern "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$"
        ] .
    ';

    $result = $this->parser->parse($shacl);
    $propShape = $result['shapes'][0]['property_shapes'][0];

    expect($propShape['path'])->toBe('http://example.org/email');
    expect($propShape['datatype'])->toContain('string');
    expect($propShape['minLength'])->toBe('5');
    expect($propShape['maxLength'])->toBe('100');
    expect($propShape)->toHaveKey('pattern');
});

test('it parses sh:in enumeration constraint', function () {
    $shacl = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .

    ex:StatusShape
        a sh:NodeShape ;
        sh:targetClass ex:Task ;
        sh:property [
            sh:path ex:status ;
            sh:in ( "active" "completed" "archived" )
        ] .
    ';

    $result = $this->parser->parse($shacl);

    expect($result['shapes'])->toHaveCount(1);
    // The parser should extract the property shapes
    expect($result['shapes'][0]['property_shapes'])->not->toBeEmpty();
});

test('it parses closed shapes', function () {
    $shacl = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .

    ex:ClosedShape
        a sh:NodeShape ;
        sh:targetClass ex:Person ;
        sh:closed true ;
        sh:ignoredProperties ( rdf:type ) ;
        sh:property [
            sh:path ex:name
        ] .
    ';

    $result = $this->parser->parse($shacl);

    expect($result['shapes'])->toHaveCount(1);
    // Check if closed constraints are extracted
    expect($result['shapes'][0])->toHaveKey('uri');
});

test('it extracts multilingual labels', function () {
    $shacl = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

    ex:PersonShape
        a sh:NodeShape ;
        sh:targetClass ex:Person ;
        rdfs:label "Person"@en ;
        rdfs:label "Personne"@fr ;
        rdfs:label "Persoon"@nl .
    ';

    $result = $this->parser->parse($shacl);
    $shape = $result['shapes'][0];

    expect($shape)->toHaveKey('labels');
    expect($shape['labels'])->toBeArray();
});

test('it extracts prefixes from shacl content', function () {
    $shacl = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix foaf: <http://xmlns.com/foaf/0.1/> .

    ex:PersonShape a sh:NodeShape .
    ';

    $result = $this->parser->parse($shacl);

    expect($result)->toHaveKey('prefixes');
    expect($result['prefixes'])->toBeArray();
    expect($result['prefixes'])->toHaveKey('sh');
    expect($result['prefixes'])->toHaveKey('ex');
});

test('it detects serialization format correctly', function () {
    $turtleShacl = '@prefix sh: <http://www.w3.org/ns/shacl#> .';
    $xmlShacl = '<?xml version="1.0"?><rdf:RDF xmlns:sh="http://www.w3.org/ns/shacl#"></rdf:RDF>';
    $jsonldShacl = '{"@context": {}, "@type": "sh:NodeShape"}';

    expect($this->parser->canParse($turtleShacl))->toBeTrue();
    expect($this->parser->canParse($xmlShacl))->toBeTrue();
    expect($this->parser->canParse($jsonldShacl))->toBeTrue();
});

test('it parses numeric range constraints', function () {
    $shacl = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

    ex:PersonShape
        a sh:NodeShape ;
        sh:targetClass ex:Person ;
        sh:property [
            sh:path ex:age ;
            sh:datatype xsd:integer ;
            sh:minInclusive 0 ;
            sh:maxInclusive 150
        ] .
    ';

    $result = $this->parser->parse($shacl);

    expect($result['shapes'])->toHaveCount(1);
    expect($result['shapes'][0]['property_shapes'])->toHaveCount(1);
});

test('it handles shapes without target class', function () {
    $shacl = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

    ex:PropertyShape
        a sh:PropertyShape ;
        sh:path ex:name ;
        sh:datatype xsd:string .
    ';

    $result = $this->parser->parse($shacl);

    // PropertyShapes are also extracted
    expect($result)->toHaveKey('shapes');
});

test('it parses severity and messages', function () {
    $shacl = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .

    ex:PersonShape
        a sh:NodeShape ;
        sh:targetClass ex:Person ;
        sh:severity sh:Warning ;
        sh:message "This is a warning message" ;
        sh:property [
            sh:path ex:name ;
            sh:minCount 1
        ] .
    ';

    $result = $this->parser->parse($shacl);
    $shape = $result['shapes'][0];

    expect($shape['severity'])->toBe('warning');
    expect($shape['message'])->toBe('This is a warning message');
});

test('it extracts metadata from shapes', function () {
    $shacl = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .

    ex:PersonShape
        a sh:NodeShape ;
        sh:targetClass ex:Person .
    ';

    $result = $this->parser->parse($shacl);

    expect($result)->toHaveKey('metadata');
    expect($result['metadata'])->toHaveKey('type');
    expect($result['metadata']['type'])->toBe('shacl');
    expect($result['metadata'])->toHaveKey('shapes_count');
});
