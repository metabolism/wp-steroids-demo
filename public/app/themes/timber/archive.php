<?php
/**
 * The template for displaying Archive pages.
 *
 * Used to display archive-type pages if nothing more specific matches a query.
 * For example, puts together date-based pages if no date.php file exists.
 *
 * Learn more: http://codex.wordpress.org/Template_Hierarchy
 *
 * Methods for TimberHelper can be found in the /lib sub-directory
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since   Timber 0.2
 */

use Timber\Timber;

$templates = array( 'archive.twig', 'index.twig' );

$context = Timber::context();

if ( is_tax() ) {
	array_unshift( $templates, 'archive-' . get_query_var( 'taxonomy' ) . '.twig' );
} elseif ( is_post_type_archive() ) {
	$context['title'] = post_type_archive_title( '', false );
    array_unshift( $templates, 'archive-' . get_query_var( 'post_type' ) . '.twig' );
}

Timber::render( $templates, $context );
