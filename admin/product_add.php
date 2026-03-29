<?php
/**
 * admin/product_add.php
 * Formulaire d'ajout de produit avec scanner de code-barres (WebRTC + QuaggaJS)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$errors = [];
$values = ['nom' => '', 'prix' => '', 'code_barre' => '', 'quantite' => '', 'categorie' => 'Général', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Requête invalide (CSRF).';
    } else {
        // Récupération & nettoyage des champs
        $values = [
            'nom'        => sanitizeString($_POST['nom']        ?? ''),
            'prix'       => $_POST['prix']       ?? '',
            'code_barre' => sanitizeString($_POST['code_barre'] ?? '', 50),
            'quantite'   => $_POST['quantite']   ?? '',
            'categorie'  => sanitizeString($_POST['categorie']  ?? 'Général'),
            'description'=> sanitizeString($_POST['description'] ?? '', 500),
        ];

        // Validation
        if ($values['nom'] === '')            $errors[] = 'Le nom est obligatoire.';
        if (!is_numeric($values['prix']) || $values['prix'] < 0) $errors[] = 'Prix invalide.';
        if ($values['code_barre'] === '')     $errors[] = 'Le code-barres est obligatoire.';
        if (!is_numeric($values['quantite']) || $values['quantite'] < 0) $errors[] = 'Quantité invalide.';

        // Vérifier si le produit existe déjà en base
        $produitExistant = ($values['code_barre'] !== '')
            ? getProduitByCodeBarre($values['code_barre'])
            : null;

        // Upload image
        $imageFilename = 'default.jpg';
        if (!empty($_FILES['image']['name'])) {
            $result = uploadImage($_FILES['image']);
            if (str_starts_with($result, 'ERREUR:')) {
                $errors[] = substr($result, 7);
            } else {
                $imageFilename = $result;
            }
        } elseif (!empty($_POST['image_base64'])) {
            // Image prise avec la caméra (base64)
            $result = saveBase64Image($_POST['image_base64']);
            if (str_starts_with($result, 'ERREUR:')) {
                $errors[] = substr($result, 7);
            } else {
                $imageFilename = $result;
            }
        }

        if (empty($errors)) {
            $pdo = getPDO();

            if ($produitExistant) {
                // ✅ Produit déjà en base → on additionne la quantité au stock existant
                $stmt = $pdo->prepare('
                    UPDATE produits
                    SET quantite = quantite + :quantite
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':quantite' => (int) $values['quantite'],
                    ':id'       => $produitExistant['id'],
                ]);

                setFlash('success', 'Stock mis à jour : +' . $values['quantite'] . ' unité(s) pour « ' . $produitExistant['nom'] . ' ».');
            } else {
                // ✅ Nouveau produit → INSERT classique
                $stmt = $pdo->prepare('
                    INSERT INTO produits (nom, prix, code_barre, quantite, image, categorie, description)
                    VALUES (:nom, :prix, :code_barre, :quantite, :image, :categorie, :description)
                ');
                $stmt->execute([
                    ':nom'         => $values['nom'],
                    ':prix'        => (float) $values['prix'],
                    ':code_barre'  => $values['code_barre'],
                    ':quantite'    => (int)   $values['quantite'],
                    ':image'       => $imageFilename,
                    ':categorie'   => $values['categorie'],
                    ':description' => $values['description'],
                ]);

                setFlash('success', 'Produit « ' . $values['nom'] . ' » ajouté avec succès !');
            }

            redirect(APP_URL . '/admin/products.php');
        }
    }
}

$categories = array_merge(getCategories(), ['Épicerie', 'Boissons', 'Produits Laitiers', 'Conserves', 'Hygiène', 'Autre']);
$categories = array_unique($categories);
sort($categories);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit – <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
    <!-- QuaggaJS – détection code-barres via caméra -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>
<div class="admin-layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="admin-main">

    <div class="page-header">
        <div>
            <h1 class="page-title">Ajouter un produit</h1>
            <p class="page-subtitle">Remplissez le formulaire ou scannez un code-barres</p>
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

        <!-- ── Formulaire ── -->
        <div class="card">
            <h2 class="card-title" style="margin-bottom:24px">📝 Informations produit</h2>
            <form method="POST" enctype="multipart/form-data" id="productForm">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="nom">Nom du produit <span class="required">*</span></label>
                        <input type="text" id="nom" name="nom" class="form-control"
                               value="<?= e($values['nom']) ?>" placeholder="Ex : Huile de Table 1L" required>
                    </div>
                </div>

                <!-- Code-barres avec bouton scanner -->
                <div class="form-group">
                    <label for="code_barre">Code-barres <span class="required">*</span></label>
                    <div class="input-with-btn">
                        <input type="text" id="code_barre" name="code_barre" class="form-control"
                               value="<?= e($values['code_barre']) ?>"
                               placeholder="Ex : 6191234560001" required>
                        <button type="button" id="toggleScanner" class="btn btn-secondary">
                            📷 Scanner
                        </button>
                    </div>
                </div>

                <!-- Visionneuse scanner -->
                <div id="scannerContainer" class="scanner-container hidden">
                    <div class="scanner-header">
                        <span>📷 Scanner en cours…</span>
                        <button type="button" id="stopScanner" class="btn btn-sm btn-danger">✕ Fermer</button>
                    </div>
                    <div id="interactive" class="viewport"></div>
                    <p id="scanStatus" class="scan-status">Pointez la caméra vers le code-barres</p>
                    <div id="scanResult" class="scan-result hidden">
                        <strong>✅ Détecté :</strong> <span id="scannedCode"></span>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="prix">Prix (DA) <span class="required">*</span></label>
                        <input type="number" id="prix" name="prix" class="form-control"
                               value="<?= e($values['prix']) ?>" step="0.01" min="0"
                               placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label for="quantite">Quantité en stock <span class="required">*</span></label>
                        <input type="number" id="quantite" name="quantite" class="form-control"
                               value="<?= e($values['quantite']) ?>" min="0"
                               placeholder="0" required>
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
                    <label for="description">Description (optionnel)</label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                              placeholder="Courte description…"><?= e($values['description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Image du produit</label>

                    <!-- Boutons de choix -->
                    <div class="img-source-btns">
                        <button type="button" class="img-source-btn active" id="btnChooseFile">
                            <span>📁</span> Choisir un fichier
                        </button>
                        <button type="button" class="img-source-btn" id="btnUseCamera">
                            <span>📷</span> Prendre une photo
                        </button>
                    </div>

                    <!-- Zone upload fichier -->
                    <div id="zoneFile">
                        <div class="upload-zone" id="uploadZone">
                            <input type="file" id="image" name="image" accept="image/*"
                                   class="upload-input" onchange="previewImage(this)">
                            <div id="uploadPlaceholder">
                                <span style="font-size:2rem">📸</span>
                                <p>Cliquer ou glisser une image<br><small>JPG, PNG, WebP – Max 5 Mo</small></p>
                            </div>
                            <img id="imagePreview" src="" alt="Aperçu" class="hidden" style="max-height:160px;border-radius:8px">
                        </div>
                    </div>

                    <!-- Zone caméra -->
                    <div id="zoneCamera" class="hidden">
                        <div class="camera-box">
                            <video id="cameraVideo" autoplay playsinline muted
                                   style="width:100%;border-radius:10px;background:#000;display:block"></video>
                            <canvas id="cameraCanvas" style="display:none"></canvas>
                            <div class="camera-controls">
                                <button type="button" id="btnSnap" class="btn btn-primary">
                                    📸 Prendre la photo
                                </button>
                                <button type="button" id="btnRetake" class="btn hidden">
                                    🔄 Reprendre
                                </button>
                                <button type="button" id="btnStopCamera" class="btn btn-danger">
                                    ✕ Fermer
                                </button>
                            </div>
                        </div>
                        <img id="cameraPreview" src="" alt="Photo prise"
                             class="hidden" style="max-height:200px;border-radius:10px;margin-top:12px">
                        <!-- Champ caché pour transmettre la photo en base64 -->
                        <input type="hidden" id="imageBase64" name="image_base64" value="">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        ✅ Enregistrer le produit
                    </button>
                    <a href="products.php" class="btn btn-lg">Annuler</a>
                </div>
            </form>
        </div>

        <!-- ── Panneau info & raccourcis ── -->
        <div>
            <div class="card" style="margin-bottom:20px">
                <h3 class="card-title">💡 Conseils</h3>
                <ul class="tip-list">
                    <li>Utilisez le scanner pour remplir le code-barres automatiquement.</li>
                    <li>Le code-barres doit être unique dans la base.</li>
                    <li>Les images doivent faire moins de 5 Mo (JPG/PNG/WebP).</li>
                    <li>Le stock à 0 affiche « Rupture » côté client.</li>
                </ul>
            </div>
            <div class="card">
                <h3 class="card-title">📷 Comment scanner</h3>
                <ol class="tip-list">
                    <li>Cliquez sur « Scanner ».</li>
                    <li>Autorisez l'accès à la caméra.</li>
                    <li>Pointez vers le code-barres.</li>
                    <li>Le champ se remplit automatiquement.</li>
                </ol>
            </div>
        </div>

    </div><!-- /two-col-grid -->

</main>
</div>

<script src="<?= APP_URL ?>/assets/js/admin.js"></script>
<script src="<?= APP_URL ?>/assets/js/scanner.js"></script>
<script>
/* ── Aperçu image depuis fichier ── */
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('imagePreview').classList.remove('hidden');
            document.getElementById('uploadPlaceholder').classList.add('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

/* ── Glisser-déposer ── */
const zone = document.getElementById('uploadZone');
zone?.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone?.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone?.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) {
        const input = document.getElementById('image');
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        previewImage(input);
    }
});

/* ══════════════════════════════════════════════
   CAMÉRA PHOTO
══════════════════════════════════════════════ */
let cameraStream = null;

const btnChooseFile  = document.getElementById('btnChooseFile');
const btnUseCamera   = document.getElementById('btnUseCamera');
const zoneFile       = document.getElementById('zoneFile');
const zoneCamera     = document.getElementById('zoneCamera');
const cameraVideo    = document.getElementById('cameraVideo');
const cameraCanvas   = document.getElementById('cameraCanvas');
const btnSnap        = document.getElementById('btnSnap');
const btnRetake      = document.getElementById('btnRetake');
const btnStopCamera  = document.getElementById('btnStopCamera');
const cameraPreview  = document.getElementById('cameraPreview');
const imageBase64    = document.getElementById('imageBase64');

/* Basculer vers upload fichier */
btnChooseFile.addEventListener('click', () => {
    btnChooseFile.classList.add('active');
    btnUseCamera.classList.remove('active');
    zoneFile.classList.remove('hidden');
    zoneCamera.classList.add('hidden');
    stopCamera();
    // Vider la base64 si on revient au fichier
    imageBase64.value = '';
});

/* Basculer vers caméra */
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

/* Démarrer la caméra */
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
        if (err.name === 'NotAllowedError')  msg = '❌ Accès caméra refusé — autorisez dans le navigateur.';
        if (err.name === 'NotFoundError')    msg = '❌ Aucune caméra détectée sur cet appareil.';
        if (err.name === 'NotReadableError') msg = '❌ Caméra déjà utilisée par une autre application.';
        showNotif(msg, 'error');
        // Revenir au mode fichier
        btnChooseFile.click();
    }
}

/* Arrêter la caméra */
function stopCamera() {
    if (cameraStream) {
        cameraStream.getTracks().forEach(t => t.stop());
        cameraStream = null;
    }
    cameraVideo.srcObject = null;
}

/* Prendre la photo */
btnSnap?.addEventListener('click', () => {
    const w = cameraVideo.videoWidth  || 640;
    const h = cameraVideo.videoHeight || 480;

    cameraCanvas.width  = w;
    cameraCanvas.height = h;

    const ctx = cameraCanvas.getContext('2d');
    ctx.drawImage(cameraVideo, 0, 0, w, h);

    const dataUrl = cameraCanvas.toDataURL('image/jpeg', 0.92);

    // Stocker la base64 dans le champ caché
    imageBase64.value = dataUrl;

    // Afficher l'aperçu
    cameraPreview.src = dataUrl;
    cameraPreview.classList.remove('hidden');
    cameraVideo.style.display = 'none';

    btnSnap.classList.add('hidden');
    btnRetake.classList.remove('hidden');

    stopCamera();
    showNotif('📸 Photo prise !', 'success');
});

/* Reprendre une photo */
btnRetake?.addEventListener('click', async () => {
    cameraPreview.classList.add('hidden');
    cameraVideo.style.display = 'block';
    imageBase64.value = '';
    btnRetake.classList.add('hidden');
    btnSnap.classList.remove('hidden');
    await startCamera();
});

/* Fermer la caméra */
btnStopCamera?.addEventListener('click', () => {
    btnChooseFile.click();
});

/* Nettoyage si on quitte la page */
window.addEventListener('beforeunload', stopCamera);
</script>
</body>
</html>