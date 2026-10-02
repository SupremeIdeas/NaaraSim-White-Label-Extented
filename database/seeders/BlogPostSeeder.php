<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use App\Support\BrandSettings;
use Illuminate\Database\Seeder;

/**
 * Starter blog for a white-label install: five evergreen, brand-neutral guides
 * (installing an eSIM, eSIM vs physical SIM, device compatibility, "no service"
 * troubleshooting, and verification numbers) — NOT the master's full 40-post series,
 * which carries the master brand and competitor comparisons. Brand mentions are
 * `{brand}` tokens resolved at seed time. Each post ships with its cover in
 * public/images/blog/{slug}.webp; the numbers post also has a second inline image.
 * Idempotent (updateOrCreate on slug). The operator edits or replaces all of it from
 * Admin -> Blog.
 */
class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $authorId = User::role('super_admin')->value('id');
        $brand = BrandSettings::name();

        foreach ($this->posts() as $post) {
            $post = $this->withImages($post);
            foreach (['title', 'excerpt', 'body', 'meta_title', 'meta_description'] as $field) {
                $post[$field] = str_replace('{brand}', $brand, $post[$field]);
            }

            Post::updateOrCreate(['slug' => $post['slug']], $post + ['author_id' => $authorId]);
        }
    }

    /** @param array<string, mixed> $post @return array<string, mixed> */
    private function withImages(array $post): array
    {
        $slug = $post['slug'];

        if (is_file(public_path("images/blog/{$slug}.webp"))) {
            $post['cover_image_url'] = "/images/blog/{$slug}.webp";
        }

        if (is_file(public_path("images/blog/{$slug}-2.webp")) && ! str_contains($post['body'], "/images/blog/{$slug}-2.webp")) {
            $image = '!['.str_replace(['[', ']'], '', $post['title'])." — illustration](/images/blog/{$slug}-2.webp)";
            $parts = preg_split('/(?=^## )/m', $post['body']);
            $at = count($parts) > 3 ? 3 : (count($parts) > 2 ? 2 : null);
            if ($at !== null) {
                $parts[$at] = $image."\n\n".$parts[$at];
                $post['body'] = implode('', $parts);
            }
        }

        return $post;
    }

    /** @return list<array<string, mixed>> */
    private function posts(): array
    {
        return [
            [
                'title' => 'How to Actually Install an eSIM Without Losing Your Mind (Step-by-Step)',
                'slug' => 'how-to-install-esim-step-by-step-guide',
                'category' => 'eSIM & Travel Guides',
                'excerpt' => 'A no-nonsense, step-by-step guide to installing an eSIM on iPhone and Android — plus the three things that actually go wrong and how to fix them.',
                'body' => 'Every single support ticket about "the eSIM isn\'t working" comes down to one of about four things. Almost never the eSIM itself. Let\'s walk through the actual install so you\'re not one of those tickets, and then I\'ll cover the annoying edge cases separately, because pretending they don\'t exist helps nobody.

## Before you do anything: check three things

- Is your phone eSIM-capable? Most iPhones from the XS onward, most Pixels from the 3 onward, and most Samsung flagships from the S20 onward support it. Budget phones and phones bought carrier-locked in some regions sometimes have eSIM disabled even if the hardware supports it. If you\'re not sure, search your exact model number plus "eSIM compatible" before you buy a plan — saves everyone a headache.
- Is your phone unlocked, or at least not blocking additional profiles? A carrier-locked phone can sometimes still install an eSIM for data-only use, but not always. Worth ten seconds of checking.
- Do you have Wi-Fi? You need internet to install an eSIM — this trips people up constantly. You cannot install your travel data plan using... the data plan you haven\'t installed yet. Do this before you leave home, or use airport/hotel Wi-Fi the moment you land.

## The actual install, step by step

## On iPhone:
- Buy your plan in the {brand} app or dashboard. You\'ll get either a QR code or a direct "one-tap" install link — most modern iPhones can skip scanning entirely if you open the link on the same device.
- Go to Settings → Cellular → Add eSIM (on some iOS versions it\'s under Mobile Data).
- Scan the QR code with your camera, or tap "use QR code" and then "enter details manually" if scanning fails for whatever reason.
- Give the plan a label — "Kenya Trip" or whatever — so you can find it later without guessing.
- Choose which line handles data (your new eSIM) and which handles calls/texts (your home SIM). This is the step people skip and then wonder why their data isn\'t working — the phone defaulted to the wrong line.
- Turn on Data Roaming for that specific eSIM line. Yes, even though it\'s not "roaming" in the traditional sense — the phone doesn\'t know the difference.

## On Android (varies slightly by manufacturer, but broadly):
- Buy the plan, get your QR code.
- Settings → Network & Internet → SIMs (or "Mobile network") → Add SIM / Add eSIM.
- Scan the code, follow the prompts.
- Under SIM settings, set your new eSIM as the data SIM specifically, not just "active."

## The three things that actually break this

- "QR code already used" error. eSIM QR codes are usually single-use. If you scanned it once, tried to change your mind, and scanned again — that\'s the problem. Don\'t scan it more than once "just to check."
- Data shows connected but nothing loads. Nine times out of ten this is the APN (Access Point Name) not being set correctly, or the eSIM line not being toggled as the active data line while your home SIM quietly hogs the connection. Double check step 5/4 above before assuming the plan is broken.
- Plan installed before landing, but "no signal" on arrival. Some plans only activate once the phone actually connects to a partner network in-country — meaning it might sit "installed but idle" until you land and your phone finds a tower. That\'s normal for a lot of pay-as-you-go travel eSIMs, not a fault. Give it two or three minutes after landing before panicking.

## One more thing, and it\'s a genuinely useful habit

Install your eSIM the night before you fly, on your home Wi-Fi, not at the gate with spotty airport Wi-Fi and twelve minutes before boarding. It takes ninety seconds when you\'re calm and have signal. It takes twenty stressful minutes when you don\'t. Future you will say thank you.',
                'meta_title' => 'How to Actually Install an eSIM Without Losing Your Mind (Step-by-Step)',
                'meta_description' => 'A no-nonsense, step-by-step guide to installing an eSIM on iPhone and Android — plus the three things that actually go wrong and how to fix them.',
                'status' => 'published',
                'published_at' => '2026-07-05 09:00:00',
            ],
            [
                'title' => 'eSIM vs Physical SIM: What Nobody Tells You Before You Travel',
                'slug' => 'esim-vs-physical-sim-what-nobody-tells-you',
                'category' => 'eSIM & Travel Guides',
                'excerpt' => 'eSIM or physical SIM for your next trip? Here\'s the honest, unglamorous breakdown — coverage, cost, backup plans, and what actually goes wrong.',
                'body' => 'Somebody asked me last week if eSIMs are "actually a thing" or just a trend that travel influencers made up to sell affiliate links. Fair question. Short answer: it\'s a real thing, it\'s been around longer than most people realize, and no, it isn\'t perfect. Let\'s talk about it properly instead of just listing pros and cons like a spec sheet.

## What an eSIM actually is (the boring but important part)

Your phone already has an eSIM chip soldered inside it — has done since around 2018 on most flagship devices. A physical SIM is a tiny piece of plastic you slot in. An eSIM is basically the same information (the bit that tells a network "yes, let this device on") except it gets installed as a digital profile instead. No tray, no tiny pin, no dropping the SIM card behind your car seat at the airport (we\'ve all done it).

That\'s really the whole trick. Everything else — coverage, speed, cost — depends on who\'s selling you the plan, not on the "e" part.

## Where physical SIM still wins, honestly

I\'m not going to pretend physical SIMs are dead, because they\'re not.

- If you\'re going somewhere for months and want a real local number that people can call you on normally, a physical SIM from a local shop is sometimes cheaper long-term.
- Some older or budget phones simply don\'t have eSIM hardware. Check before you plan your whole trip around one.
- If your phone gets stolen, your physical SIM can go straight into a replacement phone in two minutes. An eSIM re-download can be more fiddly depending on the provider, and some carriers only let you install a given eSIM profile once or twice.

## Where eSIM wins, and it\'s not close

- You can buy and activate a data plan before you even board the plane. Land, turn on data, done. No wandering an unfamiliar airport hunting a SIM kiosk with a queue of forty other tired travelers.
- You keep your home number active on your primary SIM slot for calls and 2FA, while your eSIM handles data. Dual SIM, one phone.
- No physical item to lose, damage, or forget to buy before your flight because the shop closed at 6pm.
- Switching countries mid-trip (say you\'re doing Lagos, then Accra, then Nairobi) is a few taps instead of hunting new SIM shops in three different cities.

That last point matters more in Africa than almost anywhere else, honestly, because "just grab a local SIM when you land" assumes reliable shops, ID requirements you can meet on the spot, and a queue you have time for. Sometimes yes. Sometimes you\'re standing in Murtala Muhammed International at midnight and the SIM counter closed hours ago.

## The part nobody tells you: coverage isn\'t uniform

This is the bit review sites gloss over. An eSIM provider doesn\'t build its own towers — it partners with local networks in each country, and coverage quality genuinely varies by provider and by country, sometimes down to which city you\'re in. A plan that\'s brilliant in Cape Town might be mediocre in a rural stretch of Malawi. This is exactly why, at {brand}, we can route through more than one eSIM partner behind the scenes rather than betting your whole trip on a single network having a good day. If one partner has a hiccup on a given route, the system\'s built to fail over instead of just leaving you with a spinner and no bars.

## So which one should you actually pick?

If you\'re doing a short trip — a week in Kenya, a work trip to Accra, a wedding in Kigali — go eSIM, no debate. If you\'re relocating somewhere for six months and want a proper local phone number people dial normally, get a local SIM once you land, and maybe use a virtual number in the meantime so people can reach you before you even touch down (more on that in a separate post — it\'s a whole thing on its own).

And if your phone\'s old enough that it doesn\'t do eSIM at all? No shame. Grab a physical SIM at the airport, keep the receipt, and move on with your life.

Either way — don\'t be the person still doing international roaming through their home carrier in 2026. That ship has sailed and it cost someone I know about $340 for four days in Ghana. Learn from Chinedu. Don\'t be Chinedu.',
                'meta_title' => 'eSIM vs Physical SIM: What Nobody Tells You Before You Travel',
                'meta_description' => 'eSIM or physical SIM for your next trip? Here\'s the honest, unglamorous breakdown — coverage, cost, backup plans, and what actually goes wrong.',
                'status' => 'published',
                'published_at' => '2026-07-03 09:00:00',
            ],
            [
                'title' => 'Which Phones Actually Support eSIM? The Real Compatibility List',
                'slug' => 'which-phones-support-esim-compatibility-list',
                'category' => 'Device Compatibility & Setup',
                'excerpt' => 'Not every phone that "should" support eSIM actually does — carrier locks and regional variants change the answer. Here\'s how to actually check yours.',
                'body' => '"Does my phone support eSIM?" sounds like a yes/no question. It\'s actually a yes/no/it\'s-complicated question, and the complicated part trips up more people than the actual hardware limitations do.

## The general rule, broadly

Most iPhones from the XS/XR generation onward, most Google Pixels from the 3 onward, and most Samsung Galaxy flagships from around the S20 onward support eSIM. Several other brands — recent Motorola, Huawei, and Oppo flagship models — increasingly support it too, though coverage is less consistent across their full lineup compared to Apple, Google, and Samsung.

## Where the "it\'s complicated" part comes in

- Regional variants matter more than model number. Some phones sold in certain markets (parts of the US on specific carrier-locked models historically, and some region-specific Samsung variants) have eSIM hardware physically present but disabled in software, purely due to a carrier or regional agreement. Same model name, different actual capability.
- Carrier locking can block it entirely. A phone locked to a specific carrier sometimes won\'t let you add a second eSIM profile from a different provider, even if the hardware supports it, until it\'s unlocked.
- Dual-eSIM vs. eSIM-plus-physical-slot varies by phone. Some phones support two active eSIMs simultaneously; others support one eSIM alongside one physical SIM slot, and a few older or budget models only support one line total, meaning you\'d have to remove your home SIM entirely to use a travel eSIM. Worth knowing before you assume you can run both side by side.

## How to actually check, properly, before you buy a plan

- Search your exact model number (not just "iPhone 14," but the specific variant if you know it) plus "eSIM compatible" and check results from the phone manufacturer\'s own site specifically, not just resale listings.
- On iPhone: Settings → General → About → scroll to look for an EID number. If there\'s an EID listed, the phone supports eSIM.
- On Android: Settings → About Phone → look for an EID under SIM status, or check Network & Internet → SIMs to see if there\'s an "Add eSIM" or "Add Carrier" option at all.
- If you\'re not sure whether your specific phone was sold carrier-locked in a way that blocks eSIM, contacting the carrier directly (or checking their support docs) is more reliable than guessing based on general model compatibility lists.

## What to do if your phone genuinely doesn\'t support eSIM

No shame in it — plenty of perfectly good phones, especially budget and mid-range models, simply don\'t have the hardware. In that case, a physical local SIM at your destination remains the right call for data, and a virtual number (which doesn\'t depend on eSIM hardware at all — it works through the app itself) still solves the "I need a working local-feeling number" side of things regardless of what kind of SIM your phone takes.

## A quick note on older or hand-me-down phones

If you\'ve inherited or bought a used phone, double check the eSIM situation specifically rather than assuming it matches what you remember about that model generally — a used phone can have quirks (a previous owner\'s carrier lock, a factory reset that didn\'t fully clear an old eSIM profile) that a brand-new version of the same model wouldn\'t have. Five minutes of checking before you buy a travel plan saves the frustration of discovering an incompatibility after you\'ve already paid for data you can\'t install.',
                'meta_title' => 'Which Phones Actually Support eSIM? The Real Compatibility List',
                'meta_description' => 'Not every phone that "should" support eSIM actually does — carrier locks and regional variants change the answer. Here\'s how to actually check yours.',
                'status' => 'published',
                'published_at' => '2026-09-01 09:00:00',
            ],
            [
                'title' => '"No Service" on Your eSIM? Here\'s the Actual Troubleshooting Order',
                'slug' => 'esim-no-service-troubleshooting-order',
                'category' => 'Device & Technical Troubleshooting',
                'excerpt' => 'eSIM installed but showing "no service"? Work through these six checks in order before assuming the plan is broken — most fixes take under a minute.',
                'body' => '"No service" is probably the single most common message we get, and in the vast majority of cases the plan itself is fine — something small in the phone\'s settings is the actual culprit. Here\'s the order to actually check things in, so you\'re not randomly toggling settings and hoping.

## 1. Did the plan actually finish installing?

Sounds obvious, but a slow connection or a closed app mid-install can leave an eSIM in a half-installed state. Go back into your eSIM settings and confirm the plan shows as fully installed, not "pending" or greyed out. If it\'s stuck, delete it and reinstall using the same activation link (assuming it hasn\'t already been used once — check the next point).

## 2. Has this specific QR code or activation link already been used?

Most eSIM activations are single-use. If you scanned it, second-guessed something, and tried again, that second scan often just fails silently or throws an error. If you\'re not sure, check your purchase history in the app — it\'ll usually tell you if the eSIM shows as already installed on a device.

## 3. Is the eSIM line actually turned on?

An installed eSIM profile can still be toggled off. On iPhone: Settings → Cellular → tap the eSIM line → make sure "Turn On This Line" is enabled. On Android it\'s usually under Network & Internet → SIMs, with a toggle next to each installed profile. This one gets missed constantly because the plan shows as "installed" even when it\'s switched off.

## 4. Is it set as the active line for data specifically?

This is the single biggest cause of "installed but not working." Your phone might be trying to pull data from your home SIM (which has no service abroad) instead of your new eSIM. Check Settings → Cellular → Cellular Data (iPhone) or Settings → Network & Internet → SIMs → Mobile Data (Android) and make sure your travel eSIM is explicitly selected as the data line, not just "on."

## 5. Is data roaming toggled on for that specific line?

Even though you\'re not "roaming" in the traditional sense on a local eSIM, phones often still gate the connection behind the roaming toggle for that line. Same menu as above — Data Roaming, per-line, turned on.

## 6. Has your phone actually connected to a tower yet?

Some plans sit "installed and enabled" but genuinely idle until the phone makes first contact with a supported network in that country — meaning nothing will show until you land and your phone finds a signal. Give it two or three minutes after arrival, ideally somewhere without thick concrete overhead (airports are notoriously bad for signal near the gates, for what it\'s worth, on every carrier, every country).

## If you\'ve done all six and it\'s still not working

At that point it\'s worth actually checking with support rather than continuing to guess — occasionally a specific provider partner has a genuine outage on a specific route, and no amount of settings-toggling on your end fixes an outage on their end. That\'s the point where a well-built platform should flag the issue and either fail you over to a backup provider or refund you outright, rather than leaving you stuck. If you\'re getting a shrug instead, that\'s a fair thing to be annoyed about.

## One thing worth doing before you ever hit this problem

Test your eSIM on home Wi-Fi before you fly, specifically by checking that it shows "installed" and toggled correctly, even though it won\'t have signal until you\'re actually in-country. It won\'t catch everything, but it catches the install-level mistakes (step 1–3 above) while you\'ve still got calm access to settings and good signal to fix them, instead of discovering a bad install at an unfamiliar airport at midnight.',
                'meta_title' => '"No Service" on Your eSIM? Here\'s the Actual Troubleshooting Order',
                'meta_description' => 'eSIM installed but showing "no service"? Work through these six checks in order before assuming the plan is broken — most fixes take under a minute.',
                'status' => 'published',
                'published_at' => '2026-08-12 09:00:00',
            ],
            [
                'title' => 'SMS Verification Numbers Explained: OTP, Rentals, and When to Use Which',
                'slug' => 'sms-verification-numbers-explained-otp-vs-rental',
                'category' => 'Virtual Numbers & Verification',
                'excerpt' => 'OTP number, rental number, permanent number — they\'re not the same thing and picking the wrong one wastes your money. Here\'s the actual difference.',
                'body' => 'People use "virtual number," "OTP number," and "rented number" interchangeably all the time, and honestly, that\'s how a lot of people end up buying the wrong thing and then leaving a confused one-star review that isn\'t really about the product — it\'s about not knowing there were three different products to begin with. Let\'s actually sort this out.

## OTP / one-time verification numbers

This is the cheapest, fastest option, and it\'s built for exactly one job: catching a single verification code. You pick the country and the specific service you\'re signing up for (WhatsApp, Telegram, a delivery app, whatever), you get a number, the code lands, you\'re done. The number is typically released or recycled shortly after — it\'s not yours to keep, and it\'s not meant to receive a random call from your aunt three days later.

Good for: one-off sign-ups, testing accounts, situations where you genuinely just need that one code and nothing more.
Bad for: anything you need to still be receiving messages on next week.

## Rental numbers

A step up. You keep the number for a set period — days, weeks, sometimes longer — and it can typically receive ongoing SMS during that window, not just one code. This is the right call if you\'re going to be re-verifying, receiving multiple codes over a short stay, or want a number that\'s yours for the length of a trip without paying for a long-term commitment.

Good for: a two-week work trip where you\'ll be logging into several services and might get re-verification prompts.
Bad for: pretending it\'s a permanent number and giving it out to clients as your "real" contact — it\'ll expire and that email thread six months from now won\'t reach you.

## Permanent numbers

These behave like an actual phone line — voice and SMS, ongoing, meant to stay yours indefinitely (or until you cancel it), the way a normal SIM-based number does. This is the one you want if you\'re setting up a genuine business presence in a country, relocating long-term, or want a stable number to hand out to recruiters and clients that won\'t quietly disappear on you.

Good for: long-term use, giving out to people who need to reach you reliably.
Bad for: a quick one-time sign-up — you\'re paying for durability you don\'t need for a five-minute task.

## How to actually pick, in plain terms

Ask yourself one question: will I need this number again after today? If no — OTP number, cheapest, fastest, done. If yes, but only for the length of a specific trip — rental. If yes, indefinitely — permanent.

## A quick word on why some verification attempts still fail

Occasionally a service you\'re trying to verify will reject a virtual number outright, or the code will take longer than expected. This isn\'t always a fault with the number itself — some platforms specifically screen out number ranges they\'ve flagged as commonly used for virtual/VOIP numbers, as an anti-fraud measure. It\'s frustrating, we know, but it\'s also exactly why the routing behind a good virtual number service matters: on our side, this is why numbers get sourced from more than one supplier depending on the country and the specific service being verified, rather than sending every request down one pipe that a platform might have already flagged. It doesn\'t fix every case, but it noticeably improves the odds versus using a single fixed source every time.

If you\'re not sure which type fits what you\'re doing, honestly, just start with the cheapest OTP option for a test sign-up before committing wallet funds to a longer rental. Cheap mistake beats expensive one.',
                'meta_title' => 'SMS Verification Numbers Explained: OTP, Rentals, and When to Use Which',
                'meta_description' => 'OTP number, rental number, permanent number — they\'re not the same thing and picking the wrong one wastes your money. Here\'s the actual difference.',
                'status' => 'published',
                'published_at' => '2026-07-11 09:00:00',
            ],
        ];
    }
}
