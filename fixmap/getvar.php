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

  <p><?=$_POST['ini']?></p>
  
  <pre><?php
//$target_dir = 'in/';
//$target_file = $target_dir.basename($_FILES['infile']['name'];
//if(isset($_POST['submit'])) {
//	if(move_uploaded_file($_FILES['infile']['tmp_name'], $target_file)) {
		echo htmlentities(file_get_contents($_FILES['infile']['tmp_name']));
//	} else {
//		print "--- File count not be copies to {$target_file} ---\n";
//	}
//}
?></pre>

<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin=anonymous></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin=anonymous></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin=anonymous></script>
</body>
</html>
