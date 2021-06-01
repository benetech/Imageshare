<?php

namespace Imageshare\Controllers;

require_once imageshare_php_file('classes/helpers/class.acf.php');

use Imageshare\Logger;
use Imageshare\DB;
use Imageshare\Models\ResourceFileGroup;
use Imageshare\Models\Resource;
use Imageshare\Models\ResourceFile;
use Imageshare\Helpers\ACFConfigHelper;

class FileGroupController {

    public static function acf_save_post($post_id, $post) {
        $fields = get_field_objects();

        $acf = new ACFConfigHelper;

        $is_default_field_key = $acf->get_group_field(ResourceFileGroup::type, 'is_default')->key;
        $parent_resource_field_key = $acf->get_group_field(ResourceFileGroup::type, 'parent_resource')->key;
        $files_field_key = $acf->get_group_field(ResourceFileGroup::type, 'files')->key;
        $order_field_key = $acf->get_group_field(ResourceFileGroup::type, 'order')->key;

        $is_default = $post['acf'][$is_default_field_key];
        $parent_resource_id = $post['acf'][$parent_resource_field_key];
        $files = $post['acf'][$files_field_key];
        $order = $post['acf'][$order_field_key];

        // remove any pre-existing relationship for resource and resource_file with this group
        DB::remove_group_entries($post_id);
        // store the new resource relationship
        DB::add_resource_group_relationship($parent_resource_id, $post_id, $is_default);
        // store the new files relationships
        foreach ($files as $file_id) {
            DB::add_group_resource_file_relationship($post_id, $file_id);
        }

        Resource::by_id($parent_resource_id)->reorder_groups($post_id, $order);
    }

    public static function before_delete_post($post_id) {
        $group = ResourceFileGroup::by_id($post_id);

        // if the group is the default group for its parent, any files it contains become orphaned
        // otherwise, its files are migrated to the default group for this group's parent resource
        // if there is no default group for the resource, the files become orphaned

        // remove the entries no matter what
        DB::remove_group_entries($post_id);

        if (!$group->is_default_for_parent()) {
            // get the files
            $file_ids = $group->get_file_ids();
            // get the parent resource for this group
            $resource = $group->get_parent_resource();
            // get the default group for the resource
            $default_group_id = Resource::get_default_group_id($resource->id);

            // no default group id? Log a warning and return early
            if (is_null($default_group_id)) {
                Logger::log("File Group {$post_id} has no default group!");
                return;
            }

            Logger::log("Moving files of {$post_id} to {$default_group_id}");
            $default_group = ResourceFileGroup::by_id($default_group_id);
            // move the files to the default group
            $default_group->add_file_ids($file_ids);
        }
    }

    public static function on_acf_validate_save_post() {
        $post = $_POST;

        if (ResourceFileGroup::type !== $post['post_type']) {
            return;
        }

        $fields = get_field_objects();

        if (!is_array($fields)) {
            // annoying - new posts don't have ACF data available
            // construct our own field config from the ACF config
            $acf = new ACFConfigHelper;

            $is_default_field_key = $acf->get_group_field(ResourceFileGroup::type, 'is_default')->key;
            $parent_resource_field_key = $acf->get_group_field(ResourceFileGroup::type, 'parent_resource')->key;
            $files_field_key = $acf->get_group_field(ResourceFileGroup::type, 'files')->key;

            $fields = [
                'is_default' => ['key' => $is_default_field_key],
                'parent_resource' => ['key' => $parent_resource_field_key],
                'files' => ['key' => $files_field_key]
            ];
        }

        self::validate_resource_file_group_update($post, $post['acf'], $fields);
    }

    public static function validate_resource_file_group_update($post, $data, $fields) {
        $is_default_key = $fields['is_default']['key'];
        $is_default_value = $data[$is_default_key];

        $error = false;

        if ($is_default_value) {
            $parent_resource_key = $fields['parent_resource']['key'];
            $parent_resource_value = $data[$parent_resource_key];

            $error = self::validate_is_default_group_for_parent_resource($post, $is_default_key, $parent_resource_value);
        }

        if ($error) {
            return;
        }

        $files_resource_key = $fields['files']['key'];
        $files_resource_value = $data[$files_resource_key];

        self::remove_files_from_containing_file_groups($post, $files_resource_value);
    }

    public static function remove_files_from_containing_file_groups($post, $file_ids) {
        if (!is_array($file_ids)) {
            return;
        }

        foreach ($file_ids as $file_id) {
            ResourceFileGroup::remove_resource_file_from_all_containing_groups_not_in($file_id, [$post['post_ID']]);
        }
    }

    public static function validate_is_default_group_for_parent_resource($post, $is_default_field_key, $parent_resource_id) {
        $default_group_id = DB::get_resource_default_group($parent_resource_id);

        // No default file group? All good.
        if (is_null($default_group_id)) {
            return false;
        }

        // Default file group, but is the same as the current one.
        if ($default_group_id === $post['post_ID']) {
            return false;
        }

        $group = ResourceFileGroup::by_id($default_group_id);

        // Trying to overwrite... throw error.
        $title = $group->title;
        acf_add_validation_error("acf[{$is_default_field_key}]", "Parent resource already has default file group: \"{$title}\"");

        return true;
    }

    public static function filter_relationship_query($args, $field, $post_id) {
        if (isset($args['s']) && strpos($args['s'], 'parent_resource_id:') === 0) {
            $parent_resource_id = explode(':', $args['s'])[1];

            // the 's' parameter is the parent resource -- `parent_resource_id:xxxxx`
            // this is done because ACF ajax handling strips any custom parameters
            // and does not supply a filter hook at this level

            $parent = Resource::by_id($parent_resource_id);

            if (!is_null($parent)) {
                $default_group_id = Resource::get_default_group_id($parent->id);

                if (!is_null($default_group_id)) {
                    // only return ids associated with the parent resource default group that was selected
                    $args['post__in'] = DB::get_resource_group_file_ids($default_group_id);
                }
            }

            unset($args['s']);
        }

        return $args;
    }

    public static function get_acf_fields() {
        $acf = new ACFConfigHelper;

        return [
            'is_default' => $acf->get_group_field(ResourceFileGroup::type, 'is_default')->key,
            'parent_resource' => $acf->get_group_field(ResourceFileGroup::type, 'parent_resource')->key,
            'files' => $acf->get_group_field(ResourceFileGroup::type, 'files')->key
        ];
    }
}
