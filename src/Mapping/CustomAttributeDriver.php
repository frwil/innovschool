<?php

namespace App\Mapping;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\MappingException;
use ReflectionClass;
use function get_declared_classes;
use function glob;
use function in_array;
use function realpath;
use function str_replace;
use function strpos;

/**
 * Custom attribute driver that uses glob() instead of RecursiveDirectoryIterator
 * to work around a PHP SPL iterator bug on Docker/Windows volume mounts where
 * DirectoryIterator/RecursiveDirectoryIterator returns an incomplete file listing.
 */
class CustomAttributeDriver extends AttributeDriver
{
    /**
     * {@inheritDoc}
     *
     * Overridden to use glob() instead of RecursiveDirectoryIterator.
     */
    public function getAllClassNames(): array
    {
        if ($this->classNames !== null) {
            return $this->classNames;
        }

        if ($this->paths === []) {
            throw MappingException::pathRequiredForDriver(self::class);
        }

        $classes = [];
        $includedFiles = [];

        foreach ($this->paths as $path) {
            if (!is_dir($path)) {
                throw MappingException::fileMappingDriversRequireConfiguredDirectoryPath($path);
            }

            // Use glob() to find all PHP files recursively (works around SPL iterator bug)
            $pattern = rtrim($path, '/') . '/*' . $this->fileExtension;
            $files = glob($pattern);

            if ($files === false) {
                continue;
            }

            foreach ($files as $sourceFile) {
                if (preg_match('(^phar:)i', $sourceFile) === 0) {
                    $resolvedPath = realpath($sourceFile);
                    if ($resolvedPath === false) {
                        continue;
                    }
                    $sourceFile = $resolvedPath;
                }

                $excluded = false;
                foreach ($this->excludePaths as $excludePath) {
                    $realExcludePath = realpath($excludePath);
                    if ($realExcludePath === false) {
                        continue;
                    }
                    $exclude = str_replace('\\', '/', $realExcludePath);
                    $current = str_replace('\\', '/', $sourceFile);

                    if (strpos($current, $exclude) !== false) {
                        $excluded = true;
                        break;
                    }
                }

                if ($excluded) {
                    continue;
                }

                require_once $sourceFile;

                $includedFiles[] = $sourceFile;
            }
        }

        $declared = get_declared_classes();

        foreach ($declared as $className) {
            $rc = new ReflectionClass($className);

            $sourceFile = $rc->getFileName();
            if ($sourceFile === false) {
                continue;
            }

            if (!in_array($sourceFile, $includedFiles, true) || $this->isTransient($className)) {
                continue;
            }

            $classes[] = $className;
        }

        $this->classNames = $classes;

        return $classes;
    }
}
