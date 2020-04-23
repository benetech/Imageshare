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
        );
    }

    public static function manage_columns(array $columns) {
        $columns['description'] = self::i18n('Description');
        $columns['type'] = self::i18n('Type');
        $columns['file_type'] = self::i18n('File Type');
        $columns['grades'] = self::i18n('Grade(s)');
        $columns['language'] = self::i18n('Language');
        $columns['accommodations'] = self::i18n('Accommodation(s)');

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
                echo join(', ', $post->grades);
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
        return $this->load_custom_attributes();
    }

    public function load_custom_attributes() {
        if (!empty($this->post)) {
            $this->id = $this->post->ID;
            $this->post_id = $this->post->ID;

            $this->description = get_post_meta($this->post_id, 'description', true);
            $this->resource_type = $this->get_meta_term_name('resource_type', 'resource_types');
            $this->file_type = $this->get_meta_term_name('file_type', 'file_types');
            $this->language = $this->get_meta_term_name('language', 'languages');

            $this->grades = $this->get_grades();
            $this->accommodations = $this->get_accommodations();

            return $this->id;
        }
        
        return null;
    }

    private function get_grades() {
        $term_ids = get_post_meta($this->post_id, 'grades', true);
        return array_map(function($term_id) {
            $term = get_term($term_id, 'grade_ranges');
            return $term->name;
        }, $term_ids);
    }

    private function get_accommodations() {
    }

    private function get_meta_term_name(string $meta_key, string $taxonomy) {
        $term_id = get_post_meta($this->post_id, $meta_key, true);
        $term = get_term($term_id, $taxonomy);
        return $term->name;
    }

}
