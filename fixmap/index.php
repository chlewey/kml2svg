<!DOCTYPE html>

<html lang=es>
<head>
  <meta charset=utf-8>
  <meta name=viewport content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel=stylesheet href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin=anonymous>
  <title>Fixmap</title>
</head>

<body>
  <h1>Fixmap</h1>

  <form method=post enctype="multipart/form-data" action=fixmap.php>
    <div class=form-group>
      <label for=ini_selector>Configuración</label>
      <select class=form-control id=ini_selector name=ini><?php
$ini_files = [];
foreach (new DirectoryIterator('./ini/') as $file) {
	if($file->isDot()) continue;
	if($file->getExtension()!='ini') continue;
	$ini_files[$file->getFilename()] = $file->getBasename('.ini');
}
uasort($ini_files, 'strnatcasecmp');
foreach($ini_files as $fn=>$bn) {
	$df = $bn=='default'? ' selected': '';
	print "\n        <option value=\"{$fn}\"{$df}>{$bn}</option>";
} ?>
  
      </select>
    </div>
    <div class=form-group>
      <label for=file_upldr>Archivo</label>
      <input class=form-control id=file_upldr name=infile type=file>
    </div>
	<button type=submit class="btn btn-primary" name=submit>Enviar</button>
  </form>
  <table id=ayuda border=1>
    <tr><th colspan=3>Con líneas</th><th colspan=3>(multiple)</th></tr>
    <tr><td>XA - <small>(-?)</small><em>Origen</em> - <em>Destino</em></td><td>2</td><td>Anexa Origen al Destino</td><td colspan=3></td></tr>
    <tr><td>XB - <small>(-?)</small><em>Origen</em> - <em>Destino</em></td><td></td><td></td><td colspan=3></td></tr>
    <tr><td>XC - <small>(-?)</small><em>Origen</em> - <em>Destino</em></td><td>4</td><td>Extiende Destino (entre puntos 1 y 4) como origen (entre puntos 2 y 3 - contrahorario)</td>
      <td colspan=3></td></tr>
    <tr><td>XD - <em>Objeto</em>[ - <small>[<em>Folder</em>:]</small><em>Otro</em>]</td><td>2</td><td>Borra Objeto[ y Otro].</td>
      <td>X*D  - <em>Objeto</em></td><td>1 × <em>n</em></td><td>multiple</td></tr>
    <tr><td>XH - <small>(-?)</small><em>Origen</em> - <em>Destino</em></td><td>4</td><td>Extiende Destino (entre puntos 1 y 4) como origen (entre puntos 2 y 3 - horario)</td>
      <td colspan=3></td></tr>
    <tr><td>XI - <em>Origen</em> - <small>[<em>Folder</em>:]</small><em>Destino</em></td><td>2</td><td>Perfora Origen en Destino (borra Origen).</td>
      <td>X*I  - <em>Origen</em> - <small>[<em>Folder</em>:]</small><em>Destino</em></td><td>1 × <em>n</em></td><td>multiple</td></tr>
    <tr><td>XL - <em>Origen</em> - <small>[<em>Folder</em>:]</small><em>Destino</em></td><td>2</td><td>Perfora Origen en Destino (conserva Origen).</td>
      <td>X*L  - <em>Origen</em> - <small>[<em>Folder</em>:]</small><em>Destino</em></td><td>1 × <em>n</em></td><td>multiple</td></tr>
    <tr><td>XM - <em>Origen</em> - <small>[<em>Folder</em>:]</small><em>Destino</em></td><td>4</td><td>Copia forma de Origen en Destino (conserve Origen).</td>
      <td>X*M  - <em>Origen</em> - <small>[<em>Folder</em>:]</small><em>Destino</em></td><td>2 × <em>n</em></td><td>multiple</td></tr>
    <tr><td>XN - <em>Origen</em> - <small>[<em>Folder</em>:]</small><em>Destino</em></td><td>4</td><td>Copia forma de Origen en Destino (borra Origen).</td>
      <td>X*N  - <em>Origen</em> - <small>[<em>Folder</em>:]</small><em>Destino</em></td><td>2 × <em>n</em></td><td>multiple</td></tr>
    <tr><td>XQ - <em>Objeto</em>[ - <small>[<em>Folder</em>:]</small><em>Otro</em>]</td><td>1+<em>n</em>+1</td><td>Cambia forma de Objeto con nueva línea (contrahoraria).</td>
      <td colspan=3></td></tr>
    <tr><td>XR - <em>Objeto</em>[ - <small>[<em>Folder</em>:]</small><em>Otro</em>]</td><td>1+<em>n</em>+1</td><td>Cambia forma de Objeto con nueva línea (horaria).</td>
      <td colspan=3></td></tr>
    <tr><td>XV - <small>(-?)</small><em>Origen</em> - <em>Destino</em></td><td></td><td></td><td colspan=3></td></tr>
    <tr><td>XW - <small>(-?)</small><em>Origen</em> - <em>Destino</em></td><td>2</td><td>Extiende el Destino hasta llegar al Origen</td><td colspan=3></td></tr>
    <tr><td>XX - <small>(-?)</small><em>Origen</em> - <em>Destino</em></td><td>2</td><td>Extiende el Destino siguiendo Origen (apertura)</td><td colspan=3></td></tr>
    <tr><td>XY - <small>(-?)</small><em>Origen</em> - <em>Destino</em></td><td>2</td><td>Extiende el Destino hasta abarcar el Origen</td><td colspan=3></td></tr>
    <tr><td>XZ - <small>(-?)</small><em>Origen</em> - <em>Destino</em></td><td>2</td><td>Extiende el Destino siguiendo Origen (cierre)</td><td colspan=3></td></tr>
    <tr><th colspan=3>Con áreas</th><th colspan=3></th></tr>
    <tr><td>XD - <em>Objeto</em>[ - <small>[<em>Folder</em>:]</small><em>Otro</em>]</td><td>área</td><td>Perfora Objeto[ y Otro].</td>
      <td colspan=3></td></tr>
    <tr><td>XL - <em>Objeto1</em> - <small>[<em>Folder</em>:]</small><em>Objeto2</em></td><td>área</td><td>Agrega área a Objeto1 y perfore Objeto2.</td>
      <td colspan=3></td></tr>
    <tr><td>XQ - <em>Objeto1</em> - <small>[<em>Folder</em>:]</small><em>Objeto2</em></td><td>área</td><td>Agrega area a dos objetos.</td>
      <td colspan=3></td></tr>
  </table>
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin=anonymous></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin=anonymous></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin=anonymous></script>
</body>
</html>
