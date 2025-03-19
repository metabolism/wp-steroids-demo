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

global $wp_query;

$templates = array( 'archive.twig', 'index.twig' );

$context = Timber::context();
$context['paged'] = max(1, get_query_var('paged',1));
$context['max_num_pages'] = $wp_query->max_num_pages;
$context['posts_per_page'] = $wp_query->get('posts_per_page')?:get_option( 'posts_per_page' );
$context['queried_object'] = get_queried_object();

if ( is_category() ) {
    $context['current_category'] = get_query_var( 'cat' );
    array_unshift( $templates, 'archive-category.twig' );
}
if ( is_tax() ) {

    $context['current_taxonomy'] = get_queried_object();

    if( get_query_var( 'post_type' ) ){

        array_unshift( $templates, 'archive-' . get_query_var( 'post_type' ) . '.twig' );

        if( $context['paged'] > 1 )
            array_unshift( $templates, 'archive-' . get_query_var( 'post_type' ) . '-paged.twig' );
    }

	array_unshift( $templates, 'archive-' . get_query_var( 'taxonomy' ) . '.twig' );

    if( $context['paged'] > 1 )
        array_unshift( $templates, 'archive-' . get_query_var( 'taxonomy' ) . '-paged.twig' );

} elseif ( is_post_type_archive() ) {

    array_unshift( $templates, 'archive-' . get_query_var( 'post_type' ) . '.twig' );

    if( $context['paged'] > 1 )
        array_unshift( $templates, 'archive-' . get_query_var( 'post_type' ) . '-paged.twig' );
}

Timber::render( $templates, $context );
