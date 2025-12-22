/**
 * Booked Wizard Component (Alpine.js)
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
            
            init() {
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
                    }
                } finally {
                    this.loading = false;
                }
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
            
            selectService(service) {
                this.serviceId = service.id;
                this.selectedService = service;
                this.nextStep();
            },
            
            selectLocation(location) {
                this.locationId = location.id;
                this.selectedLocation = location;
                this.fetchEmployees();
                this.nextStep();
            },
            
            selectEmployee(employee) {
                this.employeeId = employee.id;
                this.selectedEmployee = employee;
                this.nextStep();
            },
            
            selectDate(date) {
                this.date = date;
                this.fetchSlots();
            },
            
            selectSlot(slot) {
                this.time = slot.time;
                this.nextStep();
            },
            
            nextStep() {
                if (this.step < this.totalSteps) {
                    this.step++;
                }
            },
            
            prevStep() {
                if (this.step > 1) {
                    this.step--;
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
                        alert('Buchung erfolgreich!');
                        // Redirect or show success message
                    } else {
                        alert('Fehler: ' + result.message);
                    }
                } finally {
                    this.loading = false;
                }
            }
        }));
    };

    if (window.Alpine) {
        initWizard();
    } else {
        document.addEventListener('alpine:init', initWizard);
    }
})();

