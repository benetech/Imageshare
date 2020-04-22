<?php

namespace Imageshare\Models;

require_once imageshare_php_file('classes/class.logger.php');

use Imageshare\Logger;

class ResourceFile {

    const type = 'btis_resource_file';

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
            'label' => self::i18n('Resource Files'),
            'labels' => array(
                'singular_name' => self::i18n('Resource File')
            ),
            'description' => self::i18n('A file representing a particular resource.'),
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
        $columns['type'] = __('Type', 'imageshare');
        $columns['file_type'] = __('File Type', 'imageshare');
        $columns['grades'] = __('Grade(s)', 'imageshare');
        $columns['language'] = __('Language', 'imageshare');
        $columns['accommodations'] = __('Accommodation(s)', 'imageshare');

        return $columns;
    }

    public static function manage_custom_column(string $column_name, int $post_id) {
        $post = new ResourceFile($post_id);

        switch ($column_name) {
            case 'description':
                echo $post->description;
                break;

            case 'type':
                echo $post->resource_type;
                break;

            case 'file_type':
                echo $post->file_type;
                break;

            case 'grades':
                echo join(',', $post->grades);
                break;

            case 'language':
                echo $post->language;
                break;

            case 'accommodations':
                //TODO term - subterm
                break;
        }
    }

    private function get_post($post_id) {
        $this->post = get_post($post_id);

        if (!empty($this->post)) {
            $this->id = $this->post->ID;
            $this->post_id = $this->post->ID;

            // post metadata
            $this->resource_type = $this->get_resource_type();
            $this->file_type = $this->get_file_type();
            $this->preview_image = $this->get_preview_image();
            $this->grades = $this->get_grades();
            $this->accommodations = $this->get_accommodations();
            $this->language = $this->get_language();
            $this->file = $this->get_file();
            $this->description = $this->get_description();

            return $this->id;
        }
        
        return null;
    }

    private function get_description() {
        //TODO
    }

    private function get_resource_type() {
        //TODO
    }

    private function get_file_type() {
        //TODO
    }

    private function get_file() {
        //TODO
    }

    private function get_preview_image() {
        //TODO
        return array(
            'url' => "",
            'alt' => ""
        );
    }

    private function get_grades() {
        //TODO
        return array();
    }

    private function get_accommodations() {
        //TODO
        return array();
    }

    private function get_language() {
        //TODO
    }
}
