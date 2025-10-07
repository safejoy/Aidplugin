<?php
/**
 * Plugin Name: Safe Joy Dynamic Submissions
 * Description: Create custom Safe Joy forms via shortcode. Users submit info, admins see results in dashboard.
 * Version: 2.3
 * Author: Safe Joy
 */

if (!defined('ABSPATH')) exit;

class SafeJoyDynamic {
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'safejoy_submissions';
        
        // Activation hook
        register_activation_hook(__FILE__, [$this, 'create_database_table']);
        
        // Register CPTs
        add_action('init', [$this, 'register_cpts']);
        // Register dynamic shortcodes
        add_action('init', [$this, 'register_dynamic_shortcodes']);
        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        // AJAX
        add_action('wp_ajax_safejoy_submit_form', [$this, 'handle_submission']);
        add_action('wp_ajax_nopriv_safejoy_submit_form', [$this, 'handle_submission']);
        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu'], 99);
        // Admin pages
        add_action('admin_init', [$this, 'handle_admin_actions']);
    }

    public function create_database_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_title varchar(255) NOT NULL,
            name varchar(255) NOT NULL,
            description text NOT NULL,
            links longtext,
            user_ip varchar(100),
            submission_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_title (form_title),
            KEY submission_date (submission_date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function register_cpts() {
        // Forms CPT
        register_post_type('safejoy_form', [
            'labels' => [
                'name' => 'Safe Joy Forms',
                'singular_name' => 'Safe Joy Form',
                'add_new' => 'Add New Form',
                'add_new_item' => 'Add New Form',
                'edit_item' => 'Edit Form',
                'view_item' => 'View Form',
                'all_items' => 'All Forms',
                'search_items' => 'Search Forms',
                'not_found' => 'No forms found',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'edit_posts',
            ],
            'map_meta_cap' => true,
        ]);
    }

    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            'Safe Joy Forms',
            'Safe Joy Forms',
            'edit_posts',
            'safejoy-main',
            [$this, 'render_dashboard_page'],
            'dashicons-forms',
            20
        );

        // Dashboard submenu
        add_submenu_page(
            'safejoy-main',
            'Dashboard',
            'Dashboard',
            'edit_posts',
            'safejoy-main'
        );

        // Forms submenu
        add_submenu_page(
            'safejoy-main',
            'All Forms',
            'All Forms',
            'edit_posts',
            'edit.php?post_type=safejoy_form'
        );

        // Add New Form submenu
        add_submenu_page(
            'safejoy-main',
            'Add New Form',
            'Add New Form',
            'edit_posts',
            'post-new.php?post_type=safejoy_form'
        );

        // Submissions submenu
        add_submenu_page(
            'safejoy-main',
            'View Submissions',
            'View Submissions',
            'edit_posts',
            'safejoy-submissions',
            [$this, 'render_submissions_page']
        );
    }

    public function handle_admin_actions() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        // Handle delete action
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && isset($_GET['page']) && $_GET['page'] === 'safejoy-submissions') {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_submission_' . intval($_GET['id']))) {
                wp_die('Security check failed');
            }

            global $wpdb;
            $id = intval($_GET['id']);
            $wpdb->delete($this->table_name, ['id' => $id], ['%d']);
            
            wp_redirect(admin_url('admin.php?page=safejoy-submissions&deleted=1'));
            exit;
        }
    }

    public function render_dashboard_page() {
        global $wpdb;
        $total_submissions = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        ?>
        <div class="wrap">
            <h1>Safe Joy Forms Dashboard</h1>
            <p>Welcome to Safe Joy Forms! Create custom forms and collect submissions from your community.</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 30px;">
                <div style="background: white; padding: 20px; border-left: 4px solid #667eea; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;">📝 Create Forms</h2>
                    <p>Create custom forms that generate shortcodes you can use anywhere on your site.</p>
                    <a href="<?php echo admin_url('post-new.php?post_type=safejoy_form'); ?>" class="button button-primary">Add New Form</a>
                    <a href="<?php echo admin_url('edit.php?post_type=safejoy_form'); ?>" class="button">View All Forms</a>
                </div>

                <div style="background: white; padding: 20px; border-left: 4px solid #10b981; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;">📊 View Submissions</h2>
                    <p>See all user submissions from your forms in one place.</p>
                    <p style="font-size: 24px; font-weight: bold; color: #10b981;"><?php echo intval($total_submissions); ?> Total Submissions</p>
                    <a href="<?php echo admin_url('admin.php?page=safejoy-submissions'); ?>" class="button button-primary">View Submissions</a>
                </div>

                <div style="background: white; padding: 20px; border-left: 4px solid #f59e0b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;">💡 How to Use</h2>
                    <ol style="margin-left: 20px;">
                        <li>Create a new form</li>
                        <li>Copy the shortcode</li>
                        <li>Paste it in any page or post</li>
                        <li>Collect submissions!</li>
                    </ol>
                    <p><strong>Example:</strong> <code>[safejoy form="aid"]</code></p>
                </div>
            </div>

            <div style="background: white; padding: 20px; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>Your Active Forms</h2>
                <?php
                $forms = get_posts([
                    'post_type' => 'safejoy_form',
                    'posts_per_page' => -1,
                    'post_status' => 'publish'
                ]);

                if ($forms) {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr><th>Form Name</th><th>Shortcode</th><th>Submissions</th></tr></thead><tbody>';
                    
                    foreach ($forms as $form) {
                        $form_title = $form->post_title;
                        $shortcode = '[safejoy form="' . esc_attr($form_title) . '"]';
                        
                        // Count submissions for this form from database
                        $count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$this->table_name} WHERE form_title = %s",
                            $form_title
                        ));
                        
                        echo '<tr>';
                        echo '<td><strong>' . esc_html($form_title) . '</strong></td>';
                        echo '<td><code>' . esc_html($shortcode) . '</code> <button class="button button-small" onclick="navigator.clipboard.writeText(\'' . esc_js($shortcode) . '\'); this.innerText=\'Copied!\'; setTimeout(()=>this.innerText=\'Copy\', 2000);">Copy</button></td>';
                        echo '<td>' . intval($count) . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody></table>';
                } else {
                    echo '<p>No forms created yet. <a href="' . admin_url('post-new.php?post_type=safejoy_form') . '">Create your first form</a>!</p>';
                }
                ?>
            </div>

            <div style="background: white; padding: 20px; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>Database Info</h2>
                <p><strong>Table Name:</strong> <code><?php echo esc_html($this->table_name); ?></code></p>
                <p>All submissions are stored in your WordPress database and can be accessed via phpMyAdmin.</p>
            </div>
        </div>
        <?php
    }

    public function render_submissions_page() {
        global $wpdb;

        if (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Submission deleted successfully.</p></div>';
        }

        // Pagination
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Get total count
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $total_pages = ceil($total_items / $per_page);

        // Get submissions
        $submissions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY submission_date DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        ?>
        <div class="wrap">
            <h1>View Submissions</h1>
            <p>Total Submissions: <strong><?php echo intval($total_items); ?></strong></p>

            <?php if ($submissions): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Name</th>
                            <th>Form</th>
                            <th>Description</th>
                            <th>Links</th>
                            <th>Date</th>
                            <th style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $submission): ?>
                            <tr>
                                <td><?php echo intval($submission->id); ?></td>
                                <td><strong><?php echo esc_html($submission->name); ?></strong></td>
                                <td><?php echo esc_html($submission->form_title); ?></td>
                                <td><?php echo esc_html(wp_trim_words($submission->description, 15)); ?></td>
                                <td>
                                    <?php
                                    $links = maybe_unserialize($submission->links);
                                    if (is_array($links) && !empty($links)) {
                                        echo '<strong>' . count($links) . ' link(s)</strong><br>';
                                        foreach (array_slice($links, 0, 2) as $link) {
                                            echo '<a href="' . esc_url($link) . '" target="_blank" rel="noopener">' . esc_html(wp_trim_words($link, 5, '...')) . '</a><br>';
                                        }
                                        if (count($links) > 2) {
                                            echo '<em>+' . (count($links) - 2) . ' more</em>';
                                        }
                                    } else {
                                        echo 'No links';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($submission->submission_date); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=safejoy-submissions&action=view&id=' . intval($submission->id)); ?>" class="button button-small">View</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=safejoy-submissions&action=delete&id=' . intval($submission->id)), 'delete_submission_' . intval($submission->id)); ?>" class="button button-small" onclick="return confirm('Are you sure you want to delete this submission?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links([
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo; Previous',
                                'next_text' => 'Next &raquo;',
                                'total' => $total_pages,
                                'current' => $current_page
                            ]);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <p>No submissions yet.</p>
            <?php endif; ?>
        </div>

        <?php
        // View single submission
        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
            
            if ($submission) {
                ?>
                <div class="wrap" style="margin-top: 30px;">
                    <h2>Submission Details - <?php echo esc_html($submission->name); ?></h2>
                    <div style="background: white; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <p><strong>ID:</strong> <?php echo intval($submission->id); ?></p>
                        <p><strong>Name:</strong> <?php echo esc_html($submission->name); ?></p>
                        <p><strong>Form:</strong> <?php echo esc_html($submission->form_title); ?></p>
                        <p><strong>Description:</strong><br><?php echo nl2br(esc_html($submission->description)); ?></p>
                        <p><strong>Links:</strong></p>
                        <ul>
                            <?php
                            $links = maybe_unserialize($submission->links);
                            if (is_array($links)) {
                                foreach ($links as $link) {
                                    echo '<li><a href="' . esc_url($link) . '" target="_blank" rel="noopener">' . esc_html($link) . '</a></li>';
                                }
                            }
                            ?>
                        </ul>
                        <p><strong>IP Address:</strong> <?php echo esc_html($submission->user_ip); ?></p>
                        <p><strong>Submitted:</strong> <?php echo esc_html($submission->submission_date); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=safejoy-submissions'); ?>" class="button">Back to All Submissions</a>
                    </div>
                </div>
                <?php
            }
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
        wp_enqueue_style('safejoy-style', plugin_dir_url(__FILE__) . 'style.css', [], '2.3');
        wp_enqueue_script('safejoy-script', plugin_dir_url(__FILE__) . 'script.js', ['jquery'], '2.3', true);
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
                //<p class="safejoy-form-title"><?php echo esc_html($form_title); ?></p>
                 <form class="safejoy-form" data-form="<?php echo esc_attr($form_title); ?>">
                    <label>Your Name <p style="color:red;">*</label>
                    <input type="text" name="name" placeholder="Enter your name" required>
                    
                    <label>Description <p style="color:red;">*</label>
                    <textarea name="description" placeholder="Tell us about your cause or need..." required rows="6"></textarea>
                    
                    <label>Helpful Links <p style="color:red;">*</label>
                    <div class="safejoy-links">
                        <input type="url" name="links[]" placeholder="https://example.com - https:// or http:// required" required>
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
        global $wpdb;
        
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

        // Insert into database
        $result = $wpdb->insert(
            $this->table_name,
            [
                'form_title' => $form_title,
                'name' => $name,
                'description' => $description,
                'links' => maybe_serialize($links),
                'user_ip' => $this->get_user_ip(),
                'submission_date' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result !== false) {
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
}

new SafeJoyDynamic();