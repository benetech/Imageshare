<?php

namespace Imageshare\Models;

class Resource {

    const type = 'btis_resource';

    public static function create($args) {
        if (post_exists($args['title'], '', '', self::type)) {
            throw new \Exception(sprintf(__('A Resource with unique title "%s" already exists', 'imageshare'), $args['title']));
        }

        $post_data = [
            'post_type' => self::type,
            'post_title' => $args['title'],
            'comment_status' => 'closed',
            'post_category' => [],
            'meta_input' => [
                'description' => $args['description'],
                'source' => $args['source'],
                'subject' => $args['subject'],
                'files' => [],
            ]
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            // the original WP_Error for inserting a post is empty for some reason
            throw new \Exception(sprintf(__('Unable to create resource "%s"', 'imageshare'), $args['title']));
        }

        wp_set_post_terms($post_id, $args['tags']);

        // TODO create thumbnail attachment

        return $post_id;
    }

    public static function associate_resource_file($resource_id, $resource_file_id) {
        $files = get_post_meta($resource_id, 'files', true);
        array_push($files, $resource_file_id);
        update_post_meta($resource_id, 'files', $files);
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
        $columns['source'] = self::i18n('Source');
        $columns['subject'] = self::i18n('Subject');
        $columns['tags'] = self::i18n('Tags');
        $columns['files'] = self::i18n('File(s)');

        return $columns;
    }

    public static function manage_custom_column(string $column_name, int $post_id) {
        $post = new Resource($post_id);

        switch ($column_name) {
            case 'description':
                $description = strlen($post->description) > 125
                    ? substr($post->description, 0, 125) . "..."
                    : $post->description
                ;
                echo $description;
                break;

            case 'source':
                echo $post->source;
                break;

            case 'subject':
                echo $post->subject;
                break;

            case 'files':
                echo count($post->file_ids);
                break;

            case 'tags':
                $term_names = array_map(function ($term) {
                    return $term->name;
                }, wp_get_post_terms($post_id));

                echo join(', ', $term_names);
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

            // post metadata
            $this->description = get_post_meta($this->post_id, 'description', true);
            $this->source      = get_post_meta($this->post_id, 'source', true);
            $this->subject     = get_post_meta($this->post_id, 'subject', true); 
            $this->file_ids    = get_post_meta($this->post_id, 'files', true);

            return $this->id;
        }

        return null;
    }
}
