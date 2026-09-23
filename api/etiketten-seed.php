<?php
// Eenmalig: koppelt online gevonden flesfoto's aan de bestaande wijnen.
// Vult alleen wijnen waarbij nog geen foto staat, dus veilig om nogmaals te openen.
// Open na het live zetten één keer: https://wiertsema.net/wijnkelder/api/etiketten-seed.php
require __DIR__ . '/db.php';

const IMAGES = [
    2 => 'https://bilder.vinmonopolet.no/cache/1500x1500-0/8035801-1.jpg',
    3 => 'https://images.vivino.com/thumbs/UNa6a4m7RDO2Ue98M-N2vQ_375x500.jpg',
    4 => 'https://vinstyrke2.dk/wp-content/uploads/2022/01/Schloss-Bockfliess-Barock.jpg',
    5 => 'https://www.pedroescuderoviticultor.com/resources/catalogo/7a1d17eeeb73f4e057db2913448c6cbf11b0b353-th.jpg',
    6 => 'https://images.vivino.com/thumbs/gdabuq_4RlWrqEsdM1baCQ_375x500.jpg',
    7 => 'https://cdn.ct-static.com/labels/db04d7e0-5ba9-4c2d-b788-636a4f60d706.jpg',
    9 => 'https://sw-5a7a.kxcdn.com/media/52/b3/96/1715764183/2015488-nik-weis-sankt-urbans-hof-wiltinger-alte-reben-2018-etikett.jpg',
    11 => 'https://falstaff.b-cdn.net/core/948361/106996_2017-wuerzburger-stein-silvaner-trocken-vdperste-lage_948361.png',
    12 => 'https://cdn.ct-static.com/labels/136ce95a-7cd2-4ce7-93af-d957e4624d1d.jpg',
    13 => 'https://www.icheers.tw/fileserver/upload/WI00275401_btl.jpg',
    14 => 'https://www.saq.com/media/catalog/product/1/4/14700180-1_1624377342.png?optimize=high&fit=bounds&height=&width=&format=jpeg',
    15 => 'https://cdn.ct-static.com/labels/6675c5c2-6382-4031-af2f-474e84195915.jpg',
    16 => 'https://bottle-hero.de/cdn/shop/files/15052920.jpg?height=1080&v=1769090284&width=600',
    17 => 'https://www.vinorama.at/out/pictures/master/product/1/riesling_smaragd_hochrain_2024__1193424.png',
    18 => 'https://www.greatgrog.co.uk/wp-content/uploads/2018/09/IMG_4127-scaled.jpg',
    19 => 'https://www.buonvino.co.uk/app/uploads/Verdicchio-Kypra-Ca-Liptra-600x884.jpg',
    21 => 'https://images.squarespace-cdn.com/content/v1/601ec7e27930f3480b9f6509/57c6fc4a-5420-4377-8995-b09d5c5e0d1c/Guerriero+del+Mare+2019%2C+Guerrieri.jpg',
    22 => 'https://libergroup.it/wp-content/uploads/2024/03/camerte.png',
    23 => 'https://dsi2vjvztwiuk.cloudfront.net/website/products/108722/bottle/760245/original.png',
    24 => 'https://www.hetnieuwewijnhuys.nl/wp-content/uploads/2025/10/Viognier.jpg',
    26 => 'https://www.cellerssantrafel.com/sites/default/files/vins/galeria/220819-001.png',
    27 => 'https://www.lavernewines.co.za/wp-content/uploads/2018/12/Mont_Blois_Groot_Steen_Chenin_Blanc-510x765.png',
    28 => 'https://www.justincases.co.uk/image/cache/catalog/jaeger-defaix-rully-mont-palais-popup.jpg?v1764010963',
    29 => 'https://img.thewhiskyexchange.com/900/WINE_CIN2020.jpg',
    30 => 'https://static.wixstatic.com/media/0102a9_76423e454de549e3b0df7582e3726e90~mv2.jpg/v1/fill/w_600,h_800,al_c,q_85/0102a9_76423e454de549e3b0df7582e3726e90~mv2.jpg',
    31 => 'https://images.squarespace-cdn.com/content/v1/600a9bf5d3330e244f7ef1fc/1611308047185-0H1VNLD30FQA5Q3OZ2MI/IMG_4304.JPG',
    32 => 'https://www.domaine-la-louviere.com/images/wines/la-muse.jpg',
    33 => 'https://www.domainedeole.com/wp-content/uploads/2020/08/v23.jpg',
    34 => 'https://www.ferrowine.it/8472-large_default/v-ziggurat-montefalco-rosso-doc-cl-75-ten-castelbuono-umbria-2017.jpg',
    35 => 'https://static.wineaccess.com/media/optimized/images/products/2022/05/2017_Domaine_de_Baronarques_Chardonnay_Limoux_Languedoc-Roussillon_Bottleshot/a14af687a5b05dc9099a03baeefba3a9.png',
    36 => 'https://caviste-lehavre.fr/wp-content/uploads/bordeaux-rouge-lalande-de-pomerol-chateau-la-foret-2020-75cl--scaled.jpg',
    37 => 'https://media.nicks.com.au/products/ee301ce1/colle-corviano-montepulciano-dabruzzo.jpg',
    38 => 'https://www.avenuedesvins.fr/19560-large_default/coteaux-bourguignons.jpg',
    39 => 'https://cdn11.bigcommerce.com/s-26lti00957/images/stencil/1280x1280/products/1709/3459/Screenshot-2023-03-09-at-15-09-23__54619.1678374691.png?c=1',
    40 => 'https://www.bodegaselpilar.com/wp-content/uploads/2025/11/309-2-600x900.jpg',
    41 => 'https://vlassideswinery.com/wp-content/uploads/2019/05/sodeia_Shiraz_2015.jpg',
    42 => 'https://euroweinkontor.de/media/thumbs/e8/74/a2a25cb34bc9149eb660c9e1e6e186e98c38/1400x_crop=center/managed/5b/a9/5ba9e9d68340f5d02928c712167791f5/Domaine-Eole_Wein_Cuvee-Lea_Rouge_Provence_ohne-Jahrgang.jpg.thumb.jpg',
    43 => 'https://falstaff.b-cdn.net/core/989391/106389_malavoglia-valpolicella-ripasso-classico-superiore-doc-2015_989391.png?height=1080',
    44 => 'https://cdn.ct-static.com/labels/d4b6f29a-be45-4e03-bde9-72d36be9c352.jpg',
    45 => 'https://images.vivino.com/thumbs/zu84BUugSa-GjonJT0lHBg_pb_x600.png',
    47 => 'https://www.labotegadicarilius.it/uploads/MediaGalleryVarianti/valpolicella-classico-sup-carilius.jpg',
];

$stmt = $pdo->prepare("UPDATE wines SET image = ? WHERE id = ? AND (image IS NULL OR image = '')");
$n = 0;
foreach (IMAGES as $id => $url) {
    $stmt->execute([$url, $id]);
    $n += $stmt->rowCount();
}
echo json_encode(['bijgewerkt' => $n, 'totaal' => count(IMAGES)]);
