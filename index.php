<!DOCTYPE html>
<html>
<head>
  <meta charset=utf-8>
  <title>Proyecciones</title>
</head>
<?php

function ciudades($n) {
	?><select id=coord<?=$n?> onchange="setcoord(<?=$n?>)"><option value="">Seleccione una ciudad</option><?php
			$fc=file_get_contents('coords.csv');
			$l = explode(chr(10),$fc);
			foreach($l as $line) {
				if(trim($line)=='') continue;
				$c = explode(',',$line);
				echo sprintf('<option value="%.2f;%.2f">%s</option>',$c[0],$c[1],$c[2]);
			}
?></select><?php
}

function mapas($n) {
	?><select id=file<?=$n?> name=file><option value="">Seleccione un archivo</option><?php
        $d = scandir('.');
        foreach($d as $f) {
			$p = pathinfo($f);
			if($p['extension']=='kml')
				echo "<option>$f</option>";
		}
        ?></select><?php
}

function cilindrical($name,$n,$php=null) {
	if(!$php) $php="$name.php"; ?>
  <form class=form id=<?=$name?> style="display:none" action="<?=$php?>" method=get>
    <table>
      <tr>
        <td><label for=lon<?=$n?>>Meridano central</label></td>
        <td><input id=lon<?=$n?> name=lon type=text value=0 placeholder="grados; este positivo, oeste negativo"></td>
      </tr>
      <tr>
        <td><label for=w<?=$n?>>Ancho</label></td>
        <td><input id=w<?=$n?> name=width type=text value=720 placeholder="pixeles"></td>
      </tr>
      <tr>
        <td><label for=h<?=$n?>>Alto</label></td>
        <td><input id=h<?=$n?> name=height type=text value=540 placeholder="pixeles"></td>
      </tr>
      <tr>
        <td><label for=glob<?=$n?>>Color de fondo</label></td>
        <td><select id=glob<?=$n?> name=glob>
          <option style="background:#134" value="#134">azul oscuro</option>
          <option style="background:#bde" value="#bde">azul claro</option>
          <option style="background:#00f" value="#00f">azul brillante</option>
          <option style="background:#bbb" value="#bbb">gris</option>
          <option style="background:#000" value="#000">negro</option>
          <option style="background:#fff" value="#fff">blanco</option>
        </select></td>
      </tr>
      <tr>
        <td><label for=file<?=$n?>>Archivo</label></td>
        <td><?=mapas($n)?></td>
      </tr>
    </table><input type=submit>
  </form>
<?php
}

function azimutal($name,$n,$php=null) {
	if(!$php) $php="$name.php"; ?>
  <form class=form id=<?=$name?> style="display:none" action="<?=$php?>" method=get>
    <table>
      <tr>
        <td><label for=coord<?=$n?>>Ciudad</label></td>
        <td><?=ciudades($n)?></td>
      </tr>
      <tr>
        <td><label for=lon<?=$n?>>Longitud</label></td>
        <td><input id=lon<?=$n?> name=lon type=text placeholder="grados; este positivo, oeste negativo"></td>
      </tr>
      <tr>
        <td><label for=lat<?=$n?>>Latitud</label></td>
        <td><input id=lat<?=$n?> name=lat type=text placeholder="grados; norte positivo, sur negativo"></td>
      </tr>
      <tr>
        <td><label for=ori<?=$n?>>Orientación</label></td>
        <td><input id=ori<?=$n?> name=ori type=text value=0 placeholder="grados"></td>
      </tr>
      <tr>
        <td><label for=r<?=$n?>>Radio</label></td>
        <td><input id=r<?=$n?> name=r type=text value=500 placeholder="pixeles"></td>
      </tr>
      <tr>
        <td><label for=glob<?=$n?>>Color de fondo</label></td>
        <td><select id=glob<?=$n?> name=glob>
          <option style="background:#134" value="#134">azul oscuro</option>
          <option style="background:#bde" value="#bde">azul claro</option>
          <option style="background:#00f" value="#00f">azul brillante</option>
          <option style="background:#bbb" value="#bbb">gris</option>
          <option style="background:#000" value="#000">negro</option>
          <option style="background:#fff" value="#fff">blanco</option>
        </select></td>
      </tr>
      <tr>
        <td><label for=file<?=$n?>>Archivo</label></td>
        <td><?=mapas($n)?></td>
      </tr>
    </table><input type=submit>
  </form>
<?php
}

?>
<body>
  <form>
    <label for=seletor>Proyección</label>
    <select id=selector onchange="selectproj()">
      <option>Seleccione una opción</option>
      <option value=flat>Plana</option>
      <option value=laea>Azimutal de Lambert Equiárea</option>
      <option value=laed>Equidistante</option>
      <option value=sinusoidal>Sinusoidal</option>
      <option value=mercator>De Mercator</option>
      <option value=mollweide>De Mollweide</option>
      <option value=peters>De Peters</option>
    </select>
  </form>
  
<?= cilindrical('flat', 0) ?>

<?= azimutal('laea',1,'lambaz-ea.php') ?>

<?= azimutal('laed',2,'lambaz-ed.php') ?>

<?= cilindrical('sinusoidal', 3) ?>

<?= cilindrical('mercator', 4) ?>

<?= cilindrical('mollweide', 5) ?>

<?= cilindrical('peters', 6) ?>

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
