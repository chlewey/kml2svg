<?php
ini_set('display_errors', true);
error_reporting(E_ALL);

require_once 'projection.php';

function find_between($value, array $arr) {
	// $arr is an ordered array of floats
	// it returns two values of $arr, such as the first is less or equal
	// to $value and the second is the next value.
	// returns false if $value is not in range.
	// unpredicted behavior is array is not ordered.
	sort($arr);
	$n = count($arr);
	$j = -1;
	for($a=$n-1,$b=0;$a>$b;) {
		$i = (int)(($a+$b)/2);
		if($i==$j) break;
		echo ". $i/$n";
		$j = $i;
		if(($v=$arr[$i])<=$value && $value<($w=$arr[$i+1])) return [$v,$w];
		if($v<$value) $b=$i;
		else $a=$i;
	}
	echo "\n";
	return false;
}

$mollvalues = [0=>0.0, 90000=>M_PI_2];
function molltheta($phi,$iter=5,$seed=false) {
	if($phi<0) return -molltheta(-$phi);
	echo sprintf( "Calculating θ for φ=%.6f (%.4f°) with %s.\n", $phi, rad2deg($phi), $seed===false?'no seed':"seed $seed");
	global $mollvalues;
	$Klat = round(rad2deg($phi)*1000);
	if(isset($mollvalues[$Klat])) {
		echo "Already done: θ={$mollvalues[$Klat]}\n";
		return $mollvalues[$Klat];
	}
	if($seed===false) {
		$keys = array_keys($mollvalues);
		sort($keys);
		echo "finding $Klat in ".implode(';',$keys).chr(10);
		if($L=find_between($Klat, $keys)) {
			echo sprintf("Extrapollating between φ₁=%.4f° and φ₂=%.4f°, ", $L[0]/1000,$L[1]/1000);
			$p = ($Klat-$L[0])/($L[1]-$L[0]);
			$u = $mollvalues[$L[0]];
			$v = $mollvalues[$L[1]];
			$theta = $u+($v-$u)*$p;
			echo "seed is $theta.\n";
		} else {
			$theta = (1-pow(1-$phi/M_PI_2,2/3))*M_PI_2;# $phi-$phi*$phi/M_PI_2+$phi*$phi*$phi/M_PI_2/M_PI_2;
			echo "Using defaut initial value of θ=φ-2φ²/π+4φ³/π² == $theta\n";
		}
	} else
		$theta = $seed;
	$target = M_PI*sin($phi);
	$dd=100;
	for($i=0; $i<$iter; $i++) {
		$th2 = 2*$theta;
		$id = $th2+sin($th2);
		$dif = $target-$id;
		$did = 2+2*cos($th2);
		$d = $dif/$did;
		echo sprintf("%5d, π sin φ =%9.5f, θ =%9.5f, 2θ+sin2θ =%9.5f, diff=%9.5f, 2+cos2θ=%9.5f, d=%10g (%10g). (%9.5f)\n", $i, $target, $theta, $id, $dif, $did, $d, $d+$dd, $th2);
		if(abs($d+$dd)<=1e-20) { $theta + $d/=2; break; }
		$theta += ($dd = $d);
	}
	$mollvalues[$Klat] = $theta;
	ksort($mollvalues);
	return $theta;
}

function mollseed() {
	for($theta=1;$theta<90;$theta++) {
		$th2 = 2*deg2rad($theta);
		$phi1 = asin(($th2+sin($th2))/M_PI);
		$lat = round(rad2deg($phi1),3);
		$phi = deg2rad($lat);
		molltheta($phi, 10, deg2rad($theta)); // it populates $mollvalues;
	}
}

class mollweide extends projection {
	function __construct($kmlfile, $width=1080, $height=540, $viewBox=null) {
		mollseed();
		projection::__construct($kmlfile, $width, $height, $viewBox);
		#exit;
	}

	function txcoord($lon,$lat) {
		$off = isset($this->lon)? $this->lon: 0;
		$x = ($lon-$off)/180.0;
		while($x <-1.0) $x+= 2.0;
		while($x > 1.0) $x-= 2.0;
		$theta = molltheta(deg2rad($lat));
		$x*= cos($theta);
		$y = sin($theta);

		return array($x,$y);
	}

	function draw_globe($class=null) {
		$sw = isset($this->width)? $this->width/2: 360;
		$sh = isset($this->height)? $this->height/2: 270;
		$p = $this->globe->addChild('circle');
		$p->addAttribute('id', 'base');
		$p->addAttribute('cx', 0);
		$p->addAttribute('cy', 0);
		$p->addAttribute('r', $sw);
		$p->addAttribute('transform', sprintf("matrix(1,0,0,%f,%f,%f)",$sh/$sw,$sw,$sh));
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

	$P = new mollweide($file,$width,$height);
	$P->lon = $lon;
	$P->draw_base();
	$P->make();
	$P->setstyles();

	$s = ob_get_clean();
	echo $P->write();
	if($s) echo "<!-- <![CDATA[\n".$s."\n]]> -->\n";
}

?>
