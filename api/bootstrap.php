<?php
declare(strict_types=1);

// Autoloader PSR-4 sederhana: Pinjemobil\Foo\Bar -> src/Foo/Bar.php
spl_autoload_register(function (string $class): void {
    $prefix = 'Pinjemobil\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

Pinjemobil\Env::load(dirname(__DIR__) . '/.env');
return require __DIR__ . '/config/config.php';
