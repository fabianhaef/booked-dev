/**
 * Booked Search Component (Alpine.js)
 */
(function() {
    const initSearch = () => {
        if (!window.Alpine) return;

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
    };

    if (window.Alpine) {
        initSearch();
    } else {
        document.addEventListener('alpine:init', initSearch);
    }
})();

