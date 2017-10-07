<?php
ini_set('display_errors', true);
error_reporting(E_ALL);

require_once 'projection.php';

define('CONV', 180/pi());
define('HPI', pi()/2);
define('SQ2', sqrt(2) );
define('HSQ2', sqrt(2)/2 );

print_r([CONV,HPI,SQ2,HSQ2]);

class cirq_ea extends projection {
	static $bb = array(0=>0);
	
	function __construct() {
		$this->defaults(array(
			'width' => 470,
			'height' => 320,
			'scale' => 100,
			'lat' => 0,
			'lon' => 0,
			'ori' => 0,
			'file' => 'default.kml',
			'glob' => '#39c',
			'par' => 15,
			'mer' => 15,
			));
		if(isset($_GET['lines']) && !empty($_GET['lines'])) {
			$this->par = $_GET['lines'];
			$this->mer = $_GET['lines'];
		}
		$this->Lt = deg2rad($this->lat);
		$this->Ln = deg2rad($this->lon);
		/*
		$this->Clt = cos($this->Lt);
		$this->Slt = sin($this->Lt);
		$this->Cln = cos($this->Ln);
		$this->Sln = sin($this->Ln);
		$this->Az = deg2rad($this->ori);
		$this->Caz = cos($this->Az);
		$this->Saz = sin($this->Az);
		*/
		projection::__construct($this->file, $this->width, $this->height);
		$this->off = 0.025;
	}
	
	function shftrnd($x,$y) {
		$S = $this->scale;
		$cx = $this->width/2;
		$cy = $this->height/2;
		return (round(($cx+$S*$x)*8)/8).','.(round(($cy+$S*$y)*8)/8);
	}

	function shftrnd2($ar) {
		$S = $this->scale;
		$cx = $this->width/2;
		$cy = $this->height/2;
		return array(round(($cx+$S*$x)*8)/8 , round(($cy+$S*$y)*8)/8);
	}
	
	function txcoord($lon,$lat) {
		$lmb = deg2rad($lon)-$this->Ln;
		$phi = deg2rad($lat);
		
		if($lmb == 0.0)
			return array(0.0, SQ2*$phi/HPI);

		$b = $this->getB($lmb);
		if($b==0) {
			#echo "$lmb, $phi, $b\n";
			#return array(NAN, NAN);
			$b = $lmb;
		}
		$r = (2+$b*$b)/(2*$b);
		$alp = 2*atan($b*HSQ2);
		/**/$beta = $phi*$alp/HPI;
		$x = $b-$r+$r*cos($beta);
		$y = $r*sin($beta);
		
		echo "$lon $lat -- $x $y ($r $b)\n";
		return array($x, $y);
	}
	
	function getB($lmb) {
		if(abs($lmb)<5e-7) return $lmb;
		$lk = round($lmb*1e+5);
		if(isset(cirq_ea::$bb[$lk])) {
			#echo "* $lmb is catched\n";
			#var_dump(cirq_ea::$bb);
			return cirq_ea::$bb[$lk];
		}
		$b = $this->getBr($lmb,$lmb/2,1e-14,1e-3,20);
		//echo "$lmb -> $b\n";
		cirq_ea::$bb[$lk] = $b;
		return $b;
	}
	
	function getBr($lmb, $b=0, $eps=1e-14, $dlt=1e-3, $n=20) {
		$l = $this->getLm($b);
		$d = $lmb - $l;
		if(abs($d)<$eps) return $b;
		if($n<=0) {
			#echo "No convergence reached for $lmb ($b);\n";
			return abs($d)<$dlt? $b: NAN;
		}
		$bd = $b+$dlt;
		$dm = $l - $this->getLm($b+$dlt);
		$m = $dm/$dlt;
		#echo "$n) Tried $b for $lmb, got $l, dif=$d, grad=$m ($dm/$dlt @ $bd)\n";
		#if($lmb<1e-4) echo sprintf("%2d) Tried %8g for %8g, got %8g, dif=%8g, grad=%8g (%8g/%8g @ %8g)\n",$n,$b,$lmb,$l,$d,$m,$dm,$dlt,$bd);
		if($m==0) return NAN;
		return $this->getBr($lmb, $b-$d/$m, $eps, $dlt, --$n);
	}
	
	function getLm($b) {
		if($b==0) return 0;
		$b2 = $b*$b;
		$bq = SQ2*$b;
		$ob = (2+$b2)/$bq;
		$ha = atan($bq/2);
		return ($ob*$ob*$ha - (2-$b2)/$bq)/2;
	}
}

if(!isset($no_disp)) {
	#ob_start();

	echo "UNO\n";
	$P = new cirq_ea();
	echo "DOS\n";
	$P->draw_base();
	echo "TRES\n";
	$P->make();
	echo "CUATRO\n";
	$P->setstyles();
	echo "CINCO\n";

	#$s = ob_get_clean();
	echo $P->write();
	if($s) echo "<!--\n$s\n-->\n";
}

?>
