<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserShacl\Extractors\ShaclPropertyAnalyzer;
use Youri\vandenBogert\Software\ParserShacl\Extractors\ShaclShapeProcessor;

/**
 * Helper to create a ParsedRdf from Turtle content for property analyzer tests.
 */
function createParsedRdfForPropertyAnalyzer(string $turtleContent): ParsedRdf
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

/**
 * Helper to extract property shapes from Turtle.
 *
 * @return array<string, array<string, mixed>>
 */
function extractPropertyShapesFromTurtle(string $turtle): array
{
    $parsedRdf = createParsedRdfForPropertyAnalyzer($turtle);
    $processor = new ShaclShapeProcessor();
    $nodeShapes = $processor->extractNodeShapes($parsedRdf);
    $analyzer = new ShaclPropertyAnalyzer();

    return $analyzer->extractPropertyShapes($parsedRdf, $nodeShapes);
}

describe('ShaclPropertyAnalyzer', function () {

    it('is a final class', function () {
        $reflection = new ReflectionClass(ShaclPropertyAnalyzer::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    it('returns array from extractPropertyShapes', function () {
        $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .
TTL;
        $shapes = extractPropertyShapesFromTurtle($turtle);
        expect($shapes)->toBeArray();
        expect($shapes['http://example.org/PersonShape']['property_shapes'])->toBe([]);
    });

    describe('path extraction', function () {

        it('extracts simple predicate path as full URI string', function () {
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
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'];
            expect($ps)->toHaveCount(1);
            expect($ps[0]['path'])->toBe('http://example.org/name');
        });

        it('extracts inverse path structure', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path [ sh:inversePath ex:parent ] ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'];
            expect($ps)->toHaveCount(1);
            expect($ps[0]['path'])->toBe([
                'type' => 'inverse',
                'path' => 'http://example.org/parent',
            ]);
        });

        it('extracts alternative path structure', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path [ sh:alternativePath ( ex:name ex:label ) ] ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'];
            expect($ps)->toHaveCount(1);
            expect($ps[0]['path'])->toBe([
                'type' => 'alternative',
                'paths' => ['http://example.org/name', 'http://example.org/label'],
            ]);
        });

        it('extracts sequence path structure', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ( ex:address ex:city ) ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'];
            expect($ps)->toHaveCount(1);
            expect($ps[0]['path'])->toBe([
                'type' => 'sequence',
                'paths' => ['http://example.org/address', 'http://example.org/city'],
            ]);
        });

        it('extracts zero-or-more path structure', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path [ sh:zeroOrMorePath ex:parent ] ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'];
            expect($ps)->toHaveCount(1);
            expect($ps[0]['path'])->toBe([
                'type' => 'zeroOrMore',
                'path' => 'http://example.org/parent',
            ]);
        });

        it('extracts one-or-more path structure', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path [ sh:oneOrMorePath ex:parent ] ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'];
            expect($ps)->toHaveCount(1);
            expect($ps[0]['path'])->toBe([
                'type' => 'oneOrMore',
                'path' => 'http://example.org/parent',
            ]);
        });

        it('extracts zero-or-one path structure', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path [ sh:zeroOrOnePath ex:parent ] ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'];
            expect($ps)->toHaveCount(1);
            expect($ps[0]['path'])->toBe([
                'type' => 'zeroOrOne',
                'path' => 'http://example.org/parent',
            ]);
        });

        // ============================================================================
        // Story 14.3: Nested Property Paths
        // ============================================================================

        it('extracts inverse path wrapping alternative path (nested)', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path [ sh:inversePath [ sh:alternativePath ( ex:knows ex:likes ) ] ] ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'];
            expect($ps)->toHaveCount(1);
            expect($ps[0]['path'])->toBeArray();
            expect($ps[0]['path']['type'])->toBe('inverse');
            expect($ps[0]['path']['path'])->toBeArray();
            expect($ps[0]['path']['path']['type'])->toBe('alternative');
            expect($ps[0]['path']['path']['paths'])->toBe(['http://example.org/knows', 'http://example.org/likes']);
        });

        it('extracts zeroOrMore path wrapping inverse path (nested)', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path [ sh:zeroOrMorePath [ sh:inversePath ex:parent ] ] ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'];
            expect($ps)->toHaveCount(1);
            expect($ps[0]['path'])->toBeArray();
            expect($ps[0]['path']['type'])->toBe('zeroOrMore');
            expect($ps[0]['path']['path'])->toBeArray();
            expect($ps[0]['path']['path']['type'])->toBe('inverse');
            expect($ps[0]['path']['path']['path'])->toBe('http://example.org/parent');
        });

        it('extracts oneOrMore path wrapping a direct URI', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path [ sh:oneOrMorePath ex:parent ] ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'];
            expect($ps)->toHaveCount(1);
            expect($ps[0]['path']['type'])->toBe('oneOrMore');
            expect($ps[0]['path']['path'])->toBe('http://example.org/parent');
        });

        it('excludes property shapes with empty path', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:datatype xsd:string ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'];
            expect($ps)->toBe([]);
        });
    });

    describe('constraint extraction', function () {

        it('extracts sh:datatype as full URI', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
        });

        it('extracts sh:minCount as string', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:name ; sh:minCount 1 ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['minCount'])->toBe('1');
            expect($ps['minCount'])->toBeString();
        });

        it('extracts sh:maxCount as string', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:name ; sh:maxCount 1 ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['maxCount'])->toBe('1');
            expect($ps['maxCount'])->toBeString();
        });

        it('extracts sh:minLength as string', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:name ; sh:minLength 3 ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['minLength'])->toBe('3');
            expect($ps['minLength'])->toBeString();
        });

        it('extracts sh:maxLength as string', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:name ; sh:maxLength 100 ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['maxLength'])->toBe('100');
            expect($ps['maxLength'])->toBeString();
        });

        it('extracts sh:pattern as regex string', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:email ; sh:pattern "^[a-z]+@[a-z]+$" ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['pattern'])->toBe('^[a-z]+@[a-z]+$');
        });

        it('extracts sh:flags as string', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:name ; sh:pattern "^[A-Z]" ; sh:flags "i" ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['flags'])->toBe('i');
        });

        it('extracts sh:class single and multiple', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:address ; sh:class ex:Address, ex:Location ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['class'])->toBeString();
            expect($ps['class'])->toStartWith('http://');
            expect($ps['classes'])->toBeArray();
            expect($ps['classes'])->toContain('http://example.org/Address');
            expect($ps['classes'])->toContain('http://example.org/Location');
            expect(count($ps['classes']))->toBe(2);
        });

        it('extracts sh:node as URI', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:address ; sh:node ex:AddressShape ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['node'])->toBe('http://example.org/AddressShape');
        });

        it('extracts sh:nodeKind as full URI', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:id ; sh:nodeKind sh:IRI ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['nodeKind'])->toBe('http://www.w3.org/ns/shacl#IRI');
        });

        it('extracts sh:hasValue', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:status ; sh:hasValue "active" ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['hasValue'])->toBe('active');
        });

        it('extracts sh:in as array from RDF list', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:status ; sh:in ( "active" "inactive" "pending" ) ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['in'])->toBe(['active', 'inactive', 'pending']);
        });

        it('extracts sh:languageIn as array from RDF list', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:name ; sh:languageIn ( "en" "nl" "de" ) ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['languageIn'])->toBe(['en', 'nl', 'de']);
        });

        it('extracts sh:uniqueLang as string', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:name ; sh:uniqueLang true ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['uniqueLang'])->toBe('1');
            expect($ps['uniqueLang'])->toBeString();
        });

        it('extracts sh:equals as URI', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:name ; sh:equals ex:fullName ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['equals'])->toBe('http://example.org/fullName');
        });

        it('extracts sh:disjoint as URI', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:firstName ; sh:disjoint ex:lastName ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['disjoint'])->toBe('http://example.org/lastName');
        });

        it('extracts sh:lessThan as URI', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:startDate ; sh:lessThan ex:endDate ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['lessThan'])->toBe('http://example.org/endDate');
        });

        it('extracts sh:lessThanOrEquals as URI', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:startDate ; sh:lessThanOrEquals ex:endDate ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['lessThanOrEquals'])->toBe('http://example.org/endDate');
        });

        it('extracts sh:qualifiedValueShape as URI', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [
        sh:path ex:address ;
        sh:qualifiedValueShape ex:AddressShape ;
        sh:qualifiedMinCount 1 ;
        sh:qualifiedMaxCount 3 ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['qualifiedValueShape'])->toBe('http://example.org/AddressShape');
            expect($ps['qualifiedMinCount'])->toBe('1');
            expect($ps['qualifiedMaxCount'])->toBe('3');
        });

        it('extracts sh:qualifiedValueShapesDisjoint as string', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [
        sh:path ex:address ;
        sh:qualifiedValueShape ex:AddressShape ;
        sh:qualifiedValueShapesDisjoint true ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['qualifiedValueShapesDisjoint'])->toBe('1');
            expect($ps['qualifiedValueShapesDisjoint'])->toBeString();
        });

        it('extracts sh:minInclusive as string', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:age ; sh:minInclusive 0 ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['minInclusive'])->toBe('0');
            expect($ps['minInclusive'])->toBeString();
        });

        it('extracts sh:maxInclusive as string', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:age ; sh:maxInclusive 150 ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['maxInclusive'])->toBe('150');
            expect($ps['maxInclusive'])->toBeString();
        });

        it('extracts sh:minExclusive as string', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:age ; sh:minExclusive -1 ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['minExclusive'])->toBe('-1');
            expect($ps['minExclusive'])->toBeString();
        });

        it('extracts sh:maxExclusive as string', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:age ; sh:maxExclusive 200 ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['maxExclusive'])->toBe('200');
            expect($ps['maxExclusive'])->toBeString();
        });

        it('extracts sh:name as property shape label', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [
        sh:path ex:name ;
        sh:name "Full Name"@en, "Volledige Naam"@nl ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['name'])->toBeString();
            expect($ps['labels'])->toBeArray();
            expect($ps['labels'])->toHaveKey('en');
            expect($ps['labels']['en'])->toBe('Full Name');
            expect($ps['labels'])->toHaveKey('nl');
            expect($ps['labels']['nl'])->toBe('Volledige Naam');
        });

        it('extracts sh:description as property shape description', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [
        sh:path ex:name ;
        sh:description "The full name"@en, "De volledige naam"@nl ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['description'])->toBeString();
            expect($ps['descriptions'])->toBeArray();
            expect($ps['descriptions'])->toHaveKey('en');
            expect($ps['descriptions']['en'])->toBe('The full name');
            expect($ps['descriptions'])->toHaveKey('nl');
            expect($ps['descriptions']['nl'])->toBe('De volledige naam');
        });

        it('extracts sh:message single and multilingual', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [
        sh:path ex:name ;
        sh:message "Name is required"@en, "Naam is verplicht"@nl ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['message'])->toBeString();
            expect($ps['messages'])->toBeArray();
            expect(count($ps['messages']))->toBeGreaterThanOrEqual(2);
        });

        it('extracts sh:order as decimal string', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:name ; sh:order 1 ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['order'])->toBe('1');
            expect($ps['order'])->toBeString();
        });

        it('extracts sh:group as URI', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:name ; sh:group ex:PersonalInfoGroup ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['group'])->toBe('http://example.org/PersonalInfoGroup');
        });

        it('extracts sh:defaultValue', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:status ; sh:defaultValue "active" ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['defaultValue'])->toBe('active');
        });

        it('extracts sh:deactivated as string', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:name ; sh:deactivated true ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['deactivated'])->toBe('1');
            expect($ps['deactivated'])->toBeString();
        });

        it('handles property shape with multiple constraints', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
        sh:minCount 1 ;
        sh:maxCount 1 ;
        sh:minLength 1 ;
        sh:maxLength 100 ;
        sh:pattern "^[A-Z]" ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['path'])->toBe('http://example.org/name');
            expect($ps['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
            expect($ps['minCount'])->toBe('1');
            expect($ps['maxCount'])->toBe('1');
            expect($ps['minLength'])->toBe('1');
            expect($ps['maxLength'])->toBe('100');
            expect($ps['pattern'])->toBe('^[A-Z]');
        });

        it('filters out null and empty values from property shape output', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:name ; sh:datatype xsd:string ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps)->not->toHaveKey('minCount');
            expect($ps)->not->toHaveKey('maxCount');
            expect($ps)->not->toHaveKey('pattern');
            expect($ps)->not->toHaveKey('class');
            expect($ps)->toHaveKey('path');
            expect($ps)->toHaveKey('datatype');
        });

        it('uses full URIs never prefixed notation', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:name ; sh:datatype xsd:string ; sh:nodeKind sh:Literal ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['path'])->toStartWith('http://');
            expect($ps['datatype'])->toStartWith('http://');
            expect($ps['nodeKind'])->toStartWith('http://');
        });
    });

    describe('logical constraints', function () {

        it('extracts sh:or with inline class constraints', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [
        sh:path ex:contact ;
        sh:or ( [ sh:class ex:Email ] [ sh:class ex:Phone ] ) ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['sh_or'])->toBeArray();
            expect(count($ps['sh_or']))->toBe(2);
            expect($ps['sh_or'][0]['class'])->toBe('http://example.org/Email');
            expect($ps['sh_or'][1]['class'])->toBe('http://example.org/Phone');
        });

        it('extracts sh:and with inline constraints', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:property [
        sh:path ex:value ;
        sh:and ( [ sh:datatype xsd:integer ] [ sh:nodeKind sh:Literal ] ) ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['sh_and'])->toBeArray();
            expect(count($ps['sh_and']))->toBe(2);
            expect($ps['sh_and'][0]['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#integer');
            expect($ps['sh_and'][1]['nodeKind'])->toBe('http://www.w3.org/ns/shacl#Literal');
        });

        it('extracts sh:xone with inline constraints', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [
        sh:path ex:contact ;
        sh:xone ( [ sh:class ex:Email ] [ sh:class ex:Phone ] ) ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps['sh_xone'])->toBeArray();
            expect(count($ps['sh_xone']))->toBe(2);
            expect($ps['sh_xone'][0]['class'])->toBe('http://example.org/Email');
            expect($ps['sh_xone'][1]['class'])->toBe('http://example.org/Phone');
        });

        it('extracts sh:not with inline class constraint', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:property [
        sh:path ex:contact ;
        sh:not [ sh:class ex:Spam ] ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps)->toHaveKey('sh_not');
            expect($ps['sh_not'])->toBeArray();
            expect($ps['sh_not']['class'])->toBe('http://example.org/Spam');
        });

        it('extracts sh:not with inline datatype constraint', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:property [
        sh:path ex:value ;
        sh:not [ sh:datatype xsd:string ] ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps)->toHaveKey('sh_not');
            expect($ps['sh_not']['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
        });

        it('omits sh_not key when sh:not is absent', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:name ; sh:datatype xsd:string ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps)->not->toHaveKey('sh_not');
        });
    });

    // ============================================================================
    // Story 14.4: SPARQL Constraints on Property Shapes
    // ============================================================================

    describe('SPARQL constraints on property shapes', function () {

        it('extracts sh:sparql constraint from property shape', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:sparql [
            sh:select "SELECT $this ?value WHERE { $this ex:name ?value . FILTER (strlen(?value) > 100) }" ;
            sh:message "Name too long"@en ;
        ] ;
    ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps)->toHaveKey('sparql_constraints');
            expect($ps['sparql_constraints'])->toBeArray();
            expect($ps['sparql_constraints'])->toHaveCount(1);
            expect($ps['sparql_constraints'][0])->toHaveKey('select');
            expect($ps['sparql_constraints'][0]['select'])->toContain('SELECT');
            expect($ps['sparql_constraints'][0]['messages'])->toHaveKey('en');
            expect($ps['sparql_constraints'][0]['messages']['en'])->toBe('Name too long');
        });

        it('omits sparql_constraints key when no sh:sparql present', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:name ; sh:datatype xsd:string ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps)->not->toHaveKey('sparql_constraints');
        });
    });

    describe('extractRangeFromShape()', function () {

        it('returns datatype from datatype key', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            $result = $analyzer->extractRangeFromShape(['datatype' => 'http://www.w3.org/2001/XMLSchema#string']);
            expect($result)->toBe(['http://www.w3.org/2001/XMLSchema#string']);
        });

        it('returns class from class key', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            $result = $analyzer->extractRangeFromShape(['class' => 'http://example.org/Person']);
            expect($result)->toBe(['http://example.org/Person']);
        });

        it('extracts ranges from logical constraints', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            $result = $analyzer->extractRangeFromShape([
                'sh_or' => [
                    ['class' => 'http://example.org/Email'],
                    ['class' => 'http://example.org/Phone'],
                ],
            ]);
            expect($result)->toContain('http://example.org/Email');
            expect($result)->toContain('http://example.org/Phone');
        });

        it('extracts ranges from sh_and logical constraints', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            $result = $analyzer->extractRangeFromShape([
                'sh_and' => [
                    ['datatype' => 'http://www.w3.org/2001/XMLSchema#integer'],
                ],
            ]);
            expect($result)->toContain('http://www.w3.org/2001/XMLSchema#integer');
        });

        it('extracts ranges from sh_xone logical constraints', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            $result = $analyzer->extractRangeFromShape([
                'sh_xone' => [
                    ['class' => 'http://example.org/A'],
                    ['datatype' => 'http://www.w3.org/2001/XMLSchema#string'],
                ],
            ]);
            expect($result)->toContain('http://example.org/A');
            expect($result)->toContain('http://www.w3.org/2001/XMLSchema#string');
        });

        it('removes duplicate ranges', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            $result = $analyzer->extractRangeFromShape([
                'class' => 'http://example.org/Person',
                'sh_or' => [
                    ['class' => 'http://example.org/Person'],
                    ['class' => 'http://example.org/Company'],
                ],
            ]);
            expect(count($result))->toBe(2);
            expect($result)->toContain('http://example.org/Person');
            expect($result)->toContain('http://example.org/Company');
        });

        it('extracts ranges from sh_not constraint', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            $result = $analyzer->extractRangeFromShape([
                'sh_not' => ['class' => 'http://example.org/Spam'],
            ]);
            expect($result)->toContain('http://example.org/Spam');
        });

        it('returns empty array when no range found', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            $result = $analyzer->extractRangeFromShape(['minCount' => '1']);
            expect($result)->toBe([]);
        });
    });

    describe('determinePropertyTypeFromShape()', function () {

        it('returns object for class constraint', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            expect($analyzer->determinePropertyTypeFromShape(['class' => 'http://example.org/Person']))->toBe('object');
        });

        it('returns object for node constraint', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            expect($analyzer->determinePropertyTypeFromShape(['node' => 'http://example.org/PersonShape']))->toBe('object');
        });

        it('returns object for nodeKind IRI', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            expect($analyzer->determinePropertyTypeFromShape(['nodeKind' => 'http://www.w3.org/ns/shacl#IRI']))->toBe('object');
        });

        it('returns object for nodeKind BlankNodeOrIRI', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            expect($analyzer->determinePropertyTypeFromShape(['nodeKind' => 'http://www.w3.org/ns/shacl#BlankNodeOrIRI']))->toBe('object');
        });

        it('returns datatype for nodeKind Literal', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            expect($analyzer->determinePropertyTypeFromShape(['nodeKind' => 'http://www.w3.org/ns/shacl#Literal']))->toBe('datatype');
        });

        it('returns object for class within logical constraints', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            $result = $analyzer->determinePropertyTypeFromShape([
                'sh_or' => [
                    ['class' => 'http://example.org/Email'],
                ],
            ]);
            expect($result)->toBe('object');
        });

        it('returns object for nodeKind IRI within logical constraints', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            $result = $analyzer->determinePropertyTypeFromShape([
                'sh_and' => [
                    ['nodeKind' => 'http://www.w3.org/ns/shacl#IRI'],
                ],
            ]);
            expect($result)->toBe('object');
        });

        it('returns datatype as default', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            expect($analyzer->determinePropertyTypeFromShape(['minCount' => '1']))->toBe('datatype');
        });

        it('returns datatype when sh:datatype present even with class in logical constraint', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            $result = $analyzer->determinePropertyTypeFromShape([
                'datatype' => 'http://www.w3.org/2001/XMLSchema#string',
                'sh_or' => [
                    ['class' => 'http://example.org/SomeClass'],
                ],
            ]);
            expect($result)->toBe('datatype');
        });

        it('returns object for class within sh:not constraint', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            $result = $analyzer->determinePropertyTypeFromShape([
                'sh_not' => ['class' => 'http://example.org/Spam'],
            ]);
            expect($result)->toBe('object');
        });
    });

    describe('extractCardinality()', function () {

        it('returns 1 for minCount=1 maxCount=1', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            expect($analyzer->extractCardinality(['minCount' => '1', 'maxCount' => '1']))->toBe('1');
        });

        it('returns 1..3 for minCount=1 maxCount=3', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            expect($analyzer->extractCardinality(['minCount' => '1', 'maxCount' => '3']))->toBe('1..3');
        });

        it('returns 1..n for minCount=1 no maxCount', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            expect($analyzer->extractCardinality(['minCount' => '1']))->toBe('1..n');
        });

        it('returns 0..1 for no minCount maxCount=1', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            expect($analyzer->extractCardinality(['maxCount' => '1']))->toBe('0..1');
        });

        it('returns null when no cardinality constraints', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            expect($analyzer->extractCardinality(['datatype' => 'xsd:string']))->toBeNull();
        });

        it('returns null for empty array', function () {
            $analyzer = new ShaclPropertyAnalyzer();
            expect($analyzer->extractCardinality([]))->toBeNull();
        });
    });

    describe('RDF list extraction', function () {

        it('returns null for empty RDF list result via sh:in', function () {
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
ex:PersonShape a sh:NodeShape ;
    sh:property [ sh:path ex:status ; sh:in rdf:nil ] .
TTL;
            $shapes = extractPropertyShapesFromTurtle($turtle);
            $ps = $shapes['http://example.org/PersonShape']['property_shapes'][0];
            expect($ps)->not->toHaveKey('in');
        });
    });

    describe('ShaclParser integration', function () {

        it('populates property_shapes in node shapes through ShaclParser', function () {
            $parser = new \Youri\vandenBogert\Software\ParserShacl\ShaclParser();
            $turtle = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
        sh:minCount 1 ;
    ] .
TTL;
            $result = $parser->parse($turtle);
            expect($result)->toBeInstanceOf(\Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedOntology::class);

            $shapeUri = 'http://example.org/PersonShape';
            expect($result->shapes)->toHaveKey($shapeUri);
            $shape = $result->shapes[$shapeUri];
            expect($shape['property_shapes'])->not->toBe([]);
            expect($shape['property_shapes'][0]['path'])->toBe('http://example.org/name');
            expect($shape['property_shapes'][0]['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
            expect($shape['property_shapes'][0]['minCount'])->toBe('1');
        });
    });
});
