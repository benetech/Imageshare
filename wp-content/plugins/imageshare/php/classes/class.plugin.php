<?php

namespace Imageshare;

require_once imageshare_php_file('classes/class.logger.php');
require_once imageshare_php_file('classes/models/class.resource_collection.php');
require_once imageshare_php_file('classes/models/class.resource.php');
require_once imageshare_php_file('classes/models/class.resource_file.php');

use Imageshare\Logger;
use Imageshare\Models\ResourceCollection;
use Imageshare\Models\Resource;
use Imageshare\Models\ResourceFile;

class Plugin {
    private $is_activated = false;

    private $file;
    private $version;

    const TAXONOMY_HIERARCHY = array(
        'a11y_accs' => array(
            'Audio'     => array('Closed Captioning', 'High Contrast Audio', 'Sign Language', 'Transcript'),
            'Cognitive' => array('Leveled Content'),
            'Tactile'   => array('Braille', 'Textured'),
            'Visual'    => array('Audio Description', 'Image Description', 'Labels', 'Sonification', 'Tactile Graphics with Braille', 'Tactile Graphics with Text', 'Tactile Model')
        ),
        'file_types' => array(
            'Audio'     => array('AAC', 'MP3', 'WAV'),
            'Image'     => array('GIF', 'JPG', 'PNG', 'AVI', 'Generic', 'HDV', 'MP4', 'Quicktime'),
            'Other'     => array('Website', 'PDF', 'TXT', 'Word'),
            'Tactile'   => array('AI', 'OBJ', 'STL')
        ),
        'grade_ranges'  => array('Adult Education', 'College / University', 'Elementary School', 'General Literacy', 'High School', 'Middle School'),
        'languages'     => array(
            'All Languages',
            'Braille',
            'English' => array('English Audio', 'English Caption'),
            'Spanish',
            'French',
            'German'
        ),
        'licenses'          => array('CC BY 4.0', 'CC:BY', 'CC:BY-NC', 'CC:BY-NC-ND', 'CC:BY-NC-SA', 'CC:BY-ND', 'CC:BY-SA', 'DCMP Membership', 'GNU-GPL', 'OER'),
        'resource_types'    => array('2D Tactile Graphic', '3D Model', 'Audio File', 'Image', 'Lesson Plan', 'Manipulative', 'Text Document', 'URL', 'Video', 'Handmade Object'),
        'subjects'          => array(
            'Science'       => array('Biology', 'Chemistry', 'Physics', 'Environment', 'Earth', 'Astronomy', 'Algebra 1', 'Algebra 2', 'Calculus', 'Statistics'),
            'Engineering',
            'Technology' => array('Circuits', 'Computer Programming')
        )
    );

    public function __construct(string $file, string $version, bool $is_admin = false) {
        if(get_option('imageshare_plugin_is_activated', null) !== null) {
            $this->is_activated = true;
        }

        $this->file = $file;
        $this->version = $version;

        $this->setup();
    }

    private function setup() {
        register_activation_hook($this->file, array($this, 'activate'));
        register_deactivation_hook($this->file, array($this, 'deactivate'));
        $this->setup_filters_and_hooks();
        add_action('init', array($this, 'init'));
    }

    public function activate() {
        if ($this->is_activated) {
            Logger::log("Plugin already active");
            return;
        }

        $this->register_taxonomies();
        $this->register_taxonomy_terms();

        Logger::log("Activating plugin");
        $this->is_activated = true;
        add_option('imageshare_plugin_is_activated', $this->is_activated);
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
        $this->register_taxonomies();
        $this->register_custom_post_types();
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
        $this->register_accessibility_accommodations_taxonomy();
        $this->register_file_types_taxonomy();
        $this->register_grade_ranges_taxonomy();
        $this->register_languages_taxonomy();
        $this->register_licenses_taxonomy();
        $this->register_resource_types_taxonomy();
        $this->register_subjects_taxonomy();
    }

    public static function add_taxonomy_term(string $taxonomy, string $term, int $parent_term = null) {
        if ($parent_term === null) {
            return wp_insert_term(self::i18n($term), $taxonomy);
        } else {
            return wp_insert_term(self::i18n($term), $taxonomy, array('parent' => $parent_term));
        }
    }

    private function register_accessibility_accommodations_taxonomy() {
        register_taxonomy('a11y_accs', array('btis_resource_file'), array(
            'label' => self::i18n('Accessibility Accomodations'),
            'labels' => array(
                'singular_name' => self::i18n('Accessibility Accomodation')
            ),
            'hierarchical' => true
        ));
    }

    private function register_file_types_taxonomy() {
        register_taxonomy('file_types', array('btis_resource_file'), array(
            'label' => self::i18n('File Types'),
            'labels' => array(
                'singular_name' => self::i18n('File Type')
            ),
            'hierarchical' => true
        ));
    }

    private function register_grade_ranges_taxonomy() {
        register_taxonomy('grade_ranges', array('btis_resource_file'), array(
            'label' => self::i18n('Grade Ranges'),
            'labels' => array(
                'singular_name' => self::i18n('Grade Range')
            ),
            'hierarchical' => false
        ));
    }

    private function register_languages_taxonomy() {
        register_taxonomy('languages', array('btis_resource_file'), array(
            'label' => self::i18n('Languages'),
            'labels' => array(
                'singular_name' => self::i18n('Language')
            ),
            'hierarchical' => true
        ));
    }

    private function register_licenses_taxonomy() {
        register_taxonomy('licenses', array('btis_resource'), array(
            'label' => self::i18n('Licenses'),
            'labels' => array(
                'singular_name' => self::i18n('License')
            ),
            'hierarchical' => false
        ));
    }
 
    private function register_resource_types_taxonomy() {
         register_taxonomy('resource_types', array('btis_resource_file'), array(
            'label' => self::i18n('Resource Types'),
            'labels' => array(
                'singular_name' => self::i18n('Resource Type')
            ),
            'hierarchical' => false
        ));
    }

    private function register_subjects_taxonomy() {
        register_taxonomy('subjects', array('btis_resource'), array(
            'label' => self::i18n('Subjects'),
            'labels' => array(
                'singular_name' => self::i18n('Subject')
            ),
            'hierarchical' => true
        ));
    }

    private function register_taxonomy_terms() {
        foreach(array_keys(self::TAXONOMY_HIERARCHY) as $taxonomy) {
            $values = self::TAXONOMY_HIERARCHY[$taxonomy];
            foreach (array_keys($values) as $value) {
                if (is_string($value)) {
                    $term = wp_insert_term($value, $taxonomy);
                    foreach($values[$value] as $child_term) {
                        wp_insert_term($child_term, $taxonomy, array('parent' => $term['term_id']));
                    }
                } else {
                    $term = $values[$value];
                    wp_insert_term($term, $taxonomy); 
                }
            }
        }
    }

}


