<?php
// Persist unique, relevant images for ALL products (overrides existing image_url)
// Usage: php persist_unique_product_images.php

require_once 'backend/config.php';

function println($msg) { echo $msg . "\n"; }

try {
	$pdo = getDBConnection();
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	println("Assigning unique, relevant images to all products...");

	// Fetch all products
	$stmt = $pdo->prepare("SELECT id, name, category FROM products ORDER BY id ASC");
	$stmt->execute();
	$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (!$products) {
		println("No products found.");
		exit(0);
	}

	$usedImageUrls = [];
	$updateStmt = $pdo->prepare("UPDATE products SET image_url = ? WHERE id = ?");
	$updated = 0;

	foreach ($products as $product) {
		$productId = (int)$product['id'];
		$name = (string)$product['name'];
		$category = (string)$product['category'];

		$candidates = generateImageCandidates($name, $category);
		$chosen = pickDeterministicUnique($candidates, (string)$productId . '|' . $name, $usedImageUrls);

		$updateStmt->execute([$chosen, $productId]);
		$updated++;
		println("✓ Product #{$productId} - '{$name}': " . $chosen);
	}

	println("");
	println("🎉 Updated {$updated} products with unique, relevant images.");

} catch (PDOException $e) {
	println("❌ Database error: " . $e->getMessage());
} catch (Throwable $e) {
	println("❌ Error: " . $e->getMessage());
}

// Generate multiple candidate image URLs based on product name and category
function generateImageCandidates(string $productName, string $category): array {
	$name = strtolower($productName);
	$category = strtolower($category);

	$images = [
		'electronics' => [
			'laptop' => [
				'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=300&h=300&fit=crop'
			],
			'smartphone' => [
				'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?w=300&h=300&fit=crop'
			],
			'phone' => [
				'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?w=300&h=300&fit=crop'
			],
			'headphones' => [
				'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1518441310475-1573b1cf9f71?w=300&h=300&fit=crop'
			],
			'speaker' => [
				'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1585386959984-a41552231658?w=300&h=300&fit=crop'
			],
			'watch' => [
				'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1522312346375-d1a52e2b99b3?w=300&h=300&fit=crop'
			],
			'tablet' => [
				'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1510552776732-01acc9a4c1c5?w=300&h=300&fit=crop'
			],
			'camera' => [
				'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1519183071298-a2962be96f83?w=300&h=300&fit=crop'
			],
			'keyboard' => [
				'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=300&h=300&fit=crop'
			],
			'mouse' => [
				'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=300&h=300&fit=crop'
			],
			'gaming' => [
				'https://images.unsplash.com/photo-1542751371-adc38448a05e?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1603484477859-abe6a73f936d?w=300&h=300&fit=crop'
			],
			'default' => [
				'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1518779578993-ec3579fee39f?w=300&h=300&fit=crop'
			]
		],
		'fashion' => [
			'shoes' => [
				'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?w=300&h=300&fit=crop'
			],
			'backpack' => [
				'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1519669417670-68775a50919e?w=300&h=300&fit=crop'
			],
			'sunglasses' => [
				'https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=300&h=300&fit=crop'
			],
			'watch' => [
				'https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1524805444758-089113d48a6d?w=300&h=300&fit=crop'
			],
			'jacket' => [
				'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1520975916090-3105956dac38?w=300&h=300&fit=crop'
			],
			'shirt' => [
				'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1541099649105-f69ad21f3246?w=300&h=300&fit=crop'
			],
			'dress' => [
				'https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=300&h=300&fit=crop'
			],
			'jeans' => [
				'https://images.unsplash.com/photo-1542272604-787c3835535d?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1514996937319-344454492b37?w=300&h=300&fit=crop'
			],
			'default' => [
				'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1539008835657-9e8e9680c956?w=300&h=300&fit=crop'
			]
		],
		'home & kitchen' => [
			'coffee' => [
				'https://images.unsplash.com/photo-1517668808822-9ebb02f2a0e6?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?w=300&h=300&fit=crop'
			],
			'lamp' => [
				'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1484101403633-562f891dc89a?w=300&h=300&fit=crop'
			],
			'chair' => [
				'https://images.unsplash.com/photo-1567538096630-e0c55bd6374c?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?w=300&h=300&fit=crop'
			],
			'table' => [
				'https://images.unsplash.com/photo-1533090481720-856c6e3c1fdc?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1505691938895-1758d7feb511?w=300&h=300&fit=crop'
			],
			'sofa' => [
				'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1484101403633-562f891dc89a?w=300&h=300&fit=crop'
			],
			'default' => [
				'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1449247613801-ab06418e2861?w=300&h=300&fit=crop'
			]
		],
		'sports' => [
			'yoga' => [
				'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1518611012118-696072aa579a?w=300&h=300&fit=crop'
			],
			'dumbbells' => [
				'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1517960413843-0aee8e2b3285?w=300&h=300&fit=crop'
			],
			'basketball' => [
				'https://images.unsplash.com/photo-1546519638-68e109498ffc?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1517649763962-0c623066013b?w=300&h=300&fit=crop'
			],
			'tennis' => [
				'https://images.unsplash.com/photo-1551698618-1dfe5d97d256?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1505664194779-8beaceb93744?w=300&h=300&fit=crop'
			],
			'bike' => [
				'https://images.unsplash.com/photo-1532298229144-0ec0c57515c7?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1520975928316-56f6b42e9fbd?w=300&h=300&fit=crop'
			],
			'default' => [
				'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1517649763962-0c623066013b?w=300&h=300&fit=crop'
			]
		],
		'books' => [
			'book' => [
				'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1519681393784-d120267933ba?w=300&h=300&fit=crop'
			],
			'notebook' => [
				'https://images.unsplash.com/photo-1531346680769-a1d79b57de5c?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1501877008226-4fca48ee50c1?w=300&h=300&fit=crop'
			],
			'stationery' => [
				'https://images.unsplash.com/photo-1531346680769-a1d79b57de5c?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1515378791036-0648a3ef77b2?w=300&h=300&fit=crop'
			],
			'default' => [
				'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=300&h=300&fit=crop',
				'https://images.unsplash.com/photo-1457694587812-e8bf29a43845?w=300&h=300&fit=crop'
			]
		]
	];

	$candidates = [];
	if (isset($images[$category])) {
		$cat = $images[$category];
		foreach ($cat as $keyword => $urls) {
			if ($keyword === 'default') { continue; }
			if (strpos($name, $keyword) !== false && is_array($urls)) {
				$candidates = array_merge($candidates, $urls);
			}
		}
		if (empty($candidates) && isset($cat['default']) && is_array($cat['default'])) {
			$candidates = array_merge($candidates, $cat['default']);
		}
	}

	if (empty($candidates)) {
		$candidates = [
			'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=300&h=300&fit=crop',
			'https://images.unsplash.com/photo-1518779578993-ec3579fee39f?w=300&h=300&fit=crop'
		];
	}

	return array_values(array_unique($candidates));
}

// Deterministically pick a URL from candidates, avoiding duplicates in this run
function pickDeterministicUnique(array $candidates, string $seed, array &$used): string {
	$index = hashToIndex($seed, count($candidates));
	$choice = $candidates[$index];
	if (isset($used[$choice])) {
		for ($i = 1; $i < count($candidates); $i++) {
			$alt = $candidates[($index + $i) % count($candidates)];
			if (!isset($used[$alt])) { $choice = $alt; break; }
		}
	}
	$used[$choice] = true;
	return $choice;
}

function hashToIndex(string $seed, int $mod): int {
	if ($mod <= 0) return 0;
	$h = 0;
	for ($i = 0; $i < strlen($seed); $i++) {
		$h = (($h << 5) - $h) + ord($seed[$i]);
		$h &= 0x7fffffff; // keep positive 31-bit
	}
	return $h % $mod;
}

?>


