<?php
require_once 'config/db.php';

// Fetch active delivery persons
try {
    $stmt = $pdo->query("SELECT id, name FROM delivery_persons");
    $delivery_persons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching delivery persons: " . $e->getMessage());
}

// Fetch recent orders that are shipped or out for delivery
try {
    $stmt = $pdo->query("
        SELECT o.id, o.order_id, u.name as customer_name, dp.name as agent_name, o.status
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN delivery_persons dp ON o.delivery_person_id = dp.id
        WHERE o.status IN ('Shipped', 'Out for Delivery')
        ORDER BY o.id DESC
    ");
    $active_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $active_orders = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery GPS Simulator - Mock Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map { height: 500px; width: 100%; border-radius: 12px; cursor: crosshair; }
        .agent-card { transition: all 0.3s ease; }
        .agent-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .status-badge { font-size: 0.8rem; padding: 4px 8px; border-radius: 20px; }
    </style>
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="fw-bold"><i class="bi bi-geo-alt-fill text-primary"></i> GPS Simulator</h1>
            <p class="text-muted">Simulate real-time delivery tracking by clicking on the map.</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-house"></i> Back to Store</a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Sidebar Controls -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-3">Simulation Settings</h5>
                    
                    <div class="mb-3">
                        <label class="form-label">1. Select Delivery Agent</label>
                        <select id="agentSelect" class="form-select rounded-3">
                            <option value="">-- Choose Agent --</option>
                            <?php foreach ($delivery_persons as $dp): ?>
                                <option value="<?php echo $dp['id']; ?>"><?php echo htmlspecialchars($dp['name']); ?> (ID: <?php echo $dp['id']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">2. Select Active Order (Optional)</label>
                        <select id="orderSelect" class="form-select rounded-3">
                            <option value="">-- No Specific Order --</option>
                            <?php foreach ($active_orders as $order): ?>
                                <option value="<?php echo $order['id']; ?>">
                                    #<?php echo $order['order_id']; ?> - <?php echo htmlspecialchars($order['customer_name']); ?> (<?php echo $order['status']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Linking an order saves history to delivery_tracking table.</small>
                    </div>

                    <div class="d-grid gap-2">
                        <button id="updateBtn" class="btn btn-primary rounded-3" disabled>
                            <i class="bi bi-send-fill me-2"></i> Update Current Position
                        </button>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="autoUpdate">
                            <label class="form-check-label" for="autoUpdate">Auto-update every 5s (Click map to change path)</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-3">Current Data</h5>
                    <div class="p-3 bg-light rounded-3">
                        <div class="mb-2"><strong>Lat:</strong> <span id="displayLat">-</span></div>
                        <div class="mb-2"><strong>Lng:</strong> <span id="displayLng">-</span></div>
                        <div id="statusMsg" class="mt-3 small"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Map Area -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div id="map"></div>
                <div class="card-footer bg-white p-3 border-0">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-info-circle text-info fs-4"></i>
                        </div>
                        <div>
                            <p class="mb-0 small text-muted">Click anywhere on the map to set the next location. If <strong>Auto-update</strong> is on, the agent will "move" to that spot every 5 seconds.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let map, marker;
    let currentLat = 20.5937;
    let currentLng = 78.9629;
    let targetLat = null;
    let targetLng = null;
    let autoInterval = null;

    // Initialize Map
    function initMap() {
        map = L.map('map').setView([currentLat, currentLng], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        marker = L.marker([currentLat, currentLng], { draggable: true }).addTo(map);

        map.on('click', function(e) {
            updateMarker(e.latlng.lat, e.latlng.lng);
        });

        marker.on('dragend', function(e) {
            const pos = marker.getLatLng();
            updateMarker(pos.lat, pos.lng);
        });
    }

    function updateMarker(lat, lng) {
        currentLat = lat;
        currentLng = lng;
        marker.setLatLng([lat, lng]);
        document.getElementById('displayLat').textContent = lat.toFixed(6);
        document.getElementById('displayLng').textContent = lng.toFixed(6);
        document.getElementById('updateBtn').disabled = !document.getElementById('agentSelect').value;
    }

    async function sendLocationUpdate() {
        const agentId = document.getElementById('agentSelect').value;
        const orderId = document.getElementById('orderSelect').value;
        const statusMsg = document.getElementById('statusMsg');

        if (!agentId) return;

        try {
            const formData = new FormData();
            formData.append('delivery_person_id', agentId);
            formData.append('lat', currentLat);
            formData.append('lng', currentLng);
            if (orderId) formData.append('order_id', orderId);

            const response = await fetch('api/update_location.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                statusMsg.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill"></i> Updated at ${new Date().toLocaleTimeString()}</span>`;
            } else {
                statusMsg.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> ${result.message}</span>`;
            }
        } catch (error) {
            statusMsg.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Network error</span>`;
        }
    }

    document.getElementById('agentSelect').addEventListener('change', function() {
        document.getElementById('updateBtn').disabled = !this.value;
    });

    document.getElementById('updateBtn').addEventListener('click', sendLocationUpdate);

    document.getElementById('autoUpdate').addEventListener('change', function() {
        if (this.checked) {
            autoInterval = setInterval(sendLocationUpdate, 5000);
            sendLocationUpdate(); // Initial call
        } else {
            clearInterval(autoInterval);
        }
    });

    window.onload = initMap;
</script>

</body>
</html>
