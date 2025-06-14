<?php
session_start();

// Récupérer les messages de succès
$success_messages = isset($_SESSION['success_messages']) ? $_SESSION['success_messages'] : [];
$candidature_id = isset($_SESSION['candidature_id']) ? $_SESSION['candidature_id'] : '';

// Nettoyer la session
unset($_SESSION['success_messages']);
unset($_SESSION['candidature_id']);

// Si pas de messages de succès, rediriger vers le formulaire
if (empty($success_messages)) {
    header('Location: Admission.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ENSI - Confirmation de Candidature</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .confirmation-container {
            background: rgba(255, 255, 255, 0.98);
            max-width: 800px;
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideIn 0.8s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            background: linear-gradient(90deg, #1a237e, #3f51b5, #2196f3);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .success-icon {
            font-size: 4em;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .header .subtitle {
            font-size: 1.2em;
            opacity: 0.9;
            font-weight: 300;
        }

        .content {
            padding: 50px 40px;
            text-align: center;
        }

        .success-message {
            background: linear-gradient(135deg, #e8f5e8, #c8e6c9);
            border: 2px solid #4caf50;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .success-message h2 {
            color: #2e7d32;
            font-size: 2em;
            margin-bottom: 20px;
        }

        .success-list {
            list-style: none;
            text-align: left;
        }

        .success-list li {
            background: white;
            margin: 10px 0;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #4caf50;
            font-size: 1.1em;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .success-list li:before {
            content: "✅ ";
            margin-right: 10px;
        }

        .candidature-id {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border: 2px solid #2196f3;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
        }

        .candidature-id h3 {
            color: #1976d2;
            font-size: 1.5em;
            margin-bottom: 10px;
        }

        .candidature-id .id-number {
            font-size: 1.3em;
            font-weight: bold;
            color: #0d47a1;
            background: white;
            padding: 10px 20px;
            border-radius: 8px;
            display: inline-block;
            letter-spacing: 1px;
        }

        .info-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin: 30px 0;
            text-align: left;
        }

        .info-section h3 {
            color: #1a237e;
            font-size: 1.4em;
            margin-bottom: 15px;
            text-align: center;
        }

        .info-section p {
            line-height: 1.6;
            margin-bottom: 10px;
            font-size: 1.05em;
        }

        .next-steps {
            background: linear-gradient(135deg, #fff3e0, #ffe0b2);
            border: 2px solid #ff9800;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
        }

        .next-steps h3 {
            color: #e65100;
            font-size: 1.4em;
            margin-bottom: 15px;
            text-align: center;
        }

        .next-steps ul {
            list-style: none;
            text-align: left;
        }

        .next-steps li {
            margin: 10px 0;
            padding: 10px 0;
            border-bottom: 1px solid #ffcc02;
            font-size: 1.05em;
        }

        .next-steps li:before {
            content: "📋 ";
            margin-right: 10px;
        }

        .actions {
            margin-top: 40px;
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(45deg, #1a237e, #3f51b5);
            color: white;
            box-shadow: 0 4px 15px rgba(26, 35, 126, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26, 35, 126, 0.4);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #333;
            border: 2px solid #ddd;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }

        .contact-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            text-align: center;
            border-left: 4px solid #2196f3;
        }

        .contact-info h4 {
            color: #1a237e;
            margin-bottom: 10px;
        }

        .contact-info p {
            font-size: 0.95em;
            color: #666;
        }

        @media (max-width: 768px) {
            .confirmation-container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .content {
                padding: 30px 20px;
            }
            
            .actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="header">
            <div class="success-icon">🎉</div>
            <h1>Candidature Reçue !</h1>
            <p class="subtitle">École Nationale Supérieure d'Informatique</p>
        </div>

        <div class="content">
            <div class="success-message">
                <h2>Félicitations !</h2>
                <ul class="success-list">
                    <?php foreach ($success_messages as $message): ?>
                        <li><?php echo htmlspecialchars($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if (!empty($candidature_id)): ?>
            <div class="candidature-id">
                <h3>Votre numéro de candidature</h3>
                <div class="id-number"><?php echo htmlspecialchars($candidature_id); ?></div>
                <p style="margin-top: 15px; font-size: 0.9em; color: #666;">
                    <strong>Important :</strong> Conservez précieusement ce numéro pour toute correspondance future.
                </p>
            </div>
            <?php endif; ?>

            <div class="next-steps">
                <h3>Prochaines Étapes</h3>
                <ul>
                    <li>Notre commission d'admission étudiera votre dossier</li>
                    <li>Vous recevrez une réponse sous 15 jours ouvrables</li>
                    <li>Un entretien pourra être organisé si votre profil correspond</li>
                    <li>Les résultats définitifs seront communiqués par email</li>
                </ul>
            </div>

            <div class="info-section">
                <h3>Informations Importantes</h3>
                <p><strong>Délai de réponse :</strong> 15 jours ouvrables maximum</p>
                <p><strong>Moyen de contact :</strong> Tous les échanges se feront par email</p>
                <p><strong>Documents :</strong> Si des pièces complémentaires sont nécessaires, nous vous contacterons</p>
                <p><strong>Entretien :</strong> Un entretien (présentiel ou visioconférence) pourra être organisé</p>
            </div>

            <div class="actions">
                <a href="Acueil.html" class="btn btn-primary">Retour à l'Accueil</a>
                <a href="Academie.html" class="btn btn-secondary">Découvrir nos Formations</a>
            </div>

            <div class="contact-info">
                <h4>Besoin d'aide ?</h4>
                <p>Service Admission ENSI<br>
                Email: admission@ensi.edu<br>
                Tél: +33 1 42 34 56 80</p>
            </div>
        </div>
    </div>

    <script>
        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.confirmation-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(-50px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.8s ease-out';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });

        // Fonction pour copier le numéro de candidature
        document.addEventListener('DOMContentLoaded', function() {
            const idNumber = document.querySelector('.id-number');
            if (idNumber) {
                idNumber.style.cursor = 'pointer';
                idNumber.title = 'Cliquer pour copier';
                
                idNumber.addEventListener('click', function() {
                    navigator.clipboard.writeText(this.textContent).then(function() {
                        const originalText = idNumber.textContent;
                        idNumber.textContent = 'Copié !';
                        idNumber.style.background = '#4caf50';
                        idNumber.style.color = 'white';
                        
                        setTimeout(() => {
                            idNumber.textContent = originalText;
                            idNumber.style.background = 'white';
                            idNumber.style.color = '#0d47a1';
                        }, 2000);
                    });
                });
            }
        });
    </script>
</body>
</html>