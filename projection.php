<?php

class svg {
	public $xml;

	function __construct($width=720,$height=540,$viewBox=null,$desc=null) {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" />';
		$this->xml = new SimpleXMLElement($svg);

		$this->addAttribute('height',$height);
		$this->addAttribute('width',$width);
		if(empty($viewBox))
			$this->addAttribute('viewBox',sprintf('0 0 %f %f',$width,$height));
		else
			$this->addAttribute('viewBox',$viewBox);
			
		$script = $this->addChild('script');
		$script['id'] = "svgpan";
		$script['xlink:href'] = "SVGPan.js";

		$this->defs = $this->addChild('defs');
		$this->defs->addAttribute('id','defs');
		
		if(!empty($desc))
			$this->addChild('desc',$desc);
		
		$this->layer = $this->addChild('g');
		$this->layer->addAttribute('id','Layer-0');
		$this->current = $this->layer;
	}

	function addAttribute($name,$value=null,$ns=null) {
		return $this->xml->addAttribute($name,$value,$ns);
	}

	function addChild($name,$value=null,$ns=null) {
		return $this->xml->addChild($name,$value,$ns);
	}
	
	function asXML($filename=null) {
		$dom = new DOMDocument('1.0');
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML($this->xml->asXML());
		if(empty($filename))
			return $dom->saveXML();
		else
			$dom->save($filename);
	}

	function newgroup($id=null, $group=null) {
		if(empty($group)) $group = $this->layer;
		$g = $group->addChild('g');
		if(!empty($id))
			$g->addAttribute('id',$id);
		$this->current = $g;
		return $g;
	}
	
	function newpoly($coords, $id, $class=null, $parent=null) {
		if(empty($parent)) $parent = $this->current;
		$p = $parent->addchild('polygon');
		$p->addAttribute('points',$coords);
		if(!empty($id))
			$p->addAttribute('id',$id);
		if(!empty($class))
			$p->addAttribute('class',$class);
		return $p;
	}

	function newpath($data, $id=null, $class=null, $parent=null) {
		if(empty($parent)) $parent = $this->current;
		$p = $parent->addchild('path');
		$p->addAttribute('d',$data);
		if(!empty($id))
			$p->addAttribute('id',$id);
		if(!empty($class))
			$p->addAttribute('class',$class);
		return $p;
	}

	function newpoint($x, $y, $r=3, $id=null, $class=null, $parent=null) {
		if(empty($parent)) $parent = $this->current;
		$c = $parent->addchild('circle');
		$c->addAttribute('cx',$x);
		$c->addAttribute('cy',$y);
		$c->addAttribute('r',$r);
		if(!empty($id))
			$c->addAttribute('id',$id);
		if(!empty($class))
			$c->addAttribute('class',$class);
		return $c;
	}
	
	function addstyle($line) {
		if(!isset($this->styles)) {
			$this->styles = $this->defs->addChild('style',"\npath, polygon { stroke-linejoin: round; stroke-linecap: round }\n");
			$this->styles->addAttribute('id','general');
		}
		$data = (string) $this->styles;
		$data.= "$line\n";
		$this->styles[0] = $data;
	}
};

class projection {
	protected function defaults(array $arr) {
		foreach($arr as $key=>$val) {
			$this->$key = empty($_GET[$key])? $val: $_GET[$key];
		}
	}
	protected $document, $svg;
	private $styles,$ustyles;
	
	function __construct($kmlfile, $width=720, $height=540, $viewBox=null) {
		$kml = simplexml_load_file($kmlfile);
		$this->document = $kml->Document;
		$this->min = 0.0001;
		$this->off = 0.1;
		
		$this->width = $width;
		$this->height = $height;
		
		$this->svg = new svg($width, $height, $viewBox, $this->document->name);
		$this->styles = array();
		$this->ustyles = array();
		$this->styles['base'] = $this->arr2sty(array('fill'=>isset($this->glob)? $this->glob: '#39c'));
		$this->styles['lines'] = $this->arr2sty(array('fill'=>'none','stroke'=>isset($this->lcol)? $this->lcol: 'white','stroke-opacity'=>'0.375'));
		$this->styles['trop'] = $this->arr2sty(array('fill'=>'none','stroke'=>isset($this->lcol)? $this->lcol: 'white','stroke-opacity'=>'0.75','stroke-dasharray'=>'3,2,3,5'));
		$this->styles['cross'] = $this->arr2sty(array('fill'=>'none','stroke'=>isset($this->cross)? $this->cross: 'black','stroke-opacity'=>'0.375'));
		$this->getstyles();
	}

	function txcoord($lon,$lat) {
		$off = isset($this->lon)? $this->lon: 0;
		$x = ($lon-$off)/180.0;
		$y = $lat/90.0;
		while($x <-1.0) $x+= 2.0;
		while($x > 1.0) $x-= 2.0;
		return array($x,$y);
	}
	
	function shftrnd($x,$y) {
		$w = isset($this->width)? $this->width: 720;
		$h = isset($this->height)? $this->height: 540;
		return (round($w*(1+$x))/2).','.(round($h*(1-$y))/2);
	}

	function shftrnd2($ar) {
		return array(round($ar[0]*2)/2, round($ar[1]*2)/2);
	}

	function cseries($str, $par=false) {
		$cpairs = explode(' ',$str);

		$slon = $slat = null;
		$sx = $sy = null;
		$d = array();
		foreach($cpairs as $cp) {
			if(trim($cp)=="") continue;
			list($lon,$lat) = explode(',',$cp);
			if(!is_numeric($lon)) { echo "<em>$cp</em> [$name]<br>\n"; continue; }
			list($x,$y) = $this->txcoord($lon,$lat);
			if(is_null($slat)) {
				$d[] = $this->shftrnd($x,$y);
			} elseif($par) {
				$this->mkline2($d,$slon,$slat,$lon,$lat,0.01);
			} else {
				$this->mkline($d,$slon,$slat,$lon,$lat,0.01);
			}
			$slat=$lat;
			$slon=$lon;
		}
		return implode(' ',$d);
	}
	
	function midpoint($ln0,$lt0,$ln1,$lt1,$p=0.5) {
		static $t0,$f0,$t1,$f1;
		static $x0,$y0,$z0;
		static $x1,$y1,$z1;
		if($t0!=$ln0 || $f0!=$lt0) {
			list($t0,$f0) = array($ln0,$lt0);
			$x0 = sin(deg2rad($t0))*cos(deg2rad($f0));
			$z0 = cos(deg2rad($t0))*cos(deg2rad($f0));
			$y0 = sin(deg2rad($f0));
		}
		if($t1!=$ln1 || $f1!=$lt1) {
			list($t1,$f1) = array($ln1,$lt1);
			$x1 = sin(deg2rad($t1))*cos(deg2rad($f1));
			$z1 = cos(deg2rad($t1))*cos(deg2rad($f1));
			$y1 = sin(deg2rad($f1));
		}
		$q=1-$p;
		list($xr, $yr, $zr) = array($q*$x0+$p*$x1, $q*$y0+$p*$y1, $q*$z0+$p*$z1);
		$rr = sqrt($xr*$xr+$yr*$yr+$zr*$zr);
		$lnr = rad2deg(atan2($xr,$zr));
		$ltr = $rr? rad2deg(asin($yr/$rr)): 0.0;
		return array($lnr,$ltr);
	}

	function mkline(&$d,$ln0,$lt0,$ln1,$lt1,$x0=null,$y0=null) {
		$off = isset($this->off)? $this->off: 0.1;
		$min = isset($this->min)? $this->min: 0.0001;
		/*if($ln0-$ln1>180)
			return $this->mkline($d,$ln0,$lt0,$ln1+360,$lt1,$x0,$y0);
		if($ln1-$ln0>180)
			return $this->mkline($d,$ln0+360,$lt0,$ln1,$lt1,$x0,$y0);*/
		if(is_null($y0))
			list($x0,$y0) = $this->txcoord($ln0,$lt0);
		list($x,$y) = $this->txcoord($ln1,$lt1);
		$i = 1.0;
		while($i>$min) {
			list($ln,$lt) = $this->midpoint($ln0,$lt0,$ln1,$lt1,$i);
			list($x,$y) = $this->txcoord($ln,$lt);
			if(abs($x-$x0)<$off && abs($y-$y0)<$off)
				break;
			$i*=0.63;
		}
#		if($i<$min) $d[] = 'M';
		$d[] = $this->shftrnd($x,$y);
		if($i<1.0) {
			$this->mkline($d,$ln,$lt,$ln1,$lt1,$x,$y);
		}
	}

	function mkline2(&$d,$ln0,$lt0,$ln1,$lt1,$x0=null,$y0=null) {
		$off = isset($this->off)? $this->off: 10;
		$min = isset($this->min)? $this->min: 0.00001;
		if($ln0-$ln1>180)
			return $this->mkline2($d,$ln0,$lt0,$ln1+360,$lt1,$x0,$y0);
		if($ln1-$ln0>180)
			return $this->mkline2($d,$ln0+360,$lt0,$ln1,$lt1,$x0,$y0);
		if(is_null($y0))
			list($x0,$y0) = $this->txcoord($ln0,$lt0);
		list($x,$y) = $this->txcoord($ln1,$lt1);
		$i = 1.0;
		while($i>$min) {
			$ln = $i*$ln1+(1-$i)*$ln0;
			$lt = $i*$lt1+(1-$i)*$lt0;
			list($x,$y) = $this->txcoord($ln,$lt);
			if(abs($x-$x0)<$off && abs($y-$y0)<$off)
				break;
			$i*=0.63;
		}
#		if($i<$min) $d[] = 'M';
		$d[] = $this->shftrnd($x,$y);
		if($i<1.0) {
			$this->mkline2($d,$ln,$lt,$ln1,$lt1,$x,$y);
		}
	}

	function draw_globe($class=null) {
		$p = $this->globe->addChild('rect');
		$p->addAttribute('x', 0);
		$p->addAttribute('y', 0);
		$p->addAttribute('width', $this->width);
		$p->addAttribute('height', $this->height);
		if(!empty($class))
			$this->setstyle($p,$class);
	}
	
	function draw_par_at($lat,$class=null,$name=null) {
		$p = $this->globe->addChild('path');
		if(empty($name))
			$name = 'par-'.((int)abs($lat)).($lat>0?'N':($lat<0?'S':''));
		$p->addAttribute('id',$name);
		if(!empty($class))
			$this->setstyle($p,$class);
		$p->addAttribute('d','M'.$this->cseries("-180,$lat -90,$lat 0,$lat 90,$lat 180,$lat",true));
		return $p;
	}

	function draw_mer_at($lon,$class=null,$name=null) {
		$p = $this->globe->addChild('path');
		if(empty($name))
			$name = 'mer-'.((int)abs($lon)).($lon>0?'E':($lon<0?'W':''));
		$p->addAttribute('id',$name);
		if(!empty($class))
			$this->setstyle($p,$class);
		$p->addAttribute('d','M'.$this->cseries("$lon,85 $lon,0 $lon,-85",true));
		return $p;
	}
	
	function draw_par($delta=null,$class=null,$equat=true) {
		if(empty($delta)) $delta = isset($this->par)? $this->par: (isset($this->lines)? $this->lines: 15);
		if($equat)
			$this->draw_par_at(0,$class);
		for($p = $delta; $p<90; $p+=$delta) {
			$this->draw_par_at($p,$class);
			$this->draw_par_at(-$p,$class);
		}
	}

	function draw_mer($delta=null,$class=null,$offset=0) {
		if(empty($delta)) $delta = isset($this->mer)? $this->mer: (isset($this->lines)? $this->lines: 15);
		$this->draw_mer_at($offset,$class);
		for($m = $delta; $m<180; $m+=$delta) {
			$this->draw_mer_at($offset+$m,$class);
			$this->draw_mer_at($offset-$m,$class);
		}
		if($m==180)
			$this->draw_mer_at($offset+$m,$class);
	}

	function draw_trop($class=null) {
		$this->draw_par_at(0,$class,'equator');
		$this->draw_par_at( 23.43724,$class,'trop_N');
		$this->draw_par_at(-23.43724,$class,'trop_S');
		$this->draw_par_at( 66.56276,$class,'pcir_N');
		$this->draw_par_at(-66.56276,$class,'pcir_S');
	}
	
	function draw_base($dpars=null,$dmers=null) {
		$svg = $this->svg;
		$G = $this->globe = $svg->newgroup('globe');
		$this->draw_globe('base');
		$this->draw_par($dpars,'lines',false);
		$this->draw_mer($dmers,'lines',0);
		$this->draw_trop('trop');
		return $G;
	}
	
	function PMname($P) {
		$name = isset($P->name)? $P->name: null;
		if(is_null($name)) {
			foreach($P->ExtendedData as $v=>$w) {
				if((string)$w->Data['name']=='Name')
					$name = $w->Data->value;
			}
		}
		return $name;
	}
	
	function PMdraw($P, $id=null) {
		if(empty($id)) $id=$this->PMname($P);
		#echo "<!-- drawing $id -->\n";
		$svg = $this->svg;
		if(isset($P->Polygon)) {
			$co = $P->Polygon->outerBoundaryIs->LinearRing->coordinates;
			$st = $P->styleUrl;
			$p = $svg->newpoly($this->cseries($co),$id,$st);
			$this->setstyle($p, $st);
		} elseif(isset($P->Point)) {
			$co = $P->Point->coordinates;
			$c = explode(',',$co);
			list($x,$y) = $this->shftrnd2($this->txcoord($c[0],$c[1]));
			$st = $P->styleUrl;
			$p = $svg->newpoint($x,$y,3,$id,$st);
			$this->setstyle($p, $st);
		} elseif(isset($P->LineString)) {
			$co = $P->LineString->coordinates;
			$st = $P->styleUrl;
			$p = $svg->newpath('M'.$this->cseries($co),$id,$st);
			$this->setstyle($p, $st);
		} elseif(isset($P->MultiGeometry)) {
			$st = $P->styleUrl;
			$d = '';
			foreach($P->MultiGeometry->Polygon as $i=>$mg) {
				$co = $mg->outerBoundaryIs->LinearRing->coordinates;
				$d.= 'M '.$this->cseries($co).' z';
			}
			$p = $svg->newpath($d,$id,$st);
			$this->setstyle($p, $st);/**/
		} else {
			echo "<strong> $name </strong><br/>\n";
		}
	}
	
	function make() {
		$svg = $this->svg;
		$doc = $this->document;

		foreach($doc->Placemark as $P)
			$this->PMdraw($P);
			
		if(isset($doc->Folder))
			foreach($doc->Folder as $k=>$v) {
				$layer = $svg->newgroup($v->name);
				foreach($v->Placemark as $P)
					$this->PMdraw($P);
			}
	}
	
	function addstyle($desc) {
		$this->svg->addstyle($line);
	}
	
	function setstyle(&$obj, $desc) {
		$class = ltrim("$desc",'#');
		#echo "<!-- $class -->\n";
		if(!isset($this->ustyles[$class])) {
			$s = $this->styles[$class];
			#echo '<!--';var_dump($s);echo '-->'.chr(10);
			if(is_array($s)) {
				$normal = ltrim( $s['normal'], '#' );
				$highlight = ltrim( $s['highlight'], '#' );
				$this->ustyles[$class] = '{ '.$this->styles[$normal].' }';
				$this->ustyles[$class.':hover'] = '{ '.$this->styles[$highlight].' }';
			} else {
				$this->ustyles[$class] = "{ $s }";
			}
		}
		#echo '<!--';var_dump($this->ustyles);echo '-->'.chr(10);
		$obj['class'] = $class;
	}
	
	function arr2sty(array $ar, $def='') {
		$u=array();
		foreach($ar as $k=>$v)
			$u[] = "$k: $v";
		return implode('; ', $u);
	}

	function getstyles() {
		$D = $this->document->children();
		$defU = array('opacity'=>'0.75', 'fill'=>'white', 'stroke'=>'black');
		
		foreach($D as $a=>$b) {
			if($a=='Style') {
				$u = array();
				if(isset($b->LineStyle->color)) {
					$s = $b->LineStyle->color;
					preg_match('/([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})/',$s,$m);
					$u['stroke'] = '#'.$m[4].$m[3].$m[2];
					$u['stroke-opacity'] = sprintf('%.3f',hexdec($m[1])/255.0);
					if(!isset($b->PolyStyle))
						$u['fill'] = 'none';
				}
				if(isset($b->LineStyle->width)) {
					$u['stroke-width'] = $b->LineStyle->width;
				}
				if(isset($b->PolyStyle->color)) {
					$s = $b->PolyStyle->color;
					preg_match('/([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})/',$s,$m);
					$u['fill'] = '#'.$m[4].$m[3].$m[2];
					$u['fill-opacity'] = sprintf('%.3f',hexdec($m[1])/255.0);
				}
				if(isset($b->IconStyle->color)) {
					$s = $b->IconStyle->color;
					preg_match('/([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})/',$s,$m);
					$u['fill'] = '#'.$m[4].$m[3].$m[2];
					$u['fill-opacity'] = sprintf('%.3f',hexdec($m[1])/255.0);
					$u['stroke'] = 'black';
					$u['stroke-opacity'] = '0.5';
				}
				$id = (string)$b['id'];
				$this->styles[$id] = empty($u)? $this->arr2sty($defU): $this->arr2sty($u);
			}
			elseif($a=='StyleMap') {
				$u = array();
				foreach($b->Pair as $v) {
					$key = (string)$v->key;
					$u[$key] = (string)$v->styleUrl;
				}
				$id = (string)$b['id'];
				$this->styles[$id] = $u;
			}
		}
	}
	
	function setstyles() {
		foreach($this->ustyles as $k=>$v) {
			$this->svg->addstyle(".$k $v");
		}
	}
	
	function write() {
		header('content-type: image/svg+xml; charset=utf8');
		echo $this->svg->asXML();
	}
};

?>
