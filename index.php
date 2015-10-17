<!DOCTYPE html>
<html>
<head>
  <meta charset=utf-8>
  <title>Proyecciones</title>
</head>

<body>
  <form>
    <label for=seletor>Proyección</label>
    <select id=selector onchange="selectproj()">
      <option>Seleccione una opción</option>
      <option value=flat>Plana</option>
      <option value=laea>Azimutal de Lambert Equiárea</option>
      <option value=laed>Equidistante</option>
    </select>
  </form>
  
  <form class=form id=laea style="display:none" action="lambaz-ea.php" method=get>
    <table>
      <tr>
        <td><label for=coord1>Ciudad</label></td>
        <td><select id=coord1 onchange="setcoord(1)">
          <option value="">Seleccione una ciudad</option><?php
			$fc=file_get_contents('coords.csv');
			$l = explode(chr(10),$fc);
			foreach($l as $line) {
				if(trim($line)=='') continue;
				$c = explode(',',$line);
				echo sprintf('<option value="%.2f;%.2f">%s</option>',$c[0],$c[1],$c[2]);
			}
?>
        </select></td>
      </tr>
      <tr>
        <td><label for=lon1>Longitud</label></td>
        <td><input id=lon1 name=lon type=text placeholder="grados; este positivo, oeste negativo"></td>
      </tr>
      <tr>
        <td><label for=lat1>Latitud</label></td>
        <td><input id=lat1 name=lat type=text placeholder="grados; norte positivo, sur negativo"></td>
      </tr>
      <tr>
        <td><label for=ori1>Orientación</label></td>
        <td><input id=ori1 name=ori type=text value=0 placeholder="grados"></td>
      </tr>
      <tr>
        <td><label for=r1>Radio</label></td>
        <td><input id=r1 name=r type=text value=500 placeholder="pixeles"></td>
      </tr>
      <tr>
        <td><label for=glob1>Color de fondo</label></td>
        <td><select id=glob1 name=glob>
          <option style="background:#134" value="#134">azul oscuro</option>
          <option style="background:#bde" value="#bde">azul claro</option>
          <option style="background:#00f" value="#00f">azul brillante</option>
          <option style="background:#bbb" value="#bbb">gris</option>
          <option style="background:#000" value="#000">negro</option>
          <option style="background:#fff" value="#fff">blanco</option>
        </select></td>
      </tr>
      <tr>
        <td><label for=file1>Archivo</label></td>
        <td><select id=file1 name=file><option value="">Seleccione un archivo</option><?php
        $d = scandir('.');
        foreach($d as $f) {
			$p = pathinfo($f);
			if($p['extension']=='kml')
				echo "<option>$f</option>";
		}
        ?></select></td>
      </tr>
    </table><input type=submit>
  </form>

  <form class=form id=laed style="display:none" action="lambaz-ed.php" method=get>
    <table>
      <tr>
        <td><label for=coord2>Ciudad</label></td>
        <td><select id=coord2 onchange="setcoord(2)">
          <option value="">Seleccione una ciudad</option><?php
			$fc=file_get_contents('coords.csv');
			$l = explode(chr(10),$fc);
			foreach($l as $line) {
				if(trim($line)=='') continue;
				$c = explode(',',$line);
				echo sprintf('<option value="%.2f;%.2f">%s</option>',$c[0],$c[1],$c[2]);
			}
?>
        </select></td>
      </tr>
      <tr>
        <td><label for=lon2>Longitud</label></td>
        <td><input id=lon2 name=lon type=text placeholder="grados; este positivo, oeste negativo"></td>
      </tr>
      <tr>
        <td><label for=lat2>Latitud</label></td>
        <td><input id=lat2 name=lat type=text placeholder="grados; norte positivo, sur negativo"></td>
      </tr>
      <tr>
        <td><label for=ori2>Orientación</label></td>
        <td><input id=ori2 name=ori type=text value=0 placeholder="grados"></td>
      </tr>
      <tr>
        <td><label for=r2>Radio</label></td>
        <td><input id=r2 name=r type=text value=500 placeholder="pixeles"></td>
      </tr>
      <tr>
        <td><label for=glob2>Color de fondo</label></td>
        <td><select id=glob2 name=glob>
          <option style="background:#134" value="#134">azul oscuro</option>
          <option style="background:#bde" value="#bde">azul claro</option>
          <option style="background:#00f" value="#00f">azul brillante</option>
          <option style="background:#bbb" value="#bbb">gris</option>
          <option style="background:#000" value="#000">negro</option>
          <option style="background:#fff" value="#fff">blanco</option>
        </select></td>
      </tr>
      <tr>
        <td><label for=file2>Archivo</label></td>
        <td><select id=file2 name=file><option value="">Seleccione un archivo</option><?php
        $d = scandir('.');
        foreach($d as $f) {
			$p = pathinfo($f);
			if($p['extension']=='kml')
				echo "<option>$f</option>";
		}
        ?></select></td>
      </tr>
    </table><input type=submit>
  </form>

  <form class=form id=flat style="display:none" action="flat.php" method=get>
    <table>
      <tr>
        <td><label for=lon3>Meridano central</label></td>
        <td><input id=lon3 name=lon type=text value=0 placeholder="grados; este positivo, oeste negativo"></td>
      </tr>
      <tr>
        <td><label for=w3>Ancho</label></td>
        <td><input id=w3 name=width type=text value=720 placeholder="pixeles"></td>
      </tr>
      <tr>
        <td><label for=h3>Alto</label></td>
        <td><input id=h3 name=height type=text value=540 placeholder="pixeles"></td>
      </tr>
      <tr>
        <td><label for=glob3>Color de fondo</label></td>
        <td><select id=glob3 name=glob>
          <option style="background:#134" value="#134">azul oscuro</option>
          <option style="background:#bde" value="#bde">azul claro</option>
          <option style="background:#00f" value="#00f">azul brillante</option>
          <option style="background:#bbb" value="#bbb">gris</option>
          <option style="background:#000" value="#000">negro</option>
          <option style="background:#fff" value="#fff">blanco</option>
        </select></td>
      </tr>
      <tr>
        <td><label for=file3>Archivo</label></td>
        <td><select id=file3 name=file><option value="">Seleccione un archivo</option><?php
        $d = scandir('.');
        foreach($d as $f) {
			$p = pathinfo($f);
			if($p['extension']=='kml')
				echo "<option>$f</option>";
		}
        ?></select></td>
      </tr>
    </table><input type=submit>
  </form>

  <p>Look at repository in <a href="http://github.com/chlewey/kml2svg">GitHub</a>.</p>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
  <script>//<![CDATA[
function setcoord(n) {
	var c = $("#coord"+n).val();
	var s = c.split(';')
	$("#lon"+n).val(s[0]);
	$("#lat"+n).val(s[1]);
}
function selectproj() {
	var c = $("#selector").val();
	$('.form').hide();
	$('#'+c).show();
}
  //]]></script>
</body>
</html>
