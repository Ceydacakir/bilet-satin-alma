// HopBilet JavaScript Functions

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Seat selection functionality
    initializeSeatSelection();
    
    // Coupon validation
    initializeCouponValidation();
    
    // Date picker restrictions
    initializeDatePicker();
});

// Seat Selection Functions
function initializeSeatSelection() {
    const seats = document.querySelectorAll('.seat');
    const selectedSeats = [];
    
    seats.forEach(seat => {
        seat.addEventListener('click', function() {
            if (this.classList.contains('occupied')) {
                // Dolu koltuk uyarısı göster
                const alert = document.createElement('div');
                alert.className = 'alert alert-danger alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
                alert.style.zIndex = '1080';
                alert.innerHTML = '<i class="fas fa-chair me-2"></i>Bu koltuk dolu!<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                document.body.appendChild(alert);
                setTimeout(() => {
                    try { new bootstrap.Alert(alert).close(); } catch(e) {}
                }, 2000);
                return;
            }
            
            if (this.classList.contains('selected')) {
                // Deselect seat
                this.classList.remove('selected');
                this.classList.add('available');
                const seatNumber = this.dataset.seat;
                const index = selectedSeats.indexOf(seatNumber);
                if (index > -1) {
                    selectedSeats.splice(index, 1);
                }
            } else {
                // Select seat
                this.classList.remove('available');
                this.classList.add('selected');
                selectedSeats.push(this.dataset.seat);
            }
            
            updateSelectedSeats(selectedSeats);
        });
    });
}

function updateSelectedSeats(selectedSeats) {
    const selectedSeatsInput = document.getElementById('selectedSeats');
    if (selectedSeatsInput) {
        selectedSeatsInput.value = selectedSeats.join(',');
    }
    
    const seatCount = selectedSeats.length;
    const seatCountElement = document.getElementById('seatCount');
    if (seatCountElement) {
        seatCountElement.textContent = seatCount;
    }
    
    // Update total price
    updateTotalPrice();
}

// Coupon Validation
function initializeCouponValidation() {
    const couponInput = document.getElementById('couponCode');
    const applyCouponBtn = document.getElementById('applyCoupon');
    
    if (couponInput && applyCouponBtn) {
        applyCouponBtn.addEventListener('click', function() {
            const couponCode = couponInput.value.trim();
            if (couponCode) {
                validateCoupon(couponCode);
            }
        });
        
        couponInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyCouponBtn.click();
            }
        });
    }
}

function validateCoupon(couponCode) {
    const applyCouponBtn = document.getElementById('applyCoupon');
    const couponMessage = document.getElementById('couponMessage');
    
    // Show loading state
    applyCouponBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Kontrol Ediliyor...';
    applyCouponBtn.disabled = true;
    
    // Simulate API call (replace with actual AJAX call)
    fetch('ajax/validate_coupon.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ code: couponCode })
    })
    .then(response => response.json())
    .then(data => {
        if (data.valid) {
            showCouponMessage('Kupon başarıyla uygulandı!', 'success');
            updateTotalPrice(data.discount);
        } else {
            showCouponMessage(data.message, 'error');
        }
    })
    .catch(error => {
        showCouponMessage('Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
    })
    .finally(() => {
        applyCouponBtn.innerHTML = 'Kupon Uygula';
        applyCouponBtn.disabled = false;
    });
}

function showCouponMessage(message, type) {
    const couponMessage = document.getElementById('couponMessage');
    if (couponMessage) {
        couponMessage.innerHTML = `
            <div class="alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    }
}

// Price Calculation
function updateTotalPrice(discount = 0) {
    const basePrice = parseFloat(document.getElementById('basePrice')?.value || 0);
    const seatCount = document.querySelectorAll('.seat.selected').length;
    const totalPrice = basePrice * seatCount;
    const discountedPrice = totalPrice - (totalPrice * discount / 100);
    
    const totalPriceElement = document.getElementById('totalPrice');
    if (totalPriceElement) {
        totalPriceElement.textContent = formatPrice(discountedPrice);
    }
}

function formatPrice(price) {
    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: 'TRY'
    }).format(price);
}

// Date Picker
function initializeDatePicker() {
    const dateInput = document.getElementById('departure_date');
    if (dateInput) {
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        dateInput.min = today;
        
        // Set default date to today
        if (!dateInput.value) {
            dateInput.value = today;
        }
    }
}

// Search Form Enhancement
function enhanceSearchForm() {
    const departureSelect = document.getElementById('departure_city');
    const destinationSelect = document.getElementById('destination_city');
    
    if (departureSelect && destinationSelect) {
        // Disable same city selection
        function updateDestinationOptions() {
            const selectedDeparture = departureSelect.value;
            Array.from(destinationSelect.options).forEach(option => {
                if (option.value === selectedDeparture) {
                    option.disabled = true;
                } else {
                    option.disabled = false;
                }
            });
        }
        
        departureSelect.addEventListener('change', updateDestinationOptions);
        updateDestinationOptions();
    }
}

// Modal Functions
function showModal(modalId) {
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
}

function hideModal(modalId) {
    const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
    if (modal) {
        modal.hide();
    }
}

// Confirmation Dialog
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Loading States
function showLoading(element) {
    const originalContent = element.innerHTML;
    element.dataset.originalContent = originalContent;
    element.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Yükleniyor...';
    element.disabled = true;
}

function hideLoading(element) {
    if (element.dataset.originalContent) {
        element.innerHTML = element.dataset.originalContent;
        element.disabled = false;
        delete element.dataset.originalContent;
    }
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Initialize search form enhancement
document.addEventListener('DOMContentLoaded', function() {
    enhanceSearchForm();
});
