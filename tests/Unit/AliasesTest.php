<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserRdf\RdfParser;
use Youri\vandenBogert\Software\ParserShacl\ShaclParser;

/*
 * Run the deprecation-triggering subprocess once before all tests in this file.
 * The result is stored in a file-scoped variable and copied to $this in beforeEach
 * so individual tests can access it. This avoids the static $cache anti-pattern
 * which persists for the entire PHP process lifetime and can mask test failures.
 */
$deprecationSubprocessResult = null;

beforeAll(function () use (&$deprecationSubprocessResult) {
    $projectRoot = dirname(__DIR__, 2);
    $script = <<<'PHP'
<?php
if (!isset($argv[1])) {
    echo json_encode(['error' => 'Missing project root argument']);
    exit(1);
}
$deprecations = [];
set_error_handler(function (int $errno, string $errstr) use (&$deprecations) {
    if ($errno === E_USER_DEPRECATED) {
        $deprecations[] = $errstr;
    }
    return true;
});
require $argv[1] . '/vendor/autoload.php';
// Trigger alias by referencing old namespace class
class_exists('App\Services\Ontology\Parsers\ShaclParser');
echo json_encode($deprecations);
PHP;
    $tempFile = tempnam(sys_get_temp_dir(), 'alias_test_');
    if ($tempFile === false) {
        throw new \RuntimeException('Failed to create temp file');
    }
    file_put_contents($tempFile, $script);
    $rawOutput = shell_exec('php ' . escapeshellarg($tempFile) . ' ' . escapeshellarg($projectRoot) . ' 2>&1');
    unlink($tempFile);

    if ($rawOutput === null) {
        throw new \RuntimeException('Subprocess failed to execute');
    }

    $deprecationSubprocessResult = json_decode($rawOutput, true) ?? [];
});

beforeEach(function () use (&$deprecationSubprocessResult) {
    $this->deprecations = $deprecationSubprocessResult;
});

describe('class_alias bridge', function () {

    describe('alias resolution', function () {
        it('resolves ShaclParser from old namespace', function () {
            expect(class_exists('App\Services\Ontology\Parsers\ShaclParser'))->toBeTrue();
        });
    });

    describe('instanceof compatibility', function () {
        it('new ShaclParser is instanceof old namespace name', function () {
            $parser = new ShaclParser();
            expect($parser)->toBeInstanceOf('App\Services\Ontology\Parsers\ShaclParser');
        });

        it('old namespace resolves to same class as new namespace', function () {
            $oldReflection = new \ReflectionClass('App\Services\Ontology\Parsers\ShaclParser');
            $newReflection = new \ReflectionClass(ShaclParser::class);
            expect($oldReflection->getName())->toBe($newReflection->getName());
        });

        it('preserves RdfParser inheritance through alias', function () {
            $old = new \App\Services\Ontology\Parsers\ShaclParser();
            expect($old)->toBeInstanceOf(RdfParser::class);
        });
    });

    describe('deprecation warnings', function () {
        it('triggers E_USER_DEPRECATED when old ShaclParser class is referenced', function () {
            expect($this->deprecations)->toBeArray()->toHaveCount(1);
        });

        it('deprecation message contains old and new FQCN', function () {
            expect($this->deprecations[0])
                ->toContain('App\Services\Ontology\Parsers\ShaclParser')
                ->toContain('Youri\vandenBogert\Software\ParserShacl\ShaclParser');
        });

        it('deprecation message mentions v2.0 removal', function () {
            expect($this->deprecations[0])->toContain('v2.0');
        });

        it('does NOT trigger deprecation at autoload time', function () {
            $projectRoot = dirname(__DIR__, 2);
            $script = <<<'PHP'
<?php
if (!isset($argv[1])) {
    echo json_encode(['error' => 'Missing project root argument']);
    exit(1);
}
$deprecations = [];
set_error_handler(function (int $errno, string $errstr) use (&$deprecations) {
    if ($errno === E_USER_DEPRECATED) {
        $deprecations[] = $errstr;
    }
    return true;
});
require $argv[1] . '/vendor/autoload.php';
// Do NOT reference any old namespace classes
echo json_encode($deprecations);
PHP;
            $tempFile = tempnam(sys_get_temp_dir(), 'alias_test_');
            if ($tempFile === false) {
                throw new \RuntimeException('Failed to create temp file');
            }
            file_put_contents($tempFile, $script);
            $rawOutput = shell_exec('php ' . escapeshellarg($tempFile) . ' ' . escapeshellarg($projectRoot) . ' 2>&1');
            unlink($tempFile);
            expect($rawOutput)->not->toBeNull('Subprocess failed to execute');
            $output = $rawOutput ?? '[]';

            $deprecations = json_decode($output, true) ?? [];
            expect($deprecations)->toBeArray()->toHaveCount(0);
        });
    });

    describe('no aliases for internal classes', function () {
        it('does not eagerly alias ShaclShapeProcessor', function () {
            expect(class_exists('App\Services\Ontology\Shacl\ShaclShapeProcessor', false))->toBeFalse();
        });

        it('does not eagerly alias ShaclPropertyExtractor', function () {
            expect(class_exists('App\Services\Ontology\Shacl\ShaclPropertyExtractor', false))->toBeFalse();
        });
    });

    describe('no aliases for parser-core classes', function () {
        it('does not eagerly alias OntologyParserInterface (parser-core owns that)', function () {
            // OntologyParserInterface alias is owned by parser-core, not parser-shacl
            expect(class_exists('App\Services\Ontology\Parsers\OntologyParserInterface', false))->toBeFalse();
        });

        it('does not eagerly alias ParsedRdf (parser-core owns that)', function () {
            // ParsedRdf alias is owned by parser-core, not parser-shacl
            expect(class_exists('App\Services\Ontology\ParsedRdf', false))->toBeFalse();
        });
    });

    describe('behavioral equivalence', function () {
        it('aliased ShaclParser parses SHACL content identically', function () {
            $old = new \App\Services\Ontology\Parsers\ShaclParser();
            $new = new ShaclParser();

            $content = '@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .';

            $oldResult = $old->parse($content);
            $newResult = $new->parse($content);

            expect($oldResult->shapes)->toBe($newResult->shapes);
        });
    });
});
