<?php
// Script de warmup : à appeler UNE SEULE FOIS après redémarrage
// Exécute tout ce qui est lent pour que les requêtes suivantes soient rapides

$start = microtime(true);

require dirname(__DIR__).'/vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$step = function(string $label, float $t): float {
    $elapsed = round((microtime(true) - $t) * 1000);
    echo str_pad("$label:", 40) . "{$elapsed}ms\n";
    return microtime(true);
};

$t = microtime(true);
$kernel = new App\Kernel('prod', false);
$kernel->boot();
$c = $kernel->getContainer();
$t = $step('Kernel boot', $t);

// Force Doctrine metadata loading (met en cache Redis)
$em = $c->get('doctrine.orm.entity_manager');
$allMeta = $em->getMetadataFactory()->getAllMetadata();
$t = $step("Doctrine metadata (" . count($allMeta) . " entities)", $t);

// Vérifie le cache utilisé
$config = $em->getConfiguration();
$metaCache = $config->getMetadataCache();
echo str_pad("Metadata cache driver:", 40) . get_class($metaCache) . "\n";

// Test query
$conn = $em->getConnection();
$conn->executeQuery('SELECT 1');
$t = $step('DB ping', $t);

// Total
$total = round((microtime(true) - $start) * 1000);
echo str_repeat('-', 55) . "\n";
echo str_pad("TOTAL:", 40) . "{$total}ms\n";
echo "\n✅ Warmup terminé. Recharge la page d'accueil, elle devrait être rapide.\n";
