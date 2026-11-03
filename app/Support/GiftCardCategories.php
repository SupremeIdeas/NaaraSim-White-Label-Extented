<?php

namespace App\Support;

/**
 * One gift-card taxonomy for the whole platform (storefront, admin, widgets, API). Every provider words its categories differently (Zendit
 * "Gaming & Entertainment", Bitrefill "apparel", Reloadly "Gaming", Tillo "Fashion"), so each card is resolved to ONE of these keys at sync time and
 * stored in gift_card_products.category_key. Order here is the order of the filter chips.
 */
class GiftCardCategories
{
    /** key => [label, icon name from the sprite] */
    public const ALL = [
        'games-entertainment' => ['Games & Entertainment', 'play'],
        'games' => ['Games', 'gamepad'],
        'digital-apps' => ['Digital Apps', 'smartphone'],
        'clothing-accessories' => ['Clothing & Accessories', 'shirt'],
        'food-beverage' => ['Food & Beverage', 'utensils'],
        'shopping' => ['Shopping', 'shopping-bag'],
        'payment-cards' => ['Payment Cards', 'credit-card'],
        'software' => ['Software', 'cpu'],
        'travel' => ['Travel', 'plane'],
        'health-beauty' => ['Health & Beauty', 'heart'],
        'utilities' => ['Utilities', 'zap'],
        'top-ups' => ['Mobile top-ups', 'signal'],
        'other' => ['More', 'gift'],
    ];

    /** Brand words that decide the category whatever the provider called it (checked first). */
    private const BRAND_RULES = [
        'games' => ['steam', 'playstation', 'psn', 'xbox', 'nintendo', 'roblox', 'fortnite', 'v-bucks', 'valorant', 'riot', 'league of legends', 'pubg', 'free fire', 'garena', 'razer gold', 'blizzard', 'battle.net', 'minecraft', 'call of duty', 'apex', 'ea play', 'electronic arts', 'ubisoft', 'epic games', 'mobile legends', 'genshin', 'clash of', 'supercell', 'candy crush', 'warframe', 'world of warcraft', 'gog', 'itunes games', 'unipin', 'mol points', 'zepeto', 'wild rift', 'honor of kings', 'diablo', 'overwatch', 'runescape', 'path of exile', 'fifa', 'ea sports', 'gamestop', 'game pass', 'nexon', 'tencent games', 'black desert', 'lords mobile', 'cod points'],
        'payment-cards' => ['visa', 'mastercard', 'prepaid', 'virtual card', 'paysafecard', 'neosurf', 'amex', 'american express', 'one4all', 'giftcards.com', 'vanilla', 'innovative gift', 'perfect money', 'ukash', 'flexepin', 'astropay', 'jeton', 'binance', 'bitcoin card'],
        'software' => ['microsoft 365', 'office', 'windows', 'adobe', 'norton', 'mcafee', 'kaspersky', 'avast', 'bitdefender', 'nordvpn', 'expressvpn', 'surfshark', 'vpn', 'antivirus', 'dropbox', 'canva', 'zoom', 'jetbrains', 'github', 'openai', 'chatgpt', 'icloud', 'google one', 'onedrive', 'lastpass', '1password', 'grammarly'],
        'digital-apps' => ['google play', 'app store', 'itunes', 'apple', 'tinder', 'bumble', 'hinge', 'badoo', 'telegram', 'tiktok', 'snapchat', 'uber eats app', 'skype', 'viber', 'truecaller'],
        'food-beverage' => ['uber eats', 'doordash', 'grubhub', 'deliveroo', 'just eat', 'starbucks', 'mcdonald', 'kfc', 'domino', 'pizza', 'burger', 'subway', 'dunkin', 'chipotle', 'nando', 'costa coffee', 'glovo', 'swiggy', 'zomato', 'talabat', 'jumia food', 'chowdeck'],
        'games-entertainment' => ['amazon prime', 'netflix', 'spotify', 'disney', 'hulu', 'hbo', 'max ', 'prime video', 'youtube', 'twitch', 'deezer', 'tidal', 'apple music', 'showmax', 'dstv', 'crunchyroll', 'audible', 'cinema', 'cinemark', 'amc', 'ticketmaster', 'vue', 'odeon', 'paramount', 'peacock', 'dazn', 'funimation', 'starz', 'pandora', 'sirius'],
        'shopping' => ['amazon', 'ebay', 'walmart', 'target', 'jumia', 'noon', 'flipkart', 'aliexpress', 'etsy', 'best buy', 'ikea', 'costco', 'argos', 'takealot', 'konga', 'shein', 'temu', 'mercado libre', 'lazada', 'shopee', 'rakuten', 'zalando', 'otto'],
        'travel' => ['airbnb', 'booking.com', 'expedia', 'hotels.com', 'uber', 'lyft', 'bolt', 'emirates', 'airline', 'airways', 'flight', 'hilton', 'marriott', 'hyatt', 'trivago', 'agoda', 'trip.com', 'tripadvisor', 'almosafer', 'flixbus', 'greyhound', 'avis', 'hertz', 'carnival', 'cruise', 'hostelworld', 'ryanair', 'easyjet', 'wego'],
    ];

    /** Provider category text (lower-case contains) => key. First match wins, so specific words come first. */
    private const TEXT_RULES = [
        'gaming & entertainment' => 'games-entertainment', 'gaming and entertainment' => 'games-entertainment',
        'game' => 'games', 'gaming' => 'games',
        'entertain' => 'games-entertainment', 'streaming' => 'games-entertainment', 'music' => 'games-entertainment', 'movie' => 'games-entertainment', 'cinema' => 'games-entertainment', 'media' => 'games-entertainment',
        'digital app' => 'digital-apps', 'dating' => 'digital-apps', 'app' => 'digital-apps',
        'software' => 'software', 'vpn' => 'software', 'security' => 'software',
        'clothing' => 'clothing-accessories', 'apparel' => 'clothing-accessories', 'fashion' => 'clothing-accessories', 'accessor' => 'clothing-accessories', 'jewel' => 'clothing-accessories', 'shoe' => 'clothing-accessories',
        'restaurant' => 'food-beverage', 'food' => 'food-beverage', 'beverage' => 'food-beverage', 'coffee' => 'food-beverage', 'dining' => 'food-beverage', 'supermarket' => 'food-beverage', 'grocer' => 'food-beverage', 'delivery' => 'food-beverage',
        'travel' => 'travel', 'transport' => 'travel', 'experience' => 'travel', 'hotel' => 'travel', 'flight' => 'travel', 'airline' => 'travel', 'ride' => 'travel',
        'health' => 'health-beauty', 'beauty' => 'health-beauty', 'cosmetic' => 'health-beauty', 'wellness' => 'health-beauty', 'pharmac' => 'health-beauty',
        'utilit' => 'utilities', 'internet' => 'utilities', 'electricity' => 'utilities', 'bill' => 'utilities', 'imtu' => 'utilities',
        'payment' => 'payment-cards', 'prepaid' => 'payment-cards', 'voucher card' => 'payment-cards',
        'refill' => 'top-ups', 'top-up' => 'top-ups', 'topup' => 'top-ups', 'bundle' => 'top-ups', 'data' => 'top-ups', 'voip' => 'top-ups', 'mobile' => 'top-ups', 'airtime' => 'top-ups', 'pin' => 'payment-cards',
        'shopping' => 'shopping', 'merchand' => 'shopping', 'retail' => 'shopping', 'home' => 'shopping', 'garden' => 'shopping', 'electronic' => 'shopping', 'sport' => 'shopping', 'outdoor' => 'shopping', 'auto' => 'shopping', 'pet' => 'shopping', 'gift' => 'shopping', 'department' => 'shopping', 'book' => 'shopping', 'toy' => 'shopping',
    ];

    /** Resolve a card to one category key from the provider's own category text and the brand name. */
    public static function resolve(?string $providerCategory, ?string $brandName = null): string
    {
        $brand = ' '.mb_strtolower((string) $brandName).' ';
        foreach (self::BRAND_RULES as $key => $words) {
            foreach ($words as $w) {
                // Short words must match whole words ("app" must not match "apple pay"); longer ones match anywhere.
                if (strlen($w) <= 4 ? preg_match('/\b'.preg_quote(trim($w), '/').'\b/', $brand) === 1 : str_contains($brand, $w)) {
                    return $key;
                }
            }
        }
        $text = mb_strtolower(trim((string) $providerCategory));
        if ($text !== '') {
            foreach (self::TEXT_RULES as $needle => $key) {
                if (strlen($needle) <= 4 ? preg_match('/\b'.preg_quote($needle, '/').'\b/', $text) === 1 : str_contains($text, $needle)) {
                    return $key;
                }
            }
        }

        return $text === '' ? 'shopping' : 'other';
    }

    public static function label(?string $key): string
    {
        return (self::ALL[$key] ?? self::ALL['other'])[0];
    }

    /** @return array<string, string> key => label, in chip order */
    public static function labels(): array
    {
        return array_map(fn ($v) => $v[0], self::ALL);
    }
}
