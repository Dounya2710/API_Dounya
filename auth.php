<?php
    declare(strict_types=1);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    function is_admin_email(string $email): bool {
        $email = strtolower(trim($email));
        return str_ends_with($email, '@agreco.be') || str_ends_with($email, '@visioterra.fr') || str_ends_with($email, '@europa.eu');
    }

    function login_user(string $email, string $role = 'user'): void {
        $email = strtolower(trim($email));

        // si domaine admin -> admin (prioritaire)
        $isAdmin = ($role === 'admin') || is_admin_email($email);

        $_SESSION['user_email'] = $email;
        $_SESSION['role']       = $isAdmin ? 'admin' : 'user';
    }

    function logout_user(): void {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"] ?? false, $params["httponly"] ?? true
            );
        }
        session_destroy();
    }

    function current_user_email(): ?string {
        return $_SESSION['user_email'] ?? null;
    }

    function current_user_role(): string {
        return $_SESSION['role'] ?? 'guest';
    }

    function current_user_is_admin(): bool {
        return current_user_role() === 'admin';
    }

    function require_login(): void {
        if (!current_user_email()) {
            header('Location: /login.php');
            exit;
        }
    }

    function require_admin(): void {
        require_login();
        if (!current_user_is_admin()) {
            http_response_code(403);
            echo "403 Forbidden (admin only)";
            exit;
        }
    }
?>