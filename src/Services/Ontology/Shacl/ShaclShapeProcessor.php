<?php

namespace App\Services\Ontology\Shacl;

/**
 * Processes SHACL shapes to extract classes and properties
 */
class ShaclShapeProcessor
{
    use ShaclPropertyExtractor;

    /**
     * Extract class definitions from SHACL shapes
     */
    public function extractClassesFromShapes(array $shapes): array
    {
        $classes = [];
        $classUris = [];

        foreach ($shapes as $shape) {
            $potentialClasses = $this->extractTargetClassesFromShape($shape);

            foreach ($potentialClasses as $classInfo) {
                $targetClass = $classInfo['uri'];

                // Avoid duplicates
                if (in_array($targetClass, $classUris)) {
                    continue;
                }
                $classUris[] = $targetClass;

                // Extract local name from URI of the TARGET CLASS, not the shape
                $localName = $this->extractLocalName($targetClass);

                // For SHACL-derived classes, use the URI from target class but labels from shape
                // The shape provides localized/domain-specific terminology for the target class
                $classes[] = [
                    'uri' => $targetClass,
                    'label' => $classInfo['label'] ?? $localName, // Shape label provides localization
                    'labels' => $shape['labels'] ?? [], // Preserve multilingual labels from shape
                    'description' => $classInfo['description'] ?? "Class constrained by SHACL shape {$this->extractLocalName($shape['uri'] ?? '')}",
                    'descriptions' => $shape['descriptions'] ?? [], // Preserve multilingual descriptions from shape
                    'parent_classes' => [],
                    'metadata' => [
                        'source' => 'shacl_target_class',
                        'shape_uri' => $shape['uri'] ?? null,
                        'shape_label' => $shape['label'] ?? null, // Preserve shape info in metadata
                        'shape_description' => $shape['description'] ?? null,
                        'targeting_mechanism' => $classInfo['targeting_mechanism'],
                        'target_property' => $classInfo['target_property'] ?? null,
                    ],
                ];
            }
        }

        return $classes;
    }

    /**
     * Extract target classes from a SHACL shape using only standard targeting mechanisms
     */
    protected function extractTargetClassesFromShape(array $shape): array
    {
        $targetClasses = [];

        // Primary mechanism: sh:targetClass
        if (! empty($shape['target_class'])) {
            // Use the shape's label and description for the target class
            // since the shape provides localized/domain-specific terminology
            $localName = $this->extractLocalName($shape['target_class']);

            $targetClasses[] = [
                'uri' => $shape['target_class'],
                'targeting_mechanism' => 'sh:targetClass',
                'label' => $shape['label'] ?? $localName, // Use shape label for localization
                'description' => $shape['description'] ?? null, // Use shape description
            ];
        }

        // Also consider well-documented shapes with other targeting mechanisms
        // but only if they have meaningful labels and descriptions (semantic shapes)
        if (! (empty($targetClasses) && $this->isSemanticShapeWithoutTargetClass($shape))) {
            return $targetClasses;
        }
        $localName = $this->extractLocalName($shape['uri']);

        $targetClasses[] = [
            'uri' => $shape['uri'], // Use the shape URI as the class URI for semantic shapes
            'targeting_mechanism' => $this->determinePrimaryTargetingMechanism($shape),
            'label' => $shape['label'] ?? $localName,
            'description' => $shape['description'] ?? 'Semantic class defined by SHACL shape',
        ];

        return $targetClasses;
    }

    /**
     * Check if this is a semantic shape without sh:targetClass that should be treated as a class
     */
    protected function isSemanticShapeWithoutTargetClass(array $shape): bool
    {
        // Must have a meaningful label and description
        $hasLabel = ! empty($shape['label']);
        $hasDescription = ! empty($shape['description']);

        // Must have other targeting mechanisms (targetObjectsOf, targetSubjectsOf, etc.)
        $hasOtherTargets = ! empty($shape['target_objects_of']) ||
                          ! empty($shape['target_subjects_of']) ||
                          ! empty($shape['target_node']);

        // Must have property shapes (structural definition)
        $hasPropertyShapes = ! empty($shape['property_shapes']) && count($shape['property_shapes']) > 0;

        // Should be well-documented and structural
        return $hasLabel && $hasDescription && $hasOtherTargets && $hasPropertyShapes;
    }

    /**
     * Determine the primary targeting mechanism for a shape
     */
    protected function determinePrimaryTargetingMechanism(array $shape): string
    {
        if (! empty($shape['target_objects_of'])) {
            return 'sh:targetObjectsOf';
        }
        if (! empty($shape['target_subjects_of'])) {
            return 'sh:targetSubjectsOf';
        }
        if (! empty($shape['target_node'])) {
            return 'sh:targetNode';
        }

        return 'unknown';
    }

    /**
     * Extract property definitions from SHACL shapes
     */
    public function extractPropertiesFromShapes(array $shapes): array
    {
        $properties = [];
        $propertyUris = [];

        foreach ($shapes as $shape) {
            // Extract properties from property shapes
            if (empty($shape['property_shapes'])) {
                continue;
            }
            foreach ($shape['property_shapes'] as $propertyShape) {
                if (! (! empty($propertyShape['path']) && ! in_array($propertyShape['path'], $propertyUris))) {
                    continue;
                }
                $propertyUris[] = $propertyShape['path'];

                $localName = $this->extractLocalName($propertyShape['path']);

                // Determine property type from constraints (including sh:or, sh:and, sh:xone)
                $propertyType = $this->determinePropertyTypeFromShape($propertyShape);

                $cardinality = $this->extractCardinality($propertyShape);

                $properties[] = [
                    'uri' => $propertyShape['path'],
                    // Use label from property shape if available (e.g., Dutch labels in SKOS-AP-NL), fallback to local name
                    'label' => $propertyShape['label'] ?? $localName,
                    'labels' => $propertyShape['labels'] ?? [], // Language-tagged labels from rdfs:label
                    'description' => $propertyShape['message'] ?? $propertyShape['description'] ?? null,
                    'descriptions' => $propertyShape['descriptions'] ?? [], // Language-tagged descriptions
                    'property_type' => $propertyType,
                    'domain' => ! empty($shape['target_class']) ? [$shape['target_class']] : [],
                    'range' => $this->extractRangeFromShape($propertyShape),
                    'cardinality' => $cardinality,
                    'is_functional' => ! empty($cardinality) && ($cardinality === '1' || str_ends_with($cardinality, '..1')),
                    'metadata' => [
                        'source' => 'shacl_property_shape',
                        'shape_constraints' => $propertyShape,
                    ],
                ];
            }
        }

        return $properties;
    }

    // Note: The following methods are provided by the ShaclPropertyExtractor trait:
    // - extractLocalName()
    // - extractCardinality()
    // - extractRangeFromShape()
    // - determinePropertyTypeFromShape()
}
