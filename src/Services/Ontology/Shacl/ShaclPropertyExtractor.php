<?php

namespace App\Services\Ontology\Shacl;

use App\Support\RdfHelper;

/**
 * Shared logic for extracting properties from SHACL shapes
 * Used by both ShaclParser and ShaclShapeProcessor to avoid duplication
 */
trait ShaclPropertyExtractor
{
    /**
     * Extract range (datatype or class) from SHACL property shape
     * Returns array of range values
     */
    protected function extractRangeFromShape(array $propertyShape): array
    {
        $ranges = [];

        // Check for direct datatype
        if (! empty($propertyShape['datatype'])) {
            $ranges[] = $propertyShape['datatype'];
        }

        // Check for direct class
        if (! empty($propertyShape['class'])) {
            $ranges[] = $propertyShape['class'];
        }

        // Check for classes within logical operators (sh:or, sh:and, sh:xone)
        $logicalConstraints = ['sh_or', 'sh_and', 'sh_xone'];
        foreach ($logicalConstraints as $logicalKey) {
            if (! empty($propertyShape[$logicalKey]) && is_array($propertyShape[$logicalKey])) {
                foreach ($propertyShape[$logicalKey] as $constraint) {
                    if (! empty($constraint['class'])) {
                        $ranges[] = $constraint['class'];
                    }
                    if (! empty($constraint['datatype'])) {
                        $ranges[] = $constraint['datatype'];
                    }
                }
            }
        }

        // Remove duplicates
        return array_unique($ranges);
    }

    /**
     * Determine property type from SHACL shape constraints
     * Returns 'object' if the property references other resources, 'datatype' otherwise
     */
    protected function determinePropertyTypeFromShape(array $propertyShape): string
    {
        // Check direct class or node constraints
        if (! empty($propertyShape['class']) || ! empty($propertyShape['node'])) {
            return 'object';
        }

        // Check if nodeKind indicates IRI (object property)
        if (! empty($propertyShape['nodeKind'])) {
            $nodeKind = $propertyShape['nodeKind'];
            // If nodeKind is IRI or BlankNodeOrIRI, it's an object property
            if (str_contains($nodeKind, 'IRI') && ! str_contains($nodeKind, 'Literal')) {
                return 'object';
            }
        }

        // Check for class constraints within logical operators (sh:or, sh:and, sh:xone)
        $logicalConstraints = ['sh_or', 'sh_and', 'sh_xone'];
        foreach ($logicalConstraints as $logicalKey) {
            if (! empty($propertyShape[$logicalKey]) && is_array($propertyShape[$logicalKey])) {
                // Check if any constraint in the logical operator has a class
                foreach ($propertyShape[$logicalKey] as $constraint) {
                    if (! empty($constraint['class'])) {
                        return 'object';
                    }
                    // Also check nodeKind within logical constraints
                    if (! empty($constraint['nodeKind'])) {
                        $nodeKind = $constraint['nodeKind'];
                        if (str_contains($nodeKind, 'IRI') && ! str_contains($nodeKind, 'Literal')) {
                            return 'object';
                        }
                    }
                }
            }
        }

        // Default to datatype property
        return 'datatype';
    }

    /**
     * Extract cardinality constraints from SHACL property shape
     */
    protected function extractCardinality(array $propertyShape): ?string
    {
        $minCount = isset($propertyShape['minCount']) ? (int) $propertyShape['minCount'] : 0;
        $maxCount = isset($propertyShape['maxCount']) ? (int) $propertyShape['maxCount'] : null;

        // Format as min..max or specific values
        if ($minCount > 0 && $maxCount !== null) {
            if ($minCount === $maxCount) {
                return (string) $minCount; // Exactly n
            }

            return $minCount.'..'.$maxCount; // Range

        } elseif ($minCount > 0) {
            return $minCount.'..n'; // At least n
        } elseif ($maxCount !== null) {
            return '0..'.$maxCount; // At most n
        }

        return null; // No cardinality constraints
    }

    /**
     * Extract local name from URI
     */
    protected function extractLocalName(string $uri): string
    {
        return RdfHelper::extractLocalName($uri);
    }
}
