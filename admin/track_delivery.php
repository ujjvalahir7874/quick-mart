<?php
require_once '../config/db.php';

// Fetch active delivery persons
$delivery_persons = $pdo->query("SELECT * FROM delivery_persons WHERE status != 'Offline' AND current_lat IS NOT NULL")->fetchAll();
$json_persons = json_encode($delivery_persons);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Delivery Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map { height: 90vh; width: 100%; border-radius: 15px; }
        .sidebar { height: 90vh; overflow-y: auto; }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 5px; }
        .person-card { cursor: pointer; transition: all 0.2s; }
        .person-card:hover { background-color: #f1f5f9; }
    </style>
</head>
<body class="bg-light p-3">

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Active Partners</div>
                <div class="card-body sidebar p-0">
                    <?php if (empty($delivery_persons)): ?>
                        <div class="p-3 text-muted text-center">No active partners transmitting location.</div>
                    <?php else: ?>
                        <?php foreach($delivery_persons as $dp): ?>
                            <div class="p-3 border-bottom person-card" onclick="focusMap(<?= $dp['current_lat'] ?>, <?= $dp['current_lng'] ?>, '<?= htmlspecialchars($dp['name']) ?>')">
                                <div class="d-flex align-items-center">
                                    <div class="bg-success rounded-circle p-2 me-2">
                                        <i class="bi bi-bicycle text-white"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($dp['name']) ?></h6>
                                        <small class="text-secondary"><?= $dp['status'] ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-9">
            <div id="map" class="shadow-sm"></div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // Initialize Map
    var map = L.map('map').setView([20.5937, 78.9629], 5); // India Scale Default

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var partners = <?= $json_persons ?>;
    var markers = [];

    // Custom Icon
    var bikeIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/512/3721/3721619.png', // Generic Bike Icon
        iconSize: [38, 38],
        iconAnchor: [19, 38],
        popupAnchor: [0, -30]
    });

    if (partners.length > 0) {
        var bounds = L.latLngBounds();
        
        partners.forEach(function(p) {
            if(p.current_lat && p.current_lng) {
                var marker = L.marker([p.current_lat, p.current_lng], {icon: bikeIcon})
                    .addTo(map)
                    .bindPopup("<b>" + p.name + "</b><br>" + p.mobile_no + "<br>Status: " + p.status);
                markers.push(marker);
                bounds.extend([p.current_lat, p.current_lng]);
            }
        });

        if(markers.length > 0) {
            map.fitBounds(bounds, {padding: [50, 50]});
        }
    }

    function focusMap(lat, lng, name) {
        map.flyTo([lat, lng], 15);
    }
</script>
</body>
</html>
