<?php

use App\Services\Ontology\Parsers\ShaclParser;

beforeEach(function () {
    $this->parser = new ShaclParser;
});

test('it extracts sparql select constraint from node shape', function () {
    $turtle = <<<'TURTLE'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape
    a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:sparql [
        a sh:SPARQLSelectValidator ;
        sh:select """
            SELECT $this ?value
            WHERE {
                $this ex:age ?value .
                FILTER (?value < 0)
            }
        """ ;
        sh:message "Age must be non-negative" ;
    ] .
TURTLE;

    $result = $this->parser->parse($turtle);

    expect($result['shapes'])->not->toBeEmpty();
    $shape = $result['shapes'][0];

    expect($shape['sparql_constraints'])->not->toBeNull();
    expect($shape['sparql_constraints'])->toHaveCount(1);

    $constraint = $shape['sparql_constraints'][0];
    expect($constraint['type'])->toBe('select');
    expect($constraint['query'])->toContain('SELECT $this ?value');
    expect($constraint['query'])->toContain('FILTER (?value < 0)');
    expect($constraint['message'])->toBe('Age must be non-negative');
});

test('it extracts sparql ask constraint from node shape', function () {
    $turtle = <<<'TURTLE'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape
    a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:sparql [
        a sh:SPARQLAskValidator ;
        sh:ask """
            ASK {
                $this ex:firstName ?firstName .
                $this ex:lastName ?lastName .
            }
        """ ;
        sh:message "Person must have both first and last name" ;
    ] .
TURTLE;

    $result = $this->parser->parse($turtle);

    expect($result['shapes'])->not->toBeEmpty();
    $shape = $result['shapes'][0];

    expect($shape['sparql_constraints'])->not->toBeNull();
    expect($shape['sparql_constraints'])->toHaveCount(1);

    $constraint = $shape['sparql_constraints'][0];
    expect($constraint['type'])->toBe('ask');
    expect($constraint['query'])->toContain('ASK {');
    expect($constraint['message'])->toBe('Person must have both first and last name');
});

test('it extracts multiple sparql constraints', function () {
    $turtle = <<<'TURTLE'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape
    a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:sparql [
        a sh:SPARQLSelectValidator ;
        sh:select """SELECT $this WHERE { $this ex:age ?age . FILTER (?age < 0) }""" ;
    ] ;
    sh:sparql [
        a sh:SPARQLSelectValidator ;
        sh:select """SELECT $this WHERE { $this ex:age ?age . FILTER (?age > 150) }""" ;
    ] .
TURTLE;

    $result = $this->parser->parse($turtle);

    expect($result['shapes'])->not->toBeEmpty();
    $shape = $result['shapes'][0];

    expect($shape['sparql_constraints'])->not->toBeNull();
    expect($shape['sparql_constraints'])->toHaveCount(2);

    expect($shape['sparql_constraints'][0]['type'])->toBe('select');
    expect($shape['sparql_constraints'][1]['type'])->toBe('select');
});

test('it extracts sparql constraint with prefixes', function () {
    $turtle = <<<'TURTLE'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape
    a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:sparql [
        a sh:SPARQLSelectValidator ;
        sh:select """SELECT $this WHERE { $this ex:knows ?friend }""" ;
        sh:prefix [
            sh:prefix "ex" ;
            sh:namespace "http://example.org/" ;
        ] ;
    ] .
TURTLE;

    $result = $this->parser->parse($turtle);

    expect($result['shapes'])->not->toBeEmpty();
    $shape = $result['shapes'][0];

    expect($shape['sparql_constraints'])->not->toBeNull();
    $constraint = $shape['sparql_constraints'][0];

    expect($constraint['query'])->toContain('SELECT $this');
});

test('it extracts sparql constraints from property shapes', function () {
    $turtle = <<<'TURTLE'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape
    a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:email ;
        sh:sparql [
            a sh:SPARQLAskValidator ;
            sh:ask """ASK { $this ex:email ?email . FILTER (CONTAINS(?email, "@")) }""" ;
            sh:message "Email must contain @ symbol" ;
        ] ;
    ] .
TURTLE;

    $result = $this->parser->parse($turtle);

    expect($result['shapes'])->not->toBeEmpty();
    $shape = $result['shapes'][0];

    expect($shape['property_shapes'])->not->toBeEmpty();
    $propShape = $shape['property_shapes'][0];

    expect($propShape['sparql_constraints'])->not->toBeNull();
    expect($propShape['sparql_constraints'])->toHaveCount(1);

    $constraint = $propShape['sparql_constraints'][0];
    expect($constraint['type'])->toBe('ask');
    expect($constraint['query'])->toContain('ASK {');
    expect($constraint['message'])->toBe('Email must contain @ symbol');
});

test('it handles direct sh select on property shape', function () {
    $turtle = <<<'TURTLE'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape
    a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:age ;
        sh:select """SELECT $this WHERE { $this ex:age ?value . FILTER (?value < 0) }""" ;
        sh:message "Age must be positive" ;
    ] .
TURTLE;

    $result = $this->parser->parse($turtle);

    expect($result['shapes'])->not->toBeEmpty();
    $shape = $result['shapes'][0];

    expect($shape['property_shapes'])->not->toBeEmpty();
    $propShape = $shape['property_shapes'][0];

    expect($propShape['sparql_constraints'])->not->toBeNull();
    expect($propShape['sparql_constraints'])->toHaveCount(1);

    $constraint = $propShape['sparql_constraints'][0];
    expect($constraint['type'])->toBe('select');
    expect($constraint['query'])->toContain('SELECT $this');
});

test('it handles shape without sparql constraints', function () {
    $turtle = <<<'TURTLE'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape
    a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
    ] .
TURTLE;

    $result = $this->parser->parse($turtle);

    expect($result['shapes'])->not->toBeEmpty();
    $shape = $result['shapes'][0];

    expect($shape['sparql_constraints'])->toBeNull();
});

test('it extracts complex sparql query with aggregates', function () {
    $turtle = <<<'TURTLE'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:TeamShape
    a sh:NodeShape ;
    sh:targetClass ex:Team ;
    sh:sparql [
        a sh:SPARQLSelectValidator ;
        sh:select """
            SELECT $this (COUNT(?member) as ?memberCount)
            WHERE {
                $this ex:hasMember ?member .
            }
            GROUP BY $this
            HAVING (COUNT(?member) < 3)
        """ ;
        sh:message "Team must have at least 3 members" ;
    ] .
TURTLE;

    $result = $this->parser->parse($turtle);

    expect($result['shapes'])->not->toBeEmpty();
    $shape = $result['shapes'][0];

    expect($shape['sparql_constraints'])->not->toBeNull();
    $constraint = $shape['sparql_constraints'][0];

    expect($constraint['type'])->toBe('select');
    expect($constraint['query'])->toContain('COUNT(?member)');
    expect($constraint['query'])->toContain('GROUP BY $this');
    expect($constraint['query'])->toContain('HAVING');
});
