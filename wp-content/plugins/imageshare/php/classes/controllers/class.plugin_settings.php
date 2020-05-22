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

use Swaggest\JsonSchema\Schema;

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
                return new \WP_Error('imageshare', __('Unable to load records from supplied file.', 'imageshare'));
            }

            try {
                $this->validate($records);
            } catch (\Exception $validation_error) {
                Logger::log('Validation exception: ' . $validation_error->getMessage());
                return ['resources' => [], 'errors' => [$validation_error->getMessage()]];
            }

            return $this->create_resources($records);
        }
    }

    private function validate($records) {
        $schema_json = file_get_contents(imageshare_asset_file('import.schema.json'));
        $schema = Schema::import(json_decode($schema_json));
        $schema->in($records);
    }

    private function create_resources($records) {
        $result = [
            'resources' => [],
            'errors'    => []
        ];

        foreach ($records as $key => $value) {
            Logger::log("Creating resource for \"{$key}\"");

            try {
                $resource = $this->create_resource($value);
                array_push($result['resources'], sprintf(__('Resource created: %s', 'imageshare'), $key));
            } catch (\Exception $error) {
                Logger::log("Error creating resource: " . $error->getMessage());
                array_push($result['errors'], "({$key}) {$error->getMessage()}");
            }
        }

        return $result;
    }

    private function create_resource($record) {
        $resource_id = ResourceModel::create([
            'title'         => $record->unique_name,
            'thumbnail_uri' => $record->featured_image_URI,
            'thumbnail_alt' => $record->featured_image_alt,
            'source'        => $record->source,
            'description'   => $record->description,
            'subject'       => $record->subject,
            'tags'          => $record->tags
        ]);

        foreach ($record->files as $file) {
            try {
                $file_id = ResourceFileModel::create([
                    'title'          => $file->display_name,
                    'uri'            => $file->URI,
                    'type'           => $file->type,
                    'format'         => $file->format,
                    'license'        => $file->license,
                    'accommodations' => $file->accommodations,
                    'languages'      => $file->languages,
                    'length_minutes' => $file->length_minutes
                ]);

                ResourceModel::associate_resource_file($resource_id, $file_id);
            } catch (Exception $error) {
                // log the error
            }
        }

        return $resource_id;
    }

    public function render_settings_page() {
        if (isset($_POST['is_update'])) {
            $parse_result = $this->handle_form_submit();
            Logger::log($parse_result);
            $rendered = View::render(['result' => $parse_result]);
        } else {
            $rendered = View::render();
        }

        echo $rendered;
    }
}
