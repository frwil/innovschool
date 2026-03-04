<?php
namespace App\Service;

class StudentNameSimilarityService
{
    /**
     * Compare un nom donné à une liste de noms et retourne ceux ayant une similarité >= seuil (en %)
     * @param string $inputName
     * @param array $studentNames Liste de noms (chaque élément : string)
     * @param int $threshold Pourcentage minimal de similarité (0-100)
     * @return array Liste des noms similaires (chaque élément : ['name' => ..., 'similarity' => ...])
     */
    public function findSimilarNames(string $inputName, array $studentNames, int $threshold = 90): array
    {
        $results = [];
        foreach ($studentNames as $name) {
            similar_text(mb_strtolower($inputName), mb_strtolower($name), $percent);
            if ($percent >= $threshold) {
                $results[] = [
                    'name' => $name,
                    'similarity' => round($percent, 2)
                ];
            }
        }
        // Tri décroissant par similarité
        usort($results, function($a, $b) { return $b['similarity'] <=> $a['similarity']; });
        return $results;
    }
}
