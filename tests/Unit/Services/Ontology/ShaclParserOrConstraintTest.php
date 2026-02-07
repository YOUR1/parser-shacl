<?php

use App\Services\Ontology\Parsers\ShaclParser;

beforeEach(function () {
    $this->parser = new ShaclParser;
});

test('it detects object property with sh or class constraints', function () {
    $shaclContent = <<<'TURTLE'
@prefix skosapnl: <http://nlbegrip.nl/def/skosapnl#> .
@prefix skos: <http://www.w3.org/2004/02/skos/core#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

skosapnl:Collection a sh:NodeShape ;
  sh:targetClass skos:Collection ;
  sh:property skosapnl:Collection-member .

skosapnl:Collection-member
  a sh:PropertyShape ;
  rdfs:label "bevat"@nl ;
  sh:or ( [ sh:class skos:Concept ] [ sh:class skos:Collection ] ) ;
  sh:description
    "Relates a collection to one of its members."@en ,
    "Relateert een collectie aan een begrip dat onderdeel is van deze collectie."@nl ;
  sh:name "bevat"@nl, "member"@en ;
  sh:nodeKind sh:IRI ;
  sh:severity sh:Warning ;
  sh:path skos:member .
TURTLE;

    $result = $this->parser->parse($shaclContent);
    $properties = $result['properties'] ?? [];

    $skosMember = collect($properties)->firstWhere('uri', 'http://www.w3.org/2004/02/skos/core#member');

    expect($skosMember)->not->toBeNull();
    expect($skosMember['property_type'])->toBe('object');

    expect($skosMember['range'])->not->toBeEmpty();
    expect($skosMember['range'])->toContain('http://www.w3.org/2004/02/skos/core#Concept');
    expect($skosMember['range'])->toContain('http://www.w3.org/2004/02/skos/core#Collection');
});

test('it detects object property with node kind iri', function () {
    $shaclContent = <<<'TURTLE'
@prefix ex: <http://example.org/> .
@prefix sh: <http://www.w3.org/ns/shacl#> .

ex:PersonShape a sh:NodeShape ;
  sh:targetClass ex:Person ;
  sh:property [
    sh:path ex:knows ;
    sh:nodeKind sh:IRI ;
  ] .
TURTLE;

    $result = $this->parser->parse($shaclContent);
    $properties = $result['properties'] ?? [];

    $knowsProperty = collect($properties)->firstWhere('uri', 'http://example.org/knows');

    expect($knowsProperty)->not->toBeNull();
    expect($knowsProperty['property_type'])->toBe('object');
});

test('it detects datatype property with node kind literal', function () {
    $shaclContent = <<<'TURTLE'
@prefix ex: <http://example.org/> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ex:PersonShape a sh:NodeShape ;
  sh:targetClass ex:Person ;
  sh:property [
    sh:path ex:name ;
    sh:nodeKind sh:Literal ;
    sh:datatype xsd:string ;
  ] .
TURTLE;

    $result = $this->parser->parse($shaclContent);
    $properties = $result['properties'] ?? [];

    $nameProperty = collect($properties)->firstWhere('uri', 'http://example.org/name');

    expect($nameProperty)->not->toBeNull();
    expect($nameProperty['property_type'])->toBe('datatype');
});

test('it detects object property with sh and class constraints', function () {
    $shaclContent = <<<'TURTLE'
@prefix ex: <http://example.org/> .
@prefix sh: <http://www.w3.org/ns/shacl#> .

ex:PersonShape a sh:NodeShape ;
  sh:targetClass ex:Person ;
  sh:property ex:Person-location .

ex:Person-location a sh:PropertyShape ;
  sh:path ex:location ;
  sh:name "location" ;
  sh:and ( [ sh:class ex:Place ] [ sh:class ex:Geocoded ] ) .
TURTLE;

    $result = $this->parser->parse($shaclContent);
    $properties = $result['properties'] ?? [];

    $locationProperty = collect($properties)->firstWhere('uri', 'http://example.org/location');

    expect($locationProperty)->not->toBeNull();
    expect($locationProperty['property_type'])->toBe('object');
});

test('it detects object property with sh xone class constraints', function () {
    $shaclContent = <<<'TURTLE'
@prefix ex: <http://example.org/> .
@prefix sh: <http://www.w3.org/ns/shacl#> .

ex:PersonShape a sh:NodeShape ;
  sh:targetClass ex:Person ;
  sh:property ex:Person-contact .

ex:Person-contact a sh:PropertyShape ;
  sh:path ex:contact ;
  sh:name "contact" ;
  sh:xone ( [ sh:class ex:EmailAddress ] [ sh:class ex:PhoneNumber ] ) .
TURTLE;

    $result = $this->parser->parse($shaclContent);
    $properties = $result['properties'] ?? [];

    $contactProperty = collect($properties)->firstWhere('uri', 'http://example.org/contact');

    expect($contactProperty)->not->toBeNull();
    expect($contactProperty['property_type'])->toBe('object');
});
