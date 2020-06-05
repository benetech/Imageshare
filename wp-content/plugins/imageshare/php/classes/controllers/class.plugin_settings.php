<?php

namespace Imageshare\Controllers;

require_once imageshare_php_file('classes/class.logger.php');
require_once imageshare_php_file('classes/views/class.plugin_settings.php');
require_once imageshare_php_file('classes/models/class.resource.php');
require_once imageshare_php_file('classes/models/class.resource_file.php');

use Imageshare\Logger;
use ImageShare\Views\PluginSettings as View;
use ImageShare\Models\Resource as ResourceModel;
use ImageShare\Models\ResourceFile as ResourceFileModel;

class PluginSettings {
    const i18n_ns    = 'imageshare';
    const capability = 'manage_options';
    const page_slug  = 'imageshare_settings';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    public function add_admin_menu() {
        $page_title = __('Imageshare settings', self::i18n_ns);
        $menu_title = __('Imageshare', self::i18n_ns);

        add_submenu_page(
            'options-general.php',
            $page_title,
            $menu_title,
            self::capability,
            self::page_slug,
            array($this, 'render_settings_page')
        );
    }

    private function handle_form_submit() {
        if (isset($_FILES['record_file'])) {
            $json_data = file_get_contents($_FILES['record_file']['tmp_name']);
            $records = json_decode($json_data);

            if ($records === null) {
                $error = __('Unable to load records from supplied file.', 'imageshare');
                return ['resources' => [], 'errors' => [$error]];
            }

            try {
                ResourceModel::validate($records);
            } catch (\Exception $validation_error) {
                Logger::log('Validation exception: ' . $validation_error->getMessage());
                return ['resources' => [], 'errors' => [$validation_error->getMessage()]];
            }

            return $this->create_resources($records);
        }
    }

    private function create_resources($records) {
        $result = [
            'resources' => [],
            'errors'    => []
        ];

        foreach ($records as $key => $value) {
            Logger::log("Creating resource for \"{$key}\"");

            try {
                [$resource, $is_update, $files] = $this->create_resource($value);

                array_push($result['resources'], $is_update
                    ? sprintf(__('Resource updated: %s', 'imageshare'), $key)
                    : sprintf(__('Resource created: %s', 'imageshare'), $key)
                );

                foreach ($files as $file) {
                    [$name, $is_update] = $file;
                    array_push($result['resources'], $is_update
                        ? sprintf(__('File updated: %s', 'imageshare'), $name)
                        : sprintf(__('File associated: %s', 'imageshare'), $name)
                    );
                }
            } catch (\Exception $error) {
                Logger::log("Error creating resource: " . $error->getMessage());
                array_push($result['errors'], "({$key}) {$error->getMessage()}");
            }
        }

        return $result;
    }

    private function create_resource($record) {
        [$resource_id, $is_update] = ResourceModel::create([
            'title'         => $record->unique_name,
            'thumbnail_src' => $record->featured_image_URI,
            'thumbnail_alt' => $record->featured_image_alt,
            'source'        => $record->source,
            'description'   => $record->description,
            'subject'       => $record->subject,
            'tags'          => $record->tags,
            'download_uri'  => $record->URI ?? ''
        ]);

        $files = [];

        foreach ($record->files as $file) {
            try {
                [$file_id, $is_update] = ResourceFileModel::create([
                    'title'          => $file->display_name,
                    'description'    => $file->description,
                    'uri'            => $file->URI,
                    'type'           => $file->type,
                    'format'         => $file->format,
                    'license'        => $file->license,
                    'accommodations' => $file->accommodations,
                    'languages'      => $file->languages,
                    'length_minutes' => $file->length_minutes,
                    'downloadable'   => $file->downloadable
                ]);

                if (!$is_update) {
                    ResourceModel::associate_resource_file($resource_id, $file_id);
                }

                array_push($files, [$file->display_name, $is_update]);
            } catch (Exception $error) {
                Logger::log("Error creating resource file: " . $error->getMessage());
            }
        }

        return [$resource_id, $is_update, $files];
    }

    private function get_taxonomies() {
        $json = json_decode(file_get_contents(imageshare_asset_file('taxonomies.json')));
        $taxonomies = [];

        foreach ($json as $taxonomy => $definition) {
            array_push($taxonomies, [
                'term_name' => $taxonomy,
                'name' => $definition->plural,
                'settings' => [
                    'allow_empty' => get_option("taxonomy_{$taxonomy}_allow_empty", 0)
                ]
            ]);
        }


        return $taxonomies;
    }

    private function update_taxonomies() {
        $json = json_decode(file_get_contents(imageshare_asset_file('taxonomies.json')));
        $taxonomies = [];

        foreach ($json as $taxonomy => $definition) {
            $allow_empty = isset($_POST["taxonomy_{$taxonomy}_allow_empty"]) ? 1 : 0;
            update_option("taxonomy_{$taxonomy}_allow_empty", $allow_empty);

            array_push($taxonomies, [
                'term_name' => $taxonomy,
                'name' => $definition->plural,
                'settings' => [
                    'allow_empty' => $allow_empty
                ]
            ]);
        }

        return $taxonomies;
    }

    public function render_settings_page() {
        if (isset($_POST['is_import'])) {
            $parse_result = $this->handle_form_submit();
            $rendered = View::render([
                'result' => $parse_result,
                'taxonomies' => $this->get_taxonomies()
            ]);
        } else if (isset($_POST['is_taxonomy_update'])) {
            $taxonomies = $this->update_taxonomies();
            $rendered = View::render(['taxonomies' => $taxonomies]);
        } else {
            $rendered = View::render(['taxonomies' => $this->get_taxonomies()]);
        }

        echo $rendered;
    }
}
