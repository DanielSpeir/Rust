# Rust

Rust is a lightweight, but full-bodied, router for PHP 5.3+. 

## Goals

* Unopinionated routing class.
* Flexible enough to serve as a "drop-in" for a variety of applications.
* Intuitive and efficient routing that moves and grows with you.

## Example Usage

```php

$router = Rust::getRouter();

// Articles parent route
$router->route('articles', function($route){

  $route->response('Show Article List');

});

// Articles child route
$router->route('articles/:id', function($route){

  $route->get(function($id){
  
    // Load article item from DB using $id variable
  
  });
  
  $route->patch(function($id){
  
    // Update article record in DB using $id variable
  
  });

});
```

