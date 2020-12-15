<?php

namespace Imageshare\Controllers\JSONAPI;

require_once imageshare_php_file('classes/controllers/json_api/class.base.php');

class Subjects extends Base {
    public static function relationship_link($id, $relationship) {
        return parent::abs_link("/types/{$id}/relationships/{$relationship}");
    }

    public static function resource_link($id, $relationship) {
        return parent::abs_link("/types/{$id}/{$relationship}");
    }

    public static function relationship_data($id, $relation_id, $relationship) {
        return [
            'links' => [
                'self' => self::relationship_link($id, $relationship),
                'related' => self::resource_link($id, $relationship)
            ],
            'data' => [
                'type' => 'resource_subject',
                'id' => (string) $relation_id
            ]
        ];
    }

    public static function get_single($id) {
        $term = get_term($id, 'subjects');

        if (!$term) {
            return parent::error('not_found', "No such subject");
        }

        $data = [
            'type' => 'subject',
            'id' => (string) $id,
            'attributes' => [
                'name' => $term->name,
            ]
        ];

        $relationships = [];

        if ($term->parent) {
            $relationships['parent'] = self::relationship_data($id, $term->parent, 'parent');
        }

        $children = self::get_children($id);

        if (count($children)) {
            $relationships['children'] = $children;
        }

        if (count($relationships)) {
            $data['relationships'] = $relationships;
        }

        return $data;
    }

    public static function get_parent($id) {
        $term = get_term($id);
        return self::get_single($term->parent);
    }

    public static function get_children($id) {
         $children = get_terms([
            'taxonomy' => 'subjects',
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
            'taxonomy' => 'subjects',
            'orderby' => 'id',
            'hide_empty' => false,
            'fields' => 'ids'
        ]);

        $types = array_map(['self', 'get_single'], $term_ids);

        return parent::render_response($types);
    }
}
