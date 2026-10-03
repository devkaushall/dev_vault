# Mayfair Icons v3

**One folder. One family. Fully editable in Elementor.**

- `icons/` — **104 SVG files, flat, one folder** → select all → drag-drop into Media Library once.
- Family: **Phosphor Regular** (MIT) — same optical weight for UI, property, service *and* social/brand marks, so nothing looks out of place.
- Every file is `fill="currentColor"` with **no hard-coded colour, size or stroke** → Elementor's Style tab controls everything (Primary/Secondary colour, size, padding, border, radius, background, hover colour).
- File names are searchable: type `mf-` in Media Library, then a group: `mf-verified-`, `mf-services-`, `mf-property-`, `mf-amenity-`, `mf-ui-`, `mf-social-`.

Preview everything (and the motion): `preview.html`. Package: `mayfair-icons-v3.zip` (icons + css + preview).

## Elementor: how the icon stays editable

1. Elementor → Settings → Advanced → **Enable Unfiltered File Uploads: Yes** (needed once for SVG).
2. Media → Add New → drag the whole `icons/` folder contents.
3. Icon Box / Icon / Icon List → Icon → **Upload SVG → Media Library → search `mf-`** → Insert.
4. Style tab → Icon:
   - **View** Default / Stacked / Framed, **Shape** Circle / Square
   - **Primary Color** = glyph (Default view) or background (Stacked) or border+glyph (Framed) → pick a Global Color (Gold on light, Gold Light on dark)
   - **Secondary Color** = glyph inside Stacked/Framed
   - **Size**, **Padding**, **Border Width**, **Border Radius**, **Rotate**, **Hover Primary/Secondary colour** — all work because the SVG has no fixed values.

Recommended defaults

| Where | View | Primary | Secondary | Size | Padding | Border |
|---|---|---|---|---|---|---|
| S3 Why Mayfair (Sand bg) | Framed · Circle | Gold `#725B2F` | — | 26px | 15px | 1px |
| S7 Services (Ink bg) | Default | Gold Light `#A68B5B` | — | 32px | — | — |
| S5 Property card facts | Icon List | Grey `#444748` | — | 16px | — | — |
| S11 CTA list | Icon List | Gold Light | — | 18px | — | — |
| Footer socials | Social Icons → Custom | Gold Light | hover White | 20px | — | — |
| Header / mobile bar buttons | Button icon | inherit | — | 16px | — | — |

## Homepage mapping

| Section | Files |
|---|---|
| S0 header | `mf-ui-phone`, `mf-ui-clock`, `mf-social-whatsapp`, `mf-ui-mail` |
| S1 hero micro-list | `mf-ui-check-circle` |
| S2 search button | `mf-ui-search` |
| S3 Why Mayfair | `mf-verified-since-1999`, `mf-verified-shield-rera`, `mf-verified-handshake-developer`, `mf-verified-map-pin-gurugram` |
| S4 category tiles (optional) | `mf-property-apartment`, `mf-property-villa`, `mf-property-office`, `mf-property-plot` |
| S5 property card | `mf-property-bed`, `mf-property-bath`, `mf-property-area`, `mf-property-parking`, `mf-verified-badge-verified` |
| S6 locality chips | `mf-ui-arrow-right` |
| S7 services | `mf-services-buy-home`, `mf-services-sell-tag`, `mf-services-rent-key`, `mf-services-invest-chart`, `mf-services-consult-chat` |
| S8 valuation | `mf-services-valuation-rupee` |
| S11 CTA | `mf-ui-clock`, `mf-ui-check-circle`, `mf-ui-phone`, `mf-social-whatsapp` |
| S12 footer | `mf-social-facebook`, `mf-social-instagram`, `mf-social-x`, `mf-social-google`, `mf-ui-map-pin`, `mf-ui-mail` |

## Motion — deliberately restrained

Principle: motion confirms intent, it does not decorate. One easing curve, two durations (0.28s / 0.6s), ≤4px travel, **no loops, no bounce, no self-drawing strokes**. Honours `prefers-reduced-motion`.

Setup (once): Site Settings → **Custom CSS** → paste `site-custom.css`. Custom Code → `</body>` → paste `motion-reveal.html`.

| Class (Advanced → CSS Classes) | Put on | Effect |
|---|---|---|
| `mf-card` | Icon Box / card container | hover: lift 2px, border → gold-light, soft shadow; glyph deepens to Ink (light) or Ivory (dark). Framed/Stacked badge fills Gold. |
| `mf-on-dark` | with `mf-card` on Ink sections | hover glyph → Ivory instead of Ink |
| `mf-arrow` | Buttons / chips with arrow icon | arrow moves 4px right on hover |
| `mf-reveal` | the **grid container** (S3/S4/S6/S7) | children fade + rise 12px on scroll, 80ms stagger |

Turn Elementor's own Entrance Animations **off** wherever `mf-reveal` is used. Hover colours can also be set natively in the Style tab (Icon → Hover) — `mf-card` only adds the lift/border so cards and icons move together.
