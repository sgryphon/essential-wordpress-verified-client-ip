#!/usr/bin/env php
<?php

/**
 * Convert docs/user-guide.md to src/user-guide.html for inclusion in the
 * plugin's admin User Guide tab.
 *
 * Usage: php tools/build-user-guide.php
 */

declare(strict_types=1);

$root = \dirname( __DIR__ );

require $root . '/vendor/autoload.php';

$source = $root . '/docs/user-guide.md';
$output = $root . '/src/user-guide.html';

if ( ! \file_exists( $source ) ) {
	\fwrite( \STDERR, "Error: source file not found: {$source}\n" );
	exit( 1 );
}

$markdown = \file_get_contents( $source );
if ( false === $markdown ) {
	\fwrite( \STDERR, "Error: could not read {$source}\n" );
	exit( 1 );
}

$parsedown = new \Parsedown();
$parsedown->setSafeMode( false ); // We control the source; allow full HTML output.
$html = $parsedown->text( $markdown );

// Wrap in a container div for scoped styling in the admin page.
$wrapped = '<div class="vcip-user-guide">' . "\n" . $html . "\n" . '</div>' . "\n";

if ( \file_put_contents( $output, $wrapped ) === false ) {
	\fwrite( \STDERR, "Error: could not write {$output}\n" );
	exit( 1 );
}

echo "Generated: {$output}\n";
