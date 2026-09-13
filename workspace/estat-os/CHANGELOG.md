# Changelog

All notable changes to Estat.OS are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows
[semantic versioning](https://semver.org/).

## [2.7.0] — 2026-09-12

An agent standing in a flat now has a way to fix what the website has wrong,
without anyone losing control of what is published.

### Added

* **Change requests.** A property belongs to whoever added it, and until now
  anybody else in the office simply could not touch it — not even to correct a
  price they had just been told in person. They can now propose a change from
  the property itself: four fields, one short note about how they know. The
  office sees it on a new **Change Requests** screen and on their Today list,
  and says "Yes, change it" or "Not now" with a word of explanation. Approving
  writes through the same save path the editor uses, so validation, the search
  index, the audit trail and the undo snapshot behave exactly as if the office
  had typed it.

  Only the fields a person on the spot can genuinely confirm are offered —
  price, rent, whether it is still available, the description, and a note. A
  reviewer always sees today's value and the proposed value side by side,
  because "change the price to 8500000" cannot be judged from one number.

  Declining changes nothing. The property is left exactly as it is, and the
  agent's note is kept on the record as a dated line, so the thing they
  discovered is not thrown away just because the timing was wrong.

* **A mail for it, if you want one.** Office Settings → Emails has a new switch,
  "A change asked for from the field". It is off by default — a queue that also
  emails is two places to look — and one line, because the deciding happens on
  the screen, not in an inbox.

### Fixed

* **A dangling entry in the action permission map.** `trash_listing` was listed
  as an action but was never routed anywhere. Deleting a property has long gone
  through the general "remove" action, which does exist and is checked per
  record kind; the leftover entry made it look as though deletion had no route
  at all, which is how an auditor came to believe properties could not be
  removed. It is gone.

### Added — tests

* `tests/integration/it-31-field-requests.php`. The permission line, the four
  fields and nothing else, an approval that really moves the record, a refusal
  that really does not, the self-vouching guard, and a review screen that
  renders.

### Notes

* Two new office permissions appear in Team: *Ask the office to change a
  property that belongs to somebody else* (agents have it) and *Approve or turn
  down changes proposed from the field* (only owners and managers do). Nothing
  existing changes hands: an agent could not edit a colleague's property before,
  and still cannot.

## [2.6.0] — 2026-09-11

Four details, each one identified by measuring this plugin against published
conversion and performance research rather than by guessing.

### Fixed

* **The main photograph on a property page was slowing the site down and making
  it jump.** It carried no width, no height and no fetch priority. It is
  almost always the largest element on the page, which is what Google times the
  load against, so the browser was fetching it late and laying the page out
  without reserving space for it. It now states its dimensions, is fetched
  ahead of the queue, and is never lazy loaded. Card and agent photographs
  state their dimensions too.
* **"Just listed" never appeared on the day a property went live.** The check
  asked for more than zero days, but a listing published an hour ago reports
  zero — the same as a draft that was never published. The flag was hidden on
  the one day it is worth the most. Both the card and the property page now
  test whether it is live, separately from how old it is.

### Added

* **A verified badge.** The office already recorded whether it had checked a
  property and showed that to nobody. On Indian portals the commonest
  complaint is not being able to tell which listing is real, so an office that
  does the checking should be credited for it.

  Only "verified by our office" earns the badge. "Checked by the owner" does
  not, because that is a different claim and dressing one up as the other is
  exactly what made buyers stop trusting listing sites.

* **How long a property has been on the website.** Buyers read a new listing as
  "go this weekend" and an older one as "there may be room to negotiate". It is
  counted from publication, not creation — a property that sat in drafts for a
  month has not been on the market for a month. The card only says "just
  listed" while that is true; the property page gives the real figure.

* **A monthly repayment estimate** on sale listings. Research is consistent
  that this is the most used tool on a property page: a buyer who works out
  that a flat is within reach is far likelier to enquire than one left
  guessing.

  It runs entirely in the browser. Nothing is sent anywhere, nothing is stored,
  and it is not a form — somebody working out affordability is thinking, not
  enquiring, and should not become a lead for it. The arithmetic uses the
  formula every Indian lender publishes and was checked against six figures
  published by HDFC and others; all six match exactly. It defaults to a 20%
  deposit, 8.5% and twenty years, and formats its answer in lakhs and crores
  so it reads the same as the price above it.

* **A privacy note under the enquiry form.** The loudest complaint about Indian
  property portals is that one enquiry produces dozens of broker calls. This
  plugin has never sold a number on, and that was going unsaid. It now says so
  plainly, and a test checks the claim stays true.

### Added — tests

* `tests/integration/it-30-conversion-details.php`. All twelve mutations are
  caught, including one that would have made the calculator a form, and one
  that would have let the owner's own word earn a verified badge.

  The visitor still downloads 9KB gzipped, unchanged by any of this.

## [2.5.0] — 2026-09-11

The visitor's side of the site, which had been getting far less care than the
office screens.

### Changed

* **The public stylesheet was rewritten**, from 185 mostly-minified lines with
  hardcoded colours to 1,055 lines built on tokens. The office screens had four
  thousand lines of attention and the front of the site had a hundred and
  eighty; that imbalance is why it felt unfinished.

  Thirty-odd design tokens, scoped to the plugin's own wrapper classes rather
  than `:root`, so a theme can restyle the whole thing by redefining a handful
  of names and never has to fight specificity.

* **Every state is designed now**, not only the resting one: hover, keyboard
  focus, pressed, no photograph, no results, dark mode, reduced motion,
  printing, and Windows high-contrast.

* **The property card** got the most attention, because it appears on the home
  page, in search results and beside every other property. It lifts slightly on
  hover, the photograph scales gently inside its frame, the whole card is
  clickable through a stretched link on the title rather than a nested anchor,
  and tabbing to it lights the whole card rather than just the text.

* **A property with no photograph** now gets designed diagonal line-work
  instead of a grey rectangle. It is a real state, not an accident.

### Added

* **Three demo photographs** ship with the plugin and are attached to the
  practice properties. Practice mode exists so somebody can see what a finished
  office looks like, and three listings with no images taught the opposite.

### Fixed

* **Nine classes were rendered by the templates with nothing styling them** —
  the compare tray, the call to action, the favourite button and others. A test
  now compares what the templates render against what the stylesheet covers.
* **Seeding practice mode twice copied the same photographs again.** The lookup
  that was meant to reuse them searched with the default post status, and an
  attachment's status is `inherit`, so it never matched.
* **Clearing practice mode left the photographs behind.** Attachments are not
  in `PostTypes::all()`, so the cleanup walked straight past them and orphaned
  three images and three files every time.
* **A `require_once` on `wp-admin/includes/image.php` was unguarded.** A
  missing file there is a fatal error, not a warning, so an entirely cosmetic
  step — generating thumbnail sizes — could have taken down any request that
  seeded a demo office.
* The demo photograph loader now checks that the uploads folder is writable and
  falls back to the uploads root when the month folder is unavailable, rather
  than assuming and writing to the filesystem root.

### Fixed — test harness

Three stubs were lying, each in the direction that makes broken code look fine:

* `wp_insert_attachment()` **did not exist at all**, so any code importing a
  file into the media library silently did nothing here while working properly
  in production.
* `wp_upload_dir()` returned only `basedir`, never `path`, which core always
  fills.
* `wp_delete_attachment( $id, true )` dropped the record but left the file, so
  code that cleans up after itself looked as though it leaked.
* `wp_check_filetype()` reported no MIME type for a JPEG.

### Added — tests

* `tests/integration/it-29-public-face.php`. Six mutations, all caught — though
  three initially survived and each revealed something real: one assertion
  looked for a single `transform: none` when there are two movements to cancel
  and passed with one deleted; one mutation was too broken to compile; and one
  regression is a PHP fatal that no `try`/`catch` can intercept, which is now
  stated plainly in the test rather than pretended away.

## [2.4.0] — 2026-09-11

The interface now uses the office's own Mayfair asset package for its icons and
background artwork.

### Changed

* **The icon set moved to Mayfair.** 98 icons, Phosphor Regular, one family, all
  on a 256-unit grid with `fill="currentColor"` and no hard-coded colour or
  size. The previous set was 47 icons drawn by hand at 24 units; mixing two
  grids makes some icons look heavier than others at the same pixel size, so
  the whole set moved at once.

  Every name the product already used still works — the mapping was checked
  name by name before the swap — and 51 more are now available.

* **Background line-work added**, from the same package: thin gold `#725B2F` at
  10-22% opacity, behind containers only. The four small tileable pieces are
  inlined in the stylesheet as data URIs, each under 700 bytes, because the
  office screens should not wait on a second request before the first paint.
  The larger illustrative pieces ship as files, where a browser can cache them
  across page views.

  The rules from the package README are followed: one element per area, never
  behind body text, cards clear it explicitly, dark mode drops it entirely
  (gold on ink reads far stronger than gold on ivory), and printing drops it
  too rather than spending ink on decoration.

* `assets/backgrounds/README.md` documents what each piece is for, where it
  belongs, and how to apply it in Elementor.

### Fixed

* The 104 icon files were briefly shipped alongside the inlined paths — 420 KB
  that nothing read at runtime. Removed; the four inlined backgrounds were
  removed as files for the same reason, so there is one copy of each piece of
  artwork rather than two that can drift apart.

### Added — tests

* `tests/integration/it-28-mayfair-assets.php`. Six mutations, all caught — but
  two initially survived and both revealed genuinely weak assertions:

  * Checking the rendered `viewBox` proves nothing, because the wrapper always
    writes 256. An icon copied in from a 24-unit family would render at a tenth
    of the size and pass every check. The test now measures the largest
    coordinate in the artwork itself.
  * A loose pattern for "dark mode clears the background" matched whichever
    dark rule it found first, so deleting the page background entirely still
    passed. Each surface is now named.

* `it-13` no longer fails the build for naming a colour inside a comment.
  Explaining why a colour exists is the opposite of hiding one, and failing for
  it teaches people to delete the explanation rather than the hardcoded value.

## [2.3.2] — 2026-09-11

Documentation caught up with the code, and a test added so it cannot fall
behind again.

### Changed

* **The manual was sixteen releases out of date.** Its header still said 1.7.0
  and the README still said 1.0.0. Everything added since — bulk actions, undo,
  keyboard shortcuts, the seven-step form studio, twenty-five styling settings,
  finished Hinglish — was missing entirely.
* **Added Part 7: the full history.** Every one of the twenty-two releases, in
  plain language, newest first, with what changed and *why*. Most entries exist
  because something was genuinely wrong and somebody noticed; the history says
  so rather than listing features.
* The manual now explains what the three numbers in a version mean, so a reader
  can tell a safe update from one worth reading carefully.
* Added an honest "what is still not perfect" list: the orphan-visit case, the
  price-on-request view, the harness's inability to test paging properly, and
  the fact that nothing here has been run in a browser by its author.

### Added — tests

* `tests/integration/it-27-docs-current.php` checks the facts that go stale on
  their own: that all five documents name the shipping version, that every
  release in the changelog appears in the manual history, that numbers quoted
  in the manual match the constants they describe, that every documented
  shortcode is registered, and that the known-limitations section is still
  there and still names the real limits.

  This exists because a manual nobody checks becomes confidently wrong, which
  is worse than having none. All six mutations are caught, including the
  original fault: letting the manual header go stale.

## [2.3.1] — 2026-09-10

Five more inspections, each using a different method rather than repeating the
sixty-check sweep: a full user journey, an adversarial pass, every state
transition, deliberate environment failures, and documented promises against
reality. Two findings, both small — which is what a recently audited codebase
should look like.

### Fixed

* **A page number could exceed the number of pages.** `Query::search()` handed
  back whatever page was asked for, so page 100000 of a two-page result
  answered `page=100000, pages=2`. The SQL was always right and no wrong rows
  were ever returned, but anything printing "page X of Y" printed a page that
  cannot exist, and `paginate_links()` was given a current page outside its own
  range. The page is now clamped after counting, with a floor of one so an
  empty result never reports page zero.
* **There was no readme.txt.** A plugin cannot be listed in the WordPress.org
  directory without one, and an installer had no standard place to see what
  version of WordPress it was tested against. Added, with the stable tag, PHP
  and WordPress requirements, and a plain-language description.

### Added — tests

* `it-26` now pins the paging clamp and the distribution metadata, including
  that the plugin header, the `ESTAT_VERSION` constant, the readme stable tag
  and the changelog all agree, and that the PHP requirement matches in both
  files. A stale stable tag is the classic way a release ships that nobody can
  install.

  Six mutations, all caught. Two initially survived and both were my own fault
  rather than the code's: one mutation was too weak to change behaviour at all,
  and one assertion was searching a nonsense word while other tests' listings
  were still indexed, so it never exercised the empty-office path it claimed to.

### Noted, not fixed

* The fake `wpdb` in the test harness ignores `LIMIT` and `OFFSET`, so page two
  and page two hundred return identical rows there. Every paging assertion in
  the suite is weaker than it looks. This is a harness limit, not a plugin bug,
  but it is worth knowing before trusting a green paging test.

## [2.3.0] — 2026-09-10

A sixty-check audit of the whole plugin, and the eleven faults it found.

Three of them shared one shape, worth naming because it is the shape most
likely to happen again: the WRITE path and the READ path were built at
different times and nobody compared them. The editor collected a field, the
validator accepted it, the database stored it — and no reader ever asked for
it. Every piece worked on its own. Only the join was missing.

### Fixed — information that went nowhere

* **Twelve listing fields were stored and shown to nobody.** Deposit,
  maintenance, balconies, total floors, age, facing, area type, video link,
  360 tour, builder, possession date and registration number were all absent
  from `Listings::to_array()` in both modes, absent from REST, and unread by
  the property template. The office typed a security deposit and a video link
  into fields that reached no visitor. All twelve are now exposed and shown on
  the property page, with deposit and maintenance in their own "Other costs"
  section and the video and tour as real links.
* **Projects had no reader at all.** Listings and Agents both had a
  `to_array()`; Projects did not, so the builder, price range, unit counts,
  possession date, unit types and registration number reached nobody. There is
  now a `Projects::to_array()` and the project page shows all of it.
* **"Export site visits" exported nothing.** The button existed, but the
  exporter only knew listings, projects, agents and leads, so the request fell
  back to listings and the office downloaded an empty sheet with the wrong
  column headers and no warning. Visits now export properly. They still cannot
  be imported, because a visit only means anything attached to an enquiry.

### Fixed — data integrity

* **"Erase everything" did not erase everything.** `uninstall.php` never
  deleted the Insights content type or its two taxonomies; they were added
  after that file was written and the hand-copied lists drifted. An office that
  deliberately ticked the box kept every article for ever.
* **One caller became two enquiries.** The dedupe hash used every digit of a
  phone number, so "9876543210" and "+919876543210" were different people. Both
  forms are typed constantly in India, and the office rang them twice. The last
  ten digits are now used, which handles +91, 0091 and a leading zero without
  needing a country-code table.
* **A NUL byte survived into a title.** Core's `sanitize_text_field()` does not
  strip it. All C0 control characters except tab are now removed.
* **A 6,000-character headline saved happily** and wrecked every table and card
  it appeared in. Titles are capped at 180 characters, cut on a word boundary.

### Fixed — consistency

* Today and the Listings list rendered for a user with no office capability.
  Twelve other screens check first; these two leaned on the WordPress menu
  alone. Both now check.
* One JavaScript block read `estatAdmin.strings` while PHP localises `i18n`.
  Nothing called it yet, so nothing was visibly broken — but the next person to
  use it would have got English whatever language was chosen.
* `Schema::drop()` was dead code holding a second copy of the table list that
  `uninstall.php` also holds. Removed, so there is one list to keep right.
* The public and form stylesheets carried raw font sizes. More importantly,
  **it-23 only applied its own "no size outside the scale" rule to the admin
  stylesheet** while loading all three — a hole in a rule that was being
  trusted. The rule now covers all three sheets.

### Added — tests

* `tests/integration/it-26-audit-fixes.php` pins all eleven. All twelve
  mutations are caught. One initially took the whole run down with a fatal
  rather than failing cleanly, which would have hidden every later group; that
  is now guarded.

## [2.2.0] — 2026-09-10

The four things left on the list: doing many records at once, taking back a
mistake, keyboard shortcuts, and the office screens in Hinglish.

### Added

* **Act on several records at once.** Tick boxes on the Listings screen, then
  put them on the website, take them off, move them to the bin, or bring them
  back. The bar only appears once something is ticked.

  It is deliberately not a shortcut around permission: every record is looked
  up, matched to its kind, and checked against that kind's own capability. A
  user who may bin listings but not team members gets exactly the listings
  binned and is told the rest were skipped. Permanent deletion is not offered
  at all — removing many records forever from a checkbox column is too easy to
  do by accident — and no more than 100 can be changed in one go.

* **Undo the last edit.** Deleting was already safe; editing was not. Change a
  price by a digit and the old one was gone. Listings, societies and team
  members now keep a small snapshot taken immediately before each save: the
  last five, for twenty-four hours, stored with the record so they are removed
  with it.

  Two deliberate limits. Only fields the office edits are captured, never
  computed ones, so undo cannot restore a stale readiness score. And the
  publication status is captured but **never restored** — undoing a price edit
  must not put a property back on the website that somebody has since taken
  off it. Undo can itself be undone.

* **Keyboard shortcuts** for people who live in these screens: `/` for search,
  `n` for Add a Home, `g` then `t`/`l`/`e` to jump, Ctrl or Cmd + S to save,
  `?` for the list. Nothing fires while you are typing, Ctrl+S only takes the
  key when there is really a form to save, and the page says "Press ? for
  shortcuts" so it is discoverable without knowing already.

* **Hinglish, finished.** The translation was 28% done. It is now complete:
  1,504 strings, in natural Roman-script Hinglish. Business data the office
  types in is never translated — only the interface.

### Fixed

* **The string extractor was missing 627 strings.** It captured up to 400
  characters after each match to find plurals and contexts, which swallowed
  any later `__()` in the same statement — a `printf()` with three translated
  arguments lost two of them. Every screen had gaps nobody could see.
* **The compiled translation could not be read by WordPress at all.** The .mo
  writer left the hash address as 0; core checks it and rejects the file, so
  every translation silently fell back to English. A test now reads the
  compiled file with WordPress's own parser rather than trusting the writer.
* An unreachable negative-rent check was removed, and the negative-price check
  — which could never fire, because the sanitiser floors at zero before it —
  now reads what was actually typed.

### Added — tests

* `tests/integration/it-25-bulk-undo-shortcuts-language.php`. All twelve
  mutations are caught. Two initially survived and both revealed genuinely
  weak assertions: one built its record list from `BULK_LIMIT`, so raising the
  limit to a million still passed; the other checked that the "are you typing"
  guard was *defined* rather than *called*, so deleting the call — which makes
  every letter key fire mid-sentence — went unnoticed. Both are now pinned
  properly.

## [2.1.0] — 2026-09-10

Behaving properly once an office has a lot of records. Every fault here shared
one habit: the code stopped early and said nothing.

### Fixed

* **Dropdowns hid records past the two hundredth.** Agent and project pickers
  fetched a flat 200 ordered by title, so the two hundred and first was simply
  absent with no message — which to the office is indistinguishable from a
  record that has been lost. There is now a `Data\Picker` that keeps the most
  recently updated, sorts them alphabetically for reading, **says on screen how
  many were left out**, and always includes the record currently attached even
  when it falls outside the limit, so opening an old listing can never quietly
  unassign its agent.
* **Societies & Projects and Team could not be paged.** They listed a flat 50
  and 100 with no search, no filter and no page links, so an office with more
  than that could not reach the rest at all. Both now have search, page links,
  25 rows a page, and a proper empty state that distinguishes "nothing here
  yet" from "nothing matched your search".
* **Site Visits had page links that were never drawn.** The query already
  paged; page two existed and could not be reached.
* **Nonsense numbers were accepted.** A listing could be saved with 999
  bedrooms, 500 bathrooms or a price of 9.9e18. A mistyped price sorts above
  every genuine listing and breaks the price filter for everybody. Sensible
  ceilings now apply, set far above any real property, and the refusal says
  what the highest allowed number is.
* **The negative-price check had never once run.** `Sanitize::float()` floors
  at zero, so by the time the check saw the value, "-500000" had already become
  "0" — the listing saved with a price of nothing and nobody was told. The
  check now reads what was actually typed.
* **Three N+1 queries.** The Projects screen ran one database query per row to
  count listings, and the Elementor projects widget did the same on a public
  page. Both now use a single `Projects::listing_counts()`.

### Fixed — test harness

* `paginate_links()` in the harness always returned a string, ignoring
  `'type' => 'array'`. Any screen asking for an array silently drew nothing,
  so pagination could not be tested at all.

### Added — tests

* `tests/integration/it-24-scale-and-limits.php` covers all of it: absurd
  values are refused and real ones still save, a capped dropdown reports what
  it left out, the attached record stays selectable, long lists page and
  search, and counting is done in one query. All nine mutations are caught —
  one of which initially survived, revealing that the "attached record" test
  was vacuous because this harness ignores `orderby` and no record could ever
  fall outside the limit. It now drives that branch directly.

## [2.0.1] — 2026-09-10

The studio was still squeezed into the left of the screen. Same symptom as
1.9.2, different cause, and this one was mine to have found sooner.

### Fixed

* **A 780px cap on the wrong element.** `.estat-form-admin` caps admin forms at
  780px, which is right for a column of fields and wrong for the builder — and
  the builder lives inside a `<form>` so that Save posts everything at once. So
  the studio was dividing up 780px, not the window. Its own 22/43/35 split was
  correct the whole time, which is why every measurement I took looked fine.

On a 1490px page the studio goes from 780px to 1450px: controls 163 to 310px,
canvas 318 to 606px, preview 259 to 493px.

### Added — tests

* `it-22` now walks the studio's entire ancestor chain — screen, body, wrap,
  admin, form, builder form, studio — and fails if any of them caps width
  without a builder-specific override lifting it. It also checks each override
  is more specific than the cap it undoes, so it cannot silently lose the
  cascade, and that the form really carries both classes.

  This is the third width bug in a row caused by a layer above the one I was
  looking at. Checking one rule was never going to catch it; checking the chain
  does. A new cap on any ancestor now fails the build. All five mutations are
  caught.

## [2.0.0] — 2026-09-10

The interface felt machine-made. This release is about why, and it turned out
to be countable rather than a matter of taste.

Nothing about how the plugin works has changed. This is appearance and
interaction detail only, hence the major version: the icon field on a
highlight now holds a name from the icon set rather than an emoji character.

### Changed

* **A real type scale.** There were twenty-one different font sizes, including
  12.5px, 13.5px and 14.5px — sizes that exist because a number got nudged
  until it looked right, which is exactly what reads as generated. There are
  now seven tokens and one hundred and thirteen rules use them. The only raw
  size left is 16px on mobile inputs, which is a browser threshold, not a
  design choice.
* **A real icon set.** Sixty emoji across eleven files became forty-seven
  inline SVG icons in `EstatOS\Support\Icon`, all on one 24px grid with one
  1.75 stroke weight, all inheriting `currentColor` so dark mode needs no
  second definition. Emoji were drawn by the operating system, so the same
  screen looked different on Windows, macOS, Android and Linux.
* **No pure black or pure white anywhere.** Pure values flatten a surface
  against an off-white page. Text sitting on the accent colour now has its own
  token so dark mode can flip it.

### Added

* `touch-action: manipulation` on tappable controls, removing the 300ms delay a
  phone otherwise waits after every tap.
* A tap highlight in the product colour instead of the browser default grey.
* `font-variant-numeric: tabular-nums` on every number a person compares.
* `scroll-margin-top` so a linked heading does not hide under the sticky bar.
* Text selection is suppressed while a card is dragged.
* One wholesale `prefers-reduced-motion` rule rather than rule-by-rule opt-ins.

### Fixed

* Six buttons and links showed a focus ring on mouse click as well as keyboard
  focus. They now use `:focus-visible`. Form fields keep `:focus`, where a ring
  on click is wanted.
* `assets/css/public.css` still carried hardcoded colours from before the
  design tokens existed.

### Added — tests

* `tests/integration/it-23-design-discipline.php` enforces all of it: the scale
  exists and is short, no rule sets a size outside it, no whole-pixel rule is
  broken by a half pixel, no emoji appears in any PHP file, every icon name
  referenced actually exists, the icon set shares one grid and one stroke
  weight, no pure black or white survives, and the interaction details above
  stay in place. Adding a thirteenth font size or a single emoji now fails the
  build. All seven mutations are caught.

## [1.9.2] — 2026-09-10

The studio was squeezed into a corner while half the screen sat empty. Three
separate causes, all fixed.

### Fixed

* **The studio was capped at the normal reading width (1240px).** That width is
  right for a page of text, wrong for three working columns. The form builder
  now uses the whole window; every other screen keeps its reading width.
* **Three columns gave up at 1400px.** A 1408px screen leaves 1248px of page
  once the WordPress menu is out — easily enough for three columns — but the
  breakpoint fired anyway and dropped it to two, wasting half the page. The
  reflow now happens at 1100px, which is the point where the canvas genuinely
  becomes too narrow to work in.
* **The column widths overflowed their own grid.** They were percentages
  totalling 100%, and the two 20px gaps were then added on top, so the browser
  squeezed all three columns to compensate. They are now `fr` units, which
  subtract the gaps properly.
* On screens wider than 1600px the row controls fold back onto one line and the
  dropdowns relax to their full width, since the room is genuinely there.

On a 1568px screen the canvas column goes from roughly 250px to 532px, and the
whole studio from about 655px to 1408px.

### Added — tests

* `it-22` now also pins that the studio gets the full window, that only the
  builder does, that the three column breakpoint stays low enough for a normal
  laptop, and that the canvas is still usable at whatever breakpoint is chosen.
  All five mutations of this work are caught.

## [1.9.1] — 2026-09-10

The studio did not fit on screen. This release is that, and nothing else.

### Fixed

* **The row controls were clipped.** They were laid out on a single line that
  needs about 930px, inside a canvas column that is only ever about 425-560px
  wide. "SPACE BETWEEN BOXES" broke across three lines, controls were cut off,
  and the canvas grew a sideways scrollbar. The controls are now on two lines:
  the column presets on one, spacing and alignment on the other.
* **"Remove" was being clipped to "Re"** inside a narrow column. Both the
  column and field remove controls are now a small × button, each carrying an
  aria-label so screen readers still announce them properly.
* **Captions shortened** where they sit beside a control: "Space between boxes"
  became "Spacing", "Width of this column" became "Width", "Line up" became
  "Align", and the device hints are shorter.
* **The canvas got more room** — the split moved from 25/40/35 to 22/43/35.
* The device hint now drops to its own line on a narrow screen instead of
  squeezing the Computer / Tablet / Phone buttons.
* On a row split three or more ways, the "Width" caption is hidden; the
  dropdown already shows the width.
* Neither the canvas nor the preview can scroll sideways any more, long words
  inside a column wrap, and a long field name is trimmed with an ellipsis.

### Added — tests

* `tests/integration/it-22-fits-on-screen.php` does arithmetic rather than
  string matching: it reads the column percentages and control widths out of
  the stylesheet, works out the real width of each column on a 1280px laptop,
  and fails if any strip of controls cannot fit. Every other test checked that
  a class existed, never that it could be seen — which is exactly how this
  shipped. It found four further overflows nobody had reported, all fixed here.
  All seven mutations are caught.

## [1.9.0] — 2026-09-10

The form builder becomes a studio: seven clear steps, three columns, and full
control over how the form looks.

### Added

* **Seven steps across the top**, in the order an office actually works:
  Container, Layout, Content, Styling, Advanced, Where it goes, Finish. Each is
  a tab you can jump to at any time. Only one panel shows at a time, so the
  controls never become an endless scroll. Arrow keys move between steps.
* **A three column studio.** Controls on the left (25%), the rows in the middle
  (40%), and what visitors will see on the right (35%). The preview is sticky,
  so it stays in front of you while you scroll the controls.
* **Full styling**, in a new `EstatOS\Forms\Style` class — twenty-five settings
  covering label and text colour, box background, borders, the colour when a
  box is clicked into, help and warning colours, label and text size, label
  thickness, corner roundness, border thickness, padding, spacing, the writing
  style, and nine separate button controls including its width and position.
* Every colour has a "Set a colour" tick box. Leave it off and the setting is
  not written at all, so your theme keeps control.
* Styling repaints the preview live as you drag a slider or pick a colour.

### Security

* Styling can never be used to inject CSS. Colours must be plain hex — even a
  valid `rgb()` is refused — fonts are named stacks chosen from a list, and
  every number is clamped to a sane range. A value like
  `red; background:url(...)` is discarded outright rather than escaped.
* Styling lives in its own group and is validated by its own class, so it has
  no route to a field, a label, a required flag, or where a submission goes.

### Fixed

* A column printed its width twice — once as a tag and once in the dropdown
  beside it — which overlapped and read "100% 100%" on narrow columns.
* The new stage and styling code called an `all()` helper that only existed in
  a different script. Caught before release by the JS/PHP crawl; the helper is
  now defined in the builder itself.

### Added — tests

* `tests/integration/it-21-form-studio.php` pins the seven stages, the exact
  25/40/35 split, the sticky preview, that the preview sits to the right of the
  canvas, that every styling control is both posted and validated with no dead
  settings on either side, that styling reaches the visitor as real CSS, that
  hostile styling is refused, and — most importantly — that heavy styling
  leaves every business setting and every field byte-identical. All twelve
  mutations are caught.

## [1.8.0] — 2026-09-10

The form builder, rebuilt around how Elementor lets you shape a layout.

### Added

* **One-click column layouts.** Six presets — 100, 50/50, 33/33/33, 66/33,
  33/66 and four quarters — drawn as little proportion diagrams. Switching to a
  layout with fewer columns never destroys fields; they move into the last
  column.
* **A computer / tablet / phone switch.** The canvas narrows to match, and the
  width controls then edit that device only — the same idea as Elementor's
  device switch.
* **Box height.** Every field can be Small, Medium or Large. Message boxes also
  get a slider for how many lines tall they are.
* **Space between boxes**, as a slider, separately for computers and phones.
* **Line up** — columns can sit at the top, middle, bottom, or stretch to the
  same height.
* Each column now shows its own width, has its own width picker, and can be
  removed on its own. Removing a column moves its fields left rather than
  deleting them.

### Fixed

* **The canvas lied about your layout.** Every column was drawn the same size
  regardless of its real width, so a 25/75 split looked like 50/50. Columns now
  render at their true proportion.
* **Two 50% columns did not fit on one line.** Columns carried both a flex gap
  and a padding gutter, so the gutter was counted twice and columns wrapped.
  A column now subtracts its own share of the gap.
* **A field fought its own column.** The field wrapper hard-coded a percentage
  width on top of the column's width, compounding the two. The field now states
  its width as a custom property the column can honour.
* **New rows saved a gap of zero.** The builder created rows with the gap set to
  the word "normal"; the server reads a number, so it became 0. New rows now
  store a number, and older forms are repaired when opened.
* **Twelve builder labels could never be translated** because they were never
  passed to the browser — including every "only show this when" string and
  every live-preview string. All thirty-two are now localised.
* **The live preview ignored spacing, alignment and box height**, and multiplied
  the column width by the field width, so narrow columns collapsed. It now
  mirrors the real layout, including the device being previewed.
* A confirmation handler listened for `data-confirm` while the screens render
  `data-estat-confirm`, so it could never fire. Both are now honoured.

### Changed

* The front-end stylesheet was rewritten around design tokens, with proper
  focus rings, hover states, 44px touch targets and 16px inputs on phones.
* The half-width checkbox became a one-tap width strip (100 / 50 / 33 / 25).
  Every exact percentage is still available, folded away.

### Added — tests

* `tests/integration/it-20-form-layout.php` follows every layout choice from
  the builder through the sanitiser to the rendered HTML, pins that hostile
  values are clamped, and asserts that changing a form's layout never changes
  what the form means. All twelve mutations of this work are caught.

## [1.7.1] — 2026-09-10

Choosing a photo did nothing at all. This release is that one bug and nothing
else.

### Fixed

* **Photo controls looked dead.** Three separate wiring faults each broke photo
  picking on their own:
  * `data-target` was written on the picker's wrapper, but the JavaScript reads
    it from the button that was clicked. It is now on both.
  * `data-target` held a bare element id, yet it is passed straight to
    `querySelector()`. It now carries the `#` prefix it always needed.
  * The "Add photos" button reads `data-list`, which was never rendered at all.
    The gallery list now has an id and the button points at it.
* **Reordering photos did not save.** The gallery list now carries its own
  `data-target`, so dragging photos into a new order writes that order back.
* **Tapping the photo itself now opens the picker.** The preview area is a real
  button instead of a decorative box, and an empty one reads "Tap to choose a
  photo".
* **On the Gallery screen, tapping a photo tile now opens the file.** Previously
  only the small "Open" button did anything.

### Added

* `tests/integration/it-19-image-picking.php` reads the rendered HTML *and*
  `assets/js/admin.js`, then asserts that every selector the JavaScript looks
  for is one the PHP really writes — the class of mistake no earlier test could
  see. All seven mutations of these fixes are caught.

## [1.7.0] — 2026-09-09

The second and third parts of the "make it easier" work: a shorter property
screen, and real help on every screen.

### Changed

* **The property editor no longer shows 45 fields at once.** The ones an office
  fills in for nearly every property — what it is, the price, the size,
  bedrooms, bathrooms, the locality, photos, the description, who is handling
  it — now come first. The rest sit in five fold-away groups with plain
  headings: rent and deposit, more details about the building, floor plans and
  video, exact address and map, and builder and reference details. **Not one
  field was removed**; a test pins all 45 by name and fails if any goes missing.
* **A group that already holds information opens by itself**, so reopening a
  listing never makes it look as though details were lost.
* **Locality moved up** to sit with the basics. It is a required field and asking
  where a property is belongs with asking what it is.

### Added

* **Real help on all fourteen screens.** "Need help?" previously gave five
  screens a couple of lines and the other nine a single generic sentence. Every
  screen now explains what it is for, what to do on it, and the one or two
  things that catch people out — in plain words, with no technical jargon
  (a test fails if jargon creeps back in).
* Help now warns before anything irreversible: that Remove uses a bin you can
  recover from, that deleting a file is permanent, that imported properties
  arrive as drafts, and that deactivating or uninstalling keeps your data unless
  you explicitly ask otherwise.

## [1.6.0] — 2026-09-09

Making the plugin easier to start with. Until now an office could switch the
plugin on, add ten properties, and still see nothing on their website — because
nothing had told WordPress where those properties should appear. That is fixed.

### Added

* **A welcome screen that sets the office up.** A fresh install now opens on a
  short form: your name and number, the areas you cover and how you measure
  size, which pages you want, and whether those pages should arrive ready to use
  or bare for you to design yourself. Four questions, then a working website.
  It can be skipped, and everything it sets can be changed later in Office
  Settings.
* **The plugin builds your website pages for you.** Properties, Contact us, Our
  team and Saved properties. Choosing "ready to use" gives each page a heading,
  a short introduction written around your own office name, and your properties
  already laid out. Choosing "bare" drops in only the property list so you can
  build the rest in Elementor. Existing offices are untouched.
* **A "what to do next" list.** Shown on the welcome screen and at the top of
  Today. It suggests adding a first property, publishing a draft, adding your
  team, or giving an email address for enquiries — and each item disappears by
  itself once it is done, so the list empties as the office gets going.
* **A one-time reminder** on the office screens while setup is still outstanding.
  It goes away for good once setup is finished or skipped.

### Notes

* Building the pages is safe to repeat. A page that already exists is reused,
  never duplicated, and a page you have written yourself is adopted and left
  exactly as you wrote it.
* Upgrading an office that is already running never triggers the welcome screen.

## [1.5.1] — 2026-09-09

A full line-by-line audit of the plugin was carried out before first use. It
found five genuine faults. All five are fixed here, each with a test that fails
if the fix is ever removed.

### Security

* **An agent could award their own listing the office "verified" badge.** The
  guard that was supposed to prevent this checked a value the plugin never
  actually stored, so it never did anything. Setting the verification state now
  requires the "Mark listings as office verified" permission, and anyone without
  it leaves the existing badge exactly as the office set it — they can neither
  add one nor take one away, while their ordinary edits keep working.
* **A spreadsheet export could run commands on the office computer.** A website
  visitor could type a formula such as `=cmd|' /C calc'!A0` into a public
  enquiry form; when the office later exported their enquiries and opened the
  file, Excel or LibreOffice would treat it as a formula rather than as text.
  Exported cells that begin with `=`, `+`, `-`, `@`, a tab or a carriage return
  are now written as plain text. Stored data is untouched and genuine numbers
  are unaffected.
* **Unpublished projects were confirmed to anonymous visitors.** The public
  project statistics endpoint checked the record type but not whether it was
  published, so a draft or binned project answered differently from one that
  never existed. A draft now looks exactly like a missing record.
* **A draft property could be shown on the website.** The property card never
  checked whether the property was published, so choosing a draft in the
  Elementor widget put it in front of visitors. Visitors now see nothing for an
  unpublished property, while office staff can still preview their own drafts.

### Added

* **Records can finally be removed.** Nothing in the plugin could be deleted at
  all. Properties, societies and projects, team members, agencies, articles and
  documents now each have a **Remove** button that moves the record to a bin.
  Binned records can be **put back** at any time, and only something already in
  the bin can be deleted for good — which additionally requires typing DELETE to
  confirm. A restored record always comes back as a draft, so putting something
  back can never republish it to the website by accident. The listings screen
  has a new "In the bin" filter, and every removal is written to the activity
  history.

### Fixed

* The test harness now models WordPress's bin (`wp_trash_post` and
  `wp_untrash_post`), which it previously did not.

## [1.5.0] — 2026-09-09

### Added

* **Five ready-made forms.** Property enquiry, Book a site visit, Contact us,
  Sell or rent out a property, and Free property valuation. Each one arrives
  complete and working — fields, labels, wording and what happens with the
  information — so nobody has to build a form from an empty screen.
* **Standard field names across every template.** A phone box is always called
  `phone`, a locality always `locality`. Reports, exports and integrations can
  rely on the name whichever form the office started from. A test pins the exact
  field list of all five templates so a rename cannot slip through.
* **A palette people can read.** The eight boxes an estate office actually uses
  — Name, Phone, Email, Message, Property type, Locality, Budget, Consent — come
  first. The other twenty are folded into four groups (Choices, Date and time,
  Property, Advanced) behind "More field types", with a search box.
* **Three smart property fields**: `property_type` and `locality` fill their own
  choices from the office's own vocabulary and localities, and `budget` ships
  with sensible ranges.
* **A live preview** beside the designer showing roughly what a visitor will
  see, updating as the form changes. Its boxes are disabled so nobody can type
  into the preview by mistake.
* **Simpler field settings.** One plain "Half width" tick replaces three
  always-open width dropdowns (the exact per-device widths are still there,
  folded away), plus one simple "only show this box when …" rule.
* **Enquiries can be posted in from another form tool.** New public endpoint
  `POST estat/v1/enquiries` for Elementor Pro's Webhook action or any similar
  plugin. It is protected by a secret key set in Office Settings — with no key
  configured the endpoint refuses everything, so it is never open by default.
  Submissions go through the same cleaning, validation and duplicate checks as
  our own forms.

### Fixed

* The test harness was missing a `wp_list_pluck` stub, which made form
  validation fatal under test rather than failing honestly.

### Developer notes

* New `EstatOS\Forms\Templates`: `all()`, `get( $key )`, `STANDARD_IDS`.
  Filter `estat_form_templates`.
* New `FieldTypes::common()` and `FieldTypes::groups()`, filter
  `estat_form_field_groups`. Every registered type stays reachable in the
  palette — enforced by a test.
* New setting `inbound_secret`, compared with `hash_equals()`. Audit event
  `lead.inbound`.

## [1.4.0] — 2026-09-09

### Fixed

* **Dark mode was genuinely broken in places.** 40 style rules still used a
  hardcoded colour left over from before the design system existed, so parts of
  the office screens stayed white — table rows, panels, the form builder — when
  dark mode was on. Every one of them now uses a design token. A test walks the
  whole stylesheet and fails the build if a hardcoded colour is ever added back.
* **One style rule referred to a colour name that was never defined**
  (`--estat-soft`), so that text fell back to the browser default. Corrected to
  `--estat-ink-soft`, and a test now checks every colour name used actually
  exists.
* **Table rows were unreadable on a phone in several screens.** On a narrow
  screen each row becomes a small card and the column headings are hidden, so
  every cell must carry its own label. Insights, Gallery, Forms, Projects and
  Team had cells without one, showing values with nothing to say what they were.

### Added

* **A proper phone layout.** Buttons and boxes are now big enough to tap,
  typing no longer makes the phone zoom in, the search bar stacks instead of
  squashing, stat tiles go two-per-row, and wide tables can be swiped sideways
  instead of stretching the page off screen.
* **Empty screens now explain themselves** with a heading, a plain description,
  and where it helps a short numbered path out — and they distinguish "you have
  nothing here yet" (calm, with a first step) from "your search found nothing"
  (with a button to clear the filters).
* **Small conveniences.** A pressed Save button shows a spinner so a slow save
  does not look like a dead button; pressing Save twice can no longer create the
  same record twice; leaving a half-filled form warns first; `/` jumps to the
  search box, Ctrl/Cmd+S saves, and Escape closes an open confirmation drawer.
* Messages after saving now say what actually happened — publishing says the
  property is live, a draft says only the office can see it, an import says the
  new properties are drafts, and erasing says plainly that it cannot be undone.

### Developer notes

* `Partials::empty_state()` accepts either the original sentence or an array
  with `title`, `message`, `icon`, `steps`, `action`, `label`, `hint` and
  `reassuring`. Every existing call still works unchanged.
* The busy state deliberately does not disable the submit button: disabling one
  drops its `name`/`value` and would silently change what the form does.

## [1.3.0] — 2026-09-09

### Added

* **The dashboard now says what to do, not just how many.** "Today" opens with
  a short list of real jobs — overdue follow-ups first, then today's site
  visits, unanswered enquiries, enquiries nobody owns, and half-finished
  listings — each naming the person or property and linking straight to the
  screen where the work gets done. When there is genuinely nothing waiting, it
  says so and offers something useful instead of showing an empty box.
* **Adding a home is now four short steps** — the basics, price and size, a
  photo, then the highlights — instead of one long form. It is still a single
  form that posts in one go, so nothing about saving changed. If a required box
  is empty, the form points at it on that step rather than failing at the end.
  With scripting unavailable every step is simply visible, so the form still
  works.

### Changed

* **Listings, Enquiries, Site Visits, Gallery and Insights now share one search
  and filter bar**, so they look and behave the same instead of each being
  slightly different. Every list also says how many results it found.

### Developer notes

* New `EstatOS\Data\Worklist` class: `tasks( int $limit )` returns jobs shaped
  `{urgency, tone, icon, title, detail, action, url}`, lowest urgency first,
  capped at `Worklist::LIMIT`. Each source is bounded and permission-checked, so
  a user is never shown a job they cannot act on. Filter: `estat_worklist`.
* New `Partials::toolbar()` renders the shared search/filter bar.
* New `Support\Format::time()` for times of day.

## [1.2.0] — 2026-09-09

### Added

* **A real Gallery screen.** The Gallery menu used to be a link to the raw
  WordPress media library — it showed every file on the site, used language an
  estate office does not need, and loaded a heavy uploader that some browsers
  flag as suspicious. It is now the plugin's own screen: tabs for Photos, Floor
  plans, Brochures and Documents, a picture grid for images and a plain list for
  paperwork, search, a filter for files not yet attached to anything, and a
  drag-and-drop area that uploads through the plugin's own endpoint with no
  third-party uploader involved.
* Files can be filed by what they are, and attached to a property or project, so
  the gallery shows which listing a photo belongs to.
* **Light and dark screens.** Office Settings now offers Light, Dark, or
  Automatic (follow the computer). It affects only the office screens; the
  public website is untouched.

### Changed

* **The admin screens were redesigned.** Softer cards with gentle shadows,
  calmer tables, clearer buttons and inputs, better spacing and typography.
  Every colour, radius and spacing step is now a single design token, which is
  what makes the dark theme possible without touching each screen.
* Movement is switched off automatically for anyone whose computer asks for
  reduced motion.

### Developer notes

* New `EstatOS\Data\Media` class: `kinds()`, `query()`, `to_array()`,
  `kind_of()`, `set_kind()`, `link()`, `counts()`. It reads; all writes go
  through capability-checked admin actions.
* New setting `admin_theme` (`light` | `dark` | `auto`), sanitised to a fixed
  list. It is reflected as an `estat-theme-*` body class.
* The removal action verifies the target really is a file before deleting, on
  its own account rather than relying on WordPress to refuse.
* The test harness now honours `post_mime_type` and `meta_query`, which it
  previously ignored.

## [1.1.1] — 2026-09-09

### Fixed

* **Most of the Office menu was missing, and pages refused to open.** On a live
  site only Site Visits, Forms, Spreadsheet, Reports and Office Settings
  appeared; Today, Add a Home, Listings, Enquiries, Societies & Projects, Team,
  Gallery and Insights were gone, and opening Enquiries showed "Sorry, you are
  not allowed to access this page" even for the site administrator.

  The cause: several content types mapped WordPress's three per-record
  permissions (`edit_post`, `read_post`, `delete_post`) onto the same office
  permission. WordPress remembers those names in a global list and from then on
  treats them as per-record permissions that require a specific record ID. Every
  ordinary permission check made without a record ID — which is what a menu
  entry does — then returned "no" for everybody, administrators included.

  Each content type now uses its own private per-record permission names, and
  the office permissions are left alone. All roles, including administrator,
  are granted the new names on upgrade.

* The test suite never had an `administrator` role, so the part of the installer
  that grants permissions to administrators was never exercised and the fault
  above could not be caught. The harness now starts with the built-in WordPress
  roles present.

### Changed

* Database version raised to 3. The upgrade only repairs permissions; no
  business data is read, changed or removed.

## [1.1.0] — 2026-09-08

### Added

* **Highlight tags on property cards.** When someone adds a property they can
  tick the things that actually sell it — corner plot, park facing, near metro,
  clear title, lift, power backup and thirty more, grouped into Position,
  Condition, Paperwork, Comfort, Nearby and Money. The best three appear as
  small tags on the property card, and the add-a-property screen shows a live
  preview of exactly which ones will make it onto the card. A property with
  nothing ticked still shows bedrooms, bathrooms and size, so no card is ever
  blank.
* **Insights is now a real screen.** Blogs, Articles and Insights live together
  on one page with a tab for each, their own topics, a search box, drafts, cover
  pictures and a proper editor. It no longer sends the office to the WordPress
  post editor. A piece can be re-filed from one tab to another without changing
  its web address.
* A new `estat_manage_insights` permission, granted to owners and agents.
  Existing sites pick it up automatically on upgrade — nothing to reinstall.

### Changed

* Property cards show the highlight tags in place of the old repeated
  bedroom/size line, so the card reads like an advert rather than a form.
* Database version raised to 2. The upgrade only adds a permission and the three
  insight categories; no existing data is touched, rewritten or removed.

### Developer notes

* New filters `estat_highlights` (change the catalogue) and
  `estat_listing_highlights` (change what a single property shows).
* `Listings::save()` accepts a `highlights` array of keys; unknown keys are
  discarded. `Listings::to_array()` now returns `highlights` and `chips`.
* The integration harness reads `ESTAT_VERSION` and `ESTAT_DB_VERSION` from the
  plugin file instead of hardcoding them, so upgrade paths are genuinely tested.

## [1.0.0] — 2026-09-08

The first stable release, following a dedicated production validation and
release hardening phase. See `docs/release-readiness.md` for the full report and
`docs/implementation-matrix.md` for requirement-by-requirement status.

### Naming

* The product is **Estat.OS**, authored by DEVil
  (https://github.com/devkaushall). The PHP namespace is `EstatOS`, the text
  domain is `estat-os`, constants use the `ESTAT_` prefix, hooks, options,
  capabilities and tables use `estat_`, CSS classes and admin slugs use
  `estat-`, the REST namespace is `estat/v1`, and webhook headers are
  `X-Estat-Event`, `X-Estat-Timestamp` and `X-Estat-Signature`.

### Fixed during release hardening

* New-enquiry notification emails were only sent for enquiries that arrived
  through a form. Enquiries created via the REST API, a CSV import, the admin
  screens or another plugin notified nobody, even though the setting said they
  would. The `estat_lead_created` hook is now wired up, with a guard so a form
  enquiry is never emailed twice.
* The table health check ran eight uncached `SHOW TABLES` queries every time it
  was called, from thirty-four call sites. It is now cached per request and in
  the object cache, which takes a paged property search from ten queries down to
  two.
* All six `fgetcsv()`/`fputcsv()` calls now pass explicit RFC 4180 arguments,
  removing a PHP 8.4 deprecation and a behaviour change due in PHP 9.
* The plugin now shows a clear admin notice on an unsupported PHP or WordPress
  version instead of causing a fatal error.
* Fixed an undefined-property warning on the Today screen for users with no
  first name set.

### Added

* **Inventory** — properties, societies and projects, team members, agencies and
  documents, with a beginner-first editor split into three short chapters and a
  readiness score out of 100.
* **Add a Home** — a nine-question form that never sends you to the WordPress
  editor.
* **Discovery** — a dedicated flat search index table, filtered search,
  favourites and side-by-side compare of up to four properties.
* **Pipeline** — enquiries with de-duplication and idempotency, notes, follow-up
  dates, agent assignment, and a site-visit diary with outcomes.
* **Forms** — a visual drag-and-drop builder with 25 field types, per-device
  width controls, conditional logic, consent handling, spam rate limiting, and a
  form-to-enquiry pipeline.
* **Reporting** — office health, pipeline shape, per-person performance, busiest
  localities and enquiry sources.
* **Spreadsheets** — batched CSV import with de-duplication on your own
  reference numbers, plus one-click export of everything.
* **REST API** — the `estat/v1` namespace, with rate limiting on anonymous
  routes.
* **Webhooks** — five events, HMAC-SHA256 signed, with retries and a delivery
  log.
* **Elementor** — seventeen presentation-only widgets, including a *Estat Form*
  widget. Elementor is never required.
* **Languages** — English and natural Hinglish written in Latin script, plus a
  `.pot` file and documentation for adding more.
* **Care and safety** — audit log, daily and hourly housekeeping, privacy export
  and erasure, practice mode with one-click sample data, and maintenance tools
  for rebuilding the search index and repairing tables.
* **Roles** — Office Owner and Office Agent, built from nineteen granular
  capabilities.

### Security

* Capability and nonce checks on every write, all output escaped, all SQL
  prepared.
* Uploaded spreadsheets stored in a protected folder.
* Prices marked *on request* are never exposed publicly, including through the
  API and structured data.

### Notes

* Deactivating or deleting the plugin keeps all of your data. Erasure only
  happens if you explicitly opt in beforehand.
* Content-copy protection is deliberately out of scope.

[1.0.0]: https://github.com/devkaushall/estat-os/releases/tag/v1.0.0
