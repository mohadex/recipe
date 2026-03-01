<?php
/**
 * Plugin Name: Recipe Schema Plugin
 * Description: Adds Schema.org Recipe JSON-LD to WordPress posts and pages via post meta fields.
 * Version: 1.0.0
 * Author: Codex
 * License: GPL-2.0-or-later
 * Text Domain: recipe-schema-plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Recipe_Schema_Plugin {
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'register_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'));
        add_action('wp_head', array($this, 'output_recipe_schema'));
    }

    public function register_meta_box() {
        add_meta_box(
            'recipe_schema_plugin_meta_box',
            __('Recipe Schema', 'recipe-schema-plugin'),
            array($this, 'render_meta_box'),
            array('post', 'page'),
            'normal',
            'default'
        );
    }

    public function render_meta_box($post) {
        wp_nonce_field('recipe_schema_plugin_nonce_action', 'recipe_schema_plugin_nonce');

        $fields = $this->get_fields();

        echo '<table class="form-table">';

        foreach ($fields as $key => $field) {
            $value = get_post_meta($post->ID, $key, true);
            echo '<tr>';
            echo '<th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($field['label']) . '</label></th>';
            echo '<td>';

            if ($field['type'] === 'textarea') {
                echo '<textarea id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" rows="4" class="widefat">' . esc_textarea($value) . '</textarea>';
                if (!empty($field['description'])) {
                    echo '<p class="description">' . esc_html($field['description']) . '</p>';
                }
            } else {
                echo '<input type="text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                if (!empty($field['description'])) {
                    echo '<p class="description">' . esc_html($field['description']) . '</p>';
                }
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }

    public function save_meta_box($post_id) {
        if (!isset($_POST['recipe_schema_plugin_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['recipe_schema_plugin_nonce'])), 'recipe_schema_plugin_nonce_action')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = $this->get_fields();

        foreach ($fields as $key => $field) {
            if (isset($_POST[$key])) {
                $raw_value = wp_unslash($_POST[$key]);
                $value = is_string($raw_value) ? trim($raw_value) : '';
                update_post_meta($post_id, $key, sanitize_textarea_field($value));
            } else {
                delete_post_meta($post_id, $key);
            }
        }
    }

    public function output_recipe_schema() {
        if (!is_singular()) {
            return;
        }

        $post_id = get_the_ID();

        if (!$post_id) {
            return;
        }

        $recipe_name = get_post_meta($post_id, 'recipe_name', true);
        $ingredients_raw = get_post_meta($post_id, 'recipe_ingredients', true);
        $instructions_raw = get_post_meta($post_id, 'recipe_instructions', true);

        if (empty($recipe_name) || empty($ingredients_raw) || empty($instructions_raw)) {
            return;
        }

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => $recipe_name,
            'description' => get_post_meta($post_id, 'recipe_description', true),
            'image' => get_post_meta($post_id, 'recipe_image', true),
            'author' => array(
                '@type' => 'Person',
                'name' => get_post_meta($post_id, 'recipe_author', true) ?: get_the_author_meta('display_name', (int) get_post_field('post_author', $post_id)),
            ),
            'datePublished' => get_the_date('c', $post_id),
            'prepTime' => get_post_meta($post_id, 'recipe_prep_time', true),
            'cookTime' => get_post_meta($post_id, 'recipe_cook_time', true),
            'totalTime' => get_post_meta($post_id, 'recipe_total_time', true),
            'recipeYield' => get_post_meta($post_id, 'recipe_yield', true),
            'recipeCategory' => get_post_meta($post_id, 'recipe_category', true),
            'recipeCuisine' => get_post_meta($post_id, 'recipe_cuisine', true),
            'keywords' => get_post_meta($post_id, 'recipe_keywords', true),
            'recipeIngredient' => $this->split_lines($ingredients_raw),
            'recipeInstructions' => $this->build_instructions($instructions_raw),
            'nutrition' => array(
                '@type' => 'NutritionInformation',
                'calories' => get_post_meta($post_id, 'recipe_calories', true),
            ),
        );

        $schema = $this->remove_empty_values($schema);

        if (empty($schema['recipeIngredient']) || empty($schema['recipeInstructions'])) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }

    private function split_lines($text) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $text);
        if (!is_array($lines)) {
            return array();
        }

        return array_values(array_filter(array_map('trim', $lines), static function ($line) {
            return $line !== '';
        }));
    }

    private function build_instructions($text) {
        $steps = $this->split_lines($text);
        $instructions = array();

        foreach ($steps as $step) {
            $instructions[] = array(
                '@type' => 'HowToStep',
                'text' => $step,
            );
        }

        return $instructions;
    }

    private function remove_empty_values($data) {
        if (!is_array($data)) {
            return $data;
        }

        $clean = array();

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = $this->remove_empty_values($value);
                if ($value === array()) {
                    continue;
                }
            }

            if ($value === '' || $value === null) {
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    private function get_fields() {
        return array(
            'recipe_name' => array('label' => __('Recipe name', 'recipe-schema-plugin'), 'type' => 'text'),
            'recipe_description' => array('label' => __('Description', 'recipe-schema-plugin'), 'type' => 'textarea'),
            'recipe_image' => array('label' => __('Image URL', 'recipe-schema-plugin'), 'type' => 'text'),
            'recipe_author' => array('label' => __('Recipe author', 'recipe-schema-plugin'), 'type' => 'text'),
            'recipe_prep_time' => array('label' => __('Prep time (ISO 8601)', 'recipe-schema-plugin'), 'type' => 'text', 'description' => __('Example: PT20M', 'recipe-schema-plugin')),
            'recipe_cook_time' => array('label' => __('Cook time (ISO 8601)', 'recipe-schema-plugin'), 'type' => 'text', 'description' => __('Example: PT45M', 'recipe-schema-plugin')),
            'recipe_total_time' => array('label' => __('Total time (ISO 8601)', 'recipe-schema-plugin'), 'type' => 'text', 'description' => __('Example: PT1H5M', 'recipe-schema-plugin')),
            'recipe_yield' => array('label' => __('Yield', 'recipe-schema-plugin'), 'type' => 'text', 'description' => __('Example: 4 servings', 'recipe-schema-plugin')),
            'recipe_category' => array('label' => __('Category', 'recipe-schema-plugin'), 'type' => 'text'),
            'recipe_cuisine' => array('label' => __('Cuisine', 'recipe-schema-plugin'), 'type' => 'text'),
            'recipe_keywords' => array('label' => __('Keywords', 'recipe-schema-plugin'), 'type' => 'text', 'description' => __('Comma-separated', 'recipe-schema-plugin')),
            'recipe_calories' => array('label' => __('Calories', 'recipe-schema-plugin'), 'type' => 'text', 'description' => __('Example: 250 calories', 'recipe-schema-plugin')),
            'recipe_ingredients' => array('label' => __('Ingredients', 'recipe-schema-plugin'), 'type' => 'textarea', 'description' => __('One ingredient per line', 'recipe-schema-plugin')),
            'recipe_instructions' => array('label' => __('Instructions', 'recipe-schema-plugin'), 'type' => 'textarea', 'description' => __('One step per line', 'recipe-schema-plugin')),
        );
    }
}

new Recipe_Schema_Plugin();
