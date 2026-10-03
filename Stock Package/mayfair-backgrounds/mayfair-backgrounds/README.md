# Mayfair background elements

Thin gold (#725B2F) line-work, 7–22% opacity. Use ONLY as container background layers — never as content.
Open `preview.html` to see all of them on ivory/ink.

## How to apply in Elementor (any container)
Style → Background → Image → choose SVG → Position / Repeat / Size as in the table → then set the section's
background **colour** too (Ivory #F9F7F2, Sand #F2EDE4 or Ink #1A1A1A). For photos add `textures/grain-256.png`
as Background Overlay (opacity 100%, blend mode Multiply) to kill the "AI glossy" look.

| File | Where | Repeat | Size | Position |
|---|---|---|---|---|
| svg/bg-grid-plan.svg | Why, Services (light) | Repeat | Auto | Top left |
| svg/bg-dots.svg | Team, FAQ | Repeat | Auto | Top left |
| svg/bg-hatch.svg | Sell banner, strip | Repeat | Auto | Top left |
| svg/bg-contours.svg | About, CTA (light or dark) | No repeat | Cover | Center |
| svg/bg-sector-map.svg | Localities, Contact | No repeat | Cover | Center |
| svg/bg-plots.svg | Plots tile, Invest | Repeat | Auto | Top left |
| svg/bg-arcs-corner.svg | Hero fact strip, Sell hero | No repeat | 60% | Top right |
| svg/bg-frame-corners.svg | Featured section frame | No repeat | 100% 100% | Center |
| svg/bg-skyline-lines.svg | Footer top, CTA bottom | Repeat-x | Auto 40% | Bottom center |
| svg/bg-glow-dark.svg | DARK sections only (mf-dark) | No repeat | Cover | Center |
| svg/bg-watermark-1999.svg | About / Why, one time | No repeat | 60% | Bottom right |
| svg/bg-vertical-label.svg | Left edge of hero | No repeat | Auto 70% | Left center |
| textures/paper-ivory-512.webp | Instead of flat ivory | Repeat | Auto | — |
| textures/paper-sand-512.webp | Instead of flat sand | Repeat | Auto | — |
| textures/paper-ink-512.webp | Instead of flat ink | Repeat | Auto | — |
| textures/grain-256.png | Overlay on photos | Repeat | Auto | — |

Rules: one element per section, never two. Light sections: grid/dots/hatch/plots. Big statements: contours/map.
Dark: glow + skyline only. Elementor needs "Enable Unfiltered File Uploads" for SVG (already on).
