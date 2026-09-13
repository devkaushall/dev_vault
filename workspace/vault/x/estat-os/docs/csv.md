# Spreadsheets

**Office → Spreadsheet** imports and exports CSV files. Save your sheet as CSV
from Excel or Google Sheets first — *File → Download → Comma-separated values*.

* Maximum file size: 20 MB. Split anything larger.
* Rows are processed 50 at a time so the site never stalls.
* Uploaded files are stored privately inside `wp-content/uploads/estat-imports/`,
  protected by an `.htaccess` deny rule.

## Avoiding duplicates

Give every row a value in `external_id` — your own reference number. Estat.OS
matches on it, so importing the same file twice updates rather than duplicates.
Imported enquiries are additionally de-duplicated on a hash of the whole row.

Choose whether existing rows should be updated or skipped, and whether new
properties go live immediately or arrive as drafts. **Drafts are the safer
choice** and are the default.

## Property columns

Only `title` is genuinely required. Leave out any column you do not use.

| Column | What to put in it |
| --- | --- |
| `external_id` | Your own reference number |
| `title` | The headline |
| `description` | The full description |
| `offer` | `sale`, `rent` or `lease` |
| `property_type` | `apartment`, `villa`, `plot`, `shop`, … |
| `locality` | Locality name — created automatically if new |
| `address` | Full address (never shown publicly) |
| `price` | Numbers only, no symbols or commas |
| `rent` | Monthly rent, numbers only |
| `price_type` | `fixed` or `on_request` |
| `area` | Numbers only |
| `area_unit` | `sqft`, `sqyd` or `sqm` |
| `bedrooms`, `bathrooms`, `balconies` | Whole numbers |
| `floor`, `total_floors` | Whole numbers |
| `furnishing` | `unfurnished`, `semi`, `full` |
| `facing` | `north`, `south`, `east`, `west`, … |
| `availability` | `available`, `under_offer`, `sold`, `rented` |
| `construction` | `ready`, `construction`, `new_launch` |
| `agent_ref` | The team member's reference number |
| `project_ref` | The society's reference number |
| `regulatory_id` | Registration number |
| `latitude`, `longitude` | Map position |

Societies, team members and enquiries have their own column sets, all listed on
the screen itself so you never have to leave the page to check.

## Exporting

One button per record kind. Files are UTF-8 with a byte order mark so Excel
opens Indian names and the rupee symbol correctly. Your data is always yours and
always leaves in a format any spreadsheet can read.
