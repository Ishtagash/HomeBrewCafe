<?php
session_start();
$serverName="LAPTOP-8KOIBQER\SQLEXPRESS";
$connectionOptions=[
    "Database"=>"DLSU",
    "Uid"=>"",
    "PWD"=>""
];

$conn=sqlsrv_connect($serverName, $connectionOptions);

$pass=$_POST['password'];
$user=$_POST['username'];

$sql = "SELECT * FROM LogIn WHERE USERNAME = ? AND PASSWORD = ?";
$login = [$user, $pass];

$stmt = sqlsrv_query($conn, $sql, $login);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

if (sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $_SESSION['role'] = "admin";
    header("Location: home.php");
    exit();
} else {
    header("Location: adminlogin.html?error=1");
    exit();
}
?>
