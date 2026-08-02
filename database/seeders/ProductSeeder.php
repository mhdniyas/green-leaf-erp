<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    private const PRODUCT_LINES = <<<'DATA'
1|Tomato H
2|Tomato N
3|Onion
4|Pickle Onion Bag
5|Potato Agra
6|Potato Local
7|Baby Potato
8|Sambar Onion
9|Onion White
10|Garlic Natti
11|Garlic Ootty
12|Carrot
13|French Beans
14|Beans Natty
15|ladies Finger
16|Green Peas
17|Broad beans S / Belt
18|Paper Chikkdi
19|White Karamani
20|Green Karamani
21|Avarakkai/lablab Beans
22|Cluster Beans / Gorikkai
23|Green Chilli Normal
24|Chilli Spicy / Akash G4
25|Bajji Chilli
26|Red Chilli
27|Brinjal White Long
28|Brinjal Big (Bertha)
29|Brinjal Small
30|Brinjal White Kolkata
31|Brinjol Rampuri
32|Brinjal Blue Long
33|Capsicum
34|Beetroot Ootty
35|Chow Chow
36|Noolkol (Kohlrbi)
37|Radish
38|Tinda (Round Gurad)
39|Bitter Gourd Big
40|Bitter Gourd Small
41|Bitter Gourd White
42|Bottle Gourd (Loki)
43|Ridge Gourd
44|Drum Stick
45|Snake Gourd White
46|Snake Gourd Green
47|Raw Jack Fruit
48|Banana Flower
49|Banana Stem
50|Raw Banana
51|Banana Leaf
52|Sambar Cucbr / Southikai
53|Cucumber
54|English Cucumber
55|Yam
56|Pickle Mango
57|Raw Mango Gund
58|Raw Mango totapuri
59|Lemon
60|Cauliflower
61|Cabbage
62|Arbi /Taroroot
63|Big Arbi
64|Koorka Chinees Potato
65|Amla
66|Peeled Green Peas
67|Jack Fruit Ripe
68|Jack Fruit Cut
69|Raw Turmeric Kalkata
70|Delhi / Red Carrot
71|Kovakka Normal
72|Parval / Potol
73|Sweet Potato
74|Tapioca
75|Ginger
76|Ground Nut
77|Round Lauki White
78|Round Lauki Green
79|Raw Pappaya
80|SP Natti Thondakai
81|Armenian Cucumber / Kakdi
82|Disco Pumkin
83|White Pumkin
84|Yellow Pumkin
85|Natti Pumkin
86|Turnip / Shalgam
87|Mango Ginger
88|Red Radish
89|Turmeric Stick Fresh
90|Flower K
91|kakrol / Kantola
92|Kanikonna Flower
93|Lotus Stump
94|Saagaloo
95|Singada
96|Nenua gourd
97|Jeegujji / Kada Chakka
101|Corriander
102|Corriander Natti
103|Curry Leaf
104|Pudina / Mint Leaf
105|Palak
106|Green Cheera
107|Red Cheera
108|Methi / Menthaya
109|Spring Onion
110|Gongura
111|Basella leaf / Basala
112|Sabbakki / Dil Leaf
113|Chakotha
114|Mustard Leaf
115|Drum Stick Leaf
116|Arai Keerai
117|Siru Keerai
118|Radish Leaf
120|Sweet Corn
121|Peeled Corn
122|Baby Corn
123|Peeled Garlic
124|Peeled Onion
125|Cherry Tomato Box SP
126|Cherry Tomato Box
127|Cherry Tomato Loose
128|Mushroom
129|Oyster Mushroom
130|Broccoli
131|bockchoy
132|roman lettuce
133|Lettuce
134|Ice Berg Lettuce
135|Colur Capcicum Yellow
136|Colur Capcicum Red
137|Zuchini Green
138|Zuchini Yellow
139|Red Cabbage
140|Chinees Cabbage
141|Celery Leaf
142|Parsley Leaf
143|Basaley Leaf
144|Lemon Grass
145|Leaks
146|Sprouds Mix
147|Sprouds Green Gram
148|Sprouds chickpeas / chana
149|Sprouds Horse Gram
150|Sprouds Cowpea
151|Bel (Kolkata Item)
152|Misty Pumkin
153|Kolkata Lemon
154|Kochu Loti
155|White Parwal
156|Maankochoo
161|Garlic Leafs
162|Banana yelakki Green
163|Banana Yelakki Color
164|Banana Nendran
165|Banana Nendran Color
166|Banana Robusta
167|Red Banana
168|Natti Banana
169|Karpoora Banana
170|Coconut
181|Pappaya
182|Gala NZ
183|Gala Apple
184|Irani Apple
185|Fuji Apple
186|Green Apple
187|Apple Pink lady
188|Red Apple
189|Indian Apple
190|Apple Misri
191|Rockit Apple
192|Washington Apple
193|Mini Orange
194|Mini Orange SL
195|Pears
196|Citrus Orange
197|Kinnow Orange
198|Dragon Fruit
199|Red Dragon
200|Kiwi
201|Golden Kiwi
202|Imp Butter Fruit
203|Red Pear
204|Redglobe
205|Redglobe SL
206|Muscat Grape
207|Cherry
208|Plum
209|Persimmon Fruit
210|Blue Berry
211|S Tamrind
212|Raspberry
213|Bare Apple
214|Custard Apple
215|Golden Custard
216|Orange
217|Watermelon
218|Watermelon Namdhari
219|Watermelon Outside Yellow
220|Watermelon Inside Yellow
221|Pineapple
222|Strawberry
223|Musambi
224|Anar / Pomegranate
225|Anar Gujrath
226|Anar S S
227|Supporta / Chikoo
228|Thai Guava
229|Jappan Guava
230|Muskmelon
231|Patta Jam
232|Black Grapes
233|Green Grapes
234|Local Redglobe
235|Butter Fruit
236|Litchi
237|Fig / Anjeer
238|Rambutan
239|Passion Fruit
240|Orange Malta
241|Jamun
242|Mangosteen
243|Mulberry
244|Mango BP
245|Mango BP SPL
246|Mango Bdmi
247|Mango Bdmi SPL
248|Mango Malliga
249|Mango Malliga SPL
250|Mango IP
251|Mango IP SPL
252|Mango Sindura
253|Mango Kesar
254|Mango Neelam
255|Mango Malgova
256|Mango Langada
257|Mango Dasari
258|Mango Raspuri
259|Amrapalli Mango
260|Kalapad Mango
261|Sugar Baby Mango
262|Nambiar Mango
263|Mango Chausa
264|Mango Javvari
265|Himsagar Mango
266|Alphonso Mango / Hapus
267|Kalapad Mango
268|Piyyur Mango
269|Natti Mango
270|Mango Sundari
271|Rumani Mango
272|Mango South Africa
273|Movaandan Mago
300|Plate 1d
301|Plate 2D
302|Wrapping Roll
303|Container 250G
304|Container 500 G
305|Papper Bag 1kg
306|Papper Bag 3kg
307|Papper Bag 5kg
308|Cover 13X16
309|Cover 16X20
310|Cover 20X26
311|Cover 26X34
312|Billing Roll
DATA;


    private const MEASURE_LINES = <<<'DATA'
Tomato H|kg|kg:KG:1:1:1:0;crate:CRATE:20:0:1:1
Tomato N|kg|kg:KG:1:1:1:0;crate:CRATE:20:0:1:1
Onion|kg|kg:KG:1:1:1:0;bag:BAG:50:0:1:1
Pickle Onion Bag|bag|bag:BAG:1:1:1:0
Potato Agra|kg|kg:KG:1:1:1:0;bag:BAG:50:0:1:1
Potato Local|kg|kg:KG:1:1:1:0;bag:BAG:50:0:1:1
Baby Potato|bag|bag:BAG:1:1:1:0
Banana Flower|piece|piece:PIECE:1:1:1:0
Banana Stem|piece|piece:PIECE:1:1:1:0
Raw Banana|piece|piece:PIECE:1:1:1:0
Banana Leaf|piece|piece:PIECE:1:1:1:0
Yam|piece|piece:PIECE:1:1:1:0
Cauliflower|piece|piece:PIECE:1:1:1:0
Disco Pumkin|piece|piece:PIECE:1:1:1:0
Corriander|bunch|bunch:BUNCH:1:1:1:0
Corriander Natti|bunch|bunch:BUNCH:1:1:1:0
Curry Leaf|bunch|bunch:BUNCH:1:1:1:0
Pudina / Mint Leaf|bunch|bunch:BUNCH:1:1:1:0
Palak|bunch|bunch:BUNCH:1:1:1:0
Green Cheera|bunch|bunch:BUNCH:1:1:1:0
Red Cheera|bunch|bunch:BUNCH:1:1:1:0
Methi / Menthaya|bunch|bunch:BUNCH:1:1:1:0
Spring Onion|bunch|bunch:BUNCH:1:1:1:0
Gongura|bunch|bunch:BUNCH:1:1:1:0
Basella leaf / Basala|bunch|bunch:BUNCH:1:1:1:0
Sabbakki / Dil Leaf|bunch|bunch:BUNCH:1:1:1:0
Chakotha|bunch|bunch:BUNCH:1:1:1:0
Mustard Leaf|bunch|bunch:BUNCH:1:1:1:0
Drum Stick Leaf|bunch|bunch:BUNCH:1:1:1:0
Arai Keerai|bunch|bunch:BUNCH:1:1:1:0
Siru Keerai|bunch|bunch:BUNCH:1:1:1:0
Radish Leaf|bunch|bunch:BUNCH:1:1:1:0
Sweet Corn|piece|piece:PIECE:1:1:1:0
Peeled Corn|piece|piece:PIECE:1:1:1:0
Baby Corn|piece|piece:PIECE:1:1:1:0
Cherry Tomato Box SP|piece|piece:PIECE:1:1:1:0
Cherry Tomato Box|piece|piece:PIECE:1:1:1:0
Mushroom|piece|piece:PIECE:1:1:1:0
Oyster Mushroom|piece|piece:PIECE:1:1:1:0
Sprouds Green Gram|box|box:BOX:1:1:1:0
Sprouds chickpeas / chana|box|box:BOX:1:1:1:0
Sprouds Horse Gram|box|box:BOX:1:1:1:0
Sprouds Cowpea|box|box:BOX:1:1:1:0
Banana yelakki Green|full_bunch|full_bunch:FULL BUNCH:1:1:1:0
Banana Yelakki Color|full_bunch|full_bunch:FULL BUNCH:1:1:1:0
Banana Nendran|full_bunch|full_bunch:FULL BUNCH:1:1:1:0
Banana Nendran Color|full_bunch|full_bunch:FULL BUNCH:1:1:1:0
Coconut|piece|piece:PCS:1:1:1:0
Gala NZ|kg|kg:KG:1:1:0:0;box:BOX 18 KG:18:0:0:1
Gala Apple|kg|kg:KG:1:1:1:0;box:BOX:18:0:1:1
Irani Apple|kg|kg:KG:1:1:0:0;box:BOX 10 KG:10:0:0:1
Fuji Apple|kg|kg:KG:1:1:0:0;box:BOX 10 KG:10:0:0:1
Green Apple|kg|kg:KG:1:1:0:0;box:BOX 18 KG:18:0:0:1
Apple Pink lady|kg|kg:KG:1:1:0:0;box:BOX 18 KG:18:0:0:1
Red Apple|kg|kg:KG:1:1:0:0;box:BOX 14 KG:14:0:1:1;box:BOX 18 KG:18:0:1:2
Indian Apple|kg|kg:KG:1:1:0:0;box:BOX 20 KG:20:0:0:1
Apple Misri|kg|kg:KG:1:1:1:0;box:BOX 7 KG:7:0:0:1
Rockit Apple|box|box:BOX:1:1:1:0
Washington Apple|kg|kg:KG:1:1:0:0;box:BOX 11 KG:11:0:1:1
Mini Orange|kg|kg:KG:1:1:1:0;box:BOX 10 KG:10:0:0:1
Mini Orange SL|kg|kg:KG:1:1:1:0;box:BOX 10 KG:10:0:0:1
Pears|kg|kg:KG:1:1:1:0;box:BOX 13 KG:13:0:0:1
Citrus Orange|kg|kg:KG:1:1:1:0;box:BOX 15 KG:15:0:0:1
Kinnow Orange|kg|kg:KG:1:1:1:0;box:BOX 20 KG:20:0:0:1
Dragon Fruit|piece|piece:PIECE:1:1:0:2
Kiwi|piece|box:BOX 1 PIECE:1:0:0:1;piece:PIECE:1:1:1:2
Golden Kiwi|piece|box:BOX 1 PIECE:1:0:0:1;piece:PIECE:1:1:0:2
Imp Butter Fruit|kg|kg:KG:1:1:1:0;box:BOX 4 KG:4:0:0:1
Red Pear|kg|kg:KG:1:1:1:0;box:BOX 13 KG:13:0:0:1
Redglobe|kg|kg:KG:1:1:1:0;box:BOX 7 KG:7:0:0:1
Redglobe SL|kg|kg:KG:1:1:1:0;box:BOX 5 KG:5:0:0:1
Muscat Grape|kg|kg:KG:1:1:1:0;box:BOX 5 KG:5:0:0:1
Cherry|kg|kg:KG:1:1:0:0;box:BOX 2 KG:2:0:0:1;piece:PIECE:1:0:0:2
Plum|kg|kg:KG:1:1:1:0;box:BOX 5 KG:5:0:0:1
Persimmon Fruit|kg|kg:KG:1:1:1:0;box:BOX 7 KG:7:0:0:1
Blue Berry|piece|box:BOX 1 PIECE:1:0:0:1;piece:PIECE:1:1:0:2
Raspberry|piece|box:BOX 1 PIECE:1:0:0:1;piece:PIECE:1:1:0:2
Bare Apple|kg|kg:KG:1:1:1:0;box:BOX 10 KG:10:0:0:1
Watermelon|kg|kg:KG:1:1:1:0;bag:BAG 30 KG:30:0:0:1
Watermelon Namdhari|piece|piece:PCS:1:1:1:0
Watermelon Outside Yellow|piece|piece:PCS:1:1:1:0
Watermelon Inside Yellow|piece|piece:PCS:1:1:1:0
Pineapple|kg|kg:KG:1:1:0:0;piece:PIECE:1:0:0:2
Strawberry|piece|piece:PIECE:1:1:1:0
Anar / Pomegranate|kg|kg:KG:1:1:0:0;box:BOX 10 KG:10:0:1:1;crate:CRATE:20:0:1:6
Anar Gujrath|kg|kg:KG:1:1:0:0;box:BOX 10 KG:10:0:1:1;crate:CRATE:20:0:1:6
Anar S S|kg|kg:KG:1:1:0:0;box:BOX 10 KG:10:0:1:1;crate:CRATE:20:0:1:6
Muskmelon|piece|piece:PCS:1:1:1:0
Black Grapes|kg|kg:KG:1:1:0:0;crate:CRATE:9:0:1:6
Green Grapes|kg|kg:KG:1:1:0:0;crate:CRATE:9:0:1:6
Local Redglobe|kg|kg:KG:1:1:0:0;crate:CRATE:9:0:1:6
Litchi|kg|kg:KG:1:1:0:0;box:BOX 10 KG:10:0:1:1
Fig / Anjeer|box|box:BOX:1:1:1:1;bag:BAG:0.0054:0:0:3
Mulberry|piece|piece:PIECE:1:1:1:0
Mango BP|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Mango BP SPL|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Mango Bdmi|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Mango Bdmi SPL|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Mango Malliga|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Mango Malliga SPL|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Mango IP|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Mango IP SPL|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Mango Sindura|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Mango Kesar|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Mango Neelam|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Mango Malgova|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Mango Langada|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Mango Dasari|kg|kg:KG:1:1:1:0;crate:CRATE:20:0:0:6
Mango Raspuri|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Amrapalli Mango|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Kalapad Mango|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Sugar Baby Mango|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Nambiar Mango|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Mango Chausa|kg|kg:KG:1:1:1:0;box:BOX 5 KG:5:0:0:1
Mango Javvari|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Himsagar Mango|kg|kg:KG:1:1:0:0;crate:CRATE:20:0:1:6
Alphonso Mango / Hapus|kg|kg:KG:1:1:0:0;box:BOX 5 KG:5:0:1:1
Plate 1d|piece|piece:PCS:1:1:1:0
Plate 2D|piece|piece:PCS:1:1:1:0
Wrapping Roll|roll|roll:ROLL:1:1:1:0
Container 250G|piece|piece:PCS:1:1:1:0
Container 500 G|piece|piece:PCS:1:1:1:0
Papper Bag 1kg|piece|piece:PCS:1:1:1:0
Papper Bag 3kg|piece|piece:PCS:1:1:1:0
Papper Bag 5kg|piece|piece:PCS:1:1:1:0
Cover 13X16|piece|piece:PCS:1:1:1:0
Cover 16X20|piece|piece:PCS:1:1:1:0
Cover 20X26|piece|piece:PCS:1:1:1:0
Cover 26X34|piece|piece:PCS:1:1:1:0
Billing Roll|roll|roll:ROLL:1:1:1:0
DATA;

    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
        ]);

        $seededProductIds = [];

        foreach ($this->catalog() as $data) {
            $category = Category::query()->where('name', $data['category'])->first();

            if (! $category) {
                continue;
            }

            $legacySkuSuffix = str_pad($data['sku'], 3, '0', STR_PAD_LEFT);

            $product = Product::withTrashed()
                ->where(function ($query) use ($data, $legacySkuSuffix) {
                    $query->where('sku', $data['sku'])
                        ->orWhere('sku', 'like', '%-'.$legacySkuSuffix);
                })
                ->first();

            if (! $product) {
                $product = Product::query()->create([
                    'category_id' => $category->id,
                    'name' => $data['name'],
                    'sku' => $data['sku'],
                    'unit' => $data['unit'],
                    'base_price' => $this->resolveBasePrice($data['sku'], $data['unit']),
                    'is_active' => true,
                ]);

                $this->syncUnits($product, $data['units']);
            } else {
                if ($product->trashed()) {
                    $product->restore();
                }

                // Do not overwrite admin-edited values.
                // Only populate genuinely missing fields.
                $product->fill([
                    'category_id' => $product->category_id ?: $category->id,
                    'name' => $product->name ?: $data['name'],
                    'unit' => $product->unit ?: $data['unit'],
                    'base_price' => $product->base_price ?? $this->resolveBasePrice(
                        $data['sku'],
                        $data['unit']
                    ),
                ])->save();

                if (! $product->orderUnits()->exists()) {
                    $this->syncUnits($product, $data['units']);
                }
            }

            $seededProductIds[] = $product->id;
        }

        $this->command?->info('✅ '.count($seededProductIds).' products seeded successfully.');
    }

    /**
     * @return array<int, array{category: string, name: string, sku: string, unit: string, units: array<int, array{unit: string, label: string, conversion_to_base: ?float, is_base: bool, is_orderable: bool, sort_order: int}>}>
     */
    private function catalog(): array
    {
        $products = [];
        $measuresByName = $this->measuresByName();

        foreach (preg_split('/\r\n|\r|\n/', self::PRODUCT_LINES) as $line) {
            if (! $line) {
                continue;
            }

            [$sku, $name] = explode('|', $line, 2);
            $units = $measuresByName[$name]['units'] ?? $this->defaultUnits($this->resolveUnit((int) $sku));
            $baseUnit = $measuresByName[$name]['base_unit'] ?? $this->resolveUnit((int) $sku);

            $products[] = [
                'category' => $this->resolveCategory((int) $sku),
                'name' => $name,
                'sku' => $sku,
                'unit' => $baseUnit,
                'units' => $units,
            ];
        }

        return $products;
    }

    /**
     * @return array<string, array{base_unit: string, units: array<int, array{unit: string, label: string, conversion_to_base: ?float, is_base: bool, is_orderable: bool, sort_order: int}>}>
     */
    private function measuresByName(): array
    {
        $measures = [];

        foreach (preg_split('/\r\n|\r|\n/', trim(self::MEASURE_LINES)) as $line) {
            if (! $line) {
                continue;
            }

            [$name, $baseUnit, $unitLines] = explode('|', $line, 3);

            $measures[$name] = [
                'base_unit' => ProductUnit::normalizeUnit($baseUnit),
                'units' => collect(explode(';', $unitLines))
                    ->map(function (string $unitLine): array {
                        [$unit, $label, $conversion, $isBase, $isOrderable, $sortOrder] = explode(':', $unitLine);

                        return [
                            'unit' => ProductUnit::normalizeUnit($unit),
                            'label' => $label,
                            'conversion_to_base' => $conversion === '' ? null : (float) $conversion,
                            'is_base' => (bool) (int) $isBase,
                            'is_orderable' => (bool) (int) $isOrderable,
                            'sort_order' => (int) $sortOrder,
                        ];
                    })
                    ->values()
                    ->all(),
            ];
        }

        return $measures;
    }

    /**
     * @return array<int, array{unit: string, label: string, conversion_to_base: float, is_base: bool, is_orderable: bool, sort_order: int}>
     */
    private function defaultUnits(string $baseUnit): array
    {
        $unit = ProductUnit::normalizeUnit($baseUnit);

        return [[
            'unit' => $unit,
            'label' => $unit === 'piece' ? 'PCS' : strtoupper($unit),
            'conversion_to_base' => 1.0,
            'is_base' => true,
            'is_orderable' => true,
            'sort_order' => 0,
        ]];
    }

    /**
     * @param  array<int, array{unit: string, label: string, conversion_to_base: ?float, is_base: bool, is_orderable: bool, sort_order: int}>  $units
     */
    private function syncUnits(Product $product, array $units): void
    {
        $existingUnits = $product->orderUnits()->get()->keyBy('id');
        $keptIds = [];

        foreach ($units as $unit) {
            $attributes = [
                'unit' => $unit['unit'],
                'label' => $unit['label'],
                'conversion_to_base' => $unit['conversion_to_base'],
                'is_base' => $unit['is_base'],
                'is_orderable' => $unit['is_orderable'],
                'sort_order' => $unit['sort_order'],
            ];

            $existing = $product->orderUnits()
                ->whereRaw('LOWER(label) = ?', [mb_strtolower($unit['label'])])
                ->first();

            if ($existing) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;

                continue;
            }

            $created = $product->orderUnits()->create($attributes);
            $keptIds[] = $created->id;
        }

        if ($keptIds !== []) {
            $product->orderUnits()->whereNotIn('id', $keptIds)->delete();
        } elseif ($existingUnits->isNotEmpty()) {
            $product->orderUnits()->delete();
        }
    }

    private function resolveCategory(int $sku): string
    {
        return match (true) {
            $sku <= 2 => 'Supply',
            $sku <= 11 => 'Onion',
            $sku <= 97 => 'VEG',
            $sku <= 118 => 'Leaf',
            $sku <= 145 => 'English',
            $sku <= 161 => 'Kolkata',
            $sku <= 169 => 'Banana',
            $sku === 170 => 'C',
            $sku <= 273 => 'Frut',
            default => 'Stationory',
        };
    }

    private function resolveUnit(int $sku): string
    {
        return match (true) {
            $sku === 4 => 'bag',
            in_array($sku, [147, 148, 149, 150, 188, 191, 237], true) => 'box',
            in_array($sku, [48, 49, 50, 51, 55, 60, 82, 120, 121, 122, 125, 126, 128, 129, 170, 198, 200, 201, 210, 212, 218, 219, 220, 222, 230, 243, 300, 301, 303, 304, 308, 309, 310, 311], true) => 'piece',
            in_array($sku, [101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118], true) => 'bunch',
            in_array($sku, [162, 163, 164, 165], true) => 'full_bunch',
            in_array($sku, [302, 312], true) => 'roll',
            default => 'kg',
        };
    }

    private function resolveBasePrice(string $sku, string $unit): float
    {
        $skuOverrides = [
            '1' => 22.00,
            '2' => 20.00,
            '3' => 35.00,
            '4' => 35.00,
            '5' => 25.00,
            '6' => 30.00,
            '7' => 35.00,
            '8' => 80.00,
            '9' => 90.00,
            '10' => 220.00,
            '11' => 290.00,
            '12' => 105.00,
            '13' => 100.00,
            '14' => 70.00,
            '15' => 65.00,
            '16' => 110.00,
            '17' => 65.00,
            '18' => 70.00,
            '19' => 80.00,
            '20' => 80.00,
            '21' => 150.00,
            '22' => 70.00,
            '23' => 70.00,
            '24' => 85.00,
            '25' => 80.00,
            '26' => 129.00,
            '27' => 80.00,
            '28' => 85.00,
            '29' => 60.00,
            '30' => 90.00,
            '31' => 60.00,
            '32' => 90.00,
            '33' => 60.00,
            '34' => 100.00,
            '35' => 60.00,
            '36' => 40.00,
            '37' => 45.00,
            '38' => 130.00,
            '39' => 50.00,
            '40' => 70.00,
            '41' => 85.00,
            '42' => 60.00,
            '43' => 60.00,
            '44' => 60.00,
            '45' => 40.00,
            '46' => 80.00,
            '47' => 70.00,
            '48' => 25.00,
            '49' => 10.00,
            '50' => 10.00,
            '51' => 6.00,
            '52' => 25.00,
            '53' => 43.00,
            '54' => 55.00,
            '55' => 40.00,
            '56' => 90.00,
            '57' => 85.00,
            '58' => 60.00,
            '59' => 100.00,
            '60' => 43.00,
            '61' => 30.00,
            '62' => 50.00,
            '63' => 110.00,
            '64' => 120.00,
            '65' => 80.00,
            '66' => 0.00,
            '67' => 200.00,
            '68' => 80.00,
            '69' => 170.00,
            '70' => 0.00,
            '71' => 46.00,
            '72' => 80.00,
            '73' => 65.00,
            '74' => 40.00,
            '75' => 210.00,
            '76' => 100.00,
            '77' => 85.00,
            '78' => 70.00,
            '79' => 40.00,
            '80' => 175.00,
            '81' => 90.00,
            '82' => 45.00,
            '83' => 26.00,
            '84' => 26.00,
            '85' => 29.00,
            '86' => 170.00,
            '87' => 100.00,
            '88' => 170.00,
            '89' => 130.00,
            '90' => 320.00,
            '91' => 165.00,
            '92' => 20.00,
            '93' => 200.00,
            '94' => 0.00,
            '95' => 0.00,
            '96' => 110.00,
            '97' => 130.00,
            '101' => 13.00,
            '102' => 25.00,
            '103' => 50.00,
            '104' => 13.00,
            '105' => 13.00,
            '106' => 13.00,
            '107' => 19.00,
            '108' => 15.00,
            '109' => 18.00,
            '110' => 19.00,
            '111' => 19.00,
            '112' => 15.00,
            '113' => 15.00,
            '114' => 19.00,
            '116' => 13.00,
            '117' => 13.00,
            '120' => 22.00,
            '121' => 23.00,
            '122' => 28.00,
            '123' => 53.00,
            '124' => 15.00,
            '125' => 65.00,
            '126' => 35.00,
            '127' => 190.00,
            '128' => 47.00,
            '129' => 60.00,
            '130' => 120.00,
            '131' => 120.00,
            '132' => 140.00,
            '133' => 120.00,
            '134' => 120.00,
            '135' => 160.00,
            '136' => 160.00,
            '137' => 120.00,
            '138' => 120.00,
            '139' => 150.00,
            '140' => 150.00,
            '141' => 160.00,
            '142' => 140.00,
            '143' => 160.00,
            '144' => 140.00,
            '145' => 160.00,
            '146' => 25.00,
            '147' => 25.00,
            '148' => 25.00,
            '149' => 25.00,
            '150' => 25.00,
            '151' => 88.00,
            '152' => 88.00,
            '153' => 140.00,
            '154' => 90.00,
            '155' => 120.00,
            '156' => 110.00,
            '161' => 0.00,
            '162' => 92.00,
            '163' => 92.00,
            '164' => 74.00,
            '165' => 74.00,
            '166' => 37.00,
            '167' => 77.00,
            '168' => 0.00,
            '169' => 0.00,
            '170' => 38.00,
            '181' => 0.00,
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
