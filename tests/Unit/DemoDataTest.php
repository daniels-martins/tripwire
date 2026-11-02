<?php

use App\Support\DemoData;

test('seeded transaction hashes are recognisable as fabricated', function () {
    expect(DemoData::isDemoHash('demo-abc123'))->toBeTrue()
        ->and(DemoData::isDemoHash('7z1kE9WmBnAJ8rLq'))->toBeFalse()
        ->and(DemoData::isDemoHash(null))->toBeFalse();
});
