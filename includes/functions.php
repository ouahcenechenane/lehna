<?php
/**
 * includes/functions.php
 * Fonctions utilitaires partagées dans tout le projet
 */

require_once __DIR__ . '/db.php';

// ════════════════════════════════════════════════════════════
//  PRODUITS
// ════════════════════════════════════════════════════════════

/**
 * Récupère tous les produits actifs avec pagination optionnelle.
 */
function getProduits(int $page = 1, int $perPage = ITEMS_PER_PAGE, string $search = '', string $categorie = ''): array
{
    $pdo    = getPDO();
    $offset = ($page - 1) * $perPage;
    $params = [];

    $where = ['actif = 1'];

    if ($search !== '') {
        $where[]            = '(nom LIKE :search OR code_barre LIKE :search2)';
        $params[':search']  = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
    }

    if ($categorie !== '') {
        $where[]              = 'categorie = :categorie';
        $params[':categorie'] = $categorie;
    }

    $whereSql = implode(' AND ', $where);

    // Total pour la pagination
    $countSql    = "SELECT COUNT(*) FROM produits WHERE $whereSql";
    $countStmt   = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total       = (int) $countStmt->fetchColumn();

    // Produits de la page courante
    $sql  = "SELECT * FROM produits WHERE $whereSql ORDER BY nom ASC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();

    return [
        'produits'     => $stmt->fetchAll(),
        'total'        => $total,
        'pages'        => (int) ceil($total / $perPage),
        'current_page' => $page,
    ];
}

/**
 * Récupère un produit par son ID.
 */
function getProduitById(int $id): ?array
{
    $stmt = getPDO()->prepare('SELECT * FROM produits WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Récupère un produit par son code-barres.
 */
function getProduitByCodeBarre(string $code): ?array
{
    $stmt = getPDO()->prepare('SELECT * FROM produits WHERE code_barre = :code LIMIT 1');
    $stmt->execute([':code' => $code]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Liste des catégories existantes.
 */
function getCategories(): array
{
    $stmt = getPDO()->query('SELECT DISTINCT categorie FROM produits WHERE actif = 1 ORDER BY categorie');
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Statistiques du dashboard admin.
 */
function getStats(): array
{
    $pdo = getPDO();

    $stats = $pdo->query('SELECT * FROM v_produits_stats')->fetch();

    return [
        'total_produits' => (int)   ($stats['total_produits'] ?? 0),
        'total_stock'    => (int)   ($stats['total_stock']    ?? 0),
        'valeur_stock'   => (float) ($stats['valeur_stock']   ?? 0),
        'ruptures'       => (int)   ($stats['ruptures']       ?? 0),
        'stock_bas'      => (int)   ($stats['stock_bas']      ?? 0),
    ];
}

// ════════════════════════════════════════════════════════════
//  UPLOAD IMAGE
// ════════════════════════════════════════════════════════════

/**
 * Gère l'upload sécurisé d'une image produit.
 * Retourne le nom du fichier ou une chaîne d'erreur préfixée "ERREUR:".
 */
function uploadImage(array $file, string $ancienFichier = ''): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'ERREUR:Aucun fichier valide envoyé.';
    }

    // Vérification taille
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return 'ERREUR:Le fichier dépasse 5 Mo.';
    }

    // Vérification type MIME réel (pas l'extension)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, ALLOWED_TYPES, true)) {
        return 'ERREUR:Type de fichier non autorisé (JPEG, PNG, GIF, WebP uniquement).';
    }

    // Génération d'un nom unique
    $ext      = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        default      => 'jpg',
    };
    $filename = bin2hex(random_bytes(12)) . '.' . $ext;
    $dest     = UPLOAD_PATH . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return 'ERREUR:Impossible de déplacer le fichier uploadé.';
    }

    // Suppression de l'ancienne image si différente de default.jpg
    if ($ancienFichier && $ancienFichier !== 'default.jpg') {
        $old = UPLOAD_PATH . basename($ancienFichier);
        if (file_exists($old)) {
            unlink($old);
        }
    }

    return $filename;
}

/**
 * Sauvegarde une image base64 (prise avec la caméra) sur le disque.
 * Retourne le nom du fichier ou "ERREUR:message".
 */
function saveBase64Image(string $base64Data): string
{
    // Format attendu : data:image/jpeg;base64,/9j/4AAQ...
    if (!preg_match('/^data:(image\/(?:jpeg|png|gif|webp));base64,(.+)$/s', $base64Data, $matches)) {
        return 'ERREUR:Format de données image invalide.';
    }

    $mimeType = $matches[1];
    $data     = base64_decode($matches[2]);

    if ($data === false) {
        return 'ERREUR:Impossible de décoder l\'image.';
    }

    if (strlen($data) > MAX_UPLOAD_SIZE) {
        return 'ERREUR:L\'image dépasse 5 Mo.';
    }

    $ext = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        default      => 'jpg',
    };

    $filename = bin2hex(random_bytes(12)) . '.' . $ext;
    $dest     = UPLOAD_PATH . $filename;

    if (file_put_contents($dest, $data) === false) {
        return 'ERREUR:Impossible d\'enregistrer l\'image.';
    }

    return $filename;
}

/**
 * Échappe une valeur pour l'affichage HTML.
 */
function e(string $val): string
{
    return htmlspecialchars($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Formate un prix en dinars algériens.
 */
function formatPrix(float $prix): string
{
    return number_format($prix, 2, ',', ' ') . ' DA';
}

/**
 * Génère un token CSRF et le stocke en session.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie le token CSRF (lève une exception si invalide).
 */
function verifyCsrf(string $token): void
{
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Token CSRF invalide.']));
    }
}

/**
 * Renvoie une réponse JSON et arrête l'exécution.
 */
function jsonResponse(bool $success, string $message, array $data = []): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message, ...$data]);
    exit;
}

/**
 * Redirige vers une URL et arrête l'exécution.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Stocke un message flash en session.
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Récupère et supprime le message flash.
 */
function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Nettoie et valide une chaîne.
 */
function sanitizeString(string $val, int $maxLen = 255): string
{
    return substr(trim(strip_tags($val)), 0, $maxLen);
}
