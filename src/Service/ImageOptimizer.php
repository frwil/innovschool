<?php
namespace App\Service;

use Spatie\ImageOptimizer\OptimizerChainFactory;

class ImageOptimizer
{
    public function optimize(string $filePath): void
    {
        $optimizerChain = OptimizerChainFactory::create();
        $optimizerChain->optimize($filePath);
    }
}