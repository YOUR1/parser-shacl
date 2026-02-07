<?php

namespace App\Support;

/**
 * Helper utilities for working with RDF IRIs and URIs
 */
class RdfHelper
{
    /**
     * Extract the local name (fragment or last path segment) from an RDF IRI
     *
     * Examples:
     * - "http://example.org/ontology#Person" → "Person"
     * - "http://example.org/ontology/Person" → "Person"
     * - "urn:example:person" → "person"
     *
     * @param  string  $iri  The IRI to extract from
     * @return string The local name, or the original IRI if no delimiter found
     */
    public static function extractLocalName(string $iri): string
    {
        // Try fragment identifier first (after #), then path segment (after /)
        if (preg_match('/[#\/]([^#\/]+)$/', $iri, $matches)) {
            return $matches[1];
        }

        // Handle URN format (urn:namespace:localname)
        if (str_starts_with($iri, 'urn:') && preg_match('/:([^:]+)$/', $iri, $matches)) {
            return $matches[1];
        }

        return $iri;
    }

    /**
     * Extract the namespace (everything before the local name) from an RDF IRI
     *
     * @param  string  $iri  The IRI to extract from
     * @return string The namespace portion of the IRI
     */
    public static function extractNamespace(string $iri): string
    {
        $localName = self::extractLocalName($iri);

        if ($localName === $iri) {
            return '';
        }

        return substr($iri, 0, -strlen($localName));
    }

    /**
     * Convert a local name to a human-readable format
     *
     * Examples:
     * - "firstName" → "First Name"
     * - "has_member" → "Has Member"
     *
     * @param  string  $localName  The local name to humanize
     * @return string The humanized name
     */
    public static function humanizeLocalName(string $localName): string
    {
        // Split camelCase
        $spaced = preg_replace('/([a-z])([A-Z])/', '$1 $2', $localName);
        // Replace underscores with spaces
        $spaced = str_replace('_', ' ', $spaced);

        return ucwords(strtolower($spaced));
    }
}
