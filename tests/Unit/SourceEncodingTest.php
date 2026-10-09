<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Evita textos con acentos mal codificados (UTF-8 leído como Latin-1),
 * por ejemplo "cÃ©dula" en lugar de "cédula".
 */
class SourceEncodingTest extends TestCase
{
    public function test_application_source_has_no_double_encoded_accents(): void
    {
        $offenders = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app'));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            foreach (file($file->getPathname()) as $number => $line) {
                if (preg_match('/Ã[\x{80}-\x{BF}]|Â[\x{80}-\x{BF}]/u', $line)) {
                    $offenders[] = $file->getFilename().':'.($number + 1);
                }
            }
        }

        $this->assertSame([], $offenders, 'Textos con acentos mal codificados: '.implode(', ', $offenders));
    }
}
