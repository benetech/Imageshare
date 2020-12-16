<?php

namespace Imageshare\Controllers\JSONAPI;

class Base {
    const _status = [
        'invalid_request' => '401',
        'not_found' => '404'
    ];

    public static function relationship_link($id, $relationship) {
        $name = static::plural_name;
        return self::abs_link("/{$name}/{$id}/relationships/{$relationship}");
    }

    public static function resource_link($id, $relationship) {
        $name = static::plural_name;
        return self::abs_link("/{$name}/{$id}/{$relationship}");
    }

    public static function id_link($id) {
        $name = static::plural_name;
        return self::abs_link("/{$name}/{$id}");
    }

    public static function render_response($data) {
        header('Content-Type: vnd.api+json');

        if (isset($data['is_error'])) {
            echo json_encode(['errors' => [$data['error']]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode(['data' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }

    public static function abs_link($path) {
        return get_site_url() . '/json-api' . $path;
    }

    public static function error($code, $message) {
        return [
            'is_error' => true,
            'error' => [
                'status' => static::_status[$code],
                'code' => $code,
                'title' => $message
            ]
        ];
    }
}
