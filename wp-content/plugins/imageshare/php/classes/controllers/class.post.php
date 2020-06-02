<?php

namespace Imageshare\Controllers;

use Imageshare\Logger;
use Imageshare\Models\Resource as ResourceModel;
use Imageshare\Models\ResourceFile as ResourceFileModel;
use Imageshare\Models\ResourceCollection as ResourceCollectionModel;

class Post {
    public function __construct() {
    }

    public function get_post($post) {
        switch ($post->post_type) {
            case ResourceModel::type:
                $post = new ResourceModel($post->id);
            break;

            case ResourceFileModel::type:
                // we don't allow controller access to files
                $post = null;
            break;

            case ResourceCollectionModel::type:
                $post = new ResourceCollectionModel($post->id);
            break;

            default:
            break;
        }

        return $post;
    }

    public function get_posts($posts) {
        return array_map(function ($post) {
            return self::get_post($post);
        }, iterator_to_array($posts));
    }
}

