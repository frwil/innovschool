<?php
// src/Entity/Module.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Module
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 50)]
    private string $name;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    // getters/setters...
    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }
}