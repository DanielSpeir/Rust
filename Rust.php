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
     * Holds bool determining Live Route Type
     * (parent = true, child = false)
     *
     * If "serve" renders a Parent Route, this remains
     * true. If "serve" renders a Child Route, this becomes
     * false.
     *
     * @var bool
     */
    private static $bParentRoute = true;

    /**
     * Array containing all built Routes and their properties
     *
     * @var array
     */
    private static $aRoutes = array();

    /**
     * Holds Live Route Name (example: 'articles/:id')
     *
     * @var string
     */
    private static $sLiveRoute = "";

    /**
     * Holds Live Parent Route as string (example: 'articles')
     *
     * @var string
     */
    private static $sLiveParentRoute = "";

    /**
     * Null Storage Object. Set to new instance of stdClass
     * on getStore() call.
     *
     * @var null
     */
    private static $oStore = null;

    /**
     * Dictates whether the "all" route will be rendered
     * before the "otherwise" route. Default is false.
     *
     * @var bool
     */
    private static $bRenderAllBeforeOtherwise = false;

    /**
     * This is set to true when "serve" method is called for the
     * first time. We check this on every "serve" to ensure we never
     * serve twice. Failsafe to prevent humanity's downfall.
     *
     * @var bool
     */
    private static $bBeenServed = false;

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

    /* --------------------------------------------------
        CONFIG VARS
    ---------------------------------------------------*/

    /**
     * CONFIG VAR
     * Holds Defined View Path
     *
     * @var bool
     */
    private static $mViewPath = false;

    /**
     * Holds boolean determining whether or not
     * Request Method will automatically be set using
     * the methodSetter.
     *
     * @var bool
     */
    private static $bSetRequestMethod = true;

    /**
     * CONFIG VAR
     * Holds name of input used to set method.
     *
     * @var string
     */
    private static $sMethodSetter = ":method";

    /**
     * CONFIG VAR
     * Holds message printed to page as last-resort on Route Death.
     *
     * @var string
     */
    private static $sDeathMessage = "The requested route could not be rendered.";

    /**
     * CONFIG VAR
     * Holds boolean indicating Dev or Prod mode
     *
     * @var bool
     */
    private static $bDevMode = true;

    /**
     * CONFIG VAR
     * Holds name of file where user intends to define an "otherwise"
     * route. (Only usage is with serveFromDirectory functionality)
     *
     * @var string
     */
    private static $sOtherwiseRouteFile = "otherwise.php";

    /**
     * CONFIG VAR
     * Holds name of file where user intends to define an "all"
     * route. (Only usage is with serveFromDirectory functionality)
     *
     * @var string
     */
    private static $sAllRouteFile = "all.php";

    /* --------------------------------------------------
        DIAGNOSTIC VARS
    ---------------------------------------------------*/

    /**
     * Holds amount of time it took to Serve a Route.
     *
     * @var int
     */
    private static $iServeTime = 0;

    /**
     * Holds the amount of time it took to Render a Route.
     *
     * @var int
     */
    private static $iRenderTime = 0;

    /**
     * Get Instance of Rust Object
     *
     * @return Rust
     */
    public static function getRouter(){
        if (self::$oInstance === null){
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
     */
    private static function throwError($sError, $mMoreInformation = false){
        if (self::$bDevMode){
            echo "<b>Rust Error: </b> $sError";
            if ($mMoreInformation){
                echo "<br/><br/><b>More Information:</b> $mMoreInformation";
            }
            echo "<br/><br/><hr/><b>Backtrace:</b><br/>";
            echo "<pre>";
            print_r(debug_backtrace()[1]);
            echo "</pre>";
            die();
        }
    }

    /**
     * Check POST value of the sMethodSetter and change the Server's
     * Request Method based on the value, if it's acceptable.
     */
    private static function setRequestMethod(){
        $aAcceptedMethods = array('POST', 'GET', 'PUT', 'PATCH', 'DELETE');
        if (isset($_POST[self::$sMethodSetter]) && in_array(strtoupper($_POST[self::$sMethodSetter]), $aAcceptedMethods)){
           $_SERVER['REQUEST_METHOD'] = $_POST[self::$sMethodSetter];
        }
    }

    /**
     * Render response
     *
     * @param $mRequest
     * @param $aArguments
     */
    private static function renderResponse($mRequest, $aArguments){
        // Define bMethodScope to be true while rendering a response
        // so all Utility Functions may be called within.
        self::$bMethodScope = true;
        switch (gettype($mRequest)){
            case 'object':
                call_user_func_array($mRequest, $aArguments);
                break;
            case 'string':
                echo $mRequest;
                break;
        }
        // Re-define bMethodScope to be false so all subsequent calls
        // to Utility Functions outside the Method Scope are not rendered.
        self::$bMethodScope = false;
    }

    /**
     * Retrieve and return array of arguments supplied to a Route.
     *
     * @param $aRoute
     * @return array
     */
    private static function getRouteArguments($aRoute){
        // Build arguments into array that we can pass to our Closure Objects
        // using call_user_func_array().
        $aArguments = array();
        if (isset($aRoute['args'])){
            foreach ($aRoute['args'] as $iPosition => $aArgument){
                if ($aArgument['type'] === 'dynamic'){
                    $aArguments[str_replace(':', '', $aArgument['arg'])] = self::getUri($iPosition);
                }
            }
        }
        // Find Magic Arguments, if any.
        if ($aMagicArguments = array_slice(self::getUri(), count($aArguments) + 1)){
            $aArguments[':magicArguments'] = $aMagicArguments;
        } else {
            $aArguments[':magicArguments'] = false;
        }
        return $aArguments;
    }

    /**
     * Render Route
     *
     * @param $aRoute
     * @param bool|false $bAll
     *        Note: $bAll will be truthy whenever an ":all" Route exists and is rendered.
     *              It prevents the "render" function from spiralling recursively into
     *              a painful, fiery death.
     * @param bool|false $bOtherwise
     *        Note: $bOtherwise will be truthy whenever an ":otherwise" Route exists and is
     *              rendered. The "render" function must know when it's rendering the ":otherwise"
     *              route so it can determine whether or not to fire the ":all" before it.
     */
    private static function render($aRoute, $bAll = false, $bOtherwise = false){

        // We assume $bRenderOtherwise is true until proven otherwise.
        $bRenderOtherwise = true;

        // Retrieve the arguments for this Route as an array.
        $aArguments = self::getRouteArguments($aRoute);

        /**
         * TODO: Whenever a Route with no defined methods is rendered,
         *       it falls through the "render" function to the bottom,
         *       where, if it catches nothing along the way, renders the
         *       "otherwise" route, or supplies the deathMessage. Because of this,
         *       whenever an ":all" Route has been provided, it still fires before
         *       the method-less Route, believing it to be a valid Route,
         *       and therefore fires before what is eventually an "Otherwise"
         *       Render, regardless of whether the user wanted the "All" Route
         *       to render before otherwise.
         */


        // Determine whether or not the "All" route should be fired.
        if ((!$bAll && isset(self::$aRoutes[':all'])) && ($bOtherwise && self::$bRenderAllBeforeOtherwise || !$bOtherwise)){
            self::render(self::$aRoutes[':all'], true);
        }

        // If current route is not the "All" Route AND is a Parent Route AND "before" index
        // is set on the Parent, fire.
        if (!$bAll && self::$bParentRoute && isset(self::$aParentRoute['before'])) {
            self::renderResponse(self::$aParentRoute['before'], $aArguments);
            $bRenderOtherwise = false;
        }

        // If current route is a Child Route AND "beforeChildren" index is set on the Parent, fire.
        if (!$bAll && !self::$bParentRoute && isset(self::$aParentRoute['beforeChildren'])) {
            self::renderResponse(self::$aParentRoute['beforeChildren'], $aArguments);
            $bRenderOtherwise = false;
        }

        // If "beforeAll" index is set on Parent Route, fire.
        if (!$bAll && isset(self::$aParentRoute['beforeAll'])) {
            self::renderResponse(self::$aParentRoute['beforeAll'], $aArguments);
            $bRenderOtherwise = false;
        }

        // If current route is a Child Route OR is the "All" Route AND "before" index is
        // set on the Child, fire.
        if ((!self::$bParentRoute || $bAll) && isset($aRoute['before'])){
            self::renderResponse($aRoute['before'], $aArguments);
            $bRenderOtherwise = false;
        }

        // Fire request-specific methods.
        if (isset($aRoute[self::request(true)])){
            self::renderResponse($aRoute[self::request(true)], $aArguments);
            $bRenderOtherwise = false;
        } elseif (isset($aRoute['response'])){
            self::renderResponse($aRoute['response'], $aArguments);
            $bRenderOtherwise = false;
        }

        // If this is not an "All" render AND $bRenderOtherwise is
        // still truthy, then forceOtherwise.
        if (!$bAll && $bRenderOtherwise){
            self::forceOtherwise();
        }

        // If this is not an "All" render, then die after the response.
        if (!$bAll) {
            die();
        }

    }

    /* --------------------------------------------------
        PUBLIC UTILITY FUNCTIONS
        Note: Utility Functions are restricted to
              use within the Response Method Scope.
    ---------------------------------------------------*/

    /**
     * UTILITY FUNCTION
     *
     * Redirect
     *
     * @param $sLocation
     * @param bool $mBoolOrResponseCode
     */
    public static function redirect($sLocation, $mBoolOrResponseCode = false){

        if (!self::$bMethodScope){
            self::throwError(self::getError(1));
        }

        switch (gettype($mBoolOrResponseCode)){
            case 'boolean':
                if ($mBoolOrResponseCode){
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

        header('Location: ' . $sLocation, true, $iResponseCode);
        die();
    }

    public static function json(){
        header('Content-Type: application/json');
        header('Accept: application/json');
    }

    /**
     * UTILITY FUNCTION
     *
     * Fires supplied Closure Object for all members of a Route Family.
     * That is, for the Parent and all Child Routes of the provided Parent.
     *
     * Accepts String or Array of Route Parents.
     *
     * @param $mRouteParent
     * @param $oFunction
     * @return bool
     */
    public static function forRouteFamily($mRouteParent, $oFunction){

        if (!self::$bMethodScope){
            self::throwError(self::getError(1));
        }

        switch (gettype($mRouteParent)){
            case 'string':
                if (self::isRouteParent($mRouteParent) && is_callable($oFunction)){
                    $oFunction();
                } else {
                    return false;
                }
                break;
            case 'array':
                $mRouteParent = array_map('self::trimSlashes', $mRouteParent);
                $sProvidedParent = $mRouteParent[array_search(self::liveRouteParent(), $mRouteParent)];
                if (in_array(self::liveRouteParent(), $mRouteParent) && self::isRouteParent($sProvidedParent) && is_callable($oFunction)){
                    $oFunction();
                } else {
                    return false;
                }
                break;
            default:
                return false;
                break;
        }
    }

    /**
     * UTILITY FUNCTION
     *
     * Fires supplied Closure Object for all Children of a Route Family.
     * That is, for all Child Routes of the provided Parent.
     *
     * Accepts String or Array of Route Parents.
     *
     * @param $mRouteParent
     * @param $oFunction
     * @return bool
     */
    public static function forRouteChildren($mRouteParent, $oFunction){

        if (!self::$bMethodScope){
            self::throwError(self::getError(1));
        }

        switch (gettype($mRouteParent)){
            case 'string':
                if (self::isRouteParent($mRouteParent) && !self::isRoute($mRouteParent) && is_callable($oFunction)){
                    $oFunction();
                } else {
                    return false;
                }
                break;
            case 'array':
                $mRouteParent = array_map('self::trimSlashes', $mRouteParent);
                $sProvidedParent = $mRouteParent[array_search(self::liveRouteParent(), $mRouteParent)];
                if (in_array(self::liveRouteParent(), $mRouteParent) && self::isRouteParent($sProvidedParent) && !self::isRoute($sProvidedParent) && is_callable($oFunction)){
                    $oFunction();
                } else {
                    return false;
                }
                break;
            default:
                return false;
                break;
        }
    }

    /**
     * UTILITY FUNCTION
     *
     * Fires supplied closure object only for Route defined.
     *
     * Accepts String or Array of Routes.
     *
     * @param $mRoute
     * @param $oFunction
     * @return bool
     */
    public static function forRoute($mRoute, $oFunction){

        if (!self::$bMethodScope){
            self::throwError(self::getError(1));
        }

        switch (gettype($mRoute)) {
            case 'string':
                if (self::isRoute($mRoute) && is_callable($oFunction)) {
                    $oFunction();
                } else {
                    return false;
                }
                break;
            case 'array':
                if (in_array(self::liveRoute(), array_map('self::trimSlashes', $mRoute)) && is_callable($oFunction)) {
                    $oFunction();
                } else {
                    return false;
                }
                break;
            default:
                return false;
                break;
        }
    }

    /**
     * UTILITY FUNCTION
     *
     * Return instance of oStore object as a new stdClass,
     * in which variables can be stored.
     *
     * @param bool|false $bIndex
     * @return bool|null|stdClass
     */
    public static function getStore($bIndex = false){

        if (!self::$bMethodScope){
            self::throwError(self::getError(1));
        }

        if (self::$oStore === null) {
            self::$oStore = new stdClass();
        }
        if ($bIndex && isset(self::$oStore->{$bIndex})) {
            return self::$oStore->{$bIndex};
        } elseif (!$bIndex) {
            return self::$oStore;
        } else {
            return false;
        }
    }

    /**
     * UTILITY FUNCTION
     *
     * Reset oStore variable to null, and subsequently flush
     * any data associated with the object.
     */
    public static function flushStore(){

        if (!self::$bMethodScope){
            self::throwError(self::getError(1));
        }

        self::$oStore = null;
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
    public static function renderView($sViewPath, $mVariables = false){

        if (!self::$bMethodScope){
            self::throwError(self::getError(1));
        }

        // $aVariables are set and are an array, so loop through
        // and cast indexes as variables to be accessible by view.
        if ($mVariables && gettype($mVariables) === 'array'){
            foreach ($mVariables as $sKey => $sValue){
                ${$sKey} = $sValue;
            }
        }

        // View Path was set, so prepend it to passed-in sViewPath.
        if (self::$mViewPath){
            $sViewPath = self::$mViewPath . '/' . $sViewPath;
        }

        // No extension was provided, so assume '.php'.
        if (!isset(pathinfo($sViewPath)['extension'])){
            $sViewPath .= '.php';
        }

        // If file exists, require it and die.
        if (file_exists($sViewPath)){
            require_once($sViewPath);
            die();
        } else {
            return false;
        }
    }

    /* --------------------------------------------------
        PUBLIC FUNCTIONS
    ---------------------------------------------------*/

    /**
     * Handle Response Methods.
     *
     * @param $sMethod
     * @param $aArgs
     * @return $this|bool
     */
    public function __call($sMethod, $aArgs){
        /*
        | Case 1: Method is being called within the Route Scope (that is, within the
        |         second parameter Closure Object of the Route method), then execute
        |         the innards.
        | Case 2: Method is being called outside the Route Scope, so pass operations
        |         to throwError.
        */
        if (self::$bRouteScope) {

            // Retrieve the first Argument from the Argument Array.
            $mArgument = $aArgs[0];

            // Define Acceptable Methods.
            $aAcceptedMethods = array('get',
                'post',
                'delete',
                'put',
                'patch',
                'controller',
                'response',
                'before',
                'beforeAll',
                'beforeChildren'
            );

            // Define Methods that are NOT faux Request Methods from aAcceptedMethods
            // like "before", "response", etc.
            $aRestMethods = array('get', 'post', 'delete', 'put', 'patch');

            /**
            | Case 1: The current sMethod is not a faux Request Method, and the Request Method of the page
            |         matches the defined sMethod, then it is a relevant REST method.
            | Case 2: The current sMethod IS a faux Request Method, we consider it to be Relevant as well.
            | Case 3: sMethod is not a relevant REST method.
             */
            if (in_array($sMethod, $aRestMethods) && self::requestMethod(true) === $sMethod){
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
                if (!self::$bDevMode && $bRelevantRestMethod || self::$bDevMode) {
                    /*
                    | Case 1: Route in question is a Child Route.
                    | Case 2: Route in question is a Parent Route.
                    */
                    if (count(self::$aRoute) > 1) {
                        self::$aRoutes[self::$sParentRoute]['childRoutes'][self::$sRoute][$sMethod] = $mArgument;
                    } else {
                        self::$aRoutes[self::$sParentRoute][$sMethod] = $mArgument;
                    }
                    return $this;
                }
            } else {
                self::throwError(self::getError(5, $sMethod));
            }
        } else {
            self::throwError(self::getError(4, $sMethod));
        }
    }

    /**
     * Build Rust configuration.
     *
     * @param $aConfig
     */
    public static function config($aConfig){
        foreach ($aConfig as $sSetting => $mValue){
            switch($sSetting){
                case 'viewPath':
                    self::$mViewPath = trim(self::trimSlashes($mValue));
                    break;
                case 'setRequestMethod':
                    if ($mValue === 'true'){
                        self::$bSetRequestMethod = true;
                    } else {
                        self::$bSetRequestMethod = false;
                    }
                    break;
                case 'methodSetter':
                    self::$sMethodSetter = trim($mValue);
                    break;
                case 'deathMessage':
                    self::$sDeathMessage = trim($mValue);
                    break;
                case 'devMode':
                    if ($mValue === 'true'){
                        self::$bDevMode = true;
                    } else {
                        self::$bDevMode = false;
                    }
                    break;
                case 'allRouteFile':
                    if (!isset(pathinfo($mValue)['extension'])){
                        self::$sAllRouteFile = $mValue . '.php';
                    } else {
                        self::$sAllRouteFile = $mValue;
                    }
                    break;
                case 'otherwiseRouteFile':
                    if (!isset(pathinfo($mValue)['extension'])){
                        self::$sOtherwiseRouteFile = $mValue . '.php';
                    } else {
                        self::$sOtherwiseRouteFile = $mValue;
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
    public static function liveRoute(){
        return self::$sLiveRoute;
    }

    /**
     * Return Live Parent Route as string.
     *
     * @return string
     */
    public static function liveRouteParent(){
        return self::$sLiveParentRoute;
    }

    /**
     * Re-formats and checks the user-supplied Route to see if
     * it matches the Live Route. If so, returns true.
     *
     * @param $sRoute
     * @return bool
     */
    public static function isRoute($sRoute){
        if (self::liveRoute() === self::trimSlashes($sRoute)){
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
    public static function isRouteParent($sRouteParent){
        if (self::liveRouteParent() === self::trimSlashes($sRouteParent)){
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
    public static function getQueryString($bIndex = false){
        if ($_SERVER['QUERY_STRING']) {
            $aQueries = array();
            $aVarPairs = explode('&', $_SERVER['QUERY_STRING']);
            foreach ($aVarPairs as $sVarPair) {
                $aQueries[explode('=', $sVarPair)[0]] = explode('=', $sVarPair)[1];
            }
            if ($bIndex && isset($aQueries[$bIndex])){
                return $aQueries[$bIndex];
            } else {
                return $aQueries;
            }
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
    public static function requestMethod($bLower = false){
        if ($bLower){
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
    public static function isGet(){
        if (self::requestMethod() === "GET"){
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
    public static function isPost(){
        if (self::requestMethod() === "POST"){
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
    public static function isPut(){
        if (self::requestMethod() === "PUT"){
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
    public static function isPatch(){
        if (self::requestMethod() === "PATCH"){
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
    public static function isDelete(){
        if (self::requestMethod() === "DELETE"){
            return true;
        } else {
            return false;
        }
    }

    /**
     * All Route Method
     * Fires before every route ever in the world.
     *
     * @param $oAllFunction
     * @param bool|false $bRenderBeforeOtherwise
     */
    public function all($oAllFunction, $bRenderBeforeOtherwise = false){
        self::$bRenderAllBeforeOtherwise = $bRenderBeforeOtherwise;
        $this->route(':all', $oAllFunction);
    }

    /**
     * Otherwise Route Method
     * Fires when no Route matches are found
     *
     * @param $oOtherwiseFunction
     */
    public function otherwise($oOtherwiseFunction){
        $this->route(':otherwise', $oOtherwiseFunction);
    }

    /**
     * Force the "Otherwise" route.
     */
    public static function forceOtherwise(){
        http_response_code(404);
        if (isset(self::$aRoutes[':otherwise'])){
            self::render(self::$aRoutes[':otherwise'], false, true);
        } else {
            echo self::$sDeathMessage;
        }
        die();
    }

    /**
     * Run Router Diagnostics.
     *
     * @param bool|true $bPrint
     * @return array
     */
    public static function runDiagnostics($bPrint = true){

        $aDiagnostic = array();
        $iTotalRouteCount = 0;

        if (self::$bBeenServed){
            $aDiagnostic['Diagnostic Execution'] = 'Diagnostics are being executed after the Serve.';
        } else {
            $aDiagnostic['Diagnostic Execution'] = 'Diagnostics are being executed before the Serve.';
        }

        // Parent Route
        if (self::liveRouteParent()) {
            $iTotalRouteCount = 1;
            $aDiagnostic['Parent Route'] = self::liveRouteParent();
        }

        // Child Route
        if (self::liveRoute() && !self::isRoute(self::liveRouteParent())) {
            $aDiagnostic['Child Route'] = self::liveRoute();
        }

        if (isset(self::$aRoutes[self::liveRouteParent()]['childRoutes']) && $iChildRouteCount = count(self::$aRoutes[self::liveRouteParent()]['childRoutes'])) {
            $iTotalRouteCount = $iTotalRouteCount + $iChildRouteCount;
            $aDiagnostic['Child Route Count'] = $iChildRouteCount;
        }

        $aDiagnostic['Total Route Family Count'] = $iTotalRouteCount;

        if (self::$iServeTime) {
            $aDiagnostic['Serve Time'] = array('raw' => self::$iServeTime,
                'milliseconds' => round(self::$iServeTime * 1000),
                'standard' => date('H:i:s', self::$iServeTime));
        }

        if (self::$iRenderTime) {
            $aDiagnostic['Render Time'] = array('raw' => self::$iRenderTime,
                'milliseconds' => round(self::$iRenderTime * 1000),
                'standard' => date('H:i:s', self::$iRenderTime));
        }

        $aDiagnostic['Memory Usage'] = array('bytes' => memory_get_usage(),
            'kilobytes' => round(memory_get_usage() / 1024, 2),
            'megabytes' => round(memory_get_usage() / 1048576, 2));


        if ($bPrint) {
            if (!self::$bBeenServed){
                echo "<b>NOTE:</b> Diagnostics should be run WITHIN the Route you're attempting to evaluate for more comprehensive results.";
            }
            echo "<pre>";
            print_r($aDiagnostic);
            echo "</pre>";
        } else {
            return $aDiagnostic;
        }

    }

    /**
     * Build Route.
     *
     * @param $sRoute
     * @param $mRouteFunctionOrResponse
     * @return bool
     */
    public function route($sRoute, $mRouteFunctionOrResponse){
        self::$sRoute = trim($sRoute);

        /*
        | Case 1: Route is the root Route (that is, a forward slash '/')
        | Case 2: Route is not the root.
        */
        if ($sRoute === '/') {
            self::$aRoute = array();
            self::$aRoute[] = '/';
        } else {
            self::$sRoute = self::trimSlashes(self::$sRoute);
            self::$aRoute = explode('/', self::$sRoute);
        }

        // Define Parent Route as first index of aRoute.
        self::$sParentRoute = self::$aRoute[0];

        /*
        | Case 1: Parent URI matches the Parent Route, or is an "Otherwise" or "All" route,
        |         so it is considered to be a Relevant Route.
        | Case 2: Case 1 is false, so the route is not considered to be a Relevant Route.
        */
        if (self::getUriParent() === self::$sParentRoute || self::$sParentRoute === ':otherwise' || self::$sParentRoute === ':all'){
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
        if (self::$bDevMode || !self::$bDevMode && $bRelevantRoute) {

            // Parent Route has not been added to aRoutes, so add it.
            if (!array_key_exists(self::$sParentRoute, self::$aRoutes)) {
                self::$aRoutes[self::$sParentRoute] = array();
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
                self::$aRoutes[self::$sParentRoute]['childRoutes'][self::$sRoute] = $aChildRoute;
            }

            // Set bRouteScope to true so __call knows it's being fired
            // within the Route Scope.
            self::$bRouteScope = true;

            /*
            | Case 1: $mRouteFunctionOrResponse is a callable function, so
            |         call it.
            | Case 2: $mRouteFunctionOrResponse is a string, so build a
            |         response method using the string.
            | Case 3: $mRouteFunctionOrResponse is not of an acceptable type,
            |         so pass operations to throwError.
            */
            if (is_callable($mRouteFunctionOrResponse)) {
                $mRouteFunctionOrResponse($this);
            } elseif (gettype($mRouteFunctionOrResponse) === 'string') {
                $this->response($mRouteFunctionOrResponse);
            } else {
                self::throwError(self::getError(2));
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
    public static function getRoutes($mRouteOrFalse = false){
        if ($mRouteOrFalse){
            return self::$aRoutes[$mRouteOrFalse];
        } else {
            return self::$aRoutes;
        }
    }

    /**
     * Serve Routes from a directory, as opposed to a single Route file.
     *
     * @param $sDirectory
     */
    public static function serveFromDirectory($sDirectory){
        $sAllRouteFile = $sDirectory . '/' . self::$sAllRouteFile;
        $sOtherwiseRouteFile = $sDirectory . '/' . self::$sOtherwiseRouteFile;
        $sRouteFile = $sDirectory . '/' . self::getUriParent() . '.php';
        if (file_exists($sDirectory)){
            if (file_exists($sAllRouteFile)){
                require_once($sAllRouteFile);
            }

            if (file_exists($sOtherwiseRouteFile)){
                require_once($sOtherwiseRouteFile);
            }

            if (file_exists($sRouteFile)) {
                require_once($sRouteFile);
            }
            self::serve();
        } else {
            self::throwError(self::getError(3, $sDirectory));
        }
    }

    /**
     * Determine the current request, find a suitable registered Route,
     * and dispatch accordingly.
     */
    public static function serve(){

        // If user indicated the Request Method should be set using the methodSetter value,
        // do that here.
        if (self::$bSetRequestMethod) {
            self::setRequestMethod();
        }

        // Use the static $bBeenServed variable to ensure we never accidentally "serve" twice.
        if (!self::$bBeenServed) {

            // Begin counter at beginning of serve to record Serve Time
            $iBeginServe = microtime(time());

            // Set bBeenServed to true so we never get here again.
            self::$bBeenServed = true;

            // Assign URI array
            $aUri = self::getUri();

            // If this is a root request, that is, a request to /,
            // then assign the aUri's 0 index as /.
            if (self::rootRequest()) {
                $aUri[0] = '/';
            }

            // sParentRoute is always equal to the first URI parameter.
            $sParentRoute = $aUri[0];

            /*
            | Case 1: The specified Parent Route was built by user, and it is not an "All" or
            |         "Otherwise" Route.
            | Case 2: No Parent Route was found, so forceOtherwise.
            */
            if (isset(self::$aRoutes[$sParentRoute]) && $sParentRoute !== ':all' && $sParentRoute !== ':otherwise') {
                $aParent = self::$aRoutes[$sParentRoute];
            } else{
                self::forceOtherwise();
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
                                self::$aParentRoute = $aParent;
                                self::$bParentRoute = false;
                                self::$sLiveParentRoute = $sParentRoute;
                                self::$sLiveRoute = $sRoute;

                                // Log amount of time it took to find this Route
                                self::$iServeTime = microtime(time()) - $iBeginServe;

                                // Render this Route
                                self::render($aChild);
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
                    self::$aParentRoute = $aParent;
                    self::$bParentRoute = true;
                    self::$sLiveParentRoute = $sParentRoute;
                    self::$sLiveRoute = $sParentRoute;

                    // Save amount of time it took to find this Route
                    self::$iServeTime = microtime(time()) - $iBeginServe;

                    // Render this Route
                    self::render($aParent);
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
    protected static function getError($iErrorCode, $mDynamicErrorVar = false){
        $aErrors = array(
            1 => 'Utility Functions must be called within a Response Method Scope.',
            2 => 'Second parameter of a Route function must be either a string or Closure Object.',
            3 => "Directory '$mDynamicErrorVar' does not exist.",
            4 => "Method '$mDynamicErrorVar' cannot be defined outside a Route Scope.",
            5 => "Method '$mDynamicErrorVar' is not an Acceptable Response Method.",
            6 => "Middleware response methods 'beforeAll' and 'beforeChildren' are reserved for use by Parent Routes only."
        );

        if (isset($aErrors[$iErrorCode])){
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
    protected static function trimSlashes($sString){
        if ($sString !== '/') {
            return ltrim(rtrim($sString, '/'), '/');
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
    protected static function request($bLower = false){
        if ($bLower){
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
    protected static function getUriParent(){
        return self::getUri()[0];
    }

    /**
     * Get Child URI Segment(s) as an array
     *
     * @return array|bool
     */
    protected static function getUriChildren(){
        if (isset(self::getUri()[1])){
            return array_slice(self::getUri(), 1);
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
    protected static function getUri($mIndexOrReturnTypeOrBool = false){
        $aRequest = array();
        // Remove Query Strings so they don't interfere with Routing.
        $sRequestUri = explode('?', $_SERVER['REQUEST_URI'])[0];

        /**
         * @if Is Root Request, manually set 0 index.
         * @else Explode Request URI into array, filter out empties, 0-index it.
         */
        if (self::rootRequest()){
            $aRequest[0] = '/';
        } else {
            $aRequest = array_values(array_filter(explode('/', $sRequestUri)));
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
        switch (gettype($mIndexOrReturnTypeOrBool)){
            case 'integer':
                return $aRequest[$mIndexOrReturnTypeOrBool];
                break;
            case 'string':
                switch (strtolower($mIndexOrReturnTypeOrBool)){
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
    protected static function rootRequest(){
        if ($_SERVER['REQUEST_URI'] === '/'){
            return true;
        } else {
            return false;
        }
    }

}