<?php

declare(strict_types=1);

/**
 * Comprueba que translations/api.{ca,es,en,fr}.yaml tengan las mismas claves anidadas.
 * Uso: php scripts/verify-api-i18n-parity.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

/** @return array<string, string> */
function flattenYaml(array $node, string $prefix = ''): array
{
    $out = [];
    foreach ($node as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (\is_array($value) && [] !== $value && !array_is_list($value)) {
            $out += flattenYaml($value, $path);
        } else {
            $out[$path] = \is_scalar($value) ? (string) $value : json_encode($value);
        }
    }

    return $out;
}

$langs = ['ca', 'es', 'en', 'fr'];
$base = dirname(__DIR__) . '/translations';
$data = [];
foreach ($langs as $lang) {
    $path = $base . '/api.' . $lang . '.yaml';
    if (!is_file($path)) {
        fwrite(STDERR, "Falta archivo: {$path}\n");
        exit(1);
    }
    $parsed = Yaml::parseFile($path);
    if (!\is_array($parsed)) {
        fwrite(STDERR, "YAML inválido: {$path}\n");
        exit(1);
    }
    $data[$lang] = flattenYaml($parsed);
}

$allKeys = [];
foreach ($langs as $lang) {
    $allKeys = array_merge($allKeys, array_keys($data[$lang]));
}
$allKeys = array_values(array_unique($allKeys));
sort($allKeys);

$exit = 0;
foreach ($langs as $lang) {
    $missing = [];
    foreach ($allKeys as $k) {
        if (!\array_key_exists($k, $data[$lang])) {
            $missing[] = $k;
        }
    }
    if ($missing !== []) {
        fwrite(STDERR, "api.{$lang}.yaml faltan " . \count($missing) . " claves (ej.): " . implode(', ', \array_slice($missing, 0, 15)) . "\n");
        $exit = 1;
    }
}

if ($exit === 0) {
    echo 'api.*.yaml: OK, ' . \count($allKeys) . " claves aplanadas en 4 idiomas.\n";
}

exit($exit);
