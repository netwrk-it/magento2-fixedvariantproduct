# Netwrk Fixed Variant Product (Magento 2)

Nieuw producttype voor Magento 2.4, standaard **"Starter pakket"** genoemd (de naam is instelbaar). Je maakt er een
pakket mee, bijvoorbeeld voor een patroon, waarin de klant de set zelf samenstelt:

* De beheerder maakt per soort materiaal een **groep** met een vast **aantal**, bijvoorbeeld *Wol 10×, Garen 1×, Knopen 6×*,
  en kiest per groep de producten waaruit de klant mag kiezen (gele wol, rode wol, …).
* De klant verdeelt het aantal zelf over de producten van een groep (bijvoorbeeld 6× geel + 4× rood). Toevoegen aan de
  winkelwagen kan pas als elke groep precies vol is.
* Een groep die als **Extra** is gemarkeerd is optioneel ("extra materiaal voor beginners"). De klant vinkt die optie
  aan, kiest daarbinnen opnieuw het product en mag maximaal het ingestelde aantal bijbestellen.
* Net als bij een grouped product komen de gekozen producten als **losse regels** in winkelwagen en order. Prijs,
  voorraad, MSI en facturatie gaan via de gewone producten. Elke regel krijgt "Pakket: …" en "Onderdeel: …" mee, die
  ook in de backend-order te zien zijn.
* De prijs in de catalogus is "Vanaf €…": de goedkoopste samenstelling, berekend in een eigen prijsindexer.

## Backend

* **Producten › Product toevoegen › Starter pakket**: in het product staat het blok *Samenstelling* met de groepen.
  Hier stel je titel, aantal en Extra (ja/nee) in en kies je de producten (enkelvoudige en virtuele producten).
* **Content › Netwrk › Starter pakketten**: overzicht van alle pakketten, zoals het productoverzicht. Met filters,
  zoeken, het aantal groepen en stuks per set, en massa-acties (verwijderen, in- en uitschakelen).
* **Winkels › Configuratie › Netwrk › Starter pakket**: naam van het producttype (standaard "Starter pakket") en de
  teksten op de frontend.

## Installatie (bijvoorbeeld op Hypernode)

```bash
cd /data/web/magento2
composer config repositories.netwrk-fixedvariantproduct vcs git@github.com:netwrk-it/magento2-fixedvariantproduct.git
composer require netwrk/magento2-fixedvariantproduct:dev-main
# of handmatig: kopieer deze map naar app/code/Netwrk/FixedVariantProduct

bin/magento module:enable Netwrk_FixedVariantProduct
bin/magento setup:upgrade
bin/magento setup:di:compile            # in production mode
bin/magento setup:static-content:deploy nl_NL en_US   # in production mode
bin/magento indexer:reindex catalog_product_price
bin/magento cache:flush
```

## Technisch

| Onderdeel | Waar |
|---|---|
| Producttype `fixed_variant` | `etc/product_types.xml`, `Model/Product/Type/FixedVariant.php` |
| Groepen en keuzeproducten | tabellen `netwrk_fixed_variant_group` / `netwrk_fixed_variant_item` (+ `catalog_product_relation`) |
| Prijsindex ("vanaf"-prijs) | `Model/Indexer/Price.php`, plus mview-subscriptie op `netwrk_fixed_variant_group` |
| Admin-formulier | `Ui/DataProvider/Product/Form/Modifier/FixedVariant.php` |
| Overzicht onder Content | `view/adminhtml/ui_component/netwrk_fixed_variant_listing.xml` |
| Productpagina | `Block/Product/View/Selector.php`, `selector.phtml`, `web/js/fixed-variant.js` (Luma / RequireJS) |

Buy request: `fixed_variant[<group_id>][<product_id>] = aantal`, met `qty` als het aantal sets.

Getest op Magento Open Source 2.4.8-p5 (PHP 8.3, Luma): admin-formulier en opslaan, overzicht, configuratie,
productpagina (teller, extra-optie, validatie, mobiel), winkelwagen, order plaatsen, voorraadafboeking en prijsindex
(Update on Save en Update by Schedule).

Aandachtspunten:
* De frontend is gebouwd voor Luma en daarvan afgeleide thema's. Voor Hyvä is een aparte template/Alpine-variant nodig.
* Na het toevoegen kan de klant aantallen in de winkelwagen nog los aanpassen, net als bij een grouped product.

## License

[Netwrk Active Customer Use License](LICENSE) © Netwrk. Gebruiksrecht alleen voor actieve klanten van Netwrk.
