<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
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
125|Sprouds
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
146|Bel (Kolkata Item)
147|Misty Pumkin
148|Kolkata Lemon
149|Kochu Loti
150|White Parwal
151|Maankochoo
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
DATA;

    public function run(): void
    {
        $seededProductIds = [];

        foreach ($this->catalog() as $data) {
            $category = Category::query()->where('name', $data['category'])->first();

            if (! $category) {
                continue;
            }

            $legacySkuSuffix = str_pad($data['sku'], 3, '0', STR_PAD_LEFT);

            $product = Product::query()
                ->where('sku', $data['sku'])
                ->orWhere('sku', 'like', '%-'.$legacySkuSuffix)
                ->first();

            if ($product) {
                $product->update([
                    'category_id' => $category->id,
                    'name' => $data['name'],
                    'sku' => $data['sku'],
                    'unit' => $data['unit'],
                    'base_price' => $this->resolveBasePrice($data['sku'], $data['unit']),
                    'is_active' => true,
                ]);
            } else {
                $product = Product::query()->create([
                    'category_id' => $category->id,
                    'name' => $data['name'],
                    'sku' => $data['sku'],
                    'unit' => $data['unit'],
                    'base_price' => $this->resolveBasePrice($data['sku'], $data['unit']),
                    'is_active' => true,
                ]);
            }

            $seededProductIds[] = $product->id;
        }

        Product::query()
            ->whereNotIn('id', $seededProductIds)
            ->update(['is_active' => false]);

        $this->command?->info('✅ '.count($seededProductIds).' products seeded successfully.');
    }

    /**
     * @return array<int, array{category: string, name: string, sku: string, unit: string}>
     */
    private function catalog(): array
    {
        $products = [];

        foreach (preg_split('/\r\n|\r|\n/', self::PRODUCT_LINES) as $line) {
            if (! $line) {
                continue;
            }

            [$sku, $name] = explode('|', $line, 2);

            $products[] = [
                'category' => $this->resolveCategory((int) $sku),
                'name' => $name,
                'sku' => $sku,
                'unit' => $this->resolveUnit((int) $sku),
            ];
        }

        return $products;
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
            in_array($sku, [126, 128, 129, 191, 200, 201, 207, 210, 212, 222, 237, 243], true) => 'box',
            in_array($sku, [170, 218, 219, 220, 221, 230, 300, 301, 303, 304], true) => 'pcs',
            $sku === 302 => 'roll',
            default => 'kg',
        };
    }

    private function resolveBasePrice(string $sku, string $unit): float
    {
        $skuOverrides = [
            '1' => 36.00,
            '2' => 38.00,
            '5' => 34.00,
            '7' => 42.00,
            '126' => 220.00,
            '187' => 185.00,
            '221' => 65.00,
            '302' => 48.00,
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
