<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserShacl\Extractors\ShaclShapeProcessor;

/**
 * Helper to create a ParsedRdf from Turtle content.
 */
function createParsedRdfFromTurtle(string $turtleContent): ParsedRdf
{
    \EasyRdf\RdfNamespace::set('sh', 'http://www.w3.org/ns/shacl#');
    $graph = new \EasyRdf\Graph();
    $graph->parse($turtleContent, 'turtle');

    return new ParsedRdf(
        graph: $graph,
        format: 'turtle',
        rawContent: $turtleContent,
    );
}

describe('ShaclShapeProcessor', function () {

    beforeEach(function () {
        $this->processor = new ShaclShapeProcessor();
    });

    it('is a final class', function () {
        $reflection = new ReflectionClass(ShaclShapeProcessor::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    it('extracts node shape with single sh:targetClass', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/PersonShape';
        expect($shapes)->toHaveKey($shapeUri);
        expect($shapes[$shapeUri]['uri'])->toBe($shapeUri);
        expect($shapes[$shapeUri]['target_class'])->toBe('http://example.org/Person');
        expect($shapes[$shapeUri]['target_classes'])->toBe(['http://example.org/Person']);
    });

    it('extracts node shape with multiple sh:targetClass values', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonCompanyShape a sh:NodeShape ;
    sh:targetClass ex:Person, ex:Company .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/PersonCompanyShape';
        expect($shapes)->toHaveKey($shapeUri);
        expect($shapes[$shapeUri]['target_classes'])->toContain('http://example.org/Person');
        expect($shapes[$shapeUri]['target_classes'])->toContain('http://example.org/Company');
        expect(count($shapes[$shapeUri]['target_classes']))->toBe(2);
        expect($shapes[$shapeUri]['target_class'])->toBeString();
    });

    it('extracts sh:targetNode single value', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:AliceShape a sh:NodeShape ;
    sh:targetNode ex:Alice .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/AliceShape';
        expect($shapes)->toHaveKey($shapeUri);
        expect($shapes[$shapeUri]['target_node'])->toBe('http://example.org/Alice');
        expect($shapes[$shapeUri]['target_nodes'])->toBe(['http://example.org/Alice']);
    });

    it('extracts sh:targetNode multiple values', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:NamedShape a sh:NodeShape ;
    sh:targetNode ex:Alice, ex:Bob .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/NamedShape';
        expect($shapes)->toHaveKey($shapeUri);
        expect($shapes[$shapeUri]['target_nodes'])->toContain('http://example.org/Alice');
        expect($shapes[$shapeUri]['target_nodes'])->toContain('http://example.org/Bob');
        expect(count($shapes[$shapeUri]['target_nodes']))->toBe(2);
    });

    it('extracts sh:targetSubjectsOf', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:HasNameShape a sh:NodeShape ;
    sh:targetSubjectsOf ex:name .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/HasNameShape';
        expect($shapes)->toHaveKey($shapeUri);
        expect($shapes[$shapeUri]['target_subjects_of'])->toBe('http://example.org/name');
    });

    it('extracts sh:targetObjectsOf', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:KnowsObjectShape a sh:NodeShape ;
    sh:targetObjectsOf ex:knows .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/KnowsObjectShape';
        expect($shapes)->toHaveKey($shapeUri);
        expect($shapes[$shapeUri]['target_objects_of'])->toBe('http://example.org/knows');
    });

    it('detects implicit target class when shape is also rdfs:Class', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .
ex:Person a sh:NodeShape, rdfs:Class .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/Person';
        expect($shapes)->toHaveKey($shapeUri);
        expect($shapes[$shapeUri]['target_classes'])->toContain('http://example.org/Person');
        expect($shapes[$shapeUri]['target_class'])->toBe('http://example.org/Person');
    });

    it('extracts node shape without any target', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:NameShape a sh:NodeShape ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
    ] .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/NameShape';
        expect($shapes)->toHaveKey($shapeUri);
        expect($shapes[$shapeUri]['target_class'])->toBeNull();
        expect($shapes[$shapeUri]['target_classes'])->toBe([]);
        expect($shapes[$shapeUri]['target_node'])->toBeNull();
        expect($shapes[$shapeUri]['target_nodes'])->toBe([]);
        expect($shapes[$shapeUri]['target_subjects_of'])->toBeNull();
        expect($shapes[$shapeUri]['target_objects_of'])->toBeNull();
    });

    it('uses full URIs never prefixed notation', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/PersonShape';
        expect($shapes[$shapeUri]['uri'])->toStartWith('http://');
        expect($shapes[$shapeUri]['target_class'])->toStartWith('http://');
        expect($shapes[$shapeUri]['target_classes'][0])->toStartWith('http://');

        foreach ($shapes[$shapeUri]['metadata']['types'] as $type) {
            expect($type)->toStartWith('http://');
        }
    });

    it('extracts multilingual labels', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    rdfs:label "Person Shape"@en, "Persoonsvorm"@nl ;
    sh:targetClass ex:Person .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/PersonShape';
        expect($shapes[$shapeUri]['labels'])->toBeArray();
        expect($shapes[$shapeUri]['labels'])->toHaveKey('en');
        expect($shapes[$shapeUri]['labels']['en'])->toBe('Person Shape');
        expect($shapes[$shapeUri]['labels'])->toHaveKey('nl');
        expect($shapes[$shapeUri]['labels']['nl'])->toBe('Persoonsvorm');
        expect($shapes[$shapeUri]['label'])->toBeString();
    });

    it('extracts multilingual descriptions', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    rdfs:comment "Validates Person instances"@en, "Valideert Persoon-instanties"@nl ;
    sh:targetClass ex:Person .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/PersonShape';
        expect($shapes[$shapeUri]['descriptions'])->toBeArray();
        expect($shapes[$shapeUri]['descriptions'])->toHaveKey('en');
        expect($shapes[$shapeUri]['descriptions']['en'])->toBe('Validates Person instances');
        expect($shapes[$shapeUri]['descriptions'])->toHaveKey('nl');
        expect($shapes[$shapeUri]['descriptions']['nl'])->toBe('Valideert Persoon-instanties');
        expect($shapes[$shapeUri]['description'])->toBeString();
    });

    it('includes metadata with source and types', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/PersonShape';
        expect($shapes[$shapeUri]['metadata'])->toBeArray();
        expect($shapes[$shapeUri]['metadata']['source'])->toBe('shacl_parser');
        expect($shapes[$shapeUri]['metadata']['types'])->toBeArray();
        expect($shapes[$shapeUri]['metadata']['types'])->toContain('http://www.w3.org/ns/shacl#NodeShape');
    });

    it('returns shapes keyed by URI', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .
ex:CompanyShape a sh:NodeShape ;
    sh:targetClass ex:Company .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        expect($shapes)->toHaveKey('http://example.org/PersonShape');
        expect($shapes)->toHaveKey('http://example.org/CompanyShape');
        expect(count($shapes))->toBe(2);
    });

    it('includes blank node shapes with explicit sh:NodeShape type', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
[
    a sh:NodeShape ;
    sh:targetClass ex:Person
] .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        expect(count($shapes))->toBeGreaterThanOrEqual(1);

        $blankNodeShapes = array_filter($shapes, fn (array $shape): bool => str_starts_with($shape['uri'], '_:'));
        expect(count($blankNodeShapes))->toBeGreaterThanOrEqual(1);
    });

    it('excludes blank nodes from SHP-03 and SHP-04 recognition', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
    ] .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        foreach ($shapes as $uri => $shape) {
            expect($uri)->not->toStartWith('_:');
        }
    });

    it('extracts shape with multiple simultaneous targeting mechanisms', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:MultiTargetShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:targetNode ex:Alice ;
    sh:targetSubjectsOf ex:name ;
    sh:targetObjectsOf ex:knows .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/MultiTargetShape';
        expect($shapes)->toHaveKey($shapeUri);
        expect($shapes[$shapeUri]['target_class'])->toBe('http://example.org/Person');
        expect($shapes[$shapeUri]['target_node'])->toBe('http://example.org/Alice');
        expect($shapes[$shapeUri]['target_subjects_of'])->toBe('http://example.org/name');
        expect($shapes[$shapeUri]['target_objects_of'])->toBe('http://example.org/knows');
    });

    it('recognizes shape by explicit sh:PropertyShape type (SHP-02)', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:NameShape a sh:PropertyShape ;
    sh:path ex:name ;
    sh:datatype xsd:string .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        expect($shapes)->toHaveKey('http://example.org/NameShape');
    });

    it('recognizes shape by target predicate without explicit type (SHP-03)', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape sh:targetClass ex:Person .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        expect($shapes)->not->toBeEmpty();
        expect($shapes)->toHaveKey('http://example.org/PersonShape');
        expect($shapes['http://example.org/PersonShape']['target_class'])->toBe('http://example.org/Person');
    });

    it('recognizes shape by constraint parameter without explicit type (SHP-04)', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape sh:property [
    sh:path ex:name ;
    sh:datatype xsd:string ;
] .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        expect($shapes)->not->toBeEmpty();
        expect($shapes)->toHaveKey('http://example.org/PersonShape');
    });

    it('has all expected keys with defaults for a minimal shape', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:MinimalShape a sh:NodeShape .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/MinimalShape';
        expect($shapes)->toHaveKey($shapeUri);
        $shape = $shapes[$shapeUri];

        expect($shape['uri'])->toBe($shapeUri);
        expect($shape['label'])->toBeNull();
        expect($shape['labels'])->toBe([]);
        expect($shape['description'])->toBeNull();
        expect($shape['descriptions'])->toBe([]);
        expect($shape['target_class'])->toBeNull();
        expect($shape['target_classes'])->toBe([]);
        expect($shape['target_node'])->toBeNull();
        expect($shape['target_nodes'])->toBe([]);
        expect($shape['target_subjects_of'])->toBeNull();
        expect($shape['target_objects_of'])->toBeNull();
        expect($shape['property_shapes'])->toBe([]);
        expect($shape['constraints'])->toBe([]);
        expect($shape['severity'])->toBe('violation');
        expect($shape['severity_iri'])->toBeNull();
        expect($shape['message'])->toBeNull();
        expect($shape['messages'])->toBe([]);
        expect($shape['deactivated'])->toBeFalse();
        expect($shape['metadata'])->toBeArray();
        expect($shape['metadata']['source'])->toBe('shacl_parser');
        expect($shape['metadata']['types'])->toBeArray();
    });

    it('extracts labels from sh:name when rdfs:label is not present', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:name "Person Shape"@en ;
    sh:targetClass ex:Person .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/PersonShape';
        expect($shapes[$shapeUri]['labels'])->toHaveKey('en');
        expect($shapes[$shapeUri]['labels']['en'])->toBe('Person Shape');
        expect($shapes[$shapeUri]['label'])->toBe('Person Shape');
    });

    it('extracts descriptions from sh:description when rdfs:comment is not present', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:description "Validates Person"@en ;
    sh:targetClass ex:Person .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/PersonShape';
        expect($shapes[$shapeUri]['descriptions'])->toHaveKey('en');
        expect($shapes[$shapeUri]['descriptions']['en'])->toBe('Validates Person');
        expect($shapes[$shapeUri]['description'])->toBe('Validates Person');
    });

    it('stores unlanguaged labels as en default', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    rdfs:label "Person Shape" ;
    sh:targetClass ex:Person .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/PersonShape';
        expect($shapes[$shapeUri]['labels'])->toHaveKey('en');
        expect($shapes[$shapeUri]['labels']['en'])->toBe('Person Shape');
    });

    it('extracts severity from shape', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:severity sh:Warning ;
    sh:targetClass ex:Person .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/PersonShape';
        expect($shapes[$shapeUri]['severity'])->toBe('warning');
        expect($shapes[$shapeUri]['severity_iri'])->toBe('http://www.w3.org/ns/shacl#Warning');
    });

    it('extracts deactivated as native bool', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:deactivated true ;
    sh:targetClass ex:Person .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/PersonShape';
        expect($shapes[$shapeUri]['deactivated'])->toBeTrue();
        expect($shapes[$shapeUri]['deactivated'])->toBeBool();
    });

    it('extracts messages as multilingual array', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:message "Must be a valid person"@en, "Moet een geldig persoon zijn"@nl ;
    sh:targetClass ex:Person .
TTL;
        $parsedRdf = createParsedRdfFromTurtle($turtle);
        $shapes = $this->processor->extractNodeShapes($parsedRdf);

        $shapeUri = 'http://example.org/PersonShape';
        expect($shapes[$shapeUri]['messages'])->toBeArray();
        expect(count($shapes[$shapeUri]['messages']))->toBeGreaterThanOrEqual(2);
        expect($shapes[$shapeUri]['message'])->toBeString();
    });
});
