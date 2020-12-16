<?php

namespace Imageshare\Controllers\JSONAPI;

require_once imageshare_php_file('classes/controllers/json_api/class.base.php');
require_once imageshare_php_file('classes/controllers/json_api/class.resources.php');

use Imageshare\Logger;
use Imageshare\Models\ResourceCollection as Collection;
use Imageshare\Controllers\JSONAPI\Resources as ResourceController;

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

    public static function get_members($id) {
        $collection = Collection::by_id($id);

        if ($collection === null) {
            return parent::error('not_found', 'No such collection');
        }

        $member_ids = $collection->resource_ids;

        return array_map(function ($id) {
            return ResourceController::get_single($id);
        }, $member_ids);
    }

    public static function get_relationship($id, $relationship) {
        switch($relationship) {
            case 'members':
                return self::get_members($id);
                break;
            default:
                return parent::error('invalid_request', "Unknown relationship \"{$relationship}\"");
                break;
        }
    }

    public static function get_single($id) {
        $collection = Collection::by_id($id);

        if ($collection === null) {
            return parent::error('not_found', 'No such collection');
        }

        $data = [
            'type' => 'collection',
            'id' => (string) $collection->id,
            'attributes' => [
                'status' => $collection->post->post_status,
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

        return $data;
    }

    public static function render($args) {
        if (!empty($args['id'])) {
           if (!empty($args['relationship'])) {
               return parent::render_response(self::get_relationship($args['id'], $args['relationship']));
           }
           return parent::render_response(self::get_single($args['id']));
        }

        $post_ids = get_posts([
            'post_type' => 'btis_collection',
            'order_by' => 'ID',
            'order' => 'asc',
            'fields' => 'ids'
        ]);

        $collections = array_map(['self', 'get_single'], $post_ids);

        return parent::render_response($collections);
    }
}
