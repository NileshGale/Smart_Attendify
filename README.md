# Attendify - Smart Attendance System

Attendify is a modern, web-based attendance management system designed for educational institutions. It provides a seamless experience for administrators, teachers, and students to track and manage attendance using QR codes, unique session codes, and geolocation verification.

## 🚀 Technologies Used

- **Backend**: PHP (7.4+)
- **Database**: MySQL (hosted on InfinityFree)
- **Frontend**: HTML5, Vanilla CSS3, Vanilla JavaScript
- **Email Service**: PHPMailer (SMTP Integration)

## 📦 Libraries & Modules

### Frontend Libraries
- [Chart.js](https://www.chartjs.org/) (v4.4.0) - For attendance analytics and visualization.
- [QRCode.js](https://davidshimjs.github.io/qrcodejs/) - For generating student QR codes on the fly.
- [jsPDF](https://github.com/parallax/jsPDF) - For generating downloadable attendance reports in PDF format.
- [jsPDF-AutoTable](https://github.com/simonbengtsson/jsPDF-AutoTable) - Plugin for creating tables in PDF reports.
- [html5-qrcode](https://github.com/mebjas/html5-qrcode) - For scanning QR codes via the device camera.
- [jsQR](https://github.com/cozmo/jsQR) - A pure JavaScript QR code reading library.

### Backend Modules
- **PHPMailer**: Located in `backend/PHPMailer/`, used for sending OTPs and attendance alerts.
- **phpqrcode**: Located in `backend/phpqrcode/`, used for server-side QR code generation.

## ✨ Core Features

1.  **QR Code Attendance**:
    *   Students have unique QR codes.
    *   Teachers can scan student QR codes to mark attendance instantly.
2.  **Unique Code Attendance**:
    *   Teachers generate a unique 7-character code for a session.
    *   Codes have a configurable validity timer (default: 30 seconds for anti-proxy security).
    *   Students enter the code in their dashboard to mark attendance.
3.  **Geolocation Verification**:
    *   Ensures students are physically present in the classroom.
    *   Compares student coordinates with the event/teacher location.
    *   Configurable proximity radius (default: 50m).
4.  **Analytics & Reporting**:
    *   Visual charts for attendance trends.
    *   Downloadable reports in **PDF** and **CSV** formats.
    *   Real-time statistics for administrators.
5.  **Automated Notifications**:
    *   OTP-based password reset.
    *   Email alerts for low attendance.

## 🛠️ How to Use

### For Administrators
1.  **Login**: Access the admin panel using administrative credentials.
2.  **User Management**: Add new students and teachers. The system automatically generates Registration IDs.
3.  **Subject Allocation**: Allocate subjects to teachers and students.
4.  **Monitoring**: View overall attendance statistics and system health.

### For Teachers
1.  **Login**: Use your Teacher ID (e.g., TEA2024001). Department: Commerce and Management.
2.  **Dashboard**: View your schedule and assigned subjects.
3.  **Mark Attendance**:
    *   **QR Scanner**: Open the scanner and scan student codes.
    *   **Generate Code**: Click "Generate Unique Code" and show it to the class.
    *   **Manual**: Select "Manual Marking" to mark students one by one.
4.  **Reports**: Download attendance logs for any date or subject.

### For Students
1.  **Login**: Use your Student ID (e.g., SEE2004001). Branch: BCCA | Department: Commerce and Management.
2.  **Dashboard**: Check your current attendance percentage for all subjects.
3.  **Mark Presence**:
    *   Enter the unique code provided by the teacher.
    *   Ensure your **Location Services** are enabled for verification.
4.  **Show QR**: Display your profile QR code for the teacher to scan.

## 🌐 Server Setup (InfinityFree)

Attendify is optimized for hosting on [InfinityFree](https://www.infinityfree.com/).

### Deployment Steps:
1.  **Upload Files**: Use an FTP client (like FileZilla) to upload the `backend/` and `frontend/` folders to your `htdocs` directory.
2.  **Database Configuration**:
    *   Create a MySQL database in the InfinityFree Control Panel.
    *   Import the `backend/main.sql` file using phpMyAdmin.
    *   Update `backend/db_config.php` with your database credentials.




## 📁 Project Structure

```text
Attendify/
├── backend/            # PHP APIs and Database Logic
│   ├── PHPMailer/      # Email services
│   ├── phpqrcode/      # QR generation library
│   ├── db_config.php   # Database connection
│   └── ...             # API endpoints (auth, user, attendance)
├── frontend/           # HTML, CSS, and Client-side JS
│   ├── admin_dashboard.html
│   ├── teacher_dashboard.html
│   ├── student_dashboard.html
│   └── ...
├── uploads/            # User profile pictures
└── README.md           # Project documentation
```

## 🔐 Security Enhancements (Added)

Attendify now includes robust security measures to prevent multi-device login and attendance fraud:

1.  **Single-Device Login (SDL)**:
    *   **Concurrent Login Prevention**: If a user logs into a new device, their session on any previous device is automatically terminated.
    *   **Live Session Polling**: The dashboards perform a background check every 30 seconds to ensure the current session is still valid.
2.  **Anti-Proxy & Anti-Cheat Measures**:
    *   **30-Second Unique Code Window**: The validity of attendance codes has been reduced to **30 seconds**. This prevents students from effectively sharing codes via messaging apps.
    *   **Tab-Switching Detection**: The student dashboard instantly clears the attendance code input field if the student minimizes the browser or switches to another tab.
    *   **Security Alerts**: Students receive an immediate warning if a security reset (like tab switching) occurs.

---
*Developed with ❤️ for Smart Attendance Management.*
thankyou

## 📝 Changelog

*   **[2026-03-27]**: Fixed mobile layout issue where the bottom of the dashboard content was cut off on smaller screens. Added `padding-bottom` to the `.main-content` container in `@media (max-width: 768px)` and `@media (max-width: 480px)` breakpoints across `student_dashboard.html`, `teacher_dashboard.html`, and `admin_dashboard.html`.
*   **[2026-03-27]**: Fixed 'Overall Attendance' calculation in the student dashboard. It now accurately calculates the percentage based on `(Total Present / Total Lectures) * 100` across all subjects, instead of incorrectly averaging the individual subject percentages.
*   **[2026-04-04]**: **Modernized Attendance Reporting & Mobile UI/UX Optimizations**:
    *   **Advanced Filtering**: Integrated dynamic "Show Rows" (All/10/20) and "Status" (All/Present/Absent) filters for attendance records.
    *   **Mobile UX: Selective Toast Positioning**: Implemented localized toast notifications on mobile—"Login successful" stays at the top for prominence, while operational messages (e.g., "Generating PDF") now appear at the bottom to keep the main view clear.
    *   **Mobile UX: Definitive Search Bar Fix**: Surgically anchored the "Clear (X)" search icon to the far right edge in the Admin Dashboard, ensuring a professional and non-overlapping layout on small screens.
    *   **Premium Visuals & Smart PDF Exports**: Refreshed dashboard themes with Navy & Cyan-Teal accents. PDFs now perfectly reflect active filters and maintain sequential numbering.
    *   **Data Integrity & UX Refinement**: Optimized backend SQL for complete class list reports and added dismissal "X" icons to search results and live attendee panels for a faster workflow.
*   **[2026-04-05]**: **System Audit & Maintenance**: Verified dashboard stability and confirmed all existing analytics and security features are operational.
*   **[2026-04-10]**: **Comprehensive Project Analysis**: Performed a deep architectural and security audit. Documented the system's multi-layered anti-fraud mechanisms (Single-Device Login, Geolocation Proximity, Tab-Detection) and confirmed optimizations for InfinityFree hosting (DB credentials, Timezone sync, PHPMailer SMTP). Established a new workflow for tracking all future codebase modifications directly in this README.
*   **[2026-04-10]**: **Mobile UI Fix - Admin Dashboard**: Surgically fixed the alignment of the "Clear Search" cross icon in the Teacher Schedules tab. The issue was caused by a broad CSS selector in the media query applying `width: 100%` to all buttons; changed to a direct-child selector to preserve icon dimensions while keeping main buttons responsive.
*   **[2026-04-10]**: **Enhanced User Search & UI Refinement - Recent Registrations**: Added real-time search and aligned all controls into a unified horizontal interface. Restored consistent project styling (rounded corners, themed borders) by correctly mapping elements to the `search-row` design system. Integrated the search query into the **PDF Report Generation** logic and enhanced the exported document with **full grid column lines** for improved data readability and structural clarity. Optimized backend API with search parameters and consolidated JavaScript logic.