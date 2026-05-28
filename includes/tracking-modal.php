<!-- Tracking Modal -->
<div class="modal fade" id="trackingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header border-0 bg-light p-4">
                <div>
                    <h5 class="modal-title fw-800" id="trackingModalLabel">Live Delivery Tracking</h5>
                    <p class="text-muted small mb-0 fw-bold">Order #<span id="track_order_id"></span> • <span id="track_agent_name" class="text-success"></span></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 position-relative" style="height: 400px;">
                 <!-- Mock Map Interface -->
                 <div id="map_container" class="w-100 h-100 bg-light position-relative" style="background: #e9ecef;">
                     <div class="position-absolute top-50 start-50 translate-middle text-center">
                        <div class="spinner-grow text-success mb-2" role="status"></div>
                        <p class="small fw-bold text-muted">Locating delivery partner...</p>
                     </div>
                 </div>
            </div>
            <div class="modal-footer border-0 bg-white p-3 justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-success-subtle p-2 rounded-circle text-success">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.7rem;">Estimated Time</small>
                        <span class="fw-800" id="est_time">15 - 20 Mins</span>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-dark rounded-4 fw-800 px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function openLiveTracking(orderId, agentName) {
    document.getElementById('track_order_id').textContent = orderId;
    document.getElementById('track_agent_name').textContent = agentName;
    
    // Open Modal
    const trackingModal = new bootstrap.Modal(document.getElementById('trackingModal'));
    trackingModal.show();
    
    // Simulate Map Loading
    const mapContainer = document.getElementById('map_container');
    
    // Reset to loading state (optional, or just overwrite)
    
    setTimeout(() => {
        mapContainer.innerHTML = `
        <div class="w-100 h-100 position-relative overflow-hidden" style="background-color: #f0f4f8; position: relative;">
             <!-- Simple CSS Map Grid Background -->
             <div style="background-image: linear-gradient(#e1e4e8 1px, transparent 1px), linear-gradient(90deg, #e1e4e8 1px, transparent 1px); background-size: 40px 40px; width: 100%; height: 100%; opacity: 0.5;"></div>
             
             <!-- Road Path (approximate curve via SVG) -->
             <svg class="position-absolute top-0 start-0 w-100 h-100" style="pointer-events: none;">
                <path d="M 100,100 C 150,100 200,150 250,200 S 400,250 500,300" stroke="#cbd5e1" stroke-width="20" fill="none" stroke-linecap="round" />
                <path d="M 100,100 C 150,100 200,150 250,200 S 400,250 500,300" stroke="#198754" stroke-width="4" fill="none" stroke-dasharray="10 10" class="tracking-path-anim" />
             </svg>

             <!-- Home Marker -->
             <div class="position-absolute" style="bottom: 80px; right: 100px;">
                <div class="d-flex flex-column align-items-center">
                    <div class="badge bg-dark mb-1 shadow-sm">You</div>
                    <i class="bi bi-house-door-fill text-danger display-6" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));"></i>
                </div>
             </div>

             <!-- Agent Marker (Animated) -->
             <div class="position-absolute" style="top: 80px; left: 80px; animation: moveAgent 8s ease-in-out infinite alternate;">
                <div class="d-flex flex-column align-items-center">
                    <div class="bg-white rounded-3 px-2 py-1 shadow-sm mb-1 border border-success small fw-bold text-nowrap text-success">
                        <i class="bi bi-scooter me-1"></i>${agentName}
                    </div>
                    <div class="bg-success text-white rounded-circle p-2 shadow-lg d-flex align-items-center justify-content-center border-2 border-white" style="width: 48px; height: 48px;">
                        <i class="bi bi-person-fill fs-5"></i>
                    </div>
                </div>
             </div>
        </div>
        `;
    }, 1000); // 1 sec delay to simulate connecting
}
</script>

<style>
@keyframes moveAgent {
    0% { transform: translate(0, 0); }
    25% { transform: translate(50px, 0); }
    50% { transform: translate(150px, 100px); }
    75% { transform: translate(300px, 150px); }
    100% { transform: translate(400px, 200px); }
}

.tracking-path-anim {
    stroke-dashoffset: 0;
    animation: dashPath 2s linear infinite;
}

@keyframes dashPath {
    to {
        stroke-dashoffset: -20;
    }
}
</style>
