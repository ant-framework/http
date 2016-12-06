# Ant-Http

#### 创建一个请求
```php
include "vendor/autoload.php";

$request = new \Ant\Http\Request('POST','http://www.example.com');

echo $request;

//output ..
/*
GET / HTTP/1.1
Host: www.example.com


*/
```

#### 创建一个响应
```php
include "vendor/autoload.php";

$response = new \Ant\Http\Response();

// 选择装饰器,设置内容,装饰response
$response = $response->selectRenderer('json')
    ->setPackage(['foo' => 'bar'])
    ->decorate($response);

//output ..
/*
HTTP/1.1 200 OK
Content-Type: application/json;charset=utf-8

{"foo":"bar"}
*/
```