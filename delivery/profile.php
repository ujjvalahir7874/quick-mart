<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['delivery_partner_id'])) {
    header("Location: login.php");
    exit;
}

$id = $_SESSION['delivery_partner_id'];
$partner = $pdo->query("SELECT * FROM delivery_persons WHERE id = $id")->fetch();

// Fetch Messages from Admin (Exclude withdrawal notifications)
$messages = $pdo->query("SELECT * FROM contact_messages WHERE delivery_person_id = $id AND subject != 'Withdrawal Success' ORDER BY created_at DESC")->fetchAll();

// Update Message Status to Read
$pdo->prepare("UPDATE contact_messages SET status = 'Read' WHERE delivery_person_id = ? AND status = 'Unread'")->execute([$id]);

// Handle Document Upload from Profile
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // If post_max_size is exceeded, both $_POST and $_FILES will be empty
    if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $max_size = ini_get('post_max_size');
         $error_msg = "File is too large. Max allowed size is " . $max_size;
         file_put_contents('upload_log.txt', date('Y-m-d H:i:s') . " - POST Size Error: " . $error_msg . "\n", FILE_APPEND);
         header("Location: profile.php?upload=error&msg=" . urlencode($error_msg));
         exit;
     }

    if (isset($_FILES['document'])) {
        $doc_type = $_POST['doc_type'] ?? '';
        
        // Validate doc_type
        $allowed_types = ['aadhaar', 'license', 'rc', 'photo'];
        if (!in_array($doc_type, $allowed_types)) {
            header("Location: profile.php?upload=error&msg=Invalid document type selection.");
            exit;
        }

        $upload_dir = realpath(__DIR__ . '/../uploads/documents') . DIRECTORY_SEPARATOR;
        if (!$upload_dir) {
            $target_dir = __DIR__ . '/../uploads/documents/';
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $upload_dir = realpath($target_dir) . DIRECTORY_SEPARATOR;
        }

        if ($_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $name = $_FILES['document']['name'];
            $tmp_name = $_FILES['document']['tmp_name'];
            $ext = strtolower(trim(pathinfo($name, PATHINFO_EXTENSION)));
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
            if (!in_array($ext, $allowed_exts)) {
                header("Location: profile.php?upload=error&msg=Invalid file type. Only JPG, PNG, PDF allowed.");
                exit;
            }

            $safe_mobile = preg_replace('/[^0-9]/', '', $partner['mobile_no']);
            $filename = $doc_type . '_' . $safe_mobile . '_' . time() . '.' . $ext;
            $target_file = $upload_dir . $filename;
            $db_path = 'uploads/documents/' . $filename;

            if (move_uploaded_file($tmp_name, $target_file)) {
            $col_name = 'doc_' . $doc_type;
            
            // Clear rejection flags for this specific document if it was rejected
            $stmt = $pdo->prepare("SELECT rejected_docs FROM delivery_persons WHERE id = ?");
            $stmt->execute([$id]);
            $current_rejected = $stmt->fetchColumn();
            $new_rejected = NULL;
            if ($current_rejected) {
                $rejected_array = explode(',', $current_rejected);
                $filtered_array = array_filter($rejected_array, function($val) use ($doc_type) {
                    return $val !== $doc_type;
                });
                if (!empty($filtered_array)) {
                    $new_rejected = implode(',', $filtered_array);
                }
            }
            
            $rejection_reason_col = 'rejection_reason_' . $doc_type;
            $stmt = $pdo->prepare("UPDATE delivery_persons SET $col_name = ?, rejected_docs = ?, $rejection_reason_col = NULL WHERE id = ?");
            if ($stmt->execute([$db_path, $new_rejected, $id])) {
                // Log success
                file_put_contents('upload_log.txt', date('Y-m-d H:i:s') . " - Success: $db_path for ID $id\n", FILE_APPEND);
                header("Location: profile.php?upload=success");
                exit;
            } else {
                $error_info = $stmt->errorInfo();
                $error_msg = 'Database update failed: ' . ($error_info[2] ?? 'Unknown error');
                file_put_contents('upload_log.txt', date('Y-m-d H:i:s') . " - DB Error: " . $error_msg . "\n", FILE_APPEND);
            }
        } else {
            $error_msg = 'Permission denied. Could not save file to ' . $target_file;
            file_put_contents('upload_log.txt', date('Y-m-d H:i:s') . " - Move Error: " . $error_msg . "\n", FILE_APPEND);
        }
    } else {
        $upload_errors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize ('.ini_get('upload_max_filesize').')',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in form',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE    => 'No file was selected',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload',
            ];
            $error_code = $_FILES['document']['error'];
            $error_msg = $upload_errors[$error_code] ?? 'Upload failed with code: ' . $error_code;
        }
    } else {
        $error_msg = 'No file data received. Please try again.';
    }
    
    header("Location: profile.php?upload=error&msg=" . urlencode($error_msg));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Profile - Delivery Partner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f4f5f7; font-family: 'Outfit', sans-serif; padding-bottom: 90px; color: #1f2937; }
        .profile-header { background: white; padding: 2rem 1.5rem; border-radius: 0 0 30px 30px; box-shadow: 0 4px 20px -5px rgba(0,0,0,0.05); text-align: center; }
        .avatar-large { width: 100px; height: 100px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #9ca3af; margin: 0 auto 15px; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        
        .section-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03); border: 1px solid #f0f0f0; }
        .info-item { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
        .info-item:last-child { border-bottom: none; }
        .info-icon { width: 40px; height: 40px; border-radius: 10px; background: #f8fafc; color: #64748b; display: flex; align-items: center; justify-content: center; margin-right: 15px; }
        .info-label { font-size: 0.8rem; color: #64748b; margin-bottom: 2px; }
        .info-value { font-weight: 600; color: #1e293b; }

        .msg-card { background: #f8fafc; border-radius: 15px; padding: 15px; margin-bottom: 10px; border-left: 4px solid #10b981; }
        .msg-card.unread { border-left-color: #ef4444; background: #fff5f5; }
        .msg-date { font-size: 0.7rem; color: #94a3b8; margin-bottom: 5px; }
        .msg-text { font-size: 0.9rem; color: #334155; line-height: 1.5; }
        .admin-reply { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #cbd5e1; color: #059669; font-weight: 500; font-size: 0.85rem; }

        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; background: white; padding: 16px 20px; border-radius: 25px 25px 0 0; display: flex; justify-content: space-between; align-items: center; z-index: 1000; box-shadow: 0 -5px 20px rgba(0,0,0,0.05); }
        .nav-item { display: flex; flex-direction: column; align-items: center; color: #9ca3af; text-decoration: none; font-size: 0.75rem; font-weight: 600; transition: all 0.2s; }
        .nav-item i { font-size: 1.4rem; margin-bottom: 4px; }
        .nav-item.active { color: #10b981; transform: translateY(-3px); }
        .nav-item.active i { color: #10b981; filter: drop-shadow(0 4px 6px rgba(16, 185, 129, 0.3)); }
    </style>
</head>
<body>

<div class="profile-header">
    <div class="avatar-large">
        <?php if($partner['doc_photo']): ?>
            <img src="../<?= $partner['doc_photo'] ?>" class="w-100 h-100 object-fit-cover rounded-circle">
        <?php else: ?>
            <i class="bi bi-person-fill"></i>
        <?php endif; ?>
    </div>
    <h4 class="fw-bold mb-1"><?= htmlspecialchars($partner['name']) ?></h4>
    <div class="d-flex justify-content-center gap-2 mb-2">
        <span class="badge rounded-pill <?= $partner['status'] == 'Offline' ? 'bg-secondary' : 'bg-success' ?> px-3 py-2">
            <?= $partner['status'] ?>
        </span>
        <?php if($partner['is_suspended']): ?>
            <span class="badge rounded-pill bg-danger px-3 py-2">Suspended</span>
        <?php endif; ?>
    </div>
    <?php if($partner['is_suspended']): ?>
        <div class="alert alert-danger mx-3 rounded-4 small py-2 mb-0">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($partner['suspension_reason'] ?: 'Account suspended for verification.') ?>
        </div>
    <?php endif; ?>
</div>

<div class="container py-4">
    <?php if(isset($_GET['upload']) && $_GET['upload'] == 'success'): ?>
        <div class="alert alert-success rounded-4 small fw-bold mb-3"><i class="bi bi-check-circle me-2"></i>Document uploaded successfully!</div>
    <?php elseif(isset($_GET['upload']) && $_GET['upload'] == 'error'): ?>
        <div class="alert alert-danger rounded-4 small fw-bold mb-3">
            <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($_GET['msg'] ?? 'Upload failed.') ?>
        </div>
    <?php endif; ?>
    
    <!-- Account Information -->
    <h6 class="text-uppercase fw-bold text-muted mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Account Information</h6>
    <div class="section-card">
        <div class="info-item">
            <div class="info-icon text-primary bg-primary bg-opacity-10"><i class="bi bi-phone"></i></div>
            <div>
                <div class="info-label">Mobile Number</div>
                <div class="info-value"><?= htmlspecialchars($partner['mobile_no']) ?></div>
            </div>
        </div>
        <div class="info-item">
            <div class="info-icon text-purple bg-opacity-10" style="background-color: rgba(111, 66, 193, 0.1); color: #6f42c1;"><i class="bi bi-envelope"></i></div>
            <div>
                <div class="info-label">Email Address</div>
                <div class="info-value"><?= htmlspecialchars($partner['email'] ?? 'Not Linked') ?></div>
            </div>
        </div>
        <div class="info-item">
            <div class="info-icon text-warning bg-warning bg-opacity-10"><i class="bi bi-bicycle"></i></div>
            <div>
                <div class="info-label">Bike Number</div>
                <div class="info-value"><?= htmlspecialchars($partner['bike_number']) ?></div>
            </div>
        </div>
        <div class="info-item">
            <div class="info-icon text-info bg-info bg-opacity-10"><i class="bi bi-patch-check"></i></div>
            <div>
                <div class="info-label">Verification Status</div>
                <div class="info-value">
                    <?= $partner['is_verified'] ? '<span class="text-success">Verified Partner</span>' : '<span class="text-danger">Pending Verification</span>' ?>
                </div>
            </div>
        </div>
        <div class="info-item">
            <div class="info-icon text-success bg-success bg-opacity-10"><i class="bi bi-calendar-event"></i></div>
            <div>
                <div class="info-label">Joined On</div>
                <div class="info-value"><?= date('d M Y', strtotime($partner['created_at'])) ?></div>
            </div>
        </div>
    </div>

    <!-- Mandatory Documents -->
    <h6 class="text-uppercase fw-bold text-muted mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Mandatory Documents</h6>
    <div class="section-card">
        <?php 
        $rejected_list = $partner['rejected_docs'] ? explode(',', $partner['rejected_docs']) : [];
        $docs = [
            ['id' => 'aadhaar', 'label' => 'Aadhaar Card', 'icon' => 'bi-card-text'],
            ['id' => 'license', 'label' => 'Driving License', 'icon' => 'bi-person-badge'],
            ['id' => 'rc', 'label' => 'RC Book', 'icon' => 'bi-file-earmark-medical'],
            ['id' => 'photo', 'label' => 'Profile Photo', 'icon' => 'bi-person-bounding-box']
        ];

        foreach($docs as $doc):
            $is_rejected = in_array($doc['id'], $rejected_list);
            $doc_path = $partner['doc_' . $doc['id']];
            $rejection_reason = $partner['rejection_reason_' . $doc['id']];
        ?>
        <div class="info-item flex-column align-items-stretch">
            <div class="d-flex align-items-center w-100">
                <div class="info-icon text-dark bg-dark bg-opacity-10"><i class="bi <?= $doc['icon'] ?>"></i></div>
                <div class="flex-grow-1">
                    <div class="info-label"><?= $doc['label'] ?></div>
                    <div class="info-value small">
                        <?php if($is_rejected): ?>
                            <span class="text-danger fw-bold"><i class="bi bi-x-circle-fill me-1"></i>Rejected</span>
                        <?php elseif($doc_path): ?>
                            <span class="text-success">Uploaded</span>
                        <?php else: ?>
                            Not Uploaded
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ms-3 text-end d-flex align-items-center gap-2">
                    <?php if($doc_path): ?>
                        <div onclick="window.open('../<?= $doc_path ?>')" style="cursor: pointer;">
                            <img src="../<?= $doc_path ?>" class="rounded border" style="width: <?= $doc['id'] == 'photo' ? '40px' : '60px' ?>; height: 40px; object-fit: cover;">
                        </div>
                        <?php if($is_rejected): ?>
                            <button class="btn btn-sm btn-danger rounded-pill px-3" onclick="triggerUpload('<?= $doc['id'] ?>')">
                                <i class="bi bi-arrow-repeat me-1"></i> Re-upload
                            </button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-light rounded-circle" onclick="triggerUpload('<?= $doc['id'] ?>')" title="Update Document">
                                <i class="bi bi-arrow-repeat text-primary"></i>
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="btn btn-sm btn-outline-success rounded-pill" onclick="triggerUpload('<?= $doc['id'] ?>')">
                            <i class="bi bi-upload me-1"></i> Upload
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php if($is_rejected && $rejection_reason): ?>
                <div class="mt-2 p-2 bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded-3 small">
                    <strong class="text-danger">Reason:</strong> <?= htmlspecialchars($rejection_reason) ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Hidden Upload Form -->
    <form id="docUploadForm" method="POST" enctype="multipart/form-data" style="display: none;">
        <input type="hidden" name="doc_type" id="docTypeInput">
        <input type="file" name="document" id="docFileInput" onchange="submitForm()" accept="image/*,application/pdf">
    </form>

    <script>
    function triggerUpload(type) {
        document.getElementById('docTypeInput').value = type;
        document.getElementById('docFileInput').value = ''; // Clear previous selection
        document.getElementById('docFileInput').click();
    }

    function submitForm() {
        const fileInput = document.getElementById('docFileInput');
        if (fileInput.files.length > 0) {
            // Basic validation before submit
            const file = fileInput.files[0];
            const validTypes = ['image/jpeg', 'image/png', 'application/pdf'];
            
            // Check file type
            if (!file.type.match('image.*') && file.type !== 'application/pdf') {
                alert('Invalid file type. Please upload JPG, PNG or PDF.');
                fileInput.value = '';
                return;
            }
            
            // Check file size (e.g. 5MB limit)
            const maxSize = 5 * 1024 * 1024; // 5MB
            if (file.size > maxSize) {
                alert('File is too large. Maximum size is 5MB.');
                fileInput.value = '';
                return;
            }

            document.getElementById('docUploadForm').submit();
        }
    }
    </script>

    <!-- Messages from Admin -->
    <h6 class="text-uppercase fw-bold text-muted mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Messages & Notifications</h6>
    <div class="section-card">
        <?php if(empty($messages)): ?>
            <div class="text-center py-4">
                <i class="bi bi-chat-dots fs-1 text-light"></i>
                <p class="text-muted mt-2">No messages from admin yet.</p>
            </div>
        <?php else: ?>
            <?php foreach($messages as $msg): ?>
                <div class="msg-card <?= $msg['status'] == 'Unread' ? 'unread' : '' ?>">
                    <div class="msg-date"><?= date('d M, h:i A', strtotime($msg['created_at'])) ?></div>
                    <div class="fw-bold mb-1" style="font-size: 0.9rem;"><?= htmlspecialchars($msg['subject'] ?: 'Notification') ?></div>
                    <div class="msg-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                    <?php if($msg['admin_reply']): ?>
                        <div class="admin-reply">
                            <i class="bi bi-reply-fill me-1"></i> Admin: <?= nl2br(htmlspecialchars($msg['admin_reply'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="d-grid gap-2 mt-4">
        <button type="button" class="btn btn-outline-danger rounded-pill py-3 fw-bold" onclick="if(confirm('Are you sure you want to delete your account? This action cannot be undone.')) window.location.href='delete_account.php';">
            <i class="bi bi-trash3 me-2"></i> Delete My Account
        </button>
    </div>
</div>

<!-- Bottom Navigation -->
<div class="bottom-nav">
    <a href="index.php" class="nav-item">
        <i class="bi bi-grid"></i>
        <span>Home</span>
    </a>
    <a href="wallet.php" class="nav-item">
        <i class="bi bi-wallet2"></i>
        <span>Wallet</span>
    </a>
    <a href="history.php" class="nav-item">
        <i class="bi bi-clock-history"></i>
        <span>History</span>
    </a>
    <a href="profile.php" class="nav-item active">
        <i class="bi bi-person-fill"></i>
        <span>Profile</span>
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>