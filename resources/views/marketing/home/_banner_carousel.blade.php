{{-- Homepage banner carousel (owner request, 2026-09-08) — the very last
     section on the homepage, decorative/non-CMS like _flags/_video/_story
     above.

     Frontend-UX-fix blueprint Phase G — generalized into
     `partials.banner-carousel`, an admin-configurable placement (see
     `App\Support\BannerPlacements`: `marketing_home`/`dashboard_footer`
     placements, `banner_only`/`banner_with_description` display styles).
     This wrapper preserves the exact pre-existing behaviour and markup for
     the homepage specifically: same `section-key="home-banners"` (locked in
     by `LinkPreviewSettingsTest`'s existing assertions), same legacy
     `home.banner_carousel_enabled` Setting still respected as the fallback
     default inside `BannerPlacements::config()`. Zero behaviour change for
     any install that never touches the new admin controls. --}}
@include('partials.banner-carousel', ['placement' => 'marketing_home', 'sectionKey' => 'home-banners'])
