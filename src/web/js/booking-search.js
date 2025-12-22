/**
 * Booked Search Component (Alpine.js)
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('bookingSearch', () => ({
        loading: false,
        date: new Date().toISOString().split('T')[0],
        results: [],
        
        async search() {
            this.loading = true;
            try {
                const data = await window.BookedAvailability.getSlots(this.date);
                if (data.success) {
                    this.results = data.slots;
                }
            } finally {
                this.loading = false;
            }
        }
    }));
});

