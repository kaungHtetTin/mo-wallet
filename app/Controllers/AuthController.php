<?php
namespace App\Controllers;

use App\Services\Finance;
use App\ValidationException;
use Mini\Database as DB;

final class AuthController
{
    public function login(): void
    {
        if (!DB::fetchValue('SELECT COUNT(*) FROM owner_users')) redirect('setup');
        if (!empty($_SESSION['user_id'])) redirect('');
        view('auth', ['setup' => false, 'title' => 'Welcome back'], null);
    }

    public function setup(): void
    {
        if (DB::fetchValue('SELECT COUNT(*) FROM owner_users')) redirect('login');
        view('auth', ['setup' => true, 'title' => 'Your finances, in one place'], null);
    }

    public function create(): void
    {
        if (DB::fetchValue('SELECT COUNT(*) FROM owner_users')) redirect('login');
        try {
            $name = Finance::text($_POST, 'name');
            $email = Finance::text($_POST, 'email', 190);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) Finance::fail('email', 'Enter a valid email address.');
            $password = input('password');
            self::password($password);
            if ($password !== input('password_confirmation')) Finance::fail('password_confirmation', 'Passwords do not match.');
            try {
                DB::insert('owner_users', ['id' => 1, 'name' => $name, 'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT)]);
            } catch (\PDOException $e) {
                if ($e->getCode() === '23000') redirect('login');
                throw $e;
            }
            $this->authenticate(DB::fetch('SELECT * FROM owner_users WHERE id=1'));
            redirect('', t('Your account is ready. Add a business to get started.'));
        } catch (ValidationException $e) { self::errors($e, 'setup'); }
    }

    public function signIn(): void
    {
        if (!DB::fetchValue('SELECT COUNT(*) FROM owner_users')) redirect('setup');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $now = time();
        $allowed = DB::transaction(function () use ($ip, $now) {
            DB::statement('INSERT IGNORE INTO login_attempts (ip, attempts, window_start) VALUES (?,0,?)', [$ip, $now]);
            $attempt = DB::fetch('SELECT * FROM login_attempts WHERE ip=? FOR UPDATE', [$ip]);
            if ($now - (int) $attempt['window_start'] >= 900) {
                DB::statement('UPDATE login_attempts SET attempts=1, window_start=? WHERE ip=?', [$now, $ip]);
                return true;
            }
            if ((int) $attempt['attempts'] >= 10) return false;
            DB::statement('UPDATE login_attempts SET attempts=attempts+1 WHERE ip=?', [$ip]);
            return true;
        });
        if (!$allowed) { $_SESSION['errors'] = ['form' => 'Too many attempts. Please try again in 15 minutes.']; redirect('login'); }
        $owner = DB::fetch('SELECT * FROM owner_users WHERE id=1');
        if (!$owner || strcasecmp($owner['email'], input('email')) !== 0 || !password_verify(input('password'), $owner['password'])) {
            $_SESSION['errors'] = ['form' => 'Email or password is incorrect.'];
            $_SESSION['old'] = ['email' => input('email')];
            redirect('login');
        }
        DB::statement('DELETE FROM login_attempts WHERE ip=?', [$ip]);
        if (password_needs_rehash($owner['password'], PASSWORD_DEFAULT)) DB::update('owner_users', 1, ['password' => password_hash(input('password'), PASSWORD_DEFAULT)]);
        $this->authenticate($owner);
        redirect('');
    }

    private function authenticate(array $owner): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = 1;
        $_SESSION['session_version'] = (int) $owner['session_version'];
        $_SESSION['last_active'] = time();
        $_SESSION['owner'] = array_intersect_key($owner, array_flip(['id','name','email']));
        unset($_SESSION['_token'], $_SESSION['old'], $_SESSION['errors'], $_SESSION['mutations']);
    }

    public function logout(): void
    {
        $locale = $_SESSION['locale'] ?? 'en';
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['locale'] = $locale;
        redirect('login');
    }

    public function locale(): void
    {
        $_SESSION['locale'] = input('locale') === 'my' ? 'my' : 'en';
        $path = input('return');
        if (!preg_match('#^(?:|login|setup|transactions(?:/[0-9]+/edit|/new)?|debts(?:/[0-9]+(?:/edit)?|/new)?|manage/(?:businesses|books|categories|people)(?:/[0-9]+/edit|/new)?|people/import|reports|settings)(?:\?[^\r\n]*)?$#D', $path)) $path = '';
        redirect($path);
    }

    public static function password(string $password): void
    {
        if (strlen($password) < 12 || strlen($password) > 72) Finance::fail('password', 'Use a password between 12 and 72 bytes.');
        if (str_contains($password,"\0")) Finance::fail('password','Please enter a valid value.');
    }

    public static function errors(ValidationException $e, string $path): never
    {
        $_SESSION['errors'] = $e->errors;
        $_SESSION['old'] = array_filter($_POST, fn ($v, $k) => is_scalar($v) && !in_array($k, ['password','password_confirmation','current_password','_token'], true), ARRAY_FILTER_USE_BOTH);
        redirect($path);
    }
}
