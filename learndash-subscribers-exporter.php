<?php
/**
 * Plugin Name: RAE Learndash Subscribers Exporter
 * Description: Export emails of WordPress subscribers enrolled in any Learndash course.
 * Version: 1.2.0
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
    define('RAE_LD_EXPORTER_VERSION', '1.2.0');
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
        $include_enrolled = !empty($_POST['include_enrolled']);
        ld_export_subscriber_emails($export_type, $course_id, $include_enrolled);
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

                    <tr id="include-enrolled-row" style="display:none;">
                        <td colspan="2" class="rae-rowbox-cell">
                            <div class="rae-rowbox">
                                <label for="include_enrolled" class="rae-rowbox-label"><?php echo esc_html__('Include explicit enrollments', 'rae-ld-exporter'); ?></label>
                                <label>
                                    <input type="checkbox" name="include_enrolled" id="include_enrolled" value="1">
                                    <?php echo esc_html__('Also include users explicitly enrolled without progress', 'rae-ld-exporter'); ?>
                                </label>
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
function ld_export_subscriber_emails($export_type = 'all', $course_id = 0, $include_enrolled = false) {
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
    if ($export_type === 'specific') {
        // For specific course export, use the exact schema requested
        $headers = [
            __('User ID', 'rae-ld-exporter'),
            __('Email', 'rae-ld-exporter'),
            __('Name', 'rae-ld-exporter'),
            __('Course ID', 'rae-ld-exporter'),
            __('Started', 'rae-ld-exporter'),
            __('Percent', 'rae-ld-exporter'),
            __('Completed', 'rae-ld-exporter'),
            __('Completed At', 'rae-ld-exporter'),
        ];
    } else {
        $headers = [
            __('User ID', 'rae-ld-exporter'),
            __('Email', 'rae-ld-exporter'),
            __('Name', 'rae-ld-exporter'),
            __('Registration Date', 'rae-ld-exporter'),
        ];
        
        if ($export_type === 'enrolled') {
            $headers[] = __('Enrolled Courses', 'rae-ld-exporter');
        }
    }
    
    // Write headers
    fputcsv($output, $headers);
    
    // Process each user
    foreach ($users as $user) {
        // Build user row based on export type
        if ($export_type === 'all') {
            $user_data = [$user->ID, $user->user_email, $user->display_name, $user->user_registered];
            fputcsv($output, $user_data);
            continue;
        }

        if ($export_type === 'enrolled') {
            // Union: progress OR explicit enrollment when checkbox selected
            $include_user = ld_user_has_any_progress($user->ID);
            if (!$include_user && $include_enrolled) {
                $include_user = ld_user_has_any_explicit_course_enrollment($user->ID);
            }
            if ($include_user) {
                $user_data = [$user->ID, $user->user_email, $user->display_name, $user->user_registered];
                $course_ids_with_progress = ld_get_courses_with_progress($user->ID);
                if ($include_enrolled) {
                    $explicit = ld_get_explicitly_enrolled_course_ids($user->ID);
                    $course_ids_with_progress = array_values(array_unique(array_merge($course_ids_with_progress, $explicit)));
                }
                $course_titles = array_map('get_the_title', $course_ids_with_progress);
                $user_data[] = implode(', ', array_filter($course_titles));
                fputcsv($output, $user_data);
            }
            continue;
        }

        if ($export_type === 'specific' && $course_id > 0) {
            $has_progress = ld_user_has_progress_in_course($user->ID, $course_id);
            $has_explicit = $include_enrolled ? ld_user_has_explicit_course_enrollment($user->ID, $course_id) : false;
            if (!($has_progress || $has_explicit)) {
                continue;
            }

            // Compute progress and completion fields
            $p = function_exists('learndash_user_get_course_progress') ? learndash_user_get_course_progress($user->ID, $course_id) : [];
            $completed_at_raw = get_user_meta($user->ID, "course_completed_{$course_id}", true);
            $percent = 0;
            if (!empty($p) && is_array($p)) {
                $total = intval($p['total'] ?? 0);
                $completed_cnt = intval($p['completed'] ?? 0);
                $percent = ($total > 0) ? round(($completed_cnt / $total) * 100) : 0;
            }
            $started = ($percent > 0 || ld_user_has_course_activity($user->ID, $course_id) || ld_user_has_course_progress_meta($user->ID, $course_id)) ? 'Y' : 'N';
            $completed_flag = (!empty($completed_at_raw) || (!empty($p['status']) && intval($p['status']) === 100)) ? 'Y' : 'N';
            $completed_at = '';
            if (!empty($completed_at_raw)) {
                $completed_at = is_numeric($completed_at_raw) ? date('Y-m-d H:i:s', intval($completed_at_raw)) : $completed_at_raw;
            }

            // Optional debug logging if requested
            if (isset($_GET['ld_debug']) && $_GET['ld_debug'] == '1') {
                error_log(print_r($p, true));
                error_log(print_r($completed_at_raw, true));
            }

            $user_data = [
                $user->ID,
                $user->user_email,
                $user->display_name,
                $course_id,
                $started,
                $percent,
                $completed_flag,
                $completed_at,
            ];
            fputcsv($output, $user_data);
            continue;
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
    $include_enrolled = !empty($_POST['include_enrolled']);
    
    try {
    $count = ld_get_subscriber_count($export_type, $course_id, $include_enrolled);
    wp_send_json_success(['count' => $count]);
    } catch (Exception $e) {
        error_log('LD Exporter Count Error: ' . $e->getMessage());
    wp_send_json_error(sprintf(esc_html__('Error calculating count: %s', 'rae-ld-exporter'), $e->getMessage()));
    }
}

// Function to get subscriber count based on export type
function ld_get_subscriber_count($export_type = 'all', $course_id = 0, $include_enrolled = false) {
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
        
        // For enrolled and specific course counts, LearnDash should be available for progress APIs.
        // But we can still count via activity/meta without direct LD functions.
        
        $count = 0;
        
        foreach ($user_ids as $user_id) {
            if ($export_type === 'enrolled') {
                $has_progress = ld_user_has_any_progress($user_id);
                $has_enrollment = $include_enrolled ? ld_user_has_any_explicit_course_enrollment($user_id) : false;
                if ($has_progress || $has_enrollment) {
                    $count++;
                }
            } elseif ($export_type === 'specific' && $course_id > 0) {
                $has_progress = ld_user_has_progress_in_course($user_id, $course_id);
                $has_enrollment = $include_enrolled ? ld_user_has_explicit_course_enrollment($user_id, $course_id) : false;
                if ($has_progress || $has_enrollment) {
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

// Helpers: explicit enrollment detection (legacy) and new progress-based detection
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

/**
 * Detect if user has any progress in any course (lesson/topic/quiz/course started or completed)
 */
function ld_user_has_any_progress($user_id) {
    global $wpdb;
    $ua = $wpdb->prefix . 'learndash_user_activity';
    // Any activity row of relevant types with started/completed or status=1
    $sql = $wpdb->prepare(
        "SELECT 1 FROM {$ua} ua
         WHERE ua.user_id = %d
           AND ua.activity_type IN ('lesson','topic','quiz','course')
           AND (ua.activity_status = 1 OR ua.activity_started IS NOT NULL OR ua.activity_completed IS NOT NULL)
         LIMIT 1",
        $user_id
    );
    $row = $wpdb->get_var($sql);
    if (!empty($row)) {
        return true;
    }
    // Fallback to _sfwd-course_progress meta
    $progress = get_user_meta($user_id, '_sfwd-course_progress', true);
    if (is_array($progress)) {
        foreach ($progress as $cid => $data) {
            if (!empty($data['completed']) && intval($data['completed']) > 0) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Get all course IDs with explicit enrollment for a user
 */
function ld_get_explicitly_enrolled_course_ids($user_id) {
    $course_ids = [];
    $all_meta = get_user_meta($user_id);
    foreach ($all_meta as $key => $vals) {
        if (strpos($key, 'course_') === 0 && str_ends_with($key, '_access_from')) {
            $cid = intval(str_replace(['course_', '_access_from'], ['', ''], $key));
            if ($cid > 0 && !empty($vals) && intval($vals[0]) > 0) {
                $course_ids[] = $cid;
            }
        }
        if (strpos($key, 'learndash_course_') === 0 && str_ends_with($key, '_access_from')) {
            $cid = intval(str_replace(['learndash_course_', '_access_from'], ['', ''], $key));
            if ($cid > 0 && !empty($vals) && intval($vals[0]) > 0) {
                $course_ids[] = $cid;
            }
        }
    }
    return array_values(array_unique(array_map('intval', $course_ids)));
}

/**
 * Detect if user has any activity rows for a specific course_id
 */
function ld_user_has_course_activity($user_id, $course_id) {
    global $wpdb;
    $ua = $wpdb->prefix . 'learndash_user_activity';
    $uam = $wpdb->prefix . 'learndash_user_activity_meta';
    $sql = $wpdb->prepare(
        "SELECT ua.activity_id
         FROM {$ua} ua
         JOIN {$uam} am ON am.activity_id = ua.activity_id
         WHERE ua.user_id = %d
           AND am.activity_meta_key = 'course_id'
           AND am.activity_meta_value = %d
           AND ua.activity_type IN ('lesson','topic','quiz','course')
           AND (ua.activity_status = 1 OR ua.activity_started IS NOT NULL OR ua.activity_completed IS NOT NULL)
         LIMIT 1",
        $user_id,
        $course_id
    );
    $row = $wpdb->get_var($sql);
    return !empty($row);
}

/**
 * Detect progress via LearnDash API for a specific course
 */
function ld_user_has_course_progress_api($user_id, $course_id) {
    if (!function_exists('learndash_user_get_course_progress')) {
        return false;
    }
    $p = learndash_user_get_course_progress($user_id, $course_id);
    if (!is_array($p)) return false;
    $total = intval($p['total'] ?? 0);
    $completed = intval($p['completed'] ?? 0);
    if ($total > 0 && $completed > 0) {
        return true;
    }
    // Some installs may not populate totals; treat status 100 as completed
    if (!empty($p['status']) && intval($p['status']) === 100) {
        return true;
    }
    return false;
}

/**
 * Detect progress via _sfwd-course_progress user meta for a specific course
 */
function ld_user_has_course_progress_meta($user_id, $course_id) {
    $progress = get_user_meta($user_id, '_sfwd-course_progress', true);
    if (is_array($progress) && isset($progress[$course_id])) {
        $data = $progress[$course_id];
        return (!empty($data['completed']) && intval($data['completed']) > 0);
    }
    return false;
}

/**
 * Combined check for progress in a course
 */
function ld_user_has_progress_in_course($user_id, $course_id) {
    return (
        ld_user_has_course_progress_api($user_id, $course_id)
        || ld_user_has_course_activity($user_id, $course_id)
        || ld_user_has_course_progress_meta($user_id, $course_id)
    );
}

/**
 * Get list of course IDs where the user has progress
 */
function ld_get_courses_with_progress($user_id) {
    global $wpdb;
    $ua = $wpdb->prefix . 'learndash_user_activity';
    $uam = $wpdb->prefix . 'learndash_user_activity_meta';
    $sql = $wpdb->prepare(
        "SELECT DISTINCT am.activity_meta_value AS course_id
         FROM {$ua} ua
         JOIN {$uam} am ON am.activity_id = ua.activity_id
         WHERE ua.user_id = %d
           AND am.activity_meta_key = 'course_id'
           AND ua.activity_type IN ('lesson','topic','quiz','course')
           AND (ua.activity_status = 1 OR ua.activity_started IS NOT NULL OR ua.activity_completed IS NOT NULL)",
        $user_id
    );
    $course_ids = $wpdb->get_col($sql);
    // Merge with _sfwd-course_progress meta
    $progress = get_user_meta($user_id, '_sfwd-course_progress', true);
    if (is_array($progress)) {
        foreach ($progress as $cid => $data) {
            if (!empty($data['completed']) && intval($data['completed']) > 0) {
                $course_ids[] = $cid;
            }
        }
    }
    // Unique and cast to ints
    $course_ids = array_values(array_unique(array_map('intval', $course_ids)));
    return $course_ids;
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