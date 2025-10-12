<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ทดสอบรูปภาพ - Debug</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .image-test { margin: 10px; padding: 10px; border: 1px solid #ccc; }
        .status-ok { color: green; }
        .status-error { color: red; }
    </style>
</head>
<body>
    <h1>ทดสอบไฟล์รูปภาพ</h1>
    
    <?php
    require '../config/db_connect.php';
    
    function getImagePath($imageName) {
        if (empty($imageName)) {
            return '../images/noimg.png';
        }
        
        // รายการ path ที่เป็นไปได้
        $possible_paths = [
            '../images/' . $imageName,
            '../' . $imageName,
            $imageName
        ];
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // หากไม่พบไฟล์ใดๆ ใช้ noimg.png
        return '../images/noimg.png';
    }
    
    // ดึงรายการรูปภาพจากฐานข้อมูล
    $sql = "SELECT DISTINCT p.image FROM products p WHERE p.image IS NOT NULL AND p.image != '' LIMIT 10";
    $stmt = $pdo->query($sql);
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>รายการรูปภาพในฐานข้อมูล:</h2>";
    
    foreach ($images as $image) {
        $image_path = getImagePath($image);
        $exists = file_exists($image_path);
        
        echo "<div class='image-test'>";
        echo "<strong>DB Value:</strong> " . htmlspecialchars($image) . "<br>";
        echo "<strong>Resolved Path:</strong> " . htmlspecialchars($image_path) . "<br>";
        echo "<strong>File Exists:</strong> <span class='" . ($exists ? 'status-ok' : 'status-error') . "'>" . 
             ($exists ? 'YES' : 'NO') . "</span><br>";
        
        if ($exists) {
            echo "<img src='" . htmlspecialchars($image_path) . "' alt='Test' style='max-width: 100px; max-height: 100px;'><br>";
        }
        
        echo "</div>";
    }
    ?>
    
</body>
</html>