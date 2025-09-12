<?php
// Fix table name in wearos_api.php
$content = file_get_contents('wearos_api.php');
$content = str_replace('FROM tbl_employee WHERE', 'FROM tbl_employees WHERE', $content);
$content = str_replace('SELECT * FROM tbl_employee WHERE', 'SELECT * FROM tbl_employees WHERE', $content);
file_put_contents('wearos_api.php', $content);
echo "Fixed table names in wearos_api.php\n";
?>
