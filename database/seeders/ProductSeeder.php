<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds the real product master catalog.
     * Skipped all rows containing "selection" or "Wharehouse" per rules.
     */
    public function run(): void
    {
        $products = [
            // Supply
            ['category' => 'Supply', 'name' => 'Tomato H', 'sku' => 'TOMATOH-001', 'unit' => 'kg'],
            ['category' => 'Supply', 'name' => 'Tomato N', 'sku' => 'TOMATON-002', 'unit' => 'kg'],

            // VEG
            ['category' => 'VEG', 'name' => 'French Beans', 'sku' => 'FRENCHBEANS-013', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Beans Natty', 'sku' => 'BEANSNATTY-014', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Ladies Finger', 'sku' => 'LADIESFINGER-015', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Green Peas', 'sku' => 'GREENPEAS-016', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'White Karamani', 'sku' => 'WHITEKARAMANI-019', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Green Karamani', 'sku' => 'GREENKARAMANI-020', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Avarakkai/lablab Beans', 'sku' => 'AVARAKKAI-021', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Bajji Chilli', 'sku' => 'BAJJICHILLI-025', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Red Chilli', 'sku' => 'REDCHILLI-026', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Brinjal White Long', 'sku' => 'BRINJALWHTLNG-027', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Brinjal Big (Bertha)', 'sku' => 'BRINJALBIG-028', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Brinjal Small', 'sku' => 'BRINJALSMALL-029', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Brinjal White Kolkata', 'sku' => 'BRINJALWHTKOL-030', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Brinjol Rampuri', 'sku' => 'BRINJOLRAMPURI-031', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Brinjal Blue Long', 'sku' => 'BRINJALBLULNG-032', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Capsicum', 'sku' => 'CAPSICUM-033', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Radish', 'sku' => 'RADISH-037', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Tinda (Round Gurad)', 'sku' => 'TINDA-038', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Bitter Gourd White', 'sku' => 'BITTERGOURDWHT-041', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Bottle Gourd (Loki)', 'sku' => 'BOTTLEGOURDLOKI-042', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Ridge Gourd', 'sku' => 'RIDGEGOURD-043', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Snake Gourd White', 'sku' => 'SNAKEGOURDWHT-045', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Snake Gourd Green', 'sku' => 'SNAKEGOURDGRN-046', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Raw Jack Fruit', 'sku' => 'RAWJACKFRUIT-047', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Banana Flower', 'sku' => 'BANANAFLOWER-048', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Banana Stem', 'sku' => 'BANANASTEM-049', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Raw Banana', 'sku' => 'RAWBANANA-050', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Banana Leaf', 'sku' => 'BANANALEAF-051', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Sambar Cucbr / Southikai', 'sku' => 'SAMBARCUCBR-052', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'English Cucumber', 'sku' => 'ENGCUCUMBER-054', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Yam', 'sku' => 'YAM-055', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Pickle Mango', 'sku' => 'PICKLEMANGO-056', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Raw Mango Gund', 'sku' => 'RAWMANGOGUND-057', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Raw Mango totapuri', 'sku' => 'RAWMANGOTOTA-058', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Cabbage', 'sku' => 'CABBAGE-061', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Arbi /Taroroot', 'sku' => 'ARBITARO-062', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Big Arbi', 'sku' => 'BIGARBI-063', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Koorka Chinees Potato', 'sku' => 'KOORKAPOTATO-064', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Amla', 'sku' => 'AMLA-065', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Peeled Green Peas', 'sku' => 'PEELEDPEAS-066', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Delhi / Red Carrot', 'sku' => 'DELHICARROT-070', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Sweet Potato', 'sku' => 'SWEETPOTATO-073', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Tapioca', 'sku' => 'TAPIOCA-074', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Ginger', 'sku' => 'GINGER-075', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Ground Nut', 'sku' => 'GROUNDNUT-076', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Raw Pappaya', 'sku' => 'RAWPAPPAYA-079', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'SP Natti Thondakai', 'sku' => 'SPNATTITHONDAKAI-080', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Armenian Cucumber / Kakdi', 'sku' => 'ARMENIANCUCUMBER-081', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Disco Pumkin', 'sku' => 'DISCOPUMKIN-082', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'White Pumkin', 'sku' => 'WHITEPUMKIN-083', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Yellow Pumkin', 'sku' => 'YELLOWPUMKIN-084', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Natti Pumkin', 'sku' => 'NATTIPUMKIN-085', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Turnip / Shalgam', 'sku' => 'TURNIP-086', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Mango Ginger', 'sku' => 'MANGOGINGER-087', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Red Radish', 'sku' => 'REDRADISH-088', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Turmeric Stick Fresh', 'sku' => 'TURMERICSTICK-089', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Flower K', 'sku' => 'FLOWERK-090', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'kakrol / Kantola', 'sku' => 'KAKROLKANTOLA-091', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Kanikonna Flower', 'sku' => 'KANIKONNAFLWR-092', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Lotus Stump', 'sku' => 'LOTUSSTUMP-093', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Saagaloo', 'sku' => 'SAAGALOO-094', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Singada', 'sku' => 'SINGADA-095', 'unit' => 'kg'],
            ['category' => 'VEG', 'name' => 'Jeegujji / Kada Chakka', 'sku' => 'JEEGUJJI-097', 'unit' => 'kg'],

            // Leaf
            ['category' => 'Leaf', 'name' => 'Corriander', 'sku' => 'CORRIANDER-101', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Corriander Natti', 'sku' => 'CORRIANDERNATTI-102', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Curry Leaf', 'sku' => 'CURRYLEAF-103', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Pudina / Mint Leaf', 'sku' => 'PUDINA-104', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Palak', 'sku' => 'PALAK-105', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Green Cheera', 'sku' => 'GREENCHEERA-106', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Red Cheera', 'sku' => 'REDCHEERA-107', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Methi / Menthaya', 'sku' => 'METHI-108', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Spring Onion', 'sku' => 'SPRINGONION-109', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Gongura', 'sku' => 'GONGURA-110', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Basella leaf / Basala', 'sku' => 'BASELLALEAF-111', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Sabbakki / Dil Leaf', 'sku' => 'SABBAKKI-112', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Chakotha', 'sku' => 'CHAKOTHA-113', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Mustard Leaf', 'sku' => 'MUSTARDLEAF-114', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Drum Stick Leaf', 'sku' => 'DRUMSTICKLEAF-115', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Arai Keerai', 'sku' => 'ARAIKEERAI-116', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Siru Keerai', 'sku' => 'SIRUKEERAI-117', 'unit' => 'kg'],
            ['category' => 'Leaf', 'name' => 'Radish Leaf', 'sku' => 'RADISHLEAF-118', 'unit' => 'kg'],

            // English
            ['category' => 'English', 'name' => 'Sweet Corn', 'sku' => 'SWEETCORN-120', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Peeled Corn', 'sku' => 'PEELEDCORN-121', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Baby Corn', 'sku' => 'BABYCORN-122', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Peeled Garlic', 'sku' => 'PEELEDGARLIC-123', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Peeled Onion', 'sku' => 'PEELEDONION-124', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Sprouds', 'sku' => 'SPROUDS-125', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Cherry Tomato Box', 'sku' => 'CHERRYTMTOBOX-126', 'unit' => 'box'],
            ['category' => 'English', 'name' => 'Cherry Tomato Loose', 'sku' => 'CHERRYTMTOVSE-127', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Mushroom', 'sku' => 'MUSHROOM-128', 'unit' => 'box'],
            ['category' => 'English', 'name' => 'Oyster Mushroom', 'sku' => 'OYSTERMUSHROOM-129', 'unit' => 'box'],
            ['category' => 'English', 'name' => 'Broccoli', 'sku' => 'BROCCOLI-130', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'bockchoy', 'sku' => 'BOCKCHOY-131', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'roman lettuce', 'sku' => 'ROMANLETTUCE-132', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Lettuce', 'sku' => 'LETTUCE-133', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Ice Berg Lettuce', 'sku' => 'ICEBERGLETTUCE-134', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Colur Capcicum Yellow', 'sku' => 'COLCAPSICUMYEL-135', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Colur Capcicum Red', 'sku' => 'COLCAPSICUMRED-136', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Zuchini Green', 'sku' => 'ZUCHINIGREEN-137', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Zuchini Yellow', 'sku' => 'ZUCHINIYELLOW-138', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Red Cabbage', 'sku' => 'REDCABBAGE-139', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Chinees Cabbage', 'sku' => 'CHINEESCABBAGE-140', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Celery Leaf', 'sku' => 'CELERYLEAF-141', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Parsley Leaf', 'sku' => 'PARSLEYLEAF-142', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Basaley Leaf', 'sku' => 'BASALEYLEAF-143', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Lemon Grass', 'sku' => 'LEMONGRASS-144', 'unit' => 'kg'],
            ['category' => 'English', 'name' => 'Leaks', 'sku' => 'LEAKS-145', 'unit' => 'kg'],

            // Kolkata
            ['category' => 'Kolkata', 'name' => 'Bel (Kolkata Item)', 'sku' => 'BELKOLKATA-146', 'unit' => 'kg'],
            ['category' => 'Kolkata', 'name' => 'Misty Pumkin', 'sku' => 'MISTYPUMKIN-147', 'unit' => 'kg'],
            ['category' => 'Kolkata', 'name' => 'Kolkata Lemon', 'sku' => 'KOLKATALEMON-148', 'unit' => 'kg'],
            ['category' => 'Kolkata', 'name' => 'Kochu Loti', 'sku' => 'KOCHULOTI-149', 'unit' => 'kg'],
            ['category' => 'Kolkata', 'name' => 'White Parwal', 'sku' => 'WHITEPARWAL-150', 'unit' => 'kg'],
            ['category' => 'Kolkata', 'name' => 'Maankochoo', 'sku' => 'MAANKOCHOO-151', 'unit' => 'kg'],
            ['category' => 'Kolkata', 'name' => 'Garlic Leafs', 'sku' => 'GARLICLEAFS-161', 'unit' => 'kg'],

            // Banana
            ['category' => 'Banana', 'name' => 'Banana yelakki Green', 'sku' => 'BANANAYELGRN-162', 'unit' => 'kg'],
            ['category' => 'Banana', 'name' => 'Banana Yelakki Color', 'sku' => 'BANANAYELCLR-163', 'unit' => 'kg'],
            ['category' => 'Banana', 'name' => 'Banana Nendran', 'sku' => 'BANANANENDRAN-164', 'unit' => 'kg'],
            ['category' => 'Banana', 'name' => 'Banana Nendran Color', 'sku' => 'BANANANENCLR-165', 'unit' => 'kg'],
            ['category' => 'Banana', 'name' => 'Banana Robusta', 'sku' => 'BANANAROBUSTA-166', 'unit' => 'kg'],
            ['category' => 'Banana', 'name' => 'Red Banana', 'sku' => 'REDBANANA-167', 'unit' => 'kg'],
            ['category' => 'Banana', 'name' => 'Natti Banana', 'sku' => 'NATTIBANANA-168', 'unit' => 'kg'],
            ['category' => 'Banana', 'name' => 'Karpoora Banana', 'sku' => 'KARPOORABANANA-169', 'unit' => 'kg'],

            // Onion
            ['category' => 'Onion', 'name' => 'Onion', 'sku' => 'ONION-003', 'unit' => 'kg'],
            ['category' => 'Onion', 'name' => 'Pickle Onion Bag', 'sku' => 'PICKLEONIONBAG-004', 'unit' => 'bag'],
            ['category' => 'Onion', 'name' => 'Potato Agra', 'sku' => 'POTATOAGRA-005', 'unit' => 'kg'],
            ['category' => 'Onion', 'name' => 'Potato Local', 'sku' => 'POTATOLOCAL-006', 'unit' => 'kg'],
            ['category' => 'Onion', 'name' => 'Baby Potato', 'sku' => 'BABYPOTATO-007', 'unit' => 'kg'],
            ['category' => 'Onion', 'name' => 'Garlic Natti', 'sku' => 'GARLICNATTI-010', 'unit' => 'kg'],
            ['category' => 'Onion', 'name' => 'Garlic Ootty', 'sku' => 'GARLICOOTTY-011', 'unit' => 'kg'],

            // C
            ['category' => 'C', 'name' => 'Coconut', 'sku' => 'COCONUT-170', 'unit' => 'pcs'],

            // Frut
            ['category' => 'Frut', 'name' => 'Gala NZ', 'sku' => 'GALANZ-182', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Gala Apple', 'sku' => 'GALAAPPLE-183', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Irani Apple', 'sku' => 'IRANIAPPLE-184', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Fuji Apple', 'sku' => 'FUJIAPPLE-185', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Green Apple', 'sku' => 'GREENAPPLE-186', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Apple Pink lady', 'sku' => 'APPLEPINKLADY-187', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Red Apple', 'sku' => 'REDAPPLE-188', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Indian Apple', 'sku' => 'INDIANAPPLE-189', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Apple Misri', 'sku' => 'APPLEMISRI-190', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Rockit Apple', 'sku' => 'ROCKITAPPLE-191', 'unit' => 'box'],
            ['category' => 'Frut', 'name' => 'Washington Apple', 'sku' => 'WASHINGTONAPL-192', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mini Orange', 'sku' => 'MINIORANGE-193', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mini Orange SL', 'sku' => 'MINIORANGESL-194', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Pears', 'sku' => 'PEARS-195', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Citrus Orange', 'sku' => 'CITRUSORANGE-196', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Kinnow Orange', 'sku' => 'KINNOWORANGE-197', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Dragon Fruit', 'sku' => 'DRAGONFRUIT-198', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Red Dragon', 'sku' => 'REDDRAGON-199', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Kiwi', 'sku' => 'KIWI-200', 'unit' => 'box'],
            ['category' => 'Frut', 'name' => 'Golden Kiwi', 'sku' => 'GOLDENKIWI-201', 'unit' => 'box'],
            ['category' => 'Frut', 'name' => 'Imp Butter Fruit', 'sku' => 'IMPBUTTERFRUIT-202', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Red Pear', 'sku' => 'REDPEAR-203', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Redglobe', 'sku' => 'REDGLOBE-204', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Redglobe SL', 'sku' => 'REDGLOBESL-205', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Muscat Grape', 'sku' => 'MUSCATGRAPE-206', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Cherry', 'sku' => 'CHERRY-207', 'unit' => 'box'],
            ['category' => 'Frut', 'name' => 'Plum', 'sku' => 'PLUM-208', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Persimmon Fruit', 'sku' => 'PERSIMMON-209', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Blue Berry', 'sku' => 'BLUEBERRY-210', 'unit' => 'box'],
            ['category' => 'Frut', 'name' => 'S Tamrind', 'sku' => 'STAMRIND-211', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Raspberry', 'sku' => 'RASPBERRY-212', 'unit' => 'box'],
            ['category' => 'Frut', 'name' => 'Bare Apple', 'sku' => 'BAREAPPLE-213', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Custard Apple', 'sku' => 'CUSTARDAPPLE-214', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Golden Custard', 'sku' => 'GOLDENCUSTARD-215', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Orange', 'sku' => 'ORANGE-216', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Watermelon', 'sku' => 'WATERMELON-217', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Watermelon Namdhari', 'sku' => 'WATERMELONNAM-218', 'unit' => 'pcs'],
            ['category' => 'Frut', 'name' => 'Watermelon Outside Yellow', 'sku' => 'WATERMELONOUTYEL-219', 'unit' => 'pcs'],
            ['category' => 'Frut', 'name' => 'Watermelon Inside Yellow', 'sku' => 'WATERMELONINSYEL-220', 'unit' => 'pcs'],
            ['category' => 'Frut', 'name' => 'Pineapple', 'sku' => 'PINEAPPLE-221', 'unit' => 'pcs'],
            ['category' => 'Frut', 'name' => 'Strawberry', 'sku' => 'STRAWBERRY-222', 'unit' => 'box'],
            ['category' => 'Frut', 'name' => 'Musambi', 'sku' => 'MUSAMBI-223', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Anar / Pomegranate', 'sku' => 'ANAR-224', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Anar Gujrath', 'sku' => 'ANARGUJRATH-225', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Anar S S', 'sku' => 'ANARSS-226', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Supporta / Chikoo', 'sku' => 'SUPPORTACHIKOO-227', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Thai Guava', 'sku' => 'THAIGUAVA-228', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Jappan Guava', 'sku' => 'JAPPANGUAVA-229', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Muskmelon', 'sku' => 'MUSKMELON-230', 'unit' => 'pcs'],
            ['category' => 'Frut', 'name' => 'Patta Jam', 'sku' => 'PATTAJAM-231', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Black Grapes', 'sku' => 'BLACKGRAPES-232', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Green Grapes', 'sku' => 'GREENGRAPES-233', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Local Redglobe', 'sku' => 'LOCALREDGLOBE-234', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Butter Fruit', 'sku' => 'BUTTERFRUIT-235', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Litchi', 'sku' => 'LITCHI-236', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Fig / Anjeer', 'sku' => 'FIGANJEER-237', 'unit' => 'box'],
            ['category' => 'Frut', 'name' => 'Rambutan', 'sku' => 'RAMBUTAN-238', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Passion Fruit', 'sku' => 'PASSIONFRUIT-239', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Orange Malta', 'sku' => 'ORANGEMALTA-240', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Jamun', 'sku' => 'JAMUN-241', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mangosteen', 'sku' => 'MANGOSTEEN-242', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mulberry', 'sku' => 'MULBERRY-243', 'unit' => 'box'],
            ['category' => 'Frut', 'name' => 'Mango BP', 'sku' => 'MANGOBP-244', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango BP SPL', 'sku' => 'MANGOFPSPL-245', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango Bdmi', 'sku' => 'MANGOBDMI-246', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango Bdmi SPL', 'sku' => 'MANGOBDMISPL-247', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango Malliga', 'sku' => 'MANGOMALLIGA-248', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango Malliga SPL', 'sku' => 'MANGOMALLIGASPL-249', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango IP', 'sku' => 'MANGOIP-250', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango IP SPL', 'sku' => 'MANGOIPSPL-251', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango Sindura', 'sku' => 'MANGOSINDURA-252', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango Kesar', 'sku' => 'MANGOKESAR-253', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango Neelam', 'sku' => 'MANGONEELAM-254', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango Malgova', 'sku' => 'MANGOMALGOVA-255', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango Langada', 'sku' => 'MANGOLANGADA-256', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango Dasari', 'sku' => 'MANGODASARI-257', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango Raspuri', 'sku' => 'MANGORASPURI-258', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Amrapalli Mango', 'sku' => 'AMRAPALLIMANGO-259', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Kalapad Mango', 'sku' => 'KALAPADMANGO-260', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Sugar Baby Mango', 'sku' => 'SUGARBABYMANGO-261', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Nambiar Mango', 'sku' => 'NAMBIARMANGO-262', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango Chausa', 'sku' => 'MANGOCHAUSA-263', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango Javvari', 'sku' => 'MANGOJAVVARI-264', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Himsagar Mango', 'sku' => 'HIMSAGARMANGO-265', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Alphonso Mango / Hapus', 'sku' => 'ALPHONSOMANGO-266', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Kalapad Mango (Duplicate)', 'sku' => 'KALAPADMANGO-267', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Piyyur Mango', 'sku' => 'PIYYURMANGO-268', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Natti Mango', 'sku' => 'NATTIMANGO-269', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango Sundari', 'sku' => 'MANGOSUNDARI-270', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Rumani Mango', 'sku' => 'RUMANIMANGO-271', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Mango South Africa', 'sku' => 'MANGOSOUTHAFRICA-272', 'unit' => 'kg'],
            ['category' => 'Frut', 'name' => 'Movaandan Mago', 'sku' => 'MOVAANDANMANGO-273', 'unit' => 'kg'],

            // Stationory
            ['category' => 'Stationory', 'name' => 'Plate 1d', 'sku' => 'PLATE1D-300', 'unit' => 'pcs'],
            ['category' => 'Stationory', 'name' => 'Plate 2D', 'sku' => 'PLATE2D-301', 'unit' => 'pcs'],
            ['category' => 'Stationory', 'name' => 'Wrapping Roll', 'sku' => 'WRAPPINGROLL-302', 'unit' => 'roll'],
            ['category' => 'Stationory', 'name' => 'Container 250G', 'sku' => 'CONTAINER250G-303', 'unit' => 'pcs'],
            ['category' => 'Stationory', 'name' => 'Container 500 G', 'sku' => 'CONTAINER500G-304', 'unit' => 'pcs'],
        ];

        foreach ($products as $data) {
            $category = Category::where('name', $data['category'])->first();
            if (! $category) {
                continue;
            }

            Product::updateOrCreate(
                ['sku' => $data['sku']],
                [
                    'category_id' => $category->id,
                    'name' => $data['name'],
                    'sku' => $data['sku'],
                    'unit' => $data['unit'],
                    'base_price' => $this->resolveBasePrice($data['sku'], $data['unit']),
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✅ '.Product::count().' products seeded successfully.');
    }

    private function resolveBasePrice(string $sku, string $unit): float
    {
        $skuOverrides = [
            'APPLEPINKLADY-187' => 185.00,
            'BABYPOTATO-007' => 42.00,
            'PINEAPPLE-221' => 65.00,
            'TOMATON-002' => 38.00,
            'WRAPPINGROLL-302' => 48.00,
            'CHERRYTMTOBOX-126' => 220.00,
            'POTATOAGRA-005' => 34.00,
            'TOMATOH-001' => 36.00,
        ];

        if (array_key_exists($sku, $skuOverrides)) {
            return $skuOverrides[$sku];
        }

        return match ($unit) {
            'kg' => 40.00,
            'box' => 125.00,
            'pcs' => 18.00,
            'bag' => 260.00,
            'roll' => 32.00,
            'bunch' => 22.00,
            default => 30.00,
        };
    }
}
