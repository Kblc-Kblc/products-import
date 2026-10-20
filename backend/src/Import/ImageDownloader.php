<?php

declare(strict_types=1);

namespace App\Import;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ImageDownloader
{
    private const MAX_BYTES = 10 * 1024 * 1024;
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    /** @param HttpClientInterface $http must block private networks: URLs come from a user-uploaded file */
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $storageDir,
    ) {}

    /**
     * Downloads the image once per URL (products often share photos).
     *
     * @return string path relative to the storage dir, e.g. "images/ab12….jpg"
     */
    public function download(string $url): string
    {
        if (!in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new \RuntimeException("неподдерживаемая ссылка на изображение: $url");
        }

        $existing = glob($this->storageDir . '/images/' . sha1($url) . '.*');
        if ($existing) {
            return 'images/' . basename($existing[0]);
        }

        try {
            $response = $this->http->request('GET', $url, ['timeout' => 10, 'max_redirects' => 3]);
            if ((int) ($response->getHeaders()['content-length'][0] ?? 0) > self::MAX_BYTES) {
                throw new \RuntimeException('файл больше 10 МБ');
            }
            $body = $response->getContent();
            if (strlen($body) > self::MAX_BYTES) {
                throw new \RuntimeException('файл больше 10 МБ');
            }
            // Sniff the bytes: servers often send images as binary/octet-stream
            $type = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);
            $extension = self::EXTENSIONS[$type] ?? throw new \RuntimeException("не изображение ($type)");
        } catch (\Throwable $e) {
            throw new \RuntimeException("не удалось скачать $url: {$e->getMessage()}", 0, $e);
        }

        $relative = 'images/' . sha1($url) . '.' . $extension;
        is_dir($this->storageDir . '/images') || mkdir($this->storageDir . '/images', 0o775, true);
        file_put_contents($this->storageDir . '/' . $relative, $body);

        return $relative;
    }
}
