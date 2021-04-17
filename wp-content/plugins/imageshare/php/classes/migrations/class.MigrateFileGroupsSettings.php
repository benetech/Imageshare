<?php

namespace Imageshare\Migrations;

use Imageshare\ResourceFileGroupModel;

class MigrateFileGroupSettings {
    /*
     * This migration:
     *   - moves file groups away from resources 
     *   - moves the "default file group" away from a resource
     */

    public static function ajax_migrate_file_groups_settings() {
        $offset = intval(isset($_POST['offset']) ? $_POST['offset'] : 0);
        $fixed = intval(isset($_POST['fixed']) ? $_POST['fixed'] : 0);
        $errors = intval(isset($_POST['errors']) ? $_POST['errors'] : 0);
        $size = intval(isset($_POST['size']) ? $_POST['size'] : 50);

        $batch = get_posts(array(
            'order'       => 'ASC',
            'order_by'    => 'ID',
            'offset'      => $offset,
            'numberposts' => $size,
            'post_type'   => [ResourceModel::type],
            'post_status' => ['publish', 'pending', 'draft'],
            'post_parent' => null,
        ));

        foreach ($batch as $post) {
            $is_migrated = get_post_meta($post->id, 'has_migrated_file_groups_settings', false);

            if ($is_migrated) {
                continue;
            }

            $resource = ResourceModel::from_post($post);

            // get all group ids
            // for each group, set the parent resource to this id
            $group_ids = get_post_meta($post->id, 'groups', true) ?: [];
            
            foreach ($group_ids as $group_id) {
                $group = ResourceFileGroup::by_id($group_id);
                $group->set_parent_resource_id($post->id); 
            }

            // get the default group id
            // set that group's is_default meta value
            $default = get_post_meta($post_id, 'default_file_group', true);
            $group = ResourceFileGroup::by_id($default);
            $group->set_as_default_for_parent();
            
            // delete the 'groups' meta value
            // delete the 'resource_file_group_id' meta values
            delete_post_meta($post->id, 'groups');
            delete_post_meta($post->id, 'resource_file_group_id');
            delete_post_meta($post->id, 'default_file_group');

            update_post_meta('has_migrated_file_groups_settings', true);
        }

        $batch_size = count($batch);

        echo json_encode([
            'size' => $batch_size,
            'offset' => $offset + $batch_size,
            'fixed' => $fixed,
            'errors' => $errors
        ]);

        return wp_die();

    }
}
