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
            'show_ui' => true,
            'has_archive' => 'collections'
        );
    }

    public static function get_featured(int $number) {
        $posts = get_posts([
            'post_type' => self::type,
            'numberposts' => $number,
            'post_status' => 'publish',
            'meta_key' => 'is_featured',
            'meta_value' => 1,
            'meta_compare' => '==='            

        ]);

        return array_map(function ($post) {
            return self::from_post($post);
        }, $posts);
    }

    public static function manage_columns(array $columns) {
        $columns['description'] = self::i18n('Description');
        $columns['contributor'] = self::i18n('Contributor');
        $columns['size'] = self::i18n('Size');
        $columns['featured'] = self::i18n('Featured');
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

            case 'featured':
                echo $post->is_featured ? self::i18n('Yes') : self::i18n('No');
                break;
        }
    }

    public static function containing($resource_id) {
        $posts = get_posts([
            'numberposts'   => -1,
            'post_type'     => [ResourceCollection::type],
            'post_status'   => 'publish',
            'meta_key'      => 'resource_id',
            'meta_value'    => $resource_id
        ]);

        return array_map(function ($post) {
            return ResourceCollection::from_post($post);
        }, $posts);
    }

    public static function from_post(\WP_Post $post) {
        $wrapped = new ResourceCollection();
        $wrapped->post = $post;
        $wrapped->load_custom_attributes();
        return $wrapped;
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
            $this->permalink = get_permalink($this->post->ID);

            $this->description  = get_post_meta($this->post_id, 'description', true);
            $this->contributor  = get_post_meta($this->post_id, 'contributor', true);
            $this->resource_ids = get_post_meta($this->post_id, 'resources', true);

            $this->is_featured = get_post_meta($this->post_id, 'is_featured', true) == 1;

            $thumbnail_id = get_post_meta($this->post_id, 'thumbnail', true);
            $this->thumbnail = wp_get_attachment_image_src($thumbnail_id)[0];

            return $this->id;
        }
        
        return null;
    }

    public function resources() {
        if (isset($this->_resources)) {
            return $this->_resources;
        }

        return $this->_resources = array_reduce($this->resource_ids, function ($carry, $resource_id) {
            $resource_file = new Resource($resource_id);
            array_push($carry, $resource_file);
            return $carry;
        }, []);

    }

    public function acf_update_value($field, $value) {
        switch($field['name']) {
            case 'resources':
            // also store resource ids as flat database records for meta search
            // use $this->post->ID as the resource might not be finished creating
                delete_post_meta($this->post->ID, 'resource_id');
                foreach ($value as $file_id) {
                    add_post_meta($this->post->ID, 'resource_id', $file_id);
                }
            break;
        }

        return $value;
    }


}
