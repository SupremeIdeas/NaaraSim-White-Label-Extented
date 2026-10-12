# Set up Trustpilot on your install (white label quick guide)

1. Create a free business account at trustpilot.com/business and claim your company page.
2. Sign in to your admin dashboard and open More > Trustpilot reviews.
3. Paste your public profile address (like `https://www.trustpilot.com/review/your-domain.com`) into Profile address.
4. In Trustpilot, open Integrations > TrustBox library. Choose a widget (Carousel, Mini, Micro combo or Review list), set it to your domain, and copy two values from the snippet: `data-businessunit-id` and `data-template-id`.
5. Paste the Business Unit ID once. Paste each template ID next to its widget.
6. For review invitations, open Trustpilot > Integrations > JavaScript integration, copy the key from `tp('register', '...')` and paste it into Integration key. The invitation script then loads on every page while Trustpilot is switched on (it does not load when "Ask visitors before loading" is on).
7. Switch on the places you want it (homepage, rewards, sign-in, blog, reviews page), switch the layer on, and Save.
8. Open the page. If the widget is blank, open the browser console, find the blocked address, and add it to the allowed addresses list.

Good to know: only your real Trustpilot reviews are shown, nothing is filtered or rewarded, and everything is off until you turn it on. Your own testimonials stay unless you switch them off.
