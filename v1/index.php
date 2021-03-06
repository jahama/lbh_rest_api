<?php

require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
require '.././libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim(array('debug'=> true));


// User id from db - Global Variable
$user_id = NULL;

/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
function authenticate(\Slim\Route $route) {
    // Getting request headers
    
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();

    // Verifying Authorization Header
    if (isset($headers['Authorization'])) {
        $db = new DbHandler();

        // get the api key
        $api_key = $headers['Authorization'];
        // validating api key
        if (!$db->isValidApiKey($api_key)) {
            // api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid Api key";
            echoRespnse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            // get user primary key id
            $user_id = $db->getUserId($api_key);
        }
    } else {
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "Api key is misssing";
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * ----------- METHODS WITHOUT AUTHENTICATION ---------------------------------
 */
/**
 * User Registration
 * url - /register
 * method - POST
 * params - name, email, password
 */
$app->post('/register', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('name', 'email', 'password'));
            $response = array();

            // reading post params
            $name = $app->request->post('name');
            $email = $app->request->post('email');
            $password = $app->request->post('password');

            // validating email address
            validateEmail($email);

            $db = new DbHandler();
            $res = $db->createUser($name, $email, $password);

             if ($res == USER_CREATED_SUCCESSFULLY) {
                $response["error"] = false;
                $response["message"] = "You are successfully registered";
            } else if ($res == USER_CREATE_FAILED) {
                $response["error"] = true;
                $response["message"] = "Oops! An error occurred while registereing";
            } else if ($res == USER_ALREADY_EXISTED) {
                $response["error"] = true;
                $response["message"] = "Sorry, this email already existed";
            }
            // echo json response
            echoRespnse(201, $response);
        });

/**
 * User Login
 * url - /login
 * method - POST
 * params - email, password
 */
$app->post('/login', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('email', 'password'));

            // reading post params
            $email = $app->request()->post('email');
            $password = $app->request()->post('password');
            $response = array();

            $db = new DbHandler();
            // check for correct email and password
            if ($db->checkLogin($email, $password)) {
                // get the user by email
                $user = $db->getUserByEmail($email);

                if ($user != NULL) {
                    $response["error"] = false;
                    $response['name'] = $user['name'];
                    $response['email'] = $user['email'];
                    $response['apiKey'] = $user['api_key'];
                    $response['createdAt'] = $user['created_at'];
                } else {
                    // unknown error occurred
                    $response['error'] = true;
                    $response['message'] = "An error occurred. Please try again";
                }
            } else {
                // user credentials are wrong
                $response['error'] = true;
                $response['message'] = 'Login failed. Incorrect credentials';
            }

            echoRespnse(200, $response);
        });

/*
 * ------ METHODS WITH AUTHENTICATION (Including API key in the request)-------/*
 * ------------------------   Handling the API calls    ------------------------
 */

/**
 * -------------------------------  EQUIPOS    ---------------------------------
 */
/**
 * Lista todos los equipos
 * method GET
 * url /equipos          
 */
$app->get('/equipos', 'authenticate', function() {
           // global $user_id;
            $response = array();
            $db = new DbHandler();

            // Busca todos los equipos
            $result = $db->getAllEquipos();

            $response["error"] = false;
            $response["equipos"] = array();
           
          // while ($task = $result->fetch_assoc()) {
            for ($i=0;$i<count($result);$i++){

                
                $tmp = array();
                //$tmp["id"] = $task["id"];
                $tmp["nombre"] = $result[0];
                array_push($response["equipos"], $tmp);
                
            }

            echoRespnse(200, $response);
        });


/**
 * Creating new Team in db
 * method POST
 * params - nombre
 * url - /equipos/
 */
$app->post('/equipos', 'authenticate', function() use ($app) {
            // check for required params 
          
            verifyRequiredParams(array('nombre'));

            $response = array();
            $equipo = $app->request->post('nombre');


            //global $user_id;
            $db = new DbHandler();

            // creating new task
            $equipo_id = $db->createEquipo($equipo);

            if ($equipo_id != NULL) {
                $response["error"] = false;
                $response["message"] = "Task created successfully";
                $response["equipo_id"] = $equipo_id;
                echoRespnse(201, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "Failed to create Team. Please try again";
                echoRespnse(200, $response);
            }            
        });



/**
 * -------------------------------               ---------------------------------
 */

/**
 * -------------------------------  JUGADORES    ---------------------------------
 */

/**
 * Lista todos los jugadores de un equipo
 * method GET
 * params - equipo_id
 * url /equipos/:id          
 */
$app->get('/equipos/:id',  function($equipo_id) {
          
            // check for required params           
           // verifyRequiredParams(array('equipo_id'));
            $response = array();
             // reading post params
           // $equipo_id = $app->request->post('equipo_id');
            $db = new DbHandler();

            // Busca todos los equipos
            $result = $db->getAllJugadores($equipo_id);               
            $response["error"] = false;
            $response["equipos"] = array();
           
          // while ($task = $result->fetch_assoc()) {
            for ($i=0;$i<count($result);$i++){ 
                 $equipo = json_encode($result[$i]);
              
                 array_push($response["equipos"], $equipo);
            }
            echoRespnse(200, $response);
        });




/**
 * Creating new Jugador in db
 * method POST
 * params - equipo_id, nombre, apellidos, puesto
 * url - /jugadores/
 */
$app->post('/jugadores', 'authenticate', function() use ($app) {
            
            // check for required params           
            verifyRequiredParams(array('equipo_id', 'nombre', 'apellidos', 'puesto'));

            $response = array();
            // reading post params
            $equipo_id = $app->request->post('equipo_id');
            $nombre = $app->request->post('nombre');
            $apellidos = $app->request->post('apellidos');
            $puesto = $app->request->post('puesto');
           
           
            //global $user_id;
            $db = new DbHandler();

            // creating new jugador
            $jugador_id = $db->createJugador($equipo_id, $nombre,$apellidos,$puesto);

            if ($equipo_id != NULL) {
                $response["error"] = false;
                $response["message"] = "Jugador created successfully";
                $response["jugador_id"] = $jugador_id;
                echoRespnse(201, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "Failed to create Jugador. Please try again";
                echoRespnse(200, $response);
            }            
        });

/**
 * Listing single jugador 
 * method GET
 * url /jugadores/:id
 * Will return 404 if the Jugador doesn't belongs to Equipo
 */
$app->get('/jugadores/:id', 'authenticate', function($jugador_id) {
           
            $response = array();
            $db = new DbHandler();

            // fetch task
            $result = $db->getJugador($jugador_id);

            if ($result != NULL) {
                $response["error"] = false;
                $response["id"] = $result["id"];
                $response["equip_id"] = $result["equipo_id"];
                $response["nombre"] = $result["nombre"];
                $response["apellidos"] = $result["apellidos"];
                $response["puesto"] = $result["puesto"];
                echoRespnse(200, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "The requested resource doesn't exists";
                echoRespnse(404, $response);
            }
        });

/**
 * Updating existing Jugador
 * method PUT
 * params  equipo_id, nombre, apellidos, puesto
 * url - /jugadores/:id
 */
$app->put('/jugadores/:id', 'authenticate', function($jugador_id) use($app) {
            // check for required params
            verifyRequiredParams(array('nombre', 'apellidos', 'puesto'));
                      
             // reading post params
            //$equipo_id = $app->request->post('equipo_id');
            $nombre = $app->request->post('nombre');
            $apellidos = $app->request->post('apellidos');
            $puesto = $app->request->post('puesto');

            $db = new DbHandler();
            $response = array();

            // updating Jugador
            $result = $db->updateJugador($jugador_id,$nombre, $apellidos, $puesto);
            if ($result) {
                // task updated successfully
                $response["error"] = false;
                $response["message"] = "Task updated successfully";
            } else {
                // task failed to update
                $response["error"] = true;
                $response["message"] = "Task failed to update. Please try again!";
            }
            echoRespnse(200, $response);
        });

/**
 * -------------------------------               ---------------------------------
 *
 */

/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Validating email address
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) {
    
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}

$app->run();
?>