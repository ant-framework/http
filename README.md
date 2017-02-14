# Ant Framework Http Module

### 安装
```
composer require ant-framework/http
```

#### 创建一个请求
```php
include "vendor/autoload.php";

$request = new \Ant\Http\Request('GET','http://www.example.com');

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

// 设置响应内容为Json格式
$response = $response->setType('json')
    ->setContent(['foo' => 'bar'])
    ->decorate();

echo $response;
//output ..
/*
HTTP/1.1 200 OK
Content-Type: application/json;charset=utf-8

{"foo":"bar"}
*/
```