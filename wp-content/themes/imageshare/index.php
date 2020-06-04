<?php
/**
 * The main template file
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists
 *
 * Methods for TimberHelper can be found in the /lib sub-directory
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since   Timber 0.1
 */

$context = Timber::context();
$context['search_terms'] = $imageshare->controllers->search->get_available_terms();

if (($_GET['page'] ?? null) === 'search') {
    $context['search_params'] = [
        'query'         => $_GET['q'] ?? '',
        'subject'       => $_GET['subject'] ?? null,
        'type'          => $_GET['type'] ?? null,
        'accommodation' => $_GET['acc'] ?? null
    ];

    if ($_GET['p_query'] ?? null) {
        $previous_params = [
            'query'         => $_GET['p_query'] ?? '',
            'subject'       => $_GET['p_subject'] ?? null,
            'type'          => $_GET['p_type'] ?? null,
            'accommodation' => $_GET['p_acc'] ?? null
        ];
    }

    $narrow = $_GET['narrow'] ?? false;

    $context['results'] = $imageshare->controllers->search->query(
        array_merge(['narrow' => $narrow, 'previous' => $previous_params ?? null], $context['search_params'])
    );

    return Timber::render( array( 'page-search.twig'), $context );
}

$context['collections'] = $imageshare->controllers->resource_collection->get_featured_collections(8);
$context['collection_archive_href'] = get_post_type_archive_link('btis_collection');

$templates = array( 'index.twig' );
if ( is_home() ) {
    array_unshift( $templates, 'front-page.twig', 'home.twig' );
}

Timber::render( $templates, $context );
