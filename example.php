<?php
header('Content-Type: application/json');
session_start();

require('ngMySQL.php');

$ngmysql = new mysqlbinder(
  // MySQL connection settings
	$mysql_host,
	$mysql_user,
	$mysql_password, 
	$mysql_db, 
	"hot"
	);
//$mysq->table="hot";
$ngmysql->debug = true;

/*
	ROUTING
 */
if (isset($_GET['setdoneorder'])) {
	SetDoneOrder($ngmysql);
	ShowOrders($ngmysql);

}elseif(isset($_GET['delorders'])){
	DelOrder($ngmysql);
	ShowOrders($ngmysql);

}elseif(isset($_GET['insert'])){

	$hardering = [
	'id' => '/[^0-9]/', 
	'*' => '/[^a-zA-Z0-9_ \*]+/'
	];

	$ngmysql->values_hardering($hardering);
	$ngmysql->insert_json('id');

	echo $ngmysql->select_json('id>100','id,hotel,date');
	
}elseif(isset($_GET['showorders'])){
	ShowOrders($ngmysql);

}elseif(isset($_GET['addhot'])){
    $id=AddHot($ngmysql);
    echo '{"id":"'.$id[0].'"}';
    $_SESSION['last_hot_id'] = $id[0];

}elseif(isset($_GET['delhot'])){
	DelHot($ngmysql);
	ShowHots($ngmysql);

}elseif(isset($_GET['showhots'])){
  	ShowHots($ngmysql);

};

/*
	FUNCTIONS
 */
function AddHot($ngmysql){
	return $ngmysql->insert_json();
}
function SetDoneOrder($ngmysql){

	$ngmysql->table='siteorders';
	if (!$ngmysql->update_json('id','','','status=2') ){
    echo "Error during update;"
	}
}

function DelOrder($ngmysql){

	$ngmysql->table='siteorders';
	if (!$ngmysql->delete_json('id') ){
    // Send error
		$ngmysql>send_error('ww','dsd');
	}
}

function ShowOrders($ngmysql) {
	$ngmysql->table='siteorders';
	$out = $ngmysql->select_json('','id,added,name,date,country,time,price,peoples,phone,email,status','added DESC');
	echo $out;
}

function ShowHots($ngmysql) {
	$ngmysql->table='hot';
	$out = $ngmysql->select_json('','*','date DESC');
	echo $out;
}

function DelHot($ngmysql){

	$ngmysql->table='hot';
	if (!$ngmysql->delete_json('id') ){
    // Send errror
		$ngmysql>send_error('ww','dsd');
	}
}
