<?php
/**
 * The domain vocabulary.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Support\Vocabulary;

EstatTests::group( 'Vocabulary: the status axes stay separate' );

$offers       = Vocabulary::offers();
$availability = Vocabulary::availability();
$construction = Vocabulary::construction();

EstatTests::ok( isset( $offers['sale'], $offers['rent'] ), 'offers cover sale and rent' );
EstatTests::ok( ! array_intersect_key( $offers, $availability ), 'offer and availability never share a key' );
EstatTests::ok( ! array_intersect_key( $availability, $construction ), 'availability and construction never share a key' );

EstatTests::group( 'Vocabulary: helpers' );

EstatTests::ok( in_array( 'sale', Vocabulary::keys( 'offers' ), true ), 'keys() lists the raw values' );
EstatTests::is( array(), Vocabulary::keys( 'no_such_vocabulary' ), 'an unknown vocabulary returns nothing rather than exploding' );
EstatTests::ok( '' !== Vocabulary::label( 'offers', 'sale' ), 'a known value has a human label' );

EstatTests::group( 'Vocabulary: area units match the conversion table' );

foreach ( array_keys( Vocabulary::area_units() ) as $unit ) {
	EstatTests::ok(
		array_key_exists( $unit, EstatOS\Support\Format::AREA_FACTORS ),
		'the unit "' . $unit . '" offered in the interface can actually be converted'
	);
}
