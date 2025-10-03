<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('/home/mosa9111/public_html/telroi8/config/config.inc.php');
require_once('/home/mosa9111/public_html/telroi8/init.php');

// Désactiver toute redirection
$_GET['ajax'] = true;
$_POST['ajax'] = true;

$gsitemap = Module::getInstanceByName('gsitemap');

if (!$gsitemap || !$gsitemap->active) {
    die("❌ Le module gsitemap n'est pas installé ou activé.");
}

try {
    // Récupérer l'ID de la boutique
    $id_shop = (int) Configuration::get('PS_SHOP_DEFAULT');

    // On vide les anciens fichiers sitemap
    $gsitemap->emptySitemap($id_shop);

    // On lance la génération
    $result = $gsitemap->createSitemap($id_shop);

    echo "✅ Sitemap généré avec succès pour la boutique #$id_shop<br>";

    // Pour vérifier quels fichiers ont été créés
    $files = glob(_PS_ROOT_DIR_ . '/sitemap*.xml');
    if ($files) {
        echo "<h3>📂 Fichiers générés :</h3><ul>";
        foreach ($files as $file) {
            $url = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . basename($file);
            echo "<li><a href=\"$url\" target=\"_blank\">" . basename($file) . "</a></li>";
        }
        echo "</ul>";
    } else {
        echo "⚠️ Aucun fichier sitemap trouvé dans "._PS_ROOT_DIR_;
    }

} catch (Exception $e) {
    echo "❌ Erreur lors de la génération : " . $e->getMessage();
}
