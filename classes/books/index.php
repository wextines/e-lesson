<?php
// Подключение к базе данных
require_once '../../php/config.php';

// Получаем класс из GET-параметра
$class = $_GET['class'] ?? ' ';


// Получаем книги для указанного класса
try {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE class = ? AND tenant_id = ? ORDER BY created_at DESC");
    $stmt->execute([$class, Tenant::getId()]);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("[ClassesBooks] Error fetching books: " . $e->getMessage());
    die("Произошла ошибка. Попробуйте позже.");
}
?>

<!DOCTYPE html>
<html lang="uz" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?= htmlspecialchars(Tenant::getPrimaryColor()) ?>">
    <link rel="apple-touch-icon" href="/img/homepage/logo.png">
    <link rel="icon" href="/img/homepage/logo.png">
    <title>Kitoblar</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--primary-color);
            color: var(--text-color);
            line-height: 1.6;
            transition: background-color 0.3s ease;
        }

        .main-content {
            max-width: 1100px;
            margin: 0 auto;
            padding: calc(var(--nav-height, 72px) + 1.5rem) 1.5rem 2rem;
        }

        .page-header {
            margin-bottom: 1.5rem;
            text-align: center;
            padding: 0.5rem 0;
        }

        .page-title {
            font-size: 1.75rem;
            color: var(--accent-color);
            margin-bottom: 0.35rem;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
        }

        .class-info {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1rem;
        }

        .book-card {
            background-color: var(--card-bg);
            border-radius: var(--radius-md, 10px);
            overflow: hidden;
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.15));
            transition: var(--transition, all 0.3s ease);
            border: 1px solid var(--border-color);
        }

        .book-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md, 0 4px 16px rgba(0,0,0,0.2));
            border-color: var(--border-color-strong);
        }

        .book-preview {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-bottom: 1px solid var(--border-color);
        }

        .book-details {
            padding: 1.15rem;
        }

        .book-title {
            font-size: 1rem;
            color: var(--heading-color);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .book-description {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-bottom: 0.85rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .read-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1rem;
            background-color: var(--accent-color);
            color: var(--primary-color);
            text-decoration: none;
            border-radius: var(--radius-sm, 6px);
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition, all 0.2s ease);
        }

        .read-btn:hover {
            background-color: var(--accent-color-hover);
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--text-secondary);
            grid-column: 1 / -1;
            background-color: var(--card-bg);
            border-radius: var(--radius-md, 10px);
            border: 1px dashed var(--border-color-strong);
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            display: block;
            opacity: 0.5;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            margin-top: 1.5rem;
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 600;
            padding: 0.6rem 1.25rem;
            background-color: var(--card-bg);
            border: 1px solid var(--border-color-strong);
            border-radius: var(--radius-sm, 6px);
            transition: var(--transition, all 0.3s ease);
            font-size: 0.9rem;
        }

        .back-link:hover {
            background-color: var(--accent-color);
            color: var(--primary-color);
            border-color: var(--accent-color);
            transform: translateY(-1px);
        }

        .back-link i {
            margin-right: 0.5rem;
        }

        .no-preview {
            background: var(--secondary-color);
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .no-preview i {
            font-size: 2.5rem;
            color: var(--text-secondary);
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .main-content {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .page-title {
                font-size: 1.4rem;
            }

            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                gap: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .books-grid {
                grid-template-columns: 1fr;
            }

            .back-link {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <?php require_once '../../php/navbar.php' ?>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title" data-i18n="books_page_title" data-value="<?= htmlspecialchars($class) ?>"></h1>
            <p class="class-info" data-i18n="books_page_info" data-value="<?= htmlspecialchars($class) ?>"></p>
        </div>

        <div class="books-grid">
            <?php if (empty($books)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3 data-i18n="no_books_title"></h3>
                    <p data-i18n="no_books_text" data-value="<?= htmlspecialchars($class) ?>"></p>
                </div>
            <?php else: ?>
                <?php foreach ($books as $book): ?>
                    <div class="book-card">
                        <?php if (!empty($book['preview_path'])): ?>
                            <img src="/file.php?type=book_preview&id=<?= $book['book_id'] ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="book-preview">
                        <?php else: ?>
                            <div class="no-preview">
                                <i class="fas fa-book"></i>
                            </div>
                        <?php endif; ?>

                        <div class="book-details">
                            <h3 class="book-title"><?= htmlspecialchars($book['title']) ?></h3>
                            <p class="book-description"><?= nl2br(htmlspecialchars($book['description'])) ?></p>
                            <a href="/file.php?type=book&id=<?= $book['book_id'] ?>" class="read-btn" target="_blank">
                                <i class="fas fa-book-open"></i>
                                <span data-i18n="read_button"></span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <a href="/classes/?<?= ($class === '1-4' ? 'course' : 'class') ?>=<?= $class ?>" class="back-link">
            <i class="fas fa-arrow-left"></i>
            <span data-i18n="back_button"></span>
        </a>
    </div>
</body>


</html>