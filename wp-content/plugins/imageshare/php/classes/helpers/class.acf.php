<?php

namespace Imageshare\Helpers;

use Imageshare\Logger;

class ACFConfigHelper {
    private $config;

    public function __construct() {
        $this->config = json_decode(file_get_contents(imageshare_asset_file('acf-field-group-config.json')));
    }

    public function get_group_config(string $group) {
        return current(array_filter($this->config, function ($config) use ($group) {
            return $config->title === $group;
        }));
    }

    public function get_group_field(string $group, string $field_name) {
        $group_config = $this->get_group_config($group);

        if (is_null($group_config)) {
            return null;
        }

        return $field = current(array_filter($group_config->fields, function ($field) use ($field_name) {
            return $field->name === $field_name;
        }));
    }
}
