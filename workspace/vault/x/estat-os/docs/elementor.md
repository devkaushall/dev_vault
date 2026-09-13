# Elementor widgets

Elementor is entirely optional — every screen and every page works without it.
When Elementor is active, Estat.OS adds a **Estat Real Estate** category with
seventeen widgets.

The bridge only loads on `elementor/loaded`, so nothing is required at runtime
and nothing breaks when Elementor is removed.

## The widgets

| Widget | What it shows |
| --- | --- |
| Property Grid | A grid of listings with filters |
| Property List | The same data in rows |
| Property Carousel | A sliding row |
| Featured Properties | Whatever you have marked as featured |
| Property Search | The search form |
| Property Map | A map of listings |
| Single Property Gallery | Photos of the current property |
| Single Property Details | The details table |
| Single Property Price | Price with your chosen formatting |
| Single Property Map | One map position |
| Project Grid | Societies and projects |
| Project Details | Facts about one project |
| Agent Grid | The team |
| Agent Card | One person |
| Contact Call to Action | Call and WhatsApp buttons |
| Insights Grid | Your articles |
| **Estat Form** | One of your forms |

## Presentation only

Elementor controls how things look, never what they mean. **You cannot edit form
fields inside Elementor.** Build the form once under **Office → Forms**, then
drop the *Estat Form* widget on any page and choose it from a dropdown. The same
form can appear on as many pages as you like, and changing it in one place
changes it everywhere.

This is deliberate. Form definitions that live inside page builder data are
impossible to back up, impossible to export, and lost the moment the page is
rebuilt.

## Without Elementor

Every widget has a shortcode equivalent, and the plugin renders full pages on its
own through its fallback templates. For example:

```
[estat_form id="3"]
[estat_properties offer="sale" locality="green-park" per_page="9"]
[estat_search]
[estat_favorites]
[estat_compare]
```
