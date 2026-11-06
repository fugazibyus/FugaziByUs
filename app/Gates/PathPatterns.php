<?php

namespace App\Gates;

/**
 * Glob matching for repo paths, with the same meaning as the Guardrails check:
 * `**` crosses folders, `*` and `?` stay inside one folder.
 */
final class PathPatterns
{
    /**
     * Read globs from a file (one per line, `#` comments and blank lines ignored).
     *
     * @return list<string>
     */
    public static function fromFile(string $file): array
    {
        if (! is_readable($file)) {
            return [];
        }

        $lines = array_map(fn (string $line) => trim($line), file($file, FILE_IGNORE_NEW_LINES) ?: []);

        return array_values(array_filter($lines, fn (string $line) => $line !== '' && ! str_starts_with($line, '#')));
    }

    /**
     * The paths that match any of the globs.
     *
     * @param  list<string>  $paths
     * @param  list<string>  $globs
     * @return list<string>
     */
    public static function matching(array $paths, array $globs): array
    {
        $regexes = array_map(self::toRegex(...), $globs);

        return array_values(array_filter(
            $paths,
            fn (string $path) => array_any($regexes, fn (string $regex) => preg_match($regex, $path) === 1),
        ));
    }

    private static function toRegex(string $glob): string
    {
        $regex = '';

        foreach (preg_split('/(\*\*|\*|\?)/', $glob, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $regex .= match ($part) {
                '**' => '.*',
                '*' => '[^/]*',
                '?' => '[^/]',
                default => preg_quote($part, '#'),
            };
        }

        return '#^'.$regex.'$#';
    }
}
