<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserShacl\ShaclParser;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedOntology;

// =============================================================================
// W3C SHACL Conformance Tests - Target Declarations
// Verifies extraction of SHACL target declarations per W3C SHACL Section 2.1.
// =============================================================================

describe('W3C SHACL Conformance - Target Declarations', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
        $this->fixturesDir = __DIR__ . '/../Fixtures/W3c';
    });

    it('extracts sh:targetClass from explicit NodeShape [W3C targetClass-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/targetClass-001.ttl');
        $result = $this->parser->parse($content);

        expect($result)->toBeInstanceOf(ParsedOntology::class);
        expect($result->shapes)->not->toBeEmpty();

        $shapeUri = 'http://example.org/PersonShape';
        expect($result->shapes)->toHaveKey($shapeUri);

        $shape = $result->shapes[$shapeUri];
        expect($shape['uri'])->toBe($shapeUri);
        expect($shape['target_class'])->toBe('http://example.org/Person');
        expect($shape['target_classes'])->toBe(['http://example.org/Person']);
        expect($shape['label'])->toBe('Person Shape');
        expect($shape['description'])->toBe('A shape targeting the Person class');

        // Verify property shapes are extracted
        expect($shape['property_shapes'])->toHaveCount(1);
        expect($shape['property_shapes'][0]['path'])->toBe('http://example.org/name');
        expect($shape['property_shapes'][0]['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
        expect($shape['property_shapes'][0]['minCount'])->toBe('1');
        expect($shape['property_shapes'][0]['maxCount'])->toBe('1');
    });

    it('extracts sh:targetNode from explicit NodeShape [W3C targetNode-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/targetNode-001.ttl');
        $result = $this->parser->parse($content);

        expect($result)->toBeInstanceOf(ParsedOntology::class);

        $shapeUri = 'http://example.org/AliceShape';
        expect($result->shapes)->toHaveKey($shapeUri);

        $shape = $result->shapes[$shapeUri];
        expect($shape['target_node'])->toBe('http://example.org/Alice');
        expect($shape['target_nodes'])->toBe(['http://example.org/Alice']);
        expect($shape['label'])->toBe('Alice Shape');
    });

    it('extracts sh:targetSubjectsOf from explicit NodeShape [W3C targetSubjectsOf-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/targetSubjectsOf-001.ttl');
        $result = $this->parser->parse($content);

        $shapeUri = 'http://example.org/HasNameShape';
        expect($result->shapes)->toHaveKey($shapeUri);

        $shape = $result->shapes[$shapeUri];
        expect($shape['target_subjects_of'])->toBe('http://example.org/name');
        expect($shape['label'])->toBe('Has Name Shape');
    });

    it('extracts sh:targetObjectsOf from explicit NodeShape [W3C targetObjectsOf-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/targetObjectsOf-001.ttl');
        $result = $this->parser->parse($content);

        $shapeUri = 'http://example.org/KnownByShape';
        expect($result->shapes)->toHaveKey($shapeUri);

        $shape = $result->shapes[$shapeUri];
        expect($shape['target_objects_of'])->toBe('http://example.org/knows');
        expect($shape['label'])->toBe('Known By Shape');
    });

    it('extracts implicit target class from rdfs:Class combined with sh:NodeShape [W3C implicitTarget-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/implicitTarget-001.ttl');
        $result = $this->parser->parse($content);

        $shapeUri = 'http://example.org/Person';
        expect($result->shapes)->toHaveKey($shapeUri);

        $shape = $result->shapes[$shapeUri];
        // Implicit target: shape URI itself is the target class
        expect($shape['target_classes'])->toContain('http://example.org/Person');
        expect($shape['metadata']['types'])->toContain('http://www.w3.org/2000/01/rdf-schema#Class');
        expect($shape['metadata']['types'])->toContain('http://www.w3.org/ns/shacl#NodeShape');
    });
});

// =============================================================================
// W3C SHACL Conformance Tests - Core Constraints
// Verifies extraction of SHACL constraint components per W3C SHACL Section 4.
// =============================================================================

describe('W3C SHACL Conformance - Core Constraints', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
        $this->fixturesDir = __DIR__ . '/../Fixtures/W3c';
    });

    it('extracts sh:datatype as full XSD URI [W3C datatype-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/datatype-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/DatatypeShape'];
        expect($shape['property_shapes'])->toHaveCount(3);

        $paths = array_map(fn (array $ps): string => $ps['path'], $shape['property_shapes']);
        expect($paths)->toContain('http://example.org/name');
        expect($paths)->toContain('http://example.org/age');
        expect($paths)->toContain('http://example.org/birthDate');

        // Find each property shape by path
        $namePs = null;
        $agePs = null;
        $birthPs = null;
        foreach ($shape['property_shapes'] as $ps) {
            if ($ps['path'] === 'http://example.org/name') {
                $namePs = $ps;
            }
            if ($ps['path'] === 'http://example.org/age') {
                $agePs = $ps;
            }
            if ($ps['path'] === 'http://example.org/birthDate') {
                $birthPs = $ps;
            }
        }

        expect($namePs['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
        expect($agePs['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#integer');
        expect($birthPs['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#date');
    });

    it('extracts sh:class as full URI [W3C class-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/class-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/PersonShape'];

        $addressPs = null;
        $knowsPs = null;
        foreach ($shape['property_shapes'] as $ps) {
            if ($ps['path'] === 'http://example.org/address') {
                $addressPs = $ps;
            }
            if ($ps['path'] === 'http://example.org/knows') {
                $knowsPs = $ps;
            }
        }

        expect($addressPs)->not->toBeNull();
        expect($addressPs['class'])->toBe('http://example.org/Address');
        expect($knowsPs)->not->toBeNull();
        expect($knowsPs['class'])->toBe('http://example.org/Person');
    });

    it('extracts sh:nodeKind as full SHACL URI [W3C nodeKind-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/nodeKind-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/ResourceShape'];

        $identPs = null;
        $labelPs = null;
        $relatedPs = null;
        foreach ($shape['property_shapes'] as $ps) {
            if ($ps['path'] === 'http://example.org/identifier') {
                $identPs = $ps;
            }
            if ($ps['path'] === 'http://example.org/label') {
                $labelPs = $ps;
            }
            if ($ps['path'] === 'http://example.org/related') {
                $relatedPs = $ps;
            }
        }

        expect($identPs['nodeKind'])->toBe('http://www.w3.org/ns/shacl#IRI');
        expect($labelPs['nodeKind'])->toBe('http://www.w3.org/ns/shacl#Literal');
        expect($relatedPs['nodeKind'])->toBe('http://www.w3.org/ns/shacl#BlankNodeOrIRI');
    });

    it('extracts sh:minCount and sh:maxCount as string values [W3C minCount-maxCount-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/minCount-maxCount-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/CardinalityShape'];

        $namePs = null;
        $emailPs = null;
        $nickPs = null;
        foreach ($shape['property_shapes'] as $ps) {
            if ($ps['path'] === 'http://example.org/name') {
                $namePs = $ps;
            }
            if ($ps['path'] === 'http://example.org/email') {
                $emailPs = $ps;
            }
            if ($ps['path'] === 'http://example.org/nickname') {
                $nickPs = $ps;
            }
        }

        expect($namePs['minCount'])->toBe('1');
        expect($namePs['maxCount'])->toBe('1');
        expect($emailPs['minCount'])->toBe('0');
        expect($emailPs['maxCount'])->toBe('3');
        expect($nickPs)->not->toHaveKey('minCount');
        expect($nickPs['maxCount'])->toBe('5');
    });

    it('extracts sh:minLength, sh:maxLength, sh:pattern, sh:flags [W3C stringConstraints-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/stringConstraints-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/StringShape'];

        $namePs = null;
        $emailPs = null;
        foreach ($shape['property_shapes'] as $ps) {
            if ($ps['path'] === 'http://example.org/name') {
                $namePs = $ps;
            }
            if ($ps['path'] === 'http://example.org/email') {
                $emailPs = $ps;
            }
        }

        expect($namePs['minLength'])->toBe('1');
        expect($namePs['maxLength'])->toBe('100');

        expect($emailPs['pattern'])->toBeString();
        expect($emailPs['pattern'])->toContain('@');
        expect($emailPs['flags'])->toBe('i');
    });

    it('extracts sh:minInclusive, sh:maxInclusive, sh:minExclusive, sh:maxExclusive [W3C valueRange-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/valueRange-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/ValueRangeShape'];

        $tempPs = null;
        $scorePs = null;
        foreach ($shape['property_shapes'] as $ps) {
            if ($ps['path'] === 'http://example.org/temperature') {
                $tempPs = $ps;
            }
            if ($ps['path'] === 'http://example.org/score') {
                $scorePs = $ps;
            }
        }

        expect($tempPs['minInclusive'])->toBe('-273.15');
        expect($tempPs['maxInclusive'])->toBe('1000');
        expect($scorePs['minExclusive'])->toBe('0');
        expect($scorePs['maxExclusive'])->toBe('100');
    });

    it('extracts sh:equals, sh:disjoint, sh:lessThan, sh:lessThanOrEquals [W3C pairConstraints-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/pairConstraints-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/PairShape'];

        $emailPs = null;
        $nickPs = null;
        $startPs = null;
        $minAgePs = null;
        foreach ($shape['property_shapes'] as $ps) {
            if ($ps['path'] === 'http://example.org/email') {
                $emailPs = $ps;
            }
            if ($ps['path'] === 'http://example.org/nickname') {
                $nickPs = $ps;
            }
            if ($ps['path'] === 'http://example.org/startDate') {
                $startPs = $ps;
            }
            if ($ps['path'] === 'http://example.org/minAge') {
                $minAgePs = $ps;
            }
        }

        expect($emailPs['equals'])->toBe('http://example.org/primaryEmail');
        expect($nickPs['disjoint'])->toBe('http://example.org/name');
        expect($startPs['lessThan'])->toBe('http://example.org/endDate');
        expect($minAgePs['lessThanOrEquals'])->toBe('http://example.org/maxAge');
    });

    it('extracts sh:hasValue as literal value [W3C hasValue-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/hasValue-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/HasValueShape'];
        expect($shape['property_shapes'])->toHaveCount(1);
        expect($shape['property_shapes'][0]['hasValue'])->toBe('active');
    });

    it('extracts sh:in as array of literal values [W3C in-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/in-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/InShape'];
        expect($shape['property_shapes'])->toHaveCount(1);
        expect($shape['property_shapes'][0]['in'])->toBe(['Male', 'Female', 'Other']);
    });

    it('extracts sh:node as URI reference [W3C node-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/node-001.ttl');
        $result = $this->parser->parse($content);

        expect($result->shapes)->toHaveKey('http://example.org/PersonShape');
        expect($result->shapes)->toHaveKey('http://example.org/AddressShape');

        $personShape = $result->shapes['http://example.org/PersonShape'];
        expect($personShape['property_shapes'])->toHaveCount(1);
        expect($personShape['property_shapes'][0]['node'])->toBe('http://example.org/AddressShape');
    });

    it('extracts sh:qualifiedValueShape with qualified counts [W3C qualifiedValueShape-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/qualifiedValueShape-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/QualifiedShape'];
        expect($shape['property_shapes'])->toHaveCount(1);

        $ps = $shape['property_shapes'][0];
        expect($ps['path'])->toBe('http://example.org/address');
        expect($ps['qualifiedValueShape'])->toBeString(); // blank node URI
        expect($ps['qualifiedMinCount'])->toBe('1');
        expect($ps['qualifiedMaxCount'])->toBe('2');
    });
});

// =============================================================================
// W3C SHACL Conformance Tests - Logical Constraints
// Verifies extraction of sh:not, sh:and, sh:or, sh:xone per W3C SHACL Section 4.6.
// =============================================================================

describe('W3C SHACL Conformance - Logical Constraints', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
        $this->fixturesDir = __DIR__ . '/../Fixtures/W3c';
    });

    it('extracts sh:not constraint as single shape data [W3C logical-not-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/logical-not-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/NotStringShape'];
        expect($shape['property_shapes'])->toHaveCount(1);

        $ps = $shape['property_shapes'][0];
        expect($ps)->toHaveKey('sh_not');
        expect($ps['sh_not'])->toBeArray();
        expect($ps['sh_not']['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
    });

    it('extracts sh:and constraint as array of shape data [W3C logical-and-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/logical-and-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/AndShape'];
        expect($shape['property_shapes'])->toHaveCount(1);

        $ps = $shape['property_shapes'][0];
        expect($ps)->toHaveKey('sh_and');
        expect($ps['sh_and'])->toBeArray();
        expect($ps['sh_and'])->toHaveCount(2);
        expect($ps['sh_and'][0]['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
        expect($ps['sh_and'][1]['minLength'])->toBe('1');
    });

    it('extracts sh:or constraint as array of shape data [W3C logical-or-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/logical-or-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/OrShape'];
        expect($shape['property_shapes'])->toHaveCount(1);

        $ps = $shape['property_shapes'][0];
        expect($ps)->toHaveKey('sh_or');
        expect($ps['sh_or'])->toBeArray();
        expect($ps['sh_or'])->toHaveCount(2);
        expect($ps['sh_or'][0]['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#date');
        expect($ps['sh_or'][1]['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#dateTime');
    });

    it('extracts sh:xone constraint as array of shape data [W3C logical-xone-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/logical-xone-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/XoneShape'];
        expect($shape['property_shapes'])->toHaveCount(1);

        $ps = $shape['property_shapes'][0];
        expect($ps)->toHaveKey('sh_xone');
        expect($ps['sh_xone'])->toBeArray();
        expect($ps['sh_xone'])->toHaveCount(2);
        expect($ps['sh_xone'][0]['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
        expect($ps['sh_xone'][1]['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#integer');
    });
});

// =============================================================================
// W3C SHACL Conformance Tests - Node Shape Recognition
// Verifies W3C Section 2.1 shape recognition rules.
// =============================================================================

describe('W3C SHACL Conformance - Node Shape Recognition', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
        $this->fixturesDir = __DIR__ . '/../Fixtures/W3c';
    });

    it('recognizes explicit sh:NodeShape type declaration [W3C nodeShape-explicit-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/nodeShape-explicit-001.ttl');
        $result = $this->parser->parse($content);

        $shapeUri = 'http://example.org/ExplicitShape';
        expect($result->shapes)->toHaveKey($shapeUri);
        expect($result->shapes[$shapeUri]['label'])->toBe('Explicit Node Shape');
        expect($result->shapes[$shapeUri]['description'])->toBe('A shape with explicit sh:NodeShape type');
        expect($result->shapes[$shapeUri]['target_class'])->toBe('http://example.org/Thing');
    });

    it('recognizes shape by target predicate without explicit type (SHP-03) [W3C nodeShape-byTarget-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/nodeShape-byTarget-001.ttl');
        $result = $this->parser->parse($content);

        $shapeUri = 'http://example.org/ImpliedByTarget';
        expect($result->shapes)->toHaveKey($shapeUri);
        expect($result->shapes[$shapeUri]['target_class'])->toBe('http://example.org/Animal');
        expect($result->shapes[$shapeUri]['label'])->toBe('Implied by target');
    });

    it('recognizes shape by constraint parameter without explicit type (SHP-04) [W3C nodeShape-byConstraint-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/nodeShape-byConstraint-001.ttl');
        $result = $this->parser->parse($content);

        $shapeUri = 'http://example.org/ImpliedByConstraint';
        expect($result->shapes)->toHaveKey($shapeUri);
        expect($result->shapes[$shapeUri]['property_shapes'])->toHaveCount(1);
    });

    it('extracts property shapes from node shape [W3C nodeShape-withPropertyShapes-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/nodeShape-withPropertyShapes-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/FullShape'];
        expect($shape['property_shapes'])->toHaveCount(2);

        $firstNamePs = null;
        $agePs = null;
        foreach ($shape['property_shapes'] as $ps) {
            if ($ps['path'] === 'http://example.org/firstName') {
                $firstNamePs = $ps;
            }
            if ($ps['path'] === 'http://example.org/age') {
                $agePs = $ps;
            }
        }

        // firstName property shape
        expect($firstNamePs)->not->toBeNull();
        expect($firstNamePs['name'])->toBe('first name');
        expect($firstNamePs['description'])->toBe('The given name');
        expect($firstNamePs['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#string');
        expect($firstNamePs['minCount'])->toBe('1');
        expect($firstNamePs['maxCount'])->toBe('1');
        expect($firstNamePs['minLength'])->toBe('1');
        expect($firstNamePs['maxLength'])->toBe('50');

        // age property shape
        expect($agePs)->not->toBeNull();
        expect($agePs['name'])->toBe('age');
        expect($agePs['datatype'])->toBe('http://www.w3.org/2001/XMLSchema#integer');
        expect($agePs['minInclusive'])->toBe('0');
        expect($agePs['maxInclusive'])->toBe('150');
    });

    it('extracts sh:deactivated as boolean true [W3C nodeShape-deactivated-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/nodeShape-deactivated-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/DeactivatedShape'];
        expect($shape['deactivated'])->toBeTrue();
        expect($shape['label'])->toBe('Deactivated Shape');
    });

    it('extracts sh:severity with correct mapping [W3C nodeShape-severity-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/nodeShape-severity-001.ttl');
        $result = $this->parser->parse($content);

        $violationShape = $result->shapes['http://example.org/ViolationShape'];
        expect($violationShape['severity'])->toBe('violation');
        expect($violationShape['severity_iri'])->toBe('http://www.w3.org/ns/shacl#Violation');

        $warningShape = $result->shapes['http://example.org/WarningShape'];
        expect($warningShape['severity'])->toBe('warning');
        expect($warningShape['severity_iri'])->toBe('http://www.w3.org/ns/shacl#Warning');

        $infoShape = $result->shapes['http://example.org/InfoShape'];
        expect($infoShape['severity'])->toBe('info');
        expect($infoShape['severity_iri'])->toBe('http://www.w3.org/ns/shacl#Info');
    });

    it('defaults severity to violation when not specified [W3C nodeShape-defaultSeverity-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/nodeShape-byConstraint-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/ImpliedByConstraint'];
        expect($shape['severity'])->toBe('violation');
        expect($shape['severity_iri'])->toBeNull();
    });
});

// =============================================================================
// W3C SHACL Conformance Tests - Closed Shape and Ignored Properties
// =============================================================================

describe('W3C SHACL Conformance - Closed Shapes', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
        $this->fixturesDir = __DIR__ . '/../Fixtures/W3c';
    });

    it('parses closed shape with sh:ignoredProperties [W3C closed-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/closed-001.ttl');
        $result = $this->parser->parse($content);

        expect($result->shapes)->toHaveKey('http://example.org/ClosedShape');

        $shape = $result->shapes['http://example.org/ClosedShape'];
        expect($shape['target_class'])->toBe('http://example.org/Person');
        expect($shape['property_shapes'])->toHaveCount(1);
        expect($shape['property_shapes'][0]['path'])->toBe('http://example.org/name');
    });
});

// =============================================================================
// W3C SHACL Conformance Tests - Shape Metadata
// =============================================================================

describe('W3C SHACL Conformance - Shape Metadata', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
        $this->fixturesDir = __DIR__ . '/../Fixtures/W3c';
    });

    it('includes metadata source and types [W3C targetClass-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/targetClass-001.ttl');
        $result = $this->parser->parse($content);

        $shape = $result->shapes['http://example.org/PersonShape'];
        expect($shape['metadata'])->toHaveKey('source');
        expect($shape['metadata']['source'])->toBe('shacl_parser');
        expect($shape['metadata'])->toHaveKey('types');
        expect($shape['metadata']['types'])->toContain('http://www.w3.org/ns/shacl#NodeShape');
    });

    it('returns format as turtle in ParsedOntology metadata [W3C targetClass-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/targetClass-001.ttl');
        $result = $this->parser->parse($content);

        expect($result->metadata)->toHaveKey('format');
        expect($result->metadata['format'])->toBe('turtle');
    });

    it('preserves rawContent as the original input string [W3C targetClass-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/targetClass-001.ttl');
        $result = $this->parser->parse($content);

        expect($result->rawContent)->toBe($content);
    });

    it('extracts prefixes from Turtle declarations [W3C targetClass-001]', function () {
        $content = file_get_contents($this->fixturesDir . '/targetClass-001.ttl');
        $result = $this->parser->parse($content);

        expect($result->prefixes)->toHaveKey('sh');
        expect($result->prefixes['sh'])->toBe('http://www.w3.org/ns/shacl#');
        expect($result->prefixes)->toHaveKey('ex');
        expect($result->prefixes['ex'])->toBe('http://example.org/');
    });
});
