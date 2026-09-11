<?php

declare(strict_types=1);

namespace App\Service\Development;

use App\Service\Core\Urlizer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * FileUrlizerService.
 *
 * To generate file archive with filename urlized
 *
 * @author Sébastien FOURNIER <fournier.sebastien@outlook.com>
 */
class FileUrlizerService
{
    /**
     * FileUrlizerService constructor.
     */
    public function __construct(private readonly string $projectDir)
    {
    }

    /**
     * Execute urlizer.
     */
    public function execute(array $files): bool|string
    {
        $tmpDirname = $this->projectDir.'/public/uploads/tmp/rename/';
        $tmpDirname = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $tmpDirname);

        foreach ($files as $file) {
            /** @var UploadedFile $file */
            $extension = $file->guessClientExtension();
            $filename = str_replace('.'.$extension, '', $file->getClientOriginalName());

            $file->move(
                $tmpDirname,
                Urlizer::urlize($filename).'.'.$extension
            );
        }

        return $this->zip($tmpDirname);
    }

    /**
     * Generate ZipArchive.
     *
     * Returns the absolute path of the generated archive, or false if nothing could be archived.
     */
    public function zip(string $dirname, ?string $filename = null): bool|string
    {
        $filesystem = new Filesystem();

        if (!$filesystem->exists($dirname)) {
            return false;
        }

        $finder = Finder::create()->files()->in($dirname);
        if (!$finder->count()) {
            return false;
        }

        $zipPath = $this->archivePath($filename ?: 'rename-files.zip');
        $filesystem->mkdir(dirname($zipPath));
        $filesystem->remove($zipPath);

        $zip = new \ZipArchive();
        if (true !== $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            return false;
        }

        foreach ($finder as $file) {
            $zip->addFile($file->getPathname(), $file->getRelativePathname());
        }

        if (!$zip->close()) {
            $filesystem->remove($zipPath);

            return false;
        }

        return $zipPath;
    }

    /**
     * Generate a ZipArchive from an explicit entry name => absolute path map.
     *
     * Les fichiers sont lus sur place : aucune copie temporaire, et l'arborescence
     * de l'archive est portée par les clés du tableau.
     *
     * Returns the absolute path of the generated archive, or false if nothing could be archived.
     *
     * @param array<string, string> $entries
     */
    public function zipEntries(array $entries, string $filename): bool|string
    {
        if (!$entries) {
            return false;
        }

        $filesystem = new Filesystem();
        $zipPath = $this->archivePath($filename);
        $filesystem->mkdir(dirname($zipPath));
        $filesystem->remove($zipPath);

        $zip = new \ZipArchive();
        if (true !== $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            return false;
        }

        $added = 0;
        foreach ($entries as $entryName => $path) {
            if ($filesystem->exists($path) && is_file($path)) {
                $zip->addFile($path, $entryName);
                ++$added;
            }
        }

        if (!$added) {
            $zip->close();
            $filesystem->remove($zipPath);

            return false;
        }

        if (!$zip->close()) {
            $filesystem->remove($zipPath);

            return false;
        }

        return $zipPath;
    }

    /**
     * Get absolute archive path, outside of any web served directory.
     */
    private function archivePath(string $filename): string
    {
        $filename = basename($filename);
        $filename = str_ends_with(strtolower($filename), '.zip') ? substr($filename, 0, -4) : $filename;
        $filename = Urlizer::urlize($filename) ?: 'archive';
        $path = $this->projectDir.'/var/tmp/archives/'.$filename.'.zip';

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
