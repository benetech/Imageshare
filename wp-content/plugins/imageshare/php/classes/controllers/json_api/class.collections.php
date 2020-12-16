<?php

namespace Imageshare\Controllers\JSONAPI;

require_once imageshare_php_file('classes/controllers/json_api/class.base.php');

use Imageshare\Logger;
use Imageshare\Models\ResourceCollection as Collection;

class Collections extends Base {
    const plural_name = 'collections';

    public static function members($id, $members) {
        $data = array_map(function ($member) {
            return [
                'type' => 'resource',
                'id' => (string) $member->id
            ];
        }, $members);

        return [
            'links' => [
                'self' => parent::relationship_link($id, 'members'),
                'related' => parent::resource_link($id, 'members')
            ],
            'data' => count($data) > 1 ? $data : $data[0],
        ];
    }

    public static function render() {
        $posts = get_posts([
            'post_type' => 'btis_collection',
            'order_by' => 'ID',
            'order' => 'asc'
        ]);

        $collections = array_reduce($posts, function ($list, $post) {
            $collection = Collection::from_post($post);

            $data = [
                'type' => 'collection',
                'id' => (string) $collection->id,
                'attributes' => [
                    'title' => $collection->title,
                    'description' => $collection->description,
                    'featured' => $collection->is_featured,
                    'contributor' => $collection->contributor,
                    'thumbnail' => $collection->thumbnail,
                    'size' => count($collection->resource_ids)
                ]
            ];

            $resources = $collection->resources();

            if (count($resources)) {
                $data['relationships'] = [
                    'members' => self::members($collection->id, $resources)
                ];
            }

            $list[] = $data;

            return $list;
        }, []);

        return parent::render_response($collections);
    }
}
