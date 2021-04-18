<?php

namespace Imageshare\Migrations;

require_once imageshare_php_file('classes/models/class.resource_file_group.php');

use Imageshare\Logger;
use Imageshare\Models\Resource as ResourceModel;
use Imageshare\Models\ResourceFileGroup as ResourceFileGroupModel;

class MigrateFileGroupsSettings {
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
            $resource = ResourceModel::from_post($post);

            // get all group ids
            // for each group, set the parent resource to this id
            $group_ids = get_post_meta($resource->id, 'groups', true);

            if (is_null($group_ids) || $group_ids === '') {
                // groups is null or empty string for {$resource->id}, setting to empty list
                $group_ids = [];
            }

            foreach ($group_ids as $group_id) {
                $group = ResourceFileGroupModel::by_id($group_id);
                $group->set_parent_resource_id($resource->id); 
            }

            // get the default group id
            // set that group's is_default meta value
            $default = get_post_meta($resource->id, 'default_file_group', true);

            if (!is_null($default) && $default !== '') {
                $group = ResourceFileGroupModel::by_id($default);
                if (is_null($group)) {
                    Logger::log("No valid ResourceFileGroup for {$default}!");
                } else {
                    $group->set_as_default_for_parent();
                }
            }
            
            // delete the 'groups' meta value
            // delete the 'resource_file_group_id' meta values
            delete_post_meta($resource->id, 'groups');
            delete_post_meta($resource->id, 'resource_file_group_id');
            delete_post_meta($resource->id, 'default_file_group');

            $fixed++;
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
