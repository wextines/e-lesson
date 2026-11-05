<?php
declare(strict_types=1);

class VideoHelper
{
    public static function extractYouTubeId(?string $url): ?string
    {
        if (empty($url)) return null;
        $url = trim($url);
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public static function isYouTube(?string $pathOrUrl): bool
    {
        return self::extractYouTubeId($pathOrUrl) !== null;
    }

    public static function getEmbedUrl(string $videoId): string
    {
        // Специальные настройки: без сторонних рекомендаций (rel=0), скрытие логотипа (modestbranding=1), встроенный плеер для мобильных (playsinline=1)
        return 'https://www.youtube-nocookie.com/embed/' . htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8') . '?rel=0&modestbranding=1&playsinline=1';
    }

    public static function getThumbnailUrl(string $videoId): string
    {
        return 'https://img.youtube.com/vi/' . htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8') . '/hqdefault.jpg';
    }
}
