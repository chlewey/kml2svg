<?php
$no_disp=true;
require_once "lambaz-ea.php";

class lambaz_ed extends lambaz_ea {
	function txcoord($lon,$lat) {
		if(!isset($this->dpi)) $this->dpi = acos(0);
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
		$d = asin($r/2);
		$r0 = sqrt($x2*$x2+$y2*$y2);
		$x3 = $r0==0? $d: $d*$x2/$r0;
		$y3 = $r0==0? $d: $d*$y2/$r0;
		return array($x3/$this->dpi, $y3/$this->dpi);
	}
};

$P = new lambaz_ed();
$P->draw_base();
$P->make();
$P->setstyles();

echo $P->write();
?>
