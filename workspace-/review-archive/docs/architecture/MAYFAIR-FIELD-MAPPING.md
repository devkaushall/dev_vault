# Mayfair Field Mapping

All mappings are read/adoption proposals until tested with real artifacts. Compatibility mode does not rewrite source metadata; Migration mode requires a future explicit manifest and confirmation.

| Mayfair key | Canonical key | Storage | Source | Compatibility behavior | Migration behavior | Verified? |
|---|---|---|---|---|---|---|
| `mpd_reference` | `rep_reference` | string meta | Mayfair candidate | read through adapter; preserve source | copy only after conflict check | NOT VERIFIED |
| `mpd_price` | `rep_price` | numeric meta | Mayfair candidate | read, no rewrite | parse numeric; reject formatted ambiguity | NOT VERIFIED |
| `mpd_currency` | `rep_currency` | string meta | Mayfair candidate | preserve | normalize controlled currency | NOT VERIFIED |
| `mpd_floor` | `rep_floor` | integer meta | ambiguous | never merge with floor level silently | manual mapping choice | NOT VERIFIED |
| `mpd_floor_level` | `rep_floor` | integer meta | ambiguous | keep distinct source | manual conflict resolution | NOT VERIFIED |
| `mpd_video_tour_url` | `rep_virtual_tour` | URL meta | ambiguous | read independently | explicit target selection | NOT VERIFIED |
| `mpd_video_url` | `rep_video` | URL meta | ambiguous | read independently | explicit target selection | NOT VERIFIED |
| `mpd_featured` | `rep_featured` | boolean meta | ambiguous | preserve | explicit boolean conversion | NOT VERIFIED |
| `mpd_show_on_homepage` | `rep_featured` | boolean meta | ambiguous | do not equate automatically | owner-approved rule | NOT VERIFIED |
| `mpi_external_source_url` | `rep_external_source` | URL meta | ambiguous Insight candidate | preserve/read independently | explicit selection | NOT VERIFIED |
| `mpi_source_url` | `rep_external_source` | URL meta | ambiguous Insight candidate | preserve/read independently | explicit selection | NOT VERIFIED |
| `mpd_latitude` | `rep_latitude` | numeric meta | Mayfair candidate | validate without rewrite | range-check WGS84 | NOT VERIFIED |
| `mpd_longitude` | `rep_longitude` | numeric meta | Mayfair candidate | validate without rewrite | range-check WGS84 | NOT VERIFIED |
| `mpd_gallery` | `rep_gallery` | attachment references | Mayfair candidate | preserve IDs/order | validate attachment IDs | NOT VERIFIED |

Unknown `mpd_*`/ACF metadata is preserved. No ACF field group or field key is created or overwritten. Real artifact inventory is still NOT VERIFIED.
