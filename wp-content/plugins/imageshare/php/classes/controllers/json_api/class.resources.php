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

    public static function get_relationship($id, $relationship) {
        switch($relationship) {
            case 'files':
                return self::get_files($id);
                break;
            case 'subject':
                return self::get_subject($id);
                break;
            default:
                return parent::error('invalid_request', "Unknown relationship \"{$relationship}\"");
                break;
        }
    }

    public static function get_files($id) {
        $resource = Resource::by_id($id);

        if ($resource === null) {
            return parent::error('not_found', 'No such resource');
        }

        $files = $resource->files();

        return array_reduce($files, function ($list, $file) use($id) {
            $list[] = [
                'type' => 'file',
                'id' => (string) $file->id,
                'file_type' => $file->type,
                'file_format' => $file->format,
                'accommodations' => $file->accommodations,
                'title' => $file->title,
                'description' => $file->description,
                'author' => $file->author,
                'languages' => $file->languages,
                'uri' => $file->uri,
                'license' => $file->license,
                'length' => $file->length_formatted_string(),
                'downloadable' => !!$file->downloadable,
                'printable' => $file->printable,
                'print_uri' => strlen($file->print_uri) ? $file->print_uri : null,
                'print_service' => strlen($file->print_service) ? $file->print_service : null,

                'relationships' => [
                    'parent' => [
                        'links' => [
                            'self' => parent::id_link($id),
                        ],
                        'data' => [
                            'type' => 'resource',
                            'id' => (string) $id
                        ]
                    ],
                ]
            ];

            return $list;
        }, []);
    }

    public static function get_single($id) {
        $resource = Resource::by_id($id);

        if ($resource === null) {
            return parent::error('not_found', 'No such resource');
        }

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

        // TODO add collection membership
        return self::add_relationships($resource, $data);
    }

    public static function render($args) {
        if (!empty($args['id'])) {
           if (!empty($args['relationship'])) {
               return parent::render_response(self::get_relationship($args['id'], $args['relationship']));
           }
           return parent::render_response(self::get_single($args['id']));
        }

        $post_ids = get_posts([
            'post_type' => 'btis_resource',
            'order_by' => 'ID',
            'order' => 'asc',
            'fields' => 'ids'
        ]);


        $resources = array_map(['self', 'get_single'], $post_ids);

        return parent::render_response($resources);
    }
}
