<?php

/**
 * Class Rust
 */
class Rust extends RustyTools
{

    /**
     * Instance of Rust Object.
     *
     * @var
     */
    private static $oInstance;

    /**
     * Used in unit tests to simulate a URI
     *
     * @var string
     */
    public static $sSimulateUri = null;

    /**
     * Current scope's Route as string
     * @var string
     */
    private static $sRoute = "";

    /**
     * Current scope's route Route as array
     * (self::$sRoute exploded at '/')
     *
     * @var array
     */
    private static $aRoute = array();

    /**
     * Current scope's response method
     *
     * @var string
     */
    private static $sResponseMethod = "";

    /**
     * Current scope's arguments
     *
     * @var array
     */
    private static $aRouteArguments = array();

    /**
     * Current scope's Parent Route as string
     * @var string
     */
    private static $sParentRoute = "";

    /**
     * Current scope's Parent Route as array
     * (self::$sParentRoute exploded at '/')
     *
     * @var array
     */
    private static $aParentRoute = array();

    /**
     * Current scope's namespace
     *
     * @var null
     */
    private static $sNamespace = null;

    /**
     * Current scope's controller file
     *
     * @var string
     */
    private static $sControllerFile = "";

    /**
     * Current scope's controller class
     *
     * @var string
     */
    private static $sControllerClass = "";

    /**
     * Array containing all built Routes and their properties
     *
     * @var array
     */
    private $aRoutes = array();

    /**
     * Holds bool determining Live Route Type
     * (parent = true, child = false)
     *
     * If "serve" renders a Parent Route, this remains
     * true. If "serve" renders a Child Route, this becomes
     * false.
     *
     * @var bool
     */
    private $bParentRoute = true;

    /**
     * Holds Live Route Name (example: 'articles/:id')
     *
     * @var string
     */
    private $sLiveRoute = "";

    /**
     * Holds Live Parent Route as string (example: 'articles')
     *
     * @var string
     */
    private $sLiveParentRoute = "";

    /**
     * Null Storage Object. Set to new instance of stdClass
     * on getStore() call.
     *
     * @var null
     */
    private $oStore = null;

    /**
     * This is set to true when "serve" method is called for the
     * first time. We check this on every "serve" to ensure we never
     * serve twice. Failsafe to prevent humanity's downfall.
     *
     * @var bool
     */
    private $bBeenServed = false;

    /**
     * Used to determine whether or not __call functions are being
     * called within the Route Scope.
     *
     * @var bool
     */
    private static $bRouteScope = false;

    /**
     * Used to determine whether or not Utility Functions are being
     * called within the Response Method Scope.
     *
     * @var bool
     */
    private static $bMethodScope = false;

    /**
     * Holds map that will allow us to cast route arguments
     *
     * @var array
     */
    private $aParamCasts = array();

    /**
     * Array of valid variable cast types.
     * PHP's settype function doesn't handle invalid
     * cast types gracefully, so we do a manual check.
     *
     * @var array
     */
    private static $aCastTypes = array('boolean', 'integer', 'float', 'string', 'array', 'object', 'null', 'int', 'bool');

    /**
     * Used in serveFromDirectory to persist the determined
     * route file through the "group" Closure to provide
     * auto-namespacing to all individual route files.
     *
     * @var string
     */
    private $sRoutePath = "";

    /**
     * Used in serveFromDirectory to persist the determined
     * all route file through the "all" Closure.
     *
     * @var string
     */
    private $sAllRoutePath = "";

    /**
     * Used in serveFromDirectory to persist the determined
     * all route file through the "otherwise" Closure.
     *
     * @var string
     */
    private $sOtherwiseRoutePath = "";

    /**
     * Used in serveFromDirectory to persist the determined
     * index route file through the "group" Closure.
     *
     * @var string
     */
    private $sIndexRoutePath = "";

    /**
     * Controller object
     *
     * @var null
     */
    private $oController = null;

    /* --------------------------------------------------
        CONFIG VARS
    ---------------------------------------------------*/

    /**
     * CONFIG VAR
     * Dictates whether the "all" route will be rendered
     * before the "otherwise" route. Default is false.
     *
     * @var bool
     */
    private $bRenderAllBeforeOtherwise = false;

    /**
     * CONFIG VAR
     * Holds Defined View Directory
     *
     * @var bool
     */
    private $mViewDir = false;

    /**
     * CONFIG VAR
     * Holds Defined Controller Directory
     *
     * @var bool
     */
    private $mControllerDir = false;

    /**
     * CONFIG VAR
     * Holds name of variable that will be passed to controllers
     * containing the current Rust object.
     *
     * @var bool
     */
    private $mControllerRustObject = "rust";

    /**
     * Holds boolean determining whether or not
     * Request Method will automatically be set using
     * the methodSetter.
     *
     * @var bool
     */
    private $bSetRequestMethod = true;

    /**
     * CONFIG VAR
     * Holds name of input used to set method.
     *
     * @var string
     */
    private $sMethodSetter = ":method";

    /**
     * CONFIG VAR
     * Holds message printed to page as last-resort on Route Death.
     *
     * @var string
     */
    private $sDeathMessage = "The requested route could not be rendered.";

    /**
     * CONFIG VAR
     * Holds boolean indicating Dev or Prod mode
     *
     * @var bool
     */
    private $bDevMode = false;

    /**
     * CONFIG VAR
     * Holds boolean indicating whether or not Rust will
     * only build and serve "Relevant" Routes. Default is false.
     *
     * @var bool
     */
    private $bBuildAll = false;

    /**
     * CONFIG VAR
     * Holds boolean indicating Rust is being executed via a unit test
     *
     * @var bool
     */
    private $bUnitTest = false;

    /**
     * CONFIG VAR
     * Holds name of file where user intends to define an "otherwise"
     * route. (Only usage is with serveFromDirectory functionality)
     *
     * @var string
     */
    private $sOtherwiseRouteFile = "_otherwise.php";

    /**
     * CONFIG VAR
     * Holds name of file where user intends to define an "all"
     * route. (Only usage is with serveFromDirectory functionality)
     *
     * @var string
     */
    private $sAllRouteFile = "_all.php";

    /**
     * CONFIG VAR
     * Holds name of file where user intends to define the "index"
     * route. That is, the '/' route. (Only usage is with serveFromDirectory
     * functionality)
     *
     * @var string
     */
    private $sIndexRouteFile = "_index.php";

    /* --------------------------------------------------
        DIAGNOSTIC VARS
    ---------------------------------------------------*/

    /**
     * Holds amount of time it took to Serve a Route.
     *
     * @var int
     */
    private $iServeTime = 0;

    /* --------------------------------------------------
        RUST SINGLETON INSTANCE
    ---------------------------------------------------*/

    /**
     * Private constructer to prohibit instantiation.
     */
    private function __construct() {}

    /**
     * Get Instance of Rust Object
     *
     * @return Rust
     */
    public static function getRouter() {
        if (self::$oInstance === null) {
            self::$oInstance = new self();
        }
        return self::$oInstance;
    }

    /* --------------------------------------------------
        PRIVATE FUNCTIONS
    ---------------------------------------------------*/

    /**
     * In DevMode, echo error and backtrace.
     * Outside of DevMode, do absolutely nothing as to
     * not prohibit the Routing process.
     *
     * @param $sError
     * @param bool|false $mMoreInformation
     * @return bool
     */
    private function throwError($sError, $mMoreInformation = false) {
        if ($this->bDevMode && !$this->bUnitTest) {
            echo "<b>Rust Error: </b> $sError";
            if ($mMoreInformation) {
                echo "<br/><br/><b>More Information:</b> $mMoreInformation";
            }
            echo "<br/><br/><hr/><b>Backtrace:</b><br/>";
            echo "<pre>";
            print_r(debug_backtrace()[1]);
            echo "</pre>";
            die;
        }
    }

    /**
     * Render response
     *
     * @param $mRequest
     * @param $aArguments
     * @param $mControllerFileOrBool
     * @param $mControllerClassOrBool
     * @return bool|mixed
     */
    private function renderResponse($mRequest, $aArguments, $mControllerFileOrBool, $mControllerClassOrBool) {

        $sGlobalIndex = ':global';
        $sNamespaceIndex = $this->liveRouteParent();
        $sRouteIndex = $this->liveRoute();

        foreach ($aArguments as $sParam => $mValue){
            if ($sCastType = $this->getHungarianCast($sParam)){
                settype($mValue, $sCastType);
                $aArguments[$sParam] = $mValue;
            } elseif (count($this->aParamCasts)) {
                if (isset($this->aParamCasts[$sRouteIndex]) && isset($this->aParamCasts[$sRouteIndex][$sParam]) && in_array($this->aParamCasts[$sRouteIndex][$sParam], self::$aCastTypes) && settype($mValue, $this->aParamCasts[$sRouteIndex][$sParam])) {
                    $aArguments[$sParam] = $mValue;
                } elseif (isset($this->aParamCasts[$sNamespaceIndex]) && isset($this->aParamCasts[$sNamespaceIndex][$sParam]) && in_array($this->aParamCasts[$sNamespaceIndex][$sParam], self::$aCastTypes) && settype($mValue, $this->aParamCasts[$sNamespaceIndex][$sParam])) {
                    $aArguments[$sParam] = $mValue;
                } elseif (isset($this->aParamCasts[$sGlobalIndex]) && isset($this->aParamCasts[$sGlobalIndex][$sParam]) && in_array($this->aParamCasts[$sGlobalIndex][$sParam], self::$aCastTypes) && settype($mValue, $this->aParamCasts[$sGlobalIndex][$sParam])) {
                    $aArguments[$sParam] = $mValue;
                }
            }
        }

        // Define bMethodScope to be true while rendering a response
        // so all Utility Functions may be called within.
        self::$bMethodScope = true;

        // Assume return is true unless proven otherwise
        $bReturn = true;

        switch (gettype($mRequest)) {
            case 'object':
                if ($mRequest instanceof \Closure) {
                    // Bind the Closure back to Rust's $this context
                    $mRequest = $mRequest->bindTo(self::$oInstance);
                    $mResponse = call_user_func_array($mRequest, $aArguments);
                    if (gettype($mResponse) === 'string' || gettype($mResponse) === 'integer'){
                        echo $mResponse;
                    } else {
                        $bReturn = $mResponse;
                    }
                } else {
                    $bReturn = false;
                }
                break;
            case 'string':
                if ($mControllerFileOrBool && $mControllerClassOrBool){
                    $bReturn = $this->renderController($mRequest, $mControllerFileOrBool, $mControllerClassOrBool, $aArguments);
                } else {
                    echo $mRequest;
                }
                break;
            default:
                $bReturn = false;
                break;
        }
        // Re-define bMethodScope to be false so all subsequent calls
        // to Utility Functions outside the Method Scope are not rendered.
        self::$bMethodScope = false;

        return $bReturn;
    }

    /**
     * Pipe endpoint handling to specified controller
     *
     * @param $sMethod
     * @param $sFile
     * @param $sClass
     * @param $aArguments
     * @return bool|mixed
     */
    private function renderController($sMethod, $sFile, $sClass, $aArguments){
        /*
        | Case 1: User supplied a Controller Directory to the config, so
        |         prepend it to the Controller file supplied.
        | Case 2: No Controller Directory supplied, so prepend nothing.
        */
        if ($this->mControllerDir){
            $sControllerPath = $this->mControllerDir . '/' . $sFile . '.php';
        } else {
            $sControllerPath = $sFile . '.php';
        }

        // Require file if it exists, otherwise, pass operations to throwError.
        if (file_exists($sControllerPath)){
            //$rustInstance = self::$oInstance;
            require_once("$sControllerPath");
            // If we've already created an instance of this Controller and it's being called
            // again, simply use the already-created object.
            if (!is_object($this->oController) || is_object($this->oController) && get_class($this->oController) !== $sClass){
                $this->oController = new $sClass();
            }
            /*
            | Case 1: The method supplied exists within the Controller class supplied
            |         and is callable, so call it and pass arguments.
            | Case 2: Not callable.
            */
            if (is_callable(array($this->oController, $sMethod))) {
                // Declare a dynamic 'rust' variable on the Controller Class containing the Rust object
                // so that controllers may interact with eachother across the same Rust object.
                if ($this->mControllerRustObject) {
                    $this->oController->{$this->mControllerRustObject} = self::$oInstance;
                }
                $mResponse = call_user_func_array(array($this->oController, $sMethod), $aArguments);
                if (gettype($mResponse) === 'string' || gettype($mResponse) === 'integer'){
                    echo $mResponse;
                } else {
                    return $mResponse;
                }
            } else {
                /*
                | Case 1: In devMode, so return a helpful error about the failure.
                | Case 2: Not in devMode, so return false and allow renderResponse to
                |         to force the "Otherwise" render.
                */
                if ($this->bDevMode){
                    return $this->throwError(self::getError(11, $sClass, $sMethod));
                } elseif($sMethod !== "before" && $sMethod !== "beforeAll" && $sMethod !== "beforeChildren") {
                    return false;
                } else {
                    return true;
                }
            }
        } else {
            return self::throwError(self::getError(10, $sControllerPath));
        }
    }

    /**
     * Retrieve and return array of arguments supplied to a Route.
     *
     * @param $aRoute
     * @return array
     */
    private function getRouteArguments($aRoute) {
        // Build arguments into array that we can pass to our Closure Objects
        // using call_user_func_array().
        $aArguments = array();
        if (isset($aRoute['args'])) {
            foreach ($aRoute['args'] as $iPosition => $aArgument) {
                if ($aArgument['type'] === 'dynamic') {
                    $aArguments[str_replace(':', '', $aArgument['arg'])] = $this->getUri($iPosition);
                }
            }
        }
        // Find Magic Arguments, if any.
        if ($aMagicArguments = array_slice($this->getUri(), count($aArguments) + 1)) {
            $aArguments[':magicArguments'] = $aMagicArguments;
        } else {
            $aArguments[':magicArguments'] = false;
        }

        return self::$aRouteArguments = $aArguments;
    }

    /**
     * Render Route
     *
     * @param $aRoute
     * @param bool|false $bAll
     *        Note: $bAll will be truthy whenever an ":all" Route exists and is rendered.
     *              It prevents the "render" function from spiralling recursively into
     *              a painful, fiery death.
     * @return bool
     */
    private function render($mRoute = false, $bAll = false) {
        /*
        | Case 1: $mRoute is not set and both liveRoute and liveRouteParent
        |         functions return true. Therefore, render the route that
        |         was determined to be Live.
        | Case 2: $mRoute was set, so render the route based on the informa-
        |         tion provided, as opposed to the Live Route.
        | Case 3: None of the criteria above were met, so return false.
        */
        if (!$mRoute && $this->liveRoute() && $this->liveRouteParent()){
            /*
            | Case 1: The Live Route and Parent Route are identical, indic-
            |         ating that the Live Route IS a Parent Route.
            | Case 2: Not identical. Live Route is a Child Route.
            */
            if ($this->isRouteParent($this->liveRoute())){
                $this->bParentRoute = true;
                $aRoute = $this->aRoutes[$this->liveRouteParent()];
                unset($aRoute['childRoutes']);
                $aParentRoute = $aRoute;
            } else {
                $this->bParentRoute = false;
                $aParentRoute = $this->aRoutes[$this->liveRouteParent()];
                $aRoute = $aParentRoute['childRoutes'][$this->liveRoute()];
            }
        } elseif ($mRoute) {
            $sRoute = $mRoute;
            /*
            | Case 1: Provided Route contains forward slashes, indicating
            |         it to be a Child Route.
            | Case 2: No forward slashes in the Route provided, indicating
            |         it to be a Parent Route.
            */
            if (stristr($sRoute, '/')) {
                $this->bParentRoute = false;
                $sParentRoute = explode('/', $sRoute)[0];
                $aParentRoute = $this->aRoutes[$sParentRoute];
                $aRoute = $aParentRoute['childRoutes'][$sRoute];
            } else {
                $this->bParentRoute = true;
                $sParentRoute = $sRoute;
                $aRoute = $this->aRoutes[$sParentRoute];
            }
        } else {
            return false;
        }

        // Assume return to be true until proven otherwise
        $bReturn = null;

        // We assume $bRenderOtherwise is true until proven otherwise.
        $bRenderOtherwise = true;

        // Retrieve the arguments for this Route as an array.
        $aArguments = $this->getRouteArguments($aRoute);

        // Determine if Controller Properties exist
        if (isset($aRoute['controllerFile']) && isset($aRoute['controllerClass'])){
            $mControllerFile = $aRoute['controllerFile'];
            $mControllerClass = $aRoute['controllerClass'];
        } else {
            $mControllerFile = false;
            $mControllerClass = false;
        }

        if (!$bAll && isset($this->aRoutes[':all']) && count($this->aRoutes[':all'])){
            $mAllRender = $this->render(':all', true);
            if (!is_null($mAllRender) && !$mAllRender){
                return $mAllRender;
            }
        }

        // If current route is not the "All" Route AND is a Parent Route AND "before" index
        // is set on the Parent, fire.
        if (!$bAll && $this->bParentRoute && isset($aRoute['before'])) {

            $mRender = $this->renderResponse($aRoute['before'], $aArguments, $mControllerFile, $mControllerClass);
            if (!is_null($mRender) && is_null($bReturn)){
                $bReturn = $mRender;
            }
            $bRenderOtherwise = false;
        }

        // If current route is a Child Route AND "beforeChildren" index is set on the Parent, fire.
        if (!$bAll && !$this->bParentRoute && isset($aParentRoute['beforeChildren'])) {
            $mRender = $this->renderResponse($aParentRoute['beforeChildren'], $aArguments, $mControllerFile, $mControllerClass);
            if (!is_null($mRender) && is_null($bReturn)){
                $bReturn = $mRender;
            }
            $bRenderOtherwise = false;
        }

        // If "beforeAll" index is set on Parent Route, fire.
        if (!$bAll && isset($aParentRoute['beforeAll'])) {
            $mRender = $this->renderResponse($aParentRoute['beforeAll'], $aArguments, $mControllerFile, $mControllerClass);
            if (!is_null($mRender) && is_null($bReturn)){
                $bReturn = $mRender;
            }
            $bRenderOtherwise = false;
        }

        // If current route is a Child Route OR is the "All" Route AND "before" index is
        // set on the Child, fire.
        if ((!$this->bParentRoute || $bAll) && isset($aRoute['before'])) {
            $mRender = $this->renderResponse($aRoute['before'], $aArguments, $mControllerFile, $mControllerClass);
            if (!is_null($mRender) && is_null($bReturn)){
                $bReturn = $mRender;
            }
            $bRenderOtherwise = false;
        }

        // Fire request-specific methods.
        if (isset($aRoute[$this->request(true)])) {
            self::$sResponseMethod = $this->request(true);
            $mRender = $this->renderResponse($aRoute[$this->request(true)], $aArguments, $mControllerFile, $mControllerClass);
            if (!is_null($mRender) && is_null($bReturn)){
                $bReturn = $mRender;
            }
            $bRenderOtherwise = false;
        } elseif (isset($aRoute['response'])) {
            self::$sResponseMethod = 'response';
            $mRender = $this->renderResponse($aRoute['response'], $aArguments, $mControllerFile, $mControllerClass);
            if (!is_null($mRender) && is_null($bReturn)){
                $bReturn = $mRender;
            }
            $bRenderOtherwise = false;
        }

        // If this is not an "All" render AND $bRenderOtherwise is
        // still truthy, then forceOtherwise.
        if (!$bAll && $bRenderOtherwise) {
            return $bReturn = $this->forceOtherwise();
        }

        // TODO: Comment and actions regarding OB contradict
        //
        // If the return is false, 'serve' will forceOthewise, which will handle
        // the Output Buffer clean. In this case, we do not want to flush the OB
        // before forceOtherwise has the chance to clean it.
        if (($bReturn || is_null($bReturn)) && !$bAll) {
            $bReturn = true;
            ob_end_flush();
        }

        return $bReturn;

    }

    /**
     * Force the "Otherwise" route.
     */
    private function forceOtherwise() {

        // Clean Buffer
        ob_clean();

        // Set response code
        http_response_code(404);

        if (isset($this->aRoutes[':all']) && $this->bRenderAllBeforeOtherwise){
            $this->render(':all', true);
        }

        if (isset($this->aRoutes[':otherwise']) && count($this->aRoutes[':otherwise'])) {
            // Render will handle ob_end_flush
            return $this->render(':otherwise');
        } else {
            echo $this->sDeathMessage;
            // Flush Buffer
            ob_end_flush();
            return true;
        }

        // If this is a Unit Test, we want it to carry on through the forceOtherwise.
        if (!$this->bUnitTest){ die; }
    }

    /* --------------------------------------------------
        PUBLIC UTILITY FUNCTIONS
        Note: Utility Functions are restricted to
              use within the Response Method Scope.
    ---------------------------------------------------*/

    /**
     * Render JSON data and set header Content-Type
     *
     * @param $mData
     * @param $bSetHeader
     * @return string
     */
    public function json($mData, $bSetHeader = true){

        if (!self::$bMethodScope) {
            return $this->throwError($this->getError(1));
        }

        // Clean the Output Buffer
        ob_clean();

        // Set header Content-Type
        if (!headers_sent() && $bSetHeader) {
            header('Content-Type: application/json');
        }
        return json_encode($mData);
    }

    /**
     * UTILITY FUNCTION
     *
     * Redirect
     *
     * @param $sLocation
     * @param bool $mBoolOrResponseCode
     * @return bool
     */
    public function redirect($sLocation, $mBoolOrResponseCode = false) {

        if (!self::$bMethodScope) {
            return $this->throwError($this->getError(1));
        }

        // Clean the Output Buffer
        ob_clean();

        switch (gettype($mBoolOrResponseCode)) {
            case 'boolean':
                if ($mBoolOrResponseCode) {
                    $iResponseCode = 301;
                } else {
                    $iResponseCode = 302;
                }
                break;
            case 'integer':
                $iResponseCode = $mBoolOrResponseCode;
                break;
            default:
                $iResponseCode = 302;
                break;
        }

        if (!headers_sent()) {
            header('Location: ' . $sLocation, true, $iResponseCode);
            die();
        } else {
            return false;
        }
    }

    /**
     * UTILITY FUNCTION
     *
     * Render a view and expose variables to the view.
     *
     * @param $sViewPath
     * @param bool $mVariables
     * @return bool
     */
    public function renderView($sViewPath, $mVariables = false) {

        if (!self::$bMethodScope) {
            return $this->throwError($this->getError(1));
        }

        // $aVariables are set and are an array, so loop through
        // and cast indexes as variables to be accessible by view.
        if ($mVariables && gettype($mVariables) === 'array') {
            foreach ($mVariables as $sKey => $sValue) {
                ${$sKey} = $sValue;
            }
        }

        // View Path was set, so prepend it to passed-in sViewPath.
        if ($this->mViewDir) {
            $sViewPath = $this->mViewDir . '/' . $sViewPath;
        }

        // No extension was provided, so assume '.php'.
        if (!isset(pathinfo($sViewPath)['extension'])) {
            $sViewPath .= '.php';
        }

        // If file exists, require it and die.
        if (file_exists($sViewPath)) {
            if (require_once("$sViewPath")) {
                return true;
            }
        } else {
            return false;
        }
    }

    /* --------------------------------------------------
        PUBLIC FUNCTIONS
    ---------------------------------------------------*/

    /**
     * Alias function to handle the deduction and formatting of a class
     * name based on a class file, or accepts two arguments to explicity
     * define both. Then, calls dynamic controllerFile and controllerClass
     * functions to set their properties on the curent route scope.
     *
     * User can bypass this function altogether and call controllerFile and
     * controllerClass functions explicitly, if need be.
     *
     * @param $sControllerFile
     * @param bool|false $mControllerClassOrBool
     * @return bool
     */
    public function controller($sControllerFile, $mControllerClassOrBool = false){

        if (!self::$bRouteScope){
            return $this->throwError($this->getError(4, 'controller'));
        }

        // Trim potential whitespace
        $sControllerFile = trim($sControllerFile);

        // If user supplied '.php', strip it before storing
        if (isset(pathinfo($sControllerFile)['extension'])){
            $sFileExtension = pathinfo($sControllerFile)['extension'];
            $sControllerFile = str_replace('.' . $sFileExtension, '', $sControllerFile);
        }

        // Store Controller File var
        $this->controllerFile($sControllerFile);

        /*
        | Case 1: User explicitly defined a class name to instantiate for this
        |         controller, so store it.
        | Case 2: No class name was explicitly defined, so assume the class name
        |         is equal to the name of the class file minus '.php'.
         */
        if ($mControllerClassOrBool && trim($mControllerClassOrBool) !== $sControllerFile){
            $this->controllerClass($mControllerClassOrBool);
        } else {
            /*
            | Case 1: The controller file supplied contained forward slashes. We only want to
            |         retrieve the name of the class file, so explode the string at the forward
            |         slashes and grab the value of the last index for the class name.
            | Case 2: No forward slashes, store as is.
             */
            if (stristr($sControllerFile, '/')){
                $aControllerFile = explode('/', $sControllerFile);
                $this->controllerClass($aControllerFile[count($aControllerFile) - 1]);
            } else {
                $this->controllerClass($sControllerFile);
            }
        }
    }

    /**
     * Handle Response Methods.
     *
     * @param $sMethod
     * @param $aArgs
     * @return $this|bool
     */
    public function __call($sMethod, $aArgs) {
        /*
        | Case 1: Method is being called within the Route Scope (that is, within the
        |         second parameter Closure Object of the Route method), then execute
        |         the innards.$sMethod
        | Case 2: Method is being called outside the Route Scope, so pass operations
        |         to throwError.
        */
        if (self::$bRouteScope) {

            // Retrieve the first Argument from the Argument Array.
            $mArgument = $aArgs[0];

            // Define Acceptable Methods.
            $aAcceptedMethods = array(
                'get',
                'post',
                'delete',
                'put',
                'patch',
                'response',
                'controllerFile',
                'controllerClass',
                'before',
                'beforeAll',
                'beforeChildren'
            );

            // Define Methods that are NOT faux Request Methods from aAcceptedMethods
            // like "before", "response", etc.
            $aRestMethods = array('get', 'post', 'delete', 'put', 'patch');

            /*
            | Case 1: The current sMethod is not a faux Request Method, and the Request Method of the page
            |         matches the defined sMethod, then it is a relevant REST method.
            | Case 2: The current sMethod IS a faux Request Method, we consider it to be Relevant as well.
            | Case 3: sMethod is not a relevant REST method.
             */
            if (in_array($sMethod, $aRestMethods) && $this->getRequestMethod(true) === $sMethod) {
                $bRelevantRestMethod = true;
            } elseif (!in_array($sMethod, $aRestMethods)) {
                $bRelevantRestMethod = true;
            } else {
                $bRelevantRestMethod = false;
            }

            /*
            | Case 1: Called Method is an Acceptable Method.
            | Case 2: Note an Acceptable Method.
            */
            if (in_array($sMethod, $aAcceptedMethods)) {
                if (!$this->bBuildAll && $bRelevantRestMethod || $this->bBuildAll) {
                    /*
                    | Case 1: Route in question is a Child Route.
                    | Case 2: Route in question is a Parent Route.
                    */
                    if (count(self::$aRoute) > 1) {
                        $this->aRoutes[self::$sParentRoute]['childRoutes'][self::$sRoute][$sMethod] = $mArgument;
                    } else {
                        $this->aRoutes[self::$sParentRoute][$sMethod] = $mArgument;
                    }
                    return $this;
                }
            } else {
                return $this->throwError($this->getError(5, $sMethod));
            }
        } else {
            return $this->throwError($this->getError(4, $sMethod));
        }
    }

    /**
     * Return instance of oStore object as a new stdClass,
     * in which variables can be stored.
     *
     * @param bool|false $bIndex
     * @return bool|null|stdClass
     */
    public function store($bIndex = false) {
        if ($this->oStore === null) {
            $this->oStore = new stdClass();
        }
        if ($bIndex && isset($this->oStore->{$bIndex})) {
            return $this->oStore->{$bIndex};
        } elseif (!$bIndex) {
            return $this->oStore;
        } else {
            return false;
        }
    }

    /**
     * Reset oStore variable to null, and subsequently flush
     * any data associated with the object.
     */
    public function cleanStore() {
        $this->oStore = null;
        return true;
    }

    /**
     * Detect if endpoint is requested via AJAX.
     * WARNING: This has been known to fail on servers that do not provide
     * the HTTP_X_REQUESTED_WITH Server variable. Make sure your server does
     * before relying on this function.
     *
     * @return bool
     */
    public function isAjax(){
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'){
            return true;
        } else {
            return false;
        }
    }

    public function cleanCastParams(){
        $this->aParamCasts = array();
    }

    public function castParams($aCastMap) {
        if (gettype($aCastMap) === 'array') {
            // Remove leading colon, if user supplied one to the sCastVar
            foreach ($aCastMap as $sCastVar => $sCastAs){
                if ($sCastVar[0] === ':'){
                    $aCastMap[ltrim($sCastVar, ':')] = $sCastAs;
                    unset($aCastMap[$sCastVar]);
                }
            }
            if (self::$bMethodScope) {
                return $this->throwError($this->getError(8), 'Params cannot be cast after they\'ve already been passed to the Response Method closure.');
            } elseif (self::$bRouteScope) {
                $this->aParamCasts[self::$sRoute] = $aCastMap;
            } elseif (self::$sNamespace !== null) {
                $this->aParamCasts[self::$sNamespace] = $aCastMap;
            } else {
                $this->aParamCasts[':global'] = $aCastMap;
            }
            return $this->aParamCasts;
        } else {
            return $this->throwError($this->getError(9), 'Valid Cast Types are ' . implode(', ', self::$aCastTypes));
        }
    }

    /**
     * Build Rust configuration.
     *
     * @param $aConfig
     */
    public function config($aConfig) {
        foreach ($aConfig as $sSetting => $mValue) {
            switch($sSetting) {
                case 'view_directory':
                case 'view_dir':
                    $this->mViewDir = trim($this->trimSlashes($mValue));
                    break;
                case 'controller_directory':
                case 'controller_dir':
                    $this->mControllerDir = trim($this->trimSlashes($mValue));
                    break;
                case 'controller_rust_object':
                    if (gettype($mValue) === 'string') {
                        $this->mControllerRustObject = trim($this->trimSlashes($mValue));
                    } elseif (gettype($mValue) == 'boolean' && !$mValue){
                        $this->mControllerRustObject = $mValue;
                    }
                    break;
                case 'set_request_method':
                    if (gettype($mValue) === 'string'){
                        $mValue = $mValue === 'true' ? true:false;
                    }
                    $this->bSetRequestMethod = $mValue;
                    break;
                case 'method_setter':
                    $this->sMethodSetter = trim($mValue);
                    break;
                case 'death_message':
                    $this->sDeathMessage = trim($mValue);
                    break;
                case 'dev':
                    if (gettype($mValue) === 'string'){
                        $mValue = $mValue === 'true' ? true:false;
                    }
                    $this->bDevMode = $mValue;
                    break;
                case 'build_all':
                    if (gettype($mValue) === 'string'){
                        $mValue = $mValue === 'true' ? true:false;
                    }
                    $this->bBuildAll = $mValue;
                    break;
                case 'unit_test':
                    if (gettype($mValue) === 'string'){
                        $mValue = $mValue === 'true' ? true:false;
                    }
                    $this->bUnitTest = $mValue;
                    break;
                case 'render_all_b4_otherwise':
                case 'render_all_before_otherwise':
                    if (gettype($mValue) === 'string'){
                        $mValue = $mValue === 'true' ? true:false;
                    }
                    $this->bRenderAllBeforeOtherwise = $mValue;
                    break;
                case 'all_route_file':
                    if (!isset(pathinfo($mValue)['extension'])) {
                        $this->sAllRouteFile = $mValue . '.php';
                    } else {
                        $this->sAllRouteFile = $mValue;
                    }
                    break;
                case 'index_route_file':
                    if (!isset(pathinfo($mValue)['extension'])) {
                        $this->sIndexRouteFile = $mValue . '.php';
                    } else {
                        $this->sIndexRouteFile = $mValue;
                    }
                    break;
                case 'otherwise_route_file':
                    if (!isset(pathinfo($mValue)['extension'])) {
                        $this->sOtherwiseRouteFile = $mValue . '.php';
                    } else {
                        $this->sOtherwiseRouteFile = $mValue;
                    }
                    break;
            }
        }
    }

    /**
     * Return Live Route as string.
     *
     * @return string
     */
    public function liveRoute() {
        return $this->sLiveRoute;
    }

    /**
     * Return Live Parent Route as string.
     *
     * @return string
     */
    public function liveRouteParent() {
        return $this->sLiveParentRoute;
    }

    /**
     * Re-formats and checks the user-supplied Route to see if
     * it matches the Live Route. If so, returns true.
     *
     * @param $sRoute
     * @return bool
     */
    public function isRoute($sRoute) {
        if ($this->liveRoute() === $this->trimSlashes($sRoute)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Re-formats and checks the user-supplied Parent Route to see if
     * it matches the Live Parent Route. If so, returns true.
     *
     * @param $sRouteParent
     * @return bool
     */
    public function isRouteParent($sRouteParent) {
        if ($this->liveRouteParent() === $this->trimSlashes($sRouteParent)) {
            return true;
        } else {
            return false;
        }
    }

    public function isParent(){
        if ($this->isRouteParent($this->liveRoute())){
            return true;
        } else {
            return false;
        }
    }

    /**
     * Return Query Strings
     *
     * @param bool|false $bIndex
     * @return array|bool
     */
    public static function getQueryString($bIndex = false) {
        if ($_SERVER['QUERY_STRING']) {
            $aQueries = array();
            $aVarPairs = explode('&', $_SERVER['QUERY_STRING']);
            foreach ($aVarPairs as $sVarPair) {
                $aQueries[explode('=', $sVarPair)[0]] = explode('=', $sVarPair)[1];
            }
            if ($bIndex && isset($aQueries[$bIndex])) {
                return $aQueries[$bIndex];
            } else {
                return $aQueries;
            }
        } else {
            return false;
        }
    }

    /**
     * Set Server's Request Method variable.
     * Used internally in the Serve method to set the request method of the page
     * via the Method Setter Post value; however, it's exposed for public use as well
     * for easy testing and debugging different Response Methods.
     *
     * @param bool|false $mMethodOrBool
     * @return bool
     */
    public function setRequestMethod($mMethodOrBool = false) {
        $aAcceptedMethods = array('POST', 'GET', 'PUT', 'PATCH', 'DELETE');

        if ($mMethodOrBool){
            $sRequestMethod = $mMethodOrBool;
        } elseif (isset($_SERVER[$this->sMethodSetter])) {
            $sRequestMethod = $_SERVER[$this->sMethodSetter];
        }

        if (isset($sRequestMethod) && in_array(strtoupper($sRequestMethod), $aAcceptedMethods)) {
            $_SERVER['REQUEST_METHOD'] = strtoupper($sRequestMethod);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Return Request Method
     *
     * @param bool $bLower
     * @return string
     */
    public static function getRequestMethod($bLower = false) {
        if ($bLower) {
            return strtolower($_SERVER['REQUEST_METHOD']);
        } else {
            return $_SERVER['REQUEST_METHOD'];
        }
    }

    /**
     * If Request Method is GET, return true.
     *
     * @return bool
     */
    public function isGet() {
        if ($this->getRequestMethod() === "GET") {
            return true;
        } else {
            return false;
        }
    }

    /**
     * If Request Method is POST, return true.
     *
     * @return bool
     */
    public function isPost() {
        if ($this->getRequestMethod() === "POST") {
            return true;
        } else {
            return false;
        }
    }

    /**
     * If Request Method is PUT, return true.
     *
     * @return bool
     */
    public function isPut() {
        if ($this->getRequestMethod() === "PUT") {
            return true;
        } else {
            return false;
        }
    }

    /**
     * If Request Method is PATCH, return true.
     *
     * @return bool
     */
    public function isPatch() {
        if ($this->getRequestMethod() === "PATCH") {
            return true;
        } else {
            return false;
        }
    }

    /**
     * If Request Method is DELETE, return true.
     *
     * @return bool
     */
    public function isDelete() {
        if ($this->getRequestMethod() === "DELETE") {
            return true;
        } else {
            return false;
        }
    }

    public function index($oAllFunction) {
        return $this->route('/', $oAllFunction);
    }

    /**
     * All Route Method
     * Fires before every route ever in the world.
     *
     * @param $oAllFunction
     * @return bool
     */
    public function all($oAllFunction) {
        if (self::$sNamespace !== null){
            return $this->throwError($this->getError(7));
        }
        return $this->route(':all', $oAllFunction);
    }

    /**
     * Otherwise Route Method
     * Fires when no Route matches are found
     *
     * @param $oOtherwiseFunction
     * @return bool
     */
    public function otherwise($oOtherwiseFunction) {
        if (self::$sNamespace !== null){
            return $this->throwError($this->getError(7));
        }
        return $this->route(':otherwise', $oOtherwiseFunction);
    }

    /**
     * Run Router Diagnostics.
     *
     * @param bool|true $bPrint
     * @return array
     */
    public function runDiagnostics($bPrint = true) {

        $aDiagnostic = array();
        $iTotalRouteCount = 0;

        if ($this->bBeenServed) {
            $aDiagnostic['Diagnostic Execution'] = 'Diagnostics are being executed after the Serve.';
        } else {
            $aDiagnostic['Diagnostic Execution'] = 'Diagnostics are being executed before the Serve.';
        }

        // Parent Route
        if ($this->liveRouteParent()) {
            $iTotalRouteCount = 1;
            $aDiagnostic['Parent Route'] = $this->liveRouteParent();
        }

        // Child Route
        if ($this->liveRoute()) {
            $aDiagnostic['Live Route'] = $this->liveRoute();
        }

        if (isset($this->aRoutes[$this->liveRouteParent()]['childRoutes']) && $iChildRouteCount = count($this->aRoutes[$this->liveRouteParent()]['childRoutes'])) {
            $iTotalRouteCount = $iTotalRouteCount + $iChildRouteCount;
            $aDiagnostic['Child Route Count'] = $iChildRouteCount;
        }

        $aDiagnostic['Child Routes'] = array_keys($this->aRoutes[$this->liveRouteParent()]['childRoutes']);

        $aDiagnostic['Route Family'] = $this->aRoutes[$this->liveRouteParent()];

        $aDiagnostic['Total Route Family Count'] = $iTotalRouteCount;

        if ($this->iServeTime) {
            $aDiagnostic['Serve Time'] = array('raw' => $this->iServeTime,
                'milliseconds' => round($this->iServeTime * 1000),
                'standard' => date('H:i:s', $this->iServeTime));
        }

        $aDiagnostic['Memory Usage'] = array('bytes' => memory_get_usage(),
            'kilobytes' => round(memory_get_usage() / 1024, 2),
            'megabytes' => round(memory_get_usage() / 1048576, 2));

        $aDiagnostic['Storage Object'] = $this->store();

        if ($bPrint) {
            if (!$this->bBeenServed) {
                $aDiagnostic['NOTE'] = "Diagnostics should be run WITHIN a Response Method you're attempting to evaluate for more comprehensive results.";
            }
            echo "<pre>";
            print_r($aDiagnostic);
            echo "</pre>";
            return true;
        } else {
            return $aDiagnostic;
        }

    }

    public function group($sNamespace, $oClosure){
        if ($oClosure instanceof \Closure){
            self::$sNamespace = $this->trimSlashes($sNamespace);

            $oClosure = $oClosure->bindTo(self::$oInstance);
            $oClosure();

            self::$sNamespace = null;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Build Route.
     *
     * @param $sRoute
     * @param $mRouteFunctionOrResponse
     * @return bool
     */
    public function route($sRoute, $mRouteFunctionOrResponse) {

        self::$sRoute = trim($sRoute);

        // If this route is being defined within a Namespace, then
        // prepend the route with that Namespace.
        if (self::$sNamespace !== null){
            self::$sRoute = self::$sNamespace . '/' . $this->trimSlashes(self::$sRoute);
        }

        /*
        | Case 1: Route is the root Route (that is, a forward slash '/')
        | Case 2: Route is not the root.
        */
        if (self::$sRoute === '/') {
            self::$aRoute = array();
            self::$aRoute[] = '/';
        } else {
            self::$sRoute = $this->trimSlashes(self::$sRoute);
            self::$aRoute = explode('/', self::$sRoute);
        }

        // Define Parent Route as first index of aRoute.
        self::$sParentRoute = self::$aRoute[0];

        /*
        | Case 1: Parent URI matches the Parent Route, or is an "Otherwise" or "All" route,
        |         so it is considered to be a Relevant Route.
        | Case 2: Case 1 is false, so the route is not considered to be a Relevant Route.
        */
        if ($this->getUriParent() === self::$sParentRoute || self::$sParentRoute === ':otherwise' || self::$sParentRoute === ':all') {
            $bRelevantRoute = true;
        } else {
            $bRelevantRoute = false;
        }

        /*
        | Case 1: We are in DevMode, so build all routes in existence.
        | Case 2: We aren't in DevMode and the Route is considered to be
        |         relevant, so build that Route. Routes deemed as not
        |         relevant will not be built to save memory and process
        |         time.
        */
        if ($this->bBuildAll || !$this->bBuildAll && $bRelevantRoute) {

            // Parent Route has not been added to aRoutes, so add it.
            if (!array_key_exists(self::$sParentRoute, $this->aRoutes)) {
                $this->aRoutes[self::$sParentRoute] = array();
            }

            // The route contains more than one argument, therefore is considered
            // a Child Route. Add it to the childRoutes array.
            if (count(self::$aRoute) > 1) {
                $aChildRoute = array();

                foreach (self::$aRoute as $iPosition => $sArgument) {
                    $aChildRoute['args'][$iPosition] = array();
                    $aChildRoute['args'][$iPosition]['arg'] = $sArgument;
                    /*
                    | Case 1: Argument contains a colon, so we consider the
                    |         argument to be dynamic.
                    | Case 2: Argument doesn't contain a colon, so we consider
                    |         it to be static.
                    */
                    if (strpos($sArgument, ':') !== false) {
                        $aChildRoute['args'][$iPosition]['type'] = 'dynamic';
                    } else {
                        $aChildRoute['args'][$iPosition]['type'] = 'static';
                    }
                }

                // Insert aChildRoute array into the childRoute Array on
                // an Index equal to the full Route name.
                $this->aRoutes[self::$sParentRoute]['childRoutes'][self::$sRoute] = $aChildRoute;
            }

            // Set bRouteScope to true so __call knows it's being fired
            // within the Route Scope.
            self::$bRouteScope = true;

            /*
            | Case 1: $mRouteFunctionOrResponse is an instance of Closure.
            | Case 2: $mRouteFunctionOrResponse is a string, so build a
            |         response method using the string.
            | Case 3: $mRouteFunctionOrResponse is not of an acceptable type,
            |         so pass operations to throwError.
            */
            if ($mRouteFunctionOrResponse instanceof \Closure) {
                $mRouteFunctionOrResponse = $mRouteFunctionOrResponse->bindTo(self::$oInstance);
                $mRouteFunctionOrResponse();
            } elseif (gettype($mRouteFunctionOrResponse) === 'string') {
                self::$oInstance->response($mRouteFunctionOrResponse);
            } else {
                $this->throwError($this->getError(2));
            }

            // Reset bRouteScope to false so any subsequent calls to __call
            // outside the Route Scope will not be rendered.
            self::$bRouteScope = false;

            return true;

        } else {
            return false;
        }
    }

    /**
     * Return array of all registered Routes.
     * Note: This will only return Relevant Routes and their Relevant Methods if not in
     *       Dev Mode. To see ALL Routes and Response Methods, regardless or Relevance,
     *       turn on Dev Mode.
     * @param bool $mRouteOrFalse
     * @return array
     */
    public function getRoutes($mRouteOrFalse = false) {
        if ($mRouteOrFalse) {
            return $this->aRoutes[$mRouteOrFalse];
        } else {
            return $this->aRoutes;
        }
    }

    public function cleanRoutes($mIndexOrBool = false){
        $this->aRoutes = array();
    }

    /**
     * Serve Routes from a directory, as opposed to a single Route file.
     *
     * @param $sDirectory
     * @return bool
     */
    public function serveFromDirectory($sDirectory) {
        $this->sAllRoutePath = $sDirectory . '/' . $this->sAllRouteFile;
        $this->sOtherwiseRoutePath = $sDirectory . '/' . $this->sOtherwiseRouteFile;
        $this->sIndexRoutePath = $sDirectory . '/' . $this->sIndexRouteFile;

        /*
        | Case 1: This is a root request ('/'), so sRoutePath is equal to
        |         sIndexRoutePath.
        | Case 2: Not a root request, so determine sRoutePath normally.
        */
        if ($this->rootRequest()){
            $this->sRoutePath = $this->sIndexRoutePath;
        } else {
            $this->sRoutePath = $sDirectory . '/' . $this->getUriParent() . '.php';
        }

        /*
        | Case 1: The directory supplied to the serveFromDirectory exists.
        | Case 2: The directory supplied does not exist, pass to throwError.
        */
        if (file_exists($sDirectory)) {
            if (file_exists($this->sAllRoutePath)) {
                self::$oInstance->all(function(){
                    require_once("$this->sAllRoutePath");
                });
            }

            if (file_exists($this->sOtherwiseRoutePath)) {
                self::$oInstance->otherwise(function(){
                    require_once("$this->sOtherwiseRoutePath");
                });
            }

            /*
            | Case 1: If this is a root request ('/'), then require sRoutePath
            |         withing a Route Scope.
            | Case 2: If this is not a root request, then require sRoutePath
            |         within a Namespace Scope.
            */
            if ($this->rootRequest()){
                if (file_exists($this->sRoutePath)) {
                    self::$oInstance->index(function(){
                        require_once("$this->sRoutePath");
                    });
                }
            } else {
                if (file_exists($this->sRoutePath)) {
                    self::$oInstance->group($this->getUriParent(), function () {
                        require_once("$this->sRoutePath");
                    });
                }
            }

            return $this->serve();
        } else {
            return $this->throwError($this->getError(3, $sDirectory));
        }
    }

    /**
     * Determine the current request, find a suitable registered Route,
     * and dispatch accordingly.
     *
     * @param bool|false $mSimulateUri
     * @return bool
     */
    public function serve($mSimulateUri = false) {
        // If user indicated the Request Method should be set using the methodSetter value,
        // do that here.
        if ($this->bSetRequestMethod) {
            self::setRequestMethod();
        }

        // Use the static $bBeenServed variable to ensure we never accidentally "serve" twice,
        // unless this is a unit test, where serve may be executed more than once
        if (!$this->bBeenServed || $this->bUnitTest) {

            // Open the Output Buffer
            ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE | PHP_OUTPUT_HANDLER_REMOVABLE);

            // Begin counter at beginning of serve to record Serve Time
            $iBeginServe = microtime(time());

            // Set bBeenServed to true so we never get here again.
            $this->bBeenServed = true;

            // If this is a unit test and a simulation URI has been provided,
            // re-assign the server's Request URI variable to match the simulate.
            if ($this->bUnitTest || $mSimulateUri) {
                $_SERVER['REQUEST_URI'] = $mSimulateUri;
            }

            $aUri = $this->getUri();

            // If this is a root request, that is, a request to '/',
            // then assign the aUri's 0 index as '/'.
            if ($this->rootRequest()) {
                $aUri[0] = '/';
            }

            // sParentRoute is always equal to the first URI parameter.
            $sParentRoute = $aUri[0];

            /*
            | Case 1: The specified Parent Route was built by user, and it is not an "All" or
            |         "Otherwise" Route.
            | Case 2: No Parent Route was found, so forceOtherwise.
            */
            if (isset($this->aRoutes[$sParentRoute]) && $sParentRoute !== ':all' && $sParentRoute !== ':otherwise') {
                $aParent = $this->aRoutes[$sParentRoute];
            } else {
                return $this->forceOtherwise();
            }

            /*
            | Case 1: The determined Parent Route has Child Routes.
            | Case 2: Does not have Child Routes.
            */
            if (isset($aParent['childRoutes'])) {
                $mChildren = $aParent['childRoutes'];
            } else {
                $mChildren = false;
            }

            /*
            | Prepare variables for the While loop that will eventually yield a
            | Route Match, if one exists.
            |
            | iTotalRouteAttempts: The total amount of attempts we can make to find
            |                      a Route Match.
            |
            | iPossibleRoutes:     Number originally equal to iTotalRouteAttempts, decreases
            |                      by one every itteration through the While.
            |
            | iRouteAttempt:       Number of attempts to find a Route, increases by one every
            |                      itteration through the While.
            */
            $iTotalRouteAttempts = count($aUri);
            $iPossibleRoutes = $iTotalRouteAttempts;
            $iRouteAttempt = 0;

            // While the number of Total Route attempts we are allowed to make is more than
            // or equal to the number of attempts we HAVE made.
            while ($iTotalRouteAttempts >= $iRouteAttempt) {
                // Assume no Route match is found until proven otherwise.
                $bFoundRouteMatch = false;

                // If we determined the Parent Route has Child Routes.
                if ($mChildren) {
                    foreach ($mChildren as $sRoute => $aChild) {

                        // If the number of arguments in this Child Route is
                        // equal to the number of Possible Routes.
                        if (count($aChild['args']) === $iPossibleRoutes) {

                            // Assume all Static Arguments match until proven otherwise
                            $bAllStaticArgsMatch = true;

                            // For each Child Route Argument, get the intended URI position
                            // of that Argument, and the value it expects to be present
                            // in that position.
                            foreach ($aChild['args'] as $iPosition => $aArgument) {
                                // Retrieve only Static Arguments.
                                if ($aArgument['type'] === 'static') {
                                    // If even one Static Argument does not match its
                                    // equivalent URI position, the Route is not a match.
                                    if ($aUri[$iPosition] !== $aArgument['arg']) {
                                        $bAllStaticArgsMatch = false;
                                    }
                                }
                            }

                            // If all Static Arguments matched their equivalent URI position,
                            // we've found a Route Match.
                            if ($bAllStaticArgsMatch) {
                                // Persist necessary Child Route data
                                $this->sLiveParentRoute = $sParentRoute;
                                $this->sLiveRoute = $sRoute;

                                // Log amount of time it took to find this Route
                                $this->iServeTime = microtime(time()) - $iBeginServe;

                                // Render this Route
                                if ($this->render()) {
                                    return true;
                                } else {
                                    return $this->forceOtherwise();
                                }

                                $bFoundRouteMatch = true;
                            }

                            // Route found, break foreach.
                            if ($bFoundRouteMatch) {
                                break;
                            }

                        }
                    }
                }

                /*
                | Case 1: We found a Route Match, break the While.
                | Case 2: We never found a Route Match, and this is
                |         the last Possible Route, so fallback to the
                |         parent.
                */
                if ($bFoundRouteMatch) {
                    break;
                } else if ($iPossibleRoutes === 1) {
                    // Persist necessary Parent Route data
                    $this->sLiveParentRoute = $sParentRoute;
                    $this->sLiveRoute = $sParentRoute;

                    // Save amount of time it took to find this Route
                    $this->iServeTime = microtime(time()) - $iBeginServe;

                    // Render this Route
                    if ($this->render()) {
                        return true;
                    } else {
                        return $this->forceOtherwise();
                    }

                    break;
                }

                // Increment and Decrement values accordingly.
                $iRouteAttempt++;
                $iPossibleRoutes--;

            }
        }
    }
}

/**
 * Class RustyTools
 */
abstract class RustyTools
{

    /**
     * Return error associated with specific code
     *
     * @param $iErrorCode
     * @param bool|false $mDynamicErrorVar
     * @return bool
     */
    protected function getError($iErrorCode, $mDynamicErrorVar = false, $mSecondaryDynamicErrorVar = false) {
        $aErrors = array(
            1 => "Utility Functions must be called within a Response Method Scope.",
            2 => "Second parameter of a Route function must be either a string or Closure Object.",
            3 => "Directory '$mDynamicErrorVar' does not exist.",
            4 => "Method '$mDynamicErrorVar' cannot be defined outside a Route Scope.",
            5 => "Method '$mDynamicErrorVar' is not an Acceptable Response Method.",
            6 => "Middleware response methods 'beforeAll' and 'beforeChildren' are reserved for use by Parent Routes only.",
            7 => "All Route and Otherwise Route may not be defined within a namespace.",
            8 => "Method 'castParams' cannot be called within a Response Method Scope.",
            9 => "Method 'castParams' only accepts an associative array of sParam => sCastType.",
            10 => "Controller directory '$mDynamicErrorVar' does not exist.",
            11 => "Call to method '$mSecondaryDynamicErrorVar' on class '$mDynamicErrorVar' was unsuccessful."
        );

        if (isset($aErrors[$iErrorCode])) {
            return $aErrors[$iErrorCode];
        } else {
            return false;
        }
    }

    /**
     * Remove leading and trailing slashes from a string
     *
     * @param $sString
     * @return string
     */
    protected function trimSlashes($sString) {
        if ($sString !== '/') {
            return trim(ltrim(rtrim(trim($sString), '/'), '/'));
        } else {
            return $sString;
        }
    }

    /**
     * Return Request Method
     *
     * @param bool|false $bLower
     * @return string
     */
    protected function request($bLower = false) {
        if ($bLower) {
            return strtolower($_SERVER['REQUEST_METHOD']);
        } else {
            return $_SERVER['REQUEST_METHOD'];
        }
    }

    /**
     * Get Parent URI segment
     *
     * @return mixed
     */
    protected function getUriParent() {
        return $this->getUri()[0];
    }

    /**
     * Get Child URI Segment(s) as an array
     *
     * @return array|bool
     */
    protected function getUriChildren() {
        if (isset($this->getUri()[1])) {
            return array_slice($this->getUri(), 1);
        } else {
            return false;
        }
    }

    /**
     * Return the Request URI
     *
     * @param bool|false $mIndexOrReturnTypeOrBool
     * @return array|string
     */
    protected function getUri($mIndexOrReturnTypeOrBool = false) {
        $aRequest = array();

        // Remove Query Strings so they don't interfere with Routing.
        $sRequestUri = explode('?', $_SERVER['REQUEST_URI'])[0];

        /**
         * @if Is Root Request, manually set 0 index.
         * @else Explode Request URI into array, filter out empties, 0-index it.
         */
        if ($this->rootRequest()) {
            $aRequest[0] = '/';
        } else {
            $aRequest = array_values(array_filter(array_map('strval', explode('/', $sRequestUri)), 'strlen'));
        }

        /**
         * @switch Type of Variable $mIndexOrReturnTypeOrBool
         *   @case integer, return array item by that index.
         *   @case string:
         *         -- @switch Value of $mIndexOrReturnTypeOrBool
         *              @case string: return Request as String.
         *              @case array: return Request as Array.
         * @default Return Request as Array.
         */
        switch (gettype($mIndexOrReturnTypeOrBool)) {
            case 'integer':
                return $aRequest[$mIndexOrReturnTypeOrBool];
                break;
            case 'string':
                switch (strtolower($mIndexOrReturnTypeOrBool)) {
                    case 'string':
                        return implode('/', $aRequest);
                        break;
                    case 'array':
                        return $aRequest;
                        break;
                }
                break;
            default:
                return $aRequest;
                break;
        }
    }

    /**
     * Return true if Request URI is '/'
     *
     * @return bool
     */
    protected function rootRequest() {
        if ($_SERVER['REQUEST_URI'] === '/') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Used in Rust::forRoute, Rust::forRouteFamily, and Rust::forRouteChildren
     * to determine whether or not Route being supplied is intended to
     * be an exception. That is, the first character is a '!'.
     *
     * @param $sRoute
     * @return bool|string
     */
    protected function routeException($sRoute){
        if ($sRoute[0] === '!') {
            return ltrim($sRoute, '!');
        } else {
            return false;
        }
    }

    protected function getHungarianCast($sString){
        $aValidTypes = [
            'i' => 'int',
            's' => 'string',
            'b' => 'boolean',
            'f' => 'float',
            'o' => 'object',
            'a' => 'array'
        ];

        if (ctype_lower($sString[0]) && in_array($sString[0], array_keys($aValidTypes)) && ctype_upper($sString[1])){
            return $aValidTypes[$sString[0]];
        } else {
            return false;
        }

    }

}