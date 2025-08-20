<?php
echo "Server is working!";
echo "<br>Current directory: " . __DIR__;
echo "<br>Files in directory:";
$files = scandir(__DIR__);
foreach($files as $file) {
    if($file != "." && $file != "..") {
        echo "<br>- " . $file;
    }
}
?> 