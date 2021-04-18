<?php

namespace Imageshare\Controllers;

require_once imageshare_php_file('classes/helpers/class.acf.php');

use Imageshare\Logger;
use Imageshare\Models\ResourceFileGroup;
use Imageshare\Models\Resource;
use Imageshare\Models\ResourceFile;
use Imageshare\Helpers\ACFConfigHelper;

class FileGroupController {

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
        foreach ($file_ids as $file_id) {
            ResourceFileGroup::remove_resource_file_from_all_containing_groups_not_in($file_id, [$post['post_ID']]);
        }
    }

    public static function validate_is_default_group_for_parent_resource($post, $is_default_field_key, $parent_resource_id) {
        $default_group = ResourceFileGroup::get_default_group_for_resource($parent_resource_id);

        // No default file group? All good.
        if (is_null($default_group)) {
            return false;
        }

        // Default file group, but is the same as the current one.
        if ($default_group->id === $post['post_ID']) {
            return false;
        }

        // Trying to overwrite... throw error.
        $title = $default_group->title;
        acf_add_validation_error("acf[{$is_default_field_key}]", "Parent resource already has default file group: \"{$title}\"");

        return true;
    }
}
