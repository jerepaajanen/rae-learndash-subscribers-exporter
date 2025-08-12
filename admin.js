jQuery(document).ready(function($) {
    // Function to update counts via AJAX
    function updateCounts() {
        const exportType = $('input[name="export_type"]:checked').val();
        const courseId = $('#course_id').val() || 0;
        
        // For specific course type, don't update unless a course is selected
        if (exportType === 'specific' && courseId === 0) {
            $('#export-count-display').html('<span class="count-badge">Select a course to see count</span>');
            return;
        }
        
    // Show loading state (use WP spinner, styling handled in admin.css)
    $('#export-count-display').html('<span class="spinner is-active" aria-hidden="true"></span>');
        
        // Make AJAX request
        $.post(ld_ajax.ajax_url, {
            action: 'ld_get_subscriber_count',
            export_type: exportType,
            course_id: courseId,
            nonce: ld_ajax.nonce
        })
        .done(function(response) {
            if (response.success) {
                updateCountDisplay(exportType, courseId, response.data.count);
            } else {
                console.error('Error getting count:', response.data);
                $('#export-count-display').html('<span style="color: red;">Error: ' + (response.data || 'Unknown error') + '</span>');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('AJAX request failed:', xhr.responseText);
            $('#export-count-display').html('<span style="color: red;">Connection error</span>');
        });
    }
    
    // Function to update the count display based on current selection
    function updateCountDisplay(exportType, courseId, count) {
        let message = '';
        
        if (exportType === 'all') {
            message = `Will export ${count} subscribers`;
        } else if (exportType === 'enrolled') {
            message = `Will export ${count} subscribers`;
        } else if (exportType === 'specific' && courseId > 0) {
            const courseName = $('#course_id option:selected').text();
            message = `Will export ${count} subscribers`;
        }
        
        $('#export-count-display').html(`<span class="count-badge">${message}</span>`);
    }
    
    // Handle export type change
    $('input[name="export_type"]').change(function() {
        const selectedType = $(this).val();
        
        if (selectedType === 'specific') {
            $('#course-selection').show();
        } else {
            $('#course-selection').hide();
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
    
    // Load initial counts
    updateCounts();
});
