<?php

namespace Imageshare\Controllers\JSONAPI;

require_once imageshare_php_file('classes/controllers/json_api/class.base.php');
require_once imageshare_php_file('classes/controllers/json_api/class.resources.php');

use Imageshare\Logger;
use Imageshare\Models\Resource as ResourceModel;
use Imageshare\Models\ResourceCollection as CollectionModel;
use Imageshare\Controllers\JSONAPI\Collections as CollectionController;
use Imageshare\Controllers\JSONAPI\Subjects as SubjectsController;

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

    public static function add_collections($id, $collections) {
        $data = array_map(function ($collection) {
            return [
                'type' => 'collection',
                'id' => (string) $collection->id
            ];
        }, $collections);

        return [
            'links' => [
                'self' => parent::relationship_link($id, 'collections'),
                'related' => parent::resource_link($id, 'collections')
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
        $collections = CollectionModel::containing($resource->id);

        if (count($files)) {
            $relationships['files'] = self::add_files($resource->id, $files);
        }

        $relationships['subject'] = self::add_subject($resource);

        if (count($collections)) {
            $relationships['collections'] = self::add_collections($resource->id, $collections);
        }

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
            case 'collections':
                return self::get_collections($id);
                break;
            case 'subject':
                return self::get_subject($id);
                break;
            default:
                return parent::error('invalid_request', "Unknown relationship \"{$relationship}\"");
                break;
        }
    }

    public static function get_subject($id) {
        $resource = ResourceModel::by_id($id);

        if ($resource === null) {
            return parent::error('not_found', 'No such resource');
        }

        return array_map(function ($id) {
            return SubjectsController::get_single($id);
        }, [$resource->subject_term_id]);
    }

    public static function get_collections($id) {
        $collection_ids = CollectionModel::ids_containing($id);

        return array_map(function ($id) {
            return CollectionController::get_single($id);
        }, $collection_ids);
    }

    public static function get_files($id) {
        $resource = ResourceModel::by_id($id);

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

    public static function _as_data($resource) {
        $data = [
            'type' => 'resource',
            'status' => $resource->post->post_status,
            'permalink' => $resource->permalink,
            'id' => (string) $resource->id,
            'attributes' => [
                'title' => $resource->title,
                'description' => $resource->description,
                'source' => $resource->source,
                'tags' => $resource->tags,
                'files' => count($resource->files())
            ]
        ];

        // TODO add collection membership
        return self::add_relationships($resource, $data);
    }

    public static function get_single($id) {
        $resource = ResourceModel::by_id($id);

        if ($resource === null) {
            return parent::error('not_found', 'No such resource');
        }

        return self::_as_data($resource);
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

    public static function sanitise_search_params($params) {
        return array_reduce(array_keys($params), function ($map, $key) use ($params) {
            $value = $params[$key];

            $sanitised = preg_replace('/[^\w]+/', '', $key);

            if (strlen($sanitised) && self::is_valid_key($sanitised)) {
                $map[$sanitised] = $value;
            }

            return $map;
        }, []);
    }

    public static function is_valid_key($key) {
        return in_array($key, ['query', 'type', 'format', 'source', 'page']);
    }

    public static function search($args) {
        $params = self::sanitise_search_params($args);

        if (!array_key_exists('query', $params)) {
            $params['query'] = '';
        }

        if (array_key_exists('page', $params)) {
            $params['rp'] = intval($params['page']);
            unset($params['page']);
        }

        $params['_single_type'] = ResourceModel::type;

        global $imageshare;
        $search_results = $imageshare->controllers->search->query($params);

        return self::render_search_results($search_results);
    }

    public static function render_links($results) {
        $paging = $results['resources']['paging'];

        $links = [];

        foreach (['first_page', 'prev_page', 'next_page', 'last_page'] as $page) {
            $link = array_key_exists($page, $paging) && strlen($paging[$page])
                ? add_query_arg('page', $paging[$page])
                : null;

            $links[$page] = $link ? get_site_url() . $link : null;
        }

        return $links;
    }

    public static function render_search_results($results) {
        $data = array_map(['self', '_as_data'], $results['resources']['posts']);
        $links = self::render_links($results);
        return parent::render_response($data, $links);
    }
}
