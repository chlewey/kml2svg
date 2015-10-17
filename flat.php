<?php
ini_set('display_errors', true);
error_reporting(E_ALL);

require_once 'projection.php';

$file = isset($_GET['file'])? $_GET['file']: 'default.kml';
$width = isset($_GET['width'])? $_GET['width']: 720;
$height = isset($_GET['height'])? $_GET['height']: 540;
$lon = isset($_GET['lon'])? $_GET['lon']: 0;

$P = new projection($file,$width,$height);
$P->lon = $lon;
$P->draw_base();
$P->make();
$P->setstyles();

echo $P->write();
?>
