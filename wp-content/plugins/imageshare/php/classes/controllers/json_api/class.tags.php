<?php

namespace Imageshare\Controllers\JSONAPI;

class Tags extends Base {
    const name = 'tag';
    const plural_name = 'tags';

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
