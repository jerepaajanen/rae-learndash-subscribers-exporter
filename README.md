# RAE LearnDash Subscribers Exporter

Export WordPress subscriber emails to CSV, with optional LearnDash enrollment details. Adds an "Export LD Subscribers" page under Users in wp-admin.

## Requirements
- WordPress (admin access)
- Optional: LearnDash LMS (needed for enrolled/specific course exports)


## Usage
1. In wp-admin, open Users → Export LD Subscribers.
2. Choose export type:
   - All Subscribers: basic user info for all subscribers.
   - Enrolled in Any Course: only subscribers enrolled in at least one LearnDash course, including a list of enrolled courses.
   - Specific Course: only subscribers enrolled in the selected course, including progress, status, and enrollment date.
3. Click "Export to Excel" to download a UTF‑8 CSV compatible with Excel.

### CSV columns
- User ID, Email, Name, Registration Date
- Plus when filtering by enrollment:
  - Enrolled Courses
  - For Specific Course: Course Progress, Course Status, Enrollment Date

## Notes
- File name includes the date, or the course slug when exporting a specific course.
- If LearnDash is not active, only "All Subscribers" export is available.