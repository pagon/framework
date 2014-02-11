# 安装

## 新项目

下载最新版：

[下载最新版](https://github.com/hfcorriez/pagon/releases)

Git下载：

```
$ git clone https://github.com/hfcorriez/pagon
```

Composer:

```
$ composer.phar create-project pagon/pagon myapp
```

单文件：

```
$ wget https://github.com/hfcorriez/pagon/raw/0.8.0-dev/pack/pagon.core.php
```

## 已有项目

Composer:

```
$ composer.phar require pagon/framework="*"
``

# 使用

## Hello world

```php
$app = App::create();

$app->get('/', function($req, $res){
   $res->write('Hello world');
});

$app->run();
```

## 命令行模式

```php
$app = App::create();

$app->command('hello', function($req, $res){
   $res->write('Hello world');
});

$app->command('help', function($req, $res){
   $res->write('Help Guide');
});

$app->run();
```

> 命令行模式仅能在命令行下运行

## API

```php
$app = App::create();

$app->get('/users', function($req, $res){
  // 使用JSON输出
  $res->json(array(
    array('name' => 'hfcorriez', 'id' => 1)
  ));
});

$app->run();
```