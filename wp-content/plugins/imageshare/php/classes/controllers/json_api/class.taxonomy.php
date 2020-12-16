<?php

namespace Imageshare\Controllers\JSONAPI;

require_once imageshare_php_file('classes/controllers/json_api/class.base.php');

class Taxonomy extends Base {
    public static function transform($term, $data) {
        return $data;
    }

    public static function relationship_data($id, $relation_ids, $relationship) {
        $data = array_map(function ($id) {
            return [
                'type' => static::name,
                'id' => (string) $id
            ];
        }, $relation_ids);

        return [
            'links' => [
                'self' => parent::relationship_link($id, $relationship),
                'related' => parent::resource_link($id, $relationship)
            ],
            'data' => count($data) > 1 ? $data : $data[0],
        ];
    }

    public static function get_single($id) {
        $term = get_term($id, static::taxonomy);

        if (!$term) {
            return parent::error('not_found', "No such term");
        }

        $data = [
            'type' => static::name,
            'id' => (string) $id,
            'attributes' => [
                'name' => $term->name,
            ]
        ];

        $relationships = [];

        if ($term->parent) {
            $relationships['parent'] = self::relationship_data($id, [$term->parent], 'parent');
        }

        $child_ids = array_map(function ($child) {
            return $child['id'];
        }, self::get_children($id));

        if (count($child_ids)) {
            $relationships['children'] = self::relationship_data($id, $child_ids, 'children');
        }

        if (count($relationships)) {
            $data['relationships'] = $relationships;
        }

        return static::transform($term, $data);
    }

    public static function get_parent($id) {
        $term = get_term($id);
        return self::get_single($term->parent);
    }

    public static function get_children($id) {
         $children = get_terms([
            'taxonomy' => static::taxonomy,
            'child_of' => $id,
            'hide_empty' => false,
            'fields' => 'ids'
        ]);
    
        return array_map(['self', 'get_single'], $children);
    }

    public static function get_relationship($id, $relationship) {
        switch($relationship) {
            case 'parent':
                return self::get_parent($id);
                break;
            case 'children':
                return self::get_children($id);
                break;
            default:
                return parent::error('invalid_request', "Unknown relationship \"{$relationship}\"");
                break;
        }
    }

    public static function render($args) {
        if (!empty($args['id'])) {
           if (!empty($args['relationship'])) {
               return parent::render_response(self::get_relationship($args['id'], $args['relationship']));
           }
           return parent::render_response(self::get_single($args['id']));
        }

        $term_ids = get_terms([
            'taxonomy' => static::taxonomy,
            'orderby' => 'id',
            'hide_empty' => false,
            'fields' => 'ids'
        ]);

        $types = array_map(['self', 'get_single'], $term_ids);

        return parent::render_response($types);
    }

}
