<?php

namespace Jinraynor1\BplusTree\Memory;

use Exception;
use Jinraynor1\BplusTree\Exceptions\ReachedEndOfFile;
use Jinraynor1\BplusTree\Exceptions\ValueError;
use Jinraynor1\BplusTree\Helpers\System;


class File
{
    /**
     * Open a file and its directory.
     *
     * The file is opened in binary mode and created if it does not exist.
     * Both file descriptors must be closed after use to prevent them from
     * leaking.
     *
     * On Windows, the directory is not opened, as it is useless.
     * @param $path
     * @return array
     * @throws Exception
     */
    public static function open_file_in_dir($path)
    {
        $directory = dirname($path);

        if (!is_dir($directory))
            throw new ValueError(sprintf("No directory %s", $directory));

        if (!file_exists($path))
            $file_fd = fopen($path, 'x+b');
        else
            $file_fd = fopen($path, 'r+b');

        if (System::isWindows()) {
            # Opening a directory is not possible on Windows, but that is not
            # a problem since Windows does not need to fsync the directory in
            # order to persist metadata
            $dir_fd = null;
        } else {
            $dir_fd = null; // cannot open a dir in php without dio lib =(
        }

        return array($file_fd, $dir_fd);
    }


    public static function write_to_file($file_fd, $dir_fileno = null, $data = null, $fsync = true)
    {
        $length_to_write = strlen($data);
        $written = 0;
        while ($written < $length_to_write) {
            $written += fwrite($file_fd, substr($data, $written));
        }
        if ($fsync) {
            self::fsync_file_and_dir($file_fd, $dir_fileno);

        }
    }

    public static function fsync_file_and_dir($file_fd, $dir_fd)
    {

        if (function_exists('fsync')) {
            fsync($file_fd);
            if ($dir_fd)
                fsync($dir_fd);
        } else {
            fflush($file_fd);

            if ($dir_fd)
                fflush($dir_fd);

        }
    }

    public static function read_from_file($file_fd, $start, $stop)
    {

        $length = $stop - $start;
        assert($length >= 0);
        fseek($file_fd, $start);
        $data = "";
        while (ftell($file_fd) < $stop) {
            $read_data = fread($file_fd, $stop - ftell($file_fd));
            if (!$read_data && feof($file_fd)) {
                throw new ReachedEndOfFile('Read until the end of file');
            }
            $data .= $read_data;
        }
        assert(strlen($data) == $length);
        return $data;
    }


}