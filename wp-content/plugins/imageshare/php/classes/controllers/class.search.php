<?php

namespace Imageshare\Controllers;

use Imageshare\Logger;
use Imageshare\Models\Model as Model;
use Imageshare\Models\Resource as ResourceModel;
use Imageshare\Models\ResourceFile as ResourceFileModel;
use Imageshare\Models\ResourceCollection as ResourceCollectionModel;

class Search {

    public function __construct() {
        add_filter('wpfts_index_post', [$this, 'index_post'], 3, 2);
    }

    public function index_post($index, $post) {
        Logger::log("Indexing post {$post->ID}");

        switch ($post->post_type) {
            case (ResourceModel::type):
                $type = ResourceModel::type;
                $resource = ResourceModel::from_post($post);
                if ($resource->is_importing) {
                    Logger::log("Post {$post->ID} is being imported, skipping");
                    return $index;
                }

                $index['post_title'] = $resource->title;
                $index['post_content'] = '';
                $index[$type . '_data'] = implode(' ', array_unique($resource->get_index_data()));

                // subject and keyword-specific relevance clusters
                $index[$type . '_subject'] = implode(' ', $resource->get_index_data('subject'));
                $index[$type . '_type'] = implode(' ', $resource->get_index_data('type'));
                $index[$type . '_accommodation'] = implode(' ', $resource->get_index_data('accommodation'));

                break;

            case (ResourceFileModel::type):
                $resource_file = ResourceFileModel::from_post($post);
                if ($resource_file->is_importing) {
                    Logger::log("Post {$post->ID} is being imported, skipping");
                    return $index;
                }

                $index['post_content'] = '';
                $index[ResourceFileModel::type . '_data'] = implode(' ', array_unique($resource_file->get_index_data()));

                break;

             case (ResourceCollectionModel::type):
                $resource_collection = ResourceCollectionModel::from_post($post);
                $type = ResourceCollectionModel::type;
                $index['post_content'] = '';
                $index[$type . '_data'] = implode(' ', array_unique($resource_collection->get_index_data()));

                // subject and keyword-specific relevance clusters
                $index[$type . '_subject'] = implode(' ', $resource_collection->get_index_data('subject'));
                $index[$type . '_type'] = implode(' ', $resource_collection->get_index_data('type'));
                $index[$type . '_accommodation'] = implode(' ', $resource_collection->get_index_data('accommodation'));

                break;

            default:
                $index['post_title'] = $post->post_title;
                $index['post_content'] = strip_tags($post->post_content);
                break;
        }

        Logger::log("Post {$post->ID} ({$post->post_type}) added to index");

        return $index;
    }

    public static function get_available_terms() {
        return [
            'subjects'       => ResourceModel::available_subjects($hide_empty = true),
            'accommodations' => ResourceFileModel::available_accessibility_accommodations($hide_empty = true),
            'types'          => ResourceFileModel::available_types($hide_empty = true)
        ];
    }

    public function get_terms($args) {
        $results = [
            'subject' => [],
            'type' => [],
            'acc' => []
        ];

        foreach (['subject', 'type', 'acc'] as $filter) {
            if (self::is_nonempty_array($args[$filter])) {
                foreach ($args[$filter] as $term_id) {
                    $term = get_term($term_id);
                    if (!is_wp_error($term) && $term !== null) {
                        array_push($results[$filter], $term);
                    }
                }
            }
        }

        return $results;
    }

    public function get_paging($page = 1, $size = 20, $amount = 0, $total = 0) {
        $valid_size_steps = [5, 20, 50, 100];
        $default_size = 20;
        $default_page = 1;

        if (!is_int($page)) {
            $page = $default_page;
        }

        if (!in_array($size, $valid_size_steps)) {
            $size = $default_size;
        }

        $start = ($size * ($page -1) + 1) ?? 1;
        $stop = ($start + $size - 1) < $amount ?: $amount;

        return [
            'size'  => $size,
            'page'  => $page,
            'start' => $start,
            'stop'  => $stop,
            'total' => $total
        ];
    }

    public function query($args) {
        $query = trim($args['query']);
        $terms = self::get_terms($args);

        [$resources, $total_resources] = self::post_type_query(
            ResourceModel::type, $query, $terms, self::get_paging($args['rp'], $args['rs'])
        );

        [$collections, $total_collections] = self::post_type_query(
            ResourceCollectionModel::type, $query, $terms, self::get_paging($args['cp'], $args['cs'])
        );

        $filters = array_merge(['query' => $query], $terms);

        $results = [
            'resources'   => [],
            'collections' => [] 
        ];

        $resources = array_map(function ($p) {
            return ResourceModel::from_post($p);
        }, $resources);

        $collections = array_map(function ($p) {
            return ResourceCollectionModel::from_post($p);
        }, $collections);

        $results['total_count'] = count($resources) + count($collections);

        $results['resources']['paging'] = self::get_paging($args['rp'], $args['rs'], count($resources), $total_resources);
        $results['collections']['paging'] = self::get_paging($args['cp'], $args['cs'], count($collections), $total_collections);

        $results['resources']['posts'] = $resources;
        $results['collections']['posts'] = $collections;

        $results['has_filters'] =
            count($filters['subject']) ||
            count($filters['type'])    ||
            count($filters['acc'])
        ;

        $results['search_filters'] = $filters;

        return $results;
    }

    public static function is_nonempty_array($var) {
        return is_array($var) && count($var) > 0;
    }

    public function post_type_query($type, $query, $terms, $paging) {
        if (strlen($query)) {
            $cluster_weights = [
                'post_title' => 0.7,
                ($type . '_data') => 0.9
            ];
            $query = [$query];
        } else {
            $cluster_weights = [];
            $query = [];
        }

        foreach (['subject', 'type', 'acc'] as $filter) {
            if (count($terms[$filter])) {
                $cluster_weights[$type . '_' . $filter] = 0.9;
            }

            foreach ($terms[$filter] as $term) {
                array_push($query, Model::as_search_term($filter, $term->name));
            }
        }

        $results = [];

        $wpq = new \WP_Query([
            'post_type' => $type,
            'post_status' => 'publish',
            'fields' => '*',
            'wpfts_disable' => 0,
            'wpfts_nocache' => 1,
            'cluster_weights' => $cluster_weights,
            's' => implode(' ', $query),
            'posts_per_page' => $paging['size'],
            'paged' => $paging['page']
        ]);

        while ($wpq->have_posts()) {
            $wpq->the_post();
            $post = $wpq->post;
            array_push($results, $post);
        }

        return [$results, $wpq->found_posts];
    }

    public function get_published_resource_count() {
        return wp_count_posts(ResourceModel::type)->publish;
    }
}
