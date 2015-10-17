<?php
ini_set('display_errors', true);
error_reporting(E_ALL);

require_once 'projection.php';

class lambaz_ea extends projection {
	function __construct() {
		$this->defaults(array(
			'r' => 500,
			'lat' => 4,
			'lon' => -72,
			'ori' => 0,
			'file' => 'default.kml',
			'glob' => '#134',
			'par' => 15,
			'mer' => 15,
			));
		if(isset($_GET['lines']) && !empty($_GET['lines'])) {
			$this->par = $_GET['lines'];
			$this->mer = $_GET['lines'];
		}
		$this->Lt = deg2rad($this->lat);
		$this->Clt = cos($this->Lt);
		$this->Slt = sin($this->Lt);
		$this->Ln = deg2rad($this->lon);
		$this->Cln = cos($this->Ln);
		$this->Sln = sin($this->Ln);
		$this->Az = deg2rad($this->ori);
		$this->Caz = cos($this->Az);
		$this->Saz = sin($this->Az);
		projection::__construct($this->file, 2*$this->r, 2*$this->r);
		$this->off = 0.025;
	}

	function shftrnd($x,$y) {
		$R = $this->r;
		return (round($R*(1+$x)*8)/8).','.(round($R*(1-$y)*8)/8);
	}

	function shftrnd2($ar) {
		$R = $this->r;
		return array(round($R*(1+$ar[0])*8)/8, round($R*(1-$ar[1])*8)/8);
	}
	
	function txcoord($lon,$lat) {
		$phi = deg2rad($lon)-$this->Ln;
		$the = deg2rad($lat);
		$x = sin($phi)*cos($the);
		$y = sin($the);
		$z = cos($phi)*cos($the);
		$y1 = $y*$this->Clt-$z*$this->Slt;
		$z1 = 1-($y*$this->Slt+$z*$this->Clt);
		$x2 = $x*$this->Caz-$y1*$this->Saz;
		$y2 = $x*$this->Saz+$y1*$this->Caz;
		$r = sqrt($x2*$x2+$y2*$y2+$z1*$z1);
		$r0 = sqrt($x2*$x2+$y2*$y2);
		$x3 = $r0==0? $r: $r*$x2/$r0;
		$y3 = $r0==0? $r: $r*$y2/$r0;
		return array($x3/2, $y3/2);
	}

	function draw_globe($class=null) {
		$R = $this->r;
		$d = (int)($R*0.1);
		$p = $this->globe->addChild('circle');
		$p->addAttribute('cx', $R);
		$p->addAttribute('cy', $R);
		$p->addAttribute('r', $R);
		if(!empty($class))
			$this->setstyle($p,$class);
		$c = $this->svg->newpath(
			sprintf("M %d,%d %d,%d M %d,%d %d,%d", $R,$R-$d,$R,$R+$d,$R-$d,$R,$R+$d,$R),
			'crossline');
		$this->setstyle($c,'cross');
	}
}

if(!isset($no_disp)) {
	$P = new lambaz_ea();
	$P->draw_base();
	$P->make();
	$P->setstyles();

	echo $P->write();
}
?>
