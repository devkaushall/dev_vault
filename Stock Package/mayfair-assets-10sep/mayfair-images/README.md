# Mayfair Homepage — Temporary Image Pack

⚠️ **All images are AI-generated placeholders.** Replace with real photography from the owner before launch. Keep the same filenames/dimensions so nothing in Elementor needs re-linking — just re-upload via **Media Library → Replace** (or the "Enable Media Replace" plugin).

Total: 42 files · 4.3 MB · WebP (photos) + SVG (icons) + JPG (OG image).
Upload the `web/` folders to Media Library. Two sizes are given for each photo — upload the large one; WordPress will generate the smaller `srcset` automatically (the small file is a fallback if you want to upload manually for mobile).

---

## Mapping: file → homepage section → Elementor widget

### C1. Hero
| File | Use | Alt text |
|---|---|---|
| `hero/hero-2400.webp` | Hero background (Image widget, absolute, `fetchpriority=high`) — desktop/tablet | Luxury residential towers beside a golf course in Gurugram at dusk |
| `hero/hero-mobile-900x1125.webp` | Same widget → Responsive: swap on Mobile (4:5 crop keeps towers visible under the headline) | same |
| `hero/hero-1200.webp` | Optional lighter fallback / LiteSpeed LQIP | same |

### C5. Core Markets (4 linked image containers, 3:4)
| File | Card | Link |
|---|---|---|
| `markets/golf-course-road-900x1200.webp` | Golf Course Road | `/golf-course-road/` |
| `markets/dlf-phase-1-5-900x1200.webp` | DLF Phase 1–5 | `/dlf-phase-1-5/` |
| `markets/golf-course-extension-900x1200.webp` | Golf Course Extension | `/golf-course-extension/` |
| `markets/sohna-road-900x1200.webp` | Sohna Road | `/sohna-road/` |
Alt pattern: "Residential towers on Golf Course Road, Gurugram" etc.

### Explore by Category (4 tiles, 3:4) — replaces the grey gradient placeholders
| File | Tile | Link |
|---|---|---|
| `categories/residential-900x1200.webp` | Residential | `/residential/` |
| `categories/mansions-900x1200.webp` | Mansions | `/properties/?property-type=mansion` |
| `categories/commercial-900x1200.webp` | Commercial | `/commercial/` |
| `categories/plots-and-land-900x1200.webp` | Plots & Land | `/properties/?property-type=plot` |

### C4. Featured Listings (6 × Property CPT featured images, 4:3)
Create 6 dummy `property` posts and set these as Featured Image so the Loop Grid renders:
| File | Suggested dummy post | Locality | Type |
|---|---|---|---|
| `listings/listing-01-4bhk-golf-course-road-1200x900.webp` | 4 BHK Apartment, 3,200 sq ft | Golf Course Road | Residential |
| `listings/listing-02-builder-floor-dlf-phase-3-1200x900.webp` | 4 BHK Builder Floor, 360 sq yd | DLF Phase 1–5 | Residential |
| `listings/listing-03-penthouse-golf-course-ext-1200x900.webp` | 5 BHK Penthouse with terrace | Golf Course Ext. | Residential |
| `listings/listing-04-villa-sohna-road-1200x900.webp` | Independent Villa, 500 sq yd | Sohna Road | Mansion |
| `listings/listing-05-office-golf-course-ext-1200x900.webp` | Grade-A Office, 5,000 sq ft | Golf Course Ext. | Commercial |
| `listings/listing-06-plot-sohna-road-1200x900.webp` | Residential Plot, 300 sq yd | Sohna Road | Plots & Land |
*(Listings 02–06 are re-crops of the market/category images — visibly temporary. Replace first.)*

### C6. Our Developments (3 × Project CPT featured images, 4:3)
| File | Dummy project | Status |
|---|---|---|
| `projects/project-01-mayfair-residences-1200x900.webp` | Mayfair Residences | Ready |
| `projects/project-02-mayfair-corporate-park-1200x900.webp` | Mayfair Corporate Park | Under Construction |
| `projects/project-03-mayfair-enclave-1200x900.webp` | Mayfair Enclave | Upcoming |
*(Also re-crops — replace with real renders + RERA numbers.)*

### C8. Why Verified (Icon Box → SVG icon, 48 px, colour inherits gold)
| File | Box |
|---|---|
| `icons/icon-rera-shield.svg` | RERA details |
| `icons/icon-project-building.svg` | Project information |
| `icons/icon-developer-handshake.svg` | Developer track record |
| `icons/icon-gurugram-pin.svg` | Gurugram-only focus |
Requires **Elementor → Settings → Advanced → Unfiltered file uploads = Enable**.

### Social / Rank Math
| File | Where |
|---|---|
| `social/og-image-1200x630.jpg` | Rank Math → Titles & Meta → Global Meta → OpenGraph Thumbnail **and** Homepage → Facebook/Twitter tab |

---

## Not generated (need real assets from owner)
- Testimonial client photos (use initials avatar until then)
- Advisor/team portrait for final CTA
- Developer partner logos for the trust strip
- Real project renders with RERA numbers
- Google Maps embed / office photo for Contact page

## Replacement checklist when real photos arrive
1. Export to WebP, quality 80, same dimensions as the file being replaced (2400×1350 hero · 900×1200 portrait tiles · 1200×900 cards).
2. Media Library → open the placeholder → **Replace media** (keeps URL, no Elementor edits needed).
3. Update alt text.
4. Elementor → Tools → Regenerate CSS · LiteSpeed → Purge All.
