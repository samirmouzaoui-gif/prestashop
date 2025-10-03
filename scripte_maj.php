#scripte maj
<?php
// Augmenter les limites de temps d'exécution et de mémoire
set_time_limit(0); // Permet au script de s'exécuter indéfiniment
ini_set('memory_limit', '512M'); // Augmente la limite de mémoire

// **import_custom.php**

include('/home/mosa9111/public_html/telroi8/config/config.inc.php');
include('/home/mosa9111/public_html/telroi8/init.php');

// URL du CSV hébergé sur Google Sheets
$csvUrl = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vRvFluZlr-JwQYRfib-hBblA5WJXwFxaWtE6rY9jW_63J6S4WU2CM7m2io0K6MOyJJomVcUeNHmJkE2/pub?gid=823829721&single=true&output=csv';
// Taille du lot
$batchSize = 100;

// **Variable de contrôle pour désactiver les produits manquants**
$deactivateMissingProducts = true; // Par défaut, activer la désactivation

// URLs pour les tâches d'indexation
$indexationUrls = array(
    'search_index' => 'https://telroi.re/s2riadminpathtelroi/index.php?controller=AdminSearch&action=searchCron&ajax=1&full=1&token=EbKcMZFp&id_shop=1',
    'price_index' => 'https://telroi.re/module/ps_facetedsearch/cron?action=indexPrices&token=684e43b7df',
    'full_price_index' => 'https://telroi.re/module/ps_facetedsearch/cron?action=indexPrices&full=1&token=684e43b7df',
    'attributes_index' => 'https://telroi.re/module/ps_facetedsearch/cron?action=indexAttributes&token=684e43b7df',
    'clear_cache' => 'https://telroi.re/module/ps_facetedsearch/cron?action=clearCache&token=684e43b7df'
);

// Vérifier si c'est la première exécution ou une continuation
if (!isset($_POST['auto_continue'])) {
    // Première exécution - Démarrer le processus automatique
    $cleanupStats = removeDuplicateProductsByReference('keep_newest');
            // Afficher les statistiques de nettoyage
        echo '<p class="info"><strong>[' . date('H:i:s') . ']</strong> 📊 Nettoyage terminé :</p>';
        echo '<ul>';
        echo '<li>Produits analysés : ' . $cleanupStats['total_products'] . '</li>';
        echo '<li>Références en double trouvées : ' . $cleanupStats['duplicate_references'] . '</li>';
        echo '<li>Produits supprimés : ' . $cleanupStats['products_deleted'] . '</li>';
        echo '<li>Erreurs : ' . $cleanupStats['errors'] . '</li>';
        echo '</ul>';
        
        if ($cleanupStats['duplicate_references'] > 0) {
            echo '<p class="success"><strong>[' . date('H:i:s') . ']</strong> ✅ Nettoyage des doublons terminé avec succès</p>';
        } else {
            echo '<p class="info"><strong>[' . date('H:i:s') . ']</strong> ✅ Aucun doublon trouvé</p>';
        }

    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Import Automatique CSV</title>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            #progress { border: 1px solid #ccc; padding: 15px; height: 500px; overflow-y: scroll; background: #f9f9f9; margin: 10px 0; }
            .success { color: green; }
            .error { color: red; }
            .info { color: blue; }
            .warning { color: orange; }
        </style>
    </head>
    <body>
    <h2>🚀 Lancement de l\'import automatique</h2>
    <p>Le script va traiter tous les produits automatiquement.</p>
    <div id="progress"></div>';
    
    // Formulaire initial avec redirection automatique
    echo '
    <form id="autoForm" method="post" action="' . $_SERVER['PHP_SELF'] . '">
        <input type="hidden" name="auto_continue" value="1">
        <input type="hidden" name="start_index" value="0">
        <input type="hidden" name="step" value="init">
    </form>
    
    <script>
        // Fonction pour ajouter du texte dans la zone de progression
        function addProgress(text, className) {
            var progressDiv = document.getElementById("progress");
            var className = className || "info";
            var timestamp = new Date().toLocaleTimeString();
            progressDiv.innerHTML += "<p class=\"" + className + "\"><strong>[" + timestamp + "]</strong> " + text + "</p>";
            progressDiv.scrollTop = progressDiv.scrollHeight;
        }
        
        // Démarrer automatiquement
        addProgress("Démarrage de l\'import automatique...", "info");
        setTimeout(function() {
            document.getElementById("autoForm").submit();
        }, 1000);
    </script>
    </body>
    </html>';
    
    exit;
} else {
    // Mode automatique - Traitement en continu
    $startIndex = isset($_POST['start_index']) ? (int)$_POST['start_index'] : 0;
    $step = isset($_POST['step']) ? $_POST['step'] : 'init';
    
    // Commencer le buffer
    ob_start();
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Import Automatique - En cours</title>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            #progress { border: 1px solid #ccc; padding: 15px; height: 500px; overflow-y: scroll; background: #f9f9f9; margin: 10px 0; }
            .success { color: green; }
            .error { color: red; }
            .info { color: blue; }
            .warning { color: orange; }
        </style>
    </head>
    <body>
    <h2>🔄 Import automatique en cours...</h2>
    <div id="progress">';
    
    if ($step === 'init') {
        // Étape d'initialisation - Télécharger le CSV
        echo '<p class="info"><strong>[' . date('H:i:s') . ']</strong> 📥 Téléchargement du CSV...</p>';
        
        $csvContent = file_get_contents($csvUrl);
        if ($csvContent === false) {
            echo '<p class="error"><strong>[' . date('H:i:s') . ']</strong> ❌ Erreur : Impossible de télécharger le fichier CSV.</p>';
            echo '</div></body></html>';
            ob_end_flush();
            exit;
        }
        
        // Sauvegarder le CSV dans un fichier temporaire
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_import_');
        file_put_contents($tempFile, $csvContent);
        
        // Lire le contenu CSV pour compter les produits
        $csvData = array();
        $lines = explode("\n", $csvContent);
        foreach ($lines as $line) {
            if (!empty(trim($line))) {
                $csvData[] = str_getcsv($line);
            }
        }

        if (count($csvData) < 2) {
            echo '<p class="error"><strong>[' . date('H:i:s') . ']</strong> ❌ Erreur : Le fichier CSV doit contenir au moins une ligne d\'en-têtes et une ligne de données.</p>';
            echo '</div></body></html>';
            ob_end_flush();
            exit;
        }

        $totalProducts = count($csvData) - 1; // Exclure les en-têtes
        
        echo '<p class="success"><strong>[' . date('H:i:s') . ']</strong> ✅ CSV téléchargé - ' . $totalProducts . ' produits à traiter</p>';
        
        // Passer à l'étape de traitement
        $nextStep = 'process';
        $nextStartIndex = 0;
        
    } elseif ($step === 'process') {
        // Étape de traitement - Traiter un lot de produits
        $tempFile = $_POST['temp_file'];
        $totalProducts = (int)$_POST['total_products'];
        
        echo '<p class="info"><strong>[' . date('H:i:s') . ']</strong> 🔄 Traitement du lot : produits ' . ($startIndex + 1) . ' à ' . min($startIndex + $batchSize, $totalProducts) . ' sur ' . $totalProducts . '</p>';
        
        // Lire le CSV depuis le fichier temporaire
        $csvContent = file_get_contents($tempFile);
        $csvData = array();
        $lines = explode("\n", $csvContent);
        foreach ($lines as $line) {
            if (!empty(trim($line))) {
                $csvData[] = str_getcsv($line);
            }
        }

        // Supposer que la première ligne contient les en-têtes
        $headers = $csvData[0];
        array_shift($csvData);

        // Construire un tableau associatif des indices des en-têtes
        $headerIndices = array();
        for ($i = 0; $i < count($headers); $i++) {
            $headerIndices[$headers[$i]] = $i;
        }

        if (!isset($headerIndices['Reference #'])) {
            unlink($tempFile);
            echo '<p class="error"><strong>[' . date('H:i:s') . ']</strong> ❌ Erreur : Le champ "Reference #" est introuvable.</p>';
            echo '</div></body></html>';
            ob_end_flush();
            exit;
        }

        $referenceIndex = $headerIndices['Reference #'];
        $endIndex = min($startIndex + $batchSize, $totalProducts);

        // **Initialiser le tableau des références du CSV pour ce lot**
        $csvReferences = array();

        // Traiter le lot actuel
        $processedCount = 0;
        $errorCount = 0;
        
        for ($rowIndex = $startIndex; $rowIndex < $endIndex; $rowIndex++) {
            if (!isset($csvData[$rowIndex])) {
                continue;
            }
            
            $row = $csvData[$rowIndex];
            $reference = isset($row[$referenceIndex]) ? $row[$referenceIndex] : '';

            if (empty($reference)) {
                echo '<p class="warning"><strong>[' . date('H:i:s') . ']</strong> ⚠️ Ligne ' . ($rowIndex + 1) . ' ignorée (référence manquante)</p>';
                continue;
            }

            $csvReferences[] = $reference;

            // Chercher le produit par référence
            $idProduct = (int)Product::getIdByReference($reference);
            $isNewProduct = false;

            if ($idProduct) {
                $product = new Product($idProduct);
            } else {
                $product = new Product();
                $isNewProduct = true;
            }

            $defaultLangId = (int)Configuration::get('PS_LANG_DEFAULT');
            $quantity = 0;

            // Affecter les valeurs aux propriétés du produit
            for ($i = 0; $i < count($headers); $i++) {
                $key = $headers[$i];
                $value = isset($row[$i]) ? $row[$i] : '';

                if ($key == 'Name *') {
                    $product->name = array($defaultLangId => $value);
                } elseif ($key == 'Price tax excluded') {
                    $product->price = (float)$value;
                } elseif ($key == 'Tax rules ID') {
                    $product->id_tax_rules_group = (int)$value;
                } elseif ($key == 'Meta title') {
                    $product->meta_title = array($defaultLangId => $value);
                } elseif ($key == 'Meta description') {
                    $product->meta_description = array($defaultLangId => $value);
                } elseif ($key == 'Tags (x,y,z...)') {
                    $product->tags = array($defaultLangId => $value);
                } elseif ($key == 'Active (0/1)') {
                    $product->active = (int)$value;
                } elseif ($key == 'Quantity') {
                    $quantity = (int)$value;
                } elseif ($key == 'Summary') {
                    $product->description_short = array($defaultLangId => $value);
                } elseif ($key == 'Description') {
                    $product->description = array($defaultLangId => $value);
                }elseif ($key == 'URL rewritten') {
                    if (!empty($value)) {
                        // Si la valeur existe dans le CSV, on l'utilise
                        $product->link_rewrite = array($defaultLangId => Tools::link_rewrite($value));
                    } elseif (!empty($product->name[$defaultLangId])) {
                        // Sinon, on génère à partir du nom
                        $product->link_rewrite = array($defaultLangId => Tools::link_rewrite($product->name[$defaultLangId]));
                    }
                }
                 elseif ($key == 'Manufacturer') {
                    if (!empty($value)) {
                        $manufacturerName = substr($value, 0, 64);
                        $manufacturer = Manufacturer::getIdByName($manufacturerName);
                        if (!$manufacturer) {
                            $manufacturerObj = new Manufacturer();
                            $manufacturerObj->name = $manufacturerName;
                            $manufacturerObj->active = 1;
                            if ($manufacturerObj->add()) {
                                $product->id_manufacturer = $manufacturerObj->id;
                            }
                        } else {
                            $product->id_manufacturer = $manufacturer;
                        }
                    }
                } elseif ($key == 'Reference #') {
                    if ($isNewProduct) {
                        $product->reference = $value;
                    }
                }
            }

            // Gestion de la condition
            if (isset($headerIndices['Condition'])) {
                $conditionValue = isset($row[$headerIndices['Condition']]) ? strtolower(trim($row[$headerIndices['Condition']])) : '';
                if (in_array($conditionValue, array('new', 'refurbished'))) {
                    $product->condition = $conditionValue;
                } else {
                    $product->condition = 'new';
                }
            }

            // Supprimer les anciennes caractéristiques si le produit existe déjà
            if (!$isNewProduct) {
                Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'feature_product` WHERE `id_product` = ' . (int)$product->id);
            }

            // Enregistrer le produit
            try {
                if ($product->save()) {
                    // Gérer les catégories
                    if (isset($headerIndices['Categories (x,y,z...)'])) {
                        $categoriesValue = isset($row[$headerIndices['Categories (x,y,z...)']]) ? $row[$headerIndices['Categories (x,y,z...)']] : '';
                        if (!empty($categoriesValue)) {
                            $assignedCategoryIds = handleProductCategories($product, $categoriesValue);
                            if (!empty($assignedCategoryIds)) {
                                $product->updateCategories($assignedCategoryIds);
                                if (isset($assignedCategoryIds[0])) {
                                    $product->id_category_default = $assignedCategoryIds[0];
                                    $product->save();
                                }
                            }
                        }
                    }

                    // Mise à jour de la quantité
                    StockAvailable::setQuantity($product->id, null, $quantity);

                    // Gérer les images pour un nouveau produit
                    if ($isNewProduct && isset($headerIndices['Image URLs (x,y,z...)'])) {
                        $imagesValue = isset($row[$headerIndices['Image URLs (x,y,z...)']]) ? $row[$headerIndices['Image URLs (x,y,z...)']] : '';
                        if (!empty($imagesValue)) {
                            handleProductImages($product, $imagesValue);
                        }
                    }

                    // Gérer les caractéristiques
                    if (isset($headerIndices['Feature(Name:Value:Position)'])) {
                        $featuresValue = isset($row[$headerIndices['Feature(Name:Value:Position)']]) ? $row[$headerIndices['Feature(Name:Value:Position)']] : '';
                        if (!empty($featuresValue)) {
                            handleProductFeatures($product, $featuresValue);
                        }
                    }
                    
                    echo '<p class="success"><strong>[' . date('H:i:s') . ']</strong> ✅ Produit ' . ($rowIndex + 1) . '/' . $totalProducts . ' : ' . htmlspecialchars($reference) . ' ' . ($isNewProduct ? '(Nouveau)' : '(Mis à jour)') . '</p>';
                    $processedCount++;
                } else {
                    echo '<p class="error"><strong>[' . date('Hi:s') . ']</strong> ❌ Erreur avec : ' . htmlspecialchars($reference) . '</p>';
                    $errorCount++;
                }
            } catch (Exception $e) {
                echo '<p class="error"><strong>[' . date('H:i:s') . ']</strong> ❌ Exception avec ' . htmlspecialchars($reference) . ' : ' . htmlspecialchars($e->getMessage()) . '</p>';
                $errorCount++;
            }

            unset($product);
            gc_collect_cycles();
            
            // Envoyer le buffer immédiatement pour voir la progression
            ob_flush();
            flush();
            
            // Petite pause pour éviter la surcharge
            usleep(50000); // 50ms
        }

        echo '<p class="info"><strong>[' . date('H:i:s') . ']</strong> ✓ Lot terminé - ' . $processedCount . ' produits traités, ' . $errorCount . ' erreurs</p>';

        // Déterminer l'étape suivante
        if ($endIndex < $totalProducts) {
            $nextStep = 'process';
            $nextStartIndex = $endIndex;
            $progress = round(($endIndex / $totalProducts) * 100, 1);
            echo '<p class="info"><strong>[' . date('H:i:s') . ']</strong> 📊 Progression : ' . $progress . '%</p>';
        } else {
            $nextStep = 'finalize';
            $nextStartIndex = 0;
        }
        
    } elseif ($step === 'finalize') {
        // Étape de finalisation - Désactiver les produits manquants
        $tempFile = $_POST['temp_file'];
        $totalProducts = (int)$_POST['total_products'];
        
        echo '<p class="info"><strong>[' . date('H:i:s') . ']</strong> 🎯 Finalisation de l\'import...</p>';
        
        // Reconstruire toutes les références du CSV pour la désactivation
        $csvContent = file_get_contents($tempFile);
        $csvData = array();
        $lines = explode("\n", $csvContent);
        foreach ($lines as $line) {
            if (!empty(trim($line))) {
                $csvData[] = str_getcsv($line);
            }
        }

        $headers = $csvData[0];
        array_shift($csvData);

        $headerIndices = array();
        for ($i = 0; $i < count($headers); $i++) {
            $headerIndices[$headers[$i]] = $i;
        }

        $referenceIndex = $headerIndices['Reference #'];
        $allCsvReferences = array();

        foreach ($csvData as $row) {
            $reference = isset($row[$referenceIndex]) ? $row[$referenceIndex] : '';
            if (!empty($reference)) {
                $allCsvReferences[] = $reference;
            }
        }

        // Désactiver les produits manquants
        if ($deactivateMissingProducts && !empty($allCsvReferences)) {
            $escapedReferences = array_map('pSQL', $allCsvReferences);
            $escapedReferences = array_map(function($ref) {
                return "'" . $ref . "'";
            }, $escapedReferences);

            $referencesList = implode(',', $escapedReferences);

            // Récupérer les IDs des produits dans le CSV
            $sqlIds = 'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` WHERE `reference` IN (' . $referencesList . ')';
            $result = Db::getInstance()->executeS($sqlIds);

            $idProductsInCSV = array();
            if ($result) {
                foreach ($result as $row) {
                    $idProductsInCSV[] = (int)$row['id_product'];
                }
            }

            // Désactiver les produits manquants
            if (!empty($idProductsInCSV)) {
                $idProductsInCSVList = implode(',', $idProductsInCSV);
                $affectedProducts = Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'product` SET `active` = 0 WHERE `id_product` NOT IN (' . $idProductsInCSVList . ')');
                $affectedShop = Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'product_shop` SET `active` = 0 WHERE `id_product` NOT IN (' . $idProductsInCSVList . ')');
                echo '<p class="success"><strong>[' . date('H:i:s') . ']</strong> ✅ Produits manquants désactivés</p>';
            } else {
                Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'product` SET `active` = 0');
                Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'product_shop` SET `active` = 0');
                echo '<p class="warning"><strong>[' . date('H:i:s') . ']</strong> ⚠️ Tous les produits désactivés (aucune référence valide trouvée)</p>';
            }
        }

        // Nettoyer le fichier temporaire
        unlink($tempFile);
        
        echo '<p class="success"><strong>[' . date('H:i:s') . ']</strong> 🎉 Import terminé avec succès !</p>';
        echo '<p class="success"><strong>[' . date('H:i:s') . ']</strong> ✅ ' . $totalProducts . ' produits traités automatiquement</p>';
        
        // Passer à l'étape d'indexation
        $nextStep = 'indexation';
        
    } elseif ($step === 'indexation') {
        // Étape d'indexation - Exécuter les tâches d'indexation
        echo '<p class="info"><strong>[' . date('H:i:s') . ']</strong> 🔄 Démarrage des tâches d\'indexation...</p>';
        
        // Exécuter chaque tâche d'indexation
        $indexationResults = array();
        
        foreach ($indexationUrls as $taskName => $url) {
            echo '<p class="info"><strong>[' . date('H:i:s') . ']</strong> 📊 Exécution : ' . $taskName . '</p>';
            ob_flush();
            flush();
            
            $result = executeIndexationTask($url);
            $indexationResults[$taskName] = $result;
            
            if ($result['success']) {
                echo '<p class="success"><strong>[' . date('H:i:s') . ']</strong> ✅ ' . $taskName . ' terminé avec succès</p>';
                if (!empty($result['message'])) {
                    echo '<p class="info"><strong>[' . date('H:i:s') . ']</strong> ' . htmlspecialchars($result['message']) . '</p>';
                }
            } else {
                echo '<p class="error"><strong>[' . date('H:i:s') . ']</strong> ❌ ' . $taskName . ' a échoué</p>';
                if (!empty($result['error'])) {
                    echo '<p class="error"><strong>[' . date('H:i:s') . ']</strong> Erreur : ' . htmlspecialchars($result['error']) . '</p>';
                }
            }
            
            ob_flush();
            flush();
            sleep(2); // Pause de 2 secondes entre les tâches
        }
        
        echo '<p class="success"><strong>[' . date('H:i:s') . ']</strong> 🎉 Toutes les tâches d\'indexation sont terminées !</p>';
        $nextStep = 'done';
    }

    echo '</div>'; // Fermer la div progress

    // Continuer automatiquement si ce n'est pas terminé
    if ($nextStep !== 'done') {
        echo '
        <form id="continueForm" method="post" style="display:none;">
            <input type="hidden" name="auto_continue" value="1">
            <input type="hidden" name="start_index" value="' . $nextStartIndex . '">
            <input type="hidden" name="step" value="' . $nextStep . '">';
        
        if ($step === 'init') {
            echo '<input type="hidden" name="temp_file" value="' . htmlspecialchars($tempFile) . '">
                  <input type="hidden" name="total_products" value="' . $totalProducts . '">';
        } elseif ($step === 'process' || $step === 'finalize') {
            echo '<input type="hidden" name="temp_file" value="' . htmlspecialchars($_POST['temp_file']) . '">
                  <input type="hidden" name="total_products" value="' . htmlspecialchars($_POST['total_products']) . '">';
        }
        
        echo '</form>
        <script>
            setTimeout(function() {
                document.getElementById("continueForm").submit();
            }, 1000); // 1 seconde de délai entre les lots
        </script>';
    } else {
        echo '<p class="success"><strong>[' . date('H:i:s') . ']</strong> 🏁 Processus entièrement terminé !</p>';
        echo '<p><a href="javascript:history.back()">← Retour</a></p>';
    }
    
    echo '</body></html>';
    
    // Envoyer tout le contenu
    ob_end_flush();
    exit;
}

/**
 * Fonction pour exécuter une tâche d'indexation via URL
 */
function executeIndexationTask($url) {
    $result = array(
        'success' => false,
        'message' => '',
        'error' => ''
    );
    
    try {
        // Utiliser cURL pour appeler l'URL d'indexation
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Timeout de 5 minutes
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result['success'] = true;
            $result['message'] = $response;
        } else {
            $result['error'] = "HTTP Code: $httpCode - $error";
        }
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

// Les fonctions auxiliaires restent exactement les mêmes
function parseFeature($feature) {
    $firstColon = strpos($feature, ':');
    $lastColon = strrpos($feature, ':');

    if ($firstColon === false) {
        return array('', '', null);
    } elseif ($firstColon === $lastColon) {
        $name = substr($feature, 0, $firstColon);
        $value = substr($feature, $firstColon + 1);
        $position = null;
    } else {
        $name = substr($feature, 0, $firstColon);
        $value = substr($feature, $firstColon + 1, $lastColon - $firstColon - 1);
        $position = substr($feature, $lastColon + 1);
    }

    return array(trim($name), trim($value), trim($position));
}

function handleProductCategories($product, $categories) {
    $finalCategoryIds = array();

    if (!empty($categories)) {
        $categoryNames = array_map('trim', explode(',', $categories));

        for ($i = 0; $i < count($categoryNames); $i++) {
            $categoryName = $categoryNames[$i];
            $idLang = (int)Configuration::get('PS_LANG_DEFAULT');

            $categoriesFound = Category::searchByName($idLang, $categoryName, false);

            if (!empty($categoriesFound)) {
                if (is_array($categoriesFound)) {
                    foreach ($categoriesFound as $category) {
                        if (isset($category['name']) && $category['name'] == $categoryName) {
                            if (isset($category['id_category'])) {
                                $finalCategoryIds[] = (int)$category['id_category'];
                                break;
                            }
                        }
                    }
                } elseif (isset($categoriesFound['id_category'])) {
                    $finalCategoryIds[] = (int)$categoriesFound['id_category'];
                }
            } else {
                $newCategory = new Category();
                $newCategory->name = array($idLang => $categoryName);
                $newCategory->id_parent = Configuration::get('PS_HOME_CATEGORY');
                $newCategory->active = 1;
                $newCategory->link_rewrite = array($idLang => Tools::link_rewrite($categoryName));

                if ($newCategory->add()) {
                    $finalCategoryIds[] = (int)$newCategory->id;
                }
            }
        }
    }

    return $finalCategoryIds;
}

function handleProductImages($product, $imagesUrls) {
    if (!empty($imagesUrls)) {
        $urls = array_map('trim', explode(',', $imagesUrls));

        for ($i = 0; $i < count($urls); $i++) {
            $url = $urls[$i];
            addProductImage($product, $url);
        }
    }
}

function addProductImage($product, $imageUrl) {
    $image = new Image();
    $image->id_product = $product->id;
    $image->position = Image::getHighestPosition($product->id) + 1;
    $image->cover = !Image::getImages(Configuration::get('PS_LANG_DEFAULT'), $product->id);

    if ($image->add()) {
        $imagePath = _PS_PROD_IMG_DIR_ . $image->getImgPath() . '.jpg';

        $imageDir = dirname($imagePath);
        if (!is_dir($imageDir)) {
            if (!mkdir($imageDir, 0777, true)) {
                return;
            }
        }

        $imageContent = @file_get_contents($imageUrl);
        if ($imageContent === FALSE) {
            $image->delete();
            return;
        }

        if (file_put_contents($imagePath, $imageContent)) {
            $imagesTypes = ImageType::getImagesTypes('products');
            for ($i = 0; $i < count($imagesTypes); $i++) {
                $imageType = $imagesTypes[$i];
                ImageManager::resize(
                    $imagePath,
                    _PS_PROD_IMG_DIR_ . $image->getImgPath() . '-' . $imageType['name'] . '.jpg',
                    $imageType['width'],
                    $imageType['height']
                );
            }
        } else {
            $image->delete();
        }
    }
}

function handleProductFeatures($product, $featuresData) {
    if (!empty($featuresData)) {
        $features = array_map('trim', explode(',', $featuresData));
        $idLang = (int)Configuration::get('PS_LANG_DEFAULT');

        foreach ($features as $feature) {
            list($name, $value, $position) = parseFeature($feature);

            if ($name && $value) {
                $idFeature = getFeatureIdByName($name, $position);
                if (!$idFeature) {
                    continue;
                }

                $idFeatureValue = getFeatureValueId($idFeature, $value);

                if (!$idFeatureValue) {
                    continue;
                }

                $existing = Db::getInstance()->getValue(
                    'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'feature_product` WHERE `id_product` = ' . (int)$product->id . ' AND `id_feature` = ' . (int)$idFeature
                );

                if (!$existing) {
                    $insert = Db::getInstance()->insert('feature_product', array(
                        'id_feature' => (int)$idFeature,
                        'id_product' => (int)$product->id,
                        'id_feature_value' => (int)$idFeatureValue
                    ));
                }
            }
        }
    }
}

function getFeatureIdByName($name, $position = 0) {
    $idLang = (int)Configuration::get('PS_LANG_DEFAULT');
    $query = 'SELECT f.id_feature FROM ' . _DB_PREFIX_ . 'feature_lang fl
        LEFT JOIN ' . _DB_PREFIX_ . 'feature f ON (f.id_feature = fl.id_feature)
        WHERE fl.name = \'' . pSQL($name) . '\' AND fl.id_lang = ' . (int)$idLang;
    $featureId = Db::getInstance()->getValue($query);

    if (!$featureId) {
        $featureObj = new Feature();
        $featureObj->name = array($idLang => $name);
        $featureObj->position = (int)$position;
        if ($featureObj->add()) {
            $featureId = $featureObj->id;
        } else {
            return false;
        }
    }

    return $featureId;
}

function getFeatureValueId($idFeature, $value) {
    $idLang = (int)Configuration::get('PS_LANG_DEFAULT');
    $query = 'SELECT fv.id_feature_value FROM ' . _DB_PREFIX_ . 'feature_value fv
        LEFT JOIN ' . _DB_PREFIX_ . 'feature_value_lang fvl ON (fv.id_feature_value = fvl.id_feature_value)
        WHERE fv.id_feature = ' . (int)$idFeature . ' AND fvl.value = \'' . pSQL($value) . '\' AND fvl.id_lang = ' . (int)$idLang . ' AND fv.custom = 0';
    $featureValueId = Db::getInstance()->getValue($query);

    if (!$featureValueId) {
        $featureValue = new FeatureValue();
        $featureValue->id_feature = $idFeature;
        $featureValue->value = array($idLang => $value);
        $featureValue->custom = 0;
        if ($featureValue->add()) {
            $featureValueId = $featureValue->id;
        } else {
            return false;
        }
    }

    return $featureValueId;
}

/**
 * Fonction pour supprimer les produits en double par référence
 * Garde le produit le plus récent
 */
function removeDuplicateProductsByReference($mode = 'keep_newest') {
    $stats = array(
        'total_products' => 0,
        'duplicate_references' => 0,
        'products_deleted' => 0,
        'errors' => 0
    );

    try {
        $db = Db::getInstance();
        
        // Compter le nombre total de produits
        $totalProducts = $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product`');
        $stats['total_products'] = (int)$totalProducts;
        
        // Trouver les références en double
        $sqlDuplicates = '
            SELECT `reference`, COUNT(*) as count 
            FROM `' . _DB_PREFIX_ . 'product` 
            WHERE `reference` != "" AND `reference` IS NOT NULL 
            GROUP BY `reference` 
            HAVING COUNT(*) > 1
        ';
        
        $duplicates = $db->executeS($sqlDuplicates);
        $stats['duplicate_references'] = count($duplicates);
        
        if (empty($duplicates)) {
            return $stats;
        }
        
        // Pour chaque référence en double, supprimer les doublons
        foreach ($duplicates as $duplicate) {
            $reference = $duplicate['reference'];
            $count = $duplicate['count'];
            
            // Récupérer tous les produits avec cette référence
            $sqlProducts = '
                SELECT p.`id_product`
                FROM `' . _DB_PREFIX_ . 'product` p
                WHERE p.`reference` = "' . pSQL($reference) . '"
                ORDER BY p.`date_add` ' . ($mode === 'keep_newest' ? 'DESC' : 'ASC') . '
            ';
            
            $products = $db->executeS($sqlProducts);
            
            if (count($products) <= 1) {
                continue;
            }
            
            // Le premier produit est celui qu'on garde
            $productToKeep = array_shift($products);
            $productsToDelete = $products;
            
            // Supprimer les doublons
            foreach ($productsToDelete as $productToDelete) {
                $productId = $productToDelete['id_product'];
                
                try {
                    $product = new Product($productId);
                    if (Validate::isLoadedObject($product)) {
                        if ($product->delete()) {
                            $stats['products_deleted']++;
                        } else {
                            $stats['errors']++;
                        }
                    } else {
                        $stats['errors']++;
                    }
                } catch (Exception $e) {
                    $stats['errors']++;
                }
            }
        }
        
    } catch (Exception $e) {
        $stats['errors']++;
    }
    
    return $stats;
}

?>
