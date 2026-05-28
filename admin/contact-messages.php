<?php 
require_once '../config/db.php'; 

if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

// Handle Status Updates (Mark as Read)
if (isset($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    $pdo->prepare("UPDATE contact_messages SET status = 'Read' WHERE id = ? AND status = 'Unread'")->execute([$id]);
    header("Location: contact-messages.php");
    exit;
}

// Handle Admin Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $id = (int)$_POST['message_id'];
    $reply = trim($_POST['reply_text']);
    
    if (!empty($reply)) {
        $pdo->prepare("UPDATE contact_messages SET admin_reply = ?, status = 'Replied', replied_at = NOW() WHERE id = ?")
            ->execute([$reply, $id]);
        
        // In a real application, you would use mail() or a library like PHPMailer here
        // For now, we simulate the email sending
        $msg = "Reply saved and marked as sent to customer.";
    }
}

// Delete Message
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$_GET['delete']]);
    header("Location: contact-messages.php");
    exit;
}

$messages = $pdo->query("SELECT * FROM contact_messages WHERE parent_id IS NULL ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - Quick mart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: #1e293b;
            --primary-color: #10b981;
            --bg-light: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --card-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-main);
        }
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            overflow-y: auto;
            position: fixed;
            left: 0;
            top: 0;
            background-color: var(--sidebar-bg);
            color: #fff;
            z-index: 1000;
            transition: var(--transition);
        }
        .sidebar-brand {
            padding: 2rem 1.5rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        .nav-link-admin {
            padding: 0.85rem 1.5rem;
            color: #94a3b8;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }
        .nav-link-admin:hover, .nav-link-admin.active {
            background-color: #334155;
            color: #fff;
            border-left-color: var(--primary-color);
        }
        .nav-link-admin i { margin-right: 0.85rem; font-size: 1.25rem; }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2.5rem;
            transition: var(--transition);
        }
        .card { border: none; border-radius: 1rem; box-shadow: var(--card-shadow); }
        .table thead th {
            background-color: #f1f5f9;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            font-weight: 700;
            color: var(--text-muted);
            padding: 1rem;
        }
        .modal-content { border: none; border-radius: 1.25rem; }
        .conversation-bubble { border-radius: 1rem; padding: 1rem; margin-bottom: 1rem; }
        .bubble-customer { background-color: #f1f5f9; border-bottom-left-radius: 0; }
        .bubble-admin { background-color: #dcfce7; border-bottom-right-radius: 0; margin-left: 2rem; }
        @media (max-width: 992px) {
            .sidebar { left: -var(--sidebar-width); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 5px; }
            .sidebar.active { left: 0; }
            .main-content { margin-left: 0; padding: 1.5rem; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <a href="../index.php" class="sidebar-brand">
            <div class="bg-success text-white rounded-3 p-1 me-2 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                <i class="bi bi-basket2-fill fs-5"></i>
            </div>
            Quick mart
        </a>
        <div class="mt-3">
            <p class="px-4 text-muted small text-uppercase fw-bold mb-2 opacity-50">Menu</p>
            <a href="dashboard.php" class="nav-link-admin"><i class="bi bi-speedometer2"></i>Dashboard</a>
            <a href="products.php" class="nav-link-admin"><i class="bi bi-box-seam"></i>Products</a>
            <a href="categories.php" class="nav-link-admin"><i class="bi bi-tags"></i>Categories</a>
            <a href="orders.php" class="nav-link-admin"><i class="bi bi-cart-check"></i>Orders</a>
            <a href="users.php" class="nav-link-admin"><i class="bi bi-people"></i>Customers</a>
            <a href="delivery-persons.php" class="nav-link-admin"><i class="bi bi-truck"></i>Delivery Staff</a>
            <a href="coupons.php" class="nav-link-admin"><i class="bi bi-ticket-perforated"></i>Coupons</a>
            <a href="offers.php" class="nav-link-admin"><i class="bi bi-megaphone"></i>Offers</a>
            
            <p class="px-4 text-muted small text-uppercase fw-bold mt-4 mb-2 opacity-50">Support</p>
            <a href="contact-messages.php" class="nav-link-admin active"><i class="bi bi-chat-left-dots"></i>Messages</a>
            <a href="activity_logs.php" class="nav-link-admin"><i class="bi bi-journal-text"></i>Activity Logs</a>
            
            <hr class="mx-3 my-4 opacity-10">
            <a href="../logout.php?from=admin" class="nav-link-admin text-danger"><i class="bi bi-box-arrow-left"></i>Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <button class="btn btn-white shadow-sm d-lg-none me-3" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h2 class="fw-bold mb-0">Contact Messages</h2>
                <p class="text-muted small mb-0">Customer inquiries and support conversations.</p>
            </div>
            <div class="text-muted small fw-medium">Total Conversations: <?php echo count($messages); ?></div>
        </div>

        <?php if (isset($msg)): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>Customer</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($messages)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-chat-left-dots display-4 opacity-25 d-block mb-3"></i>
                                    No messages found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($messages as $m): 
                                // Check for new replies in this thread
                                $newRepliesCount = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE parent_id = ? AND status = 'Unread'");
                                $newRepliesCount->execute([$m['id']]);
                                $hasNew = $newRepliesCount->fetchColumn() > 0;
                            ?>
                                <tr class="<?php echo $hasNew ? 'bg-warning bg-opacity-10' : ''; ?>">
                                    <td class="ps-4">
                                        <div class="small fw-bold text-dark"><?php echo date('M d, Y', strtotime($m['created_at'])); ?></div>
                                        <div class="extra-small text-muted"><?php echo date('h:i A', strtotime($m['created_at'])); ?></div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($m['name']); ?>&background=random" class="rounded-circle me-2" width="32">
                                            <div>
                                                <div class="fw-bold">
                                                    <?php echo htmlspecialchars($m['name']); ?>
                                                    <?php if ($hasNew): ?>
                                                        <span class="badge bg-danger rounded-pill extra-small ms-1">New</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="extra-small text-muted"><?php echo htmlspecialchars($m['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-truncate small" style="max-width: 250px;">
                                            <?php echo htmlspecialchars($m['subject'] ?: '(No Subject)'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($m['status'] === 'Unread' || $hasNew): ?>
                                            <span class="badge bg-danger-subtle text-danger px-3">Unread</span>
                                        <?php elseif ($m['status'] === 'Read'): ?>
                                            <span class="badge bg-primary-subtle text-primary px-3">Read</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success px-3">Replied</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button class="btn btn-sm btn-light rounded-circle shadow-sm" data-bs-toggle="modal" data-bs-target="#viewMsg<?php echo $m['id']; ?>" title="View Conversation">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="?delete=<?php echo $m['id']; ?>" class="btn btn-sm btn-light text-danger rounded-circle shadow-sm" onclick="return confirm('Delete this entire conversation?')" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                                <!-- View Modal -->
                                <div class="modal fade" id="viewMsg<?php echo $m['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header border-0 pb-0">
                                                <h5 class="modal-title fw-bold">Conversation with <?php echo htmlspecialchars($m['name']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="conversation-thread mb-4 pe-2" style="max-height: 400px; overflow-y: auto;">
                                                    <!-- Original Message -->
                                                    <div class="conversation-bubble bubble-customer border-0 shadow-sm">
                                                        <div class="d-flex justify-content-between mb-2 small">
                                                            <strong class="text-dark"><?php echo htmlspecialchars($m['name']); ?></strong>
                                                            <span class="text-muted"><?php echo date('M d, h:i A', strtotime($m['created_at'])); ?></span>
                                                        </div>
                                                        <p class="mb-0 small text-dark"><?php echo nl2br(htmlspecialchars($m['message'])); ?></p>
                                                    </div>

                                                    <!-- Admin Reply -->
                                                    <?php if ($m['admin_reply']): ?>
                                                        <div class="conversation-bubble bubble-admin border-0 shadow-sm">
                                                            <div class="d-flex justify-content-between mb-2 small">
                                                                <strong class="text-success">Admin</strong>
                                                                <span class="text-muted"><?php echo date('M d, h:i A', strtotime($m['replied_at'])); ?></span>
                                                            </div>
                                                            <p class="mb-0 small text-dark"><?php echo nl2br(htmlspecialchars($m['admin_reply'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Replies in Thread -->
                                                    <?php 
                                                    $thread = $pdo->prepare("SELECT * FROM contact_messages WHERE parent_id = ? ORDER BY created_at ASC");
                                                    $thread->execute([$m['id']]);
                                                    foreach ($thread->fetchAll() as $t): 
                                                    ?>
                                                        <div class="conversation-bubble bubble-customer border-0 shadow-sm">
                                                            <div class="d-flex justify-content-between mb-2 small">
                                                                <strong class="text-dark"><?php echo htmlspecialchars($t['name']); ?></strong>
                                                                <span class="text-muted"><?php echo date('M d, h:i A', strtotime($t['created_at'])); ?></span>
                                                            </div>
                                                            <p class="mb-0 small text-dark"><?php echo nl2br(htmlspecialchars($t['message'])); ?></p>
                                                        </div>

                                                        <?php if ($t['admin_reply']): ?>
                                                            <div class="conversation-bubble bubble-admin border-0 shadow-sm">
                                                                <div class="d-flex justify-content-between mb-2 small">
                                                                    <strong class="text-success">Admin</strong>
                                                                    <span class="text-muted"><?php echo date('M d, h:i A', strtotime($t['replied_at'])); ?></span>
                                                                </div>
                                                                <p class="mb-0 small text-dark"><?php echo nl2br(htmlspecialchars($t['admin_reply'])); ?></p>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>

                                                <form action="contact-messages.php" method="POST" class="bg-light p-3 rounded-4 border">
                                                    <?php 
                                                    // Find the last un-replied message in the thread
                                                    $lastMsg = $pdo->prepare("SELECT id FROM contact_messages WHERE (id = ? OR parent_id = ?) AND admin_reply IS NULL ORDER BY created_at DESC LIMIT 1");
                                                    $lastMsg->execute([$m['id'], $m['id']]);
                                                    $targetId = $lastMsg->fetchColumn() ?: $m['id'];
                                                    ?>
                                                    <input type="hidden" name="message_id" value="<?php echo $targetId; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold">Quick Reply</label>
                                                        <textarea name="reply_text" class="form-control border-0 shadow-sm rounded-3" rows="3" placeholder="Type your reply here..." required></textarea>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <?php if ($m['status'] === 'Unread' || $hasNew): ?>
                                                            <a href="?mark_read=<?php echo $m['id']; ?>" class="btn btn-sm btn-link text-muted text-decoration-none extra-small">Mark All as Read</a>
                                                        <?php else: ?>
                                                            <span></span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="send_reply" class="btn btn-primary rounded-pill px-4">
                                                            <i class="bi bi-send me-2"></i>Send Reply
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>
