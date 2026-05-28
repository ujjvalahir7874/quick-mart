<?php
function testGetCategoryImage($image_url, $category_name) {
    // Decode entities and normalize
    $name = strtolower(html_entity_decode(trim($category_name)));
    
    // Priority 1: Use database image_url if it's a valid external URL
    if (!empty($image_url) && strpos($image_url, 'http') === 0) {
        return "Priority 1: " . $image_url;
    }

    // Priority 2: Use local image if it looks like a valid path
    if (!empty($image_url) && 
        $image_url !== 'null' && 
        $image_url !== 'undefined' && 
        (strpos($image_url, '/') !== false || strpos($image_url, '.') !== false) &&
        strtolower(trim($image_url)) !== $name) {
        return "Priority 2: " . $image_url;
    }

    // Define fallbacks
    $fallbacks = [
        'fruit' => [
            'url' => 'FRUIT_URL',
            'keywords' => ['fruit', 'apple', 'banana', 'mango', 'orange']
        ],
        'vegetable' => [
            'url' => 'VEG_URL',
            'keywords' => ['vegetable', 'veg', 'sabzi', 'carrot', 'spinach', 'potato', 'onion']
        ],
        'dairy' => [
            'url' => 'DAIRY_URL',
            'keywords' => ['dairy', 'milk', 'cheese', 'paneer', 'yogurt', 'butter']
        ],
        'bakery' => [
            'url' => 'BAKERY_URL',
            'keywords' => ['bakery', 'bread', 'cake', 'biscuit', 'pastry']
        ],
        'meat' => [
            'url' => 'MEAT_URL',
            'keywords' => ['meat', 'chicken', 'fish', 'seafood', 'mutton', 'beef']
        ],
        'beverage' => [
            'url' => 'BEV_URL',
            'keywords' => ['beverage', 'drink', 'juice', 'soda', 'tea', 'coffee', 'water']
        ],
        'snack' => [
            'url' => 'SNACK_URL',
            'keywords' => ['snack', 'chips', 'namkeen', 'kurkure', 'biscuit']
        ],
        'sweet' => [
            'url' => 'SWEET_URL',
            'keywords' => ['sweet', 'mithai', 'dessert', 'candy', 'chocolate']
        ]
    ];

    // Priority 3: Keyword-based fallbacks
    foreach ($fallbacks as $type => $data) {
        foreach ($data['keywords'] as $keyword) {
            if (strpos($name, $keyword) !== false) {
                return "Priority 3 ($type): " . $data['url'];
            }
        }
    }

    return "Priority 4: DEFAULT_URL";
}

$test_cases = [
    ['', 'Vegetables'],
    ['', 'Snacks & Sweets'],
    ['vegetables.jpg', 'Vegetables'], // Potential broken path
    ['snacks.png', 'Snacks & Sweets'], // Potential broken path
    ['null', 'Vegetables'],
    ['', 'Fruits'],
    ['', 'Meat & Seafood']
];

foreach ($test_cases as $case) {
    echo "Testing: [URL: '{$case[0]}', Name: '{$case[1]}']\n";
    echo "Result: " . testGetCategoryImage($case[0], $case[1]) . "\n\n";
}
