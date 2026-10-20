<?php
    error_reporting(0);
    date_default_timezone_set("America/Mexico_City");
    header('Content-Type: application/json; charset=utf-8');

    // Sesión PHP: se usa para login/logout y para saber quién guarda tarjetas.
    session_start();

    // Origen de datos:
    // Se movió FUERA de la ruta pública directa (antes se leía con fetch
    // directo desde app.js); ahora solo este endpoint lo toca.
    define('RUTA_DATOS', __DIR__ . '/data/tarjetas.json');
    define('RUTA_USUARIOS', __DIR__ . '/data/usuarios.json');
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
    /*--------------------------------------------------------------------------
    * Persistencia: el archivo JSON es la única fuente de datos.
    *--------------------------------------------------------------------------*/
    function leerTarjetas() {
        if (!file_exists(RUTA_DATOS)) {
            return [];
        }

        $contenido = file_get_contents(RUTA_DATOS);
        if ($contenido === false || trim($contenido) === '') {
            return [];
        }

        $tarjetas = json_decode($contenido, true);
        // Si el JSON está corrupto se trata como lista vacía en lugar de
        // romper toda la galería.
        return is_array($tarjetas) ? $tarjetas : [];
    }
    function guardarTarjetas($tarjetas) {
        $directorio = dirname(RUTA_DATOS);
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $json = json_encode(
            $tarjetas,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($json === false) {
            return false;
        }

        // LOCK_EX evita que dos escrituras simultáneas se pisen entre sí.
        return file_put_contents(RUTA_DATOS, $json, LOCK_EX) !== false;
    }
    /*--------------------------------------------------------------------------
    * Usuarios: mismos helpers que las tarjetas, pero en api/data/usuarios.json.
    * Las contraseñas NUNCA se guardan en texto plano.
    *--------------------------------------------------------------------------*/
    function leerUsuarios() {
        if (!file_exists(RUTA_USUARIOS)) {
            return [];
        }

        $contenido = file_get_contents(RUTA_USUARIOS);
        if ($contenido === false || trim($contenido) === '') {
            return [];
        }

        $usuarios = json_decode($contenido, true);
        return is_array($usuarios) ? $usuarios : [];
    }
    function guardarUsuarios($usuarios) {
        $directorio = dirname(RUTA_USUARIOS);
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $json = json_encode(
            $usuarios,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($json === false) {
            return false;
        }

        return file_put_contents(RUTA_USUARIOS, $json, LOCK_EX) !== false;
    }
    // Quita el hash de la contraseña antes de devolver un usuario al cliente.
    function usuarioPublico($usuario) {
        unset($usuario['password']);
        return $usuario;
    }
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
    * Acciones
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

        $nuevaTarjeta['id'] = uniqid('user_');
        $nuevaTarjeta['createdAt'] = date('Y-m-d H:i:s');

        // Si hay una sesión activa, la tarjeta queda ligada a ese usuario.
        $nuevaTarjeta['usuario_id'] = $_SESSION['usuario']['id'] ?? null;

        $tarjetas = leerTarjetas();
        $tarjetas[] = $nuevaTarjeta;

        if (guardarTarjetas($tarjetas) === false) {
            responderError('Error de permisos. El servidor no permite escribir en api/data/.', 500);
        }

        responderExito('Tarjeta guardada correctamente en el servidor.', $nuevaTarjeta);
    }
    function accionListarTarjetas() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            responderError('Método no permitido. Usa GET.', 405);
        }

        $tarjetas = leerTarjetas();
        responderExito('Tarjetas obtenidas correctamente.', $tarjetas);
    }
    function accionRegistrarUsuario() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderError('Método no permitido. Usa POST.', 405);
        }

        // Mismos campos que el formulario de registro del frontend.
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

        $usuarios = leerUsuarios();

        foreach ($usuarios as $usuarioExistente) {
            if (isset($usuarioExistente['email']) && strcasecmp($usuarioExistente['email'], $email) === 0) {
                responderError('Ya existe una cuenta registrada con ese correo.', 409);
            }
            if (isset($usuarioExistente['nickname']) && strcasecmp($usuarioExistente['nickname'], trim($_POST['nickname'])) === 0) {
                responderError('Ese nickname ya está en uso.', 409);
            }
        }

        $usuario = [
            'id'         => uniqid('user_'),
            'nombre'     => trim($_POST['nombre']),
            'nacimiento' => trim($_POST['nacimiento']),
            'educacion'  => trim($_POST['educacion']),
            'telefono'   => trim($_POST['telefono']),
            'horas'      => trim($_POST['horas']),
            'email'      => $email,
            'nickname'   => trim($_POST['nickname']),
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'createdAt'  => date('Y-m-d H:i:s'),
        ];

        $usuarios[] = $usuario;

        if (guardarUsuarios($usuarios) === false) {
            responderError('Error de permisos. El servidor no permite escribir en api/data/.', 500);
        }

        responderExito('Registro exitoso. Ahora puedes iniciar sesión.', usuarioPublico($usuario));
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

        $usuarioEncontrado = null;
        foreach (leerUsuarios() as $usuario) {
            if (isset($usuario['email']) && strcasecmp($usuario['email'], $email) === 0) {
                $usuarioEncontrado = $usuario;
                break;
            }
        }

        // Mismo mensaje para correo inexistente y contraseña incorrecta:
        // así no se filtra qué correos están registrados.
        if (
            $usuarioEncontrado === null
            || !isset($usuarioEncontrado['password'])
            || !password_verify($password, $usuarioEncontrado['password'])
        ) {
            responderError('Correo o contraseña incorrectos.', 401);
        }

        // Evita fijación de sesión al iniciar sesión.
        session_regenerate_id(true);
        $_SESSION['usuario'] = usuarioPublico($usuarioEncontrado);

        responderExito('Bienvenido de nuevo.', usuarioPublico($usuarioEncontrado));
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
