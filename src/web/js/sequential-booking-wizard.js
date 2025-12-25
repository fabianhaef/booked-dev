/**
 * Sequential Booking Wizard Component (Alpine.js)
 * Handles multi-service sequential booking flow
 */
(function() {
    const initSequentialWizard = () => {
        if (!window.Alpine) return;

        Alpine.data('sequentialBookingWizard', (config) => ({
            step: 1,
            totalSteps: 6,
            loading: false,
            errorMessage: null,

            // Form Data
            selectedServices: [], // Array of selected service objects
            employeeId: null,
            locationId: null,
            date: null,
            time: null,
            customerName: '',
            customerEmail: '',
            customerPhone: '',
            notes: '',
            website: '', // Honeypot field

            // Data lists
            services: [],
            employees: [],
            locations: [],
            availableSlots: [],

            // Selected objects
            selectedEmployee: null,
            selectedLocation: null,

            // Drag & Drop state
            draggedIndex: null,

            // Success data
            sequenceDetails: null,
            reservationDetails: null,

            /**
             * Initialize component
             */
            init() {
                this.fetchServices();
                this.fetchEmployees();
                this.fetchLocations();

                // Set minimum date to tomorrow
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                this.minDate = tomorrow.toISOString().split('T')[0];
            },

            /**
             * Fetch available services
             */
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
                } catch (error) {
                    console.error('Error fetching services:', error);
                } finally {
                    this.loading = false;
                }
            },

            /**
             * Fetch available employees
             */
            async fetchEmployees() {
                this.loading = true;
                try {
                    const response = await fetch('/actions/booked/booking/get-employees', {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.employees = data.employees;
                    }
                } catch (error) {
                    console.error('Error fetching employees:', error);
                } finally {
                    this.loading = false;
                }
            },

            /**
             * Fetch available locations
             */
            async fetchLocations() {
                this.loading = true;
                try {
                    const response = await fetch('/actions/booked/booking/get-locations', {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.locations = data.locations;
                    }
                } catch (error) {
                    console.error('Error fetching locations:', error);
                } finally {
                    this.loading = false;
                }
            },

            /**
             * Load available time slots for the sequential booking
             */
            async loadSequentialSlots() {
                if (!this.date || this.selectedServices.length === 0) return;

                this.loading = true;
                this.errorMessage = null;

                try {
                    const serviceIds = this.selectedServices.map(s => s.id);

                    const response = await fetch('/actions/booked/booking/get-sequential-slots', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            [window.csrfTokenName]: window.csrfTokenValue
                        },
                        body: JSON.stringify({
                            serviceIds: serviceIds,
                            date: this.date,
                            employeeId: this.employeeId,
                            locationId: this.locationId
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.availableSlots = data.slots;
                    } else {
                        this.errorMessage = data.message || 'Failed to load available slots';
                        this.availableSlots = [];
                    }
                } catch (error) {
                    console.error('Error loading sequential slots:', error);
                    this.errorMessage = 'An error occurred while loading available time slots';
                    this.availableSlots = [];
                } finally {
                    this.loading = false;
                }
            },

            /**
             * Toggle service selection
             */
            toggleService(service) {
                const index = this.selectedServices.findIndex(s => s.id === service.id);
                if (index > -1) {
                    this.selectedServices.splice(index, 1);
                } else {
                    this.selectedServices.push({...service});
                }
            },

            /**
             * Check if service is selected
             */
            isServiceSelected(serviceId) {
                return this.selectedServices.some(s => s.id === serviceId);
            },

            /**
             * Remove service from sequence by index
             */
            removeService(index) {
                this.selectedServices.splice(index, 1);
            },

            /**
             * Calculate total duration of all services including buffers
             */
            calculateTotalDuration() {
                return this.selectedServices.reduce((total, service, index) => {
                    let duration = total + parseInt(service.duration);
                    // Add buffer time for all services except the last one
                    if (index < this.selectedServices.length - 1 && service.bufferAfter) {
                        duration += parseInt(service.bufferAfter);
                    }
                    return duration;
                }, 0);
            },

            /**
             * Calculate total price of all services
             */
            calculateTotalPrice() {
                return this.selectedServices.reduce((total, service) => {
                    return total + parseFloat(service.price || 0);
                }, 0).toFixed(2);
            },

            /**
             * Calculate end time based on start time and total duration
             */
            calculateEndTime() {
                if (!this.time) return 'N/A';

                const [hours, minutes] = this.time.split(':').map(Number);
                const totalMinutes = this.calculateTotalDuration();

                const endDate = new Date();
                endDate.setHours(hours, minutes, 0);
                endDate.setMinutes(endDate.getMinutes() + totalMinutes);

                return endDate.toTimeString().substring(0, 5);
            },

            /**
             * Select employee
             */
            selectEmployee(employee) {
                this.employeeId = employee.id;
                this.selectedEmployee = employee;
            },

            /**
             * Select location
             */
            selectLocation(location) {
                this.locationId = location.id;
                this.selectedLocation = location;
            },

            /**
             * Select time slot
             */
            selectSlot(slot) {
                this.time = slot.time;
                this.nextStep();
            },

            /**
             * Drag and drop handlers
             */
            dragStart(index) {
                this.draggedIndex = index;
            },

            drop(dropIndex) {
                if (this.draggedIndex === null) return;

                const draggedItem = this.selectedServices[this.draggedIndex];
                this.selectedServices.splice(this.draggedIndex, 1);
                this.selectedServices.splice(dropIndex, 0, draggedItem);

                this.draggedIndex = null;
            },

            /**
             * Navigation: Next step
             */
            nextStep() {
                if (this.step < this.totalSteps) {
                    this.step++;

                    // Load slots when arriving at date/time step
                    if (this.step === 4 && this.date) {
                        this.loadSequentialSlots();
                    }
                }
            },

            /**
             * Navigation: Previous step
             */
            prevStep() {
                if (this.step > 1) {
                    this.step--;
                }
            },

            /**
             * Submit the sequential booking
             */
            async submitBooking() {
                this.loading = true;
                this.errorMessage = null;

                const serviceIds = this.selectedServices.map(s => s.id);

                const bookingData = {
                    serviceIds: serviceIds,
                    date: this.date,
                    startTime: this.time,
                    customerName: this.customerName,
                    customerEmail: this.customerEmail,
                    customerPhone: this.customerPhone,
                    employeeId: this.employeeId,
                    locationId: this.locationId,
                    notes: this.notes,
                    website: this.website // Honeypot
                };

                try {
                    const response = await fetch('/actions/booked/booking/create-sequential-booking', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            [window.csrfTokenName]: window.csrfTokenValue
                        },
                        body: JSON.stringify(bookingData)
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.sequenceDetails = result.sequence;
                        this.step = this.totalSteps + 1; // Move to success step
                    } else {
                        this.errorMessage = result.message || 'Failed to create booking. Please try again.';
                    }
                } catch (error) {
                    console.error('Error submitting booking:', error);
                    this.errorMessage = 'An error occurred while creating your booking. Please try again.';
                } finally {
                    this.loading = false;
                }
            },

            /**
             * Reset wizard to start over
             */
            resetWizard() {
                this.step = 1;
                this.selectedServices = [];
                this.employeeId = null;
                this.locationId = null;
                this.date = null;
                this.time = null;
                this.customerName = '';
                this.customerEmail = '';
                this.customerPhone = '';
                this.notes = '';
                this.website = '';
                this.selectedEmployee = null;
                this.selectedLocation = null;
                this.availableSlots = [];
                this.sequenceDetails = null;
                this.errorMessage = null;
            }
        }));
    };

    // Initialize when Alpine is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSequentialWizard);
    } else {
        initSequentialWizard();
    }

    // Also try to initialize immediately if Alpine is already loaded
    if (window.Alpine) {
        initSequentialWizard();
    }
})();
