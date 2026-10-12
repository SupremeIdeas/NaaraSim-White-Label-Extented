# Trustpilot reviews (display layer)

Admin: Admin > More > Trustpilot reviews. Everything is OFF until an admin turns it on.

## What ships
- Official TrustBox widgets only (carousel, mini, micro combo, review list). The admin pastes the Business Unit ID and the template IDs from Trustpilot's own TrustBox builder; the code never guesses an ID.
- Placements: homepage reviews section, rewards page, sign-in/register badge, end of every blog article, and a `/reviews` page (404 until its switch is on).
- With no template ID a placement is a plain link to the profile. Nothing is curated, filtered or gated; ratings are never required for any reward.
- The platform's own testimonials stay on unless an admin switches them off.
- CSP: Trustpilot origins are added to script/style/frame/connect-src only while the layer is live. The list is editable.
- Optional "click to load": the widget script loads only after the visitor presses a button (the platform has no cookie-consent banner).


## NOT built (on purpose)
Review invitations with reference IDs, review sync, and any reward for leaving a review.

## UNVERIFIED, confirm before relying on them
1. The default CSP origins (widget.trustpilot.com, www.trustpilot.com, *.trustpilot.com): check the browser console on a live page and adjust the list.
2. Whether the Trustpilot plan in use allows the review-list widget and invitations.
3. Trustpilot's rules on incentivised reviews: sources conflict. Rewards stay off until the owner has read Trustpilot's current guidelines and signed off.
