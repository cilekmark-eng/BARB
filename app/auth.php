<?php
/**
 * app/auth.php — Registration, login, logout
 */

declare(strict_types=1);

function auth_register(array $data): array
{
    $errors = [];

    $name  = trim($data['name']  ?? '');
    $email = trim($data['email'] ?? '');
    $pass  = $data['password']   ?? '';
    $conf  = $data['confirm']    ?? '';

    if (mb_strlen($name) < 2)          $errors[] = 'Имя должно быть не короче 2 символов';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Некорректный email';
    if (mb_strlen($pass) < 8)          $errors[] = 'Пароль должен быть не менее 8 символов';
    if ($pass !== $conf)               $errors[] = 'Пароли не совпадают';

    if (!$errors) {
        // Check unique email
        $st = db()->prepare("SELECT id FROM users WHERE email=:e LIMIT 1");
        $st->execute([':e' => $email]);
        if ($st->fetch()) $errors[] = 'Email уже зарегистрирован';
    }

    if ($errors) return ['ok' => false, 'errors' => $errors];

    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    db()->prepare("INSERT INTO users (name,email,password) VALUES (:n,:e,:p)")
        ->execute([':n'=>$name,':e'=>$email,':p'=>$hash]);

    $userId = (int)db()->lastInsertId();
    auth_login_by_id($userId);
    return ['ok' => true];
}

function auth_login(string $email, string $pass): bool
{
    $st = db()->prepare("SELECT * FROM users WHERE email=:e LIMIT 1");
    $st->execute([':e' => $email]);
    $user = $st->fetch();

    if (!$user || !password_verify($pass, $user['password'])) return false;

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];

    // Merge guest cart
    try { cart_merge_session((int)$user['id']); } catch (\Throwable) {}

    return true;
}

function auth_login_by_id(int $id): void
{
    $st = db()->prepare("SELECT * FROM users WHERE id=:id LIMIT 1");
    $st->execute([':id' => $id]);
    $user = $st->fetch();
    if (!$user) return;

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];
    try { cart_merge_session($id); } catch (\Throwable) {}
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();
}
