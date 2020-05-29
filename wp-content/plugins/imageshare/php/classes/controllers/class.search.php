<?php

namespace Imageshare\Controllers;

use Imageshare\Logger;
use Imageshare\Models\Resource as ResourceModel;
use Imageshare\Models\ResourceFile as ResourceFileModel;

class Search {

    public function __construct() {
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
            'post_type'     => [ResourceModel::type, ResourceFileModel::type],
            'post_status'   => 'publish',
            's'             => $args['query'],
            'tax_query'     => []
        ];

        if ($args['subject'] !== null) {
            array_push($query_args, [
                'taxonomy' => 'subjects',
                'terms' => $args['subject'],
            ]);
        }

        if ($args['type'] !== null) {
            array_push($query_args, [
                'taxonomy' => 'file_types',
                'terms' => $args['type'],
            ]);
        }

        if ($args['accommodation'] !== null) {
            array_push($query_args, [
                'taxonomy' => 'a11y_accs',
                'terms' => $args['accommodation'],
            ]);
        }

        $matching = get_posts($query_args);

        Logger::log($matching);

        return $matching;
    }
}
