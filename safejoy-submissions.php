<?php
/**
 * Plugin Name: Safe Joy Submissions
 * Description: Allows users to submit name, description, and multiple links via a pop-up form. Admins can view all submissions in the dashboard.
 * Version: 1.1
 * Author: Safe Joy
 */

if (!defined('ABSPATH')) exit;

class SafeJoySubmissions {
    public function __construct() {
        add_action('init', [$this, 'register_submission_post_type']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('safejoy_submit', [$this, 'render_button']);
        add_action('wp_footer', [$this, 'render_form']);
        add_action('wp_ajax_safejoy_submit_form', [$this, 'handle_submission']);
        add_action('wp_ajax_nopriv_safejoy_submit_form', [$this, 'handle_submission']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('add_meta_boxes', [$this, 'add_links_metabox']);
    }

    public function register_submission_post_type() {
        register_post_type('safejoy_submission', [
            'labels' => [
                'name' => 'Safe Joy Submissions',
                'singular_name' => 'Safe Joy Submission'
            ],
            'public' => false,
            'show_ui' => true,
            'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-feedback'
        ]);
    }

    public function enqueue_assets() {
        wp_enqueue_style('safejoy-style', plugin_dir_url(__FILE__) . 'style.css');
        wp_enqueue_script('safejoy-script', plugin_dir_url(__FILE__) . 'script.js', ['jquery'], null, true);
        wp_localize_script('safejoy-script', 'safejoy_ajax', ['ajax_url' => admin_url('admin-ajax.php')]);
    }

    public function render_button() {
        return '<button id="safejoy-open-form" class="safejoy-btn">Submit Info</button>';
    }

    public function render_form() { ?>
        <div id="safejoy-modal" class="safejoy-modal">
            <div class="safejoy-modal-content">
                <span id="safejoy-close">&times;</span>
                <h2>Submit Your Info</h2>
                <form id="safejoy-form">
                    <input type="text" name="name" placeholder="Your Name" required><br>
                    <textarea name="description" placeholder="Description" required></textarea><br>

                    <div id="safejoy-links">
                        <input type="url" name="links[]" placeholder="Helpful Link (https://...)" required><br>
                    </div>
                    <button type="button" id="add-link">+ Add another link</button><br><br>

                    <button type="submit">Submit</button>
                </form>
                <div id="safejoy-message"></div>
            </div>
        </div>
    <?php }

    public function handle_submission() {
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        $links_raw = isset($_POST['links']) ? (array) $_POST['links'] : [];
        $links = array_map('esc_url_raw', array_filter($links_raw));

        $post_id = wp_insert_post([
            'post_type' => 'safejoy_submission',
            'post_title' => $name,
            'post_content' => $description,
            'post_status' => 'publish'
        ]);

        if ($post_id) {
            update_post_meta($post_id, 'links', $links);
            wp_send_json_success("Thank you, your info was submitted!");
        } else {
            wp_send_json_error("Submission failed. Please try again.");
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'Safe Joy Submissions',
            'Safe Joy Submissions',
            'manage_options',
            'edit.php?post_type=safejoy_submission',
            '',
            'dashicons-feedback'
        );
    }

    // Show links in a metabox in admin
    public function add_links_metabox() {
        add_meta_box(
            'safejoy_links_box',
            'Submitted Links',
            [$this, 'render_links_metabox'],
            'safejoy_submission',
            'normal',
            'high'
        );
    }

    public function render_links_metabox($post) {
        $links = get_post_meta($post->ID, 'links', true);
        if (!empty($links) && is_array($links)) {
            echo "<ul>";
            foreach ($links as $link) {
                echo "<li><a href='" . esc_url($link) . "' target='_blank'>" . esc_html($link) . "</a></li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No links submitted.</p>";
        }
    }
}

new SafeJoySubmissions();
