/**
 * Booked Availability Handler
 * 
 * Handles fetching availability calendar and time slots via AJAX.
 */
window.BookedAvailability = {
    /**
     * Fetch availability calendar for a date range
     */
    async getCalendar(startDate, endDate, options = {}) {
        const params = new URLSearchParams({
            startDate,
            endDate,
            ...options
        });

        try {
            const response = await fetch(`/actions/booked/booking/get-availability-calendar?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            return await response.json();
        } catch (error) {
            console.error('Booked: Failed to fetch availability calendar', error);
            return { success: false, calendar: {} };
        }
    },

    /**
     * Fetch available time slots for a specific date
     */
    async getSlots(date, options = {}) {
        const body = {
            date,
            ...options,
            [window.csrfTokenName]: window.csrfTokenValue
        };

        try {
            const response = await fetch('/actions/booked/booking/get-available-slots', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(body)
            });
            return await response.json();
        } catch (error) {
            console.error('Booked: Failed to fetch time slots', error);
            return { success: false, slots: [] };
        }
    },

    /**
     * Create a new booking
     */
    async createBooking(data) {
        const body = {
            ...data,
            [window.csrfTokenName]: window.csrfTokenValue
        };

        try {
            const response = await fetch('/actions/booked/booking/create-booking', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(body)
            });
            return await response.json();
        } catch (error) {
            console.error('Booked: Failed to create booking', error);
            return { success: false, message: 'Server error' };
        }
    }
};

