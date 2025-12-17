<?php
session_start();
include __DIR__ . '/../dbConfig.php';

if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php?next=Afterlogin/alcontact.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$username = 'Customer';
$email = '';
$address = '';

// 1. Fetch user details
$stmt = $conn->prepare("SELECT Username, Email, Address FROM user WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $username = $row['Username'];
    $email = $row['Email'];
    $address = $row['Address'] ?? '';
}
$stmt->close();

$success = '';
$error = '';

// 2. Handle form submission (Insert into 'inquiry' table)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if (empty($subject) || empty($message)) {
        $error = "All fields are required.";
    } else {
        // Insert new ticket
        $stmt = $conn->prepare("INSERT INTO inquiry (User_ID_FK, Inquiry_Date, Subject, Message, Status) VALUES (?, NOW(), ?, ?, 'Pending')");
        $stmt->bind_param("iss", $user_id, $subject, $message);
        
        if ($stmt->execute()) {
            $success = "Your message has been sent successfully! Check the history below for updates.";
        } else {
            $error = "Failed to send message. Please try again.";
        }
        $stmt->close();
    }
}

// 3. Fetch Ticket History (To show Admin Replies)
$tickets = [];
$stmt = $conn->prepare("SELECT * FROM inquiry WHERE User_ID_FK = ? ORDER BY Inquiry_Date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
}
$stmt->close();
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer Support | Velvet Vogue</title>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@636&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../layout.css">
  <style>
    .navbar-brand { font-family: 'Dancing Script', cursive; color: #8a2be2 !important; }
    .contact-container { max-width: 800px; margin: 3rem auto; }
    .card { border-radius: 16px; overflow: hidden; }
    .card-header { background: linear-gradient(135deg, #8a2be2 0%, #614d73 100%); color: white; }
    .btn-primary { background: linear-gradient(135deg, #8a2be2 0%, #614d73 100%); border: none; }
    .btn-primary:hover { background: linear-gradient(135deg, #7a1bd2 0%, #513d63 100%); }
    .contact-icon { font-size: 2.5rem; color: #8a2be2; margin-bottom: 1rem; }
    .info-box { padding: 2rem; background: #f8f9fa; border-radius: 12px; }
    .form-control:focus { border-color: #8a2be2; box-shadow: 0 0 0 0.2rem rgba(138, 43, 226, 0.25); }
    .required:after { content: " *"; color: #dc3545; }
    .hero-section {
        background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80');
        background-size: cover;
        background-position: center;
        color: white;
        padding: 4rem 0;
        margin-bottom: 3rem;
        text-align: center;
    }
    
    /* New Styles for Reply Section (Matches your theme) */
    .ticket-history-card { margin-top: 2rem; }
    .status-badge { font-size: 0.8rem; padding: 5px 10px; border-radius: 20px; color: white; }
    .status-Pending { background-color: #ffc107; color: #000; }
    .status-Replied { background-color: #28a745; }
    .status-Resolved { background-color: #6c757d; }
    .admin-response { background-color: #f1f8ff; border-left: 4px solid #8a2be2; padding: 15px; margin-top: 15px; border-radius: 4px; }
  </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg bg-white shadow-sm border-bottom sticky-top">
  <div class="container">
    <a class="navbar-brand fs-3" href="alhome.php">Velvet Vogue</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav m-auto">
        <li class="nav-item"><a class="nav-link" href="alhome.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="alshop.php">Shop</a></li>
        <li class="nav-item"><a class="nav-link active" href="alcontact.php">Contact</a></li>
      </ul>
      <div class="d-flex align-items-center gap-3">
        <div class="dropdown">
          <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fa fa-user"></i> <?= htmlspecialchars($username) ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
            <li><a class="dropdown-item" href="my_orders.php">Orders</a></li>
            <li><a class="dropdown-item" href="wishlist.php">Wishlist</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="?logout=1">Logout</a></li>
          </ul>
        </div>
        <a href="alcart.php" class="btn btn-outline-primary position-relative">
          <i class="fa fa-shopping-cart"></i> Cart
        </a>
      </div>
    </div>
  </div>
</nav>

<section class="hero-section">
  <div class="container">
    <h1 class="display-4 fw-bold mb-3">Customer Support</h1>
    <p class="lead mb-4">We are here to help. Please fill out the form below, send us and get back to you shortly.</p>
  </div>
</section>

<div class="container contact-container">
  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  
  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="card shadow-lg border-0">
    <div class="card-header text-center py-4">
      <h2 class="mb-0"><i class="fas fa-comments me-2"></i>Contact Support Team</h2>
    </div>
    <div class="card-body p-5">
      <form method="POST" action="">
        <div class="row mb-4">
          <div class="col-md-6 mb-3">
            <label class="form-label required">Full Name</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-user"></i></span>
              <input type="text" class="form-control" name="full_name" 
                     value="<?= htmlspecialchars($username) ?>" 
                     placeholder="Enter your full name" disabled> </div>
          </div>
          
          <div class="col-md-6 mb-3">
            <label class="form-label required">Email Address</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-envelope"></i></span>
              <input type="email" class="form-control" name="email" 
                     value="<?= htmlspecialchars($email) ?>" 
                     placeholder="Enter your email address" disabled> </div>
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label required">Subject</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-tag"></i></span>
            <select class="form-select" name="subject" required>
              <option value="" disabled selected>Select subject of your inquiry</option>
              <option value="Order Issues">Order Issues</option>
              <option value="Shipping & Delivery">Shipping & Delivery</option>
              <option value="Returns & Refunds">Returns & Refunds</option>
              <option value="Product Inquiry">Product Inquiry</option>
              <option value="Account Issues">Account Issues</option>
              <option value="Payment Problems">Payment Problems</option>
              <option value="Other">Other</option>
            </select>
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label required">Your Message</label>
          <div class="input-group">
            <span class="input-group-text align-items-start pt-3"><i class="fas fa-comment-dots"></i></span>
            <textarea class="form-control" name="message" rows="6" 
                      placeholder="Type your message here..." required><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
          </div>
        </div>

        <div class="d-grid">
          <button type="submit" name="submit" class="btn btn-primary btn-lg py-3">
            <i class="fas fa-paper-plane me-2"></i>Send Message
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php if (!empty($tickets)): ?>
  <div class="card shadow-lg border-0 ticket-history-card">
    <div class="card-header text-center py-4">
      <h3 class="mb-0"><i class="fas fa-history me-2"></i>My Support History</h3>
    </div>
    <div class="card-body p-4">
        <?php foreach ($tickets as $t): ?>
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0 text-primary"><?= htmlspecialchars($t['Subject']) ?></h5>
                    <span class="status-badge status-<?= $t['Status'] ?>"><?= $t['Status'] ?></span>
                </div>
                <small class="text-muted"><i class="fas fa-clock me-1"></i> <?= date('M d, Y', strtotime($t['Inquiry_Date'])) ?></small>
                <p class="mt-2 mb-0"><?= nl2br(htmlspecialchars($t['Message'])) ?></p>

                <?php if (!empty($t['Response'])): ?>
                    <div class="admin-response">
                        <div class="d-flex align-items-center mb-1">
                            <i class="fas fa-headset me-2 text-primary"></i>
                            <strong>Support Team Reply</strong>
                            <small class="ms-auto text-muted"><?= date('M d, Y', strtotime($t['Response_Date'])) ?></small>
                        </div>
                        <p class="mb-0 text-dark"><?= nl2br(htmlspecialchars($t['Response'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="row mt-5">
    <div class="col-md-4 mb-4">
      <div class="info-box text-center h-100">
        <div class="contact-icon">
          <i class="fas fa-map-marker-alt"></i>
        </div>
        <h4>Address</h4>
        <p class="text-muted">
          <?php if (!empty($address)): ?>
            <?= htmlspecialchars($address) ?>
          <?php else: ?>
            Your address is not set. <a href="profile.php">Update profile</a>
          <?php endif; ?>
        </p>
      </div>
    </div>
    
    <div class="col-md-4 mb-4">
      <div class="info-box text-center h-100">
        <div class="contact-icon">
          <i class="fas fa-phone"></i>
        </div>
        <h4>Phone Number</h4>
        <p class="text-muted">+1 (555) 123-4567</p>
        <p class="text-muted small">Mon-Fri: 9:00 AM - 6:00 PM</p>
      </div>
    </div>
    
    <div class="col-md-4 mb-4">
      <div class="info-box text-center h-100">
        <div class="contact-icon">
          <i class="fas fa-envelope"></i>
        </div>
        <h4>Email Address</h4>
        <p class="text-muted">support@velvetvogue.com</p>
        <p class="text-muted small">Response within 24 hours</p>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mt-5">
    <div class="card-header bg-white">
      <h4 class="mb-0"><i class="fas fa-question-circle me-2"></i>Frequently Asked Questions</h4>
    </div>
    <div class="card-body">
      <div class="accordion" id="faqAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
              How long does shipping take?
            </button>
          </h2>
          <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
              Standard shipping takes 5-7 business days. Express shipping takes 2-3 business days.
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
              What is your return policy?
            </button>
          </h2>
          <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
              We offer a 30-day return policy for unused items with original tags attached.
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
              How can I track my order?
            </button>
          </h2>
          <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
              You can track your order in your account under "My Orders" section.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<footer class="bg-light text-dark pt-5 pb-4 mt-5 border-top">
  <div class="container">
    <div class="row">
      <div class="col-md-4 mb-4">
        <h5 class="mb-3">About Velvet Vogue</h5>
        <p class="text-muted small">Premium fashion with timeless elegance and unmatched quality.</p>
      </div>
      <div class="col-md-4 mb-4">
        <h5 class="mb-3">Quick Links</h5>
        <ul class="list-unstyled">
          <li><a href="alhome.php" class="text-muted text-decoration-none">Home</a></li>
          <li><a href="alshop.php" class="text-muted text-decoration-none">Shop</a></li>
          <li><a href="#promotions-section" class="text-light text-decoration-none footer-link">Promotions</a></li>
          <li><a href="alcontact.php" class="text-muted text-decoration-none">Contact</a></li>
        </ul>
      </div>
      <div class="col-md-4 mb-4">
        <h5 class="mb-3">Need Help?</h5>
        <p class="text-muted small">
          <i class="fas fa-phone me-2"></i> +1 (555) 123-4567<br>
          <i class="fas fa-envelope me-2"></i> help@velvetvogue.com
        </p>
      </div>
    </div>
    <p class="text-center small mb-0 mt-4">© 2025 Velvet Vogue. All Rights Reserved.</p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>