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
        expect($shapes[$shapeUri]['target_subjects_of'])->toBe(['http://example.org/name']);
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
        expect($shapes[$shapeUri]['target_objects_of'])->toBe(['http://example.org/knows']);
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
        expect($shapes[$shapeUri]['target_subjects_of'])->toBe([]);
        expect($shapes[$shapeUri]['target_objects_of'])->toBe([]);
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
        expect($shapes[$shapeUri]['target_subjects_of'])->toBe(['http://example.org/name']);
        expect($shapes[$shapeUri]['target_objects_of'])->toBe(['http://example.org/knows']);
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
        expect($shape['target_subjects_of'])->toBe([]);
        expect($shape['target_objects_of'])->toBe([]);
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

    // ============================================================================
    // Story 14.2: Node-Level Constraint Extraction
    // ============================================================================

    describe('node-level constraints', function () {

        it('extracts sh:and constraint with component shapes as array', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:and ( ex:NamedShape ex:AgedShape ) .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/PersonShape';
            expect($shapes[$shapeUri]['constraints'])->toHaveKey('and');
            expect($shapes[$shapeUri]['constraints']['and'])->toBeArray();
            expect($shapes[$shapeUri]['constraints']['and'])->toContain('http://example.org/NamedShape');
            expect($shapes[$shapeUri]['constraints']['and'])->toContain('http://example.org/AgedShape');
            expect(count($shapes[$shapeUri]['constraints']['and']))->toBe(2);
        });

        it('extracts sh:or constraint with component shapes as array', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:ContactShape a sh:NodeShape ;
    sh:targetClass ex:Contact ;
    sh:or ( ex:PersonShape ex:OrganizationShape ) .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/ContactShape';
            expect($shapes[$shapeUri]['constraints'])->toHaveKey('or');
            expect($shapes[$shapeUri]['constraints']['or'])->toContain('http://example.org/PersonShape');
            expect($shapes[$shapeUri]['constraints']['or'])->toContain('http://example.org/OrganizationShape');
        });

        it('extracts sh:xone constraint with component shapes as array', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:IdShape a sh:NodeShape ;
    sh:targetClass ex:Entity ;
    sh:xone ( ex:NumericIdShape ex:StringIdShape ) .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/IdShape';
            expect($shapes[$shapeUri]['constraints'])->toHaveKey('xone');
            expect($shapes[$shapeUri]['constraints']['xone'])->toContain('http://example.org/NumericIdShape');
            expect($shapes[$shapeUri]['constraints']['xone'])->toContain('http://example.org/StringIdShape');
        });

        it('extracts sh:not constraint as single shape URI', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:NonSpamShape a sh:NodeShape ;
    sh:targetClass ex:Message ;
    sh:not ex:SpamShape .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/NonSpamShape';
            expect($shapes[$shapeUri]['constraints'])->toHaveKey('not');
            expect($shapes[$shapeUri]['constraints']['not'])->toBe('http://example.org/SpamShape');
        });

        it('extracts combined logical constraints on same shape', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:ComplexShape a sh:NodeShape ;
    sh:targetClass ex:Thing ;
    sh:and ( ex:ShapeA ex:ShapeB ) ;
    sh:not ex:ShapeC .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/ComplexShape';
            expect($shapes[$shapeUri]['constraints'])->toHaveKey('and');
            expect($shapes[$shapeUri]['constraints'])->toHaveKey('not');
            expect($shapes[$shapeUri]['constraints']['and'])->toHaveCount(2);
            expect($shapes[$shapeUri]['constraints']['not'])->toBe('http://example.org/ShapeC');
        });

        it('extracts sh:closed as native boolean true', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
ex:ClosedShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:closed true ;
    sh:ignoredProperties ( rdf:type ) .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/ClosedShape';
            expect($shapes[$shapeUri]['constraints'])->toHaveKey('closed');
            expect($shapes[$shapeUri]['constraints']['closed'])->toBeTrue();
            expect($shapes[$shapeUri]['constraints']['closed'])->toBeBool();
        });

        it('extracts sh:ignoredProperties as array of full URIs', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
ex:ClosedShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:closed true ;
    sh:ignoredProperties ( rdf:type ex:internalFlag ) .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/ClosedShape';
            expect($shapes[$shapeUri]['constraints'])->toHaveKey('ignoredProperties');
            expect($shapes[$shapeUri]['constraints']['ignoredProperties'])->toBeArray();
            expect($shapes[$shapeUri]['constraints']['ignoredProperties'])->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#type');
            expect($shapes[$shapeUri]['constraints']['ignoredProperties'])->toContain('http://example.org/internalFlag');
        });

        it('extracts sh:closed true without sh:ignoredProperties', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:StrictShape a sh:NodeShape ;
    sh:targetClass ex:Strict ;
    sh:closed true .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/StrictShape';
            expect($shapes[$shapeUri]['constraints'])->toHaveKey('closed');
            expect($shapes[$shapeUri]['constraints']['closed'])->toBeTrue();
            expect($shapes[$shapeUri]['constraints'])->not->toHaveKey('ignoredProperties');
        });

        it('keeps empty constraints array for shapes without node-level constraints', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:SimpleShape a sh:NodeShape ;
    sh:targetClass ex:Simple .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/SimpleShape';
            expect($shapes[$shapeUri]['constraints'])->toBe([]);
        });
    });

    // ============================================================================
    // Story 14.3: Multi-Value Targets
    // ============================================================================

    describe('multi-value targets', function () {

        it('extracts multiple sh:targetSubjectsOf values', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:MultiSubjectShape a sh:NodeShape ;
    sh:targetSubjectsOf ex:name, ex:email .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/MultiSubjectShape';
            expect($shapes[$shapeUri]['target_subjects_of'])->toBeArray();
            expect($shapes[$shapeUri]['target_subjects_of'])->toContain('http://example.org/name');
            expect($shapes[$shapeUri]['target_subjects_of'])->toContain('http://example.org/email');
            expect(count($shapes[$shapeUri]['target_subjects_of']))->toBe(2);
        });

        it('extracts multiple sh:targetObjectsOf values', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:MultiObjectShape a sh:NodeShape ;
    sh:targetObjectsOf ex:knows, ex:likes .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/MultiObjectShape';
            expect($shapes[$shapeUri]['target_objects_of'])->toBeArray();
            expect($shapes[$shapeUri]['target_objects_of'])->toContain('http://example.org/knows');
            expect($shapes[$shapeUri]['target_objects_of'])->toContain('http://example.org/likes');
            expect(count($shapes[$shapeUri]['target_objects_of']))->toBe(2);
        });

        it('wraps single sh:targetSubjectsOf value in array', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:SingleSubjectShape a sh:NodeShape ;
    sh:targetSubjectsOf ex:name .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/SingleSubjectShape';
            expect($shapes[$shapeUri]['target_subjects_of'])->toBeArray();
            expect($shapes[$shapeUri]['target_subjects_of'])->toBe(['http://example.org/name']);
        });

        it('wraps single sh:targetObjectsOf value in array', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:SingleObjectShape a sh:NodeShape ;
    sh:targetObjectsOf ex:knows .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/SingleObjectShape';
            expect($shapes[$shapeUri]['target_objects_of'])->toBeArray();
            expect($shapes[$shapeUri]['target_objects_of'])->toBe(['http://example.org/knows']);
        });
    });

    // ============================================================================
    // Story 14.4: SPARQL Constraint Extraction (Node Shapes)
    // ============================================================================

    describe('SPARQL constraints on node shapes', function () {

        it('extracts single sh:sparql constraint with sh:select query', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:sparql [
        sh:select """
            SELECT $this
            WHERE { $this ex:age ?age . FILTER (?age < 0) }
        """ ;
    ] .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/PersonShape';
            expect($shapes[$shapeUri])->toHaveKey('sparql_constraints');
            expect($shapes[$shapeUri]['sparql_constraints'])->toBeArray();
            expect($shapes[$shapeUri]['sparql_constraints'])->toHaveCount(1);
            expect($shapes[$shapeUri]['sparql_constraints'][0])->toHaveKey('select');
            expect($shapes[$shapeUri]['sparql_constraints'][0]['select'])->toContain('SELECT');
        });

        it('extracts sh:sparql constraint with sh:ask query', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:sparql [
        sh:ask """
            ASK { $this ex:name ?name }
        """ ;
    ] .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/PersonShape';
            expect($shapes[$shapeUri]['sparql_constraints'])->toHaveCount(1);
            expect($shapes[$shapeUri]['sparql_constraints'][0])->toHaveKey('ask');
            expect($shapes[$shapeUri]['sparql_constraints'][0]['ask'])->toContain('ASK');
        });

        it('extracts multiple sh:sparql constraints on same shape', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:sparql [
        sh:select "SELECT $this WHERE { $this ex:age ?age . FILTER (?age < 0) }" ;
    ] ;
    sh:sparql [
        sh:select "SELECT $this WHERE { $this ex:name ?n . FILTER (!bound(?n)) }" ;
    ] .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/PersonShape';
            expect($shapes[$shapeUri]['sparql_constraints'])->toHaveCount(2);
        });

        it('extracts sh:message from SPARQL constraint as multilingual map', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:sparql [
        sh:select "SELECT $this WHERE { $this ex:age ?age . FILTER (?age < 0) }" ;
        sh:message "Age must be positive"@en, "Leeftijd moet positief zijn"@nl ;
    ] .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/PersonShape';
            $sparql = $shapes[$shapeUri]['sparql_constraints'][0];
            expect($sparql)->toHaveKey('messages');
            expect($sparql['messages'])->toBeArray();
            expect($sparql['messages'])->toHaveKey('en');
            expect($sparql['messages']['en'])->toBe('Age must be positive');
            expect($sparql['messages'])->toHaveKey('nl');
            expect($sparql['messages']['nl'])->toBe('Leeftijd moet positief zijn');
        });

        it('extracts sh:deactivated from SPARQL constraint as native bool', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:sparql [
        sh:select "SELECT $this WHERE { $this ex:age ?age . FILTER (?age < 0) }" ;
        sh:deactivated true ;
    ] .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/PersonShape';
            $sparql = $shapes[$shapeUri]['sparql_constraints'][0];
            expect($sparql)->toHaveKey('deactivated');
            expect($sparql['deactivated'])->toBeTrue();
            expect($sparql['deactivated'])->toBeBool();
        });

        it('defaults sh:deactivated to false in SPARQL constraint', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:sparql [
        sh:select "SELECT $this WHERE { $this ex:age ?age . FILTER (?age < 0) }" ;
    ] .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/PersonShape';
            $sparql = $shapes[$shapeUri]['sparql_constraints'][0];
            expect($sparql['deactivated'])->toBeFalse();
        });

        it('extracts sh:prefixes with sh:declare prefix declarations', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .

ex:ExampleOntology a owl:Ontology ;
    sh:declare [
        sh:prefix "ex" ;
        sh:namespace "http://example.org/"^^<http://www.w3.org/2001/XMLSchema#anyURI> ;
    ] ;
    sh:declare [
        sh:prefix "xsd" ;
        sh:namespace "http://www.w3.org/2001/XMLSchema#"^^<http://www.w3.org/2001/XMLSchema#anyURI> ;
    ] .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:sparql [
        sh:prefixes ex:ExampleOntology ;
        sh:select "SELECT $this WHERE { $this ex:age ?age . FILTER (?age < 0) }" ;
    ] .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/PersonShape';
            $sparql = $shapes[$shapeUri]['sparql_constraints'][0];
            expect($sparql)->toHaveKey('prefixes');
            expect($sparql['prefixes'])->toBeArray();
            expect($sparql['prefixes'])->toHaveKey('ex');
            expect($sparql['prefixes']['ex'])->toBe('http://example.org/');
            expect($sparql['prefixes'])->toHaveKey('xsd');
            expect($sparql['prefixes']['xsd'])->toBe('http://www.w3.org/2001/XMLSchema#');
        });

        it('returns empty sparql_constraints array when no sh:sparql present', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:SimpleShape a sh:NodeShape ;
    sh:targetClass ex:Simple .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            $shapeUri = 'http://example.org/SimpleShape';
            expect($shapes[$shapeUri]['sparql_constraints'])->toBe([]);
        });
    });

    // ============================================================================
    // Story 14.5: Shape Recognition Edge Cases
    // ============================================================================

    describe('shape recognition edge cases', function () {

        it('recognizes named shape referenced as sh:node value with own constraints', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:address ;
        sh:node ex:AddressShape ;
    ] .
ex:AddressShape
    sh:property [
        sh:path ex:street ;
        sh:datatype xsd:string ;
    ] .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            expect($shapes)->toHaveKey('http://example.org/AddressShape');
        });

        it('recognizes named shape referenced as sh:node value without own predicates', function () {
            // This is the true edge case: ex:EmptyValidatorShape is referenced as sh:node
            // but has no rdf:type, no target predicates, no constraint parameters of its own.
            // Per SHACL spec, being the value of sh:node means it IS a shape.
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:address ;
        sh:node ex:EmptyValidatorShape ;
    ] .
ex:EmptyValidatorShape rdfs:label "Empty validator" .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            expect($shapes)->toHaveKey('http://example.org/EmptyValidatorShape');
        });

        it('recognizes named shape referenced as sh:qualifiedValueShape value', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:address ;
        sh:qualifiedValueShape ex:HomeAddressShape ;
        sh:qualifiedMinCount 1 ;
    ] .
ex:HomeAddressShape
    sh:property [
        sh:path ex:street ;
        sh:datatype xsd:string ;
    ] .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            expect($shapes)->toHaveKey('http://example.org/HomeAddressShape');
        });

        it('continues recognizing explicitly typed shapes', function () {
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
        });

        it('recognizes named shapes referenced in sh:and/sh:or lists', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:ContactShape a sh:NodeShape ;
    sh:targetClass ex:Contact ;
    sh:or ( ex:PersonRefShape ex:OrgRefShape ) .
ex:PersonRefShape
    sh:property [
        sh:path ex:firstName ;
        sh:datatype xsd:string ;
    ] .
ex:OrgRefShape
    sh:property [
        sh:path ex:orgName ;
        sh:datatype xsd:string ;
    ] .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            expect($shapes)->toHaveKey('http://example.org/PersonRefShape');
            expect($shapes)->toHaveKey('http://example.org/OrgRefShape');
        });

        it('recognizes named shape referenced via sh:not', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:ValidShape a sh:NodeShape ;
    sh:targetClass ex:Thing ;
    sh:not ex:InvalidShape .
ex:InvalidShape
    sh:property [
        sh:path ex:status ;
        sh:hasValue "invalid" ;
    ] .
TTL;
            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $shapes = $this->processor->extractNodeShapes($parsedRdf);

            expect($shapes)->toHaveKey('http://example.org/InvalidShape');
        });
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
