<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserShacl\ShaclParser;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedOntology;

// =============================================================================
// Application Profile Integration Smoke Tests
// Verifies shapes are extracted from real-world SHACL application profiles.
// =============================================================================

describe('SHACL Application Profile Integration - DCAT-AP', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
        $this->fixturesDir = __DIR__ . '/../Fixtures/Shacl';
    });

    it('parses DCAT-AP 2.1.1 and returns ParsedOntology [DCAT-AP smoke]', function () {
        $content = file_get_contents($this->fixturesDir . '/DcatAp/dcat-ap_2.1.1.ttl');
        $result = $this->parser->parse($content);

        expect($result)->toBeInstanceOf(ParsedOntology::class);
        expect($result->metadata['format'])->toBe('turtle');
        expect($result->rawContent)->toBe($content);
    });

    it('extracts at least 5 shapes from DCAT-AP [DCAT-AP shapes]', function () {
        $content = file_get_contents($this->fixturesDir . '/DcatAp/dcat-ap_2.1.1.ttl');
        $result = $this->parser->parse($content);

        expect(count($result->shapes))->toBeGreaterThanOrEqual(5);
    });

    it('extracts dcat:Catalog shape with property shapes [DCAT-AP Catalog]', function () {
        $content = file_get_contents($this->fixturesDir . '/DcatAp/dcat-ap_2.1.1.ttl');
        $result = $this->parser->parse($content);

        $catalogUri = 'http://www.w3.org/ns/dcat#Catalog';
        expect($result->shapes)->toHaveKey($catalogUri);

        $shape = $result->shapes[$catalogUri];
        expect($shape['uri'])->toBe($catalogUri);
        expect($shape['property_shapes'])->not->toBeEmpty();
        expect(count($shape['property_shapes']))->toBeGreaterThanOrEqual(10);
    });

    it('extracts dcat:Dataset shape with property shapes [DCAT-AP Dataset]', function () {
        $content = file_get_contents($this->fixturesDir . '/DcatAp/dcat-ap_2.1.1.ttl');
        $result = $this->parser->parse($content);

        $datasetUri = 'http://www.w3.org/ns/dcat#Dataset';
        expect($result->shapes)->toHaveKey($datasetUri);

        $shape = $result->shapes[$datasetUri];
        expect($shape['property_shapes'])->not->toBeEmpty();
    });

    it('extracts dcat:Distribution shape with property shapes [DCAT-AP Distribution]', function () {
        $content = file_get_contents($this->fixturesDir . '/DcatAp/dcat-ap_2.1.1.ttl');
        $result = $this->parser->parse($content);

        $distUri = 'http://www.w3.org/ns/dcat#Distribution';
        expect($result->shapes)->toHaveKey($distUri);

        $shape = $result->shapes[$distUri];
        expect($shape['property_shapes'])->not->toBeEmpty();
    });

    it('extracts DateOrDateTimeDataType shape with sh:or constraint [DCAT-AP date type]', function () {
        $content = file_get_contents($this->fixturesDir . '/DcatAp/dcat-ap_2.1.1.ttl');
        $result = $this->parser->parse($content);

        $dateShapeUri = 'http://data.europa.eu/r5r#DateOrDateTimeDataType';
        expect($result->shapes)->toHaveKey($dateShapeUri);

        $shape = $result->shapes[$dateShapeUri];
        expect($shape['label'])->toBe('Date time date disjunction');
    });

    it('extracts Catalog property shapes with sh:class constraints [DCAT-AP class constraints]', function () {
        $content = file_get_contents($this->fixturesDir . '/DcatAp/dcat-ap_2.1.1.ttl');
        $result = $this->parser->parse($content);

        $catalogShape = $result->shapes['http://www.w3.org/ns/dcat#Catalog'];
        $classConstrained = array_filter(
            $catalogShape['property_shapes'],
            fn (array $ps): bool => isset($ps['class'])
        );

        // Catalog has multiple sh:class constraints (hasPart, isPartOf, license, etc.)
        expect(count($classConstrained))->toBeGreaterThanOrEqual(3);
    });

    it('extracts prefixes from DCAT-AP [DCAT-AP prefixes]', function () {
        $content = file_get_contents($this->fixturesDir . '/DcatAp/dcat-ap_2.1.1.ttl');
        $result = $this->parser->parse($content);

        expect($result->prefixes)->toHaveKey('sh');
        expect($result->prefixes)->toHaveKey('dcat');
        expect($result->prefixes)->toHaveKey('dct');
    });
});

describe('SHACL Application Profile Integration - ADMS-AP', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
        $this->fixturesDir = __DIR__ . '/../Fixtures/Shacl';
    });

    it('parses ADMS-AP 2.0.0 and returns ParsedOntology [ADMS-AP smoke]', function () {
        $content = file_get_contents($this->fixturesDir . '/AdmsAp/adms-ap_2.0.0.ttl');
        $result = $this->parser->parse($content);

        expect($result)->toBeInstanceOf(ParsedOntology::class);
        expect($result->metadata['format'])->toBe('turtle');
    });

    it('extracts at least 3 shapes from ADMS-AP [ADMS-AP shapes]', function () {
        $content = file_get_contents($this->fixturesDir . '/AdmsAp/adms-ap_2.0.0.ttl');
        $result = $this->parser->parse($content);

        expect(count($result->shapes))->toBeGreaterThanOrEqual(3);
    });

    it('extracts adms:Asset shape with targetClass and property shapes [ADMS-AP Asset]', function () {
        $content = file_get_contents($this->fixturesDir . '/AdmsAp/adms-ap_2.0.0.ttl');
        $result = $this->parser->parse($content);

        $assetUri = 'http://www.w3.org/ns/adms#Asset';
        expect($result->shapes)->toHaveKey($assetUri);

        $shape = $result->shapes[$assetUri];
        expect($shape['target_class'])->toBe('http://www.w3.org/ns/adms#Asset');
        expect($shape['label'])->toBe('Asset Shape');
        expect($shape['property_shapes'])->not->toBeEmpty();
        expect(count($shape['property_shapes']))->toBeGreaterThanOrEqual(5);
    });

    it('extracts adms:AssetDistribution shape [ADMS-AP AssetDistribution]', function () {
        $content = file_get_contents($this->fixturesDir . '/AdmsAp/adms-ap_2.0.0.ttl');
        $result = $this->parser->parse($content);

        $distUri = 'http://www.w3.org/ns/adms#AssetDistribution';
        expect($result->shapes)->toHaveKey($distUri);

        $shape = $result->shapes[$distUri];
        expect($shape['target_class'])->toBe('http://www.w3.org/ns/adms#AssetDistribution');
        expect($shape['property_shapes'])->not->toBeEmpty();
    });

    it('extracts sh:or constraint from ADMS-AP issued property [ADMS-AP logical or]', function () {
        $content = file_get_contents($this->fixturesDir . '/AdmsAp/adms-ap_2.0.0.ttl');
        $result = $this->parser->parse($content);

        $assetShape = $result->shapes['http://www.w3.org/ns/adms#Asset'];

        // Find the issued property shape which has sh:or
        $issuedPs = null;
        foreach ($assetShape['property_shapes'] as $ps) {
            if ($ps['path'] === 'http://purl.org/dc/terms/issued') {
                $issuedPs = $ps;
                break;
            }
        }

        expect($issuedPs)->not->toBeNull();
        expect($issuedPs)->toHaveKey('sh_or');
        expect($issuedPs['sh_or'])->toHaveCount(2);
    });
});

describe('SHACL Application Profile Integration - NL-SBB SKOS-AP-NL', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
        $this->fixturesDir = __DIR__ . '/../Fixtures/Shacl';
    });

    it('parses SKOS-AP-NL and returns ParsedOntology [NL-SBB smoke]', function () {
        $content = file_get_contents($this->fixturesDir . '/NlSbb/skos-ap-nl.ttl');
        $result = $this->parser->parse($content);

        expect($result)->toBeInstanceOf(ParsedOntology::class);
        expect($result->metadata['format'])->toBe('turtle');
    });

    it('extracts 5 shapes from SKOS-AP-NL [NL-SBB shapes]', function () {
        $content = file_get_contents($this->fixturesDir . '/NlSbb/skos-ap-nl.ttl');
        $result = $this->parser->parse($content);

        expect(count($result->shapes))->toBe(5);
    });

    it('extracts Concept shape targeting skos:Concept [NL-SBB Concept]', function () {
        $content = file_get_contents($this->fixturesDir . '/NlSbb/skos-ap-nl.ttl');
        $result = $this->parser->parse($content);

        $conceptUri = 'http://nlbegrip.nl/def/skosapnl#Concept';
        expect($result->shapes)->toHaveKey($conceptUri);

        $shape = $result->shapes[$conceptUri];
        expect($shape['target_class'])->toBe('http://www.w3.org/2004/02/skos/core#Concept');
        expect($shape['label'])->toBe('Begrip');
    });

    it('extracts SourceDocument shape with sh:targetObjectsOf [NL-SBB SourceDocument]', function () {
        $content = file_get_contents($this->fixturesDir . '/NlSbb/skos-ap-nl.ttl');
        $result = $this->parser->parse($content);

        $srcDocUri = 'http://nlbegrip.nl/def/skosapnl#SourceDocument';
        expect($result->shapes)->toHaveKey($srcDocUri);

        $shape = $result->shapes[$srcDocUri];
        expect($shape['target_objects_of'])->toBe(['http://purl.org/dc/terms/source']);
        expect($shape['label'])->toBe('Brondocument');
    });

    it('extracts Dutch language labels from SKOS-AP-NL [NL-SBB labels]', function () {
        $content = file_get_contents($this->fixturesDir . '/NlSbb/skos-ap-nl.ttl');
        $result = $this->parser->parse($content);

        $conceptShape = $result->shapes['http://nlbegrip.nl/def/skosapnl#Concept'];
        expect($conceptShape['labels'])->toHaveKey('nl');
        expect($conceptShape['labels']['nl'])->toBe('Begrip');
    });
});

describe('SHACL Application Profile Integration - TopBraid Person', function () {

    beforeEach(function () {
        $this->parser = new ShaclParser();
        $this->fixturesDir = __DIR__ . '/../Fixtures/Shacl';
    });

    it('parses TopBraid Person example and returns ParsedOntology [TopBraid smoke]', function () {
        $content = file_get_contents($this->fixturesDir . '/TopBraid/person.ttl');
        $result = $this->parser->parse($content);

        expect($result)->toBeInstanceOf(ParsedOntology::class);
        expect($result->metadata['format'])->toBe('turtle');
    });

    it('extracts at least 3 node shapes from TopBraid [TopBraid shapes]', function () {
        $content = file_get_contents($this->fixturesDir . '/TopBraid/person.ttl');
        $result = $this->parser->parse($content);

        // PersonShape, AddressShape, EmployeeShape are the main node shapes
        $namedNodeShapes = ['http://example.org/ns#PersonShape', 'http://example.org/ns#AddressShape', 'http://example.org/ns#EmployeeShape'];
        foreach ($namedNodeShapes as $uri) {
            expect($result->shapes)->toHaveKey($uri);
        }
    });

    it('extracts PersonShape with named property shapes [TopBraid PersonShape]', function () {
        $content = file_get_contents($this->fixturesDir . '/TopBraid/person.ttl');
        $result = $this->parser->parse($content);

        $personShape = $result->shapes['http://example.org/ns#PersonShape'];
        expect($personShape['target_class'])->toBe('http://example.org/ns#Person');
        expect($personShape['label'])->toBe('Person Shape');
        expect($personShape['property_shapes'])->not->toBeEmpty();
    });

    it('extracts named property shapes as top-level shapes [TopBraid named property shapes]', function () {
        $content = file_get_contents($this->fixturesDir . '/TopBraid/person.ttl');
        $result = $this->parser->parse($content);

        // Named property shapes like ex:PersonShape-firstName are recognized as shapes too
        $firstNameUri = 'http://example.org/ns#PersonShape-firstName';
        expect($result->shapes)->toHaveKey($firstNameUri);
    });

    it('extracts AddressShape with multiple property shapes [TopBraid AddressShape]', function () {
        $content = file_get_contents($this->fixturesDir . '/TopBraid/person.ttl');
        $result = $this->parser->parse($content);

        $addressShape = $result->shapes['http://example.org/ns#AddressShape'];
        expect($addressShape['target_class'])->toBe('http://example.org/ns#Address');
        expect(count($addressShape['property_shapes']))->toBeGreaterThanOrEqual(4);
    });

    it('extracts EmployeeShape targeting Employee class [TopBraid EmployeeShape]', function () {
        $content = file_get_contents($this->fixturesDir . '/TopBraid/person.ttl');
        $result = $this->parser->parse($content);

        $employeeShape = $result->shapes['http://example.org/ns#EmployeeShape'];
        expect($employeeShape['target_class'])->toBe('http://example.org/ns#Employee');
        expect($employeeShape['label'])->toBe('Employee Shape');
        expect($employeeShape['property_shapes'])->not->toBeEmpty();
    });

    it('extracts sh:or constraint from birthDate property shape [TopBraid logical or]', function () {
        $content = file_get_contents($this->fixturesDir . '/TopBraid/person.ttl');
        $result = $this->parser->parse($content);

        $birthDateUri = 'http://example.org/ns#PersonShape-birthDate';
        expect($result->shapes)->toHaveKey($birthDateUri);

        // Named property shapes are expanded inline in PersonShape's property_shapes.
        // Verify the sh:or constraint is extracted from the birthDate property shape.
        $personShape = $result->shapes['http://example.org/ns#PersonShape'];
        $birthPs = null;
        foreach ($personShape['property_shapes'] as $ps) {
            if (is_string($ps['path']) && $ps['path'] === 'http://example.org/ns#birthDate') {
                $birthPs = $ps;
                break;
            }
        }

        expect($birthPs)->not->toBeNull();
        expect($birthPs)->toHaveKey('sh_or');
        expect($birthPs['sh_or'])->toHaveCount(2);
    });

    it('extracts sh:in constraint from gender property shape [TopBraid sh:in]', function () {
        $content = file_get_contents($this->fixturesDir . '/TopBraid/person.ttl');
        $result = $this->parser->parse($content);

        $genderUri = 'http://example.org/ns#PersonShape-gender';
        expect($result->shapes)->toHaveKey($genderUri);

        // Verify sh:in values are extracted via PersonShape's expanded property shapes
        $personShape = $result->shapes['http://example.org/ns#PersonShape'];
        $genderPs = null;
        foreach ($personShape['property_shapes'] as $ps) {
            if (is_string($ps['path']) && $ps['path'] === 'http://example.org/ns#gender') {
                $genderPs = $ps;
                break;
            }
        }

        expect($genderPs)->not->toBeNull();
        expect($genderPs)->toHaveKey('in');
        expect($genderPs['in'])->toContain('Male');
        expect($genderPs['in'])->toContain('Female');
    });

    it('extracts sh:pattern and sh:flags from email property shape [TopBraid pattern]', function () {
        $content = file_get_contents($this->fixturesDir . '/TopBraid/person.ttl');
        $result = $this->parser->parse($content);

        $emailUri = 'http://example.org/ns#PersonShape-email';
        expect($result->shapes)->toHaveKey($emailUri);

        // Verify sh:pattern and sh:flags are extracted via PersonShape's expanded property shapes
        $personShape = $result->shapes['http://example.org/ns#PersonShape'];
        $emailPs = null;
        foreach ($personShape['property_shapes'] as $ps) {
            if (is_string($ps['path']) && $ps['path'] === 'http://example.org/ns#email') {
                $emailPs = $ps;
                break;
            }
        }

        expect($emailPs)->not->toBeNull();
        expect($emailPs)->toHaveKey('pattern');
        expect($emailPs['pattern'])->toContain('@');
        expect($emailPs)->toHaveKey('flags');
        expect($emailPs['flags'])->toBe('i');
    });

    it('extracts severity variations across property shapes [TopBraid severity]', function () {
        $content = file_get_contents($this->fixturesDir . '/TopBraid/person.ttl');
        $result = $this->parser->parse($content);

        // PersonShape-age has sh:severity sh:Warning
        $ageShape = $result->shapes['http://example.org/ns#PersonShape-age'];
        expect($ageShape['severity'])->toBe('warning');
        expect($ageShape['severity_iri'])->toBe('http://www.w3.org/ns/shacl#Warning');

        // PersonShape-firstName has sh:severity sh:Violation
        $firstNameShape = $result->shapes['http://example.org/ns#PersonShape-firstName'];
        expect($firstNameShape['severity'])->toBe('violation');
        expect($firstNameShape['severity_iri'])->toBe('http://www.w3.org/ns/shacl#Violation');

        // PersonShape-address has sh:severity sh:Info
        $addressShape = $result->shapes['http://example.org/ns#PersonShape-address'];
        expect($addressShape['severity'])->toBe('info');
        expect($addressShape['severity_iri'])->toBe('http://www.w3.org/ns/shacl#Info');
    });
});
