<?php

namespace Imageshare;

require_once imageshare_php_file('classes/class.logger.php');

require_once imageshare_php_file('classes/models/class.resource_collection.php');
require_once imageshare_php_file('classes/models/class.resource.php');
require_once imageshare_php_file('classes/models/class.resource_file.php');

require_once imageshare_php_file('classes/controllers/class.resource_collection.php');
require_once imageshare_php_file('classes/controllers/class.plugin_settings.php');

use Imageshare\Logger;

use Imageshare\Models\ResourceCollection;
use Imageshare\Models\Resource;
use Imageshare\Models\ResourceFile;

use Imageshare\Controllers\ResourceCollection as ResourceCollectionController;
use Imageshare\Controllers\PluginSettings as PluginSettingsController;

class Plugin {
    private $is_activated = false;

    private $file;
    private $version;
    private $is_admin;

    private $taxonomy_json;

    public function __construct(string $file, string $version, bool $is_admin = false) {
        if(get_option('imageshare_plugin_is_activated', null) !== null) {
            $this->is_activated = true;
        }

        $this->file = $file;
        $this->version = $version;
        $this->is_admin = $is_admin;

        $this->setup();
    }

    private function setup() {
        register_activation_hook($this->file, array($this, 'activate'));
        register_deactivation_hook($this->file, array($this, 'deactivate'));
        $this->setup_filters_and_hooks();
        add_action('init', array($this, 'init'));
    }

    private function load_controllers() {
        $this->controllers = (object) array();
        $this->controllers->resource_collection = new ResourceCollectionController();

        if ($this->is_admin) {
            $this->controllers->plugin_settings = new PluginSettingsController();
        }
    }

    public function activate() {
        if ($this->is_activated) {
            Logger::log("Plugin already active");
            return;
        }

        $this->load_taxonomy_data();
        $this->register_taxonomies();
        $this->register_taxonomy_terms();

        Logger::log("Activating plugin");
        $this->is_activated = true;
        add_option('imageshare_plugin_is_activated', $this->is_activated);
    }

    private function load_taxonomy_data() {
        $file = imageshare_asset_file('taxonomies.json');
        if ($contents = file_get_contents($file)) {
            $json = json_decode($contents);
            if ($json === null) {
                throw new \Exception("Unable to decode taxonomy json.");
            }
            $this->taxonomy_json = $json;
        } else {
            throw new \Exception("Unable to load taxonomy data.");
        }
    }

    public function deactivate() {
        Logger::log("Deactivating plugin");
        delete_option('imageshare_plugin_is_activated');
        $this->is_activated = false;
    }

    public static function i18n(string $text) {
        return __($text, 'imageshare');
    }

    public function init() {
        $this->load_taxonomy_data();
        $this->register_taxonomies();
        $this->register_custom_post_types();
        $this->load_controllers();
    }

    public static function model(string $model) {
        $ns = __NAMESPACE__;
        return "{$ns}\Models\\{$model}"; 
    }

    private function setup_filters_and_hooks() {
        add_filter('manage_btis_collection_posts_columns', array(self::model('ResourceCollection'), 'manage_columns'), 10, 1);
        add_action('manage_btis_collection_posts_custom_column', array(self::model('ResourceCollection'), 'manage_custom_column'), 10, 2);

        add_filter('manage_btis_resource_posts_columns', array(self::model('Resource'), 'manage_columns'), 10, 1);
        add_action('manage_btis_resource_posts_custom_column', array(self::model('Resource'), 'manage_custom_column'), 10, 2);

        add_filter('manage_btis_resource_file_posts_columns', array(self::model('ResourceFile'), 'manage_columns'), 10, 1);
        add_action('manage_btis_resource_file_posts_custom_column', array(self::model('ResourceFile'), 'manage_custom_column'), 10, 2);
    }

    private function register_custom_post_types() {
        register_post_type(Resource::type, Resource::typedef());
        register_post_type(ResourceFile::type, ResourceFile::typedef());
        register_post_type(ResourceCollection::type, ResourceCollection::typedef());
    }

    private function register_taxonomies() {
        $json = $this->taxonomy_json;
        foreach ($json as $taxonomy => $definition) {
            register_taxonomy(
                $taxonomy,
                $definition->applies_to,
                [
                    'label' => self::i18n($definition->plural),
                    'labels' => [
                        'singular_name' => self::i18n($definition->singular)
                    ],
                    'hierarchical' => $definition->hierarchical
                ]
            );
        }
    }

    private function register_taxonomy_terms() {
        $json = $this->taxonomy_json;

        foreach ($json as $taxonomy => $definition) {
            Logger::log("Generating taxonomy {$taxonomy}");

            $terms = $definition->terms;
            $terms_meta = (property_exists($definition, 'terms_meta'))
                ? $definition->terms_meta
                : (object) [];

            // flat hierarchy
            if (is_array($terms)) {
                foreach ($terms as $term_value) {
                    Logger::log("Inserting term {$term_value} into taxonomy {$taxonomy}");

                    $term = wp_insert_term(
                        $term_value,
                        $taxonomy,
                        ['slug' => join('-', ['imageshare', $term_value])]
                    ); 

                    if (is_wp_error($term)) {
                        Logger::log(sprintf(__('Error creating term %s in taxonomy %s', 'imageshare'), $term_value, $taxonomy));
                        Logger::log($term->get_error_message());
                    }

                    if (property_exists($terms_meta, $term_value)) {
                        foreach ($terms_meta->$term_value as $key => $value) {
                            add_term_meta($term['term_id'], $key, $value, true);
                        }
                    }
                }

                continue;
            }

            foreach ($terms as $parent => $children) {
                Logger::log("Inserting term {$parent} into hierarchical taxonomy {$taxonomy}");

                $term = wp_insert_term(
                    $parent,
                    $taxonomy,
                    ['slug' => join('-', ['imageshare', $parent])]
                );

                if (is_wp_error($term)) {
                    Logger::log(sprintf(__('Error creating term %s in taxonomy %s', 'imageshare'), $value, $taxonomy));
                    Logger::log($term->get_error_message());
                }

                if (property_exists($terms_meta, $parent)) {
                    foreach ($terms_meta->$parent as $key => $value) {
                        add_term_meta($term['term_id'], $key, $value, true);
                    }
                }


                if ($children === null) {
                    // this term has no children
                    continue;
                }

                foreach($children as $child_term_value) {
                    Logger::log("Inserting term {$child_term_value} (child term of {$parent}) into taxonomy {$taxonomy}");

                    $child_term = wp_insert_term(
                        $child_term_value, 
                        $taxonomy,
                        [
                            'parent' => $term['term_id'], 
                            'slug'   => join('-', ['imageshare', $parent, $child_term_value])
                        ]
                    );

                    if (is_wp_error($child_term)) {
                        Logger::log(sprintf(__('Error creating child term %s of term %s in taxonomy %s', 'imageshare'), $child_term_value, $parent, $taxonomy));
                        Logger::log($child_term->get_error_message());
                    }

                    if (property_exists($terms_meta, $child_term_value)) {
                        foreach ($terms_meta->$child_term_value as $key => $value) {
                            add_term_meta($child_term['term_id'], $key, $value, true);
                        }
                    }

                }
            } 
        }
    }

}


