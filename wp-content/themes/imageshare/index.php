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
$context['resource_count'] = $imageshare->controllers->search->get_published_resource_count();

function multi_param ($var) {
    $vars = [];

    foreach ($_GET as $key => $value) {
        if (preg_match("/^{$var}(_[0-9]+)?$/", $key)) {
            if (is_array($value)) {
                $vars = array_merge($vars, $value);
            } else if ($value !== null) {
                array_push($vars, $value);
            }
        } 
    }

    return $vars;
}

if (($_GET['page'] ?? null) === 'search') {
    track_last_search();

    $search_params = [
        'query'   => $_GET['q'] ?? '',
        'subject' => array_unique(multi_param('subject')),
        'type'    => array_unique(multi_param('type')),
        'acc'     => array_unique(multi_param('acc'))
    ];

    $paging = [
        'rp' => $_GET['rp'] ?? null,
        'rs' => $_GET['rs'] ?? null,
        'cp' => $_GET['cp'] ?? null,
        'cs' => $_GET['cs'] ?? null
    ];

    $context['results'] = $imageshare->controllers->search->query(array_merge(
       $paging, $search_params
    ));

    $context['is_search'] = true;

    return Timber::render( array( 'search-results.twig'), $context );
}

clear_last_search();

$context['collections'] = $imageshare->controllers->resource_collection->get_featured_collections(8);
$context['collection_archive_href'] = get_post_type_archive_link('btis_collection');
$context['is_home'] = is_home();

$templates = array( 'index.twig' );
if ( is_home() ) {
    array_unshift( $templates, 'front-page.twig', 'home.twig' );
}

Timber::render( $templates, $context );
