<?php
session_start();
include __DIR__ . '/../dbConfig.php';

// Check if admin is logged in
if (empty($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$admin_id = intval($_SESSION['admin_id']);

// === 1. FETCH ALL TICKETS (From 'inquiry' table) ===
$query = "
    SELECT 
        i.Inquiry_ID AS Message_ID,
        i.User_ID_FK,
        i.Message,
        i.Inquiry_Date AS Timestamp,
        i.Subject,
        i.Status,
        u.Username,
        u.Email
    FROM inquiry i
    JOIN user u ON i.User_ID_FK = u.User_ID
    ORDER BY i.Inquiry_Date DESC
";
$result = $conn->query($query);

$tickets = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
}

// Handle viewing/responding to a specific ticket
$current_ticket = null;
$chat_history = [];
$customer_id = null;

if (isset($_GET['ticket_id'])) {
    $ticket_id = intval($_GET['ticket_id']);
    
    // Get the ticket details
    $stmt = $conn->prepare("
        SELECT 
            i.Inquiry_ID AS Message_ID,
            i.User_ID_FK,
            i.Message,
            i.Subject,
            i.Inquiry_Date AS Timestamp,
            i.Response,
            i.Response_Date,
            i.Status,
            u.Username,
            u.Email
        FROM inquiry i
        JOIN user u ON i.User_ID_FK = u.User_ID
        WHERE i.Inquiry_ID = ?
    ");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result_ticket = $stmt->get_result();
    $current_ticket = $result_ticket->fetch_assoc();
    $stmt->close();
    
    if ($current_ticket) {
        $customer_id = $current_ticket['User_ID_FK'];
        
        // === CONSTRUCT CHAT HISTORY FROM SINGLE ROW ===
        // 1. Add Customer Message
        $chat_history[] = [
            'Message' => $current_ticket['Message'],
            'Timestamp' => $current_ticket['Timestamp'],
            'Is_Admin' => 0 // 0 = Customer
        ];

        // 2. Add Admin Response (if exists)
        if (!empty($current_ticket['Response'])) {
            $chat_history[] = [
                'Message' => $current_ticket['Response'],
                'Timestamp' => $current_ticket['Response_Date'],
                'Is_Admin' => 1 // 1 = Admin
            ];
        }
    }
}

// Handle sending a reply (UPDATES the existing row)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    // Note: We use ticket_id (Inquiry_ID) passed via GET or Hidden Input
    $ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;
    $reply_message = trim($_POST['reply_message']);
    
    if (!empty($reply_message) && $ticket_id > 0) {
        $stmt = $conn->prepare("
            UPDATE inquiry 
            SET Response = ?, 
                Response_Date = NOW(), 
                A_ID_FK = ?, 
                Status = 'Replied' 
            WHERE Inquiry_ID = ?
        ");
        $stmt->bind_param("sii", $reply_message, $admin_id, $ticket_id);
        
        if ($stmt->execute()) {
            // Refresh page
            header("Location: chat.php?ticket_id=" . $ticket_id);
            exit();
        }
        $stmt->close();
    }
}

// Handle closing/resolving a ticket
if (isset($_GET['resolve']) && isset($_GET['ticket_id'])) {
    $ticket_id = intval($_GET['ticket_id']);
    $stmt = $conn->prepare("UPDATE inquiry SET Status = 'Resolved' WHERE Inquiry_ID = ?");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    header("Location: chat.php?ticket_id=" . $ticket_id); // Stay on page to see status change
    exit();
}

// Handle marking as unread (Optional logic removed for simplicity or mapped to status)
if (isset($_GET['action']) && isset($_GET['ticket_id'])) {
    header("Location: chat.php");
    exit();
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer Support Chat | Admin Panel</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --primary-color: #8a2be2;
      --sidebar-width: 250px;
    }
    
    body {
      background-color: #f8f9fa;
    }
    
    .sidebar {
      background: linear-gradient(135deg, #040404ff, #362b3f5a);
      min-height: 100vh;
      width: var(--sidebar-width);
      position: fixed;
      left: 0;
      top: 0;
      z-index: 100;
      box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    }
    
    .main-content {
      margin-left: var(--sidebar-width);
      padding: 20px;
    }
    
    .nav-link {
      color: #e0e0e0 !important;
      padding: 12px 20px;
      margin: 5px 0;
      border-radius: 8px;
      transition: all 0.3s;
    }
    
    .nav-link:hover, .nav-link.active {
      background-color: rgba(144, 137, 151, 0.92);
      color: white !important;
    }
    
    .nav-link i {
      width: 20px;
      margin-right: 10px;
    }
    
    .card {
      border: none;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.05);
      margin-bottom: 20px;
    }
    
    .card-header {
      background-color: white;
      border-bottom: 2px solid #f0f0f0;
      font-weight: 600;
    }
    
    .ticket-card {
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
      border-left: 4px solid transparent;
    }
    
    .ticket-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 12px rgba(0,0,0,0.1);
    }
    
    .ticket-card.unread {
      border-left-color: var(--primary-color);
      background-color: rgba(138, 43, 226, 0.05);
    }
    
    .badge-new {
      background-color: #dc3545;
      animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
      0% { opacity: 1; }
      50% { opacity: 0.7; }
      100% { opacity: 1; }
    }
    
    .chat-container {
      height: 500px;
      overflow-y: auto;
      border-radius: 10px;
      background-color: #f8f9fa;
    }
    
    .message-bubble {
      max-width: 70%;
      padding: 12px 16px;
      border-radius: 18px;
      margin-bottom: 12px;
      position: relative;
      word-wrap: break-word;
    }
    
    .customer-message {
      background-color: #e9ecef;
      align-self: flex-start;
      border-bottom-left-radius: 4px;
    }
    
    .admin-message {
      background-color: var(--primary-color);
      color: white;
      align-self: flex-end;
      border-bottom-right-radius: 4px;
    }
    
    .message-time {
      font-size: 0.75rem;
      opacity: 0.7;
      margin-top: 4px;
    }
    
    .customer-info {
      background: linear-gradient(135deg, var(--primary-color) 0%, #614d73 100%);
      color: white;
      border-radius: 10px;
      padding: 20px;
    }
    
    .status-badge {
      font-size: 0.75rem;
      padding: 4px 8px;
      border-radius: 12px;
    }
    
    .status-Pending {
      background-color: #ffc107;
      color: black;
    }
    
    .status-Replied {
      background-color: #28a745;
      color: white;
    }
    
    .status-Resolved {
      background-color: #6c757d;
      color: white;
    }
    
    .chat-input {
      border-radius: 25px;
      border: 2px solid #e0e0e0;
      padding: 12px 20px;
      resize: none;
    }
    
    .chat-input:focus {
      border-color: var(--primary-color);
      box-shadow: none;
    }
    
    .btn-send {
      border-radius: 50%;
      width: 50px;
      height: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
  </style>
</head>
<body>

<div class="d-flex">
  <div class="sidebar p-1">
    <div class="p-4">
      <h4 class="text-white mb-4">
        <i class="me-4"></i>Admin Panel
      </h4>
      <ul class="nav flex-column">
        <li class="nav-item">
          <a class="nav-link" href="admindashboard.php">
            <i class="fa fa-tachometer-alt"></i>Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="products.php">
            <i class="fa fa-box"></i>Products
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="orders.php">
            <i class="fa fa-shopping-bag"></i>Orders
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="users.php">
            <i class="fa fa-users"></i>Users
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="categories.php">
            <i class="fa fa-tags"></i>Categories
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="offers.php">
            <i class="fa fa-percentage"></i>Offers & Promotions
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="chat.php">
            <i class="fa fa-comments"></i>Customer Support
          </a>
        </li>
        <li class="nav-item mt-4">
          <a class="nav-link text-warning" href="?logout=1">
            <i class="fa fa-sign-out-alt"></i>Logout
          </a>
        </li>
      </ul>
    </div>
  </div>

  <div class="main-content flex-grow-1">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h2 class="mb-1">
            <i class="fas fa-comments text-primary me-2"></i>Customer Support
          </h2>
          <p class="text-muted mb-0">Manage customer inquiries and support tickets</p>
        </div>
        <div class="d-flex align-items-center">
          <span class="badge bg-primary me-3"><?= count($tickets) ?> Active Tickets</span>
          <button class="btn btn-outline-primary" onclick="refreshTickets()">
            <i class="fas fa-sync-alt"></i> Refresh
          </button>
        </div>
      </div>

      <div class="row">
        <div class="<?= $current_ticket ? 'col-md-4' : 'col-md-12' ?>">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Support Tickets</h5>
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                  <i class="fas fa-filter"></i> Filter
                </button>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="?filter=all">All Tickets</a></li>
                  <li><a class="dropdown-item" href="?filter=unread">Unread</a></li>
                  <li><a class="dropdown-item" href="?filter=open">Open</a></li>
                  <li><a class="dropdown-item" href="?filter=resolved">Resolved</a></li>
                </ul>
              </div>
            </div>
            <div class="card-body p-0">
              <?php if (empty($tickets)): ?>
                <div class="text-center py-5">
                  <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                  <h5 class="text-muted">No Support Tickets</h5>
                  <p class="text-muted">No customer inquiries at the moment</p>
                </div>
              <?php else: ?>
                <div class="list-group list-group-flush">
                  <?php foreach ($tickets as $ticket): 
                    $is_active = isset($_GET['ticket_id']) && $_GET['ticket_id'] == $ticket['Message_ID'];
                    $is_unread = ($ticket['Status'] == 'Pending'); 
                    $status_class = 'status-' . ($ticket['Status'] ?? 'Pending');
                    ?>
                    <a href="chat.php?ticket_id=<?= $ticket['Message_ID'] ?>" 
                       class="list-group-item list-group-item-action p-3 ticket-card <?= $is_unread ? 'unread' : '' ?> <?= $is_active ? 'active' : '' ?>">
                      <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                          <div class="d-flex align-items-center mb-1">
                            <h6 class="mb-0 me-2"><?= htmlspecialchars($ticket['Username']) ?></h6>
                            <?php if ($is_unread): ?>
                              <span class="badge badge-new">NEW</span>
                            <?php endif; ?>
                          </div>
                          <p class="text-muted small mb-1">
                            <i class="fas fa-tag me-1"></i>
                            <?= htmlspecialchars($ticket['Subject'] ?? 'No Subject') ?>
                          </p>
                          <p class="mb-1 text-truncate" style="max-width: 300px;">
                            <?= htmlspecialchars(substr($ticket['Message'], 0, 100)) ?>...
                          </p>
                          <small class="text-muted">
                            <i class="far fa-clock me-1"></i>
                            <?= date('M d, Y H:i', strtotime($ticket['Timestamp'])) ?>
                          </small>
                        </div>
                        <div class="ms-3 text-end">
                          <span class="badge bg-light text-dark mb-2">
                            <i class="fas fa-envelope me-1"></i>
                            <?= htmlspecialchars($ticket['Email']) ?>
                          </span><br>
                          <span class="badge <?= $status_class ?>"><?= htmlspecialchars($ticket['Status'] ?? 'Pending') ?></span>
                        </div>
                      </div>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <?php if ($current_ticket): 
             $status_class = 'status-' . ($current_ticket['Status'] ?? 'Pending');
        ?>
          <div class="col-md-8">
            <div class="card">
              <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                  <h5 class="mb-0">
                    <i class="fas fa-user-circle me-2"></i>
                    <?= htmlspecialchars($current_ticket['Username']) ?>
                  </h5>
                  <small class="text-muted"><?= htmlspecialchars($current_ticket['Email']) ?></small>
                </div>
                <div>
                  <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#resolveModal">
                    <i class="fas fa-check-circle"></i> Mark as Resolved
                  </button>
                  <a href="chat.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-times"></i> Close
                  </a>
                </div>
              </div>
              
              <div class="card-body">
                <div class="customer-info mb-4">
                  <div class="row">
                    <div class="col-md-8">
                      <h6 class="mb-2">
                        <i class="fas fa-tag me-2"></i>
                        <?= htmlspecialchars($current_ticket['Subject'] ?? 'No Subject') ?>
                      </h6>
                      <p class="mb-0 small">Initial inquiry received on <?= date('F j, Y \a\t H:i', strtotime($current_ticket['Timestamp'])) ?></p>
                    </div>
                    <div class="col-md-4 text-end">
                      <span class="badge status-badge <?= $status_class ?>"><?= $current_ticket['Status'] ?? 'Pending' ?></span>
                    </div>
                  </div>
                </div>

                <div class="chat-container p-3 mb-4" id="chatHistory">
                  <?php if (empty($chat_history)): ?>
                    <div class="text-center py-5">
                      <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                      <p class="text-muted">No messages yet</p>
                    </div>
                  <?php else: ?>
                    <div class="d-flex flex-column">
                      <?php foreach ($chat_history as $message): 
                        $is_admin = $message['Is_Admin'] == 1;
                        ?>
                        <div class="d-flex <?= $is_admin ? 'justify-content-end' : 'justify-content-start' ?> mb-3">
                          <div class="message-bubble <?= $is_admin ? 'admin-message' : 'customer-message' ?>">
                            <div class="message-content">
                              <?= nl2br(htmlspecialchars($message['Message'])) ?>
                            </div>
                            <div class="message-time text-end">
                              <?= date('H:i', strtotime($message['Timestamp'])) ?>
                              <?php if ($is_admin): ?>
                                <i class="fas fa-user-shield ms-1"></i>
                              <?php else: ?>
                                <i class="fas fa-user ms-1"></i>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>

                <form method="POST" action="">
                  <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                  <div class="input-group">
                    <textarea name="reply_message" 
                              class="form-control chat-input" 
                              rows="3" 
                              placeholder="Type your reply here..." 
                              required></textarea>
                    <button type="submit" name="send_reply" class="btn btn-primary btn-send ms-2">
                      <i class="fas fa-paper-plane"></i>
                    </button>
                  </div>
                  <div class="mt-2 text-end">
                    <small class="text-muted">
                      <i class="fas fa-info-circle"></i> Press Shift+Enter for new line
                    </small>
                  </div>
                </form>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="resolveModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Mark as Resolved</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to mark this ticket as resolved?</p>
        <p class="text-muted small">This will close the conversation and move it to resolved tickets.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="?ticket_id=<?= $current_ticket['Message_ID'] ?? '' ?>&resolve=1" class="btn btn-success">Mark as Resolved</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Auto-scroll to bottom of chat
  function scrollToBottom() {
    const chatHistory = document.getElementById('chatHistory');
    if (chatHistory) {
      chatHistory.scrollTop = chatHistory.scrollHeight;
    }
  }
  
  // Refresh tickets list
  function refreshTickets() {
    window.location.reload();
  }
  
  // Handle Shift+Enter for new line, Enter to send
  document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.querySelector('textarea[name="reply_message"]');
    if (textarea) {
      textarea.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          if (this.value.trim() !== '') {
            this.form.submit();
          }
        }
      });
    }
    
    // Auto-scroll on page load
    scrollToBottom();
    
    // Auto-refresh every 30 seconds if on a ticket page
    <?php if ($current_ticket): ?>
      setInterval(refreshTickets, 30000);
    <?php endif; ?>
  });
</script>
</body>
</html>