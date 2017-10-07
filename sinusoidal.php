<?php
ini_set('display_errors', true);
error_reporting(E_ALL);

require_once 'projection.php';

class sinusoidal extends projection {
	function txcoord($lon,$lat) {
		$off = isset($this->lon)? $this->lon: 0;
		$x = ($lon-$off)/180.0;
		while($x <-1.0) $x+= 2.0;
		while($x > 1.0) $x-= 2.0;
		$x*=cos(deg2rad($lat));
		$y = $lat/90.0;
		return array($x,$y);
	}

	function draw_globe($class=null) {
		$p = $this->globe->addChild('path');
		$c0 = pi()/2;
		$c1 = 0.512286623256592433;
		$c2 = $c0-0.512286623256592433;
		$c3 = $c0-1.002313685767898599;
		$d = [[0,1],[$c1,$c2/$c0],[1,$c3/$c0],[1,0],[1,-$c3/$c0],[$c1,-$c2/$c0],[0,-1],[-$c1,-$c2/$c0],[-1,-$c3/$c0],[-1,0],[-1,$c3/$c0],[-$c1,$c2/$c0],[-0,1]];
		$s = ['M'];
		foreach($d as $c)
			$s[] = implode(',',$this->shftrnd2($c));
		$s[] = ['Z'];
		array_splice($s,2,0,'C');
		$p->addAttribute('d',implode(' ',$s));
		if(!empty($class))
			$this->setstyle($p,$class);
	}
}

$file = isset($_GET['file'])? $_GET['file']: 'default.kml';
$width = isset($_GET['width'])? $_GET['width']: 720;
$height = isset($_GET['height'])? $_GET['height']: 540;
$lon = isset($_GET['lon'])? $_GET['lon']: 0;

if(!isset($no_disp)) {
	ob_start();

	$P = new sinusoidal($file,$width,$height);
	$P->lon = $lon;
	$P->draw_base();
	$P->make();
	$P->setstyles();

	$s = ob_get_clean();
	echo $P->write();
	if($s) echo "<!-- <![CDATA[\n".$s."\n]]> -->\n";
}

?>
