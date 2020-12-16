<?php

namespace Imageshare\Models;

require_once imageshare_php_file('classes/class.logger.php');

use Imageshare\Logger;

class ResourceCollection {

    const type = 'btis_collection';

    public function __construct($post_id = null) {
        if (!empty($post_id)) {
            $this->get_post($post_id);
        }
    }

    public static function i18n(string $text) {
        return __($text, 'imageshare');
    }

    public static function typedef() {
        return array(
            'label' => self::i18n('Collections'),
            'labels' => array(
                'singular_name' => self::i18n('Collection')
            ),
            'description' => self::i18n('A categorised collection of resources.'),
            'capability_type' => 'post',
            'supports' => array(
                'title',
            ),
            'public' => true,
            'show_ui' => true,
            'has_archive' => 'collections'
        );
    }

    public static function get_featured(int $number) {
        $posts = get_posts([
            'post_type' => self::type,
            'numberposts' => $number,
            'post_status' => 'publish',
            'meta_key' => 'is_featured',
            'meta_value' => 1,
            'meta_compare' => '==='            

        ]);

        return array_map(function ($post) {
            return self::from_post($post);
        }, $posts);
    }

    public static function manage_columns(array $columns) {
        $columns['description'] = self::i18n('Description');
        $columns['contributor'] = self::i18n('Contributor');
        $columns['resources'] = self::i18n('Resources');
        $columns['featured'] = self::i18n('Featured');
        return $columns;
    }

    public static function manage_custom_column(string $column_name, int $post_id) {
        $post = new ResourceCollection($post_id);

        switch ($column_name) {
            case 'description':
                echo $post->description;
                break;

            case 'resources':
                $fbs = Model::children_by_status($post->resources());

                if (empty($fbs)) {
                    echo '0';
                    break;
                }

                echo join(', ', array_map(function($status) use($fbs) {
                    return "{$fbs[$status]} {$status}";
                }, array_keys($fbs)));
                break;

            case 'contributor':
                echo $post->contributor;
                break;

            case 'featured':
                echo $post->is_featured ? self::i18n('Yes') : self::i18n('No');
                break;
        }
    }

    public static function containing($resource_id) {
        $posts = get_posts([
            'numberposts'   => -1,
            'post_type'     => [ResourceCollection::type],
            'post_status'   => 'publish',
            'meta_key'      => 'resource_id',
            'meta_value'    => $resource_id
        ]);

        return array_map(function ($post) {
            return ResourceCollection::from_post($post);
        }, $posts);
    }

    public static function by_id($id) {
        $post = get_post($id);

        if ($post !== null && $post->post_type === static::type) {
            return self::from_post($post);
        }

        return null;
    }

    public static function from_post(\WP_Post $post) {
        $wrapped = new ResourceCollection();
        $wrapped->post = $post;
        $wrapped->load_custom_attributes();
        return $wrapped;
    }

    private function get_post($post_id) {
        $this->post = get_post($post_id);
        return $this->load_custom_attributes();
    }

    public function load_custom_attributes() {
        if (!empty($this->post)) {
            $this->id = $this->post->ID;
            $this->post_id = $this->post->ID;
            $this->title = $this->post->post_title;
            $this->permalink = get_permalink($this->post->ID);

            $this->description  = get_post_meta($this->post_id, 'description', true);
            $this->contributor  = get_post_meta($this->post_id, 'contributor', true);
            $this->resource_ids = $this->get_resource_ids();

            $this->is_featured = get_post_meta($this->post_id, 'is_featured', true) == 1;

            $thumbnail_id = get_post_meta($this->post_id, 'thumbnail', true);
            $this->thumbnail = wp_get_attachment_image_src($thumbnail_id)[0];

            return $this->id;
        }
        
        return null;
    }

    public function all_thumbnails() {
        $thumbnail_id = get_post_meta($this->post_id, 'thumbnail', true);
        $seen = [];
        $thumbnails = [];

        foreach (Model::get_image_sizes() as $size) {
            $thumbnail = wp_get_attachment_image_src($thumbnail_id, [$size['width'], $size['height']]);
            if (!empty($thumbnail) && !in_array($thumbnail[0], $seen)) {
                array_push($thumbnails, [
                    'src' => $thumbnail[0],
                    'width' => $thumbnail[1],
                    'height' => $thumbnail[2]
                ]);
                array_push($seen, $thumbnail[0]);
            }
        }

        return $thumbnails;
    }

    private function get_resource_ids() {
        $resource_ids = get_post_meta($this->post_id, 'resources', true);
        if (is_array($resource_ids)) {
            return $resource_ids;
        }
        return [];
    }

    public function published_resources() {
        return array_filter($this->resources(), function ($resource) {
            return $resource->post->post_status === 'publish';
        });
    }

    public function resources() {
        if (isset($this->_resources) && is_array($this->_resources)) {
            return $this->_resources;
        }

        return $this->_resources = array_reduce($this->resource_ids, function ($carry, $resource_id) {
            $resource = new Resource($resource_id);
            array_push($carry, $resource);
            return $carry;
        }, []);

    }

    public function acf_update_value($field, $value) {
        switch($field['name']) {
            case 'resources':
            // also store resource ids as flat database records for meta search
            // use $this->post->ID as the resource might not be finished creating
                delete_post_meta($this->post->ID, 'resource_id');
                foreach ($value as $file_id) {
                    add_post_meta($this->post->ID, 'resource_id', $file_id);
                }
            break;
        }

        return $value;
    }

    public static function remove_resource($resource_id) {
        $collections = self::containing($resource_id);
        foreach ($collections as $collection) {
            delete_post_meta($collection->post_id, 'resource_file', $resource_id);
            $other_resource_ids = array_filter($collection->get_resource_ids(), function ($id) use ($resource_id) {
                return $id != $resource_id;
            });

            update_field('resources', $other_resource_ids, $collection->post_id);
            $collection->resource_ids = $other_resource_ids;
            wpfts_post_reindex($collection->post_id);
        }
    }

    public static function on_acf_relationship_result($post_id, $related_post, $field) {
        // this can only be a file
        $resource = Resource::from_post($related_post);
        return sprintf('%s (%s)', $resource->title, $resource->subject);
    }

    public static function on_insert_post_data($post_id, $data) {
        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (!$post_id) {
            Logger::log('Post id 0 is auto_draft, skipping');
            return;
        }

        $collection = new ResourceCollection($post_id);
        $old_status = $collection->post->post_status;

        if ($old_status === 'publish') {
            Logger::log("Collection {$post_id} is already published, skipping filter");
            return;
        }

        $new_status = $data['post_status'];

        if ($new_status === 'publish') {
            Logger::log("Collection {$post_id} going from {$old_status} to {$new_status}");
            Model::force_publish_children($collection->resources());
        }
    }

    public function get_constituting_file_types() {
        // php can't do transform compare to save its life
        
        $seen_term_ids = [];
        $types = [];

        foreach ($this->published_resources() as $resource) {
            foreach ($resource->get_constituting_file_types() as $type) {
                if (in_array($type['term_id'], $seen_term_ids)) {
                    continue;
                }
                array_push($types, $type);
                array_push($seen_term_ids, $type['term_id']);
            }
        }
        
        return $types;
    }

    public function get_index_data($specific = null) {
        if ($specific === 'subject') {
            return array_unique(Model::flatten(array_map(function ($resource) {
                return Model::as_search_term('subject', $resource->subject);
            }, $this->published_resources())));
        }

        if ($specific === 'type') {
            return array_unique(Model::flatten(array_map(function ($resource) {
                return array_map(function ($type) {
                    return Model::as_search_term('type', $type);
                }, $resource->get_resource_file_types());
            }, $this->published_resources())));
        }

        if ($specific === 'accommodation') {
            return array_unique(Model::flatten(array_map(function ($resource) {
                return array_map(function ($accommodation) {
                    return Model::as_search_term('accommodation', $accommodation);
                }, Model::flatten($resource->get_resource_file_accommodations()));
            }, $this->published_resources())));
        }

        if ($specific === 'resources') {
            return array_unique(Model::flatten(array_map(function ($resource) {
                return [$resource->get_index_data(), $resource->get_index_data('files')];
            }, $this->published_resources())));
        }

        $resource_indices = array_map(function ($resource) {
            return $resource->get_index_data();
        }, $this->published_resources());

        return Model::flatten([$resource_indices, $this->description, $this->contributor]);
    }

}
