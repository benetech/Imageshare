<?php

namespace Imageshare\Models;

use Imageshare\Logger;
use Imageshare\Models\Model;
use Imageshare\Models\Resource as ResourceModel;

class ResourceFile {

    const type = 'btis_resource_file';
    const default_license = 'GNU-GPL';

    public static function available_accessibility_accommodations($hide_empty = false) {
        return Model::get_hierarchical_terms('a11y_accs', $hide_empty);
    }

    public static function available_types($hide_empty = false) {
        return Model::get_terms('file_types', $hide_empty);
    }

    public static function create($args) {
        $is_update = false;

        $license = strlen($args['license'])
            ? $args['license']
            : self::default_license
        ;

        $format  = Model::get_taxonomy_term_id('file_formats', $args['format']);
        $type    = Model::get_taxonomy_term_id('file_types', $args['type']);
        $license = Model::get_taxonomy_term_id('licenses', $license);
        $accommodations = self::accommodations_to_term_ids($args['accommodations']);
        $languages = self::language_codes_to_term_ids($args['languages']);

        $length = strlen($args['length_minutes'])
            ? $args['length_minutes']
            : '0'
        ;

        $existing = get_posts([
            'post_type' => self::type,
            'post_status' => ['publish', 'draft'],
            'meta_key' => 'uri',
            'meta_value' => $args['uri'],
            'meta_compare' => '==='
        ]);

        if (count($existing)) {
            Logger::log(sprintf(__('A published or draft ResourceFile with URI "%s" already exists, updating', 'imageshare'), $args['uri']));
            $post_id = $existing[0]->ID;
            $is_update = true;
        } else {
            $post_data = [
                'post_type' => self::type,
                'post_title' => $args['title'],
                'comment_status' => 'closed',
                'post_category' => [],
                'tags_input' => [],
                'meta_input' => [
                    'importing' => true
                ]
            ];

            $post_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($post_id)) {
            // the original WP_Error for inserting a post is empty for some reason
            throw new \Exception(sprintf(__('Unable to create resource file "%s"', 'imageshare'), $args['title']));
        }

        if (!$is_update) {
            Logger::log(sprintf('New resource file created, %s (%s)', $post_id, $args['uri']));
        }

        update_field('description', $args['description'], $post_id);
        update_field('author', $args['author'], $post_id);
        update_field('uri', $args['uri'], $post_id);
        update_field('length_minutes', $length, $post_id);
        update_field('type', $type, $post_id);
        update_field('format', $format, $post_id);
        update_field('license', $license, $post_id);
        update_field('accommodations', $accommodations, $post_id);
        update_field('languages', $languages, $post_id);
        update_field('downloadable', $args['downloadable'], $post_id);

        if (!$is_update) {
            Model::finish_importing($post_id);
        }

        return [$post_id, $is_update];
    }

    public static function on_save_post($post_id, $post, $update) {
        ResourceModel::reindex_resources_containing_resource_file($post_id);
        return $post;
    }

    public static function accommodations_to_term_ids($accommodations) {
        return array_map(function ($acc) {
            return Model::get_taxonomy_term_id('a11y_accs', $acc);
        }, $accommodations);
    }

    public static function language_codes_to_term_ids($language_codes) {
        $term_ids = array_map(function ($lc) {
            $term = get_term_by('name', $lc, 'languages');

            if ($term) {
               return $term->term_id;
            }

            // not found. A language alias?

            //look up by language meta code
            $terms = get_terms([
                'number' => 1,
                'hide_empty' => false,
                'meta_query' => [[
                   'key' => 'term_alias',
                   'value' => $lc,
                   'compare' => '='
                ]],
                'taxonomy'  => 'languages',
            ]);

            if (count($terms)) {
                return $terms[0]->term_id;
            }

            throw new \Exception(sprintf(__('Term %s was not found in taxonomy %s', 'imageshare'), $lc, 'languages'));
        }, $language_codes);

        return $term_ids;
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
        $columns['description'] = self::i18n('Description');
        $columns['file_author'] = self::i18n('Author');
        $columns['uri'] = self::i18n('URI');
        $columns['type'] = self::i18n('Type');
        $columns['format'] = self::i18n('Format');
        $columns['languages'] = self::i18n('Language(s)');
        $columns['accommodations'] = self::i18n('Accommodation(s)');
        $columns['license'] = self::i18n('License');
        $columns['length'] = self::i18n('Length');
        $columns['downloadable'] = self::i18n('Downloadable');

        return $columns;
    }

    public static function manage_custom_column(string $column_name, int $post_id) {
        $post = new ResourceFile($post_id);

        switch ($column_name) {
            case 'description':
                echo $post->description;
                break;

            case 'file_author':
                echo $post->author;
                break;

            case 'uri':
                echo $post->uri;
                break;

            case 'type':
                echo $post->type;
                break;

            case 'format':
                echo $post->format;
                break;

            case 'languages':
                echo join(', ', $post->languages);
                break;

            case 'accommodations':
                echo join(', ', $post->accommodations);
                break;

            case 'license':
                echo $post->license;
                break;

            case 'length':
                echo $post->length_formatted_string();
                break;

            case 'downloadable':
                echo $post->downloadable ? self::i18n('Yes') : self::i18n('No');
                break;
        }
    }

    private function get_post($post_id) {
        $this->post = get_post($post_id);
        return $this->load_custom_attributes();
    }

    public static function from_post(\WP_Post $post) {
        $wrapped = new ResourceFile();
        $wrapped->post = $post;
        $wrapped->is_importing = false;

        if (Model::is_importing($post->ID)) {
            $wrapped->is_importing = true;
        } else {
            $wrapped->load_custom_attributes();
        }

        return $wrapped;
    }

    public function reindex() {
        wpfts_post_reindex($this->id);
        ResourceModel::reindex_resources_containing_resource_file($this->id);
    }

    public function load_custom_attributes() {
        if (!empty($this->post)) {
            $this->id = $this->post->ID;
            $this->post_id = $this->post->ID;
            $this->title = $this->post->post_title;
            $this->permalink = get_permalink($this->post->ID);

            $this->description = get_post_meta($this->post_id, 'description', true);
            $this->author = get_post_meta($this->post_id, 'author', true);
            $this->uri = get_post_meta($this->post_id, 'uri', true);
            $this->downloadable = get_post_meta($this->post_id, 'downloadable', true);
            $this->length_minutes = get_post_meta($this->post_id, 'length_minutes', true);
            $this->length = $this->get_length();
            $this->license = Model::get_meta_term_name($this->post_id, 'license', 'licenses');

            $this->type = Model::get_meta_term_name($this->post_id, 'type', 'file_types');
            $this->format = Model::get_meta_term_name($this->post_id, 'format', 'file_formats');

            $this->languages = $this->get_languages();

            $this->accommodations = array_map(function ($a) {
                return join(' - ', $a);
            }, $this->get_accommodations());

            return $this->id;
        }
        
        return null;
    }

    public function length_formatted_string() {
        $length = $this->get_length();
        if (($length['hours'] + $length['minutes']) === 0) {
            echo "0";
        } else if ($length['hours'] === 0) {
            echo sprintf(_n('%d minute', '%d minutes', $length['minutes'], 'imageshare'), $length['minutes']); 
        } else {
            echo sprintf(_n('%d hour', '%d hours', $length['hours'], 'imageshare'), $length['hours']);
            if ($length['minutes']) {
                echo " ";
                echo sprintf(_n('%d minute', '%d minutes', $length['minutes'], 'imageshare'), $length['minutes']); 
            }
        }
    }

    public function get_type_term_id() {
        return get_post_meta($this->post_id, 'type', true);
    }

    private function get_length() {
        $length = intval($this->length_minutes);

        if ($length < 60) {
            return ['hours' => 0, 'minutes' => $length];
        }

        $hours = floor($length / 60);
        $minutes = $length % 60;

        return [ 'hours' => $hours, 'minutes' => $minutes ];
    }

    public static function get_type_name_by_term_id($term_id) {
        return Model::get_taxonomy_term_name($term_id, 'file_types');
    }

    public static function get_accommodation_name_by_term_id($term_id) {
        return Model::get_taxonomy_term_name($term_id, 'accommodations');
    }

    public function get_index_data() {
        return Model::flatten([$this->description, $this->license, $this->type, $this->format, $this->languages, $this->accommodations]);
    }

    private function get_languages() {
        $languages = get_post_meta($this->post_id, 'languages', true);
        return array_map(function ($term_id) {
            return get_term($term_id, 'languages')->name;
        }, $languages);
    }

    public function get_accommodations() {
        $accommodations = get_post_meta($this->post_id, 'accommodations', true);
        return array_map(function ($term_id) {
            $term = get_term($term_id, 'a11y_accs');

            if ($parent_id = $term->parent) {
                $parent_term = get_term($parent_id);
                return [$term->name, $parent_term->name];
            }

            return [$term->name];
        }, $accommodations);
    }

}
