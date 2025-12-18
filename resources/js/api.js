/**
 * API Client for Bearer Token Authentication
 * Handles token storage in localStorage and API requests
 */

const API_BASE_URL = window.location.origin + '/api';

// Token management
const TokenManager = {
    /**
     * Get token from localStorage
     */
    getToken() {
        return localStorage.getItem('auth_token');
    },

    /**
     * Save token to localStorage
     */
    setToken(token) {
        localStorage.setItem('auth_token', token);
    },

    /**
     * Remove token from localStorage
     */
    removeToken() {
        localStorage.removeItem('auth_token');
    },

    /**
     * Check if user is authenticated
     */
    isAuthenticated() {
        return !!this.getToken();
    }
};

// API Client
const apiClient = {
    /**
     * Make API request with Bearer token
     */
    async request(url, options = {}) {
        const token = TokenManager.getToken();
        
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers,
        };

        // Add Bearer token if available
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        const config = {
            ...options,
            headers,
        };

        try {
            const response = await fetch(`${API_BASE_URL}${url}`, config);
            
            // Handle 401 Unauthorized - token expired or invalid
            if (response.status === 401) {
                TokenManager.removeToken();
                // Redirect to login if not already there
                if (!window.location.pathname.includes('/login')) {
                    window.location.href = '/login';
                }
                throw new Error('Session expired. Please login again.');
            }

            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'An error occurred');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    /**
     * GET request
     */
    async get(url, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const fullUrl = queryString ? `${url}?${queryString}` : url;
        return this.request(fullUrl, { method: 'GET' });
    },

    /**
     * POST request
     */
    async post(url, data = {}) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data),
        });
    },

    /**
     * PUT request
     */
    async put(url, data = {}) {
        return this.request(url, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    },

    /**
     * DELETE request
     */
    async delete(url) {
        return this.request(url, { method: 'DELETE' });
    },

    /**
     * POST request with FormData (for file uploads)
     */
    async postFormData(url, formData) {
        const token = TokenManager.getToken();
        const headers = {};
        
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        const response = await fetch(`${API_BASE_URL}${url}`, {
            method: 'POST',
            headers,
            body: formData,
        });

        if (response.status === 401) {
            TokenManager.removeToken();
            if (!window.location.pathname.includes('/login')) {
                window.location.href = '/login';
            }
            throw new Error('Session expired. Please login again.');
        }

        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'An error occurred');
        }

        return data;
    },
};

// Authentication API
const authAPI = {
    /**
     * Login user
     */
    async login(email, password) {
        const response = await apiClient.post('/login', { email, password });
        
        if (response.token) {
            TokenManager.setToken(response.token);
        }
        
        return response;
    },

    /**
     * Register user
     */
    async register(userData) {
        return await apiClient.post('/register', userData);
    },

    /**
     * Logout user
     */
    async logout() {
        try {
            await apiClient.post('/logout');
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            TokenManager.removeToken();
        }
    },

    /**
     * Get current user
     */
    async me() {
        return await apiClient.get('/me');
    },
};

// Booking API
const bookingAPI = {
    /**
     * Get customer dashboard
     */
    async getDashboard(status = null) {
        const params = status ? { status } : {};
        return await apiClient.get('/customer/dashboard', params);
    },

    /**
     * Get all bookings
     */
    async getAll(status = null) {
        const params = status ? { status } : {};
        return await apiClient.get('/customer/bookings', params);
    },

    /**
     * Get single booking
     */
    async getById(id) {
        return await apiClient.get(`/customer/bookings/${id}`);
    },

    /**
     * Create booking
     */
    async create(bookingData) {
        return await apiClient.post('/customer/bookings', bookingData);
    },

    /**
     * Update booking
     */
    async update(id, bookingData) {
        return await apiClient.put(`/customer/bookings/${id}`, bookingData);
    },

    /**
     * Delete booking
     */
    async delete(id) {
        return await apiClient.delete(`/customer/bookings/${id}`);
    },
};

// Therapist API
const therapistAPI = {
    /**
     * Get therapist dashboard
     */
    async getDashboard(status = null) {
        const params = status ? { status } : {};
        return await apiClient.get('/therapist/dashboard', params);
    },

    /**
     * Get all bookings
     */
    async getBookings(status = null) {
        const params = status ? { status } : {};
        return await apiClient.get('/therapist/bookings', params);
    },

    /**
     * Accept booking
     */
    async acceptBooking(id) {
        return await apiClient.post(`/therapist/bookings/${id}/accept`);
    },

    /**
     * Decline booking
     */
    async declineBooking(id) {
        return await apiClient.post(`/therapist/bookings/${id}/decline`);
    },

    /**
     * Update booking status
     */
    async updateStatus(id, status) {
        return await apiClient.post(`/therapist/bookings/${id}/update-status`, { status });
    },

    /**
     * Toggle availability
     */
    async toggleAvailability() {
        return await apiClient.post('/therapist/toggle-availability');
    },
};

// Staff API
const staffAPI = {
    /**
     * Get staff dashboard
     */
    async getDashboard() {
        return await apiClient.get('/staff/dashboard');
    },

    /**
     * Get all users
     */
    async getUsers() {
        return await apiClient.get('/staff/users');
    },

    /**
     * Get all therapists
     */
    async getTherapists() {
        return await apiClient.get('/staff/therapists');
    },

    /**
     * Get all bookings
     */
    async getBookings(status = null) {
        const params = status ? { status } : {};
        return await apiClient.get('/staff/bookings', params);
    },

    /**
     * Delete user
     */
    async deleteUser(id) {
        return await apiClient.delete(`/staff/users/${id}`);
    },

    /**
     * Delete therapist
     */
    async deleteTherapist(id) {
        return await apiClient.delete(`/staff/therapists/${id}`);
    },

    /**
     * Delete booking
     */
    async deleteBooking(id) {
        return await apiClient.delete(`/staff/bookings/${id}`);
    },
};

// Profile API
const profileAPI = {
    /**
     * Get profile
     */
    async get() {
        return await apiClient.get('/profile');
    },

    /**
     * Update profile
     */
    async update(profileData) {
        return await apiClient.put('/profile', profileData);
    },

    /**
     * Update profile with image
     */
    async updateWithImage(formData) {
        return await apiClient.postFormData('/profile', formData);
    },
};

// Export for use in other scripts
window.TokenManager = TokenManager;
window.apiClient = apiClient;
window.authAPI = authAPI;
window.bookingAPI = bookingAPI;
window.therapistAPI = therapistAPI;
window.staffAPI = staffAPI;
window.profileAPI = profileAPI;

