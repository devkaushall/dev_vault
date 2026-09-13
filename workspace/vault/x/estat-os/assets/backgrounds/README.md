# Mayfair background elements

From the office's own asset package:
`github.com/devkaushall/dev_vault` → `Stock Package/mayfair-backgrounds`.

Thin gold (`#725B2F`) line-work at 10–22% opacity. These are **background
layers only** — never content, and never more than one per section.

## What is here, and what is not

The four small tileable pieces — `bg-grid-plan`, `bg-dots`, `bg-hatch` and
`bg-plots` — are **not** in this folder. They are inlined as data URIs inside
`assets/css/admin.css`, because each is under 700 bytes and the office screens
should not wait on a second request before the first paint.

What remains are the larger illustrative pieces, kept as files because they are
8–24 KB and because a browser can cache them across page views on the public
site.

| File | Where it is meant to go | Repeat | Size | Position |
|---|---|---|---|---|
| `bg-arcs-corner.svg` | Property grid, team, compare | No repeat | 40–60% | Top right |
| `bg-frame-corners.svg` | A single property page | No repeat | 100% 100% | Centre |
| `bg-contours.svg` | About, big statements | No repeat | Cover | Centre |
| `bg-sector-map.svg` | Localities, contact | No repeat | Cover | Centre |
| `bg-skyline-lines.svg` | Footer top, bottom of a CTA | Repeat-x | Auto 40% | Bottom centre |
| `bg-glow-dark.svg` | Dark sections only | No repeat | Cover | Centre |
| `bg-vertical-label.svg` | Left edge of a hero | No repeat | Auto 70% | Left centre |
| `bg-watermark-1999.svg` | About or Why, once only | No repeat | 60% | Bottom right |
| `grain-256.png` | Overlay on a photograph | Repeat | Auto | — |

## Using them in Elementor

Style → Background → Image → choose the SVG → set Position, Repeat and Size
from the table above → then set the section's background **colour** as well.

For a photograph, add `grain-256.png` as a Background Overlay at 100% opacity
with the blend mode set to Multiply. That is what stops a stock photo looking
glossy and artificial.

Elementor needs **Enable Unfiltered File Uploads** turned on before it will
accept an SVG.

## Rules

- One element per section. Never two.
- Light sections: grid, dots, hatch, plots.
- Big statements: contours, sector map.
- Dark sections: glow and skyline only.
- Never behind body text. Put it behind the container, and let the card sit on
  top of it.
