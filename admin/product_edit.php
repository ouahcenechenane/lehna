<?php
/**
 * admin/product_edit.php
 * Modification d'un produit existant
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('danger', 'Produit introuvable.');
    redirect(APP_URL . '/admin/products.php');
}

$produit = getProduitById($id);
if (!$produit) {
    setFlash('danger', 'Produit introuvable.');
    redirect(APP_URL . '/admin/products.php');
}

$errors = [];
$values = $produit; // Valeurs par défaut = données actuelles

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Requête invalide (CSRF).';
    } else {
        $values = [
            'nom'        => sanitizeString($_POST['nom']         ?? ''),
            'prix'       => $_POST['prix']        ?? '',
            'code_barre' => sanitizeString($_POST['code_barre']  ?? '', 50),
            'quantite'   => $_POST['quantite']    ?? '',
            'categorie'  => sanitizeString($_POST['categorie']   ?? 'Général'),
            'description'=> sanitizeString($_POST['description'] ?? '', 500),
        ];

        if ($values['nom'] === '')            $errors[] = 'Le nom est obligatoire.';
        if (!is_numeric($values['prix']) || $values['prix'] < 0) $errors[] = 'Prix invalide.';
        if ($values['code_barre'] === '')     $errors[] = 'Le code-barres est obligatoire.';
        if (!is_numeric($values['quantite']) || $values['quantite'] < 0) $errors[] = 'Quantité invalide.';

        // Unicité code-barres (sauf pour le produit lui-même)
        $existing = getProduitByCodeBarre($values['code_barre']);
        if ($existing && (int)$existing['id'] !== $id) {
            $errors[] = 'Ce code-barres est déjà utilisé par un autre produit.';
        }

        $imageFilename = $produit['image'];
        if (!empty($_FILES['image']['name'])) {
            $result = uploadImage($_FILES['image'], $produit['image']);
            if (str_starts_with($result, 'ERREUR:')) {
                $errors[] = substr($result, 7);
            } else {
                $imageFilename = $result;
            }
        } elseif (!empty($_POST['image_base64'])) {
            $result = saveBase64Image($_POST['image_base64']);
            if (str_starts_with($result, 'ERREUR:')) {
                $errors[] = substr($result, 7);
            } else {
                // Supprimer l'ancienne image
                if ($produit['image'] && $produit['image'] !== 'default.jpg') {
                    $old = UPLOAD_PATH . basename($produit['image']);
                    if (file_exists($old)) unlink($old);
                }
                $imageFilename = $result;
            }
        }

        if (empty($errors)) {
            $pdo  = getPDO();
            $stmt = $pdo->prepare('
                UPDATE produits
                SET nom=:nom, prix=:prix, code_barre=:code_barre,
                    quantite=:quantite, image=:image, categorie=:categorie, description=:description
                WHERE id=:id
            ');
            $stmt->execute([
                ':nom'         => $values['nom'],
                ':prix'        => (float) $values['prix'],
                ':code_barre'  => $values['code_barre'],
                ':quantite'    => (int)   $values['quantite'],
                ':image'       => $imageFilename,
                ':categorie'   => $values['categorie'],
                ':description' => $values['description'],
                ':id'          => $id,
            ]);

            setFlash('success', 'Produit mis à jour avec succès !');
            redirect(APP_URL . '/admin/products.php');
        }
        // Si erreurs, conserver l'image actuelle
        $values['image'] = $produit['image'];
    }
}

$categories = array_merge(getCategories(), ['Épicerie', 'Boissons', 'Produits Laitiers', 'Conserves', 'Hygiène', 'Autre']);
$categories = array_unique($categories);
sort($categories);

$imgSrc = ($values['image'] && $values['image'] !== 'default.jpg')
          ? UPLOAD_URL . $values['image']
          : APP_URL . '/assets/images/default.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier – <?= e($produit['nom']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>
<div class="admin-layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="admin-main">

    <div class="page-header">
        <div>
            <h1 class="page-title">Modifier le produit</h1>
            <p class="page-subtitle"><?= e($produit['nom']) ?> · ID #<?= $id ?></p>
        </div>
        <a href="products.php" class="btn">← Retour</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Erreurs :</strong><br>
            <?php foreach ($errors as $e): ?>
                • <?= e($e) ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="two-col-grid">
        <div class="card">
            <h2 class="card-title" style="margin-bottom:24px">✏️ Modifier les informations</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                <div class="form-group">
                    <label for="nom">Nom <span class="required">*</span></label>
                    <input type="text" id="nom" name="nom" class="form-control"
                           value="<?= e($values['nom']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="code_barre">Code-barres <span class="required">*</span></label>
                    <div class="input-with-btn">
                        <input type="text" id="code_barre" name="code_barre" class="form-control"
                               value="<?= e($values['code_barre']) ?>" required>
                        <button type="button" id="toggleScanner" class="btn btn-secondary">📷 Scanner</button>
                    </div>
                </div>

                <div id="scannerContainer" class="scanner-container hidden">
                    <div class="scanner-header">
                        <span>📷 Scanner en cours…</span>
                        <button type="button" id="stopScanner" class="btn btn-sm btn-danger">✕ Fermer</button>
                    </div>
                    <div id="interactive" class="viewport"></div>
                    <p id="scanStatus" class="scan-status">Pointez la caméra vers le code-barres</p>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="prix">Prix (DA) <span class="required">*</span></label>
                        <input type="number" id="prix" name="prix" class="form-control"
                               value="<?= e($values['prix']) ?>" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="quantite">Quantité <span class="required">*</span></label>
                        <input type="number" id="quantite" name="quantite" class="form-control"
                               value="<?= e($values['quantite']) ?>" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="categorie">Catégorie</label>
                    <select id="categorie" name="categorie" class="form-control">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat) ?>" <?= $values['categorie'] === $cat ? 'selected' : '' ?>>
                                <?= e($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?= e($values['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>Image du produit</label>

                    <div class="img-source-btns">
                        <button type="button" class="img-source-btn active" id="btnChooseFile">
                            <span>📁</span> Choisir un fichier
                        </button>
                        <button type="button" class="img-source-btn" id="btnUseCamera">
                            <span>📷</span> Prendre une photo
                        </button>
                    </div>

                    <!-- Image actuelle -->
                    <div id="zoneFile">
                        <div style="display:flex;align-items:center;gap:16px;margin-top:10px">
                            <img id="imagePreview" src="<?= e($imgSrc) ?>" alt="Image"
                                 style="height:80px;border-radius:8px;border:1px solid var(--border)">
                            <div>
                                <input type="file" id="image" name="image" accept="image/*"
                                       class="upload-input" style="position:relative;opacity:1;width:auto"
                                       onchange="previewImage(this)">
                                <small class="text-muted">Laisser vide pour conserver l'image actuelle</small>
                            </div>
                        </div>
                    </div>

                    <!-- Zone caméra -->
                    <div id="zoneCamera" class="hidden" style="margin-top:10px">
                        <div class="camera-box">
                            <video id="cameraVideo" autoplay playsinline muted
                                   style="width:100%;border-radius:10px;background:#000;display:block"></video>
                            <canvas id="cameraCanvas" style="display:none"></canvas>
                            <div class="camera-controls">
                                <button type="button" id="btnSnap" class="btn btn-primary">📸 Prendre la photo</button>
                                <button type="button" id="btnRetake" class="btn hidden">🔄 Reprendre</button>
                                <button type="button" id="btnStopCamera" class="btn btn-danger">✕ Fermer</button>
                            </div>
                        </div>
                        <img id="cameraPreview" src="" alt="Photo"
                             class="hidden" style="max-height:200px;border-radius:10px;margin-top:12px">
                        <input type="hidden" id="imageBase64" name="image_base64" value="">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">💾 Enregistrer les modifications</button>
                    <a href="products.php" class="btn btn-lg">Annuler</a>
                </div>
            </form>
        </div>

        <!-- Résumé actuel -->
        <div class="card" style="align-self:start">
            <h3 class="card-title" style="margin-bottom:20px">📊 État actuel</h3>
            <table class="data-table">
                <tr><th>ID</th><td>#<?= $id ?></td></tr>
                <tr><th>Créé le</th><td><?= date('d/m/Y H:i', strtotime($produit['created_at'])) ?></td></tr>
                <tr><th>Modifié</th><td><?= date('d/m/Y H:i', strtotime($produit['updated_at'])) ?></td></tr>
                <tr><th>Prix actuel</th><td><?= formatPrix((float)$produit['prix']) ?></td></tr>
                <tr><th>Stock actuel</th><td><?= $produit['quantite'] ?> unités</td></tr>
            </table>
        </div>
    </div>

</main>
</div>

<script src="<?= APP_URL ?>/assets/js/admin.js"></script>
<script src="<?= APP_URL ?>/assets/js/scanner.js"></script>
<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { document.getElementById('imagePreview').src = e.target.result; };
        reader.readAsDataURL(input.files[0]);
    }
}

/* ══ CAMÉRA PHOTO ══ */
let cameraStream = null;
const btnChooseFile = document.getElementById('btnChooseFile');
const btnUseCamera  = document.getElementById('btnUseCamera');
const zoneFile      = document.getElementById('zoneFile');
const zoneCamera    = document.getElementById('zoneCamera');
const cameraVideo   = document.getElementById('cameraVideo');
const cameraCanvas  = document.getElementById('cameraCanvas');
const btnSnap       = document.getElementById('btnSnap');
const btnRetake     = document.getElementById('btnRetake');
const btnStopCamera = document.getElementById('btnStopCamera');
const cameraPreview = document.getElementById('cameraPreview');
const imageBase64   = document.getElementById('imageBase64');

btnChooseFile.addEventListener('click', () => {
    btnChooseFile.classList.add('active');
    btnUseCamera.classList.remove('active');
    zoneFile.classList.remove('hidden');
    zoneCamera.classList.add('hidden');
    stopCamera();
    imageBase64.value = '';
});

btnUseCamera.addEventListener('click', async () => {
    btnUseCamera.classList.add('active');
    btnChooseFile.classList.remove('active');
    zoneCamera.classList.remove('hidden');
    zoneFile.classList.add('hidden');
    cameraPreview.classList.add('hidden');
    btnRetake.classList.add('hidden');
    btnSnap.classList.remove('hidden');
    await startCamera();
});

async function startCamera() {
    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
            audio: false
        });
        cameraVideo.srcObject = cameraStream;
        cameraVideo.style.display = 'block';
    } catch (err) {
        let msg = '❌ Impossible d\'accéder à la caméra.';
        if (err.name === 'NotAllowedError')  msg = '❌ Accès caméra refusé.';
        if (err.name === 'NotFoundError')    msg = '❌ Aucune caméra détectée.';
        showNotif(msg, 'error');
        btnChooseFile.click();
    }
}

function stopCamera() {
    if (cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null; }
    cameraVideo.srcObject = null;
}

btnSnap?.addEventListener('click', () => {
    const w = cameraVideo.videoWidth || 640, h = cameraVideo.videoHeight || 480;
    cameraCanvas.width = w; cameraCanvas.height = h;
    cameraCanvas.getContext('2d').drawImage(cameraVideo, 0, 0, w, h);
    const dataUrl = cameraCanvas.toDataURL('image/jpeg', 0.92);
    imageBase64.value = dataUrl;
    cameraPreview.src = dataUrl;
    cameraPreview.classList.remove('hidden');
    cameraVideo.style.display = 'none';
    btnSnap.classList.add('hidden');
    btnRetake.classList.remove('hidden');
    stopCamera();
    showNotif('📸 Photo prise !', 'success');
});

btnRetake?.addEventListener('click', async () => {
    cameraPreview.classList.add('hidden');
    cameraVideo.style.display = 'block';
    imageBase64.value = '';
    btnRetake.classList.add('hidden');
    btnSnap.classList.remove('hidden');
    await startCamera();
});

btnStopCamera?.addEventListener('click', () => btnChooseFile.click());
window.addEventListener('beforeunload', stopCamera);
</script>
</body>
</html>
