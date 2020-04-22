<?php

namespace Imageshare\Models;

require_once imageshare_php_file('classes/class.logger.php');

use Imageshare\Logger;

class ResourceCollection {

    const type = 'btis_collection';

    public function __construct($post_id = null) {
        if (!empty($post_id)) {
            $this->get_post($post_id);
        }
    }

    public static function i18n(string $text) {
        return __($text, 'imageshare');
    }

    public static function typedef() {
        return array(
            'label' => self::i18n('Collections'),
            'labels' => array(
                'singular_name' => self::i18n('Collection')
            ),
            'description' => self::i18n('A categorised collection of resources.'),
            'capability_type' => 'post',
            'supports' => array(
                'title',
                'thumbnail'
            ),
            'public' => true
            //'register_meta_box_cb' => array(Resource, 'meta_box_cb'),
        );
    }

    public static function manage_columns(array $columns) {
        $columns['description'] = __('Description', 'imageshare');
        $columns['contributor'] = __('Contributor', 'imageshare');
        $columns['size'] = __('Size', 'imageshare');
        return $columns;
    }

    public static function manage_custom_column(string $column_name, int $post_id) {
        $post = new ResourceCollection($post_id);

        switch ($column_name) {
            case 'size':
                echo count($post->resources);

            case 'contributor':
                echo $post->contributor;
        }
    }

    private function get_post($post_id) {
        $this->post = get_post($post_id);

        if (!empty($this->post)) {
            $this->id = $this->post->ID;
            $this->post_id = $this->post->ID;
            $this->title = $this->post->post_title;

            // post metadata
            $this->contributor = $this->get_contributor();
            $this->resources   = $this->get_resources();

            return $this->id;
        }
        
        return null;
    }

    private function get_contributor() {
        //TODO
        return false;
    }

    private function get_resources() {
        //TODO
        return [];
    }
}
