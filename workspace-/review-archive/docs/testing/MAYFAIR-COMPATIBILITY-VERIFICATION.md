# Mayfair Compatibility Verification

## Environment
A workspace search found no installable Mayfair Core, Mayfair Forms & Leads, ACF, Elementor, or Elementor Pro ZIP artifacts. The existing WordPress Playground harness uses representative registrations/classes only.

## Command/Test
Search workspace artifacts, then—if available—install actual dependencies and capture CPTs, taxonomies, post IDs, settings, REST namespaces, and lead workflow state before and after platform activation.

## Expected result
Mayfair Core, Forms & Leads, ACF, Elementor, and Elementor Pro are detected. Existing `property`, `project`, `insight`, Mayfair taxonomies, IDs, settings, REST namespaces, and lead workflows remain untouched and unduplicated.

## Actual result
Representative fixture detection passes for all named dependencies, three CPTs, an `mpd_` taxonomy, compatibility mode, and absence of a replacement platform CPT. No real proprietary Mayfair artifact was available, so preservation in a real installation was not executed.

## Result
**NOT VERIFIED**

## Evidence
- `scripts/playground-verify.mjs`
- `verification-results/php-8.1.json`
- `verification-results/php-8.2.json`
- `verification-results/php-8.3.json`

Fixture results are explicitly not treated as full compatibility.
