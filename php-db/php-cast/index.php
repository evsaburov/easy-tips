<?
require 'functions.php';
require 'database.php';
require 'router.php';



$config = [
  'host' => 'localhost',
  'port' => 3306,
  'dbname' => 'myapp',
  'charset' => 'utf8mb4'
];

$id = $_GET['id'];
$query = "select * from posts where id = ?";
// $query = "select * from posts where id = :id";


$db = new Database($config['database']);
$posts = $db->query($query, [$id])->fetch();
// $posts = $db->query($query, [':id' => $id])->fetch();
