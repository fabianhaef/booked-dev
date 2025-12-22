/**
 * Booked Catalog Component (Alpine.js)
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('bookingCatalog', () => ({
        loading: false,
        services: [],
        
        init() {
            this.fetchServices();
        },
        
        async fetchServices() {
            this.loading = true;
            try {
                const response = await fetch('/actions/booked/booking/get-services');
                const data = await response.json();
                if (data.success) {
                    this.services = data.services;
                }
            } finally {
                this.loading = false;
            }
        },
        
        openWizard(serviceId) {
            // Logic to open wizard with pre-selected service
            window.location.href = `/booking?serviceId=${serviceId}`;
        }
    }));
});

