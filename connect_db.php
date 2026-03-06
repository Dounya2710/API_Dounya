<?php
    /**
     * NaturAfrica / KLCD - DB connection helper (PostgreSQL via PDO)
     *
     * Best practice:
     * - Use environment variables (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS)
     * - Optionally set a per-request "app user" for auditing: SET app.user = '...'
     */

    function get_env(string $key, string $default = ''): string {
        $v = getenv($key);
        return ($v === false || $v === '') ? $default : $v;
    }

    /**
     * Return a connected PDO object to the NaturAfrica database.
     */
    function get_naturafrica_pdo(): PDO
    {
        static $pdo = null;
        if ($pdo !== null) return $pdo;

        $dbHost = get_env('DB_HOST', 'localhost');
        $dbPort = get_env('DB_PORT', '5432');
        $dbName = get_env('DB_NAME', 'naturafrica');
        $dbUser = get_env('DB_USER', 'postgres');
        $dbPass = get_env('DB_PASS', '');

        $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName";

        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die("Error connection to the NaturAfrica database: " . $e->getMessage());
        }

        return $pdo;
    }

    /**
     * Optionally set an "application user" in the DB session (used by audit triggers).
     * Call this at the start of each request if you have an authenticated user.
     */
    function set_db_app_user(PDO $pdo, string $username): void
    {
        // Basic sanitation to avoid breaking the SQL
        $username = preg_replace('/[^a-zA-Z0-9_.@-]/', '_', $username);
        $stmt = $pdo->prepare("SELECT set_config('app.user', :u, true)");
        $stmt->execute([':u' => $username]);
    }
?>