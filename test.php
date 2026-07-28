<?php
require 'db.php';
$db = get_db();
echo "<pre>";
print_r(array_slice($db['students'], 0, 5));
echo "</pre>";
