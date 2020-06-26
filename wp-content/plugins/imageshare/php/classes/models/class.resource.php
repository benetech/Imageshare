<?php

namespace Imageshare\Models;

require_once imageshare_php_file('classes/models/class.model.php');

use Imageshare\Views\View;
use Imageshare\Logger;
use Swaggest\JsonSchema\Schema;
use Imageshare\Models\Model;
use Imageshare\Models\ResourceFile;
use Imageshare\Models\ResourceCollection;

class Resource {

    const type = 'btis_resource';

    public static function available_subjects($hide_empty = false) {
        return Model::get_hierarchical_terms('subjects', $hide_empty);
    }

    public static function create($args) {
        $is_update = false;
        $subject = Model::get_taxonomy_term_id('subjects', $args['subject']);

        $post_id = post_exists($args['title'], '', '', self::type);

        if ($post_id && in_array(get_post_status($post_id), ['publish', 'draft'])) {
            Logger::log(sprintf(__('A published or draft Resource with unique title "%s" already exists, updating', 'imageshare'), $args['title']));
            $is_update = true;
        } else {
            $post_data = [
                'post_type' => self::type,
                'post_title' => $args['title'],
                'comment_status' => 'closed',
                'post_category' => [],
                'meta_input' => [
                    'importing' => true
                ]
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

        if (strlen($args['download_uri'])) {
            update_field('download_uri', $args['download_uri'], $post_id);
        } else {
            update_field('download_uri', null, $post_id);
        }

        if (!$is_update) {
            // don't strip existing file associations when updating an existing resource
            update_field('files', [], $post_id);
            Model::finish_importing($post_id);
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
    
    public static function reindex_resources_containing_resource_file($resource_file_id) {
        $existing = get_posts([
            'post_type' => self::type,
            'post_status' => 'publish',
            'meta_key' => 'resource_file_id',
            'meta_value' => $resource_file_id,
            'meta_compare' => '==='
        ]);

        $collection_ids = [];

        foreach ($existing as $resource) {
            wpfts_post_reindex($resource->ID);
            $collections = ResourceCollection::containing($resource->ID);
            $collection_ids = array_merge($collection_ids, array_map(function ($r) {
                return $r->id;
            }, $collections));
        }

        foreach (array_unique($collection_ids) as $collection_id) {
            wpfts_post_reindex($collection_id);
        }
    }

    public function reindex() {
        wpfts_post_reindex($this->id);

        $collections = ResourceCollection::containing($resource->ID);
        $collection_ids = array_merge($collection_ids, array_map(function ($r) {
            return $r->id;
        }, $collections));

        foreach (array_unique($collection_ids) as $collection_id) {
            wpfts_post_reindex($collection_id);
        }
    }

    public function __construct($post_id = null) {
        if (!empty($post_id)) {
            $this->get_post($post_id);
        }
    }

    public static function get_schema() {
        $template = View::load('import.schema.json.twig');
        $taxonomies = json_decode(file_get_contents(imageshare_asset_file('taxonomies.json')));
        $terms = [];
        foreach ($taxonomies as $taxonomy => $definition) {
            $terms[$taxonomy] = [];

            if (get_option("taxonomy_{$taxonomy}_allow_empty", false)) {
                array_push($terms[$taxonomy], "");
            }

            foreach (get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]) as $term) {
                array_push($terms[$taxonomy], strtolower($term->name));
                if ($term_aliases = get_field('term_aliases', "{$taxonomy}_{$term->term_id}")) {
                    $aliases = array_map(function($a) {
                        return trim($a);
                    }, explode(',', $term_aliases));

                    foreach ($aliases as $alias) {
                        array_push($terms[$taxonomy], strtolower($alias));
                    }
                }
            }
        }

        return $template->render(['terms' => $terms]);
    }

    public static function validate($records) {
        $schema_json = self::get_schema();
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
                $fbs = Model::children_by_status($post->files());
                echo join(', ', array_map(function($status) use($fbs) {
                    return "{$fbs[$status]} {$status}";
                }, array_keys($fbs)));

                break;

            case 'tags':
                echo join(', ', $post->tags);
                break;
        }
    }

    public static function on_acf_relationship_result($post_id, $related_post, $field) {
        // this can only be a file
        $file = ResourceFile::from_post($related_post);
        return sprintf('%s (%s - %s)', $file->title, $file->type, $file->format);
    }

    public static function on_insert_post_data($post_id, $data) {
        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (!$post_id) {
            Logger::log('Post id 0 is auto_draft, skipping');
            return;
        }

        $resource = new Resource($post_id);

        Logger::log([$resource, $post_id]);

        $old_status = $resource->post->post_status;

        if ($old_status === 'publish') {
            Logger::log("Resource {$post_id} is already published, skipping filter");
            return;
        }

        $new_status = $data['post_status'];

        if ($new_status === 'publish') {
            Logger::log("Resource {$post_id} going from {$old_status} to {$new_status}");
            Model::force_publish_children($resource->files());
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
        $wrapped->is_importing = false;

        if (Model::is_importing($post->ID)) {
            $wrapped->is_importing = true;
        } else {
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
            $this->description   = get_post_meta($this->post_id, 'description', true);
            $this->source        = get_post_meta($this->post_id, 'source', true);
            $this->file_ids      = get_post_meta($this->post_id, 'files', true);
            $this->download_uri  = get_post_meta($this->post_id, 'download_uri', true);
            $this->subject       = Model::get_meta_term_name($this->post_id, 'subject', 'subjects', true);
            $this->tags          = $this->get_tags();

            return $this->id;
        }

        return null;
    }

    private function get_tags() {
        return array_map(function ($term) {
            return $term->name;
        }, wp_get_post_terms($this->post_id));
    }

    public static function get_subject_name_by_term_id($term_id) {
        return Model::get_taxonomy_term_name($term_id, 'subjects');
    }

    public function collections() {
        if (isset($this->_collections)) {
            return $this->_collections;
        }

        return $this->_collections = ResourceCollection::containing($this->post_id);
    }

    public function force_publish_files() {
        foreach ($this->files() as $file) {
            if ($file->post->post_status !== 'draft') {
                Logger::log("File {$file->id} status is {$file->post->post_status}, skipping");
                continue;
            }

            $file->post->post_status = 'publish';

            if (!$result = wp_update_post($file->post)) {
                Logger::log("Unable to force publish file {$file->id}");
                continue;
            }

            Logger::log("Force published file {$file->id}");
        }
    }

    public function published_files() {
        return array_filter($this->files(), function ($file) {
            return $file->post->post_status === 'publish';
        });
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

    public function get_constituting_file_types() {
        $term_ids = array_unique(array_map(function ($file) {
            return $file->get_type_term_id();
        }, $this->published_files()));

        if (!count($term_ids)) {
            return [];    
        }

        $terms = get_terms(['taxonomy' => 'file_types', 'include' => $term_ids]);

        return array_map(function ($term) {
            $url = get_field('thumbnail', 'category_' . $term->term_id);

            return [
                'term_id' => $term->term_id,
                'name' => $term->name,
                'thumbnail_url' => $url
            ];
        }, $terms);
    }

    public function get_index_data($specific = null) {
        if ($specific === 'subject') {
            $subject_term_id = get_post_meta($this->post_id, 'subject', true);

            $term = get_term($subject_term_id, 'subjects');

            $data = [Model::as_search_term('subject', $term->name)];

            if ($parent_id = $term->parent) {
                $parent_term = get_term($parent_id);
                array_push($data, Model::as_search_term('subject', $parent_term->name));
            }

            return $data;
        }

        if ($specific === 'type') {
            return array_unique(Model::flatten(array_map(function ($type) {
                return Model::as_search_term('type', $type);
            }, $this->get_resource_file_types())));
        }

        if ($specific === 'accommodation') {
            return array_unique(Model::flatten(array_map(function ($accommodation) {
                return Model::as_search_term('accommodation', $accommodation);
            }, Model::flatten($this->get_resource_file_accommodations()))));
        }

        $term_names = array_map(function ($term) {
            return $term->name;
        }, wp_get_post_terms($this->post_id));

        return Model::flatten([
            Model::as_search_term('subject', $this->subject),
            $this->thumbnail_alt,
            $this->source,
            $this->description,
            $this->subject,
            $term_names,

            array_map(function ($accommodation) {
                return Model::as_search_term('accommodation', $accommodation);
            }, Model::flatten($this->get_resource_file_accommodations())),

            array_map(function ($type) {
                return Model::as_search_term('type', $type);
            }, $this->get_resource_file_types())
        ]);
    }

    public function get_resource_file_types() {
        return array_map(function ($resource_file) {
            return $resource_file->type;
        }, $this->published_files());
    }

    public function get_resource_file_accommodations() {
        return array_map(function ($resource_file) {
            return $resource_file->get_accommodations();
        }, $this->published_files());
    }
}
