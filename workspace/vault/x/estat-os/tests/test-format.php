<?php
/**
 * Money and area formatting.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

use EstatOS\Support\Format;

EstatTests::group( 'Format: area is always normalised to square feet' );

EstatTests::is( 100.0, Format::to_sqft( 100.0, 'sqft' ), 'square feet pass through unchanged' );
EstatTests::is( 900.0, Format::to_sqft( 100.0, 'sqyd' ), '100 square yards is 900 square feet' );
EstatTests::is( 1076.39, Format::to_sqft( 100.0, 'sqm' ), '100 square metres is 1076.39 square feet' );
EstatTests::is( 100.0, Format::to_sqft( 100.0, 'nonsense' ), 'an unknown unit is treated as square feet' );
EstatTests::is( 100.0, Format::from_sqft( 900.0, 'sqyd' ), 'converting back is lossless' );

EstatTests::group( 'Format: Indian money' );

EstatTests::is( '₹8.5 Cr', Format::money( 85000000.0, '₹', 'indian' ), '85000000 reads as 8.5 Cr' );
EstatTests::is( '₹1 Cr', Format::money( 10000000.0, '₹', 'indian' ), 'exactly one crore has no decimals' );
EstatTests::is( '₹45 Lakh', Format::money( 4500000.0, '₹', 'indian' ), '4500000 reads as 45 Lakh' );
EstatTests::is( '₹99 K', Format::money( 99000.0, '₹', 'indian' ), 'thousands read as K' );
EstatTests::is( '₹950', Format::money( 950.0, '₹', 'indian' ), 'small amounts are shown in full' );
EstatTests::is( '₹0', Format::money( -5.0, '₹', 'indian' ), 'a negative amount never leaks a minus sign' );

EstatTests::group( 'Format: international money' );

EstatTests::is( '$8.5M', Format::money( 8500000.0, '$', 'compact' ), 'millions read as M' );
EstatTests::is( '$450K', Format::money( 450000.0, '$', 'compact' ), 'thousands read as K' );

EstatTests::group( 'Format: readable area' );

EstatTests::is( '1,450 sq ft', Format::area( 1450.0, 'sqft' ), 'whole numbers have no decimals' );
EstatTests::is( '1,450.50 sq ft', Format::area( 1450.5, 'sqft' ), 'fractions keep two decimals' );
