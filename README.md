# ngMySQL
PHP + MySQL Binding library for AngularJS.

This library gets PHP STDIN and parse for JSON coming from AngularJS frontend and UPDATE,DELETE records in table based on paramaters which columns need to be changed and whats use as a key in JSON request.
Also you can get records from table(s) in JSON format for AngularJS based on filtering which passes to methods.


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

You dont need to get and parse STDIN, make SQL and etc., just pass which columns from table you need to pass back to angular.

Example of **SHOW** table records with `id,added,name,date` columns and order by `added DESC`.
```php
function ShowOrders($ngmysql) {
	$ngmysql->table='table'; // You can change default table name if you need
	$out = $ngmysql->select_json('','id,added,name,date','added DESC');
	echo $out;
}

```

Example of **UPDATE** table records from `POST` request `JSON` from AngularJS where `KEY` for `UPDATE` is `id` and additional parameters for `UPDATE` SQL statement is `status=2`:
```php
if (!$ngmysql->update_json('id','','','status=2') ){
  echo 'Error durng update';
}
```

Look to `example.php` for more...
