/**
 * Attendify Frontend Integration Examples
 * Complete JavaScript code for all features
 */

// ============================================================================
// CONFIGURATION
// ============================================================================

const API_BASE = 'backend/';
const ENDPOINTS = {
    auth: API_BASE + 'auth_api.php',
    user: API_BASE + 'user_api.php',
    attendance: API_BASE + 'attendance_api.php',
    qr: API_BASE + 'qr_generator.php'
};

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Make API request
 */
async function apiRequest(endpoint, action, data = {}, method = 'POST') {
    data.action = action;
    
    const options = {
        method: method,
        headers: method === 'POST' ? {
            'Content-Type': 'application/x-www-form-urlencoded'
        } : {}
    };
    
    if (method === 'POST') {
        options.body = new URLSearchParams(data);
    }
    
    const url = method === 'GET' 
        ? `${endpoint}?${new URLSearchParams(data).toString()}`
        : endpoint;
    
    const response = await fetch(url, options);
    return await response.json();
}

/**
 * Show notification
 */
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// ============================================================================
// AUTHENTICATION
// ============================================================================

/**
 * Login function
 */
async function login(regId, password) {
    const result = await apiRequest(ENDPOINTS.auth, 'login', {
        reg_id: regId,
        password: password
    });
    
    if (result.success) {
        window.location.href = result.redirect;
    } else {
        showNotification(result.message, 'error');
    }
}

/**
 * Logout function
 */
async function logout() {
    const result = await apiRequest(ENDPOINTS.auth, 'logout');
    if (result.success) {
        window.location.href = 'index.html';
    }
}

/**
 * Check if user is logged in
 */
async function checkSession() {
    const result = await apiRequest(ENDPOINTS.auth, 'checkSession', {}, 'GET');
    
    if (result.logged_in) {
        return result.user;
    }
    return null;
}

/**
 * Reset password via OTP
 */
async function resetPassword(email, otp, newPassword) {
    const result = await apiRequest(ENDPOINTS.auth, 'resetPassword', {
        email: email,
        otp: otp,
        new_password: newPassword
    });
    
    showNotification(result.message, result.success ? 'success' : 'error');
    return result.success;
}

// ============================================================================
// USER MANAGEMENT
// ============================================================================

/**
 * Search users (students/teachers)
 */
async function searchUsers(query, role = '') {
    const result = await apiRequest(ENDPOINTS.user, 'searchUsers', {
        query: query,
        role: role,
        limit: 20
    }, 'GET');
    
    return result.success ? result.users : [];
}

/**
 * Display search results
 */
function displaySearchResults(users, containerId) {
    const container = document.getElementById(containerId);
    
    if (users.length === 0) {
        container.innerHTML = '<p>No users found</p>';
        return;
    }
    
    const html = users.map(user => `
        <div class="user-card" data-user-id="${user.id}">
            <div class="user-info">
                <div class="user-name">${user.full_name}</div>
                <div class="user-details">
                    ${user.reg_id} • ${user.email}
                </div>
            </div>
            <button onclick="selectUser(${user.id})" class="btn-select">
                Select
            </button>
        </div>
    `).join('');
    
    container.innerHTML = html;
}

/**
 * Get user details with subjects
 */
async function getUserDetails(userId) {
    const result = await apiRequest(ENDPOINTS.user, 'getUserDetails', {
        user_id: userId
    }, 'GET');
    
    return result.success ? result.user : null;
}

/**
 * Add new user (Admin only)
 */
async function addUser(userData) {
    const result = await apiRequest(ENDPOINTS.user, 'addUser', userData);
    
    showNotification(result.message, result.success ? 'success' : 'error');
    
    if (result.success) {
        console.log('Registration ID:', result.reg_id);
        console.log('Default Password:', result.default_password);
    }
    
    return result;
}

/**
 * Allocate subjects to user
 */
async function allocateSubjects(userId, subjectIds) {
    const result = await apiRequest(ENDPOINTS.user, 'allocateSubjects', {
        user_id: userId,
        subjects: JSON.stringify(subjectIds)
    });
    
    showNotification(result.message, result.success ? 'success' : 'error');
    return result.success;
}

/**
 * Get all subjects
 */
async function getAllSubjects() {
    const result = await apiRequest(ENDPOINTS.user, 'getAllSubjects', {}, 'GET');
    return result.success ? result.subjects : [];
}

// ============================================================================
// QR CODE GENERATION
// ============================================================================

/**
 * Generate QR code for student
 */
async function generateQRCode(studentId) {
    const result = await apiRequest(ENDPOINTS.qr, 'generateQR', {
        student_id: studentId
    });
    
    if (result.success) {
        showNotification('QR code generated successfully!');
        return result.qr_path;
    } else {
        showNotification(result.message, 'error');
        return null;
    }
}

/**
 * Download QR code
 */
function downloadQRCode(studentId) {
    window.location.href = `${ENDPOINTS.qr}?action=downloadQR&student_id=${studentId}`;
}

/**
 * Display QR code in modal
 */
function displayQRCode(qrPath, studentName, regId) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <span class="close" onclick="this.parentElement.parentElement.remove()">&times;</span>
            <h2>QR Code - ${studentName}</h2>
            <p>Registration ID: ${regId}</p>
            <img src="${qrPath}" alt="QR Code" style="max-width: 400px; margin: 20px auto; display: block;">
            <button onclick="downloadQRCode(${studentId})" class="btn-primary">
                &#x1F4E5; Download QR Code
            </button>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// ============================================================================
// ATTENDANCE - UNIQUE CODE METHOD
// ============================================================================

/**
 * Generate unique attendance code (Teacher)
 */
async function generateAttendanceCode(subjectId, validityMinutes = 15) {
    const result = await apiRequest(ENDPOINTS.attendance, 'generateUniqueCode', {
        subject_id: subjectId,
        validity_minutes: validityMinutes,
        session_date: new Date().toISOString().split('T')[0]
    });
    
    if (result.success) {
        displayAttendanceCode(result.unique_code, result.expires_at);
        return result.unique_code;
    } else {
        showNotification(result.message, 'error');
        return null;
    }
}

/**
 * Display attendance code with countdown
 */
function displayAttendanceCode(code, expiresAt) {
    const container = document.getElementById('codeDisplay');
    
    container.innerHTML = `
        <div class="code-container">
            <div class="code-label">Attendance Code</div>
            <div class="code-value">${code}</div>
            <div class="code-timer" id="codeTimer">Expires in: <span id="countdown"></span></div>
            <button onclick="checkCodeAttendance()" class="btn-primary">
                &#x1F4CA; View Attendance
            </button>
        </div>
    `;
    
    // Start countdown
    const expiryTime = new Date(expiresAt).getTime();
    
    const interval = setInterval(() => {
        const now = new Date().getTime();
        const distance = expiryTime - now;
        
        if (distance < 0) {
            clearInterval(interval);
            document.getElementById('countdown').textContent = 'EXPIRED';
            document.getElementById('countdown').style.color = '#ef4444';
            return;
        }
        
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        document.getElementById('countdown').textContent = 
            `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }, 1000);
}

/**
 * Student submits unique code
 */
async function submitAttendanceCode(code) {
    const result = await apiRequest(ENDPOINTS.attendance, 'markByUniqueCode', {
        unique_code: code.toUpperCase()
    });
    
    showNotification(result.message, result.success ? 'success' : 'error');
    return result.success;
}

/**
 * Get students who marked attendance via code
 */
async function checkCodeAttendance(subjectId) {
    const result = await apiRequest(ENDPOINTS.attendance, 'getCodeAttendance', {
        subject_id: subjectId,
        session_date: new Date().toISOString().split('T')[0]
    }, 'GET');
    
    if (result.success) {
        displayCodeAttendanceList(result.students);
    }
}

/**
 * Display list of students who used code
 */
function displayCodeAttendanceList(students) {
    const container = document.getElementById('attendanceList');
    
    if (students.length === 0) {
        container.innerHTML = '<p>No students have marked attendance yet</p>';
        return;
    }
    
    const html = `
        <table>
            <thead>
                <tr>
                    <th>Reg ID</th>
                    <th>Name</th>
                    <th>Marked At</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                ${students.map(s => `
                    <tr>
                        <td>${s.reg_id}</td>
                        <td>${s.full_name}</td>
                        <td>${new Date(s.marked_at).toLocaleString()}</td>
                        <td><span class="badge badge-present">Present</span></td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
        <button onclick="downloadAttendanceCSV()" class="btn-success" style="margin-top: 1rem;">
            &#x1F4BE; Download CSV
        </button>
    `;
    
    container.innerHTML = html;
}

// ============================================================================
// ATTENDANCE - QR CODE METHOD
// ============================================================================

/**
 * Mark attendance by scanning QR code
 */
async function markAttendanceByQR(qrData, subjectId) {
    const result = await apiRequest(ENDPOINTS.attendance, 'markByQR', {
        qr_data: qrData,
        subject_id: subjectId,
        attendance_date: new Date().toISOString().split('T')[0]
    });
    
    showNotification(result.message, result.success ? 'success' : 'error');
    
    if (result.success) {
        // Update attendance list
        addToAttendanceList(result.student_name, result.reg_id);
    }
    
    return result.success;
}

/**
 * Initialize QR code scanner
 */
function initQRScanner() {
    // Using html5-qrcode library
    // Include: <script src="https://unpkg.com/html5-qrcode"></script>
    
    const html5QrCode = new Html5Qrcode("qr-reader");
    
    html5QrCode.start(
        { facingMode: "environment" },
        {
            fps: 10,
            qrbox: { width: 250, height: 250 }
        },
        (decodedText, decodedResult) => {
            // QR code detected
            const subjectId = document.getElementById('subjectSelect').value;
            markAttendanceByQR(decodedText, subjectId);
            
            // Optionally stop scanning after successful scan
            html5QrCode.stop();
        },
        (errorMessage) => {
            // Scanning error (can be ignored for continuous scanning)
        }
    );
}

// ============================================================================
// ATTENDANCE - MANUAL MARKING
// ============================================================================

/**
 * Get students for manual attendance
 */
async function getStudentsForAttendance(subjectId) {
    const result = await apiRequest(ENDPOINTS.user, 'getStudentsBySubject', {
        subject_id: subjectId
    }, 'GET');
    
    if (result.success) {
        displayAttendanceForm(result.students);
    }
    
    return result.students;
}

/**
 * Display manual attendance form
 */
function displayAttendanceForm(students) {
    const container = document.getElementById('attendanceForm');
    
    const html = `
        <table id="attendanceTable">
            <thead>
                <tr>
                    <th>Reg ID</th>
                    <th>Student Name</th>
                    <th>Present</th>
                    <th>Absent</th>
                </tr>
            </thead>
            <tbody>
                ${students.map(s => `
                    <tr>
                        <td>${s.reg_id}</td>
                        <td>${s.full_name}</td>
                        <td>
                            <input type="radio" name="attendance_${s.id}" 
                                   value="present" checked>
                        </td>
                        <td>
                            <input type="radio" name="attendance_${s.id}" 
                                   value="absent">
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
        <button onclick="submitManualAttendance()" class="btn-primary" 
                style="margin-top: 1rem;">
            &#x2714; Mark Attendance
        </button>
    `;
    
    container.innerHTML = html;
}

/**
 * Submit manual attendance
 */
async function submitManualAttendance() {
    const table = document.getElementById('attendanceTable');
    const rows = table.querySelectorAll('tbody tr');
    const students = [];
    
    rows.forEach(row => {
        const regId = row.cells[0].textContent;
        const studentId = row.querySelector('input').name.split('_')[1];
        const status = row.querySelector('input:checked').value;
        
        students.push({ id: studentId, status: status });
    });
    
    const subjectId = document.getElementById('subjectSelect').value;
    
    const result = await apiRequest(ENDPOINTS.attendance, 'markManual', {
        students: JSON.stringify(students),
        subject_id: subjectId,
        attendance_date: document.getElementById('dateInput').value
    });
    
    showNotification(result.message, result.success ? 'success' : 'error');
}

// ============================================================================
// ATTENDANCE - INCREASE PERCENTAGE
// ============================================================================

/**
 * Increase student attendance percentage
 */
async function increaseAttendance(studentId, subjectId, percentageIncrease) {
    const result = await apiRequest(ENDPOINTS.attendance, 'increaseAttendance', {
        student_id: studentId,
        subject_id: subjectId,
        percentage_increase: percentageIncrease
    });
    
    showNotification(result.message, result.success ? 'success' : 'error');
    
    if (result.success) {
        console.log('Classes added:', result.classes_added);
        // Refresh attendance display
        loadStudentAttendance(studentId, subjectId);
    }
}

/**
 * Show increase attendance modal
 */
function showIncreaseAttendanceModal(studentId, studentName, subjectId) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <span class="close" onclick="this.parentElement.parentElement.remove()">&times;</span>
            <h2>Increase Attendance</h2>
            <p>Student: ${studentName}</p>
            <div class="form-group">
                <label>Increase by how many %?</label>
                <input type="number" id="percentageInput" 
                       min="1" max="50" value="10" step="1">
            </div>
            <button onclick="increaseAttendance(${studentId}, ${subjectId}, 
                    document.getElementById('percentageInput').value)" 
                    class="btn-primary">
                &#x1F4C8; Increase
            </button>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// ============================================================================
// ATTENDANCE REPORTS & ANALYTICS
// ============================================================================

/**
 * Get student attendance report
 */
async function getStudentReport(studentId, subjectId = null) {
    const params = { student_id: studentId };
    if (subjectId) params.subject_id = subjectId;
    
    const result = await apiRequest(ENDPOINTS.attendance, 'getStudentReport', params, 'GET');
    
    if (result.success) {
        return result.report;
    }
    return [];
}

/**
 * Display attendance report as table
 */
function displayAttendanceReport(report) {
    const container = document.getElementById('reportContainer');
    
    const html = `
        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Total Classes</th>
                    <th>Present</th>
                    <th>Absent</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                ${report.map(r => `
                    <tr>
                        <td>${r.subject_name}</td>
                        <td>${r.total_classes}</td>
                        <td>${r.present_count}</td>
                        <td>${r.absent_count}</td>
                        <td>
                            <span class="percentage ${r.percentage < 75 ? 'low' : ''}">
                                ${r.percentage}%
                            </span>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    container.innerHTML = html;
}

/**
 * Display attendance as chart (Chart.js)
 */
function displayAttendanceChart(report, chartId) {
    const ctx = document.getElementById(chartId).getContext('2d');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: report.map(r => r.subject_name),
            datasets: [{
                label: 'Attendance %',
                data: report.map(r => r.percentage),
                backgroundColor: report.map(r => 
                    r.percentage >= 75 ? 'rgba(16, 185, 129, 0.6)' : 'rgba(239, 68, 68, 0.6)'
                ),
                borderColor: report.map(r => 
                    r.percentage >= 75 ? '#10b981' : '#ef4444'
                ),
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Attendance: ' + context.parsed.y + '%';
                        }
                    }
                }
            }
        }
    });
}

// ============================================================================
// DOWNLOAD CSV
// ============================================================================

/**
 * Download attendance CSV
 */
function downloadAttendanceCSV(subjectId, date) {
    const url = `${ENDPOINTS.attendance}?action=downloadAttendanceCSV&subject_id=${subjectId}&attendance_date=${date}`;
    window.location.href = url;
}

// ============================================================================
// DASHBOARD STATISTICS
// ============================================================================

/**
 * Get admin dashboard stats
 */
async function getDashboardStats() {
    const result = await apiRequest(ENDPOINTS.user, 'getDashboardStats', {}, 'GET');
    
    if (result.success) {
        displayDashboardStats(result.stats);
    }
}

/**
 * Display dashboard statistics
 */
function displayDashboardStats(stats) {
    document.getElementById('totalStudents').textContent = stats.total_students;
    document.getElementById('totalTeachers').textContent = stats.total_teachers;
    document.getElementById('totalSubjects').textContent = stats.total_subjects;
    document.getElementById('todayAttendance').textContent = stats.today_attendance;
    document.getElementById('overallPercentage').textContent = stats.overall_percentage + '%';
}

