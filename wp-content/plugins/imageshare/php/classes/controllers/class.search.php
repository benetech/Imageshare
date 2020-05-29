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
        switch ($post->post_type) {
            case (ResourceModel::type):
                $resource = ResourceModel::from_post($post);
                $index['post_title'] = $resource->title;
                $index['post_content'] = '';
                $index['resource_data'] = implode(' ', $resource->get_index_data());
                break;
            case (ResourceFileModel::type):
                $resource_file = ResourceFileModel::from_post($post);
                $index['post_content'] = '';
                $index['resource_file_data'] = implode(' ', $resource_file->get_index_data());
                break;
            default:
                $index['post_title'] = $post->post_title;
                $index['post_content'] = strip_tags($post->post_content);
                break;
        }

        return $index;
    }

    public static function get_available_terms() {
        return [
            'subjects'       => ResourceModel::available_subjects(),
            'accommodations' => ResourceFileModel::available_accessibility_accommodations(),
            'types'          => ResourceFileModel::available_types()
        ];
    }

    public function query($args) {
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
