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
            ),
            'public' => true,
            'show_ui' => true
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
            case 'description':
                echo $post->description;
                break;

            case 'size':
                if (empty($post->resource_ids)) {
                    echo "0";
                } else {
                    echo count($post->resource_ids);
                }
                break;

            case 'contributor':
                echo $post->contributor;
                break;
        }
    }

    public static function from_post(\WP_Post $post) {
        $wrapped = new ResourceCollection();
        $wrapped->post = $post;
        return $wrapped->load_custom_attributes();
    }

    private function get_post($post_id) {
        $this->post = get_post($post_id);
        return $this->load_custom_attributes();
    }

    public function load_custom_attributes() {
        if (!empty($this->post)) {
            $this->id = $this->post->ID;
            $this->post_id = $this->post->ID;
            $this->title = $this->post->post_title;

            $this->_metadata = get_metadata('post', $this->post_id);

            // post metadata
            $this->description = get_post_meta($this->post_id, 'description', true);
            $this->contributor = get_post_meta($this->post_id, 'contributor', true);
            $this->resource_ids = get_post_meta($this->post_id, 'resources', true);

            return $this->id;
        }
        
        return null;
    }

}
