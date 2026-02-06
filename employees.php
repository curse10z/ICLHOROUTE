<?php
session_start();
include "db.php";

// Protect page from unauthorized access
if(!isset($_SESSION['admin'])){
    header("Location: login.php");
    exit();
}

// Create employees table if it doesn't exist
$createTable = "CREATE TABLE IF NOT EXISTS employees (
    employee_id VARCHAR(20) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(50) NOT NULL,
    team VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $createTable);

// Create teams table if it doesn't exist
$createTeamsTable = "CREATE TABLE IF NOT EXISTS teams (
    team_id INT AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $createTeamsTable);

// Insert default teams if table is empty
$checkTeams = "SELECT COUNT(*) as count FROM teams";
$teamsResult = mysqli_query($conn, $checkTeams);
if ($teamsResult) {
    $teamsRow = mysqli_fetch_assoc($teamsResult);
    if ($teamsRow['count'] == 0) {
        $insertTeams = "INSERT INTO teams (team_name, description) VALUES
            ('Admin', 'Administrative Team'),
            ('Frontdesk', 'Front Desk Operations'),
            ('Technical', 'Technical Support Team'),
            ('Survey', 'Survey and Assessment Team'),
            ('TXZ', 'TXZ Department'),
            ('Atty. Peter', 'Legal - Atty. Peter'),
            ('OV', 'Office of the Vice'),
            ('Eviction and Dismantling', 'Eviction and Dismantling Operations'),
            ('Legal Team', 'Legal Department'),
            ('HHRO', 'Human Resources and Housing Operations')";
        mysqli_query($conn, $insertTeams);
    }
}

// Migrate existing table structure if needed
$checkColumn = "SHOW COLUMNS FROM employees LIKE 'employee_id'";
$columnResult = mysqli_query($conn, $checkColumn);
if ($columnResult && mysqli_num_rows($columnResult) > 0) {
    $column = mysqli_fetch_assoc($columnResult);
    if ($column['Type'] == 'int(11)') {
        // Table exists with INT, need to migrate
        $migrateQuery = "ALTER TABLE employees MODIFY employee_id VARCHAR(20)";
        mysqli_query($conn, $migrateQuery);
    }
}

// Migrate username column to email if it exists
$checkEmailColumn = "SHOW COLUMNS FROM employees LIKE 'email'";
$emailColumnResult = mysqli_query($conn, $checkEmailColumn);
if ($emailColumnResult && mysqli_num_rows($emailColumnResult) == 0) {
    // Check if username column exists
    $checkUsernameColumn = "SHOW COLUMNS FROM employees LIKE 'username'";
    $usernameColumnResult = mysqli_query($conn, $checkUsernameColumn);
    if ($usernameColumnResult && mysqli_num_rows($usernameColumnResult) > 0) {
        // Rename username to email
        $renameQuery = "ALTER TABLE employees CHANGE username email VARCHAR(100) NOT NULL";
        mysqli_query($conn, $renameQuery);
    }
}

// Function to generate next employee ID in format YYYY-NN
function getNextEmployeeId($conn) {
    $currentYear = date('Y');
    $yearPrefix = $currentYear . '-';
    
    // Get all existing employee IDs for current year
    $query = "SELECT employee_id FROM employees WHERE employee_id LIKE '$yearPrefix%' ORDER BY employee_id ASC";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        // No employees for this year, start at 01
        return $yearPrefix . '01';
    }
    
    $ids = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $id = $row['employee_id'];
        // Extract the number part after the year
        if (preg_match('/' . preg_quote($yearPrefix, '/') . '(\d+)/', $id, $matches)) {
            $ids[] = (int)$matches[1];
        }
    }
    
    // Find the first gap starting from 1
    for ($i = 1; $i <= count($ids) + 1; $i++) {
        if (!in_array($i, $ids)) {
            return $yearPrefix . str_pad($i, 2, '0', STR_PAD_LEFT);
        }
    }
    
    // No gaps found, return next sequential number
    $nextNum = max($ids) + 1;
    return $yearPrefix . str_pad($nextNum, 2, '0', STR_PAD_LEFT);
}

// Handle add employee
if(isset($_POST['add_employee'])){
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = mysqli_real_escape_string($conn, trim($_POST['password']));
    $team = mysqli_real_escape_string($conn, trim($_POST['team']));
    
    // Validate inputs
    if(empty($name) || empty($email) || empty($password) || empty($team)){
        $error = "All fields are required!";
    } else {
        // Validate email format
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $error = "Please enter a valid email address!";
        } else {
            // Check if email already exists
            $checkQuery = "SELECT * FROM employees WHERE email = '$email'";
            $checkResult = mysqli_query($conn, $checkQuery);
            
            if(mysqli_num_rows($checkResult) > 0){
                $error = "Email already exists! Please use a different email address.";
            } else {
                // Get the next available ID in format YYYY-NN
                $nextId = getNextEmployeeId($conn);
                
                // Insert with formatted ID
                $query = "INSERT INTO employees (employee_id, name, email, password, team) VALUES ('$nextId', '$name', '$email', '$password', '$team')";
                if(mysqli_query($conn, $query)){
                    // Redirect to prevent form resubmission on refresh
                    header("Location: employees.php?success=Employee added successfully!");
                    exit();
                } else {
                    $error = "Error adding employee: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Handle success message from redirect
if(isset($_GET['success'])){
    $success = $_GET['success'];
}

// Handle edit employee
if(isset($_POST['edit_employee'])){
    $id = mysqli_real_escape_string($conn, trim($_POST['employee_id']));
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = mysqli_real_escape_string($conn, trim($_POST['password']));
    $team = mysqli_real_escape_string($conn, trim($_POST['team']));
    
    // Validate inputs
    if(empty($name) || empty($email) || empty($password) || empty($team)){
        $error = "All fields are required!";
    } else {
        // Validate email format
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $error = "Please enter a valid email address!";
        } else {
            // Check if email already exists for another employee
            $checkQuery = "SELECT * FROM employees WHERE email = '$email' AND employee_id != '$id'";
            $checkResult = mysqli_query($conn, $checkQuery);
            
            if(mysqli_num_rows($checkResult) > 0){
                $error = "Email already exists! Please use a different email address.";
            } else {
                // Update employee
                $updateQuery = "UPDATE employees SET name = '$name', email = '$email', password = '$password', team = '$team' WHERE employee_id = '$id'";
                
                if(mysqli_query($conn, $updateQuery)){
                    header("Location: employees.php?success=Employee updated successfully!");
                    exit();
                } else {
                    $error = "Error updating employee: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Handle delete employee
if(isset($_GET['delete'])){
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // First, verify the employee exists
    $checkQuery = "SELECT * FROM employees WHERE employee_id = '$id'";
    $checkResult = mysqli_query($conn, $checkQuery);
    
    if(mysqli_num_rows($checkResult) > 0){
        // Employee exists, proceed with deletion
        $query = "DELETE FROM employees WHERE employee_id = '$id'";
        if(mysqli_query($conn, $query)){
            // Verify deletion was successful
            $verifyQuery = "SELECT * FROM employees WHERE employee_id = '$id'";
            $verifyResult = mysqli_query($conn, $verifyQuery);
            
            if(mysqli_num_rows($verifyResult) == 0){
                // Successfully deleted
                header("Location: employees.php?success=Employee deleted successfully!");
                exit();
            } else {
                $error = "Error: Employee could not be deleted.";
            }
        } else {
            $error = "Error deleting employee: " . mysqli_error($conn);
        }
    } else {
        $error = "Employee not found or already deleted.";
    }
}

// Get employee data for editing
$editEmployee = null;
if(isset($_GET['edit'])){
    $editId = mysqli_real_escape_string($conn, $_GET['edit']);
    $editQuery = "SELECT * FROM employees WHERE employee_id = '$editId'";
    $editResult = mysqli_query($conn, $editQuery);
    if($editResult && mysqli_num_rows($editResult) > 0){
        $editEmployee = mysqli_fetch_assoc($editResult);
    }
}

// Handle search
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

// Fetch all employees with search filter
$query = "SELECT * FROM employees";
if(!empty($search)){
    // Check if email column exists, otherwise use username for backward compatibility
    $columnCheck = "SHOW COLUMNS FROM employees LIKE 'email'";
    $colResult = mysqli_query($conn, $columnCheck);
    $hasEmailColumn = ($colResult && mysqli_num_rows($colResult) > 0);
    
    $query .= " WHERE name LIKE '%$search%' 
                OR employee_id LIKE '%$search%' 
                OR team LIKE '%$search%'";
    
    if($hasEmailColumn){
        $query .= " OR email LIKE '%$search%'";
    } else {
        $query .= " OR username LIKE '%$search%'";
    }
}
$query .= " ORDER BY employee_id DESC";
$result = mysqli_query($conn, $query);

// Fetch all teams for dropdown
$teamsQuery = "SELECT team_name FROM teams ORDER BY team_name ASC";
$teamsResult = mysqli_query($conn, $teamsQuery);
$teams = [];
if ($teamsResult) {
    while ($teamRow = mysqli_fetch_assoc($teamsResult)) {
        $teams[] = $teamRow['team_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - DRIMS</title>
    <link rel="stylesheet" type="text/css" href="/ICLHO_Route/style.css">
</head>
<body>
    <div class="top-bar">
        <img src="/ICLHO_Route/ICLOGO.jpg" alt="Logo" class="top-bar-logo">
        <div class="top-bar-content">
            <div class="top-bar-title">DRIMS</div>
            <div class="top-bar-desc">Document Route Internal Management System</div>
        </div>
    </div>
    
    <!-- Menu Button Below Top Bar -->
    <div class="menu-button-container">
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
    
    <!-- Sidebar Menu -->
    <div class="sidebar hidden" id="sidebar">
        <div class="sidebar-header">
            <h3>Admin Menu</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
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
                </div>
            </div>
            <a href="messages.php" class="nav-item">
                <span class="nav-icon">💬</span>
                <span class="nav-text">Inbox</span>
            </a>
            <a href="#" class="nav-item">
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
            <a href="employees.php" class="nav-item active">
                <span class="nav-icon">👥</span>
                <span class="nav-text">Employees</span>
            </a>
            <a href="#" class="nav-item">
                <span class="nav-icon">⚙️</span>
                <span class="nav-text">Settings</span>
            </a>
            <a href="logout.php" class="nav-item logout-item">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Logout</span>
            </a>
        </nav>
    </div>
    
    <!-- Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="dashboard-container sidebar-hidden">
        <div class="employees-content">
            <div class="page-header">
                <h1>Employees Management</h1>
                <button class="btn-add" id="addEmployeeBtn">+ Add Employee</button>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Search Bar -->
            <div class="search-container">
                <form method="GET" action="" class="search-form">
                    <input type="text" name="search" class="search-input" 
                           placeholder="Search by ID, Name, Email, or Team..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-search">🔍 Search</button>
                    <?php if(!empty($search)): ?>
                        <a href="employees.php" class="btn-clear">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="table-container">
                <table class="employees-table">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Password</th>
                            <th>Team</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?php echo $row['employee_id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars(isset($row['email']) ? $row['email'] : (isset($row['username']) ? $row['username'] : '')); ?></td>
                                    <td><?php echo htmlspecialchars($row['password']); ?></td>
                                    <td><?php echo htmlspecialchars($row['team']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?php echo $row['employee_id']; ?>" 
                                               class="btn-edit" 
                                               onclick="openEditModal('<?php echo $row['employee_id']; ?>', '<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars(isset($row['email']) ? $row['email'] : (isset($row['username']) ? $row['username'] : ''), ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['password'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['team'], ENT_QUOTES); ?>'); return false;">Edit</a>
                                            <a href="?delete=<?php echo $row['employee_id']; ?>" 
                                               class="btn-delete" 
                                               onclick="return confirm('Are you sure you want to delete this employee?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="no-data">No employees found. Click "Add Employee" to add one.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Employee Modal -->
    <div class="modal" id="addEmployeeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Employee</h2>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Employee Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="example@email.com">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="team">Team</label>
                    <select id="team" name="team" required>
                        <option value="">Select a team...</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo htmlspecialchars($team); ?>"><?php echo htmlspecialchars($team); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" id="cancelBtn">Cancel</button>
                    <button type="submit" name="add_employee" class="btn-submit">Add Employee</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Employee Modal -->
    <div class="modal" id="editEmployeeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Employee</h2>
                <button class="modal-close" id="closeEditModal">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="edit_employee_id" name="employee_id">
                <div class="form-group">
                    <label for="edit_name">Employee Name</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email Address</label>
                    <input type="email" id="edit_email" name="email" required placeholder="example@email.com">
                </div>
                <div class="form-group">
                    <label for="edit_password">Password</label>
                    <input type="text" id="edit_password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="edit_team">Team</label>
                    <select id="edit_team" name="team" required>
                        <option value="">Select a team...</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo htmlspecialchars($team); ?>"><?php echo htmlspecialchars($team); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" id="cancelEditBtn">Cancel</button>
                    <button type="submit" name="edit_employee" class="btn-submit">Update Employee</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const dashboardContainer = document.querySelector('.dashboard-container');
        const menuButtonContainer = document.querySelector('.menu-button-container');
        const addEmployeeBtn = document.getElementById('addEmployeeBtn');
        const addEmployeeModal = document.getElementById('addEmployeeModal');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        
        // Toggle sidebar visibility
        menuToggle.addEventListener('click', () => {
            const isHidden = sidebar.classList.contains('hidden');
            if (isHidden) {
                sidebar.classList.remove('hidden');
                dashboardContainer.classList.remove('sidebar-hidden');
                if (menuButtonContainer) {
                    menuButtonContainer.classList.add('sidebar-open');
                }
                if (sidebarOverlay) sidebarOverlay.classList.add('active');
            } else {
                sidebar.classList.add('hidden');
                dashboardContainer.classList.add('sidebar-hidden');
                if (menuButtonContainer) {
                    menuButtonContainer.classList.remove('sidebar-open');
                }
                if (sidebarOverlay) sidebarOverlay.classList.remove('active');
            }
        });
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.add('hidden');
                dashboardContainer.classList.add('sidebar-hidden');
                if (menuButtonContainer) {
                    menuButtonContainer.classList.remove('sidebar-open');
                }
                sidebarOverlay.classList.remove('active');
            });
        }
        
        // Inbox submenu toggle
        const inboxMenu = document.getElementById('inboxMenu');
        if (inboxMenu) {
            inboxMenu.addEventListener('click', (e) => {
                if (e.target.closest('.nav-item-header')) {
                    inboxMenu.classList.toggle('active');
                }
            });
        }
        
        // Modal functionality
        addEmployeeBtn.addEventListener('click', () => {
            addEmployeeModal.classList.add('active');
        });
        
        closeModal.addEventListener('click', () => {
            addEmployeeModal.classList.remove('active');
        });
        
        cancelBtn.addEventListener('click', () => {
            addEmployeeModal.classList.remove('active');
        });
        
        // Close modal when clicking outside
        addEmployeeModal.addEventListener('click', (e) => {
            if (e.target === addEmployeeModal) {
                addEmployeeModal.classList.remove('active');
            }
        });
        
        // Edit Modal functionality
        const editEmployeeModal = document.getElementById('editEmployeeModal');
        const closeEditModal = document.getElementById('closeEditModal');
        const cancelEditBtn = document.getElementById('cancelEditBtn');
        
        function openEditModal(id, name, email, password, team) {
            document.getElementById('edit_employee_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_password').value = password;
            document.getElementById('edit_team').value = team;
            editEmployeeModal.classList.add('active');
        }
        
        closeEditModal.addEventListener('click', () => {
            editEmployeeModal.classList.remove('active');
        });
        
        cancelEditBtn.addEventListener('click', () => {
            editEmployeeModal.classList.remove('active');
        });
        
        // Close edit modal when clicking outside
        editEmployeeModal.addEventListener('click', (e) => {
            if (e.target === editEmployeeModal) {
                editEmployeeModal.classList.remove('active');
            }
        });
        
        // Open edit modal if edit parameter is in URL
        <?php if(isset($_GET['edit']) && $editEmployee): ?>
            openEditModal(
                '<?php echo htmlspecialchars($editEmployee['employee_id'], ENT_QUOTES); ?>',
                '<?php echo htmlspecialchars($editEmployee['name'], ENT_QUOTES); ?>',
                '<?php echo htmlspecialchars(isset($editEmployee['email']) ? $editEmployee['email'] : (isset($editEmployee['username']) ? $editEmployee['username'] : ''), ENT_QUOTES); ?>',
                '<?php echo htmlspecialchars($editEmployee['password'], ENT_QUOTES); ?>',
                '<?php echo htmlspecialchars($editEmployee['team'], ENT_QUOTES); ?>'
            );
        <?php endif; ?>
    </script>
</body>
</html>
