=== Estat.OS — Real Estate Operating System ===
Contributors: devil
Tags: real estate, property, listings, enquiries, crm
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.6.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Run a real-estate office from WordPress: listings, enquiries, site visits, team, forms and reports, in plain language.

== Description ==

Estat.OS is the operating system for a small or medium estate office. WordPress
is the platform it runs on; this plugin is the business system.

It handles the whole cycle an office actually works through: put a property on
the website, a visitor asks about it, the enquiry lands in your inbox, somebody
is made responsible, a site visit is arranged, and it is marked as won or lost.
Reports then tell you how that is going.

The screens are written for someone who does not build websites. There is no
talk of custom post types, meta fields or endpoints anywhere in the interface.
A property is a "property", not a "post".

**What it manages**

* Listings, with photos, price, size, features and a readiness score
* Societies and projects that several listings belong to
* Your team, and who is responsible for what
* Enquiries, with notes, follow-up dates and duplicate protection
* Site visits, and how each one went
* Blogs, articles and market insights
* A gallery of every photo and document in one place
* Forms you design yourself, which feed straight into Enquiries
* Import and export by spreadsheet
* Reports on enquiries, visits and team activity

**What it does not require**

* No page builder. Elementor widgets are included, but if Elementor is switched
  off every part of the office keeps working.
* No ACF or any other field plugin. Estat.OS owns its own data model.
* No Composer, no build step, no external service.
* Maps work out of the box with OpenStreetMap. A Google key is optional.

**Your data stays yours**

Deactivating the plugin changes nothing. Deleting it changes nothing either,
unless you have explicitly ticked the box in Office Settings that says to erase
everything. Nothing is ever thrown away because a plugin was removed.

Every listing, enquiry and visit can be exported to a spreadsheet at any time.

**Languages**

English and Hinglish written in ordinary English letters, the way most Indian
offices actually speak. The language of the office screens and the language of
the public website are set separately. Information you type in — a property
name, a customer's message — is never translated.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install the ZIP from
   Plugins → Add New → Upload Plugin.
2. Activate it.
3. Open **Office** in the admin menu. A short setup asks four questions and
   builds your website pages for you. You can skip it and do everything by hand.

== Frequently Asked Questions ==

= Do I need Elementor? =

No. Estat.OS renders its own property pages, search and forms. If you do use
Elementor, seventeen widgets are available so you can lay those pages out
yourself instead.

= What happens to my properties if I remove the plugin? =

They stay exactly where they are. Removing the plugin does not delete business
data unless you have deliberately asked for that in Office Settings → Tools.

= Can two people use it at once? =

Yes. There are two roles — Office Owner and Office Agent — and each enquiry can
be handed to a particular person. Permissions are checked on every action, not
just hidden from the menu.

= How many properties can it handle? =

It is built and tested for a small or medium office on ordinary shared hosting,
comfortably into the thousands. Lists are paged, searches are indexed, and
imports are processed a few rows at a time so a large spreadsheet does not time
the site out.

= Is the information visitors send me protected? =

Enquiries are stored in your own database, never sent anywhere else. Duplicate
submissions are merged, spam is filtered without a third-party service, and a
person's name, phone and email can be erased on request while keeping the
record of the enquiry itself.

== Screenshots ==

1. Today — what needs attention right now
2. Listings, with readiness scores
3. The property editor, grouped into three short chapters
4. Enquiries, with follow-up dates and notes
5. The form studio: seven steps, live preview
6. Reports

== Changelog ==

= 2.3.0 =
* Twelve listing fields were being collected and shown to nobody. All are now
  on the property page, including deposit, maintenance, video and 360 tour.
* Projects had no reader at all; the builder, price range, unit counts and
  possession date now appear on the project page.
* "Export site visits" produced an empty listings sheet. It now exports visits.
* Erasing everything on uninstall missed Insights and its two taxonomies.
* One caller typing "9876543210" and "+919876543210" became two enquiries.
* Titles are capped and control characters stripped.
* Today and Listings now check permission themselves rather than relying on
  the menu alone.

= 2.2.0 =
* Act on many records at once, with permission checked per record.
* Undo the last edit to a listing, society or team member.
* Keyboard shortcuts, with a visible list under "?".
* Hinglish finished: 1,504 strings.

= 2.1.0 =
* Dropdowns no longer hide records past the two hundredth without saying so.
* Societies and Team can be searched and paged.
* Nonsense numbers are refused with an explanation.

= 2.0.0 =
* A real type scale, a real icon set, and no pure black or white.

Earlier releases are listed in CHANGELOG.md inside the plugin folder.

== Upgrade Notice ==

= 2.3.0 =
Fixes twelve property fields and seven project fields that were saved but never
displayed. If you have been filling in deposit, video links or possession dates,
they will start appearing on your website after this update.
