<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

/**
 * @return list<string>
 */
function filumKeys(string $locale): array
{
    /** @var array<string, mixed> $translations */
    $translations = require __DIR__."/../../lang/{$locale}/filum.php";

    $keys = array_keys(Arr::dot($translations));
    sort($keys);

    return $keys;
}

it('translates every key in both locales', function (): void {
    expect(filumKeys('ka'))->toBe(filumKeys('en'));
});

it('leaves nothing in Georgian untranslated', function (): void {
    /** @var array<string, mixed> $en */
    $en = require __DIR__.'/../../lang/en/filum.php';
    /** @var array<string, mixed> $ka */
    $ka = require __DIR__.'/../../lang/ka/filum.php';

    // Keys whose value is legitimately identical across locales would be listed
    // here. There are none: every string differs, which is what "shipped
    // complete, not stubbed" means.
    $identical = array_keys(array_intersect_assoc(Arr::dot($en), Arr::dot($ka)));

    expect($identical)->toBe([]);
});

it('resolves Georgian strings through the package namespace', function (): void {
    app()->setLocale('ka');

    expect(__('filum::filum.composer.send'))->toBe('გაგზავნა')
        ->and(__('filum::filum.sidebar.online'))->toBe('ონლაინ');
});

it('falls back to English for an unknown locale', function (): void {
    app()->setLocale('fr');

    expect(__('filum::filum.composer.send'))->toBe('Send');
});

it('keeps placeholders identical between locales', function (): void {
    /** @var array<string, mixed> $en */
    $en = require __DIR__.'/../../lang/en/filum.php';
    /** @var array<string, mixed> $ka */
    $ka = require __DIR__.'/../../lang/ka/filum.php';

    foreach (Arr::dot($en) as $key => $english) {
        $georgian = Arr::get(Arr::dot($ka), $key);

        preg_match_all('/:(\w+)/', is_string($english) ? $english : '', $inEnglish);
        preg_match_all('/:(\w+)/', is_string($georgian) ? $georgian : '', $inGeorgian);

        $expected = $inEnglish[1];
        $actual = $inGeorgian[1];
        sort($expected);
        sort($actual);

        expect($actual)->toBe($expected, "Placeholders differ for {$key}");
    }
});
