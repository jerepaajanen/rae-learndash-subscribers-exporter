<?php
/**
 * Plugin Name: Learndash Subscribers Exporter
 * Description: Export emails of WordPress subscribers enrolled in any Learndash course.
 * Version: 1.0
 * Author: Rae Agency
 * Author URI: https://www.rae.fi
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
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
    add_users_page('Export LD Subscribers', 'Export LD Subscribers', 'manage_options', 'ld-subscribers-export', 'ld_export_page');
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
        <h1>Export Learndash Subscribers</h1>
        
        <div class="card">
            <h2>Export Options</h2>
            <form method="post">
                <?php wp_nonce_field('ld_export_action', 'ld_export_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Export Type:</th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Export Type</legend>
                                <label>
                                    <input type="radio" name="export_type" value="all" checked>
                                    All Subscribers
                                </label><br>
                                <label>
                                    <input type="radio" name="export_type" value="enrolled">
                                    All Subscribers Enrolled in Any Course
                                </label><br>
                                <label>
                                    <input type="radio" name="export_type" value="specific">
                                    Subscribers Enrolled in Specific Course
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr id="course-selection" style="display: none;">
                        <th scope="row">Select Course:</th>
                        <td>
                            <select name="course_id" id="course_id">
                                <option value="">-- Select Course --</option>
                                <?php foreach ($courses as $id => $title): ?>
                                    <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($title); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($courses)): ?>
                                <p class="description" style="color: red;">No LearnDash courses found.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="export_ld_emails" class="button button-primary" value="Export to Excel">
                </p>
            </form>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('input[name="export_type"]').change(function() {
            if ($(this).val() === 'specific') {
                $('#course-selection').show();
            } else {
                $('#course-selection').hide();
            }
        });
    });
    </script>
    <?php
}

// Export function
function ld_export_subscriber_emails($export_type = 'all', $course_id = 0) {
    // Verify LearnDash is active
    if (!function_exists('learndash_user_get_enrolled_courses') && $export_type !== 'all') {
        wp_die('LearnDash is not active. Please activate LearnDash to use this feature.');
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
    $headers = ['User ID', 'Email', 'Name', 'Registration Date'];
    
    if ($export_type === 'enrolled' || $export_type === 'specific') {
        $headers[] = 'Enrolled Courses';
        
        if ($export_type === 'specific') {
            $headers[] = 'Course Progress';
            $headers[] = 'Course Status';
            $headers[] = 'Enrollment Date';
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
                
                // Skip user if they're not enrolled in any course
                if ($export_type === 'enrolled' && empty($enrolled_courses)) {
                    $include_user = false;
                }
                
                // Skip user if they're not enrolled in the specific course
                if ($export_type === 'specific') {
                    if (!in_array($course_id, $enrolled_courses)) {
                        $include_user = false;
                    }
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
                $course_access_list = get_user_meta($user->ID, 'learndash_course_' . $course_id . '_access_from', true);
                if (!empty($course_access_list)) {
                    $enrollment_date = date('Y-m-d H:i:s', $course_access_list);
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
?>