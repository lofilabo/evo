<?php
session_start();

include 'Y_CONFIG/database.php'; 
include 'allclasses.php'; 
$fs=new FormStuffer($db);
$result = $fs->grabFromForm($db);
echo $result;
?>