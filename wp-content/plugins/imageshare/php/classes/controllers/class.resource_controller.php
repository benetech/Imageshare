<?php

namespace Imageshare\Controllers;

use Imageshare\DB;
use Imageshare\Models\ResourceFileGroup as ResourceFileGroupModel;
use Imageshare\Models\ResourceCollection as ResourceCollectionModel;

class ResourceController {

    public static function delete_post($post_id) {
        DB::remove_resource_entries($post_id);
        ResourceFileGroupModel::remove_parent_where($post_id);
        ResourceCollectionModel::remove_resource($post_id);
    }

}
