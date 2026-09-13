# Estat.OS — The Complete Manual

**The operating system for a real-estate office.**

Version 2.6.0 · Built by DEVil · https://github.com/devkaushall
Licence GPL-2.0-or-later · For WordPress 6.0+ and PHP 7.4+

*Every version ever released is listed in Part 7, with what changed and why.*

---

## How to read this manual

This manual is written for the person who runs the office, not for a
programmer. If you can use WhatsApp and fill in a form, you can use this
plugin and you can read this manual.

- **Part 1** tells you what Estat.OS is and why it exists.
- **Part 2** gets you from "just installed" to "working website" in about
  fifteen minutes. **If you read nothing else, read this.**
- **Part 3** goes screen by screen through everything in the office.
- **Part 4** covers your website — the visitor's side.
- **Part 5** is for when something goes wrong.
- **Part 6** is the technical appendix. Skip it unless you are a developer.

Anything marked **⚠️** is worth reading twice. It usually means "this cannot
be undone" or "this catches people out".

---

# PART 1 — ABOUT ESTAT.OS

## 1.1 What it is

Estat.OS is a WordPress plugin that runs a real-estate office.

Most property plugins are a *listings display*: a pretty way to show houses on
a website. Estat.OS is not that. It is the **business system** for the office.
Showing houses on a website is one of the things it does, not the whole point
of it.

The difference matters in practice. A listings plugin can tell you what is on
your website. Estat.OS can tell you who enquired yesterday, who has not been
called back, which viewing is at four o'clock, which agent is handling it,
which listing is missing photographs, and how many enquiries turned into
viewings last month.

## 1.2 The idea behind it

The plugin was built on a single principle, and everything in it follows from
that principle:

> **The plugin is the business system. WordPress is only the platform.**

From which:

| | |
|---|---|
| **WordPress** | is only the platform it runs on |
| **Elementor** | is only the presentation layer, and is optional |
| **The website** | is the customer's interface to your business |
| **The admin screens** | are the office's interface to your business |
| **The database** | is the single source of truth |
| **The API** | is how other systems connect to you |
| **Security** | is a protection layer, not an add-on |
| **Forms** | are the bridge from a website visitor to your business |

Two consequences you should know about:

- **If you switch Elementor off, your office keeps working.** Your enquiries,
  visits, follow-ups and reports are untouched. Only the fancy page design
  goes away, and the plugin falls back to its own plain rendering.
- **You never need ACF or any other custom-fields plugin.** Estat.OS owns its
  own data model. There is nothing to configure before you start.

## 1.3 Who it is for

A small or medium real-estate office on ordinary shared hosting. It is
designed and tested to stay comfortable up to about **10,000 properties**,
which is far more than most offices will ever have.

It is deliberately built for someone who is **not technical**. You will not
find the words "custom post type", "taxonomy" or "REST endpoint" anywhere in
the interface. You will find "Listings", "Property Type" and "API Connection".

## 1.4 What it is not

Being honest about this saves disappointment:

- It is **not** a portal like MagicBricks. It runs *your* office, not a
  marketplace of many offices.
- It is **not** a website theme. It supplies the business system and the
  content; how your site looks is your theme's job (and Elementor's, if you
  use it).
- It does **not** sign contracts, handle payments, or do your accounts.
- It does **not** copy listings from other websites.

## 1.5 Who built it, and when

Estat.OS was designed and built by **DEVil** (https://github.com/devkaushall)
and released as free, open-source software under the **GPL-2.0-or-later**
licence. That means you may use it, modify it and share it, for free, for
commercial work, forever.

Development history:

| Version | What it brought |
|---|---|
| 1.0.0 | First working release: listings, enquiries, visits, forms, reports, API |
| 1.1.x | Fixes and refinements |
| 1.2.0 | Gallery screen, design tokens, dark mode |
| 1.3.0 | Today dashboard with a worklist, four-step Add a Home |
| 1.4.0 | Mobile layout, better empty screens, conveniences |
| 1.5.0 | Form builder redesign: ready-made templates, live preview |
| 1.5.1 | A full security audit, and the five faults it found, all fixed |
| 1.6.0 | Setup wizard; the plugin now builds your website pages for you |
| 1.7.0 | A shorter property screen, and real help on every screen |

The full detail of every change is in `CHANGELOG.md`.

## 1.6 A note on your data

This is a promise the plugin keeps, and it is worth stating plainly:

- **Deactivating the plugin deletes nothing.**
- **Uninstalling the plugin deletes nothing**, unless you have gone to
  Office Settings → Tools and explicitly ticked the box asking for deletion.
- Nothing in the office can be destroyed in a single click. Removing a record
  puts it in a bin you can recover from.
- Your office's name, phone number, city and licence details are **never**
  written into the code. Everything is configured through the settings
  screens, which is why any other agency can use this plugin without editing
  a single file.

---

# PART 2 — GETTING STARTED

## 2.1 Before you begin

You need:

- A WordPress website, version **6.0 or newer**
- PHP version **7.4 or newer** (almost all hosting has this)
- An administrator login to that website

You do **not** need: Elementor, ACF, a page builder, a paid theme, or any
other plugin.

## 2.2 Installing

1. Log in to your WordPress site as an administrator.
2. In the left-hand menu go to **Plugins → Add New**.
3. Click the **Upload Plugin** button at the top of the screen.
4. Click **Choose File** and pick the `estat-os-1.7.0.zip` file.
5. Click **Install Now**, wait, then click **Activate Plugin**.

**⚠️ If you see "Please select a file":** you have landed on the upload page
without a file attached — usually because the page was refreshed, or the
browser Back button was used. Nothing is broken. Go to **Plugins → Add New →
Upload Plugin** afresh, choose the file, and click Install without refreshing.

## 2.3 The welcome screen

The moment the plugin activates, it opens a **welcome screen**. This is the
fastest path to a working website, and it asks only four short questions.

**Question 1 — Who are you?**
Your office name, your phone number, and an email address. The name and number
appear on your website. The email is where the plugin sends you a note when a
new enquiry comes in.

**Question 2 — Where do you work?**
The areas you cover (separate them with commas, like `Delhi, Gurgaon, Noida`),
your currency sign, and whether you normally measure in square feet, square
yards or square metres.

**Question 3 — Which pages should we build for you?**
This is the important one. When you install the plugin your website has
nowhere to *show* properties. You could add fifty homes and your site would
still look empty. This step fixes that. Tick the pages you want:

| Page | What it is |
|---|---|
| **Properties** | Where visitors browse everything you have. Comes with a search box. |
| **Contact us** | Your enquiry form, so visitors can reach you. |
| **Our team** | The people in your office, with their phone numbers. |
| **Saved properties** | Where a visitor finds the homes they marked with a heart. |

**Question 4 — How should those pages look?**

- **Ready to use** — each page arrives with a heading, a short introduction
  written around your own office name, and your properties already laid out.
  Choose this if you want something working today.
- **Just the bare page** — an empty page with only the property list dropped
  in, for you to design yourself in Elementor.

Either way you can redesign them later. They are ordinary WordPress pages.

Click **Set up my office**. Your pages are created and are live immediately.
The screen then lists them with links, so you can click through and see
exactly what a visitor sees.

**If you would rather do everything yourself**, click *"Skip this, I will do
it myself"*. The plugin works identically; you will just need to create your
own pages and place the shortcodes from Part 4.2.

**Running setup is safe to repeat.** It never creates a second copy of a page,
and if you have already made a page yourself with a matching name, the plugin
quietly adopts yours and leaves every word of it alone.

## 2.4 Your first property

From the office menu, click **Add a Home**. This screen is deliberately short
— four small steps, nine fields in total, and only three of them required.

**Step 1 — The basics.** What kind of property, is it for sale or rent, a
headline, and the locality.
*Headline tip:* say what it is and where, in a few words —
`3 BHK apartment with a park view` works better than `Property No. 47`.

**Step 2 — The price.** Type **numbers only**: `8500000`, not `85 lakh` and
not `₹85,00,000`. The plugin adds the currency sign and the commas itself.
If you would rather not publish a figure, tick **Price on request** — visitors
then see the words "Price on request" and the real number stays private to
your office.

**Step 3 — A photo.** The main photo. This is what people see first, and a
listing with a photo gets far more enquiries than one without.

**Step 4 — Highlights.** Tick the features that make this one worth looking
at. Two or three of the ticked ones appear as small badges on the property
card, which is what makes a card stand out in a list.

Then choose:

- **Save as draft** — kept in your office, invisible to the public. Nothing
  goes on your website until you say so.
- **Put it on the website** — live for visitors immediately.

**⚠️ Add a Home never sends you to the normal WordPress editor.** You stay in
your office the whole time.

## 2.5 Your first fifteen minutes — a checklist

1. Install and activate the plugin. *(2 min)*
2. Answer the four setup questions and let it build your pages. *(3 min)*
3. Visit your new Properties page and see the empty state. *(1 min)*
4. Add one property with **Add a Home** and publish it. *(3 min)*
5. Refresh the Properties page — your home is there. *(1 min)*
6. Go to **Team** and add yourself. *(2 min)*
7. Open your Contact page and send yourself a test enquiry. *(2 min)*
8. Check **Enquiries** — your test is waiting. *(1 min)*

At that point the whole loop is proven end to end: a home goes on the website,
a visitor enquires, the enquiry lands in your office.

## 2.6 The "What to do next" list

You will see a short numbered list on the welcome screen and at the top of
**Today**. It suggests things like adding your first property, publishing a
draft, adding your team, or giving an email address for enquiries.

**Each item removes itself as soon as it is done.** The list empties on its
own as you get going. It is not a nag, and there is nothing to dismiss.

---

# PART 3 — THE OFFICE, SCREEN BY SCREEN

Your office lives under the **Office** menu in the WordPress sidebar. There
are fourteen screens. Every one of them has a **"Need help?"** button in the
top corner that explains what the screen is for, what to do on it, and the
things that catch people out.

## 3.1 Today

**What it is for:** the screen you open first thing in the morning.

It answers five questions without you having to look for anything:

- What new enquiries came in?
- Which follow-ups are due or overdue?
- Which site visits are happening today?
- Which listings are missing information?
- **What should I do next?**

That last one is the useful part. At the top is a worklist in order of
urgency: overdue follow-ups first, then today's visits, then new enquiries,
then unassigned enquiries, then incomplete listings. Work down the list and
your day is done.

Below that are the numbers — live listings, drafts, new enquiries, follow-ups
due, visits today, upcoming visits, team size, and an overall **listing
readiness** percentage. Every number is a link to the screen behind it.

## 3.2 Add a Home

Covered in Part 2.4. The short four-step form for adding a property quickly.

## 3.3 Listings

**What it is for:** every property your office is handling.

Each row shows the headline, the price, the locality, its state, and how
complete it is. The buttons across the top let you search, filter, and add a
new home.

**The two states that matter:**
- **On the website** — visitors can see it.
- **Draft** — only your office can.

### Doing many at once

Every row has a tick box. Tick a few and a bar appears at the bottom with four
choices:

| Choice | What it does |
|---|---|
| Put on the website | Makes them live |
| Take off the website | Turns them back into drafts |
| Move to the bin | Takes them off the website; you can bring them back |
| Bring back from the bin | Returns them **as drafts**, so you check before publishing |

Two things worth knowing:

- **You cannot delete for good in bulk.** Removing twenty records forever from
  a row of tick boxes is too easy to do by accident, so it is simply not
  offered. Permanent deletion is one record at a time, and only from the bin.
- **Permission is checked on every single record**, not once for the batch. If
  you tick things you are not allowed to change, the ones you *are* allowed to
  change go through and the screen tells you how many were skipped.

There is a limit of 100 at a time, so a huge batch cannot time out halfway and
leave you unsure what happened.

### Undoing a mistake

Next to each record there is **Undo last edit**, but only when there is
genuinely something to undo.

If you change a price by a digit and save, that button puts the details back to
how they were. It keeps the **last five** versions of a record, for **24
hours**. It works on properties, societies and team members.

Two deliberate limits:

- **Undo never puts something back on the website.** If you deliberately took a
  property off the site and then undo an unrelated price change, it stays off.
  Undo restores details, never publication.
- **Undo can itself be undone.** Press it twice and you are back where you
  started.

**Readiness score.** Each listing gets a percentage out of 100, made up of:

| Part of the listing | Worth |
|---|---|
| Cover photo | 15 |
| Price | 15 |
| Locality | 15 |
| Headline | 10 |
| Description | 10 |
| Three or more gallery photos | 10 |
| Size | 10 |
| Bedrooms | 5 |
| Assigned agent | 5 |
| Map position | 5 |

It is a hint, not a rule. You can publish a listing at 40%. But a listing with
photographs, a price and a locality gets meaningfully more enquiries than one
without, and the score is there to tell you which ones are letting you down.

**Removing a property.** Click **Remove**. The property goes to a **bin**: it
comes off your website but nothing is destroyed. Switch the filter to
**In the bin** to see what is in there, and either **Put it back** or
**Delete for good**.

**⚠️ Deleting for good cannot be undone.** The plugin makes you do two things
first: the property must already be in the bin, and you must confirm. A single
stray click can never destroy a live property.

**⚠️ Putting something back always returns it as a draft**, never straight
onto your website. Check it over, then publish it deliberately.

### The property editor

Clicking **Edit** opens the full editor. There are 45 fields available, but
you will never see them all at once. The screen is in three chapters, and inside each
chapter the everyday fields come first, with the rarely-used ones folded away
behind a single line you can click open.

**Chapter 1 — The home.** What it is, for sale or rent, the headline, the
price, the size, bedrooms, bathrooms, the locality.
*Folded away:* rent, deposit and maintenance (only needed for a rental); and
more details about the building — which area measurement, balconies, parking,
which floor, floors in the building, age.

**Chapter 2 — Story and extras.** The description, the main photo, the photo
gallery, amenities and special features.
*Folded away:* floor plans, brochures, video link and 360° tour; and the exact
address, which way it faces, furnishing, and the map position.

**Chapter 3 — Office information.** Whether it is still available, the
construction stage, whether your office has verified it, who is handling it,
which society it belongs to, whether it is featured, and your private notes.
*Folded away:* builder, possession date, investment flag, an automatic
take-down date, and your own reference number.

**A folded group that already has something in it opens by itself**, so
reopening a listing never makes it look as though your details were lost.

### Separate states, deliberately

A property has several independent states, and keeping them separate is
intentional — real transactions are messier than one dropdown allows:

| State | Possible values |
|---|---|
| Offer | For sale, For rent, For lease |
| Availability | Available, Under offer, Sold, Rented |
| Construction | Ready to move, Under construction, New launch |
| Verification | Not checked, Owner confirmed, Office verified |
| Website | On the website, Draft, In the bin |

A property can be "under construction", "available", "office verified" and
"draft" all at once, because that is a real situation.

**⚠️ Only someone with the "Mark listings as office verified" permission can
set the verification state.** An ordinary agent cannot award their own listing
your office's badge of trust, nor remove one you have awarded.

## 3.4 Enquiries

**What it is for:** everyone who has contacted you through your website,
newest first.

Every form submission on your site arrives here. You never have to check a
separate inbox.

For each enquiry you can record who is handling it, what state it is in, a
follow-up date, and notes of your conversations. **Set the follow-up date** —
that is what makes the enquiry appear on your Today screen when it is due.

**Duplicate handling.** If the same person asks about the same property twice
within 24 hours, the plugin keeps it as **one** enquiry and adds their second
message as a note ("Asked again: …"). Nothing is lost, and you do not
accidentally chase the same person twice. Enquiries about *different*
properties are always kept separate.

**⚠️ "Erase personal details"** permanently removes the name, phone number and
email address. Use it when somebody asks you to delete their information. The
record of the enquiry itself is kept, so your reports stay accurate. **This
cannot be undone**, and the plugin makes you confirm.

## 3.5 Site Visits

**What it is for:** viewings your team has arranged, so nobody turns up at the
wrong time or forgets.

Book a visit against an enquiry and a property, with a date and time. A visit
booked for today appears on your Today screen automatically.

**Mark visits as done afterwards.** This is the one habit worth forming: your
reports are only meaningful if completed visits are recorded as completed.

Visits in the past cannot be booked, and nonsense dates are rejected.

## 3.6 Societies & Projects

**What it is for:** apartment complexes, gated societies and builder projects
that several of your listings belong to.

Add the society once — its name, the builder, its amenities, its location —
then simply pick it on each property inside it. The shared details are typed
once instead of thirty times, and a visitor looking at one flat can see how
many others you have in the same society.

Entirely optional. A standalone house does not need to belong to anything.

## 3.7 Team

**What it is for:** the people in your office.

Add each person with their name, photo, phone number and email. Once they are
here you can hand each enquiry to whoever is dealing with it, and your website
can show a proper team page.

**⚠️ Adding somebody here does not give them a login.** This is a record of a
person, not a user account. Logins are created in the normal WordPress
**Users** screen; see Part 3.13 for the two roles the plugin provides.

## 3.8 Forms

**What it is for:** building the forms your website visitors fill in.

Start from one of five ready-made forms, which already ask for the right
things:

| Template | Asks for |
|---|---|
| **Property enquiry** | Name, phone, email, message, consent |
| **Book a site visit** | Name, phone, preferred date, preferred time, message, consent |
| **Contact us** | Name, phone, email, message |
| **Sell or rent out a property** | Name, phone, property type, locality, budget, address, consent |
| **Free property valuation** | Name, phone, locality, property type, size, consent |

### The form studio

The builder is laid out in three columns: the controls on the left, your rows
and boxes in the middle, and **what visitors will see** on the right, updating
as you work.

Across the top are seven steps, in the order most people work through them.
They are tabs, not a wizard — jump to whichever one you need.

| Step | What you do there |
|---|---|
| **1. Container** | Name the form, set the space between boxes, choose the writing style |
| **2. Layout** | How many columns each row has, and how wide |
| **3. Content** | Add the boxes people fill in, and set their labels |
| **4. Styling** | Colours, corners, sizes and the button |
| **5. Advanced** | Rules for one box: exact widths, show-only-when |
| **6. Where it goes** | Whether it makes an enquiry, who gets emailed, spam limits |
| **7. Finish** | Copy the shortcode and put it on a page |

**Columns in one click.** The Layout step has six little diagrams — 100,
50/50, 33/33/33, 66/33, 33/66 and four quarters. Click one and the row changes
shape. If the new shape has fewer columns, your boxes move into the last
column; nothing is thrown away.

**Computer, Tablet, Phone.** Buttons above the rows switch which screen size
you are editing. The canvas narrows to match, and the widths you set then apply
to that size only.

**Styling.** Twenty-five settings: label and text colour, box background,
borders, the colour when a box is clicked into, help and warning colours, sizes,
corner roundness, spacing, the writing style, and nine separate button controls.

Every colour has a **"Set a colour"** tick box. Leave it off and that setting is
not written at all, so your theme keeps control. This matters — an unticked
colour is different from a white one.

Colours must be plain hex codes. That is not fussiness: it is what stops
someone pasting a value that smuggles other instructions into your page.

**The most important promise in the whole plugin:**

> **Changing how a form looks can never change what it does.**

Rearranging fields, changing widths, altering labels — none of it can break
lead creation, notifications, duplicate protection, spam protection,
permissions or your activity history. Design and business logic are separate
by construction.

Every form sends what it collects to your **Enquiries** screen.

**Spam protection** is built in and needs no configuration: a hidden field
that only a robot would fill in, a timing check that catches instant
submissions, and duplicate detection.

## 3.9 Gallery & Media

**What it is for:** every photo and document you have uploaded, in one place,
sorted by what it is.

Four kinds: **Photos**, **Floor plans**, **Brochures**, **Documents**. Use the
tabs to switch. Drag files straight onto the page to upload them.

**⚠️ Deleting a file here removes it for good, and any property using that
photo will lose it.** Check before you delete. This is the one place in the
plugin without a bin, because a file is not a business record.

## 3.10 Insights

**What it is for:** writing. Three kinds, all on this one screen:

- **Blogs** — informal, regular
- **Articles** — longer, more considered
- **Insights** — market analysis, price trends, area reports

Each kind has its own topics, so you can organise them separately. Use the tabs
to switch between them.

Writing about your area is one of the cheapest ways to be found on Google. An
article called *"What a 3 BHK in Saket actually costs in 2026"* will bring you
visitors that no amount of listing pages will.

## 3.11 Spreadsheet

**What it is for:** bringing properties in from a spreadsheet, and taking your
records out to one.

**Importing:**

1. Download the sample file first. It already has the right column names.
2. Fill it in — one property per row.
3. Upload it and **run a test**. The test checks your file and changes
   nothing.
4. Fix anything the test complains about.
5. Run the real import.

**⚠️ Imported properties always arrive as drafts**, never live. You check them
before anything reaches your website.

**Give every row your own reference number.** That is what stops the same file
creating duplicates if you import it twice. It is the single most useful
column in the file.

Large files are processed in batches of 50 rows so they do not time out on
shared hosting. Files up to 20 MB are accepted.

**Exporting:** take your listings, projects, team or enquiries out as a CSV
file that opens in Excel, Google Sheets or LibreOffice.

**A note on exports and safety.** If a website visitor types a spreadsheet
formula into your enquiry form, and you later open your exported enquiries in
Excel, Excel would ordinarily treat that text as a formula and *run* it. The
plugin prevents this: any exported cell that could be read as a formula is
written as plain text instead. Your data is unchanged and genuine numbers are
untouched — the export is simply safe to open.

## 3.12 Reports

**What it is for:** seeing how the office is actually doing.

Enquiries over time, visits arranged and completed, which team member is
handling what, which properties get the most interest, and how complete your
listings are on average.

**⚠️ Reports only reflect what has been recorded.** If your team books visits
by phone and never enters them, the reports cannot know. The numbers are as
good as the habits.

## 3.13 Office Settings

Six tabs.

**Your office** — name, tagline, contact person, phone, WhatsApp, email,
address, office hours, logo, country, currency and currency sign, how prices
are formatted, default area unit, the areas you cover, and how the office
screens look (light, dark, or follow the computer's setting).

**Website** — map provider (OpenStreetMap by default and free; Google Maps if
you supply a key), default map position and zoom, how many properties to a
page, whether visitors can save favourites and compare properties, and whether
to output search-engine data.

**Language** — two separate settings: the language of the **office screens**
you work in, and the language of the **public website** visitors see. They do
not have to match.

Two languages ship: **English**, and **Hinglish written in ordinary English
letters**, the way most Indian offices actually speak. All 1,504 pieces of text
are translated, not just the common ones.

You can add a third. Copy the translation file from the plugin's `languages`
folder, translate it with a free tool such as Poedit, and save it into
`wp-content/languages/plugins`. It then appears in this list on its own.

**Your business data is never translated.** A property headline, a customer's
message, a note you typed — those are yours, and they are shown exactly as you
wrote them whatever language the screens are in.

**Emails** — where notifications go and which events trigger them: a new
enquiry, a new visit, a follow-up due, a listing expiring, an import finishing,
a webhook failing.

**Connections** — the API and webhooks. Covered in Part 6.

**Tools** — the maintenance buttons. Rebuild the property search, clear stored
copies, run housekeeping, repair database tables, create practice records to
experiment with, and clear them again.

**⚠️ The last item on Tools is the only thing in the plugin that can delete
your data.** It is a tick-box saying that when the plugin is uninstalled, your
data should be deleted too. It is **off** by default and must be turned on
deliberately. Leave it off unless you specifically want that.

### Practice mode

Practice mode creates realistic fake properties, enquiries and visits so you
can learn the system, or train new staff, without touching real records.
Practice records are clearly marked and can all be removed with one button.
Use it for the first week.

### Roles and permissions

The plugin adds two roles:

- **Office Owner** — everything.
- **Office Agent** — can add and edit listings, work with enquiries, and
  manage visits. Cannot change settings, cannot delete listings, cannot mark
  listings as office verified, and cannot erase personal data.

There are **20 individual permissions** behind those roles, so a developer can
build any intermediate role you need. The important ones to understand:

| Permission | Lets someone |
|---|---|
| Add and edit listings | Create and change properties |
| Publish listings | Put a property on the website |
| Delete listings | Move a property to the bin, and empty it |
| Mark as office verified | Award your office's badge of trust |
| Work with enquiries | See and update enquiries |
| Erase personal information | Permanently remove somebody's details |
| Change office settings | Everything on the Settings screen |

**⚠️ An agent without "Publish listings" who tries to publish gets a draft
instead.** The plugin does not throw an error at them; it quietly saves their
work in a state they are allowed to save it in, and their listing waits for
someone who can publish it.

## 3.14 The welcome screen

Covered in Part 2.3. Once setup is done it stops appearing in the menu, but it
stays reachable and shows your pages plus the "what to do next" list.

---

## 3.15 Keyboard shortcuts

If you spend all day in these screens, these save a lot of clicking. Press
**?** anywhere in the office to see the list on screen — you do not have to
remember it.

| Key | What it does |
|---|---|
| `/` | Jump to the search box |
| `n` | Add a Home |
| `g` then `t` | Go to Today |
| `g` then `l` | Go to Listings |
| `g` then `e` | Go to Enquiries |
| `Ctrl` + `S` (`Cmd` + `S` on a Mac) | Save whatever you are editing |
| `?` | Show the list of shortcuts |
| `Esc` | Close the list |

**Nothing fires while you are typing.** If your cursor is in a box, `n` types
the letter n. The shortcuts only listen when you are not writing something.

Everything a shortcut does can also be done by clicking. They are a shortcut,
never the only way.

---

# PART 4 — YOUR WEBSITE

## 4.1 The two ways to build it

**The easy way — let the plugin do it.** The setup wizard creates your pages
with everything already on them. Nothing further to do.

**The flexible way — build it in Elementor.** Design pages however you like and
drop Estat.OS widgets onto them.

You can mix the two. Let the plugin build the pages, then open any of them in
Elementor and redesign it.

## 4.2 Shortcodes

A shortcode is a piece of text in square brackets that turns into something
real when a visitor views the page. Type it into any page or post.

| Shortcode | What appears |
|---|---|
| `[estat_properties]` | A grid of your properties |
| `[estat_search]` | A search and filter box |
| `[estat_form]` | An enquiry form |
| `[estat_team]` | Your team, with contact details |
| `[estat_compare]` | A side-by-side comparison of saved properties |
| `[estat_favorites]` | The properties this visitor has hearted |
| `[estat_language]` | A language switcher |

They accept settings. `[estat_properties offer="rent" per_page="9"]` shows nine
rentals to a page. Every shortcode works whether or not Elementor is
installed.

## 4.3 Elementor widgets

If you use Elementor, open any page in the editor and search for "Estat" in the
widget panel. There are **17 widgets**:

| Widget | What it does |
|---|---|
| Property Search | Search and filter box |
| Property Directory | A grid or list of properties |
| Property Card | One property, as a card |
| Property Details | The full details of one property |
| Societies & Projects | A list of your societies |
| Project Details | Everything about one society |
| Team Directory | Your whole team |
| Team Member | One person |
| Estat Form | Any form you have built |
| Contact Call to Action | A phone/WhatsApp prompt |
| Property Map | A map with your properties on it |
| Property Gallery | The photos of one property |
| Property Comparison | Side-by-side comparison |
| Saved Properties | A visitor's hearted properties |
| Insights & Articles | Your writing |
| Brochures & Documents | Downloadable files |
| Visit Request | A "book a viewing" form |

Each has full Elementor styling controls, so they inherit your design.

**⚠️ If you disable Elementor, your business keeps running.** Pages built with
Elementor widgets will lose their layout, but your listings, enquiries, visits
and reports are entirely unaffected, and the shortcodes still work.

## 4.4 What visitors can do

- **Search and filter** by locality, price range, size, bedrooms, property
  type, sale or rent, and construction stage.
- **Save favourites** by clicking a heart. Stored in their own browser; no
  account needed.
- **Compare** up to four properties side by side.
- **Enquire**, which lands in your Enquiries screen.
- **See a map**, using free OpenStreetMap by default.

## 4.5 What visitors can never see

This is enforced in the code and verified by tests:

- Draft properties, in any form, anywhere — including if somebody picks a
  draft in an Elementor widget by mistake.
- Your private office notes.
- Your own reference numbers.
- The real figure behind a "Price on request" listing.
- The exact street address, unless you have filled it in deliberately.
- Anything at all about your enquiries, your visits or your team's internal
  records.
- Unpublished societies and projects — a draft society answers exactly as if
  it did not exist.

## 4.6 Google and search engines

The plugin outputs structured data about your properties, so Google can show
rich results. It never publishes a price you have marked as "on request".

The single best thing you can do for your search ranking is to write. See
Part 3.10.

---

# PART 5 — WHEN SOMETHING GOES WRONG

## 5.1 Common problems

**"I added properties but my website is empty."**
You have no page showing them. Go to **Office → Finish setup**, or create a
page and put `[estat_properties]` on it.

**"My property is not on the website."**
It is probably a draft. Open **Listings**, filter by **Drafts**, open it and
choose *Put it on the website*.

**"I published it but visitors still cannot see it."**
Check *Availability* — if it is marked Sold or Rented it may be filtered out.
Then use **Office Settings → Tools → rebuild the property search**.

**"My prices look wrong."**
You typed text where a number belongs. Prices must be digits only:
`8500000`, not `85 lakh`. Check your currency sign in Settings.

**"The same enquiry appears twice."**
It should not, within 24 hours for the same property. Across different
properties, two enquiries from one person are two genuine enquiries.

**"I imported the same file twice and now everything is duplicated."**
Your file had no reference-number column. Add one and the plugin will update
existing rows instead of creating new ones. Bin the duplicates.

**"I deleted something by mistake."**
Look in the bin: **Listings → filter: In the bin → Put it back**. It returns as
a draft. If you deleted it *for good*, it is gone — restore from your website
backup.

**"An agent marked their own listing as verified."**
They cannot, as of version 1.5.1. If you are on an older version, upgrade.

**"Nothing on the screen looks right."**
A theme or plugin conflict. Test by switching to a default WordPress theme; if
it fixes it, tell your theme's author.

## 5.2 The maintenance tools

**Office Settings → Tools**:

| Tool | Use it when |
|---|---|
| Rebuild the property search | Searches return the wrong or missing results |
| Clear stored copies | Changes are not showing up |
| Run housekeeping | Manually trigger the daily tidy-up |
| Repair database tables | After a hosting problem or a failed update |
| Create practice records | You want to experiment safely |
| Clear practice records | You are done experimenting |

## 5.3 The activity history

Every meaningful action is recorded: who did it, what they did, and when.
Listings published, enquiries updated, records binned, records deleted,
settings changed, imports run, files removed.

When you cannot work out what happened, look here first. It is usually the
fastest answer.

## 5.4 Asking for help

Every screen has a **"Need help?"** button, and at the bottom of that panel is
a block of support information: your plugin version, WordPress version, PHP
version, whether Elementor is active, and how many listings you have.

**Copy that when you ask for help.** It contains no passwords, no keys and no
personal information — that is checked by a test.

Report problems at **https://github.com/devkaushall**.

## 5.5 Backups

The plugin protects you from accidents — bins, confirmations, imports as
drafts, keeping your data on uninstall. It cannot protect you from a hosting
failure.

**Take regular backups of your whole website.** Any decent backup plugin will
do. Nothing in this manual replaces that.

---

# PART 6 — TECHNICAL APPENDIX

*For developers. Skip this if you run the office.*

## 6.1 At a glance

| | |
|---|---|
| Plugin folder | `estat-os/estat-os.php` |
| Namespace | `EstatOS` |
| Prefix | `estat` / `ESTAT_` |
| Text domain | `estat-os` |
| REST namespace | `estat/v1` |
| Minimum WordPress | 6.0 |
| Minimum PHP | 7.4 |
| Licence | GPL-2.0-or-later |
| Size | 74 PHP files, ~21,600 lines in `includes/` |
| Dependencies | None. No Composer in production. |
| Automated tests | 1,725 integration + 49 unit |
| Languages | English, Hinglish (both complete) |

## 6.2 Architecture

- **Custom autoloader**, no Composer. `includes/` maps one-to-one onto
  sub-namespaces.
- **Module pattern.** Each module is a class with a `register()` method, wired
  up by `Plugin::register_modules()`. Filter `estat_modules`, action
  `estat_loaded`.
- Every file begins `declare(strict_types=1)` and `defined('ABSPATH') || exit;`
  — no exceptions.
- All formatting lives in one place, `Support\Format`.
- All listing writes funnel through `Data\Listings::save()`.
- Migrations are additive only.

Deliberately **not** used: React admin, Elasticsearch, Redis, Node at runtime,
required Elementor, required ACF.

## 6.3 Data model

Content types: `estat_listing`, `estat_project`, `estat_agent`, `estat_agency`,
`estat_document`, `estat_insight`.

Taxonomies: `estat_locality`, `estat_feature`, `estat_amenity`,
`estat_insight_kind`, `estat_insight_topic`.

Eight custom tables: `{prefix}estat_leads`, `estat_visits`, `estat_forms`,
`estat_submissions`, `estat_index`, `estat_audit`, `estat_webhook_log`,
`estat_import_jobs`.

Options: `estat_settings`, `estat_db_version`, `estat_version`,
`estat_installed_at`, `estat_index_repair_offset`, `estat_show_setup`.

Areas are normalised to square feet on save (sqft 1.0, sqyd 9.0, sqm 10.7639),
with the original unit preserved for display.

## 6.4 REST API

Base: `/wp-json/estat/v1/`. Fourteen endpoints, every one with a permission
callback.

**Public:**
```
GET  /properties
GET  /properties/{id}          (published only)
GET  /projects/{id}/stats      (published only)
GET  /agents
POST /forms/{id}/submit
POST /enquiries                (requires a shared secret)
```

**Authenticated:**
```
GET|POST  /leads
PATCH     /leads/{id}
GET|POST  /visits
PATCH     /visits/{id}
GET       /audit
POST      /maintenance/{task}
```

The inbound `/enquiries` endpoint refuses everything until you set a shared
secret in Office Settings → Connections. The secret is compared with
`hash_equals`.

## 6.5 Webhooks

Configure a URL in Office Settings → Connections. Events:
`lead.created`, `lead.updated`, `visit.scheduled`, `visit.completed`,
`listing.published`.

Each request carries:

```
X-Estat-Event:     lead.created
X-Estat-Timestamp: 1757404800
X-Estat-Signature: sha256=<hex>
```

The signature is HMAC-SHA256 of `timestamp + "." + rawBody`, keyed with your
secret. Reject anything older than five minutes. Plain `http://` URLs are
refused outright.

## 6.6 Extending it

Filters include `estat_modules`, `estat_worklist`, `estat_form_templates`,
`estat_form_field_groups`, `estat_validate_listing`. Actions include
`estat_loaded`. The full list is in `docs/hooks.md`.

Other documentation in `docs/`: `architecture.md`, `rest-api.md`,
`webhooks.md`, `csv.md`, `elementor.md`, `translating.md`, `hooks.md`.

## 6.7 Testing

```
php tests/run-tests.php                    # 49 unit tests
php tests/integration/run-integration.php  # 1,023 integration tests
```

The suite covers the full lifecycle, security, performance, delivery, the
gallery, the worklist, mobile layout, form templates, the audit fixes, the
setup wizard, the simplified editor, and the help system.

Every safety-critical assertion has been **mutation tested** — the guard it
claims to cover is deliberately removed to confirm the test actually fails.

## 6.8 Security summary

Audited in full before release. Verified:

- SQL parameterised throughout; tested against injection payloads.
- No XSS across 16 admin and public surfaces.
- Capability, nonce, validation and sanitisation on every write.
- No leakage of owner details, internal notes, on-request prices, or lead data
  through public routes.
- CSV imports contained to the uploads folder; path traversal refused.
- CSV exports neutralise spreadsheet formulas.
- Webhooks signed with HMAC-SHA256, compared with `hash_equals`; secrets never
  logged.
- IP addresses salted and hashed, never stored raw.
- Nothing deleted on deactivate or uninstall without explicit opt-in.

Five faults were found by that audit and all five are fixed in 1.5.1; they are
listed in the changelog.

## 6.9 Performance

Tested with 10,000 indexed listings: every query bounded and paged, page size
capped at 60, deep paging behaved, peak memory 36 MB.

There are no unbounded queries in runtime code. Search runs against a dedicated
index table rather than post meta. Assets load only on the screens that need
them. Imports are batched. Housekeeping runs on cron, hourly and daily.

---

---

# PART 7 — THE FULL HISTORY

Every version of Estat.OS, newest first, in plain language.

If you are wondering **"why does it work this way?"** — the answer is usually
here. Most of these entries exist because something was genuinely wrong and
somebody noticed.

## How to read the version numbers

The plugin uses three numbers, `2.3.1`, and each one means something:

| Position | Called | Changes when |
|---|---|---|
| First | Major | Something changed that could affect how you already work |
| Second | Minor | A new feature arrived; nothing you rely on broke |
| Third | Patch | Only a fix; nothing new, nothing changed |

So going from 2.3.0 to 2.3.1 is always safe. Going from 1.x to 2.0 is the one
to read carefully.

---

## Version 2 — polish, honesty and scale

### 2.6.0 — four details that matter to a buyer

Measured against published research rather than guessed at.

The main photograph on a property page now tells the browser its size and is
fetched first, so the page stops jumping as it loads and Google times it
faster. A **verified badge** appears on properties the office has checked —
that information was always recorded and shown to nobody. Listings say **how
long they have been up**, which buyers read as urgency or as room to negotiate.

And sale listings now carry a **monthly repayment estimate**. It works entirely
in your visitor's browser, sends nothing anywhere, and is not a form — someone
working out whether they can afford a flat is thinking, not enquiring.

There is also a short line under every enquiry form saying the number goes to
your office and nowhere else. On Indian portals the loudest complaint is the
flood of broker calls after one enquiry; this plugin has never done that, and
now it says so.

### 2.5.0 — the visitor's side

The office screens had four thousand lines of stylesheet and the public website
had a hundred and eighty. That is the whole explanation for why the front of
the site felt unfinished.

Rewritten on the same footing as the admin: a proper set of design tokens, so a
theme can restyle everything by redefining a few names; hover, focus, empty,
dark mode, print and high-contrast all designed rather than left to chance; and
the property card given real attention, since it is the thing a visitor sees
most.

Three demo photographs now ship with the plugin and attach themselves to the
practice properties, because three listings with no images taught exactly the
wrong lesson about what a finished office looks like.

### 2.4.0 — the Mayfair asset package

The icons and the background line-work now come from the office's own asset
package rather than being drawn by hand.

**98 icons**, one family (Phosphor Regular), all on the same 256-unit grid, all
taking their colour from the text around them — which is why they follow dark
mode without a second definition. The hand-drawn set they replaced was 47
icons on a 24-unit grid, and mixing the two would have looked worse than
either.

**Background line-work** — thin gold at 10-22% opacity, sitting behind
containers. The four small tileable pieces are built into the stylesheet; the
larger illustrative ones ship as files for use on the public site and in
Elementor. One element per area, never behind body text, and dropped entirely
in dark mode and when printing.

### 2.3.2 — the manual caught up

The manual had been sixteen releases out of date: its header still said 1.7.0,
the README still said 1.0.0, and nothing added since — bulk actions, undo,
shortcuts, the form studio, finished Hinglish — was in it at all.

This part you are reading was added, along with a test that fails the build if
any document falls behind the code again. A manual nobody checks becomes
confidently wrong, which is worse than not having one.

### 2.3.1 — a paging lie and a missing file

Five more inspections, each using a different method: a full run through the
business day, a deliberate attempt to break in, every status transition, the
database pulled out from underneath it, and documented promises checked against
the code. Two findings.

- **A page number could exceed the number of pages.** Ask for page 100000 of a
  two-page result and it said "page 100000 of 2". No wrong data was ever shown —
  the answer was just untrue.
- **There was no `readme.txt`.** A plugin cannot be listed on WordPress.org
  without one. Added, with the version and requirements.

### 2.3.0 — the sixty-check audit

The whole plugin inspected in sixty passes. **Eleven faults found.** Three of
them shared one shape, and it is worth understanding because it explains a lot:

> The part that **saves** information and the part that **shows** it were built
> at different times, and nobody compared them. The editor collected a field,
> the checks accepted it, the database stored it — and nothing ever read it
> back. Every piece worked. Only the join was missing.

- **Twelve property fields were saved and shown to nobody.** Deposit,
  maintenance, balconies, total floors, age, facing, area type, video link,
  360° tour, builder, possession date and registration number. You typed them
  in and no visitor ever saw them. All twelve now appear on the property page.
- **Societies had the same problem, worse** — there was no reader at all. The
  builder, price range, unit counts, possession date and unit types reached
  nobody. Fixed.
- **"Export site visits" gave you an empty file** with the wrong column
  headings, and said nothing. It exports visits now.
- **"Erase everything" did not erase everything.** Insights and its two
  categories were missed, so an office that deliberately asked to be wiped kept
  every article for ever.
- **One caller became two enquiries.** `9876543210` and `+919876543210` were
  treated as different people. Both are typed constantly in India, so offices
  were ringing the same person twice.
- Titles are now capped and control characters removed.
- Today and Listings now check permission themselves instead of trusting the
  menu.

### 2.2.0 — bulk, undo, shortcuts, Hinglish

The four things left on the list.

- **Act on many records at once**, with permission checked on every single one.
- **Undo the last edit.** Deleting was always safe; editing was not.
- **Keyboard shortcuts**, with a visible list under `?`.
- **Hinglish finished** — from 28% to all 1,504 pieces of text.

Two bugs found while doing it: the tool that collects translatable text was
**missing 627 of them**, and the compiled translation file **could not be read
by WordPress at all**, so it silently fell back to English.

### 2.1.0 — behaving properly with a lot of records

Every fault here shared one habit: **the code stopped early and said nothing.**

- Dropdowns hid everything past the two hundredth record with no message. To an
  office that looks exactly like a record that has been lost.
- Societies and Team could not be searched or paged past the first fifty.
- Site Visits had page links that were never drawn — page two existed and could
  not be reached.
- 999 bedrooms and a price of 9,900,000,000,000,000,000 were both accepted.
- A negative price check had **never once run**, because the value was already
  turned into zero before the check saw it. A minus price saved as "0" and
  nobody was told.

### 2.0.1 — the studio, squeezed

Same symptom as 1.9.2, third different cause: a 780px width limit on the form
the builder happens to live inside. On a 1490px screen the studio went from
780px to 1450px.

This was the third width bug in a row caused by a layer **above** the one being
looked at, so the test now walks the whole chain rather than checking one rule.

### 2.0.0 — why it felt machine-made

The office said the interface "felt AI-made". It did, and the reason turned out
to be countable rather than a matter of taste:

| | Before | After |
|---|---|---|
| Different font sizes | **21** (including 12.5px, 13.5px) | **7**, on a real scale |
| Emoji used as icons | **60**, across 11 files | **0** |
| Proper icons | none | **47**, one grid, one weight (98 from 2.4.0) |
| Pure black or white | 9 places | **0** |

The twenty-one font sizes were the biggest tell. Sizes like 12.5px exist
because a number got nudged until it looked right — and that is exactly what
reads as generated. Emoji were the second: your computer draws them, so the
same screen looked different on Windows, Mac and Android.

Also added the small interaction details a person would not skip: no 300ms
delay after a tap, numbers that line up in columns, focus rings only for
keyboard users.

---

## Version 1 — building it

### 1.9.2 — half the screen was empty

Three separate causes, all found at once: a 1240px reading-width cap, a
breakpoint at 1400px that dropped a 1408px screen to two columns, and column
widths that overflowed their own grid once the gaps were added.

### 1.9.1 — the studio did not fit

Row controls needed about 930px inside a column that was 425px wide. Labels
broke across three lines, "Remove" was cut to "Re".

The fix that mattered was not the layout — it was a **new test that does
arithmetic**: it reads the real column widths out of the stylesheet and fails
if anything cannot fit. It immediately found four more overflows nobody had
reported.

### 1.9.0 — the form studio

Seven steps, three columns, and twenty-five styling settings. The preview moved
to the right and stays in front of you as you scroll.

### 1.8.0 — the form builder, rebuilt

One-click column layouts, a computer/tablet/phone switch, box heights, spacing
sliders.

Found while doing it: **the canvas had been lying about your layout.** Every
column was drawn the same size regardless of its real width, so a 25/75 split
looked like 50/50. And new rows silently saved a spacing of zero, because the
builder sent the word "normal" where a number was expected.

### 1.7.1 — tapping a photo did nothing

Three separate wiring faults, each fatal on its own. The photo controls looked
completely dead.

This one is worth remembering: **all 1,023 tests were green.** The PHP was fine
on its own, the JavaScript was fine on its own, and they simply disagreed about
one attribute name. No test compared the two halves. Now one does.

### 1.7.0 — a shorter property screen

Forty-five fields at once became the everyday ones first, with the rest in five
fold-away groups. **Not one field was removed** — a test pins all forty-five by
name. A group that already has something in it opens by itself, so nothing ever
looks lost.

Real help written for all fourteen screens.

### 1.6.0 — the welcome screen

Until now, activating the plugin gave you an office with nowhere to put
anything. Four short questions now build your website pages for you. You can
skip it entirely.

### 1.5.1 — the pre-release audit

A line-by-line audit before first real use. **Five genuine bugs**, including a
verification status a plain agent could grant themselves, and a spreadsheet
export that could carry a formula into Excel.

This release also added the **bin**: nothing is destroyed by a single click any
more. Permanent deletion needs the record to already be in the bin *and* the
word DELETE typed out.

### 1.5.0 — the form builder

Design your own forms, with five ready-made ones to start from. Every
submission becomes an enquiry automatically.

The rule set here has held ever since: **changing how a form looks can never
change what it does.**

### 1.4.0 — the interface on a phone

Forty hardcoded colours converted to the design system, tables that reflow on a
narrow screen, and finger-sized controls.

### 1.3.0 — Today became useful

A dashboard of numbers became **a short list of real jobs**, in order of
urgency: overdue follow-ups first, then today's visits, then unanswered
enquiries. Adding a home became four short steps.

### 1.2.0 — Gallery, and a dark theme

The Gallery menu had been pointing at the plain WordPress media library — every
file on the site, in language an estate office does not use. Replaced with a
real gallery: photos, floor plans, brochures and documents, sorted by kind,
with drag-and-drop upload.

### 1.1.1 — five screens that were not real

Five menu entries had been quietly pointing at built-in WordPress screens. They
were honestly rebuilt.

### 1.1.0 — feature chips

Tick what actually sells a property — corner plot, park facing, near metro —
and the best two or three appear as small tags on the property card.

### 1.0.0 — the first stable release

Everything an estate office needs: listings, societies, team, enquiries, site
visits, media, insights, search, favourites, compare, spreadsheet import and
export, a Today dashboard, settings, roles, an activity history, a REST API,
signed webhooks, Elementor widgets, and a practice mode for trying it safely.

---

## What the numbers say

| | At 1.0.0 | At 2.3.1 |
|---|---|---|
| Files | 121 | 140 |
| Lines of code | ~17,000 | ~21,500 |
| Automated tests | 830 | **1,725** |
| Languages | 1 | 2, fully translated |
| Known open faults | — | none |

**Every fix in this list has a test.** Not "the code exists" — a test that has
been deliberately broken to prove it fails when the fault comes back. That
technique found several tests that looked fine and were checking nothing.

## What is still not perfect

An honest list, because a manual that claims perfection is not trustworthy:

- **A visit can outlive its enquiry.** If an enquiry is deleted, a site visit
  attached to it stays in the list without a name beside it.
- **A price marked "on request" is hidden from your own office view too**, not
  just from visitors. It is in the editor, but not on the listing summary.
- **The test harness is not a real database.** It cannot check `LIMIT`/`OFFSET`,
  so paging tests are weaker than they look. Real MySQL behaviour has been
  reasoned through, not executed.
- **Nothing here has been run in a browser by the person who wrote it.** The
  HTML, JavaScript and CSS are checked against each other by machine, and every
  browser-facing bug found so far was reported by an actual user. That is a real
  limit, and it is why your feedback has mattered so much.


## Credits

**Estat.OS** — the operating system for a real-estate office.

Built by **DEVil** · https://github.com/devkaushall
Free and open source under **GPL-2.0-or-later**.

Version 1.7.0 · This manual matches that version.

*If this plugin runs your office well, tell another office about it.*
