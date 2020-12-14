<?php

namespace Imageshare;

require_once imageshare_php_file('classes/class.logger.php');

require_once imageshare_php_file('classes/models/class.resource_collection.php');
require_once imageshare_php_file('classes/models/class.resource.php');
require_once imageshare_php_file('classes/models/class.term.php');
require_once imageshare_php_file('classes/models/class.resource_file.php');

require_once imageshare_php_file('classes/controllers/class.resource_collection.php');
require_once imageshare_php_file('classes/controllers/class.plugin_settings.php');
require_once imageshare_php_file('classes/controllers/class.search.php');
require_once imageshare_php_file('classes/controllers/class.post.php');
require_once imageshare_php_file('classes/controllers/class.json_api.php');

use Imageshare\Logger;

use Imageshare\Models\Term;
use Imageshare\Models\ResourceCollection;
use Imageshare\Models\Resource;
use Imageshare\Models\ResourceFile;

use Imageshare\Controllers\ResourceCollection as ResourceCollectionController;
use Imageshare\Controllers\PluginSettings as PluginSettingsController;
use Imageshare\Controllers\Search as SearchController;
use Imageshare\Controllers\Post as PostController;
use Imageshare\Controllers\JSONAPI as JSONAPIController;

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
        $this->controllers->search = new SearchController();
        $this->controllers->post = new PostController();
        $this->controllers->json_api = new JSONAPIController();

        if ($this->is_admin) {
            $this->controllers->plugin_settings = new PluginSettingsController();
        }
    }

    public static function admin_enqueue_styles_and_scripts() {
        $file = dirname(plugin_dir_url(__FILE__), 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'admin.css';
        wp_enqueue_style('imageshare-admin-css', $file, [], false, 'screen, print');

        $file = dirname(plugin_dir_url(__FILE__), 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'acf_imageshare_relationship.js';
        wp_enqueue_script('imageshare-admin-acf_imageshare_relationship-js', $file, ['acf-input', 'select2']);

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

    public function add_action_links($links) {
        $settings_links = array(
            sprintf('<a href="%s">%s</a>', admin_url('options-general.php?page=imageshare_settings'), self::i18n('Settings')),
        );

        return array_merge($links, $settings_links);
    }

    public function on_wp() {
        global $wp_query;

        // return 404 when querying a ResourceFile post
        if ($wp_query->query_vars['post_type'] === ResourceFile::type && $wp_query->is_singular) {
            $wp_query->set_404();
            status_header(404);
        }
    }

    private function register_acf_fields() {
        include_once(imageshare_php_file('classes/class.acf.relationship.php'));
    }

    private function set_acf_hooks_and_filters() {
        add_filter('acf/update_value', [$this, 'on_acf_update_value'], 20, 3);
        add_filter('acf/fields/relationship/result', [$this, 'on_acf_relationship_result'], 20, 4);
    }

    public function on_acf_relationship_result($text, $post, $field, $post_id) {
        $post_type = get_post_type($post_id);

        switch (get_post_type($post_id)) {
            case Resource::type:
                $text = Resource::on_acf_relationship_result($post_id, $post, $field);
                break;
            case ResourceCollection::type:
                $text = ResourceCollection::on_acf_relationship_result($post_id, $post, $field);
                break;
        }

        return $text;
    }

    public function on_acf_update_value($value, $post_id, $field) {
        $matches = [];

        if (preg_match('/^term_(\d+)$/', $post_id, $matches)) {
            return Term::update_meta($matches[1], $field, $value);
        }

        $post = get_post($post_id);

        if ($post->post_type === Resource::type) {
            return Resource::from_post($post)->acf_update_value($field, $value);
        }

        if ($post->post_type === ResourceCollection::type) {
            return ResourceCollection::from_post($post)->acf_update_value($field, $value);
        }

        return $value;
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
        $this->register_acf_fields();
        $this->set_acf_hooks_and_filters();

        add_filter('plugin_action_links_' . plugin_basename($this->file), [$this, 'add_action_links']);
        add_filter('wp_insert_post_data', [$this, 'on_insert_post_data'], 2, 10);
        add_filter('save_post_btis_resource_file', [self::model('ResourceFile'), 'on_save_post'], 3, 10);
        add_filter('delete_post', [$this, 'on_delete_post'], 1, 10);
        add_filter('edited_term', [$this, 'on_edited_term',], 3, 10);
        add_action('wp', [$this, 'on_wp']);
        add_action('pre_get_posts', [$this, 'patch_admin_search']);
        add_action('posts_join', [$this, 'patch_admin_search_join']);
        add_action('posts_where', [$this, 'patch_admin_search_where']);
        add_action('posts_groupby', [$this, 'patch_admin_search_groupby']);
    }

    public function is_admin_search() {
        global $pagenow;
        return is_admin() &&
            $pagenow === 'edit.php' &&
            (!isset($_POST['action']) || !(isset($_POST['action']) && $_POST['action'] === 'wpftsi_submit_testsearch'))
        ;
    }

    public function patch_admin_search_join($join) {
        global $wpdb;

        if (self::is_admin_search()) {
            $join .= 'LEFT JOIN ' . $wpdb->postmeta . ' ON ' . $wpdb->posts . '.ID = ' . $wpdb->postmeta . '.post_id ';
        }

        return $join;
    }

    public function patch_admin_search_groupby($groupby) {
        global $wpdb;

        if (self::is_admin_search()) {
            $groupby = "{$wpdb->posts}.ID";
        }

        return $groupby;
    }

    public function patch_admin_search_where($where) {
        global $wpdb;

        if (self::is_admin_search()) {
            $where = preg_replace(
                "/\(\s*" . $wpdb->posts . ".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
                "(" . $wpdb->posts . ".post_title LIKE $1) OR (" . $wpdb->postmeta . ".meta_value LIKE $1)", $where
            );
        }

        return $where;
    }

    public function patch_admin_search($query) {
        global $pagenow;

        if (self::is_admin_search()) {
            $query->set('wpfts_disable', 1);
        }

        return $query;
    }

    public function on_delete_post($post_id) {
        $post_type = get_post_type($post_id);

        switch ($post_type) {
            case Resource::type:
                ResourceCollection::remove_resource($post_id);
                break;
             case ResourceFile::type:
                Resource::remove_resource_file($post_id);
                break;
        }
    }

    public function on_edited_term($term_id, $tt_id, $taxonomy_name) {
        $taxonomy = get_taxonomy($taxonomy_name);

        Logger::log("Modified term {$term_id} ({$taxonomy_name})");

        foreach ($taxonomy->object_type as $post_type) {
            switch ($post_type) {
                case Resource::type:
                case ResourceFile::type:
                    try {
                        $this->reindex_posts_with_term($term_id, $taxonomy_name);
                    } catch (Error $e) {
                        Logger::log("Error reindexing posts: " . $e->getMessage());
                    }
            }
        }
    }

    private function reindex_posts_with_term($term_id, $taxonomy) {
        $posts = get_posts([
            'post_type' => [Resource::type, ResourceFile::type],
            'post_status' => 'publish',
            'tax_query' => [
                [
                    'taxonomy' => $taxonomy,
                    'terms' => $term_id
                ]
            ]
        ]);

        Logger::log(sprintf('Found %d posts for term %d (%s)', count($posts), $term_id, $taxonomy));

        foreach ($posts as $post) {
            switch ($post->post_type) {
                case Resource::type:
                    Resource::from_post($post)->reindex();
                    break;
                case ResourceFile::type:
                    ResourceFile::from_post($post)->reindex();
                    break;
            }
        }
    }

    public function on_insert_post_data($data, $postarr) {
        switch ($postarr['post_type']) {
            case Resource::type:
                Resource::on_insert_post_data($postarr['ID'], $postarr);
                break;
            case ResourceCollection::type:
                ResourceCollection::on_insert_post_data($postarr['ID'], $postarr);
                break;
        }

        return $data;
    }

    public static function model(string $model) {
        $ns = __NAMESPACE__;
        return "{$ns}\Models\\{$model}"; 
    }

    public static function remove_edit_metaboxes() {
        $box_ids = ['a11y_accsdiv', 'tagsdiv-languages', 'tagsdiv-file_types', 'file_formatsdiv', 'tagsdiv-licenses'];
        foreach ($box_ids as $id) {
            remove_meta_box($id, ResourceFile::type, 'side');
        }
        
        remove_meta_box('subjectsdiv', Resource::type, 'side');
    }

    private function setup_filters_and_hooks() {
        add_action('add_meta_boxes', array($this, 'remove_edit_metaboxes'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_styles_and_scripts'));

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
            $taxonomy = register_taxonomy(
                $taxonomy,
                $definition->applies_to,
                [
                    'label' => self::i18n($definition->plural),
                    'labels' => [
                        'singular_name'     => self::i18n($definition->singular),
                        'search_items'      => self::i18n('Search ' . $definition->plural),
                        'popular_items'     => self::i18n('Popular ' . $definition->plural),
                        'all_items'         => self::i18n('All ' . $definition->plural),
                        'parent_item'       => self::i18n('Parent ' . $definition->singular),
                        'parent_item_colon' => self::i18n('Parent ' . $definition->singular . ':'),
                        'edit_item'         => self::i18n('Edit ' . $definition->singular),
                        'view_item'         => self::i18n('View ' . $definition->singular),
                        'update_item'       => self::i18n('Update ' . $definition->singular),
                        'add_new_item'      => self::i18n('Add New ' . $definition->singular),
                        'new_item_name'     => self::i18n('New ' . $definition->singular . ' name'),
                        'back_to_items'     => self::i18n('Back to ' . $definition->plural),
                        'not_found'         => self::i18n('No ' . $definition->plural . ' found')
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
            $terms_meta = (property_exists($definition, 'meta'))
                ? $definition->meta
                : (object) [];

            if (property_exists($terms_meta, '$self')) {
                foreach ($terms_meta->{'$self'} as $key => $value) {
                    update_option("taxonomy_{$taxonomy}_{$key}", $value);
                }
            }

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
                        continue;
                    }

                    if (property_exists($terms_meta, $term_value)) {
                        foreach ($terms_meta->$term_value as $key => $value) {
                            update_field($key, $value, "{$taxonomy}_{$term['term_id']}");
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
                    Logger::log(sprintf(__('Error creating term %s in taxonomy %s', 'imageshare'), $parent, $taxonomy));
                    Logger::log($term->get_error_message());
                    continue;
                }

                if (property_exists($terms_meta, $parent)) {
                    foreach ($terms_meta->$parent as $key => $value) {
                        update_field($key, $value, "{$taxonomy}_{$term['term_id']}");
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
                        continue;
                    }

                    if (property_exists($terms_meta, $child_term_value)) {
                        foreach ($terms_meta->$child_term_value as $key => $value) {
                            update_field($key, $value, "{$taxonomy}_{$child_term['term_id']}");
                        }
                    }

                }
            } 
        }
    }

}


