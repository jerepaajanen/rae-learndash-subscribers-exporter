# RAE LearnDash Subscribers Exporter

Export WordPress subscriber emails to CSV, with optional Learndash enrollment details and a live "Will export …" count. Adds an "Export Learndash Subscribers" page under Users in wp-admin.

## Requirements
- WordPress (admin access)
- Optional: Learndash LMS (needed for enrolled/specific course exports)


## Usage
1. In wp-admin, open Users → Export Learndash Subscribers.
2. Choose export type (a live count shows how many will be exported):
   - All Subscribers: basic user info for all subscribers.
  - Enrolled in Any Course: only subscribers enrolled in at least one Learndash course, including a list of enrolled courses.
   - Specific Course: only subscribers enrolled in the selected course, including progress, status, and enrollment date.
3. Click "Export to Excel" to download a UTF‑8 CSV compatible with Excel.

### CSV columns
- User ID, Email, Name, Registration Date
- Plus when filtering by enrollment:
  - Enrolled Courses
  - For Specific Course: Course Progress, Course Status, Enrollment Date

## Notes
- File name includes the date, or the course slug when exporting a specific course.
- If Learndash is not active, only "All Subscribers" export is available.
- For Learndash courses set to "Open": users are not explicitly enrolled by default. This plugin only counts and exports users with explicit enrollment (based on course_{ID}_access_from user meta), so "Open" courses will show 0 unless users were actually enrolled (manually, via groups, or purchases). This keeps counts accurate and avoids including all site users.

## UI/UX
- The export options are shown inside a full‑width wp-admin card.
- The live count and loading spinner sit inline with the Export button.
- The "Select Course" row uses a native-looking bordered box and inline notice styles.

## Troubleshooting
- Live count shows 0 for Open courses: expected if no explicit enrollments exist.
- AJAX 500 errors: check wp-content/debug.log for any PHP errors. Ensure Learndash is active for enrolled/specific exports.