<!DOCTYPE html>
<html>
<head>
  <meta charset=utf-8>
  <title>Lambert</title>
</head>

<body>
  <form action="laea.php" method=get>
    <table>
      <tr>
        <td><label for=coord>Ciudad</label></td>
        <td><select id=coord onchange="setcoord()">
          <option value="">Seleccione una ciudad</option>
          <option value="-74.06;4.7">Bogotá</option>
          <option value="35.2167;31.7833">Jerusalén</option>
        </select></td>
      </tr>
      <tr>
        <td><label for=lon>Longitud</label></td>
        <td><input id=lon name=lon type=text></td>
      </tr>
      <tr>
        <td><label for=lat>Latitud</label></td>
        <td><input id=lat name=lat type=text></td>
      </tr>
      <tr>
        <td><label for=ori>Orientación</label></td>
        <td><input id=ori name=ori type=text value=0></td>
      </tr>
      <tr>
        <td><label for=r>Radio</label></td>
        <td><input id=r name=r type=text value=500></td>
      </tr>
      <tr>
        <td><label for=file>Archivo</label></td>
        <td><select id=file name=file><option value="">Seleccione un archivo</option><?php
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
function setcoord() {
	var c = $("#coord").val();
	var s = c.split(';')
	$("#lon").val(s[0]);
	$("#lat").val(s[1]);
}
  //]]></script>
</body>
</html>
