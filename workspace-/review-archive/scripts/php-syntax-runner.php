<?php
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( '/workspace/plugin' ) );
$failed   = array();
$count    = 0;
foreach ( $iterator as $file ) {
	if ( $file->isFile() && str_ends_with( $file->getFilename(), '.php' ) ) {
		++$count;
		try {
			token_get_all( file_get_contents( $file->getPathname() ), TOKEN_PARSE );
		} catch ( ParseError $error ) {
			$failed[ $file->getPathname() ] = $error->getMessage();
		}
	}
}
echo json_encode( array( 'status' => $failed ? 'FAIL' : 'PASS', 'files' => $count, 'failed' => $failed ), JSON_PRETTY_PRINT );
