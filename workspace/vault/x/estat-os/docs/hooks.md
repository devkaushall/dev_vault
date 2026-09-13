# Hooks

Everything below is stable API and follows semantic versioning: no hook is
renamed or removed in a minor release.

## Actions

| Hook | Arguments | Fires |
| --- | --- | --- |
| `estat_loaded` | `Plugin $plugin` | Once every module has registered |
| `estat_lead_created` | `int $lead_id` | A new enquiry has been stored |
| `estat_lead_updated` | `int $lead_id, array $fields` | An enquiry changed |
| `estat_visit_scheduled` | `int $visit_id` | A site visit was arranged |
| `estat_form_submitted` | `int $form_id, array $payload, int $lead_id` | A form passed validation and was stored |

```php
add_action( 'estat_lead_created', function ( int $lead_id ) {
	$lead = EstatOS\Leads\Leads::get( $lead_id );
	// Send it to your own system.
}, 10, 1 );
```

## Filters

| Hook | Filters |
| --- | --- |
| `estat_modules` | The module class map, before instantiation |
| `estat_listing_data` | The array a listing is turned into for output |
| `estat_validate_listing` | Validation errors before a listing is saved |
| `estat_sanitize_settings` | Settings after sanitising, before saving |
| `estat_form_field_types` | The catalogue of form field types |
| `estat_schema_data` | The structured data emitted for a listing |
| `estat_available_languages` | The list of interface languages |

### Adding your own module

```php
add_filter( 'estat_modules', function ( array $classes ) {
	$classes['my_addon'] = My\Addon::class; // must expose register()
	return $classes;
} );
```

### Adding a form field type

```php
add_filter( 'estat_form_field_types', function ( array $types ) {
	$types['budget'] = array(
		'label'   => 'Budget slider',
		'input'   => 'number',
		'options' => false,
	);
	return $types;
} );
```

### Changing how a listing appears publicly

```php
add_filter( 'estat_listing_data', function ( array $data, int $listing_id ) {
	$data['badge'] = get_post_meta( $listing_id, '_my_badge', true );
	return $data;
}, 10, 2 );
```

## Capabilities

Nineteen capabilities, all prefixed `estat_`:

`estat_manage_listings`, `estat_publish_listings`, `estat_delete_listings`,
`estat_verify_listings`, `estat_manage_projects`, `estat_manage_team`,
`estat_manage_leads`, `estat_assign_leads`, `estat_erase_leads`,
`estat_manage_visits`, `estat_manage_forms`, `estat_view_submissions`,
`estat_import_inventory`, `estat_export_inventory`, `estat_view_reports`,
`estat_view_audit`, `estat_manage_settings`, `estat_manage_integrations`,
`estat_run_maintenance`.

Two roles ship with the plugin: **Office Owner** (all of them) and
**Office Agent** (day-to-day work, but no settings, no deletion, no erasure).
Administrators receive every capability on activation.
