jQuery(document).ready(function($) {
    // Function to update counts via AJAX
    function updateCounts() {
        const exportType = $('input[name="export_type"]:checked').val();
        const courseId = $('#course_id').val() || 0;
    const includeEnrolled = $('#include_enrolled').is(':checked') ? 1 : 0;
        
        // For specific course type, don't update unless a course is selected
        if (exportType === 'specific' && courseId === 0) {
            $('#export-count-display').html('<span class="count-badge">' + (ld_ajax?.strings?.select_course_prompt || 'Select a course to see count') + '</span>');
            return;
        }
        
    // Show loading state (use WP spinner, styling handled in admin.css)
    $('#export-count-display').html('<span class="spinner is-active" aria-hidden="true"></span>');
        
        // Make AJAX request
        $.post(ld_ajax.ajax_url, {
            action: 'ld_get_subscriber_count',
            export_type: exportType,
            course_id: courseId,
            include_enrolled: includeEnrolled,
            nonce: ld_ajax.nonce
        })
        .done(function(response) {
            if (response.success) {
                updateCountDisplay(exportType, courseId, response.data.count);
            } else {
                console.error('Error getting count:', response.data);
                var errPrefix = (ld_ajax?.strings?.error_prefix || 'Error:');
                $('#export-count-display').html('<span style="color: red;">' + errPrefix + ' ' + (response.data || 'Unknown error') + '</span>');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('AJAX request failed:', xhr.responseText);
            var connErr = (ld_ajax?.strings?.connection_error || 'Connection error');
            $('#export-count-display').html('<span style="color: red;">' + connErr + '</span>');
        });
    }
    
    // Function to update the count display based on current selection
    function updateCountDisplay(exportType, courseId, count) {
    let message = '';
        
        var tpl = (ld_ajax?.strings?.will_export || 'Will export %s subscribers');
        if (exportType === 'all') {
            message = tpl.replace('%s', count);
        } else if (exportType === 'enrolled') {
            message = tpl.replace('%s', count);
        } else if (exportType === 'specific' && courseId > 0) {
            message = tpl.replace('%s', count);
        }
        
        $('#export-count-display').html(`<span class="count-badge">${message}</span>`);
    }
    
    // Handle export type change
    $('input[name="export_type"]').change(function() {
        const selectedType = $(this).val();
        
        if (selectedType === 'specific') {
            $('#course-selection').show();
            $('#include-enrolled-row').show();
        } else {
            $('#course-selection').hide();
            if (selectedType === 'enrolled') {
                $('#include-enrolled-row').show();
            } else {
                $('#include-enrolled-row').hide();
            }
        }
        
        // Update counts when selection changes
        updateCounts();
    });
    
    // Handle course selection change
    $('#course_id').change(function() {
        const exportType = $('input[name="export_type"]:checked').val();
        
        // Only update if we're in specific course mode
        if (exportType === 'specific') {
            updateCounts();
        }
    });
    
    // Handle include_enrolled toggle
    $('#include_enrolled').change(function() {
        const exportType = $('input[name="export_type"]:checked').val();
        if (exportType === 'enrolled' || exportType === 'specific') {
            updateCounts();
        }
    });
    
    // Load initial counts
    // Set initial visibility of include-enrolled row
    const initialType = $('input[name="export_type"]:checked').val();
    if (initialType === 'enrolled' || initialType === 'specific') {
        $('#include-enrolled-row').show();
    } else {
        $('#include-enrolled-row').hide();
    }
    updateCounts();
});
