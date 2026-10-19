<?php

namespace App\Http\Controllers;

use App\Support\IconPacks\IconPacks;
use Symfony\Component\HttpFoundation\Response;

/** Streams one canonical icon recoloured for a pack. Public (marketing pages show these to visitors), read-only, cached for a year per version. */
class IconPackController extends Controller
{
    public function __invoke(string $pack, string $group, string $file): Response
    {
        abort_unless(IconPacks::exists($pack), 404);
        $dir = (array) config('icon_packs.dirs', []);
        abort_unless(isset($dir[$group]) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $file) === 1, 404);

        $path = public_path(trim((string) $dir[$group], '/').'/'.$file.'.svg');
        abort_unless(is_file($path), 404);

        $dark = str_ends_with($file, '-dark');
        $svg = IconPacks::recolor((string) file_get_contents($path), $pack, $dark ? 'dark' : 'light');
        $etag = '"'.md5($svg).'"';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
        ]);
    }
}
