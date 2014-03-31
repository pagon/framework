# 安装

## 新项目

下载：

[https://github.com/hfcorriez/pagon/releases](https://github.com/hfcorriez/pagon/releases)

```bash
$ wget https://drone.io/github.com/hfcorriez/pagon/files/build/pagon-master.tar.gz
```

Git 安装：

```
$ git clone https://github.com/hfcorriez/pagon myapp
$ cd myapp && composer.phar install
```

Composer:

```
$ composer.phar create-project pagon/pagon="dev-master" myapp
```

## 已有项目

Composer:

```
$ composer.phar require pagon/framework="dev-master"
```

## 单文件：

```
$ wget https://drone.io/github.com/pagon/framework/files/pack/pagon.core.php
```

> 单文件版本目前只包含核心组件

# 使用

## Hello world

```php
$app = Pagon::create();

$app->get('/', function($req, $res){
   $res->write('Hello world');
});

$app->run();
```

## 命令行模式

```php
$app = Pagon::create();

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
$app = Pagon::create();

$app->get('/users', function($req, $res){
  // 使用JSON输出
  $res->json(array(
    array('name' => 'hfcorriez', 'id' => 1)
  ));
});

$app->run();
```