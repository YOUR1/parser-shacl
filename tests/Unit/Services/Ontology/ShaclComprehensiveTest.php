<?php

use App\Services\Ontology\Parsers\ShaclParser;

beforeEach(function () {
    $this->parser = new ShaclParser;
});

test('it provides comprehensive shacl coverage', function () {
    $testCases = [
        'basic_node_shape' => createBasicNodeShape(),
        'complex_property_constraints' => createComplexPropertyConstraints(),
        'multilingual_shapes' => createMultilingualShapes(),
        'various_targeting_mechanisms' => createVariousTargetingMechanisms(),
        'severity_and_messages' => createSeverityAndMessages(),
    ];

    foreach ($testCases as $testName => $shaclContent) {
        $result = $this->parser->parse($shaclContent);

        expect($result)->toBeArray("Test case '{$testName}' should return array");
        expect($result)->toHaveKey('shapes');
        expect($result)->toHaveKey('metadata');

        $metadata = $result['metadata'];
        expect($metadata['type'])->toBe('shacl');

        $shapes = $result['shapes'] ?? [];
        expect($shapes)->not->toBeEmpty("Test case '{$testName}' should extract shapes");
    }
});

test('it handles all constraint types', function () {
    $shaclContent = createAllConstraintTypes();
    $result = $this->parser->parse($shaclContent);

    $shapes = $result['shapes'] ?? [];
    expect($shapes)->toHaveCount(1);

    $shape = $shapes[0];
    expect($shape['property_shapes'])->not->toBeEmpty();

    $constraintTypes = [
        'minCount', 'maxCount', 'minLength', 'maxLength',
        'pattern', 'datatype', 'nodeKind', 'class',
    ];

    $foundConstraints = [];
    foreach ($shape['property_shapes'] as $propertyShape) {
        foreach ($constraintTypes as $constraint) {
            if (! empty($propertyShape[$constraint])) {
                $foundConstraints[$constraint] = true;
            }
        }
    }

    expect(count($foundConstraints))->toBeGreaterThan(5);
});

test('it handles edge cases', function () {
    $edgeCases = [
        'empty_shape' => createEmptyShape(),
        'nested_property_shapes' => createNestedPropertyShapes(),
        'special_characters' => createSpecialCharacters(),
    ];

    foreach ($edgeCases as $testName => $shaclContent) {
        $result = $this->parser->parse($shaclContent);

        expect($result)->toBeArray("Edge case '{$testName}' should parse without error");
        expect($result['metadata']['type'])->toBe('shacl');
    }
});

// Helper functions

function createBasicNodeShape(): string
{
    return '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

    ex:PersonShape
        a sh:NodeShape ;
        sh:targetClass ex:Person ;
        rdfs:label "Person Shape" ;
        rdfs:comment "A basic shape for validating persons" ;
        sh:property [
            sh:path ex:name ;
            sh:datatype <http://www.w3.org/2001/XMLSchema#string> ;
            sh:minCount 1 ;
        ] .
    ';
}

function createComplexPropertyConstraints(): string
{
    return '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
    @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

    ex:ComplexShape
        a sh:NodeShape ;
        sh:targetClass ex:ComplexEntity ;
        rdfs:label "Complex Shape" ;
        sh:property [
            sh:path ex:stringProp ;
            sh:datatype xsd:string ;
            sh:minLength 5 ;
            sh:maxLength 100 ;
            sh:pattern "^[A-Z].*" ;
        ] ;
        sh:property [
            sh:path ex:intProp ;
            sh:datatype xsd:integer ;
            sh:minInclusive 0 ;
            sh:maxInclusive 999 ;
        ] ;
        sh:property [
            sh:path ex:classProp ;
            sh:class ex:RelatedClass ;
            sh:nodeKind sh:IRI ;
        ] .
    ';
}

function createMultilingualShapes(): string
{
    return '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

    ex:MultilingualShape
        a sh:NodeShape ;
        sh:targetClass ex:MultilingualEntity ;
        rdfs:label "Multilingual Shape"@en ;
        rdfs:label "Meertalige Vorm"@nl ;
        rdfs:label "Forme Multilingue"@fr ;
        rdfs:comment "A shape with multiple language labels"@en ;
        rdfs:comment "Een vorm met meertalige labels"@nl ;
        sh:property [
            sh:path ex:title ;
            sh:name "Title"@en ;
            sh:name "Titel"@nl ;
            sh:description "The title of the entity"@en ;
            sh:description "De titel van de entiteit"@nl ;
        ] .
    ';
}

function createVariousTargetingMechanisms(): string
{
    return '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

    ex:TargetClassShape
        a sh:NodeShape ;
        sh:targetClass ex:TargetEntity ;
        rdfs:label "Target Class Shape" .

    ex:TargetNodeShape
        a sh:NodeShape ;
        sh:targetNode ex:SpecificNode ;
        rdfs:label "Target Node Shape" .

    ex:TargetSubjectsOfShape
        a sh:NodeShape ;
        sh:targetSubjectsOf ex:hasProperty ;
        rdfs:label "Target Subjects Of Shape" .

    ex:TargetObjectsOfShape
        a sh:NodeShape ;
        sh:targetObjectsOf ex:isPropertyOf ;
        rdfs:label "Target Objects Of Shape" .
    ';
}

function createSeverityAndMessages(): string
{
    return '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

    ex:SeverityShape
        a sh:NodeShape ;
        sh:targetClass ex:ValidatedEntity ;
        sh:severity sh:Warning ;
        sh:message "This is a warning-level validation" ;
        rdfs:label "Severity Shape" ;
        sh:property [
            sh:path ex:criticalProp ;
            sh:severity sh:Violation ;
            sh:message "Critical property is required" ;
            sh:minCount 1 ;
        ] ;
        sh:property [
            sh:path ex:infoProp ;
            sh:severity sh:Info ;
            sh:message "Informational constraint" ;
        ] .
    ';
}

function createAllConstraintTypes(): string
{
    return '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
    @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

    ex:AllConstraintsShape
        a sh:NodeShape ;
        sh:targetClass ex:AllConstraintsEntity ;
        rdfs:label "All Constraints Shape" ;
        sh:property [
            sh:path ex:cardinalityProp ;
            sh:minCount 1 ;
            sh:maxCount 3 ;
        ] ;
        sh:property [
            sh:path ex:lengthProp ;
            sh:datatype xsd:string ;
            sh:minLength 2 ;
            sh:maxLength 50 ;
        ] ;
        sh:property [
            sh:path ex:patternProp ;
            sh:pattern "^[a-zA-Z0-9]+$" ;
        ] ;
        sh:property [
            sh:path ex:datatypeProp ;
            sh:datatype xsd:dateTime ;
        ] ;
        sh:property [
            sh:path ex:nodeKindProp ;
            sh:nodeKind sh:Literal ;
        ] ;
        sh:property [
            sh:path ex:classProp ;
            sh:class ex:TargetClass ;
        ] ;
        sh:property [
            sh:path ex:rangeProp ;
            sh:minInclusive 0 ;
            sh:maxExclusive 100 ;
        ] .
    ';
}

function createEmptyShape(): string
{
    return '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .

    ex:EmptyShape
        a sh:NodeShape ;
        sh:targetClass ex:EmptyEntity .
    ';
}

function createNestedPropertyShapes(): string
{
    return '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

    ex:NestedShape
        a sh:NodeShape ;
        sh:targetClass ex:NestedEntity ;
        rdfs:label "Nested Shape" ;
        sh:property [
            sh:path ex:nestedProp ;
            sh:node ex:InnerShape ;
        ] .

    ex:InnerShape
        a sh:NodeShape ;
        rdfs:label "Inner Shape" ;
        sh:property [
            sh:path ex:innerProp ;
            sh:minCount 1 ;
        ] .
    ';
}

function createSpecialCharacters(): string
{
    return '
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

    ex:SpecialShape
        a sh:NodeShape ;
        sh:targetClass ex:SpecialEntity ;
        rdfs:label "Special Characters: àáâäčć & symbols!" ;
        rdfs:comment "Shape with special characters: €£¥©®™" ;
        sh:property [
            sh:path ex:specialProp ;
            sh:pattern "[àáâäčć]+" ;
            sh:message "Must contain special characters: àáâäčć" ;
        ] .
    ';
}
