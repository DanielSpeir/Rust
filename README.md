# Rust [![Build Status](https://travis-ci.org/DanielSpeir/Rust.svg?branch=master)](https://travis-ci.org/DanielSpeir/Rust)

Rust is a simple, full-bodied router for PHP 5.4+.

## Goals

* Unopinionated routing class.
* Flexible enough to serve as a "drop-in" for a variety of applications.
* Intuitive and efficient routing that moves and grows with your application.

## Example Usage

```php

// Build 'blog' Parent Route
$router->route('blog', function(){

  $this->response(function(){
    return $this->renderView('blog');
  });
  
});

// Build 'blog/new' Child Route
$router->route('blog/new', function(){

  $this->get(function(){
    return $this->renderView('blog-add');
  });

  $this->post(function(){
    // On successful insert to database:
    return $this->redirect('blog/' . $newBlogId);
  });
  
});

// Build 'blog/:id' Dynamic Child Route
$router->route('blog/:id', function(){

  $this->response(function($id){
    return $this->renderView('blog-single', $id);
  });
  
});

```


## Getting Started with Rust

----
### The Route Scope

The 'route' method accepts a minimum of two arguments: the Route Path, and the Route Action. The Route Action can take the form of a string, which the router would simply print, or a Closure object, which the router would fire. 

##### Basic String Return
```php
  $router->route('blog', 'Show all blogs');
```

##### Closure Return
```php
  $router->route('blog', function(){
	// Route Scope
  });
```

Whenever a Closure object is supplied as the route action, everything directly within this Closure object is considered to be within the "Route Scope". All Response Methods (get, post, delete, etc.) *must* be called within the Route Scope. 

#### The Index Route ( '/ ' )
The Index Route of your application (that is, your homepage, or root page) is defined by passing a single forward slash as the Route Path.
```php
$router->route('/', function(){
  // Route Scope
});
```

Alternatively, you can use the Index Route Method to perform this same action, as it's simply an alias of the code above. 

```php
$router->index(function(){
  // Route Scope
});
```

######**>> Flash Forward:** Both of these methods are and can be used within a Route Namespace to define a Parent Route. 

-----
### The Response Method Scope

The usage of Response Methods is confined directly to the Route Scope, and are used to supply specific actions to a Route depending on the request method. A Response Method accepts a maximum of one argument (*with the exception of the controller, which we'll cover later*), and can be one of two types: a string (to be printed), or a Closure object (to be called).

The complete list of Response Methods is: 

* `get()`
* `post()`
* `put()`
* `patch()`
* `delete()`
* `response()`
* `before()`
* `beforeAll()`
* `beforeChildren()`
* `controller()`

###### **>> Flash Forward:** The controller method is not so much a Response Method, as it is a Response Method *manipulator*. More on this later.

##### Example Usage
```php
	$router->route('blog', function(){
	
	  // Return a view on Get
	  $this->get(function(){
		// This is the Response Method Scope
	    return $this->returnView('blogs');
	  });
	  
	  // Manually echo a string on Post
	  $this->post(function(){
	    // Option 1:
	    echo "Posting a blog!";

		// Option 2:
		return "Posting a blog!";
	  });

	  // Let Rust echo the string on Delete
	  $this->delete('No deleting is allowed');
	  
	});
```

In the first example, we use Rust's 'renderView' function to send a view file to the 'blog' Route on a Get Request. 

In the second example, we employ two different methods to manually print a string inside the Response Method Scope. The first is to simply echo it, and the second is to return it. Every Response Method can, but is not required, to return a value. A null or true return will always render the Route as expected; however, a false return will render the "otherwise" Route and throw a 404 response.

The third example passes a string argument to the delete method, which is the equivalent of manually returning a string within a Closure object.

#### Method Overview: get, post, put, patch, and delete
Only one of the REST methods (get, post, put, patch, and delete) are rendered at a time. Rust determines which one to serve up by matching it to the server's Request Method variable at the time of render. This allows for the building of RESTful APIs.

#### Method Overview: response
The 'response' method is rendered in the absence of any other applicable request. For instance, if you were to define only a 'response' method within a Route Scope, then Rust would render that method for every request made to that Route, regardless of the Request Method. However, if you supplied both a 'get' and 'response' method, Rust would render the 'get' method on a Get Request, and the 'response' method on every remaining request type (post, put, patch, delete, etc.).

#### Method overview:  before, beforeAll, beforeChildren

The 'before' methods are middleware functions that are rendered before all other requests to a Route. The 'beforeAll' and 'beforeChildren' function are restricted to use within a Parent Route Scope, while the 'before' method can be used in both a Parent Route Scope and Child Route Scope. These are especially useful, for example, in authenticating user access to an API endpoint before that endpoint is rendered.

In a Parent Route Scope, 'beforeAll' would render before both the Parent Route and all Child Route(s), where 'beforeChildren' would render exclusively before Child Route(s), but not before the Parent. 
 
```php
  // The Parent Route
  $router->route('blog', function(){
  
	// Renders only before this Parent Route
    $this->before('String Return');

	// Renders before Parent and Child Route(s)
	$this->beforeAll('String Return');

	// Renders only before Child Route(s)
	$this->beforeChildren('String Return');

  });

  // The Child Route
  $router->route('blog/:id', function(){
  
    // Renders only before this Child Route, 
    // but after the beforeAll and beforeChildren
    // from the Parent.
	$this->before('String Return');

  });
```

#### Method Overview: controller
The controller method is the only Response Method than can accept a maximum of two arguments. The first argument being the controller file, and the second being the controller class. The second argument is only required if the controller class name is different than the name of the controller file. Controllers are covered in more depth in the Reverse Routing section.

```php
  $router->route('blog', function(){
    $this->controller('ctrlFile', 'ctrlClass');
  });
```
----
### The Namespace Scope

A Route Family (that is, a Route Parent and its Children) can be namespaced to avoid repetition using the 'group' method. The group method serves as a wrapper around all of your 'route' methods.

When building Routes within the a Namespace, you can use either of the Index Route Methods to build the Parent Route.

```php
  $router->group('blog', function(){
    
    // Route: 'blog' (Option 1)
    $this->route('/', 'String Return');

	// Route: 'blog' (Option 2)
	$this->index('String Return');

	// Route: 'blog/new'
	$this->route('new', 'String Return');

    // Dynamic Route: 'blog/:id'
    $this->route(':id', 'String Return');
    
  });
```
######**>> Flash Forward:** When using Rust's 'serveFromDirectory' function, all individual Route Files are auto-namespaced according to the name of the file.

----
### Dynamic Routing

You may build dynamic routes simply by prepending a colon to the beginning of each route parameter you intend to be dynamic. Each dynamic parameter is then made accessible as arguments to all Response Methods within the Route Scope in sequential order.

```php
	$router->route('blog/:id/:action', function(){
	
	  $this->response(function($id, $action){
	    return $action . ' Blog Number ' . $id;
	  });
	  
	}); 
```
Navigating to the new Route '**blog/32/Delete**' in the browser would yield:

```html
	Delete Blog Number 32
```

#### Casting Dynamic Parameters

All dynamic parameters are passed to the Response Method Scope as strings, but in some instances, you'll want to cast these parameters differently. You can do this manually within the Response Method, or use one of two options Rust provides.

##### Option 1: castParams function
The castParams function accepts one associative array argument.
```php
$router->castParams([
  'id' => 'int',
  'action' => 'string'
]);
```
The castParams function can be called at 3 different levels: Global level, Namespace level, or Route level. Rust will evaluate in order of specificity. The Route level being the most specific, then Namespace, then Global.

```php
/* 
  Global Level
  Specificity Level: Least Specific.
  
  Will cast ALL ':id' Route parameters defined
  in your application as 'int', unless overwritten
  by a more specific castParams.
*/
$router->castParams(['id' => 'int']);

$router->group('blog', function(){
  /*
    Namespace Level
    Specificity Level: Moderately Specific.
    
    Will overwrite any Global Level castParams 
    and will cast all ':id' Route parameters 
    used within this Namespace as 'int'.
  */
  $this->castParams(['id' => 'int']);
  
  $this->route(':id', function(){
    /*
      Route Level
      Specificity Level: Most Specific.
    
      Will overwrite any Global and Namespace 
      Level castParams and will cast the ':id'
      Route Parameter as an 'int' only for this 
      Route.
    */
    $this->castParams(['id' => 'int']);
    $this->get(function($id){
      return gettype($id);
    });
  });
});
```

Parameter casts can be cleared and reset at any point in the routing process by using the `$router->cleanCastParams()` function.

#####Option 2: Hungarian Notation Casting
There is one more option for casting parameters in Rust called Hungarian Notation Casting. It employs the use of a Hungarian Naming Convention to
cast parameter types for a specific Route, and is the *most most* specific option, as it will override ALL castParam levels.

```php
$router->route('blog/:iId', function(){
  $this->get(function($id){
    return gettype($id);
  });
});
```

#### Dynamic Order of Specificity

When building dynamic routes, keep in mind that more specific routes (that is, routes that explicitly declare static parameters in the same position that your dynamic route declares dynamic ones) should be defined *before* less specific routes. For example:

```php
  // Most Specific
  $router->route('blog/new', 'Add a new blog');

  // Less Specific
  $router->route('blog/:id', 'Show blog with id.');
```
In this example, if the less specific route were declared before the most specific, then when you navigated to '**blog/new**' in your browser, Rust would render the less specific route first, and pass 'new' to the 'id' position. To avoid this, we declare 'blog/new' first.

----
### Sterile Routes

Sterile Routes within Rust are Routes than cannot declare Child Routes, and cannot be defined within Namespaces. There are two total:  the All Route, and Otherwise Route.

Sterile routes are prepended with a colon, and are inaccessible directly from the browser.

#### All Route
The All Route is a middleware route that is rendered before *every* Route request in your application. 

```php
$router->all(function(){
  // Route Scope
});
```
The 'all' function is simply an alias for: 

```php
$router->route(':all', function(){
  // Route Scope
});
```
Therefore, the All Route can accept Response Methods like any normal route. The ':all' route is unique in that it is built to be rendered before any other routes in your application. The ':all' route cannot be accessed via URI.

#### Otherwise Route
The Otherwise Route is rendered in the absence of an applicable route, or when a Response Method explicitly returns false.

```php
$router->otherwise(function(){
  // Route Scope
});
```
Like the 'all' function, this is simply an alias for: 

```php
$router->route(':otherwise', function(){
  // Route Scope
});
```

Also like the ':all' route, the ':otherwise' route cannot be accessed via URI. When no Otherwise Route is defined, Rust falls back to a "Death Message", which can be configured via Rust's 'config' method.

######**>> Flash Forward**: Rust configuration

----
###Rust Storage
Rust provides a storage object for easily persisting data through a route ancestry. This object could be useful, for instance, in passing data downward to routes from the All Route, or from passing data from a Parent Route to a Child Route using the Parent's 'before' methods. 

```php
// Store an item
$router->store()->userId = 32;
```

Once an item is stored, it can be passed to the 'store' function as an argument for retrieval. Passing the variable as an argument allows Rust to determine whether or not it is set and will return truthy or falsey based on the determination.

```php
// Store an item
echo $router->store('userId');
```

Otherwise, retrieve it standard: 
```php
// Store an item
echo $router->store()->userId;
```
The storage object can be cleaned and reset at any point using the cleanStorage function.
```php
$router->cleanStore();
```
----
### Utility Functions
In Rust, Utility Functions are methods of the Rust class that can *only* be used within a Response Method Scope. Using them outside that scope will have no affect in a production environment. In a dev environment, it will throw an error. There are three of these: json, redirect, and renderView. 

###### **>> Flash Forward:** Setting environment type in Rust.

#### json()

#####Available Arguments:
* data | mixed
* setHeader | bool

Rust employs the use of output buffers to encapsulate responses printed from the router. The json method will clean the current output buffer, set a Content Type header of 'application/json' (unless instructed otherwise), and echo json encoded data.

```php
$router->route('blog/:id', function(){

  $this->get(function($id){
    $blogRecord = // fetch a blog record by $id
    return $this->json($blogRecordObject);
  });	
  
});
```

#### redirect()

#####Available Arguments:
* location | string
* response code | boolean or integer | default: false

Like the json method, the redirect method will clean the output buffer, and then reset the header location using a 302 response code. You may pass a boolean value of 'true' to the second argument to force a 301 permanent redirect, or you may supply a custom response code.

```php
$router->route('blog', function(){

  $this->get(function(){
    return $this->redirect('/');
  });
  
});
```

#### renderView()

#####Available Arguments:
* path | string
* variables | array

The renderView function allows you to render template files directly from a Response Method. It accepts two arguments: a view path, and an array of variables to expose to that view, if any.

The path argument assumes `.php`, so does no require you specify the file type; however, explicitly defining the file type will override Rust's assumption. The view's path will always be in relation to the route file. A view file on the same level as the route file could be rendered like so:

```php
$router->route('blog', function(){
  $this->get(function(){
    return $this->renderView('blog');
    // Or, if it were an HTML file
    return $this->renderView('blog.html');
  });
});
```

If the view file were located, for instance, in  a 'views' directory, we could use the **view_directory** config option to declare a path for Rust to prepend to our renderView path arguments, or declare the full path manually within the method. Either option works fine.

###### **>> Flash Forward:** Rust configuration.

```php
$router->config([
  'view_directory' => 'views';
]);

$router->route('blog', function(){
  $this->get(function(){
    // renders 'views/blog.php'
    return $this->renderView('blog');
    // Or, if view_directory is not set:
    return $this->renderView('views/blog');
  });
});
```

In order to expose variables to a view, an array of defined variables must be passed to the second argument position of the renderView function as an associative array.
```php
array(
  'first_name' => 'daniel',
  'last_name' => 'speir'
);
```
This associative array would result in these variables being exposed to your view:
#####  View File
```php
<h1>
  <?php echo $first_name . ' ' . $last_name; ?>
</h1>
```

PHP has two functions to make generating this associative array easy: `get_defined_vars()` and `compact().` 

```php
$router->route('blog/:id', function(){
  $this->get(function($id){
    // Using get_defined_vars()
    return $this->renderView('blog', get_defined_vars());

    // Using compact()
    return $this->renderView('blog', compact('id'));
  });
});
```
In this case, both functions produce the same array; however, `get_defined_vars()` can produce undesirable results if there are more variables being defined within the Response Method Scope than you want exposed to the view. `compact()` is the safest choice for the job.

----
### Helpers
Helpers are public methods just like Utility Methods, but are not limited to any scope. Helpers can be used at any point or scope of the routing build. 

* `isAjax()`
* `liveRoute()`
* `liveRouteParent()`
* `isRoute()`
* `isRouteParent()`
* `isParent()`
* `getQueryString()`
* `setRequestMethod()`
* `getRequestMethod()`
* `isGet()`
* `isPost()`
* `isPut()`
* `isPatch()`
* `isDelete()`

#### isAjax()

#####No Arguments.

Returns bool depending on whether or not endpoint was requested via Ajax. Note: this function employs the use of the HTTP_X_REQUESTED_WITH server variable, which some servers have been known to not provide. Ensure your server uses this variable before relying on this method.
```php
if ($router->isAjax()) { 
  // render json data
}
```

#### liveRoute()

#####No Arguments.
Returns route name rendered by Rust. For instance, 'blog/:id' or 'blog/new'.

```php
$this->liveRoute();
```

#### liveRouteParent()

#####No Arguments.
Returns parent route name rendered by Rust. For instance, 'blog'.

```php
$this->liveRouteParent();
```

#### isRoute()

#####Available Arguments:
* route name | string

Returns true or false depending on if route name argument supplied matches the liveRoute().

```php
if ($this->isRoute('blog/:id')){
  // do something
}
```

#### isRouteParent()

#####Available Arguments:
* parent route name | string

Returns true or false depending on if parent route name argument supplied matches the liveRouteParent().

```php
if ($this->isRouteParent('blog')){
  // do something
}
```

#### isParent()

##### No Arguments.

Returns true if `liveRoute()` and `liveParentRoute()` are identical. In other words, the live route is a parent route.

```php
if ($this->isParent()){
  // do something
}
```

#### getQueryString()

#####Available Arguments:
* query string index | string or bool | default: false

With no argument, returns query string arguments as an associative array. With argument, returns value of query string variable provided.

```php
// Return full array
$router->getQueryString();

// Return one value
$router->getQueryString('myVar');
```

#### setRequestMethod()

#####Available Arguments:
* request method | string

Sets the value of the server's Request Method variable. Cast insensitive. 

```php
$router->setRequestMethod('post');
```

#### getRequestMethod()

##### No Arguments.

Returns the value of the server's Request Method variable.

```php
$router->getRequestMethod();
```

#### isGet(), isPost(), isPut(), isPatch(), isDelete()

##### No Arguments.

Each of these arguments returns true if the server's Request Method variable matches the respective method name.

```php
if ($this->isDelete()){
  // do something
}
```
----
### Reverse Routing


# WORK IN PROGESS
