<?php

namespace Imageshare\Models;

class Model {
    public static function as_search_term($term, $value) {
        return
            preg_replace('/_{2,}/', '_',
            preg_replace('/[^\w]+/', '',
            preg_replace('/\s+/', '_', implode('_', [$term, $value]
        ))));
    }

    public static function flatten(array $arr) {
        return array_reduce($arr, function ($c, $a) {
            return is_array($a) ? array_merge($c, Model::flatten($a)) : array_merge($c, [$a]);
        }, []);
    }

    public static function get_meta_term_name(string $post_id, string $meta_key, string $taxonomy, bool $reverse = false) {
        $term_id = get_post_meta($post_id, $meta_key, true);

        $term = get_term($term_id, $taxonomy);

        if (is_wp_error($term)) {
            error_log(sprintf('Unable to get term %s with term_id %d in taxonomy %s', $meta_key, $term_id, $taxonomy));
            return '';
        }

        if ($parent_id = $term->parent) {
            $parent_term = get_term($parent_id);
            return join(' - ', $reverse ? [$term->name, $parent_term->name] : [$parent_term->name, $term->name]);
        }

        return $term->name;
    }

    public static function get_hierarchical_terms($taxonomy, $hide_empty) {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'orderby' => 'term_group',
            'hide_empty' => $hide_empty,
        ]);

        $id_lookup = array_reduce($terms, function ($carry, $item) {
            $carry[$item->term_id] = $item;
            return $carry;
        }, []);

        $results = [];

        foreach ($terms as $term) {
            if ($parent_id = $term->parent) {
                $parent = $id_lookup[$parent_id];
                $results[$term->term_id] = [$term->name, $parent->name];
                continue;
            }

            $results[$term->term_id] = [$term->name];
        }

        return $results;
    }

    public static function get_terms($taxonomy, $hide_empty) {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'orderby' => 'name',
            'hide_empty' => $hide_empty
        ]);

        return array_reduce($terms, function ($carry, $item) {
            $carry[$item->term_id] = $item->name;
            return $carry;
        }, []);
    }

    public static function get_taxonomy_term_id($taxonomy, $term_name) {
        $term = get_term_by('name', $term_name, $taxonomy);

        if ($term === false) {
            throw new \Exception(sprintf(__('Term %s was not found in taxonomy %s', 'imageshare'), $term_name, $taxonomy));
        }

        return $term->term_id;
    }

    public static function get_taxonomy_term_name($term_id, $taxonomy) {
        $term = get_term($term_id, $taxonomy);

        if ($term === null || is_wp_error($term)) {
            return null;
        }

        return $term->name;
    }

    public static function finish_importing($post_id) {
        // remove importing flag
        // this is done so the search indexing hook doesn't cause problems
        delete_post_meta($post_id, 'importing');
    }

    public static function is_importing($post_id) {
        return get_post_meta($post_id, 'importing', false);
    }
}

