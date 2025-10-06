<?php
/**
 * Plugin Name: Safe Joy Dynamic Submissions
 * Description: Create custom Safe Joy forms via shortcode. Users submit info, admins see results in dashboard.
 * Version: 2.1
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
        // Custom admin menu
        add_action('admin_menu', [$this, 'customize_admin_menu']);
        // Remove add new button for submissions
        add_action('admin_head', [$this, 'hide_add_new_submission']);
    }

    public function register_cpts() {
        // Forms
        register_post_type('safejoy_form', [
            'labels' => [
                'name' => 'Safe Joy Forms',
                'singular_name' => 'Safe Joy Form',
                'add_new' => 'Add New Form',
                'add_new_item' => 'Add New Form',
                'edit_item' => 'Edit Form',
                'view_item' => 'View Form',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // We'll add it manually
            'supports' => ['title'],
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'edit_posts',
            ],
            'map_meta_cap' => true,
        ]);

        // Submissions
        register_post_type('safejoy_submission', [
            'labels' => [
                'name' => 'Submissions',
                'singular_name' => 'Submission',
                'view_item' => 'View Submission',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // We'll add it as submenu
            'supports' => ['title', 'editor'],
            'capabilities' => [
                'create_posts' => 'do_not_allow', // Disable add new
                'edit_post' => 'edit_posts',
                'read_post' => 'read',
                'delete_post' => 'delete_posts',
            ],
            'map_meta_cap' => true,
        ]);
    }

    public function customize_admin_menu() {
        // Main menu
        add_menu_page(
            'Safe Joy Forms',
            'Safe Joy Forms',
            'edit_posts',
            'edit.php?post_type=safejoy_form',
            '',
            'dashicons-forms',
            20
        );

        // Submenu - Forms (default)
        add_submenu_page(
            'edit.php?post_type=safejoy_form',
            'All Forms',
            'All Forms',
            'edit_posts',
            'edit.php?post_type=safejoy_form'
        );

        // Submenu - Add New Form
        add_submenu_page(
            'edit.php?post_type=safejoy_form',
            'Add New Form',
            'Add New Form',
            'edit_posts',
            'post-new.php?post_type=safejoy_form'
        );

        // Submenu - View Submissions (no add new)
        add_submenu_page(
            'edit.php?post_type=safejoy_form',
            'View Submissions',
            'View Submissions',
            'edit_posts',
            'edit.php?post_type=safejoy_submission'
        );
    }

    public function hide_add_new_submission() {
        global $pagenow, $typenow;
        if ($pagenow === 'edit.php' && $typenow === 'safejoy_submission') {
            echo '<style>
                .page-title-action { display: none !important; }
                .wrap h1.wp-heading-inline + .page-title-action { display: none !important; }
            </style>';
        }
    }

    public function register_dynamic_shortcodes() {
        add_shortcode('safejoy', function($atts) {
            $atts = shortcode_atts(['form' => ''], $atts);
            $form_title = sanitize_text_field($atts['form']);

            if (!$form_title) {
                return '<p style="color:red;">Safe Joy: Missing form attribute.</p>';
            }

            // Check if form exists
            $form = get_page_by_title($form_title, OBJECT, 'safejoy_form');
            if (!$form) {
                return '<p style="color:red;">Safe Joy form not found: '.esc_html($form_title).'</p>';
            }

            return $this->render_form_button($form_title);
        });
    }

    public function enqueue_assets() {
        wp_enqueue_style('safejoy-style', plugin_dir_url(__FILE__) . 'style.css', [], '2.1');
        wp_enqueue_script('safejoy-script', plugin_dir_url(__FILE__) . 'script.js', ['jquery'], '2.1', true);
        wp_localize_script('safejoy-script', 'safejoy_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('safejoy_nonce')
        ]);
    }

    private function render_form_button($form_title) {
        $unique_id = 'safejoy-modal-' . uniqid();
        ob_start(); ?>
        <button class="safejoy-open-btn" data-modal="<?php echo esc_attr($unique_id); ?>" data-form="<?php echo esc_attr($form_title); ?>">
            Submit Info
        </button>

        <div id="<?php echo esc_attr($unique_id); ?>" class="safejoy-modal">
            <div class="safejoy-modal-content">
                <span class="safejoy-close">&times;</span>
                <h2>Submit Your Info</h2>
                <p class="safejoy-form-title"><?php echo esc_html($form_title); ?></p>
                <form class="safejoy-form" data-form="<?php echo esc_attr($form_title); ?>">
                    <label>Your Name *</label>
                    <input type="text" name="name" placeholder="Enter your name" required>
                    
                    <label>Description *</label>
                    <textarea name="description" placeholder="Tell us about your cause or need..." required rows="5"></textarea>
                    
                    <label>Helpful Links</label>
                    <div class="safejoy-links">
                        <input type="url" name="links[]" placeholder="https://example.com" required>
                    </div>
                    <button type="button" class="safejoy-add-link">+ Add Another Link</button>
                    
                    <input type="hidden" name="form_title" value="<?php echo esc_attr($form_title); ?>">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('safejoy_nonce'); ?>">
                    
                    <button type="submit" class="safejoy-submit-btn">Submit</button>
                </form>
                <div class="safejoy-message"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_submission() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'safejoy_nonce')) {
            wp_send_json_error('Security check failed.');
            return;
        }

        // Sanitize inputs
        $name = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $form_title = sanitize_text_field($_POST['form_title'] ?? '');
        
        if (empty($name) || empty($description) || empty($form_title)) {
            wp_send_json_error('Please fill in all required fields.');
            return;
        }

        $links_raw = isset($_POST['links']) ? (array) $_POST['links'] : [];
        $links = array_values(array_filter(array_map('esc_url_raw', $links_raw)));

        // Create submission
        $post_id = wp_insert_post([
            'post_type' => 'safejoy_submission',
            'post_title' => $name,
            'post_content' => $description,
            'post_status' => 'publish',
            'meta_input' => [
                'links' => $links,
                'form_title' => $form_title,
                'submission_date' => current_time('mysql'),
                'user_ip' => $this->get_user_ip()
            ]
        ]);

        if ($post_id) {
            wp_send_json_success('Thank you! Your information has been submitted successfully.');
        } else {
            wp_send_json_error('Submission failed. Please try again.');
        }
    }

    private function get_user_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        return sanitize_text_field($ip);
    }

    public function add_submission_columns($columns) {
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = 'Name';
        $new_columns['form_title'] = 'Form';
        $new_columns['links'] = 'Links';
        $new_columns['date'] = 'Date';
        return $new_columns;
    }

    public function render_submission_columns($column, $post_id) {
        if ($column === 'form_title') {
            echo esc_html(get_post_meta($post_id, 'form_title', true) ?: 'N/A');
        } elseif ($column === 'links') {
            $links = get_post_meta($post_id, 'links', true);
            if (is_array($links) && !empty($links)) {
                echo '<strong>' . count($links) . ' link(s)</strong><br>';
                foreach (array_slice($links, 0, 2) as $link) {
                    echo '<a href="' . esc_url($link) . '" target="_blank" rel="noopener">' . esc_html($link) . '</a><br>';
                }
                if (count($links) > 2) {
                    echo '<em>+' . (count($links) - 2) . ' more...</em>';
                }
            } else {
                echo 'No links';
            }
        }
    }
}

new SafeJoyDynamic();