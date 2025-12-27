/**
 * Booked Wizard Component (Alpine.js)
 *
 * Flow:
 * Step 1: Select Service
 * Step 2: Select Location (skipped if only one)
 * Step 3: Select Employee (skipped if service has no employee-specific schedules)
 * Step 4: Select Date & Time
 * Step 5: Enter Customer Info
 * Step 6: Review & Confirm
 * Step 7: Success
 */
(function() {
    const initWizard = () => {
        if (!window.Alpine) return;

        Alpine.data('bookingWizard', (config) => ({
            step: 1,
            totalSteps: 6,
            loading: false,

            // Form Data
            serviceId: null,
            employeeId: null,
            locationId: null,
            date: null,
            time: null,
            quantity: 1,
            customerName: '',
            customerEmail: '',
            customerPhone: '',
            notes: '',

            // Data lists
            services: [],
            employees: [],
            locations: [],
            availableSlots: [],
            calendar: {},

            // Selected objects
            selectedService: null,
            selectedEmployee: null,
            selectedLocation: null,

            // Flow control
            employeeRequired: false,
            hasSchedules: false,
            skipEmployeeStep: false,

            // Booking result
            reservationDetails: null,

            init() {
                // Initialize with URL parameters if present
                const urlParams = new URLSearchParams(window.location.search);

                if (urlParams.has('serviceId')) {
                    const serviceId = parseInt(urlParams.get('serviceId'));
                    this.serviceId = serviceId;
                    this.step = 2; // Jump to location selection
                }

                if (urlParams.has('locationId')) {
                    this.locationId = parseInt(urlParams.get('locationId'));
                    this.step = 3; // Jump to employee selection
                }

                if (urlParams.has('employeeId')) {
                    const empId = urlParams.get('employeeId');
                    this.employeeId = empId === 'null' ? null : parseInt(empId);
                    this.step = 4; // Jump to date selection
                }

                if (urlParams.has('date')) {
                    this.date = urlParams.get('date');
                    this.fetchSlots();
                    this.step = 4;
                }

                if (urlParams.has('time')) {
                    this.time = urlParams.get('time');
                    this.step = 5; // Jump to info
                }

                this.fetchServices();
                this.fetchLocations();
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

            async fetchEmployees() {
                this.loading = true;
                const params = new URLSearchParams();
                if (this.locationId) params.append('locationId', this.locationId);
                if (this.serviceId) params.append('serviceId', this.serviceId);

                try {
                    const response = await fetch(`/actions/booked/booking/get-employees?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.employees = data.employees;
                        this.employeeRequired = data.employeeRequired;
                        this.hasSchedules = data.hasSchedules;

                        // Determine if we should skip the employee step
                        // Skip if: no employees available OR service has service-level schedules (employeeId = null)
                        this.skipEmployeeStep = !data.employeeRequired && data.hasSchedules;

                        return data;
                    }
                } finally {
                    this.loading = false;
                }
                return null;
            },

            async fetchLocations() {
                this.loading = true;
                try {
                    const response = await fetch('/actions/booked/booking/get-locations', {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.locations = data.locations;

                        // Auto-select if only one location exists
                        if (this.locations.length === 1) {
                            this.locationId = this.locations[0].id;
                            this.selectedLocation = this.locations[0];
                        }
                    }
                } finally {
                    this.loading = false;
                }
            },

            async fetchSlots() {
                if (!this.date) return;
                this.loading = true;
                try {
                    const data = await window.BookedAvailability.getSlots(this.date, {
                        serviceId: this.serviceId,
                        employeeId: this.employeeId,
                        locationId: this.locationId,
                        quantity: this.quantity
                    });
                    if (data.success) {
                        this.availableSlots = data.slots;
                    }
                } finally {
                    this.loading = false;
                }
            },

            async selectService(service) {
                this.serviceId = service.id;
                this.selectedService = service;

                // Determine the next step based on location count
                if (this.locations.length === 1 && this.locationId) {
                    // Skip location step, check employees
                    await this.checkEmployeesAndProceed();
                } else {
                    // Go to location selection
                    this.nextStep();
                }
            },

            async selectLocation(location) {
                this.locationId = location.id;
                this.selectedLocation = location;
                await this.checkEmployeesAndProceed();
            },

            async checkEmployeesAndProceed() {
                // Fetch employees and determine next step
                const employeeData = await this.fetchEmployees();

                if (!employeeData || !employeeData.hasSchedules) {
                    // No schedules configured - show error state (step 3 will show the message)
                    this.step = 3;
                    return;
                }

                if (this.skipEmployeeStep) {
                    // Service-level schedules exist, skip to date selection
                    this.employeeId = null;
                    this.selectedEmployee = null;
                    this.step = 4;
                } else if (this.employees.length === 0) {
                    // No employees for this service - go to step 3 to show message
                    this.step = 3;
                } else if (this.employees.length === 1 && this.employeeRequired) {
                    // Only one employee, auto-select
                    this.employeeId = this.employees[0].id;
                    this.selectedEmployee = this.employees[0];
                    this.step = 4;
                } else {
                    // Multiple employees or optional selection - show employee step
                    this.step = 3;
                }
            },

            selectEmployee(employee) {
                this.employeeId = employee.id;
                this.selectedEmployee = employee;
                this.step = 4; // Go to date selection
            },

            skipEmployee() {
                // User chose "Any available" employee
                this.employeeId = null;
                this.selectedEmployee = null;
                this.step = 4;
            },

            selectDate(date) {
                this.date = date;
                this.fetchSlots();
            },

            selectSlot(slot) {
                this.time = slot.time;
                // If slot has a specific employee, use it
                if (slot.employeeId) {
                    this.employeeId = slot.employeeId;
                }
                this.nextStep();
            },

            nextStep() {
                if (this.step < this.totalSteps) {
                    this.step++;
                }
            },

            prevStep() {
                if (this.step > 1) {
                    // Handle skipped steps when going back
                    if (this.step === 4) {
                        // Going back from date selection
                        if (this.skipEmployeeStep) {
                            // Skip employee step
                            if (this.locations.length === 1) {
                                this.step = 1; // Back to service
                            } else {
                                this.step = 2; // Back to location
                            }
                        } else {
                            this.step = 3; // Back to employee
                        }
                    } else if (this.step === 3 && this.locations.length === 1) {
                        this.step = 1; // Skip location, back to service
                    } else {
                        this.step--;
                    }
                }
            },

            async submitBooking() {
                this.loading = true;
                const data = {
                    serviceId: this.serviceId,
                    employeeId: this.employeeId,
                    locationId: this.locationId,
                    date: this.date,
                    time: this.time,
                    quantity: this.quantity,
                    customerName: this.customerName,
                    customerEmail: this.customerEmail,
                    customerPhone: this.customerPhone,
                    notes: this.notes
                };

                try {
                    const result = await window.BookedAvailability.createBooking(data);
                    if (result.success) {
                        // Move to success step (step 7)
                        this.step = this.totalSteps + 1;
                        this.reservationDetails = result.reservation;

                        // Scroll to top of page
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    } else {
                        alert('Fehler: ' + result.message);
                    }
                } catch (error) {
                    alert('Ein unerwarteter Fehler ist aufgetreten: ' + error.message);
                } finally {
                    this.loading = false;
                }
            },

            resetWizard() {
                // Reset all form data
                this.step = 1;
                this.serviceId = null;
                this.employeeId = null;
                this.locationId = null;
                this.date = null;
                this.time = null;
                this.quantity = 1;
                this.customerName = '';
                this.customerEmail = '';
                this.customerPhone = '';
                this.notes = '';
                this.selectedService = null;
                this.selectedEmployee = null;
                this.selectedLocation = null;
                this.reservationDetails = null;
                this.employeeRequired = false;
                this.hasSchedules = false;
                this.skipEmployeeStep = false;
            }
        }));
    };

    if (window.Alpine) {
        initWizard();
    } else {
        document.addEventListener('alpine:init', initWizard);
    }
})();
