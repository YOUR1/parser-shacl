<?php

declare(strict_types=1);

namespace Youri\vandenBogert\Software\ParserShacl;

use Youri\vandenBogert\Software\ParserCore\Contracts\RdfFormatHandlerInterface;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedOntology;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdf\RdfParser;
use Youri\vandenBogert\Software\ParserShacl\Extractors\ShaclPropertyAnalyzer;
use Youri\vandenBogert\Software\ParserShacl\Extractors\ShaclShapeProcessor;

/**
 * SHACL Parser - Extends RdfParser with SHACL-specific post-processing.
 *
 * Inherits the full RDF parsing pipeline (format handlers, extractors)
 * and adds SHACL-specific enhancement via processShaclFeatures().
 *
 * NOT final: designed for potential extension.
 */
class ShaclParser extends RdfParser
{
    private readonly ShaclShapeProcessor $shaclShapeProcessor;
    private readonly ShaclPropertyAnalyzer $shaclPropertyAnalyzer;

    public function __construct()
    {
        parent::__construct();
        $this->shaclShapeProcessor = new ShaclShapeProcessor();
        $this->shaclPropertyAnalyzer = new ShaclPropertyAnalyzer();
    }

    protected function buildParsedOntology(
        ParsedRdf $parsedRdf,
        RdfFormatHandlerInterface $handler,
        string $content,
        array $options = [],
    ): ParsedOntology {
        $base = parent::buildParsedOntology($parsedRdf, $handler, $content, $options);

        return $this->processShaclFeatures($base, $parsedRdf);
    }

    /**
     * Apply SHACL-specific post-processing to the base RDF parse result.
     *
     * Stories 6.2-6.3 populate this method with:
     * - ShaclShapeProcessor (node shapes, target declarations)
     * - ShaclPropertyAnalyzer (property shapes, constraints) -- Story 6.3
     */
    protected function processShaclFeatures(
        ParsedOntology $base,
        ParsedRdf $parsedRdf,
    ): ParsedOntology {
        $enhancedShapes = $this->shaclShapeProcessor->extractNodeShapes($parsedRdf);

        // Enrich node shapes with property shape analysis
        $enhancedShapes = $this->shaclPropertyAnalyzer->extractPropertyShapes($parsedRdf, $enhancedShapes);

        // Merge with base shapes from RdfParser's ShapeExtractor
        $mergedShapes = array_merge($base->shapes, $enhancedShapes);

        // Construct NEW ParsedOntology since it's final with readonly properties
        return new ParsedOntology(
            classes: $base->classes,
            properties: $base->properties,
            prefixes: $base->prefixes,
            shapes: $mergedShapes,
            restrictions: $base->restrictions,
            metadata: $base->metadata,
            rawContent: $base->rawContent,
            graphs: $base->graphs,
        );
    }
}
