<?php

namespace App\DependencyInjection\Compiler;

use App\Mapping\CustomAttributeDriver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Replaces the default Doctrine AttributeDriver with a custom one
 * that uses glob() instead of RecursiveDirectoryIterator
 * to work around a PHP SPL iterator bug on Docker/Windows volumes.
 */
class DoctrineDriverPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Target the attribute metadata driver service for each entity manager
        foreach ($container->getDefinitions() as $id => $definition) {
            // Match services like: doctrine.orm.default_attribute_metadata_driver
            if (preg_match('/^doctrine\.orm\.\w+_attribute_metadata_driver$/', $id)) {
                $class = $definition->getClass();
                if ($class === 'Doctrine\ORM\Mapping\Driver\AttributeDriver'
                    || $class === \Doctrine\ORM\Mapping\Driver\AttributeDriver::class
                ) {
                    $definition->setClass(CustomAttributeDriver::class);
                }
            }
        }
    }
}
