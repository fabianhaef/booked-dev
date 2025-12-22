/**
 * Booked Catalog Component (Alpine.js)
 */
(function() {
    const initCatalog = () => {
        if (!window.Alpine) return;

        Alpine.data('bookingCatalog', () => ({
            loading: false,
            services: [],
            
            init() {
                this.fetchServices();
            },
            
            async fetchServices() {
                this.loading = true;
            try {
                const response = await fetch('/actions/booked/booking/get-services', {
                    headers: { 'Accept': 'application/json' }
                });
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
    };

    if (window.Alpine) {
        initCatalog();
    } else {
        document.addEventListener('alpine:init', initCatalog);
    }
})();

