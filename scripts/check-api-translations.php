<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

/**
 * @param array<string, mixed> $a
 * @return array<string, true>
 */
function flatten(array $a, string $p = ''): array
{
    $o = [];
    foreach ($a as $k => $v) {
        $nk = $p === '' ? (string) $k : $p.'.'.$k;
        if (\is_array($v) && !array_is_list($v)) {
            $o += flatten($v, $nk);
        } else {
            $o[$nk] = true;
        }
    }

    return $o;
}

$base = dirname(__DIR__) . '/translations';
$langs = ['ca', 'es', 'en', 'fr'];
$sets = [];
foreach ($langs as $L) {
    $path = $base . '/api.'.$L.'.yaml';
    $sets[$L] = array_keys(flatten(Yaml::parseFile($path)));
}

$all = [];
foreach ($langs as $L) {
    $all = array_merge($all, $sets[$L]);
}
$all = array_values(array_unique($all));
sort($all);

$failed = false;
foreach ($langs as $L) {
    $miss = array_values(array_diff($all, $sets[$L]));
    if ($miss !== []) {
        $failed = true;
        fwrite(STDERR, "[$L] missing ".count($miss)." keys\n");
        foreach (array_slice($miss, 0, 30) as $m) {
            fwrite(STDERR, "  - $m\n");
        }
    }
}

if ($failed) {
    exit(1);
}

echo 'api.*.yaml: '.count($all)." keys aligned for ".implode(', ', $langs)."\n";
