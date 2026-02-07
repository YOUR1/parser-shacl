<?php

use App\Services\Ontology\Parsers\ShaclParser;

beforeEach(function () {
    $this->parser = new ShaclParser;
});

test('it can parse topbraid person example', function () {
    $fixturePath = __DIR__.'/../../../Fixtures/Shacl/TopBraid/person.ttl';

    if (! file_exists($fixturePath)) {
        $this->markTestSkipped('Could not load TopBraid person example');
    }

    $shaclContent = file_get_contents($fixturePath);

    $result = $this->parser->parse($shaclContent);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('shapes');
    expect($result)->toHaveKey('metadata');

    $shapes = $result['shapes'] ?? [];
    expect($shapes)->not->toBeEmpty();

    $personShapes = array_filter($shapes, function ($shape) {
        return str_contains($shape['uri'], 'Person');
    });

    expect($personShapes)->not->toBeEmpty();
});

test('it handles complex property shapes', function () {
    $shaclContent = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
    @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

    ex:EmployeeShape
        a sh:NodeShape ;
        sh:targetClass ex:Employee ;
        rdfs:label "Employee Shape" ;
        rdfs:comment "Shape for validating employee data" ;
        sh:property [
            sh:path ex:firstName ;
            sh:name "First Name" ;
            sh:description "The employee\'s first name" ;
            sh:datatype xsd:string ;
            sh:minCount 1 ;
            sh:maxCount 1 ;
            sh:minLength 1 ;
            sh:maxLength 50 ;
        ] ;
        sh:property [
            sh:path ex:age ;
            sh:name "Age" ;
            sh:datatype xsd:integer ;
            sh:minInclusive 18 ;
            sh:maxInclusive 65 ;
            sh:maxCount 1 ;
        ] ;
        sh:property [
            sh:path ex:email ;
            sh:name "Email Address" ;
            sh:datatype xsd:string ;
            sh:pattern "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$" ;
            sh:maxCount 1 ;
        ] ;
        sh:property [
            sh:path ex:manager ;
            sh:name "Manager" ;
            sh:class ex:Employee ;
            sh:maxCount 1 ;
        ] .
    ';

    $result = $this->parser->parse($shaclContent);
    $shapes = $result['shapes'] ?? [];

    expect($shapes)->toHaveCount(1);

    $employeeShape = $shapes[0];
    expect($employeeShape['label'])->toBe('Employee Shape');
    expect($employeeShape['property_shapes'])->toHaveCount(4);

    $propertyMap = [];
    foreach ($employeeShape['property_shapes'] as $prop) {
        $path = $prop['path'];
        $propertyMap[substr($path, strrpos($path, '/') + 1)] = $prop;
    }

    $firstNameProp = $propertyMap['firstName'];
    expect($firstNameProp['name'])->toBe('First Name');
    expect($firstNameProp['minLength'])->toBe('1');
    expect($firstNameProp['maxLength'])->toBe('50');

    $ageProp = $propertyMap['age'];
    expect($ageProp['name'])->toBe('Age');
    expect($ageProp['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#integer');

    $emailProp = $propertyMap['email'];
    expect($emailProp['name'])->toBe('Email Address');
    expect($emailProp['pattern'])->not->toBeEmpty();

    $managerProp = $propertyMap['manager'];
    expect($managerProp['name'])->toBe('Manager');
    expect($managerProp['class'])->toBe('http://example.org/Employee');
});

test('it handles target objects of constraints', function () {
    $shaclContent = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

    ex:ManagerShape
        a sh:NodeShape ;
        sh:targetObjectsOf ex:manager ;
        rdfs:label "Manager Shape" ;
        rdfs:comment "Shape for validating manager instances" ;
        sh:property [
            sh:path ex:department ;
            sh:minCount 1 ;
            sh:class ex:Department ;
        ] .
    ';

    $result = $this->parser->parse($shaclContent);
    $shapes = $result['shapes'] ?? [];

    $managerShape = $shapes[0];
    expect($managerShape['label'])->toBe('Manager Shape');
    expect($managerShape['target_objects_of'])->toBe('http://example.org/manager');
    expect($managerShape['target_class'])->toBeNull();
    expect($managerShape['property_shapes'])->toHaveCount(1);
});

test('it handles multiple targeting mechanisms', function () {
    $shaclContent = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

    ex:MultiTargetShape
        a sh:NodeShape ;
        sh:targetClass ex:Person ;
        sh:targetNode ex:SpecialPerson ;
        rdfs:label "Multi-Target Shape" ;
        sh:property [
            sh:path ex:name ;
            sh:minCount 1 ;
        ] .
    ';

    $result = $this->parser->parse($shaclContent);
    $shapes = $result['shapes'] ?? [];

    $multiShape = $shapes[0];
    expect($multiShape['label'])->toBe('Multi-Target Shape');
    expect($multiShape['target_class'])->toBe('http://example.org/Person');
    expect($multiShape['target_node'])->toBe('http://example.org/SpecialPerson');
});

test('it extracts constraint messages', function () {
    $shaclContent = '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

    ex:ValidatedShape
        a sh:NodeShape ;
        sh:targetClass ex:Product ;
        rdfs:label "Product Shape" ;
        sh:message "Product validation failed" ;
        sh:property [
            sh:path ex:price ;
            sh:datatype <http://www.w3.org/2001/XMLSchema#decimal> ;
            sh:minInclusive 0.01 ;
            sh:message "Price must be at least 0.01" ;
        ] .
    ';

    $result = $this->parser->parse($shaclContent);
    $shapes = $result['shapes'] ?? [];

    $productShape = $shapes[0];
    expect($productShape['message'])->toBe('Product validation failed');

    $priceProperty = $productShape['property_shapes'][0];
    expect($priceProperty['message'])->toBe('Price must be at least 0.01');
});
