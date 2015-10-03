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
</body>
</html>
