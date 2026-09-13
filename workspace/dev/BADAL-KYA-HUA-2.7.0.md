# Kya hua — 13 September 2026 (short note, Dev ke liye)

## Pichhli chat kahan ruki thi

Agent ne ek naya feature shuru kiya tha: **"Change Requests"** (agent dusre ki
property ka price/sold waghera khud badal nahi sakta — bas office se *pooch*
sakta hai). Code ka ek tukda likha tha, table bhi bana tha — **lekin feature
sele se bandha hi nahi gaya tha.** Kaagaz par tha, duniya mein kuch nahi hota.

## Maine kya kiya (aur check bhi kiya)

1. **Feature poora joda:** naya screen "Change Requests", office ke Today list
   mein entry, agent ke liye property par chhota "Suggest a change" form,
   approve/not-now buttons, email ka option (band by default), permissions.
2. **Do asli galtiyan pakdi aur theek ki:**
   - "Yes, change it" button ka lock (nonce) galat naam se ban raha tha —
     ek bhi approval kabhi kaam nahi karta, hamesha "link expired" aata.
   - Khaali form bhi request bana deta tha (sirf note ho, kuch badle nahi).
3. **Ek murdaam entry bhi nikaali:** permissions map mein ek dead entry thi
   (purane audit ne isi ko "delete kaam nahi karta" samajh liya tha — delete
   pahle se kaam karta hai, ab wo bhram hi nahi rahega).
4. **Naye test:** `tests/integration/it-31-field-requests.php` — 88 assertions.
   Maine jaan-boojh kar 3 bar code kharab karke dekha ki tests unhe pakadte
   hain — teenon pakde gaye.
5. **Docs update:** version 2.7.0, manual ka Part 3.6 naya section, changelog,
   readme — sab test se verified.

## Numbers (asli run, bahana nahi)

- Integration tests: **2,038 passing, 0 failing** (asli WordPress 7.2-core ke
  saath, PHP 8.3)
- Unit tests: **49 passing**
- Saari PHP files ka syntax: clean

## Ek imaandari wali baat

Is environment mein browser nahi hai. Maine design CSS markup-level tak check
kiya (tokens, dark mode, focus rings, contrast rules sab pass), lekin **asli
browser mein "Yes, change it" par click karna aapko hi hai** — ek baar. Plugin
ke apne manual mein bhi ye limit likhi hai.

## Aapke liye files

- `dev/design-check-change-requests.html` — naye screens ka visual (light/dark
  toggle ke saath). Pehle ye dekhein.
- `dev/estat-os-2.7.0.zip` — poora updated plugin. GitHub par is folder ko
  replace kar dein, ya `estat-os/` ke andar ka code copy kar dein.

## Kaise chalu karein (apni machine par)

```
php tests/run-tests.php
php tests/integration/run-integration.php
```

WordPress ka source `/tmp/wordpress/` chahiye (test harness wahi dhoondhta hai).
