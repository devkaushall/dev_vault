<?php
/**
 * Input sanitising.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Support\Sanitize;

EstatTests::group( 'Sanitize: numbers are clamped, never trusted' );

EstatTests::is( 5, Sanitize::int( '5' ), 'numeric strings become integers' );
EstatTests::is( 0, Sanitize::int( 'not a number' ), 'nonsense becomes zero' );
EstatTests::is( 1, Sanitize::int( 0, 1, 10 ), 'a value below the minimum is raised' );
EstatTests::is( 10, Sanitize::int( 9999, 1, 10 ), 'a value above the maximum is capped' );
EstatTests::is( 0.0, Sanitize::float( -12.5 ), 'negative money is refused by default' );

EstatTests::group( 'Sanitize: text' );

EstatTests::is( 'Hello', Sanitize::text( '  Hello  ' ), 'surrounding whitespace is trimmed' );
EstatTests::is( 'alert(1)', Sanitize::text( '<script>alert(1)</script>' ), 'tags are stripped from plain text' );

EstatTests::group( 'Sanitize: contact details' );

EstatTests::is( 'a@b.com', Sanitize::email( ' a@b.com ' ), 'a valid address survives' );
EstatTests::is( '', Sanitize::email( 'not-an-email' ), 'an invalid address becomes empty' );
EstatTests::is( '', Sanitize::url( 'javascript:alert(1)' ), 'a javascript URL is refused' );

EstatTests::group( 'Sanitize: choices fall back safely' );

EstatTests::is( 'sale', Sanitize::choice( 'sale', array( 'sale', 'rent' ), 'sale' ), 'an allowed value passes' );
EstatTests::is( 'sale', Sanitize::choice( 'burglary', array( 'sale', 'rent' ), 'sale' ), 'anything else falls back' );

EstatTests::group( 'Sanitize: identifier lists' );

EstatTests::is( array( 1, 2, 3 ), Sanitize::id_list( '1,2,3' ), 'a comma separated list becomes integers' );
EstatTests::is( array( 1, 2 ), Sanitize::id_list( array( '1', 'x', 2, -0 ) ), 'non-numeric entries are dropped' );
