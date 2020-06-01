<?php

namespace Imageshare\Controllers;

use Imageshare\Logger;
use Imageshare\Models\Resource as ResourceModel;
use Imageshare\Models\ResourceFile as ResourceFileModel;

class Search {

    public function __construct() {
        add_filter('wpfts_index_post', [$this, 'index_post'], 3, 2);
    }

    public function index_post($index, $post) {
        Logger::log("Indexing post {$post->ID}");

        switch ($post->post_type) {
            case (ResourceModel::type):
                $resource = ResourceModel::from_post($post);
                if (!$resource->is_created) {
                    Logger::log("Post {$post->ID} is not ready, skipping");
                    return $index;
                }

                $index['post_title'] = $resource->title;
                $index['post_content'] = '';
                $index['resource_data'] = implode(' ', $resource->get_index_data());
                break;

            case (ResourceFileModel::type):
                $resource_file = ResourceFileModel::from_post($post);
                if (!$resource_file->is_created) {
                    Logger::log("Post {$post->ID} is not ready, skipping");
                    return $index;
                }

                $index['post_content'] = '';
                $index['resource_file_data'] = implode(' ', $resource_file->get_index_data());
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
            'subjects'       => ResourceModel::available_subjects(),
            'accommodations' => ResourceFileModel::available_accessibility_accommodations(),
            'types'          => ResourceFileModel::available_types()
        ];
    }

    public function get_resources_including_file($resource_file_id, $exclude_post_ids) {
        return get_posts([
            'numberposts'   => -1,
            'post_type'     => [ResourceModel::type],
            'post_status'   => 'publish',
            'meta_key'      => 'resource_file_id',
            'meta_value'    => $resource_file_id,
            'post__not_in'  => $exclude_post_ids
        ]);
    }

    public function query_terms_only($args) {
        $tax_query = ['relation' => 'AND'];

        if ($args['subject'] !== null) {
            $term = get_term($args['subject']);
            if (!is_wp_error($term)) {
                array_push($tax_query, [
                    'taxonomy' => 'subjects',
                    'field' => 'term_id',
                    'include_children' => true,
                    'terms' => $term->term_id
                ]);
            }
        }

        if ($args['type'] !== null) {
            $term = get_term($args['type']);
            if (!is_wp_error($term)) {
                array_push($tax_query, [
                    'taxonomy' => 'file_types',
                    'field' => 'term_id',
                    'include_children' => false,
                    'terms' => $term->term_id
                ]);
            }
        }

        if ($args['accommodation'] !== null) {
            $term = get_term($args['accommodation']);
            if (!is_wp_error($term)) {
                array_push($tax_query, [
                    'taxonomy' => 'a11y_accs',
                    'field' => 'term_id',
                    'include_children' => true,
                    'terms' => $term->term_id
                ]);
            }
        }

        $posts = get_posts([
            'numberposts'   => -1,
            'post_type'     => [ResourceModel::type, ResourceFileModel::type],
            'post_status'   => 'publish',
            'tax_query'     => $tax_query
        ]);

        // TODO sort all this out with a custom SQL query

        $post_ids = array_reduce($posts, function ($carry, $post) {
            if ($post->post_type === ResourceModel::type) {
                array_push($carry, $post->ID);
                return $carry;
            }

            return $carry;
        }, []);

        return array_reduce($posts, function ($carry, $post) use ($post_ids) {
            if ($post->post_type === ResourceModel::type) {
                array_push($carry, $post);
                return $carry;
            }

            $posts = self::get_resources_including_file($post->ID, array_merge(wp_list_pluck($carry, 'ID'), $post_ids));

            return array_merge($carry, $posts);
        }, []);
    }

    public function query($args) {
        if (!strlen(trim($args['query']))) {
            return self::query_terms_only($args);
        }

        $query_args = [
            'numberposts'   => -1,
            'post_type'     => [ResourceModel::type],
            'post_status'   => 'publish',
        ];

        $query = [$args['query']];

        if ($args['subject'] !== null) {
            $term = get_term($args['subject']);
            if (!is_wp_error($term)) {
                array_push($query, $term->name);
            }
        }

        if ($args['type'] !== null) {
            $term = get_term($args['type']);
            if (!is_wp_error($term)) {
                array_push($query, $term->name);
            }
        }

        if ($args['accommodation'] !== null) {
            $term = get_term($args['accommodation']);
            if (!is_wp_error($term)) {
                array_push($query, $term->name);
            }
        }

        $query_args['s'] = implode(' ', $query);

        $wpq = new \WP_Query([
            'fields' => '*',
            'wpfts_disable' => 0,
            'wpfts_nocache' => 1,
            's' => implode(' ', $query)
        ]);

        $results = [];

        while ($wpq->have_posts()) {
            $wpq->the_post();
            $post = $wpq->post;
            if ($post->post_type === ResourceModel::type) {
                array_push($results, ResourceModel::from_post($post));
            }
        }

        return $results;
    }
}
