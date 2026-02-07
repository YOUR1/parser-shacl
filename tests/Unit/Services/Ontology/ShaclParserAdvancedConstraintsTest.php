<?php

use App\Services\Ontology\Parsers\ShaclParser;

beforeEach(function () {
    $this->parser = new ShaclParser;
});

test('it extracts has value constraint', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:status ;
        sh:hasValue "active" ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $propertyShapes = $shapes[0]['property_shapes'];
    expect($propertyShapes)->toHaveCount(1);
    expect($propertyShapes[0]['hasValue'])->toBe('active');
});

test('it extracts in constraint with list', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:StatusShape a sh:NodeShape ;
    sh:targetClass ex:Record ;
    sh:property [
        sh:path ex:status ;
        sh:in ( "active" "pending" "closed" ) ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $propertyShapes = $shapes[0]['property_shapes'];
    expect($propertyShapes)->toHaveCount(1);
    expect($propertyShapes[0]['in'])->toBeArray();
    expect($propertyShapes[0]['in'])->toContain('active');
    expect($propertyShapes[0]['in'])->toContain('pending');
    expect($propertyShapes[0]['in'])->toContain('closed');
});

test('it extracts language in constraint', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:DocumentShape a sh:NodeShape ;
    sh:targetClass ex:Document ;
    sh:property [
        sh:path ex:title ;
        sh:languageIn ( "en" "fr" "de" ) ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $propertyShapes = $shapes[0]['property_shapes'];
    expect($propertyShapes)->toHaveCount(1);
    expect($propertyShapes[0]['languageIn'])->toBeArray();
    expect($propertyShapes[0]['languageIn'])->toContain('en');
    expect($propertyShapes[0]['languageIn'])->toContain('fr');
    expect($propertyShapes[0]['languageIn'])->toContain('de');
});

test('it extracts unique lang constraint', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:DocumentShape a sh:NodeShape ;
    sh:targetClass ex:Document ;
    sh:property [
        sh:path ex:label ;
        sh:uniqueLang true ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $propertyShapes = $shapes[0]['property_shapes'];
    expect($propertyShapes)->toHaveCount(1);
    expect($propertyShapes[0]['uniqueLang'])->toBe('true');
});

test('it extracts pattern flags constraint', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:email ;
        sh:pattern "^[a-z]+@example\\.com$" ;
        sh:flags "i" ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $propertyShapes = $shapes[0]['property_shapes'];
    expect($propertyShapes)->toHaveCount(1);
    expect($propertyShapes[0]['pattern'])->toContain('@example');
    expect($propertyShapes[0]['flags'])->toBe('i');
});

test('it extracts property pair constraints', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:EventShape a sh:NodeShape ;
    sh:targetClass ex:Event ;
    sh:property [
        sh:path ex:startDate ;
        sh:lessThan ex:endDate ;
    ] ;
    sh:property [
        sh:path ex:primaryEmail ;
        sh:disjoint ex:secondaryEmail ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $propertyShapes = $shapes[0]['property_shapes'];
    expect($propertyShapes)->toHaveCount(2);

    $startDateProp = array_values(array_filter($propertyShapes, fn ($p) => str_contains($p['path'], 'startDate')))[0];
    expect($startDateProp['lessThan'])->toContain('endDate');

    $emailProp = array_values(array_filter($propertyShapes, fn ($p) => str_contains($p['path'], 'primaryEmail')))[0];
    expect($emailProp['disjoint'])->toContain('secondaryEmail');
});

test('it extracts qualified value shape constraints', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:knows ;
        sh:qualifiedValueShape ex:FriendShape ;
        sh:qualifiedMinCount 2 ;
        sh:qualifiedMaxCount 10 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $propertyShapes = $shapes[0]['property_shapes'];
    expect($propertyShapes)->toHaveCount(1);
    expect($propertyShapes[0]['qualifiedValueShape'])->toContain('FriendShape');
    expect($propertyShapes[0]['qualifiedMinCount'])->toBe('2');
    expect($propertyShapes[0]['qualifiedMaxCount'])->toBe('10');
});

test('it extracts logical and constraint', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:and (
        ex:AdultShape
        ex:EmployedShape
    ) .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    expect($shapes[0]['sh_and'])->toBeArray();
    expect($shapes[0]['sh_and'])->toHaveCount(2);
    expect($shapes[0]['sh_and'][0]['type'])->toBe('reference');
    expect($shapes[0]['sh_and'][0]['uri'])->toContain('AdultShape');
});

test('it extracts logical or constraint', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:ContactShape a sh:NodeShape ;
    sh:targetClass ex:Contact ;
    sh:or (
        ex:HasEmailShape
        ex:HasPhoneShape
    ) .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    expect($shapes[0]['sh_or'])->toBeArray();
    expect($shapes[0]['sh_or'])->toHaveCount(2);
});

test('it extracts logical xone constraint', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:AddressShape a sh:NodeShape ;
    sh:targetClass ex:Address ;
    sh:xone (
        ex:StreetAddressShape
        ex:POBoxShape
    ) .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    expect($shapes[0]['sh_xone'])->toBeArray();
    expect($shapes[0]['sh_xone'])->toHaveCount(2);
});

test('it extracts closed shape constraint', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix ex: <http://example.org/> .

ex:StrictShape a sh:NodeShape ;
    sh:targetClass ex:StrictEntity ;
    sh:closed true ;
    sh:ignoredProperties ( rdf:type ) ;
    sh:property [
        sh:path ex:name ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    expect($shapes[0]['closed'])->toBe('true');
    expect($shapes[0]['ignored_properties'])->toBeArray();
    expect($shapes[0]['ignored_properties'])->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#type');
});
