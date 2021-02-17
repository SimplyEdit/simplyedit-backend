<?php

/**
 * determine where data should be stored so it is accessible from the web.
 *
 * Under normal circumstances, using __DIR__ for $basedir would suffice. However,
 * for symlinks this would cause problems. Hence we need to find the path where
 * the $basedir is supposed to be, regardless of symlinks.
 *
 * There are three ways to do so, but not all are guaranteed to be available
 * (depending on server environment and configuration).
 *
 * @return string
 *
 * @throws RuntimeException
 */
function determine_basedir()
{
    if (isset($_SERVER['SCRIPT_FILENAME'])) {
        $currentFile = $_SERVER['SCRIPT_FILENAME'];
    } elseif (isset($_SERVER['DOCUMENT_ROOT'], $_SERVER['SCRIPT_NAME'])) {
        $currentFile = $_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_NAME'];
    } elseif (isset($_SERVER['DOCUMENT_ROOT'], $_SERVER['PHP_SELF'])) {
        $currentFile = $_SERVER['DOCUMENT_ROOT'] . $_SERVER['PHP_SELF'];
    } else {
        throw new \RuntimeException('Could not reliably determine base directory to store data. ($_SERVER variables "DOCUMENT_ROOT", "PHP_SELF", "SCRIPT_FILENAME", and "SCRIPT_NAME" are all unavailable)');
    }

    return dirname(dirname($currentFile));
}
