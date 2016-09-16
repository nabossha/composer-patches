<?php

/**
 * @file
 * Contains cweagans\Composer\Resolvers\PatchesFile.
 */

namespace cweagans\Composer\Resolvers;

use cweagans\Composer\Exception\InvalidPatchesFileException;
use cweagans\Composer\Patch;
use cweagans\Composer\PatchCollection;

class PatchesFile extends ResolverBase {

    /**
     * We're going to consider patches declared in a patches file the same as
     * if they were declared in the root composer.json.
     */
    const PATCH_TYPE = 'root';

    /**
     * {@inheritDoc}
     */
    public function isEnabled()
    {
        $extra = $this->composer->getPackage()->getExtra();
        return array_key_exists('patches-file', $extra) &&
            file_exists(realpath($extra['patches-file'])) &&
            is_readable(realpath($extra['patches-file']));
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage()
    {
        return 'Gathering patches from patches file.';
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(PatchCollection $collection)
    {
        $extra = $this->composer->getPackage()->getExtra();
        $patches_file = $extra['patches-file'];
        $patches = $this->readPatchesFile($patches_file);

        foreach ($patches as $package_name => $patch_list) {
            foreach ($patch_list as $patch_entry) {
                $patch = Patch::createFromJsonObject($package_name, $patch_entry, self::PATCH_TYPE);
                $collection->addPatch($patch);
            }
        }
    }

    /**
     * Read a patches file.
     *
     * @param $patches_file
     *   A URI to a file. Can be anything accepted by file_get_contents().
     * @return array
     *   A list of patches.
     * @throws InvalidPatchesFileException
     */
    protected function readPatchesFile($patches_file)
    {
        $patches = file_get_contents($patches_file);
        $patches = json_decode($patches, TRUE);

        // First, check for JSON syntax issues.
        $error = json_last_error();
        if ($error !== JSON_ERROR_NONE) {
            switch ($error) {
                case JSON_ERROR_DEPTH:
                    $msg = 'Maximum stack depth exceeded.';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $msg = 'Underflow or the modes mismatch.';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $msg = 'Unexpected control character found.';
                    break;
                case JSON_ERROR_SYNTAX:
                    $msg = 'Syntax error, malformed JSON.';
                    break;
                case JSON_ERROR_UTF8:
                    $msg = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
                    break;
                default:
                    $msg = 'Unknown error.';
                    break;
            }

            throw new InvalidPatchesFileException($msg);
        }

        // Next, make sure there is a patches key in the file.
        if (!array_key_exists('patches', $patches)) {
            throw new InvalidPatchesFileException('No patches found.');
        }

        // If nothing is wrong at this point, we can return the list of patches.
        return $patches['patches'];
    }

}