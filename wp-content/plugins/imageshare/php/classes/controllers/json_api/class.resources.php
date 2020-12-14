<?php

namespace Imageshare\Controllers\JSONAPI;

require_once imageshare_php_file('classes/controllers/json_api/class.base.php');

use Imageshare\Logger;
use Imageshare\Models\Resource;

class Resources extends Base {
    public static function render() {
        $posts = get_posts([
            'post_type' => 'btis_resource',
            'order_by' => 'ID',
            'order' => 'asc'
        ]);

        $resources = array_reduce($posts, function ($list, $post) {
            $resource = Resource::from_post($post);

            $list[] = [
                'type' => 'resources',
                'id' => (string) $resource->id,
                'attributes' => [
                    'title' => $resource->title,
                    'description' => $resource->description,
                    'source' => $resource->source,
                    'subject' => $resource->subject,
                    'files' => count($resource->file_ids)
                ]
            ];

            return $list;
        }, []);

        return parent::render_response($resources);
    }
}
