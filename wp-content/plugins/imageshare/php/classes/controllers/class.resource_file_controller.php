<?php

namespace Imageshare\Controllers;

use Imageshare\Models\Resource as ResourceModel;
use Imageshare\Models\ResourceFileGroup as ResourceFileGroupModel;

class ResourceFileController {

    public static function save_post($post_id) {
        ResourceModel::reindex_resources_containing_resource_file($post_id);
    }

    public static function delete_post($post_id) {
        DB::remove_resource_file_entries($post_id);
        ResourceFileGroup::remove_resource_file_from_all_containing_groups($post_id);
    }

}
