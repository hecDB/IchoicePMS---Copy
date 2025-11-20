<?php
if (extension_loaded('zip')) {
    echo "✅ ZipArchive extension is ENABLED!";
} else {
    echo "❌ ZipArchive extension is NOT enabled";
}
echo "\n\nPHP Version: " . phpversion();
echo "\n\nLoaded Extensions:\n";
print_r(get_loaded_extensions());
?>
