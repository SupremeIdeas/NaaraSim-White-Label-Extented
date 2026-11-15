<?php

/**
 * eSIM Plan Finder (storefront). Read-only ranking over the plans the catalogue already synced: nothing here
 * touches a provider, a price, or a cost column. Weights must add up to 1; they are tunable without a code change.
 */
return [
    'weights' => [
        'data' => (float) env('ESIM_FINDER_W_DATA', 0.45),
        'price' => (float) env('ESIM_FINDER_W_PRICE', 0.40),
        'days' => (float) env('ESIM_FINDER_W_DAYS', 0.15),
    ],

    // A capped plan must hold at least this share of the data asked for (1000 MB plans are sold as "1 GB").
    'min_cover' => 0.95,

    // ...and no more than this many times the need (stops silly overbuys such as 100 GB for a 1 GB ask).
    'max_overbuy' => 6,

    // Price slider bounds in USD (log scale in the UI).
    'price_min' => 1,
    'price_max' => 2000,

    // Below this many plans in a context the finder would be noise (a short list is easier to just read), so it stays hidden.
    'min_plans' => 6,

    'per_page' => 12,
];
