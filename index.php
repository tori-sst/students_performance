<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Document</title>
</head>
<body>
	<?php
  require_once 'login.php';
  try
  {
    $pdo = new PDO($attr, $user, $pass, $opts);
  }
  catch (PDOException $e)
  {
    throw new PDOException($e->getMessage(), (int)$e->getCode());
  }
?>

<H1>Отчет итоговый: </H1>
<?php
$query = "SELECT * FROM report";
$result = $pdo->query($query);

while ($row = $result->fetch()) {
    
    echo "<p>" . $row['ФИО студента'] . " | Группа: " . $row['Группа'] . " | Оценка: " . $row['Оценка'] . "</p>";
}



?>

</body>
</html>
