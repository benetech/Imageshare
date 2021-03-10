<?php

namespace Imageshare\Controllers\JSONAPI;

require_once imageshare_php_file('classes/controllers/json_api/class.tags.php');

class Tags extends Base {
    const name = 'keyword';
    const plural_name = 'keywords';

    public static function render() {
        $tags = get_tags([
            'hide_empty' => true
        ]);

        $tags = array_reduce($tags, function ($list, $tag) {
            $list[] = [
                'type' => static::name,
                'id' => $tag->term_id,
                'attributes' => [
                    'name' => $tag->name
                ]
            ];

            return $list;
        }, []);

        return parent::render_response($tags);
    }
}
