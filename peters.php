<?php
ini_set('display_errors', true);
error_reporting(E_ALL);

require_once 'projection.php';

class peters extends projection {
	function txcoord($lon,$lat) {
		$off = isset($this->lon)? $this->lon: 0;
		$x = ($lon-$off)/180.0;
		$y = sin(deg2rad($lat));
		while($x <-1.0) $x+= 2.0;
		while($x > 1.0) $x-= 2.0;
		return array($x,$y);
	}
}

$file = isset($_GET['file'])? $_GET['file']: 'default.kml';
$width = isset($_GET['width'])? $_GET['width']: 720;
$height = isset($_GET['height'])? $_GET['height']: 540;
$lon = isset($_GET['lon'])? $_GET['lon']: 0;

if(!isset($no_disp)) {
	ob_start();

	$P = new peters($file,$width,$height);
	$P->lon = $lon;
	$P->draw_base();
	$P->make();
	$P->setstyles();

	$s = ob_get_clean();
	echo $P->write();
	if($s) echo "<!-- <![CDATA[\n".$s."\n]]> -->\n";
}

?>
