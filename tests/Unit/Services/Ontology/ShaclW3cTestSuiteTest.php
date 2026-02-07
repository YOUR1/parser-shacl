<?php

use App\Services\Ontology\Parsers\ShaclParser;

beforeEach(function () {
    $this->parser = new ShaclParser;
});

test('it can parse w3c shacl core examples', function () {
    $filename = 'targetClass-001.ttl';
    $fixturePath = __DIR__.'/../../../Fixtures/Shacl/W3c/'.$filename;

    if (! file_exists($fixturePath)) {
        $this->markTestSkipped('Could not load W3C SHACL test example');
    }

    $shaclContent = file_get_contents($fixturePath);

    $result = $this->parser->parse($shaclContent);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('shapes');
    expect($result)->toHaveKey('metadata');

    $metadata = $result['metadata'];
    expect($metadata['type'])->toBe('shacl');

    $shapes = $result['shapes'] ?? [];
    expect($shapes)->not->toBeEmpty();
});

test('it handles target class constraints', function () {
    $shaclContent = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

    ex:PersonShape
        a sh:NodeShape ;
        sh:targetClass ex:Person ;
        rdfs:label "Person Shape" ;
        rdfs:comment "A shape for validating persons" ;
        sh:property [
            sh:path ex:name ;
            sh:datatype <http://www.w3.org/2001/XMLSchema#string> ;
            sh:minCount 1 ;
            sh:maxCount 1 ;
        ] .
    ';

    $result = $this->parser->parse($shaclContent);
    $shapes = $result['shapes'] ?? [];

    expect($shapes)->toHaveCount(1);

    $personShape = $shapes[0];
    expect($personShape['uri'])->toBe('http://example.org/PersonShape');
    expect($personShape['label'])->toBe('Person Shape');
    expect($personShape['target_class'])->toBe('http://example.org/Person');
    expect($personShape['property_shapes'])->toHaveCount(1);

    $nameProperty = $personShape['property_shapes'][0];
    expect($nameProperty['path'])->toBe('http://example.org/name');
    expect($nameProperty['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
    expect($nameProperty['minCount'])->toBe('1');
    expect($nameProperty['maxCount'])->toBe('1');
});

test('it handles node kind constraints', function () {
    $shaclContent = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

    ex:AddressShape
        a sh:NodeShape ;
        sh:targetClass ex:Address ;
        rdfs:label "Address Shape" ;
        sh:property [
            sh:path ex:street ;
            sh:nodeKind sh:Literal ;
            sh:datatype <http://www.w3.org/2001/XMLSchema#string> ;
        ] ;
        sh:property [
            sh:path ex:country ;
            sh:nodeKind sh:IRI ;
        ] .
    ';

    $result = $this->parser->parse($shaclContent);
    $shapes = $result['shapes'] ?? [];

    expect($shapes)->toHaveCount(1);

    $addressShape = $shapes[0];
    expect($addressShape['property_shapes'])->toHaveCount(2);

    foreach ($addressShape['property_shapes'] as $propertyShape) {
        expect($propertyShape['nodeKind'])->not->toBeEmpty();
        expect($propertyShape['nodeKind'])->toBeIn([
            'http://www.w3.org/ns/shacl#Literal',
            'http://www.w3.org/ns/shacl#IRI',
        ]);
    }
});

test('it handles cardinality constraints', function () {
    $shaclContent = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

    ex:BookShape
        a sh:NodeShape ;
        sh:targetClass ex:Book ;
        rdfs:label "Book Shape" ;
        sh:property [
            sh:path ex:title ;
            sh:minCount 1 ;
            sh:maxCount 1 ;
        ] ;
        sh:property [
            sh:path ex:author ;
            sh:minCount 1 ;
        ] ;
        sh:property [
            sh:path ex:isbn ;
            sh:maxCount 1 ;
        ] .
    ';

    $result = $this->parser->parse($shaclContent);
    $shapes = $result['shapes'] ?? [];

    $bookShape = $shapes[0];
    expect($bookShape['property_shapes'])->toHaveCount(3);

    $titleProperty = null;
    $authorProperty = null;
    $isbnProperty = null;

    foreach ($bookShape['property_shapes'] as $prop) {
        if ($prop['path'] === 'http://example.org/title') {
            $titleProperty = $prop;
        } elseif ($prop['path'] === 'http://example.org/author') {
            $authorProperty = $prop;
        } elseif ($prop['path'] === 'http://example.org/isbn') {
            $isbnProperty = $prop;
        }
    }

    expect($titleProperty)->not->toBeNull();
    expect($titleProperty['minCount'])->toBe('1');
    expect($titleProperty['maxCount'])->toBe('1');

    expect($authorProperty)->not->toBeNull();
    expect($authorProperty['minCount'])->toBe('1');
    expect($authorProperty['maxCount'] ?? null)->toBeNull();

    expect($isbnProperty)->not->toBeNull();
    expect($isbnProperty['minCount'] ?? null)->toBeNull();
    expect($isbnProperty['maxCount'])->toBe('1');
});

test('it handles severity levels', function () {
    $shaclContent = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

    ex:WarningShape
        a sh:NodeShape ;
        sh:targetClass ex:Person ;
        sh:severity sh:Warning ;
        rdfs:label "Warning Shape" ;
        sh:property [
            sh:path ex:email ;
            sh:severity sh:Info ;
        ] .
    ';

    $result = $this->parser->parse($shaclContent);
    $shapes = $result['shapes'] ?? [];

    $warningShape = $shapes[0];
    expect($warningShape['severity'])->toBe('warning');
});
