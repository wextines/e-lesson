<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../php/config.php'; // подключение к БД через PDO в переменной $pdo

try {
    // Пользователи
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL DEFAULT 1,
        class VARCHAR(255),
        phone VARCHAR(255) DEFAULT '',
        balance DECIMAL(10,2) DEFAULT 0,
        points INT DEFAULT 0,
        email VARCHAR(255) NOT NULL,
        password VARCHAR(255),
        first_name VARCHAR(255),
        last_name VARCHAR(255),
        slug VARCHAR(255) UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1001;");

    // Курсы
    $pdo->exec("CREATE TABLE IF NOT EXISTS courses (
        course_id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL DEFAULT 1,
        subject VARCHAR(255),
        title VARCHAR(255) NOT NULL,
        description TEXT,
        price INT NOT NULL,
        subscription_days INT DEFAULT 30,
        classLevel VARCHAR(50),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Материалы
    $pdo->exec("CREATE TABLE IF NOT EXISTS materials (
        material_id INT AUTO_INCREMENT PRIMARY KEY,
        lesson_id INT,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        file_path TEXT NOT NULL,
        thumbnail_path TEXT,
        duration INT,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Ежедневные тесты
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_tests (
    daily_test_id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT,
    course_id INT NOT NULL,
    title VARCHAR(100),
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    min_score INT DEFAULT 0,
    max_score INT DEFAULT 100,
    FOREIGN KEY (material_id) REFERENCES materials(material_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Еженедельные тесты
    $pdo->exec("CREATE TABLE IF NOT EXISTS weekly_tests (
    weekly_test_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(100),
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    min_score INT DEFAULT 0,
    max_score INT DEFAULT 100,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");


    // Вопросы для ежедневных тестов
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_questions (
        daily_question_id INT AUTO_INCREMENT PRIMARY KEY,
        daily_test_id INT NOT NULL,
        sort_order INT NOT NULL DEFAULT 1,
        question_text TEXT NULL,
        question_img_url TEXT NULL,
        correct_answer VARCHAR(5),
        FOREIGN KEY (daily_test_id) REFERENCES daily_tests(daily_test_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Вопросы для еженедельных тестов
    $pdo->exec("CREATE TABLE IF NOT EXISTS weekly_questions (
        weekly_question_id INT AUTO_INCREMENT PRIMARY KEY,
        weekly_test_id INT NOT NULL,
        sort_order INT NOT NULL DEFAULT 1,
        question_text TEXT NULL,
        question_img_url TEXT NULL,
        correct_answer VARCHAR(5),
        FOREIGN KEY (weekly_test_id) REFERENCES weekly_tests(weekly_test_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Результаты тестов
    $pdo->exec("CREATE TABLE IF NOT EXISTS test_results (
    result_id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(255),
    test_type ENUM('daily','weekly') NOT NULL,
    test_id INT NOT NULL,
    score VARCHAR(50),
    get_points INT,
    completed_at TIMESTAMP,
    FOREIGN KEY (slug) REFERENCES users(slug) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");


    // Платежи
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        payment_id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL DEFAULT 1,
        user_id INT,
        amount INT NOT NULL,
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(50),
        receipt_path VARCHAR(255),
        comment VARCHAR(255),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1001;");

    // Подписки на курсы
    $pdo->exec("CREATE TABLE IF NOT EXISTS course_subs (
        subscription_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        price INT NOT NULL,
        purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        access_expires_at TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");


    $pdo->exec("CREATE TABLE IF NOT EXISTS books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    class VARCHAR(100) NOT NULL,
    preview_path VARCHAR(255) NOT NULL,
    book_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);");

    $pdo->exec("CREATE TABLE IF NOT EXISTS other_resources (
    resource_id INT AUTO_INCREMENT PRIMARY KEY,
    resource_type VARCHAR(255) NOT NULL,
    resource_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 1,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL);");

    echo "Все таблицы успешно созданы или уже существуют.\n";
} catch (PDOException $e) {
    error_log("DB setup error: " . $e->getMessage());
    echo "Ошибка при создании таблиц. Проверьте логи.\n";
    exit(1);
}
