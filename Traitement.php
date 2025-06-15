<?php
// Traitement.php - Traitement du formulaire d'admission ENSI
// Auteurs: COULIBALY TOUBY BAKARY & OUERDAOGO FAYSSAL DIMITRI

// Configuration de l'affichage des erreurs (à désactiver en production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration de l'encodage
header('Content-Type: text/html; charset=UTF-8');

// Démarrage de la session
session_start();

// Configuration de la base de données
$host = 'localhost';
$dbname = 'ensi_admission';
$username = 'root'; // À modifier selon votre configuration
$password = '';     // À modifier selon votre configuration

// Configuration email
$admin_email = 'admission@ensi.rnu.tn';
$smtp_host = 'localhost'; // À configurer selon votre serveur SMTP

// Dossier de stockage des fichiers uploadés
$upload_dir = 'uploads/candidatures/';

// Vérification si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Admission.html');
    exit();
}

try {
    // Connexion à la base de données
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Validation et nettoyage des données
    $data = validateAndSanitizeData($_POST);
    
    // Gestion des fichiers uploadés
    $uploaded_files = handleFileUploads($_FILES);
    
    // Insertion en base de données
    $candidature_id = insertCandidature($pdo, $data, $uploaded_files);
    
    // Envoi des emails de confirmation
    sendConfirmationEmails($data, $candidature_id);
    
    // Affichage de la page de succès
    displaySuccessPage($data, $candidature_id);
    
} catch (Exception $e) {
    // Gestion des erreurs
    error_log("Erreur traitement candidature: " . $e->getMessage());
    displayErrorPage($e->getMessage());
}

/**
 * Validation et nettoyage des données du formulaire
 */
function validateAndSanitizeData($post_data) {
    $data = [];
    
    // Informations personnelles (obligatoires)
    $required_fields = [
        'nom', 'prenom', 'date-naissance', 'lieu-naissance', 
        'nationalite', 'sexe', 'adresse', 'telephone', 'email',
        'niveau-admission', 'specialite', 'dernier-diplome', 
        'etablissement', 'annee-obtention', 'niveau-anglais', 'motivation'
    ];
    
    foreach ($required_fields as $field) {
        if (empty($post_data[$field])) {
            throw new Exception("Le champ '$field' est obligatoire.");
        }
        $data[$field] = trim(htmlspecialchars($post_data[$field], ENT_QUOTES, 'UTF-8'));
    }
    
    // Validation email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("L'adresse email n'est pas valide.");
    }
    
    // Validation date
    if (!validateDate($data['date-naissance'])) {
        throw new Exception("La date de naissance n'est pas valide.");
    }
    
    // Validation année
    if ($data['annee-obtention'] < 2000 || $data['annee-obtention'] > date('Y')) {
        throw new Exception("L'année d'obtention du diplôme n'est pas valide.");
    }
    
    // Champs optionnels
    $optional_fields = [
        'telephone-urgence', 'contact-urgence', 'mention', 'parcours',
        'autres-langues', 'experiences', 'projet-professionnel'
    ];
    
    foreach ($optional_fields as $field) {
        $data[$field] = isset($post_data[$field]) ? trim(htmlspecialchars($post_data[$field], ENT_QUOTES, 'UTF-8')) : '';
    }
    
    // Langages de programmation (array)
    $data['langages'] = isset($post_data['langages']) && is_array($post_data['langages']) 
        ? implode(', ', array_map('htmlspecialchars', $post_data['langages'])) 
        : '';
    
    // Comment a connu l'école
    $data['connaissance'] = isset($post_data['connaissance']) ? htmlspecialchars($post_data['connaissance'], ENT_QUOTES, 'UTF-8') : '';
    
    // Consentements (obligatoires)
    if (empty($post_data['declaration-honneur']) || empty($post_data['rgpd']) || empty($post_data['conditions'])) {
        throw new Exception("Vous devez accepter tous les consentements obligatoires.");
    }
    
    $data['newsletter'] = isset($post_data['newsletter']) ? 1 : 0;
    
    return $data;
}

/**
 * Validation d'une date
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Gestion des fichiers uploadés
 */
function handleFileUploads($files) {
    global $upload_dir;
    
    $uploaded_files = [];
    $max_file_size = 5 * 1024 * 1024; // 5MB
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception("Impossible de créer le dossier de stockage.");
        }
    }
    
    // ID unique pour cette candidature
    $candidate_id = uniqid('ENSI_', true);
    $candidate_dir = $upload_dir . $candidate_id . '/';
    
    if (!mkdir($candidate_dir, 0755)) {
        throw new Exception("Impossible de créer le dossier candidat.");
    }
    
    // Types de fichiers autorisés
    $allowed_types = [
        'cv' => ['application/pdf'],
        'photo' => ['image/jpeg', 'image/png', 'image/jpg'],
        'diplomes' => ['application/pdf'],
        'autres-documents' => ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg']
    ];
    
    foreach ($files as $field_name => $file_info) {
        if ($field_name === 'diplomes' || $field_name === 'autres-documents') {
            // Fichiers multiples
            if (isset($file_info['name']) && is_array($file_info['name'])) {
                $uploaded_files[$field_name] = [];
                for ($i = 0; $i < count($file_info['name']); $i++) {
                    if ($file_info['error'][$i] === UPLOAD_ERR_OK) {
                        $file = processFile(
                            $file_info['tmp_name'][$i],
                            $file_info['name'][$i],
                            $file_info['type'][$i],
                            $file_info['size'][$i],
                            $candidate_dir,
                            $field_name,
                            $allowed_types[$field_name],
                            $max_file_size
                        );
                        $uploaded_files[$field_name][] = $file;
                    }
                }
            }
        } else {
            // Fichier unique
            if (isset($file_info['error']) && $file_info['error'] === UPLOAD_ERR_OK) {
                if (in_array($field_name, ['cv', 'photo']) && empty($file_info['name'])) {
                    throw new Exception("Le fichier '$field_name' est obligatoire.");
                }
                
                if (!empty($file_info['name'])) {
                    $uploaded_files[$field_name] = processFile(
                        $file_info['tmp_name'],
                        $file_info['name'],
                        $file_info['type'],
                        $file_info['size'],
                        $candidate_dir,
                        $field_name,
                        $allowed_types[$field_name],
                        $max_file_size
                    );
                }
            } elseif (in_array($field_name, ['cv', 'photo'])) {
                throw new Exception("Le fichier '$field_name' est obligatoire.");
            }
        }
    }
    
    return ['candidate_id' => $candidate_id, 'files' => $uploaded_files];
}

/**
 * Traitement d'un fichier individuel
 */
function processFile($tmp_name, $original_name, $type, $size, $candidate_dir, $field_name, $allowed_types, $max_size) {
    // Vérification de la taille
    if ($size > $max_size) {
        throw new Exception("Le fichier '$original_name' est trop volumineux (max 5MB).");
    }
    
    // Vérification du type MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected_type = finfo_file($finfo, $tmp_name);
    finfo_close($finfo);
    
    if (!in_array($detected_type, $allowed_types)) {
        throw new Exception("Le type de fichier '$original_name' n'est pas autorisé.");
    }
    
    // Génération d'un nom de fichier sécurisé
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $safe_filename = $field_name . '_' . time() . '_' . uniqid() . '.' . $extension;
    $destination = $candidate_dir . $safe_filename;
    
    // Déplacement du fichier
    if (!move_uploaded_file($tmp_name, $destination)) {
        throw new Exception("Erreur lors du téléchargement du fichier '$original_name'.");
    }
    
    return [
        'original_name' => $original_name,
        'stored_name' => $safe_filename,
        'path' => $destination,
        'size' => $size,
        'type' => $detected_type
    ];
}

/**
 * Insertion de la candidature en base de données
 */
function insertCandidature($pdo, $data, $uploaded_files) {
    $sql = "INSERT INTO candidatures (
        nom, prenom, date_naissance, lieu_naissance, nationalite, sexe, adresse,
        telephone, email, telephone_urgence, contact_urgence,
        niveau_admission, specialite, dernier_diplome, etablissement, 
        annee_obtention, mention, parcours, langages, niveau_anglais, 
        autres_langues, experiences, motivation, projet_professionnel,
        connaissance_ecole, newsletter, candidate_id, fichiers,
        date_candidature, statut
    ) VALUES (
        :nom, :prenom, :date_naissance, :lieu_naissance, :nationalite, :sexe, :adresse,
        :telephone, :email, :telephone_urgence, :contact_urgence,
        :niveau_admission, :specialite, :dernier_diplome, :etablissement,
        :annee_obtention, :mention, :parcours, :langages, :niveau_anglais,
        :autres_langues, :experiences, :motivation, :projet_professionnel,
        :connaissance, :newsletter, :candidate_id, :fichiers,
        NOW(), 'en_attente'
    )";
    
    $stmt = $pdo->prepare($sql);
    
    $params = [
        ':nom' => $data['nom'],
        ':prenom' => $data['prenom'],
        ':date_naissance' => $data['date-naissance'],
        ':lieu_naissance' => $data['lieu-naissance'],
        ':nationalite' => $data['nationalite'],
        ':sexe' => $data['sexe'],
        ':adresse' => $data['adresse'],
        ':telephone' => $data['telephone'],
        ':email' => $data['email'],
        ':telephone_urgence' => $data['telephone-urgence'],
        ':contact_urgence' => $data['contact-urgence'],
        ':niveau_admission' => $data['niveau-admission'],
        ':specialite' => $data['specialite'],
        ':dernier_diplome' => $data['dernier-diplome'],
        ':etablissement' => $data['etablissement'],
        ':annee_obtention' => $data['annee-obtention'],
        ':mention' => $data['mention'],
        ':parcours' => $data['parcours'],
        ':langages' => $data['langages'],
        ':niveau_anglais' => $data['niveau-anglais'],
        ':autres_langues' => $data['autres-langues'],
        ':experiences' => $data['experiences'],
        ':motivation' => $data['motivation'],
        ':projet_professionnel' => $data['projet-professionnel'],
        ':connaissance' => $data['connaissance'],
        ':newsletter' => $data['newsletter'],
        ':candidate_id' => $uploaded_files['candidate_id'],
        ':fichiers' => json_encode($uploaded_files['files'], JSON_UNESCAPED_UNICODE)
    ];
    
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

/**
 * Envoi des emails de confirmation
 */
function sendConfirmationEmails($data, $candidature_id) {
    global $admin_email;
    
    $subject_candidate = "Confirmation de votre candidature - ENSI Tunisie";
    $subject_admin = "Nouvelle candidature reçue - ENSI Tunisie";
    
    // Email au candidat
    $message_candidate = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #1a237e;'>Confirmation de candidature - ENSI Tunisie</h2>
            
            <p>Bonjour {$data['prenom']} {$data['nom']},</p>
            
            <p>Nous avons bien reçu votre candidature pour intégrer l'École Nationale des Sciences de l'Informatique.</p>
            
            <div style='background: #f8f9fa; padding: 15px; border-left: 4px solid #2196f3; margin: 20px 0;'>
                <strong>Détails de votre candidature :</strong><br>
                • Numéro de candidature : <strong>ENSI-" . str_pad($candidature_id, 6, '0', STR_PAD_LEFT) . "</strong><br>
                • Niveau d'admission : {$data['niveau-admission']}<br>
                • Spécialité : {$data['specialite']}<br>
                • Date de soumission : " . date('d/m/Y à H:i') . "
            </div>
            
            <p><strong>Prochaines étapes :</strong></p>
            <ol>
                <li>Étude de votre dossier par notre commission d'admission</li>
                <li>Entretien téléphonique ou en visio (si votre profil est retenu)</li>
                <li>Décision finale sous 15 jours ouvrables</li>
            </ol>
            
            <p>Vous recevrez un email dès que votre dossier aura été étudié.</p>
            
            <hr style='margin: 30px 0;'>
            <p style='font-size: 12px; color: #666;'>
                École Nationale des Sciences de l'Informatique<br>
                Campus Universitaire de la Manouba, 2010 Manouba, Tunisie<br>
                Tél : +216 70 860 260 | Email : admission@ensi.rnu.tn
            </p>
        </div>
    </body>
    </html>";
    
    // Email à l'administration
    $message_admin = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h3>Nouvelle candidature reçue</h3>
        <p><strong>Candidat :</strong> {$data['prenom']} {$data['nom']}</p>
        <p><strong>Email :</strong> {$data['email']}</p>
        <p><strong>Téléphone :</strong> {$data['telephone']}</p>
        <p><strong>Niveau :</strong> {$data['niveau-admission']}</p>
        <p><strong>Spécialité :</strong> {$data['specialite']}</p>
        <p><strong>ID Candidature :</strong> $candidature_id</p>
        <p><strong>Date :</strong> " . date('d/m/Y à H:i') . "</p>
    </body>
    </html>";
    
    // Headers pour l'email HTML
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: ENSI Admission <$admin_email>" . "\r\n";
    
    // Envoi des emails (en production, utiliser une bibliothèque comme PHPMailer)
    mail($data['email'], $subject_candidate, $message_candidate, $headers);
    mail($admin_email, $subject_admin, $message_admin, $headers);
}

/**
 * Affichage de la page de succès
 */
function displaySuccessPage($data, $candidature_id) {
    $candidature_number = "ENSI-" . str_pad($candidature_id, 6, '0', STR_PAD_LEFT);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Candidature envoyée - ENSI Tunisie</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(90deg, #1a237e, #3f51b5); color: white; padding: 30px; text-align: center; }
            .content { padding: 40px; }
            .success-icon { font-size: 80px; color: #4caf50; text-align: center; margin-bottom: 20px; }
            .info-box { background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4caf50; }
            .steps { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .btn { display: inline-block; background: #1a237e; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 10px 5px; }
            .btn:hover { background: #303f9f; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>École Nationale des Sciences de l'Informatique</h1>
                <p>Candidature envoyée avec succès</p>
            </div>
            
            <div class="content">
                <div class="success-icon">✅</div>
                
                <h2 style="text-align: center; color: #1a237e;">Félicitations <?php echo htmlspecialchars($data['prenom']); ?> !</h2>
                
                <p style="text-align: center; font-size: 18px;">Votre candidature a été transmise avec succès à notre équipe d'admission.</p>
                
                <div class="info-box">
                    <h3>📋 Récapitulatif de votre candidature</h3>
                    <p><strong>Numéro de candidature :</strong> <?php echo $candidature_number; ?></p>
                    <p><strong>Nom :</strong> <?php echo htmlspecialchars($data['nom'] . ' ' . $data['prenom']); ?></p>
                    <p><strong>Email :</strong> <?php echo htmlspecialchars($data['email']); ?></p>
                    <p><strong>Niveau d'admission :</strong> <?php echo htmlspecialchars($data['niveau-admission']); ?></p>
                    <p><strong>Spécialité :</strong> <?php echo htmlspecialchars($data['specialite']); ?></p>
                    <p><strong>Date de soumission :</strong> <?php echo date('d/m/Y à H:i'); ?></p>
                </div>
                
                <div class="steps">
                    <h3>🚀 Prochaines étapes</h3>
                    <ol>
                        <li><strong>Confirmation par email</strong> - Vous recevrez un email de confirmation dans les prochaines minutes</li>
                        <li><strong>Étude du dossier</strong> - Notre commission étudiera votre candidature sous 5 jours ouvrables</li>
                        <li><strong>Entretien</strong> - Si votre profil correspond, nous vous contacterons pour un entretien</li>
                        <li><strong>Décision finale</strong> - Réponse définitive sous 15 jours ouvrables maximum</li>
                    </ol>
                </div>
                
                <div style="background: #fff3e0; padding: 15px; border-radius: 8px; border-left: 4px solid #ff9800;">
                    <h4>📧 Important</h4>
                    <p>Vérifiez votre boîte de réception (y compris les spams) pour l'email de confirmation.</p>
                    <p>Conservez précieusement votre <strong>numéro de candidature : <?php echo $candidature_number; ?></strong></p>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <a href="index.html" class="btn">🏠 Retour à l'accueil</a>
                    <a href="Academie.html" class="btn">📚 Découvrir nos formations</a>
                </div>
                
                <hr style="margin: 40px 0;">
                
                <div style="text-align: center; color: #666; font-size: 14px;">
                    <p><strong>Contact :</strong></p>
                    <p>📞 +216 70 860 260 | 📧 admission@ensi.rnu.tn</p>
                    <p>🌐 www.ensi.rnu.tn</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Affichage de la page d'erreur
 */
function displayErrorPage($error_message) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erreur - ENSI Tunisie</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .header { background: #f44336; color: white; padding: 30px; text-align: center; }
            .content { padding: 40px; text-align: center; }
            .error-icon { font-size: 80px; color: #f44336; margin-bottom: 20px; }
            .btn { display: inline-block; background: #1a237e; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Erreur lors du traitement</h1>
            </div>
            
            <div class="content">
                <div class="error-icon">❌</div>
                <h2>Une erreur est survenue</h2>
                <p style="color: #f44336; background: #ffebee; padding: 15px; border-radius: 6px;">
                    <?php echo htmlspecialchars($error_message); ?>
                </p>
                <p>Veuillez réessayer ou contacter l'administration si le problème persiste.</p>
                
                <a href="javascript:history.back()" class="btn">⬅️ Retour</a>
                <a href="Admission.html" class="btn">🔄 Nouveau formulaire</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
