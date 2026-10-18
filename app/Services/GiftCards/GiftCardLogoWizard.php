<?php

namespace App\Services\GiftCards;

use App\Models\GiftCardBrandLogo;
use App\Models\GiftCardProduct;
use App\Services\AI\AnthropicClient;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Logo Wizard: every gift-card brand ends with a real, locally stored logo (never hot-linked, never AI-drawn).
 *
 * Source order per brand: (a) the provider's own logo URL, (b) Simple Icons when the brand name matches a slug exactly,
 * (c) the brand's official site (apple-touch-icon / og:image) once a domain is known (admin-entered, or resolved by Claude with a
 * confidence score), (d) a monogram that is flagged for review. Only (a) and an exact Simple Icons match are auto-approved; a site
 * logo found through a Claude-guessed domain is approved only at confidence >= 90, otherwise it waits in the review queue.
 * Brand logos are trademarks: confirm each provider's terms permit showing them for resale.
 */
class GiftCardLogoWizard
{
    private const SIZE = 256;

    private const DIR = 'giftcards/logos';

    /** Logos shipped with the app (one normalised file per brand + a manifest) so a fresh install already has them. */
    public const PACK_DIR = 'images/giftcards/logos/';

    /**
     * Load the shipped logo pack (database/seeders/giftcard-logos.json) for every brand that has no row yet. No network, idempotent, and
     * never touches a brand the wizard or an admin already handled.
     */
    public function seedFromPack(): int
    {
        $file = database_path('seeders/giftcard-logos.json');
        if (! is_file($file)) {
            return 0;
        }
        $pack = json_decode((string) file_get_contents($file), true);
        if (! is_array($pack)) {
            return 0;
        }
        $have = GiftCardBrandLogo::query()->pluck('brand_key')->flip();
        $now = now();
        $rows = [];
        foreach ($pack as $e) {
            if (isset($have[$e['brand_key']]) || ! is_file(public_path(self::PACK_DIR.$e['file']))) {
                continue;
            }
            $rows[] = ['brand_key' => $e['brand_key'], 'brand_name' => $e['brand_name'], 'path' => self::PACK_DIR.$e['file'], 'source' => $e['source'], 'source_url' => $e['source_url'] ?? null,
                'status' => $e['status'], 'fetched_at' => $now, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            GiftCardBrandLogo::query()->insert($chunk);
        }
        return count($rows);
    }

    /** @return array{brands:int, approved:int, review:int, fallback:int, skipped:int} */
    public function run(?int $limit = null, bool $refresh = false, ?callable $progress = null): array
    {
        $stats = ['brands' => 0, 'approved' => 0, 'review' => 0, 'fallback' => 0, 'skipped' => 0];
        $brands = GiftCardProduct::query()->selectRaw('brand_key, MIN(brand_name) as brand_name, MIN(brand_color) as brand_color')
            ->whereNotNull('brand_key')->groupBy('brand_key')->orderBy('brand_key')->get();
        $existing = GiftCardBrandLogo::query()->pluck('status', 'brand_key');
        $todo = [];
        foreach ($brands as $b) {
            // Approved and manually uploaded logos are never replaced by a re-run unless --refresh asks; review/fallback rows are retried.
            if (! $refresh && ($existing[$b->brand_key] ?? null) === GiftCardBrandLogo::APPROVED) {
                $stats['skipped']++;
                continue;
            }
            $todo[] = $b;
        }
        if ($limit !== null) {
            $todo = array_slice($todo, 0, $limit);
        }
        // Provider images are fetched concurrently in small batches (one slow host never stalls the whole run); the rest of the chain stays per brand.
        foreach (array_chunk($todo, 25) as $batch) {
            $urls = [];
            foreach ($batch as $b) {
                $u = GiftCardProduct::query()->where('brand_key', $b->brand_key)->whereNotNull('logo_url')->where('logo_url', 'like', 'http%')->value('logo_url');
                if ($u) {
                    $urls[$b->brand_key] = $u;
                }
            }
            $bodies = $this->prefetch($urls);
            foreach ($batch as $b) {
                $stats['brands']++;
                $row = $this->resolve((string) $b->brand_key, (string) $b->brand_name, $b->brand_color ? (string) $b->brand_color : null, null, $bodies[$b->brand_key] ?? null);
                $stats[$row->status === GiftCardBrandLogo::APPROVED ? 'approved' : $row->status]++;
                if ($progress) {
                    $progress($row);
                }
            }
        }
        $this->applyToProducts();

        return $stats;
    }

    /** @param string|null $providerBody the provider image already downloaded by run()'s concurrent pre-fetch */
    public function resolve(string $brandKey, string $brandName, ?string $color = null, ?string $domain = null, ?string $providerBody = null): GiftCardBrandLogo
    {
        $row = GiftCardBrandLogo::firstOrNew(['brand_key' => $brandKey]);
        $row->brand_name = $brandName;

        // (a) the provider's own image
        $url = GiftCardProduct::query()->where('brand_key', $brandKey)->whereNotNull('logo_url')->where('logo_url', 'like', 'http%')->value('logo_url');
        if ($url && ($png = $providerBody !== null ? $this->normalise($providerBody) : $this->fetchRaster($url))) {
            return $this->store($row, $png, 'webp', 'provider', $url, GiftCardBrandLogo::APPROVED);
        }

        // (b) Simple Icons, exact slug only
        $slug = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($brandName)));
        if ($slug !== '' && ($svg = $this->fetchSimpleIcon($slug, $color))) {
            return $this->store($row, $svg, 'svg', 'simpleicons', 'https://cdn.simpleicons.org/'.$slug, GiftCardBrandLogo::APPROVED);
        }

        // (c) the official site, from an admin-entered domain or one Claude resolves with a confidence score
        $confidence = null;
        if ($domain === null) {
            [$domain, $confidence] = $this->resolveDomain($brandName);
        }
        if ($domain && ($site = $this->fetchSiteLogo($domain))) {
            $row->domain = $domain;
            $row->confidence = $confidence;
            $auto = $confidence === null || $confidence >= 90;   // an admin-entered domain is trusted; a guessed one needs high confidence

            return $this->store($row, $site[0], 'webp', 'site', $site[1], $auto ? GiftCardBrandLogo::APPROVED : GiftCardBrandLogo::REVIEW);
        }

        // (c2) the same brand sold for another country ("Birkenstock Qatar" shares "Birkenstock"'s logo): longest approved key that prefixes this one
        $parts = explode('-', $brandKey);
        $prefixes = [];
        for ($i = count($parts) - 1; $i >= 1; $i--) {
            $p = implode('-', array_slice($parts, 0, $i));
            if (strlen($p) >= 4) {
                $prefixes[] = $p;   // longest first
            }
        }
        $sibling = $prefixes === [] ? null : GiftCardBrandLogo::query()->where('status', GiftCardBrandLogo::APPROVED)->whereNotNull('path')
            ->whereIn('source', ['provider', 'simpleicons', 'site', 'upload'])->whereIn('brand_key', $prefixes)->get()
            ->sortBy(fn ($l) => array_search($l->brand_key, $prefixes, true))->first();
        if ($sibling) {
            $bytes = Storage::disk(MediaStorage::disk())->get($sibling->path);
            if ($bytes !== null && $bytes !== '') {
                return $this->store($row, $bytes, str_ends_with($sibling->path, '.svg') ? 'svg' : 'webp', 'sibling', $sibling->source_url, GiftCardBrandLogo::APPROVED);
            }
        }

        // (d) honest fallback: a monogram, flagged for review
        $row->domain = $domain ?: $row->domain;
        $row->confidence = $confidence;

        return $this->store($row, $this->monogram($brandName, $color), 'svg', 'monogram', null, GiftCardBrandLogo::FALLBACK);
    }

    /** Admin upload / replace for one brand (never auto-replaced afterwards). */
    public function storeUpload(string $brandKey, string $brandName, string $bytes): ?GiftCardBrandLogo
    {
        $png = $this->normalise($bytes);
        if ($png === null) {
            return null;
        }
        $row = GiftCardBrandLogo::firstOrNew(['brand_key' => $brandKey]);
        $row->brand_name = $brandName;
        $out = $this->store($row, $png, 'webp', 'upload', null, GiftCardBrandLogo::APPROVED);
        $this->applyToProducts($brandKey);

        return $out;
    }

    /** Point every product's logo_url at the stored file (the storefront reads logo_url). A sync keeps it through urlFor(). */
    public function applyToProducts(?string $brandKey = null): int
    {
        $n = 0;
        GiftCardBrandLogo::query()->whereNotNull('path')->when($brandKey, fn ($q) => $q->where('brand_key', $brandKey))->each(function (GiftCardBrandLogo $l) use (&$n) {
            $n += GiftCardProduct::query()->where('brand_key', $l->brand_key)->update(['logo_url' => $this->url($l)]);
        });

        return $n;
    }

    /** The stored logo URL for a brand, or null when the wizard has none yet. */
    public static function urlFor(string $brandKey): ?string
    {
        $l = GiftCardBrandLogo::query()->where('brand_key', $brandKey)->whereNotNull('path')->first();

        return $l ? app(self::class)->url($l) : null;
    }

    public function url(GiftCardBrandLogo $l): string
    {
        // A logo from the shipped pack is a committed file under public/, not on the media disk.
        if (str_starts_with((string) $l->path, self::PACK_DIR)) {
            return asset($l->path);
        }

        return Storage::disk(MediaStorage::disk())->url($l->path);
    }

    // ---- fetching ---------------------------------------------------------------------------------------------------------------

    /**
     * @param  array<string,string>  $urls brand_key => https URL
     * @return array<string,string> brand_key => raw body (only the ones that answered 200 and are small enough)
     */
    private function prefetch(array $urls): array
    {
        $safe = array_filter($urls, fn ($u) => $this->safeUrl($u));
        if ($safe === []) {
            return [];
        }
        try {
            $res = Http::pool(fn ($pool) => array_map(
                fn ($key, $u) => $pool->as($key)->timeout(15)->withHeaders(['User-Agent' => 'NaaraSim-LogoWizard/1.0'])->get($u),
                array_keys($safe), array_values($safe),
            ));
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($res as $key => $r) {
            if ($r instanceof \Illuminate\Http\Client\Response && $r->successful() && strlen($r->body()) <= 3_000_000) {
                $out[$key] = $r->body();
            }
        }

        return $out;
    }

    private function safeUrl(string $url): bool
    {
        if (! preg_match('#^https://#i', $url)) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host !== '' && $host !== 'localhost' && ! (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));
    }

    private function get(string $url, int $timeout = 15): ?\Illuminate\Http\Client\Response
    {
        if (! $this->safeUrl($url)) {
            return null;
        }
        try {
            $res = Http::timeout($timeout)->withHeaders(['User-Agent' => 'NaaraSim-LogoWizard/1.0'])->withOptions(['allow_redirects' => ['max' => 3, 'protocols' => ['https']]])->get($url);
        } catch (\Throwable) {
            return null;
        }

        return $res->successful() && strlen($res->body()) <= 3_000_000 ? $res : null;
    }

    private function fetchRaster(string $url): ?string
    {
        $res = $this->get($url);

        return $res ? $this->normalise($res->body()) : null;
    }

    private function fetchSimpleIcon(string $slug, ?string $color): ?string
    {
        $hex = $color && preg_match('/^#?([0-9a-f]{6})$/i', $color, $m) ? '/'.$m[1] : '';
        $res = $this->get('https://cdn.simpleicons.org/'.$slug.$hex, 10);
        $body = $res ? $res->body() : '';

        return str_contains($body, '<svg') ? MediaStorage::sanitizeSvg($body) : null;
    }

    /** @return array{0:string,1:string}|null normalised webp bytes + the source URL */
    private function fetchSiteLogo(string $domain): ?array
    {
        $domain = strtolower(trim(preg_replace('#^https?://#i', '', $domain), '/'));
        if (! preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            return null;
        }
        $page = $this->get('https://'.$domain.'/');
        $cands = [];
        if ($page) {
            $html = $page->body();
            foreach (['apple-touch-icon[^"\']*', 'icon', 'shortcut icon'] as $rel) {
                if (preg_match_all('/<link[^>]+rel=["\']'.$rel.'["\'][^>]*>/i', $html, $links)) {
                    foreach ($links[0] as $tag) {
                        if (preg_match('/href=["\']([^"\']+)["\']/i', $tag, $h) && ! str_ends_with(strtolower(strtok($h[1], '?')), '.svg') && ! str_ends_with(strtolower(strtok($h[1], '?')), '.ico')) {
                            $cands[] = $this->absolute($domain, $h[1]);
                        }
                    }
                }
            }
            if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $o)) {
                $cands[] = $this->absolute($domain, $o[1]);
            }
        }
        $cands[] = 'https://'.$domain.'/apple-touch-icon.png';
        foreach (array_values(array_unique($cands)) as $u) {
            if (($png = $this->fetchRaster($u))) {
                return [$png, $u];
            }
        }

        return null;
    }

    private function absolute(string $domain, string $href): string
    {
        return match (true) {
            str_starts_with($href, '//') => 'https:'.$href,
            str_starts_with($href, 'http') => $href,
            str_starts_with($href, '/') => 'https://'.$domain.$href,
            default => 'https://'.$domain.'/'.$href,
        };
    }

    /** @return array{0:?string,1:?int} official domain and a 0-100 confidence, or [null, null] when Claude is not configured */
    private function resolveDomain(string $brandName): array
    {
        try {
            $ai = app(AnthropicClient::class);
            if (! $ai->enabled()) {
                return [null, null];
            }
            $reply = $ai->complete('You map gift-card product names to the brand they represent and the brand\'s OFFICIAL website domain. Reply with JSON only: {"brand": string, "domain": string|null, "confidence": 0-100}. If you are not sure, use a low confidence or a null domain. Never invent a domain.', [['role' => 'user', 'content' => $brandName]], 200, 0.0);
            $j = json_decode((string) preg_replace('/^```(?:json)?|```$/m', '', trim($reply)), true);
            $d = is_array($j) ? strtolower((string) ($j['domain'] ?? '')) : '';

            return $d !== '' ? [$d, (int) ($j['confidence'] ?? 0)] : [null, null];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    // ---- image handling -----------------------------------------------------------------------------------------------------------

    /** Square 256px WebP on the logo's own corner colour (or transparent for PNGs with alpha), with consistent padding. */
    public function normalise(string $bytes): ?string
    {
        $info = @getimagesizefromstring($bytes);
        if (! $info || $info[0] < 48 || $info[1] < 48) {
            return null;
        }
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $out = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagesavealpha($out, true);
        $corner = imagecolorat($src, 0, 0);
        $alpha = ($corner >> 24) & 0x7F;
        $fill = $alpha > 100 ? imagecolorallocatealpha($out, 0, 0, 0, 127) : imagecolorallocate($out, ($corner >> 16) & 255, ($corner >> 8) & 255, $corner & 255);
        imagealphablending($out, false);
        imagefilledrectangle($out, 0, 0, self::SIZE, self::SIZE, $fill);
        imagealphablending($out, true);
        $box = (int) (self::SIZE * 0.84);
        $scale = min($box / $w, $box / $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        imagecopyresampled($out, $src, (int) ((self::SIZE - $nw) / 2), (int) ((self::SIZE - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagewebp($out, null, 85);
        $webp = (string) ob_get_clean();

        return $webp !== '' ? $webp : null;
    }

    private function monogram(string $name, ?string $color): string
    {
        $initials = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9 ]/', '', Str::ascii($name)) ?: 'G', 0, 1).(preg_match('/\s(\w)/', preg_replace('/[^A-Za-z0-9 ]/', '', Str::ascii($name)), $m) ? $m[1] : ''));
        $palette = ['#0A6E6E', '#1F6FEB', '#7A3FD1', '#C2410C', '#B4235A', '#0F766E', '#3F6212', '#92400E'];
        $bg = $color && preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : $palette[abs(crc32($name)) % count($palette)];

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><rect width="256" height="256" rx="48" fill="'.$bg.'"/><text x="128" y="128" text-anchor="middle" dominant-baseline="central" font-family="Arial,Helvetica,sans-serif" font-size="104" font-weight="700" fill="#fff">'.e($initials).'</text></svg>';
    }

    private function store(GiftCardBrandLogo $row, string $bytes, string $ext, string $source, ?string $sourceUrl, string $status): GiftCardBrandLogo
    {
        $hash = hash('sha256', $bytes);
        $path = self::DIR.'/'.$row->brand_key.'-'.substr($hash, 0, 8).'.'.$ext;
        $disk = Storage::disk(MediaStorage::disk());
        $disk->put($path, $bytes, 'public');
        if ($row->path && $row->path !== $path && ! str_starts_with($row->path, self::PACK_DIR)) {
            $disk->delete($row->path);   // replaced: no orphan files
        }
        $row->fill(['path' => $path, 'source' => $source, 'source_url' => $sourceUrl, 'sha256' => $hash, 'status' => $status, 'fetched_at' => now()])->save();

        return $row;
    }
}
