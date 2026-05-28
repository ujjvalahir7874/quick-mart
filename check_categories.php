<?php
require_once 'config/db.php';

try {
    $stmt = $pdo->query("SELECT id, name, image_url FROM categories");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $output = "<h2>Categories in Database:</h2>";
    $output .= "<table border='1'>";
    $output .= "<tr><th>ID</th><th>Name</th><th>Image URL</th></tr>";
    foreach ($categories as $category) {
        $output .= "<tr>";
        $output .= "<td>" . htmlspecialchars($category['id']) . "</td>";
        $output .= "<td>" . htmlspecialchars($category['name']) . "</td>";
        $output .= "<td>" . htmlspecialchars($category['image_url']) . "</td>";
        $output .= "</tr>";
    }
    $output .= "</table>";

    file_put_contents('category_output.html', $output);
    echo "Category data written to category_output.html";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>