<?php

namespace Imageshare\Controllers\JSONAPI;

require_once imageshare_php_file('classes/controllers/json_api/class.base.php');

use Imageshare\Logger;
use Imageshare\Models\ResourceCollection as Collection;

class Collections extends Base {
    public static function render() {
        $posts = get_posts([
            'post_type' => 'btis_collection',
            'order_by' => 'ID',
            'order' => 'asc'
        ]);

        $collections = array_reduce($posts, function ($list, $post) {
            $collection = Collection::from_post($post);

            $list[] = [
                'type' => 'collection',
                'id' => (string) $collection->id,
                'attributes' => [
                    'title' => $collection->title,
                    'description' => $collection->description,
                    'featured' => $collection->is_featured,
                    'size' => count($collection->resource_ids)
                ]
            ];

            return $list;
        }, []);

        return parent::render_response($collections);
    }
}
