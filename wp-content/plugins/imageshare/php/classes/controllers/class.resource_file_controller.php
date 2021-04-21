<?php

namespace Imageshare\Controllers;

use Imageshare\DB;
use Imageshare\Models\Resource as ResourceModel;
use Imageshare\Models\ResourceFileGroup as ResourceFileGroupModel;

class ResourceFileController {

    public static function save_post($post_id) {
        ResourceModel::reindex_resources_containing_resource_file($post_id);
    }

    public static function delete_post($post_id) {
        DB::remove_resource_file_entries($post_id);
        ResourceFileGroupModel::remove_resource_file_from_all_containing_groups($post_id);
    }

}
