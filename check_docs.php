<?php
require_once 'config/db.php';

try {
    $stmt = $pdo->query("SELECT id, name, doc_aadhaar, doc_license, doc_rc, doc_photo FROM delivery_persons");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h1>Delivery Persons Document Paths</h1>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Aadhaar</th><th>License</th><th>RC</th><th>Photo</th></tr>";
    
    foreach ($rows as $row) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['name'] . "</td>";
        echo "<td>" . ($row['doc_aadhaar'] ?: 'NULL') . "</td>";
        echo "<td>" . ($row['doc_license'] ?: 'NULL') . "</td>";
        echo "<td>" . ($row['doc_rc'] ?: 'NULL') . "</td>";
        echo "<td>" . ($row['doc_photo'] ?: 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
