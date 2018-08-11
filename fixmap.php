<?php
header('Content-type: text/plain; charset=utf-8');
function errcho($s) { fwrite(STDERR, "$s\n"); }
function errptr($s) { fwrite(STDERR, "$s"); }
function errdmp($s) { fwrite(STDERR, print_r($s,TRUE)); }
function err_dump($o) { ob_start(); var_dump($o); errptr(ob_get_clean()); }

$ini = parse_ini_file(isset($argv[1])? $argv[1]: "default.ini", true);
#print_r($ini);

$def_path = isset($ini['file']['path'])? $ini['file']['path']: '.';
$def_ipath = isset($ini['file']['input_path'])? $ini['file']['input_path']: $def_path;
$def_opath = isset($ini['file']['output_path'])? $ini['file']['output_path']: $def_path;
$def_reverse = isset($ini['file']['reverse'])? ($ini['file']['reverse']!=false): false;

$def_input = isset($argv[2])? $argv[2]: (
	isset($ini['file']['input_file'])? rtrim($def_ipath,'/').'/'.$ini['file']['input_file']: rtrim($def_ipath,'/').'/default.kml'
	);
$def_output = isset($argv[3])? $argv[3]: (
	isset($ini['file']['output_file'])? rtrim($def_opath,'/').'/'.$ini['file']['output_file']: rtrim($def_opath,'/').'/fixes.kml'
	);

errcho("Input: '$def_input'; Output: '$def_output'" );

$config = $ini['config'];
$aliases = isset($ini['alias'])? $ini['alias']: [];
$styles = isset($ini['style'])? $ini['style']: [];

function fix_sing(string $co) {
	if(empty($co)) return '';
	global $config;
	$lon = isset($config['lon'])? (float) $config['lon']: 0.0;
	$t = explode(',',$co);
	$t[1] = round($t[1],3); #echo "<!--".print_r($t,true)."-- “{$co}” -->\n";
	$x = round(1000*cos(deg2rad($t[1])));
	$l = round(($t[0]-$lon)*$x);
	$t[0] = round($l/$x+$lon,5);
	$t[2] = 0;
	return implode(',',$t);
}

function fix_coor(string $coo) {
	global $def_reverse;
	$r = preg_split('{\s+}', trim($coo) );
	if($def_reverse) $r = array_reverse($r);
	for($i=0;$i<count($r);++$i)
		$r[$i] = fix_sing($r[$i]);
	return $r;
}

function degminsec(float $d,int $pre=0) {
	$N = $d<0;
	if($N) $d=-$d;
	$D = (int)$d;
	$X = "{$D}°";
	if($d>$D && $pre>-2) {
		$m = 60*($d-$D);
		$M = (int)$m;
		$X= "{$X}{$M}'";
		if($m>$M && $pre>-1) {
			$s = 60*($m-$M);
			$X = sprintf("{$X}%.{$pre}f\"",$s);
		}
	}
	return [$X,$N];
}

function coor_name(string $co) {
	$co = fix_sing($co);
	$C = explode(',',$co);
	$lon = degminsec($C[0]);
	$lat = degminsec($C[1],1);
	#errdmp([$C,$lon,$lat]);
	return sprintf("%s%s %s%s", $lat[0], $lat[1]?'S':'N', $lon[0], $lon[1]?'W':'E');
}

function vector($coo, $idx=null) {
	if(empty($coo)) return '';
	global $config;
	$lon = isset($config['lon'])? (float) $config['lon']: 0.0;

	$c = explode(',',$coo);
	$th = deg2rad($c[0]-$lon);
	$ph = deg2rad($c[1]);
	return [sin($th)*cos($ph), sin($ph), cos($th)*cos($ph)];
	return [3600*sin($th)*cos($ph), 3600*sin($ph), 3600*cos($th)*cos($ph)];
}

function vprod($v1, $v2) {
	return [$v1[1]*$v2[2]-$v1[2]*$v2[1],$v1[2]*$v2[0]-$v1[0]*$v2[2],$v1[0]*$v2[1]-$v1[1]*$v2[0]];
}

function eprod($v1, $v2) {
	return $v1[0]*$v2[0]+$v1[1]*$v2[1]+$v1[2]*$v2[2];
}

function vadd($v1, $v2) {
	return [$v1[0]+$v2[0],$v1[1]+$v2[1],$v1[2]+$v2[2]];
}

function vdif($v1, $v2) {
	return [$v1[0]-$v2[0],$v1[1]-$v2[1],$v1[2]-$v2[2]];
}

function ptrvec($v) {
	return sprintf('[%.5f,%.5f,%.5f]',$v[0],$v[1],$v[2]);
}

function varea($varray) {
	$c = [0.0, 0.0, 0.0];
	#errdmp($varray);
	#errptr('varea: ');
	for($i=1;$i<count($varray);$i++) {
		#errptr(ptrvec($c).' + '.ptrvec($varray[$i-1]).'×'.ptrvec($varray[$i]).' -> ');
		$c = vadd($c, vprod($varray[$i-1],$varray[$i]));
	}
	#errptr(ptrvec($c).chr(10));
	return [$c[0]/2,$c[1]/2,$c[2]/2];
}

function latlong($v) {
	global $config;
	$lon = isset($config['lon'])? (float) $config['lon']: 0.0;
	$ln = atan2($v[0],$v[2]);
	$rn = sqrt($v[0]*$v[0]+$v[2]*$v[2]);
	$lt = atan2($v[1],$rn);
	#errcho(sprintf("(%.4f,%.4f,%.4f) %.4f, %.3f° %.3f°",$v[0],$v[1],$v[2],$rn,rad2deg($lt),rad2deg($ln)));
	return fix_sing(sprintf('%f,%f', rad2deg($ln)+$lon, rad2deg($lt) ));
}

function form($coo) {
	global $config;
	$lon = isset($config['lon'])? (float) $config['lon']: 0.0;

	$c=explode(',',$coo);
	$sln = sin(deg2rad($c[0]-$lon));
	$cln = cos(deg2rad($c[0]-$lon));
	$slt = sin(deg2rad($c[1]));
	$clt = cos(deg2rad($c[1]));
	return [[-$sln,$cln,0],[-$cln*$slt,-$sln*$slt,$clt]];
}

function acim($co1, $co2) {
	$M = form($co1);
	$V = vector($co2);
	$y = eprod($V,$M[0]);
	$z = eprod($V,$M[1]);
	return rad2deg( atan2($y, $z) );
}

define('ARG_NORMAL',0);
define('ARG_DIVZERO',1);
define('ARG_INLINE',2);
define('ARG_NOPOLY',3);
$arg_exep = ARG_NORMAL;
function argument($v0, $v1, $v2) {
	global $arg_exep;
	$X01 = vprod($v0,$v1);
	$X02 = vprod($v0,$v2);
	$num = vprod($X01,$X02);
	$de0 = eprod($v0,$v0);
	$de1 = eprod($X01,$X01); // if $de1=0, $v1 is scaled $v0
	$de2 = eprod($X02,$X02); // if $de2=0, $v2 is scaled $v0
	if($de0==0 || $de1==0 || $de2==0) {
		$arg_exep = ARG_DIVZERO;
		return false;
	}
	$sin = eprod($v0,$num)/sqrt($de0*$de1*$de2);
	$arg = asin($sin);
	$chk = eprod($X01,$X02); // if positive, argument in (-pi/2, pi/2)
	if($chk>=0) return $arg;
	#errcho(sprintf("[ %s %s %s ]: %.3f %.1f°|%.1f° %.3f", coor_name(latlong($v0)), latlong($v1), latlong($v2), $sin, rad2deg($arg), rad2deg(pi()-$arg), $chk));
	if($arg==0) {
		$arg_exep = ARG_INLINE;
		return pi();
	}
	return $arg>0? pi()-$arg: -pi()-$arg;
}

function line_arg($co0, $line, $DEBUG=false) {
	global $arg_exep;
	$v0 = vector($co0);
	$arg = 0;
	$v1 = vector($co1=array_shift($line));
	foreach($line as $coo) {
		#if($coo==$co1) continue;
		$v2 = vector($coo);
		#if($v1[0]==$v2[0] && $v1[1]==$v2[1] && $v1[2]==$v2[2]) continue;
		$a = argument($v0,$v1,$v2);
		if($DEBUG) errptr(sprintf('%.5f %s %.5f -> ', $arg, $a>=0?'+':'-', abs($a)));
		$arg+= $a;
		if($arg_exep) return false;
		$v1 = $v2;
	}
	if($DEBUG) errptr(sprintf("%.5f\n", $arg));
	return $arg;
}

function isinside($co0, $polyline, $DEBUG=false) {
	// If a point is inside a counter-clockwise defined polygon, the argument should be 2pi
	// If it is inside a clockwize defined polygon, the argument would be -2pi
	// Outside, the argument would be 0.
	// however, given the nature of spherical geometry if the antipod is inside the polygon
	// the results might be oposite. So if we assume that the polygons are always defined
	// counterclockwise, the result should be positive if inside.}
	// exceptions: if arg_exep is raised, it means the point (or antipod) is in the border.
	// I will return false, caller must verify arg_exep.
	// if $polyline is not closed, it will raise an exception (and return false);
	// then, I will return if the argument is positive enough (allowing any cummulative error).
	global $arg_exep;
	$n = count($polyline);
	if($DEBUG) errcho("DEBUGING $co0 {$polyline[0]}");
	if($polyline[0] != $polyline[$n-1]) {
		$arg_exep = ARG_NOPOLY;
		return false;
	}
	$arg = line_arg($co0, $polyline, $DEBUG);
	if($arg_exep) return false;
	#errdmp([$co0, $polyline[0], $n, $arg]);
	return $arg>1;
}

function norm($coo, $idx=null) {
	global $config;
	$lon = isset($config['lon'])? (float) $config['lon']: 0.0;

	$c = explode(',',$coo);
	$th = deg2rad($c[0]-$lon);
	$ph = deg2rad($c[1]);
	return [$th*6378, sin($ph)*6357, 0.0];
}

function narea($narray) {
	$v = varea($narray);
	return $v[2];
}

function line_insert($co1, $co2, $cyclic=false) {
	$m = count($co1);
	$n = count($co2);
	$ini = $co2[0];
	$end = $co2[$n-1];
	$ii = array_search($ini, $co1);
	$ie = array_search($end, $co1);
	if($cyclic) {
		if($ii===false || $ie===false)
			return false;
		if($ie==0) {
			if($co1[0] != $co1[$m-1])
				return false;
			$ie = $m-1;
		}
		if($ii<$ie) {
			$head = array_slice($co1, 0, $ii);
			$tail = array_slice($co1, $ie+1);
			return array_merge($head, $co2, $tail);
		}
		$mid = array_slice($co1,$ie+1,$ii-$ie);
		return array_merge($co2,$mid);
	} else {
		if($ii===false) {
			if($ie===0) {
				$tail = array_slice($co1,1);
				return array_merge($co2,$tail);
			} else
				return false;
		}
		if($ie===false) {
			if($ii==$m-1) {
				$tail = array_slice($co2,1);
				return array_merge($co1,$tail);
			} else
				return false;
		}
		if($ie<$ii)
			return false;
		$head = array_slice($co1, 0, $ii);
		$tail = array_slice($co1, $ie+1);
		return arra_merge($head, $co2, $tail);
	}
}

function line_append($co1, $co2) {
	$m = count($co1);
	$n = count($co2);
	$ini = $co2[0];
	$ii = array_search($ini, $co1);

	if($ii===false)
		return false;

	$head = array_slice($co1, 0, $ii);
	$tail = array_slice($co1, $ii+1);
	return array_merge($head, $co2, $tail);
}

function line_prepend($co1, $co2) {
	$m = count($co1);
	$n = count($co2);
	$end = $co2[$n-1];
	$ie = array_search($end, $co1);
	if($ie===0 && $co1[$m-1]==$end)
		$ie = $m-1;

	if($ie===false)
		return false;

	$head = array_slice($co1, 0, $ie);
	$tail = array_slice($co1, $ie+1);
	### if(count($co1)<20)
	return array_merge($head, $co2, $tail);
}

function str2key($str) {
	$n = preg_replace([
		'/à|á|â|ä|å|ã/','/æ/',
		'/è|é|ê|ë/',
		'/ì|í|î|ï/',
		'/ò|ó|ô|ö|õ/','/ø/',
		'/ù|ú|û|ü/',
		'/ç/',
		'/ñ/',
		'/\W+/',
		'/^\W+|\W+$/'
		],[
		'a','ae',
		'e','i','o','oe','u',
		'c','n',
		'-',''
		],mb_strtolower($str));
	return $n;
}

class map_item {
	function __construct(string $name) {
		$this->name = $name;
		$this->key = str2key($name);
	}
	
	function move($from,$to) { return false; }
	function reline($line) { return false; }
	function xml($node='Placemark') {
		return simplexml_load_string(isset($this->description)?
			"<$node><name>{$this->name}</name><description><![CDATA[{$this->description}]]></description></$node>":
			"<$node><name>{$this->name}</name></$node>");
	}
}

class folder extends map_item {
	function __construct($name) {
		map_item::__construct($name);
		$this->areas=[];
		$this->lines=[];
		$this->points=[];
	}
	
	function add($obj) {
		if(!is_object($obj)) return err_dump($obj);
		$n = trim($obj->name);
		if(is_a($obj,'map_area')) {
			if(isset($this->areas[$n]))
				$this->areas[$n]->add_area($obj);
			else
				$this->areas[$n] = $obj;
		} elseif(is_a($obj,'map_line')) {
			$this->lines[] = $obj;
		} elseif(is_a($obj,'map_point')) {
			$this->points[$n] = $obj;
		} elseif(is_a($obj,'map_poly')) {
			if(isset($this->areas[$n]))
				$this->areas[$n]->add_area($obj);
			else
				$this->areas[$n] = new map_area($obj);
		} else return false;
		return true;
	}

	function move($from,$to) {
		foreach($this->areas as $i=>$area)
			$this->areas[$i]->move($from,$to);

		foreach($this->lines as $i=>$line)
			$this->lines[$i]->move($from,$to);

		foreach($this->points as $i=>$point)
			$this->points[$i]->move($from,$to);
	}

	function reline($line) {
		foreach($this->areas as $i=>$area)
			$this->areas[$i]->reline($line);
	}
	
	function empty() {
		return empty($this->areas) && empty($this->lines) && empty($this->points);
	}
	
	function xml($node='Folder') {
		$xml = map_item::xml($node);
		$dom = dom_import_simplexml($xml);
		foreach($this->areas as $area) {
			$dom_a = dom_import_simplexml($area->xml());
			$dom_a = $dom->ownerDocument->importNode($dom_a, true);
			$dom->appendChild($dom_a);
		}
		foreach($this->lines as $line) {
			if(is_null($line)) continue;
			$dom_l = dom_import_simplexml($line->xml());
			$dom_l = $dom->ownerDocument->importNode($dom_l, true);
			$dom->appendChild($dom_l);
		}
		foreach($this->points as $point) {
			$dom_p = dom_import_simplexml($point->xml());
			$dom_p = $dom->ownerDocument->importNode($dom_p, true);
			$dom->appendChild($dom_p);
		}

		return $xml;
	}
}

class map_poly extends map_item {
	function __construct($pm_poly,$name) {
		map_item::__construct($name);
		if(is_array($pm_poly)) {
			$this->outer = $pm_poly;
			$this->inner = [];
		} else {
			$coo = (string) $pm_poly->outerBoundaryIs->LinearRing->coordinates;
			$this->outer = fix_coor($coo);
			$this->inner = [];
			foreach($pm_poly->innerBoundaryIs as $ib) {
				$coo = (string) $ib->LinearRing->coordinates;
				$this->inner[] = fix_coor($coo);
			}
		}
	}
	
	function area_vector() {
		#errdmp($this->outer);
		$vects = array_map('vector', $this->outer);
		#errdmp($vects);
		$area = $x = varea($vects);
		foreach($this->inner as $c) {
			$v = array_map('vector', $c);
			$y = varea($v);
			$f = eprod($x,$y) > 0.0;
			$area = $f? vdif($area, $y): vadd($area, $y);
		}
		return $area;
	}

	function area_norm() {
		$nn = array_map('norm', $this->outer);
		$area = $x = narea($nn);
		foreach($this->inner as $c) {
			$nn = array_map('norm', $c);
			$y = narea($nn);
			$area+= $y>0.0? -$y: $y;
		}
		return $area;
	}
	
	function mid_point() {
		return latlong($this->area_vector());
	}
	
	function move($from,$to) {
		if(($i = array_search($from,$this->outer))!==false)
			$this->outer[$i] = $to;
		foreach($this->inner as $ib) {
			if(($i = array_search($from,$ib))!==false)
				$ib[$i] = $to;
		}
	}
	
	static function __reline($ring, $line, $append=false) {
		return $append?
			line_append($ring, $line):
			line_insert($ring, $line, true);
	}

	function reline($line) {
		if(is_a($line,'map_line')) {
			$coo = $line->coord;
		} elseif(is_array($line)) {
			$coo = $line;
		} elseif(is_string($line)) {
			$coo = fix_coor($line);
		} else {
			return false;
		}
		if(isset($line->append))
			$f = line_append($this->outer, $coo);
		elseif(isset($line->prepend))
			$f = line_prepend($this->outer, $coo);
		else
			$f = line_insert($this->outer, $coo, true);
		if(!empty($f))
			return $this->outer = $f;

		foreach($this->inner as $i=>$ib) {
			if(isset($line->append))
				$f = line_append($ib, $coo);
			elseif(isset($line->prepend))
				$f = line_prepend($ib, $coo);
			else
				$f = line_insert($ib, $coo, true);
			if(empty($f)) continue;
			return $this->inner[$i] = $f;
		}
		return false;
	}
	
	static function __subline($ring, $ini, $end) {
		$ii = array_search($ini, $ring);
		$ie = array_search($end, $ring);
		if($ii===false || $ie===false) return false;
		if($ii<$ie)
			return array_slice($ring, $ii, $ie-$ii+1);
		else {
			$tail = array_slice($ring, 1, $ie);
			$head = array_slice($ring, $ii);
			return array_merge($head, $tail);
		}
	}
	
	function subline($ini, $end) {
		$r = map_poly::__subline($this->outer, $ini, $end);
		if(!empty($r)) return $r;
		foreach($this->inner as $ib) {
			$r = map_poly::__subline($ib, $ini, $end);
			if(!empty($r)) return $r;
		}
		return false;
	}

	function has_point($co) {
		return array_search($co,$this->outer)!==false;
	}

	function has_inner($co) {
		foreach($this->inner as $i=>$ib) {
			if(array_search($co,$ib)!==false)
				return $i;
		}
		return false;
	}

	function delete_inner($idx) {
		unset($this->inner[$idx]);
	}

	function xml($node='Polygon') {
		if(empty($this->outer))
			return null;
		$xml = simplexml_load_string("<$node/>");
		$dom = dom_import_simplexml($xml);
		$ob = simplexml_load_string("<outerBoundaryIs><LinearRing></LinearRing></outerBoundaryIs>");
		$ob->LinearRing->coordinates = implode(' ',$this->outer);
		$obdom = dom_import_simplexml($ob);
		$obdom  = $dom->ownerDocument->importNode($obdom, true);
		$dom->appendChild($obdom);

		foreach($this->inner as $ib) {
			$ibxml = simplexml_load_string("<innerBoundaryIs><LinearRing></LinearRing></innerBoundaryIs>");
			$ibxml->LinearRing->coordinates = implode(' ',$ib);
			$ibdom = dom_import_simplexml($ibxml);
			$ibdom  = $dom->ownerDocument->importNode($ibdom, true);
			$dom->appendChild($ibdom);
		}
		return $xml;
	}
}

class map_area extends map_item {
	function __construct($placemark) {
		map_item::__construct($name = trim($placemark->name));

		$this->polys = [];
		if(is_a($placemark, 'map_poly')) {
			$this->polys[] = $placemark;
		} elseif(isset($placemark->MultiGeometry)) {
			foreach($placemark->MultiGeometry->Polygon as $poly)
				$this->polys[] = new map_poly($poly,$name);
		} elseif (isset($placemark->Polygon)) {
			$this->polys[] = new map_poly($placemark->Polygon,$name);
		} else {
		}
	}
	
	function add_area($placemark) {
		$name = trim($this->name);
		if(is_a($placemark, 'map_poly')) {
			$this->polys[] = $placemark;
		} elseif(isset($placemark->MultiGeometry)) {
			foreach($placemark->MultiGeometry->Polygon as $poly)
				$this->polys[] = new map_poly($poly,$name);
		} elseif (isset($placemark->Polygon)) {
			$this->polys[] = new map_poly($placemark->Polygon,$name);
		} else {
		}
	}
	
	function add_hole($poly) {
		$n = empty($poly->sub)? 0: $poly->sub-1;
		$o = $this->find_greater($n);
		$this->polys[$o]->inner[] = $poly->outer;
		#errdmp([$this]);
	}
	
	function area_vector() {
		$c = [0.0, 0.0, 0.0];
		foreach($this->polys as $p) {
			$c = vadd($c, $p->area_vector());
		}
		return $c;
	}

	function area_norm() {
		$a = 0.0;
		foreach($this->polys as $p) {
			$a+= $p->area_norm();
		}
		return $a;
	}

	function mid_point() {
		return latlong($this->area_vector());
	}
	
	function move($from,$to) {
		foreach($this->polys as $i=>$poly) {
			$this->polys[$i]->move($from,$to);
		}
	}

	function reline($line) {
		if(isset($line->delete))
			return $this->delete($line);
		$I = count($this->polys);
		foreach($this->polys as $i=>$poly) {
			$n = count($line->coord);
			$a = $line->coord[0];
			$b = $line->coord[$n-1];
			#$ic = count($poly->inner);
			#errcho("$i: {$poly->outer[0]} + ".count($poly->outer).". ");
			#foreach($poly->inner as $in)
			#	errcho("---- {$in[0]} + ".count($in).". ");
			$f = $this->polys[$i]->reline($line);
			if($f!==false) return $f;
		}
		#errdmp($this->polys[10]);
		#errdmp($this->polys[0]->outer);
		return false;
	}
	
	function delete($line) {
		if(is_array($line)) $co = $line[0];
		elseif(is_string($line)) $co = $line;
		elseif(is_a($line,'map_line')) $co = $line->coord[0];
		foreach($this->polys as $i=>$poly) {
			if($poly->has_point($co)) {
				unset($this->polys[$i]);
				return true;
			}
			if(($j=$poly->has_inner($co))!==false) {
				$poly->delete_inner($j);
				return true;
			}
		}
		return false;
	}
	
	function trans($data) {
	}
	
	function subline($ini, $end) {
		foreach($this->polys as $i=>$poly) {
			$r = $poly->subline($ini, $end);
			if(!empty($r)) {
				$this->idx = $i;
				return $r;
			}
		}
		return false;
	}
	
	function find_poly($ini, $end, $inner=false) {
		foreach($this->polys as $poly) {
			if($poly->has_point($ini) and $poly->has_point($end))
				return $poly;
			if($inner) {
				$a = $poly->has_inner($ini);
				$b = $poly->has_inner($end);
				if($a!==false && $a==$b) {
					return new map_poly($poly->inner[$a],$poly->name);
				}
			}
		}
		return false;
	}
	
	function find_greater($ord=0) {
		$l = [];
		foreach($this->polys as $i=>$poly)
			$l[sprintf('x%03d',$i)] = count($poly->outer);
		arsort($l);
		if($ord>=count($l)) return false;
		$k = array_keys($l);
		#errdmp([$ord,$l,$k]);
		return (int) substr($k[$ord],1);
	}

	function xml($node='Placemark') {
		global $aliases, $styles;
		$xml = map_item::xml();
		$l = $this->key;
		if(isset($aliases[$l])) $l=$aliases[$l];
		$style = "area-$l";
		if(!isset($styles[$style])) $styles[$style] = '';
		$xml->styleUrl = "#$style";
		if(count($this->polys)>1) {
			$xml->addChild('MultiGeometry');
			$dom = dom_import_simplexml($xml->MultiGeometry);
		} else {
			$dom = dom_import_simplexml($xml);
		}
		foreach($this->polys as $poly) {
			$polydom = dom_import_simplexml($poly->xml());
			$polydom  = $dom->ownerDocument->importNode($polydom, true);
			$dom->appendChild($polydom);
		}
		return $xml;
	}
}

class map_line extends map_item {
	function __construct($mixed, string $name, $reversed = false) {
		map_item::__construct($name);
		if(is_array($mixed)) {
			$coord = $mixed;
		} elseif(is_string($mixed)) {
			$coord = fix_coor($mixed);
		} elseif(is_a($mixed,'map_line')) {
			$coord = $mixed->coord;
		} elseif(isset($mixed->coordinates)) {
			$coord = fix_coor($mixed->coordinates);
		} elseif(isset($mixed->LineString)) {
			$coord = fix_coor($mixed->LineString->coordinates);
		} else {
			$coord = [];
		}
		$this->coord = $reversed? array_reverse($coord): $coord;
	}
	
	function reverse($name=null) {
		if(is_null($name)) {
			$this->coord = array_reverse($this->coord);
			return $this;
		}
		return new map_line($this->coord, $name, true);
	}

	function move($from,$to) {
		if(($i = array_search($from,$this->coord))!==false)
			$this->coord[$i] = $to;
	}

	function reline($line) {
		if(is_a($line,'map_line')) {
			$coo = $line->coord;
		} elseif(is_array($line)) {
			$coo = $line;
		} elseif(is_string($line)) {
			$coo = fix_coor($line);
		} else {
			return false;
		}
		
		$f = line_insert($this->coord, $coo, true);
		if(!empty($f)) {
			errcho("Successfully relined {$this->name}.");
			$this->coord = $f;
		} else
			errcho("Unable to reline {$this->name}.");
		return $f;
	}

	function xml($node='Placemark') {
		$xml = map_item::xml($node);
		$xml->styleUrl = "#line-default";
		$xml->addChild('LineString');
		$xml->LineString->coordinates = implode(' ',$this->coord);
		return $xml;
	}
}

class map_point extends map_item {
	function __construct($pm_point, string $name) {
		map_item::__construct($name);
		$coo = is_string($pm_point)? $pm_point: (string) $pm_point->coordinates;
		$this->coord = fix_sing($coo);
	}

	function move($from,$to) {
		if($this->coord == $from)
			return $this->coord = $to;
		return false;
	}

	function xml($node='Placemark') {
		$xml = map_item::xml($node);
		$xml->styleUrl = "#point-default";
		$xml->addChild('Point');
		$xml->Point->coordinates = $this->coord;
		return $xml;
	}
}

class map_data extends map_item {

	function __construct($kml, $specials=null) {
		$process = isset($specials['process'])? $specials['process']: 'process';
		$points = isset($specials['points'])? $specials['points']: 'points';
		$trash = isset($specials['trash'])? $specials['trash']: 'trash';
		$ignore = isset($specials['ignore'])? $specials['ignore']: [];

		map_item::__construct(trim($kml->Document->name));
		$this->folders = [];
		$this->process = $process;
		$this->points = $points;
		$this->trash = $trash;
		$this->ignore = $ignore;
		
		$i=0;
		foreach ( $kml->Document->Folder as $folder ) {
			$f = new folder($m=trim((string)$folder->name));
			$areas = [];
			$lines = [];
			$points = [];
			foreach($folder->Placemark as $pm) {
				$n = trim((string) $pm->name);

				if(isset($pm->Point)) {
					$points[$n] = new map_point($pm->Point, $n);
				} elseif(isset($pm->LineString)) {
					$lines[] = new map_line($pm->LineString, $n);
				} elseif(isset($areas[$n])) {
					$areas[$n]->add_area($pm);
				} else {
					$areas[$n] = new map_area($pm);
				}
			}

			if(!empty($areas)) {
				ksort($areas);
				$f->areas = $areas;
			}
			if(!empty($lines)) {
				#ksort($lines);
				$f->lines = $lines;
			}
			if(!empty($points)) {
				ksort($points);
				$f->points = $points;
			}
			$this->folders[$m] = $f;
		}
			
		#ksort($pms['Cities']);
	}
	
	function transform() {
		$p = $this->process;
		if(isset($this->folders[$p])) {
			$lines = [];
			$moves = [];
			$holes = [];
			$adds = [];
			$data = $this->folders[$p]->lines;
			foreach($data as $action) {
				$n = trim($action->name);
				if(substr($n,0,4)=='MOVE') {
					$moves[] = $action->coord;
				}
				elseif(preg_match('/^X([S])\s*[:-]\s+([^-]+?)(?:\s+-\s+([^:]*\:)?([^-]+?))?\s*$/', $n, $m)) {
					$from = $m[2];
					$len = count($rc=$action->coord);
					$ini = $rc[0];
					$end = $rc[$len-1];
					$cr = array_reverse($rc);
					$fold = empty($m[3])? '': trim($m[3],':');
					$to = empty($m[4])? $from: trim($m[4]);

					$count = 0;
					foreach($this->folders as $fn=>$f) {
						if(isset($f->areas[$from])) {
							if($line = $f->areas[$m[2]]->subline($end, $ini)) {
								if($to==$from) errcho("Splitting $from from $ini to $end at $fn");
								elseif($fold) errcho("Splitting $fold:$to off $fn:$from, from $ini to $end");
								else errcho("Splitting $to off $from, from $ini to $end at $fn");
								$count++;
								$i = $f->areas[$from]->idx;
								$p1 = new map_poly(array_merge($line, array_slice($rc,1)), $to);
								$l2 = $f->areas[$from]->subline($ini, $end);
								$ob = array_merge($l2, array_slice($cr,1));
								$f->areas[$from]->polys[$i]->outer = $ob;
								if($fold) $this->folders[$fold]->add($p1);
								else $f->add($p1);
								break;
							}
						}
					}
					if(!$count) {
						$this->send_trash( $action );
						errcho("'$n': could not split $from from $ini to $end.");
						errdmp($m);
					}
				}
				elseif(preg_match('/^X([DQR])\s*[:-]\s+([^-]+?)\s*$/', $n, $m)) {
					// XD - Object ==> deletes Object
					// XQ - Object ==> adds line into Object
					// XR - Object ==> adds line (reverse) into Object
					$x = new map_line($action->coord, $m[2], $m[1]=='R');
					if($m[1]=='D') $x->delete = true; else $x->reverse = true;
					$lines[] = $x;
				}
				elseif(preg_match('/^X([DILMNPT])\s*[:-]\s+([^-]+?)\s+-\s+([^:]*\:)?([^][-]+?)(\[\d+\])?\s*$/', $n, $m)) {
					// XD - Object1 - [Folder:]Object2 ==> deletes Object1 and Object2
					// XI - Origin - [Folder:]Target ==> drill Origin into Target, deletes Origin
					// XL - Origin - [Folder:]Target ==> drill Origin into Target, preserves Origin
					// XM - Origin - [Folder:]Target ==> reshape Target from Origin, keeps Origin
					// XN - Origin - [Folder:]Target ==> reshape Target from Origin, deletes Origin
					// XP - Origin - [Folder:]Target ==> copy Origin into Target
					// XT - Origin - [Folder:]Target ==> move Origin as Target (rename)
					$from = $m[2];
					$to = $m[4];
					$len = count($action->coord);
					$Ini = $action->coord[0];
					$End = $action->coord[$len-1];
					$ini = in_array($m[1],['M','N'])? $action->coord[1]: $Ini;
					$end = in_array($m[1],['M','N'])? $action->coord[$len-2]: $End;
					$fold = trim($m[3],':');
					if($fold && !isset($this->folders[$fold]))
						errcho("'$n': Folder '$fold' does not exist.");
					
					$count=0;
					foreach($this->folders as $f) {
						$F = isset($this->folders[$fold])? $this->folders[$fold]: $f;
						if(isset($f->areas[$from])) {
							if(($poly = $f->areas[$from]->find_poly($ini,$end,$m[1]=='L'))!==false) {
								#if($ini!=$poly->outer[0]) errcho("{$from}->{$to}: {$ini} vs {$poly->outer[0]}");
								$count++;
								$poly->name = $to;
								switch($m[1]) {
									case 'I': // drill and erase source
									case 'L': // drill and keep source
									$poly->sub = isset($m[5])? (int)($m[5]): 0;
									$poly->from = $from;
									$holes[] = $poly;
									break;
									case 'M': // reshape target from source, keep source
									case 'N': // reshape target from source, remove source
									case 'P': // copy source into target
									case 'T': // translate source to target
									$F->add($poly);
									break;
								}
								break;
							}
						}
					}
					if(!$count)
						errcho("'$n': no $from found ($ini ; $end).");
					// Delete original source
					if(in_array($m[1],['D','I','N','T'])) {
						$x = new map_line([$ini,$end], $from, true);
						$x->delete = true;
						$x->bounce = false;
						$lines[] = $x;
					}
					// Delete original target
					if(in_array($m[1],['D','M','N'])) {
						$y = new map_line([$Ini,$End], $to, true);
						$y->delete = true;
						$y->bounce = true;
						$lines[] = $y;
					}
				}
				elseif(preg_match('/^X(-?[QR])\s*[:-]\s+([^-]+?)\s+-\s+([^-]+?)\s*$/', $n, $m)) {
					// XQ - Object1 - Object2 ==> insert line into Object1 and Object2
					// XR - Object1 - Object2 ==> insert line (reverse) into Object1 and Object2
					$x = new map_line($action->coord, $m[2], $m[1]=='R');
					$lines[] = $x;
					$y = new map_line($action->coord, $m[3], $m[1]=='R');
					$lines[] = $y;
				}
				elseif(preg_match('/^X(-?[ABCHVWXYZ])\s*[:-]\s+([^-]+?)\s+-\s+([^-]+?)\s*$/', $n, $m)) {
					// XA - Source - Target ==> Annex Source into Target
					// XB - ??? - ??? ==>
					// XC - Source - Target ==> reshape Target from Source
					// XH - Source - Target ==> reshape Target from reverse Source
					// XV - ??? - ??? ==>
					// XW - Source - Target ==> conform boundary of Target to Source
					// XX - Source - Target ==> extend boundary of Target as Source (from point)
					// XY - ??? - ??? ==>
					// XZ - Source - Target ==> extend boundary of Target as Source (into point)
					if(substr($m[1],0,1)=='-') {
						$act = array_reverse($action->coord);
						$m[1] = substr($m[1],1);
					} else
						$act = $action->coord;
					$from = $m[2];
					$to = $m[3];
					$len = count($act);
					$p0 = $act[0];
					$p1 = $act[1];
					$q0 = $act[$len-2];
					$q1 = $act[$len-1];
					$rev = in_array($m[1],['H','W']);
					$IN = array_combine(['C','H','W','Z'],[$p1,$q0,$q1,$q0]);
					$ini = isset($IN[$m[1]])? $IN[$m[1]]: $p0;
					#errdmp([$n,$IN,$ini]);
					$EN = array_combine(['C','H','V','W','X'],[$q0,$p1,$p1,$p0,$p1]);
					$end = isset($EN[$m[1]])? $EN[$m[1]]: $q1;
					#if($m[1]=='W') errdmp(['$m'=>$m,'$IN'=>$IN,'$EN'=>$EN,'$act'=>$act,[$ini,$end,$rev]]);
					foreach($this->folders as $f) {
						if(!isset($f->areas[$from])) continue;
						if($line = $f->areas[$from]->subline($ini, $end, $rev))
							break;
					}
					if($line) {
						$x = new map_line($line, $to, $rev);
						switch($m[1]) {
						case 'X':
							if($len>2)
								$x->coord = array_merge( $x->coord, array_splice($act,2) );
							$x->append = $from;
							break;
						case 'Z':
							if($len>2)
								$x->coord = array_merge( array_splice($act,0,$len-2), $x->coord );
							$x->prepend = $from;
							break;
						case 'V':
							$line2 = $f->areas[$from]->subline($q0, $q1);
							if(!$line2) {
								errcho( "'$n': No end $from found ($q0; $q1)." );
								$line2 = [$q0,$q1];
							}
							if($len>4)
								$x->coord = array_merge( $x->coord, array_splice($act,2,$len-4), $line2 );
							else
								$x->coord = array_merge( $x->coord, $line2 );
							$x->insert = $from;
							break;
						case 'C':
						case 'H':
							array_unshift($x->coord, $p0);
							array_push($x->coord, $q1);
						default:
							$x->insert = $from;
						}
						$lines[] = $x;

						if($m[1]=='A') {
							$x = new map_line([$ini,$end], $from);
							$x->delete = true;
							$x->XA = true;
							$lines[] = $x;
						}
					}
					else {
						$this->send_trash( $action );
						errcho( "'$n': No $from found ($ini; $end)." );
					}
				}
				elseif(preg_match('/^X([Y])\s*[:-]\s+([^-]+?)\s+-\s+([^-]+)\s+-\s*([^-]+?)\s*$/', $n, $m)) {
					$from = $m[2];
					$to1 = $m[3];
					$to2 = $m[4];
					$ini = $action->coord[0];
					$end = $action->coord[1];
					foreach($this->folders as $f)
						if(isset($f->areas[$from]))
							if($line = $f->areas[$from]->subline($ini, $end)) {
								$x = new map_line($line, $to1);
								$x->insert = $from;
								$lines[] = $x;
								$y = new map_line($line, $to2, true);
								$y->insert = $from;
								$lines[] = $y;
								break;
							}
				}
				elseif(preg_match('/^X\*([D])\s*[:-]\s+([^-]+?)\s*$/', $n, $m)) {
					$from = $m[2];
					foreach($action->coord as $cc) {
						$x = new map_line([$cc,$cc], $from);
						$x->delete = true;
						$lines[] = $x;
					}
				}
				elseif(preg_match('/^X\*([DILMNPT])\s*[:-]\s+([^-]+?)\s+-\s+([^:]*\:)?([^-]+?)(\[\d+\])?\s*$/', $n, $m)) {
					$from = $m[2];
					$to = $m[4];
					$fold = trim($m[3],':');
					if($fold && !isset($this->folders[$fold])) {
						errcho("'$n': Folder '$fold' does not exist.");
						$fold = '';
					}
					$sub = isset($m[5])? (int)($m[5]): 0;

					$record=[];
					foreach($this->folders as $f) {
						if(!isset($f->areas[$from])) continue;
						$F = $fold? $this->folders[$fold]: $f;

						for($i=0; $i<count($action->coord); ) {
							$ct = in_array($m[1],['M','N'])? $action->coord[$i++]: False;
							$cf = $action->coord[$i++];
							if(!isset($record[$cf])) $record[$cf]=0;
							#if(!$ct) $ct = $cf;
							#else errcho("... X*{$m[1]} $from($cf) -> $to($ct)");

							if($poly=$f->areas[$from]->find_poly($cf, $cf, in_array($m[1],['I','L']))) {
								$record[$cf]++;
								$poly->name = $to;
								// copy shape into target
								if(in_array($m[1],['M','N','P','T'])) {
									#errdmp([$n,$F->name,$m]);
									$F->add($poly);
								}
								// drill into target
								if(in_array($m[1],['I','L'])) {
									$poly->sub = $sub;
									$poly->from = $from;
									$holes[] = $poly;
								}
								// clear original sources
								if(in_array($m[1],['D','I','N','T'])) {
									$x = new map_line([$cf,$cf], $from, true);
									$x->delete = true;
									$x->bounce = false;
									$lines[] = $x;
								}
								// clear original targets
								if(in_array($m[1],['D','M','N'])) {
									if(!$ct) $ct = $cf;
									$y = new map_line([$ct,$ct], $to, true);
									$y->delete = true;
									$y->bounce = true;
									$lines[] = $y;
								}
							}
						}
					}
					foreach($record as $cc=>$count)
						if($count==0)
							errcho("'$n': No shape for $from found at $cc.");
				}
				elseif(preg_match('/^\s*([^-]+?)\s+-\s+(\w.+?)\s*$/', $n, $m)) {
					$x = new map_line($action->coord, $m[1]); $x->direct = true; $lines[] = $x;
					$y = new map_line($action->coord, $m[2], true); $y->reverse = true; $lines[] = $y;
				}
				else {
					$action->direct = true;
					$lines[] = $action;
				}
			}
			$data = $this->folders[$p]->areas;
			#errdmp($data);
			foreach($data as $name=>$action) {
				if(preg_match('/^X([D])\s*[:-]\s+([^-]+?)\s*$/', $name, $m)) {
					foreach($action->polys as $poly) {
						$poly->outer = array_reverse($poly->outer);
						$poly->name = $m[2];
						$poly->sub = 1;
						#errdmp([$name,$poly]);
						$holes[] = $poly;
					}
				}
				elseif(preg_match('/^X([D])\s*[:-]\s+([^-]+?)\s*[:-]\s+(\d+)\s*$/', $name, $m)) {
					foreach($action->polys as $poly) {
						$poly->outer = array_reverse($poly->outer);
						$poly->name = $m[2];
						$poly->sub = (int)$m[3];
						#errdmp([$name,$poly]);
						$holes[] = $poly;
					}
				}
				elseif(preg_match('/^X([DLQ])\s*[:-]\s+([^-]+?[^-]+?)\s*[:-]\s+([^-]+?)\s*$/', $name, $m)) {
					foreach($action->polys as $poly) {
						$pa = new map_poly($poly->outer, $m[2]);
						if($m[1]=='D') $holes[] = $pa;
						else $adds[] = $pa;
						
						$ps = new map_poly($poly->outer, $m[3]);
						if($m[1]=='Q') $adds[] = $ps;
						else $holes[] = $ps;
					}
				}
				elseif(preg_match('/^\s*([^-]+?)\s*$/', $name, $m)) {
					foreach($action->polys as $poly) {
						$poly->name = $m[1];
						$adds[] = $poly;
					}
				}
			}
		}
		
		#$lines = array_slice($lines,0,150);
		
		$MP = $this->folders[$this->points] = new folder($this->points);
		
		$founds = [];
		foreach($this->folders as $fn=>$folder) {
			errcho("FOLDER: $fn");
			if($fn == $p) continue;
			if($fn == $this->points) continue;
			if($fn == $this->trash) continue;
			if(isset($moves)) foreach($moves as $move) {
				$from = $move[0];
				$to = $move[1];
				$this->folders[$fn]->move($from,$to);
			}
			if(isset($holes)) foreach($holes as $hole) {
				$an = trim($hole->name);
				if(!isset($founds[$an])) $founds[$an] = 0;
				$from = isset($hole->from)? "from {$hole->from} ": "";
				$len = count($hole->outer);
				$ini = $hole->outer[0];
				$ctrl = "$len points from $ini";
				if(isset($folder->areas[$an])) {
					++$founds[$an];
					$folder->areas[$an]->add_hole($hole);
					errcho("       Drilling into $an $from($ctrl).");
				}
			}
			if(isset($adds)) foreach($adds as $poly) {
				$an = trim($poly->name);
				if(!isset($founds[$an])) $founds[$an] = 0;
				if(isset($folder->areas[$an])) {
					++$founds[$an];
					$folder->areas[$an]->add_area($poly);
				}
			}
			if(isset($lines)) foreach($lines as $line) {
				$an = trim($line->name);
				if(!isset($founds[$an])) $founds[$an] = 0;

				$len = count($line->coord);
				$ini = $line->coord[0]; $end = $line->coord[$len-1];
				$verb = isset($line->insert)? 'Inserting into': (
					isset($line->append)? 'Appending into': (
					isset($line->preppend)? 'Preppending into': (
					isset($line->delete)? 'Deletting at': 'Reshaping' )));
				$from = isset($line->insert)? "from {$line->insert} ": (
					isset($line->append)? "from {$line->append} ": (
					isset($line->preppend)? "from {$line->prepend} ": ''));
				$ctrl = isset($line->delete)? "control $ini and $end":
					"$len points, from $ini to $end";
				$shape = isset($line->reverse)? "-$an": $an;
				if(isset($folder->areas[$an])) {
					++$founds[$an];
					if( $folder->areas[$an]->reline($line) === false )
						errcho("Error: $verb $shape $from($ctrl)");
					else
						errcho("       $verb $shape $from($ctrl)");
				}
			}
			foreach($folder->lines as $i=>$line) {
				$an = trim($line->name);
				if(!isset($founds[$an])) $founds[$an] = 0;
				if(isset($folder->areas[$an])) {
					++$founds[$an];
					if(empty($folder->areas[$an]->reline($line)))
						$this->send_trash($line,$fn);
					else
						$folder->lines[$i] = null;
				} else {
					$this->send_trash($line,$fn);
				}
			}
			
			foreach($folder->areas as $i=>$area) {
				$c = $area->mid_point();
				$d = $area->area_norm();
				$this->folders[$this->points]->points[$i] = new map_point($c, $i);
				$this->folders[$this->points]->points[$i]->description = "Area: $d";
			}
		}
		foreach($founds as $name=>$count)
			if(!$count)
				errcho("Area '$name' not found.");
	}
	
	function send_trash($obj,$folder=null) {
		$trash = $this->trash;
		if($folder) {
			$name = trim($obj->name);
		}
		if(!isset($this->folders[$trash]))
			$this->folders[$trash] = new folder($trash);
		$this->folders[$trash]->add($obj);
	}
	
	function stats($starr) {
		$folder = isset($starr['folder'])? $starr['folder']: 'stats';
		if(isset($this->folders[$folder]))
			unset($this->folders[$folder]);
		$F = new folder($folder);
		if(isset($starr['largest'])) {
			$L = (int)$starr['largest'];
			foreach($this->folders as $fn=>$f) {
				foreach($f->areas as $an=>$area) {
					foreach($area->polys as $i=>$poly) {
						$n = count($ob=($poly->outer));
						if($n>=$L) {
							$pn="{$fn}:{$an}[{$i}]";
							$m = count($poly->inner);
							$points = [$ob[0],$ob[$n/4],$ob[$n/2],$ob[3*$n/4],$ob[$n-1]];
							$P = new map_poly($points, $m?"$pn ($n; $m)":"$pn ($n)");
							$A = new map_area($P);
							$A->key = $area->key;
							$F->areas[] = $A;
							errcho("STAT: $pn, $n points, $m holes");
						}
					}
				}
			}
		}
		if(isset($starr['inside'])) {
			global $arg_exep;
			$points = fix_coor($starr['inside']);
			foreach($points as $coor) {
				$name = coor_name($coor);
				#errdmp([$coor,$name]);
				$F->points[] = new map_point($coor, $name);
				foreach($this->folders as $fn=>$f) {
					foreach($f->areas as $an=>$area) {
						foreach($area->polys as $i=>$poly) {
							if(isinside($coor,$ob=$poly->outer/*,$poly->outer[0]=='102.422,0.801,0'*/)) {
								foreach($poly->inner as $ib)
									if(isinside($coor,$ib)) continue 2;
								$P = new map_poly($ob, "$an @ $fn ($name)");
								$A = new map_area($P);
								$A->key = $area->key;
								$F->areas[] = $A;
								errcho("STAT: $name inside $an @ $fn");
							}
							elseif($arg_exep) {
								errcho("STAT: $name argument exception for $an @ $fn");
								$arg_exep = ARG_NORMAL;
							}
						}
					}
				}
			}
		}
		$this->folders[$folder] = $F;
	}
	
	function xml($node='Document', $folders=False) {
		$xml = simplexml_load_string("<kml xmlns='http://www.opengis.net/kml/2.2'><Document><name>{$this->name}</name></Document></kml>");
		errdmp($folders);

		$doc = dom_import_simplexml($xml->Document);
		if($folders) {
			foreach($folders as $fn) {
				$folder = $this->folders[$fn];
				
				$fold = dom_import_simplexml($folder->xml());
				$fold = $doc->ownerDocument->importNode($fold, true);
				$doc->appendChild($fold);
			}
		}
		else {
			foreach($this->folders as $fn=>$folder) {
				if(in_array($fn,$this->ignore)) continue;
				if($fn==$this->process) continue;
				if($folder->empty()) continue;
				
				$fold = dom_import_simplexml($folder->xml());
				$fold = $doc->ownerDocument->importNode($fold, true);
				$doc->appendChild($fold);
			}
		}
		
		global $styles;
		ksort($styles);
		$c = 0;
		foreach($styles as $l=>$rgb)
			if(empty($rgb)) ++$c;
			
		$i = 0;
		foreach($styles as $l=>$rgb) {
			if(empty($rgb)) {
				$x = 6.0*$i/$c;
				switch($d = (int)$x) {
				case 1: $rgb = sprintf('%02x%02x%02x', 255, round(255*($x-$d)), 0); break;
				case 2: $rgb = sprintf('%02x%02x%02x', round(255*(1+$d-$x)), 255, 0); break;
				case 3: $rgb = sprintf('%02x%02x%02x', 0, 255, round(255*($x-$d))); break;
				case 4: $rgb = sprintf('%02x%02x%02x', 0, round(255*(1+$d-$x)), 255); break;
				case 5: $rgb = sprintf('%02x%02x%02x', round(255*($x-$d)), 0, 255); break;
				case 0: $rgb = sprintf('%02x%02x%02x', 255, 0, round(255*(1+$d-$x))); break;
				}
				++$i;
			}
			add_style($xml->Document, $l, $rgb);
		}

		return $xml;
	}
}

function add_style($doc, $label, $rgb) {
	$bgr = substr($rgb,4,2).substr($rgb,2,2).substr($rgb,0,2);
	$nl = $doc->addChild('Style');
	$nl['id'] = "$label-normal";
	$nl->LineStyle->color = "99$bgr";
	$nl->PolyStyle->color = "7f$bgr";
	
	$hl = $doc->addChild('Style');
	$hl['id'] = "$label-highlight";
	$hl->LineStyle->color = "cc$bgr";
	$hl->PolyStyle->color = "bf$bgr";
	
	$sm = $doc->addChild('StyleMap');
	$sm['id'] = "$label";
	
	$p = $sm->addChild('Pair');
	$p->key = 'normal';
	$p->styleUrl = "#$label-normal";

	$p = $sm->addChild('Pair');
	$p->key = 'highlight';
	$p->styleUrl = "#$label-highlight";

	return $sm;
}


$file = empty($_GET['file'])? $def_input: $_GET['file'];
$kml = simplexml_load_file($file);

$data = new map_data($kml, isset($ini['special'])? $ini['special']: null);
$data->transform();
if(isset($ini['stats'])) $data->stats($ini['stats']);
if(isset($ini[$def_output]['folders']))
	$xml = $data->xml('Document',explode(',', $ini[$def_output]['folders']));
else
	$xml = $data->xml();

$dom = dom_import_simplexml($xml)->ownerDocument;
$dom->formatOutput = true;
$xml_text = $dom->saveXML($dom->documentElement);
$xml_text = preg_replace('/  /',"\t",$xml_text);
file_put_contents($def_output, "<?xml version='1.0' encoding='UTF-8'?>\n".$xml_text);

header('Content-type: application/vnd.google-earth.kml+xml; charset=utf-8');
header('Content-type: text/xml; charset=utf-8');
echo "<?xml version='1.0' encoding='UTF-8'?>\n";
echo $xml_text;

?>
