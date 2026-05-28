<?php
require_once 'config/db.php';

if (!isLoggedIn() && !isAdmin()) {
    header("Location: login.php");
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    die("Order ID is required.");
}

// Fetch order details with delivery person
$stmt = $pdo->prepare("SELECT o.*, u.full_name, u.email, c.code as coupon_code, dp.name as delivery_name, dp.mobile_no as delivery_mobile, dp.bike_number 
                        FROM orders o 
                        LEFT JOIN users u ON o.user_id = u.id 
                        LEFT JOIN coupons c ON o.coupon_id = c.id 
                        LEFT JOIN delivery_persons dp ON o.delivery_person_id = dp.id
                        WHERE o.id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Order not found.");
}

// Prefer customer context for customer-owned receipts even if an admin session also exists.
$viewer_is_customer = isLoggedIn() && isset($_SESSION['user_id']) && (int) $order['user_id'] === (int) $_SESSION['user_id'];
$back_to_orders_url = $viewer_is_customer ? 'my-orders.php' : 'admin/orders.php';

// Security check: Customers can only see their own orders
if (!$viewer_is_customer && !isAdmin()) {
    die("Access denied.");
}

// Fetch order items with their tax percentage and variant details
$stmt = $pdo->prepare("SELECT oi.*, p.name, p.tax_percentage, pv.size_name 
                        FROM order_items oi 
                        JOIN products p ON oi.product_id = p.id 
                        LEFT JOIN product_variants pv ON oi.variant_id = pv.id
                        WHERE oi.order_id = ?");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

$subtotal = 0;
$total_tax = 0;
$total_tax_percentage = 0;
$item_count = count($items);
foreach ($items as $item) {
    // Price at time is inclusive
    $item_total_inclusive = $item['price_at_time'] * $item['quantity'];
    
    $tax_percentage = $item['tax_percentage'] ?? 0;
    $total_tax_percentage += $tax_percentage;
    
    // Extract tax
    $item_tax = $item_total_inclusive - ($item_total_inclusive / (1 + ($tax_percentage / 100)));
    $total_tax += $item_tax;
    
    $item_base = $item_total_inclusive - $item_tax;
    $subtotal += $item_base;
}
$avg_tax_percentage = $item_count > 0 ? $total_tax_percentage / $item_count : 0;
$total_inclusive_subtotal = $subtotal + $total_tax;
$delivery_charge = ($total_inclusive_subtotal < 250 && $total_inclusive_subtotal > 0) ? 40 : 0;
$discount = $order['discount_amount'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo $order_id; ?> - Quick mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { 
            background-color: #f0f2f5; 
            font-family: 'Inter', sans-serif;
            color: #2d3436;
        }
        .receipt-container { 
            max-width: 850px; 
            margin: 40px auto; 
            background: white; 
            padding: 50px; 
            border-radius: 24px; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.05); 
            position: relative;
            overflow: hidden;
        }
        .receipt-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 8px;
            background: linear-gradient(90deg, #198754, #2ecc71);
        }
        .receipt-header { 
            border-bottom: 1px solid #edf2f7; 
            padding-bottom: 30px; 
            margin-bottom: 40px; 
        }
        .receipt-brand-panel {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            max-width: 58%;
        }
        .receipt-brand-mark {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            background: linear-gradient(135deg, #157347, #26b36a);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 14px 26px rgba(25, 135, 84, 0.18);
            flex-shrink: 0;
        }
        .receipt-kicker {
            margin: 0 0 6px;
            color: #198754;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 1.6px;
            text-transform: uppercase;
        }
        .receipt-logo { 
            color: #198754; 
            font-weight: 800; 
            font-size: 2rem; 
            text-decoration: none; 
            letter-spacing: -1px;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1;
            margin-bottom: 10px;
        }
        .receipt-logo:hover {
            color: #157347;
        }
        .receipt-company-meta {
            margin: 0;
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.7;
        }
        .receipt-company-meta i {
            color: #198754;
            margin-right: 6px;
        }
        .receipt-meta-panel {
            min-width: 280px;
            padding: 18px 20px;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            background: linear-gradient(180deg, #fcfdfd, #f8fafc);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }
        .receipt-doc-label {
            margin: 0 0 8px;
            color: #198754;
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }
        .receipt-doc-number {
            margin: 0 0 12px;
            color: #0f172a;
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -1px;
        }
        .receipt-meta-grid {
            display: grid;
            grid-template-columns: auto auto;
            gap: 6px 18px;
            justify-content: end;
            font-size: 0.82rem;
        }
        .receipt-meta-grid span {
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-size: 0.68rem;
            font-weight: 700;
        }
        .receipt-meta-grid strong {
            color: #1e293b;
            font-weight: 700;
        }
        .table thead th { 
            border: none;
            background-color: #f8fafc; 
            text-transform: uppercase; 
            font-size: 0.75rem; 
            font-weight: 700;
            letter-spacing: 1px; 
            padding: 15px;
            color: #64748b;
        }
        .table tbody td {
            padding: 20px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }
        .totals-row { 
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            margin-top: 20px;
        }
        .status-badge {
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .bg-light-success { background: #dcfce7; color: #166534; }
        .bg-light-warning { background: #fef9c3; color: #854d0e; }
        .bg-light-primary { background: #dbeafe; color: #1e40af; }
        .bg-light-info { background: #e0f2fe; color: #075985; }
        
        @media print {
            html, body {
                background-color: white !important;
                margin: 0 !important;
                padding: 0 !important;
                font-size: 12px !important;
                line-height: 1.3 !important;
            }
            .no-print, .btn, .navbar, .footer, .breadcrumb { display: none !important; visibility: hidden !important; }
            .receipt-container {
                margin: 0 !important;
                padding: 16px !important;
                box-shadow: none !important;
                max-width: 100% !important;
                border: none !important;
                border-radius: 0 !important;
            }
            .receipt-container::before { display: none; }
            .container { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .receipt-header {
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: flex-start !important;
                gap: 16px !important;
                padding-bottom: 14px !important;
                margin-bottom: 18px !important;
                text-align: left !important;
            }
            .receipt-brand-panel {
                gap: 12px !important;
                max-width: 56% !important;
                flex-direction: row !important;
                align-items: flex-start !important;
                text-align: left !important;
            }
            .receipt-brand-mark {
                width: 42px !important;
                height: 42px !important;
                border-radius: 14px !important;
                box-shadow: none !important;
            }
            .receipt-kicker {
                font-size: 0.6rem !important;
                margin-bottom: 4px !important;
            }
            .receipt-logo {
                font-size: 1.4rem !important;
                gap: 8px !important;
                margin-bottom: 6px !important;
            }
            .receipt-company-meta {
                font-size: 0.7rem !important;
                line-height: 1.45 !important;
            }
            .receipt-meta-panel {
                min-width: 230px !important;
                padding: 12px 14px !important;
                border-radius: 16px !important;
                text-align: right !important;
            }
            .receipt-doc-label {
                font-size: 0.62rem !important;
                margin-bottom: 5px !important;
            }
            .receipt-doc-number {
                font-size: 1.35rem !important;
                margin-bottom: 8px !important;
            }
            .receipt-meta-grid {
                gap: 4px 12px !important;
                font-size: 0.7rem !important;
            }
            .receipt-meta-grid span {
                font-size: 0.56rem !important;
            }
            .receipt-header .badge,
            .status-badge {
                padding: 4px 12px !important;
                font-size: 0.72rem !important;
            }
            .row.mb-5,
            .table-responsive.mb-5 {
                margin-bottom: 1rem !important;
            }
            .row.g-4 {
                --bs-gutter-x: 1rem !important;
                --bs-gutter-y: 0.75rem !important;
            }
            .p-3,
            .p-4 {
                padding: 0.9rem !important;
            }
            .mt-5 {
                margin-top: 1rem !important;
            }
            .pt-4 {
                padding-top: 1rem !important;
            }
            .mb-4 {
                margin-bottom: 0.75rem !important;
            }
            .table {
                margin-bottom: 0 !important;
            }
            .table thead th {
                padding: 10px !important;
                font-size: 0.68rem !important;
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .table tbody td {
                padding: 10px !important;
            }
            .table tbody tr,
            .receipt-header,
            .row,
            .table-responsive,
            .totals-row,
            .border-top {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .col-lg-5 .fs-3 {
                font-size: 1.7rem !important;
            }
            .extra-small {
                font-size: 0.64rem !important;
            }
            .small {
                font-size: 0.74rem !important;
            }
            @page { size: A4 portrait; margin: 8mm; }
        }
        @media screen and (max-width: 767.98px) {
            .receipt-container { padding: 30px 20px; margin: 20px auto; }
            .receipt-header { flex-direction: column; text-align: center; gap: 20px; }
            .receipt-brand-panel {
                max-width: 100%;
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .receipt-meta-panel {
                width: 100%;
                min-width: 0;
            }
            .receipt-meta-grid {
                justify-content: center;
            }
            .receipt-header .text-end { text-align: center !important; }
        }
    </style>
</head>
<body>

<div class="container mb-5">
    <div class="no-print mt-4 d-flex justify-content-between align-items-center mb-4">
        <a href="<?php echo $back_to_orders_url; ?>" class="btn btn-white shadow-sm rounded-pill px-4 fw-bold border">
            <i class="bi bi-arrow-left me-2"></i>Back to Orders
        </a>
        <div class="d-flex gap-2">
            <button onclick="window.print();" class="btn btn-success shadow-sm rounded-pill px-4 fw-bold">
                <i class="bi bi-printer me-2"></i>Print Receipt
            </button>
        </div>
    </div>

    <div class="receipt-container">
        <div class="receipt-header d-flex justify-content-between align-items-center">
            <div class="receipt-brand-panel">
                <div class="receipt-brand-mark">
                    <i class="bi bi-basket2-fill fs-3"></i>
                </div>
                <div>
                    <p class="receipt-kicker">Retail Invoice</p>
                    <a href="index.php" class="receipt-logo">Quick mart</a>
                    <p class="receipt-company-meta">
                        <i class="bi bi-geo-alt-fill"></i>179 Vijaya Nagar 1, Udhna, Surat, Gujarat 394210<br>
                        <i class="bi bi-telephone-fill"></i>+91 9173791005
                    </p>
                </div>
            </div>
            <div class="receipt-meta-panel text-end">
                <p class="receipt-doc-label">Official Tax Invoice</p>
                <h3 class="receipt-doc-number">INV-<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h3>
                <div class="receipt-meta-grid">
                    <span>Order ID</span>
                    <strong>#<?php echo $order['id']; ?></strong>
                    <span>Issue Date</span>
                    <strong><?php echo date('d M Y', strtotime($order['order_date'])); ?></strong>
                    <span>Placed At</span>
                    <strong><?php echo date('h:i A', strtotime($order['order_date'])); ?></strong>
                    <span>Status</span>
                    <strong><?php echo ucfirst(strtolower($order['status'])); ?></strong>
                </div>
            </div>
        </div>

        <div class="row mb-5 g-4">
            <div class="col-sm-6">
                <h6 class="text-muted text-uppercase extra-small fw-800 mb-3" style="letter-spacing: 1px;">Billed To</h6>
                <div class="p-3 rounded-4 border bg-light-subtle">
                    <p class="mb-1 fw-bold fs-5"><?php echo htmlspecialchars($order['full_name']); ?></p>
                    <p class="mb-1 text-muted small"><i class="bi bi-envelope me-2"></i><?php echo htmlspecialchars($order['email']); ?></p>
                    <p class="mb-0 text-muted small"><i class="bi bi-telephone me-2"></i><?php echo htmlspecialchars($order['contact_number'] ?? 'N/A'); ?></p>
                </div>
            </div>
            <div class="col-sm-6">
                <h6 class="text-muted text-uppercase extra-small fw-800 mb-3" style="letter-spacing: 1px;">Shipping Address</h6>
                <div class="p-3 rounded-4 border bg-light-subtle h-100">
                    <p class="text-muted mb-0 small lh-base">
                        <i class="bi bi-geo-alt-fill text-success me-2"></i><?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? 'N/A')); ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="row mb-5 g-4">
            <div class="col-sm-6">
                <h6 class="text-muted text-uppercase extra-small fw-800 mb-3" style="letter-spacing: 1px;">Payment Information</h6>
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="bg-success-subtle text-success rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-credit-card-2-back fs-4"></i>
                    </div>
                    <div>
                        <p class="fw-bold mb-0 text-success"><?php echo htmlspecialchars($order['payment_method'] ?? 'Cash on Delivery'); ?></p>
                        <p class="text-muted small mb-0">Transaction ID: <span class="fw-medium">#TXN-<?php echo substr(md5($order['id']), 0, 10); ?></span></p>
                    </div>
                </div>

                <?php if ($order['delivery_name']): ?>
                <h6 class="text-muted text-uppercase extra-small fw-800 mb-3" style="letter-spacing: 1px;">Delivery Agent</h6>
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary-subtle text-primary rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-person-badge fs-4"></i>
                    </div>
                    <div>
                        <p class="fw-bold mb-0 text-primary"><?php echo htmlspecialchars($order['delivery_name']); ?></p>
                        <p class="text-muted extra-small mb-1 fw-bold"><?php echo htmlspecialchars($order['bike_number']); ?></p>
                        <p class="text-muted small mb-0"><i class="bi bi-telephone-fill me-1"></i> <?php echo htmlspecialchars($order['delivery_mobile']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-sm-6 text-sm-end">
                <h6 class="text-muted text-uppercase extra-small fw-800 mb-3" style="letter-spacing: 1px;">Order Status</h6>
                <?php 
                    $statusClass = 'bg-light-primary';
                    if($order['status'] === 'Delivered') $statusClass = 'bg-light-success';
                    elseif($order['status'] === 'Cancelled') $statusClass = 'bg-light-danger';
                    elseif($order['status'] === 'Processing') $statusClass = 'bg-light-info';
                ?>
                <span class="status-badge <?php echo $statusClass; ?>">
                    <i class="bi bi-circle-fill me-2" style="font-size: 0.5rem;"></i>
                    <?php echo ucfirst(strtolower($order['status'])); ?>
                </span>
                
                <?php if ($order['status'] === 'Delivered' && !empty($order['delivery_date'])): ?>
                    <p class="text-success small fw-bold mt-2 mb-0"><i class="bi bi-check2-circle me-1"></i> Delivered on <?php echo date('M d, Y', strtotime($order['delivery_date'])); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive mb-5">
            <table class="table border-0">
                <thead>
                    <tr>
                        <th style="border-top-left-radius: 12px; border-bottom-left-radius: 12px;">Item Description</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end" style="border-top-right-radius: 12px; border-bottom-right-radius: 12px;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">
                                <?php echo htmlspecialchars($item['name']); ?>
                                <?php if (!empty($item['size_name'])): ?>
                                    <span class="badge bg-light text-success border ms-2 small fw-600"><?php echo htmlspecialchars($item['size_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted extra-small">Category: Fresh Produce</div>
                        </td>
                        <td class="text-center fw-medium"><?php echo $item['quantity']; ?></td>
                        <td class="text-end fw-medium">₹<?php echo number_format($item['price_at_time'], 2); ?></td>
                        <td class="text-end fw-bold text-dark">₹<?php echo number_format($item['price_at_time'] * $item['quantity'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row justify-content-end">
            <div class="col-lg-5 col-md-6">
                <div class="p-4 rounded-4 bg-light">
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted fw-medium small">Subtotal (Excl. Tax)</span>
                        <span class="fw-bold small text-dark">₹<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted fw-medium small">GST (<?php echo number_format($avg_tax_percentage, 1); ?>%)</span>
                        <span class="fw-bold small text-dark">₹<?php echo number_format($total_tax, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted fw-medium small">Delivery Charge</span>
                        <?php if ($delivery_charge > 0): ?>
                            <span class="fw-bold small text-dark">₹<?php echo number_format($delivery_charge, 2); ?></span>
                        <?php else: ?>
                            <span class="text-success fw-bold small">FREE</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($discount > 0): ?>
                    <div class="d-flex justify-content-between mb-3 text-success">
                        <span class="fw-bold small">Coupon (<?php echo htmlspecialchars($order['coupon_code']); ?>)</span>
                        <span class="fw-bold small">- ₹<?php echo number_format($discount, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <hr class="my-3 opacity-10">
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-800 text-uppercase small text-muted" style="letter-spacing: 1px;">Grand Total</span>
                        <div class="text-end">
                            <?php if ($discount > 0): ?>
                                <div class="text-muted text-decoration-line-through extra-small mb-1">₹<?php echo number_format($order['total_amount'] + $discount, 2); ?></div>
                            <?php endif; ?>
                            <span class="fw-800 text-success fs-3 mb-0" style="letter-spacing: -1px;">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-5 pt-4 border-top">
            <div class="row align-items-center">
                <div class="col-md-7 mb-3 mb-md-0">
                    <p class="fw-bold mb-1 small text-dark">Notes & Instructions:</p>
                    <p class="text-muted extra-small mb-0 lh-base">This is a computer-generated invoice and does not require a physical signature. For any discrepancies, please reach out to our support team within 24 hours of delivery. Thank you for choosing Quick mart!</p>
                </div>
                <div class="col-md-5 text-md-end">
                    <div class="bg-success-subtle d-inline-block px-4 py-3 rounded-4">
                        <p class="text-success fw-800 mb-0" style="font-size: 0.9rem;">THANK YOU FOR YOUR ORDER!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4 no-print">
        <p class="text-muted small">Need help? <a href="contact.php" class="text-success fw-bold text-decoration-none">Contact Support</a></p>
    </div>
</div>

<style>
    .fw-800 { font-weight: 800; }
    .extra-small { font-size: 0.7rem; }
    .bg-light-danger { background: #fee2e2; color: #991b1b; }
    .bg-success-subtle { background-color: #f0fdf4 !important; }
    .btn-white:hover { background-color: #f8f9fa; }
</style>

</body>
</html>
