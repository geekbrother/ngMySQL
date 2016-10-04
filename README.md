# ngMysql
Mysql PHP Binding for AngularJS.

Using  `JSON` from AngularJS `POST` requests to update , delete or show elements from  MySQL table.

Create MySQL connection binding:
```php
require('ngmysql.php');

$ngmysql = new mysqlbinder(
	$mysql_host,
	$mysql_user,
	$mysql_password, 
	$mysql_db, 
	"table"
	);
$mysq->table="new table"; // Optional MySQL table change
$ngmysql->debug = true;

```

Example of SHOW table records with `id,added,name,date` columns and order by `added DESC`:
```php
function ShowOrders($ngmysql) {
	$ngmysql->table='table';
	$out = $ngmysql->select_json('','id,added,name,date','added DESC');
	echo $out;
}

```

Example of UPDATE table records from `POST` request `JSON` from AngularJS where KEY for UPDATE is `id` and additional SQL parameters for UPDATE is `status=2`:
```php
if (!$ngmysql->update_json('id','','','status=2') ){
  echo 'Error durng update';
}
```
