# RAE LearnDash Subscribers Exporter

Export WordPress subscriber emails to CSV, with LearnDash progress-aware filtering and a live "Will export …" count. Adds an "Export Learndash Subscribers" page under Users in wp-admin.

## Requirements
- WordPress (admin access)
- Optional: Learndash LMS (needed for enrolled/specific course exports)


## Usage
1. In wp-admin, open Users → Export Learndash Subscribers.
2. Choose export type (a live count shows how many will be exported):
   - All Subscribers: basic user info for all subscribers.
  - Enrolled in Any Course: subscribers with progress in at least one course. Optionally include explicit enrollments with no progress.
  - Specific Course: subscribers with progress in the selected course. Optionally include explicit enrollments with no progress. CSV includes started, percent, completed, completed_at.
3. Click "Export to Excel" to download a UTF‑8 CSV compatible with Excel.

### CSV columns
- All Subscribers: User ID, Email, Name, Registration Date
- Enrolled in Any Course: + Enrolled Courses (from progress and optionally enrollments)
- Specific Course: user_id, email, display_name, course_id, started(Y/N), percent, completed(Y/N), completed_at

## Notes
- File name includes the date, or the course slug when exporting a specific course.
- If Learndash is not active, only "All Subscribers" export is available.
- Open courses: users aren’t explicitly enrolled by default. This plugin includes users with actual progress. You can also include explicit enrollments without progress via the checkbox.

## Changelog

### 1.2.0
- Treat enrollment on Open courses as: any logged-in user with progress.
- Add checkbox to include explicit enrollments without progress.
- Specific course CSV now: user_id, email, display_name, course_id, started, percent, completed, completed_at.

## UI/UX
- The export options are shown inside a full‑width wp-admin card.
- The live count and loading spinner sit inline with the Export button.
- The "Select Course" row uses a native-looking bordered box and inline notice styles.

## Troubleshooting
- Live count shows 0 for Open courses: expected if no explicit enrollments exist.
- AJAX 500 errors: check wp-content/debug.log for any PHP errors. Ensure Learndash is active for enrolled/specific exports.