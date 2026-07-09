<?php
require_once __DIR__ . '/../includes/header.php';

// Redirect if not logged in
if (!$user->isLoggedIn()) {
    header('Location: ' . SITE_URL . 'google-auth.php?action=login');
    exit();
}

$pageTitle = 'My Profile';
?>

<style>
.profile-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 2rem;
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 2rem;
    min-height: 600px;
}

.profile-sidebar {
    background: #f8f9fa;
    border-radius: 16px;
    padding: 2rem;
    height: fit-content;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.profile-picture-container {
    text-align: center;
    margin-bottom: 2rem;
}

.profile-picture {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: #e9ecef;
    border: 4px solid #FF7A3D;
    margin: 0 auto 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.profile-picture img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-picture-placeholder {
    font-size: 3rem;
    color: #6c757d;
}

.edit-profile-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #FF7A3D;
    text-decoration: none;
    font-weight: 600;
    font-size: 1rem;
    transition: color 0.2s ease;
}

.edit-profile-link:hover {
    color: #FF6B1F;
}

.profile-form {
    margin-top: 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #495057;
    font-size: 0.95rem;
}

.form-group input {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 1rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    background: white;
}

.form-group input:focus {
    outline: none;
    border-color: #FF7A3D;
    box-shadow: 0 0 0 3px rgba(255, 122, 61, 0.1);
}

.profile-content {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.profile-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid #f1f3f4;
}

.tab-button {
    padding: 1rem 2rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    color: #6c757d;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-bottom: -2px;
}

.tab-button.active {
    color: #FF7A3D;
    border-bottom-color: #FF7A3D;
}

.tab-button:hover {
    color: #FF7A3D;
}

.tab-content {
    min-height: 400px;
}

.booking-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid #e9ecef;
}

.booking-section h3 {
    margin: 0 0 1rem 0;
    color: #495057;
    font-size: 1.1rem;
    font-weight: 600;
}

.booking-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 120px;
    background: white;
    border-radius: 8px;
    color: #6c757d;
    font-style: italic;
    border: 2px dashed #dee2e6;
}

@media (max-width: 768px) {
    .profile-container {
        grid-template-columns: 1fr;
        padding: 1rem;
    }
    
    .profile-sidebar {
        order: 2;
    }
    
    .profile-content {
        order: 1;
    }
    
    .profile-tabs {
        flex-direction: column;
    }
    
    .tab-button {
        text-align: left;
        border-bottom: 1px solid #e9ecef;
        border-left: 3px solid transparent;
    }
    
    .tab-button.active {
        border-bottom-color: #e9ecef;
        border-left-color: #FF7A3D;
    }
}
</style>

<div class="profile-container">
    <!-- Profile Sidebar -->
    <div class="profile-sidebar">
        <div class="profile-picture-container">
            <div class="profile-picture">
                <?php if (!empty($_SESSION['user_avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($_SESSION['user_avatar']); ?>" alt="Profile Picture">
                <?php else: ?>
                    <div class="profile-picture-placeholder">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
            </div>
            <a href="#" class="edit-profile-link" onclick="enableEditProfile(); return false;">
                <i class="fas fa-edit"></i>
                Edit Profile
            </a>
        </div>
        
        <form class="profile-form" id="profileForm" method="POST" action="<?php echo SITE_URL; ?>controllers/AuthController.php">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="phone">PH no</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>" readonly>
            </div>
        </form>
    </div>
    
    <!-- Profile Content -->
    <div class="profile-content">
        <div class="profile-tabs">
            <button class="tab-button active" onclick="switchTab('past', this)">Past</button>
            <button class="tab-button" onclick="switchTab('cancelled', this)">Cancelled</button>
        </div>
        
        <div class="tab-content">
            <!-- Past Bookings Tab -->
            <div id="past-tab" class="tab-pane">
                <div class="booking-section">
                    <h3>Past Bookings</h3>
                    <div class="booking-placeholder">
                        No past bookings found
                    </div>
                </div>
                
                <div class="booking-section">
                    <h3>Recent Activity</h3>
                    <div class="booking-placeholder">
                        No recent activity
                    </div>
                </div>
            </div>
            
            <!-- Cancelled Bookings Tab -->
            <div id="cancelled-tab" class="tab-pane" style="display: none;">
                <div class="booking-section">
                    <h3>Cancelled Bookings</h3>
                    <div class="booking-placeholder">
                        No cancelled bookings found
                    </div>
                </div>
                
                <div class="booking-section">
                    <h3>Cancellation History</h3>
                    <div class="booking-placeholder">
                        No cancellation history
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tabName, buttonElement) {
    // Remove active class from all tabs and buttons
    const allTabs = document.querySelectorAll('.tab-pane');
    const allButtons = document.querySelectorAll('.tab-button');
    
    allTabs.forEach(tab => tab.style.display = 'none');
    allButtons.forEach(btn => btn.classList.remove('active'));
    
    // Show selected tab and activate button
    document.getElementById(tabName + '-tab').style.display = 'block';
    buttonElement.classList.add('active');
}

function enableEditProfile() {
    const form = document.getElementById('profileForm');
    const inputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"]');
    
    inputs.forEach(input => {
        input.removeAttribute('readonly');
        input.style.background = '#fff';
        input.style.borderColor = '#FF7A3D';
    });
    
    // Add save button
    const saveButton = document.createElement('button');
    saveButton.type = 'submit';
    saveButton.className = 'btn-primary';
    saveButton.style.cssText = 'width: 100%; padding: 0.75rem; background: #FF7A3D; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 1rem;';
    saveButton.textContent = 'Save Changes';
    saveButton.onclick = function() {
        form.submit();
    };
    
    // Remove existing save button if any
    const existingSaveButton = form.querySelector('button[type="submit"]');
    if (existingSaveButton) {
        existingSaveButton.remove();
    }
    
    form.appendChild(saveButton);
    
    // Focus on first input
    inputs[0].focus();
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // You can add any initialization code here
});
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
