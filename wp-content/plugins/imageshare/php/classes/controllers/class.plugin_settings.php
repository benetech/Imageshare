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
use ImageShare\Models\ResourceFileGroup as ResourceFileGroupModel;

class PluginSettings {
    const i18n_ns    = 'imageshare';
    const capability = 'manage_options';
    const page_slug  = 'imageshare_settings';

    public static function ajax_verify_default_resource_file_group() {
        $offset = intval(isset($_POST['offset']) ? $_POST['offset'] : 0);
        $fixed = intval(isset($_POST['fixed']) ? $_POST['fixed'] : 0);
        $errors = intval(isset($_POST['errors']) ? $_POST['errors'] : 0);

        $batch = get_posts(array(
            'order'       => 'ASC',
            'order_by'    => 'ID',
            'offset'      => $offset,
            'numberposts' => 50,
            'post_type'   => [ResourceModel::type],
            'post_status' => ['publish', 'pending', 'draft'],
            'post_parent' => null,
        ));

        foreach ($batch as $post) {
            $resource = ResourceModel::from_post($post);

            if ($resource->has_default_file_group()) {
                continue;
            }

            try {
                $resource_file_group_id = ResourceFileGroupModel::create($resource->title);
                ResourceModel::set_default_file_group($resource->id, $resource_file_group_id);
                ResourceModel::migrate_files_to_default_group($resource->id, $resource_file_group_id);
                $fixed++;
            } catch (\Exception $e) {
                Logger::log("Unexpected error migrating to resource file groups: " . $e->getMessage());
                $errors++;
            }
        }

        $batch_size = count($batch);

        echo json_encode([
            'size' => $batch_size,
            'offset' => $offset + $batch_size,
            'fixed' => $fixed,
            'errors' => $errors
        ]);

        return wp_die();
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_imageshare_verify_default_resource_file_group', [self::class, 'ajax_verify_default_resource_file_group']);
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'imageshare_settings_js',
            imageshare_asset_url('settings.js'),
            false
        );

        wp_localize_script('imageshare_settings_js', 'imageshare_ajax_obj', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('imageshare_ajax'),
        ));
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

                return [
                    'resources' => [],
                    'errors' => [$error],
                    'new_resources' => 0,
                    'new_files' => 0,
                    'updated_resources' => 0,
                    'updated_files' => 0
                ];
            }

            try {
                ResourceModel::validate($records);
            } catch (\Exception $validation_error) {
                Logger::log('Validation exception: ' . $validation_error->getMessage());

                return [
                    'resources' => [],
                    'errors' => [$validation_error->getMessage()],
                    'new_resources' => 0,
                    'new_files' => 0,
                    'updated_resources' => 0,
                    'updated_files' => 0
                ];
            }

            return $this->create_resources($records);
        }
    }

    private function create_resources($records) {
        $result = [
            'resources' => [],
            'errors'    => [],
            'new_resources' => 0,
            'new_files' => 0,
            'updated_resources' => 0,
            'updated_files' => 0
        ];

        foreach ($records as $key => $value) {
            Logger::log("Creating resource for \"{$key}\"");

            try {
                [$resource, $is_resource_update, $files] = $this->create_resource($value);

                if ($is_resource_update) {
                    $result['updated_resources']++;
                } else {
                    $result['new_resources']++; 
                }

                array_push($result['resources'], $is_resource_update
                    ? sprintf(__('Resource updated: %s (%d)', 'imageshare'), $key, $resource)
                    : sprintf(__('Resource created: %s (%d)', 'imageshare'), $key, $resource)
                );

                foreach ($files as $file) {
                    [$name, $id, $is_file_update] = $file;

                    if ($is_file_update) {
                        $result['updated_files']++;
                    } else {
                        $result['new_files']++; 
                    }

                    array_push($result['resources'], $is_file_update
                        ? sprintf(__('File updated: %s (%d)', 'imageshare'), $name, $id)
                        : sprintf(__('File created and associated: %s (%d)', 'imageshare'), $name, $id)
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
        [$resource_id, $is_resource_update] = ResourceModel::create([
            'unique_id'     => $record->unique_id,
            'title'         => $record->unique_name,
            'thumbnail_src' => $record->featured_image_URI,
            'thumbnail_alt' => $record->featured_image_alt,
            'source'        => $record->source,
            'source_uri'    => $record->source_URI ?? '',
            'description'   => $record->description,
            'subject'       => $record->subject,
            'tags'          => $record->tags,
            'download_uri'  => $record->URI ?? ''
        ]);

        if (!$is_resource_update) {
            $resource_file_group_id = ResourceFileGroupModel::create([
                'title' => $record->unique_name
            ]);

            ResourceModel::set_default_file_group($resource_id, $resource_file_group_id);
        } else {
            $resource_file_group_id = ResourceModel::get_default_group_id($resource_id);
        }

        $files = [];

        foreach ($record->files as $file) {
            try {
                [$file_id, $is_file_update] = ResourceFileModel::create([
                    'title'          => $file->display_name,
                    'description'    => $file->description,
                    'author'         => $file->author ?? '',
                    'uri'            => $file->URI,
                    'type'           => $file->type,
                    'format'         => $file->format,
                    'license'        => $file->license,
                    'accommodations' => $file->accommodations,
                    'languages'      => $file->languages,
                    'length_minutes' => $file->length_minutes,
                    'downloadable'   => $file->downloadable,
                    'print_service'  => $file->print_service ?? '',
                    'print_uri'      => $file->print_URI ?? '',
                    'group'          => $file->group ?? ''
                ]);

                if (!$is_file_update) {
                    ResourceFileGroupModel::associate_resource_file($resource_file_group_id, $file_id);
                }

                array_push($files, [$file->display_name, $file_id, $is_file_update]);
            } catch (Exception $error) {
                Logger::log("Error creating resource file: " . $error->getMessage());
            }
        }

        return [$resource_id, $is_resource_update, $files];
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

    private function get_json_schema() {
        return ResourceModel::get_schema();
    }

    public function render_settings_page() {
        if (isset($_POST['is_schema_view'])) {
            $rendered = View::render([
                'json_schema' => $this->get_json_schema(),
                'taxonomies' => $this->get_taxonomies()
            ]);
        }
        else if (isset($_POST['is_import'])) {
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
