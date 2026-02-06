<?php
session_start();
include "db.php";

// Protect dashboard: admin only
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$userName = $_SESSION['admin'];

// Fetch statistics
$totalDocuments = 0;
$pendingDocuments = 0;
$totalEmployees = 0;
$unreadMessages = 0;

// Get total documents
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM documents");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalDocuments = $row['count'];
}


// Get total employees
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM employees");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalEmployees = $row['count'];
}

// Get unread messages for admin
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM messages WHERE recipient_type = 'admin' AND is_read = 0");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $unreadMessages = $row['count'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DRIMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="/ICLHO_Route/style.css">
    <style>
        /* CSS Variables for Design System */
        :root {
            --primary: #4267B2;
            --primary-dark: #365899;
            --primary-light: #5b7bd5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --spacing-md: 16px;
            --spacing-lg: 24px;
            --spacing-xl: 32px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-top: var(--spacing-xl);
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-xl);
            padding: 28px;
            box-shadow: var(--shadow-xl);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 30px rgba(102, 126, 234, 0.3);
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 12px;
            display: block;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }

        .stat-value {
            font-size: 2.8rem;
            font-weight: 800;
            margin: 12px 0;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 0.95rem;
            color: var(--gray-600);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        .welcome-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-xl);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .welcome-section h1 {
            margin: 0 0 12px 0;
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }

        .welcome-section p {
            margin: 0;
            font-size: 1.15rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        .system-info {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-xl);
            padding: 28px;
            margin-top: var(--spacing-xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .system-info h2 {
            margin-top: 0;
            font-size: 1.6rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .system-info p {
            color: var(--gray-700);
            line-height: 1.7;
            font-size: 1.05rem;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <img src="/ICLHO_Route/ICLOGO.jpg" alt="Logo" class="top-bar-logo">
        <div class="top-bar-content">
            <div class="top-bar-title">DRIMS</div>
            <div class="top-bar-desc">Document Route Internal Management System</div>
        </div>
    </div>

    <div class="menu-button-container">
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>

    <div class="sidebar hidden" id="sidebar">
        <div class="sidebar-header">
            <h3>Admin Menu</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
                <span class="nav-icon">🏠</span>
                <span class="nav-text">Dashboard</span>
            </a>
            <div class="nav-item nav-item-parent" id="inboxMenu">
                <div class="nav-item-header">
                    <span class="nav-icon">📬</span>
                    <span class="nav-text">Routing Tray</span>
                    <span class="nav-arrow">▼</span>
                </div>
                <div class="nav-submenu" id="inboxSubmenu">
                    <a href="inbox.php?type=new" class="nav-subitem">
                        <span class="nav-icon">🆕</span>
                        <span class="nav-text">New</span>
                    </a>
                    <a href="inbox.php?type=incoming" class="nav-subitem">
                        <span class="nav-icon">📥</span>
                        <span class="nav-text">Incoming Routed Documents</span>
                    </a>
                    <a href="inbox.php?type=outgoing" class="nav-subitem">
                        <span class="nav-icon">📤</span>
                        <span class="nav-text">Outgoing Routed</span>
                    </a>
                    <a href="messages.php" class="nav-subitem">
                        <span class="nav-icon">💬</span>
                        <span class="nav-text">Inbox</span>
                    </a>
                </div>
            </div>
            <a href="file_management.php" class="nav-item">
                <span class="nav-icon">📄</span>
                <span class="nav-text">File Management</span>
            </a>
            <a href="new_document.php" class="nav-item">
                <span class="nav-icon">📤</span>
                <span class="nav-text">Document Upload</span>
            </a>
            <a href="#" class="nav-item">
                <span class="nav-icon">📋</span>
                <span class="nav-text">Routes</span>
            </a>
            <a href="employees.php" class="nav-item">
                <span class="nav-icon">👥</span>
                <span class="nav-text">Employees</span>
            </a>
            <a href="logout.php" class="nav-item logout-item">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Logout</span>
            </a>
        </nav>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="dashboard-container sidebar-hidden">
        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>Welcome back, <?php echo htmlspecialchars($userName); ?>! 👋</h1>
                <p>Here's an overview of your document routing system</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-icon">📄</span>
                    <div class="stat-value"><?php echo $totalDocuments; ?></div>
                    <div class="stat-label">Total Documents</div>
                </div>


                <div class="stat-card">
                    <span class="stat-icon">👥</span>
                    <div class="stat-value"><?php echo $totalEmployees; ?></div>
                    <div class="stat-label">Total Employees</div>
                </div>

                <div class="stat-card">
                    <span class="stat-icon">💬</span>
                    <div class="stat-value"><?php echo $unreadMessages; ?></div>
                    <div class="stat-label">Unread Messages</div>
                </div>
            </div>

            <div class="system-info">
                <h2>📋 About DRIMS</h2>
                <p><strong>Document Route Internal Management System (DRIMS)</strong> is designed to streamline document routing and management within your organization. Track documents, manage employee access, and maintain efficient communication through our integrated messaging system.</p>
            </div>
        </div>
    </div>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const dashboardContainer = document.querySelector('.dashboard-container');
        const menuButtonContainer = document.querySelector('.menu-button-container');

        menuToggle.addEventListener('click', () => {
            const isHidden = sidebar.classList.contains('hidden');
            if (isHidden) {
                sidebar.classList.remove('hidden');
                dashboardContainer.classList.remove('sidebar-hidden');
                if (menuButtonContainer) menuButtonContainer.classList.add('sidebar-open');
                if (sidebarOverlay) sidebarOverlay.classList.add('active');
            } else {
                sidebar.classList.add('hidden');
                dashboardContainer.classList.add('sidebar-hidden');
                if (menuButtonContainer) menuButtonContainer.classList.remove('sidebar-open');
                if (sidebarOverlay) sidebarOverlay.classList.remove('active');
            }
        });

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.add('hidden');
                dashboardContainer.classList.add('sidebar-hidden');
                if (menuButtonContainer) menuButtonContainer.classList.remove('sidebar-open');
                sidebarOverlay.classList.remove('active');
            });
        }

        const inboxMenu = document.getElementById('inboxMenu');
        if (inboxMenu) {
            inboxMenu.addEventListener('click', (e) => {
                if (e.target.closest('.nav-item-header')) inboxMenu.classList.toggle('active');
            });
        }

    </script>
</body>
</html>
