<?php
ini_set('display_errors', true);
error_reporting(E_ALL);

require_once 'projection.php';

define('MERC_LIM',1.5);
define('MERC_TOP',3.34);

class mercator extends projection {
	function txcoord($lon,$lat) {
		$off = isset($this->lon)? $this->lon: 0;

		$x = ($lon-$off)/180.0;
		while($x <-1.0) $x+= 2.0;
		while($x > 1.0) $x-= 2.0;

		$th = deg2rad($lat);
		if(abs($th)<MERC_LIM)
			$y = log((1+sin($th))/cos($th))/pi();
		else
			$y = $th>0? MERC_TOP: -MERC_TOP;

		return array($x,$y);
	}

	function shftrnd($x,$y) {
		$sw = isset($this->width)? $this->width/2: 360;
		$sh = isset($this->height)? $this->height/2: 270;

		return round($sw + $sw*$x,1).','.round($sh - $sw*$y,1);
	}

	function shftrnd2($ar) {
		$sw = isset($this->width)? $this->width/2: 360;
		$sh = isset($this->height)? $this->height/2: 270;

		return [round($sw + $sw*$ar[0],1) , round($sh - $sw*$ar[1],1)];
	}/**/
}

$file = isset($_GET['file'])? $_GET['file']: 'default.kml';
$width = isset($_GET['width'])? $_GET['width']: 720;
$height = isset($_GET['height'])? $_GET['height']: 540;
$lon = isset($_GET['lon'])? $_GET['lon']: 0;

if(!isset($no_disp)) {
	ob_start();

	$P = new mercator($file,$width,$height);
	$P->lon = $lon;
	$P->draw_base();
	$P->make();
	$P->setstyles();

	$s = ob_get_clean();
	echo $P->write();
	if($s) echo "<!-- <![CDATA[\n".$s."\n]]> -->\n";
}

?>
