Create prestashop bug recherche nombre de produits indexés ne correspond pas
<img width="1107" height="736" alt="image" src="https://github.com/user-attachments/assets/fd306712-bfd2-4be3-9e37-546f622cead2" />

le bug est probablement due a une absence de nom sur une table prestashop 

SELECT p.id_product, pl.id_lang, pl.name
FROM ps_product p
INNER JOIN ps_product_shop ps ON (p.id_product = ps.id_product)
LEFT JOIN ps_product_lang pl ON (p.id_product = pl.id_product AND pl.id_shop = 1)
WHERE ps.active = 1 AND ps.id_shop = 1
AND p.id_product NOT IN (SELECT DISTINCT id_product FROM ps_search_index);

solution supprestion des produit detecté via cette requette
