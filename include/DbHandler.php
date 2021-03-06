<?php

/**
 * Class to handle all db operations
 * This class will have CRUD methods for database tables
 *
 * @author Ravi Tamada
 * @link URL Tutorial link
 */
class DbHandler {

    private $conn;

    function __construct() {
        require_once dirname(__FILE__) . '/DbConnect.php';
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();
    }

    /* ------------- `users` table method ------------------ */

    /**
     * Creating new user
     * @param String $name User full name
     * @param String $email User login email id
     * @param String $password User login password
     */
    public function createUser($name, $email, $password) {
        require_once 'PassHash.php';
        $response = array(); 

        // First check if user already existed in db
        if (!$this->isUserExists($email)) {
            // Generating password hash
            $password_hash = PassHash::hash($password);

            // Generating API key
            $api_key = $this->generateApiKey();

            // insert query
            $stmt = $this->conn->prepare("INSERT INTO users(name, email, password_hash, api_key, status) values(?, ?, ?, ?, 1)");
            $stmt->bind_param("ssss", $name, $email, $password_hash, $api_key);

            $result = $stmt->execute();

            $stmt->close();

            // Check for successful insertion
            if ($result) {
                // User successfully inserted
                return USER_CREATED_SUCCESSFULLY;
            } else {
                // Failed to create user
                return USER_CREATE_FAILED;
            }
        } else {
            // User with same email already existed in the db
            return USER_ALREADY_EXISTED;
        }

        return $response;
    }

    /**
     * Checking user login
     * @param String $email User login email id
     * @param String $password User login password
     * @return boolean User login status success/fail
     */
    public function checkLogin($email, $password) {
        // fetching user by email
        $stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE email = ?");

        $stmt->bind_param("s", $email);

        $stmt->execute();

        $stmt->bind_result($password_hash);

        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Found user with the email
            // Now verify the password

            $stmt->fetch();

            $stmt->close();

            if (PassHash::check_password($password_hash, $password)) {
                // User password is correct
                return TRUE;
            } else {
                // user password is incorrect
                return FALSE;
            }
        } else {
            $stmt->close();

            // user not existed with the email
            return FALSE;
        }
    }

    /**
     * Checking for duplicate user by email address
     * @param String $email email to check in db
     * @return boolean
     */
    private function isUserExists($email) {
        $stmt = $this->conn->prepare("SELECT id from users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Fetching user by email
     * @param String $email User email id
     */
    public function getUserByEmail($email) {
        $stmt = $this->conn->prepare("SELECT name, email, api_key, status, created_at FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            // $user = $stmt->get_result()->fetch_assoc();
            $stmt->bind_result($name, $email, $api_key, $status, $created_at);
            $stmt->fetch();
            $user = array();
            $user["name"] = $name;
            $user["email"] = $email;
            $user["api_key"] = $api_key;
            $user["status"] = $status;
            $user["created_at"] = $created_at;
            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching user api key
     * @param String $user_id user id primary key in user table
     */
    public function getApiKeyById($user_id) {
        $stmt = $this->conn->prepare("SELECT api_key FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            // $api_key = $stmt->get_result()->fetch_assoc();
            // TODO
            $stmt->bind_result($api_key);
            $stmt->close();
            return $api_key;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching user id by api key
     * @param String $api_key user api key
     */
    public function getUserId($api_key) {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        if ($stmt->execute()) {
            $stmt->bind_result($user_id);
            $stmt->fetch();
            // TODO
            // $user_id = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user_id;
        } else {
            return NULL;
        }
    }

    /**
     * Validating user api key
     * If the api key is there in db, it is a valid key
     * @param String $api_key user api key
     * @return boolean
     */
    public function isValidApiKey($api_key) {
        $stmt = $this->conn->prepare("SELECT id from users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Generating random Unique MD5 String for user Api key
     */
    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }

    /* ------------- `Equipo` table method ------------------ */
     /**
     * Creando un nuevo equipo
     * @param String $user_id user id to whom task belongs to
     * @param String $task task text
     */
    public function createEquipo($nombre) {
        
        $stmt = $this->conn->prepare("INSERT INTO equipos(nombre) VALUES(?)");
        $stmt->bind_param("s", $nombre);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // equipo row created
            // now assign the task to user
            $new_equipo_id = $this->conn->insert_id;
           
                return $new_equipo_id;
           
        } else {
            // task failed to create
            return NULL;
        }
    }

    /**
     * Busca todos los equipos
     * @param 
     */
    public function getAllEquipos() {
        $stmt = $this->conn->prepare("SELECT nombre FROM equipos");
        $stmt->execute();

       

        /* vincular variables a la sentencia preparada */
         $stmt->bind_result($nombre);

          while ($fila = $stmt->fetch()) {
                $equipos[]=$nombre;
            }

        $stmt->close();
        return $equipos;
    }


    /* ------------- `Jugadores table method ------------------ */

    /**
     * Busca todos los jugadores de un equipo
     * @param Integer $equipo_id 
     */
    public function getAllJugadores($equipo_id) {
        //$stmt = $this->conn->prepare("SELECT id,nombre FROM jugadores WHERE equipo_id = ?");
        $mysqli = new mysqli("localhost", "root", "vikiwi3", "lbh_rest_api");
        $query = "SELECT * FROM jugadores WHERE equipo_id = " . $equipo_id;
        if ($result = $mysqli->query($query)) {

            /* fetch associative array */
            $equipos=array();
            while ($row = $result->fetch_assoc()) {
                $equipos[]=$row;
            }

            /* free result set */
          $equiposJSON = json_encode($equipos);
/*
            echo "<pre>*************";
           var_dump($equipos);
            echo "</pre>";
            */
            $result->free();
        }

      
       // var_dump($equiposJSON);
        //$result->close();
        return $equipos;
    }


    /**
     * Creando un nuevo jugador
     * @param String $user_id user id to whom task belongs to
     * @param String $task task text
     */
    public function createJugador($equipo_id, $nombre,$apellidos,$puesto) {
        
        $stmt = $this->conn->prepare("INSERT INTO jugadores(equipo_id, nombre,apellidos,puesto) VALUES(?,?,?,?)");
       // $stmt->bind_param("s", $equipo_id);
        $stmt->bind_param("isss",$equipo_id, $nombre, $apellidos, $puesto);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // equipo row created
            // now assign the task to user
            $new_jugador_id = $this->conn->insert_id;
           
                return $new_jugador_id;
           
        } else {
            // task failed to create
            return NULL;
        }
    }

    /**
     * Busca un jugador
     * @param String $jugador_id
     */
    public function getJugador($jugador_id) {
        $stmt = $this->conn->prepare("SELECT id, equipo_id, nombre, apellidos, puesto from jugadores WHERE id = ? ");
        $stmt->bind_param("i", $jugador_id);
        if ($stmt->execute()) {
            $res = array();
            $stmt->bind_result($id, $equipo_id, $nombre, $apellidos, $puesto);
            // TODO
            // $task = $stmt->get_result()->fetch_assoc();
            $stmt->fetch();
            $res["id"] = $id;
            $res["equipo_id"] = $equipo_id;
            $res["nombre"] = $nombre;
            $res["apellidos"] = $apellidos;
            $res["puesto"] = $puesto;
            $stmt->close();
            return $res;
        } else {
            return NULL;
        }
    }

     /**
     * Updating jugador
     * @param Id     $equipo_id 
     * @param String $nombre
     * @param String $apellidos
     * @param String $puesto
     */
    public function updateJugador($jugador_id,$nombre, $apellidos, $puesto) {
        $stmt = $this->conn->prepare("UPDATE jugadores set nombre = ?, apellidos = ? , puesto = ? WHERE id = ?");
        $stmt->bind_param("sssi", $nombre, $apellidos, $puesto,$jugador_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }


}

?>
