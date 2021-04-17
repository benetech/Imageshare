<?php

namespace Imageshare\Migrations;

use Imageshare\ResourceModel;
use Imageshare\ResourceFileGroupModel;

class MigrateVerifyDefaultResourceFileGroup {
    /*
     * This migration:
     *   - sets a default file group for each resource, creating one if needed
     *   - moves all files belonging to the resource, to this group
     */

    public static function ajax_verify_default_resource_file_group() {
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

            if ($resource->has_default_file_group()) {
                continue;
            }

            try {
                $resource_file_group_id = ResourceFileGroupModel::create($resource->title . ' [default]', $resource->post->post_status);
                ResourceModel::set_default_file_group($resource->id, $resource_file_group_id);
                ResourceModel::migrate_files_to_default_group($resource->id, $resource_file_group_id);
                $fixed++;
            } catch (\Exception $e) {
                Logger::log("Unexpected error migrating to resource file groups: " . $e->getMessage());
                $errors++;
            }
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
