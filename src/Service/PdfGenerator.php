<?php

namespace App\Service;

use Mpdf\Mpdf;
use setasign\Fpdi\Fpdi;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PdfGenerator
{
    private ParameterBagInterface $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    /**
     * Génère un PDF à partir du contenu HTML
     */
    public function generatePdf(string $html, string $filename = 'bulletin.pdf', bool $download = true): Response
    {
        try {
            // Configuration mPDF optimisée pour les bordures
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 25,
                'margin_bottom' => 20,
                'margin_header' => 5,
                'margin_footer' => 10,
                'default_font' => 'dejavusans',
                'dpi' => 150, // Augmenter la résolution
                'img_dpi' => 150, // Résolution des images
                'default_font_size' => 9,
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'showBorders' => true, // Important pour les bordures
                'use_kwt' => true, // Keep-with-table: essaie de garder les tableaux entiers
                'shrink_tables_to_fit' => 0, // Ne pas réduire les tableaux
                'keep_table_proportions' => true,
            ]);

            // Activer explicitement les en-têtes et pieds de page
            $mpdf->setAutoTopMargin = 'stretch';
            $mpdf->setAutoBottomMargin = 'stretch';

            // CSS optimisé pour les bordures
            $bootstrapCss = @file_get_contents($this->params->get('kernel.project_dir') . '/public/css/bootstrap5.min.css');
            if (!$bootstrapCss) {
                $bootstrapCss = @file_get_contents($this->params->get('kernel.project_dir') . '/public/css/bootstrap.min.css');
            }

            $css = '
                <style>
                    ' . $bootstrapCss . '
                    
                    @page {
                        margin: 3mm;
                        margin-top: 10mm;
                        margin-bottom: 10mm;
                        margin-left: 20mm;
                    }
                    
                    body {
                        font-family: DejaVu Sans, sans-serif;
                        font-size: 7pt !important;
                        line-height: 1 !important;
                        margin: 0;
                        padding: 0;
                        /*zoom: 50% !important;*/
                        transform: scale(0.8);
                        transform-origin: top left;
                        -webkit-print-color-adjust: exact !important;
                        color-adjust: exact !important;
                    }
                    
                    /* STYLES SPÉCIFIQUES POUR LES BORDURES DES TABLEAUX */
                    table {
                        width: 100%;
                        border-collapse: collapse !important;
                        border-spacing: 0;
                        font-size: 7pt;
                        border: 2px solid #000000 !important; /* Bordure externe */
                    }
                    
                    th, td {
                        border: 2px solid #000000 !important;
                        padding: 3px !important;
                        text-align: left;
                        background-clip: padding-box;
                        width : 15px !important;
                        -webkit-print-color-adjust: exact !important;
                        color-adjust: exact !important;
                    }
                    
                    th {
                        background-color: #f8f9fa !important;
                        font-weight: bold;
                        border: 2px solid #000000 !important;
                    }
                    
                    /* Assurer que les bordures sont visibles */
                    .table-bordered {
                        border: 1px solid #000000 !important;
                    }
                    
                    .table-bordered th,
                    .table-bordered td {
                        border: 1px solid #000000 !important;
                    }
                    
                    /* Forcer l affichage des bordures */
                    * {
                        border-collapse: collapse !important;
                    }

                    .table-recap td{
                        font-weight: bold;
                        font-style: italic;
                    }
                    
                    .page-break {
                        page-break-after: always;
                    }
                    
                    .no-break {
                        page-break-inside: avoid;
                    }
                    
                    .cell-number{
                        text-align: center !important;
                        width: 10px !important;

                    }

                    /* Style spécifique pour les bordures visibles */
                    *[border="1"], *[border="0"], table, td, th {
                        border-collapse: collapse !important;
                        border-spacing: 0 !important;
                    }
                    
                    /* Assurer que les cellules vides ont des bordures */
                    td:empty, th:empty {
                        border: 1px solid #000000 !important;
                    }
                    
                    /* Style pour les rangées et colonnes spécifiques */
                    .bg-secondary th, .bg-secondary td {
                        background-color: #6c757d !important;
                        color: white !important;
                        border: 1px solid #000000 !important;
                    }

                    .coloured-header th{
                        background-color: #a9f3d4 !important;
                        border: 1px solid #000000 !important;
                    }

                    .teacher-name {
                        font-style: italic;
                        font-size: 6pt !important;
                        display: block !important;
                        margin:0 !important
                    }

                    .bulletin-header p {
                        line-height: 1 !important;
                    }
                    
                    /* SYSTEME DE GRILLE COMPATIBLE PDF */
                    .container-fluid, .container {
                        width: 100% !important;
                        padding-left: 5px;
                        padding-right: 5px;
                        box-sizing: border-box;
                    }
                    
                    /* ROW avec clearfix pour PDF */
                    .row {
                        width: 100%;
                        display: block;
                        margin-left: 0;
                        margin-right: 0;
                        margin-bottom: 8px;
                        clear: both;
                    }
                    .row::after {
                        content: "";
                        display: table;
                        clear: both;
                    }
                    
                    /* COL avec float pour PDF */
                    .col, .col-1, .col-2, .col-3, .col-4, .col-5, .col-6, 
                    .col-7, .col-8, .col-9, .col-10, .col-11, .col-12,
                    .col-sm-1, .col-sm-2, .col-sm-3, .col-sm-4, .col-sm-5, .col-sm-6,
                    .col-sm-7, .col-sm-8, .col-sm-9, .col-sm-10, .col-sm-11, .col-sm-12,
                    .col-md-1, .col-md-2, .col-md-3, .col-md-4, .col-md-5, .col-md-6,
                    .col-md-7, .col-md-8, .col-md-9, .col-md-10, .col-md-11, .col-md-12,
                    .col-lg-1, .col-lg-2, .col-lg-3, .col-lg-4, .col-lg-5, .col-lg-6,
                    .col-lg-7, .col-lg-8, .col-lg-9, .col-lg-10, .col-lg-11, .col-lg-12,
                    .col-xl-1, .col-xl-2, .col-xl-3, .col-xl-4, .col-xl-5, .col-xl-6,
                    .col-xl-7, .col-xl-8, .col-xl-9, .col-xl-10, .col-xl-11, .col-xl-12 {
                        float: left;
                        padding-left: 5px;
                        padding-right: 5px;
                        box-sizing: border-box;
                        min-height: 1px;
                    }
                    
                    /* Largeurs des colonnes pour PDF */
                    .col-1 { width: 7.333333%; }
                    .col-2 { width: 15.666667%; }
                    .col-3 { width: 23%; }
                    .col-4 { width: 25.333333%; }
                    .col-5 { width: 39.666667%; }
                    .col-6 { width: 47%; }
                    .col-7 { width: 57.333333%; }
                    .col-8 { width: 65.666667%; }
                    .col-9 { width: 74%; }
                    .col-10 { width: 82.333333%; }
                    .col-11 { width: 90.666667%; }
                    .col-12 { width: 100%; float: none; }
                    
                    /* Désactiver flexbox pour PDF */
                    .d-flex, .flex-row, .flex-column {
                        display: block !important;
                    }
                    
                    /* Alternative pour l alignement */
                    .text-center { text-align: center !important; }
                    .text-left { text-align: left !important; }
                    .text-right { text-align: right !important; }
                    .text-end { text-align: right !important; }
                    
                    .align-items-center > [class*="col-"] {
                        vertical-align: middle;
                        float: none;
                        display: inline-block;
                    }
                    
                    .justify-content-between > [class*="col-"] {
                        margin-right: 10px;
                    }
                    .justify-content-between > [class*="col-"]:last-child {
                        margin-right: 0;
                    }
                    
                    .text-danger { color: #dc3545; }
                    .text-success { color: #28a745; }
                    .bg-secondary { background-color: #6c757d; color: white; }
                    .bg-light { background-color: #f8f9fa; }
                    
                    .progress-bar { 
                        height: 20px; 
                        background-color: #e9ecef; 
                        border-radius: 4px; 
                        overflow: hidden;
                    }
                    .progress-bar .progress { 
                        height: 100%; 
                        background-color: #007bff; 
                    }
                    
                    /* Correction pour les éléments inline */
                    .d-inline { display: inline !important; }
                    .d-inline-block { display: inline-block !important; }
                    .d-block { display: block !important; }
                    
                    /* Espacement */
                    .m-0 { margin: 0 !important; }
                    .m-1 { margin: 4px !important; }
                    .m-2 { margin: 8px !important; }
                    .m-3 { margin: 16px !important; }
                    .p-0 { padding: 0 !important; }
                    .p-1 { padding: 4px !important; }
                    .p-2 { padding: 8px !important; }
                    .p-3 { padding: 16px !important; }

                    .no-break {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
        
        .signature-section {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            margin-top: 10px;
        }
                </style>
            ';

            // Définir l'en-tête et le pied de page pour chaque bulletin
            $mpdf->SetHTMLHeader('
                <div style="text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px;">
                    <strong>Bulletin scolaire</strong>
                </div>
            ');

            $mpdf->SetHTMLFooter('
                <div style="text-align: center; border-top: 1px solid #ccc; padding-top: 5px;color:black;">
                    Page {PAGENO} / {nbpg}
                </div>
            ');

            // Écrire le CSS d'abord
            $mpdf->WriteHTML($css);

            // Écrire le contenu HTML
            $mpdf->WriteHTML($html);

            if ($download) {
                return new Response(
                    $mpdf->Output($filename, Destination::STRING_RETURN),
                    Response::HTTP_OK,
                    [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    ]
                );
            } else {
                $filePath = $this->params->get('kernel.project_dir') . '/public/uploads/bulletins/' . $filename;
                // Créer le répertoire s'il n'existe pas
                $dir = dirname($filePath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $mpdf->Output($filePath, Destination::FILE);

                return new Response(json_encode([
                    'status' => 'success',
                    'filePath' => '/uploads/bulletins/' . $filename
                ]));
            }
        } catch (\Exception $e) {
            return new Response(json_encode([
                'status' => 'error',
                'message' => 'Erreur lors de la génération du PDF: ' . $e->getMessage()
            ]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Génère un PDF multi-pages avec plusieurs bulletins
     */
    public function generateMultipleBulletinsPdf(array $bulletinsHtml, string $filename = 'bulletins_classe.pdf'): string
    {
        try {
            // Validation stricte du tableau d'entrée
            if (!is_array($bulletinsHtml) || empty($bulletinsHtml)) {
                throw new \InvalidArgumentException('Le tableau des bulletins est vide ou invalide.');
            }

            // Filtre les éléments non valides
            $validBulletins = array_values(array_filter($bulletinsHtml, function ($html) {
                return is_string($html) && !empty(trim(strip_tags($html)));
            }));

            if (empty($validBulletins)) {
                throw new \InvalidArgumentException('Aucun contenu HTML valide trouvé dans les bulletins.');
            }

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 25,
                'margin_bottom' => 20,
                'margin_header' => 5,
                'margin_footer' => 10,
                'default_font' => 'dejavusans',
                'dpi' => 150, // Augmenter la résolution
                'img_dpi' => 150, // Résolution des images
                'default_font_size' => 9,
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'showBorders' => true, // Important pour les bordures
                'use_kwt' => true, // Keep-with-table: essaie de garder les tableaux entiers
                'shrink_tables_to_fit' => 0, // Ne pas réduire les tableaux
                'keep_table_proportions' => true,
            ]);

            // Activer explicitement les en-têtes et pieds de page
            $mpdf->setAutoTopMargin = 'stretch';
            $mpdf->setAutoBottomMargin = 'stretch';

            // Définir l'en-tête et le pied de page
            $mpdf->SetHTMLHeader('
            <div style="text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px;">
                <strong>Bulletin scolaire</strong>
            </div>
        ');

            $mpdf->SetHTMLFooter('
            <div style="text-align: center; border-top: 1px solid #ccc; padding-top: 5px; font-size: 8pt;">
                Page {PAGENO} / {nbpg}
            </div>
        ');

            // CSS Bootstrap
            $bootstrapCss = @file_get_contents($this->params->get('kernel.project_dir') . '/public/css/bootstrap5.min.css');

            $css = '
        <style>
            ' . $bootstrapCss . '
            
            @page {
                margin: 3mm;
                margin-top: 10mm;
                margin-bottom: 10mm;
                margin-left: 20mm;
            }
            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 7pt;
                line-height: 1;
                margin: 0;
                padding: 0;
                /*zoom: 50% !important;*/
                transform: scale(0.8);
                transform-origin: top left;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            .bulletin-container {
                margin-bottom: 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 7pt;
                margin-bottom: 10px;
            }
            th, td {
                border: 2px solid #000000 !important;
                padding: 3px !important;
                text-align: left;
                background-clip: padding-box;
                width : 15px !important;
            }
            
            th {
                background-color: #f8f9fa !important;
                font-weight: bold;
                border: 1px solid #000000 !important;
            }
            
            /* Assurer que les bordures sont visibles */
            .table-bordered {
                border: 1px solid #000000 !important;
            }
            
            .table-bordered th,
            .table-bordered td {
                border: 1px solid #000000 !important;
            }

            /* Style spécifique pour les bordures visibles */
            *[border="1"], *[border="0"], table, td, th {
                border-collapse: collapse !important;
                border-spacing: 0 !important;
            }
            
            /* Assurer que les cellules vides ont des bordures */
            td:empty, th:empty {
                border: 1px solid #000000 !important;
            }
            
            /* Style pour les rangées et colonnes spécifiques */
            .bg-secondary th, .bg-secondary td {
                background-color: #6c757d !important;
                color: white !important;
                border: 1px solid #000000 !important;
            }
            
            /* SYSTEME DE GRILLE COMPATIBLE PDF */
            .container-fluid, .container {
                width: 100% !important;
                padding-left: 5px;
                padding-right: 5px;
                box-sizing: border-box;
            }
            
            .row {
                width: 100%;
                display: block;
                margin-left: 0;
                margin-right: 0;
                margin-bottom: 8px;
                clear: both;
            }
            
            .col, .col-1, .col-2, .col-3, .col-4, .col-5, .col-6, 
            .col-7, .col-8, .col-9, .col-10, .col-11, .col-12 {
                float: left;
                padding-left: 5px;
                padding-right: 5px;
                box-sizing: border-box;
            }

            .cell-number{
                text-align: center !important;
                width: 10px !important;
            }

            .coloured-header th{
                background-color: #a9f3d4 !important;
            }

            .bulletin-header p {
                line-height: 1 !important;
            }

            .teacher-name {
                font-style: italic;
                font-size: 6pt !important;
                display: block !important;
                margin:0 !important;
            }
            
            /* Largeurs des colonnes pour PDF */
            .col-1 { width: 7.333333%; }
            .col-2 { width: 15.666667%; }
            .col-3 { width: 23%; }
            .col-4 { width: 25.333333%; }
            .col-5 { width: 39.666667%; }
            .col-6 { width: 47%; }
            .col-7 { width: 57.333333%; }
            .col-8 { width: 65.666667%; }
            .col-9 { width: 74%; }
            .col-10 { width: 82.333333%; }
            .col-11 { width: 90.666667%; }
            .col-12 { width: 100%; float: none; }
            
            .d-flex { display: block !important; }
            
            .student-header {
                margin-bottom: 10px;
            }
            
            .text-center { text-align: center !important; }
            .text-left { text-align: left !important; }
            .text-right { text-align: right !important; }
            .text-end { text-align: right !important; }
            
            .text-danger { color: #dc3545; }
            .text-success { color: #28a745; }
            .bg-secondary { background-color: #6c757d; color: white; }
            .bg-light { background-color: #f8f9fa; }
            .table-bordered { border: 1px solid #000; }
        </style>
    ';

            // ÉCRIRE LE CSS
            $mpdf->WriteHTML($css);

            // TRAITER CHAQUE BULLETIN
            foreach ($validBulletins as $index => $bulletinHtml) {
                // Ajouter une page seulement si ce n'est pas le premier bulletin
                if ($index > 0) {
                    $mpdf->AddPage();
                }

                // ÉCRIRE LE CONTENU
                $mpdf->WriteHTML($bulletinHtml);
            }

            $filePath = $this->params->get('kernel.project_dir') . '/public/uploads/bulletins/' . $filename;

            // Créer le répertoire si nécessaire
            $dir = dirname($filePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            // Sauvegarder le fichier
            $mpdf->Output($filePath, Destination::FILE);

            // Retourner le chemin relatif pour le téléchargement
            return '/uploads/bulletins/' . $filename;
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la génération du PDF multiple: ' . $e->getMessage());
        }
    }

    /**
     * Génère un PDF multi-pages avec plusieurs bulletins et le sauvegarde dans un fichier
     */
    public function generateMultipleBulletinsPdfToFile(array $bulletinsHtml, string $filePath): void
    {
        try {
            // Validation des entrées
            if (empty($bulletinsHtml)) {
                throw new \InvalidArgumentException('Le tableau des bulletins est vide');
            }

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 25,
                'margin_bottom' => 20,
                'margin_header' => 5,
                'margin_footer' => 10,
                'default_font' => 'dejavusans',
                'dpi' => 150, // Augmenter la résolution
                'img_dpi' => 150, // Résolution des images
                'default_font_size' => 9,
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'showBorders' => true, // Important pour les bordures
                'use_kwt' => true, // Keep-with-table: essaie de garder les tableaux entiers
                'shrink_tables_to_fit' => 0, // Ne pas réduire les tableaux
                'keep_table_proportions' => true,
            ]);

            // Activer explicitement les en-têtes et pieds de page
            $mpdf->setAutoTopMargin = 'stretch';
            $mpdf->setAutoBottomMargin = 'stretch';

            // CSS Bootstrap
            $bootstrapCss = @file_get_contents($this->params->get('kernel.project_dir') . '/public/css/bootstrap.min.css');
            if (!$bootstrapCss) {
                $bootstrapCss = '';
            }

            $css = '
        <style>
            ' . $bootstrapCss . '
            
            @page {
                margin: 0;
                margin-top: 10mm;
                margin-bottom: 10mm;
                margin-left: 20mm;
            }
            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 7pt;
                line-height: 1.2;
                margin: 0;
                padding: 0;
                /*zoom: 50% !important;*/
                transform: scale(0.8);
                transform-origin: top left;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            /* ÉVITER LES SAUTS DE PAGE AUTOMATIQUES PROBLÉMATIQUES */
            .bulletin-page {
                page-break-after: auto;
                margin-bottom: 0;
            }
            
            /* Forcer un saut de page avant chaque bulletin sauf le premier */
            .bulletin-page:not(:first-child) {
                page-break-before: always;
            }
            
            .bulletin-page:last-child {
                page-break-after: avoid;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 7pt;
                margin-bottom: 10px;
            }
            
            th, td {
                border: 2px solid #000;
                padding: 3px;
                text-align: left;
                background-clip: padding-box;
                width : 15px !important;
            }
            
            th {
                background-color: #f8f9fa;
                font-weight: bold;
            }

            /* Style spécifique pour les bordures visibles */
            *[border="1"], *[border="0"], table, td, th {
                border-collapse: collapse !important;
                border-spacing: 0 !important;
            }
            
            /* Assurer que les cellules vides ont des bordures */
            td:empty, th:empty {
                border: 1px solid #000000 !important;
            }
            
            /* Style pour les rangées et colonnes spécifiques */
            .bg-secondary th, .bg-secondary td {
                background-color: #6c757d !important;
                color: white !important;
                border: 1px solid #000000 !important;
            }

            .cell-number{
                text-align: center !important;
                width: 10px !important;
            }
            
            /* SYSTEME DE GRILLE COMPATIBLE PDF */
            .container-fluid, .container {
                width: 100% !important;
                padding-left: 5px;
                padding-right: 5px;
                box-sizing: border-box;
            }
            
            .row {
                width: 100%;
                display: block;
                margin-left: 0;
                margin-right: 0;
                margin-bottom: 8px;
                clear: both;
            }
            
            .row::after {
                content: "";
                display: table;
                clear: both;
            }
            
            .col, .col-1, .col-2, .col-3, .col-4, .col-5, .col-6, 
            .col-7, .col-8, .col-9, .col-10, .col-11, .col-12 {
                float: left;
                padding-left: 5px;
                padding-right: 5px;
                box-sizing: border-box;
            }
            
            .col-1 { width: 8.333333%; }
            .col-2 { width: 16.666667%; }
            .col-3 { width: 25%; }
            .col-4 { width: 25.333333%; }
            .col-5 { width: 39.666667%; }
            .col-6 { width: 50%; }
            .col-7 { width: 58.333333%; }
            .col-8 { width: 66.666667%; }
            .col-9 { width: 75%; }
            .col-10 { width: 83.333333%; }
            .col-11 { width: 91.666667%; }
            .col-12 { width: 100%; float: none; }
            
            .d-flex { display: block !important; }
            
            .student-header {
                background-color: #e9ecef;
                padding: 10px;
                margin-bottom: 10px;
                border: 1px solid #000;
            }
            
            .text-center { text-align: center !important; }
            .text-end { text-align: right !important; }
        </style>
    ';

            // Écrire le CSS
            $mpdf->WriteHTML($css);

            // Définir l'en-tête et le pied de page
            $mpdf->SetHTMLHeader('
            <div style="text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px;">
                <strong>Bulletin scolaire</strong>
            </div>
        ');

            $mpdf->SetHTMLFooter('
            <div style="text-align: center; border-top: 1px solid #ccc; padding-top: 5px;">
                Page {PAGENO} / {nbpg}
            </div>
        ');

            // Traiter chaque bulletin
            foreach ($bulletinsHtml as $index => $bulletinHtml) {
                // Ajouter une page seulement si ce n'est pas le premier bulletin
                if ($index > 0) {
                    $mpdf->AddPage();
                }

                // Écrire le contenu HTML
                $mpdf->WriteHTML($bulletinHtml);
            }

            // Créer le répertoire si nécessaire
            $dir = dirname($filePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            // Générer le PDF
            $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la génération du PDF: ' . $e->getMessage());
        }
    }

    /**
     * Fusionne plusieurs fichiers PDF en un seul
     */
    public function mergePdfFiles(array $pdfFiles, string $filename = 'bulletins_fusionnes.pdf'): Response
    {
        try {
            $fpdi = new Fpdi();

            foreach ($pdfFiles as $file) {
                if (!file_exists($file)) {
                    continue;
                }

                $pageCount = $fpdi->setSourceFile($file);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $template = $fpdi->importPage($pageNo);
                    $size = $fpdi->getTemplateSize($template);

                    $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $fpdi->useTemplate($template);
                }
            }

            $mergedPdf = $fpdi->Output('S');

            return new Response(
                $mergedPdf,
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ]
            );
        } catch (\Exception $e) {
            return new Response(json_encode([
                'status' => 'error',
                'message' => 'Erreur lors de la fusion des PDFs: ' . $e->getMessage()
            ]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
