<?php

namespace App;

/** Archives finales uniquement, jamais les fichiers temporaires ou les liens symboliques. */
class LocalBackups
{
    public function __construct(private string $directory) {}

    private function validName(string $name): bool
    {
        return $name !== '' && !str_contains($name, '/') && !str_contains($name, '\\')
            && !preg_match('/[\x00-\x1f\x7f]/', $name)
            && $this->archiveDate($name) !== null;
    }

    private function archiveDate(string $name): ?\DateTimeImmutable
    {
        if (!preg_match('/\.(tar|zip)$/D', $name)
            || preg_match_all('/\d{4}-\d{2}-\d{2}__\d{2}-\d{2}-\d{2}/', $name, $matches) !== 1) return null;
        $value = $matches[0][0];
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d__H-i-s', $value);
        // PHP peut normaliser silencieusement une date invalide : refuser ces noms.
        return $date !== false && $date->format('Y-m-d__H-i-s') === $value ? $date : null;
    }

    private function path(string $id): ?string
    {
        if (!$this->validName($id)) return null;
        $root = realpath($this->directory);
        if ($root === false) return null;
        $path = $root . DIRECTORY_SEPARATOR . $id;
        if (is_link($path) || !is_file($path) || !is_readable($path)) return null;
        return $path;
    }

    public function list(?int $maxAgeDays = null): array
    {
        if ($maxAgeDays !== null && ($maxAgeDays < 1 || $maxAgeDays > 365000)) {
            throw new \InvalidArgumentException('Invalid archive age');
        }
        $cutoff = $maxAgeDays === null ? null : (new \DateTimeImmutable())->sub(new \DateInterval('P' . $maxAgeDays . 'D'));
        if (!file_exists($this->directory)) return [];
        $names = scandir($this->directory);
        if ($names === false) throw new \RuntimeException('Archive directory unavailable');
        $files = [];
        foreach ($names as $name) {
            $path = $this->path($name);
            if ($path === null) continue;
            $created = $this->archiveDate($name);
            if ($cutoff !== null && $created <= $cutoff) continue;
            $size = filesize($path);
            $modified = filemtime($path);
            if ($size === false || $modified === false) continue;
            $files[] = ['id' => $name, 'name' => $name, 'size' => $size,
                'created_at' => $created->format('c'),
                'modified_at' => gmdate('c', $modified)];
        }
        usort($files, fn($a, $b) => strcmp($b['created_at'], $a['created_at']) ?: strcmp($a['name'], $b['name']));
        return $files;
    }

    /** Vérifie également le fichier effectivement ouvert pour refuser un remplacement par un lien. */
    public function open(string $id): mixed
    {
        $path = $this->path($id);
        if ($path === null) return null;
        $file = @fopen($path, 'rb');
        if ($file === false) return null;
        clearstatcache(true, $path);
        $current = @lstat($path);
        $opened = fstat($file);
        if ($current === false || $opened === false || ($current['mode'] & 0170000) !== 0100000
            || $current['ino'] !== $opened['ino'] || $current['dev'] !== $opened['dev']) {
            fclose($file);
            return null;
        }
        return $file;
    }
}
