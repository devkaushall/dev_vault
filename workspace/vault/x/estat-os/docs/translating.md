# Translating Estat.OS

The plugin uses nothing but standard gettext, so any WordPress translation tool
works. Two languages ship in the box:

| Locale | Language |
| --- | --- |
| `en_US` | English |
| `en_IN_hinglish` | Hinglish — Hindi written in normal English letters |

## Why Hinglish

Most Indian property offices do not speak formal Hindi at work, and Devanagari
on a shared office computer is a barrier. They say *"nayi property add karein"*.
So that is exactly what the interface says. It is a first-class locale, not a
joke: the same `.po`/`.mo` machinery as any other language.

## Choosing a language

**Office Settings → Language** sets the office interface language and the public
website language independently, and can let visitors switch for themselves.
Individual staff can also pick their own language on their WordPress profile.

## Adding a language

1. Copy `languages/estat-os.pot`.
2. Open it in [Poedit](https://poedit.net/) (free) and choose your language.
3. Translate. You do not need to finish — anything untranslated falls back to
   English.
4. Save as `estat-os-{locale}.po`; Poedit writes the `.mo` beside it.
5. Put both files in `wp-content/languages/plugins/`. That folder survives plugin
   updates, so put them there rather than inside the plugin.
6. Your locale appears automatically in the language dropdown.

To make it appear under a friendlier name:

```php
add_filter( 'estat_available_languages', function ( array $languages ) {
	$languages['mr_IN'] = 'Marathi';
	return $languages;
} );
```

## Notes for translators

* Placeholders such as `%s`, `%d` and `%1$d` must survive exactly as they are.
* Some strings are plural forms with two boxes to fill in.
* Keep the plain, friendly register. This interface is written for someone who
  has never used WordPress before, and it should stay that way in every
  language. Prefer *"Website par live karein"* over a literal *"Prakashit
  karein"*.
* Never translate a technical identifier such as `[estat_form id="3"]`.
