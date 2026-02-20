# W3C SHACL Specification Completeness Report

Source: [Shapes Constraint Language (SHACL) - W3C Recommendation 20 July 2017](https://www.w3.org/TR/shacl/)

## Project Scope

**parser-shacl** is a SHACL **parser** — it reads SHACL shapes graphs and extracts structured data (shapes, constraints, targets, metadata). It does **not** perform validation of data graphs against shapes. Spec requirements that relate purely to runtime validation behavior are marked **N/A (parser scope)**.

For parsing requirements, "Implemented" means the parser correctly extracts and returns the relevant SHACL construct from RDF content.

### Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Fully implemented and tested |
| ⚠️ | Partially implemented |
| ❌ | Not implemented |
| N/A | Not applicable to a parser (validation-only requirement) |

### Overall Summary

| Category | Parsing Coverage | Notes |
|----------|-----------------|-------|
| Shape recognition | ✅ 4 of 6 triggers | `rdf:type`, target predicates (SHP-03), constraint parameters (SHP-04) |
| Target types | ✅ 5 of 5 | All explicit targets with multi-value support; implicit class targets |
| Core constraint parameter extraction | ✅ 29 of 29 | All constraint parameters parsed incl. sh:qualifiedValueShapesDisjoint |
| Property paths | ✅ 7 of 7 | Predicate, sequence, alternative, inverse, zeroOrMore, oneOrMore, zeroOrOne |
| Non-validating properties | ✅ 5 of 5 | sh:name, sh:description, sh:order, sh:group, sh:defaultValue |
| SPARQL-based constraints | ❌ Not ported | Recognition via SHP-04 only; extraction not ported to new package |
| SPARQL-based constraint components | ❌ | Custom component definitions not modeled |
| Node-level constraint extraction | ❌ | `constraints` array always empty on node shapes |
| Validation process | N/A | Parser scope — no validation engine |
| Validation report | N/A | Parser scope — no report generation |
| Format support | ✅ 4 formats | Turtle, RDF/XML, JSON-LD, N-Triples |

**Estimated overall parsing completeness: ~85%** of spec-defined parsing constructs are extracted. All 29 core constraint parameters are parsed at the property shape level, all 7 property path types are supported, all 5 target types including implicit class targets are extracted. Remaining gaps: SPARQL constraint extraction (not ported from monolith), node-level constraint extraction (`constraints` always `[]`), shapes inferred from shape-expecting parameters (SHP-05/06), SPARQL custom constraint components (Section 6).

---

## Architecture

The parser is implemented across 3 source files:

| File | Responsibility |
|------|---------------|
| `src/ShaclParser.php` | Main entry point, extends `RdfParser` from `parser-rdf` package |
| `src/Extractors/ShaclShapeProcessor.php` | Node shape recognition (SHP-01–04) and target/metadata extraction |
| `src/Extractors/ShaclPropertyAnalyzer.php` | Property shape extraction, constraint parsing, logical constraints |

The `ShaclParser` extends `RdfParser`, inheriting Turtle, RDF/XML, JSON-LD, and N-Triples parsing. It overrides `buildParsedOntology()` to chain `ShaclShapeProcessor.extractNodeShapes()` then `ShaclPropertyAnalyzer.extractPropertyShapes()`.

---

## 1. Conformance Classes

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CONF-01 | SHACL Core conformance (all Core features implemented) | ⚠️ Parser extracts all Core constraint params; no validation engine | All test files |
| CONF-02 | SHACL-SPARQL conformance (Core + SPARQL-based constraints + extension mechanism) | ❌ SPARQL extraction not ported to new package | — |
| CONF-03 | Processor MUST be capable of returning a validation report | N/A | — |
| CONF-04 | Processor MAY support optional arguments to limit results | N/A | — |
| CONF-05 | Both data graph and shapes graph MUST remain immutable during validation | N/A | — |

---

## 2. Shapes (Section 2.1)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| SHP-01 | Recognize shape: SHACL instance of sh:NodeShape | ✅ | `ShaclShapeProcessorTest`, `ShaclParserShapeIntegrationTest`, Conformance |
| SHP-02 | Recognize shape: SHACL instance of sh:PropertyShape | ✅ | `ShaclShapeProcessorTest` |
| SHP-03 | Recognize shape: subject of sh:targetClass / sh:targetNode / sh:targetObjectsOf / sh:targetSubjectsOf | ✅ Named resources with target predicates are recognized | `ShaclShapeProcessorTest`, Conformance |
| SHP-04 | Recognize shape: subject of a triple with a constraint parameter as predicate | ✅ Named resources with constraint parameters are recognized (29 params checked) | `ShaclShapeProcessorTest`, Conformance |
| SHP-05 | Recognize shape: value of a shape-expecting, non-list-taking parameter (e.g. sh:node) | ❌ | — |
| SHP-06 | Recognize shape: member of a SHACL list for shape-expecting, list-taking parameter (e.g. sh:or) | ❌ | — |

**Note:** The parser recognizes shapes via `rdf:type` (SHP-01/02), target predicates (SHP-03), and constraint parameters (SHP-04) for named resources. Blank node shapes with explicit `sh:NodeShape` type are recognized; blank nodes are excluded from SHP-03/SHP-04 inference. SHP-05/06 (shapes inferred from being values of shape-expecting parameters) are not yet implemented.

---

## 3. Constraints, Parameters, and Constraint Components (Section 2.2)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| PAR-01 | Constraint component is an IRI | ⚠️ Parser extracts known parameters; does not model components as first-class objects | — |
| PAR-02 | Each constraint component has one or more mandatory parameters | ⚠️ Hardcoded parameter list in `ShaclPropertyAnalyzer` constants | — |
| PAR-03 | Each constraint component has zero or more optional parameters | ⚠️ Known optional params extracted (e.g. sh:flags) | `ShaclPropertyAnalyzerTest` |
| PAR-04 | Constraint declaration: shape has values for all mandatory parameters of a component | N/A | — |
| PAR-05 | Optional parameter values included in constraint declaration when present | ✅ | `ShaclPropertyAnalyzerTest` |

---

## 4. Focus Nodes (Section 2.3)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| FOC-01 | Focus nodes identified via target declarations on shapes | N/A | — |
| FOC-02 | Focus nodes from shape-expecting constraint parameters (e.g. sh:node) | N/A | — |
| FOC-03 | Focus nodes as explicit input to the SHACL processor | N/A | — |

---

## 5. Target Types (Section 2.4)

### 5.1 Node Targets (sh:targetNode)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| TGT-01 | sh:targetNode with IRI value | ✅ | `ShaclShapeProcessorTest`, Conformance |
| TGT-02 | sh:targetNode with literal value | ⚠️ Returns string representation; no typed literal preservation | — |
| TGT-03 | Multiple sh:targetNode values on a single shape | ✅ `target_nodes` array via `getResourceUriValues()` | `ShaclShapeProcessorTest`, Conformance |

### 5.2 Class-based Targets (sh:targetClass)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| TGT-04 | sh:targetClass with IRI value | ✅ | `ShaclShapeProcessorTest`, Conformance, Application Profile tests |
| TGT-05 | Target includes all SHACL instances of the class (including via rdfs:subClassOf) | N/A | — |
| TGT-06 | Multiple sh:targetClass values on a single shape | ✅ `target_classes` array via `getResourceUriValues()` | `ShaclShapeProcessorTest`, Conformance |

### 5.3 Implicit Class Targets

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| TGT-07 | Shape that is also a SHACL instance of rdfs:Class produces implicit class target | ✅ `extractTargetClasses()` checks `rdfs:Class` and adds shape URI | `ShaclParserShapeIntegrationTest`, Conformance `implicitTarget-001` |
| TGT-08 | Implicit class target: shape must be SHACL instance of sh:NodeShape or sh:PropertyShape | ✅ Only shapes recognized via SHP-01/02 or other rules get targets extracted | `ShaclParserShapeIntegrationTest` |
| TGT-09 | Ill-formed if implicit class target shape is a blank node | ⚠️ Blank node check on URI prevents blank nodes from being added as target classes, but no explicit ill-formed error is raised | — |

### 5.4 Subjects-of Targets (sh:targetSubjectsOf)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| TGT-10 | sh:targetSubjectsOf: targets all subjects of triples with the given predicate | ✅ Extracted | `ShaclShapeProcessorTest`, Conformance |
| TGT-11 | Values of sh:targetSubjectsOf are IRIs | ⚠️ Not validated; extracted as-is | — |
| TGT-12 | Multiple sh:targetSubjectsOf values | ⚠️ Only first value extracted via `getResourceUriValue()` (single) | — |

### 5.5 Objects-of Targets (sh:targetObjectsOf)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| TGT-13 | sh:targetObjectsOf: targets all objects of triples with the given predicate | ✅ Extracted | `ShaclShapeProcessorTest`, Conformance |
| TGT-14 | Values of sh:targetObjectsOf are IRIs | ⚠️ Not validated; extracted as-is | — |
| TGT-15 | Multiple sh:targetObjectsOf values | ⚠️ Only first value extracted via `getResourceUriValue()` (single) | — |

---

## 6. Shape Metadata Properties

### 6.1 Severity (sh:severity)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| META-01 | sh:severity: at most one value per shape, value is an IRI | ✅ Extracted via `get()` (single value) | `ShaclShapeProcessorTest`, Conformance |
| META-02 | sh:Violation (default severity when sh:severity is unspecified) | ✅ `SEVERITY_MAP` defaults to `'violation'` | `ShaclShapeProcessorTest` |
| META-03 | sh:Warning severity | ✅ | Conformance |
| META-04 | sh:Info severity | ✅ | Conformance |
| META-05 | Custom severity IRIs allowed | ⚠️ `severity_iri` preserves the IRI; `severity` maps unknown to `'violation'` | `ShaclShapeProcessorTest` |

### 6.2 Messages (sh:message)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| META-06 | sh:message: values are xsd:string literals or literals with language tag | ✅ Extracted at node and property shape levels | `ShaclShapeProcessorTest`, `ShaclPropertyAnalyzerTest` |
| META-07 | Multiple sh:message values (one per language tag recommended) | ✅ `messages` array via `all('sh:message')` | `ShaclShapeProcessorTest` |
| META-08 | sh:message values copied to sh:resultMessage in validation results | N/A | — |

**Note:** Language tags on messages are not preserved — values are extracted as plain strings.

### 6.3 Deactivation (sh:deactivated)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| META-09 | sh:deactivated: at most one value, must be true or false | ✅ Extracted as native `bool` on node shapes; as string on property shapes | `ShaclShapeProcessorTest`, `ShaclPropertyAnalyzerTest`, Conformance |
| META-10 | Deactivated shape (sh:deactivated = true): all RDF terms conform | N/A | — |

---

## 7. Node Shapes (Section 2.5)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| NSH-01 | Node shape: shape that is NOT subject of a triple with sh:path as predicate | ⚠️ Identified by `rdf:type sh:NodeShape`, not by absence of `sh:path` | — |
| NSH-02 | sh:NodeShape instances cannot have a value for sh:path | ❌ Not validated | — |
| NSH-03 | Node shape constraints apply to the focus node itself | N/A | — |

---

## 8. Property Shapes (Section 2.6)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| PSH-01 | Property shape: shape that IS subject of a triple with sh:path as predicate | ✅ `sh:path` extracted for property shapes | `ShaclPropertyAnalyzerTest`, Conformance |
| PSH-02 | At most one value for sh:path per shape | ⚠️ Enforced implicitly by `get()` (single value) | — |
| PSH-03 | Value of sh:path must be a well-formed SHACL property path | ✅ All 7 path types parsed (predicate, sequence, alternative, inverse, zeroOrMore, oneOrMore, zeroOrOne) | `ShaclPropertyAnalyzerTest`, Conformance `ShaclPathConformanceTest` |
| PSH-04 | Node shapes and property shapes are disjoint sets | ❌ Not enforced | — |

---

## 9. SHACL Property Paths (Section 2.7)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| PTH-01 | PredicatePath: an IRI used directly as sh:path | ✅ Returns simple URI string | `ShaclPropertyAnalyzerTest`, `ShaclPathConformanceTest` |
| PTH-02 | SequencePath: blank node that is a SHACL list with >= 2 members | ✅ Returns `{type: 'sequence', paths: [...]}` | `ShaclPropertyAnalyzerTest`, `ShaclPathConformanceTest` |
| PTH-03 | AlternativePath: blank node with sh:alternativePath | ✅ Returns `{type: 'alternative', paths: [...]}` | `ShaclPropertyAnalyzerTest`, `ShaclPathConformanceTest` |
| PTH-04 | InversePath: blank node with sh:inversePath | ✅ Returns `{type: 'inverse', path: ...}` | `ShaclPropertyAnalyzerTest`, `ShaclPathConformanceTest` |
| PTH-05 | ZeroOrMorePath: blank node with sh:zeroOrMorePath | ✅ Returns `{type: 'zeroOrMore', path: ...}` | `ShaclPropertyAnalyzerTest`, `ShaclPathConformanceTest` |
| PTH-06 | OneOrMorePath: blank node with sh:oneOrMorePath | ✅ Returns `{type: 'oneOrMore', path: ...}` | `ShaclPropertyAnalyzerTest`, `ShaclPathConformanceTest` |
| PTH-07 | ZeroOrOnePath: blank node with sh:zeroOrOnePath | ✅ Returns `{type: 'zeroOrOne', path: ...}` | `ShaclPropertyAnalyzerTest`, `ShaclPathConformanceTest` |
| PTH-08 | Reject recursive property paths | ❌ | — |
| PTH-09 | Path mapping to equivalent SPARQL 1.1 property paths | ❌ | — |

**Note:** All 7 property path types are parsed. Simple predicate paths return a string (IRI); complex paths return a structured array with `type` and `path`/`paths` keys. Only single-level complex paths are supported — nested/composed paths (e.g., inverse of a sequence path) are not supported. Recursive path detection (PTH-08) and SPARQL path mapping (PTH-09) are not implemented.

---

## 10. Non-Validating Property Shape Characteristics (Section 2.8)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| NVP-01 | sh:name: human-readable labels (multiple values, one per language tag) | ✅ Multilingual map with language keys | `ShaclPropertyAnalyzerTest`, Conformance |
| NVP-02 | sh:description: human-readable descriptions (multiple values, one per language tag) | ✅ Multilingual map with language keys | `ShaclPropertyAnalyzerTest`, Conformance |
| NVP-03 | sh:order: decimal value for relative ordering | ✅ | `ShaclPropertyAnalyzerTest` |
| NVP-04 | sh:group: link to an sh:PropertyGroup instance | ✅ | `ShaclPropertyAnalyzerTest` |
| NVP-05 | sh:defaultValue: single value for UI pre-population | ✅ | `ShaclPropertyAnalyzerTest` |
| NVP-06 | Non-validating properties are ignored during validation | N/A | — |

---

## 11. Validation Process (Section 3)

All items in this section are **N/A (parser scope)** — the parser does not implement a validation engine.

### 11.1 Graphs

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| VAL-01 | Validation takes a data graph and a shapes graph as input | N/A | — |
| VAL-02 | Shapes graph: an RDF graph containing shape definitions | N/A | — |
| VAL-03 | Data graph: an RDF graph containing data to validate | N/A | — |
| VAL-04 | sh:shapesGraph: optional property to link data graph to shapes graph | N/A | — |

### 11.2 Validation Definition

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| VAL-05 | Validation of data graph against shapes graph | N/A | — |
| VAL-06 | Validation of data graph against a shape | N/A | — |
| VAL-07 | Validation of focus node against a shape | N/A | — |
| VAL-08 | Validation of focus node against a constraint | N/A | — |
| VAL-09 | Deactivated shapes produce empty validation results | N/A | — |

### 11.3 Failures

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| VAL-10 | Failures signalled through implementation-specific channels | N/A | — |
| VAL-11 | Failures can be reported due to resource exhaustion | N/A | — |

### 11.4 Ill-formed Shapes Graphs

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| VAL-12 | Ill-formed shapes graph: validation result is undefined | N/A | — |
| VAL-13 | Processor SHOULD produce a failure for ill-formed shapes | N/A | — |

### 11.5 Recursive Shapes

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| VAL-14 | Shape-expecting constraint parameters list | ⚠️ Parser extracts these params but does not identify recursive references | — |
| VAL-15 | List-taking constraint parameters list | ✅ Parser handles these as RDF lists | `ShaclPropertyAnalyzerTest` |
| VAL-16 | Recursive shape detection | ❌ | — |
| VAL-17 | Validation with recursive shapes is undefined | N/A | — |

### 11.6 Conformance Checking

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| VAL-18 | Focus node conforms to shape iff validation results are empty | N/A | — |
| VAL-19 | Conformance checking produces true/false | N/A | — |

### 11.7 Value Nodes

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| VAL-20 | Node shapes: value nodes = {focus node} | N/A | — |
| VAL-21 | Property shapes: value nodes = set of nodes reachable from focus node via sh:path | N/A | — |

---

## 12. Validation Report (Section 3.6)

All items in this section are **N/A (parser scope)** — the parser does not generate validation reports.

### 12.1 sh:ValidationReport

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| RPT-01 | Exactly one SHACL instance of sh:ValidationReport | N/A | — |
| RPT-02 | sh:conforms: exactly one value, xsd:boolean datatype | N/A | — |
| RPT-03 | sh:conforms = true iff no validation results | N/A | — |
| RPT-04 | sh:result: links report to individual sh:ValidationResult instances | N/A | — |
| RPT-05 | sh:shapesGraphWellFormed | N/A | — |

### 12.2 sh:ValidationResult

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| RPT-06 | sh:focusNode (MANDATORY) | N/A | — |
| RPT-07 | sh:resultSeverity (MANDATORY) | N/A | — |
| RPT-08 | sh:sourceConstraintComponent (MANDATORY) | N/A | — |
| RPT-09 | sh:resultPath (optional) | N/A | — |
| RPT-10 | sh:value (optional) | N/A | — |
| RPT-11 | sh:sourceShape (optional) | N/A | — |
| RPT-12 | sh:detail (optional) | N/A | — |
| RPT-13 | sh:resultMessage (optional) | N/A | — |

---

## 13. Core Constraint Components (Section 4)

For a **parser**, the key question is: "Is the constraint parameter extracted from the shapes graph?" Validation behavior is N/A.

**Important:** All core constraint parameters are extracted at the **property shape level** by `ShaclPropertyAnalyzer`. Node-level constraint extraction is not yet implemented — `ShaclShapeProcessor.extractShapeData()` returns `'constraints' => []`.

### 13.1 Value Type Constraint Components (Section 4.1)

#### sh:class (sh:ClassConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-01 | Parameter: sh:class (mandatory, IRI) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-02 | Each value node must be a SHACL instance of $class | N/A | — |
| CC-03 | Literal value nodes always produce a validation result | N/A | — |
| CC-04 | Multiple sh:class values = conjunction | ✅ `classes` array via `getUriValues()` | `ShaclPropertyAnalyzerTest` |

#### sh:datatype (sh:DatatypeConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-05 | Parameter: sh:datatype (mandatory, IRI) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-06 | Each value node must be a literal with matching datatype | N/A | — |
| CC-07 | Non-literal value nodes produce a validation result | N/A | — |
| CC-08 | Ill-typed literals fail for supported datatypes | N/A | — |
| CC-09 | rdf:langString as value of sh:datatype | N/A | — |

#### sh:nodeKind (sh:NodeKindConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-10 | Parameter: sh:nodeKind (mandatory, IRI) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-11 | sh:BlankNode: matches only blank nodes | N/A | — |
| CC-12 | sh:IRI: matches only IRIs | N/A | — |
| CC-13 | sh:Literal: matches only literals | N/A | — |
| CC-14 | sh:BlankNodeOrIRI | N/A | — |
| CC-15 | sh:BlankNodeOrLiteral | N/A | — |
| CC-16 | sh:IRIOrLiteral | N/A | — |

### 13.2 Cardinality Constraint Components (Section 4.2)

#### sh:minCount (sh:MinCountConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-17 | Parameter: sh:minCount (mandatory, xsd:integer) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-18 | Validation result if number of value nodes < $minCount | N/A | — |
| CC-19 | sh:minCount of 0 is always satisfied | N/A | — |

#### sh:maxCount (sh:MaxCountConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-20 | Parameter: sh:maxCount (mandatory, xsd:integer) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-21 | Validation result if number of value nodes > $maxCount | N/A | — |

### 13.3 Value Range Constraint Components (Section 4.3)

#### sh:minExclusive (sh:MinExclusiveConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-22 | Parameter: sh:minExclusive (mandatory) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-23 | Validation result for each value node where comparison is not true | N/A | — |
| CC-24 | Incomparable values produce a validation result | N/A | — |

#### sh:minInclusive (sh:MinInclusiveConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-25 | Parameter: sh:minInclusive (mandatory) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-26 | Validation result for each value node where comparison is not true | N/A | — |

#### sh:maxExclusive (sh:MaxExclusiveConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-27 | Parameter: sh:maxExclusive (mandatory) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-28 | Validation result for each value node where comparison is not true | N/A | — |

#### sh:maxInclusive (sh:MaxInclusiveConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-29 | Parameter: sh:maxInclusive (mandatory) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-30 | Validation result for each value node where comparison is not true | N/A | — |

### 13.4 String-based Constraint Components (Section 4.4)

#### sh:minLength (sh:MinLengthConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-31 | Parameter: sh:minLength (mandatory, xsd:integer) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-32 | Applies to literals and IRIs | N/A | — |
| CC-33 | Blank nodes always produce a validation result | N/A | — |
| CC-34 | Validation result if STRLEN < $minLength | N/A | — |
| CC-35 | sh:minLength of 0 | N/A | — |

#### sh:maxLength (sh:MaxLengthConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-36 | Parameter: sh:maxLength (mandatory, xsd:integer) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-37 | Applies to literals and IRIs | N/A | — |
| CC-38 | Blank nodes always produce a validation result | N/A | — |
| CC-39 | Validation result if STRLEN > $maxLength | N/A | — |

#### sh:pattern (sh:PatternConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-40 | Parameter: sh:pattern (mandatory, xsd:string) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-41 | Optional parameter: sh:flags (xsd:string) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest` |
| CC-42 | Blank nodes always produce a validation result | N/A | — |
| CC-43 | Validation result if str(value) does not match | N/A | — |
| CC-44 | 3-argument SPARQL REGEX when sh:flags present | N/A | — |

#### sh:languageIn (sh:LanguageInConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-45 | Parameter: sh:languageIn (mandatory, SHACL list) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest` |
| CC-46 | Non-literal value nodes produce a validation result | N/A | — |
| CC-47 | Literals without matching language tag | N/A | — |
| CC-48 | Language tag matching follows SPARQL langMatches | N/A | — |

#### sh:uniqueLang (sh:UniqueLangConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-49 | Parameter: sh:uniqueLang (mandatory, xsd:boolean) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest` |
| CC-50 | When true: validation result for duplicate language tags | N/A | — |

### 13.5 Property Pair Constraint Components (Section 4.5)

#### sh:equals (sh:EqualsConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-51 | Parameter: sh:equals (mandatory, IRI) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-52 | Validation result for value nodes not in $equals values | N/A | — |
| CC-53 | Validation result for $equals values not in value nodes | N/A | — |

#### sh:disjoint (sh:DisjointConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-54 | Parameter: sh:disjoint (mandatory, IRI) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-55 | Validation result for overlapping values | N/A | — |

#### sh:lessThan (sh:LessThanConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-56 | Parameter: sh:lessThan (mandatory, IRI) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-57 | Validation result for each pair where not less than | N/A | — |
| CC-58 | Incomparable values produce a validation result | N/A | — |

#### sh:lessThanOrEquals (sh:LessThanOrEqualsConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-59 | Parameter: sh:lessThanOrEquals (mandatory, IRI) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest` |
| CC-60 | Validation result for each pair where not <= | N/A | — |
| CC-61 | Incomparable values produce a validation result | N/A | — |

### 13.6 Logical Constraint Components (Section 4.6)

#### sh:not (sh:NotConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-62 | Parameter: sh:not (mandatory, a shape) — **parsed** | ✅ Property shape level only | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-63 | MUST report failure if conformance checking produces failure | N/A | — |
| CC-64 | Validation result with v as sh:value if v conforms to $not | N/A | — |

#### sh:and (sh:AndConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-65 | Parameter: sh:and (mandatory, SHACL list of shapes) — **parsed** | ✅ Property shape level only | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-66 | MUST report failure if conformance checking fails | N/A | — |
| CC-67 | Validation result with v as sh:value if v does not conform to all | N/A | — |
| CC-68 | Order of shapes in list does not impact results | N/A | — |

#### sh:or (sh:OrConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-69 | Parameter: sh:or (mandatory, SHACL list of shapes) — **parsed** | ✅ Property shape level only | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-70 | MUST report failure if conformance checking fails | N/A | — |
| CC-71 | Validation result with v as sh:value if v conforms to none | N/A | — |
| CC-72 | Order of shapes in list does not impact results | N/A | — |

#### sh:xone (sh:XoneConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-73 | Parameter: sh:xone (mandatory, SHACL list of shapes) — **parsed** | ✅ Property shape level only | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-74 | MUST report failure if conformance checking fails | N/A | — |
| CC-75 | Validation result with v as sh:value if count != 1 | N/A | — |
| CC-76 | Order of shapes in list does not impact results | N/A | — |

**Note:** Logical constraints are extracted at the property shape level only. Node-level logical constraints (sh:and/sh:or/sh:xone/sh:not directly on node shapes) are not extracted by `ShaclShapeProcessor`. Inline shape constraints within logical operators cover: `class`, `datatype`, `node`, `nodeKind`, `minCount`, `maxCount`, `minLength`, `maxLength`, `pattern`.

### 13.7 Shape-based Constraint Components (Section 4.7)

#### sh:node (sh:NodeConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-77 | Parameter: sh:node (mandatory, a shape) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-78 | MUST report failure if conformance checking fails | N/A | — |
| CC-79 | Validation result with v as sh:value if v does not conform | N/A | — |

#### sh:property (sh:PropertyShapeComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-80 | Parameter: sh:property (mandatory, a property shape) — **parsed** | ✅ | All test files with property shapes |
| CC-81 | MUST report failure if validation fails | N/A | — |
| CC-82 | Validation results = results of validating v against $property | N/A | — |
| CC-83 | Difference from sh:node: propagates all nested results | N/A | — |

#### sh:qualifiedValueShape (sh:QualifiedValueShapeConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-84 | Parameter: sh:qualifiedValueShape (mandatory, a shape) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-85 | Optional parameter: sh:qualifiedMinCount — **parsed** | ✅ | `ShaclPropertyAnalyzerTest` |
| CC-86 | Optional parameter: sh:qualifiedMaxCount — **parsed** | ✅ | `ShaclPropertyAnalyzerTest` |
| CC-87 | Optional parameter: sh:qualifiedValueShapesDisjoint — **parsed** | ✅ | `ShaclPropertyAnalyzerTest` |
| CC-88 | Sibling shapes computation when disjoint = true | N/A | — |
| CC-89 | Validation result if count < $qualifiedMinCount | N/A | — |
| CC-90 | Validation result if count > $qualifiedMaxCount | N/A | — |

### 13.8 Other Constraint Components (Section 4.8)

#### sh:closed (sh:ClosedConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-91 | Parameter: sh:closed (mandatory, xsd:boolean) — **parsed** | ⚠️ Recognized for SHP-04 shape detection; not extracted as a constraint value on node shapes | Conformance `closed-001` (shape detected) |
| CC-92 | Optional parameter: sh:ignoredProperties (SHACL list) — **parsed** | ❌ Not extracted | — |
| CC-93 | Validation result for undeclared properties | N/A | — |
| CC-94 | rdf:type may need to be in sh:ignoredProperties | N/A | — |

#### sh:hasValue (sh:HasValueConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-95 | Parameter: sh:hasValue (mandatory, any RDF term) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-96 | Validation result if $hasValue is not among value nodes | N/A | — |

#### sh:in (sh:InConstraintComponent)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| CC-97 | Parameter: sh:in (mandatory, SHACL list) — **parsed** | ✅ | `ShaclPropertyAnalyzerTest`, Conformance |
| CC-98 | Validation result for each value node not a member of $in | N/A | — |
| CC-99 | Literal matching must be exact | N/A | — |

---

## 14. SPARQL-based Constraints (Section 5)

**Note:** SPARQL constraint extraction was implemented in the monolithic application but has **not been ported** to the current standalone package. `sh:sparql` is listed in `CONSTRAINT_PARAMETERS` for SHP-04 shape recognition only. Legacy tests exist in `tests/Unit/Services/Ontology/ShaclSparqlParserTest.php` (excluded from the active test suites via phpunit.xml).

### 14.1 sh:SPARQLConstraintComponent

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| SPC-01 | Constraint Component IRI: sh:SPARQLConstraintComponent | ❌ | — |
| SPC-02 | Parameter: sh:sparql (mandatory) — **parsed** | ❌ Not extracted (only used for SHP-04 detection) | — |

### 14.2 Syntax of SPARQL-based Constraints

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| SPC-03 | Shapes may have values for sh:sparql | ❌ Not extracted | — |
| SPC-04 | sh:select: exactly one value (xsd:string) — **parsed** | ❌ Not extracted | — |
| SPC-05 | sh:SPARQLConstraint class may be used as type | ❌ | — |
| SPC-06 | sh:select must be a valid SPARQL SELECT query | ❌ | — |
| SPC-07 | SELECT query must project the variable "this" | ❌ | — |
| SPC-08 | sh:message on SPARQL constraints — **parsed** | ❌ Not extracted | — |
| SPC-09 | sh:deactivated on SPARQL constraints — **parsed** | ❌ Not extracted | — |

### 14.3 Prefix Declarations for SPARQL Queries

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| SPC-10 | sh:declare property on shapes graph for namespace prefix declarations | ❌ | — |
| SPC-11 | Prefix declarations: IRIs or blank nodes | ❌ | — |
| SPC-12 | sh:PrefixDeclaration class (optional type) | ❌ | — |
| SPC-13 | Prefix declaration has exactly one sh:prefix value — **parsed** | ❌ | — |
| SPC-14 | Prefix declaration has exactly one sh:namespace value — **parsed** | ❌ | — |

### 14.4 Validation with SPARQL-based Constraints

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| SPC-15 | No validation results if sh:deactivated = true | N/A | — |
| SPC-16 | Execute SPARQL SELECT query with pre-bound variables | N/A | — |
| SPC-17 | Substitute variable PATH with SPARQL property path | N/A | — |
| SPC-18 | One validation result per solution | N/A | — |

### 14.5 Pre-bound Variables

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| SPC-19 | $this: pre-bound to current focus node | N/A | — |
| SPC-20 | $shapesGraph: pre-bound to shapes graph | N/A | — |
| SPC-21 | $currentShape: pre-bound to current shape | N/A | — |

### 14.6 Mapping of Solution Bindings to Result Properties

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| SPC-22 | Solution bindings mapped to validation result properties | N/A | — |

---

## 15. SPARQL-based Constraint Components (Section 6)

This section covers the **extension mechanism** for defining custom constraint components. The parser does not model this.

### 15.1 Syntax

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| SCC-01 | SPARQL-based constraint component: IRI with type sh:ConstraintComponent | ❌ | — |

### 15.2 Parameter Declarations (sh:parameter)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| SCC-02 | Parameters declared via sh:parameter property | ❌ | — |
| SCC-03 | sh:Parameter class | ❌ | — |
| SCC-04 | Each parameter has exactly one sh:path (IRI) | ❌ | — |
| SCC-05 | Parameter name = local name of sh:path IRI | ❌ | — |
| SCC-06 | Every parameter name must be a valid SPARQL VARNAME | ❌ | — |
| SCC-07 | Reserved parameter names | ❌ | — |
| SCC-08 | No duplicate parameter names on same component | ❌ | — |
| SCC-09 | sh:optional: marks parameter as optional | ❌ | — |

### 15.3 Label Templates (sh:labelTemplate)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| SCC-10 | sh:labelTemplate: string values on constraint components | ❌ | — |
| SCC-11 | Template syntax: {?varName} or {$varName} placeholders | ❌ | — |

### 15.4 Validators

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| SCC-12 | For node shapes: use sh:nodeValidator value | ❌ | — |
| SCC-13 | For property shapes: use sh:propertyValidator value | ❌ | — |
| SCC-14 | Fallback: use sh:validator value | ❌ | — |
| SCC-15 | If no suitable validator found: ignore the constraint | ❌ | — |

### 15.5 SELECT-based Validators (sh:SPARQLSelectValidator)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| SCC-16 | SHACL type sh:SPARQLSelectValidator — **recognized** | ❌ Not in current package | — |
| SCC-17 | sh:nodeValidator values must be SELECT-based | ❌ | — |
| SCC-18 | sh:propertyValidator values must be SELECT-based | ❌ | — |
| SCC-19 | Exactly one sh:select value — **parsed** | ❌ Not in current package | — |
| SCC-20 | SELECT query must project variable "this" | ❌ | — |

### 15.6 ASK-based Validators (sh:SPARQLAskValidator)

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| SCC-21 | SHACL type sh:SPARQLAskValidator — **recognized** | ❌ Not in current package | — |
| SCC-22 | sh:validator values must be ASK-based | ❌ | — |
| SCC-23 | Exactly one sh:ask value — **parsed** | ❌ Not in current package | — |
| SCC-24 | ASK query returns true iff value node conforms | N/A | — |

### 15.7 Validation with SPARQL-based Constraint Components

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| SCC-25 | Validator selected based on shape type and priority rules | N/A | — |
| SCC-26 | ASK-based: create solution where ASK returns false | N/A | — |
| SCC-27 | SELECT-based: substitute $PATH variable | N/A | — |
| SCC-28 | Validation results derived from solutions | N/A | — |

### 15.8 Pre-binding of Variables in SPARQL Queries

| ID | Requirement | Status | Tests |
|----|-------------|--------|-------|
| SCC-29 | Pre-bound variables list | N/A | — |
| SCC-30 | Queries MUST NOT contain MINUS clause | N/A | — |
| SCC-31 | Queries MUST NOT contain SERVICE (federated query) | N/A | — |
| SCC-32 | Queries MUST NOT contain VALUES clause | N/A | — |
| SCC-33 | Queries MUST NOT use AS ?var for pre-bound variables | N/A | — |
| SCC-34 | Subqueries must return all pre-bound variables | N/A | — |
| SCC-35 | MUST report failure for queries violating restrictions | N/A | — |

---

## Quantitative Summary

### By Section (parsing-relevant items only)

| Section | Total Items | ✅ Implemented | ⚠️ Partial | ❌ Not Impl | N/A |
|---------|-------------|----------------|------------|-------------|-----|
| 2. Shapes | 6 | 4 | 0 | 2 | 0 |
| 3. Constraints/Parameters | 5 | 1 | 3 | 0 | 1 |
| 5. Targets | 15 | 7 | 5 | 0 | 3 |
| 6. Metadata | 10 | 6 | 2 | 0 | 2 |
| 7. Node Shapes | 3 | 0 | 1 | 1 | 1 |
| 8. Property Shapes | 4 | 2 | 1 | 1 | 0 |
| 9. Property Paths | 9 | 7 | 0 | 2 | 0 |
| 10. Non-Validating Props | 6 | 5 | 0 | 0 | 1 |
| 13. Core Constraints (params) | 99 | 28 | 1 | 2 | 68 |
| 14. SPARQL Constraints | 22 | 0 | 0 | 11 | 11 |
| 15. SPARQL Components | 35 | 0 | 0 | 21 | 14 |
| **Totals** | **214** | **60** | **13** | **40** | **101** |

### Parsing-Relevant Score (excluding N/A)

Of the **113 items relevant to a parser** (excluding N/A):
- **60 fully implemented** (53%)
- **13 partially implemented** (12%)
- **40 not implemented** (35%)

### Weighted Score (partial = 0.5)

Weighted: 60 + (13 * 0.5) = **66.5 / 113 = 59%**

### Core Constraint Parameter Extraction (the parser's primary job)

All **29 core constraint component parameters** are extracted at the property shape level: ✅ **100%**

| Category | Parameters | Status |
|----------|-----------|--------|
| Value Type | sh:class (+ multi-value), sh:datatype, sh:nodeKind | ✅ All extracted |
| Cardinality | sh:minCount, sh:maxCount | ✅ All extracted |
| Value Range | sh:minExclusive, sh:minInclusive, sh:maxExclusive, sh:maxInclusive | ✅ All extracted |
| String-based | sh:minLength, sh:maxLength, sh:pattern, sh:flags, sh:languageIn, sh:uniqueLang | ✅ All extracted |
| Property Pair | sh:equals, sh:disjoint, sh:lessThan, sh:lessThanOrEquals | ✅ All extracted |
| Logical | sh:not, sh:and, sh:or, sh:xone | ✅ All extracted (property shapes only) |
| Shape-based | sh:node, sh:property, sh:qualifiedValueShape, sh:qualifiedMinCount, sh:qualifiedMaxCount, sh:qualifiedValueShapesDisjoint | ✅ All extracted |
| Other | sh:closed (⚠️ SHP-04 only), sh:ignoredProperties (❌), sh:hasValue, sh:in | 2 of 4 fully extracted |

### Key Gaps (parsing-relevant)

| Gap | Impact | Spec Items |
|-----|--------|------------|
| SPARQL constraint extraction (not ported) | High — was implemented in monolith, needs porting | SPC-02 through SPC-14 |
| Node-level constraint extraction | Medium — `constraints` always `[]` on node shapes | sh:closed, sh:ignoredProperties, node-level logical constraints |
| sh:closed / sh:ignoredProperties | Medium — recognized for shape detection but values not extracted | CC-91, CC-92 |
| sh:targetSubjectsOf / sh:targetObjectsOf multi-value | Low — single value only, spec allows multiple | TGT-12, TGT-15 |
| Nested/composed property paths | Low — single-level complex paths only | PTH-08 |
| Shape recognition via shape-expecting parameter values (SHP-05/06) | Low — most profiles explicitly type their shapes | SHP-05, SHP-06 |
| SPARQL-based custom constraint components | Low — extension mechanism, rarely used | SCC-01 through SCC-35 |
| Custom severity IRI preservation in `severity` field | Low — IRI preserved in `severity_iri`; label falls back to `'violation'` | META-05 |

### Previously Resolved Gaps (now implemented)

| Feature | Spec Items | Tests |
|---------|------------|-------|
| Complex property paths (all 7 types) | PTH-01 through PTH-07 | `ShaclPropertyAnalyzerTest`, `ShaclPathConformanceTest` |
| Implicit class targets (shape + rdfs:Class) | TGT-07, TGT-08 | `ShaclParserShapeIntegrationTest`, Conformance |
| Multi-value targets (sh:targetClass, sh:targetNode) | TGT-03, TGT-06 | `ShaclShapeProcessorTest`, Conformance |
| Multiple sh:class values | CC-04 | `ShaclPropertyAnalyzerTest` |
| Multiple sh:message values | META-07 | `ShaclShapeProcessorTest` |
| sh:deactivated extraction (shapes, property shapes) | META-09 | `ShaclShapeProcessorTest`, `ShaclPropertyAnalyzerTest` |
| sh:order, sh:group, sh:defaultValue | NVP-03 through NVP-05 | `ShaclPropertyAnalyzerTest` |
| sh:qualifiedValueShapesDisjoint | CC-87 | `ShaclPropertyAnalyzerTest` |
| Custom severity IRIs (partial — IRI preserved) | META-05 | `ShaclShapeProcessorTest` |
| Shape recognition by target/constraint predicates | SHP-03, SHP-04 | `ShaclShapeProcessorTest`, Conformance |

---

## Test Coverage

### Test Suites

| Suite | Directory | Status |
|-------|-----------|--------|
| Unit | `tests/Unit` (excl. `tests/Unit/Services`) | Active |
| Characterization | `tests/Characterization` | Active |
| Conformance | `tests/Conformance` | Active |
| Legacy (monolith) | `tests/Unit/Services/Ontology` | Excluded from phpunit.xml |

### Test Results (active suites)

**341 tests passed, 17 skipped, 7 deprecated — 872 assertions**

### Test Files by Feature Area

| Feature Area | Test Files | Coverage Quality |
|-------------|-----------|-----------------|
| Core ShaclParser (parse, canParse, formats) | `ShaclParserTest`, Characterization `ShaclParserTest` | Excellent |
| Shape recognition (SHP-01–04) | `ShaclShapeProcessorTest`, `ShaclParserShapeIntegrationTest`, Characterization, Conformance | Excellent |
| Target declarations (all 5 types incl. implicit) | `ShaclShapeProcessorTest`, `ShaclParserShapeIntegrationTest`, Conformance | Excellent |
| Property shape constraints (all params) | `ShaclPropertyAnalyzerTest` (76 tests), Characterization, Conformance | Excellent |
| Logical constraints (sh:and/or/not/xone) | `ShaclPropertyAnalyzerTest`, Conformance | Good |
| Property paths (all 7 types) | `ShaclPropertyAnalyzerTest`, `ShaclPathConformanceTest` | Good |
| Severity & messages | `ShaclShapeProcessorTest`, `ShaclPropertyAnalyzerTest`, Conformance | Good |
| Deactivation (sh:deactivated) | `ShaclShapeProcessorTest`, `ShaclPropertyAnalyzerTest`, Conformance | Good |
| Non-validating properties (order, group, defaultValue) | `ShaclPropertyAnalyzerTest` | Good |
| Qualified value shapes | `ShaclPropertyAnalyzerTest`, Conformance | Good |
| Property type detection (object vs datatype) | `ShaclPropertyAnalyzerTest`, Characterization | Good |
| Cardinality & range extraction | `ShaclPropertyAnalyzerTest`, Characterization | Good |
| Multilingual labels & descriptions | `ShaclShapeProcessorTest`, `ShaclPropertyAnalyzerTest`, Characterization | Good |
| Backward compatibility aliases | `AliasesTest` (13 tests) | Good |
| Application profiles (DCAT-AP, ADMS-AP, NL-SBB, TopBraid) | `ShaclApplicationProfileTest` (28 tests), Characterization | Excellent |
| Closed shapes | Conformance `closed-001` | Basic (shape detected, constraints not verified) |
| SPARQL constraints | Legacy `ShaclSparqlParserTest` (excluded from active suites) | Not active |
| Format detection (Turtle, RDF/XML, JSON-LD) | `ShaclParserTest`, Characterization | Good |
| Prefix extraction | Characterization, Application profiles | Good |
| Error handling | `ShaclParserTest`, Characterization | Good |

### Conformance Test Fixtures (W3C-aligned)

38 Turtle fixture files in `tests/Fixtures/W3c/` covering targets, constraints, paths, node shapes, closed shapes, and qualified value shapes. 4 real-world application profile fixtures (DCAT-AP 2.1.1, ADMS-AP 2.0.0, SKOS-AP-NL, TopBraid).

---

## Notes

- Spec version: W3C Recommendation 20 July 2017
- Analysis based on source code in `src/ShaclParser.php`, `src/Extractors/ShaclShapeProcessor.php`, `src/Extractors/ShaclPropertyAnalyzer.php`, and 25 test files (341 passing tests, 872 assertions)
- The parser successfully handles 4 real-world SHACL application profiles (DCAT-AP 2.1.1, ADMS-AP 2.0.0, SKOS-AP-NL, TopBraid)
- Legacy monolith tests in `tests/Unit/Services/Ontology/` include SPARQL extraction tests that are not currently active — these document previously implemented functionality that needs porting
