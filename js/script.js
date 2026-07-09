// Smooth scrolling for navigation links
document.addEventListener('DOMContentLoaded', function() {
    // Header scroll effect
    window.addEventListener('scroll', function() {
        const header = document.querySelector('.header');
        if (window.scrollY > 100) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
        
        // Show/hide scroll to top button
        const scrollTop = document.querySelector('.scroll-top');
        if (window.scrollY > 500) {
            scrollTop.classList.add('show');
        } else {
            scrollTop.classList.remove('show');
        }
    });

    // Star rating functionality
    const stars = document.querySelectorAll('.star-rating i');
    let selectedRating = 0;

    stars.forEach(star => {
        star.addEventListener('click', function() {
            selectedRating = parseInt(this.dataset.rating);
            updateStarDisplay(selectedRating);
        });

        star.addEventListener('mouseenter', function() {
            const hoverRating = parseInt(this.dataset.rating);
            updateStarDisplay(hoverRating);
        });
    });

    const starRatingContainer = document.querySelector('.star-rating');
    if (starRatingContainer) {
        starRatingContainer.addEventListener('mouseleave', function() {
            updateStarDisplay(selectedRating);
        });
    }

    function updateStarDisplay(rating) {
        stars.forEach((star, index) => {
            if (index < rating) {
                star.classList.add('active');
            } else {
                star.classList.remove('active');
            }
        });
        const ratingInput = document.getElementById('ratingInput');
        if (ratingInput) {
            ratingInput.value = rating;
        }
    }

    // Password validation for signup form
    const signupPassword = document.getElementById('signupPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    
    if (signupPassword && confirmPassword) {
        // Real-time password match validation
        confirmPassword.addEventListener('input', validatePasswordMatch);
        signupPassword.addEventListener('input', validatePasswordMatch);
    }

    // Sample reviews data
    const sampleReviews = [
        {
            name: "John Doe",
            rating: 5,
            text: "Amazing resort! Perfect for family vacations. The pools are clean and well-maintained."
        },
        {
            name: "Jane Smith",
            rating: 4,
            text: "Great experience overall. Staff were friendly and accommodating. Will definitely come back!"
        },
        {
            name: "Mike Johnson",
            rating: 5,
            text: "Perfect spot for our barkada hangout. The facilities are excellent and the price is reasonable."
        }
    ];

    // Load sample reviews on page load when there are no existing reviews
    loadSampleReviews();

    function loadSampleReviews() {
        const reviewsList = document.getElementById('reviewsList');
        if (reviewsList && reviewsList.children.length === 0) {
            sampleReviews.forEach(review => {
                addReviewToList(review.name, review.rating, review.text);
            });
        }
    }

    function addReviewToList(name, rating, text) {
        const reviewsList = document.getElementById('reviewsList');
        if (!reviewsList) return;

        const reviewItem = document.createElement('div');
        reviewItem.className = 'review-item';
        
        const stars = '★'.repeat(rating) + '☆'.repeat(5 - rating);
        
        reviewItem.innerHTML = `
            <div class="review-stars">${stars}</div>
            <p><strong>${name}</strong></p>
            <p>${text}</p>
        `;
        
        reviewsList.appendChild(reviewItem);
    }

    // Form validation for auth pages
    const authForms = document.querySelectorAll('.auth-form');
    authForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const inputs = this.querySelectorAll('input[required]');
            let isValid = true;

            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = '#ff4444';
                } else {
                    input.style.borderColor = '#e0e0e0';
                }
            });

            if (!isValid) {
                e.preventDefault();
                showNotification('Please fill in all required fields', 'error');
            }
        });
    });

    // Notification system
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            padding: 1rem 2rem;
            background: ${type === 'error' ? '#e74c3c' : '#27ae60'};
            color: white;
            border-radius: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            max-width: 400px;
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
});

// Smooth scroll to booking section
function scrollToBooking() {
    const bookingSection = document.querySelector('.reviews');
    if (bookingSection) {
        bookingSection.scrollIntoView({ behavior: 'smooth' });
    }
}

function openLoginModal() {
    const modal = document.getElementById('loginPopup');
    if (modal) {
        modal.hidden = false;
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
    }
}

function closeLoginModal() {
    const modal = document.getElementById('loginPopup');
    if (modal) {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        modal.hidden = true;
    }
}

document.addEventListener('click', function(event) {
    const modal = document.getElementById('loginPopup');
    if (modal && modal.classList.contains('show') && event.target === modal) {
        closeLoginModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeLoginModal();
    }
});

// Submit review function
function submitReview() {
    const reviewText = document.getElementById('reviewText');
    const selectedRating = document.querySelectorAll('.star-rating i.active').length;
    
    if (!reviewText.value.trim()) {
        showNotification('Please write a review before submitting', 'error');
        return;
    }

    if (selectedRating === 0) {
        showNotification('Please select a star rating', 'error');
        return;
    }

    // Add the review to the list
    addReviewToList('You', selectedRating, reviewText.value);
    
    // Clear the form
    reviewText.value = '';
    document.querySelectorAll('.star-rating i').forEach(star => {
        star.classList.remove('active');
    });

    showNotification('Review submitted successfully!', 'success');
}

// Mobile menu toggle
function toggleMobileMenu() {
    const navLinks = document.querySelector('.nav-links');
    if (navLinks) {
        navLinks.classList.toggle('active');
    }
}

// Scroll to top function
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Parallax effect for hero section
window.addEventListener('scroll', function() {
    const scrolled = window.pageYOffset;
    const hero = document.querySelector('.hero');
    if (hero) {
        hero.style.transform = `translateY(${scrolled * 0.5}px)`;
    }
});

// Form input animations
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input, textarea');
    
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });

        input.addEventListener('blur', function() {
            if (!this.value) {
                this.parentElement.classList.remove('focused');
            }
        });
    });
});

// Loading animation for page transitions
function showLoading() {
    const loader = document.createElement('div');
    loader.className = 'page-loader';
    loader.innerHTML = `
        <div class="loader-spinner"></div>
    `;
    loader.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.9);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    `;

    const spinnerStyle = document.createElement('style');
    spinnerStyle.textContent = `
        .loader-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(spinnerStyle);

    document.body.appendChild(loader);
    return loader;
}

function hideLoading(loader) {
    if (loader) {
        document.body.removeChild(loader);
    }
}

// Image lazy loading
document.addEventListener('DOMContentLoaded', function() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                observer.unobserve(img);
            }
        });
    });

    images.forEach(img => imageObserver.observe(img));
});

// Date picker for booking (placeholder implementation)
function initDatePicker() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        input.min = new Date().toISOString().split('T')[0];
    });
}

// Initialize date picker
initDatePicker();

// Google Maps initialization
function initMap() {
    // Resort location coordinates (Batangas, Philippines area)
    const resortLocation = { 
        lat: 13.7565, 
        lng: 121.0583 
    };
    
    // Create map instance
    const map = new google.maps.Map(document.getElementById('google-map'), {
        zoom: 15,
        center: resortLocation,
        styles: [
            {
                "featureType": "water",
                "elementType": "geometry",
                "stylers": [{"color": "#e9e9e9"}, {"lightness": 17}]
            },
            {
                "featureType": "landscape",
                "elementType": "geometry",
                "stylers": [{"color": "#f5f5f5"}, {"lightness": 20}]
            },
            {
                "featureType": "road.highway",
                "elementType": "geometry.fill",
                "stylers": [{"color": "#ffffff"}, {"lightness": 17}]
            },
            {
                "featureType": "road.highway",
                "elementType": "geometry.stroke",
                "stylers": [{"color": "#ffffff"}, {"lightness": 29}, {"weight": 0.2}]
            },
            {
                "featureType": "road.arterial",
                "elementType": "geometry",
                "stylers": [{"color": "#ffffff"}, {"lightness": 18}]
            },
            {
                "featureType": "road.local",
                "elementType": "geometry",
                "stylers": [{"color": "#ffffff"}, {"lightness": 16}]
            },
            {
                "featureType": "poi",
                "elementType": "geometry",
                "stylers": [{"color": "#f5f5f5"}, {"lightness": 21}]
            },
            {
                "featureType": "poi.park",
                "elementType": "geometry",
                "stylers": [{"color": "#dedede"}, {"lightness": 21}]
            },
            {
                "elementType": "labels.text.stroke",
                "stylers": [{"visibility": "on"}, {"color": "#ffffff"}, {"lightness": 16}]
            },
            {
                "elementType": "labels.text.fill",
                "stylers": [{"saturation": 36}, {"color": "#333333"}, {"lightness": 40}]
            },
            {
                "elementType": "labels.icon",
                "stylers": [{"visibility": "off"}]
            },
            {
                "featureType": "transit",
                "elementType": "geometry",
                "stylers": [{"color": "#f2f2f2"}, {"lightness": 19}]
            },
            {
                "featureType": "administrative",
                "elementType": "geometry.fill",
                "stylers": [{"color": "#fefefe"}, {"lightness": 20}]
            },
            {
                "featureType": "administrative",
                "elementType": "geometry.stroke",
                "stylers": [{"color": "#fefefe"}, {"lightness": 17}, {"weight": 1.2}]
            }
        ]
    });
    
    // Custom marker icon
    const customIcon = {
        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
            <svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#764ba2;stop-opacity:1" />
                    </linearGradient>
                </defs>
                <circle cx="20" cy="20" r="18" fill="url(#grad1)" stroke="white" stroke-width="2"/>
                <path d="M20 10 C15 10, 11 14, 11 19 C11 25, 20 35, 20 35 S29 25, 29 19 C29 14, 25 10, 20 10 Z" fill="white"/>
                <circle cx="20" cy="19" r="5" fill="url(#grad1)"/>
            </svg>
        `),
        scaledSize: new google.maps.Size(40, 40),
        anchor: new google.maps.Point(20, 40)
    };
    
    // Add marker for resort location
    const marker = new google.maps.Marker({
        position: resortLocation,
        map: map,
        title: 'Resort Paradise',
        icon: customIcon,
        animation: google.maps.Animation.DROP
    });
    
    // Info window content
    const infoContent = `
        <div style="padding: 10px; max-width: 250px;">
            <h3 style="margin: 0 0 10px 0; color: #667eea;">Resort Paradise</h3>
            <p style="margin: 5px 0; color: #333;">
                <strong>Address:</strong><br>
                Paradise Beach Road, Batangas, Philippines
            </p>
            <p style="margin: 5px 0; color: #333;">
                <strong>Phone:</strong> +1234567890
            </p>
            <p style="margin: 5px 0; color: #333;">
                <strong>Hours:</strong> 24/7 Open
            </p>
            <div style="margin-top: 10px;">
                <a href="https://www.google.com/maps/dir/?api=1&destination=13.7565,121.0583" 
                   target="_blank" 
                   style="background: linear-gradient(135deg, #667eea, #764ba2); 
                          color: white; 
                          padding: 8px 15px; 
                          text-decoration: none; 
                          border-radius: 20px; 
                          display: inline-block;
                          font-size: 12px;
                          font-weight: 600;">
                    Get Directions
                </a>
            </div>
        </div>
    `;
    
    // Add info window
    const infoWindow = new google.maps.InfoWindow({
        content: infoContent
    });
    
    // Open info window when marker is clicked
    marker.addListener('click', function() {
        infoWindow.open(map, marker);
    });
    
    // Auto-open info window
    setTimeout(() => {
        infoWindow.open(map, marker);
    }, 1000);
    
    // Add nearby points of interest
    const nearbyPlaces = [
        { lat: 13.7565, lng: 121.0583, name: "Main Resort", type: "resort" },
        { lat: 13.7570, lng: 121.0590, name: "Swimming Pool", type: "pool" },
        { lat: 13.7560, lng: 121.0575, name: "Restaurant", type: "restaurant" },
        { lat: 13.7580, lng: 121.0585, name: "Parking Area", type: "parking" }
    ];
    
    nearbyPlaces.forEach(place => {
        const placeIcon = {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                <svg width="30" height="30" viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="15" cy="15" r="12" fill="#764ba2" stroke="white" stroke-width="2"/>
                    <circle cx="15" cy="15" r="4" fill="white"/>
                </svg>
            `),
            scaledSize: new google.maps.Size(30, 30),
            anchor: new google.maps.Point(15, 15)
        };
        
        const placeMarker = new google.maps.Marker({
            position: { lat: place.lat, lng: place.lng },
            map: map,
            title: place.name,
            icon: placeIcon
        });
    });
}

// Fallback function if Google Maps fails to load
window.gm_authFailure = function() {
    document.getElementById('google-map').innerHTML = `
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 400px; background: #f8f9fa; border-radius: 15px; padding: 20px; text-align: center;">
            <i class="fas fa-map-marked-alt" style="font-size: 4rem; color: #667eea; margin-bottom: 1rem;"></i>
            <h3 style="color: #333; margin-bottom: 1rem;">Map temporarily unavailable</h3>
            <p style="color: #666; margin-bottom: 1.5rem;">Please check back later or contact us for directions.</p>
            <a href="https://maps.google.com?q=13.7565,121.0583" target="_blank" 
               style="background: linear-gradient(135deg, #667eea, #764ba2); 
                      color: white; 
                      padding: 10px 20px; 
                      text-decoration: none; 
                      border-radius: 25px; 
                      display: inline-block;">
                Open in Google Maps
            </a>
        </div>
    `;
};

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const navLinks = document.querySelector('.nav-links');
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    
    if (navLinks && mobileToggle) {
        if (!navLinks.contains(event.target) && !mobileToggle.contains(event.target)) {
            navLinks.classList.remove('active');
        }
    }
});

// Password visibility toggle function
function togglePasswordVisibility(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Password confirmation validation
function validatePasswordMatch() {
    const password = document.getElementById('signupPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    
    if (password && confirmPassword) {
        if (password.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
}

// Test function for verification modal
function testVerificationModal() {
    console.log('Testing verification modal with mock data...');
    const mockUserData = {
        id: '123456789',
        name: 'Test User',
        email: 'test@example.com',
        avatar: 'https://picsum.photos/seed/testuser/100/100.jpg',
        provider: 'test'
    };
    showVerificationModal(mockUserData);
}

// Social Login and Verification System
let currentSocialUser = null;
let currentSocialProvider = null;

// Google Sign-In Handler
function handleGoogleSignIn() {
    console.log('Google sign-in initiated');
    showLoading();
    
    // Check if Google API is loaded
    if (typeof gapi === 'undefined') {
        console.error('Google API not loaded');
        hideLoading();
        showNotification('Google services are temporarily unavailable. Please try again later.', 'error');
        return;
    }
    
    try {
        gapi.auth2.getAuthInstance().signIn().then(
            function(googleUser) {
                console.log('Google sign-in successful:', googleUser);
                const profile = googleUser.getBasicProfile();
                const userData = {
                    id: profile.getId(),
                    name: profile.getName(),
                    email: profile.getEmail(),
                    avatar: profile.getImageUrl(),
                    provider: 'google'
                };
                
                console.log('Google user data:', userData);
                processSocialLogin(userData);
            },
            function(error) {
                console.error('Google sign-in error:', error);
                hideLoading();
                if (error.error === 'popup_closed_by_user') {
                    showNotification('Google sign-in was cancelled', 'info');
                } else {
                    showNotification('Google sign-in failed. Please try again.', 'error');
                }
            }
        );
    } catch (error) {
        console.error('Google sign-in exception:', error);
        hideLoading();
        showNotification('Google sign-in failed. Please try again.', 'error');
    }
}

// Facebook Sign-In Handler
function handleFacebookSignIn() {
    console.log('Facebook sign-in initiated');
    showLoading();
    
    // Check if Facebook SDK is loaded
    if (typeof FB === 'undefined') {
        console.error('Facebook SDK not loaded');
        hideLoading();
        showNotification('Facebook services are temporarily unavailable. Please try again later.', 'error');
        return;
    }
    
    try {
        FB.login(function(response) {
            console.log('Facebook login response:', response);
            
            if (response.authResponse) {
                FB.api('/me', {fields: 'name,email,picture'}, function(fbUser) {
                    console.log('Facebook user data:', fbUser);
                    
                    if (fbUser.error) {
                        console.error('Facebook API error:', fbUser.error);
                        hideLoading();
                        showNotification('Failed to get Facebook user data. Please try again.', 'error');
                        return;
                    }
                    
                    const userData = {
                        id: fbUser.id,
                        name: fbUser.name,
                        email: fbUser.email,
                        avatar: fbUser.picture ? fbUser.picture.data.url : null,
                        provider: 'facebook'
                    };
                    
                    processSocialLogin(userData);
                });
            } else {
                hideLoading();
                showNotification('Facebook login cancelled', 'info');
            }
        }, {scope: 'email,public_profile'});
    } catch (error) {
        console.error('Facebook sign-in exception:', error);
        hideLoading();
        showNotification('Facebook sign-in failed. Please try again.', 'error');
    }
}

// Process Social Login Data
function processSocialLogin(userData) {
    currentSocialUser = userData;
    currentSocialProvider = userData.provider;
    
    // Show verification modal
    showVerificationModal(userData);
}

// Show Verification Modal
function showVerificationModal(userData) {
    console.log('Showing verification modal for:', userData);
    
    const modal = document.getElementById('verificationModal');
    const userName = document.getElementById('userName');
    const userEmail = document.getElementById('userEmail');
    const userAvatar = document.getElementById('userAvatar');
    
    if (!modal || !userName || !userEmail || !userAvatar) {
        console.error('Modal elements not found');
        showNotification('Verification modal elements not found', 'error');
        return;
    }
    
    // Update modal content
    userName.textContent = userData.name || 'Unknown User';
    userEmail.textContent = userData.email || 'unknown@example.com';
    
    // Update avatar
    if (userData.avatar && userData.avatar !== '') {
        userAvatar.innerHTML = `<img src="${userData.avatar}" alt="${userData.name}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">`;
    } else {
        userAvatar.innerHTML = '<i class="fas fa-user"></i>';
    }
    
    // Show modal with a slight delay to ensure DOM is ready
    setTimeout(() => {
        modal.classList.add('show');
        hideLoading();
        console.log('Verification modal should now be visible');
    }, 100);
}

// Close Verification Modal
function closeVerificationModal() {
    const modal = document.getElementById('verificationModal');
    modal.classList.remove('show');
    currentSocialUser = null;
    currentSocialProvider = null;
}

// Cancel Verification
function cancelVerification() {
    closeVerificationModal();
    showNotification('Login cancelled', 'info');
}

// Confirm Verification and Complete Login
function confirmVerification() {
    if (!currentSocialUser) {
        showNotification('No user data available', 'error');
        return;
    }
    
    showLoading();
    
    // Send data to server for authentication
    const formData = new FormData();
    formData.append('social_login', 'true');
    formData.append('provider', currentSocialProvider);
    formData.append('social_id', currentSocialUser.id);
    formData.append('name', currentSocialUser.name);
    formData.append('email', currentSocialUser.email);
    formData.append('avatar', currentSocialUser.avatar || '');
    
    fetch('process_social_login.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        closeVerificationModal();
        
        if (data.success) {
            showNotification('Login successful!', 'success');
            
            // Redirect based on user role
            setTimeout(() => {
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else {
                    window.location.href = 'index.php';
                }
            }, 1500);
        } else {
            showNotification(data.message || 'Login failed', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Social login error:', error);
        showNotification('An error occurred during login', 'error');
    });
}

// Show/Hide Loading Overlay
function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('show');
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.remove('show');
    }
}

// Initialize Google Sign-In
function initGoogleSignIn() {
    gapi.load('auth2', function() {
        gapi.auth2.init({
            client_id: 'YOUR_GOOGLE_CLIENT_ID',
            cookiepolicy: 'single_host_origin',
            scope: 'profile email'
        });
    });
}

// Enhanced form validation for login
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const emailInput = this.querySelector('input[name="email_number"]');
            const passwordInput = this.querySelector('input[name="password"]');
            
            // Basic validation
            if (!emailInput.value.trim()) {
                e.preventDefault();
                showNotification('Please enter your email', 'error');
                emailInput.focus();
                return;
            }
            
            if (!passwordInput.value) {
                e.preventDefault();
                showNotification('Please enter your password', 'error');
                passwordInput.focus();
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailInput.value.trim())) {
                e.preventDefault();
                showNotification('Please enter a valid email address', 'error');
                emailInput.focus();
                return;
            }
            
            // Show loading for traditional login
            showLoading();
        });
    }
    
    // Initialize Google Sign-In if available
    if (typeof gapi !== 'undefined') {
        initGoogleSignIn();
    }
    
    // Handle forgot password
    const forgotPasswordLink = document.querySelector('.forgot-password');
    if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener('click', function(e) {
            e.preventDefault();
            showNotification('Password reset feature coming soon!', 'info');
        });
    }
    
    // Handle modal close on backdrop click
    const modal = document.getElementById('verificationModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeVerificationModal();
            }
        });
    }
    
    // Handle escape key for modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('show')) {
            closeVerificationModal();
        }
    });
});

// Social login error handling
window.onGoogleSignInFailure = function(error) {
    hideLoading();
    console.error('Google sign-in failure:', error);
    showNotification('Google sign-in failed. Please try again.', 'error');
};

window.onFacebookSignInFailure = function(error) {
    hideLoading();
    console.error('Facebook sign-in failure:', error);
    showNotification('Facebook sign-in failed. Please try again.', 'error');
};

// Enhanced notification system for social login
function showSocialLoginNotification(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `social-login-notification ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), duration);
}

// Dropdown toggle functions for navigation
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) {
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        // Close profile dropdown if open
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileDropdown) profileDropdown.style.display = 'none';
    }
}

function toggleProfileMenu() {
    const dropdown = document.getElementById('profileDropdown');
    if (dropdown) {
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        // Close notification dropdown if open
        const notificationDropdown = document.getElementById('notificationDropdown');
        if (notificationDropdown) notificationDropdown.style.display = 'none';
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const notificationDropdown = document.getElementById('notificationDropdown');
    const profileDropdown = document.getElementById('profileDropdown');
    const btnNotification = document.querySelector('.btn-notification');
    const profileCircle = document.querySelector('.profile-circle');
    
    if (notificationDropdown && btnNotification && !btnNotification.contains(event.target) && !notificationDropdown.contains(event.target)) {
        notificationDropdown.style.display = 'none';
    }
    
    if (profileDropdown && profileCircle && !profileCircle.contains(event.target) && !profileDropdown.contains(event.target)) {
        profileDropdown.style.display = 'none';
    }
});
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#27ae60' : type === 'error' ? '#e74c3c' : '#667eea'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        z-index: 30000;
        animation: slideInRight 0.3s ease;
        max-width: 350px;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, duration);
}

// Add CSS animations for notifications
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    .social-login-notification .notification-content {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .social-login-notification i {
        font-size: 1.2rem;
    }
`;
document.head.appendChild(notificationStyles);

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
