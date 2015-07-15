<?php
echo phpversion();
exit;
require('../Rust.php');

$router = Rust::getRouter();

$router->config([
    'build_all' => true,
    'dev' => true,
    'controller_directory' => '../controllers/',
    'controller_rust_object' => true
]);

$router->setRequestMethod('post');

$router->all(function(){
    $this->before('okay');
});

$router->group('base', function(){

    $this->index(function(){
       $this->beforeChildren('yeah');
        $this->response('parents');
    });

    $this->route(':id', function(){
        $this->before('hello');
        $this->response('hi');
    });

});


$router->serve('base/3');
