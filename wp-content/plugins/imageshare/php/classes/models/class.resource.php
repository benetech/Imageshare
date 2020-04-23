<?php

namespace Imageshare\Models;

require_once imageshare_php_file('classes/class.logger.php');

use Imageshare\Logger;

class Resource {

    const type = 'btis_resource';

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
            'label' => self::i18n('Resources'),
            'labels' => array(
                'singular_name' => self::i18n('Resource')
            ),
            'description' => self::i18n('A collection of one or more representations of a subject.'),
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
        $columns['contributor'] = self::i18n('Contributor');
        $columns['source'] = self::i18n('Source');
        $columns['subjects'] = self::i18n('Subject(s)');
        $columns['license'] = self::i18n('License');
        $columns['files'] = self::i18n('File(s)');

        return $columns;
    }

    public static function manage_custom_column(string $column_name, int $post_id) {
        $post = new Resource($post_id);

        switch ($column_name) {
            case 'description':
                echo $post->description;
                break;

            case 'contributor':
                echo $post->contributor;
                break;

            case 'source':
                echo $post->source;
                break;

            case 'subjects':
                echo join(', ', $post->subjects);
                break;

            case 'license':
                echo $post->license;
                break;

            case 'files':
                echo count($post->file_ids);
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
            $this->title = $this->post->post_title;

            $this->_metadata = get_metadata('post', $this->post_id);

            // post metadata
            $this->description = get_post_meta($this->post_id, 'description', true);
            $this->contributor = get_post_meta($this->post_id, 'contributor', true);
            $this->source      = get_post_meta($this->post_id, 'source', true);

            $this->license     = $this->get_license();
            $this->subjects    = $this->get_subjects();

            $this->file_ids = get_post_meta($this->post_id, 'files', false);

            //$this->files       = $this->get_files();

            return $this->id;
        }

        return null;
    }

    private function get_license() {
        $term_id = get_post_meta($this->post_id, 'license', true);
        $term = get_term($term_id, 'licenses');
        return $term->name;
    }

    private function get_subjects() {
        $term_ids = get_post_meta($this->post_id, 'subjects', true);
        return array_map(function($term_id) {
            $term = get_term($term_id, 'subjects');
            return $term->name;
        }, $term_ids);
    }

    private function get_files() {
        return [];
    }


}
