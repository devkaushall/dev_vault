# Mayfair BG PRO set (17 SVG)

Same rules as the basic set: gold #725B2F hairlines only, 5–35% opacity, one element per section, max 4 sections per page.
Open `preview-pro.html` – every element is shown on a real section mock-up with its exact Elementor settings.

| File | Section | Repeat | Size | Position | Extra |
|---|---|---|---|---|---|
| pro-corridor-map.svg | Hero (dark) / Contact | No | Cover | Center | class `mf-bg-drift` on the bg container |
| pro-floor-plan.svg | Services / Sell | No | Cover | Center right | class `mf-bg-fade-l` |
| pro-elevation.svg | Featured / footer top | Repeat-x | Auto 70% | Bottom center | |
| pro-skyline-far / mid / near.svg | Dark CTA | Repeat-x | Auto 45% | Bottom center | 3 empty containers `mf-bg-layer far/mid/near` inside `mf-parallax` + parallax.js |
| pro-facade-perspective.svg | Why / About | No | Cover | Center | |
| pro-terrain-section.svg | Plots / Invest (Sand) | Repeat-x | Auto 60% | Bottom | |
| pro-node-network.svg | Localities / Insights | No | Cover | Center | class `mf-bg-fade-b` |
| pro-halftone-fade.svg | Sell hero | No | 100% 100% | Center | |
| pro-sunburst-corner.svg | Team / Thank-you | No | 70% | Top right | |
| pro-compass.svg | About | No | 60% | Right center | |
| pro-iso-block.svg | Category tile corner | No | 240px | Bottom right | |
| pro-double-frame.svg | Featured / quote | No | 100% 100% | Center | |
| pro-ledger-rows.svg | Services 01–08 list | No | 100% 100% | Center | |
| pro-vein-lines.svg | Luxury listing header | No | Cover | Center | |
| pro-ruler-edge.svg | Any section top border | Repeat-x | 200px 40px | Top | or class `mf-ruler-top` (replace RULER_URL) |

`mf-bg-pro.css` → append to Site Settings Custom CSS. `parallax.js` → Elementor Custom Code, location `</body> End`.
Dark sections: SVGs are gold on transparent, so they work on Ink #1A1A1A without changes.
