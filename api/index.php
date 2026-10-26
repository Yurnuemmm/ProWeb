<?php
    error_reporting(0);
    date_default_timezone_set("America/Mexico_City");
    header('Content-Type: application/json; charset=utf-8');

    // Sesión PHP: se usa para login/logout y para asociar tarjetas al usuario activo.
    session_start();

    // Ruta del archivo JSON para tarjetas
    define('RUTA_TARJETAS_JSON', __DIR__ . '/data/tarjetas.json');

    /**
    * --------------------------------------------------
    * Persistencia en JSON para Tarjetas
    * --------------------------------------------------
    */
    function leerTarjetasJSON() {
        if (!file_exists(RUTA_TARJETAS_JSON)) {
            return [];
        }

        $contenido = file_get_contents(RUTA_TARJETAS_JSON);
        if ($contenido === false || trim($contenido) === '') {
            return [];
        }

        $tarjetas = json_decode($contenido, true);
        return is_array($tarjetas) ? $tarjetas : [];
    }

    function guardarTarjetasJSON($tarjetas) {
        $directorio = dirname(RUTA_TARJETAS_JSON);
        if (!is_dir($directorio)) {
            mkdir($directorio, 0777, true);
        }

        $json = json_encode(
            $tarjetas,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($json === false) {
            return false;
        }

        return file_put_contents(RUTA_TARJETAS_JSON, $json, LOCK_EX) !== false;
    }

    /**
    * --------------------------------------------------
    * Conexión a Base de Datos MySQL (PDO) para Usuarios
    * --------------------------------------------------
    */
    function parseEnvFile($ruta) {
        if (!file_exists($ruta)) return [];
        $vars = [];
        $lines = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $vars[trim($key)] = trim($val);
            }
        }
        return $vars;
    }

    function inicializarTablasSiNoExisten($pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `usuarios` (
                `id` VARCHAR(64) NOT NULL PRIMARY KEY,
                `nombre` VARCHAR(255) NOT NULL,
                `nacimiento` VARCHAR(50) NOT NULL,
                `educacion` VARCHAR(255) NOT NULL,
                `telefono` VARCHAR(50) NOT NULL,
                `horas` VARCHAR(50) NOT NULL,
                `email` VARCHAR(191) NOT NULL UNIQUE,
                `nickname` VARCHAR(100) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `createdAt` DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    function obtenerConexionBD() {
        static $pdo = null;
        if ($pdo !== null) {
            return $pdo;
        }

        $env = parseEnvFile(dirname(__DIR__) . '/.env');

        $host = getenv('DB_HOST') ?: ($env['DB_HOST'] ?? '127.0.0.1');
        $port = getenv('DB_PORT') ?: ($env['DB_PORT'] ?? '3306');
        $db   = getenv('DB_DATABASE') ?: ($env['DB_DATABASE'] ?? 'web_db');
        $user = getenv('DB_USER') ?: ($env['DB_USER'] ?? 'web_user');
        $pass = getenv('DB_PASSWORD') ?: ($env['DB_PASSWORD'] ?? 'secret_password_123');

        $hostsAProbar = [$host];
        if ($host !== '127.0.0.1' && $host !== 'localhost') {
            $hostsAProbar[] = '127.0.0.1';
        }

        $ultimoError = null;
        foreach ($hostsAProbar as $h) {
            try {
                $dsn = "mysql:host={$h};port={$port};dbname={$db};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                $pdo = new PDO($dsn, $user, $pass, $options);
                inicializarTablasSiNoExisten($pdo);
                return $pdo;
            } catch (PDOException $e) {
                $ultimoError = $e->getMessage();
            }
        }

        responderError('Error de conexión a la base de datos MySQL: ' . $ultimoError, 500);
    }

    /**
    * --------------------------------------------------
    * Utilidades de respuesta
    * --------------------------------------------------
    */
    function responder($statusCode, $payload) {
        http_response_code($statusCode);
        echo json_encode($payload);
        exit;
    }

    function responderExito($mensaje, $data = null) {
        responder(200, [
            'success' => true,
            'message' => $mensaje,
            'data' => $data,
        ]);
    }

    function responderError($mensaje, $statusCode = 400) {
        responder($statusCode, [
            'success' => false,
            'error' => $mensaje,
        ]);
    }

    function usuarioPublico($usuario) {
        if (is_array($usuario)) {
            unset($usuario['password']);
        }
        return $usuario;
    }

    /*--------------------------------------------------------------------------
    * Router de acciones
    *--------------------------------------------------------------------------*/
    $action = $_GET['action'] ?? $_POST['action'] ?? null;
    switch($action){
        case 'crear_tarjeta':
            accionCrearTarjeta();
            break;
        case 'listar_tarjeta':
        case 'listar_tarjetas':
            accionListarTarjetas();
            break;
        case 'registrar_usuario':
            accionRegistrarUsuario();
            break;
        case 'login':
            accionLogin();
            break;
        case 'logout':
            accionLogout();
            break;
        case 'check_session':
            accionCheckSession();
            break;
        default:
            responderError('Accion no reconocida o no especificada', 404);
    }

    /*--------------------------------------------------------------------------
    * Acciones de Tarjetas (en JSON)
    *--------------------------------------------------------------------------*/
    function accionCrearTarjeta() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderError('Método no permitido. Usa POST.', 405);
        }

        $inputJSON = $_POST['datos_tarjeta'] ?? file_get_contents('php://input');
        $nuevaTarjeta = json_decode($inputJSON, true);

        if (!$nuevaTarjeta || empty($nuevaTarjeta['name'])) {
            responderError('Datos inválidos o incompletos.', 400);
        }

        $nuevaTarjeta['id'] = uniqid('card_');
        $nuevaTarjeta['createdAt'] = date('Y-m-d H:i:s');
        $nuevaTarjeta['usuario_id'] = $_SESSION['usuario']['id'] ?? null;

        $tarjetas = leerTarjetasJSON();
        $tarjetas[] = $nuevaTarjeta;

        if (guardarTarjetasJSON($tarjetas) === false) {
            responderError('Error de permisos. El servidor no permite escribir en api/data/tarjetas.json.', 500);
        }

        responderExito('Tarjeta guardada correctamente en el archivo JSON.', $nuevaTarjeta);
    }

    function accionListarTarjetas() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            responderError('Método no permitido. Usa GET.', 405);
        }

        $tarjetas = leerTarjetasJSON();
        responderExito('Tarjetas obtenidas correctamente.', $tarjetas);
    }

    /*--------------------------------------------------------------------------
    * Acciones de Usuarios (en MySQL)
    *--------------------------------------------------------------------------*/
    function accionRegistrarUsuario() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderError('Método no permitido. Usa POST.', 405);
        }

        $camposObligatorios = [
            'nombre', 'nacimiento', 'educacion', 'telefono',
            'horas', 'email', 'nickname', 'password',
        ];
        foreach ($camposObligatorios as $campo) {
            if (!isset($_POST[$campo]) || trim((string) $_POST[$campo]) === '') {
                responderError("El campo \"$campo\" es obligatorio.", 422);
            }
        }

        $email = trim($_POST['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            responderError('El correo electrónico no es válido.', 422);
        }

        $password = (string) $_POST['password'];
        if (strlen($password) < 6) {
            responderError('La contraseña debe tener al menos 6 caracteres.', 422);
        }

        $nickname = trim($_POST['nickname']);

        try {
            $db = obtenerConexionBD();

            // Verificar si el correo o nickname ya existen
            $checkStmt = $db->prepare("SELECT email, nickname FROM usuarios WHERE LOWER(email) = LOWER(?) OR LOWER(nickname) = LOWER(?) LIMIT 1");
            $checkStmt->execute([$email, $nickname]);
            $existente = $checkStmt->fetch();

            if ($existente) {
                if (strcasecmp($existente['email'], $email) === 0) {
                    responderError('Ya existe una cuenta registrada con ese correo.', 409);
                }
                if (strcasecmp($existente['nickname'], $nickname) === 0) {
                    responderError('Ese nickname ya está en uso.', 409);
                }
            }

            $id = uniqid('user_');
            $createdAt = date('Y-m-d H:i:s');
            $hashPassword = password_hash($password, PASSWORD_DEFAULT);

            $insertStmt = $db->prepare("
                INSERT INTO usuarios (id, nombre, nacimiento, educacion, telefono, horas, email, nickname, password, createdAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $id,
                trim($_POST['nombre']),
                trim($_POST['nacimiento']),
                trim($_POST['educacion']),
                trim($_POST['telefono']),
                trim($_POST['horas']),
                $email,
                $nickname,
                $hashPassword,
                $createdAt,
            ]);

            $usuario = [
                'id'         => $id,
                'nombre'     => trim($_POST['nombre']),
                'nacimiento' => trim($_POST['nacimiento']),
                'educacion'  => trim($_POST['educacion']),
                'telefono'   => trim($_POST['telefono']),
                'horas'      => trim($_POST['horas']),
                'email'      => $email,
                'nickname'   => $nickname,
                'createdAt'  => $createdAt,
            ];

            responderExito('Registro exitoso. Ahora puedes iniciar sesión.', $usuario);
        } catch (PDOException $e) {
            responderError('Error al registrar usuario en la base de datos: ' . $e->getMessage(), 500);
        }
    }

    function accionLogin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderError('Método no permitido. Usa POST.', 405);
        }

        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            responderError('Correo y contraseña son obligatorios.', 422);
        }

        try {
            $db = obtenerConexionBD();
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE LOWER(email) = LOWER(?) LIMIT 1");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if (
                !$usuario
                || empty($usuario['password'])
                || !password_verify($password, $usuario['password'])
            ) {
                responderError('Correo o contraseña incorrectos.', 401);
            }

            session_regenerate_id(true);
            $usuarioPublico = usuarioPublico($usuario);
            $_SESSION['usuario'] = $usuarioPublico;

            responderExito('Bienvenido de nuevo.', $usuarioPublico);
        } catch (PDOException $e) {
            responderError('Error en inicio de sesión: ' . $e->getMessage(), 500);
        }
    }

    function accionLogout() {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        responderExito('Sesión cerrada correctamente.');
    }

    function accionCheckSession() {
        if (empty($_SESSION['usuario'])) {
            responderError('No hay una sesión activa.', 401);
        }

        responderExito('Sesión activa.', $_SESSION['usuario']);
    }
