<?php 
/**
*
* @author Brendan Dileo
*
* Establishes a connection to the MySQL database, eliminating the need to re-establish the connection in every file.
*/

$dsn = "mysql:dbname=db_name;host=host"; // data source name / connection string
$username = "";
$password = "";

try { // try to create connection
    $dbh = new PDO($dsn, $username, $password); // creates a new pdo containing the db connection and stores it in a database handle referencing the connection
} catch (Exception $ex) {
    echo "An error occurred connecting to the database. Reason: " . $ex->getMessage();
}
?>