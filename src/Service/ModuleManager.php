<?php
// src/Service/ModuleManager.php
namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class ModuleManager
{
    private $em;
    private $cache = [];

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function isEnabled(string $module): bool
    {
        if (!isset($this->cache[$module])) {
            $mod = $this->em->getRepository(\App\Entity\Module::class)->find($module);
            
            $this->cache[$module] = $mod!==null ? $mod && $mod->isEnabled() : true;
        }
        return $this->cache[$module];
    }
}