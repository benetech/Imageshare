<?php

namespace Imageshare\Models;

require_once imageshare_php_file('classes/models/class.model.php');
require_once imageshare_php_file('classes/controllers/json_api/class.resources.php');

use Imageshare\Views\View;
use Imageshare\Logger;
use Swaggest\JsonSchema\Schema;
use Imageshare\Models\Model;
use Imageshare\Models\ResourceFileGroup;
use Imageshare\Models\ResourceCollection;

class Resource {

    const type = 'btis_resource';

    public static function available_subjects($hide_empty = false) {
        return array_map(function($terms) {
            return array_reverse($terms);
        }, Model::get_hierarchical_terms('subjects', $hide_empty));
    }

    public static function create($args) {
        $is_update = false;
        $subject = Model::get_taxonomy_term_id('subjects', $args['subject']);

        $existing = get_posts([
            'numberposts' => 1,
            'post_type' => self::type,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'meta_query' => [[
                'key' => 'unique_id',
                'value' => $args['unique_id'],
                'compare' => '='
            ]]
        ]);

        if (count($existing)) {
            Logger::log(sprintf(__('A Resource with unique id "%s" already exists, updating', 'imageshare'), $args['unique_id']));
            $post = $existing[0];
            $post_id = $post->ID;
            $is_update = true;

            if ($post->post_status === 'publish') {
                $post->post_status = 'pending';
                wp_update_post($post);
            }
        } else {
            $post_data = [
                'post_type' => self::type,
                'post_title' => $args['title'],
                'post_name' => sanitize_title_with_dashes(join('-', [$args['title'], $args['source']])),
                'comment_status' => 'closed',
                'post_category' => [],
                'meta_input' => [
                    'importing' => true,
                    'unique_id' => $args['unique_id']
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

        if (strlen($args['source_uri'])) {
            update_field('source_uri', $args['source_uri'], $post_id);
        } else {
            update_field('source_uri', null, $post_id);
        }

        if (strlen($args['download_uri'])) {
            update_field('download_uri', $args['download_uri'], $post_id);
        } else {
            update_field('download_uri', null, $post_id);
        }

        if (!$is_update) {
            Model::finish_importing($post_id);
        }

        return [$post_id, $is_update];
    }

    /**
     * FIXME deprecated
     */
    public static function xremove_resource_file($resource_file_id) {
        $resources = self::containing($resource_file_id);

        foreach ($resources as $resource) {
            $resource = Resource::from_post($resource);

            delete_post_meta($resource->post_id, 'resource_file_id', $resource_file_id);
            $other_resource_file_ids = array_filter($resource->file_ids, function ($id) use ($resource_file_id) {
                return $id != $resource_file_id;
            });

            update_field('files', $other_resource_file_ids, $resource->post_id);
            $resource->file_ids = $other_resource_file_ids;

            $resource->reindex();
        }
    }

    /*
     * FIXME deprecated
     */
    public static function xcontaining_file_group($resource_file_group_id, $ids_only = false) {
        return array_map(function ($post) {
            return self::from_post($post);
        }, get_posts([
            'post_type' => self::type,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'meta_key' => 'resource_file_group_id',
            'meta_value' => $resource_file_group_id,
            'meta_compare' => '==='
        ]));
    }

    /*
     * FIXME deprecated
     */
    public static function xcontaining_resource_file($resource_file_id) {
        $groups = ResourceFileGroup::containing_resource_file($resource_file_id, true);
        return array_reduce($groups, function ($carry, $group) {
            $resources = Resource::containing_file_group($group->post_id);
            return array_merge($carry, $resources);
        }, []);
    }

    /**
     * Utility function used by the settings page
     */
    public static function migrate_files_to_default_group($resource_id, $group_id) {
        $file_ids = get_post_meta($resource_id, 'files', true);

        if (!is_array($file_ids)) {
            $file_ids = [];
        }

        // remove file metadata from the resource
        update_post_meta($resource_id, 'files', []);

        // add it to the group
        update_post_meta($group_id, 'files', $file_ids);

        // delete flat id list of files associated with resource
        delete_post_meta($resource_id, 'resource_file_id');

        // reproduce flat list for the group
        foreach ($file_ids as $file_id) {
            add_post_meta($group_id, 'file_id', $file_id);
        }

        // reindex the resource and resourcefilegroup
        self::by_id($resource_id)->reindex();
        ResourceFileGroup::by_id($group_id)->reindex();
    }

    // FIXME this can go once we've switched over
    public static function set_default_file_group($resource_id, $file_group_id) {
        update_field('default_file_group', $file_group_id, $resource_id);
        add_post_meta($resource_id, 'resource_file_group_id', $file_group_id);
        self::update_resource_file_group_ids($resource_id);
    }

    public function has_default_file_group() {
        return isset($this->default_file_group_id) && intval($this->default_file_group_id) > 0;
    }

    public static function reindex_resources_containing_resource_file($resource_file_id) {
        Logger::log("Reindexing resources containing resource file {$resource_file_id}");
        $existing = self::containing_resource_file($resource_file_id);

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

        $collection_ids = [];

        $collections = ResourceCollection::containing($this->id);
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
        $columns['groups'] = self::i18n('Group(s)');
        $columns['download_uri'] = self::i18n('Download URI');
        $columns['source_uri'] = self::i18n('Source URI');

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

            case 'download_uri':
                echo '(' . (strlen($post->download_uri) ? self::i18n('Yes') : self::i18n('No')) . ')';
                break;

            case 'source_uri':
                echo '(' . (strlen($post->source_uri) ? self::i18n('Yes') : self::i18n('No')) . ')';
                break;

            case 'subject':
                echo $post->subject;
                break;

            case 'files':
                $fbs = Model::children_by_status($post->files());

                if (empty($fbs)) {
                    echo '0';
                    break;
                }

                echo join(', ', array_map(function($status) use($fbs) {
                    return "{$fbs[$status]} {$status}";
                }, array_keys($fbs)));

                break;

            case 'groups':
                echo count(ResourceFileGroup::with_parent_resource($post->id, true) ?? '0');
                break;

            case 'tags':
                echo join(', ', $post->tags);
                break;
        }
    }

    /*
     * FIXME this can go once we're not dealing with groups at this level anymore 
     */
    public static function on_acf_relationship_result($post_id, $related_post, $field) {
        // this can only be a published file group
        $file = ResourceFileGroup::from_post($related_post);
        return sprintf('%s', $file->title);
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

        $old_status = $resource->post->post_status;

        if ($old_status === 'publish') {
            Logger::log("Resource {$post_id} is already published, skipping filter");
            return;
        }

        $new_status = $data['post_status'];

        if ($new_status === 'publish') {
            Logger::log("Resource {$post_id} going from {$old_status} to {$new_status}");
            Model::force_publish_children($resource->groups());
        }
    }

    /*
     * FIXME this can go once we're not dealing with groups at this level anymore 
     */
    public static function update_resource_file_group_ids($post_id) {
        $group_ids = get_post_meta($post_id, 'groups', true) ?: [];
        $default = get_post_meta($post_id, 'default_file_group', true);
        $default = is_null($default) ? [] : [$default];

        $ids = array_unique(array_merge($group_ids, $default));

        delete_post_meta($post_id, 'resource_file_group_id');

        foreach ($ids as $file_group_id) {
            add_post_meta($post_id, 'resource_file_group_id', $file_group_id);
        }

        update_post_meta($post_id, 'groups', $ids);
    }

    /*
     * FIXME this can go once we're not dealing with groups at this level anymore 
     */
    public static function on_pre_acf_save_post($post_id) {
        // monkey patch the POST data to prevent race conditions
        // between group file ids being saved and the default id being saved
        // so we explicitly merge the two.

        $groups_field = get_field_object('groups', $post_id);
        $default_field = get_field_object('default_file_group', $post_id);

        $groups_field_key = $groups_field['key'];
        $default_field_key = $default_field['key'];

        $groups_value = $_POST['acf'][$groups_field_key] ?: [];
        $default_value = $_POST['acf'][$default_field_key] ?: null;

        $default_value = is_null($default_value) ? [] : [$default_value];

        $all_ids = array_unique(array_merge($groups_value, $default_value));

        $_POST['acf'][$groups_field_key] = $all_ids;
    }

    /*
     * FIXME this can go once we're not dealing with groups at this level anymore 
     */
    public function acf_update_value($field, $value) {
        switch($field['name']) {
            case 'groups':
                // also store resource file group ids as flat database records for meta search
                // use $this->post->ID as the resource might not be finished creating
                delete_post_meta($this->post->ID, 'resource_file_group_id');

                if (is_array($value)) {
                    foreach ($value as $file_group_id) {
                        add_post_meta($this->post->ID, 'resource_file_group_id', $file_group_id);
                    }
                };
                break;
        }

        return $value;
    }

    public static function by_id($id) {
        $post = get_post($id);

        if ($post !== null && $post->post_type === static::type) {
            return self::from_post($post);
        }

        return null;
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

            // TODO the ?: [] guard can go eventually
            //$this->group_ids     = get_post_meta($this->post_id, 'groups', true) ?: [];

            $this->download_uri  = get_post_meta($this->post_id, 'download_uri', true);
            $this->source_uri    = get_post_meta($this->post_id, 'source_uri', true);
            $this->subject       = Model::get_meta_term_name($this->post_id, 'subject', 'subjects', true);
            $this->tags          = $this->get_tags();

            $this->default_file_group_id = get_post_meta($this->post_id, 'default_file_group', true);

            $this->subject_term_id = get_post_meta($this->post_id, 'subject', true);

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

    public function ordered_published_groups() {
        $groups = $this->published_groups();

        $default = array_filter($groups, function ($group) {
            return $group->is_default_for_parent();
        });

        $rest = array_filter($groups, function ($group) {
            return !$group->is_default_for_parent();
        });

        return array_merge($default, $rest);
    }

    public function published_groups() {
        return array_filter($this->groups(), function ($group) {
            return $group->post->post_status === 'publish';
        });
    }

    public function published_files() {
        return array_reduce($this->published_groups(), function ($carry, $group) {
            return array_merge($carry, $group->published_files());
        }, []);
    }

    public function files() {
        return array_reduce($this->groups(), function ($carry, $group) {
            return array_merge($carry, $group->files());
        }, []);
    }

    public function groups() {
        if (isset($this->_groups) && is_array($this->_groups)) {
            return $this->_groups;
        }

        return $this->_groups = ResourceFileGroup::with_parent_resource($this->id);
    }

    public static function get_default_group_id($resource_id) {
        $groups = ResourceFileGroup::with_parent_resource($resource_id);
        $default = array_filter(function ($group) {
            return $group->is_default_for_parent();
        });

        return count($default) === 1 ? $default[0]->id : null;
    }

    public function get_constituting_file_types() {
        $published = $this->published_files();

        $thumbnails = array_filter(array_map(function ($file) {
            return $file->get_display_thumbnail_with_type();
        }, $published), function ($t) { return !isset($t['custom']); });

        $known_ids = [];
        $types = [];

        foreach ($thumbnails as $thumb) {
            if (isset($known_ids[$thumb['term']->term_id])) {
                continue;
            }

            $known_ids[$thumb['term']->term_id] = true;

            array_push($types, [
                'term_id' => $thumb['term']->term_id,
                'name' => $thumb['term']->name,
                'thumbnail_url' => $thumb['path']
            ]);
        }

        return $types;
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

        if ($specific === 'files') {
            return array_unique(Model::flatten(array_map(function ($file) {
                return implode(' ', [$file->title, $file->description, $file->author]);
            }, $this->published_files())));
        }

        $term_names = array_map(function ($term) {
            return $term->name;
        }, wp_get_post_terms($this->post_id));

        return Model::flatten([
            Model::as_search_term('subject', $this->subject),
            $this->title,
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
