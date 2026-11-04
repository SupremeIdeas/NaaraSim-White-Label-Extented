# Gift cards: real provider cost, face-value pricing, and categories

Checked live on 2026-11-03 (read-only calls, no purchases).

## Does the provider give us a discount?

| Provider | Finding (live catalogue) | How we price now |
|---|---|---|
| **Zendit** | 6,713 offers, all USD. Every offer lists `price` (face) and `cost` (what we pay). Average discount where positive: **4.3%**; up to 89.6%. 59 offers cost MORE than face (down to about -9%), and a handful are data glitches (cost 3x face). | Exact: `cost = face x (1 - discount)` (+ fees), discount capped at 25% either way. Offers more than 25% above face are withheld (`priceable = false`). |
| **Bitrefill** | Price is quoted in satoshis. Converted at the live BTC/USD rate: **no discount** (median -0.5%); small cards carry a fee ($1 card costs about $1.25). | Face as cost (nearly right). Small cards price at cost + minimum profit, so no loss, but thin margin. A live rate source is the next step if we want exact. |
| **Reloadly** | Lists `discountPercentage` per product. | Already used (unchanged). Not re-verified live (no key supplied). |
| **Tillo** | Not verified (no key supplied). | Face as cost (unchanged). |

## What the customer pays

Admin > Pricing > Gift cards > "How a gift card is priced":

- **Face value (default):** the customer pays what the card says ($100 card = $100). Our profit is the provider's discount. Optional extra fee %.
- **Cost plus markup:** provider cost plus the markup %, as before.

In both modes the price is floored at real cost + the minimum profit (MarginGuard), so a card whose real cost is above face is priced up, never sold at a loss. Cards denominated in another currency (naira, rand...) always use cost plus markup, because their face is not dollars.

Before this change the code treated face value as cost for Zendit, so it (a) never saw the discount, so a "$100" card cost the customer $108, and (b) sold the 59 above-face offers at a loss.

## Categories

One taxonomy for the whole platform (`App\Support\GiftCardCategories`), stored on `gift_card_products.category_key` at sync time and backfilled by migration: Games & Entertainment, Games, Digital Apps, Clothing & Accessories, Food & Beverage, Shopping, Payment Cards, Software, Travel, Health & Beauty, Utilities, Mobile top-ups, More. Brand rules run first (Amazon is Shopping even when Zendit files it under Digital Apps), then the provider's category text. Shown as chips on the storefront (combine with country and search), on cards and the detail sheet, and in the Naara Widgets catalogue (`category`, `category_key`, `lookups.giftcard_categories`).
