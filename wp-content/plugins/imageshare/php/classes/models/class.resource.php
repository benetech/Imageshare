<?php

namespace Imageshare\Models;

require_once imageshare_php_file('classes/models/class.model.php');

use Imageshare\Views\View;
use Imageshare\Logger;
use Swaggest\JsonSchema\Schema;
use Imageshare\Models\Model;
use Imageshare\Models\ResourceFile;

class Resource {

    const type = 'btis_resource';

    public static function available_subjects() {
        return Model::get_hierarchical_terms('subjects');
    }

    public static function create($args) {
        $is_update = false;
        $subject = Model::get_taxonomy_term_id('subjects', $args['subject']);

        if ($post_id = post_exists($args['title'], '', '', self::type)) {
            Logger::log(sprintf(__('A Resource with unique title "%s" already exists, updating', 'imageshare'), $args['title']));
            $is_update = true;
        } else {
            $post_data = [
                'post_type' => self::type,
                'post_title' => $args['title'],
                'comment_status' => 'closed',
                'post_category' => []
            ];

            $post_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($post_id)) {
            // the original WP_Error for inserting a post is empty for some reason
            throw new \Exception(sprintf(__('Unable to create resource "%s"', 'imageshare'), $args['title']));
        }

        if (!$is_update) {
            Logger::log(sprintf('New resource created, %s (%s)', $post_id, $args['title']));
        }

        wp_set_post_terms($post_id, $args['tags']);

        update_field('thumbnail_src', $args['thumbnail_src'], $post_id);
        update_field('thumbnail_alt', $args['thumbnail_alt'], $post_id);
        update_field('description', $args['description'], $post_id);
        update_field('source', $args['source'], $post_id);
        update_field('subject', $subject, $post_id);

        if ($args['downloadable'] && strlen($args['download_uri'])) {
            update_field('is_downloadable', 1, $post_id);
            update_field('download_uri', $args['download_uri'], $post_id);
        } else {
            update_field('is_downloadable', 0, $post_id);
            update_field('download_uri', null, $post_id);
        }

        if (!$is_update) {
            // don't strip existing file associations when updating an existing resource
            update_field('files', [], $post_id);

            Model::mark_created($post_id);
        }

        return [$post_id, $is_update];
    }

    public static function associate_resource_file($resource_id, $resource_file_id) {
        $files = get_post_meta($resource_id, 'files', true);

        if (in_array($resource_file_id, $files)) {
            return;
        }

        array_push($files, $resource_file_id);
        update_field('files', $files, $resource_id);
    }

    public function __construct($post_id = null) {
        if (!empty($post_id)) {
            $this->get_post($post_id);
        }
    }

    public static function validate($records) {
        $template = View::load('import.schema.json.twig');
        $taxonomies = json_decode(file_get_contents(imageshare_asset_file('taxonomies.json')));
        $terms = [];
        foreach ($taxonomies as $taxonomy => $definition) {
            $terms[$taxonomy] = [];

            // meta values are meta-lookup keys for a particular term
            // such as "en" for "English"
            if (property_exists($definition, 'terms_meta')) {
                foreach ($definition->terms_meta as $term => $meta) {
                    foreach ($meta as $key => $value) {
                        array_push($terms[$taxonomy], $value);
                    }
                }
            }

            if (is_array($definition->terms)) {
                $terms[$taxonomy] = array_merge($terms[$taxonomy], $definition->terms);
                if (property_exists($definition, 'allow_empty') && $definition->allow_empty) {
                    array_push($terms[$taxonomy], "");
                }
                continue;
            }

            $leafs = [];
            foreach ($definition->terms as $key => $value) {
                // no children
                if ($value === null) {
                    array_push($leafs, $key);
                    continue;
                }

                $leafs = array_merge($leafs, $value);
            }

            $terms[$taxonomy] = array_merge($terms[$taxonomy], $leafs);

            if (property_exists ($definition, 'allow_empty') && $definition->allow_empty) {
                array_push($terms[$taxonomy], "");
            }
        };

        $schema_json = $template->render(['terms' => $terms]);
        $schema = Schema::import(json_decode($schema_json));
        $schema->in($records);
    }

    public static function i18n(string $text) {
        return __($text, 'imageshare');
    }

    public static function typedef() {
        return array(
            'label' => self::i18n('Resources'),
            'labels' => ['singular_name' => self::i18n('Resource')],
            'description' => self::i18n('A collection of one or more representations of a subject.'),
            'capability_type' => 'post',
            'supports' => ['title'],
            'taxonomies' => ['post_tag'],
            'public' => true
        );
    }

    public static function manage_columns(array $columns) {
        $columns['thumbnail'] = self::i18n('Thumbnail');
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
            case 'thumbnail':
                echo "<img src=\"{$post->thumbnail_src}\" alt=\"{$post->thumbnail_alt}\"/>";
                break;

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

    public function acf_update_value($field, $value) {
        switch($field['name']) {
            case 'files':
            // also store resource file ids as flat database records for meta search
            // use $this->post->ID as the resource might not be finished creating
                delete_post_meta($this->post->ID, 'resource_file_id');
                foreach ($value as $file_id) {
                    add_post_meta($this->post->ID, 'resource_file_id', $file_id);
                }
            break;
        }

        return $value;
    }

    private function get_post($post_id) {
        $this->post = get_post($post_id);
        return $this->load_custom_attributes();
    }

    public static function from_post(\WP_Post $post) {
        $wrapped = new Resource();
        $wrapped->post = $post;
        $wrapped->is_created = false;

        if (Model::is_created($post->ID)) {
            $wrapped->is_created = true;
            $wrapped->load_custom_attributes();
        }

        return $wrapped;
    }

    public function load_custom_attributes() {
        if (!empty($this->post)) {
            $this->id = $this->post->ID;
            $this->post_id = $this->post->ID;
            $this->title = $this->post->post_title;
            $this->permalink = get_permalink($this->post->ID);

            // post metadata
            $this->thumbnail_src = get_post_meta($this->post_id, 'thumbnail_src', true);
            $this->thumbnail_alt = get_post_meta($this->post_id, 'thumbnail_alt', true);

            $this->description = get_post_meta($this->post_id, 'description', true);
            $this->source      = get_post_meta($this->post_id, 'source', true);
            $this->file_ids    = get_post_meta($this->post_id, 'files', true);

            $this->subject     = Model::get_meta_term_name($this->post_id, 'subject', 'subjects', true);

            return $this->id;
        }

        return null;
    }

    public function collections() {
        if (isset($this->_collections)) {
            return $this->_collections;
        }

        return $this->_collections = ResourceCollection::containing($this->post_id);
    }

    public function files() {
        if (isset($this->_files)) {
            return $this->_files;
        }

        return $this->_files = array_reduce($this->file_ids, function ($carry, $file_id) {
            $resource_file = new ResourceFile($file_id);
            array_push($carry, $resource_file);
            return $carry;
        }, []);
    }

    public function get_index_data() {
        $term_names = array_map(function ($term) {
            return $term->name;
        }, wp_get_post_terms($this->post_id));

        return Model::flatten([$this->thumbnail_alt, $this->source, $this->description, $this->subject, $term_names]);
    }
}
