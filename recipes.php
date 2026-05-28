<?php
require_once 'includes/header.php';

$recipes = $pdo->query("SELECT * FROM recipes ORDER BY created_at DESC")->fetchAll();
?>

<div class="bg-success py-5 mb-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
    <div class="position-absolute top-0 start-0 w-100 h-100" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png'); opacity: 0.1;"></div>
    <div class="container position-relative">
        <div class="text-center animate__animated animate__fadeIn">
            <nav aria-label="breadcrumb" class="d-flex justify-content-center">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="index.php" class="text-white text-opacity-75 text-decoration-none small fw-600">Home</a></li>
                    <li class="breadcrumb-item active text-white small fw-600" aria-current="page">Smart Recipes</li>
                </ol>
            </nav>
            <h1 class="text-white fw-800 mb-2 display-4">Cook with <span class="text-white opacity-75">Love</span></h1>
            <p class="text-white text-opacity-75 mb-0 fw-600 mx-auto" style="max-width: 700px;">Discover delicious, healthy meals curated by experts. Add all fresh ingredients to your cart with just one click and start cooking!</p>
        </div>
    </div>
</div>

<div class="container mb-5">
    <div class="row g-4">
        <?php if (empty($recipes)): ?>
            <div class="col-12 text-center py-5 animate__animated animate__fadeInUp">
                <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-4 shadow-sm animate__animated animate__pulse animate__infinite" style="width: 140px; height: 140px;">
                    <i class="bi bi-journal-text text-success display-1"></i>
                </div>
                <h2 class="fw-800 mb-2 display-6">No recipes found yet</h2>
                <p class="text-muted mb-5 fs-5 fw-600">We're currently cooking up some amazing recipes for you. Check back soon!</p>
                <a href="products.php" class="btn btn-success rounded-4 px-5 py-3 fw-800 shadow-lg transition-hover btn-lg tracking-wider">
                    START SHOPPING <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($recipes as $index => $recipe): ?>
                <div class="col-md-6 col-lg-4 animate__animated animate__fadeInUp" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                    <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden transition-hover recipe-card bg-white tilt-card">
                        <div class="position-relative overflow-hidden" style="height: 280px;">
                            <img src="<?php echo getRecipeImage($recipe['image_url'], $recipe['name']); ?>" class="card-img-top h-100 transition-zoom" alt="<?php echo $recipe['name']; ?>" style="object-fit: cover;">
                            <div class="position-absolute top-0 end-0 m-3" style="z-index: 2;">
                                <span class="badge bg-white text-dark shadow-sm px-3 py-2 rounded-4 fw-800 smaller tracking-wider">
                                    <i class="bi bi-star-fill text-warning me-1"></i> NEW
                                </span>
                            </div>
                            <div class="position-absolute bottom-0 start-0 m-3" style="z-index: 2;">
                                <span class="badge bg-success shadow-lg px-3 py-2 rounded-4 fw-800 smaller tracking-wider">
                                    <i class="bi bi-clock-fill me-1"></i> <?php echo strtoupper($recipe['prep_time']); ?>
                                </span>
                            </div>
                            <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark opacity-0 transition-hover overlay-gradient" style="z-index: 1;"></div>
                        </div>
                        <div class="card-body p-4">
                            <h4 class="fw-800 mb-2 h5 text-dark"><?php echo htmlspecialchars($recipe['name']); ?></h4>
                            <p class="text-muted small mb-4 line-clamp-2 fw-600" style="height: 40px;">
                                <?php echo htmlspecialchars($recipe['description']); ?>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-success-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                        <i class="bi bi-people-fill text-success"></i>
                                    </div>
                                    <span class="text-muted small fw-800">Serves <?php echo $recipe['servings']; ?></span>
                                </div>
                                <a href="recipe-details.php?id=<?php echo $recipe['id']; ?>" class="btn btn-success rounded-4 px-4 py-2 fw-800 transition-hover shadow-sm">
                                    VIEW RECIPE <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
:root {
    --transition-base: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.tracking-wider { letter-spacing: 0.8px; }

.transition-hover {
    transition: var(--transition-base);
}

.transition-hover:hover {
    transform: translateY(-8px);
    box-shadow: 0 1.5rem 4rem rgba(0,0,0,0.12)!important;
}

.transition-zoom {
    transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.recipe-card:hover .transition-zoom {
    transform: scale(1.1) rotate(1deg);
}

.recipe-card:hover .overlay-gradient {
    opacity: 0.2 !important;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;  
    overflow: hidden;
}

.smaller { font-size: 0.7rem; }

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 8px;
}
::-webkit-scrollbar-track {
    background: #f1f1f1;
}
::-webkit-scrollbar-thumb {
    background: #198754;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #157347;
}
</style>

<?php require_once 'includes/footer.php'; ?>