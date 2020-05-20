<?php

namespace Imageshare\Models;

require_once imageshare_php_file('classes/class.logger.php');

use Imageshare\Logger;

class ResourceFile {

    const type = 'btis_resource_file';

    public static function create($args) {
        $existing = get_posts([
            'post_type' => self::type,
            'meta_key' => 'uri',
            'meta_value' => $args['uri'],
            'meta_compare' => '==='            
        ]);

        if (count($existing)) {
            throw new \Exception(sprintf(__('A ResourceFile with URI "%s" already exists', 'imageshare'), $args['uri']));
        }

        $format  = self::get_taxonomy_term_id('file_formats', $args['format']);
        $type    = self::get_taxonomy_term_id('file_types', $args['type']);
        $license = self::get_taxonomy_term_id('licenses', $args['license']);
        $accommodations = self::map_accommodations($args['accommodations']);
        $language = self::map_language_code($args['language']);

        $post_data = [
            'post_type' => self::type,
            'post_title' => $args['title'],
            'comment_status' => 'closed',
            'post_category' => [],
            'tags_input' => [],
            'meta_input' => [
                'uri' => $args['uri'],
                'length_minutes' => $args['length_minutes'],
                'type' => $type,
                'format' => $format,
                'license' => $license,
                'accommodations' => $accommodations,
                'language' => $language
            ]
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            // the original WP_Error for inserting a post is empty for some reason
            throw new \Exception(sprintf(__('Unable to create resource file "%s"', 'imageshare'), $args['title']));
        }

        // TODO create thumbnail attachment

        return $post_id;
    }

    public static function map_accommodations($accommodations) {
        // TODO map to list of term IDs
        return [];
    }

    public static function map_language_code($language_code) {
        // TODO map to taxonomy id
        return $language_code;
    }

    public static function get_taxonomy_term_id($taxonomy, $term_name) {
        $term = get_term_by('name', $term_name, $taxonomy);

        if ($term === false) {
            throw new \Exception(sprintf(__('Term %s was not found in taxonomy %s', 'imageshare'), $term_name, $taxonomy));
        }

        return $term->term_id;
    }

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
        $columns['uri'] = self::i18n('URI');
        $columns['type'] = self::i18n('Type');
        $columns['format'] = self::i18n('Format');
        $columns['language'] = self::i18n('Language');
        $columns['accommodations'] = self::i18n('Accommodation(s)');
        $columns['license'] = self::i18n('License');
        $columns['length_minutes'] = self::i18n('Length (minutes)');

        return $columns;
    }

    public static function manage_custom_column(string $column_name, int $post_id) {
        $post = new ResourceFile($post_id);

        switch ($column_name) {
            case 'uri':
                echo $post->uri;
                break;

            case 'type':
                echo $post->type;
                break;

            case 'format':
                echo $post->format;
                break;

            case 'language':
                echo $post->language;
                break;

            case 'accommodations':
                //TODO term - subterm
                break;

            case 'license':
                echo $post->license;
                break;

            case 'length_minutes':
                echo $post->length_minutes;
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

            $this->uri = get_post_meta($this->post_id, 'uri', true);
            $this->length_minutes = get_post_meta($this->post_id, 'length_minutes', true);

            $this->type = $this->get_meta_term_name('type', 'file_types');
            $this->format = $this->get_meta_term_name('format', 'file_formats');

            //$this->language = $this->get_meta_term_name('language', 'languages');
            // TODO map language to ISO name
            $this->language = get_post_meta($this->post_id, 'language', true);

            $this->license = $this->get_meta_term_name('license', 'licenses');

            $this->accommodations = $this->get_accommodations();

            return $this->id;
        }
        
        return null;
    }

    private function get_accommodations() {
        //TODO
        // these will be multiple term IDs that can then be mapped
        return '';
    }

    private function get_meta_term_name(string $meta_key, string $taxonomy) {
        $term_id = get_post_meta($this->post_id, $meta_key, true);
        $term = get_term($term_id, $taxonomy);

        if ($parent_id = $term->parent) {
            $parent_term = get_term($parent_id);
            return join(' - ', [$parent_term->name, $term->name]);
        }

        return $term->name;
    }

}
