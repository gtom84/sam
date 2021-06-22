<?php declare(strict_types = 1);

require_once './bootstrap.php';

if(!isset($_REQUEST['token']) || $_REQUEST['token'] !== ACCESS_KEY){
    header('HTTP/1.0 403 Forbidden');
    exit;
}

$vs = $_REQUEST['vs'];

if (!is_numeric($vs)){
    throw new \Exception('VS must be numeric YYYYID');
}

$year = substr($vs, 0, 4);
$id = substr($vs, 4);

/*
*   @var \PDOStatement
*/
$stm = $SERVICES['pdo']->prepare(
    'SELECT
        `vegetarian`,
        `appdetail`
    FROM
        `sam_prihlasky`
    WHERE
        `year`=? AND id=?'
);
$stm->execute([$year, $id]);

$data = $stm->fetch(PDO::FETCH_ASSOC);

if (isset($_REQUEST['spec'])){
    // if string is present
    $data['allowed'] = strpos($data['appdetail'], $_REQUEST['spec']) > -1;
}

print json_encode($data);
