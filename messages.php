<?php
session_start();
include "db.php";

// Protect page from unauthorized access
if(!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])){
    header("Location: login.php");
    exit();
}

// Create messages table if it doesn't exist (make subject optional)
$createTable = "CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id VARCHAR(20) NOT NULL,
    sender_type ENUM('admin', 'employee') NOT NULL,
    sender_name VARCHAR(100) NOT NULL,
    recipient_id VARCHAR(20) NOT NULL,
    recipient_type ENUM('admin', 'employee') NOT NULL,
    recipient_name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient (recipient_id, recipient_type),
    INDEX idx_sender (sender_id, sender_type),
    INDEX idx_created (created_at)
)";
mysqli_query($conn, $createTable);

// Modify existing table to make subject nullable if needed
$alterTable = "ALTER TABLE messages MODIFY subject VARCHAR(255) DEFAULT NULL";
@mysqli_query($conn, $alterTable);

// Determine user type and info
$isAdmin = isset($_SESSION['admin']);
$userType = $isAdmin ? 'admin' : 'employee';
$userId = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_id'];
$userName = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_name'];

// Handle send message
if(isset($_POST['send_message'])){
    $recipient_id = mysqli_real_escape_string($conn, trim($_POST['recipient_id']));
    $recipient_type = mysqli_real_escape_string($conn, trim($_POST['recipient_type']));
    $message = mysqli_real_escape_string($conn, trim($_POST['message']));
    
    if(empty($recipient_id) || empty($message)){
        $error = "Recipient and message are required!";
    } else {
        // Get recipient name
        $recipientName = '';
        if($recipient_type == 'admin'){
            $recipientQuery = mysqli_query($conn, "SELECT username FROM admin WHERE username = '$recipient_id'");
            if($recipientRow = mysqli_fetch_assoc($recipientQuery)){
                $recipientName = $recipientRow['username'];
            }
        } else {
            $recipientQuery = mysqli_query($conn, "SELECT name FROM employees WHERE employee_id = '$recipient_id'");
            if($recipientRow = mysqli_fetch_assoc($recipientQuery)){
                $recipientName = $recipientRow['name'];
            }
        }
        
        if($recipientName){
            $senderName = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_name'];
            $insertQuery = "INSERT INTO messages (sender_id, sender_type, sender_name, recipient_id, recipient_type, recipient_name, message) 
                           VALUES ('$userId', '$userType', '$senderName', '$recipient_id', '$recipient_type', '$recipientName', '$message')";
            if(mysqli_query($conn, $insertQuery)){
                header("Location: messages.php?chat=" . urlencode($recipient_id) . "&type=" . urlencode($recipient_type));
                exit();
            } else {
                $error = "Error sending message: " . mysqli_error($conn);
            }
        } else {
            $error = "Recipient not found!";
        }
    }
}

// Handle mark conversation as read
if(isset($_GET['mark_read']) && isset($_GET['chat']) && isset($_GET['type'])){
    $chatId = mysqli_real_escape_string($conn, $_GET['chat']);
    $chatType = mysqli_real_escape_string($conn, $_GET['type']);
    $updateQuery = "UPDATE messages SET is_read = 1 WHERE recipient_id = '$userId' AND recipient_type = '$userType' AND sender_id = '$chatId' AND sender_type = '$chatType'";
    mysqli_query($conn, $updateQuery);
    header("Location: messages.php?chat=" . urlencode($chatId) . "&type=" . urlencode($chatType));
    exit();
}

// Get selected conversation
$selectedChatId = isset($_GET['chat']) ? mysqli_real_escape_string($conn, $_GET['chat']) : null;
$selectedChatType = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : null;

// Get all conversations (group messages by the other person)
$conversations = array();
$convQuery = "SELECT 
    CASE 
        WHEN sender_id = '$userId' AND sender_type = '$userType' THEN recipient_id
        ELSE sender_id
    END as other_user_id,
    CASE 
        WHEN sender_id = '$userId' AND sender_type = '$userType' THEN recipient_type
        ELSE sender_type
    END as other_user_type,
    CASE 
        WHEN sender_id = '$userId' AND sender_type = '$userType' THEN recipient_name
        ELSE sender_name
    END as other_user_name,
    MAX(message_id) as last_message_id,
    MAX(created_at) as last_message_time,
    SUM(CASE WHEN recipient_id = '$userId' AND recipient_type = '$userType' AND is_read = 0 THEN 1 ELSE 0 END) as unread_count
FROM messages
WHERE (sender_id = '$userId' AND sender_type = '$userType') OR (recipient_id = '$userId' AND recipient_type = '$userType')
GROUP BY other_user_id, other_user_type, other_user_name
ORDER BY last_message_time DESC";

$convResult = mysqli_query($conn, $convQuery);
while($conv = mysqli_fetch_assoc($convResult)){
    // Get last message preview
    $lastMsgQuery = "SELECT message, created_at FROM messages WHERE message_id = " . $conv['last_message_id'];
    $lastMsgResult = mysqli_query($conn, $lastMsgQuery);
    $lastMsg = mysqli_fetch_assoc($lastMsgResult);
    
    $conversations[] = array(
        'user_id' => $conv['other_user_id'],
        'user_type' => $conv['other_user_type'],
        'user_name' => $conv['other_user_name'],
        'last_message' => $lastMsg['message'],
        'last_message_time' => $conv['last_message_time'],
        'unread_count' => $conv['unread_count']
    );
}

// Get chat partner name for selected conversation
$chatPartnerName = '';
if($selectedChatId && $selectedChatType){
    // First try to get from conversations list
    foreach($conversations as $conv){
        if($conv['user_id'] == $selectedChatId && $conv['user_type'] == $selectedChatType){
            $chatPartnerName = $conv['user_name'];
            break;
        }
    }
    
    // If not found, get from recipients list or database
    if(empty($chatPartnerName)){
        if($selectedChatType == 'admin'){
            $partnerQuery = mysqli_query($conn, "SELECT username FROM admin WHERE username = '$selectedChatId'");
            if($partnerRow = mysqli_fetch_assoc($partnerQuery)){
                $chatPartnerName = $partnerRow['username'];
            }
        } else {
            $partnerQuery = mysqli_query($conn, "SELECT name FROM employees WHERE employee_id = '$selectedChatId'");
            if($partnerRow = mysqli_fetch_assoc($partnerQuery)){
                $chatPartnerName = $partnerRow['name'];
            }
        }
    }
}

// Get messages for selected conversation
$chatMessages = array();
if($selectedChatId && $selectedChatType){
    $chatQuery = "SELECT * FROM messages 
                  WHERE ((sender_id = '$userId' AND sender_type = '$userType' AND recipient_id = '$selectedChatId' AND recipient_type = '$selectedChatType')
                      OR (recipient_id = '$userId' AND recipient_type = '$userType' AND sender_id = '$selectedChatId' AND sender_type = '$selectedChatType'))
                  ORDER BY created_at ASC";
    $chatResult = mysqli_query($conn, $chatQuery);
    while($msg = mysqli_fetch_assoc($chatResult)){
        $chatMessages[] = $msg;
    }
    
    // Mark messages as read when viewing conversation
    mysqli_query($conn, "UPDATE messages SET is_read = 1 WHERE recipient_id = '$userId' AND recipient_type = '$userType' AND sender_id = '$selectedChatId' AND sender_type = '$selectedChatType'");
    
    // Add to conversations list if not already there (for new chats)
    $foundInList = false;
    foreach($conversations as $conv){
        if($conv['user_id'] == $selectedChatId && $conv['user_type'] == $selectedChatType){
            $foundInList = true;
            break;
        }
    }
    if(!$foundInList && !empty($chatPartnerName)){
        array_unshift($conversations, array(
            'user_id' => $selectedChatId,
            'user_type' => $selectedChatType,
            'user_name' => $chatPartnerName,
            'last_message' => 'No messages yet',
            'last_message_time' => date('Y-m-d H:i:s'),
            'unread_count' => 0
        ));
    }
}

// Get unread count for badge
$unreadQuery = "SELECT COUNT(*) as unread_count FROM messages WHERE recipient_id = '$userId' AND recipient_type = '$userType' AND is_read = 0";
$unreadResult = mysqli_query($conn, $unreadQuery);
$unreadData = mysqli_fetch_assoc($unreadResult);
$unreadCount = $unreadData['unread_count'];

// Get all employees and admin for new conversation
$recipients = array();
if($isAdmin){
    $empQuery = "SELECT employee_id, name, email FROM employees ORDER BY name";
    $empResult = mysqli_query($conn, $empQuery);
    while($emp = mysqli_fetch_assoc($empResult)){
        $recipients[] = array('id' => $emp['employee_id'], 'name' => $emp['name'], 'type' => 'employee');
    }
} else {
    $adminQuery = "SELECT username FROM admin";
    $adminResult = mysqli_query($conn, $adminQuery);
    while($admin = mysqli_fetch_assoc($adminResult)){
        $recipients[] = array('id' => $admin['username'], 'name' => $admin['username'], 'type' => 'admin');
    }
    
    $empQuery = "SELECT employee_id, name, email FROM employees WHERE employee_id != '$userId' ORDER BY name";
    $empResult = mysqli_query($conn, $empQuery);
    while($emp = mysqli_fetch_assoc($empResult)){
        $recipients[] = array('id' => $emp['employee_id'], 'name' => $emp['name'], 'type' => 'employee');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - DRIMS</title>
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
            <h3><?php echo $isAdmin ? 'Admin' : 'Employee'; ?> Menu</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="<?php echo $isAdmin ? 'dashboard.php' : 'employee_dashboard.php'; ?>" class="nav-item">
                <span class="nav-icon">🏠</span>
                <span class="nav-text">Dashboard</span>
            </a>
            <div class="nav-item nav-item-parent active" id="routingTrayMenu">
                <div class="nav-item-header">
                    <span class="nav-icon">📬</span>
                    <span class="nav-text">Routing Tray</span>
                    <span class="nav-arrow">▼</span>
                </div>
                <div class="nav-submenu active" id="routingTraySubmenu">
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
                    <a href="messages.php" class="nav-subitem active">
                        <span class="nav-icon">💬</span>
                        <span class="nav-text">Inbox</span>
                        <?php if($unreadCount > 0): ?>
                            <span class="unread-badge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
            <?php if($isAdmin): ?>
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
                <a href="employees.php" class="nav-item">
                    <span class="nav-icon">👥</span>
                    <span class="nav-text">Employees</span>
                </a>
            <?php else: ?>
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
                <a href="#" class="nav-item">
                    <span class="nav-icon">👤</span>
                    <span class="nav-text">Profile</span>
                </a>
            <?php endif; ?>
            <a href="logout.php" class="nav-item logout-item">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Logout</span>
            </a>
        </nav>
    </div>
    
    <!-- Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="dashboard-container sidebar-hidden">
        <div class="messenger-container">
            <!-- Conversations List -->
            <div class="conversations-panel">
                <div class="conversations-header">
                    <h2>Messages</h2>
                    <button type="button" class="btn-new-chat" onclick="openNewChatModal()">+ New Chat</button>
                </div>
                <div class="conversations-list">
                    <?php if(empty($conversations)): ?>
                        <div class="no-conversations">No conversations yet. Start a new chat!</div>
                    <?php else: ?>
                        <?php foreach($conversations as $conv): ?>
                            <a href="messages.php?chat=<?php echo urlencode($conv['user_id']); ?>&type=<?php echo urlencode($conv['user_type']); ?>" 
                               class="conversation-item <?php echo ($selectedChatId == $conv['user_id'] && $selectedChatType == $conv['user_type']) ? 'active' : ''; ?>">
                                <div class="conversation-avatar">
                                    <?php echo strtoupper(substr($conv['user_name'], 0, 1)); ?>
                                </div>
                                <div class="conversation-info">
                                    <div class="conversation-name-row">
                                        <span class="conversation-name"><?php echo htmlspecialchars($conv['user_name']); ?></span>
                                        <span class="conversation-time"><?php echo date('M d', strtotime($conv['last_message_time'])); ?></span>
                                    </div>
                                    <div class="conversation-preview-row">
                                        <span class="conversation-preview"><?php echo htmlspecialchars(substr($conv['last_message'], 0, 50)); ?><?php echo strlen($conv['last_message']) > 50 ? '...' : ''; ?></span>
                                        <?php if($conv['unread_count'] > 0): ?>
                                            <span class="conversation-unread"><?php echo $conv['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chat Panel -->
            <div class="chat-panel">
                <?php if($selectedChatId && $selectedChatType && !empty($chatPartnerName)): ?>
                    <div class="chat-header">
                        <div class="chat-partner-info">
                            <div class="chat-avatar"><?php echo strtoupper(substr($chatPartnerName, 0, 1)); ?></div>
                            <div>
                                <div class="chat-partner-name"><?php echo htmlspecialchars($chatPartnerName); ?></div>
                                <div class="chat-status">Online</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chat-messages" id="chatMessages">
                        <?php if(empty($chatMessages)): ?>
                            <div class="no-messages-yet">
                                <p>No messages yet. Start the conversation!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($chatMessages as $msg): ?>
                                <div class="message-bubble <?php echo ($msg['sender_id'] == $userId && $msg['sender_type'] == $userType) ? 'sent' : 'received'; ?>">
                                    <div class="message-content">
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                    </div>
                                    <div class="message-time"><?php echo date('h:i A', strtotime($msg['created_at'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="chat-input-container">
                        <form method="POST" action="messages.php?chat=<?php echo urlencode($selectedChatId); ?>&type=<?php echo urlencode($selectedChatType); ?>" class="chat-form">
                            <input type="hidden" name="recipient_id" value="<?php echo htmlspecialchars($selectedChatId); ?>">
                            <input type="hidden" name="recipient_type" value="<?php echo htmlspecialchars($selectedChatType); ?>">
                            <textarea name="message" class="chat-input" placeholder="Type a message..." rows="1" required></textarea>
                            <button type="submit" name="send_message" class="btn-send">Send</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="no-chat-selected">
                        <div class="no-chat-icon">💬</div>
                        <h3>Select a conversation</h3>
                        <p>Choose a conversation from the list to start messaging</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- New Chat Modal -->
    <div id="newChatModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>New Chat</h2>
                <span class="close" onclick="closeNewChatModal()">&times;</span>
            </div>
            <form method="GET" action="messages.php">
                <div class="form-group">
                    <label>Start conversation with:</label>
                    <select name="chat" required>
                        <option value="">Select a person</option>
                        <?php foreach($recipients as $recipient): ?>
                            <option value="<?php echo htmlspecialchars($recipient['id']); ?>">
                                <?php echo htmlspecialchars($recipient['name']); ?> (<?php echo ucfirst($recipient['type']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="type" id="chat_type" value="">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Start Chat</button>
                    <button type="button" class="btn-secondary" onclick="closeNewChatModal()">Cancel</button>
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
        
        // Routing Tray submenu toggle
        const routingTrayMenu = document.getElementById('routingTrayMenu');
        if (routingTrayMenu) {
            routingTrayMenu.addEventListener('click', (e) => {
                if (e.target.closest('.nav-item-header')) {
                    routingTrayMenu.classList.toggle('active');
                }
            });
        }
        
        // Set chat type based on selection
        const chatSelect = document.querySelector('select[name="chat"]');
        if(chatSelect) {
            chatSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if(selectedOption.value) {
                    const chatType = selectedOption.text.includes('(Admin)') ? 'admin' : 'employee';
                    document.getElementById('chat_type').value = chatType;
                }
            });
            
            // Also set on form submit to ensure it's set
            const newChatForm = chatSelect.closest('form');
            if(newChatForm) {
                newChatForm.addEventListener('submit', function(e) {
                    const selectedOption = chatSelect.options[chatSelect.selectedIndex];
                    if(selectedOption.value) {
                        const chatType = selectedOption.text.includes('(Admin)') ? 'admin' : 'employee';
                        document.getElementById('chat_type').value = chatType;
                    } else {
                        e.preventDefault();
                        alert('Please select a person to chat with');
                    }
                });
            }
        }
        
        // Initialize modal and set up event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Add click event listener to New Chat button
            const newChatBtn = document.querySelector('.btn-new-chat');
            if(newChatBtn) {
                newChatBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openNewChatModal();
                });
            }
        });
        
        // New Chat Modal functions
        function openNewChatModal() {
            const modal = document.getElementById('newChatModal');
            if(modal) {
                modal.classList.add('active');
                modal.style.display = 'flex';
            } else {
                console.error('Modal element not found');
                alert('Error: Modal not found. Please refresh the page.');
            }
        }
        
        function closeNewChatModal() {
            const modal = document.getElementById('newChatModal');
            if(modal) {
                modal.classList.remove('active');
                modal.style.display = 'none';
            }
        }
        
        // Make functions globally accessible
        window.openNewChatModal = openNewChatModal;
        window.closeNewChatModal = closeNewChatModal;
        
        // Auto-resize textarea
        const chatInput = document.querySelector('.chat-input');
        if(chatInput) {
            chatInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        }
        
        // Scroll to bottom of chat
        const chatMessages = document.getElementById('chatMessages');
        if(chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const newChatModal = document.getElementById('newChatModal');
            if (event.target == newChatModal) {
                closeNewChatModal();
            }
        }
        
        // Auto-scroll on new messages
        if(chatMessages) {
            const observer = new MutationObserver(() => {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            });
            observer.observe(chatMessages, { childList: true });
        }
    </script>
</body>
</html>
