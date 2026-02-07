<?php

use App\Services\Ontology\Parsers\ShaclParser;

beforeEach(function () {
    $this->parser = new ShaclParser;
});

// ============================================================================
// Section 2.1: Shape Recognition (SHP-03, SHP-04)
// ============================================================================

test('it recognizes shape by target predicate without explicit type (SHP-03)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype <http://www.w3.org/2001/XMLSchema#string> ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    // Shape should be recognized even without "a sh:NodeShape"
    expect($shapes)->toHaveCount(1);
    expect($shapes[0]['uri'])->toBe('http://example.org/PersonShape');
    expect($shapes[0]['target_class'])->toBe('http://example.org/Person');
    expect($shapes[0]['property_shapes'])->toHaveCount(1);
});

test('it recognizes shape by constraint parameter without explicit type (SHP-04)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:NameConstraint sh:property [
    sh:path ex:name ;
    sh:minCount 1 ;
] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    expect($shapes[0]['uri'])->toBe('http://example.org/NameConstraint');
    expect($shapes[0]['property_shapes'])->toHaveCount(1);
});

test('it does not recognize blank nodes as top-level shapes via SHP-03/04', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:age ;
        sh:datatype xsd:integer ;
        sh:minInclusive 0 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    // Only the named NodeShape should appear, not the blank node property shape
    expect($shapes)->toHaveCount(1);
    expect($shapes[0]['uri'])->toBe('http://example.org/PersonShape');
});

// ============================================================================
// Section 2.4: Multi-value Targets (TGT-03, TGT-06)
// ============================================================================

test('it extracts multiple target classes (TGT-06)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:NameShape a sh:NodeShape ;
    sh:targetClass ex:Person, ex:Organization ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    // target_class returns first value (backward compat)
    expect($shapes[0]['target_class'])->not->toBeNull();
    // target_classes returns all values
    expect($shapes[0]['target_classes'])->toBeArray();
    expect($shapes[0]['target_classes'])->toHaveCount(2);
    expect($shapes[0]['target_classes'])->toContain('http://example.org/Person');
    expect($shapes[0]['target_classes'])->toContain('http://example.org/Organization');
});

test('it extracts multiple target nodes (TGT-03)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:SpecificShape a sh:NodeShape ;
    sh:targetNode ex:Alice, ex:Bob ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    expect($shapes[0]['target_nodes'])->toBeArray();
    expect($shapes[0]['target_nodes'])->toHaveCount(2);
    expect($shapes[0]['target_nodes'])->toContain('http://example.org/Alice');
    expect($shapes[0]['target_nodes'])->toContain('http://example.org/Bob');
});

// ============================================================================
// Section 2.3.3: Deactivation (META-09)
// ============================================================================

test('it extracts sh:deactivated from shapes (META-09)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:DeactivatedShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:deactivated true ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
    ] .

ex:ActiveShape a sh:NodeShape ;
    sh:targetClass ex:Organization ;
    sh:deactivated false ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(2);

    $deactivated = collect($shapes)->firstWhere('uri', 'http://example.org/DeactivatedShape');
    expect($deactivated['deactivated'])->toBe('true');

    $active = collect($shapes)->firstWhere('uri', 'http://example.org/ActiveShape');
    expect($active['deactivated'])->toBe('false');
});

// ============================================================================
// Section 2.3.1: Custom Severity (META-05)
// ============================================================================

test('it preserves custom severity IRIs (META-05)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:CustomSeverityShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:severity ex:CustomSeverity ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    // Custom severity IRIs are preserved as-is instead of defaulting to 'violation'
    expect($shapes[0]['severity'])->toBe('http://example.org/CustomSeverity');
    expect($shapes[0]['severity_iri'])->toBe('http://example.org/CustomSeverity');
});

test('it still maps built-in severities correctly', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:WarningShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:severity sh:Warning ;
    sh:property [
        sh:path ex:name ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    expect($shapes[0]['severity'])->toBe('warning');
    expect($shapes[0]['severity_iri'])->toBe('http://www.w3.org/ns/shacl#Warning');
});

// ============================================================================
// Section 2.3.2: Multiple Messages (META-07)
// ============================================================================

test('it extracts multiple sh:message values (META-07)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:message "Name is required"@en, "Le nom est obligatoire"@fr ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    // 'message' returns first (backward compat)
    expect($shapes[0]['message'])->not->toBeNull();
    // 'messages' returns all values
    expect($shapes[0]['messages'])->toBeArray();
    expect($shapes[0]['messages'])->toHaveCount(2);
});

// ============================================================================
// Section 2.8: Non-Validating Properties (NVP-03, NVP-04, NVP-05)
// ============================================================================

test('it extracts sh:order from property shapes (NVP-03)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:firstName ;
        sh:order "1"^^xsd:decimal ;
        sh:datatype xsd:string ;
    ] ;
    sh:property [
        sh:path ex:lastName ;
        sh:order "2"^^xsd:decimal ;
        sh:datatype xsd:string ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $props = $shapes[0]['property_shapes'];
    expect($props)->toHaveCount(2);

    $firstName = collect($props)->firstWhere('path', 'http://example.org/firstName');
    $lastName = collect($props)->firstWhere('path', 'http://example.org/lastName');

    expect((float) $firstName['order'])->toBe(1.0);
    expect((float) $lastName['order'])->toBe(2.0);
});

test('it extracts sh:group from property shapes (NVP-04)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ex:PersonalInfoGroup a sh:PropertyGroup ;
    sh:order "0"^^xsd:decimal .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:firstName ;
        sh:group ex:PersonalInfoGroup ;
        sh:datatype xsd:string ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    $personShape = collect($shapes)->firstWhere('uri', 'http://example.org/PersonShape');
    expect($personShape)->not->toBeNull();
    $props = $personShape['property_shapes'];
    expect($props[0]['group'])->toBe('http://example.org/PersonalInfoGroup');
});

test('it extracts sh:defaultValue from property shapes (NVP-05)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:status ;
        sh:defaultValue "active" ;
        sh:datatype xsd:string ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $props = $shapes[0]['property_shapes'];
    expect($props[0]['defaultValue'])->toBe('active');
});

// ============================================================================
// Section 2.7: Property Paths (PTH-02 through PTH-07)
// ============================================================================

test('it extracts inverse property path (PTH-04)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path [ sh:inversePath ex:knows ] ;
        sh:minCount 1 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $props = $shapes[0]['property_shapes'];
    expect($props)->toHaveCount(1);

    $path = $props[0]['path'];
    expect($path)->toBeArray();
    expect($path['type'])->toBe('inverse');
    expect($path['path'])->toBe('http://example.org/knows');
});

test('it extracts alternative property path (PTH-03)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path [ sh:alternativePath ( ex:firstName ex:givenName ) ] ;
        sh:minCount 1 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $props = $shapes[0]['property_shapes'];
    expect($props)->toHaveCount(1);

    $path = $props[0]['path'];
    expect($path)->toBeArray();
    expect($path['type'])->toBe('alternative');
    expect($path['paths'])->toHaveCount(2);
    expect($path['paths'])->toContain('http://example.org/firstName');
    expect($path['paths'])->toContain('http://example.org/givenName');
});

test('it extracts sequence property path (PTH-02)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ( ex:address ex:city ) ;
        sh:minCount 1 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $props = $shapes[0]['property_shapes'];
    expect($props)->toHaveCount(1);

    $path = $props[0]['path'];
    expect($path)->toBeArray();
    expect($path['type'])->toBe('sequence');
    expect($path['paths'])->toHaveCount(2);
    expect($path['paths'])->toContain('http://example.org/address');
    expect($path['paths'])->toContain('http://example.org/city');
});

test('it extracts zero-or-more property path (PTH-05)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path [ sh:zeroOrMorePath ex:knows ] ;
        sh:nodeKind sh:IRI ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $path = $shapes[0]['property_shapes'][0]['path'];
    expect($path)->toBeArray();
    expect($path['type'])->toBe('zeroOrMore');
    expect($path['path'])->toBe('http://example.org/knows');
});

test('it extracts one-or-more property path (PTH-06)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path [ sh:oneOrMorePath ex:parent ] ;
        sh:nodeKind sh:IRI ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $path = $shapes[0]['property_shapes'][0]['path'];
    expect($path)->toBeArray();
    expect($path['type'])->toBe('oneOrMore');
    expect($path['path'])->toBe('http://example.org/parent');
});

test('it extracts zero-or-one property path (PTH-07)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path [ sh:zeroOrOnePath ex:middleName ] ;
        sh:maxCount 1 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $path = $shapes[0]['property_shapes'][0]['path'];
    expect($path)->toBeArray();
    expect($path['type'])->toBe('zeroOrOne');
    expect($path['path'])->toBe('http://example.org/middleName');
});

test('it still handles simple predicate paths correctly (PTH-01)', function () {
    $ttl = <<<'TTL'
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

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $path = $shapes[0]['property_shapes'][0]['path'];
    // Simple predicate path should remain a string
    expect($path)->toBeString();
    expect($path)->toBe('http://example.org/name');
});

// ============================================================================
// Section 4.7.3: sh:qualifiedValueShapesDisjoint (CC-87)
// ============================================================================

test('it extracts sh:qualifiedValueShapesDisjoint (CC-87)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:TeamShape a sh:NodeShape ;
    sh:targetClass ex:Team ;
    sh:property [
        sh:path ex:member ;
        sh:qualifiedValueShape ex:ManagerShape ;
        sh:qualifiedMinCount 1 ;
        sh:qualifiedMaxCount 3 ;
        sh:qualifiedValueShapesDisjoint true ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $props = $shapes[0]['property_shapes'];
    expect($props)->toHaveCount(1);
    expect($props[0]['qualifiedValueShapesDisjoint'])->toBe('true');
    expect($props[0]['qualifiedValueShape'])->toContain('ManagerShape');
    expect($props[0]['qualifiedMinCount'])->toBe('1');
    expect($props[0]['qualifiedMaxCount'])->toBe('3');
});

// ============================================================================
// Multiple sh:class values (CC-04)
// ============================================================================

test('it extracts multiple sh:class values on property shape (CC-04)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:employer ;
        sh:class ex:Organization, ex:LegalEntity ;
        sh:minCount 1 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $props = $shapes[0]['property_shapes'];
    // 'class' returns first value (backward compat)
    expect($props[0]['class'])->not->toBeNull();
    // 'classes' returns all values
    expect($props[0]['classes'])->toBeArray();
    expect($props[0]['classes'])->toHaveCount(2);
    expect($props[0]['classes'])->toContain('http://example.org/Organization');
    expect($props[0]['classes'])->toContain('http://example.org/LegalEntity');
});

// ============================================================================
// Multiple sh:message on property shapes
// ============================================================================

test('it extracts multiple messages on property shapes', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
        sh:message "Name is required"@en, "Naam is verplicht"@nl ;
        sh:datatype xsd:string ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $props = $shapes[0]['property_shapes'];
    expect($props[0]['messages'])->toBeArray();
    expect($props[0]['messages'])->toHaveCount(2);
});

// ============================================================================
// Deactivated property shapes
// ============================================================================

test('it extracts sh:deactivated from property shapes', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:legacyField ;
        sh:deactivated true ;
        sh:datatype xsd:string ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $props = $shapes[0]['property_shapes'];
    expect($props[0]['deactivated'])->toBe('true');
});

// ============================================================================
// Value range constraints on property shapes
// ============================================================================

test('it extracts value range constraints from property shapes', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:age ;
        sh:datatype xsd:integer ;
        sh:minInclusive 0 ;
        sh:maxInclusive 150 ;
    ] ;
    sh:property [
        sh:path ex:score ;
        sh:datatype xsd:decimal ;
        sh:minExclusive 0 ;
        sh:maxExclusive 100 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    $props = $shapes[0]['property_shapes'];

    $ageProp = collect($props)->firstWhere('path', 'http://example.org/age');
    expect($ageProp)->toHaveKey('minInclusive');
    expect($ageProp)->toHaveKey('maxInclusive');
    expect((int) $ageProp['minInclusive'])->toBe(0);
    expect((int) $ageProp['maxInclusive'])->toBe(150);

    $scoreProp = collect($props)->firstWhere('path', 'http://example.org/score');
    expect($scoreProp)->toHaveKey('minExclusive');
    expect($scoreProp)->toHaveKey('maxExclusive');
    expect((int) $scoreProp['minExclusive'])->toBe(0);
    expect((int) $scoreProp['maxExclusive'])->toBe(100);
});

// ============================================================================
// SPARQL constraint deactivation (SPC-09)
// ============================================================================

test('it extracts sh:deactivated on SPARQL constraints (SPC-09)', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:sparql [
        sh:select "SELECT $this WHERE { $this ex:age ?age . FILTER (?age < 0) }" ;
        sh:message "Age must be non-negative" ;
        sh:deactivated true ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    expect($shapes[0]['sparql_constraints'])->toBeArray();
    expect($shapes[0]['sparql_constraints'])->toHaveCount(1);
    expect($shapes[0]['sparql_constraints'][0]['deactivated'])->toBe('true');
});

// ============================================================================
// sh:qualifiedValueShapesDisjoint in constraints extraction
// ============================================================================

test('it extracts qualifiedValueShapesDisjoint from shape constraints', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:TeamShape a sh:NodeShape ;
    sh:targetClass ex:Team ;
    sh:qualifiedValueShape ex:ManagerShape ;
    sh:qualifiedMinCount 1 ;
    sh:qualifiedValueShapesDisjoint true .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    expect($shapes[0]['constraints']['qualifiedValueShapesDisjoint'])->toBe('true');
});

// ============================================================================
// Backward compatibility: existing features still work with new fields
// ============================================================================

test('it maintains backward compatibility for single target values', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:targetNode ex:Alice ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    // Old single-value fields still work
    expect($shapes[0]['target_class'])->toBe('http://example.org/Person');
    expect($shapes[0]['target_node'])->toBe('http://example.org/Alice');
    // New multi-value fields also present
    expect($shapes[0]['target_classes'])->toContain('http://example.org/Person');
    expect($shapes[0]['target_nodes'])->toContain('http://example.org/Alice');
});

test('it defaults severity to violation when not specified', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    expect($shapes[0]['severity'])->toBe('violation');
    expect($shapes[0]['severity_iri'])->toBeNull();
});

test('it sets deactivated to null when not present', function () {
    $ttl = <<<'TTL'
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
    ] .
TTL;

    $result = $this->parser->parse($ttl);
    $shapes = $result['shapes'];

    expect($shapes)->toHaveCount(1);
    expect($shapes[0]['deactivated'])->toBeNull();
});
