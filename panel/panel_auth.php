<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * SISTEMA DE AUTENTICACIÓN DEL PANEL
 * ═══════════════════════════════════════════════════════════════
 */

class PanelAuth {
    
    public static function iniciarSesion() {
        if (session_status() === PHP_SESSION_NONE) {
            // Iniciar output buffering para evitar problemas de headers
            if (!headers_sent()) {
                session_name(SESSION_NAME);
                session_start();
            } else {
                // Si ya se enviaron headers, intentar recuperar sesión existente
                if (isset($_COOKIE[SESSION_NAME])) {
                    session_id($_COOKIE[SESSION_NAME]);
                }
                @session_start();
            }
        }
    }
    
    public static function login($username, $password) {
        if ($username === ADMIN_WEB_USERNAME && 
            password_verify($password, ADMIN_WEB_PASSWORD)) {
            
            // Verificar IP si está configurado
            if (!self::verificarIP()) {
                return false;
            }
            
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_login_time'] = time();
            $_SESSION['admin_last_activity'] = time();
            
            // Registrar login
            self::registrarAcceso($username, 'login');
            
            return true;
        }
        
        return false;
    }
    
    public static function logout() {
        self::registrarAcceso($_SESSION['admin_username'] ?? 'unknown', 'logout');
        
        session_destroy();
        
        // Usar JavaScript para redireccionar si headers ya fueron enviados
        if (headers_sent()) {
            echo '<script>window.location.href="login.php";</script>';
            exit;
        } else {
            header('Location: login.php');
            exit;
        }
    }
    
    public static function estaAutenticado() {
        self::iniciarSesion();
        
        if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
            return false;
        }
        
        // Verificar timeout de sesión
        if (time() - $_SESSION['admin_last_activity'] > SESSION_TIMEOUT) {
            self::logout();
            return false;
        }
        
        // Actualizar última actividad
        $_SESSION['admin_last_activity'] = time();
        
        return true;
    }
    
    public static function requerirAuth() {
        if (!self::estaAutenticado()) {
            // Usar JavaScript si headers ya fueron enviados
            if (headers_sent()) {
                echo '<script>window.location.href="login.php";</script>';
                exit;
            } else {
                header('Location: login.php');
                exit;
            }
        }
    }
    
    private static function verificarIP() {
        global $ADMIN_ALLOWED_IPS;
        
        if (empty($ADMIN_ALLOWED_IPS)) {
            return true; // No hay restricción de IP
        }
        
        $ip = $_SERVER['REMOTE_ADDR'];
        
        return in_array($ip, $ADMIN_ALLOWED_IPS);
    }
    
    private static function registrarAcceso($username, $accion) {
        $log = [
            'timestamp' => date('Y-m-d H:i:s'),
            'username' => $username,
            'action' => $accion,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
        
        $logFile = __DIR__ . '/logs/admin_access.log';
        
        if (!is_dir(dirname($logFile))) {
            @mkdir(dirname($logFile), 0755, true);
        }
        
        @file_put_contents($logFile, json_encode($log) . "\n", FILE_APPEND);
    }
    
    public static function getUsuarioActual() {
        return $_SESSION['admin_username'] ?? null;
    }
    
    public static function getTiempoSesion() {
        if (!isset($_SESSION['admin_login_time'])) {
            return 0;
        }
        
        return time() - $_SESSION['admin_login_time'];
    }
}

?>
