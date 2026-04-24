# Changelog

## 1.3.0 — 2026-04-23

### Statistics page (ProcessWireNPS)

- Redesigned stat cards using AdminThemeUikit design system CSS variables — full light/dark theme support
- Cards now laid out in a single flex row; 8 cards total on desktop
- Added **Feedback Rate** card — percentage of ratings that include a written comment
- Added **Last Rating** card — shows how long ago the most recent rating was submitted
- Added **Top Pages** block — top 5 pages by rating count with average score and clickable edit links
- Added **Monthly Breakdown** block — last 6 months with rating count, promoters, detractors, and NPS per month
- Top Pages and Monthly Breakdown displayed in a 50/50 grid
- Page titles in Recent Ratings table are now clickable (opens page editor in new tab)
- User names in Recent Ratings table are now clickable (opens user editor)
- Extended trend chart period from 30 to 90 days
- Fixed trend chart background color (was broken due to CSS variable string concatenation)
- Replaced `uk-button uk-button-primary` on Export with a custom `.wirenps-btn` — removes unwanted underline
- Removed `uk-card-secondary` grey background from Export section
- Fixed chart colors — replaced `--pw-alert-success/warning` (background tokens) with direct hex values for text use
- Removed all `console.log` / `console.warn` / `console.error` calls from `WireNPS.js`
- Renamed chart canvas IDs to avoid potential conflicts

## 1.2.0

- Initial admin statistics page with NPS score, ratings table, score distribution chart, 30-day trend chart, and CSV export
- Multilingual widget support (EN, FR, DE, ZH)
- Guest and authenticated user submission modes
- Cookie and session-based duplicate submission prevention