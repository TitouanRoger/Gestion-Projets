<!DOCTYPE html>
<!-- ============================================================ -->
<!-- PRIVACY.PHP - POLITIQUE DE CONFIDENTIALITÉ -->
<!-- ============================================================ -->
<!-- Page statique décrivant la politique de confidentialité -->
<!-- conforme au RGPD: -->
<!-- - Données collectées (identifiants, projets, tâches, messages) -->
<!-- - Finalités et bases légales -->
<!-- - Conservation (12 mois pour logs, suppression à la demande) -->
<!-- - Sécurité (AES-256-GCM, sessions sécurisées) -->
<!-- - Droits des utilisateurs (accès, rectification, effacement) -->
<!-- ============================================================ -->
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>Politique de confidentialité</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/privacy.css">
</head>

<body>
    <!-- ============================ -->
    <!-- CONTENU PRINCIPAL -->
    <!-- ============================ -->
    <!-- Carte centrée avec sections numérotées -->
    <div class="container">
        <div class="card">
            <h1>Politique de confidentialité</h1>


            <!-- Description générale -->
            <p>La présente politique décrit comment nous collectons, utilisons et protégeons vos données personnelles
                dans l’application « gestion_projet ».</p>

            <h2>1. Responsable du traitement</h2>
            <p>Le responsable du traitement est l’administrateur de l’application. Pour toute question, vous pouvez nous
                contacter via la page « Contact ».</p>

            <h2>2. Données collectées</h2>
            <ul>
                <li><strong>Identifiants de compte :</strong> prénom, nom, email, mot de passe (haché avec bcrypt).</li>
                <li><strong>Données de projet :</strong> titres de projets, descriptions, rôles des membres, statuts.
                </li>
                <li><strong>Tâches et tickets :</strong> titres, descriptions, types, priorités, statuts, dates de
                    création, assignations, historiques de validation/rejet, fichiers joints.</li>
                <li><strong>Messages privés :</strong> contenu des messages, fichiers joints, horodatages d'envoi,
                    statuts de lecture, indicateurs de frappe.</li>
                <li><strong>Code source et documents :</strong> fichiers uploadés dans le dépôt de code (stockés
                    chiffrés avec AES-256-GCM), documents du projet, métadonnées associées.</li>
                <li><strong>Journaux techniques :</strong> adresses IP, horodatages de connexion, événements
                    applicatifs, logs d'erreurs serveur, tentatives d'accès.</li>
                <li><strong>Données de session :</strong> identifiants de session, durée d'inactivité, préférences
                    utilisateur temporaires.</li>
            </ul>


            <!-- ============================ -->
            <!-- SECTION 3: FINALITÉS -->
            <!-- ============================ -->
            <h2>3. Finalités et bases légales</h2>
            <ul>
                <li>Fournir les fonctionnalités de gestion de projets et de collaboration (exécution du contrat).</li>
                <li>Assurer la sécurité, la prévention de la fraude et la continuité de service (intérêt légitime).</li>
                <li>Répondre aux demandes d’assistance (intérêt légitime/consentement).</li>
            </ul>


            <!-- ============================ -->
            <!-- SECTION 4: CONSERVATION -->
            <!-- ============================ -->
            <!-- Détaille les durées de rétention et procédures de purge -->
            <h2>4. Conservation des données</h2>
            <p>Les données sont conservées le temps nécessaire à la fourniture du service et au respect des obligations
                légales :</p>
            <ul>
                <li><strong>Comptes utilisateurs actifs :</strong> tant que le compte reste actif et utilisé.</li>
                <li><strong>Suppression de compte :</strong> la suppression d'un compte utilisateur entraîne la purge
                    immédiate de toutes ses données personnelles (profil, projets créés, memberships, tickets, tâches,
                    messages, fichiers uploadés) dans un délai technique de 24 heures maximum.</li>
                <li><strong>Projets supprimés :</strong> la suppression d'un projet déclenche une purge des données
                    associées (membres du projet, tickets, tâches et assignations, messages privés et fichiers joints,
                    documents, code source chiffré) selon la logique technique en place.</li>
                <li><strong>Journaux techniques :</strong> conservés 12 mois maximum pour la sécurité et le débogage,
                    puis automatiquement purgés.</li>
                <li><strong>Sessions expirées :</strong> nettoyées automatiquement après 15 minutes d'inactivité.</li>
            </ul>


            <!-- ============================ -->
            <!-- SECTION 5-9: AUTRES MENTIONS LÉGALES -->
            <!-- ============================ -->
            <!-- Partage, sécurité, droits, cookies, transferts hors UE -->
            <h2>5. Partage et destinataires</h2>
            <p>Les données ne sont pas vendues. Elles peuvent être partagées avec des prestataires techniques
                strictement nécessaires (hébergement, sécurité) sous accords de confidentialité.</p>

            <h2>6. Sécurité</h2>
            <ul>
                <li>Authentification par session sécurisée.</li>
                <li>Chiffrement au repos de certains fichiers (AES-256-GCM) et bonnes pratiques de stockage.</li>
                <li>Mesures de durcissement serveur (limitation des uploads, contrôles d’accès, validation des chemins).
                </li>
            </ul>

            <h2>7. Vos droits</h2>
            <ul>
                <li>Droit d’accès, de rectification, d’effacement et de portabilité.</li>
                <li>Droit d’opposition et de limitation du traitement.</li>
                <li>Pour exercer vos droits, contactez-nous via la page « Contact ». Une preuve d’identité peut être
                    requise.</li>
            </ul>

            <h2>8. Cookies et traces</h2>
            <p>Des cookies techniques peuvent être utilisés pour la session et la sécurité. Aucun cookie publicitaire
                n’est défini par défaut.</p>

            <h2>9. Transferts hors UE</h2>
            <p>Si des transferts sont nécessaires, ils seront encadrés par des garanties appropriées (clauses
                contractuelles types ou équivalents).</p>

            <div class="footer">
                <!-- Liens de navigation -->
                <p>Retour à l’<a href="index.php">Accueil</a> — Besoin d’aide ? Voir <a href="contact.php">Contact</a>.
                </p>
            </div>
        </div>
    </div>
</body>

</html>