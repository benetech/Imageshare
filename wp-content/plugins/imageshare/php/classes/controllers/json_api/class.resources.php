<?php

namespace Imageshare\Controllers\JSONAPI;

require_once imageshare_php_file('classes/controllers/json_api/class.base.php');

use Imageshare\Logger;
use Imageshare\Models\Resource;

class Resources extends Base {
    const plural_name = 'resources';

    public static function add_files($id, $files) {
        $data = array_map(function ($file) {
            return [
                'type' => 'resource_file',
                'id' => (string) $file->id
            ];
        }, $files);

        return [
            'links' => [
                'self' => parent::relationship_link($id, 'files'),
                'related' => parent::resource_link($id, 'files')
            ],
            'data' => count($data) > 1 ? $data : $data[0],
        ];
    }

    public static function add_subject($resource) {
         $data = [
            'type' => 'subject',
            'id' => (string) $resource->subject_term_id
        ];

        return [
            'links' => [
                'self' => parent::relationship_link($resource->id, 'subject'),
                'related' => parent::resource_link($resource->id, 'subject')
            ],
            'data' => $data
        ];
    }

    public static function add_relationships($resource, $data) {
        $relationships = [];

        $files = $resource->files();

        if (count($files)) {
            $relationships['files'] = self::add_files($resource->id, $files);
        }

        $relationships['subject'] = self::add_subject($resource);

        if (count(array_keys($relationships))) {
            $data['relationships'] = $relationships;
        }

        return $data;
    }

    public static function render() {
        $posts = get_posts([
            'post_type' => 'btis_resource',
            'order_by' => 'ID',
            'order' => 'asc'
        ]);

        $resources = array_reduce($posts, function ($list, $post) {
            $resource = Resource::from_post($post);

            $data = [
                'type' => 'resource',
                'status' => $resource->post->post_status,
                'id' => (string) $resource->id,
                'attributes' => [
                    'title' => $resource->title,
                    'description' => $resource->description,
                    'source' => $resource->source,
                    'tags' => $resource->tags,
                    'files' => count($resource->file_ids)
                ]
            ];

            $list[] = self::add_relationships($resource, $data);

            return $list;
        }, []);

        return parent::render_response($resources);
    }
}
