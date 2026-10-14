<!-- database connection  -->
<?php

$dbHost = '127.0.0.1';
 $dbName = 'iaims_db';
 $dbUser = 'root';
 $dbPassword = '';

 try{
  // create a PDO connection to the database
    $pdo = new PDO(
      "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
      $dbUser,
      $dbPassword,

      [
          // throw exceptions when db operation fails
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

          // return query results as associative arrays
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // use native prepared statements where supported
            PDO::ATTR_EMULATE_PREPARES => false,
      ]
    );
} catch (PDOException $exception){
  // record the technical error in the php error log
   error_log('Database connection failed: ' . $exception->getMessage());

  //  show generic message instead of exposing db details
    exit('unable to connect to the database.');
}

?>

