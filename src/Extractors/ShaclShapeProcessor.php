<?php

declare(strict_types=1);

namespace Youri\vandenBogert\Software\ParserShacl\Extractors;

use EasyRdf\Literal;
use EasyRdf\RdfNamespace;
use EasyRdf\Resource;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;

/**
 * Extracts SHACL node shapes with target declarations from parsed RDF.
 *
 * Implements W3C SHACL Section 2.1 shape recognition rules:
 * - SHP-01: explicit rdf:type sh:NodeShape
 * - SHP-02: explicit rdf:type sh:PropertyShape (top-level)
 * - SHP-03: Named resources that are subjects of target predicates
 * - SHP-04: Named resources that are subjects of constraint parameters
 */
final class ShaclShapeProcessor
{
    private const string SHACL_NS = 'http://www.w3.org/ns/shacl#';

    /** @var list<string> */
    private const array TARGET_PREDICATES = [
        'http://www.w3.org/ns/shacl#targetClass',
        'http://www.w3.org/ns/shacl#targetNode',
        'http://www.w3.org/ns/shacl#targetSubjectsOf',
        'http://www.w3.org/ns/shacl#targetObjectsOf',
    ];

    /** @var list<string> */
    private const array CONSTRAINT_PARAMETERS = [
        'http://www.w3.org/ns/shacl#class',
        'http://www.w3.org/ns/shacl#datatype',
        'http://www.w3.org/ns/shacl#nodeKind',
        'http://www.w3.org/ns/shacl#minCount',
        'http://www.w3.org/ns/shacl#maxCount',
        'http://www.w3.org/ns/shacl#minExclusive',
        'http://www.w3.org/ns/shacl#minInclusive',
        'http://www.w3.org/ns/shacl#maxExclusive',
        'http://www.w3.org/ns/shacl#maxInclusive',
        'http://www.w3.org/ns/shacl#minLength',
        'http://www.w3.org/ns/shacl#maxLength',
        'http://www.w3.org/ns/shacl#pattern',
        'http://www.w3.org/ns/shacl#languageIn',
        'http://www.w3.org/ns/shacl#uniqueLang',
        'http://www.w3.org/ns/shacl#equals',
        'http://www.w3.org/ns/shacl#disjoint',
        'http://www.w3.org/ns/shacl#lessThan',
        'http://www.w3.org/ns/shacl#lessThanOrEquals',
        'http://www.w3.org/ns/shacl#not',
        'http://www.w3.org/ns/shacl#and',
        'http://www.w3.org/ns/shacl#or',
        'http://www.w3.org/ns/shacl#xone',
        'http://www.w3.org/ns/shacl#node',
        'http://www.w3.org/ns/shacl#property',
        'http://www.w3.org/ns/shacl#qualifiedValueShape',
        'http://www.w3.org/ns/shacl#closed',
        'http://www.w3.org/ns/shacl#hasValue',
        'http://www.w3.org/ns/shacl#in',
        'http://www.w3.org/ns/shacl#sparql',
    ];

    /** @var list<string> */
    private const array LABEL_PROPERTIES = [
        'rdfs:label',
        'sh:name',
        'skos:prefLabel',
        'dc:title',
        'dcterms:title',
    ];

    /** @var list<string> */
    private const array DESCRIPTION_PROPERTIES = [
        'rdfs:comment',
        'sh:description',
        'skos:definition',
        'dc:description',
        'dcterms:description',
    ];

    /** @var array<string, string> */
    private const array SEVERITY_MAP = [
        'http://www.w3.org/ns/shacl#Violation' => 'violation',
        'http://www.w3.org/ns/shacl#Warning' => 'warning',
        'http://www.w3.org/ns/shacl#Info' => 'info',
    ];

    /**
     * Extract SHACL node shapes with target declarations from parsed RDF.
     *
     * @return array<string, array<string, mixed>>
     */
    public function extractNodeShapes(ParsedRdf $parsedRdf): array
    {
        RdfNamespace::set('sh', self::SHACL_NS);

        $graph = $parsedRdf->graph;
        $shapes = [];

        foreach ($graph->resources() as $resource) {
            if (!$this->isShape($resource)) {
                continue;
            }

            $uri = $resource->getUri();
            if ($uri === '' || $uri === '0') {
                continue;
            }

            $shape = $this->extractShapeData($resource);
            $shapes[$uri] = $shape;
        }

        return $shapes;
    }

    /**
     * Determine if a resource is a SHACL shape using W3C Section 2.1 rules.
     */
    private function isShape(Resource $resource): bool
    {
        // SHP-01: explicit rdf:type sh:NodeShape
        if ($resource->isA('sh:NodeShape')) {
            return true;
        }

        // SHP-02: explicit rdf:type sh:PropertyShape
        if ($resource->isA('sh:PropertyShape')) {
            return true;
        }

        // SHP-03 and SHP-04 only apply to named resources (not blank nodes)
        $uri = $resource->getUri();
        if ($uri === '' || $uri === '0' || str_starts_with($uri, '_:')) {
            return false;
        }

        // SHP-03: Subject of a target predicate
        foreach (self::TARGET_PREDICATES as $targetPred) {
            $shortForm = str_replace(self::SHACL_NS, 'sh:', $targetPred);
            /** @var Resource|Literal|null $value */
            $value = $resource->get($shortForm);
            if ($value !== null) {
                return true;
            }
        }

        // SHP-04: Subject of a constraint parameter
        foreach (self::CONSTRAINT_PARAMETERS as $param) {
            $shortForm = str_replace(self::SHACL_NS, 'sh:', $param);
            /** @var Resource|Literal|null $value */
            $value = $resource->get($shortForm);
            if ($value !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract all shape data from a resource.
     *
     * @return array<string, mixed>
     */
    private function extractShapeData(Resource $resource): array
    {
        $uri = $resource->getUri();
        $labels = $this->extractLabels($resource);
        $descriptions = $this->extractDescriptions($resource);
        $targetClasses = $this->extractTargetClasses($resource);
        $targetNodes = $this->getResourceUriValues($resource, 'sh:targetNode');
        $severityData = $this->extractSeverity($resource);
        $messages = $this->extractMessages($resource);

        return [
            'uri' => $uri,
            'label' => $this->pickBestValue($labels),
            'labels' => $labels,
            'description' => $this->pickBestValue($descriptions),
            'descriptions' => $descriptions,
            'target_class' => $targetClasses !== [] ? $targetClasses[0] : null,
            'target_classes' => $targetClasses,
            'target_node' => $targetNodes !== [] ? $targetNodes[0] : null,
            'target_nodes' => $targetNodes,
            'target_subjects_of' => $this->getResourceUriValue($resource, 'sh:targetSubjectsOf'),
            'target_objects_of' => $this->getResourceUriValue($resource, 'sh:targetObjectsOf'),
            'property_shapes' => [],
            'constraints' => [],
            'severity' => $severityData['severity'],
            'severity_iri' => $severityData['severity_iri'],
            'message' => $messages !== [] ? $messages[0] : null,
            'messages' => $messages,
            'deactivated' => $this->extractDeactivated($resource),
            'metadata' => [
                'source' => 'shacl_parser',
                'types' => $this->extractTypeUris($resource),
            ],
        ];
    }

    /**
     * Extract target classes, including implicit targets.
     *
     * @return list<string>
     */
    private function extractTargetClasses(Resource $resource): array
    {
        $targetClasses = $this->getResourceUriValues($resource, 'sh:targetClass');

        // Implicit target class: shape is also an rdfs:Class
        if ($resource->isA('rdfs:Class')) {
            $shapeUri = $resource->getUri();
            if ($shapeUri !== '' && $shapeUri !== '0' && !in_array($shapeUri, $targetClasses, true)) {
                $targetClasses[] = $shapeUri;
            }
        }

        return $targetClasses;
    }

    /**
     * Extract language key from a literal.
     * EasyRdf's getLang() returns string (empty string for no language).
     */
    private function getLiteralLangKey(Literal $literal): string
    {
        $lang = (string) $literal->getLang();

        return ($lang !== '') ? $lang : 'en';
    }

    /**
     * Extract all language-tagged labels from a resource.
     *
     * @return array<string, string>
     */
    private function extractLabels(Resource $resource): array
    {
        $labels = [];

        foreach (self::LABEL_PROPERTIES as $property) {
            $allValues = $resource->all($property);
            foreach ($allValues as $value) {
                if ($value instanceof Literal) {
                    $langKey = $this->getLiteralLangKey($value);
                    if (!isset($labels[$langKey])) {
                        $labels[$langKey] = (string) $value->getValue();
                    }
                } elseif ($value !== null) {
                    if (!isset($labels['en'])) {
                        $labels['en'] = (string) $value;
                    }
                }
            }
        }

        return $labels;
    }

    /**
     * Extract all language-tagged descriptions from a resource.
     *
     * @return array<string, string>
     */
    private function extractDescriptions(Resource $resource): array
    {
        $descriptions = [];

        foreach (self::DESCRIPTION_PROPERTIES as $property) {
            $allValues = $resource->all($property);
            foreach ($allValues as $value) {
                if ($value instanceof Literal) {
                    $langKey = $this->getLiteralLangKey($value);
                    if (!isset($descriptions[$langKey])) {
                        $descriptions[$langKey] = (string) $value->getValue();
                    }
                } elseif ($value !== null) {
                    if (!isset($descriptions['en'])) {
                        $descriptions['en'] = (string) $value;
                    }
                }
            }
        }

        return $descriptions;
    }

    /**
     * Extract messages from the shape.
     *
     * @return list<string>
     */
    private function extractMessages(Resource $resource): array
    {
        $messages = [];

        foreach ($resource->all('sh:message') as $value) {
            if ($value instanceof Literal) {
                $messages[] = (string) $value->getValue();
            } else {
                $messages[] = (string) $value;
            }
        }

        return $messages;
    }

    /**
     * Extract severity information.
     *
     * @return array{severity: string, severity_iri: string|null}
     */
    private function extractSeverity(Resource $resource): array
    {
        /** @var Resource|Literal|null $severityValue */
        $severityValue = $resource->get('sh:severity');

        if ($severityValue === null) {
            return ['severity' => 'violation', 'severity_iri' => null];
        }

        $severityIri = ($severityValue instanceof Resource)
            ? $severityValue->getUri()
            : (string) $severityValue;

        $severity = self::SEVERITY_MAP[$severityIri] ?? 'violation';

        return ['severity' => $severity, 'severity_iri' => $severityIri];
    }

    /**
     * Extract the deactivated flag as a native boolean.
     */
    private function extractDeactivated(Resource $resource): bool
    {
        /** @var Resource|Literal|null $value */
        $value = $resource->get('sh:deactivated');

        if ($value === null) {
            return false;
        }

        if ($value instanceof Literal) {
            $raw = (string) $value->getValue();

            return $raw === 'true' || $raw === '1';
        }

        $stringVal = (string) $value;

        return $stringVal === 'true' || $stringVal === '1';
    }

    /**
     * Extract all RDF type URIs as full URIs.
     *
     * @return list<string>
     */
    private function extractTypeUris(Resource $resource): array
    {
        $types = [];

        foreach ($resource->all('rdf:type') as $type) {
            if ($type instanceof Resource) {
                $typeUri = $type->getUri();
                if ($typeUri !== '' && $typeUri !== '0') {
                    $types[] = $typeUri;
                }
            }
        }

        return $types;
    }

    /**
     * Get a single URI value from a resource property.
     */
    private function getResourceUriValue(Resource $resource, string $property): ?string
    {
        /** @var Resource|Literal|null $value */
        $value = $resource->get($property);

        if ($value === null) {
            return null;
        }

        if ($value instanceof Resource) {
            return $value->getUri();
        }

        return (string) $value;
    }

    /**
     * Get all URI values from a resource property.
     *
     * @return list<string>
     */
    private function getResourceUriValues(Resource $resource, string $property): array
    {
        $values = [];

        foreach ($resource->all($property) as $value) {
            if ($value instanceof Resource) {
                $uri = $value->getUri();
                if ($uri !== '' && $uri !== '0') {
                    $values[] = $uri;
                }
            } else {
                $stringVal = (string) $value;
                if ($stringVal !== '') {
                    $values[] = $stringVal;
                }
            }
        }

        return $values;
    }

    /**
     * Pick the best single value from a multilingual map (prefers English).
     *
     * @param array<string, string> $values
     */
    private function pickBestValue(array $values): ?string
    {
        if ($values === []) {
            return null;
        }

        // Prefer English
        if (isset($values['en'])) {
            return $values['en'];
        }

        // Return first available -- array is guaranteed non-empty here
        return reset($values);
    }
}
