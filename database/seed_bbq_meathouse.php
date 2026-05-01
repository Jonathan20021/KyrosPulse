<?php
/**
 * Seeder de demo: BBQ MeatHouse.
 *
 * Activa modo restaurante en el tenant `kyros-demo` y carga el menu completo
 * (entradas, platos, bebidas, postres, etc.) tal como aparece en las fotos
 * del menu fisico.
 *
 * Uso:
 *   C:/xampp/php/php.exe database/seed_bbq_meathouse.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Env.php';
require_once __DIR__ . '/../app/Core/Config.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

// Solo cargar env/config si no fue inicializado por la aplicacion (CLI mode).
if (!class_exists('App\\Core\\Database', false) || App\Core\Config::get('database.connections.mysql') === null) {
    App\Core\Env::load(__DIR__ . '/../.env');
    App\Core\Config::setPath(__DIR__ . '/../config');
}

// Autoload PSR-4 manual (solo se registra una vez)
if (!function_exists('kyros_seed_autoload_registered')) {
    function kyros_seed_autoload_registered(): bool { return true; }
    spl_autoload_register(function (string $class): void {
        if (!str_starts_with($class, 'App\\')) return;
        $file = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) require_once $file;
    });
}

use App\Core\Database;

echo "\n=== Seeder BBQ MeatHouse (demo) ===\n\n";

// 1. Asegurar schema multichannel + restaurante (Schema::ensure es idempotente)
try {
    App\Core\Schema::ensure();
    echo "[+] Schema verificado.\n";
} catch (Throwable $e) {
    echo "[!] Aviso schema: " . $e->getMessage() . "\n";
}

// 2. Localizar tenant demo
$tenant = Database::fetch("SELECT * FROM tenants WHERE slug = :s LIMIT 1", ['s' => 'kyros-demo']);
if (!$tenant) {
    fwrite(STDERR, "[ERROR] No existe el tenant 'kyros-demo'. Corre install.php primero.\n");
    exit(1);
}
$tenantId = (int) $tenant['id'];
echo "[+] Tenant demo encontrado (#$tenantId).\n";

// 3. Activar modo restaurante + settings
$settings = [
    'tax_rate'          => 18.0,
    'tip_default'       => 10.0,
    'min_order'         => 500.0,
    'currency'          => 'DOP',
    'allow_delivery'    => 1,
    'allow_pickup'      => 1,
    'allow_dine_in'     => 1,
    'payment_methods'   => ['cash', 'card', 'transfer', 'online'],
    'order_prep_min'    => 35,
    'auto_accept'       => 0,
    'show_calories'     => 0,
    'whatsapp_menu_pdf' => null,
    'address'           => 'Av. Winston Churchill 53, Santo Domingo',
    'cuisine_type'      => 'BBQ · Parrilla · Cervezas artesanales',
];
Database::update('tenants', [
    'name'                => 'BBQ MeatHouse',
    'legal_name'          => 'BBQ MeatHouse SRL',
    'industry'            => 'Restaurante',
    'is_restaurant'       => 1,
    'restaurant_settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
    'currency'            => 'DOP',
    'country'             => 'DO',
    'timezone'            => 'America/Santo_Domingo',
    'language'            => 'es',
    'welcome_message'     => '¡Hola! Bienvenido a BBQ MeatHouse 🥩. Te puedo enviar nuestro menu completo, recibir tu pedido o reservar mesa. ¿Que se te antoja hoy?',
    'ai_assistant_name'   => 'Maitre BBQ',
    'ai_tone'             => 'cercano, gourmet, experto en parrilla y orientado a vender',
    'ai_enabled'          => 1,
], ['id' => $tenantId]);
echo "[+] Tenant actualizado a 'BBQ MeatHouse' con modo restaurante activo.\n";

// 4. Limpiar menu y zonas previos (este seeder es destructivo solo del menu demo)
Database::run("DELETE FROM menu_items WHERE tenant_id = :t", ['t' => $tenantId]);
Database::run("DELETE FROM menu_categories WHERE tenant_id = :t", ['t' => $tenantId]);
Database::run("DELETE FROM delivery_zones WHERE tenant_id = :t", ['t' => $tenantId]);
echo "[+] Menu y zonas previas limpiadas.\n";

// 5. Categorias (orden y emojis)
$categories = [
    ['Entradas',       '🥟', 1],
    ['Pollo',          '🍗', 2],
    ['Res',            '🥩', 3],
    ['Cerdo',          '🐷', 4],
    ['Entre panes',    '🍔', 5],
    ['Del mar',        '🐟', 6],
    ['Guarniciones',   '🍟', 7],
    ['Platones',       '🍽',  8],
    ['Menu de ninos',  '👶', 9],
    ['Salsas',         '🥫', 10],
    ['Refrescos',      '🥤', 20],
    ['Cervezas',       '🍺', 21],
    ['Cocteles',       '🍹', 22],
    ['Ron',            '🍾', 23],
    ['Vodka',          '🍸', 24],
    ['Whisky',         '🥃', 25],
    ['Tequila',        '🌵', 26],
    ['Ginebra',        '🍷', 27],
    ['Vino tinto',     '🍷', 28],
    ['Vino blanco',    '🥂', 29],
    ['Cafe',           '☕', 30],
    ['Otras bebidas',  '💧', 31],
];

$catIds = [];
foreach ($categories as [$name, $icon, $order]) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $id = Database::insert('menu_categories', [
        'tenant_id'  => $tenantId,
        'name'       => $name,
        'slug'       => $slug,
        'icon'       => $icon,
        'sort_order' => $order,
        'is_active'  => 1,
    ]);
    $catIds[$name] = $id;
}
echo "[+] " . count($catIds) . " categorias creadas.\n";

// 6. Items
$items = [
    // ====== ENTRADAS ======
    ['Entradas', 'Crema de cepa de apio',          325, 'ENT-001', 'Crema cremosa de cepa de apio. Sirve con pan, casabe o tostones.', null],
    ['Entradas', 'Crema de auyama',                325, 'ENT-002', 'Crema de auyama tradicional. Sirve con pan, casabe o tostones.', null],
    ['Entradas', 'Dos empanadas de cerdo',         325, 'ENT-003', 'Dos empanadas rellenas de cerdo desmechado.', null],
    ['Entradas', 'Salchichas naturales',           425, 'ENT-004', 'Salchichas artesanales naturales asadas a la parrilla.', null],
    ['Entradas', 'Salchichas picantes',            425, 'ENT-005', 'Salchichas con toque picante.', null],
    ['Entradas', 'Carne salada',                   425, 'ENT-006', 'Carne salada estilo dominicano.', null],
    ['Entradas', 'Longaniza',                      425, 'ENT-007', 'Longaniza criolla a la parrilla.', null],
    ['Entradas', 'Dos empanadas de picana',        425, 'ENT-008', 'Dos empanadas rellenas de picana.', null],
    ['Entradas', 'Chicharron 8oz',                 475, 'ENT-009', 'Chicharron crujiente 8oz.', null],
    ['Entradas', 'Alitas a la parrilla',           475, 'ENT-010', 'Alitas marinadas y asadas a la parrilla.', null],
    ['Entradas', 'Morcilla Burgalesa',             595, 'ENT-011', 'Morcilla estilo Burgos a la parrilla.', null],

    // ====== POLLO ======
    ['Pollo', 'Ensalada caesar con pollo',         495, 'POL-001', 'Ensalada caesar clasica con pollo a la plancha.', 12],
    ['Pollo', 'Ensalada caesar grillada con pollo',545, 'POL-002', 'Ensalada caesar con lechuga grillada y pollo.', 12],
    ['Pollo', 'Pinchos de pollo',                  575, 'POL-003', 'Pinchos de pollo marinados al BBQ.', 18],
    ['Pollo', 'Pechuga de pollo',                  595, 'POL-004', 'Pechuga de pollo a la parrilla.', 18],

    // ====== RES ======
    ['Res', 'Pinchos de churrasco con salsa teriyaki', 1395, 'RES-001', 'Pinchos de churrasco glaseados con salsa teriyaki. Corte importado.', 22],
    ['Res', 'Tenderloin 10 oz',                    1425, 'RES-002', 'Tenderloin 10 oz. Corte importado.', 22],
    ['Res', 'Como anoche de res',                  1425, 'RES-003', 'Cocido lento al estilo de la casa. Corte importado.', 35],
    ['Res', 'Vacio 10 oz',                         1495, 'RES-004', 'Vacio 10 oz a termino solicitado. Corte importado.', 22],
    ['Res', 'Churrasco 8 oz',                      1525, 'RES-005', 'Churrasco 8 oz a la parrilla. Corte importado.', 22],
    ['Res', 'Picana 16 oz',                        1695, 'RES-006', 'Picana 16 oz a la parrilla. Corte importado.', 25],
    ['Res', 'Ribeye 16-18 oz',                     2295, 'RES-007', 'Ribeye 16-18 oz importado, marmoleado premium.', 28],

    // ====== CERDO ======
    ['Cerdo', 'Chuletas de cerdo',                 495, 'CER-001', 'Chuletas marinadas a la parrilla.', 18],
    ['Cerdo', 'Bife aplatao de cerdo',             495, 'CER-002', 'Bife de cerdo aplatado a la plancha.', 16],
    ['Cerdo', 'Alitas de cerdo',                   595, 'CER-003', 'Alitas de cerdo glaseadas.', 22],
    ['Cerdo', 'Filete de cerdo',                   595, 'CER-004', 'Filete de cerdo importado.', 20],
    ['Cerdo', 'Costilla St. Louise',               995, 'CER-005', 'Costilla St. Louise estilo americano.', 30],
    ['Cerdo', 'Baby back ribs',                   1295, 'CER-006', 'Baby back ribs glaseadas, importadas.', 30],

    // ====== ENTRE PANES ======
    ['Entre panes', 'Fried chicken sandwich',      495, 'PAN-001', 'Pollo crujiente, lechuga, mayo casa. Acompana con papas fritas.', 12],
    ['Entre panes', 'Pulled pork',                 495, 'PAN-002', 'Pan brioche con pulled pork BBQ. Acompana con papas fritas.', 10],
    ['Entre panes', 'Choripan',                    525, 'PAN-003', 'Choripan argentino con chimichurri. Acompana con papas fritas.', 10],
    ['Entre panes', 'Smash Burger',                625, 'PAN-004', 'Smash burger doble carne, queso americano. Acompana con papas fritas.', 12],
    ['Entre panes', 'Spicy smash',                 625, 'PAN-005', 'Smash con jalapenos y mayo picante. Acompana con papas fritas.', 12],
    ['Entre panes', 'Smash pulled pork',           625, 'PAN-006', 'Smash + pulled pork BBQ. Acompana con papas fritas.', 12],
    ['Entre panes', 'Smash Argentina',             625, 'PAN-007', 'Smash con chimichurri y queso provoleta. Acompana con papas fritas.', 12],
    ['Entre panes', 'Cheeseburger angus 8 oz',     675, 'PAN-008', 'Hamburguesa angus 8 oz, importada. Acompana con papas fritas.', 14],

    // ====== DEL MAR ======
    ['Del mar', 'Salmon a la parrilla',            995, 'MAR-001', 'Salmon a la parrilla con vegetales. Importado.', 18],

    // ====== GUARNICIONES ======
    ['Guarniciones', 'Tostones',                   115, 'GRN-001', 'Tostones crujientes.', 8],
    ['Guarniciones', 'Casabe',                     125, 'GRN-002', 'Casabe tostado.', 5],
    ['Guarniciones', 'Pan con ajo',                125, 'GRN-003', 'Pan con ajo y mantequilla.', 6],
    ['Guarniciones', 'Ensalada verde',             135, 'GRN-004', 'Mix de hojas verdes con vinagreta.', 5],
    ['Guarniciones', 'Papas fritas',               145, 'GRN-005', 'Papas fritas crujientes.', 8],
    ['Guarniciones', 'Papas wedge',                145, 'GRN-006', 'Papas wedge sazonadas.', 10],
    ['Guarniciones', 'Palitos de yuca',            145, 'GRN-007', 'Palitos de yuca frita.', 10],
    ['Guarniciones', 'Tostones chips',             145, 'GRN-008', 'Tostones tipo chips.', 8],
    ['Guarniciones', 'Pure de yuca',               165, 'GRN-009', 'Pure cremoso de yuca.', 8],
    ['Guarniciones', 'Maiz',                       165, 'GRN-010', 'Maiz mantequilla a la parrilla.', 8],
    ['Guarniciones', 'Vegetales al grill',         195, 'GRN-011', 'Vegetales mixtos a la parrilla.', 12],
    ['Guarniciones', 'Pure de cepa de apio',       195, 'GRN-012', 'Pure de cepa de apio.', 8],
    ['Guarniciones', 'Mac N Cheese',               245, 'GRN-013', 'Macarrones con queso al horno.', 12],
    ['Guarniciones', 'Moro fondue',                295, 'GRN-014', 'Moro de habichuelas con fondue de queso.', 15],

    // ====== PLATONES ======
    ['Platones', 'Platon de Frituras',            1295, 'PLT-001', 'Carne salada, longaniza, chicharron, casabe y tostones.', 30],
    ['Platones', 'Platon para Dos',               1795, 'PLT-002', 'Pulled pork, alitas de cerdo, salmon, pan con ajo, tostones y casabe.', 35],
    ['Platones', 'De Aqui (4 personas)',          3195, 'PLT-003', 'Alitas de pollo, salchichas, pechuga de pollo, baby back ribs (importado), filete de cerdo, tostones, casabe, maiz y vegetales.', 45],
    ['Platones', 'Para Todos (4 personas)',       4095, 'PLT-004', 'Filete de cerdo (importado), churrasco/vacio (importado), baby back ribs (importado), salchichas, pechuga de pollo, tostones, casabe, maiz y vegetales.', 50],

    // ====== MENU DE NINOS ======
    ['Menu de ninos', 'Mac N Cheese (ninos)',      245, 'NIN-001', 'Mac N Cheese porcion para ninos. Acompana con papitas fritas.', 12],
    ['Menu de ninos', 'Deditos de pollo',          365, 'NIN-002', 'Deditos de pollo crujientes. Acompana con papitas fritas.', 12],
    ['Menu de ninos', 'Smash ninos',               395, 'NIN-003', 'Smash burger porcion ninos. Acompana con papitas fritas.', 12],

    // ====== SALSAS (gratis) ======
    ['Salsas', 'BBQ chipotle',                       0, 'SLS-001', 'Salsa BBQ con toque chipotle. Cortesia de la casa.', null],
    ['Salsas', 'BBQ honey',                          0, 'SLS-002', 'Salsa BBQ con miel. Cortesia de la casa.', null],
    ['Salsas', 'Mantequilla ajo y perejil',          0, 'SLS-003', 'Mantequilla con ajo y perejil. Cortesia de la casa.', null],
    ['Salsas', 'Blue cheese',                        0, 'SLS-004', 'Salsa cremosa de queso azul. Cortesia de la casa.', null],
    ['Salsas', 'Honey mustard',                      0, 'SLS-005', 'Mostaza con miel. Cortesia de la casa.', null],
    ['Salsas', 'Mayonesa sriracha',                  0, 'SLS-006', 'Mayonesa con sriracha. Cortesia de la casa.', null],
    ['Salsas', 'Bufalo',                             0, 'SLS-007', 'Salsa bufalo picante. Cortesia de la casa.', null],
    ['Salsas', 'Chimichurri',                        0, 'SLS-008', 'Chimichurri argentino. Cortesia de la casa.', null],

    // ====== REFRESCOS ======
    ['Refrescos', 'Sprite 12 oz',                    90, 'BEB-001', 'Sprite 12 oz.', null],
    ['Refrescos', 'Refresco rojo 12 oz',             90, 'BEB-002', 'Refresco rojo nacional 12 oz.', null],
    ['Refrescos', 'Merengue 12 oz',                  90, 'BEB-003', 'Refresco Merengue 12 oz.', null],
    ['Refrescos', 'Uva 12 oz',                       90, 'BEB-004', 'Refresco de uva 12 oz.', null],
    ['Refrescos', 'Naranja 12 oz',                   90, 'BEB-005', 'Refresco de naranja 12 oz.', null],
    ['Refrescos', 'Coca Cola 16 oz',                110, 'BEB-006', 'Coca Cola 16 oz.', null],
    ['Refrescos', 'Coca Cola Zero 16 oz',           110, 'BEB-007', 'Coca Cola Zero 16 oz.', null],

    // ====== OTRAS BEBIDAS ======
    ['Otras bebidas', 'Botella de agua',             65, 'BEB-010', 'Botella de agua mineral.', null],
    ['Otras bebidas', 'Agua tonica',                110, 'BEB-011', 'Agua tonica.', null],
    ['Otras bebidas', 'Soda amarga',                110, 'BEB-012', 'Soda amarga.', null],
    ['Otras bebidas', 'Clamato',                    110, 'BEB-013', 'Clamato.', null],
    ['Otras bebidas', 'San Pellegrino',             160, 'BEB-014', 'Agua mineral San Pellegrino.', null],
    ['Otras bebidas', 'Agua Perrier',               160, 'BEB-015', 'Agua mineral Perrier.', null],
    ['Otras bebidas', 'Jugos naturales',            165, 'BEB-016', 'Jugos naturales del dia.', null],

    // ====== CAFE ======
    ['Cafe', 'Cortadito',                            85, 'CAF-001', 'Espresso cortado.', null],
    ['Cafe', 'Espresso',                             85, 'CAF-002', 'Espresso simple.', null],
    ['Cafe', 'Cafe dominicano',                      85, 'CAF-003', 'Cafe dominicano clasico.', null],
    ['Cafe', 'Cafe Americano',                       85, 'CAF-004', 'Cafe americano largo.', null],
    ['Cafe', 'Cafe con leche',                       85, 'CAF-005', 'Cafe con leche caliente.', null],
    ['Cafe', 'Capuccino',                           100, 'CAF-006', 'Capuccino con leche espumada.', null],
    ['Cafe', 'Frappe de Caramelo',                  195, 'CAF-007', 'Frappe helado de caramelo.', null],
    ['Cafe', 'Frappe de Chocolate',                 195, 'CAF-008', 'Frappe helado de chocolate.', null],

    // ====== VINO BLANCO ======
    ['Vino blanco', 'Santa Rita (botella)',        1295, 'VBL-001', 'Vino blanco Santa Rita - botella.', null],
    ['Vino blanco', 'Santa Rita (copa)',            255, 'VBL-002', 'Vino blanco Santa Rita - copa.', null],
    ['Vino blanco', 'Leira Albarino (botella)',    2095, 'VBL-003', 'Vino blanco Leira Albarino - botella.', null],
    ['Vino blanco', 'Santa Margherita Pinot Grigio (botella)', 2895, 'VBL-004', 'Pinot Grigio Santa Margherita - botella.', null],

    // ====== VINO TINTO ======
    ['Vino tinto', 'Calvet Merlot (botella)',       995, 'VTI-001', 'Calvet Merlot - botella.', null],
    ['Vino tinto', 'Calvet Merlot (copa)',          215, 'VTI-002', 'Calvet Merlot - copa.', null],
    ['Vino tinto', 'Frontera Cabernet Sauvignon (botella)',  995, 'VTI-003', 'Frontera Cabernet Sauvignon - botella.', null],
    ['Vino tinto', 'Primal Roots Red Blend (botella)',      1545, 'VTI-004', 'Primal Roots Red Blend - botella.', null],
    ['Vino tinto', 'Palo Alto Reserves (botella)',          1545, 'VTI-005', 'Palo Alto Reserves - botella.', null],
    ['Vino tinto', 'Yellow Tail Shiraz (botella)',          1545, 'VTI-006', 'Yellow Tail Shiraz - botella.', null],
    ['Vino tinto', '19 Crimes (botella)',                   1895, 'VTI-007', '19 Crimes - botella.', null],
    ['Vino tinto', 'Apothic Red Wine (botella)',            2495, 'VTI-008', 'Apothic Red Wine - botella.', null],
    ['Vino tinto', 'Apothic Red Wine (copa)',                395, 'VTI-009', 'Apothic Red Wine - copa.', null],
    ['Vino tinto', 'Robert Mondavi PS Cabernet Sauvignon (botella)', 2495, 'VTI-010', 'Robert Mondavi PS Cabernet Sauvignon - botella.', null],
    ['Vino tinto', 'Boogle Essential Red California (botella)',     2495, 'VTI-011', 'Boogle Essential Red California - botella.', null],
    ['Vino tinto', 'Napa Valley 689 (botella)',                     2495, 'VTI-012', 'Napa Valley 689 - botella.', null],

    // ====== RON ======
    ['Ron', 'Barcelo Gran Anejo',                   255, 'RON-001', 'Trago de Barcelo Gran Anejo.', null],
    ['Ron', 'Brugal Extra Viejo',                   255, 'RON-002', 'Trago de Brugal Extra Viejo.', null],
    ['Ron', 'Brugal XV',                            275, 'RON-003', 'Trago de Brugal XV.', null],
    ['Ron', 'Bermudez Don Armando',                 295, 'RON-004', 'Trago de Bermudez Don Armando.', null],
    ['Ron', 'Brugal Doble Reserva',                 325, 'RON-005', 'Trago de Brugal Doble Reserva.', null],
    ['Ron', 'Barcelo Imperial',                     345, 'RON-006', 'Trago de Barcelo Imperial.', null],
    ['Ron', 'Siboney Gran Reserva',                 345, 'RON-007', 'Trago de Siboney Gran Reserva.', null],
    ['Ron', 'Brugal Leyenda',                       425, 'RON-008', 'Trago de Brugal Leyenda.', null],
    ['Ron', 'Brugal 1888',                          595, 'RON-009', 'Trago de Brugal 1888.', null],

    // ====== VODKA ======
    ['Vodka', 'Eristoff',                           195, 'VOD-001', 'Trago de Vodka Eristoff.', null],
    ['Vodka', 'Stolichnaya',                        255, 'VOD-002', 'Trago de Vodka Stolichnaya.', null],
    ['Vodka', 'Absolut',                            295, 'VOD-003', 'Trago de Vodka Absolut.', null],
    ['Vodka', 'Grey Goose',                         495, 'VOD-004', 'Trago de Vodka Grey Goose.', null],

    // ====== WHISKY ======
    ['Whisky', 'Dewars White Label',                195, 'WHI-001', 'Trago de Dewars White Label.', null],
    ['Whisky', 'Dewars 12 Anos',                    515, 'WHI-002', 'Trago de Dewars 12 Anos.', null],
    ['Whisky', 'Black Label',                       515, 'WHI-003', 'Trago de Johnnie Walker Black Label.', null],
    ['Whisky', 'Chivas Regal 12 Anos',              515, 'WHI-004', 'Trago de Chivas Regal 12 Anos.', null],
    ['Whisky', 'Buchanan\'s 12 Anos',               595, 'WHI-005', 'Trago de Buchanan\'s 12 Anos.', null],
    ['Whisky', 'Dewars 15 Anos',                    625, 'WHI-006', 'Trago de Dewars 15 Anos.', null],
    ['Whisky', 'Doble Black',                       695, 'WHI-007', 'Trago de Johnnie Walker Doble Black.', null],
    ['Whisky', 'Dewars 18 Anos',                    845, 'WHI-008', 'Trago de Dewars 18 Anos.', null],
    ['Whisky', 'Chivas Regal 18 Anos',              895, 'WHI-009', 'Trago de Chivas Regal 18 Anos.', null],

    // ====== GINEBRA ======
    ['Ginebra', 'Bermudez',                         225, 'GIN-001', 'Trago de Ginebra Bermudez.', null],
    ['Ginebra', 'Bombay',                           445, 'GIN-002', 'Trago de Ginebra Bombay Sapphire.', null],

    // ====== TEQUILA ======
    ['Tequila', 'El Jimador',                       295, 'TEQ-001', 'Trago de Tequila El Jimador.', null],
    ['Tequila', 'Patron Silver',                    645, 'TEQ-002', 'Trago de Tequila Patron Silver.', null],
    ['Tequila', 'Patron Reposado',                  665, 'TEQ-003', 'Trago de Tequila Patron Reposado.', null],
    ['Tequila', 'Patron Anejo',                     695, 'TEQ-004', 'Trago de Tequila Patron Anejo.', null],
    ['Tequila', 'Patron Cristalino',                725, 'TEQ-005', 'Trago de Tequila Patron Cristalino.', null],

    // ====== CERVEZAS ======
    ['Cervezas', 'Presidente',                      195, 'CRV-001', 'Cerveza Presidente.', null],
    ['Cervezas', 'Presidente Light',                195, 'CRV-002', 'Cerveza Presidente Light.', null],
    ['Cervezas', 'Coors Light',                     195, 'CRV-003', 'Cerveza Coors Light.', null],
    ['Cervezas', 'Corona Cero',                     205, 'CRV-004', 'Cerveza Corona Cero (sin alcohol).', null],
    ['Cervezas', 'Michelob Ultra',                  215, 'CRV-005', 'Cerveza Michelob Ultra.', null],
    ['Cervezas', 'Coors Banquet',                   225, 'CRV-006', 'Cerveza Coors Banquet.', null],
    ['Cervezas', 'Heineken',                        225, 'CRV-007', 'Cerveza Heineken.', null],
    ['Cervezas', 'Corona',                          235, 'CRV-008', 'Cerveza Corona.', null],
    ['Cervezas', 'Stella Artois',                   235, 'CRV-009', 'Cerveza Stella Artois.', null],
    ['Cervezas', 'Modelo Rubia',                    245, 'CRV-010', 'Cerveza Modelo Rubia.', null],
    ['Cervezas', 'Modelo Negra',                    245, 'CRV-011', 'Cerveza Modelo Negra.', null],
    ['Cervezas', 'Peroni',                          255, 'CRV-012', 'Cerveza Peroni.', null],
    ['Cervezas', 'BBM',                             285, 'CRV-013', 'Cerveza BBM artesanal.', null],
    ['Cervezas', 'Paulaner Rubia',                  365, 'CRV-014', 'Cerveza Paulaner Rubia (Alemana).', null],
    ['Cervezas', 'Paulaner Negra',                  365, 'CRV-015', 'Cerveza Paulaner Negra (Alemana).', null],

    // ====== COCTELES ======
    ['Cocteles', 'Apple Martini',                   250, 'COC-001', 'Coctel Apple Martini.', null],
    ['Cocteles', 'Sangria Roja',                    250, 'COC-002', 'Sangria con vino tinto y frutas.', null],
    ['Cocteles', 'Sangria Rosada',                  250, 'COC-003', 'Sangria rosada con frutas frescas.', null],
    ['Cocteles', 'Sex On The Beach',                250, 'COC-004', 'Coctel Sex On The Beach.', null],
    ['Cocteles', 'Michelada',                       325, 'COC-005', 'Cerveza preparada con limon, salsas y especias.', null],
    ['Cocteles', 'Mojito de Menta',                 325, 'COC-006', 'Mojito clasico de menta.', null],
    ['Cocteles', 'Mojito de Chinola',               325, 'COC-007', 'Mojito con chinola (maracuya).', null],
    ['Cocteles', 'Mojito de Fresa',                 325, 'COC-008', 'Mojito con fresa fresca.', null],
    ['Cocteles', 'Mojito de Coco',                  325, 'COC-009', 'Mojito con coco.', null],
    ['Cocteles', 'Margarita Clasica',               325, 'COC-010', 'Margarita clasica con tequila.', null],
    ['Cocteles', 'Margarita Blue',                  325, 'COC-011', 'Margarita con curacao azul.', null],
    ['Cocteles', 'Margarita de Chinola',            325, 'COC-012', 'Margarita con chinola.', null],
    ['Cocteles', 'Margarita de Fresa',              325, 'COC-013', 'Margarita con fresa.', null],
    ['Cocteles', 'Negroni',                         325, 'COC-014', 'Coctel Negroni clasico.', null],
    ['Cocteles', 'Pina Colada',                     325, 'COC-015', 'Pina Colada cremosa.', null],
    ['Cocteles', 'Cosmopolitan',                    325, 'COC-016', 'Coctel Cosmopolitan.', null],
    ['Cocteles', 'Aperol Spritz',                   345, 'COC-017', 'Coctel Aperol Spritz italiano.', null],
    ['Cocteles', 'Bloody Cesar',                    345, 'COC-018', 'Bloody Cesar (variante de Bloody Mary con clamato).', null],
    ['Cocteles', 'Margarita con Jose Cuervo y Cointreau', 395, 'COC-019', 'Margarita premium con Jose Cuervo y Cointreau.', null],
    ['Cocteles', 'Long Island',                     425, 'COC-020', 'Coctel Long Island Iced Tea.', null],
];

$inserted = 0;
$now = date('Y-m-d H:i:s');
foreach ($items as $idx => [$catName, $name, $price, $sku, $desc, $prepMin]) {
    $catId = $catIds[$catName] ?? null;
    if (!$catId) {
        echo "[!] Categoria desconocida para '$name': $catName\n";
        continue;
    }
    Database::insert('menu_items', [
        'tenant_id'    => $tenantId,
        'category_id'  => $catId,
        'sku'          => $sku,
        'name'         => $name,
        'description'  => $desc,
        'price'        => $price,
        'currency'     => 'DOP',
        'is_available' => 1,
        'is_featured'  => $price >= 1500 && $price <= 3000 ? 1 : 0,
        'is_combo'     => $catName === 'Platones' ? 1 : 0,
        'prep_time_min' => $prepMin,
        'sort_order'   => $idx,
    ]);
    $inserted++;
}
echo "[+] $inserted items insertados.\n";

// 7. Marcar destacados (los mas pedidos)
$featuredSkus = ['PAN-004', 'RES-005', 'CER-006', 'POL-003', 'PLT-002', 'COC-006', 'CRV-001'];
foreach ($featuredSkus as $sku) {
    Database::run(
        "UPDATE menu_items SET is_featured = 1 WHERE tenant_id = :t AND sku = :s",
        ['t' => $tenantId, 's' => $sku]
    );
}
echo "[+] Items destacados marcados.\n";

// 8. Zonas de delivery (Santo Domingo)
$zones = [
    ['Naco',             150, 25, 600],
    ['Piantini',         150, 25, 600],
    ['Bella Vista',      180, 30, 600],
    ['Gazcue',           200, 35, 600],
    ['Los Cacicazgos',   200, 30, 700],
    ['La Esperilla',     200, 35, 600],
    ['Mirador Sur',      220, 35, 700],
    ['Arroyo Hondo',     280, 45, 800],
    ['Los Rios',         300, 50, 800],
    ['Distrito Nacional - Otros', 350, 55, 900],
];
foreach ($zones as [$name, $fee, $eta, $min]) {
    Database::insert('delivery_zones', [
        'tenant_id' => $tenantId,
        'name'      => $name,
        'fee'       => $fee,
        'eta_min'   => $eta,
        'min_order' => $min,
        'is_active' => 1,
    ]);
}
echo "[+] " . count($zones) . " zonas de delivery creadas.\n";

// 9. Base de conocimiento del restaurante (info para la IA)
Database::run("DELETE FROM knowledge_base WHERE tenant_id = :t AND category IN ('horario','politica','restaurante')", ['t' => $tenantId]);
$kb = [
    ['restaurante', 'Sobre BBQ MeatHouse',
        'BBQ MeatHouse es una parrilla y steakhouse en Av. Winston Churchill 53, Santo Domingo. Especializados en cortes premium importados, pulled pork, smash burgers, ribs y cocteleria de autor. Atendemos delivery, pickup y mesa en local.'],
    ['horario', 'Horario de atencion',
        'Lunes a jueves: 12:00 PM a 11:00 PM. Viernes y sabado: 12:00 PM a 1:00 AM. Domingo: 12:00 PM a 10:00 PM. Cocina cierra 30 minutos antes.'],
    ['politica', 'Politicas',
        'Pedido minimo para delivery: RD$500-900 segun zona. Tiempo de preparacion promedio: 35 minutos. Acompanamientos cortesia (salsas) incluidos. Cortes marcados (l) son importados. Impuestos NO incluidos en los precios; se anaden al cobrar (ITBIS 18%).'],
    ['politica', 'Metodos de pago',
        'Aceptamos efectivo, tarjeta de credito/debito (Visa, Mastercard, Amex), transferencia bancaria y pago online via Stripe. Para tarjetas hay POS al momento de la entrega.'],
    ['horario', 'Reservaciones',
        'Reservaciones disponibles para grupos de 4+ personas. Se requiere confirmacion 24h antes. Para grupos de 10+ contactar para menu corporativo.'],
];
foreach ($kb as $idx => [$cat, $title, $content]) {
    Database::insert('knowledge_base', [
        'tenant_id'  => $tenantId,
        'category'   => $cat,
        'title'      => $title,
        'content'    => $content,
        'is_active'  => 1,
        'sort_order' => $idx,
    ]);
}
echo "[+] " . count($kb) . " articulos de base de conocimiento agregados.\n";

// 10. Asegurar que el agente IA principal del tenant este en modo restaurante
try {
    Database::run(
        "UPDATE ai_agents SET
            category    = 'sales',
            tone        = 'cercano, gourmet, experto en parrilla y orientado a vender',
            objective   = 'Tomar pedidos del menu de BBQ MeatHouse, sugerir combos y guarniciones, calcular total y confirmar antes de emitir [ORDER:...]. Escalar a humano si el cliente reclama.',
            instructions = CONCAT(COALESCE(instructions, ''), '\\n\\nReglas BBQ MeatHouse:\\n- Saluda como Maitre de la casa.\\n- Sugiere proteinas premium (Tenderloin, Ribeye, Picana) y combos para grupos.\\n- Si piden delivery pregunta zona y aplica costo correcto.\\n- ITBIS 18% se anade al cobrar.\\n- Tiempo prep promedio: 35 min.\\n- Recomienda salsas (BBQ chipotle, chimichurri).')
         WHERE tenant_id = :t",
        ['t' => $tenantId]
    );
    echo "[+] Agentes IA actualizados al modo BBQ MeatHouse.\n";
} catch (Throwable $e) {
    echo "[!] Aviso agentes IA: " . $e->getMessage() . "\n";
}

// 11. Invalidar cache schema para forzar re-check
try {
    App\Core\Schema::invalidate();
} catch (Throwable) {}

echo "\n=== BBQ MeatHouse listo ===\n";
echo "Tenant:          BBQ MeatHouse (#$tenantId, slug=kyros-demo)\n";
echo "Login owner:     owner@kyrosrd.com / demo12345\n";
echo "Categorias:      " . count($catIds) . "\n";
echo "Items:           $inserted\n";
echo "Zonas delivery:  " . count($zones) . "\n";
echo "Modo restaurante: ON · ITBIS 18% · Moneda DOP\n";
echo "\nProxima accion:\n";
echo "  1. Login con owner@kyrosrd.com / demo12345\n";
echo "  2. Visita /menu para ver el menu cargado\n";
echo "  3. Visita /orders para ver el kanban\n";
echo "  4. Visita /settings/restaurant para ajustes\n\n";
