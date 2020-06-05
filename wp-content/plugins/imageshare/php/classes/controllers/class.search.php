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
            'types'          => ResourceFileModel::available_types($hide_empty = false)
        ];
    }

    public function add_weight(&$weights, $post_type, $term) {
        $term = get_term($args['type']);
        if (!is_wp_error($term)) {
            $weights[$post_type .'_'. $type] = 0.9;
            array_push($query, Model::as_search_term('type', $term->name));
        }
    }

    public function query($args) {
        $resources   = self::post_type_query(ResourceModel::type, $args);
        $collections = self::post_type_query(ResourceCollectionModel::type, $args);

        if ($args['previous'] !== null && $args['narrow']) {
            $resources = self::post_type_query(ResourceModel::type,
                array_merge($args['previous'], ['id_in' => wp_list_pluck($resources, 'ID')])
            );
            $collections = self::post_type_query(ResourceCollectionModel::type,
                array_merge($args['previous'], ['id_in' => wp_list_pluck($collections, 'ID')])
            );
        }

        $results = [
            'resources' => array_map(function ($p) {
                return ResourceModel::from_post($p);
            }, $resources),

            'collections' => array_map(function ($p) {
                return ResourceCollectionModel::from_post($p);
            }, $collections)
        ];

        $results['count'] = count($results['resources']) + count($results['collections']);

        return $results;
    }

    public function post_type_query($type, $args) {
        $query = [$args['query']];

        if (strlen(trim($args['query']))) {
            $cluster_weights = [
                'post_title' => 0.7,
                ($type . '_data') => 0.9
            ];
        } else {
            $cluster_weights = [];
        }

        if ($args['subject'] !== null) {
            $term = get_term($args['subject']);
            if (!is_wp_error($term)) {
                $cluster_weights[$type . '_subject'] = 0.9;
                array_push($query, Model::as_search_term('subject', $term->name));
            }
        }

        if ($args['type'] !== null) {
            $term = get_term($args['type']);
            if (!is_wp_error($term)) {
                $cluster_weights[$type . '_type'] = 0.9;
                array_push($query, Model::as_search_term('type', $term->name));
            }
        }

        if ($args['accommodation'] !== null) {
            $term = get_term($args['accommodation']);
            if (!is_wp_error($term)) {
                $cluster_weights[$type . '_accommodation'] = 0.9;
                array_push($query, Model::as_search_term('accommodation', $term->name));
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
            'post__in' => $args['id_in'] ?? null
        ]);

        while ($wpq->have_posts()) {
            $wpq->the_post();
            $post = $wpq->post;
            array_push($results, $post);
        }

        return $results;
    }

    public function get_published_resource_count() {
        return wp_count_posts(ResourceModel::type)->publish;
    }
}
