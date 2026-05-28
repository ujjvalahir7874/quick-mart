<?php
require_once 'includes/header.php';

$recipe_id = $_GET['id'] ?? 0;

// Fetch recipe details
$stmt = $pdo->prepare("SELECT * FROM recipes WHERE id = ?");
$stmt->execute([$recipe_id]);
$recipe = $stmt->fetch();

if (!$recipe) {
    echo "<div class='container py-5 text-center'><h3>Recipe not found.</h3><a href='recipes.php' class='btn btn-success mt-3'>Back to Recipes</a></div>";
    require_once 'includes/footer.php';
    exit;
}

// Fetch ingredients with product details
$stmt = $pdo->prepare("
    SELECT ri.*, p.name as product_name, p.price, p.discount_price, p.image_url as product_image, p.stock_quantity 
    FROM recipe_ingredients ri 
    JOIN products p ON ri.product_id = p.id 
    WHERE ri.recipe_id = ?
");
$stmt->execute([$recipe_id]);
$ingredients = $stmt->fetchAll();
?>

<div class="bg-success py-5 mb-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
    <div class="position-absolute top-0 start-0 w-100 h-100" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png'); opacity: 0.1;"></div>
    <div class="container position-relative">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 animate__animated animate__fadeIn">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="index.php" class="text-white text-opacity-75 text-decoration-none small fw-600">Home</a></li>
                        <li class="breadcrumb-item"><a href="recipes.php" class="text-white text-opacity-75 text-decoration-none small fw-600">Smart Recipes</a></li>
                        <li class="breadcrumb-item active text-white small fw-600" aria-current="page"><?php echo htmlspecialchars($recipe['name']); ?></li>
                    </ol>
                </nav>
                <h1 class="text-white fw-800 mb-1 display-5"><?php echo htmlspecialchars($recipe['name']); ?></h1>
                <p class="text-white text-opacity-75 mb-0 fw-600">Fresh ingredients, delicious results</p>
            </div>
            <div class="d-flex gap-3">
                <div class="bg-white bg-opacity-10 rounded-4 px-4 py-3 border border-white border-opacity-25 backdrop-blur">
                    <div class="smaller text-white text-opacity-75 fw-800 tracking-wider mb-1">PREP TIME</div>
                    <div class="text-white fw-800"><i class="bi bi-clock-fill me-2"></i><?php echo strtoupper($recipe['prep_time']); ?></div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-4 px-4 py-3 border border-white border-opacity-25 backdrop-blur">
                    <div class="smaller text-white text-opacity-75 fw-800 tracking-wider mb-1">SERVINGS</div>
                    <div class="text-white fw-800"><i class="bi bi-people-fill me-2"></i><?php echo $recipe['servings']; ?> PERSONS</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">
    <div class="row g-5">
        <!-- Recipe Header & Image -->
        <div class="col-lg-7 animate__animated animate__fadeInLeft">
            <div class="position-relative rounded-4 overflow-hidden shadow-lg mb-5 group">
                <img src="<?php echo getRecipeImage($recipe['image_url'], $recipe['name']); ?>" class="img-fluid w-100 transition-zoom" alt="<?php echo $recipe['name']; ?>" style="max-height: 550px; object-fit: cover;">
                <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark opacity-0 transition-hover" style="z-index: 1;"></div>
            </div>
            
            <div class="recipe-content">
                <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden bg-white">
                    <div class="card-header bg-white border-0 p-4 pb-0">
                        <h4 class="fw-800 mb-0 d-flex align-items-center text-dark">
                            <span class="bg-success-subtle p-2 rounded-3 me-3">
                                <i class="bi bi-info-circle-fill text-success"></i>
                            </span>
                            About this Recipe
                        </h4>
                    </div>
                    <div class="card-body p-4 pt-3">
                        <p class="lead text-muted mb-0 fw-600"><?php echo htmlspecialchars($recipe['description']); ?></p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden bg-white">
                    <div class="card-header bg-white border-0 p-4 pb-0">
                        <h4 class="fw-800 mb-0 d-flex align-items-center text-dark">
                            <span class="bg-success-subtle p-2 rounded-3 me-3">
                                <i class="bi bi-list-check text-success"></i>
                            </span>
                            Instructions
                        </h4>
                    </div>
                    <div class="card-body p-4 pt-3">
                        <div class="recipe-instructions lh-lg text-muted">
                            <?php 
                                $instructions = explode("\n", $recipe['instructions']);
                                foreach($instructions as $index => $step): 
                                    if(trim($step)):
                            ?>
                                <div class="d-flex mb-4 align-items-start transition-hover p-3 rounded-4 hover-bg-light">
                                    <div class="me-4 mt-1">
                                        <span class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center fw-800 shadow-sm" style="width: 40px; height: 40px; flex-shrink: 0; font-size: 1.1rem;">
                                            <?php echo $index + 1; ?>
                                        </span>
                                    </div>
                                    <div class="fw-600 fs-5 text-dark text-opacity-75"><?php echo nl2br(htmlspecialchars(trim($step))); ?></div>
                                </div>
                            <?php 
                                    endif;
                                endforeach; 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ingredients Sidebar -->
        <div class="col-lg-5 animate__animated animate__fadeInRight">
            <div class="card border-0 shadow-lg rounded-4 sticky-top bg-white" style="top: 100px;">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-800 mb-0">Ingredients</h4>
                        <span class="badge bg-success-subtle text-success rounded-4 border border-success-subtle px-3 py-2 fw-800 tracking-wider smaller"><?php echo count($ingredients); ?> ITEMS</span>
                    </div>
                    
                    <form id="recipe-ingredients-form">
                        <div class="list-group list-group-flush mb-4 custom-scrollbar pe-2" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($ingredients as $ingredient): ?>
                                <div class="list-group-item bg-transparent border-0 px-0 py-3 d-flex align-items-center transition-hover rounded-4 px-3 mb-2 hover-bg-light <?php echo $ingredient['stock_quantity'] <= 0 ? 'opacity-50' : ''; ?>">
                                    <div class="form-check custom-checkbox">
                                        <input class="form-check-input ingredient-check shadow-none cursor-pointer" type="checkbox" 
                                               value="<?php echo $ingredient['product_id']; ?>" 
                                               id="ing-<?php echo $ingredient['product_id']; ?>" 
                                               style="width: 24px; height: 24px;"
                                               <?php echo $ingredient['stock_quantity'] > 0 ? 'checked' : 'disabled'; ?>>
                                    </div>
                                    <div class="ms-3 flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-0 fw-800 text-dark"><?php echo htmlspecialchars($ingredient['product_name']); ?></h6>
                                                <div class="d-flex align-items-center gap-2 mt-1">
                                                    <small class="text-muted fw-800 smaller tracking-wider text-uppercase"><?php echo htmlspecialchars($ingredient['quantity_text']); ?></small>
                                                    <?php if ($ingredient['stock_quantity'] <= 0): ?>
                                                        <span class="badge bg-danger-subtle text-danger rounded-4 fw-800 tracking-wider" style="font-size: 0.6rem;">SOLD OUT</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="text-end bg-success bg-opacity-10 p-2 rounded-4 px-3 border border-success border-opacity-10">
                                                <?php if (!empty($ingredient['discount_price']) && $ingredient['discount_price'] > 0): ?>
                                                    <span class="fw-800 text-success d-block fs-6">₹<?php echo number_format($ingredient['discount_price'], 2); ?></span>
                                                    <small class="text-muted text-decoration-line-through smaller fw-600 opacity-50">₹<?php echo number_format($ingredient['price'], 2); ?></small>
                                                <?php else: ?>
                                                    <span class="fw-800 text-success fs-6">₹<?php echo number_format($ingredient['price'], 2); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" id="add-all-to-cart" class="btn btn-success btn-lg w-100 rounded-4 py-4 fw-800 shadow-lg transition-hover tracking-wider">
                            <i class="bi bi-bag-plus-fill me-2 fs-4"></i> ADD SELECTED TO CART
                        </button>
                        <div class="text-center mt-4">
                            <p class="text-muted smaller fw-800 mb-0 opacity-75">
                                <i class="bi bi-shield-check-fill text-success me-2"></i> ALL INGREDIENTS ARE ORGANIC & FRESH
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --transition-base: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.tracking-wider { letter-spacing: 0.8px; }

.transition-hover {
    transition: var(--transition-base);
}

.transition-hover:hover {
    transform: translateY(-3px);
}

.hover-bg-light:hover {
    background-color: #f8f9fa !important;
}

.transition-zoom {
    transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.group:hover .transition-zoom {
    transform: scale(1.05);
}

.group:hover .bg-dark {
    opacity: 0.1 !important;
}

.backdrop-blur {
    backdrop-filter: blur(10px);
}

.smaller { font-size: 0.7rem; }

.custom-checkbox .form-check-input:checked {
    background-color: #198754;
    border-color: #198754;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #198754;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #146c43;
}

.recipe-instructions {
    line-height: 1.8;
}

.sticky-top {
    z-index: 100;
}
</style>

<script>
document.getElementById('add-all-to-cart').addEventListener('click', function() {
    const checkedIngredients = Array.from(document.querySelectorAll('.ingredient-check:checked')).map(cb => cb.value);
    
    if (checkedIngredients.length === 0) {
        alert('Please select at least one ingredient.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_multiple');
    checkedIngredients.forEach(id => formData.append('product_ids[]', id));

    fetch('manage_cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count in header
            const cartCountElement = document.querySelector('.cart-count');
            if (cartCountElement) {
                cartCountElement.textContent = data.total_items;
            }
            
            // Show success message
            const btn = document.getElementById('add-all-to-cart');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check2-circle me-2"></i> Added Successfully!';
            btn.classList.replace('btn-success', 'btn-dark');
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.replace('btn-dark', 'btn-success');
            }, 3000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Something went wrong. Please try again.');
    });
});
</script>

<style>
.recipe-instructions {
    line-height: 1.8;
}
.ingredient-check:checked + label {
    text-decoration: line-through;
    color: #6c757d;
}
.sticky-top {
    z-index: 100;
}
</style>

<?php require_once 'includes/footer.php'; ?>
