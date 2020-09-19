<?php 
session_start();
include 'Y_CONFIG/database.php'; 
include 'allclasses.php'; 
$tablename = 'form_submissions';
$_SESSION['tablename'] = $tablename;
$fm = new FormMaker($db);
$fb = new FormBuilder();
$html = $fb->acceptRowsAndMake($fm->get_table_metadata($tablename));
echo($html);
//var_dump( $allrows );


?>