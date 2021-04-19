<?php

namespace Imageshare\Models;

use Imageshare\Logger;

class ResourceFileGroup {

    const type = 'btis_file_group';

    public static function create($title, $status = 'draft') {
        $post_data = [
            'post_type' => self::type,
            'post_title' => $title,
            'post_status' => $status,
            'post_name' => sanitize_title_with_dashes($title),
            'comment_status' => 'closed',
            'post_category' => [],
            'meta_input' => [
                'files' => []
            ]
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            // the original WP_Error for inserting a post is empty for some reason
            throw new \Exception(sprintf(__('Unable to create resource file group "%s"', 'imageshare'), $args['title']));
        }

        return $post_id;
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
            'label' => self::i18n('File Groups'),
            'labels' => array(
                'singular_name' => self::i18n('File Group')
            ),
            'description' => self::i18n('A titled group of resource files.'),
            'capability_type' => 'post',
            'supports' => array(
                'title',
            ),
            'public' => true,
            'show_ui' => true
        );
    }

    public static function manage_columns(array $columns) {
        $columns['size'] = self::i18n('Size');
        $columns['parent'] = self::i18n('Resource');
        $columns['is_default'] = self::i18n('Default for parent resource');
        return $columns;
    }

    public static function manage_custom_column(string $column_name, int $post_id) {
        $post = new ResourceFileGroup($post_id);

        switch ($column_name) {
            case 'size':
                $fbs = Model::children_by_status($post->files());

                if (empty($fbs)) {
                    echo '0';
                    break;
                }

                echo join(', ', array_map(function($status) use($fbs) {
                    return "{$fbs[$status]} {$status}";
                }, array_keys($fbs)));
                break;

            case 'parent':
                $parent = $post->get_parent_resource();
                echo isset($parent)
                    ? '<a href="' . get_permalink($parent->id) . '">' . $parent->title . '</a>'
                    : self::i18n('None &#9888;')
                ;
                break;

            case 'is_default':
                echo (bool) $post->is_default_for_parent() ? self::i18n('Yes') : self::i18n('No');
                break;
        }
    }

    public static function by_id($id) {
        $post = get_post($id);

        if ($post !== null && $post->post_type === static::type) {
            return self::from_post($post);
        }

        return null;
    }

    public static function from_post(\WP_Post $post) {
        $wrapped = new ResourceFileGroup();
        $wrapped->post = $post;
        $wrapped->load_custom_attributes();
        return $wrapped;
    }

    private function get_post($post_id) {
        $this->post = get_post($post_id);
        return $this->load_custom_attributes();
    }

    public function set_parent_resource_id($resource_id) {
        update_post_meta($this->post_id, 'parent_resource', $resource_id);
    }

    public function set_as_default_for_parent() {
        update_post_meta($this->post_id, 'is_default', 1);
    }

    public function get_parent_resource() {
        if (!isset($this->parent_resource)) {
            return null;
        }

        return Resource::by_id($this->parent_resource);
//        $resources = Resource::containing_file_group($this->post_id);
//        return count($resources) === 1 ? $resources[0] : null;
    }

    public static function containing_resource_file($resource_file_id, $ids_only = false) {
        $args = [
            'numberposts'   => -1,
            'post_type'     => [self::type],
            'post_status'   => 'publish',
            'meta_key'      => 'file_id',
            'meta_value'    => $resource_file_id
        ];

        if ($ids_only) {
            $args['fields'] = 'ids';
        }

        $posts = get_posts($args);

        if ($ids_only) {
            return $posts;
        }

        return array_map(function ($post) {
            return self::from_post($post);
        }, $posts);
    }

    public static function with_parent_resource($resource_id, $ids_only = false) {
        $args = [
            'numberposts'   => -1,
            'post_type'     => [self::type],
            // only published -- huge performance hit otherwise
            'post_status'   => 'publish',
            'meta_key'      => 'parent_resource',
            'meta_value'    => $resource_id
        ];

        if ($ids_only) {
            $args['fields'] = 'ids';
        }

        $posts = get_posts($args);

        if ($ids_only) {
            return $posts;
        }

        return array_map(function ($post) {
            return self::from_post($post);
        }, $posts);
    }

    public function is_default_for_parent() {
        return (bool) $this->is_default;
    }

    public static function get_default_group_for_resource($resource_id) {
        $resources = self::with_parent_resource($resource_id);

        $default = array_filter($resources, function ($group) {
            return $group->is_default_for_parent();
        });

        return count($default) === 1 ? array_pop($default) : null;
    }

    public function load_custom_attributes() {
        if (!empty($this->post)) {
            $this->id = $this->post->ID;
            $this->post_id = $this->post->ID;
            $this->title = $this->post->post_title;
            $this->permalink = get_permalink($this->post->ID);

            $this->parent_resource = get_post_meta($this->post_id, 'parent_resource', true);
            $this->is_default = get_post_meta($this->post_id, 'is_default', true);

            $this->file_ids = $this->get_file_ids();
//            $this->parent = $this->get_parent();

            return $this->id;
        }
        
        return null;
    }

    private function get_file_ids() {
        $file_ids = get_post_meta($this->post_id, 'files', true);
        if (is_array($file_ids)) {
            return $file_ids;
        }
        return [];
    }

    public function published_files() {
        return array_filter($this->files(), function ($file) {
            return $file->post->post_status === 'publish';
        });
    }

    public function files() {
        if (isset($this->_files) && is_array($this->_files)) {
            return $this->_files;
        }

        return $this->_files = array_reduce($this->file_ids, function ($carry, $file_id) {
            $file = new ResourceFile($file_id);
            array_push($carry, $file);
            return $carry;
        }, []);
    }

    public function acf_update_value($field, $value) {
        switch($field['name']) {
            case 'files':
            // also store file ids as flat database records for meta search
            // use $this->post->ID as the resource file might not be finished creating
                delete_post_meta($this->post->ID, 'file_id');
                foreach ($value as $file_id) {
                    add_post_meta($this->post->ID, 'file_id', $file_id);
                }
            break;
        }

        return $value;
    }

    public static function associate_resource_file($resource_file_group_id, $resource_file_id) {
        $files = get_post_meta($resource_file_group_id, 'files', true);

        if (in_array($resource_file_id, $files)) {
            return;
        }

        array_push($files, $resource_file_id);
        update_field('files', $files, $resource_file_group_id);
    }

    public static function remove_resource_file_from_all_containing_groups_not_in($resource_file_id, $group_ids) {
        $groups = self::containing_resource_file($resource_file_id);
        foreach ($groups as $group) {
            if (in_array($group->id, $group_ids)) {
                continue;
            }
            $group->remove_resource_file($resource_file_id);
        }
    }

    public static function remove_resource_file_from_all_containing_groups($resource_file_id) {
        $groups = self::containing_resource_file($resource_file_id);
        foreach ($groups as $group) {
            $group->remove_resource_file($resource_file_id);
        }
    }

    public function remove_resource_file($resource_file_id) {
        Logger::log("Removing resource file {$resource_file_id} from group {$this->id}");

        delete_post_meta($this->id, 'file_id', $resource_file_id);
        $other_resource_file_ids = array_filter($this->get_file_ids(), function ($id) use ($resource_file_id) {
            return $id != $resource_file_id;
        });

        update_field('files', $other_resource_file_ids, $this->id);
        $this->file_ids = $other_resource_file_ids;
        unset($this->_files);
        wpfts_post_reindex($this->id);
    }

    public function reindex() {
        wpfts_post_reindex($this->id);
    }

    public static function on_acf_relationship_result($post_id, $related_post, $field) {
        // this can only be a file
        $file = ResourceFile::from_post($related_post);
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

        $group = new ResourceFileGroup($post_id);
        $old_status = $group->post->post_status;

        if ($old_status === 'publish') {
            Logger::log("File Group {$post_id} is already published, skipping filter");
            return;
        }

        $new_status = $data['post_status'];

        if ($new_status === 'publish') {
            Logger::log("File Group {$post_id} going from {$old_status} to {$new_status}");
            Model::force_publish_children($group->files());
        }
    }
}
