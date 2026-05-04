<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// ------------------------------------------------------------
// 1. DEPENDANCES
// ------------------------------------------------------------
require_once 'config/database.php';
require_once 'lib/fpdf/fpdf.php';

// ------------------------------------------------------------
// 2. INITIALISATION BASE DE DONNEES
// ------------------------------------------------------------
$database = new Database();
$db = $database->getConnection();

// ------------------------------------------------------------
// 3. VALIDATION DE L'ID
// ------------------------------------------------------------
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("ID invalide.");
}

// ------------------------------------------------------------
// 4. RECUPERATION DES DONNEES
// ------------------------------------------------------------
$stmt = $db->prepare("
    SELECT 
        c.*, 
        a.nom, 
        a.prenom, 
        a.postnom, 
        f.nom_formation, 
        f.duree, 
        f.niveau, 
        f.description 
    FROM certificat c 
    JOIN inscription i ON c.id_inscription = i.id_inscription 
    JOIN apprenant a ON i.id_apprenant = a.id_apprenant 
    JOIN formation f ON i.id_formation = f.id_formation 
    WHERE c.id_certificat = :id 
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$cert = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cert) {
    die("Certificat introuvable.");
}

// ------------------------------------------------------------
// 5. FONCTION DE NETTOYAGE AGRESSIF
// ------------------------------------------------------------
function cleanString($string)
{
    if (empty($string)) return '';
    
    // Table de conversion complete des caracteres accentues
    $accentedChars = [
        'À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ',
        'à', 'á', 'â', 'ã', 'ä', 'å', 'æ',
        'È', 'É', 'Ê', 'Ë',
        'è', 'é', 'ê', 'ë',
        'Ì', 'Í', 'Î', 'Ï',
        'ì', 'í', 'î', 'ï',
        'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø',
        'ò', 'ó', 'ô', 'õ', 'ö', 'ø',
        'Ù', 'Ú', 'Û', 'Ü',
        'ù', 'ú', 'û', 'ü',
        'Ý', 'ý', 'ÿ',
        'Ñ', 'ñ',
        'Ç', 'ç',
        'Œ', 'œ',
        'ß'
    ];
    
    $nonAccentedChars = [
        'A', 'A', 'A', 'A', 'A', 'A', 'AE',
        'a', 'a', 'a', 'a', 'a', 'a', 'ae',
        'E', 'E', 'E', 'E',
        'e', 'e', 'e', 'e',
        'I', 'I', 'I', 'I',
        'i', 'i', 'i', 'i',
        'O', 'O', 'O', 'O', 'O', 'O',
        'o', 'o', 'o', 'o', 'o', 'o',
        'U', 'U', 'U', 'U',
        'u', 'u', 'u', 'u',
        'Y', 'y', 'y',
        'N', 'n',
        'C', 'c',
        'OE', 'oe',
        'ss'
    ];
    
    $string = str_replace($accentedChars, $nonAccentedChars, $string);
    
    // Supprimer tous les autres caracteres non-ASCII restants
    $string = preg_replace('/[^\x20-\x7E]/', '', $string);
    
    return trim($string);
}

// ------------------------------------------------------------
// 6. PREPARATION DES VARIABLES NETTOYEES
// ------------------------------------------------------------
$numeroCertificat = 'CERT-' . date('Y') . '-' . str_pad($cert['id_certificat'], 4, '0', STR_PAD_LEFT);
$dateDelivrance   = date('d/m/Y', strtotime($cert['date_delivrance']));
$nomComplet       = cleanString(strtoupper($cert['nom'] . ' ' . $cert['prenom']));
$nomFormation     = cleanString(strtoupper($cert['nom_formation']));
$postnom          = !empty($cert['postnom']) ? cleanString($cert['postnom']) : '';
$description      = !empty($cert['description']) ? cleanString($cert['description']) : '';
$duree            = cleanString($cert['duree']);
$niveau           = cleanString($cert['niveau']);
$mentionText      = !empty($cert['mention']) ? 'Mention : ' . cleanString($cert['mention']) : '';

// ------------------------------------------------------------
// 7. GENERATION DU QR CODE
// ------------------------------------------------------------
$qrData  = "MCC|{$numeroCertificat}|{$nomComplet}|{$nomFormation}|{$mentionText}|{$dateDelivrance}";
$qrUrl   = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&color=1A3C5E&bgcolor=FFF&data=' . urlencode($qrData);
$qrPath  = sys_get_temp_dir() . '/qr_' . $id . '.png';
$qrImage = @file_get_contents($qrUrl);

if ($qrImage) {
    file_put_contents($qrPath, $qrImage);
}

// ------------------------------------------------------------
// 8. CONFIGURATION DU PDF
// ------------------------------------------------------------
$pdf = new FPDF('L', 'mm', 'Letter');
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

// Couleurs
$bgColor       = [252, 250, 245];
$borderDark    = [26, 60, 94];
$borderGold    = [180, 150, 60];
$textDark      = [26, 60, 94];
$textMedium    = [60, 70, 90];
$textGray      = [80, 80, 80];
$textLightGray = [140, 140, 140];
$mentionGold   = [180, 140, 20];

// ------------------------------------------------------------
// 9. FOND ET CADRES PRINCIPAUX
// ------------------------------------------------------------
// Fond
$pdf->SetFillColor($bgColor[0], $bgColor[1], $bgColor[2]);
$pdf->Rect(0, 0, 279, 216, 'F');

// Cadre exterieur bleu fonce
$pdf->SetLineWidth(3);
$pdf->SetDrawColor($borderDark[0], $borderDark[1], $borderDark[2]);
$pdf->Rect(10, 10, 259, 196);

// Cadre interieur dore
$pdf->SetLineWidth(1);
$pdf->SetDrawColor($borderGold[0], $borderGold[1], $borderGold[2]);
$pdf->Rect(13, 13, 253, 190);

// ------------------------------------------------------------
// 10. EN-TETE
// ------------------------------------------------------------
$pdf->SetFont('Helvetica', 'B', 26);
$pdf->SetTextColor($textDark[0], $textDark[1], $textDark[2]);
$pdf->SetY(32);
$pdf->Cell(0, 12, 'CERTIFICAT DE REUSSITE', 0, 1, 'C');

$pdf->SetFont('Helvetica', '', 11);
$pdf->SetTextColor($textGray[0], $textGray[1], $textGray[2]);
$pdf->Cell(0, 6, 'MCC Gestion - Centre de Formation Professionnelle', 0, 1, 'C');
$pdf->Ln(10);

// ------------------------------------------------------------
// 11. CORPS DU TEXTE
// ------------------------------------------------------------
// Introduction
$pdf->SetFont('Helvetica', '', 14);
$pdf->SetTextColor(40, 40, 40);
$pdf->Cell(0, 8, 'Le present certificat est decerne a', 0, 1, 'C');
$pdf->Ln(6);

// Nom en tres grand
$pdf->SetFont('Helvetica', 'B', 28);
$pdf->SetTextColor($textDark[0], $textDark[1], $textDark[2]);
$pdf->Cell(0, 14, $nomComplet, 0, 1, 'C');

// Ligne decorative
$yLine = $pdf->GetY() + 3;
$pdf->SetDrawColor($borderGold[0], $borderGold[1], $borderGold[2]);
$pdf->SetLineWidth(0.8);
$pdf->Line(70, $yLine, 209, $yLine);
$pdf->Ln(6);

// Postnom
if (!empty($postnom)) {
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->SetTextColor($textLightGray[0], $textLightGray[1], $textLightGray[2]);
    $pdf->Cell(0, 7, $postnom, 0, 1, 'C');
    $pdf->Ln(3);
}

$pdf->Ln(4);

// Texte suivi formation
$pdf->SetFont('Helvetica', '', 14);
$pdf->SetTextColor(40, 40, 40);
$pdf->Cell(0, 8, 'pour avoir suivi avec succes la formation', 0, 1, 'C');
$pdf->Ln(5);

// Nom de la formation
$pdf->SetFont('Helvetica', 'B', 20);
$pdf->SetTextColor($textMedium[0], $textMedium[1], $textMedium[2]);
$pdf->Cell(0, 10, $nomFormation, 0, 1, 'C');

// Description entre guillemets
if (!empty($description)) {
    $pdf->SetFont('Helvetica', 'I', 11);
    $pdf->SetTextColor($textLightGray[0], $textLightGray[1], $textLightGray[2]);
    $pdf->Cell(0, 6, '<< ' . $description . ' >>', 0, 1, 'C');
    $pdf->Ln(2);
}

$pdf->Ln(4);

// Duree et Niveau
$pdf->SetFont('Helvetica', '', 12);
$pdf->SetTextColor($textGray[0], $textGray[1], $textGray[2]);
$pdf->Cell(0, 7, 'Duree : ' . $duree . '     |     Niveau : ' . $niveau, 0, 1, 'C');

// Mention
if (!empty($mentionText)) {
    $pdf->Ln(5);
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->SetTextColor($mentionGold[0], $mentionGold[1], $mentionGold[2]);
    $pdf->Cell(0, 8, $mentionText, 0, 1, 'C');
}

// ------------------------------------------------------------
// 12. BLOC DATE ET QR CODE ENCADRE
// ------------------------------------------------------------
$pdf->Ln(8);

// Ligne de separation
$pdf->SetDrawColor($borderGold[0], $borderGold[1], $borderGold[2]);
$pdf->SetLineWidth(0.3);
$pdf->Line(30, $pdf->GetY(), 249, $pdf->GetY());
$pdf->Ln(8);

// Position Y pour le bloc
$blockY = $pdf->GetY();

// Cadre pour la date (a gauche)
$pdf->SetDrawColor($borderDark[0], $borderDark[1], $borderDark[2]);
$pdf->SetLineWidth(1);
$pdf->SetFillColor(255, 255, 255);
$pdf->Rect(40, $blockY, 85, 25, 'DF');

$pdf->SetFont('Helvetica', 'B', 10);
$pdf->SetTextColor($textDark[0], $textDark[1], $textDark[2]);
$pdf->SetXY(40, $blockY + 3);
$pdf->Cell(85, 7, 'Date de delivrance', 0, 1, 'C');

$pdf->SetFont('Helvetica', 'B', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(85, 10, $dateDelivrance, 0, 1, 'C');

// Cadre pour le QR code (a droite) avec bordure epaisse
$qrX = 200;
$qrY = $blockY - 5;
$qrSize = 35;

// Bordure exterieure bleu fonce
$pdf->SetLineWidth(2);
$pdf->SetDrawColor($borderDark[0], $borderDark[1], $borderDark[2]);
$pdf->SetFillColor(255, 255, 255);
$pdf->Rect($qrX - 2, $qrY - 2, $qrSize + 4, $qrSize + 4, 'DF');

// Bordure interieure doree
$pdf->SetLineWidth(1);
$pdf->SetDrawColor($borderGold[0], $borderGold[1], $borderGold[2]);
$pdf->Rect($qrX, $qrY, $qrSize, $qrSize, 'D');

// QR Code image
if ($qrImage && file_exists($qrPath)) {
    $pdf->Image($qrPath, $qrX + 2, $qrY + 2, $qrSize - 4, $qrSize - 4);
    @unlink($qrPath);
} else {
    // Placeholder si QR code non disponible
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetTextColor(200, 200, 200);
    $pdf->SetXY($qrX + 2, $qrY + 12);
    $pdf->Cell($qrSize - 4, 5, 'QR Code', 0, 1, 'C');
    $pdf->Cell($qrSize - 4, 5, 'Non disponible', 0, 1, 'C');
}

// Texte sous le QR code
$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor($textGray[0], $textGray[1], $textGray[2]);
$pdf->SetXY($qrX - 2, $qrY + $qrSize + 3);
$pdf->Cell($qrSize + 4, 4, 'Verification', 0, 1, 'C');

// ------------------------------------------------------------
// 13. SIGNATURES
// ------------------------------------------------------------
$pdf->SetY($blockY + 50);
$pdf->Ln(5);

// Lignes de signature
$sigY = $pdf->GetY();
$pdf->SetDrawColor($textMedium[0], $textMedium[1], $textMedium[2]);
$pdf->SetLineWidth(0.5);

$pdf->Line(50, $sigY, 105, $sigY);
$pdf->Line(120, $sigY, 165, $sigY);
$pdf->Line(180, $sigY, 235, $sigY);
$pdf->Ln(5);

// Textes signatures
$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor($textGray[0], $textGray[1], $textGray[2]);

$pdf->SetX(35);
$pdf->Cell(70, 6, 'Le Directeur', 0, 0, 'C');
$pdf->Cell(50, 6, 'Le Formateur', 0, 0, 'C');
$pdf->Cell(65, 6, "L'Apprenant(e)", 0, 1, 'C');

// ------------------------------------------------------------
// 14. NUMERO DE CERTIFICAT (en bas a gauche)
// ------------------------------------------------------------
$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor($textLightGray[0], $textLightGray[1], $textLightGray[2]);
$pdf->SetXY(18, 198);
$pdf->Cell(100, 5, $numeroCertificat, 0, 0, 'L');

// Texte verification en bas a droite
$pdf->SetFont('Helvetica', '', 7);
$pdf->SetXY(180, 198);
$pdf->Cell(85, 5, 'Certificat securise - Verifiable en ligne', 0, 0, 'R');

// ------------------------------------------------------------
// 15. SORTIE DU PDF
// ------------------------------------------------------------
$pdf->Output('I', 'Certificat_' . $numeroCertificat . '.pdf');
exit();