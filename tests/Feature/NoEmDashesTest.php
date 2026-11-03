<?php

use Illuminate\Support\Facades\File;

/*
 * Em dashes were removed from the interface once and came straight back from
 * config/tripwires.php, because that sweep only covered views and markdown.
 * The descriptions there are rendered on /thesis and persisted to the
 * tripwires table, so they are interface text like any other.
 */

test('no em or en dashes in anything a visitor reads', function () {
    $roots = [
        base_path('resources/views'),
        base_path('resources/memo'),
        base_path('config'),
    ];

    $offenders = [];

    foreach ($roots as $root) {
        foreach (File::allFiles($root) as $file) {
            if (! in_array($file->getExtension(), ['php', 'md'], true)) {
                continue;
            }

            if (preg_match('/[\x{2013}\x{2014}]|&[mn]dash;/u', (string) File::get($file->getPathname()))) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }
    }

    foreach ([base_path('README.md'), base_path('HACKATHON.md')] as $doc) {
        if (File::exists($doc) && preg_match('/[\x{2013}\x{2014}]|&[mn]dash;/u', (string) File::get($doc))) {
            $offenders[] = basename($doc);
        }
    }

    expect($offenders)->toBe([]);
});
