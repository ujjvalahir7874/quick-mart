<?php

if (!function_exists('offerHasBogoRule')) {
    function offerHasBogoRule(array $offer): bool
    {
        if (strtoupper((string)($offer['offer_type'] ?? '')) === 'BOGO') {
            return true;
        }

        $haystack = strtolower(trim(implode(' ', [
            $offer['title'] ?? '',
            $offer['subtitle'] ?? '',
            $offer['discount_text'] ?? '',
            $offer['badge_text'] ?? ''
        ])));

        return strpos($haystack, 'buy 1 get 1') !== false
            || strpos($haystack, 'buy one get one') !== false
            || strpos($haystack, 'bogo') !== false;
    }
}

if (!function_exists('extractOfferProductId')) {
    function extractOfferProductId(string $linkUrl): int
    {
        $query = parse_url($linkUrl, PHP_URL_QUERY);
        if (!$query) {
            return 0;
        }

        parse_str($query, $params);
        return isset($params['id']) ? (int)$params['id'] : 0;
    }
}

if (!function_exists('normalizeBogoOffer')) {
    function normalizeBogoOffer(array $offer): ?array
    {
        if (!offerHasBogoRule($offer)) {
            return null;
        }

        $offerType = strtoupper((string)($offer['offer_type'] ?? ''));
        $buyQty = max(1, (int)($offer['buy_quantity'] ?? 0));
        $getQty = max(1, (int)($offer['get_quantity'] ?? 0));
        $scope = (string)($offer['offer_scope'] ?? 'same_product');
        $applicableProductId = (int)($offer['applicable_product_id'] ?? 0);
        $freeProductId = (int)($offer['free_product_id'] ?? 0);

        if ($offerType !== 'BOGO') {
            $applicableProductId = $applicableProductId > 0 ? $applicableProductId : extractOfferProductId((string)($offer['link_url'] ?? ''));
            $freeProductId = $freeProductId > 0 ? $freeProductId : $applicableProductId;
            $buyQty = 1;
            $getQty = 1;
            $scope = 'same_product';
        }

        if ($applicableProductId <= 0) {
            return null;
        }

        if ($scope !== 'different_product') {
            $scope = 'same_product';
            $freeProductId = $applicableProductId;
        } elseif ($freeProductId <= 0) {
            return null;
        }

        $label = trim((string)($offer['discount_text'] ?: $offer['badge_text'] ?: ('Buy ' . $buyQty . ' Get ' . $getQty . ' Free')));
        $maxFreeItems = isset($offer['max_free_items']) && $offer['max_free_items'] !== null && $offer['max_free_items'] !== ''
            ? max(0, (int)$offer['max_free_items'])
            : null;

        return [
            'id' => (int)$offer['id'],
            'label' => $label,
            'buy_quantity' => $buyQty,
            'get_quantity' => $getQty,
            'offer_scope' => $scope,
            'applicable_product_id' => $applicableProductId,
            'free_product_id' => $freeProductId,
            'max_free_items' => $maxFreeItems
        ];
    }
}

if (!function_exists('getActiveBogoOffers')) {
    function getActiveBogoOffers(PDO $pdo): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT id, title, subtitle, discount_text, badge_text, link_url, offer_type,
                   buy_quantity, get_quantity, offer_scope, applicable_product_id,
                   free_product_id, max_free_items
            FROM offers
            WHERE is_active = 1
              AND (start_date IS NULL OR start_date <= ?)
              AND (end_date IS NULL OR end_date >= ?)
            ORDER BY id DESC
        ");
        $stmt->execute([$today, $today]);

        $offers = [];
        foreach ($stmt->fetchAll() as $offer) {
            $normalized = normalizeBogoOffer($offer);
            if ($normalized !== null) {
                $offers[] = $normalized;
            }
        }

        $cache = $offers;
        return $cache;
    }
}

if (!function_exists('calculateOfferFreeUnits')) {
    function calculateOfferFreeUnits(int $qualifyingQty, array $offer): int
    {
        $bundleSize = $offer['buy_quantity'] + $offer['get_quantity'];
        if ($qualifyingQty < $offer['buy_quantity'] || $bundleSize <= 0) {
            return 0;
        }

        $freeQty = intdiv($qualifyingQty, $bundleSize) * $offer['get_quantity'];
        if ($offer['max_free_items'] !== null) {
            $freeQty = min($freeQty, (int)$offer['max_free_items']);
        }

        return max(0, $freeQty);
    }
}

if (!function_exists('calculateUnlockQuantity')) {
    function calculateUnlockQuantity(int $qualifyingQty, array $offer): int
    {
        if ($qualifyingQty < $offer['buy_quantity']) {
            return $offer['buy_quantity'] - $qualifyingQty;
        }

        $bundleSize = $offer['buy_quantity'] + $offer['get_quantity'];
        $remainder = $qualifyingQty % $bundleSize;
        if ($remainder < $offer['buy_quantity']) {
            return $offer['buy_quantity'] - $remainder;
        }

        return 0;
    }
}

if (!function_exists('buildFreeAllocationMap')) {
    function buildFreeAllocationMap(array $sessionCart, array $offers): array
    {
        $lineItems = [];
        $qtyByProduct = [];

        foreach ($sessionCart as $cartKey => $qty) {
            $qty = (int)$qty;
            if ($qty <= 0) {
                continue;
            }

            $parts = explode('_', (string)$cartKey);
            $productId = (int)$parts[0];
            $lineItems[] = [
                'cart_key' => $cartKey,
                'product_id' => $productId,
                'qty' => $qty
            ];
            $qtyByProduct[$productId] = ($qtyByProduct[$productId] ?? 0) + $qty;
        }

        $freeQtyByCartKey = [];
        $lineOfferMeta = [];

        foreach ($offers as $offer) {
            $qualifyingQty = (int)($qtyByProduct[$offer['applicable_product_id']] ?? 0);
            $freeUnits = calculateOfferFreeUnits($qualifyingQty, $offer);
            if ($freeUnits <= 0) {
                continue;
            }

            $targetProductId = $offer['offer_scope'] === 'different_product'
                ? $offer['free_product_id']
                : $offer['applicable_product_id'];

            foreach ($lineItems as $lineItem) {
                if ($lineItem['product_id'] !== $targetProductId || $freeUnits <= 0) {
                    continue;
                }

                $lineFreeQty = min($lineItem['qty'], $freeUnits);
                if ($lineFreeQty <= 0) {
                    continue;
                }

                $cartKey = $lineItem['cart_key'];
                $freeQtyByCartKey[$cartKey] = ($freeQtyByCartKey[$cartKey] ?? 0) + $lineFreeQty;
                $lineOfferMeta[$cartKey] = $offer;
                $freeUnits -= $lineFreeQty;
            }
        }

        return [
            'free_qty_by_cart_key' => $freeQtyByCartKey,
            'offer_meta_by_cart_key' => $lineOfferMeta,
            'qualifying_qty_by_product' => $qtyByProduct
        ];
    }
}

if (!function_exists('calculateCartPricing')) {
    function calculateCartPricing(PDO $pdo, array $sessionCart, ?array $appliedCoupon = null): array
    {
        $cartItems = [];
        $invalidCartKeys = [];
        $subtotal = 0.0;
        $subtotalBeforeBogo = 0.0;
        $totalTax = 0.0;
        $totalTaxBeforeBogo = 0.0;
        $totalTaxPercentage = 0.0;
        $bogoDiscount = 0.0;

        $bogoOffers = getActiveBogoOffers($pdo);
        $allocation = buildFreeAllocationMap($sessionCart, $bogoOffers);
        $freeQtyByCartKey = $allocation['free_qty_by_cart_key'];
        $offerMetaByCartKey = $allocation['offer_meta_by_cart_key'];
        $qualifyingQtyByProduct = $allocation['qualifying_qty_by_product'];

        foreach ($sessionCart as $cartKey => $qty) {
            $qty = (int)$qty;
            if ($qty <= 0) {
                $invalidCartKeys[] = $cartKey;
                continue;
            }

            $parts = explode('_', (string)$cartKey);
            $productId = (int)$parts[0];
            $variantId = isset($parts[1]) ? (int)$parts[1] : 0;

            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'Active'");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product) {
                $invalidCartKeys[] = $cartKey;
                continue;
            }

            $sizeName = '';
            $price = (float)$product['price'];
            $discountPrice = $product['discount_price'] !== null ? (float)$product['discount_price'] : null;
            $stock = (int)$product['stock_quantity'];

            if ($variantId > 0) {
                $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE id = ? AND product_id = ?");
                $stmt->execute([$variantId, $productId]);
                $variant = $stmt->fetch();

                if ($variant) {
                    $sizeName = (string)$variant['size_name'];
                    $price = (float)$variant['price'];
                    $discountPrice = $variant['discount_price'] !== null ? (float)$variant['discount_price'] : null;
                    $stock = (int)$variant['stock_quantity'];
                    if (isExpiredDateValue($variant['expiry_date'] ?? null)) {
                        $invalidCartKeys[] = $cartKey;
                        continue;
                    }
                }
            }

            if (isExpiredDateValue($product['expiry_date'] ?? null)) {
                $invalidCartKeys[] = $cartKey;
                continue;
            }

            $currentPrice = ($discountPrice !== null && $discountPrice > 0) ? $discountPrice : $price;
            $offer = $offerMetaByCartKey[$cartKey] ?? null;
            $freeQty = min($qty, (int)($freeQtyByCartKey[$cartKey] ?? 0));
            $payableQty = max(0, $qty - $freeQty);
            $lineOriginalInclusive = $currentPrice * $qty;
            $lineTotalInclusive = $currentPrice * $payableQty;
            $lineBogoDiscount = $lineOriginalInclusive - $lineTotalInclusive;

            $taxPercentage = isset($product['tax_percentage']) ? (float)$product['tax_percentage'] : 0.0;
            $totalTaxPercentage += $taxPercentage;

            $lineTax = $lineTotalInclusive - ($lineTotalInclusive / (1 + ($taxPercentage / 100)));
            $lineTaxBeforeBogo = $lineOriginalInclusive - ($lineOriginalInclusive / (1 + ($taxPercentage / 100)));
            $lineBase = $lineTotalInclusive - $lineTax;
            $lineBaseBeforeBogo = $lineOriginalInclusive - $lineTaxBeforeBogo;

            $subtotal += $lineBase;
            $subtotalBeforeBogo += $lineBaseBeforeBogo;
            $totalTax += $lineTax;
            $totalTaxBeforeBogo += $lineTaxBeforeBogo;
            $bogoDiscount += $lineBogoDiscount;

            $unlockFreeQty = 0;
            if ($offer && $lineBogoDiscount <= 0) {
                $unlockFreeQty = calculateUnlockQuantity((int)($qualifyingQtyByProduct[$offer['applicable_product_id']] ?? 0), $offer);
            }

            $cartItems[] = array_merge($product, [
                'cart_key' => $cartKey,
                'variant_id' => $variantId,
                'qty' => $qty,
                'size_name' => $sizeName,
                'price' => $price,
                'discount_price' => $discountPrice,
                'stock_quantity' => $stock,
                'display_price' => $currentPrice,
                'total' => $lineTotalInclusive,
                'original_total' => $lineOriginalInclusive,
                'free_qty' => $freeQty,
                'payable_qty' => $payableQty,
                'bogo_discount' => $lineBogoDiscount,
                'bogo_label' => $offer['label'] ?? '',
                'unlock_free_qty' => $unlockFreeQty,
                'offer_scope' => $offer['offer_scope'] ?? '',
                'item_tax' => $lineTax,
                'item_base' => $lineBase
            ]);
        }

        $itemCount = count($cartItems);
        $avgTaxPercentage = $itemCount > 0 ? $totalTaxPercentage / $itemCount : 0.0;
        $totalInclusiveSubtotal = $subtotal + $totalTax;
        $totalInclusiveBeforeBogo = $subtotalBeforeBogo + $totalTaxBeforeBogo;
        $deliveryCharge = ($totalInclusiveSubtotal < 250 && $totalInclusiveSubtotal > 0) ? 40.0 : 0.0;
        $grandTotalBeforeCoupon = $totalInclusiveSubtotal + $deliveryCharge;
        $grandTotal = $grandTotalBeforeCoupon;
        $discountAmount = 0.0;
        $couponCode = '';
        $couponStillValid = false;
        $couponId = null;

        if (!empty($appliedCoupon)) {
            $couponCode = (string)($appliedCoupon['code'] ?? '');
            if ($grandTotalBeforeCoupon >= (float)($appliedCoupon['min_purchase'] ?? 0)) {
                $couponStillValid = true;
                $couponId = isset($appliedCoupon['id']) ? (int)$appliedCoupon['id'] : null;
                if (strtolower((string)($appliedCoupon['discount_type'] ?? '')) === 'percentage') {
                    $discountAmount = $grandTotalBeforeCoupon * ((float)$appliedCoupon['discount_value'] / 100);
                } else {
                    $discountAmount = (float)($appliedCoupon['discount_value'] ?? 0);
                }
                $grandTotal = max(0, $grandTotalBeforeCoupon - $discountAmount);
            }
        }

        return [
            'cart_items' => $cartItems,
            'invalid_cart_keys' => $invalidCartKeys,
            'subtotal' => $subtotal,
            'subtotal_before_bogo' => $subtotalBeforeBogo,
            'total_tax' => $totalTax,
            'total_tax_before_bogo' => $totalTaxBeforeBogo,
            'avg_tax_percentage' => $avgTaxPercentage,
            'bogo_discount' => $bogoDiscount,
            'total_inclusive_subtotal' => $totalInclusiveSubtotal,
            'total_inclusive_before_bogo' => $totalInclusiveBeforeBogo,
            'delivery_charge' => $deliveryCharge,
            'grand_total_before_coupon' => $grandTotalBeforeCoupon,
            'grand_total' => $grandTotal,
            'discount_amount' => $discountAmount,
            'coupon_code' => $couponCode,
            'coupon_still_valid' => $couponStillValid,
            'coupon_id' => $couponId
        ];
    }
}
