<?php

namespace App\Services\Ontology\Parsers;

use App\Services\Ontology\Exceptions\OntologyImportException;
use App\Services\Ontology\Shacl\ShaclPropertyExtractor;
use EasyRdf\Graph;

class ShaclParser implements OntologyParserInterface
{
    use ShaclPropertyExtractor;

    public function parse(string $content, array $options = []): array
    {
        try {
            // Create EasyRdf graph to parse the SHACL content
            $graph = new Graph;

            // Detect the actual serialization format (turtle, rdf/xml, etc.)
            $serializationFormat = $this->detectSerializationFormat($content);
            $easyrdfFormat = $this->mapFormatName($serializationFormat);

            // Parse content into graph
            $graph->parse($content, $easyrdfFormat);

            // Register SHACL namespace for easier querying
            \EasyRdf\RdfNamespace::set('sh', 'http://www.w3.org/ns/shacl#');

            // Extract prefixes from the content - this is crucial for SHACL files
            $prefixes = $this->extractPrefixes($graph, $content, $serializationFormat);

            // Extract SHACL shapes (not vocabulary terms!)
            $shapes = $this->extractShaclShapes($graph);

            // For SHACL files, we extract minimal classes/properties from shapes
            // but focus on the shapes themselves
            $classes = $this->extractClassesFromShapes($shapes, $prefixes);
            $properties = $this->extractPropertiesFromShapes($shapes, $prefixes);

            return [
                'metadata' => [
                    'type' => 'shacl',
                    'format' => $serializationFormat,
                    'resource_count' => count($graph->resources()),
                    'shapes_count' => count($shapes),
                ],
                'prefixes' => $prefixes,
                'classes' => $classes,
                'properties' => $properties,
                'shapes' => $shapes,
                'raw_content' => $content,
            ];

        } catch (\Exception $e) {
            throw new OntologyImportException('SHACL parsing failed: '.$e->getMessage(), 0, $e);
        }
    }

    public function canParse(string $content): bool
    {
        return str_contains($content, 'sh:') ||
               str_contains($content, 'http://www.w3.org/ns/shacl#') ||
               str_contains($content, 'NodeShape') ||
               str_contains($content, 'PropertyShape');
    }

    public function getSupportedFormats(): array
    {
        return ['shacl'];
    }

    /**
     * Detect the actual serialization format (turtle, rdf/xml, etc.)
     */
    protected function detectSerializationFormat(string $content): string
    {
        $content = trim($content);

        if (str_starts_with($content, '<?xml') || str_contains($content, '<rdf:RDF')) {
            return 'rdf/xml';
        }

        if (str_starts_with($content, '@prefix') || str_contains($content, 'PREFIX')) {
            return 'turtle';
        }

        if (str_starts_with($content, '{') && str_contains($content, '@context')) {
            return 'json-ld';
        }

        // Default to turtle for SHACL files
        return 'turtle';
    }

    /**
     * Map serialization format to EasyRdf format names
     */
    protected function mapFormatName(string $format): string
    {
        return match ($format) {
            'rdf/xml', 'xml' => 'rdfxml',
            'turtle', 'ttl' => 'turtle',
            'json-ld', 'jsonld' => 'jsonld',
            'n-triples', 'nt' => 'ntriples',
            default => 'turtle'
        };
    }

    /**
     * Extract prefixes from SHACL content - critical for proper namespace detection
     */
    protected function extractPrefixes(Graph $graph, string $content, string $format): array
    {
        $prefixes = [];

        // Method 1: Extract from EasyRdf graph's namespace map
        if (! method_exists($graph, 'getNamespaceMap')) {

            // Method 2: Parse prefixes from raw content based on format
            $contentPrefixes = $this->extractPrefixesFromContent($content, $format);
            $prefixes = array_merge($prefixes, $contentPrefixes);

            return $prefixes;
        }
        $namespaces = $graph->getNamespaceMap();
        foreach ($namespaces as $prefix => $namespace) {
            if (! empty($prefix) && ! empty($namespace)) {
                $prefixes[$prefix] = $namespace;
            }
        }

        // Method 2: Parse prefixes from raw content based on format
        $contentPrefixes = $this->extractPrefixesFromContent($content, $format);
        $prefixes = array_merge($prefixes, $contentPrefixes);

        return $prefixes;
    }

    /**
     * Extract prefixes from raw content
     */
    protected function extractPrefixesFromContent(string $content, string $format): array
    {
        $prefixes = [];

        if ($format === 'turtle' || $format === 'ttl') {
            // Extract @prefix declarations from Turtle
            if (preg_match_all('/@prefix\s+([^:]+):\s*<([^>]+)>/i', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $prefix = trim($match[1]);
                    $namespace = trim($match[2]);
                    if (! empty($prefix) && ! empty($namespace)) {
                        $prefixes[$prefix] = $namespace;
                    }
                }
            }
            // Also handle PREFIX (SPARQL style)
            if (preg_match_all('/PREFIX\s+([^:]+):\s*<([^>]+)>/i', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $prefix = trim($match[1]);
                    $namespace = trim($match[2]);
                    if (! empty($prefix) && ! empty($namespace)) {
                        $prefixes[$prefix] = $namespace;
                    }
                }
            }
        } elseif ($format === 'rdf/xml' || $format === 'xml') {
            // Extract xmlns declarations from RDF/XML
            if (preg_match_all('/xmlns:([^=]+)="([^"]+)"/i', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $prefix = trim($match[1]);
                    $namespace = trim($match[2]);
                    if (! empty($prefix) && ! empty($namespace)) {
                        $prefixes[$prefix] = $namespace;
                    }
                }
            }
        }

        return $prefixes;
    }

    /**
     * Known SHACL constraint parameter predicates (for shape recognition via SHP-03/SHP-04)
     */
    protected const CONSTRAINT_PARAMETERS = [
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

    /**
     * SHACL target predicates (for shape recognition via SHP-03)
     */
    protected const TARGET_PREDICATES = [
        'http://www.w3.org/ns/shacl#targetClass',
        'http://www.w3.org/ns/shacl#targetNode',
        'http://www.w3.org/ns/shacl#targetSubjectsOf',
        'http://www.w3.org/ns/shacl#targetObjectsOf',
    ];

    /**
     * Determine if a resource is a SHACL shape using the spec's recognition rules (Section 2.1).
     *
     * For SHP-03 (target predicates) and SHP-04 (constraint parameters), only named resources
     * (IRIs) are recognized as top-level shapes. Blank node shapes that appear inline within
     * sh:property are already handled by extractShapeProperties().
     */
    protected function isShape($resource): bool
    {
        // SHP-01/SHP-02: SHACL instance of sh:NodeShape or sh:PropertyShape
        $types = $resource->all('rdf:type');
        foreach ($types as $type) {
            if (! method_exists($type, 'getUri')) {
                continue;
            }
            $typeUri = $type->getUri();
            if ($typeUri === 'http://www.w3.org/ns/shacl#NodeShape' ||
                $typeUri === 'http://www.w3.org/ns/shacl#PropertyShape') {
                return true;
            }
        }

        // SHP-03/SHP-04 only apply to named resources (not blank nodes).
        // Blank node shapes inside sh:property blocks are handled as nested property shapes.
        $uri = $resource->getUri();
        if (! $uri || str_starts_with($uri, '_:')) {
            return false;
        }

        // SHP-03: Subject of a target predicate
        foreach (self::TARGET_PREDICATES as $targetPred) {
            $shortForm = str_replace('http://www.w3.org/ns/shacl#', 'sh:', $targetPred);
            if ($resource->get($shortForm) !== null) {
                return true;
            }
        }

        // SHP-04: Subject of a triple with a constraint parameter as predicate
        foreach (self::CONSTRAINT_PARAMETERS as $param) {
            $shortForm = str_replace('http://www.w3.org/ns/shacl#', 'sh:', $param);
            if ($resource->get($shortForm) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract SHACL shapes from the graph
     */
    protected function extractShaclShapes(Graph $graph): array
    {
        $shapes = [];

        // Find all resources that are SHACL shapes
        foreach ($graph->resources() as $resource) {
            if (! $this->isShape($resource)) {
                continue;
            }

            $uri = $resource->getUri();
            if (! $uri) {
                continue;
            }

            $labels = $this->getAllResourceLabels($resource);
            $descriptions = $this->getAllResourceComments($resource);
            $rawSeverity = $this->getResourceValue($resource, 'sh:severity');
            $shape = [
                'uri' => $uri,
                'label' => $this->getResourceLabel($resource),
                'labels' => $labels,
                'description' => $this->getResourceComment($resource),
                'descriptions' => $descriptions,
                'target_class' => $this->getResourceValue($resource, 'sh:targetClass'),
                'target_classes' => $this->getResourceValues($resource, 'sh:targetClass'),
                'target_node' => $this->getResourceValue($resource, 'sh:targetNode'),
                'target_nodes' => $this->getResourceValues($resource, 'sh:targetNode'),
                'target_subjects_of' => $this->getResourceValue($resource, 'sh:targetSubjectsOf'),
                'target_objects_of' => $this->getResourceValue($resource, 'sh:targetObjectsOf'),
                'target_property' => $this->getResourceValue($resource, 'sh:path'),
                'property_shapes' => $this->extractShapeProperties($resource),
                'constraints' => $this->extractShapeConstraints($resource),
                'severity' => $this->mapSeverity($rawSeverity ?? 'sh:Violation'),
                'severity_iri' => $rawSeverity,
                'message' => $this->getResourceValue($resource, 'sh:message'),
                'messages' => $this->getResourceValues($resource, 'sh:message'),
                'deactivated' => $this->getResourceValue($resource, 'sh:deactivated'),
                // Logical constraints
                'sh_and' => $this->extractLogicalConstraint($resource, 'sh:and'),
                'sh_or' => $this->extractLogicalConstraint($resource, 'sh:or'),
                'sh_not' => $this->extractLogicalConstraint($resource, 'sh:not'),
                'sh_xone' => $this->extractLogicalConstraint($resource, 'sh:xone'),
                // Closed shape
                'closed' => $this->getResourceValue($resource, 'sh:closed'),
                'ignored_properties' => $this->extractRdfList($resource, 'sh:ignoredProperties'),
                // SPARQL constraints
                'sparql_constraints' => $this->extractSparqlConstraints($resource),
                'metadata' => [
                    'source' => 'shacl_parser',
                    'types' => $this->getResourceValues($resource, 'rdf:type'),
                ],
            ];

            $shapes[] = $shape;
        }

        return $shapes;
    }

    /**
     * For SHACL files, we don't extract classes from shapes directly.
     * Classes should be extracted from the shapes' target classes in the importer.
     * SHACL shapes are constraint definitions, not class definitions themselves.
     */
    protected function extractClassesFromShapes(array $shapes, array $prefixes): array
    {
        // For SHACL parsers, we return empty array here
        // The OntologyImporter will handle extracting target classes from the shapes
        return [];
    }

    /**
     * Extract properties from SHACL shapes - focus on meaningful property constraints
     */
    protected function extractPropertiesFromShapes(array $shapes, array $prefixes): array
    {
        $properties = [];
        $propertyUris = [];

        foreach ($shapes as $shape) {
            // Only extract properties from shapes in custom namespaces
            if (! empty($shape['uri'])) {
                $shapeNamespace = $this->getNamespaceFromUri($shape['uri']);
                if (! $this->isCustomNamespace($shapeNamespace, $prefixes)) {
                    continue; // Skip shapes from standard namespaces
                }
            }

            if (empty($shape['property_shapes'])) {
                continue;
            }
            foreach ($shape['property_shapes'] as $propertyShape) {
                $path = $propertyShape['path'] ?? null;
                // Skip complex paths (arrays) for property extraction — they don't have a single URI
                if (empty($path) || is_array($path) || in_array($path, $propertyUris)) {
                    continue;
                }
                // Only include properties that have meaningful constraints or custom descriptions
                if (! $this->hasSignificantConstraints($propertyShape)) {
                    continue; // Skip properties without meaningful constraints
                }

                $propertyUris[] = $path;
                $localName = $this->extractLocalName($path);

                // Determine property type from constraints
                $propertyType = $this->determinePropertyTypeFromShape($propertyShape);

                // Use the custom name/description if available
                $label = $propertyShape['name'] ?? $localName;
                $description = $propertyShape['description'] ?? $propertyShape['message'] ?? null;

                // If no custom description, create a meaningful one based on constraints
                if (empty($description)) {
                    $description = $this->generatePropertyDescription($propertyShape, $shape);
                }

                $properties[] = [
                    'uri' => $propertyShape['path'],
                    'label' => $label,
                    'labels' => $propertyShape['labels'] ?? [],
                    'description' => $description,
                    'descriptions' => $propertyShape['descriptions'] ?? [],
                    'property_type' => $propertyType,
                    'domain' => ! empty($shape['target_class']) ? [$shape['target_class']] : [],
                    'range' => $this->extractRangeFromShape($propertyShape),
                    'cardinality' => $this->extractCardinality($propertyShape),
                    'is_functional' => $this->isFunctionalProperty($propertyShape),
                    'metadata' => [
                        'source' => 'shacl_property_shape',
                        'shape_uri' => $shape['uri'],
                        'constraints' => array_filter($propertyShape), // Remove null values
                    ],
                ];

            }

        }

        return $properties;
    }

    /**
     * Helper methods for resource extraction from EasyRdf
     */
    protected function getResourceLabel($resource): ?string
    {
        $labels = [
            $resource->get('rdfs:label'),
            $resource->get('sh:name'),
            $resource->get('skos:prefLabel'),
            $resource->get('dc:title'),
            $resource->get('dcterms:title'),
        ];

        foreach ($labels as $label) {
            if ($label) {
                return (string) $label;
            }
        }

        return null;
    }

    /**
     * Extract all language-tagged labels from a resource
     */
    protected function getAllResourceLabels($resource): array
    {
        $allLabels = [];
        $labelProperties = [
            'rdfs:label',
            'sh:name',
            'skos:prefLabel',
            'dc:title',
            'dcterms:title',
        ];

        foreach ($labelProperties as $labelProp) {
            $labels = $resource->all($labelProp);
            foreach ($labels as $label) {
                // Check if it's a literal with a language
                if ($label instanceof \EasyRdf\Literal && $label->getLang()) {
                    $allLabels[$label->getLang()] = $label->getValue();
                } elseif ($label) {
                    // If no language tag, store as default
                    if (empty($allLabels['en'])) {
                        $allLabels['en'] = (string) $label;
                    }
                }
            }
        }

        return $allLabels;
    }

    /**
     * Extract all language-tagged comments/descriptions from a resource
     */
    protected function getAllResourceComments($resource): array
    {
        $allComments = [];
        $commentProperties = [
            'rdfs:comment',
            'sh:description',
            'skos:definition',
            'dc:description',
            'dcterms:description',
        ];

        foreach ($commentProperties as $commentProp) {
            $comments = $resource->all($commentProp);
            foreach ($comments as $comment) {
                // Check if it's a literal with a language
                if ($comment instanceof \EasyRdf\Literal && $comment->getLang()) {
                    $allComments[$comment->getLang()] = $comment->getValue();
                } elseif ($comment) {
                    // If no language tag, store as default
                    if (empty($allComments['en'])) {
                        $allComments['en'] = (string) $comment;
                    }
                }
            }
        }

        return $allComments;
    }

    protected function getResourceComment($resource): ?string
    {
        $comments = [
            $resource->get('rdfs:comment'),
            $resource->get('sh:description'),
            $resource->get('skos:definition'),
            $resource->get('dc:description'),
            $resource->get('dcterms:description'),
        ];

        foreach ($comments as $comment) {
            if ($comment) {
                return (string) $comment;
            }
        }

        return null;
    }

    protected function getResourceValue($resource, string $property): ?string
    {
        $value = $resource->get($property);
        if (! $value) {
            return null;
        }

        if (method_exists($value, 'getUri') && $value->getUri()) {
            return $value->getUri();
        }

        return (string) $value;
    }

    protected function getResourceValues($resource, string $property): array
    {
        $values = [];
        $resources = $resource->all($property);

        foreach ($resources as $resourceValue) {
            if (method_exists($resourceValue, 'getUri') && $resourceValue->getUri()) {
                $values[] = $resourceValue->getUri();
            } else {
                $values[] = (string) $resourceValue;
            }
        }

        return $values;
    }

    protected function extractShapeProperties($resource): array
    {
        $properties = [];
        $propertyShapes = $resource->all('sh:property');

        foreach ($propertyShapes as $propertyShape) {
            $labels = $this->getAllResourceLabels($propertyShape);
            $descriptions = $this->getAllResourceComments($propertyShape);
            $property = [
                'path' => $this->extractPropertyPath($propertyShape),
                'datatype' => $this->getResourceValue($propertyShape, 'sh:datatype'),
                'nodeKind' => $this->getResourceValue($propertyShape, 'sh:nodeKind'),
                'minCount' => $this->getResourceValue($propertyShape, 'sh:minCount'),
                'maxCount' => $this->getResourceValue($propertyShape, 'sh:maxCount'),
                'minLength' => $this->getResourceValue($propertyShape, 'sh:minLength'),
                'maxLength' => $this->getResourceValue($propertyShape, 'sh:maxLength'),
                'pattern' => $this->getResourceValue($propertyShape, 'sh:pattern'),
                'flags' => $this->getResourceValue($propertyShape, 'sh:flags'),
                'class' => $this->getResourceValue($propertyShape, 'sh:class'),
                'classes' => $this->getResourceValues($propertyShape, 'sh:class'),
                'node' => $this->getResourceValue($propertyShape, 'sh:node'),
                'message' => $this->getResourceValue($propertyShape, 'sh:message'),
                'messages' => $this->getResourceValues($propertyShape, 'sh:message'),
                'name' => $this->getResourceValue($propertyShape, 'sh:name'),
                'description' => $this->getResourceValue($propertyShape, 'sh:description'),
                'labels' => $labels,
                'descriptions' => $descriptions,
                // Non-validating properties
                'order' => $this->getResourceValue($propertyShape, 'sh:order'),
                'group' => $this->getResourceValue($propertyShape, 'sh:group'),
                'defaultValue' => $this->getResourceValue($propertyShape, 'sh:defaultValue'),
                // Deactivation
                'deactivated' => $this->getResourceValue($propertyShape, 'sh:deactivated'),
                // Value range constraints
                'minExclusive' => $this->getResourceValue($propertyShape, 'sh:minExclusive'),
                'minInclusive' => $this->getResourceValue($propertyShape, 'sh:minInclusive'),
                'maxExclusive' => $this->getResourceValue($propertyShape, 'sh:maxExclusive'),
                'maxInclusive' => $this->getResourceValue($propertyShape, 'sh:maxInclusive'),
                // Value constraints
                'hasValue' => $this->getResourceValue($propertyShape, 'sh:hasValue'),
                'in' => $this->extractRdfList($propertyShape, 'sh:in'),
                'languageIn' => $this->extractRdfList($propertyShape, 'sh:languageIn'),
                'uniqueLang' => $this->getResourceValue($propertyShape, 'sh:uniqueLang'),
                // Property pair constraints
                'equals' => $this->getResourceValue($propertyShape, 'sh:equals'),
                'disjoint' => $this->getResourceValue($propertyShape, 'sh:disjoint'),
                'lessThan' => $this->getResourceValue($propertyShape, 'sh:lessThan'),
                'lessThanOrEquals' => $this->getResourceValue($propertyShape, 'sh:lessThanOrEquals'),
                // Qualified value shapes
                'qualifiedValueShape' => $this->getResourceValue($propertyShape, 'sh:qualifiedValueShape'),
                'qualifiedMinCount' => $this->getResourceValue($propertyShape, 'sh:qualifiedMinCount'),
                'qualifiedMaxCount' => $this->getResourceValue($propertyShape, 'sh:qualifiedMaxCount'),
                'qualifiedValueShapesDisjoint' => $this->getResourceValue($propertyShape, 'sh:qualifiedValueShapesDisjoint'),
                // Logical constraints
                'sh_or' => $this->extractLogicalConstraintWithClasses($propertyShape, 'sh:or'),
                'sh_and' => $this->extractLogicalConstraintWithClasses($propertyShape, 'sh:and'),
                'sh_xone' => $this->extractLogicalConstraintWithClasses($propertyShape, 'sh:xone'),
                // SPARQL constraints
                'sparql_constraints' => $this->extractSparqlConstraints($propertyShape),
            ];

            // For predicate paths (string), use existing check; for complex paths (array), always include
            $pathValue = $property['path'];
            if (! empty($pathValue)) {
                // Use strict filter to preserve falsy values like '0' and 'false'
                $properties[] = array_filter($property, fn ($v) => $v !== null && $v !== '' && $v !== []);
            }
        }

        return $properties;
    }

    protected function extractShapeConstraints($resource): array
    {
        $constraints = [];

        $constraintProperties = [
            'sh:minCount', 'sh:maxCount', 'sh:minLength', 'sh:maxLength',
            'sh:pattern', 'sh:flags', 'sh:datatype', 'sh:nodeKind', 'sh:class', 'sh:node',
            'sh:minInclusive', 'sh:maxInclusive', 'sh:minExclusive', 'sh:maxExclusive',
            'sh:hasValue', 'sh:languageIn', 'sh:uniqueLang',
            'sh:equals', 'sh:disjoint', 'sh:lessThan', 'sh:lessThanOrEquals',
            'sh:qualifiedValueShape', 'sh:qualifiedMinCount', 'sh:qualifiedMaxCount',
            'sh:qualifiedValueShapesDisjoint',
            'sh:closed',
        ];

        foreach ($constraintProperties as $property) {
            $value = $this->getResourceValue($resource, $property);
            if ($value !== null) {
                $key = str_replace('sh:', '', $property);
                $constraints[$key] = $value;
            }
        }

        // Extract list-based constraints
        $inValues = $this->extractRdfList($resource, 'sh:in');
        if ($inValues !== null) {
            $constraints['in'] = $inValues;
        }

        $languageIn = $this->extractRdfList($resource, 'sh:languageIn');
        if ($languageIn !== null) {
            $constraints['languageIn'] = $languageIn;
        }

        $ignoredProperties = $this->extractRdfList($resource, 'sh:ignoredProperties');
        if ($ignoredProperties !== null) {
            $constraints['ignoredProperties'] = $ignoredProperties;
        }

        return $constraints;
    }

    /**
     * Utility methods
     */
    // Note: extractLocalName() and extractCardinality() are provided by ShaclPropertyExtractor trait

    protected function getNamespaceFromUri(string $uri): string
    {
        if (strpos($uri, '#') !== false) {
            return substr($uri, 0, strrpos($uri, '#') + 1);
        }
        if (strpos($uri, '/') !== false) {
            return substr($uri, 0, strrpos($uri, '/') + 1);
        }

        return '';
    }

    protected function isCustomNamespace(string $namespace, array $prefixes): bool
    {
        // Known standard namespaces that we don't consider "custom"
        $standardNamespaces = [
            'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'http://www.w3.org/2000/01/rdf-schema#',
            'http://www.w3.org/2002/07/owl#',
            'http://www.w3.org/2001/XMLSchema#',
            'http://purl.org/dc/elements/1.1/',
            'http://purl.org/dc/terms/',
            'http://www.w3.org/2004/02/skos/core#',
            'http://www.w3.org/ns/shacl#',
            'http://xmlns.com/foaf/0.1/',
        ];

        return ! in_array(rtrim($namespace, '#/'), array_map(fn ($ns) => rtrim($ns, '#/'), $standardNamespaces));
    }

    protected function isFunctionalProperty(array $propertyShape): bool
    {
        return isset($propertyShape['maxCount']) && (int) $propertyShape['maxCount'] === 1;
    }

    /**
     * Map SHACL severity URIs to simple string values.
     * Built-in severities map to canonical names; custom IRIs are preserved as-is.
     */
    protected function mapSeverity(?string $shaclSeverity): string
    {
        if (empty($shaclSeverity)) {
            return 'violation';
        }

        // Handle both URI and simple forms for built-in severities
        return match ($shaclSeverity) {
            'sh:Violation', 'http://www.w3.org/ns/shacl#Violation' => 'violation',
            'sh:Warning', 'http://www.w3.org/ns/shacl#Warning' => 'warning',
            'sh:Info', 'http://www.w3.org/ns/shacl#Info' => 'info',
            default => $shaclSeverity
        };
    }

    /**
     * Check if a property shape has significant constraints worth extracting
     */
    protected function hasSignificantConstraints(array $propertyShape): bool
    {
        // Properties with custom names or descriptions are always significant
        if (! empty($propertyShape['name']) || ! empty($propertyShape['description'])) {
            return true;
        }

        // Properties with specific constraints are significant
        $significantConstraints = [
            'minCount', 'maxCount', 'minLength', 'maxLength', 'pattern',
            'datatype', 'class', 'node', 'nodeKind',
        ];

        foreach ($significantConstraints as $constraint) {
            if (! empty($propertyShape[$constraint])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a meaningful description for a property based on its constraints
     */
    protected function generatePropertyDescription(array $propertyShape, array $shape): string
    {
        $constraints = [];

        if (! empty($propertyShape['datatype'])) {
            $datatype = $this->extractLocalName($propertyShape['datatype']);
            $constraints[] = "datatype: {$datatype}";
        }

        if (! empty($propertyShape['minCount']) || ! empty($propertyShape['maxCount'])) {
            $cardinality = $this->extractCardinality($propertyShape);
            $constraints[] = "cardinality: {$cardinality}";
        }

        if (! empty($propertyShape['pattern'])) {
            $constraints[] = 'pattern constraint';
        }

        if (! empty($propertyShape['class'])) {
            $className = $this->extractLocalName($propertyShape['class']);
            $constraints[] = "class: {$className}";
        }

        $description = 'Property constraint';
        if (! empty($constraints)) {
            $description .= ' ('.implode(', ', $constraints).')';
        }

        return $description;
    }

    /**
     * Determine the type of a SHACL shape (NodeShape or PropertyShape)
     */
    protected function getShapeType(array $shape): string
    {
        $metadata = $shape['metadata'] ?? [];
        $types = $metadata['types'] ?? [];

        // Check the RDF types to determine if it's a NodeShape or PropertyShape
        if (in_array('http://www.w3.org/ns/shacl#NodeShape', $types)) {
            return 'NodeShape';
        }

        if (in_array('http://www.w3.org/ns/shacl#PropertyShape', $types)) {
            return 'PropertyShape';
        }

        // Fallback: if it has target mechanisms, likely a NodeShape
        if (! empty($shape['target_class']) || ! empty($shape['target_node']) ||
            ! empty($shape['target_subjects_of']) || ! empty($shape['target_objects_of'])) {
            return 'NodeShape';
        }

        // If it has a sh:path, likely a PropertyShape
        if (! empty($shape['target_property']) || ! empty($shape['property_shapes'])) {
            return 'PropertyShape';
        }

        // Default to NodeShape
        return 'NodeShape';
    }

    /**
     * Determine if a NodeShape represents a semantic class or a validation constraint
     */
    protected function isSemanticShape(array $shape): bool
    {
        // Semantic shapes should have meaningful labels and descriptions
        // and define the structure of actual entities

        // 1. Must have a meaningful label (not just local name)
        $hasLabel = ! empty($shape['label']);

        // 2. Must have a meaningful description (not generic SHACL description)
        $hasDescription = ! empty($shape['description']) &&
                         ! str_contains($shape['description'], 'SHACL shape constraining') &&
                         ! str_contains($shape['description'], 'SHACL node shape definition');

        // 3. Semantic shapes typically have property shapes (structure definition)
        $hasPropertyShapes = ! empty($shape['property_shapes']) && count($shape['property_shapes']) > 0;

        // 4. Validation shapes often have SPARQL constraints but few/no property shapes
        $hasConstraints = ! empty($shape['constraints']);

        // A shape is semantic if:
        // - It has both label and description (meaningful documentation)
        // - AND it has property shapes (defines structure)
        // OR
        // - It has label and description but no complex constraints (simple semantic definition)

        $isDocumented = $hasLabel && $hasDescription;

        if ($isDocumented && $hasPropertyShapes) {
            return true; // Well-documented shape with structure
        }

        if ($isDocumented && ! $hasConstraints) {
            return true; // Simple documented shape without complex validation
        }

        // If it's only about constraints/validation without proper documentation, it's not semantic
        return false;
    }

    /**
     * Extract a SHACL property path from a property shape resource.
     * Handles predicate paths (IRIs) and complex paths (sequence, alternative, inverse, wildcards).
     * Returns a string for simple predicate paths, or an array for complex paths.
     *
     * @return string|array|null
     */
    protected function extractPropertyPath($propertyShape)
    {
        $pathResource = $propertyShape->get('sh:path');
        if (! $pathResource) {
            return null;
        }

        return $this->parsePathResource($pathResource);
    }

    /**
     * Recursively parse a path resource into a structured representation.
     *
     * @return string|array|null
     */
    protected function parsePathResource($pathResource)
    {
        if (! $pathResource) {
            return null;
        }

        // Simple predicate path: the path is an IRI
        if (method_exists($pathResource, 'getUri') && $pathResource->getUri()
            && ! str_starts_with($pathResource->getUri(), '_:')) {
            return $pathResource->getUri();
        }

        // Complex path: blank node with specific sh: property
        // InversePath
        $inverse = $pathResource->get('sh:inversePath');
        if ($inverse) {
            return [
                'type' => 'inverse',
                'path' => $this->parsePathResource($inverse),
            ];
        }

        // AlternativePath
        $alt = $pathResource->get('sh:alternativePath');
        if ($alt) {
            $members = $this->extractPathList($alt);

            return [
                'type' => 'alternative',
                'paths' => $members,
            ];
        }

        // ZeroOrMorePath
        $zeroOrMore = $pathResource->get('sh:zeroOrMorePath');
        if ($zeroOrMore) {
            return [
                'type' => 'zeroOrMore',
                'path' => $this->parsePathResource($zeroOrMore),
            ];
        }

        // OneOrMorePath
        $oneOrMore = $pathResource->get('sh:oneOrMorePath');
        if ($oneOrMore) {
            return [
                'type' => 'oneOrMore',
                'path' => $this->parsePathResource($oneOrMore),
            ];
        }

        // ZeroOrOnePath
        $zeroOrOne = $pathResource->get('sh:zeroOrOnePath');
        if ($zeroOrOne) {
            return [
                'type' => 'zeroOrOne',
                'path' => $this->parsePathResource($zeroOrOne),
            ];
        }

        // SequencePath: a blank node that is an RDF list (has rdf:first)
        $first = $pathResource->get('rdf:first');
        if ($first) {
            $members = $this->extractPathList($pathResource);
            if (count($members) >= 2) {
                return [
                    'type' => 'sequence',
                    'paths' => $members,
                ];
            }
        }

        // Fallback: try to get URI anyway (some parsers may present paths differently)
        if (method_exists($pathResource, 'getUri') && $pathResource->getUri()) {
            return $pathResource->getUri();
        }

        return null;
    }

    /**
     * Extract members of a path list (RDF list of path resources)
     */
    protected function extractPathList($listResource): array
    {
        $members = [];
        $current = $listResource;

        while ($current) {
            if (method_exists($current, 'getUri') &&
                $current->getUri() === 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil') {
                break;
            }

            $first = $current->get('rdf:first');
            if ($first) {
                $parsed = $this->parsePathResource($first);
                if ($parsed !== null) {
                    $members[] = $parsed;
                }
            }

            $rest = $current->get('rdf:rest');
            if (! $rest) {
                break;
            }
            $current = $rest;
        }

        return $members;
    }

    /**
     * Extract RDF list values (for sh:in, sh:languageIn, sh:ignoredProperties)
     */
    protected function extractRdfList($resource, string $property): ?array
    {
        $listResource = $resource->get($property);
        if (! $listResource) {
            return null;
        }

        $values = [];
        $current = $listResource;

        // Traverse the RDF list (rdf:first, rdf:rest pattern)
        while ($current && $current->getUri() !== 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil') {
            $first = $current->get('rdf:first');
            if ($first) {
                if (method_exists($first, 'getUri') && $first->getUri()) {
                    $values[] = $first->getUri();
                } else {
                    $values[] = (string) $first;
                }
            }

            $rest = $current->get('rdf:rest');
            if (! $rest) {
                break;
            }
            $current = $rest;
        }

        return ! empty($values) ? $values : null;
    }

    /**
     * Extract logical constraint (sh:and, sh:or, sh:not, sh:xone)
     * Returns array of shape references
     */
    protected function extractLogicalConstraint($resource, string $property): ?array
    {
        $logicalResource = $resource->get($property);
        if (! $logicalResource) {
            return null;
        }

        $shapeRefs = [];

        // For sh:not, it's a single shape reference
        if ($property === 'sh:not') {
            if (method_exists($logicalResource, 'getUri') && $logicalResource->getUri()) {
                return [
                    'type' => 'reference',
                    'uri' => $logicalResource->getUri(),
                ];
            }

            return [
                'type' => 'inline',
                'constraints' => $this->extractShapeConstraints($logicalResource),
            ];
        }

        // For sh:and, sh:or, sh:xone, it's a list of shapes
        $shapeList = $this->extractRdfList($resource, $property);
        if ($shapeList) {
            foreach ($shapeList as $shapeUri) {
                $shapeRefs[] = [
                    'type' => 'reference',
                    'uri' => $shapeUri,
                ];
            }
        }

        return ! empty($shapeRefs) ? $shapeRefs : null;
    }

    // Note: extractRangeFromShape() and determinePropertyTypeFromShape()
    // are provided by the ShaclPropertyExtractor trait

    /**
     * Extract logical constraint with inline class constraints
     * Specifically handles sh:or, sh:and, sh:xone with inline blank nodes containing sh:class
     */
    protected function extractLogicalConstraintWithClasses($resource, string $property): ?array
    {
        $logicalResource = $resource->get($property);
        if (! $logicalResource) {
            return null;
        }

        $constraints = [];

        // Extract the RDF list but get the actual resource objects, not just URIs
        $listItems = [];
        if (method_exists($logicalResource, 'getUri')) {
            // It's a list - traverse it
            $currentNode = $logicalResource;
            while ($currentNode && ! $currentNode->isA('rdf:nil')) {
                $first = $currentNode->get('rdf:first');
                if ($first) {
                    $listItems[] = $first;
                }
                $currentNode = $currentNode->get('rdf:rest');
            }
        }

        // Extract constraints from each item in the list
        foreach ($listItems as $item) {
            $constraint = [];

            // Extract sh:class if present
            $class = $this->getResourceValue($item, 'sh:class');
            if ($class) {
                $constraint['class'] = $class;
            }

            // Extract sh:datatype if present
            $datatype = $this->getResourceValue($item, 'sh:datatype');
            if ($datatype) {
                $constraint['datatype'] = $datatype;
            }

            // Extract sh:nodeKind if present
            $nodeKind = $this->getResourceValue($item, 'sh:nodeKind');
            if ($nodeKind) {
                $constraint['nodeKind'] = $nodeKind;
            }

            // If the item is just a URI reference, store it
            if (empty($constraint) && method_exists($item, 'getUri') && $item->getUri()) {
                $constraint['reference'] = $item->getUri();
            }

            if (! empty($constraint)) {
                $constraints[] = $constraint;
            }
        }

        return ! empty($constraints) ? $constraints : null;
    }

    /**
     * Extract SPARQL constraints (sh:sparql, sh:select, sh:ask)
     * Returns array of SPARQL constraint definitions or null
     */
    protected function extractSparqlConstraints($resource): ?array
    {
        $sparqlConstraints = [];

        // Extract sh:sparql constraints (can be multiple)
        $sparqlResources = $resource->all('sh:sparql');
        foreach ($sparqlResources as $sparqlResource) {
            $constraint = $this->extractSingleSparqlConstraint($sparqlResource);
            if ($constraint) {
                $sparqlConstraints[] = $constraint;
            }
        }

        // Check for direct sh:select on the shape (property-level constraint)
        $selectQuery = $this->getResourceValue($resource, 'sh:select');
        if ($selectQuery) {
            $sparqlConstraints[] = [
                'type' => 'select',
                'query' => $selectQuery,
                'prefixes' => $this->extractPrefixesFromSparqlConstraint($resource),
                'message' => $this->getResourceValue($resource, 'sh:message'),
            ];
        }

        // Check for direct sh:ask on the shape (property-level constraint)
        $askQuery = $this->getResourceValue($resource, 'sh:ask');
        if ($askQuery) {
            $sparqlConstraints[] = [
                'type' => 'ask',
                'query' => $askQuery,
                'prefixes' => $this->extractPrefixesFromSparqlConstraint($resource),
                'message' => $this->getResourceValue($resource, 'sh:message'),
            ];
        }

        return ! empty($sparqlConstraints) ? $sparqlConstraints : null;
    }

    /**
     * Extract a single SPARQL constraint from a sh:sparql resource
     */
    protected function extractSingleSparqlConstraint($sparqlResource): ?array
    {
        // Get the constraint type (SPARQLSelectValidator or SPARQLAskValidator)
        $types = $sparqlResource->all('rdf:type');
        $constraintType = null;

        foreach ($types as $type) {
            if (! method_exists($type, 'getUri')) {
                continue;
            }
            $typeUri = $type->getUri();
            if ($typeUri === 'http://www.w3.org/ns/shacl#SPARQLSelectValidator') {
                $constraintType = 'select';
                break;
            } elseif ($typeUri === 'http://www.w3.org/ns/shacl#SPARQLAskValidator') {
                $constraintType = 'ask';
                break;
            }
        }

        // Extract sh:select or sh:ask query
        $query = null;
        if ($constraintType === 'select' || ! $constraintType) {
            $query = $this->getResourceValue($sparqlResource, 'sh:select');
            if ($query) {
                $constraintType = 'select';
            }
        }

        if (! $query && ($constraintType === 'ask' || ! $constraintType)) {
            $query = $this->getResourceValue($sparqlResource, 'sh:ask');
            if ($query) {
                $constraintType = 'ask';
            }
        }

        if (! $query) {
            return null;
        }

        return [
            'type' => $constraintType,
            'query' => $query,
            'prefixes' => $this->extractPrefixesFromSparqlConstraint($sparqlResource),
            'message' => $this->getResourceValue($sparqlResource, 'sh:message'),
            'deactivated' => $this->getResourceValue($sparqlResource, 'sh:deactivated'),
        ];
    }

    /**
     * Extract prefix declarations from a SPARQL constraint
     */
    protected function extractPrefixesFromSparqlConstraint($resource): ?array
    {
        $prefixes = [];

        // Extract sh:prefixes (array of sh:PrefixDeclaration)
        $prefixDeclarations = $resource->all('sh:prefixes');
        foreach ($prefixDeclarations as $prefixDecl) {
            $prefix = $this->getResourceValue($prefixDecl, 'sh:prefix');
            $namespace = $this->getResourceValue($prefixDecl, 'sh:namespace');

            if ($prefix && $namespace) {
                $prefixes[$prefix] = $namespace;
            }
        }

        // Also check for sh:prefix and sh:namespace directly on the resource
        $directPrefix = $this->getResourceValue($resource, 'sh:prefix');
        $directNamespace = $this->getResourceValue($resource, 'sh:namespace');
        if ($directPrefix && $directNamespace) {
            $prefixes[$directPrefix] = $directNamespace;
        }

        return ! empty($prefixes) ? $prefixes : null;
    }
}
