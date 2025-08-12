<?php
/**
 * Plugin Name: RAE Learndash Subscribers Exporter
 * Description: Export emails of WordPress subscribers enrolled in any Learndash course.
 * Version: 1.1.0
 * Author: Rae Agency
 * Author URI: https://www.rae.fi
 * Text Domain: rae-ld-exporter
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
if (!defined('RAE_LD_EXPORTER_VERSION')) {
    define('RAE_LD_EXPORTER_VERSION', '1.1.0');
}
if (!defined('RAE_LD_EXPORTER_URL')) {
    define('RAE_LD_EXPORTER_URL', plugin_dir_url(__FILE__));
}
if (!defined('RAE_LD_EXPORTER_PATH')) {
    define('RAE_LD_EXPORTER_PATH', plugin_dir_path(__FILE__));
}

// Hook into admin_init to handle export before headers are sent
add_action('admin_init', function () {
    if (isset($_POST['export_ld_emails']) && check_admin_referer('ld_export_action', 'ld_export_nonce')) {
        $export_type = sanitize_text_field($_POST['export_type'] ?? 'all');
        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        ld_export_subscriber_emails($export_type, $course_id);
    }
});

// Add submenu under Users
add_action('admin_menu', function () {
    add_users_page(
    __('Export Learndash Subscribers', 'rae-ld-exporter'),
    __('Export Learndash Subscribers', 'rae-ld-exporter'),
        'manage_options',
        'ld-subscribers-export',
        'ld_export_page'
    );
});

// Render the admin page
function ld_export_page() {
    // Get all LearnDash courses
    $courses = [];
    if (function_exists('ld_course_list')) {
        $args = [
            'post_type' => 'sfwd-courses',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ];
        $course_query = new WP_Query($args);
        if ($course_query->have_posts()) {
            while ($course_query->have_posts()) {
                $course_query->the_post();
                $courses[get_the_ID()] = get_the_title();
            }
            wp_reset_postdata();
        }
    }
    ?>
    <div class="wrap">
    <h1><?php echo esc_html__('Export Learndash Subscribers', 'rae-ld-exporter'); ?></h1>
        
        <div class="card rae-ld-exporter" id="rae-ld-exporter">
            <h2><?php echo esc_html__('Export Options', 'rae-ld-exporter'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('ld_export_action', 'ld_export_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Export Type:', 'rae-ld-exporter'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Export Type</legend>
                                <label>
                                    <input type="radio" name="export_type" value="all" checked>
                                    <?php echo esc_html__('All Subscribers', 'rae-ld-exporter'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="export_type" value="enrolled">
                                    <?php echo esc_html__('All Subscribers Enrolled in Any Course', 'rae-ld-exporter'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="export_type" value="specific">
                                    <?php echo esc_html__('Subscribers Enrolled in Specific Course', 'rae-ld-exporter'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr id="course-selection">
                        <td colspan="2" class="rae-rowbox-cell">
                            <div class="rae-rowbox">
                                <label for="course_id" class="rae-rowbox-label"><?php echo esc_html__('Select Course', 'rae-ld-exporter'); ?></label>
                                <select name="course_id" id="course_id">
                                    <option value="">-- <?php echo esc_html__('Select Course', 'rae-ld-exporter'); ?> --</option>
                                    <?php foreach ($courses as $id => $title): ?>
                                        <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($courses)): ?>
                                    <div class="notice notice-warning inline" style="margin-top:8px;">
                                        <p><?php echo esc_html__('No Learndash courses found.', 'rae-ld-exporter'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="export_ld_emails" class="button button-primary" value="<?php echo esc_attr__('Export to Excel', 'rae-ld-exporter'); ?>">
                    <span id="export-count-display" class="count-display" role="status" aria-live="polite"></span>
                </p>
            </form>
        </div>
    </div>
    <?php
}

// Export function
function ld_export_subscriber_emails($export_type = 'all', $course_id = 0) {
    // Verify LearnDash is active
    if (!function_exists('learndash_user_get_enrolled_courses') && $export_type !== 'all') {
        wp_die(esc_html__('Learndash is not active. Please activate Learndash to use this feature.', 'rae-ld-exporter'));
    }
    
    // Set up the query
    $args = [
        'role' => 'subscriber',
        'number' => -1,
        'fields' => ['ID', 'user_email', 'display_name', 'user_registered']
    ];
    
    // Get users
    $users = get_users($args);
    
    // Set up filename
    $filename = 'learndash-subscribers-' . date('Y-m-d') . '.csv';
    
    // Get course title if specific course
    $course_title = '';
    if ($export_type === 'specific' && $course_id > 0) {
        $course_title = get_the_title($course_id);
        $filename = 'learndash-course-' . sanitize_title($course_title) . '-' . date('Y-m-d') . '.csv';
    }
    
    // Set headers for Excel CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // BOM (Byte Order Mark) for Excel UTF-8 support
    fputs($output, "\xEF\xBB\xBF");
    
    // Define headers based on export type
    $headers = [
        __('User ID', 'rae-ld-exporter'),
        __('Email', 'rae-ld-exporter'),
        __('Name', 'rae-ld-exporter'),
        __('Registration Date', 'rae-ld-exporter'),
    ];
    
    if ($export_type === 'enrolled' || $export_type === 'specific') {
        $headers[] = __('Enrolled Courses', 'rae-ld-exporter');
        
        if ($export_type === 'specific') {
            $headers[] = __('Course Progress', 'rae-ld-exporter');
            $headers[] = __('Course Status', 'rae-ld-exporter');
            $headers[] = __('Enrollment Date', 'rae-ld-exporter');
        }
    }
    
    // Write headers
    fputcsv($output, $headers);
    
    // Process each user
    foreach ($users as $user) {
        $user_data = [$user->ID, $user->user_email, $user->display_name, $user->user_registered];
        
        // Get enrolled courses
        $include_user = true;
        $enrolled_courses = [];
        
        if ($export_type === 'enrolled' || $export_type === 'specific') {
            if (function_exists('learndash_user_get_enrolled_courses')) {
                $enrolled_courses = learndash_user_get_enrolled_courses($user->ID);
                
                // For Open courses, Learndash may grant access without explicit enrollment.
                // Require explicit enrollment (course_*_access_from meta) to include.
                if ($export_type === 'enrolled') {
                    $include_user = ld_user_has_any_explicit_course_enrollment($user->ID, $enrolled_courses);
                } elseif ($export_type === 'specific') {
                    $include_user = ld_user_has_explicit_course_enrollment($user->ID, $course_id);
                }
            } else {
                // LearnDash functions not available
                $include_user = ($export_type === 'all');
            }
            
            if ($include_user && $export_type === 'enrolled') {
                $course_titles = [];
                foreach ($enrolled_courses as $c_id) {
                    $course_titles[] = get_the_title($c_id);
                }
                $user_data[] = implode(', ', $course_titles);
            }
            
            // Add course-specific data for specific course export
            if ($include_user && $export_type === 'specific' && $course_id > 0) {
                // For "specific" export, add enrolled courses
                $course_titles = [];
                foreach ($enrolled_courses as $c_id) {
                    $course_titles[] = get_the_title($c_id);
                }
                $user_data[] = implode(', ', $course_titles);
                
                // Get course progress
                $progress = 0;
                if (function_exists('learndash_course_progress')) {
                    $course_progress = learndash_course_progress([
                        'user_id'   => $user->ID,
                        'course_id' => $course_id,
                        'array'     => true
                    ]);
                    $progress = $course_progress['percentage'] ?? 0;
                }
                $user_data[] = $progress . '%';
                
                // Get course status
                $status = '';
                if (function_exists('learndash_course_status')) {
                    $status = learndash_course_status($course_id, $user->ID);
                }
                $user_data[] = $status;
                
                // Get enrollment date (using user meta or best estimate)
                $enrollment_date = '';
                $access_ts = ld_get_course_access_from($user->ID, $course_id);
                if (!empty($access_ts)) {
                    $enrollment_date = date('Y-m-d H:i:s', $access_ts);
                }
                $user_data[] = $enrollment_date;
            }
        }
        
        // Write user data if they should be included
        if ($include_user) {
            fputcsv($output, $user_data);
        }
    }
    
    // Close file pointer
    fclose($output);
    exit;
}

// AJAX handler for getting subscriber counts
add_action('wp_ajax_ld_get_subscriber_count', 'ld_get_subscriber_count_ajax');

function ld_get_subscriber_count_ajax() {
    // Check nonce for security
    if (!check_ajax_referer('ld_count_nonce', 'nonce', false)) {
        wp_send_json_error(esc_html__('Invalid nonce', 'rae-ld-exporter'));
        return;
    }

    $export_type = sanitize_text_field($_POST['export_type'] ?? 'all');
    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    
    try {
        $count = ld_get_subscriber_count($export_type, $course_id);
    wp_send_json_success(['count' => $count]);
    } catch (Exception $e) {
        error_log('LD Exporter Count Error: ' . $e->getMessage());
    wp_send_json_error(sprintf(esc_html__('Error calculating count: %s', 'rae-ld-exporter'), $e->getMessage()));
    }
}

// Function to get subscriber count based on export type
function ld_get_subscriber_count($export_type = 'all', $course_id = 0) {
    try {
        // Set up the query for subscribers
        $args = [
            'role' => 'subscriber',
            'number' => -1,
            'fields' => 'ID' // Get only IDs as array
        ];
        
        // Get all subscriber IDs
        $user_ids = get_users($args);
        $total_subscribers = count($user_ids);
        
        if ($export_type === 'all') {
            return $total_subscribers;
        }
        
        // For enrolled and specific course counts, we need LearnDash functions
        if (!function_exists('learndash_user_get_enrolled_courses')) {
            return 0; // LearnDash not available
        }
        
        $count = 0;
        
        foreach ($user_ids as $user_id) {
            $enrolled_courses = learndash_user_get_enrolled_courses($user_id);

            if ($export_type === 'enrolled') {
                if (ld_user_has_any_explicit_course_enrollment($user_id, $enrolled_courses)) {
                    $count++;
                }
            } elseif ($export_type === 'specific' && $course_id > 0) {
                if (ld_user_has_explicit_course_enrollment($user_id, $course_id)) {
                    $count++;
                }
            }
        }
        
        return $count;
        
    } catch (Exception $e) {
        error_log('LD Exporter Count Calculation Error: ' . $e->getMessage());
        throw $e;
    }
}

// Helpers: explicit enrollment detection (ignores access from Open courses)
function ld_get_course_access_from($user_id, $course_id) {
    // Primary key used by LearnDash
    $ts = get_user_meta($user_id, 'course_' . $course_id . '_access_from', true);
    if (!empty($ts)) {
        return intval($ts);
    }
    // Back-compat or alternate key some installs used
    $ts_alt = get_user_meta($user_id, 'learndash_course_' . $course_id . '_access_from', true);
    if (!empty($ts_alt)) {
        return intval($ts_alt);
    }
    return 0;
}

function ld_user_has_explicit_course_enrollment($user_id, $course_id) {
    if (empty($course_id)) return false;
    return ld_get_course_access_from($user_id, $course_id) > 0;
}

function ld_user_has_any_explicit_course_enrollment($user_id, $enrolled_courses = []) {
    // If we already have LD's enrolled courses, filter them; otherwise, scan user meta keys.
    if (is_array($enrolled_courses) && !empty($enrolled_courses)) {
        foreach ($enrolled_courses as $cid) {
            if (ld_user_has_explicit_course_enrollment($user_id, $cid)) {
                return true;
            }
        }
        return false;
    }
    // Fallback: scan user meta for any course_*_access_from
    $all_meta = get_user_meta($user_id);
    foreach ($all_meta as $key => $vals) {
        if (strpos($key, 'course_') === 0 && str_ends_with($key, '_access_from')) {
            if (!empty($vals) && intval($vals[0]) > 0) {
                return true;
            }
        }
    }
    return false;
}

// Enqueue scripts and styles for admin page
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'users_page_ld-subscribers-export') {
        return;
    }

    wp_enqueue_style('ld-exporter-admin', plugin_dir_url(__FILE__) . 'admin.css', [], '1.0');

    wp_enqueue_script('jquery');
    wp_enqueue_script('ld-exporter-admin', plugin_dir_url(__FILE__) . 'admin.js', ['jquery'], RAE_LD_EXPORTER_VERSION, true);

    // Localize script with AJAX URL and nonce
    wp_localize_script('ld-exporter-admin', 'ld_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ld_count_nonce'),
        'strings' => [
            'select_course_prompt' => esc_html__('Select a course to see count', 'rae-ld-exporter'),
            'will_export' => esc_html__('Will export %s subscribers', 'rae-ld-exporter'),
            'error_prefix' => esc_html__('Error:', 'rae-ld-exporter'),
            'connection_error' => esc_html__('Connection error', 'rae-ld-exporter'),
        ],
    ]);
});

// Load translations
add_action('plugins_loaded', function() {
    load_plugin_textdomain('rae-ld-exporter', false, dirname(plugin_basename(__FILE__)) . '/languages');
});
?>