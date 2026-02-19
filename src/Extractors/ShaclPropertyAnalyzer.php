<?php

declare(strict_types=1);

namespace Youri\vandenBogert\Software\ParserShacl\Extractors;

use EasyRdf\Literal;
use EasyRdf\RdfNamespace;
use EasyRdf\Resource;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;

/**
 * Extracts SHACL property shapes with full constraint definitions from parsed RDF.
 */
final class ShaclPropertyAnalyzer
{
    private const string SHACL_NS = 'http://www.w3.org/ns/shacl#';
    private const string RDF_NIL = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil';

    /** @var list<string> */
    private const array LABEL_PROPERTIES = ['rdfs:label', 'sh:name', 'skos:prefLabel', 'dc:title', 'dcterms:title'];

    /** @var list<string> */
    private const array DESCRIPTION_PROPERTIES = ['rdfs:comment', 'sh:description', 'skos:definition', 'dc:description', 'dcterms:description'];

    /** @var list<string> Constraint properties that hold URI values */
    private const array URI_CONSTRAINTS = [
        'datatype', 'class', 'node', 'nodeKind', 'equals', 'disjoint',
        'lessThan', 'lessThanOrEquals', 'qualifiedValueShape', 'group',
    ];

    /** @var list<string> Constraint properties that hold literal/string values */
    private const array LITERAL_CONSTRAINTS = [
        'minCount', 'maxCount', 'minLength', 'maxLength', 'pattern', 'flags',
        'uniqueLang', 'minInclusive', 'maxInclusive', 'minExclusive', 'maxExclusive',
        'qualifiedMinCount', 'qualifiedMaxCount', 'qualifiedValueShapesDisjoint',
        'order', 'deactivated',
    ];

    /** @var list<string> Constraint properties holding RDF lists */
    private const array LIST_CONSTRAINTS = ['in', 'languageIn'];

    /** @var list<string> Logical constraint operators */
    private const array LOGICAL_CONSTRAINTS = ['or', 'and', 'xone'];

    /** @var list<string> Node kind URIs that indicate object properties */
    private const array OBJECT_NODE_KINDS = [
        'http://www.w3.org/ns/shacl#IRI',
        'http://www.w3.org/ns/shacl#BlankNode',
        'http://www.w3.org/ns/shacl#BlankNodeOrIRI',
    ];

    /**
     * @param array<string, array<string, mixed>> $nodeShapes
     * @return array<string, array<string, mixed>>
     */
    public function extractPropertyShapes(ParsedRdf $parsedRdf, array $nodeShapes): array
    {
        $graph = $parsedRdf->graph;
        RdfNamespace::set('sh', self::SHACL_NS);

        foreach ($nodeShapes as $shapeUri => &$shape) {
            $resource = $graph->resource($shapeUri);
            $propertyShapeResources = $resource->all('sh:property');
            $propertyShapes = [];

            foreach ($propertyShapeResources as $psResource) {
                if (!$psResource instanceof Resource) {
                    continue;
                }
                $propertyShape = $this->extractSinglePropertyShape($psResource);
                if ($propertyShape !== null) {
                    $propertyShapes[] = $propertyShape;
                }
            }

            $shape['property_shapes'] = $propertyShapes;
        }
        unset($shape);

        return $nodeShapes;
    }

    /**
     * Extract range URIs from a property shape data array.
     *
     * @param array<string, mixed> $shapeData
     * @return list<string>
     */
    public function extractRangeFromShape(array $shapeData): array
    {
        $ranges = [];

        if (isset($shapeData['datatype']) && is_string($shapeData['datatype'])) {
            $ranges[] = $shapeData['datatype'];
        }

        if (isset($shapeData['class']) && is_string($shapeData['class'])) {
            $ranges[] = $shapeData['class'];
        }

        // Check logical constraints for class/datatype references
        foreach (['sh_or', 'sh_and', 'sh_xone'] as $logicalKey) {
            if (isset($shapeData[$logicalKey]) && is_array($shapeData[$logicalKey])) {
                /** @var array<int, array<string, mixed>> $items */
                $items = $shapeData[$logicalKey];
                foreach ($items as $item) {
                    if (isset($item['class']) && is_string($item['class'])) {
                        $ranges[] = $item['class'];
                    }
                    if (isset($item['datatype']) && is_string($item['datatype'])) {
                        $ranges[] = $item['datatype'];
                    }
                }
            }
        }

        // Check sh:not for class/datatype references
        if (isset($shapeData['sh_not']) && is_array($shapeData['sh_not'])) {
            /** @var array<string, mixed> $notItem */
            $notItem = $shapeData['sh_not'];
            if (isset($notItem['class']) && is_string($notItem['class'])) {
                $ranges[] = $notItem['class'];
            }
            if (isset($notItem['datatype']) && is_string($notItem['datatype'])) {
                $ranges[] = $notItem['datatype'];
            }
        }

        return array_values(array_unique($ranges));
    }

    /**
     * Determine property type (object vs datatype) from a property shape.
     *
     * @param array<string, mixed> $shapeData
     */
    public function determinePropertyTypeFromShape(array $shapeData): string
    {
        if (isset($shapeData['class']) || isset($shapeData['node'])) {
            return 'object';
        }

        if (isset($shapeData['nodeKind']) && is_string($shapeData['nodeKind'])) {
            if (in_array($shapeData['nodeKind'], self::OBJECT_NODE_KINDS, true)) {
                return 'object';
            }
        }

        // Explicit datatype constraint takes precedence over logical constraints
        if (isset($shapeData['datatype'])) {
            return 'datatype';
        }

        // Check logical constraints for object indicators
        foreach (['sh_or', 'sh_and', 'sh_xone'] as $logicalKey) {
            if (isset($shapeData[$logicalKey]) && is_array($shapeData[$logicalKey])) {
                /** @var array<int, array<string, mixed>> $items */
                $items = $shapeData[$logicalKey];
                foreach ($items as $item) {
                    if (isset($item['class']) || isset($item['node'])) {
                        return 'object';
                    }
                    if (isset($item['nodeKind']) && is_string($item['nodeKind'])
                        && in_array($item['nodeKind'], self::OBJECT_NODE_KINDS, true)) {
                        return 'object';
                    }
                }
            }
        }

        // Check sh:not for object indicators
        if (isset($shapeData['sh_not']) && is_array($shapeData['sh_not'])) {
            /** @var array<string, mixed> $notItem */
            $notItem = $shapeData['sh_not'];
            if (isset($notItem['class']) || isset($notItem['node'])) {
                return 'object';
            }
            if (isset($notItem['nodeKind']) && is_string($notItem['nodeKind'])
                && in_array($notItem['nodeKind'], self::OBJECT_NODE_KINDS, true)) {
                return 'object';
            }
        }

        return 'datatype';
    }

    /**
     * Extract cardinality string from a property shape.
     *
     * @param array<string, mixed> $shapeData
     */
    public function extractCardinality(array $shapeData): ?string
    {
        $min = isset($shapeData['minCount']) ? (string) $shapeData['minCount'] : null;
        $max = isset($shapeData['maxCount']) ? (string) $shapeData['maxCount'] : null;

        if ($min === null && $max === null) {
            return null;
        }

        if ($min !== null && $max !== null) {
            if ($min === $max) {
                return $min;
            }

            return $min . '..' . $max;
        }

        if ($min !== null) {
            return $min . '..n';
        }

        return '0..' . $max;
    }

    /**
     * Extract a single property shape from a blank node resource.
     *
     * @return array<string, mixed>|null
     */
    private function extractSinglePropertyShape(Resource $resource): ?array
    {
        $path = $this->extractPath($resource);
        if ($path === null) {
            return null;
        }

        $result = ['path' => $path];

        // URI constraints
        foreach (self::URI_CONSTRAINTS as $constraint) {
            $value = $this->getUriValue($resource, 'sh:' . $constraint);
            if ($value !== null) {
                $result[$constraint] = $value;
            }
        }

        // Multi-value class handling
        if (isset($result['class'])) {
            $classes = $this->getUriValues($resource, 'sh:class');
            if (count($classes) > 1) {
                $result['classes'] = $classes;
            } else {
                $result['classes'] = [$result['class']];
            }
        }

        // Literal constraints
        foreach (self::LITERAL_CONSTRAINTS as $constraint) {
            $value = $this->getLiteralValue($resource, 'sh:' . $constraint);
            if ($value !== null) {
                $result[$constraint] = $value;
            }
        }

        // hasValue (can be literal or URI)
        $hasValue = $this->getAnyValue($resource, 'sh:hasValue');
        if ($hasValue !== null) {
            $result['hasValue'] = $hasValue;
        }

        // defaultValue (can be literal or URI)
        $defaultValue = $this->getAnyValue($resource, 'sh:defaultValue');
        if ($defaultValue !== null) {
            $result['defaultValue'] = $defaultValue;
        }

        // List constraints (sh:in, sh:languageIn)
        foreach (self::LIST_CONSTRAINTS as $constraint) {
            $list = $this->extractRdfList($resource, 'sh:' . $constraint);
            if ($list !== null && $list !== []) {
                $result[$constraint] = $list;
            }
        }

        // Labels (sh:name)
        $labels = $this->extractLabelsFromResource($resource);
        if ($labels !== []) {
            $result['name'] = $this->pickBestValue($labels);
            $result['labels'] = $labels;
        }

        // Descriptions (sh:description)
        $descriptions = $this->extractDescriptionsFromResource($resource);
        if ($descriptions !== []) {
            $result['description'] = $this->pickBestValue($descriptions);
            $result['descriptions'] = $descriptions;
        }

        // Messages (sh:message)
        $messages = $this->extractMessages($resource);
        if ($messages !== []) {
            $result['message'] = $messages[0];
            $result['messages'] = $messages;
        }

        // Logical constraints (sh:or, sh:and, sh:xone)
        foreach (self::LOGICAL_CONSTRAINTS as $constraint) {
            $logicalShapes = $this->extractLogicalConstraint($resource, 'sh:' . $constraint);
            if ($logicalShapes !== null) {
                $result['sh_' . $constraint] = $logicalShapes;
            }
        }

        // sh:not constraint (W3C SHACL Section 4.6.1 - single shape, not a list)
        $notConstraint = $this->extractNotConstraint($resource);
        if ($notConstraint !== null) {
            $result['sh_not'] = $notConstraint;
        }

        return $result;
    }

    /**
     * Extract the path from a property shape resource.
     *
     * @return string|array<string, mixed>|null
     */
    private function extractPath(Resource $resource): string|array|null
    {
        /** @var Resource|Literal|null $pathValue */
        $pathValue = $resource->get('sh:path');

        if ($pathValue === null) {
            return null;
        }

        if ($pathValue instanceof Resource) {
            // Check for complex path types
            $complexPath = $this->extractComplexPath($pathValue);
            if ($complexPath !== null) {
                return $complexPath;
            }

            // Check if it's a sequence path (RDF list on sh:path directly)
            $sequencePath = $this->extractSequencePath($pathValue);
            if ($sequencePath !== null) {
                return $sequencePath;
            }

            $uri = $pathValue->getUri();

            return ($uri !== '' && $uri !== '0') ? $uri : null;
        }

        return null;
    }

    /**
     * Extract complex path (inverse, alternative, zeroOrMore, oneOrMore, zeroOrOne).
     *
     * @return array<string, mixed>|null
     */
    private function extractComplexPath(Resource $resource): ?array
    {
        // Inverse path
        /** @var Resource|Literal|null $inversePath */
        $inversePath = $resource->get('sh:inversePath');
        if ($inversePath instanceof Resource) {
            return ['type' => 'inverse', 'path' => $inversePath->getUri()];
        }

        // Alternative path
        /** @var Resource|Literal|null $altPath */
        $altPath = $resource->get('sh:alternativePath');
        if ($altPath instanceof Resource) {
            $paths = $this->collectRdfListUris($altPath);
            if ($paths !== []) {
                return ['type' => 'alternative', 'paths' => $paths];
            }
        }

        // Zero or more
        /** @var Resource|Literal|null $zeroOrMorePath */
        $zeroOrMorePath = $resource->get('sh:zeroOrMorePath');
        if ($zeroOrMorePath instanceof Resource) {
            return ['type' => 'zeroOrMore', 'path' => $zeroOrMorePath->getUri()];
        }

        // One or more
        /** @var Resource|Literal|null $oneOrMorePath */
        $oneOrMorePath = $resource->get('sh:oneOrMorePath');
        if ($oneOrMorePath instanceof Resource) {
            return ['type' => 'oneOrMore', 'path' => $oneOrMorePath->getUri()];
        }

        // Zero or one
        /** @var Resource|Literal|null $zeroOrOnePath */
        $zeroOrOnePath = $resource->get('sh:zeroOrOnePath');
        if ($zeroOrOnePath instanceof Resource) {
            return ['type' => 'zeroOrOne', 'path' => $zeroOrOnePath->getUri()];
        }

        return null;
    }

    /**
     * Extract sequence path from an RDF list node.
     *
     * @return array<string, mixed>|null
     */
    private function extractSequencePath(Resource $resource): ?array
    {
        $paths = $this->collectRdfListUris($resource);
        if ($paths !== []) {
            return ['type' => 'sequence', 'paths' => $paths];
        }

        return null;
    }

    /**
     * Collect URIs from an RDF list (rdf:first/rdf:rest chain).
     *
     * @return list<string>
     */
    private function collectRdfListUris(Resource $listNode): array
    {
        $uris = [];
        $current = $listNode;
        $maxIterations = 100;

        do {
            if ($current->getUri() === self::RDF_NIL) {
                break;
            }

            /** @var Resource|Literal|null $first */
            $first = $current->get('rdf:first');
            if ($first instanceof Resource) {
                $uri = $first->getUri();
                if ($uri !== '' && $uri !== '0') {
                    $uris[] = $uri;
                }
            } elseif ($first instanceof Literal) {
                $uris[] = (string) $first->getValue();
            }

            /** @var Resource|Literal|null $rest */
            $rest = $current->get('rdf:rest');
            if (!$rest instanceof Resource) {
                break;
            }
            $current = $rest;
        } while (--$maxIterations > 0);

        return $uris;
    }

    /**
     * Get a URI value from a resource property.
     */
    private function getUriValue(Resource $resource, string $property): ?string
    {
        /** @var Resource|Literal|null $value */
        $value = $resource->get($property);

        if ($value instanceof Resource) {
            $uri = $value->getUri();

            return ($uri !== '' && $uri !== '0') ? $uri : null;
        }

        return null;
    }

    /**
     * Get all URI values from a resource property.
     *
     * @return list<string>
     */
    private function getUriValues(Resource $resource, string $property): array
    {
        $values = [];
        foreach ($resource->all($property) as $value) {
            if ($value instanceof Resource) {
                $uri = $value->getUri();
                if ($uri !== '' && $uri !== '0') {
                    $values[] = $uri;
                }
            }
        }

        return $values;
    }

    /**
     * Get a literal string value from a resource property.
     */
    private function getLiteralValue(Resource $resource, string $property): ?string
    {
        /** @var Resource|Literal|null $value */
        $value = $resource->get($property);

        if ($value === null) {
            return null;
        }

        if ($value instanceof Literal) {
            return (string) $value->getValue();
        }

        // Resource — return URI as string
        $uri = $value->getUri();

        return ($uri !== '' && $uri !== '0') ? $uri : null;
    }

    /**
     * Get any value (literal or URI) as string.
     */
    private function getAnyValue(Resource $resource, string $property): ?string
    {
        /** @var Resource|Literal|null $value */
        $value = $resource->get($property);

        if ($value === null) {
            return null;
        }

        if ($value instanceof Resource) {
            return $value->getUri();
        }

        // Literal — return string value
        return (string) $value->getValue();
    }

    /**
     * Extract an RDF list from a property.
     *
     * @return list<string>|null
     */
    private function extractRdfList(Resource $resource, string $property): ?array
    {
        /** @var Resource|Literal|null $listHead */
        $listHead = $resource->get($property);

        if (!$listHead instanceof Resource) {
            return null;
        }

        if ($listHead->getUri() === self::RDF_NIL) {
            return [];
        }

        $items = $this->collectRdfListUris($listHead);

        return $items !== [] ? $items : null;
    }

    /**
     * Extract labels from a property shape resource.
     *
     * @return array<string, string>
     */
    private function extractLabelsFromResource(Resource $resource): array
    {
        $labels = [];

        foreach (self::LABEL_PROPERTIES as $property) {
            foreach ($resource->all($property) as $value) {
                if ($value instanceof Literal) {
                    $lang = (string) $value->getLang();
                    $langKey = ($lang !== '') ? $lang : 'en';
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
     * Extract descriptions from a property shape resource.
     *
     * @return array<string, string>
     */
    private function extractDescriptionsFromResource(Resource $resource): array
    {
        $descriptions = [];

        foreach (self::DESCRIPTION_PROPERTIES as $property) {
            foreach ($resource->all($property) as $value) {
                if ($value instanceof Literal) {
                    $lang = (string) $value->getLang();
                    $langKey = ($lang !== '') ? $lang : 'en';
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
     * Extract messages from a resource.
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
     * Extract a logical constraint (sh:or, sh:and, sh:xone) as array of shape data.
     *
     * @return list<array<string, string>>|null
     */
    private function extractLogicalConstraint(Resource $resource, string $property): ?array
    {
        /** @var Resource|Literal|null $listHead */
        $listHead = $resource->get($property);

        if (!$listHead instanceof Resource) {
            return null;
        }

        if ($listHead->getUri() === self::RDF_NIL) {
            return null;
        }

        $items = [];
        $current = $listHead;
        $maxIterations = 100;

        do {
            if ($current->getUri() === self::RDF_NIL) {
                break;
            }

            /** @var Resource|Literal|null $first */
            $first = $current->get('rdf:first');
            if ($first instanceof Resource) {
                $shapeData = $this->extractInlineShapeConstraints($first);
                if ($shapeData !== []) {
                    $items[] = $shapeData;
                }
            }

            /** @var Resource|Literal|null $rest */
            $rest = $current->get('rdf:rest');
            if (!$rest instanceof Resource) {
                break;
            }
            $current = $rest;
        } while (--$maxIterations > 0);

        return $items !== [] ? $items : null;
    }

    /**
     * Extract sh:not constraint (W3C SHACL Section 4.6.1).
     *
     * Unlike sh:or/sh:and/sh:xone, sh:not takes a single shape (not an RDF list).
     *
     * @return array<string, string>|null
     */
    private function extractNotConstraint(Resource $resource): ?array
    {
        /** @var Resource|Literal|null $notShape */
        $notShape = $resource->get('sh:not');

        if (!$notShape instanceof Resource) {
            return null;
        }

        $shapeData = $this->extractInlineShapeConstraints($notShape);

        return $shapeData !== [] ? $shapeData : null;
    }

    /**
     * Extract constraints from an inline shape resource (within sh:or/sh:and/sh:xone).
     *
     * @return array<string, string>
     */
    private function extractInlineShapeConstraints(Resource $resource): array
    {
        $data = [];

        // Check key URI constraints
        foreach (['class', 'datatype', 'node', 'nodeKind'] as $constraint) {
            $value = $this->getUriValue($resource, 'sh:' . $constraint);
            if ($value !== null) {
                $data[$constraint] = $value;
            }
        }

        // Check key literal constraints
        foreach (['minCount', 'maxCount', 'minLength', 'maxLength', 'pattern'] as $constraint) {
            $value = $this->getLiteralValue($resource, 'sh:' . $constraint);
            if ($value !== null) {
                $data[$constraint] = $value;
            }
        }

        return $data;
    }

    /**
     * Pick the best single value from a multilingual map.
     *
     * @param array<string, string> $values
     */
    private function pickBestValue(array $values): ?string
    {
        if ($values === []) {
            return null;
        }

        if (isset($values['en'])) {
            return $values['en'];
        }

        return reset($values);
    }
}
