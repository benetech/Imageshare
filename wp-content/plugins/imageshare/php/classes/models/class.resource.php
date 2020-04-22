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
            //'register_meta_box_cb' => array(Resource, 'meta_box_cb'),
        );
    }

    public static function manage_columns(array $columns) {
        $columns['description'] = __('Description', 'imageshare');
        $columns['contributor'] = __('Contributor', 'imageshare');
        $columns['source'] = __('Source', 'imageshare');
        $columns['subjects'] = __('Subject(s)', 'imageshare');
        $columns['license'] = __('License', 'imageshare');
        $columns['files'] = __('File(s)', 'imageshare');

        return $columns;
    }

    public static function manage_custom_column(string $column_name, int $post_id) {
        $post = new Resource($post_id);

        switch ($column_name) {
            case 'contributor':
                echo $post->contributor;
                break;

            case 'source':
                echo $post->source;
                break;

            case 'subjects':
                echo join(',', $post->subjects);
                break;

            case 'license':
                echo $post->license;
                break;

            case 'files':
                echo count($post->files);
                break;
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
            $this->source = $this->get_source();
            $this->subjects = $this->get_subjects();
            $this->license = $this->get_license();
            $this->files = $this->get_files();

            return $this->id;
        }
        
        return null;
    }

    private function get_contributor() {
        //TODO
    }

    private function get_source() {
        //TODO
    }

    private function get_subjects() {
        //TODO
        return array();
    }

    private function get_license() {
        //TODO
    }

    private function get_files() {
        //TODO
        return array();
    }
}
