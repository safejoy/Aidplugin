<?php
/**
 * Plugin Name: Safe Joy Dynamic Submissions
 * Description: Create custom Safe Joy forms via shortcode. Users submit info, admins see results in dashboard.
 * Version: 2.0
 * Author: Safe Joy
 */

if (!defined('ABSPATH')) exit;

class SafeJoyDynamic {
    public function __construct() {
        // Register CPTs
        add_action('init', [$this, 'register_cpts']);
        // Register dynamic shortcodes
        add_action('init', [$this, 'register_dynamic_shortcodes']);
        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        // AJAX
        add_action('wp_ajax_safejoy_submit_form', [$this, 'handle_submission']);
        add_action('wp_ajax_nopriv_safejoy_submit_form', [$this, 'handle_submission']);
        // Admin columns
        add_filter('manage_safejoy_submission_posts_columns', [$this, 'add_submission_columns']);
        add_action('manage_safejoy_submission_posts_custom_column', [$this, 'render_submission_columns'], 10, 2);
    }

    public function register_cpts() {
        // Forms
        register_post_type('safejoy_form', [
            'labels' => [
                'name' => 'Safe Joy Forms',
                'singular_name' => 'Safe Joy Form'
            ],
            'public' => false,
            'show_ui' => true,
            'supports' => ['title'],
            'menu_icon' => 'dashicons-forms'
        ]);

        // Submissions
        register_post_type('safejoy_submission', [
            'labels' => [
                'name' => 'Safe Joy Submissions',
                'singular_name' => 'Safe Joy Submission'
            ],
            'public' => false,
            'show_ui' => true,
            'supports' => ['title','editor'],
            'menu_icon' => 'dashicons-feedback'
        ]);
    }

    public function register_dynamic_shortcodes() {
        $forms = get_posts(['post_type' => 'safejoy_form','numberposts' => -1]);
        foreach ($forms as $form) {
            $shortcode = 'safejoy=' . sanitize_title($form->post_title);
            add_shortcode($shortcode, function() use ($form) {
                return $this->render_form_button($form->post_title);
            });
        }
    }

    public function enqueue_assets() {
        wp_enqueue_style('safejoy-style', plugin_dir_url(__FILE__) . 'style.css');
        wp_enqueue_script('safejoy-script', plugin_dir_url(__FILE__) . 'script.js', ['jquery'], null, true);
        wp_localize_script('safejoy-script', 'safejoy_ajax', ['ajax_url' => admin_url('admin-ajax.php')]);
    }

    private function render_form_button($form_title) {
        ob_start(); ?>
        <button class="safejoy-open-btn" data-form="<?php echo esc_attr($form_title); ?>">Submit Info</button>

        <div class="safejoy-modal" style="display:none;">
            <div class="safejoy-modal-content">
                <span class="safejoy-close">&times;</span>
                <h2>Submit Your Info (<?php echo esc_html($form_title); ?>)</h2>
                <form class="safejoy-form" data-form="<?php echo esc_attr($form_title); ?>">
                    <input type="text" name="name" placeholder="Your Name" required><br>
                    <textarea name="description" placeholder="Description" required></textarea><br>
                    <div class="safejoy-links">
                        <input type="url" name="links[]" placeholder="Helpful Link (https://...)" required><br>
                    </div>
                    <button type="button" class="add-link">+ Add another link</button><br><br>
                    <input type="hidden" name="form_title" value="<?php echo esc_attr($form_title); ?>">
                    <button type="submit">Submit</button>
                </form>
                <div class="safejoy-message"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_submission() {
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        $form_title = sanitize_text_field($_POST['form_title']);
        $links_raw = isset($_POST['links']) ? (array) $_POST['links'] : [];
        $links = array_map('esc_url_raw', array_filter($links_raw));

        $post_id = wp_insert_post([
            'post_type' => 'safejoy_submission',
            'post_title' => $name,
            'post_content' => $description,
            'post_status' => 'publish',
            'meta_input' => [
                'links' => $links,
                'form_title' => $form_title
            ]
        ]);

        if ($post_id) {
            wp_send_json_success("Thank you, your info was submitted!");
        } else {
            wp_send_json_error("Submission failed. Please try again.");
        }
    }

    public function add_submission_columns($columns) {
        $columns['form_title'] = 'Form';
        return $columns;
    }

    public function render_submission_columns($column, $post_id) {
        if ($column === 'form_title') {
            echo esc_html(get_post_meta($post_id, 'form_title', true));
        }
    }
}

new SafeJoyDynamic();
