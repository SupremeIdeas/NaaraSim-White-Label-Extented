<?php

namespace App\Support;

use Illuminate\Translation\Translator;

/** The translator, saying the licensee's brand (see BrandWordFilter): every language line, toast and notification follows the name. */
class BrandedTranslator extends Translator
{
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $line = parent::get($key, $replace, $locale, $fallback);

        return is_string($line) ? BrandWordFilter::text($line) : $line;
    }
}
